<?php

/** @var yii\web\View $this */
/** @var yii\bootstrap5\ActiveForm $form */
/** @var \backend\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$params      = Yii::$app->params;
$companyName = $params['login.companyName'] ?? 'Your Company';
$tagline     = $params['login.tagline'] ?? '';
$logoPath    = $params['login.logo'] ?? null;
$accent      = $params['login.accent'] ?? '#0d9488';
$logoUrl     = $logoPath ? Yii::getAlias('@web/' . ltrim($logoPath, '/')) : null;

// Initials for the monogram shown when no logo is configured.
$initials = '';
foreach (preg_split('/\s+/', trim($companyName)) as $word) {
    if ($word !== '' && mb_strlen($initials) < 2) {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
}

$this->title = 'Sign in — ' . $companyName;

// blank layout does not include Font Awesome — register it here for the field icons.
$this->registerCssFile('@web/css/font-awesome/css/font-awesome.min.css');
$this->registerCssFile('@web/css/all.min.css');
?>
<style>
    .lg {
        --lg-accent: <?= Html::encode($accent) ?>;
        --lg-band: color-mix(in srgb, var(--lg-accent) 32%, #0b1416);
        --lg-bg: #f2f4f3;
        --lg-card: #ffffff;
        --lg-text: #14201f;
        --lg-muted: #66706f;
        --lg-line: #dde3e1;
        --lg-danger: #c0392b;

        height: 100vh;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        background: var(--lg-bg);
        color: var(--lg-text);
        font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    /* Header band */
    .lg-band {
        position: relative;
        flex: 0 0 auto;
        height: 42vh;
        min-height: 260px;
        background-color: var(--lg-band);
        background-image: repeating-linear-gradient(-45deg, rgba(255,255,255,.035) 0 1px, transparent 1px 14px);
        color: #fff;
        padding: 1.5rem 2.5rem;
    }
    .lg-band::after { /* accent rule along the bottom edge */
        content: '';
        position: absolute;
        left: 0; right: 0; bottom: 0;
        height: 4px;
        background: var(--lg-accent);
    }
    .lg-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }
    .lg-brand {
        display: flex;
        align-items: center;
        gap: .8rem;
        min-width: 0;
    }
    .lg-brand img {
        height: 40px;
        width: auto;
        background: #fff;
        padding: 4px 8px;
        border-radius: 6px;
    }
    .lg-mono {
        width: 40px; height: 40px;
        flex: 0 0 40px;
        display: grid;
        place-items: center;
        border-radius: 8px;
        background: var(--lg-accent);
        font-weight: 700;
        font-size: .95rem;
        letter-spacing: .03em;
    }
    .lg-brand .name {
        font-weight: 600;
        font-size: 1.05rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lg-time {
        font-size: .82rem;
        color: rgba(255,255,255,.7);
        text-align: right;
        white-space: nowrap;
    }
    .lg-time strong { color: #fff; font-weight: 600; font-variant-numeric: tabular-nums; }

    /* Centre column — pulled up so the card overlaps the band */
    .lg-main {
        flex: 1 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 0 1rem 2rem;
        margin-top: calc(-42vh + 7.5rem);
        position: relative;
        z-index: 1;
    }
    .lg-intro {
        text-align: center;
        color: #fff;
        margin-bottom: 1.5rem;
    }
    .lg-intro h1 {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0 0 .35rem;
        letter-spacing: -.01em;
    }
    .lg-intro p {
        margin: 0;
        color: rgba(255,255,255,.72);
        font-size: .95rem;
    }

    .lg-card {
        width: 100%;
        max-width: 420px;
        background: var(--lg-card);
        border: 1px solid var(--lg-line);
        border-radius: 12px;
        box-shadow: 0 18px 40px -18px rgba(11, 20, 22, .35);
        padding: 2rem 2rem 1.75rem;
    }
    .lg-card h2 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 1.4rem;
    }

    .lg-field { position: relative; margin-bottom: 1rem; }
    .lg-field .fi {
        position: absolute;
        left: .95rem;
        top: 1.45rem;
        transform: translateY(-50%);
        color: #9aa4a2;
        font-size: .9rem;
        pointer-events: none;
        z-index: 4;
        transition: color .15s ease;
    }
    .lg-field .form-floating > .form-control {
        height: 2.9rem;
        min-height: 2.9rem;
        padding: 1.2rem .9rem .3rem 2.6rem;
        border: 1px solid var(--lg-line);
        border-radius: 8px;
        background: #fafbfb;
        font-size: .95rem;
        color: var(--lg-text);
    }
    .lg-field .form-floating > label {
        padding: .85rem .9rem 0 2.6rem;
        color: var(--lg-muted);
        font-size: .9rem;
        font-weight: 400;
        text-transform: none;
        letter-spacing: 0;
        height: auto;
    }
    .lg-field .form-floating > .form-control:focus ~ label,
    .lg-field .form-floating > .form-control:not(:placeholder-shown) ~ label {
        transform: scale(.78) translateY(-.55rem) translateX(.75rem);
        opacity: 1;
        color: var(--lg-muted);
    }
    .lg-field .form-floating > label::after { background: transparent !important; }
    .lg .lg-field .form-control:focus {
        background: #fff;
        border-color: var(--lg-accent) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--lg-accent) 18%, transparent) !important;
    }
    .lg-field:focus-within .fi { color: var(--lg-accent); }
    .lg-field .form-control.is-invalid {
        border-color: var(--lg-danger) !important;
        background-image: none;
    }
    .lg-field .invalid-feedback:not(:empty) {
        display: block;
        font-size: .78rem;
        color: var(--lg-danger);
        margin-top: .3rem;
    }
    .lg-field .pw-toggle {
        position: absolute;
        right: .4rem;
        top: 1.45rem;
        transform: translateY(-50%);
        z-index: 5;
        border: 0;
        background: transparent;
        color: #8a9492;
        padding: .3rem .5rem;
        border-radius: 6px;
    }
    .lg-field .pw-toggle:hover { color: var(--lg-text); background: #eef1f0; }
    .lg-field.has-toggle .form-control { padding-right: 2.6rem; }

    .lg-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: .25rem 0 1.4rem;
        font-size: .85rem;
    }
    .lg-row .form-check { margin: 0; min-height: 0; }
    .lg-row .form-check-input { margin-top: .15rem; }
    .lg-row .form-check-input:checked {
        background-color: var(--lg-accent);
        border-color: var(--lg-accent);
    }
    .lg-row .form-check-input:focus {
        border-color: var(--lg-accent) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--lg-accent) 18%, transparent) !important;
    }
    .lg-row .form-check-label { color: var(--lg-muted); }

    .lg-btn {
        width: 100%;
        border: 0;
        border-radius: 8px;
        background: var(--lg-accent);
        color: #fff;
        font-weight: 600;
        font-size: .95rem;
        padding: .8rem 1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        transition: filter .15s ease, transform .05s ease;
    }
    .lg-btn:hover { filter: brightness(.92); }
    .lg-btn:active { transform: translateY(1px); }
    .lg-btn:focus-visible { outline: 3px solid color-mix(in srgb, var(--lg-accent) 35%, transparent); outline-offset: 2px; }

    .lg-alert {
        display: flex;
        gap: .6rem;
        align-items: flex-start;
        background: #fdf1ef;
        border: 1px solid #f3c9c3;
        color: var(--lg-danger);
        border-radius: 8px;
        font-size: .85rem;
        padding: .65rem .8rem;
        margin-bottom: 1.2rem;
    }

    .lg-note {
        max-width: 420px;
        width: 100%;
        margin-top: 1.25rem;
        display: flex;
        gap: .65rem;
        align-items: flex-start;
        font-size: .82rem;
        color: var(--lg-muted);
        line-height: 1.5;
    }
    .lg-note i { color: var(--lg-accent); margin-top: .2rem; }

    .lg-foot {
        flex: 0 0 auto;
        text-align: center;
        font-size: .76rem;
        color: #8a9492;
        padding: 1rem;
    }

    @media (max-width: 576px) {
        .lg-band { padding: 1.1rem; height: 300px; min-height: 0; }
        .lg-main { margin-top: -190px; }
        .lg-time .date { display: none; }
        .lg-intro h1 { font-size: 1.45rem; }
        .lg-card { padding: 1.5rem 1.25rem; }
    }
</style>

<div class="lg">
    <header class="lg-band">
        <div class="lg-bar">
            <div class="lg-brand">
                <?php if ($logoUrl): ?>
                    <img src="<?= Html::encode($logoUrl) ?>" alt="<?= Html::encode($companyName) ?>">
                <?php else: ?>
                    <span class="lg-mono" aria-hidden="true"><?= Html::encode($initials) ?></span>
                <?php endif; ?>
                <span class="name"><?= Html::encode($companyName) ?></span>
            </div>
            <div class="lg-time">
                <strong id="lg-clock"><?= date('h:i A') ?></strong>
                <span class="date">&nbsp;·&nbsp;<?= date('l, d M Y') ?></span>
            </div>
        </div>
    </header>

    <main class="lg-main">
        <div class="lg-intro">
            <h1>Welcome back</h1>
            <?php if ($tagline !== ''): ?><p><?= Html::encode($tagline) ?></p><?php endif; ?>
        </div>

        <div class="lg-card">
            <h2>Sign in to continue</h2>

            <?php if (Yii::$app->session->hasFlash('error')): ?>
                <div class="lg-alert">
                    <i class="fas fa-circle-exclamation mt-1"></i>
                    <span><?= Html::encode(Yii::$app->session->getFlash('error')) ?></span>
                </div>
            <?php endif; ?>

            <?php $form = ActiveForm::begin([
                'id' => 'login-form',
                'fieldConfig' => [
                    'options'      => ['class' => 'lg-field'],
                    'inputOptions' => ['class' => 'form-control'],
                ],
            ]); ?>

                <?= $form->field($model, 'username', [
                    'template' => "<i class=\"fi fas fa-user\"></i>\n<div class=\"form-floating\">{input}\n{label}</div>\n{error}",
                ])->textInput([
                    'autofocus'    => true,
                    'placeholder'  => 'Username',
                    'autocomplete' => 'username',
                ])->label('Username') ?>

                <?= $form->field($model, 'password', [
                    'options'  => ['class' => 'lg-field has-toggle'],
                    'template' => "<i class=\"fi fas fa-lock\"></i>\n<div class=\"form-floating\">{input}\n{label}</div>\n<button type=\"button\" class=\"pw-toggle\" id=\"login-password-toggle\" tabindex=\"-1\" aria-label=\"Show password\"><i class=\"fas fa-eye\"></i></button>\n{error}",
                ])->passwordInput([
                    'placeholder'  => 'Password',
                    'autocomplete' => 'current-password',
                    'id'           => 'loginform-password',
                ])->label('Password') ?>

                <div class="lg-row">
                    <?= $form->field($model, 'rememberMe', ['options' => ['class' => 'm-0']])
                        ->checkbox()->label('Keep me signed in') ?>
                </div>

                <?= Html::submitButton('Sign in <i class="fas fa-arrow-right-to-bracket"></i>', ['class' => 'lg-btn', 'name' => 'login-button']) ?>

            <?php ActiveForm::end(); ?>
        </div>

        <div class="lg-note">
            <i class="fas fa-circle-info"></i>
            <span>Forgot your password or need an account? Contact your system administrator.</span>
        </div>
    </main>

    <footer class="lg-foot">
        &copy; <?= date('Y') ?> <?= Html::encode($companyName) ?>. All rights reserved.
    </footer>
</div>
<?php
$this->registerJs(<<<JS
jQuery('#login-password-toggle').on('click', function() {
    var \$btn   = jQuery(this);
    var \$input = jQuery('#loginform-password');
    var isText = \$input.attr('type') === 'text';
    \$input.attr('type', isText ? 'password' : 'text');
    \$btn.attr('aria-label', isText ? 'Show password' : 'Hide password');
    \$btn.html(isText ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>');
});

(function tick() {
    var el = document.getElementById('lg-clock');
    if (!el) return;
    el.textContent = new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
    setTimeout(tick, 15000);
})();
JS, \yii\web\View::POS_READY);
?>
