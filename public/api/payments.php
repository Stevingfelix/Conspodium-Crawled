<?php
// api/payments.php - Payment Gateway Configuration & Transaction API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'public_config';

// PUBLIC CONFIG ENDPOINT (Used by in-modal checkout UI)
if ($action === 'public_config') {
    try {
        $stmt = $pdo->query("SELECT key, value FROM payment_settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        echo json_encode([
            "success" => true,
            "data" => [
                "paystack_public_key" => $settings['paystack_public_key'] ?? '',
                "paystack_enabled" => ($settings['paystack_enabled'] ?? '1') === '1',
                "stripe_public_key" => $settings['stripe_public_key'] ?? '',
                "stripe_enabled" => ($settings['stripe_enabled'] ?? '1') === '1',
                "default_gateway" => $settings['default_gateway'] ?? 'paystack'
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// RECORD COMPLETED TRANSACTION
if ($action === 'record_transaction') {
    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $ref = csp_sanitize($input['transaction_ref'] ?? 'TXN_' . time() . '_' . rand(1000, 9999));
    $gateway = csp_sanitize($input['gateway'] ?? 'paystack');
    $amount = floatval($input['amount'] ?? 0);
    $currency = csp_sanitize($input['currency'] ?? 'USD');
    $email = filter_var(trim($input['customer_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $name = csp_sanitize($input['customer_name'] ?? '');
    $tier = csp_sanitize($input['tier_name'] ?? 'Membership / Sponsorship');

    if (!$email || $amount <= 0) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Valid email and amount are required."]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO payment_transactions (transaction_ref, gateway, amount, currency, customer_email, customer_name, tier_name, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', CURRENT_TIMESTAMP)");
        $stmt->execute([$ref, $gateway, $amount, $currency, $email, $name, $tier]);

        // Auto-subscribe customer to 'sponsors' list segment
        try {
            $stmtSub = $pdo->prepare("INSERT OR REPLACE INTO subscribers (name, email, list_segment, status) VALUES (?, ?, 'sponsors', 'active')");
            $stmtSub->execute([$name, $email]);
        } catch (Exception $e) {}

        echo json_encode([
            "success" => true,
            "message" => "Transaction recorded successfully! Thank you for your support.",
            "transaction_ref" => $ref
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

// ADMIN PROTECTED ENDPOINTS BELOW
require_once __DIR__ . '/auth_guard.php';

if ($action === 'settings') {
    try {
        $stmt = $pdo->query("SELECT key, value FROM payment_settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }
        echo json_encode(["success" => true, "data" => $settings]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}

if ($action === 'save_settings') {
    $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    
    try {
        // Update existing, insert if new
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) as cnt FROM payment_settings WHERE key = ?");
        $stmtUpdate = $pdo->prepare("UPDATE payment_settings SET value = ? WHERE key = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO payment_settings (key, value) VALUES (?, ?)");
        
        $allowedKeys = [
            'paystack_public_key', 'paystack_secret_key', 'paystack_enabled',
            'stripe_public_key', 'stripe_secret_key', 'stripe_enabled', 'default_gateway'
        ];

        foreach ($allowedKeys as $k) {
            if (isset($input[$k])) {
                $stmtCheck->execute([$k]);
                $exists = $stmtCheck->fetch()['cnt'] > 0;
                if ($exists) {
                    $stmtUpdate->execute([(string)$input[$k], $k]);
                } else {
                    $stmtInsert->execute([$k, (string)$input[$k]]);
                }
            }
        }

        echo json_encode(["success" => true, "message" => "Payment settings saved successfully."]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
    exit();
}
