from app.db.admin_chat_migration import ensure_admin_chat_workflow


class MigrationCursor:
    def __init__(self, state):
        self.state = state
        self.result = {"c": 0}

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc, traceback):
        return False

    def fetchone(self):
        return self.result

    def execute(self, sql, params=()):
        compact = " ".join(sql.split())
        self.state["queries"].append((compact, params))

        if "COUNT(DISTINCT COLUMN_NAME)" in compact:
            count = len(
                {
                    "assigned_admin_id",
                    "assigned_at",
                    "closed_by_admin_id",
                    "reopened_at",
                }
                & self.state["columns"]
            )
            self.result = {"c": count}
            return

        if "SELECT COLUMN_TYPE AS column_type" in compact:
            values = ",".join(
                f"'{value}'" for value in sorted(self.state["status_values"])
            )
            self.result = {"column_type": f"enum({values})"}
            return

        if "FROM information_schema.TABLES" in compact:
            table = params[0] if params else "chat_session_events"
            self.result = {"c": int(table in self.state["tables"])}
            return

        if "FROM information_schema.COLUMNS" in compact:
            self.result = {"c": int(params[1] in self.state["columns"])}
            return

        if "FROM information_schema.STATISTICS" in compact:
            self.result = {"c": int(params[1] in self.state["indexes"])}
            return

        if "FROM information_schema.TABLE_CONSTRAINTS" in compact:
            self.result = {"c": int(params[1] in self.state["constraints"])}
            return

        for column in (
            "assigned_admin_id",
            "assigned_at",
            "closed_by_admin_id",
            "reopened_at",
        ):
            if f"ADD COLUMN {column} " in compact:
                self.state["columns"].add(column)

        if "MODIFY status ENUM" in compact:
            self.state["status_values"] = {
                "active",
                "open",
                "handoff",
                "in_progress",
                "closed",
            }
        if "ADD INDEX idx_chat_assigned_admin" in compact:
            self.state["indexes"].add("idx_chat_assigned_admin")
        if "ADD INDEX idx_chat_event_session" in compact:
            self.state["indexes"].add("idx_chat_event_session")
        if "ADD INDEX idx_chat_event_admin" in compact:
            self.state["indexes"].add("idx_chat_event_admin")
        if "ADD CONSTRAINT fk_chat_assigned_admin" in compact:
            self.state["constraints"].add("fk_chat_assigned_admin")
        if "ADD CONSTRAINT fk_chat_closed_by_admin" in compact:
            self.state["constraints"].add("fk_chat_closed_by_admin")
        if "CREATE TABLE IF NOT EXISTS chat_session_events" in compact:
            self.state["tables"].add("chat_session_events")
            self.state["indexes"].update(
                {"idx_chat_event_session", "idx_chat_event_admin"}
            )
            self.state["constraints"].add("fk_chat_event_session")
        if "ADD CONSTRAINT fk_chat_event_session" in compact:
            self.state["constraints"].add("fk_chat_event_session")
        if "ADD CONSTRAINT fk_chat_event_admin" in compact:
            self.state["constraints"].add("fk_chat_event_admin")


class MigrationConnection:
    def __init__(self, base_schema=True):
        self.state = {
            "tables": {"admins", "chat_sessions"} if base_schema else set(),
            "columns": set(),
            "status_values": {"active", "handoff", "closed"},
            "indexes": set(),
            "constraints": set(),
            "queries": [],
        }

    def cursor(self):
        return MigrationCursor(self.state)


def test_runtime_migration_installs_schema_and_is_idempotent():
    conn = MigrationConnection()

    assert ensure_admin_chat_workflow(conn) is True
    assert conn.state["columns"] == {
        "assigned_admin_id",
        "assigned_at",
        "closed_by_admin_id",
        "reopened_at",
    }
    assert "chat_session_events" in conn.state["tables"]
    assert "idx_chat_assigned_admin" in conn.state["indexes"]
    assert {
        "idx_chat_event_session",
        "idx_chat_event_admin",
    } <= conn.state["indexes"]
    assert {
        "fk_chat_assigned_admin",
        "fk_chat_closed_by_admin",
        "fk_chat_event_session",
        "fk_chat_event_admin",
    } <= conn.state["constraints"]
    first_query_count = len(conn.state["queries"])

    assert ensure_admin_chat_workflow(conn) is True
    repeated_queries = conn.state["queries"][first_query_count:]
    assert not any(
        "ADD COLUMN" in query
        or "ADD INDEX" in query
        or "ADD CONSTRAINT" in query
        or "MODIFY status ENUM" in query
        for query, _params in repeated_queries
    )


def test_runtime_migration_skips_when_base_chat_schema_is_missing():
    conn = MigrationConnection(base_schema=False)

    assert ensure_admin_chat_workflow(conn) is False
    assert not any(
        query.startswith("ALTER TABLE")
        for query, _params in conn.state["queries"]
    )


def test_startup_migration_uses_direct_mysql_connection(monkeypatch):
    from app import main as main_module
    from app.db import admin_chat_migration, mysql

    class StartupConnection:
        closed = False

        def close(self):
            self.closed = True

    conn = StartupConnection()
    seen = []
    monkeypatch.setattr(mysql, "get_conn", lambda _settings: conn)
    monkeypatch.setattr(
        admin_chat_migration,
        "ensure_admin_chat_workflow",
        lambda received: seen.append(received) or True,
    )

    main_module._ensure_admin_chat_workflow()

    assert seen == [conn]
    assert conn.closed is True
    assert main_module._admin_chat_workflow_ready is True
