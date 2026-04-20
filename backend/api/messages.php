<?php
// ═══════════════════════════════════════════════
//  api/messages.php
//  GET  → fetch conversation or contacts list
//  POST → send a message
// ═══════════════════════════════════════════════

require_once '../config/helpers.php';
require_once '../config/db.php';

$user   = requireAuth();
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ════════════════════════════════
//  GET — Fetch contacts or messages
// ════════════════════════════════
if ($method === 'GET') {

    // GET /messages.php?with=5  → fetch conversation with user #5
    if (!empty($_GET['with'])) {
        $otherId = (int)$_GET['with'];

        // Mark messages from other user as read
        $stmt = $db->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
        $stmt->bind_param('ii', $otherId, $user['id']);
        $stmt->execute();
        $stmt->close();

        // Fetch conversation
        $stmt = $db->prepare('
            SELECT m.*, u.name AS sender_name, u.photo AS sender_photo
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?)
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.sent_at ASC
        ');
        $stmt->bind_param('iiii', $user['id'], $otherId, $otherId, $user['id']);
        $stmt->execute();
        $msgs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $db->close();
        respond(true, 'Messages fetched.', $msgs);
    }

    // GET /messages.php  → fetch all contacts (people this user has chatted with)
    $stmt = $db->prepare('
        SELECT DISTINCT
            u.id, u.name, u.photo, u.role,
            (SELECT message FROM messages
             WHERE (sender_id = ? AND receiver_id = u.id)
                OR (sender_id = u.id AND receiver_id = ?)
             ORDER BY sent_at DESC LIMIT 1) AS last_message,
            (SELECT sent_at FROM messages
             WHERE (sender_id = ? AND receiver_id = u.id)
                OR (sender_id = u.id AND receiver_id = ?)
             ORDER BY sent_at DESC LIMIT 1) AS last_time,
            (SELECT COUNT(*) FROM messages
             WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) AS unread_count
        FROM users u
        JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = ?)
                        OR (m.sender_id = ? AND m.receiver_id = u.id)
        WHERE u.id != ?
        ORDER BY last_time DESC
    ');
    $id = $user['id'];
    $stmt->bind_param('iiiiiiii', $id,$id,$id,$id,$id,$id,$id,$id);
    $stmt->execute();
    $contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();
    respond(true, 'Contacts fetched.', $contacts);
}

// ════════════════════════════════
//  POST — Send a message
// ════════════════════════════════
if ($method === 'POST') {
    $body       = getBody();
    $receiverId = (int)($body['receiver_id'] ?? 0);
    $message    = clean($body['message']     ?? '');

    if (!$receiverId || !$message) {
        respond(false, 'Receiver and message are required.');
    }
    if ($receiverId === $user['id']) {
        respond(false, 'Cannot send message to yourself.');
    }

    // Check receiver exists
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
    $stmt->bind_param('i', $receiverId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) respond(false, 'Receiver not found.');
    $stmt->close();

    // Insert message
    $stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (?,?,?)');
    $stmt->bind_param('iis', $user['id'], $receiverId, $message);
    if (!$stmt->execute()) respond(false, 'Failed to send message.');
    $msgId = $stmt->insert_id;
    $stmt->close();
    $db->close();

    respond(true, 'Message sent.', [
        'id'          => $msgId,
        'sender_id'   => $user['id'],
        'receiver_id' => $receiverId,
        'message'     => $message,
        'sent_at'     => date('Y-m-d H:i:s'),
    ]);
}
