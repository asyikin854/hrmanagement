<?php
// CHIP calls this after a successful payment (success_callback). The body is not trusted: the purchase is
// fetched again from CHIP's API with our key and only a "paid" purchase made through our checkout counts.
declare(strict_types=1);
require __DIR__ . '/../app/lib/billing.php';

$in = json_decode((string)file_get_contents('php://input'), true);
$id = is_array($in) ? (string)($in['id'] ?? '') : '';
if ($id === '') {
    $id = (string)($_POST['id'] ?? '');
}
try {
    $r = process_purchase($id);
    header('Content-Type: text/plain');
    echo $r;
} catch (Throwable $e) {
    error_log('chip-callback: ' . $id . ': ' . $e->getMessage());
    http_response_code(500); // CHIP retries; the daily job also re-checks open payments
    echo 'retry';
}
