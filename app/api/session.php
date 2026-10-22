<?php
// Who is logged in (GET), and names/emails of colleagues for the attendance list (POST ?op=profiles).
declare(strict_types=1);
require __DIR__ . '/../lib/api.php';

$u = api_user(false);
if (($_GET['op'] ?? '') === 'profiles') {
    $b = body_json(65536);
    $ids = [];
    foreach ((array)($b['ids'] ?? []) as $x) {
        if (is_string($x) && preg_match('/^u(\d{1,10})$/', $x, $m)) {
            $ids[] = (int)$m[1];
        }
    }
    $out = new stdClass();
    if ($ids && can_edit($u)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q("SELECT id, name, email FROM users WHERE tenant_id = ? AND id IN ($in)", array_merge([(int)$u['tenant_id']], $ids)) as $r) {
            $out->{public_user_id((int)$r['id'])} = ['name' => $r['name'], 'email' => $r['email']];
        }
    }
    json_out(['profiles' => $out]);
}
json_out([
    'user' => ['id' => public_user_id((int)$u['id']), 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']],
    'canEdit' => can_edit($u),
    'company' => $u['company'],
    'ai' => (string)cfg('anthropic_api_key') !== '',
    'expires' => $u['expires_at'],
]);
