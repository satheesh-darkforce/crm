/**
 * Leads module (lead, lead-followers views) — loaded by LeadAsset after page-ui.js.
 *
 * Lead status, active/inactive, closing follow-ups, deleting attachments and the
 * "Other" mode field. Requests, loading and error handling come from window.PageUI.
 *
 * URLs come from window.leadUrls (LeadAsset::registerFor()).
 */
(function ($) {
    'use strict';

    var urls = window.leadUrls || {};
    var UI = window.PageUI;

    // ── Lead pipeline status (Dropped asks for a reason) ────────────────

    $(document).on('change', '.js-lead-status', function () {
        var $el = $(this), status = String($el.val());
        var label = $.trim($el.find('option:selected').text());
        var data = {id: $el.data('id'), status: status, reason: ''};

        if (status === String($el.data('prev'))) { return; }

        if (status === '6') {
            Swal.fire({
                title: 'Drop this lead?',
                input: 'textarea',
                inputLabel: 'Reason',
                inputPlaceholder: 'Why is this lead being dropped?',
                inputAttributes: {maxlength: 1000},
                showCancelButton: true,
                confirmButtonText: 'Drop Lead',
                customClass: {confirmButton: 'is-danger'},
                inputValidator: function (v) {
                    v = $.trim(v || '');
                    if (!v) { return 'Please enter a reason.'; }
                    if (v.length < 3) { return 'Please enter a meaningful reason.'; }
                    return null;
                }
            }).then(function (r) {
                if (!r.isConfirmed) { UI.revert($el); return; }
                data.reason = $.trim(r.value);
                UI.send(urls.status, data, $el);
            });
            return;
        }
        Swal.fire({title: 'Change status to "' + label + '"?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, change'})
            .then(function (r) { r.isConfirmed ? UI.send(urls.status, data, $el) : UI.revert($el); });
    });

    // ── Active / Inactive ───────────────────────────────────────────────

    $(document).on('change', '.js-lead-active', function () {
        var $el = $(this);
        if (String($el.val()) === String($el.data('prev'))) { return; }
        var label = $.trim($el.find('option:selected').text());
        Swal.fire({title: 'Mark this lead as ' + label + '?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, change'})
            .then(function (r) {
                r.isConfirmed ? UI.send(urls.active, {id: $el.data('id'), status: $el.val()}, $el) : UI.revert($el);
            });
    });

    // ── Close a follow-up — the only open one can only be replaced ──────

    $(document).on('click', '.js-close-followup', function (e) {
        e.preventDefault();
        var $btn = $(this), id = $btn.data('id'), onlyOpen = String($btn.data('only-open')) === '1';
        var createUrl = urls.create + (urls.create.indexOf('?') === -1 ? '?' : '&')
            + 'id=' + encodeURIComponent($btn.data('lead')) + '&followup=' + encodeURIComponent(id);
        Swal.fire({
            title: 'Close this follow-up?',
            text: onlyOpen
                ? 'This is the only open follow-up. Create the next follow-up and close this one?'
                : 'Close it now, or create the next follow-up and close this one.',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: !onlyOpen,
            confirmButtonText: 'Create & Close',
            denyButtonText: 'Close Follow-up'
        }).then(function (r) {
            if (r.isConfirmed) { window.location.href = createUrl; }
            else if (r.isDenied) { UI.send(urls.followup, {followupid: id}); }
        });
    });

    // ── Remove a lead attachment (edit form) ────────────────────────────

    $(document).on('click', '.js-delete-attachment', function (e) {
        e.preventDefault();
        var id = $(this).data('id'), name = $(this).data('name') || 'this attachment';
        Swal.fire({
            title: 'Delete attachment?',
            text: name + ' will be removed from the lead.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            customClass: {confirmButton: 'is-danger'}
        }).then(function (r) { if (r.isConfirmed) { UI.send(urls.deleteAt, {id: id}); } });
    });

    // ── "Other" mode reveals its text field ─────────────────────────────

    $(document).on('change', '[data-other-mode]', function () {
        var $wrap = $($(this).data('other-mode')), isOther = String($(this).val()) === '5';
        $wrap.toggle(isOther);
        if (!isOther) {
            var $input = $wrap.find('input');
            $input.val('');
            var $form = $input.closest('form');
            if ($form.data('yiiActiveForm')) { $form.yiiActiveForm('validateAttribute', $input.attr('id')); }
        }
    });

    // ── Smart form helpers ───────────────────────────────────────────────

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function money(v) { return '₹' + Math.round(v).toLocaleString('en-IN'); }

    // Live duplicate check: same phone / email on another lead or a client.
    var dupTimer = null, dupLast = '';
    function checkDuplicates() {
        var $box = $('#lead-dup-alert');
        if (!$box.length || !urls.dup) { return; }
        var params = {
            phone: $('#lead-contact_no').val() || '', phone2: $('#lead-contact_no2').val() || '',
            mobile: $('#lead-contact_person_mobile').val() || '', email: $('#lead-email').val() || '',
            id: $box.data('id') || ''
        };
        var key = JSON.stringify(params);
        if (key === dupLast) { return; }
        dupLast = key;
        if ((params.phone + params.phone2 + params.mobile).replace(/\D/g, '').length < 6 && params.email.indexOf('@') < 0) {
            $box.addClass('d-none').empty();
            return;
        }
        $.getJSON(urls.dup, params).done(function (r) {
            var m = (r && r.matches) || [];
            if (!m.length) { $box.addClass('d-none').empty(); return; }
            var items = m.map(function (d) {
                var name = d.url ? '<a href="' + esc(d.url) + '" target="_blank" rel="noopener">' + esc(d.name) + '</a>' : esc(d.name);
                return '<li>' + name + ' <span class="text-muted">— same ' + esc(d.match) + ' · ' + esc(d.detail) + '</span></li>';
            });
            $box.html('<i class="fas fa-user-friends me-1"></i><strong>This contact may already exist.</strong> Check before adding a second lead:<ul>' + items.join('') + '</ul>')
                .removeClass('d-none');
        });
    }
    $(document).on('input change', '#lead-contact_no, #lead-contact_no2, #lead-contact_person_mobile, #lead-email', function () {
        clearTimeout(dupTimer);
        dupTimer = setTimeout(checkDuplicates, 450);
    });
    $(function () { if ($('#lead-dup-alert').length) { checkDuplicates(); } });

    // Value estimate from products of interest × site area (catalogue prices).
    $(document).on('click', '.js-lead-estimate', function () {
        var $out = $('#lead-estimate');
        var ids = $('#lead-interestids').val() || [];
        var area = parseFloat($('#lead-site_area').val());
        if (!ids.length || !(area > 0)) {
            $out.html('<i class="fas fa-info-circle me-1"></i>Pick the products of interest and enter the approx. area first.').removeClass('d-none');
            return;
        }
        $out.html('<i class="fas fa-spinner fa-spin me-1"></i>Calculating…').removeClass('d-none');
        $.getJSON(urls.estimate, {interest: ids, area: area, unit: $('#lead-area_unit').val()}).done(function (r) {
            var e = r && r.estimate;
            if (!e || !e.lines.length) { $out.text('No estimate available.'); return; }
            var rows = e.lines.map(function (l) {
                if (!l.products) { return '<div class="text-muted">' + esc(l.category) + ': ' + esc(l.note) + '</div>'; }
                var range = l.low === l.high ? money(l.typical) : money(l.low) + ' – ' + money(l.high);
                return '<div><strong>' + esc(l.category) + '</strong>: ' + range +
                    ' <button type="button" class="btn btn-link js-use-estimate" data-value="' + l.typical + '">use ' + money(l.typical) + '</button></div>';
            });
            $out.html(rows.join('') + '<div class="text-muted mt-1">' + esc(e.area) + ' ' + esc(e.unit) + ' at catalogue prices, before GST.</div>');
        }).fail(function () {
            $out.text('Could not calculate the estimate. Please try again.');
        });
    });
    $(document).on('click', '.js-use-estimate', function () {
        $('#lead-lead_value').val($(this).data('value')).trigger('change');
    });

})(jQuery);
