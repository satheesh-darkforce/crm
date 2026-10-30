<?php
$this->title = 'Add Inward Invoice';
echo $this->render('_form', compact('model', 'vendor', 'ledgerCategories', 'paymentCategories'));
?>
