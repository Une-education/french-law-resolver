<?php
declare(strict_types=1);

$config = require __DIR__ . '/stripe_config.php';
header('Content-Type: application/json; charset=utf-8');

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_USERPWD => $config['stripe_secret_key'] . ':',
    CURLOPT_POSTFIELDS => http_build_query([
        'payment_method_types' => ['card'],
        'mode' => 'payment',
        'success_url' => 'https://legal.une.education/france/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => 'https://legal.une.education/france/cancel.php',
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => 2900,
                'product_data' => [
                    'name' => 'French Law & Dispute Resolver - 50 000 Crédits',
                    'description' => 'Accès API & MCP aux 6 outils juridiques français',
                ],
            ],
            'quantity' => 1,
        ]],
        'metadata' => [
            'mode' => 'create_new_key',
            'credits' => 50000,
        ],
    ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur Stripe', 'details' => json_decode((string)$response, true)]);
    exit;
}

$data = json_decode((string)$response, true);
echo json_encode(['checkout_url' => $data['url'], 'session_id' => $data['id']]);
