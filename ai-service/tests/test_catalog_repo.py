import pytest
from app.config import get_settings
from app.db import mysql, catalog_repo


@pytest.fixture
def conn():
    try:
        c = mysql.get_conn(get_settings())
    except Exception:
        pytest.skip("MySQL not available")
    yield c
    c.close()


def test_list_products_returns_rows(conn):
    rows = catalog_repo.list_products(conn)
    assert len(rows) > 0
    assert {"id", "ten_banh", "gia"} <= set(rows[0].keys())


def test_find_by_ids_empty():
    assert catalog_repo.build_in_placeholders([]) == ""
    assert catalog_repo.build_in_placeholders([1, 2, 3]) == "%s,%s,%s"


def test_find_products_by_ids(conn):
    rows = catalog_repo.find_products_by_ids(conn, [1])
    if rows:
        assert {"id", "ten_banh", "gia"} <= set(rows[0].keys())


def test_search_products_like(conn):
    rows = catalog_repo.search_products_like(conn, "banh", limit=5)
    if rows:
        assert {"id", "ten_banh", "gia"} <= set(rows[0].keys())
