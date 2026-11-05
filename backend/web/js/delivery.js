/**
 * Quotations, Delivery challans, Purchase orders, Delivery products —
 * loaded by DeliveryAsset after page-ui.js.
 *
 * - .js-digits: digits only (HSN codes)
 * - [data-dc-return] / [data-dc-cancel]: open the shared DC modals (views/dc/_action-modals.php)
 * - .ui-page[data-download-url]: fetch a PDF once after "Save and download", then drop the URL flag
 */
(function ($) {
    'use strict';

    var UI = window.PageUI;

    $(document).on('input', '.js-digits', function () {
        var v = this.value.replace(/\D+/g, '');
        if (v !== this.value) { this.value = v; }
    });

    // ── DC: mark returned / cancel ─────────────────────────────────────
    function openDcModal(id, $btn) {
        var $m = $(id);
        $m.find('[name=id]').val($btn.data('id'));
        $m.find('.js-dc-no').text($btn.data('no') || '');
        $m.find('.js-dc-error').text('');
        $m.find('textarea').val('');
        $m.find('[type=submit]').prop('disabled', false).removeClass('is-busy');
        bootstrap.Modal.getOrCreateInstance($m[0]).show();
        return $m;
    }

    $(document).on('click', '[data-dc-return]', function (e) {
        e.preventDefault();
        var $m = openDcModal('#returnDcModal', $(this));
        var dcDate = String($(this).data('dc-date') || '').slice(0, 10);
        $m.data('min-date', dcDate);
        var $d = $('#return_dc_date').val(new Date().toISOString().slice(0, 10));
        if (typeof $d.kvDatepicker === 'function') {
            try { $d.kvDatepicker('setStartDate', dcDate || null); $d.kvDatepicker('update', $d.val()); } catch (err) { /* plain input */ }
        }
    });

    $(document).on('click', '[data-dc-cancel]', function (e) {
        e.preventDefault();
        openDcModal('#cancelDcModal', $(this));
    });

    function submitDcModal($form, check) {
        var $m = $form.closest('.modal'), msg = check();
        $form.find('.js-dc-error').text(msg || '');
        if (msg) { return; }
        var $btn = $form.find('[type=submit]').prop('disabled', true).addClass('is-busy');
        var data = {};
        $.each($form.serializeArray(), function (_, f) { data[f.name] = f.value; });
        UI.post($m.data('url'), data).done(function (r) {
            if (r && r.success) {
                bootstrap.Modal.getInstance($m[0]).hide();
                Swal.fire({icon: 'success', title: r.message || 'Saved', timer: 1300, showConfirmButton: false}).then(function () { location.reload(); });
            } else {
                $form.find('.js-dc-error').text((r && r.message) || 'Could not save. Please try again.');
                $btn.prop('disabled', false).removeClass('is-busy');
            }
        }).fail(function (xhr, status) {
            $btn.prop('disabled', false).removeClass('is-busy');
            UI.showFailure(xhr, status);
        });
    }

    $(document).on('submit', '.js-dc-return-form', function (e) {
        e.preventDefault();
        var $f = $(this), min = $f.closest('.modal').data('min-date'), today = new Date().toISOString().slice(0, 10);
        submitDcModal($f, function () {
            var d = $('#return_dc_date').val();
            if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) { return 'Pick the date it was returned.'; }
            if (min && d < min) { return 'The return date cannot be before the DC date (' + min + ').'; }
            if (d > today) { return 'The return date cannot be in the future.'; }
            return '';
        });
    });

    $(document).on('submit', '.js-dc-cancel-form', function (e) {
        e.preventDefault();
        submitDcModal($(this), function () {
            return $.trim($('#cancel_dc_reason').val()) === '' ? 'Enter why the DC is being cancelled.' : '';
        });
    });

    // ── Download a PDF once after "Save and download" ──────────────────
    $(function () {
        var url = $('.ui-page[data-download-url]').data('download-url');
        if (!url) { return; }
        $('<iframe hidden title="Download">').attr('src', url).appendTo('body');
        if (window.history && history.replaceState) {
            var u = new URL(location.href);
            ['download_id', 'download_submit'].forEach(function (k) { u.searchParams.delete(k); });
            history.replaceState({}, '', u.toString());
        }
    });

})(jQuery);
