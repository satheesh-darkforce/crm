<?php
use yii\bootstrap5\Html;

$this->title = 'Update Project';
?>
<div class="project-update">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0 fw-semibold">Update Project</h4>
        <?= Html::a('Back', ['index'], [
            'class' => 'btn btn-secondary btn-sm',
            'onclick' => 'event.preventDefault(); window.location.replace(this.href); return false;',
        ]) ?>
    </div>
    <?= $this->render('_form', compact('project', 'clients', 'developers', 'existingPhases')) ?>
</div>
