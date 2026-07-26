"""Unit tests for AI CSKH feature handlers (features.py) and the custom-quote
multi-turn service (custom_quote_service.py).

Mocking style follows tests/test_action_agent.py: a fake conn/cursor pair
that dispatches by SQL substring, plus a fake vector store for
_resolve_product_ids.
"""

from decimal import Decimal

from app.engines.multiagent.features import (
    coupon_inquiry_node,
    review_lookup_node,
    product_compare_node,
    favorite_add_node,
    favorite_view_node,
    dietary_inquiry_node,
)
from app.services.custom_quote_service import advance_quote


# --- fake store (vector search) ---------------------------------------------

class _Doc:
    def __init__(self, id, metadata=None):
        self.id = id
        self.metadata = metadata or {}


class _FakeStore:
    """Returns a fixed list of docs regardless of query text."""

    def __init__(self, docs):
        self._docs = docs

    def query(self, collection, text, top_k=5):
        return self._docs[:top_k]


# --- fake conn/cursor (MySQL-ish) --------------------------------------------

class _FakeCursor:
    """Dispatches fetchone/fetchall results by SQL substring."""

    def __init__(self, fake_conn):
        self._conn = fake_conn
        self._mode = None

    def __enter__(self):
        return self

    def __exit__(self, *a):
        return False

    def execute(self, sql, params=None):
        self._conn.executed.append((sql, params))
        if "cart_coupons" in sql:
            self._mode = "coupons"
        elif "AVG(rating)" in sql:
            self._mode = "review_summary"
        elif "FROM product_reviews" in sql:
            self._mode = "review_samples"
        elif "SELECT 1 FROM favorites" in sql:
            self._mode = "has_favorite"
        elif "INSERT INTO favorites" in sql:
            self._mode = "insert_favorite"
        elif "FROM favorites" in sql:
            self._mode = "list_favorites"
        elif "WHERE id IN" in sql:
            self._mode = "products_by_ids"
        elif "INSERT INTO contact_requests" in sql:
            self._mode = "insert_contact_request"
        elif "FROM banh WHERE" in sql:
            self._mode = "dietary_filter"
        else:
            self._mode = "unknown"

    def fetchone(self):
        data = self._conn.data.get(self._mode)
        if self._mode == "has_favorite":
            # data is the set of (user_id, banh_id) pairs already favorited
            return {"1": 1} if self._conn.already_favorited else None
        if isinstance(data, list):
            return data[0] if data else None
        return data

    def fetchall(self):
        data = self._conn.data.get(self._mode)
        return list(data) if data else []

    @property
    def lastrowid(self):
        return 999


class _FakeConn:
    def __init__(self, data=None, already_favorited=False):
        # data: dict keyed by mode -> rows/row
        self.data = data or {}
        self.already_favorited = already_favorited
        self.closed = False
        self.committed = False
        self.executed = []

    def cursor(self):
        return _FakeCursor(self)

    def commit(self):
        self.committed = True

    def close(self):
        self.closed = True


class _Deps:
    def __init__(self, conn_factory, store=None):
        self.conn_factory = conn_factory
        self.store = store


def _state(query, intent="", user_id=None, normalized_query=None):
    ctx = {"user_id": user_id} if user_id is not None else {}
    st = {"query": query, "normalized_query": normalized_query or query,
          "intent": intent, "context": ctx}
    return st


def _product_row(id, ten_banh="Bánh kem dâu", loai="kem", gia=350000,
                  mo_ta="Bánh 20cm cho 4-6 người", hinh_anh="a.jpg", slug="banh-kem-dau"):
    return {"id": id, "ten_banh": ten_banh, "loai": loai, "gia": gia,
            "mo_ta": mo_ta, "hinh_anh": hinh_anh, "slug": slug}


# ── coupon_inquiry ────────────────────────────────────────────────────────

def test_coupon_inquiry_with_coupons():
    conn = _FakeConn(data={"coupons": [
        {"code": "GAU10", "discount_percent": 10, "min_subtotal": 200000, "ends_at": "2026-12-31"},
    ]})
    deps = _Deps(conn_factory=lambda: conn)
    out = coupon_inquiry_node(deps, _state("có mã giảm giá không"))
    assert "GAU10" in out["response"]
    assert "10%" in out["response"]
    assert "200.000 VNĐ" in out["response"]
    assert conn.closed is True


def test_coupon_inquiry_empty():
    conn = _FakeConn(data={"coupons": []})
    deps = _Deps(conn_factory=lambda: conn)
    out = coupon_inquiry_node(deps, _state("có mã giảm giá không"))
    assert "chưa có mã giảm giá công khai" in out["response"]


def test_coupon_inquiry_no_conn():
    deps = _Deps(conn_factory=lambda: None)
    out = coupon_inquiry_node(deps, _state("có mã giảm giá không"))
    assert "Không kết nối được" in out["response"]


# ── review_lookup ─────────────────────────────────────────────────────────

def test_review_lookup_with_reviews():
    store = _FakeStore([_Doc("product-57")])
    row = _product_row(57, ten_banh="Bánh Tiramisu")
    conn = _FakeConn(data={
        "products_by_ids": [row],
        "review_summary": {"avg_rating": 4.5, "n": 2},
        "review_samples": [{"name": "An", "rating": 5, "content": "Ngon lắm"}],
    })
    deps = _Deps(conn_factory=lambda: conn, store=store)
    out = review_lookup_node(deps, _state("đánh giá bánh tiramisu thế nào"))
    assert "4.5" in out["response"]
    assert "Ngon lắm" in out["response"]
    assert "⭐" in out["response"]
    assert len(out["products"]) == 1
    assert out["products"][0]["id"] == 57


def test_review_lookup_no_reviews():
    store = _FakeStore([_Doc("product-57")])
    row = _product_row(57, ten_banh="Bánh Tiramisu")
    conn = _FakeConn(data={
        "products_by_ids": [row],
        "review_summary": {"avg_rating": None, "n": 0},
    })
    deps = _Deps(conn_factory=lambda: conn, store=store)
    out = review_lookup_node(deps, _state("đánh giá bánh tiramisu thế nào"))
    assert "chưa có đánh giá" in out["response"]
    assert out["products"][0]["id"] == 57


def test_review_lookup_no_product_resolved():
    store = _FakeStore([])
    deps = _Deps(conn_factory=lambda: None, store=store)
    out = review_lookup_node(deps, _state("đánh giá thế nào"))
    assert "tên bánh" in out["response"]
    assert "products" not in out


# ── product_compare ───────────────────────────────────────────────────────

def test_product_compare_two_products():
    store = _FakeStore([_Doc("product-1"), _Doc("product-2")])
    row1 = _product_row(1, ten_banh="Bánh Chocolate", gia=300000)
    row2 = _product_row(2, ten_banh="Bánh Dâu", gia=350000)
    conn = _FakeConn(data={"products_by_ids": [row1, row2]})
    deps = _Deps(conn_factory=lambda: conn, store=store)
    out = product_compare_node(deps, _state("so sánh bánh chocolate và bánh dâu"))
    assert "Bánh Chocolate" in out["response"]
    assert "Bánh Dâu" in out["response"]
    assert "300.000 VNĐ" in out["response"]
    assert "350.000 VNĐ" in out["response"]
    assert len(out["products"]) == 2


def test_product_compare_only_one_resolved():
    store = _FakeStore([_Doc("product-1")])
    row1 = _product_row(1, ten_banh="Bánh Chocolate")
    conn = _FakeConn(data={"products_by_ids": [row1]})
    deps = _Deps(conn_factory=lambda: conn, store=store)
    out = product_compare_node(deps, _state("so sánh bánh chocolate với gì"))
    assert "so sánh" in out["response"].lower()
    assert "bánh nào" in out["response"]
    assert "products" not in out


# ── favorite_add ──────────────────────────────────────────────────────────

def test_favorite_add_no_user_id():
    deps = _Deps(conn_factory=lambda: None)
    out = favorite_add_node(deps, _state("lưu bánh kem dâu"))
    assert "đăng nhập" in out["response"]


def test_favorite_add_new_product():
    store = _FakeStore([_Doc("product-5")])
    row = _product_row(5, ten_banh="Bánh Socola")
    conn = _FakeConn(data={"products_by_ids": [row]}, already_favorited=False)
    deps = _Deps(conn_factory=lambda: conn, store=store)
    out = favorite_add_node(deps, _state("lưu bánh socola", user_id=1))
    assert "Đã thêm" in out["response"]
    assert "Bánh Socola" in out["response"]
    assert out["products"][0]["id"] == 5


def test_favorite_add_already_favorited():
    store = _FakeStore([_Doc("product-5")])
    row = _product_row(5, ten_banh="Bánh Socola")
    conn = _FakeConn(data={"products_by_ids": [row]}, already_favorited=True)
    deps = _Deps(conn_factory=lambda: conn, store=store)
    out = favorite_add_node(deps, _state("lưu bánh socola", user_id=1))
    assert "đã có trong danh sách" in out["response"]


# ── favorite_view ─────────────────────────────────────────────────────────

def test_favorite_view_no_user_id():
    deps = _Deps(conn_factory=lambda: None)
    out = favorite_view_node(deps, _state("xem bánh yêu thích"))
    assert "đăng nhập" in out["response"]


def test_favorite_view_empty():
    conn = _FakeConn(data={"list_favorites": []})
    deps = _Deps(conn_factory=lambda: conn)
    out = favorite_view_node(deps, _state("xem bánh yêu thích", user_id=1))
    assert "chưa lưu" in out["response"]


def test_favorite_view_with_rows():
    row = _product_row(9, ten_banh="Bánh Trà Xanh")
    conn = _FakeConn(data={"list_favorites": [row]})
    deps = _Deps(conn_factory=lambda: conn)
    out = favorite_view_node(deps, _state("xem bánh yêu thích", user_id=1))
    assert len(out["products"]) == 1
    assert out["products"][0]["id"] == 9


# ── dietary_inquiry ───────────────────────────────────────────────────────

def test_dietary_inquiry_no_egg():
    row = _product_row(3, ten_banh="Bánh Rau Câu")
    conn = _FakeConn(data={"dietary_filter": [row]})
    deps = _Deps(conn_factory=lambda: conn)
    out = dietary_inquiry_node(deps, _state("bánh nào không trứng"))
    assert "trứng" in out["response"]
    assert "Lưu ý" in out["response"]
    assert len(out["products"]) == 1


def test_dietary_inquiry_vegan_excludes_egg_and_milk():
    conn = _FakeConn(data={"dietary_filter": []})
    deps = _Deps(conn_factory=lambda: conn)
    out = dietary_inquiry_node(deps, _state("có bánh thuần chay không"))
    assert "trứng" in out["response"]
    assert "sữa" in out["response"]


def test_dietary_inquiry_no_recognized_allergen():
    deps = _Deps(conn_factory=lambda: None)
    out = dietary_inquiry_node(deps, _state("bánh này ăn có ngon không"))
    assert "thành phần nào" in out["response"]


def test_dietary_inquiry_empty_result():
    conn = _FakeConn(data={"dietary_filter": []})
    deps = _Deps(conn_factory=lambda: conn)
    out = dietary_inquiry_node(deps, _state("bánh nào không trứng"))
    assert "chưa có bánh nào" in out["response"]
    assert "Lưu ý" in out["response"]


# ── custom_quote advance_quote ────────────────────────────────────────────

def _session(draft=None):
    return {"metadata": {"custom_quote": draft} if draft else {}}


def test_advance_quote_full_flow():
    deps = _Deps(conn_factory=lambda: None)
    session = _session()

    resp, created, draft = advance_quote(deps, session, "sinh nhật")
    assert draft["step"] == "servings"
    assert created is False
    assert "bao nhiêu người" in resp

    session["metadata"]["custom_quote"] = draft
    resp, created, draft = advance_quote(deps, session, "20 người")
    assert draft["step"] == "flavor"
    assert "vị bánh" in resp

    session["metadata"]["custom_quote"] = draft
    resp, created, draft = advance_quote(deps, session, "chocolate")
    assert draft["step"] == "date"
    assert "ngày nào" in resp

    session["metadata"]["custom_quote"] = draft
    resp, created, draft = advance_quote(deps, session, "20/12")
    assert draft["step"] == "note"
    assert "ghi chú" in resp.lower() or "trang trí" in resp

    session["metadata"]["custom_quote"] = draft
    resp, created, draft = advance_quote(deps, session, "không")
    assert draft["step"] == "name"
    assert draft["note"] == ""
    assert "tên" in resp

    session["metadata"]["custom_quote"] = draft
    resp, created, draft = advance_quote(deps, session, "Nguyễn Văn A")
    assert draft["step"] == "phone"
    assert "điện thoại" in resp

    # invalid phone re-asks
    session["metadata"]["custom_quote"] = draft
    resp, created, draft = advance_quote(deps, session, "abc")
    assert draft["step"] == "phone"
    assert created is False
    assert "chưa hợp lệ" in resp

    session["metadata"]["custom_quote"] = draft
    resp, created, draft = advance_quote(deps, session, "0901234567")
    assert draft["step"] == "confirm"
    assert draft["phone"] == "0901234567"
    assert "Xác nhận" in resp
    assert created is False

    # final confirm
    conn = _FakeConn()
    deps2 = _Deps(conn_factory=lambda: conn)
    session["metadata"]["custom_quote"] = draft
    resp, created, new_draft = advance_quote(deps2, session, "đồng ý")
    assert created is True
    assert new_draft == {}
    assert "Đã ghi nhận" in resp
    assert "0901234567" in resp
    assert conn.committed is True
    assert any("INSERT INTO contact_requests" in sql for sql, _ in conn.executed)


def test_advance_quote_note_skip_stores_empty():
    deps = _Deps(conn_factory=lambda: None)
    draft = {"step": "note", "occasion": "sinh nhật", "servings": "10", "flavor": "vani",
             "date": "mai"}
    session = _session(draft)
    resp, created, new_draft = advance_quote(deps, session, "không")
    assert new_draft["note"] == ""
    assert new_draft["step"] == "name"


def test_advance_quote_confirm_correction_reasks():
    draft = {"step": "confirm", "occasion": "sinh nhật", "servings": "10", "flavor": "vani",
             "date": "mai", "note": "", "name": "A", "phone": "0901234567"}
    session = _session(draft)
    deps = _Deps(conn_factory=lambda: None)
    resp, created, new_draft = advance_quote(deps, session, "sửa ghi chú thành có nến")
    assert created is False
    assert new_draft["step"] == "confirm"
    assert new_draft["note"] == "sửa ghi chú thành có nến"
    assert "chỉnh sửa" in resp
