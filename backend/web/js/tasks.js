/**
 * Tasks + Work Reports — loaded by TaskAsset after page-ui.js.
 *
 * - Timer: [data-task-start] / [data-task-stop]
 * - Complete: [data-task-complete]
 * - Task Logs modal: [data-task-logs] (uses the shared [data-ui-modal] loader)
 * - Re-assign / Subtask modals: [data-reassign] / [data-subtask] fill the hidden task id
 * - Hours + minutes pairs: form[data-hm-target] packs them into one field
 *
 * URLs come from window.taskUrls (TaskAsset::register()).
 */
(function ($) {
    'use strict';

    var urls = window.taskUrls || {};
    var UI = window.PageUI;

    /** Confirm, then POST and reload on success. */
    function confirmPost(opts) {
        Swal.fire({title: opts.title, text: opts.text || '', icon: 'question', showCancelButton: true, confirmButtonText: opts.confirm})
            .then(function (r) {
                if (!r.isConfirmed) { return; }
                UI.showLoading(opts.loading);
                UI.post(opts.url, opts.data)
                    .done(function (res) {
                        if (res && res.success) {
                            Swal.fire({icon: 'success', title: res.message || 'Done', timer: 1400, showConfirmButton: false})
                                .then(function () { location.reload(); });
                        } else {
                            Swal.fire({icon: 'warning', title: opts.failTitle || 'Not done', text: (res && res.message) || 'Please try again.'});
                        }
                    })
                    .fail(UI.showFailure);
            });
    }

    $(document).on('click', '[data-task-start]', function (e) {
        e.preventDefault();
        var $b = $(this);
        confirmPost({
            url: urls.start, data: {taskid: $b.data('task-start')},
            title: ($b.data('label') || 'Start') + ' the timer?',
            text: $b.data('name') ? '"' + $b.data('name') + '"' : '',
            confirm: 'Yes, ' + String($b.data('label') || 'start').toLowerCase(), loading: 'Starting…', failTitle: 'Not started'
        });
    });

    $(document).on('click', '[data-task-stop]', function (e) {
        e.preventDefault();
        confirmPost({url: urls.stop, data: {taskid: $(this).data('task-stop')}, title: 'Stop the timer?',
            text: 'The time so far is saved to this task.', confirm: 'Yes, stop', loading: 'Stopping…', failTitle: 'Not stopped'});
    });

    $(document).on('click', '[data-task-complete]', function (e) {
        e.preventDefault();
        var $b = $(this);
        confirmPost({url: urls.complete, data: {taskid: $b.data('task-complete')}, title: 'Mark this task completed?',
            text: $b.data('name') ? '"' + $b.data('name') + '"' : '', confirm: 'Yes, complete', loading: 'Completing…', failTitle: 'Cannot complete yet'});
    });

    // Task logs → shared modal loader (data-ui-modal + data-url are set in markup).

    // Re-assign / Subtask modals: put the task id in the form, then show it.
    $(document).on('click', '[data-reassign], [data-subtask]', function (e) {
        e.preventDefault();
        var isSub = this.hasAttribute('data-subtask');
        var $modal = $(isSub ? '#subtaskModal' : '#reassignModal');
        $modal.find('[name=task_id]').val($(this).data(isSub ? 'subtask' : 'reassign'));
        $modal.find('.js-task-name').text($(this).data('name') || '');
        $modal.find('form').removeData('ui-busy').find('[type=submit]').removeClass('is-busy');
        bootstrap.Modal.getOrCreateInstance($modal[0]).show();
    });

    // Hours + minutes → one value. form[data-hm-target="#field"][data-hm-format="seconds|hhmm"]
    $(document).on('submit', 'form[data-hm-target]', function (e) {
        var $f = $(this), h = parseInt($f.find('.js-hm-hours').val(), 10) || 0, m = parseInt($f.find('.js-hm-minutes').val(), 10) || 0;
        var $err = $f.find('.js-hm-error');
        var msg = '';
        if (h < 0 || m < 0 || m > 59) { msg = 'Minutes must be 0–59.'; }
        else if (h * 60 + m < 1) { msg = 'Enter at least 1 minute.'; }
        else if (h > 1000) { msg = 'Cannot exceed 1,000 hours.'; }
        $err.text(msg).toggle(!!msg);
        $f.find('.js-hm-hours, .js-hm-minutes').toggleClass('is-invalid', !!msg);
        if (msg || $f.data('ui-busy')) { e.preventDefault(); return false; }
        var $t = $($f.data('hm-target'));
        $t.val($f.data('hm-format') === 'hhmm' ? h + ':' + (m < 10 ? '0' + m : m) : h * 3600 + m * 60);
        // Busy state only once the check passed (so a rejected submit never locks the button).
        $f.data('ui-busy', true).find('[type=submit]').addClass('is-busy')
            .html('<span class="spinner-border me-1" role="status" aria-hidden="true"></span>Saving…');
    });

    // ── Work report form: 12-hour From/To pickers + 24-hour total ────────

    var $wr = $('#work-report-form');
    if ($wr.length) {
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var toMins = function (val) {           // "HH:MM AM" or "HH:MM" → minutes since midnight
            var m12 = /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i.exec(String(val || '').trim());
            if (m12) { return (parseInt(m12[1], 10) % 12 + (m12[3].toUpperCase() === 'PM' ? 12 : 0)) * 60 + parseInt(m12[2], 10); }
            var m24 = /^(\d{1,2}):(\d{2})$/.exec(String(val || '').trim());
            return m24 ? parseInt(m24[1], 10) * 60 + parseInt(m24[2], 10) : null;
        };
        var sync = function () {
            $.each(['from', 'to'], function (_, p) {
                var h = $('#wr-' + p + '-h').val(), m = $('#wr-' + p + '-m').val();
                $('#wr-' + p + '-time').val(h && m ? h + ':' + m + ' ' + $('#wr-' + p + '-ap').val() : '');
            });
            var th = $('#wr-total-hh').val(), tm = $('#wr-total-mm').val();
            $('#wr-total-time').val(th !== '' && tm !== '' ? th + ':' + tm : '');
        };
        $wr.on('change', 'select[id^="wr-"]', sync);
        sync();

        var fail = function (msg, $focus) {
            Swal.fire({icon: 'warning', title: 'Check the times', text: msg});
            if ($focus) { $focus.trigger('focus'); }
            return false;
        };
        // Bound on the form itself so it runs before the shared busy-button handler.
        $wr.on('beforeSubmit', function () {
            sync();
            var total = toMins($('#wr-total-time').val());
            if (total === null || total < 1) { return fail('Pick the hours and minutes you worked (at least 1 minute).', $('#wr-total-hh')); }
            var from = toMins($('#wr-from-time').val()), to = toMins($('#wr-to-time').val());
            if (from !== null && to !== null) {
                if (to <= from) { return fail('"To" must be later than "From".', $('#wr-to-h')); }
                if (total > to - from) {
                    return fail('Total time cannot be more than the From–To window (' + pad(Math.floor((to - from) / 60)) + ':' + pad((to - from) % 60) + ').', $('#wr-total-hh'));
                }
            }
            return true;
        });
    }

    // Work report form: HH:MM total time — digits and one colon only.
    $(document).on('input', '.js-hhmm', function () {
        var v = this.value.replace(/[^\d:]/g, '');
        var i = v.indexOf(':');
        if (i !== -1) { v = v.slice(0, i + 1) + v.slice(i + 1).replace(/:/g, ''); }
        if (v !== this.value) { this.value = v; }
    });

})(jQuery);
