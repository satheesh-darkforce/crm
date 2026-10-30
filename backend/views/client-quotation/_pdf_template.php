<?php

/**
 * Quotation PDF body — exact port of delivery/views/quotation/_pdf_template.php.
 *
 * @var backend\models\ClientQuotation $model
 * @var backend\models\ClientModel|null $client
 * @var backend\models\Developer|null   $developer
 * @var backend\models\Developer|null   $additionalDeveloper
 * @var string|null                    $company_name
 * @var int                            $preview   optional flag
 */

use yii\helpers\Html;

$displayDate        = !empty($model->quote_date) ? date('Y-m-d', strtotime($model->quote_date)) : date('Y-m-d');
$displayQuoteNo     = trim((string) ($model->quote_no ?: '-'));
$client             = $client ?? ($model->client ?? null);
$displayCompany     = trim((string) ($company_name ?? ($client->company_name ?? $client->client_name ?? '-')));
$displayAddress     = trim((string) ($model->address ?: ($client->address ?? '')));
$displayClientGst   = trim((string) ($client->gst_no ?? ''));
$displaySubject     = trim((string) ($model->project_name ?? ''));

// Trial quotations may have no estimation cost — when blank (or zero) the
// Estimated Cost row is omitted entirely from the document.
$rawCost            = trim((string) ($model->estimation_cost ?? ''));
$hasCost            = ($rawCost !== '' && is_numeric($rawCost) && (float) $rawCost > 0);
$displayCost        = $hasCost ? $rawCost : '-';

$formatIndianAmount = static function ($amount): string {
    $amount = trim(str_replace(',', '', (string) $amount));
    if ($amount === '' || !is_numeric($amount)) {
        return (string) $amount;
    }
    $sign = '';
    if (strpos($amount, '-') === 0) {
        $sign   = '-';
        $amount = substr($amount, 1);
    }
    [$integerPart, $decimalPart] = array_pad(explode('.', $amount, 2), 2, '');

    if (strlen($integerPart) > 3) {
        $lastThree = substr($integerPart, -3);
        $remaining = substr($integerPart, 0, -3);
        $groups    = [];
        while (strlen($remaining) > 2) {
            array_unshift($groups, substr($remaining, -2));
            $remaining = substr($remaining, 0, -2);
        }
        if ($remaining !== '') {
            array_unshift($groups, $remaining);
        }
        $integerPart = implode(',', $groups) . ',' . $lastThree;
    }
    return $sign . $integerPart . ($decimalPart !== '' ? '.' . $decimalPart : '');
};

if ($displayCost !== '-' && is_numeric((string) $displayCost)) {
    $displayCost = $formatIndianAmount($displayCost);
}

$displayAlterReason               = trim((string) ($model->alter_reason ?? ''));
$displayDeveloperName             = trim((string) ($developer->developer_name     ?? ''));
$displayDeveloperEmail            = trim((string) ($developer->official_email_id  ?? $developer->email_id   ?? ''));
$displayDeveloperMobile           = trim((string) ($developer->official_mobile_no ?? $developer->mobile     ?? ''));
$displayAdditionalDeveloperName   = trim((string) ($additionalDeveloper->developer_name     ?? ''));
$displayAdditionalDeveloperEmail  = trim((string) ($additionalDeveloper->official_email_id  ?? $additionalDeveloper->email_id ?? ''));
$displayAdditionalDeveloperMobile = trim((string) ($additionalDeveloper->official_mobile_no ?? $additionalDeveloper->mobile   ?? ''));
?>

<style>
    body {
        font-family: "Times New Roman", serif;
        color: #111;
        font-size: 12px;
        margin: 0;
    }

    .proposal-page {
        position: relative;
        min-height: 940px;
        width: 100%;
        text-align: center;
        padding-top: 100px;
    }

    .proposal-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .proposal-card {
        border: 1px solid #222;
        background: #fff;
        width: 92%;
        margin: 0 auto;
        display: inline-block;
        text-align: left;
    }

    .proposal-title {
        background: #154d66;
        color: #fff;
        text-align: center;
        font-size: 18px;
        font-weight: 700;
        padding: 12px 10px;
        letter-spacing: 0.3px;
    }

    .proposal-table td {
        border: 1px solid #222;
        padding: 9px 8px;
        vertical-align: top;
        line-height: 1.35;
        font-size: 13px;
    }

    .proposal-label {
        width: 28%;
        font-weight: 700;
        background: #f7f7f7;
    }

    .proposal-contact-block {
        line-height: 1.55;
        min-height: 82px;
    }

    .proposal-contact-block + .proposal-contact-block {
        border-top: 1px solid #222;
    }

    .proposal-contact-title {
        font-weight: 700;
        margin-bottom: 2px;
    }

    .proposal-contact-name {
        display: block;
        font-weight: 700;
    }

    .proposal-company-name {
        display: block;
        font-weight: 700;
    }
</style>

<div class="proposal-page">
    <div class="proposal-card">
        <div class="proposal-title">PROJECT PROPOSAL</div>
        <table class="proposal-table">
            <tr>
                <td class="proposal-label">Date</td>
                <td><?= Html::encode($displayDate) ?></td>
            </tr>
            <tr>
                <td class="proposal-label">Subject</td>
                <td><?= nl2br(Html::encode($displaySubject !== '' ? $displaySubject : '-')) ?></td>
            </tr>
            <tr>
                <td class="proposal-label">To</td>
                <td>
                    <div class="proposal-company-name"><strong><?= Html::encode($displayCompany) ?></strong></div>
                    <?php if ($displayAddress !== ''): ?>
                        <?= nl2br(Html::encode($displayAddress)) ?><br>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="proposal-label">Contact Details</td>
                <td>
                    <div>
                        <?php if (!empty($client->contact_person)): ?>
                            <?= Html::encode($client->contact_person) ?><br>
                        <?php endif; ?>

                        <?php if (!empty($client->phone)): ?>
                            <?= Html::encode($client->phone) ?><br>
                        <?php endif; ?>

                        <?php if (!empty($client->email_id)): ?>
                            <?= Html::encode($client->email_id) ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="proposal-label">From</td>
                <td>Maestro Technology Services Pvt. ltd</td>
            </tr>
            <tr>
                <td class="proposal-label">Quotation No</td>
                <td><?= Html::encode($displayQuoteNo) ?></td>
            </tr>
            <?php if ($hasCost): ?>
            <tr>
                <td class="proposal-label">Estimated Cost</td>
                <td>₹<?= Html::encode($displayCost) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="proposal-label">Submitted By</td>
                <td style="padding:10px 8px;">
                    <?php if ($displayDeveloperName !== '' || $displayDeveloperEmail !== '' || $displayDeveloperMobile !== ''): ?>
                        <div class="proposal-contact-block">
                            <?php if ($displayDeveloperName !== ''): ?>
                                <div class="proposal-contact-name"><strong><?= Html::encode($displayDeveloperName) ?></strong></div>
                            <?php endif; ?>
                            <?php if ($displayDeveloperEmail !== ''): ?>
                                <?= Html::encode($displayDeveloperEmail) ?><br>
                            <?php endif; ?>
                            <?php if ($displayDeveloperMobile !== ''): ?>
                                <?= Html::encode($displayDeveloperMobile) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($displayAdditionalDeveloperName !== '' || $displayAdditionalDeveloperEmail !== '' || $displayAdditionalDeveloperMobile !== ''): ?>
                            <div class="proposal-contact-block">
                                <br>
                                <?php if ($displayAdditionalDeveloperName !== ''): ?>
                                    <div class="proposal-contact-name"><strong><?= Html::encode($displayAdditionalDeveloperName) ?></strong></div>
                                <?php endif; ?>
                                <?php if ($displayAdditionalDeveloperEmail !== ''): ?>
                                    <?= Html::encode($displayAdditionalDeveloperEmail) ?><br>
                                <?php endif; ?>
                                <?php if ($displayAdditionalDeveloperMobile !== ''): ?>
                                    <?= Html::encode($displayAdditionalDeveloperMobile) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($displayAdditionalDeveloperName !== '' || $displayAdditionalDeveloperEmail !== '' || $displayAdditionalDeveloperMobile !== ''): ?>
                        <div class="proposal-contact-block">
                            <br>
                            <?php if ($displayAdditionalDeveloperName !== ''): ?>
                                <div class="proposal-contact-name"><strong><?= Html::encode($displayAdditionalDeveloperName) ?></strong></div>
                            <?php endif; ?>
                            <?php if ($displayAdditionalDeveloperEmail !== ''): ?>
                                <?= Html::encode($displayAdditionalDeveloperEmail) ?><br>
                            <?php endif; ?>
                            <?php if ($displayAdditionalDeveloperMobile !== ''): ?>
                                <?= Html::encode($displayAdditionalDeveloperMobile) ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>
