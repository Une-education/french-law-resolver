<?php
declare(strict_types=1);

$config = require __DIR__ . '/stripe_config.php';

$payload = @file_get_contents('php://input');
$data = json_decode($payload, true);

if (!isset($data['type']) || $data['type'] !== 'checkout.session.completed') {
    http_response_code(200);
    exit('Event ignored');
}

$session = $data['data']['object'];
$session_id = $session['id'] ?? '';
$client_email = $session['customer_details']['email'] ?? 'unknown_client';
$amount_total = $session['amount_total'] ?? 0;
$metadata = $session['metadata'] ?? [];

$mode = $metadata['mode'] ?? 'create_new_key';
$credits_to_add = (int)($metadata['credits'] ?? 50000);
$api_key_id = isset($metadata['api_key_id']) ? (int)$metadata['api_key_id'] : null;

$dbPath = $config['db_path'] ?? '/var/www/atoa-api/gateway.sqlite';
$db = new PDO("sqlite:{$dbPath}", null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$stmt = $db->prepare('SELECT id FROM stripe_transactions WHERE stripe_session_id = ?');
$stmt->execute([$session_id]);
if ($stmt->fetch()) {
    http_response_code(200);
    exit('Already processed');
}

$plain_key_created = null;

if ($mode === 'reload_key' && $api_key_id !== null) {
    $up = $db->prepare('UPDATE api_keys SET balance_credits = balance_credits + ? WHERE id = ?');
    $up->execute([$credits_to_add, $api_key_id]);
    $target_key_id = $api_key_id;
} else {
    $raw_token = bin2hex(random_bytes(16));
    $plain_key_created = "sk_live_{$raw_token}";
    $key_hash = hash('sha256', $plain_key_created);
    $label = "stripe:" . $client_email;

    $ins_key = $db->prepare('INSERT INTO api_keys (key_hash, label, tier, balance_credits, is_active) VALUES (?, ?, "standard", ?, 1)');
    $ins_key->execute([$key_hash, $label, $credits_to_add]);
    $target_key_id = (int)$db->lastInsertId();
}

$ins_tx = $db->prepare('INSERT INTO stripe_transactions (key_id, stripe_session_id, amount_cents, credits_added, status) VALUES (?, ?, ?, ?, "completed")');
$ins_tx->execute([$target_key_id, $session_id, $amount_total, $credits_to_add]);

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'mode' => $mode,
    'key_id' => $target_key_id,
    'new_key_issued' => (bool)$plain_key_created,
    'credits_added' => $credits_to_add
]);
