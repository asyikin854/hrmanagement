<?php
// Emails a link to set a new password. Always shows the same message, so it can't be used to test emails.
declare(strict_types=1);
require __DIR__ . '/lib/layout.php';

$sent = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && !too_many_attempts($email)) {
        record_failed_login($email); // counts towards the limit, so the form can't be used to flood an inbox
        $row = q('SELECT * FROM users WHERE email = ? AND active = 1', [$email])->fetch();
        if ($row) {
            $link = make_password_link((int)$row['id'], 2);
            send_mail($email, t('Tetapkan semula kata laluan anda', 'Reset your password'), t(
                "Hai {$row['name']},\n\nTetapkan kata laluan baru di sini (pautan sah selama 2 hari):\n$link\n\nJika anda tidak memintanya, abaikan e-mel ini.\n\nExcellent Synergy Resources",
                "Hi {$row['name']},\n\nSet a new password here (the link works for 2 days):\n$link\n\nIf you didn't ask for this, ignore this email.\n\nExcellent Synergy Resources"));
        }
    }
    $sent = true;
}
page_head(t('Lupa kata laluan', 'Forgot your password'));
echo '<div class="wrap narrow"><h1>' . t('Lupa kata laluan', 'Forgot your password') . '</h1>';
if ($sent) {
    echo '<p class="lead" style="margin:16px 0">' . t('Jika e-mel itu mempunyai akaun, kami telah menghantar pautan untuk menetapkan kata laluan baru. Semak peti masuk dan folder spam anda.',
        "If that email has an account, we've sent a link to set a new password. Check your inbox and spam folder.")
        . '</p><a href="/app/login.php">' . t('Kembali ke log masuk', 'Back to log in') . '</a>';
} else {
    echo '<p class="lead" style="margin:12px 0 24px">' . t('Masukkan e-mel akaun anda dan kami akan menghantar pautan.', "Enter your account's email and we'll send you a link.") . '</p>'
        . '<form method="post" class="card stack">' . csrf_field()
        . '<label class="field">' . t('E-mel', 'Email') . '<input type="email" name="email" autocomplete="username" required autofocus></label>'
        . '<button class="btn primary" type="submit">' . t('Hantar pautan', 'Send the link') . '</button></form>';
}
echo '</div>';
page_foot();
