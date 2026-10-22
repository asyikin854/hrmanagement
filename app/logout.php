<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();
    log_out();
}
header('Location: /app/login.php', true, 303);
