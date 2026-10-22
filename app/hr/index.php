<?php
// The hr app for logged-in customers. Data comes from app/api/ through app/runtime.js.
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u) {
    header('Location: /app/login.php?next=' . rawurlencode('/app/hr/'), true, 303);
    exit;
}
if ($u['role'] === 'superadmin' || !subscription_active($u)) {
    header('Location: /app/', true, 303);
    exit;
}
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');
readfile(__DIR__ . '/app.html');
