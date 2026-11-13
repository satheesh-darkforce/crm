/**
 * Line-item editor (views/_ui/_line_items.php) — quotations and invoices.
 *
 * Product search (Select2 + product/search), fills description / HSN / unit /
 * price / GST from the product, live line amounts and totals (same maths as
 * LineItems::read() on the server, which recalculates on save).
 * Loaded by LineItemsAsset.
 */
(function ($) {
    'use strict';

    var TAX_INTRA = 1, TAX_INTER = 2, TAX_NONE = 3;

    function r2(v) { return Math.round((v + Number.EPSILON) * 100) / 100; }
    function money(v, dp) {
        dp = dp === undefined ? 2 : dp;
        return '₹' + Number(v).toLocaleString('en-IN', {minimumFractionDigits: dp, maximumFractionDigits: dp});
    }
    function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }

    function taxMode($ed) {
        var sel = $ed.data('tax-mode-input');
        var v = sel ? parseInt($(sel).val(), 10) : NaN;
        return isNaN(v) ? parseInt($ed.data('tax-mode'), 10) || TAX_INTRA : v;
    }

    function initProduct($row, $ed) {
        var $sel = $row.find('.js-li-product');
        if (!$.fn.select2 || $sel.data('select2')) { return; }
        $sel.select2({
            theme: 'krajee-bs5',
            width: '100%',
            allowClear: true,
            placeholder: 'Search products by name or code…',
            minimumInputLength: 0,
            ajax: {
                url: $ed.data('search-url'),
                dataType: 'json',
                delay: 250,
                data: function (p) { return {q: p.term || ''}; },
                processResults: function (r) {
                    return {results: ((r && r.results) || []).map(function (p) {
                        return {id: p.id, text: (p.code ? p.code + ' · ' : '') + p.name, product: p};
                    })};
                }
            },
            templateResult: function (item) {
                if (!item.product) { return item.text; }
                var p = item.product;
                var price = p.price === null ? 'Price on request' : money(p.price) + (p.unit ? ' / ' + p.unit : '');
                return $('<div class="li-opt"><div class="li-opt-name">' + esc(item.text) + '</div>' +
                    '<div class="li-opt-meta">' + esc([p.category, price, p.gst_rate === null ? '' : 'GST ' + p.gst_rate + '%'].filter(Boolean).join(' · ')) + '</div></div>');
            }
        }).on('select2:select', function (e) {
            var p = e.params.data.product;
            if (!p) { return; }
            $row.find('.js-li-desc').val(p.name);
            $row.find('.js-li-hsn').val(p.hsn || '');
            if (p.unit) { $row.find('.js-li-unit').val(p.unit).trigger('change.select2'); }
            $row.find('.js-li-price').val(p.price === null ? '' : p.price);
            if (p.gst_rate !== null) { $row.find('.js-li-gst').val(String(p.gst_rate)).trigger('change.select2'); }
            var $qty = $row.find('.js-li-qty');
            if (!$qty.val()) { $qty.val(1); }
            stepFor($row, $ed);
            recalc($ed);
            (p.price === null ? $row.find('.js-li-price') : $qty).trigger('focus').trigger('select');
        });
    }

    // Unit (searchable, 28 units) and GST rate as Select2, same look as every other dropdown.
    function initPickers($row) {
        if (!$.fn.select2) { return; }
        $row.find('.js-li-unit, .js-li-gst').each(function () {
            var $s = $(this);
            if ($s.data('select2')) { return; }
            var isUnit = $s.hasClass('js-li-unit');
            $s.select2({
                theme: 'krajee-bs5',
                width: '100%',
                dropdownAutoWidth: true,
                placeholder: isUnit ? 'Unit' : 'GST',
                minimumResultsForSearch: isUnit ? 6 : Infinity
            });
        });
    }

    // Count units take whole numbers; measured units allow decimals.
    function stepFor($row, $ed) {
        var counts = $ed.data('count-units') || [];
        var whole = counts.indexOf($row.find('.js-li-unit').val()) !== -1;
        $row.find('.js-li-qty').attr('step', whole ? '1' : 'any');
    }

    function recalc($ed) {
        var mode = taxMode($ed);
        var t = {sub: 0, disc: 0, taxable: 0, gst: 0, byRate: {}};
        $ed.find('[data-line]').each(function (i) {
            var $r = $(this);
            $r.find('.li-index').text(i + 1);
            var qty = num($r.find('.js-li-qty').val()), price = num($r.find('.js-li-price').val());
            var disc = Math.min(100, Math.max(0, num($r.find('.js-li-disc').val())));
            var rate = mode === TAX_NONE ? 0 : num($r.find('.js-li-gst').val());
            var gross = r2(qty * price), dAmt = r2(gross * disc / 100), taxable = r2(gross - dAmt), gst = r2(taxable * rate / 100);
            var filled = qty > 0 || price > 0;
            $r.find('.js-li-amount').text(filled ? money(r2(taxable + gst)) : '—');
            $r.find('.js-li-amount-sub').text(filled ? (money(taxable) + ' + ' + (mode === TAX_NONE ? 'no GST' : rate + '% GST')) : '');
            t.sub += gross; t.disc += dAmt; t.taxable += taxable; t.gst += gst;
            if (gst > 0) {
                t.byRate[rate] = t.byRate[rate] || {taxable: 0, gst: 0};
                t.byRate[rate].taxable += taxable; t.byRate[rate].gst += gst;
            }
        });
        $ed.find('.js-li-t-sub').text(money(r2(t.sub)));
        $ed.find('.js-li-t-disc').text('−' + money(r2(t.disc)));
        $ed.find('.js-li-t-disc-row').toggleClass('d-none', t.disc <= 0);
        $ed.find('.js-li-t-taxable').text(money(r2(t.taxable)));
        var rows = Object.keys(t.byRate).sort(function (a, b) { return a - b; }).map(function (rate) {
            var g = r2(t.byRate[rate].gst);
            if (mode === TAX_INTRA) {
                return '<tr><th>CGST @' + (rate / 2) + '%</th><td class="num">' + money(r2(g / 2)) + '</td></tr>' +
                       '<tr><th>SGST @' + (rate / 2) + '%</th><td class="num">' + money(r2(g - r2(g / 2))) + '</td></tr>';
            }
            return '<tr><th>IGST @' + rate + '%</th><td class="num">' + money(g) + '</td></tr>';
        });
        if (mode === TAX_NONE) { rows = ['<tr><th>GST</th><td class="num text-muted">Not applicable</td></tr>']; }
        $ed.find('.js-li-t-gst').html(rows.join(''));
        var grand = r2(t.taxable + t.gst), rounded = Math.round(grand);
        $ed.find('.js-li-t-grand').text(money(rounded, 0));
        var target = $ed.data('total-target');
        if (target) {
            var $t = $(target);
            var hasLines = t.sub > 0;
            $t.prop('readonly', hasLines).toggleClass('li-auto', hasLines);
            if (hasLines) { $t.val(rounded).trigger('change'); }
        }
    }

    function addRow($ed, focus) {
        var idx = parseInt($ed.attr('data-next') || $ed.find('[data-line]').length, 10);
        $ed.attr('data-next', idx + 1);
        var html = $ed.find('template[data-line-template]').html().replace(/\[999999\]/g, '[' + idx + ']');
        var $row = $(html).appendTo($ed.find('[data-lines]'));
        initProduct($row, $ed);
        initPickers($row);
        recalc($ed);
        if (focus && $.fn.select2) { $row.find('.js-li-product').select2('open'); }
        return $row;
    }

    function init($ed) {
        if ($ed.data('li-ready')) { return; }
        $ed.data('li-ready', true);
        $ed.attr('data-next', $ed.find('[data-line]').length);
        $ed.find('[data-line]').each(function () { initProduct($(this), $ed); initPickers($(this)); stepFor($(this), $ed); });
        $ed.on('input change', '.js-li-qty, .js-li-price, .js-li-disc, .js-li-gst', function () { recalc($ed); });
        $ed.on('change', '.js-li-unit', function () { stepFor($(this).closest('[data-line]'), $ed); recalc($ed); });
        $ed.on('click', '.js-li-add', function () { addRow($ed, true); });
        $ed.on('click', '.js-li-remove', function () {
            var $rows = $ed.find('[data-line]');
            var $row = $(this).closest('[data-line]');
            if ($rows.length === 1) {   // keep one (empty) row
                $row.find('input').val('');
                $row.find('.js-li-product').val(null).trigger('change');
            } else {
                $row.remove();
            }
            recalc($ed);
        });
        // Enter in the last row's price adds a new row instead of submitting the form.
        $ed.on('keydown', 'input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if ($(this).closest('[data-line]').is(':last-child')) { addRow($ed, true); }
            }
        });
        var sel = $ed.data('tax-mode-input');
        if (sel) { $(document).on('change', sel, function () { recalc($ed); }); }
        recalc($ed);
    }

    $(function () { $('[data-line-items]').each(function () { init($(this)); }); });
    window.LineItemsEditor = {init: function (el) { init($(el)); }};
})(jQuery);
