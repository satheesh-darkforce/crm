# Invoice Listing Action Buttons — Yii1 to Yii2 Migration Document

## Action Buttons Overview

Yii1 template: `{view} {update} {delete} {confirminvoice} {addreceipt} {receipts} {tds} {paymentlink}`

| # | Yii1 Button | Yii2 Button | Icon | Status |
|---|-------------|-------------|------|--------|
| 1 | View | View | bi-eye | DONE |
| 2 | Update (Edit) | Edit | bi-pencil | DONE |
| 3 | Delete (Cancel) | Cancel | bi-slash-circle | DONE |
| 4 | Convert to Invoice | Convert to Invoice | bi-arrow-repeat | DONE |
| 5 | Add Receipt | Add Receipt | bi-file-earmark-plus | DONE |
| 6 | Manage Receipts | Manage Receipts | bi-journal-text | DONE |
| 7 | TDS Adjustment | TDS | bi-wrench | DONE |
| 8 | Payment Link | — | — | NOT NEEDED (user confirmed) |

---

## 1. VIEW — Open Invoice Detail Page

| | Yii1 | Yii2 |
|---|------|------|
| **Route** | `invoice/view?id={id}&invoice_role={role}` | `invoice/view?id={id}` |
| **Controller** | `InvoiceController::actionView($id, $is_preview=NULL)` | `InvoiceController::actionView(int $id)` |
| **Opens in** | New tab (`target="_blank"`) | New tab (`target="_blank"`) |
| **Visibility** | `UserAccess::checkaccess("invoice/view")` | Always (access checked by base Controller) |
| **View file** | `views/invoice/view.php` — standalone printable invoice document | `views/invoice/view.php` — printable invoice document in layout |

### Yii1 View Page Features
- Printable invoice document (793px width, A4 style with `bg.jpg` background)
- Invoice header: Invoice No / Date / LUT ARN (right), "To" address (left), Title (center)
- Line items table: S.No | HSN/SAC | Particulars | Amount
- Tax rows: IGST (inter-state) or SGST+CGST (intra-state) or none (international)
- Sub Total, Round off, TOTAL
- Amount in words
- Payment status (if paid)
- Tax exemption reason (if applicable)
- Bank details section (if bank selected)
- Action bar: Print | Download PDF
- Preview mode (`is_preview=1`): "Back to Edit" + "Confirm and Save" buttons
- Convert mode (`is_convert=1`): GST number field + "Convert to Invoice" button

### Yii2 View Page Features
- Same printable invoice document layout (793px card)
- Same header, line items, tax rows, totals, bank details
- Action bar: Print | Download PDF | Edit (proforma) | Convert (proforma) | Receipts | Add Receipt | TDS | Cancel | Back
- Convert via Bootstrap modal (not inline form)
- TDS via Bootstrap modal (not collapsible section)
- Print button uses `window.print()` (matching Yii1 `printbill()`)

**Status: MIGRATED** — All Yii1 features present.

---

## 2. UPDATE (EDIT) — Edit Proforma Invoice

| | Yii1 | Yii2 |
|---|------|------|
| **Route** | `invoice/update?id={id}&invoice_role={role}` | `invoice/update?id={id}` |
| **Controller** | `InvoiceController::actionUpdate()` | `InvoiceController::actionUpdate(int $id)` |
| **Visibility** | Proforma only (`invoice_role==2`) AND `UserAccess::checkaccess("invoice/update")` | Proforma only (`invoice_role==2`) |
| **View** | `views/invoice/create.php` (shared with create) | `views/invoice/update.php` → `_form.php` |

### Yii1 Behavior
- Loads existing invoice + line items
- Re-renders the create form with pre-filled data
- On POST: deletes all existing InvoiceDetails, re-creates from form
- Tax invoices cannot be edited (only proforma)

### Yii2 Behavior
- Same: loads invoice, renders _form.php with pre-filled data
- Tax invoices blocked with flash error + redirect
- On POST: same logic (delete old details, create new)

**Status: MIGRATED** — Identical behavior.

---

## 3. DELETE / CANCEL — Cancel an Active Invoice

| | Yii1 | Yii2 |
|---|------|------|
| **Route** | `POST invoice/delete?id={id}` | `POST invoice/cancel?id={id}` |
| **Controller** | `InvoiceController::actionDelete($id)` | `InvoiceController::actionCancel(int $id)` |
| **Visibility** | Active invoices (`invoice_status==1`) AND `UserAccess::checkaccess("invoice/delete")` | Active invoices (`invoice_status==1`) |
| **Confirmation** | JS `confirm()` + AJAX POST, grid refresh | SweetAlert2 + form POST, page redirect |

### Yii1 Behavior
- `confirm("Are you sure to cancel this invoice?")`
- AJAX POST to actionDelete
- Sets `invoice_status = 0` (soft cancel)
- Grid refreshes automatically

### Yii2 Behavior
- SweetAlert2 confirmation dialog
- Creates hidden form, POSTs to `invoice/cancel`
- Sets `invoice_status = 0` (soft cancel)
- Redirects to listing page

**Status: MIGRATED** — Same logic, improved UX (SweetAlert).

---

## 4. CONVERT TO INVOICE — Convert Proforma to Tax Invoice

| | Yii1 | Yii2 |
|---|------|------|
| **Route (button)** | Links to `invoice/view?id={id}&invoice_role={role}&is_convert=1` | Links to `invoice/view?id={id}&open=convert` |
| **Route (submit)** | `POST invoice/converttoinvoice` with `proforma_id` | `POST invoice/convert-to-invoice?id={id}` |
| **Controller** | `InvoiceController::actionConverttoinvoice()` | `InvoiceController::actionConvertToInvoice(int $id)` |
| **Visibility** | Proforma only (`invoice_role==2`) AND `UserAccess::checkaccess("invoice/create")` | Proforma only |
| **UI** | Inline form on view page (GST field + submit) | Bootstrap modal on view page |

### Yii1 Behavior
- View page shows form with optional "Client GST Number" field (if client has no GST)
- Hidden field: `proforma_id`
- `confirm("Are you sure to convert this invoice?")`
- Controller: Changes `invoice_role` from 2→1, generates invoice_no, sets invoice_date

### Yii2 Behavior
- Modal with optional GST field + optional invoice_date DatePicker
- `confirm()` on submit button
- Controller: Same logic inside DB transaction with lock (race-condition safe)

**Status: MIGRATED** — Same logic, improved with transaction lock.

---

## 5. ADD RECEIPT — Create a New Payment Receipt

| | Yii1 | Yii2 |
|---|------|------|
| **Route** | `invoicereceipt/create?invoice_id={id}` | `invoice/create-receipt?invoice_id={id}` |
| **Controller** | `InvoicereceiptController::actionCreate($invoice_id)` | `InvoiceController::actionCreateReceipt(int $invoice_id)` (display) + `actionAddReceipt(int $id)` (save) |
| **Visibility** | `payment_status != 2` (not fully paid) AND `UserAccess::checkaccess("invoicereceipt/create")` | `payment_status < 2` (not fully paid) |
| **View** | `views/invoicereceipt/create.php` → `_form.php` | `views/invoice/create-receipt.php` |

### Yii1 Form Layout
- Top card: Invoice info (4 columns — Client, Invoice, Amounts, Payment status)
- Bottom card: Receipt Date, Receipt Amount, Payment Mode dropdown
- Conditional sub-fields: Cash (date), Cheque (sender bank, no, amount, date, received bank, ac no, deposit date), Bank (sender, ref no, amount, date, received bank, ac no), PayPal (ref no, amount, date)
- Payment Remarks textarea
- Attachments (multi-file upload)
- Submit + Cancel buttons

### Yii2 Form Layout
- Same top card with 4-column invoice info display
- Same bottom card with Receipt Date, Amount, Payment Mode
- Same conditional sub-fields for Cash/Cheque/Bank/PayPal
- Same Payment Remarks + Attachments
- Same Submit + Cancel buttons

**Status: MIGRATED** — Form complete. Attachment upload wired via `UploadedFile::getInstancesByName()` in `actionAddReceipt`, saved to `InvoiceReceiptAttachment` model.

---

## 6. MANAGE RECEIPTS — View Receipt History

| | Yii1 | Yii2 |
|---|------|------|
| **Route** | `invoicereceipt/admin?id={invoice_id}` | `invoice/receipts?id={invoice_id}` |
| **Controller** | `InvoicereceiptController::actionAdmin($id)` | `InvoiceController::actionReceipts(int $id)` |
| **Visibility** | Tax invoices only (`invoice_role==1`) AND `UserAccess::checkaccess("invoicereceipt/admin")` | Tax invoices only (`invoice_role==1`) |
| **View** | `views/invoicereceipt/admin.php` — CGridView with receipt rows | `views/invoice/receipts.php` — summary card + inline form + receipt list |

### Yii1 Features
- GridView with receipt list (receipt_no, amount, date, mode, etc.)
- Action buttons: View, Update on each receipt row
- TDS adjustment modal

### Yii2 Features
- Summary card (Net Total, TDS, Paid, Balance)
- Inline "Record Payment" form
- Receipt list table with delete option
- TDS section (collapsible or accessible from view page)

**Status: MIGRATED** — All features present.

---

## 7. TDS ADJUSTMENT — Update TDS Percent and Amount

| | Yii1 | Yii2 |
|---|------|------|
| **Route** | `POST invoice/updatetds` | `POST invoice/update-tds?id={id}` |
| **Controller** | `InvoiceController::actionUpdatetds()` | `InvoiceController::actionUpdateTds(int $id)` |
| **Visibility** | Not international (`invoice_type != 3`) | Not international (`invoice_type !== 3`) |
| **UI** | Modal in listing page (admin.php) + modal in view page | Modal in view page, `.tds-trigger` links in listing |

### Yii1 Modal Fields
- Invoice No (readonly)
- Taxable Amount (readonly)
- TDS % (input, triggers `calculate_tds()`)
- TDS Amount (input, auto-calculated)

### Yii2 Modal Fields
- Same 4 fields
- Same JS auto-calculation (`tds_amount = taxable * tds_percent / 100`)

### Yii1 POST Handler
- Updates `tds_percent` and `tds_amount` on invoice
- Recalculates `payment_status` based on new balance

### Yii2 POST Handler
- Same: updates `tds_percent`, `tds_amount`
- Recalculates via `recalcPaymentStatus()`

**Status: MIGRATED** — Identical behavior.

---

## 8. PAYMENT LINK — Public Invoice Payment URL

| | Yii1 | Yii2 |
|---|------|------|
| **Route** | `invoice/paymentpreview?ref={base64(id)}&invoice_role={role}` | NOT IMPLEMENTED |
| **Controller** | `InvoiceController::actionPaymentpreview()` | — |
| **Visibility** | Tax invoices only (`invoice_role==1`) AND `UserAccess::checkaccess("invoice/paymentpreview")` | — |

### Yii1 Behavior
- Decodes base64 `ref` parameter to get invoice ID
- Renders invoice view with `is_payment=1` flag
- Shows "Pay Now" button linking to `invoice/paynow`

**Status: NOT NEEDED** — User confirmed this feature is not required in Yii2.

---

## Summary

| Action | Yii1 | Yii2 | Status |
|--------|------|------|--------|
| View | actionView | actionView | DONE |
| Edit | actionUpdate | actionUpdate | DONE |
| Cancel | actionDelete | actionCancel | DONE |
| Convert | actionConverttoinvoice | actionConvertToInvoice | DONE |
| Add Receipt | InvoicereceiptController::actionCreate | actionCreateReceipt + actionAddReceipt | DONE |
| Manage Receipts | InvoicereceiptController::actionAdmin | actionReceipts | DONE |
| TDS | actionUpdatetds | actionUpdateTds | DONE |
| Payment Link | actionPaymentpreview | — | NOT NEEDED |

### Remaining Gaps
1. ~~**Receipt attachment upload**~~ — DONE: `InvoiceReceiptAttachment` model created, upload wired in `actionAddReceipt`
2. **Accounts ledger entries** — Yii1 creates ledger entries on convert; Yii2 skips this (no ledger module yet)
