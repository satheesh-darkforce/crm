<?php

/**
 * Standalone page shown when the database cannot be reached.
 * Rendered by backend\components\ErrorHandler — must not touch the DB,
 * layouts or asset bundles, so everything is inline.
 *
 * @var \Throwable $exception
 */

use yii\helpers\Html;

$params      = Yii::$app->params;
$companyName = $params['login.companyName'] ?? 'Your Company';
$logoPath    = $params['login.logo'] ?? null;
$accent      = $params['login.accent'] ?? '#0d9488';

try {
    $baseUrl = Yii::$app->request->baseUrl;
} catch (\Throwable $e) {
    $baseUrl = '';
}
$logoUrl  = $logoPath ? $baseUrl . '/' . ltrim($logoPath, '/') : null;
$loginUrl = $baseUrl . '/site/login';

$initials = '';
foreach (preg_split('/\s+/', trim($companyName)) as $word) {
    if ($word !== '' && mb_strlen($initials) < 2) {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connection lost — <?= Html::encode($companyName) ?></title>
    <link rel="icon" type="image/png" href="<?= Html::encode($baseUrl) ?>/images/favicon.png">
    <style>
        :root {
            --accent: <?= Html::encode($accent) ?>;
            --band: color-mix(in srgb, var(--accent) 32%, #0b1416);
            --bg: #f2f4f3;
            --card: #ffffff;
            --text: #14201f;
            --muted: #66706f;
            --line: #dde3e1;
            --warn: #b45309;
            --warn-bg: #fef3c7;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        .band {
            position: relative;
            height: 38vh;
            min-height: 220px;
            background-color: var(--band);
            background-image: repeating-linear-gradient(-45deg, rgba(255,255,255,.035) 0 1px, transparent 1px 14px);
            color: #fff;
            padding: 1.5rem 2.5rem;
        }
        .band::after {
            content: '';
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 4px;
            background: var(--accent);
        }
        .brand { display: flex; align-items: center; gap: .8rem; }
        .brand img { height: 40px; background: #fff; padding: 4px 8px; border-radius: 6px; }
        .mono {
            width: 40px; height: 40px;
            display: grid; place-items: center;
            border-radius: 8px;
            background: var(--accent);
            font-weight: 700; font-size: .95rem;
        }
        .brand .name { font-weight: 600; font-size: 1.05rem; }
        main {
            flex: 1 0 auto;
            display: flex;
            justify-content: center;
            padding: 0 1rem 2rem;
            margin-top: calc(-38vh + 6.5rem);
            position: relative;
        }
        .card {
            width: 100%;
            max-width: 440px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 18px 40px -18px rgba(11,20,22,.35);
            padding: 2.25rem 2rem 2rem;
            text-align: center;
        }
        .icon {
            width: 64px; height: 64px;
            margin: 0 auto 1.25rem;
            display: grid; place-items: center;
            border-radius: 50%;
            background: var(--warn-bg);
            color: var(--warn);
        }
        h1 { font-size: 1.35rem; margin: 0 0 .5rem; }
        p { margin: 0 0 1rem; color: var(--muted); font-size: .93rem; line-height: 1.55; }
        .status {
            display: inline-flex; align-items: center; gap: .45rem;
            font-size: .8rem; color: var(--muted);
            margin-bottom: 1.5rem;
        }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--warn); animation: pulse 1.6s infinite; }
        @keyframes pulse { 50% { opacity: .3; } }
        .actions { display: flex; gap: .6rem; justify-content: center; flex-wrap: wrap; }
        .btn {
            appearance: none;
            border: 1px solid var(--accent);
            background: var(--accent);
            color: #fff;
            font: inherit; font-size: .9rem; font-weight: 600;
            padding: .6rem 1.3rem;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { filter: brightness(.93); }
        .btn-outline { background: transparent; color: var(--accent); }
        details {
            margin-top: 1.5rem;
            text-align: left;
            font-size: .78rem;
            color: var(--muted);
            border-top: 1px solid var(--line);
            padding-top: 1rem;
        }
        details pre { white-space: pre-wrap; word-break: break-word; margin: .5rem 0 0; }
        footer { text-align: center; font-size: .75rem; color: var(--muted); padding: 1rem; }
    </style>
</head>
<body>
<header class="band">
    <div class="brand">
        <?php if ($logoUrl): ?>
            <img src="<?= Html::encode($logoUrl) ?>" alt="<?= Html::encode($companyName) ?>">
        <?php else: ?>
            <span class="mono"><?= Html::encode($initials) ?></span>
        <?php endif; ?>
        <span class="name"><?= Html::encode($companyName) ?></span>
    </div>
</header>

<main>
    <div class="card">
        <div class="icon" aria-hidden="true">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="9" ry="3"/>
                <path d="M3 5v6c0 1.66 4 3 9 3 1.3 0 2.53-.09 3.64-.25"/>
                <path d="M3 11v6c0 1.66 4 3 9 3 .9 0 1.77-.04 2.6-.13"/>
                <path d="M21 5v5"/>
                <line x1="17" y1="15" x2="22" y2="20"/>
                <line x1="22" y1="15" x2="17" y2="20"/>
            </svg>
        </div>
        <h1>Database connection lost</h1>
        <p>We can't reach the database right now, so the application is temporarily unavailable. Your data is safe — please try again in a moment.</p>
        <div class="status"><span class="dot"></span><span id="retry-text">Retrying automatically in <strong id="countdown">30</strong>s</span></div>
        <div class="actions">
            <button type="button" class="btn" onclick="location.reload()">Try again</button>
            <a class="btn btn-outline" href="<?= Html::encode($loginUrl) ?>">Back to sign in</a>
        </div>
        <?php if (YII_DEBUG): ?>
            <details>
                <summary>Technical details (debug mode only)</summary>
                <pre><?= Html::encode(get_class($exception) . ': ' . $exception->getMessage()) ?></pre>
            </details>
        <?php endif; ?>
    </div>
</main>

<footer>If this keeps happening, please contact your system administrator.</footer>

<script>
    (function () {
        var left = 30, el = document.getElementById('countdown');
        var timer = setInterval(function () {
            left -= 1;
            if (el) el.textContent = left;
            if (left <= 0) { clearInterval(timer); location.reload(); }
        }, 1000);
    })();
</script>
</body>
</html>
