/**
 * Invoices, Inward invoices, Bank details — loaded by InvoiceAsset after page-ui.js.
 *
 * - .js-upper: upper-cases as you type (IFSC, SWIFT, GSTIN)
 * - .js-digits: keeps digits only (account numbers)
 * - form.js-inward-form: inward invoice line editor, live totals, payment toggle, checks before submit
 * - [data-ii-convert][data-url]: mark a provisional inward invoice as actual
 * - [data-ii-delete-att][data-url]: remove an inward invoice attachment
 *
 * Totals here are only a preview; the server recomputes them on save.
 */
(function ($) {
    'use strict';

    var UI = window.PageUI;

    $(document).on('input', '.js-upper', function () {
        var v = this.value.toUpperCase().replace(/\s+/g, '');
        if (v !== this.value) { this.value = v; }
    });

    $(document).on('input', '.js-digits', function () {
        var v = this.value.replace(/\D+/g, '');
        if (v !== this.value) { this.value = v; }
    });

    var num = function (v) { var n = parseFloat(v); return isFinite(n) ? n : 0; };
    var r2 = function (n) { return Math.round((n + Number.EPSILON) * 100) / 100; };
    var fmt = function (n) { return r2(n).toFixed(2); };
    var inr = function (n) { return '₹' + r2(n).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };

    /** Searchable ledger pickers (select2 is on the page via the payment ledger widget). */
    function initLedger($scope) {
        if (!$.fn.select2) { return; }
        $scope.find('select.js-ledger').each(function () {
            if ($(this).hasClass('select2-hidden-accessible')) { return; }
            $(this).select2({theme: 'krajee-bs5', width: '100%', allowClear: true, placeholder: 'No ledger'});
        });
    }

    // ── Inward invoice form (on ready: the Select2 library loads after this file) ──
    $(function () {
    var $form = $('form.js-inward-form');
    if (!$form.length) { return; }
    {
        var counter = $form.find('.js-line, .js-expense').length + 1;
        var payLedgers = $form.data('ledgers') || {};

        var recalc = function () {
            var sub = 0, tax = {sgst: 0, cgst: 0, igst: 0}, extra = 0;
            $form.find('.js-line').each(function (i) {
                var $r = $(this);
                $r.find('.js-sno').text(i + 1);
                var item = r2(num($r.find('.js-qty').val()) * num($r.find('.js-rate').val()));
                var line = item;
                $r.find('.js-tax').each(function () {
                    var amt = r2(item * num(this.value) / 100);
                    tax[$(this).data('tax')] += amt;
                    line += amt;
                });
                sub += item;
                $r.find('.js-line-total').text(fmt(line));
            });
            $form.find('.js-expense-amount').each(function () { extra += num(this.value); });
            var net = r2(sub + tax.sgst + tax.cgst + tax.igst + extra + num($form.find('.js-round').val()));
            $form.find('.js-sum-sub').text(fmt(sub));
            $.each(tax, function (k, v) { $form.find('.js-sum-' + k).text(fmt(v)); });
            $form.find('.js-sum-extra').text(fmt(extra));
            $form.find('.js-sum-net').text(fmt(net));
            $form.data('net', net);
            payHint();
            return net;
        };

        var payHint = function () {
            var net = $form.data('net') || 0, paid = num($form.find('.js-pay-amount').val()), tds = num($form.find('.js-tds').val());
            var left = r2(net - paid - tds);
            $form.find('.js-pay-hint').text(paid || tds
                ? (left > 0.004 ? inr(left) + ' of ' + inr(net) + ' still unpaid.' : (left < -0.004 ? 'Payment plus TDS is ' + inr(-left) + ' more than the net total.' : 'Fully paid.'))
                : '');
        };

        var addRow = function (tplId, $target) {
            var html = document.getElementById(tplId).innerHTML.replace(/__i__/g, 'n' + (counter++));
            var $row = $(html).appendTo($target);
            initLedger($row);
            return $row;
        };

        $form.on('input change', '.js-qty, .js-rate, .js-tax, .js-expense-amount, .js-round', recalc);
        $form.on('input', '.js-pay-amount, .js-tds', payHint);

        $form.on('click', '.js-add-line', function () {
            addRow('ii-line-tpl', $('#ii-lines')).find('.js-product').trigger('focus');
            recalc();
        });
        $form.on('click', '.js-remove-line', function () {
            if ($form.find('.js-line').length <= 1) {
                Swal.fire({icon: 'info', title: 'At least one line', text: 'An invoice needs at least one item. Change this line instead.'});
                return;
            }
            $(this).closest('.js-line').remove();
            recalc();
        });
        $form.on('click', '.js-add-expense', function () {
            addRow('ii-expense-tpl', $('#ii-expenses')).find('input').first().trigger('focus');
            $form.find('.js-no-expenses').prop('hidden', true);
        });
        $form.on('click', '.js-remove-expense', function () {
            $(this).closest('.js-expense').remove();
            $form.find('.js-no-expenses').prop('hidden', $form.find('.js-expense').length > 0);
            recalc();
        });

        // Paid → show payment details and suggest the full amount.
        $form.on('change', '.js-pay-status', function () {
            var paid = this.value === '1' && this.checked;
            $form.find('.js-paid-fields').prop('hidden', !paid);
            var $amt = $form.find('.js-pay-amount');
            if (paid && !num($amt.val())) { $amt.val(fmt(($form.data('net') || 0) - num($form.find('.js-tds').val()))); }
            payHint();
        });
        // Payment type → only its ledgers.
        $form.on('change', '.js-pay-type', function () {
            var $l = $('#ii-pay-ledger').empty().append(new Option('', '', true, true));
            $.each(payLedgers[this.value] || {}, function (id, title) { $l.append(new Option(title, id)); });
            $l.trigger('change');
        });

        // Check before submit; the server checks everything again.
        $form.on('submit', function (e) {
            if ($form.data('ui-busy')) { e.preventDefault(); return; }
            var net = recalc(), errs = [], focus = null;
            var need = function (ok, msg, $el) { if (!ok) { errs.push(msg); if (!focus && $el) { focus = $el; } } };
            need($.trim($('#ii-number').val()) !== '', 'Enter the vendor invoice number.', $('#ii-number'));
            need(/^\d{4}-\d{2}-\d{2}$/.test($('#ii-date').val()), 'Pick the invoice date.', $('#ii-date'));
            $form.find('.js-line').each(function (i) {
                var $r = $(this), n = 'Line ' + (i + 1) + ': ';
                need($.trim($r.find('.js-product').val()) !== '', n + 'enter what was bought.', $r.find('.js-product'));
                need(num($r.find('.js-qty').val()) > 0, n + 'quantity must be more than 0.', $r.find('.js-qty'));
                need(num($r.find('.js-rate').val()) > 0, n + 'rate must be more than 0.', $r.find('.js-rate'));
                $r.find('.js-tax').each(function () { need(num(this.value) >= 0 && num(this.value) <= 100, n + 'tax must be 0–100%.', $(this)); });
            });
            $form.find('.js-expense').each(function (i) {
                var $r = $(this), n = 'Extra expense ' + (i + 1) + ': ';
                need($.trim($r.find('input[type=text]').val()) !== '', n + 'enter a description.', $r.find('input[type=text]'));
                need(num($r.find('.js-expense-amount').val()) > 0, n + 'amount must be more than 0.', $r.find('.js-expense-amount'));
            });
            need(Math.abs(num($form.find('.js-round').val())) <= 1000, 'Round off must be between -1,000 and 1,000.', $form.find('.js-round'));
            need(net > 0, 'The net total must be more than zero.');
            var status = $form.find('.js-pay-status:checked').val();
            need(status !== undefined, 'Choose whether the invoice is paid.', $form.find('.js-pay-status').first());
            if (status === '1') {
                need(!!$('#ii-pay-type').val(), 'Choose the payment type.', $('#ii-pay-type'));
                need(!!$('#ii-pay-ledger').val(), 'Choose the ledger it was paid from.', $('#ii-pay-ledger'));
                need(/^\d{4}-\d{2}-\d{2}$/.test($('#ii-pay-date').val()), 'Pick the payment date.', $('#ii-pay-date'));
                need(!$('#ii-pay-date').val() || $('#ii-pay-date').val() >= $('#ii-date').val(), 'The payment date cannot be before the invoice date.', $('#ii-pay-date'));
                need(num($('#ii-pay-amount').val()) > 0, 'Enter the amount paid.', $('#ii-pay-amount'));
                need(num($('#ii-pay-amount').val()) + num($('#ii-tds').val()) <= net + 0.005, 'Payment plus TDS cannot be more than the net total (' + inr(net) + ').', $('#ii-pay-amount'));
            }
            if (errs.length) {
                e.preventDefault();
                Swal.fire({icon: 'warning', title: 'Please check the invoice', html: '<ul class="text-start mb-0"><li>' + errs.map(function (m) { return $('<div>').text(m).html(); }).join('</li><li>') + '</li></ul>'})
                    .then(function () { if (focus) { focus.trigger('focus'); } });
                return;
            }
            $form.data('ui-busy', true).find('[type=submit]').addClass('is-busy')
                .html('<span class="spinner-border me-1" role="status" aria-hidden="true"></span>Saving…');
        });

        initLedger($form);
        recalc();
    }
    });

    // ── Inward invoice: convert / attachments ───────────────────────────
    function confirmPost(opts) {
        Swal.fire({title: opts.title, text: opts.text || '', icon: opts.icon || 'question', showCancelButton: true, confirmButtonText: opts.confirm,
            customClass: opts.danger ? {confirmButton: 'is-danger'} : {}})
            .then(function (r) {
                if (!r.isConfirmed) { return; }
                UI.showLoading(opts.loading);
                UI.post(opts.url).done(function (res) {
                    if (res && res.success) { opts.done(res); }
                    else { Swal.fire({icon: 'warning', title: 'Not done', text: (res && res.message) || 'Please try again.'}); }
                }).fail(UI.showFailure);
            });
    }

    $(document).on('click', '[data-ii-convert]', function (e) {
        e.preventDefault();
        confirmPost({url: $(this).data('url'), title: 'Mark as an actual invoice?', text: 'Use this once the vendor has sent the final tax invoice.',
            confirm: 'Yes, mark actual', loading: 'Saving…', done: function (res) {
                Swal.fire({icon: 'success', title: res.message, timer: 1300, showConfirmButton: false}).then(function () { location.reload(); });
            }});
    });

    // ── Invoice list: TDS, convert, download after "Save and download" ──

    $(document).on('click', '[data-inv-tds]', function (e) {
        e.preventDefault();
        var $b = $(this), $m = $('#tdsModal'), taxable = num($b.data('taxable'));
        $m.find('form').attr('action', $b.data('url')).data('max', num($b.data('max'))).data('taxable', taxable)
            .removeData('ui-busy').find('[type=submit]').removeClass('is-busy');
        $m.find('.js-tds-no').text($b.data('no'));
        $m.find('.js-tds-taxable').text(fmt(taxable));
        $('#tds-pct').val(num($b.data('pct')) || '');
        $('#tds-amt').val(num($b.data('amt')) || '');
        $m.find('.js-tds-error').text('');
        bootstrap.Modal.getOrCreateInstance($m[0]).show();
    });
    $(document).on('input', '#tds-pct', function () {
        $('#tds-amt').val(fmt(num($('#tdsForm').data('taxable')) * num(this.value) / 100));
    });
    // Runs before the shared busy-form handler (bound on document).
    $('#tdsForm').on('submit', function (e) {
        var pct = num($('#tds-pct').val()), amt = num($('#tds-amt').val()), max = num($(this).data('max')), msg = '';
        if (pct < 0 || pct > 100) { msg = 'TDS % must be between 0 and 100.'; }
        else if (amt < 0) { msg = 'TDS amount cannot be negative.'; }
        else if (amt > max + 0.005) { msg = 'TDS cannot be more than the unpaid amount (' + fmt(max) + ').'; }
        $(this).find('.js-tds-error').text(msg);
        if (msg) { e.preventDefault(); e.stopImmediatePropagation(); }
    });

    $(document).on('click', '[data-inv-convert]', function (e) {
        e.preventDefault();
        var $b = $(this), needGst = String($b.data('needgst')) === '1', today = new Date().toISOString().slice(0, 10);
        Swal.fire({
            icon: 'question',
            title: 'Convert to a tax invoice?',
            html: '<p class="small text-muted mb-2">' + $('<div>').text($b.data('no')).html() + ' gets the next tax invoice number.</p>'
                + (needGst ? '<label class="form-label d-block text-start small fw-semibold" for="swal-gst">Client GSTIN</label>'
                    + '<input id="swal-gst" class="form-control form-control-sm mb-2 js-upper" maxlength="15" placeholder="33ABCDE1234F1Z5" autocomplete="off">' : '')
                + '<label class="form-label d-block text-start small fw-semibold" for="swal-date">Invoice date</label>'
                + '<input id="swal-date" type="date" class="form-control form-control-sm" value="' + today + '" max="' + today + '">',
            showCancelButton: true, confirmButtonText: 'Convert', focusConfirm: false,
            preConfirm: function () {
                var gst = needGst ? $('#swal-gst').val().toUpperCase().replace(/\s+/g, '') : '', date = $('#swal-date').val();
                if (needGst && !/^\d{2}[A-Z]{5}\d{4}[A-Z][A-Z0-9]Z[A-Z0-9]$/.test(gst)) { Swal.showValidationMessage('Enter a valid 15-character GSTIN, e.g. 33ABCDE1234F1Z5.'); return false; }
                if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || date > today) { Swal.showValidationMessage('Pick an invoice date that is not in the future.'); return false; }
                return {gst: gst, date: date};
            }
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            var $f = $('<form method="post">').attr('action', $b.data('url')).appendTo('body');
            $('<input type="hidden">').attr('name', yii.getCsrfParam()).val(yii.getCsrfToken()).appendTo($f);
            $('<input type="hidden" name="invoice_date">').val(r.value.date).appendTo($f);
            if (r.value.gst) { $('<input type="hidden" name="client_gst_no">').val(r.value.gst).appendTo($f); }
            UI.showLoading('Converting…');
            $f.trigger('submit');
        });
    });

    // Monthly GST report: picking a month reloads with ?year=&month=.
    $(document).on('change', '.js-month-form select', function () {
        var p = this.value.split('-'), $f = $(this).closest('form');
        $f.find('[name=year]').val(p[0]); $f.find('[name=month]').val(p[1]);
        $(this).prop('disabled', true);
        $f.trigger('submit');
    });

    // ── Record payment form ─────────────────────────────────────────────
    $(document).on('change', '.js-receipt-mode', function () {
        var mode = this.value;
        $(this).closest('form').find('[data-receipt-mode]').each(function () {
            $(this).prop('hidden', String($(this).data('receipt-mode')) !== mode);
        });
    });
    // Bound on the form so it runs before the shared busy-button handler.
    $(function () {
        $('form.js-receipt-form').on('submit', function (e) {
            var $f = $(this), amt = num($f.find('#rc-amount').val()), bal = num($f.data('balance')), cur = $f.data('currency') || '';
            var date = $f.find('#rc-date').val(), today = new Date().toISOString().slice(0, 10), msg = '';
            if (amt <= 0) { msg = 'Enter the amount received.'; }
            else if (amt > bal + 0.005) { msg = 'The amount is more than the balance due (' + cur + ' ' + fmt(bal) + '). Record TDS separately if it was deducted.'; }
            else if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) { msg = 'Pick the receipt date.'; }
            else if (date > today) { msg = 'The receipt date cannot be in the future.'; }
            if (msg) {
                e.preventDefault(); e.stopImmediatePropagation();
                Swal.fire({icon: 'warning', title: 'Check the payment', text: msg});
                return;
            }
            if ($f.data('ui-busy')) { e.preventDefault(); return; }
            $f.data('ui-busy', true).find('[type=submit]').addClass('is-busy')
                .html('<span class="spinner-border me-1" role="status" aria-hidden="true"></span>Saving…');
        });
    });

    $(function () {
        var url = $('.ui-page[data-download-url]').data('download-url');
        if (!url) { return; }
        $('<iframe hidden title="Invoice download">').attr('src', url).appendTo('body');
        if (window.history && history.replaceState) {
            var u = new URL(location.href);
            u.searchParams.delete('download_id');
            history.replaceState({}, '', u.toString());
        }
    });

    $(document).on('click', '[data-ii-delete-att]', function (e) {
        e.preventDefault();
        var id = $(this).data('ii-delete-att');
        confirmPost({url: $(this).data('url'), title: 'Remove this attachment?', icon: 'warning', danger: true, confirm: 'Yes, remove', loading: 'Removing…',
            done: function (res) {
                $('#ii-att-' + id).remove();
                Swal.fire({icon: 'success', title: res.message, timer: 1100, showConfirmButton: false});
            }});
    });

})(jQuery);
