<?php
// Document store for the HR and Finance apps. Every company's data is kept apart by tenant_id, and each
// app ("hr", "fin") has its own collections. GET returns documents changed since a version number; POST
// writes one document (set, update or delete).
// Staff accounts only see the clock settings (hr pub/clock) and their own clock record (hr clock/<their id>).
declare(strict_types=1);
require __DIR__ . '/../lib/api.php';

$u = api_user(false);
$tid = (int)$u['tenant_id'];
$app = (string)($_GET['app'] ?? '');
if (!in_array($app, ['hr', 'fin'], true)) {
    api_error(400, 'invalid-argument', 'Unknown app');
}
$edit = can_edit($u);
$own = public_user_id((int)$u['id']);
if (!$edit && $app !== 'hr') {
    api_error(403, 'permission-denied', 'Not allowed');
}

function staff_may(string $col, string $id, string $own, bool $write): bool
{
    return ($col === 'clock' && $id === $own) || (!$write && $col === 'pub' && $id === 'clock');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $since = max(0, (int)($_GET['since'] ?? 0));
    if ($edit) {
        $rows = q('SELECT col, id, data, deleted, ver FROM docs WHERE tenant_id = ? AND app = ? AND ver > ?' . ($since ? '' : ' AND deleted = 0'),
            [$tid, $app, $since])->fetchAll();
    } else {
        $rows = q("SELECT col, id, data, deleted, ver FROM docs WHERE tenant_id = ? AND app = 'hr' AND ver > ?
                   AND ((col = 'clock' AND id = ?) OR (col = 'pub' AND id = 'clock'))", [$tid, $since, $own])->fetchAll();
    }
    $max = $since;
    $out = [];
    foreach ($rows as $r) {
        $max = max($max, (int)$r['ver']);
        $out[] = $r['deleted']
            ? ['col' => $r['col'], 'id' => $r['id'], 'deleted' => true]
            : ['col' => $r['col'], 'id' => $r['id'], 'data' => json_decode($r['data'], false)];
    }
    if (!$out) {
        $max = max($since, (int)q('SELECT COALESCE(MAX(ver), 0) FROM docs WHERE tenant_id = ? AND app = ?', [$tid, $app])->fetchColumn());
    }
    // Hand-built so empty objects in stored documents stay {} rather than becoming []
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ver' => $max, 'docs' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$b = body_json(8 * 1024 * 1024);
$op = (string)($b['op'] ?? '');
$col = (string)($b['col'] ?? '');
$id = (string)($b['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $col) || !preg_match('/^[A-Za-z0-9_.:@-]{1,160}$/', $id) || !in_array($op, ['set', 'update', 'delete'], true)) {
    api_error(400, 'invalid-argument', 'Bad document path');
}
if (!$edit && !staff_may($col, $id, $own, true)) {
    api_error(403, 'permission-denied', 'Not allowed');
}
$data = $b['data'] ?? null;
if ($op !== 'delete' && !is_object($data)) {
    api_error(400, 'invalid-argument', 'Document data must be an object');
}

$pdo = db();
$pdo->beginTransaction();
try {
    // The counter row lock orders writes per company, so version numbers follow commit order
    q('INSERT INTO counters (tenant_id, v) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE v = LAST_INSERT_ID(v + 1)', [$tid]);
    $ver = (int)$pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    $cur = q('SELECT data, deleted FROM docs WHERE tenant_id = ? AND app = ? AND col = ? AND id = ? FOR UPDATE', [$tid, $app, $col, $id])->fetch();
    $exists = $cur && !$cur['deleted'];
    if ($op === 'delete') {
        if ($cur) {
            q('UPDATE docs SET deleted = 1, data = ?, ver = ?, updated_at = ? WHERE tenant_id = ? AND app = ? AND col = ? AND id = ?',
                ['{}', $ver, now(), $tid, $app, $col, $id]);
        }
        $pdo->commit();
        json_out(['ok' => true, 'doc' => ['col' => $col, 'id' => $id, 'deleted' => true]]);
    }
    if ($op === 'update') {
        if (!$exists) {
            $pdo->rollBack();
            api_error(404, 'not-found', 'That record no longer exists.');
        }
        $doc = json_decode($cur['data'], false);
        foreach (get_object_vars($data) as $k => $v) {
            $parts = explode('.', (string)$k); // "a.b" updates a nested field, as in Firestore
            $node = $doc;
            foreach (array_slice($parts, 0, -1) as $p) {
                if (!isset($node->$p) || !is_object($node->$p)) {
                    $node->$p = new stdClass();
                }
                $node = $node->$p;
            }
            $last = end($parts);
            $node->$last = $v;
        }
        $data = $doc;
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (strlen($json) > 8 * 1024 * 1024) {
        $pdo->rollBack();
        api_error(413, 'invalid-argument', t('Rekod ini terlalu besar.', 'That record is too large.'));
    }
    q('INSERT INTO docs (tenant_id, app, col, id, data, ver, deleted, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?)
       ON DUPLICATE KEY UPDATE data = VALUES(data), ver = VALUES(ver), deleted = 0, updated_at = VALUES(updated_at)',
        [$tid, $app, $col, $id, $json, $ver, now()]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('esr data: ' . $e->getMessage());
    api_error(500, 'unavailable', t('Tidak dapat menyimpan. Cuba lagi.', "Couldn't save. Please try again."));
}
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo '{"ok":true,"doc":{"col":' . json_encode($col) . ',"id":' . json_encode($id) . ',"data":' . $json . '}}';
