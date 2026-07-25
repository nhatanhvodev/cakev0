import json

_ALLOWED = {
    'status',
    'intent_label',
    'summary',
    'metadata',
    'assigned_admin_id',
    'assigned_at',
    'closed_at',
    'closed_by_admin_id',
    'reopened_at',
}

WORKFLOW_COLUMNS = {
    'assigned_admin_id',
    'assigned_at',
    'closed_by_admin_id',
    'reopened_at',
}


def normalize_support_status(status: str | None) -> str:
    '''Expose a stable UI status while retaining legacy database values.'''
    if status in {'handoff', 'open'}:
        return 'waiting'
    return status or 'active'


def _session_owner_matches(row, user_id, guest_token, external_user_id) -> bool:
    '''Match session ownership and fail closed when no identifier matches.'''
    if user_id is not None and row.get('user_id') == user_id:
        return True
    if guest_token and row.get('guest_token') == guest_token:
        return True
    if external_user_id and row.get('external_user_id') == external_user_id:
        return True
    return False


def get_or_create_session(conn, user_id=None, guest_token=None, source='widget',
                          external_user_id=None, session_id=None) -> dict:
    with conn.cursor() as cur:
        if session_id:
            cur.execute('SELECT * FROM chat_sessions WHERE id = %s', (session_id,))
            row = cur.fetchone()
            if row and _session_owner_matches(row, user_id, guest_token, external_user_id):
                return row
        if external_user_id and not session_id:
            cur.execute(
                'SELECT * FROM chat_sessions WHERE external_user_id = %s '
                'AND status = \'active\' ORDER BY id DESC LIMIT 1',
                (external_user_id,),
            )
            row = cur.fetchone()
            if row:
                return row
        cur.execute(
            'INSERT INTO chat_sessions (user_id, guest_token, source, external_user_id) '
            'VALUES (%s, %s, %s, %s)',
            (user_id, guest_token, source, external_user_id),
        )
        cur.execute('SELECT * FROM chat_sessions WHERE id = %s', (cur.lastrowid,))
        return cur.fetchone()


def append_message(conn, session_id, sender, content, content_type='text', metadata=None) -> int:
    with conn.cursor() as cur:
        cur.execute(
            'INSERT INTO chat_messages (session_id, sender, content, content_type, metadata) '
            'VALUES (%s, %s, %s, %s, %s)',
            (
                session_id,
                sender,
                content,
                content_type,
                json.dumps(metadata, ensure_ascii=False) if metadata else None,
            ),
        )
        return cur.lastrowid


def get_message_page(conn, session_id, limit=50, before_id=None, after_id=None) -> dict:
    '''Return a chronological cursor page, defaulting to the latest window.'''
    if before_id is not None and after_id is not None:
        raise ValueError('only one cursor may be supplied')

    limit = max(1, min(int(limit), 100))
    params: list[int] = [int(session_id)]

    if after_id is not None:
        where = ' AND id > %s'
        params.append(int(after_id))
        order = 'ASC'
    else:
        where = ''
        if before_id is not None:
            where = ' AND id < %s'
            params.append(int(before_id))
        order = 'DESC'

    params.append(limit + 1)
    with conn.cursor() as cur:
        cur.execute(
            f'SELECT * FROM chat_messages WHERE session_id = %s{where} '
            f'ORDER BY id {order} LIMIT %s',
            tuple(params),
        )
        rows = list(cur.fetchall())

    has_more = len(rows) > limit
    rows = rows[:limit]
    if order == 'DESC':
        rows.reverse()

    return {
        'messages': rows,
        'has_more': has_more,
        'oldest_id': rows[0]['id'] if rows else None,
        'latest_id': rows[-1]['id'] if rows else None,
    }


def get_messages(conn, session_id, limit=50) -> list[dict]:
    return get_message_page(conn, session_id, limit=limit)['messages']


def workflow_schema_ready(conn) -> bool:
    '''Detect whether the additive admin workflow migration is installed.'''
    with conn.cursor() as cur:
        cur.execute(
            '''
            SELECT COUNT(DISTINCT COLUMN_NAME) AS c
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'chat_sessions'
              AND COLUMN_NAME IN ('assigned_admin_id', 'assigned_at',
                                  'closed_by_admin_id', 'reopened_at')
            '''
        )
        columns = int((cur.fetchone() or {}).get('c', 0))
        cur.execute(
            '''
            SELECT COUNT(*) AS c
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'chat_session_events'
            '''
        )
        events_table = int((cur.fetchone() or {}).get('c', 0))
    return columns == len(WORKFLOW_COLUMNS) and events_table == 1


def get_session(conn, session_id, for_update=False) -> dict | None:
    query = 'SELECT * FROM chat_sessions WHERE id = %s'
    if for_update:
        query += ' FOR UPDATE'
    with conn.cursor() as cur:
        cur.execute(query, (session_id,))
        return cur.fetchone()


def record_session_event(conn, session_id, admin_id, event_type, from_status=None,
                         to_status=None, metadata=None) -> int:
    with conn.cursor() as cur:
        cur.execute(
            '''
            INSERT INTO chat_session_events
                (session_id, admin_id, event_type, from_status, to_status, metadata)
            VALUES (%s, %s, %s, %s, %s, %s)
            ''',
            (
                session_id,
                admin_id,
                event_type,
                from_status,
                to_status,
                json.dumps(metadata, ensure_ascii=False) if metadata else None,
            ),
        )
        return cur.lastrowid


def claim_session(conn, session_id, admin_id, event_type='claimed') -> dict | None:
    '''Claim an unassigned/open session, or keep ownership for the same admin.'''
    previous = get_session(conn, session_id, for_update=True)
    if previous is None or previous.get('status') == 'closed':
        return None
    assigned_admin_id = previous.get('assigned_admin_id')
    if (
        previous.get('status') == 'in_progress'
        and assigned_admin_id is not None
        and int(assigned_admin_id) == int(admin_id)
    ):
        return previous

    with conn.cursor() as cur:
        cur.execute(
            '''
            UPDATE chat_sessions
            SET assigned_admin_id = %s,
                assigned_at = COALESCE(assigned_at, CURRENT_TIMESTAMP),
                status = 'in_progress',
                closed_at = NULL,
                closed_by_admin_id = NULL
            WHERE id = %s
              AND (assigned_admin_id IS NULL OR assigned_admin_id = %s)
              AND status <> 'closed'
            ''',
            (admin_id, session_id, admin_id),
        )
        if cur.rowcount != 1:
            return None

    if previous.get('status') != 'in_progress' or previous.get('assigned_admin_id') != admin_id:
        record_session_event(
            conn,
            session_id,
            admin_id,
            event_type,
            previous.get('status'),
            'in_progress',
        )
    return get_session(conn, session_id)


def close_session(conn, session_id, admin_id) -> dict | None:
    previous = get_session(conn, session_id, for_update=True)
    if previous is None:
        return None

    with conn.cursor() as cur:
        cur.execute(
            '''
            UPDATE chat_sessions
            SET status = 'closed',
                assigned_admin_id = COALESCE(assigned_admin_id, %s),
                assigned_at = COALESCE(assigned_at, CURRENT_TIMESTAMP),
                closed_at = CURRENT_TIMESTAMP,
                closed_by_admin_id = %s
            WHERE id = %s
              AND (assigned_admin_id IS NULL OR assigned_admin_id = %s)
              AND status <> 'closed'
            ''',
            (admin_id, admin_id, session_id, admin_id),
        )
        if cur.rowcount != 1:
            return None

    record_session_event(
        conn, session_id, admin_id, 'closed', previous.get('status'), 'closed'
    )
    return get_session(conn, session_id)


def reopen_session(conn, session_id, admin_id) -> dict | None:
    previous = get_session(conn, session_id, for_update=True)
    if previous is None or previous.get('status') != 'closed':
        return None

    with conn.cursor() as cur:
        cur.execute(
            '''
            UPDATE chat_sessions
            SET status = 'open',
                assigned_admin_id = NULL,
                assigned_at = NULL,
                closed_at = NULL,
                closed_by_admin_id = NULL,
                reopened_at = CURRENT_TIMESTAMP
            WHERE id = %s AND status = 'closed'
            ''',
            (session_id,),
        )
        if cur.rowcount != 1:
            return None

    record_session_event(
        conn, session_id, admin_id, 'reopened', previous.get('status'), 'open'
    )
    return get_session(conn, session_id)


def update_session(conn, session_id, **fields):
    sets, vals = [], []
    for key, value in fields.items():
        if key not in _ALLOWED:
            raise ValueError(f'field not allowed: {key}')
        if key == 'metadata' and isinstance(value, dict):
            value = json.dumps(value, ensure_ascii=False)
        sets.append(f'{key} = %s')
        vals.append(value)
    if not sets:
        return
    assignments = ', '.join(sets)
    with conn.cursor() as cur:
        cur.execute(f'UPDATE chat_sessions SET {assignments} WHERE id = %s', (*vals, session_id))
