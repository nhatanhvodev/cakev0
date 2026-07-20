import pymysql
from app.config import Settings


def get_conn(settings: Settings) -> pymysql.connections.Connection:
    """Get a MySQL connection with DictCursor and autocommit enabled."""
    return pymysql.connect(
        host=settings.mysql_host,
        port=settings.mysql_port,
        user=settings.mysql_user,
        password=settings.mysql_password,
        database=settings.mysql_database,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )
