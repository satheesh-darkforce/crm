<?php
/**
 * IoT Product Barcode (loaded into a Bootstrap modal via AJAX).
 * Uses the JsBarcode library (CODE128) for client-side rendering.
 *
 * @var \yii\web\View             $this
 * @var \backend\models\IotProduct $model
 * @var string                    $location
 */

use yii\bootstrap5\Html;

$pn   = Html::encode($model->product_name ?: '-');
$part = Html::encode($model->part_number ?: '-');
$code = Html::encode($model->product_code ?: '');
$loc  = Html::encode($location ?: '-');
?>
<div class="iot-barcode text-center">
    <div id="iot-barcode-card" class="d-inline-block border rounded p-3 bg-white" style="min-width:300px">
        <div class="fw-semibold small mb-1"><?= $code ?></div>
        <div class="small mb-1"><?= $pn ?></div>
        <div class="small mb-1"><?= $part ?></div>
        <div class="small mb-2 text-muted"><?= $loc ?></div>
        <svg id="iot-barcode-svg" style="max-width:280px;height:80px"></svg>
    </div>

    <div class="mt-3 d-flex justify-content-center gap-2">
        <button type="button" class="btn btn-primary btn-sm" id="iot-barcode-print">
            <i class="fas fa-comment"></i> Print
        </button>
    </div>
</div>

<script>
(function () {
    var code = <?= json_encode($model->product_code ?: '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var productName = <?= json_encode($model->product_name ?: '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var partNumber  = <?= json_encode($model->part_number ?: '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var location    = <?= json_encode($location ?: '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function render() {
        if (typeof JsBarcode !== 'function') return;
        if (!code) return;
        try {
            JsBarcode('#iot-barcode-svg', code, { format: 'CODE128', displayValue: false, height: 60, margin: 4 });
        } catch (e) { /* ignore malformed codes */ }
    }

    function loadJsBarcode(cb) {
        if (typeof JsBarcode === 'function') { cb(); return; }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';
        s.onload  = cb;
        s.onerror = function () { console.warn('JsBarcode failed to load'); };
        document.head.appendChild(s);
    }

    loadJsBarcode(render);

    document.getElementById('iot-barcode-print').addEventListener('click', function () {
        var svgEl = document.getElementById('iot-barcode-svg');
        if (!svgEl) return;
        var svgMarkup = new XMLSerializer().serializeToString(svgEl);
        var truncName = productName.length > 28 ? productName.slice(0, 28) : productName;

        var w = window.open('', '_blank', 'width=480,height=520');
        if (!w) return;
        w.document.write('<!DOCTYPE html><html><head><title>Barcode - ' + code + '</title>' +
            '<style>body{font-family:sans-serif;margin:0;padding:16px;display:flex;justify-content:center;align-items:center;min-height:100vh}' +
            'table{border-collapse:collapse;border:1px solid #000}' +
            'td{border:1px solid #000;padding:10px 15px;font-size:16px;text-align:center}' +
            'svg{width:260px;height:60px}</style></head><body>' +
            '<table>' +
              '<tr><td>' + code + '</td></tr>' +
              '<tr><td>' + truncName + '</td></tr>' +
              '<tr><td>' + partNumber + '</td></tr>' +
              '<tr><td>' + location + '</td></tr>' +
              '<tr><td>' + svgMarkup + '</td></tr>' +
            '</table>' +
            '<script>window.onload=function(){setTimeout(function(){window.print();},200);};<\/script>' +
            '</body></html>');
        w.document.close();
    });
}());
</script>
