<?php
// AI for the HR and Finance apps: forwards one Claude Messages API request and returns the reply.
// The tool loop for Ask HR / Ask Finance runs in the browser (app/runtime.js), so each loop step is one call
// here. Each company gets ai_monthly_limit requests a month (esr-config.php) so costs stay predictable.
declare(strict_types=1);
require __DIR__ . '/../lib/api.php';

const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';

$u = api_user(true);
$key = (string)cfg('anthropic_api_key');
if ($key === '') {
    api_error(503, 'unavailable', t('Ciri AI belum diaktifkan.', 'AI features are not switched on yet.'));
}
$tid = (int)$u['tenant_id'];
$month = date('Y-m');
$used = (int)q('SELECT requests FROM ai_usage WHERE tenant_id = ? AND month = ?', [$tid, $month])->fetchColumn();
$limit = (int)cfg('ai_monthly_limit', 300);
if ($used >= $limit) {
    api_error(429, 'resource-exhausted', t(
        "Had AI bulanan akaun anda ($limit permintaan) telah dicapai. Ia ditetapkan semula pada awal bulan depan, atau hubungi kami untuk menambahnya.",
        "Your account has used this month's AI allowance ($limit requests). It resets at the start of next month, or contact us to raise it."));
}

$b = body_json(16 * 1024 * 1024);
$messages = $b['messages'] ?? null;
if (!is_array($messages) || !$messages || count($messages) > 80) {
    api_error(400, 'invalid-argument', 'Bad request');
}
foreach ($messages as $m) {
    if (!is_object($m) || !in_array($m->role ?? '', ['user', 'assistant'], true) || !(is_string($m->content ?? null) || is_array($m->content ?? null))) {
        api_error(400, 'invalid-argument', 'Bad message');
    }
}
$tools = [];
foreach ((array)($b['tools'] ?? []) as $t) {
    if (!is_object($t) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string)($t->name ?? ''))) {
        api_error(400, 'invalid-argument', 'Bad tool');
    }
    $tools[] = ['name' => $t->name, 'description' => mb_substr((string)($t->description ?? ''), 0, 2000),
        'input_schema' => is_object($t->input_schema ?? null) ? $t->input_schema : (object)['type' => 'object']];
}
if (count($tools) > 30) {
    api_error(400, 'invalid-argument', 'Too many tools');
}

$system = 'You are the assistant inside an HR, payroll and finance system used by a small company in Malaysia. '
    . 'Use only the data you are given or can look up with the tools; never invent people, numbers or records.';
if (!empty($b['json'])) {
    $system .= ' Reply with only the JSON value the request asks for: no code fences and no other text.';
}
$req = [
    'model' => (string)cfg('ai_model', 'claude-opus-5-5'),
    'max_tokens' => 16000,
    'system' => $system,
    'messages' => $messages,
    'output_config' => ['effort' => (string)cfg('ai_effort', 'medium')],
    // If the model declines a request, the API retries it on a suitable fallback model in the same call
    'fallbacks' => 'default',
];
if ($tools) {
    $req['tools'] = $tools;
}

$ch = curl_init(ANTHROPIC_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $key,
        'anthropic-version: 2026-10-21',
        'anthropic-beta: server-side-fallback-2026-07-01',
    ],
    CURLOPT_POSTFIELDS => json_encode($req, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
set_time_limit(320);
$res = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);
$r = is_string($res) ? json_decode($res, false) : null;

if ($code !== 200 || !is_object($r)) {
    error_log('esr ai: HTTP ' . $code . ': ' . substr((string)$res, 0, 500));
    $busy = in_array($code, [429, 500, 502, 503, 529], true);
    api_error($busy ? 503 : 502, 'unavailable', $busy
        ? t('Perkhidmatan AI sibuk sekarang. Cuba lagi sebentar.', 'The AI service is busy right now. Please try again in a moment.')
        : t('Permintaan AI gagal. Cuba lagi.', 'The AI request failed. Please try again.'));
}

$in = (int)($r->usage->input_tokens ?? 0) + (int)($r->usage->cache_read_input_tokens ?? 0) + (int)($r->usage->cache_creation_input_tokens ?? 0);
q('INSERT INTO ai_usage (tenant_id, month, requests, input_tokens, output_tokens) VALUES (?, ?, 1, ?, ?)
   ON DUPLICATE KEY UPDATE requests = requests + 1, input_tokens = input_tokens + VALUES(input_tokens), output_tokens = output_tokens + VALUES(output_tokens)',
    [$tid, $month, $in, (int)($r->usage->output_tokens ?? 0)]);

if (($r->stop_reason ?? '') === 'refusal') {
    api_error(422, 'refused', t('AI tidak dapat membantu dengan permintaan ini.', "The AI couldn't help with this request."));
}
// Content goes back unchanged (thinking blocks included) so the browser can continue a tool loop with it
json_out(['content' => $r->content ?? [], 'stop_reason' => $r->stop_reason ?? null]);
