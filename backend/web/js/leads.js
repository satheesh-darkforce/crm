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

})(jQuery);
