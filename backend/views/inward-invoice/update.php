<?php
$this->title = 'Edit Inward Invoice — #' . $model->invoice_number;
echo $this->render('_form', compact('model', 'vendor', 'ledgerCategories', 'paymentCategories'));
?>
