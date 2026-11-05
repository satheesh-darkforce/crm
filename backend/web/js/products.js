/**
 * Products — catalogue form helpers.
 * Loaded by ProductAsset after page-ui.js.
 */
(function ($) {
    'use strict';

    // Repeaters: [data-repeat] holds rows; <template data-repeat-template> is cloned.
    // Row inputs use __i__ as the index placeholder in their names.
    function nextIndex($box) {
        var n = parseInt($box.attr('data-next') || $box.find('[data-repeat-row]').length, 10);
        $box.attr('data-next', n + 1);
        return n;
    }
    $(document).on('click', '[data-repeat-add]', function () {
        var $box = $(this).closest('[data-repeat]');
        var max = parseInt($box.attr('data-max') || '50', 10);
        if ($box.find('[data-repeat-row]').length >= max) { return; }
        var html = $box.find('template[data-repeat-template]').html().replace(/__i__/g, nextIndex($box));
        var $row = $(html).appendTo($box.find('[data-repeat-list]'));
        $row.find('input').first().trigger('focus');
    });
    $(document).on('click', '[data-repeat-remove]', function () {
        $(this).closest('[data-repeat-row]').remove();
    });
    // Enter in a feature/spec input adds a new row instead of submitting the form.
    $(document).on('keydown', '[data-repeat] input', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).closest('[data-repeat]').find('[data-repeat-add]').trigger('click');
        }
    });

    // Pricing preview: discount off MRP and price incl. GST.
    function money(v) {
        return '₹' + v.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function updatePreview() {
        var $out = $('#price-preview');
        if (!$out.length) { return; }
        var onRequest = $('#product-price_on_request').is(':checked');
        var price = parseFloat($('#product-sale_price').val());
        var mrp = parseFloat($('#product-mrp').val());
        var gst = parseFloat($('#product-gst_rate').val()) || 0;
        var unit = $('#product-units').val();
        $('.field-product-sale_price .p-req').toggle(!onRequest);
        if (isNaN(price)) {
            $out.text(onRequest ? 'Shown as "Price on request" — the rate is entered on each quotation. A reference rate is optional.' : 'Enter a price to see the totals.');
            return;
        }
        var parts = [money(price) + (unit ? ' / ' + unit : '') + ' + ' + gst + '% GST = ' + money(price * (1 + gst / 100))];
        if (!isNaN(mrp) && mrp > price) {
            parts.push(Math.round((mrp - price) / mrp * 100) + '% off MRP ' + money(mrp));
        }
        $out.text(parts.join(' · '));
    }
    $(document).on('input change', '#product-sale_price, #product-mrp, #product-gst_rate, #product-units, #product-price_on_request', updatePreview);

    // Unit: "per <unit>" next to the prices, and how quotations will count it.
    function updateUnit() {
        var $sel = $('#product-units');
        if (!$sel.length) { return; }
        var unit = $sel.val();
        var $opt = $sel.find('option:selected');
        $('.p-per').text(unit ? 'per ' + unit : 'per unit');
        var hint = 'Measured by area, length, weight, volume, count or as a service.';
        if (unit && $opt.data('group')) {
            hint = $opt.data('group') + ' — quotations enter the quantity in ' + unit +
                ($opt.data('decimals') === 1 || $opt.data('decimals') === '1' ? ' (decimals allowed, e.g. 12.5).' : ' (whole numbers only).');
        }
        $('#unit-hint').text(hint);
    }
    $(document).on('change', '#product-units', updateUnit);
    $(function () { updateUnit(); });

    // HSN for products, SAC for services.
    function updateCodeLabel() {
        var service = $('#product-item_type').val() === '2';
        $('label[for="product-hsn_code"]').text(service ? 'SAC code' : 'HSN code');
        $('#product-hsn_code').attr('placeholder', service ? 'e.g. 995465' : 'e.g. 57033090');
    }
    // Re-check fields whose rules depend on another field, so stale messages clear.
    function revalidate(id) {
        var $f = $('#product-form');
        if ($f.length && $f.data('yiiActiveForm') && $('#' + id).val() !== '') { $f.yiiActiveForm('validateAttribute', id); }
    }
    $(document).on('change', '#product-item_type', function () { updateCodeLabel(); revalidate('product-hsn_code'); });
    $(document).on('change', '#product-price_on_request', function () {
        var $f = $('#product-form');
        if ($f.data('yiiActiveForm')) { $f.yiiActiveForm('validateAttribute', 'product-sale_price'); }
    });

    $(function () { updatePreview(); updateCodeLabel(); });
})(jQuery);
