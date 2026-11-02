<?php
/**
 * AJAX handlers shared by the lead lists and lead view:
 * lead status, active/inactive, closing follow-ups and deleting attachments.
 *
 * @var \yii\web\View $this
 */

use yii\helpers\Json;
use yii\helpers\Url;

$urls = Json::htmlEncode([
    'status'   => Url::to(['/lead/change-status']),
    'active'   => Url::to(['/lead/change-lead-status']),
    'followup' => Url::to(['/lead/change-followup-status']),
    'deleteAt' => Url::to(['/lead/delete-attachment']),
    'create'   => Url::to(['/lead-followers/create']),
]);

$this->registerJs(<<<JS
var leadUrls = {$urls};

function leadPost(url, data) {
    data[yii.getCsrfParam()] = yii.getCsrfToken();
    return jQuery.ajax({url: url, type: 'POST', data: data, dataType: 'json'});
}
function leadFail(xhr) {
    var msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) || 'Request failed. Please try again.';
    Swal.fire({icon: 'error', title: 'Error', text: msg});
}
function leadDone(res, \$revert) {
    if (res.success) {
        Swal.fire({icon: 'success', title: res.message, timer: 1300, showConfirmButton: false})
            .then(function () { location.reload(); });
    } else {
        if (\$revert) { \$revert.val(\$revert.data('prev')); }
        Swal.fire({icon: 'error', title: 'Not changed', text: res.message});
    }
}

// Lead pipeline status — Dropped asks for a reason.
jQuery(document).on('change', '.js-lead-status', function () {
    var \$el = jQuery(this), id = \$el.data('id'), status = \$el.val();
    var label = \$el.find('option:selected').text();
    var send = function (reason) {
        leadPost(leadUrls.status, {id: id, status: status, reason: reason || ''})
            .done(function (res) { leadDone(res, \$el); })
            .fail(function (xhr) { \$el.val(\$el.data('prev')); leadFail(xhr); });
    };
    if (String(status) === '6') {
        Swal.fire({
            title: 'Drop this lead?', input: 'textarea', inputLabel: 'Reason',
            inputPlaceholder: 'Why is this lead being dropped?', showCancelButton: true, confirmButtonText: 'Drop Lead',
            inputValidator: function (v) { return !jQuery.trim(v) ? 'Please enter a reason' : null; }
        }).then(function (r) { r.isConfirmed ? send(r.value) : \$el.val(\$el.data('prev')); });
    } else {
        Swal.fire({title: 'Change status to "' + label + '"?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, change'})
            .then(function (r) { r.isConfirmed ? send('') : \$el.val(\$el.data('prev')); });
    }
});

// Active / Inactive
jQuery(document).on('change', '.js-lead-active', function () {
    var \$el = jQuery(this);
    Swal.fire({title: 'Change status?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, change'})
        .then(function (r) {
            if (!r.isConfirmed) { \$el.val(\$el.data('prev')); return; }
            leadPost(leadUrls.active, {id: \$el.data('id'), status: \$el.val()})
                .done(function (res) { leadDone(res, \$el); })
                .fail(function (xhr) { \$el.val(\$el.data('prev')); leadFail(xhr); });
        });
});

// Close a follow-up. The only open follow-up can't just be closed — offer "Create & Close".
jQuery(document).on('click', '.js-close-followup', function (e) {
    e.preventDefault();
    var \$btn = jQuery(this), id = \$btn.data('id'), leadId = \$btn.data('lead'), onlyOpen = \$btn.data('only-open') == 1;
    var createUrl = leadUrls.create + (leadUrls.create.indexOf('?') === -1 ? '?' : '&') + 'id=' + leadId + '&followup=' + id;
    Swal.fire({
        title: 'Close this follow-up?',
        text: onlyOpen ? 'This is the only open follow-up. Create the next follow-up and close this one?' : 'Close it now, or create the next follow-up and close this one.',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: !onlyOpen,
        confirmButtonText: 'Create & Close',
        denyButtonText: 'Close Follow-up'
    }).then(function (r) {
        if (r.isConfirmed) { window.location.href = createUrl; }
        else if (r.isDenied) {
            leadPost(leadUrls.followup, {followupid: id}).done(function (res) { leadDone(res); }).fail(leadFail);
        }
    });
});

// Remove a lead attachment (edit form)
jQuery(document).on('click', '.js-delete-attachment', function (e) {
    e.preventDefault();
    var id = jQuery(this).data('id');
    Swal.fire({title: 'Delete this attachment?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc3545'})
        .then(function (r) {
            if (r.isConfirmed) { leadPost(leadUrls.deleteAt, {id: id}).done(function (res) { leadDone(res); }).fail(leadFail); }
        });
});
JS, \yii\web\View::POS_READY, 'lead-status-js');
