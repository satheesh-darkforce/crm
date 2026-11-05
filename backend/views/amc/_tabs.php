<?php
/**
 * Tabs across the AMC lists.
 *
 * @var \yii\web\View $this
 * @var string $active  route id of the current list
 */

use common\components\UserAccess;
use yii\bootstrap5\Html;

$tabs = [
    'projects'   => 'AMC Projects',
    'active'     => 'Active',
    'renew-list' => 'Due this month',
    'ended'      => 'Ended',
];
?>
<nav class="ui-tabs" aria-label="AMC lists">
    <?php foreach ($tabs as $route => $label): ?>
        <?php if (UserAccess::checkAccess('amc/' . $route)): ?>
            <?= Html::a($label, [$route], ['class' => $route === $active ? 'active' : '', 'aria-current' => $route === $active ? 'page' : null]) ?>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
