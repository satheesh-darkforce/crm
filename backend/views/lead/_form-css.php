<?php
/**
 * Shared form styling for the Leads module: Yii marks required fields with a
 * `required` class on the field wrapper — show the red asterisk from that, so
 * the marker always matches the model rules.
 */
?>
<style>
.lead-form-scope .required > .form-label::after,
.lead-form-scope .required > label::after{content:" *";color:var(--app-danger,#dc3545);font-weight:700}
.lead-form-scope .form-control::placeholder,
.lead-form-scope textarea::placeholder{color:#9aa4b2;opacity:1}
</style>
