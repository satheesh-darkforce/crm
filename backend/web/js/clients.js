/**
 * Clients module — loaded by ClientAsset after page-ui.js.
 *
 * - Reset-password modal (list + view pages)
 * - Client form: tax-exemption reason, password visibility, GST / phone rules
 * - Portal permissions: select-all and per-section checkboxes
 */
(function ($) {
    'use strict';

    // ── Reset password modal ────────────────────────────────────────────
    // Trigger: [data-bs-target="#resetModal"][data-url][data-name][data-return]

    $(document).on('show.bs.modal', '#resetModal', function (e) {
        var $btn = $(e.relatedTarget), $form = $('#resetForm');
        $('#resetClientName').text($btn.data('name') || '');
        $form.attr('action', $btn.data('url'));
        $form.find('[name=return]').val($btn.data('return') || 'index');
        $form.find('[name=new_password]').val('').removeClass('is-invalid');
        $form.removeData('ui-busy').find('[type=submit]').removeClass('is-busy').text('Reset Password');
    });
    $(document).on('shown.bs.modal', '#resetModal', function () {
        $('#resetForm [name=new_password]').trigger('focus');
    });
    // Length check before submit (the server checks again), then a busy button.
    $(document).on('submit', '#resetForm', function (e) {
        var $form = $(this), $pwd = $form.find('[name=new_password]'), v = $.trim($pwd.val() || ''),
            min = Number($pwd.attr('minlength')) || 6, max = Number($pwd.attr('maxlength')) || 128;
        var ok = v.length >= min && v.length <= max;
        $pwd.toggleClass('is-invalid', !ok);
        if (!ok || $form.data('ui-busy')) { e.preventDefault(); return; }
        $form.data('ui-busy', true);
        $form.find('[type=submit]').addClass('is-busy')
            .html('<span class="spinner-border me-1" role="status" aria-hidden="true"></span>Resetting…');
    });

    // ── Client form ─────────────────────────────────────────────────────

    $(document).on('change', '#tax-exemption-select', function () {
        $('#tax-reason-wrap').toggle($(this).val() === '1');
    });

    $(document).on('click', '.js-toggle-pwd', function () {
        var $input = $($(this).data('target')), $icon = $(this).find('i'), show = $input.attr('type') === 'password';
        $input.attr('type', show ? 'text' : 'password');
        $icon.toggleClass('bi-eye', !show).toggleClass('bi-eye-slash', show);
        $(this).attr('aria-label', show ? 'Hide password' : 'Show password');
    });

    // GST Registration Type → GST No. is required for Regular (server enforces the same).
    $(document).on('change', '#clientmodel-gst_registration_type', function () {
        $('.gst-required-mark').toggle($(this).val() === '1');
    });

    // Client Type → phone length: International up to 15 digits, others exactly 10.
    function applyPhoneLength(typeVal) {
        var isIntl = String(typeVal) === '3', max = isIntl ? 15 : 10;
        $('#clientmodel-phone').attr('maxlength', max).attr('placeholder', isIntl ? 'Up to 15 digits' : '10-digit number');
        $('#clientmodel-alt_phone').attr('maxlength', max).attr('placeholder', isIntl ? 'Up to 15 digits (optional)' : '10-digit number (optional)');
        $('.js-client-phone').each(function () {
            var v = (this.value || '').replace(/[^0-9]/g, '');
            if (v.length > max) { v = v.substring(0, max); }
            if (v !== this.value) { this.value = v; }
        });
    }
    $(document).on('change', '#clientmodel-client_type', function () { applyPhoneLength($(this).val()); });
    $(document).on('input', '.js-client-phone', function () {
        var v = this.value.replace(/[^0-9]/g, '');
        if (v !== this.value) { this.value = v; }
    });

    // GST No. → uppercase; state code 33 (Tamil Nadu) is Intra-State for this business.
    $(document).on('input blur', '#clientmodel-gst_no', function () {
        var val = (this.value || '').toUpperCase().replace(/\s+/g, '');
        if (val !== this.value) { this.value = val; }
        if (val.substring(0, 2) === '33') {
            var $type = $('#clientmodel-client_type');
            if ($type.val() !== '2') { $type.val('2').trigger('change'); }
        }
    });

    // ── Portal permissions ──────────────────────────────────────────────

    function syncPermissionToggles() {
        $('.js-perm-section').each(function () {
            var $boxes = $(this).closest('tr').find('.js-perm');
            this.checked = $boxes.length > 0 && $boxes.filter(':checked').length === $boxes.length;
            this.indeterminate = $boxes.filter(':checked').length > 0 && !this.checked;
        });
        var $all = $('.js-perm'), checked = $all.filter(':checked').length;
        $('#checkAll').prop('checked', $all.length > 0 && checked === $all.length).prop('indeterminate', checked > 0 && checked < $all.length);
        $('#perm-count').text(checked + ' of ' + $all.length + ' selected');
    }
    $(document).on('change', '#checkAll', function () {
        $('.js-perm').prop('checked', this.checked);
        syncPermissionToggles();
    });
    $(document).on('change', '.js-perm-section', function () {
        $(this).closest('tr').find('.js-perm').prop('checked', this.checked);
        syncPermissionToggles();
    });
    $(document).on('change', '.js-perm', syncPermissionToggles);
    $(syncPermissionToggles);

})(jQuery);
