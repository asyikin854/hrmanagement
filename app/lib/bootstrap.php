<?php
// Shared setup for the customer portal, the app data API, CHIP callbacks and the admin dashboard.
// Secrets (database password, API keys) live in esr-config.php ONE FOLDER ABOVE the web root, never in this
// public repository. See DEPLOY.md, "Customer accounts".
declare(strict_types=1);
defined('ESR') || define('ESR', true);

const SCHEMA_VERSION = 1;
const PLANS = [
    'quarterly' => ['price' => 55000, 'months' => 3, 'code' => 'Q',
        'ms' => 'Langganan 3 bulan: sistem HR, gaji dan kewangan', 'en' => '3-month subscription: HR, payroll and finance system'],
    'yearly' => ['price' => 199900, 'months' => 12, 'code' => 'Y',
        'ms' => 'Langganan tahunan: sistem HR, gaji dan kewangan', 'en' => 'Yearly subscription: HR, payroll and finance system'],
];

function web_root(): string
{
    return rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') ?: dirname(__DIR__, 2);
}

function cfg(?string $key = null, mixed $default = null): mixed
{
    static $c = null;
    if ($c === null) {
        $c = [];
        $file = getenv('ESR_CONFIG') ?: dirname(web_root()) . '/esr-config.php';
        if (is_file($file)) {
            $c = (array)require $file;
        }
        // The CHIP key may also sit in the older chip-config.php next to it
        $chip = dirname(web_root()) . '/chip-config.php';
        if (empty($c['chip_api_key']) && is_file($chip)) {
            $x = (array)require $chip;
            $c['chip_api_key'] = $x['api_key'] ?? '';
            $c['chip_brand_id'] = $x['brand_id'] ?? '';
        }
        $c += [
            'site' => 'https://hrmanagement.esrmy.com',
            'mail_from' => 'work@esrmy.com',
            'mail_name' => 'Excellent Synergy Resources',
            'owner_email' => 'work@esrmy.com',
            'data_dir' => dirname(web_root()) . '/esr-data',
            'ai_model' => 'claude-opus-5-5',
            'ai_monthly_limit' => 300,
            'chip_api_key' => getenv('CHIP_API_KEY') ?: '',
            'chip_brand_id' => getenv('CHIP_BRAND_ID') ?: '',
        ];
    }
    return $key === null ? $c : ($c[$key] ?? $default);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $d = cfg('db');
    if (!is_array($d) || empty($d['name'])) {
        fail_page(503, 'The database is not set up yet (esr-config.php).');
    }
    $pdo = new PDO('mysql:host=' . ($d['host'] ?? 'localhost') . ';dbname=' . $d['name'] . ';charset=utf8mb4',
        (string)($d['user'] ?? ''), (string)($d['pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    $pdo->exec("SET time_zone = '+08:00'");
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    try {
        $v = (int)$pdo->query("SELECT v FROM meta WHERE k = 'schema'")->fetchColumn();
        if ($v >= SCHEMA_VERSION) {
            return;
        }
    } catch (PDOException) {
        // first run: tables don't exist yet
    }
    $sql = [
        "CREATE TABLE IF NOT EXISTS meta (k VARCHAR(32) PRIMARY KEY, v VARCHAR(255) NOT NULL)",
        "CREATE TABLE IF NOT EXISTS tenants (
            id INT AUTO_INCREMENT PRIMARY KEY, company VARCHAR(160) NOT NULL, phone VARCHAR(32) NOT NULL DEFAULT '',
            plan VARCHAR(16) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'active', lang CHAR(2) NOT NULL DEFAULT 'ms',
            starts_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, reminded_for DATETIME NULL,
            notes TEXT NULL, created_at DATETIME NOT NULL, KEY (expires_at)) CHARACTER SET utf8mb4",
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY, tenant_id INT NULL, email VARCHAR(190) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL DEFAULT '', role VARCHAR(16) NOT NULL, password_hash VARCHAR(255) NULL,
            active TINYINT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL, last_login_at DATETIME NULL,
            KEY (tenant_id)) CHARACTER SET utf8mb4",
        "CREATE TABLE IF NOT EXISTS tokens (
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at DATETIME NOT NULL, KEY (user_id)) CHARACTER SET utf8mb4",
        "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY, purchase_id VARCHAR(64) NULL UNIQUE, tenant_id INT NULL,
            email VARCHAR(190) NOT NULL, name VARCHAR(160) NOT NULL, company VARCHAR(160) NOT NULL, phone VARCHAR(32) NOT NULL,
            plan VARCHAR(16) NOT NULL, amount_sen INT NOT NULL, status VARCHAR(16) NOT NULL, kind VARCHAR(16) NULL,
            lang CHAR(2) NOT NULL DEFAULT 'ms', period_from DATETIME NULL, period_to DATETIME NULL, note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL, paid_at DATETIME NULL, KEY (status), KEY (paid_at), KEY (tenant_id)) CHARACTER SET utf8mb4",
        "CREATE TABLE IF NOT EXISTS counters (tenant_id INT PRIMARY KEY, v BIGINT NOT NULL)",
        "CREATE TABLE IF NOT EXISTS docs (
            tenant_id INT NOT NULL, app VARCHAR(8) NOT NULL, col VARCHAR(64) NOT NULL, id VARCHAR(160) NOT NULL,
            data LONGTEXT NOT NULL, ver BIGINT NOT NULL, deleted TINYINT NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL,
            PRIMARY KEY (tenant_id, app, col, id), KEY (tenant_id, app, ver)) CHARACTER SET utf8mb4",
        "CREATE TABLE IF NOT EXISTS assets (
            id CHAR(32) PRIMARY KEY, tenant_id INT NOT NULL, mime VARCHAR(100) NOT NULL, size INT NOT NULL,
            created_at DATETIME NOT NULL, KEY (tenant_id)) CHARACTER SET utf8mb4",
        "CREATE TABLE IF NOT EXISTS ai_usage (
            tenant_id INT NOT NULL, month CHAR(7) NOT NULL, requests INT NOT NULL DEFAULT 0,
            input_tokens BIGINT NOT NULL DEFAULT 0, output_tokens BIGINT NOT NULL DEFAULT 0, PRIMARY KEY (tenant_id, month))",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45) NOT NULL, email VARCHAR(190) NOT NULL, at DATETIME NOT NULL,
            KEY (ip, at), KEY (email, at)) CHARACTER SET utf8mb4",
        "CREATE TABLE IF NOT EXISTS email_log (
            id INT AUTO_INCREMENT PRIMARY KEY, to_email VARCHAR(190) NOT NULL, subject VARCHAR(255) NOT NULL,
            ok TINYINT NOT NULL, created_at DATETIME NOT NULL, KEY (created_at)) CHARACTER SET utf8mb4",
    ];
    foreach ($sql as $q) {
        $pdo->exec($q);
    }
    $pdo->prepare("REPLACE INTO meta (k, v) VALUES ('schema', ?)")->execute([(string)SCHEMA_VERSION]);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function q(string $sql, array $args = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fail_page(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/* ---------- sessions and the logged-in user ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
    session_name('ESRSESS');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function current_user(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    start_session();
    $id = (int)($_SESSION['uid'] ?? 0);
    $u = null;
    if ($id) {
        $row = q('SELECT u.*, t.company, t.plan, t.status AS tenant_status, t.expires_at, t.starts_at
                  FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id WHERE u.id = ? AND u.active = 1', [$id])->fetch();
        if ($row && (int)($_SESSION['pwv'] ?? 0) === pw_version($row)) {
            $u = $row;
        } else {
            unset($_SESSION['uid']);
        }
    }
    return $u;
}

// Changes when the password changes, so other sessions are logged out after a reset
function pw_version(array $u): int
{
    return crc32((string)($u['password_hash'] ?? ''));
}

function log_in(array $u): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['pwv'] = pw_version($u);
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
}

function log_out(): void
{
    start_session();
    $_SESSION = [];
    session_regenerate_id(true);
}

function subscription_active(array $u): bool
{
    return $u['role'] === 'superadmin'
        || ($u['tenant_status'] === 'active' && strtotime((string)$u['expires_at']) > time());
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        fail_page(400, 'Session expired. Go back, refresh the page and try again.');
    }
}

// Login throttling: 8 failed tries per email or 20 per IP in 15 minutes
function too_many_attempts(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - 900);
    $byEmail = (int)q('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND at > ?', [$email, $since])->fetchColumn();
    $byIp = (int)q('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > ?', [client_ip(), $since])->fetchColumn();
    return $byEmail >= 8 || $byIp >= 20;
}

function record_failed_login(string $email): void
{
    q('INSERT INTO login_attempts (ip, email, at) VALUES (?, ?, ?)', [client_ip(), $email, now()]);
    q('DELETE FROM login_attempts WHERE at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
}

/* ---------- set-password links ---------- */

function make_password_link(int $userId, int $days = 7): string
{
    $raw = bin2hex(random_bytes(24));
    q('INSERT INTO tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
        [$userId, hash('sha256', $raw), date('Y-m-d H:i:s', time() + $days * 86400), now()]);
    return rtrim((string)cfg('site'), '/') . '/app/set-password.php?token=' . $raw;
}

function find_token(string $raw): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $raw)) {
        return null;
    }
    $t = q('SELECT t.*, u.email, u.name FROM tokens t JOIN users u ON u.id = t.user_id
            WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > ? AND u.active = 1',
        [hash('sha256', $raw), now()])->fetch();
    return $t ?: null;
}

/* ---------- email ---------- */

function send_mail(string $to, string $subject, string $body): bool
{
    $from = (string)cfg('mail_from');
    $name = (string)cfg('mail_name');
    $smtp = cfg('smtp');
    try {
        if (cfg('mail_to_files')) { // local testing only: write emails to files instead of sending
            @mkdir(cfg('data_dir') . '/mail', 0700, true);
            $ok = (bool)file_put_contents(cfg('data_dir') . '/mail/' . date('Ymd-His-') . bin2hex(random_bytes(3)) . '.txt',
                "To: $to\nSubject: $subject\n\n$body");
        } else $ok = is_array($smtp) && !empty($smtp['host'])
            ? smtp_send($smtp, $from, $name, $to, $subject, $body)
            : mail($to, mime_header($subject), $body, implode("\r\n", [
                'From: ' . mime_header($name) . ' <' . $from . '>',
                'Reply-To: ' . (string)cfg('owner_email'),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ]), '-f' . $from);
    } catch (Throwable $e) {
        error_log('esr mail: ' . $e->getMessage());
        $ok = false;
    }
    try {
        q('INSERT INTO email_log (to_email, subject, ok, created_at) VALUES (?, ?, ?, ?)', [$to, mb_substr($subject, 0, 255), $ok ? 1 : 0, now()]);
    } catch (Throwable) {
    }
    return $ok;
}

function mime_header(string $s): string
{
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

// Minimal SMTP client (STARTTLS or SSL, AUTH LOGIN), used when esr-config.php has an 'smtp' block
function smtp_send(array $s, string $from, string $name, string $to, string $subject, string $body): bool
{
    $port = (int)($s['port'] ?? 587);
    $secure = (string)($s['secure'] ?? ($port === 465 ? 'ssl' : 'tls'));
    $fp = stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . $port, $en, $es, 20);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: $es");
    }
    stream_set_timeout($fp, 20);
    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read): string {
        if ($c !== '') {
            fwrite($fp, $c . "\r\n");
        }
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) {
            throw new RuntimeException('SMTP: ' . trim($r));
        }
        return $r;
    };
    $host = parse_url((string)cfg('site'), PHP_URL_HOST) ?: 'localhost';
    $cmd('', [220]);
    $cmd("EHLO $host", [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $cmd("EHLO $host", [250]);
    }
    if (!empty($s['user'])) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode((string)$s['user']), [334]);
        $cmd(base64_encode((string)$s['pass']), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    $msg = 'Date: ' . date('r') . "\r\nFrom: " . mime_header($name) . " <$from>\r\nTo: <$to>\r\nSubject: " . mime_header($subject)
        . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
        . preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $body));
    $cmd($msg . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}

/* ---------- language for portal pages ---------- */

function lang(): string
{
    static $l = null;
    if ($l !== null) {
        return $l;
    }
    $q = (string)($_GET['lang'] ?? '');
    if ($q === 'ms' || $q === 'en') {
        setcookie('esr_lang', $q, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        return $l = $q;
    }
    return $l = (($_COOKIE['esr_lang'] ?? '') === 'en' ? 'en' : 'ms');
}

function t(string $ms, string $en): string
{
    return lang() === 'en' ? $en : $ms;
}

function fmt_date(?string $d): string
{
    if (!$d) {
        return '-';
    }
    $ts = strtotime($d);
    $m = lang() === 'en'
        ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        : ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];
    return date('j', $ts) . ' ' . $m[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function rm(int $sen): string
{
    return 'RM' . number_format($sen / 100, $sen % 100 ? 2 : 0);
}
