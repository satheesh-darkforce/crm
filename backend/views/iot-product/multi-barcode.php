<?php
/**
 * IoT Multi-Barcode (loaded into a Bootstrap modal via AJAX).
 * Mirrors Yii1 iotproducts/multibarcode — renders one barcode card per
 * selected product and triggers a single combined print window.
 *
 * @var \yii\web\View               $this
 * @var \backend\models\IotProduct[] $models
 */

use yii\bootstrap5\Html;

$items = [];
foreach ($models as $m) {
    $items[] = [
        'code'         => (string)($m->product_code ?? ''),
        'product_name' => (string)($m->product_name ?? ''),
        'part_number'  => (string)($m->part_number  ?? ''),
        'location'     => (string)($m->location->location ?? ''),
    ];
}
?>
<div class="iot-multi-barcode">
    <div class="d-flex flex-wrap justify-content-center gap-3" id="iot-multi-barcode-list">
        <?php foreach ($items as $i => $it): ?>
            <div class="iot-mb-card border rounded p-2 bg-white text-center" style="min-width:230px">
                <div class="fw-semibold small mb-1"><?= Html::encode($it['code']) ?></div>
                <div class="small mb-1"><?= Html::encode($it['product_name']) ?></div>
                <div class="small mb-1"><?= Html::encode($it['part_number'] ?: '-') ?></div>
                <div class="small mb-2 text-muted"><?= Html::encode($it['location'] ?: '-') ?></div>
                <svg class="iot-mb-svg" data-code="<?= Html::encode($it['code']) ?>" style="max-width:200px;height:60px"></svg>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-3 d-flex justify-content-center gap-2">
        <button type="button" class="btn btn-primary btn-sm" id="iot-multi-barcode-print">
            <i class="fas fa-comment"></i> Print <?= count($items) ?> Barcode<?= count($items) === 1 ? '' : 's' ?>
        </button>
    </div>
</div>

<script>
(function () {
    var items = <?= json_encode($items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function renderAll() {
        if (typeof JsBarcode !== 'function') return;
        document.querySelectorAll('.iot-mb-svg').forEach(function (svg) {
            var code = svg.getAttribute('data-code');
            if (!code) return;
            try {
                JsBarcode(svg, code, { format: 'CODE128', displayValue: false, height: 50, margin: 4 });
            } catch (e) { /* ignore malformed codes */ }
        });
    }

    function loadJsBarcode(cb) {
        if (typeof JsBarcode === 'function') { cb(); return; }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';
        s.onload  = cb;
        s.onerror = function () { console.warn('JsBarcode failed to load'); };
        document.head.appendChild(s);
    }

    loadJsBarcode(renderAll);

    document.getElementById('iot-multi-barcode-print').addEventListener('click', function () {
        // Serialize each rendered SVG so the print window receives drawn barcodes.
        var svgs = document.querySelectorAll('.iot-mb-svg');
        var rendered = [];
        svgs.forEach(function (svg, idx) {
            rendered.push({
                item: items[idx] || {},
                svg:  new XMLSerializer().serializeToString(svg)
            });
        });
        if (!rendered.length) return;

        var w = window.open('', '_blank', 'width=900,height=700');
        if (!w) return;

        var html = '<!DOCTYPE html><html><head><title>Barcodes</title>' +
            '<style>' +
            'body{font-family:sans-serif;margin:0;padding:16px;}' +
            '.grid{display:flex;flex-wrap:wrap;gap:12px;justify-content:flex-start}' +
            'table{border-collapse:collapse;border:1px solid #000;page-break-inside:avoid}' +
            'td{border:1px solid #000;padding:8px 12px;font-size:14px;text-align:center}' +
            'svg{width:220px;height:55px}' +
            '@media print { .grid{gap:8px} }' +
            '</style></head><body><div class="grid">';

        rendered.forEach(function (r) {
            var name = (r.item.product_name || '').slice(0, 28);
            html += '<table>' +
                      '<tr><td>' + (r.item.code || '') + '</td></tr>' +
                      '<tr><td>' + name + '</td></tr>' +
                      '<tr><td>' + (r.item.part_number || '-') + '</td></tr>' +
                      '<tr><td>' + (r.item.location    || '-') + '</td></tr>' +
                      '<tr><td>' + r.svg + '</td></tr>' +
                    '</table>';
        });

        html += '</div>' +
            '<script>window.onload=function(){setTimeout(function(){window.print();},250);};<\/script>' +
            '</body></html>';

        w.document.write(html);
        w.document.close();
    });
}());
</script>
