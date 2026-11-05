/**
 * HR — employees, attendance, leave, holidays, payslips, allowances, RFID.
 * Loaded by HrAsset after page-ui.js.
 */
(function ($) {
    'use strict';

    // Digits only (PINs, employee codes typed as numbers, amounts without decimals).
    $(document).on('input', '.js-digits', function () {
        var v = this.value.replace(/\D+/g, '');
        if (v !== this.value) { this.value = v; }
    });

})(jQuery);
