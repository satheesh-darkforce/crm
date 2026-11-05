<?php
/**
 * Empty state for redesigned pages (inside .ui-page).
 *
 * @var string      $icon    Font Awesome icon name without "fa-" (e.g. "bullseye")
 * @var string      $title
 * @var string|null $text
 * @var string|null $action  ready-made HTML (e.g. a button), optional
 * @var bool|null   $small
 */

use yii\bootstrap5\Html;
?>
<div class="ui-empty<?= !empty($small) ? ' sm' : '' ?>">
    <span class="icon" aria-hidden="true"><i class="fas fa-<?= Html::encode($icon) ?>"></i></span>
    <h6><?= Html::encode($title) ?></h6>
    <?php if (!empty($text)): ?><p><?= Html::encode($text) ?></p><?php endif; ?>
    <?= $action ?? '' ?>
</div>
