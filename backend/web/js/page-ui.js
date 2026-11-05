/**
 * Shared page UI behaviour for redesigned modules — loaded by PageUiAsset.
 *
 * window.PageUI:
 *   post(url, data)                 CSRF-protected JSON POST with a timeout
 *   failureMessage(xhr, textStatus) user-facing text, or null when the session expired
 *   showFailure(xhr, textStatus)    error / "session expired" popup
 *   showLoading(title)              blocking "Saving…" popup
 *   send(url, data, $select)        POST with loading popup; success → toast + reload,
 *                                   refusal / failure → message and $select reverted
 *   revert($select)                 put an inline select back to data-prev
 *
 * Markup hooks:
 *   [data-ui-modal="#id"][data-url]  load a partial into a modal (spinner, error + Retry)
 *   form.ui-form                     ActiveForm: busy submit button after client validation
 *   form.js-busy-form                plain form: busy submit button, no double submit
 */
(function ($) {
    'use strict';

    var TIMEOUT = 20000;

    // ── Requests ────────────────────────────────────────────────────────

    function post(url, data) {
        data = data || {};
        data[yii.getCsrfParam()] = yii.getCsrfToken();
        return $.ajax({url: url, type: 'POST', data: data, dataType: 'json', timeout: TIMEOUT});
    }

    function failureMessage(xhr, textStatus) {
        // Logged out: the server answers with X-Redirect and yii.js navigates to the login page.
        if (xhr && xhr.getResponseHeader && xhr.getResponseHeader('X-Redirect')) {
            return null;
        }
        var serverMsg = xhr && xhr.responseJSON && xhr.responseJSON.message;
        if (textStatus === 'timeout') {
            return 'The server took too long to respond. Please try again.';
        }
        if (!xhr || xhr.status === 0) {
            return 'Could not reach the server. Check your connection and try again.';
        }
        switch (xhr.status) {
            case 400: return serverMsg || 'The request was not valid. Refresh the page and try again.';
            case 403: return serverMsg || 'You do not have permission to do this.';
            case 404: return serverMsg || 'This record no longer exists. Refresh the page.';
            case 405: return 'This action is not allowed. Refresh the page and try again.';
        }
        if (xhr.status >= 500) {
            return 'Something went wrong on the server. Please try again in a moment.';
        }
        if (textStatus === 'parsererror') {
            return 'Unexpected response from the server. Refresh the page and try again.';
        }
        return serverMsg || 'Request failed. Please try again.';
    }

    function showFailure(xhr, textStatus) {
        var msg = failureMessage(xhr, textStatus);
        if (msg === null) {
            Swal.fire({icon: 'info', title: 'Session expired', text: 'Taking you to the login page…', showConfirmButton: false, allowOutsideClick: false});
            return;
        }
        Swal.fire({icon: 'error', title: 'Could not complete the request', text: msg});
    }

    function showLoading(title) {
        Swal.fire({title: title || 'Saving…', allowOutsideClick: false, allowEscapeKey: false, didOpen: function () { Swal.showLoading(); }});
    }

    function revert($select) {
        if ($select && $select.length) {
            $select.val($select.data('prev')).removeClass('is-busy').prop('disabled', false);
        }
    }

    function handleResult(res, $select) {
        if (res && res.success) {
            Swal.fire({icon: 'success', title: res.message || 'Saved', timer: 1300, showConfirmButton: false})
                .then(function () { location.reload(); });
            return;
        }
        revert($select);
        Swal.fire({icon: 'warning', title: 'Not changed', text: (res && res.message) || 'The change could not be saved.'});
    }

    function send(url, data, $select) {
        if ($select) { $select.addClass('is-busy').prop('disabled', true); }
        showLoading();
        post(url, data)
            .done(function (res) { handleResult(res, $select); })
            .fail(function (xhr, textStatus) { revert($select); showFailure(xhr, textStatus); });
    }

    window.PageUI = {
        post: post, failureMessage: failureMessage, showFailure: showFailure,
        showLoading: showLoading, send: send, revert: revert
    };

    // ── Modals that load a partial ──────────────────────────────────────

    function loadModal($modal, url) {
        var $body = $modal.find('.modal-body');
        $body.html('<div class="ui-modal-state"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading…</div>');
        $.ajax({url: url, type: 'GET', dataType: 'html', timeout: TIMEOUT})
            .done(function (html) {
                $body.html($.trim(html) ? html : '<div class="ui-modal-state">Nothing to show.</div>');
            })
            .fail(function (xhr, textStatus) {
                var msg = failureMessage(xhr, textStatus);
                if (msg === null) { msg = 'Your session has expired. Taking you to the login page…'; }
                var $state = $('<div class="ui-modal-state text-danger"></div>').text(msg);
                $state.append('<div class="mt-2"><button type="button" class="btn btn-outline-secondary btn-sm js-modal-retry">Retry</button></div>');
                $body.empty().append($state);
                $body.find('.js-modal-retry').on('click', function () { loadModal($modal, url); });
            });
    }

    $(document).on('click', '[data-ui-modal]', function (e) {
        e.preventDefault();
        var $modal = $($(this).data('ui-modal'));
        if (!$modal.length) { return; }
        loadModal($modal, $(this).data('url'));
        bootstrap.Modal.getOrCreateInstance($modal[0]).show();
    });

    // ── Busy state on submit (no double saves) ──────────────────────────

    /** Lock the submit button; false when the form is already submitting. */
    function markBusy($form) {
        var $btn = $form.find('[type=submit]').first();
        if ($form.data('ui-busy')) { return false; }
        $form.data('ui-busy', true);
        $btn.data('label', $btn.html()).addClass('is-busy')
            .html('<span class="spinner-border me-1" role="status" aria-hidden="true"></span>' + ($btn.data('busy-text') || 'Saving…'));
        return true;
    }

    // ActiveForm forms: only after client-side validation passes.
    $(document).on('beforeSubmit', 'form.ui-form', function () {
        return markBusy($(this));
    });
    // Plain forms.
    $(document).on('submit', 'form.js-busy-form', function (e) {
        if (!markBusy($(this))) { e.preventDefault(); }
    });
    // Back/forward cache can restore a page with the button still busy.
    $(window).on('pageshow', function () {
        $('.ui-page [type=submit].is-busy').each(function () {
            $(this).removeClass('is-busy').html($(this).data('label'));
            $(this).closest('form').removeData('ui-busy');
        });
    });

})(jQuery);
