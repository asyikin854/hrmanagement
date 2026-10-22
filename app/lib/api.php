<?php
// Helpers for the JSON endpoints in app/api/ that the HR and Finance apps call through app/runtime.js.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(int $status, string $code, string $message): void
{
    json_out(['error' => $message, 'code' => $code], $status);
}

// Every API call that changes something must come from our own pages: the custom header can't be sent by another site's form or
// script without a CORS preflight, which this server never allows.
function api_user(bool $needEdit = true, bool $needHeader = true): array
{
    if ($needHeader && ($_SERVER['HTTP_X_ESR'] ?? '') !== '1') {
        api_error(400, 'invalid-argument', 'Bad request');
    }
    $u = current_user();
    if (!$u) {
        api_error(401, 'unauthenticated', 'Please log in.');
    }
    if ($u['role'] === 'superadmin' || !$u['tenant_id']) {
        api_error(403, 'permission-denied', 'This account has no company.');
    }
    if (!subscription_active($u)) {
        api_error(402, 'revoked', t('Langganan anda telah tamat.', 'Your subscription has ended.'));
    }
    if ($needEdit && !can_edit($u)) {
        api_error(403, 'permission-denied', t('Akaun anda hanya boleh daftar masuk.', 'Your account can only clock in.'));
    }
    return $u;
}

function can_edit(array $u): bool
{
    return in_array($u['role'], ['owner', 'admin'], true);
}

function body_json(int $maxBytes): array
{
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($len > $maxBytes) {
        api_error(413, 'invalid-argument', t('Data terlalu besar.', 'That is too large to save.'));
    }
    $raw = (string)file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if (strlen($raw) > $maxBytes) {
        api_error(413, 'invalid-argument', t('Data terlalu besar.', 'That is too large to save.'));
    }
    $j = json_decode($raw, false); // objects stay objects, so {} is not turned into []
    if (!is_object($j)) {
        api_error(400, 'invalid-argument', 'Bad request');
    }
    return (array)$j;
}

function public_user_id(int $id): string
{
    return 'u' . $id;
}
