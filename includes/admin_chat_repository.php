<?php

function admin_chat_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_chat_fetch_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function admin_chat_fetch_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function admin_chat_execute(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return -1;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $stmt->close();
        return -1;
    }
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function admin_chat_table_exists(mysqli $conn, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($conn) . ':table:' . $table;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $row = admin_chat_fetch_one(
        $conn,
        "SELECT COUNT(*) c
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?",
        's',
        [$table]
    );
    return $cache[$key] = ((int) ($row['c'] ?? 0)) > 0;
}

function admin_chat_column_count(mysqli $conn, string $table, array $columns): int
{
    if (!$columns) {
        return 0;
    }
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $types = 's' . str_repeat('s', count($columns));
    $row = admin_chat_fetch_one(
        $conn,
        "SELECT COUNT(DISTINCT COLUMN_NAME) c
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME IN ({$placeholders})",
        $types,
        array_merge([$table], $columns)
    );
    return (int) ($row['c'] ?? 0);
}

function admin_chat_assignment_ready(mysqli $conn): bool
{
    static $cache = [];
    $key = spl_object_id($conn);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $columns = ['assigned_admin_id', 'assigned_at', 'closed_by_admin_id', 'reopened_at'];
    return $cache[$key] = admin_chat_column_count($conn, 'chat_sessions', $columns) === count($columns);
}

function admin_chat_workflow_ready(mysqli $conn): bool
{
    return admin_chat_assignment_ready($conn) && admin_chat_table_exists($conn, 'chat_session_events');
}

function admin_chat_normalize_status(?string $status): string
{
    if ($status === 'handoff' || $status === 'open') {
        return 'waiting';
    }
    return $status ?: 'active';
}

function admin_chat_decode_metadata($value): ?array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return null;
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : null;
}

function admin_chat_serialize_message(array $message): array
{
    return [
        'id' => (int) $message['id'],
        'sender' => (string) $message['sender'],
        'content' => (string) $message['content'],
        'content_type' => $message['content_type'] ?? null,
        'metadata' => admin_chat_decode_metadata($message['metadata'] ?? null),
        'admin_id' => isset($message['admin_id']) ? (int) $message['admin_id'] : null,
        'created_at' => isset($message['created_at']) ? (string) $message['created_at'] : null,
    ];
}

function admin_chat_serialize_session(array $row): array
{
    $item = $row;
    $item['id'] = (int) $item['id'];
    $item['stored_status'] = (string) ($item['status'] ?? '');
    $item['status'] = admin_chat_normalize_status($item['stored_status']);
    $item['user_id'] = isset($item['user_id']) ? (int) $item['user_id'] : null;
    $item['assigned_admin_id'] = isset($item['assigned_admin_id']) ? (int) $item['assigned_admin_id'] : null;
    $item['open_ticket_count'] = isset($item['open_ticket_count']) ? (int) $item['open_ticket_count'] : 0;

    foreach (['created_at', 'updated_at', 'assigned_at', 'closed_at', 'reopened_at'] as $key) {
        $item[$key] = isset($item[$key]) && $item[$key] !== null ? (string) $item[$key] : null;
    }

    return $item;
}

function admin_chat_empty_stats(): array
{
    return [
        'today_sessions' => 0,
        'waiting_sessions' => 0,
        'handoff_sessions' => 0,
        'in_progress_sessions' => 0,
        'mine_sessions' => 0,
        'closed_sessions' => 0,
        'intent_counts' => [],
    ];
}

function admin_chat_sessions_response(mysqli $conn, string $view, int $adminId): array
{
    $workflowReady = admin_chat_workflow_ready($conn);
    $assignmentReady = admin_chat_assignment_ready($conn);
    $ticketExpr = admin_chat_table_exists($conn, 'support_tickets')
        ? "(SELECT COUNT(*) FROM support_tickets t
            WHERE t.session_id = s.id
              AND t.status IN ('open', 'in_progress')) AS open_ticket_count"
        : '0 AS open_ticket_count';

    $assignmentColumns = $assignmentReady
        ? "s.assigned_admin_id, s.assigned_at, s.closed_at, s.reopened_at,
           a.username AS assigned_admin_name,"
        : "NULL AS assigned_admin_id, NULL AS assigned_at, s.closed_at,
           NULL AS reopened_at, NULL AS assigned_admin_name,";
    $assignmentJoin = $assignmentReady ? 'LEFT JOIN admins a ON a.id = s.assigned_admin_id' : '';

    $params = [];
    $types = '';
    if ($view === 'waiting') {
        $where = "s.status IN ('open', 'handoff')";
    } elseif ($view === 'mine' && $assignmentReady) {
        $where = "s.assigned_admin_id = ? AND s.status = 'in_progress'";
        $types = 'i';
        $params = [$adminId];
    } elseif ($view === 'in_progress' && $assignmentReady) {
        $where = "s.status = 'in_progress'";
    } elseif ($view === 'closed') {
        $where = "s.status = 'closed'";
    } elseif ($view === 'all') {
        $where = '1 = 1';
    } else {
        $where = '1 = 0';
    }

    $sessions = admin_chat_fetch_all(
        $conn,
        "SELECT s.id, s.source, s.status, s.user_id, s.intent_label,
                s.created_at, s.updated_at,
                {$assignmentColumns}
                (SELECT content FROM chat_messages m
                 WHERE m.session_id = s.id
                 ORDER BY m.id DESC LIMIT 1) AS last_message,
                (SELECT sender FROM chat_messages m
                 WHERE m.session_id = s.id
                 ORDER BY m.id DESC LIMIT 1) AS last_sender,
                {$ticketExpr}
         FROM chat_sessions s
         {$assignmentJoin}
         WHERE {$where}
         ORDER BY s.updated_at DESC, s.id DESC
         LIMIT 100",
        $types,
        $params
    );

    $stats = admin_chat_empty_stats();
    $today = admin_chat_fetch_one($conn, "SELECT COUNT(*) c FROM chat_sessions WHERE DATE(created_at) = CURDATE()");
    $stats['today_sessions'] = (int) ($today['c'] ?? 0);

    if ($assignmentReady) {
        $counts = admin_chat_fetch_one(
            $conn,
            "SELECT
                COALESCE(SUM(status IN ('open', 'handoff')), 0) waiting_sessions,
                COALESCE(SUM(status = 'in_progress'), 0) in_progress_sessions,
                COALESCE(SUM(status = 'closed'), 0) closed_sessions,
                COALESCE(SUM(assigned_admin_id = ? AND status = 'in_progress'), 0) mine_sessions
             FROM chat_sessions",
            'i',
            [$adminId]
        );
    } else {
        $counts = admin_chat_fetch_one(
            $conn,
            "SELECT
                COALESCE(SUM(status IN ('open', 'handoff')), 0) waiting_sessions,
                0 in_progress_sessions,
                COALESCE(SUM(status = 'closed'), 0) closed_sessions,
                0 mine_sessions
             FROM chat_sessions"
        );
    }

    $waiting = (int) ($counts['waiting_sessions'] ?? 0);
    $stats['waiting_sessions'] = $waiting;
    $stats['handoff_sessions'] = $waiting;
    $stats['in_progress_sessions'] = (int) ($counts['in_progress_sessions'] ?? 0);
    $stats['mine_sessions'] = (int) ($counts['mine_sessions'] ?? 0);
    $stats['closed_sessions'] = (int) ($counts['closed_sessions'] ?? 0);

    $intentRows = admin_chat_fetch_all(
        $conn,
        "SELECT intent_label, COUNT(*) c
         FROM chat_sessions
         WHERE intent_label IS NOT NULL AND intent_label <> ''
         GROUP BY intent_label
         ORDER BY c DESC
         LIMIT 8"
    );
    foreach ($intentRows as $row) {
        $stats['intent_counts'][(string) $row['intent_label']] = (int) $row['c'];
    }

    return [
        'sessions' => array_map('admin_chat_serialize_session', $sessions),
        'stats' => $stats,
        'workflow_ready' => $workflowReady,
    ];
}

function admin_chat_history_response(mysqli $conn, int $sessionId, int $limit, ?int $beforeId, ?int $afterId): array
{
    $session = admin_chat_fetch_one($conn, 'SELECT id FROM chat_sessions WHERE id = ? LIMIT 1', 'i', [$sessionId]);
    if (!$session) {
        admin_chat_json(['error' => 'session_not_found'], 404);
    }

    $params = [$sessionId];
    $types = 'i';
    $where = '';
    $order = 'DESC';
    if ($afterId !== null) {
        $where = ' AND id > ?';
        $params[] = $afterId;
        $types .= 'i';
        $order = 'ASC';
    } elseif ($beforeId !== null) {
        $where = ' AND id < ?';
        $params[] = $beforeId;
        $types .= 'i';
    }

    $fetchLimit = $limit + 1;
    $params[] = $fetchLimit;
    $types .= 'i';
    $rows = admin_chat_fetch_all(
        $conn,
        "SELECT *
         FROM chat_messages
         WHERE session_id = ?{$where}
         ORDER BY id {$order}
         LIMIT ?",
        $types,
        $params
    );

    $hasMore = count($rows) > $limit;
    $rows = array_slice($rows, 0, $limit);
    if ($order === 'DESC') {
        $rows = array_reverse($rows);
    }

    return [
        'session_id' => $sessionId,
        'messages' => array_map('admin_chat_serialize_message', $rows),
        'has_more' => $hasMore,
        'oldest_id' => isset($rows[0]['id']) ? (int) $rows[0]['id'] : null,
        'latest_id' => isset($rows[count($rows) - 1]['id']) ? (int) $rows[count($rows) - 1]['id'] : null,
    ];
}

function admin_chat_get_session(mysqli $conn, int $sessionId, bool $forUpdate = false): ?array
{
    $forUpdateSql = $forUpdate ? ' FOR UPDATE' : '';
    if (admin_chat_assignment_ready($conn)) {
        return admin_chat_fetch_one(
            $conn,
            "SELECT s.*, a.username AS assigned_admin_name, 0 AS open_ticket_count
             FROM chat_sessions s
             LEFT JOIN admins a ON a.id = s.assigned_admin_id
             WHERE s.id = ?
             LIMIT 1{$forUpdateSql}",
            'i',
            [$sessionId]
        );
    }

    return admin_chat_fetch_one(
        $conn,
        "SELECT s.*, NULL AS assigned_admin_id, NULL AS assigned_at,
                NULL AS closed_by_admin_id, NULL AS reopened_at,
                NULL AS assigned_admin_name, 0 AS open_ticket_count
         FROM chat_sessions s
         WHERE s.id = ?
         LIMIT 1{$forUpdateSql}",
        'i',
        [$sessionId]
    );
}

function admin_chat_record_event(mysqli $conn, int $sessionId, int $adminId, string $eventType, ?string $fromStatus, ?string $toStatus, array $metadata = null): void
{
    if (!admin_chat_table_exists($conn, 'chat_session_events')) {
        return;
    }
    $json = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    admin_chat_execute(
        $conn,
        "INSERT INTO chat_session_events
             (session_id, admin_id, event_type, from_status, to_status, metadata)
         VALUES (?, ?, ?, ?, ?, ?)",
        'iissss',
        [$sessionId, $adminId, $eventType, $fromStatus, $toStatus, $json]
    );
}

function admin_chat_claim_session(mysqli $conn, int $sessionId, int $adminId, string $eventType = 'claimed'): ?array
{
    $previous = admin_chat_get_session($conn, $sessionId, true);
    if (!$previous || ($previous['status'] ?? '') === 'closed') {
        return null;
    }

    $assignedId = $previous['assigned_admin_id'] ?? null;
    if (($previous['status'] ?? '') === 'in_progress' && $assignedId !== null && (int) $assignedId === $adminId) {
        return $previous;
    }

    $affected = admin_chat_execute(
        $conn,
        "UPDATE chat_sessions
         SET assigned_admin_id = ?,
             assigned_at = COALESCE(assigned_at, CURRENT_TIMESTAMP),
             status = 'in_progress',
             closed_at = NULL,
             closed_by_admin_id = NULL
         WHERE id = ?
           AND (assigned_admin_id IS NULL OR assigned_admin_id = ?)
           AND status <> 'closed'",
        'iii',
        [$adminId, $sessionId, $adminId]
    );
    if ($affected !== 1) {
        return null;
    }

    if (($previous['status'] ?? '') !== 'in_progress' || (int) ($assignedId ?? 0) !== $adminId) {
        admin_chat_record_event($conn, $sessionId, $adminId, $eventType, $previous['status'] ?? null, 'in_progress');
    }
    return admin_chat_get_session($conn, $sessionId);
}

function admin_chat_close_session(mysqli $conn, int $sessionId, int $adminId): ?array
{
    $previous = admin_chat_get_session($conn, $sessionId, true);
    if (!$previous) {
        return null;
    }

    $affected = admin_chat_execute(
        $conn,
        "UPDATE chat_sessions
         SET status = 'closed',
             assigned_admin_id = COALESCE(assigned_admin_id, ?),
             assigned_at = COALESCE(assigned_at, CURRENT_TIMESTAMP),
             closed_at = CURRENT_TIMESTAMP,
             closed_by_admin_id = ?
         WHERE id = ?
           AND (assigned_admin_id IS NULL OR assigned_admin_id = ?)
           AND status <> 'closed'",
        'iiii',
        [$adminId, $adminId, $sessionId, $adminId]
    );
    if ($affected !== 1) {
        return null;
    }

    admin_chat_record_event($conn, $sessionId, $adminId, 'closed', $previous['status'] ?? null, 'closed');
    return admin_chat_get_session($conn, $sessionId);
}

function admin_chat_reopen_session(mysqli $conn, int $sessionId, int $adminId): ?array
{
    $previous = admin_chat_get_session($conn, $sessionId, true);
    if (!$previous || ($previous['status'] ?? '') !== 'closed') {
        return null;
    }

    $affected = admin_chat_execute(
        $conn,
        "UPDATE chat_sessions
         SET status = 'open',
             assigned_admin_id = NULL,
             assigned_at = NULL,
             closed_at = NULL,
             closed_by_admin_id = NULL,
             reopened_at = CURRENT_TIMESTAMP
         WHERE id = ? AND status = 'closed'",
        'i',
        [$sessionId]
    );
    if ($affected !== 1) {
        return null;
    }

    admin_chat_record_event($conn, $sessionId, $adminId, 'reopened', $previous['status'] ?? null, 'open');
    return admin_chat_get_session($conn, $sessionId);
}

function admin_chat_action_response(mysqli $conn, int $sessionId, int $adminId, string $action): array
{
    if (!admin_chat_workflow_ready($conn)) {
        admin_chat_json(['error' => 'chat_workflow_migration_required'], 503);
    }

    $conn->begin_transaction();
    try {
        $session = [
            'claim' => 'admin_chat_claim_session',
            'close' => 'admin_chat_close_session',
            'reopen' => 'admin_chat_reopen_session',
        ][$action]($conn, $sessionId, $adminId);

        if (!$session) {
            $conn->rollback();
            $exists = admin_chat_get_session($conn, $sessionId) !== null;
            admin_chat_json(['error' => $exists ? 'session_transition_conflict' : 'session_not_found'], $exists ? 409 : 404);
        }
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        admin_chat_json(['error' => 'chat_action_failed', 'detail' => $error->getMessage()], 500);
    }

    return [
        'session' => admin_chat_serialize_session($session),
        'workflow_ready' => true,
    ];
}

function admin_chat_reply_response(mysqli $conn, int $sessionId, int $adminId, string $content): array
{
    $workflowReady = admin_chat_workflow_ready($conn);
    $conn->begin_transaction();
    try {
        $session = admin_chat_get_session($conn, $sessionId, true);
        if (!$session) {
            $conn->rollback();
            admin_chat_json(['error' => 'session_not_found'], 404);
        }
        if (($session['status'] ?? '') === 'closed') {
            $conn->rollback();
            admin_chat_json(['error' => 'session_closed'], 409);
        }

        if ($workflowReady) {
            $session = admin_chat_claim_session($conn, $sessionId, $adminId, 'reply_auto_assigned');
            if (!$session) {
                $conn->rollback();
                admin_chat_json(['error' => 'session_assigned_elsewhere'], 409);
            }
        }

        $stmt = $conn->prepare(
            "INSERT INTO chat_messages (session_id, sender, content, admin_id)
             VALUES (?, 'agent', ?, ?)"
        );
        if (!$stmt) {
            throw new RuntimeException('prepare_message_insert_failed');
        }
        $stmt->bind_param('isi', $sessionId, $content, $adminId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('message_insert_failed');
        }
        $messageId = (int) $stmt->insert_id;
        $stmt->close();

        if ($workflowReady) {
            admin_chat_record_event($conn, $sessionId, $adminId, 'replied', 'in_progress', 'in_progress', ['message_id' => $messageId]);
        }
        admin_chat_execute($conn, 'UPDATE chat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = ?', 'i', [$sessionId]);

        $updatedSession = admin_chat_get_session($conn, $sessionId);
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        admin_chat_json(['error' => 'chat_reply_failed', 'detail' => $error->getMessage()], 500);
    }

    return [
        'message_id' => $messageId,
        'session' => $updatedSession ? admin_chat_serialize_session($updatedSession) : null,
        'workflow_ready' => $workflowReady,
    ];
}
