import pytest
from app.config import get_settings
from app.db import mysql, orders_repo


@pytest.fixture
def conn():
    try:
        c = mysql.get_conn(get_settings())
    except Exception:
        pytest.skip("MySQL not available")
    yield c
    c.close()


def test_lookup_orders_no_filters_returns_empty():
    # No filter provided → short-circuits before touching the connection.
    assert orders_repo.lookup_orders(None, phone=None, order_id=None, user_id=None) == []


def test_lookup_orders_by_order_id(conn):
    rows = orders_repo.lookup_orders(conn, order_id=1)
    if rows:
        assert {"id", "status", "total_amount", "created_at", "payment_method", "items"} <= set(rows[0].keys())


def test_lookup_orders_by_phone(conn):
    rows = orders_repo.lookup_orders(conn, phone="0901234567")
    assert isinstance(rows, list)


def test_lookup_orders_by_user_id(conn):
    rows = orders_repo.lookup_orders(conn, user_id=1)
    assert isinstance(rows, list)
