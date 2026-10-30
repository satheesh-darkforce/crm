<?php
/** @var \yii\web\View $this */
/** @var \yii\data\SqlDataProvider $dataProvider */
/** @var \backend\models\ClientModel[] $clients */
/** @var string $clientF */
/** @var string $titleF */
/** @var string $typeF */
/** @var string $warrantyF */
/** @var string $stateF */
/** @var string $startF */
/** @var string $endF */

$this->title = 'Ended AMC';
echo $this->render('_list', [
    'dataProvider' => $dataProvider,
    'clients'      => $clients,
    'clientF'      => $clientF,
    'titleF'       => $titleF,
    'typeF'        => $typeF,
    'warrantyF'    => $warrantyF,
    'stateF'       => $stateF,
    'startF'       => $startF,
    'endF'         => $endF,
    'heading'      => 'Ended AMC',
    'resetUrl'     => ['ended'],
    'mode'         => 'ended',
]);
