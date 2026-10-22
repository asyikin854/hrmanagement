<?php
// One-time setup of the owner's dashboard login. Needs admin_setup_token from esr-config.php, and stops
// working once an owner login exists.
declare(strict_types=1);
require __DIR__ . '/../app/lib/layout.php';

$exists = (int)q("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();
if ($exists) {
    header('Location: /admin/', true, 303);
    exit;
}
$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();
    $token = (string)cfg('admin_setup_token');
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pw = (string)($_POST['password'] ?? '');
    if (too_many_attempts('setup')) {
        $err = t('Terlalu banyak cubaan. Tunggu 15 minit.', 'Too many tries. Wait 15 minutes.');
    } elseif (strlen($token) < 12 || !hash_equals($token, (string)($_POST['token'] ?? ''))) {
        record_failed_login('setup');
        $err = t('Kod persediaan salah.', 'Wrong setup code.');
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 10) {
        $err = t('Isi nama, e-mel dan kata laluan sekurang-kurangnya 10 aksara.', 'Add a name, email and a password of at least 10 characters.');
    } elseif (q('SELECT id FROM users WHERE email = ?', [$email])->fetch()) {
        $err = t('E-mel ini sudah digunakan oleh akaun pelanggan.', 'This email is already used by a customer account.');
    } else {
        q('INSERT INTO users (tenant_id, email, name, role, password_hash, created_at) VALUES (NULL, ?, ?, ?, ?, ?)',
            [$email, $name, 'superadmin', password_hash($pw, PASSWORD_DEFAULT), now()]);
        log_in(q('SELECT * FROM users WHERE email = ?', [$email])->fetch());
        header('Location: /admin/', true, 303);
        exit;
    }
}
page_head(t('Persediaan papan pemuka', 'Dashboard setup'), null, '/admin/');
echo '<div class="wrap narrow"><h1>' . t('Persediaan papan pemuka', 'Dashboard setup') . '</h1><p class="lead" style="margin:12px 0 24px">'
    . t('Cipta log masuk pemilik untuk memantau pelanggan. Kod persediaan ialah admin_setup_token dalam esr-config.php.',
        "Create the owner login for monitoring subscribers. The setup code is admin_setup_token in esr-config.php.") . '</p>';
if ($err) {
    echo flash('bad', h($err));
}
echo '<form method="post" class="card stack">' . csrf_field()
    . '<label class="field">' . t('Kod persediaan', 'Setup code') . '<input name="token" autocomplete="off" required></label>'
    . '<label class="field">' . t('Nama', 'Name') . '<input name="name" required></label>'
    . '<label class="field">' . t('E-mel', 'Email') . '<input type="email" name="email" autocomplete="username" required></label>'
    . '<label class="field">' . t('Kata laluan (sekurang-kurangnya 10 aksara)', 'Password (at least 10 characters)') . '<input type="password" name="password" minlength="10" autocomplete="new-password" required></label>'
    . '<button class="btn primary" type="submit">' . t('Cipta log masuk', 'Create login') . '</button></form></div>';
page_foot();
