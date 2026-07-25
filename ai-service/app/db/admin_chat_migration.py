"""Idempotent runtime migration for the admin chat workflow.

Render deploys the AI service independently from the PHP application and does
not provide a repository-level database migration job. Keeping this migration
best-effort at startup ensures the workflow becomes available after deploy
while allowing the service to boot in legacy-compatible mode if DDL privileges
or the base chat schema are unavailable.
"""

from app.db import chat_repo


SESSION_COLUMNS = {
    "assigned_admin_id": "INT NULL AFTER metadata",
    "assigned_at": "TIMESTAMP NULL AFTER assigned_admin_id",
    "closed_by_admin_id": "INT NULL AFTER closed_at",
    "reopened_at": "TIMESTAMP NULL AFTER closed_by_admin_id",
}


def _exists(cursor, catalog: str, name_field: str, name: str, table: str) -> bool:
    cursor.execute(
        f"""
        SELECT COUNT(*) AS c
        FROM information_schema.{catalog}
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = %s
          AND {name_field} = %s
        """,
        (table, name),
    )
    return int((cursor.fetchone() or {}).get("c", 0)) > 0


def _table_exists(cursor, table: str) -> bool:
    cursor.execute(
        """
        SELECT COUNT(*) AS c
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = %s
        """,
        (table,),
    )
    return int((cursor.fetchone() or {}).get("c", 0)) > 0


def _column_exists(cursor, table: str, column: str) -> bool:
    return _exists(cursor, "COLUMNS", "COLUMN_NAME", column, table)


def _status_enum_ready(cursor) -> bool:
    cursor.execute(
        """
        SELECT COLUMN_TYPE AS column_type
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'chat_sessions'
          AND COLUMN_NAME = 'status'
        """
    )
    column_type = str((cursor.fetchone() or {}).get("column_type", ""))
    return all(
        f"'{status}'" in column_type
        for status in ("active", "open", "handoff", "in_progress", "closed")
    )


def _index_exists(cursor, table: str, index: str) -> bool:
    return _exists(cursor, "STATISTICS", "INDEX_NAME", index, table)


def _constraint_exists(cursor, table: str, constraint: str) -> bool:
    cursor.execute(
        """
        SELECT COUNT(*) AS c
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = %s
          AND CONSTRAINT_NAME = %s
        """,
        (table, constraint),
    )
    return int((cursor.fetchone() or {}).get("c", 0)) > 0


def _workflow_artifacts_ready(conn) -> bool:
    if not chat_repo.workflow_schema_ready(conn):
        return False
    with conn.cursor() as cursor:
        admins_exist = _table_exists(cursor, "admins")
        ready = (
            _status_enum_ready(cursor)
            and _index_exists(
                cursor, "chat_sessions", "idx_chat_assigned_admin"
            )
            and _index_exists(
                cursor, "chat_session_events", "idx_chat_event_session"
            )
            and _index_exists(
                cursor, "chat_session_events", "idx_chat_event_admin"
            )
            and _constraint_exists(
                cursor, "chat_session_events", "fk_chat_event_session"
            )
        )
        if admins_exist:
            ready = ready and all(
                (
                    _constraint_exists(
                        cursor,
                        "chat_sessions",
                        "fk_chat_assigned_admin",
                    ),
                    _constraint_exists(
                        cursor,
                        "chat_sessions",
                        "fk_chat_closed_by_admin",
                    ),
                    _constraint_exists(
                        cursor,
                        "chat_session_events",
                        "fk_chat_event_admin",
                    ),
                )
            )
        return ready


def ensure_admin_chat_workflow(conn) -> bool:
    """Install the additive workflow schema and report whether it is ready."""
    with conn.cursor() as cursor:
        if not _table_exists(cursor, "chat_sessions"):
            return False

        if not _status_enum_ready(cursor):
            cursor.execute(
                """
                ALTER TABLE chat_sessions
                MODIFY status ENUM(
                    'active',
                    'open',
                    'handoff',
                    'in_progress',
                    'closed'
                ) NOT NULL DEFAULT 'active'
                """
            )

        for column, definition in SESSION_COLUMNS.items():
            if not _column_exists(cursor, "chat_sessions", column):
                cursor.execute(
                    f"ALTER TABLE chat_sessions ADD COLUMN {column} {definition}"
                )

        if not _index_exists(
            cursor, "chat_sessions", "idx_chat_assigned_admin"
        ):
            cursor.execute(
                """
                ALTER TABLE chat_sessions
                ADD INDEX idx_chat_assigned_admin (assigned_admin_id, status)
                """
            )

        admins_exist = _table_exists(cursor, "admins")
        if admins_exist and not _constraint_exists(
            cursor, "chat_sessions", "fk_chat_assigned_admin"
        ):
            cursor.execute(
                """
                ALTER TABLE chat_sessions
                ADD CONSTRAINT fk_chat_assigned_admin
                FOREIGN KEY (assigned_admin_id)
                REFERENCES admins(id)
                ON DELETE SET NULL
                """
            )

        if admins_exist and not _constraint_exists(
            cursor, "chat_sessions", "fk_chat_closed_by_admin"
        ):
            cursor.execute(
                """
                ALTER TABLE chat_sessions
                ADD CONSTRAINT fk_chat_closed_by_admin
                FOREIGN KEY (closed_by_admin_id)
                REFERENCES admins(id)
                ON DELETE SET NULL
                """
            )

        cursor.execute(
            """
            CREATE TABLE IF NOT EXISTS chat_session_events (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                admin_id INT NULL,
                event_type VARCHAR(40) NOT NULL,
                from_status VARCHAR(24) NULL,
                to_status VARCHAR(24) NULL,
                metadata JSON NULL,
                created_at TIMESTAMP(3) DEFAULT CURRENT_TIMESTAMP(3),
                INDEX idx_chat_event_session (session_id, id),
                INDEX idx_chat_event_admin (admin_id, created_at),
                CONSTRAINT fk_chat_event_session
                    FOREIGN KEY (session_id)
                    REFERENCES chat_sessions(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
            """
        )

        if not _index_exists(
            cursor, "chat_session_events", "idx_chat_event_session"
        ):
            cursor.execute(
                """
                ALTER TABLE chat_session_events
                ADD INDEX idx_chat_event_session (session_id, id)
                """
            )

        if not _index_exists(
            cursor, "chat_session_events", "idx_chat_event_admin"
        ):
            cursor.execute(
                """
                ALTER TABLE chat_session_events
                ADD INDEX idx_chat_event_admin (admin_id, created_at)
                """
            )

        if not _constraint_exists(
            cursor, "chat_session_events", "fk_chat_event_session"
        ):
            cursor.execute(
                """
                ALTER TABLE chat_session_events
                ADD CONSTRAINT fk_chat_event_session
                FOREIGN KEY (session_id)
                REFERENCES chat_sessions(id)
                ON DELETE CASCADE
                """
            )

        if admins_exist and not _constraint_exists(
            cursor, "chat_session_events", "fk_chat_event_admin"
        ):
            cursor.execute(
                """
                ALTER TABLE chat_session_events
                ADD CONSTRAINT fk_chat_event_admin
                FOREIGN KEY (admin_id)
                REFERENCES admins(id)
                ON DELETE SET NULL
                """
            )

        cursor.execute(
            "UPDATE chat_sessions SET status = 'open' WHERE status = 'handoff'"
        )

    return _workflow_artifacts_ready(conn)
