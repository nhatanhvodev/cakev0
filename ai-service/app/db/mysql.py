import pymysql
from app.config import Settings


def get_conn(settings: Settings) -> pymysql.connections.Connection:
    """Get a MySQL connection with DictCursor and autocommit enabled.

    TLS: managed MySQL (Aiven) rejects plaintext. Set MYSQL_SSL=true to enable
    TLS; optionally point MYSQL_SSL_CA at a CA cert file to verify the server.
    """
    kwargs = dict(
        host=settings.mysql_host,
        port=settings.mysql_port,
        user=settings.mysql_user,
        password=settings.mysql_password,
        database=settings.mysql_database,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )
    if settings.mysql_ssl_ca:
        kwargs["ssl"] = {"ca": settings.mysql_ssl_ca}
    elif settings.mysql_ssl:
        kwargs["ssl"] = {}  # TLS without CA verification
    return pymysql.connect(**kwargs)
