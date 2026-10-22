<?php
// Files uploaded in the apps (candidates' CVs). Stored outside the web root, one folder per company.
// POST (multipart "file") uploads, GET ?id= downloads, POST ?op=delete&id= removes.
declare(strict_types=1);
require __DIR__ . '/../lib/api.php';

const ALLOWED = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp', 'image/gif',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];

// Plain GET links (opening a CV) can't send the X-ESR header; reads are safe without it
$u = api_user(true, ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET');
$tid = (int)$u['tenant_id'];
$dir = rtrim((string)cfg('data_dir'), '/') . '/assets/' . $tid;
$id = (string)($_GET['id'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $a = preg_match('/^[a-f0-9]{32}$/', $id) ? q('SELECT * FROM assets WHERE id = ? AND tenant_id = ?', [$id, $tid])->fetch() : null;
    if (!$a || !is_file("$dir/$id")) {
        api_error(404, 'not-found', 'File not found');
    }
    $inline = $a['mime'] === 'application/pdf' || str_starts_with($a['mime'], 'image/');
    header('Content-Type: ' . $a['mime']);
    header('Content-Length: ' . filesize("$dir/$id"));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="file-' . $id . '"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: sandbox');
    header('Cache-Control: private, max-age=86400');
    readfile("$dir/$id");
    exit;
}

if (($_GET['op'] ?? '') === 'delete') {
    if (preg_match('/^[a-f0-9]{32}$/', $id) && q('DELETE FROM assets WHERE id = ? AND tenant_id = ?', [$id, $tid])->rowCount()) {
        @unlink("$dir/$id");
    }
    json_out(['ok' => true]);
}

$f = $_FILES['file'] ?? null;
if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
    api_error(400, 'invalid-argument', t('Muat naik gagal.', 'Upload failed.'));
}
if ($f['size'] > 10 * 1024 * 1024) {
    api_error(413, 'invalid-argument', t('Fail melebihi 10 MB.', 'The file is over 10 MB.'));
}
$used = (int)q('SELECT COALESCE(SUM(size), 0) FROM assets WHERE tenant_id = ?', [$tid])->fetchColumn();
if ($used + (int)$f['size'] > (int)cfg('storage_limit_mb', 2048) * 1024 * 1024) {
    api_error(413, 'resource-exhausted', t('Ruang storan fail syarikat anda sudah penuh. Hubungi kami.', "Your company's file storage is full. Contact us."));
}
$mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
if ($mime === 'application/zip' && str_ends_with(strtolower((string)$f['name']), '.docx')) {
    $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
}
if (!in_array($mime, ALLOWED, true)) {
    api_error(415, 'invalid-argument', t('Jenis fail ini tidak disokong.', 'That file type is not supported.'));
}
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    error_log('esr assets: cannot create ' . $dir);
    api_error(500, 'unavailable', t('Tidak dapat menyimpan fail.', "Couldn't store the file."));
}
$id = bin2hex(random_bytes(16));
if (!move_uploaded_file($f['tmp_name'], "$dir/$id")) {
    api_error(500, 'unavailable', t('Tidak dapat menyimpan fail.', "Couldn't store the file."));
}
q('INSERT INTO assets (id, tenant_id, mime, size, created_at) VALUES (?, ?, ?, ?, ?)', [$id, $tid, $mime, (int)$f['size'], now()]);
json_out(['id' => $id]);
