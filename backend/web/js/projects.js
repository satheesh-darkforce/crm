/**
 * Projects + AMC module — loaded by ProjectAsset after page-ui.js.
 *
 * - Project list: inline status change; Completed → live date → "Start AMC?"
 * - Project view: "Start AMC" (asks for the live date first)
 * - Date range filters: [data-range-from="#toId"] keeps To ≥ From
 * - Project form: phase/milestone builder, cost/hours rules, milestone status & delete
 * - AMC form: charge type toggle and computed amount
 *
 * URLs come from window.projectUrls (ProjectAsset::register()).
 */
(function ($) {
    'use strict';

    var urls = window.projectUrls || {};
    var UI = window.PageUI;
    var COMPLETED = '3';

    function today() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    // ── Live date → Start AMC ───────────────────────────────────────────

    /**
     * Ask for the project's live / updated date, save it, then (if allowed)
     * offer to start AMC. opts: {projectId, defaultDate, canStartAmc, goStraightToAmc}
     */
    function askLiveDate(opts) {
        Swal.fire({
            title: 'When did the project go live?',
            html: '<p class="small text-muted mb-2">The AMC start date defaults to this date.</p>'
                + '<input type="text" id="live-date-input" class="form-control form-control-sm text-center mx-auto" '
                + 'placeholder="YYYY-MM-DD" autocomplete="off" readonly style="max-width:220px;background:#fff;cursor:pointer">',
            confirmButtonText: opts.goStraightToAmc ? 'Save & continue' : 'Save',
            showCancelButton: true,
            cancelButtonText: 'Skip',
            allowOutsideClick: false,
            didOpen: function () {
                var $in = $('#live-date-input');
                if ($.fn.kvDatepicker) {
                    $in.kvDatepicker({format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true, endDate: '0d'})
                       .kvDatepicker('update', opts.defaultDate || today());
                } else {
                    $in.prop('readonly', false).val(opts.defaultDate || today());
                }
            },
            preConfirm: function () {
                var v = $.trim($('#live-date-input').val() || '');
                if (!/^\d{4}-\d{2}-\d{2}$/.test(v)) { Swal.showValidationMessage('Pick the live date.'); return false; }
                if (v > today()) { Swal.showValidationMessage('The live date cannot be in the future.'); return false; }
                return v;
            }
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            UI.showLoading();
            UI.post(urls.setLiveDate, {id: opts.projectId, live_updated_date: r.value})
                .done(function (res) {
                    if (!res || !res.success) {
                        Swal.fire({icon: 'warning', title: 'Not saved', text: (res && res.message) || 'The date could not be saved.'});
                        return;
                    }
                    var startUrl = urls.amcStart + (urls.amcStart.indexOf('?') === -1 ? '?' : '&') + 'project_id=' + encodeURIComponent(opts.projectId);
                    if (opts.goStraightToAmc) { window.location.href = startUrl; return; }
                    if (!opts.canStartAmc) {
                        Swal.fire({icon: 'success', title: 'Live date saved', timer: 1300, showConfirmButton: false});
                        return;
                    }
                    Swal.fire({title: 'Start an AMC for this project?', icon: 'question', showCancelButton: true, confirmButtonText: 'Start AMC', cancelButtonText: 'Not now'})
                        .then(function (r2) { if (r2.isConfirmed) { window.location.href = startUrl; } });
                })
                .fail(UI.showFailure);
        });
    }

    // Project list: inline status (no confirmation, as before — the change is easy to undo).
    $(document).on('focus', '.js-project-status', function () {
        if ($(this).data('prev') === undefined) { $(this).data('prev', $(this).val()); }
    });
    $(document).on('change', '.js-project-status', function () {
        var $el = $(this), status = String($el.val());
        if (status === String($el.data('prev'))) { return; }
        $el.addClass('is-busy').prop('disabled', true);
        UI.post(urls.changeStatus, {id: $el.data('id'), status: status})
            .done(function (res) {
                $el.removeClass('is-busy').prop('disabled', false);
                if (!res || !res.success) {
                    UI.revert($el);
                    Swal.fire({icon: 'warning', title: 'Not changed', text: (res && res.message) || 'The status could not be changed.'});
                    return;
                }
                $el.data('prev', status);
                if (res.completed) {
                    askLiveDate({projectId: $el.data('id'), canStartAmc: !!$el.data('can-start-amc')});
                } else {
                    Swal.fire({icon: 'success', title: res.message || 'Status updated', timer: 1200, showConfirmButton: false});
                }
            })
            .fail(function (xhr, ts) { UI.revert($el); UI.showFailure(xhr, ts); });
    });

    // Project view: Start AMC.
    $(document).on('click', '.js-start-amc', function (e) {
        e.preventDefault();
        askLiveDate({projectId: $(this).data('id'), defaultDate: $(this).data('live-date'), goStraightToAmc: true});
    });

    // ── Date range filters ──────────────────────────────────────────────
    // <input data-range-from="#to-id"> keeps the To picker from going before From.

    function syncRange($from) {
        var $to = $($from.data('range-from'));
        if (!$to.length || !$.fn.kvDatepicker) { return; }
        $to.kvDatepicker('setStartDate', $from.val() || null);
        $from.kvDatepicker('setEndDate', $to.val() || null);
    }
    $(function () { $('[data-range-from]').each(function () { syncRange($(this)); }); });
    $(document).on('changeDate change', '[data-range-from]', function () {
        var $from = $(this), $to = $($from.data('range-from'));
        if ($to.val() && $from.val() && $to.val() < $from.val()) { $to.val(''); }
        syncRange($from);
    });
    $(document).on('changeDate change', '[data-range-to]', function () {
        var $from = $($(this).data('range-to'));
        if ($from.length) { syncRange($from); }
    });

    // ── Project form ────────────────────────────────────────────────────

    var $form = $('#project-form');
    if ($form.length) {
        // Non-productive → cost optional (defaults to 0); IOT → hours optional (defaults to 0).
        var applyTypeRules = function () {
            var nonProductive = $('#project-project_type').val() === '2';
            var iot = $('#project-industry_type').val() === '2';
            $('#total_estimation_cost').closest('.mb-3').toggleClass('required', !nonProductive && !$('#total_estimation_cost').data('legacy-empty'));
            $('#total_estimation_hours').closest('.mb-3').toggleClass('required', !iot && !$('#total_estimation_hours').data('legacy-empty'));
            if (nonProductive && $('#total_estimation_cost').val() === '') { $('#total_estimation_cost').val('0'); }
            if (iot && $('#total_estimation_hours').val() === '') { $('#total_estimation_hours').val('0'); }
        };
        $(document).on('change', '#project-project_type, #project-industry_type', applyTypeRules);
        applyTypeRules();

        // Only digits and one decimal point in the estimate fields.
        $(document).on('input', '#total_estimation_cost, #total_estimation_hours', function () {
            var v = this.value.replace(/[^\d.]/g, ''), i = v.indexOf('.');
            if (i !== -1) { v = v.slice(0, i + 1) + v.slice(i + 1).replace(/\./g, ''); }
            if (v !== this.value) { this.value = v; }
            updatePhaseSum();
        });

        var phaseRow = 0;
        var statusOptions = $form.data('milestone-statuses') || {};

        var phaseHours = function () {
            var sum = 0;
            $('#phase-tbody .js-phase-hours').each(function () { var v = parseFloat($(this).val()); if (!isNaN(v)) { sum += v; } });
            return Math.round(sum * 100) / 100;
        };
        var updatePhaseSum = function () {
            var hasRows = $('#phase-tbody tr').length > 0;
            $('#phase-table-section').toggle(hasRows);
            $('#phase-empty').toggle(!hasRows);
            var sum = phaseHours(), total = parseFloat($('#total_estimation_hours').val()) || 0;
            $('#phase-sum').text(sum).closest('.phase-sum').toggleClass('is-mismatch', hasRows && total > 0 && Math.abs(sum - total) > 0.01);
            $('#phase-total').text(total || '—');
        };
        updatePhaseSum();

        var phaseError = function (msg) { Swal.fire({icon: 'warning', title: 'Check the milestone', text: msg}); };

        $(document).on('click', '#btn-add-phase', function () {
            var phase = parseInt($('#phase_input').val(), 10), milestone = parseInt($('#milestone_input').val(), 10),
                hours = parseFloat($('#milestone_hours_input').val());
            if (isNaN(phase) || isNaN(milestone) || isNaN(hours)) { return phaseError('Enter the phase, milestone and hours.'); }
            if (phase < 1 || phase > 15) { return phaseError('Phase must be between 1 and 15.'); }
            if (milestone < 1 || milestone > 15) { return phaseError('Milestone must be between 1 and 15.'); }
            if (hours <= 0 || hours > 500000) { return phaseError('Hours must be greater than 0 and at most 500,000.'); }

            var phases = [];
            $('#phase-tbody [data-phase]').each(function () { var p = Number($(this).data('phase')); if (phases.indexOf(p) === -1) { phases.push(p); } });
            if (!phases.length && phase !== 1) { return phaseError('The first phase must be 1.'); }
            if (phases.length && phase > Math.max.apply(null, phases) + 1) { return phaseError('Add phase ' + (Math.max.apply(null, phases) + 1) + ' before phase ' + phase + '.'); }

            var ms = [];
            $('#phase-tbody [data-phase="' + phase + '"]').each(function () { ms.push(Number($(this).data('milestone'))); });
            if (ms.indexOf(milestone) !== -1) { return phaseError('Milestone ' + milestone + ' already exists in phase ' + phase + '.'); }
            if (!ms.length && milestone !== 1) { return phaseError('The first milestone in phase ' + phase + ' must be 1.'); }
            if (ms.length && milestone > Math.max.apply(null, ms) + 1) { return phaseError('Add milestone ' + (Math.max.apply(null, ms) + 1) + ' in phase ' + phase + ' first.'); }

            phaseRow++;
            var opts = '';
            $.each(statusOptions, function (v, l) { opts += '<option value="' + v + '">' + $('<div>').text(l).html() + '</option>'; });
            var $tr = $('<tr class="js-new-phase" data-phase="' + phase + '" data-milestone="' + milestone + '">'
                + '<td data-label="Phase">' + phase + '<input type="hidden" name="phase_value[]" value="' + phase + '"></td>'
                + '<td data-label="Milestone">' + milestone + '<input type="hidden" name="milestone_value[' + phase + '][]" value="' + milestone + '"></td>'
                + '<td data-label="Hours">' + hours + '<input type="hidden" class="js-phase-hours" name="hours_value[' + phase + '][]" value="' + hours + '"></td>'
                + '<td data-label="Status"><select class="form-select form-select-sm" name="status_value[' + phase + '][]" aria-label="Status for phase ' + phase + ' milestone ' + milestone + '">' + opts + '</select></td>'
                + '<td class="text-end"><span class="ui-pill plain tone-accent me-2">New</span>'
                + '<button type="button" class="btn btn-outline-secondary btn-sm js-remove-new-phase" aria-label="Remove">&times;</button></td></tr>');
            $('#phase-tbody').append($tr);
            $('#phase_input, #milestone_input, #milestone_hours_input').val('');
            $('#phase_input').trigger('focus');
            updatePhaseSum();
        });
        $(document).on('keydown', '#phase_input, #milestone_input, #milestone_hours_input', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); $('#btn-add-phase').trigger('click'); }
        });
        $(document).on('click', '.js-remove-new-phase', function () { $(this).closest('tr').remove(); updatePhaseSum(); });

        // Saved milestones: status change and delete happen immediately (AJAX).
        $(document).on('focus', '.js-phase-status', function () { $(this).data('prev', $(this).val()); });
        $(document).on('change', '.js-phase-status', function () {
            var $el = $(this);
            Swal.fire({title: 'Change this milestone status?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, change'})
                .then(function (r) {
                    if (!r.isConfirmed) { UI.revert($el); return; }
                    $el.addClass('is-busy').prop('disabled', true);
                    UI.post(urls.phaseStatus, {project_id: $el.data('project'), phase_value: $el.data('phase'), milestone_value: $el.data('milestone'), status_value: $el.val()})
                        .done(function (res) {
                            $el.removeClass('is-busy').prop('disabled', false);
                            if (res && res.success) {
                                $el.data('prev', $el.val());
                                Swal.fire({icon: 'success', title: res.message || 'Updated', timer: 1200, showConfirmButton: false});
                            } else {
                                UI.revert($el);
                                Swal.fire({icon: 'warning', title: 'Not changed', text: (res && res.message) || 'The status could not be changed.'});
                            }
                        })
                        .fail(function (xhr, ts) { UI.revert($el); UI.showFailure(xhr, ts); });
                });
        });
        $(document).on('click', '.js-delete-phase', function () {
            var $btn = $(this), $tr = $btn.closest('tr');
            Swal.fire({
                title: 'Delete phase ' + $tr.data('phase') + ', milestone ' + $tr.data('milestone') + '?',
                text: 'Its work reports are deleted too. This cannot be undone from here.',
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', customClass: {confirmButton: 'is-danger'}
            }).then(function (r) {
                if (!r.isConfirmed) { return; }
                $btn.addClass('is-busy');
                var url = urls.deletePhase + (urls.deletePhase.indexOf('?') === -1 ? '?' : '&') + 'id=' + encodeURIComponent($btn.data('id'));
                UI.post(url, {})
                    .done(function (res) {
                        if (res && res.success) {
                            $tr.remove(); updatePhaseSum();
                            Swal.fire({icon: 'success', title: res.message || 'Deleted', timer: 1400, showConfirmButton: false});
                        } else {
                            $btn.removeClass('is-busy');
                            Swal.fire({icon: 'warning', title: 'Not deleted', text: (res && res.message) || 'The milestone could not be deleted.'});
                        }
                    })
                    .fail(function (xhr, ts) { $btn.removeClass('is-busy'); UI.showFailure(xhr, ts); });
            });
        });

        // Final checks that need the phase table (the server repeats them).
        $form.on('beforeValidate', function () { $(this).find('.js-form-alert').remove(); });
        $form.on('beforeSubmit', function () {
            var total = parseFloat($('#total_estimation_hours').val()) || 0, sum = phaseHours();
            if ($('#phase-tbody .js-new-phase').length && total > 0 && Math.abs(sum - total) > 0.01) {
                Swal.fire({icon: 'warning', title: 'Hours do not add up',
                    text: 'Total Estimation Hours (' + total + ') must equal the sum of milestone hours (' + sum + ').'});
                return false;
            }
            return true;
        });
    }

    // ── AMC form ────────────────────────────────────────────────────────

    var $amc = $('#amc-form, #amc-renew-form');
    if ($amc.length) {
        var $type = $amc.find('[name$="[charge_type]"]'), $pct = $amc.find('[name$="[percentage]"]');
        var cost = parseFloat($('#amc-est-cost').data('cost')) || 0;
        var toggleCharge = function () {
            var isAmount = $type.val() === '2';
            $('.amc-amt-wrap').toggle(isAmount);
            $('.amc-pct-wrap').toggle(!isAmount);
        };
        var calc = function () {
            var p = parseFloat($pct.val());
            $('#amc-pct-amount').text(!isNaN(p) && cost > 0
                ? '₹' + (cost * p / 100).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})
                : '—');
        };
        $type.on('change', toggleCharge);
        $pct.on('input', calc);
        toggleCharge();
        calc();
    }

})(jQuery);
