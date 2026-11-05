<?php
/**
 * A follow-up date with an Overdue / Today / Tomorrow chip.
 *
 * @var string|null $label  formatted date to show
 * @var string      $state  Lead::followUpState() result ('' = no chip)
 */

use yii\bootstrap5\Html;

$chips = ['overdue' => 'Overdue', 'today' => 'Today', 'tomorrow' => 'Tomorrow'];
?>
<span class="fu-due">
    <span><?= Html::encode($label ?: '-') ?></span>
    <?php if (isset($chips[$state])): ?><span class="fu-chip is-<?= $state ?>"><?= $chips[$state] ?></span><?php endif; ?>
</span>
