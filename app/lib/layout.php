<?php
// Page frame for the account pages (/app/) and the owner dashboard (/admin/), in the website's style.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function page_head(string $title, ?array $u = null, string $home = '/app/'): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    $l = lang();
    $self = strtok((string)($_SERVER['REQUEST_URI'] ?? '/app/'), '?');
    $qs = $_GET;
    unset($qs['lang']);
    $base = $self . '?' . ($qs ? http_build_query($qs) . '&' : '');
    echo '<!doctype html><html lang="' . $l . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<title>' . h($title) . ' | Excellent Synergy Resources</title><meta name="robots" content="noindex, nofollow">'
        . '<meta name="theme-color" content="#2F6F62"><link rel="icon" type="image/png" sizes="32x32" href="/assets/img/icon-32.png">'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="/assets/css/style.css"><link rel="stylesheet" href="/app/portal.css?v=1"></head><body>'
        . '<header class="top"><div class="wrap"><a class="logo-tile" href="' . h($home) . '"><img src="/assets/img/logo.png" width="422" height="138" alt="Excellent Synergy Resources" style="height:38px;width:auto"></a>'
        . '<div class="portal-actions"><div class="langsw" role="group" aria-label="Language / Bahasa">'
        . '<a href="' . h($base . 'lang=ms') . '" lang="ms" aria-current="' . ($l === 'ms' ? 'true' : 'false') . '">BM</a>'
        . '<a href="' . h($base . 'lang=en') . '" lang="en" aria-current="' . ($l === 'en' ? 'true' : 'false') . '">EN</a></div>';
    if ($u) {
        echo '<form method="post" action="/app/logout.php">' . csrf_field()
            . '<button class="btn small" type="submit">' . t('Log keluar', 'Log out') . '</button></form>';
    }
    echo '</div></div></header><main class="portal">';
}

function page_foot(): void
{
    echo '</main><footer><div class="wrap"><span>© ' . date('Y') . ' Excellent Synergy Resources · <a href="tel:+601116320694">011-1632 0694</a> · '
        . '<a href="mailto:work@esrmy.com">work@esrmy.com</a></span><span><a href="/' . (lang() === 'en' ? 'en/' : '') . '">'
        . t('Laman web', 'Website') . '</a></span></div></footer></body></html>';
}

function flash(string $kind, string $msg): string
{
    return '<p class="notice ' . h($kind) . '" role="' . ($kind === 'bad' ? 'alert' : 'status') . '">' . $msg . '</p>';
}

function safe_next(string $next, string $fallback): string
{
    return preg_match('#^/(app|admin)/[A-Za-z0-9/_.?=&%-]*$#', $next) && !str_contains($next, '//') ? $next : $fallback;
}
