<?php
/**
 * Card footer with "Showing x–y of n" and the pager, for lists inside .ui-page.
 * Renders nothing when everything fits on one page.
 *
 * @var \yii\data\Pagination|null $pagination
 * @var int    $total
 * @var int    $count   rows on this page
 * @var string|null $noun  e.g. "projects" (optional)
 */

use yii\widgets\LinkPager;

if (!$pagination || $pagination->getPageCount() <= 1) {
    return;
}
$offset = $pagination->getOffset();
?>
<div class="card-footer bg-white ui-pager">
    <small class="text-muted">Showing <?= $offset + 1 ?>–<?= $offset + $count ?> of <?= number_format($total) ?><?= !empty($noun) ? ' ' . $noun : '' ?></small>
    <?= LinkPager::widget([
        'pagination' => $pagination,
        'options' => ['class' => 'pagination pagination-sm mb-0'],
        'linkContainerOptions' => ['class' => 'page-item'],
        'linkOptions' => ['class' => 'page-link'],
        'disabledListItemSubTagOptions' => ['tag' => 'span', 'class' => 'page-link'],
        'maxButtonCount' => 5,
        'firstPageLabel' => '«', 'lastPageLabel' => '»', 'prevPageLabel' => '‹', 'nextPageLabel' => '›',
    ]) ?>
</div>
