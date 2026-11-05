# Project Analysis — CRM for Green Divine

Prepared 2026-11-05 as Phase 1 of `new-feature.md`. This is the roadmap for turning the Yii2 application (Project B) into Green Divine's CRM, using their current website admin (Project A) and their business as the functional reference.

---

## 1. The client: Green Divine

Source: https://greendivine.in and the live site data in `current-live/greendivine/data/content/`.

| | |
|---|---|
| Business | Artificial turf, vertical gardens (living walls), artificial plants, creepers, sports turf arenas, commercial landscaping |
| Base / area | Koramangala, Bengaluru — services Bangalore (in-house crew, no sub-contractors) |
| Since | 2017 · claims 500+ installations, 2,000+ clients, 50+ corporate projects |
| Customers | B2C (homes, balconies, terraces) and B2B (Prestige Group, Karle Infra, HAL, PVR INOX, Joyalukkas, Shriram Capital …) |
| Sales model | **Quote-based.** No online checkout. Enquiry (form / WhatsApp / call) → free site visit + measurement within 48 h → 3D mock-up and **line-item quotation within 24 h** → installation → handover + aftercare (e.g. 1-year maintenance plan) |

### How they price and sell

* Products are sold **by area (per sq.ft)**, **by weight (per kg — pebbles)**, **per piece / panel**, or as a **package/job** (rooftop landscape package, sports arena).
* Website prices are labels: an offer price with a struck-through "was" price (e.g. ₹2,625, was ₹3,500 → 25% off) or "Starting ₹45 / kg", or "Custom quote". All 18 products currently hide the price ("Get Quote").
* Specs are free-form key/value pairs (Pack Size, Sizes, Colours, Use Case, Timeline, Aftercare, Warranty …), plus a features list.
* Quotations combine **supply** (goods → HSN, GST) and **installation / services** (→ SAC, GST), so a product master must support both goods and services with their own tax code and GST rate.

### What Green Divine needs from a CRM

1. **Leads** from the website quote form / WhatsApp / calls, with source, area (sq.ft), site-visit scheduling and follow-ups.
2. **Clients** (homes and corporates) with contacts, GSTIN and site addresses.
3. **Products & services catalogue** — the same 6 categories as the website, unit (sq.ft / kg / piece / job), offer price + MRP, GST rate, HSN/SAC, specs and features.
4. **Quotations** built from catalogue items (qty × rate, discount, GST), with expiry and status, PDF to send on WhatsApp/email.
5. **Invoices** from accepted quotations, with partial payments (advance before installation, balance at handover).
6. **Employees / HR** for the installation crew — attendance, leave, holidays.

---

## 2. Reference admin panel (Project A) — audit

Location: `C:\xampp\htdocs\current-live\greendivine\admin` (44 PHP files, ~3,800 lines, flat-file storage).

| Area | What it actually does |
|---|---|
| Storage | No database. Every content type is a PHP array file in `data/content/*.php`, saved atomically with 20 rolling backups (`inc/store.php`) |
| Auth | Config-file users, login throttling, CSRF, activity log |
| **Products** | List with manual ordering, add/edit: name, slug, category, short + full description, price label, was-price, badge, "Today's Deal" (single), show-price toggle (off = "Get Quote"), images, features (one per line), specs (key/value repeater), installation gallery, SEO meta |
| **Categories** | slug, name, icon, image, short description; delete blocked while products use it |
| Website content | Blogs, FAQs, landing pages, page content, hero, services, service details, team, reviews, clients/logos, certifications, stats, process steps, why-us, festival banner, quote popup, nav, settings, backups, users |
| CRM features | **None** — no leads, clients, quotations, invoices, employees or HR. Enquiries go to WhatsApp/email only |

Conclusion: Project A contributes the **product/category data model** and the client's vocabulary; every CRM workflow has to come from the Yii2 application.

---

## 3. Yii2 application (Project B) — audit

Yii2 advanced template, backend app only, MySQL `crm`, Bootstrap 5 + shared kit (`page-ui.css/js`, `_ui/_head`, `_ui/_empty`, `_ui/_pager`), per-module asset bundles, `UserAccess` route permissions (admin / sub-admin / developer / client), soft deletes (`status=0`). Modules 1–8 of the earlier redesign are done (UI kit, validation, security).

| Required module | Yii2 today | Status |
|---|---|---|
| Client Management | `client` CRUD, contacts, GST, addresses, access permissions | **Available** — needs a client 360° page (quotes, invoices, payments, leads) |
| Product Management | Only `delivery_product_details` (name, HSN, unit — DC item list) and IoT inventory | **Missing** as a sales catalogue |
| Quotation Management | `client_quotation` = header + estimate amount + attached files, versions | **Partial** — no line items, no tax/discount maths, no statuses sent/accepted/rejected/expired, no PDF from items |
| Invoice Management | Tax/Proforma/Credit note, free-text items, receipts, GL posting, PDF | **Available** — needs product picker, quotation → invoice conversion, due date / overdue status |
| Employee Management | `developer` (employees), ex-employees, payslips | **Available** |
| HR Management | Attendance, leave, holidays, RFID, payroll | **Available** |
| Leads Management | Leads, follow-ups, followers, lead master, conversion, upload | **Available** — add source "Website/WhatsApp", area and site-visit date |

---

## 4. Gap analysis

**Missing:** product & service catalogue with categories, pricing, GST and specs; quotation line items; quotation → invoice conversion; client 360° page; CRM dashboard figures.

**Partial:** quotations (header only), lead → client conversion (exists, needs prefill into quotation), invoice items (free text, no product link).

**Needs improvement:** sidebar shows ~20 modules — must show only Dashboard + 7 modules (others hidden, documented in `HIDDEN_MODULES.md`).

**Preserve:** all existing modules, tables, routes and data; DC, PO, IoT, AMC, work reports, payroll keep working.

---

## 5. Product Management — design (built first)

**Decision: extend the existing item master instead of creating a second product table.** `delivery_product_details` already is the company's item list (name, HSN, unit) and is referenced by delivery challans (`dc_products.product_id`). The spec forbids duplicate tables, so the migration adds nullable columns to it and adds one new table for categories. DC keeps working unchanged (its model `DeliveryProduct` reads the same rows); the new `Product` model is the catalogue view of the same table.

| Column (new unless noted) | Purpose |
|---|---|
| `item_name` (existing) | Product / service name |
| `hsn_code` (existing) | HSN (goods, 4–8 digits) or SAC (services, 6 digits) |
| `units` (existing) | Selling unit — area, length, weight, volume, count or service (see 5.1); the price is per one unit |
| `product_code` | SKU, unique among active items |
| `category_id` → `product_category.id` | Category (Artificial Grass, Vertical Gardens …) |
| `item_type` | 1 = Product (goods), 2 = Service (installation, maintenance) |
| `short_description`, `description` | Card line and full description |
| `sale_price`, `mrp` | Unit price (per one unit, before GST) and list ("was") price per unit; discount % derived |
| `price_on_request` | "Get quote" items with no fixed rate |
| `gst_rate` | 0 / 5 / 12 / 18 / 28 % |
| `features`, `specs` | JSON list / JSON key–value (same shape as the website) |
| `image` | Main image (safe upload) |
| `is_active` | Active / inactive (separate from soft delete `status`) |
| `created_by`, `updated_at`, `updated_by` | Audit |

New table `product_category`: name, slug, description, sort_order, status, audit columns.

Financial history rule for later phases: quotation and invoice lines will **copy** name, unit, rate, HSN/SAC and GST rate at the time of creation, so later product edits never change issued documents.

### 5.1 Units and unit price

Green Divine sells the same kind of item in very different measures (turf per square foot, edging per metre, pebbles per kilogram, panels per piece, installation per job). Every item therefore has **one selling unit** and the price is always entered as the **unit price — the price for one unit, before GST**. The form asks "How is it sold?" first and then shows the price field as `₹ … per <unit>`.

| Measured by | Units | Quantity on quotes |
|---|---|---|
| Area | Sq.ft, Sq.m | decimals allowed (e.g. 120.5 Sq.ft) |
| Length | Mtr, Rft, Ft, Cm | decimals allowed |
| Weight | Kg, Gram, Ton | decimals allowed |
| Volume | Ltr, Cu.ft | decimals allowed |
| Quantity (count) | Nos, Piece, Panel, Roll, Set, Pair, Box, Bag, Bunch | whole numbers only |
| Service / time | Job, Lot, Visit, Hour, Day, Month, Year | decimals allowed |

Rules:

* `line amount = quantity (in the item's unit) × unit price`; GST is applied on top per line.
* The unit is copied onto each quotation/invoice line, so a quote always reads "120 Sq.ft × ₹85 / Sq.ft" even if the product's unit changes later.
* Old data spelt differently ("NOS", "sqft", "m") is shown and saved in the canonical form (`Product::normaliseUnit()`).
* The picker API (`product/search`) returns `unit`, `unit_group`, `qty_decimals`, `price`, `gst_rate`, `hsn` so the line editor can set the quantity step and defaults.

---

## 6. Product ↔ Quotation ↔ Invoice mapping

Products, quotations and invoices must be linked so that a quote can be built from the catalogue, an accepted quote becomes an invoice without retyping, and every amount can be traced back.

```
product_category ─┐
                  └─< Product (delivery_product_details)
                            │ picked on
                            ▼
client ─< client_quotation ─< quotation line (new: client_quotation_items)
              │ accepted → convert
              ▼
client ─< invoice ─< invoice_details (+ product_id, unit, gst_rate, discount …)
              └─< invoice_receipt (payments)
```

### Line fields and where they come from

| Line field | Quotation line | Invoice line (`invoice_details`) | Filled from the product | Editable on the line |
|---|---|---|---|---|
| Product link | `product_id` (new) | `product_id` (new, nullable — old free-text lines stay valid) | `id` | yes (pick another) |
| Description | `description` | `particulars` (existing) | name + short description | yes |
| HSN / SAC | `hsn_sac` | `sac` (existing) | `hsn_code` | yes |
| Unit | `unit` | `unit` (new) | `units` | no (comes with the product) |
| Quantity | `quantity` decimal(12,3) | `quantity` (existing decimal(12,4)) | — | yes; whole numbers for count units |
| Unit price | `unit_price` | `unit_rate` (existing) | `sale_price` (blank when "price on request") | yes |
| Discount | `discount_percent` | `discount_percent` (new) | — | yes |
| GST rate | `gst_rate` | `gst_rate` (new) | `gst_rate` | yes |
| Taxable amount | `taxable_amount` | `amount` (existing) | qty × price − discount | calculated |
| GST amount | `gst_amount` | `gst_amount` (new) | taxable × rate | calculated |

### Rules

1. **Snapshot, not reference:** the line keeps its own copy of description, HSN/SAC, unit, unit price and GST rate. Editing or deactivating a product never changes an existing quotation or invoice. `product_id` is kept for reporting ("how much artificial grass did we sell").
2. **Only active items** can be picked on new quotations and invoices; inactive or removed items stay on old documents.
3. **Per-line GST:** a Green Divine quote mixes goods (e.g. turf, 12 %) and installation (18 %). Today `invoice` stores one SGST/CGST/IGST percent for the whole invoice, so lines get their own `gst_rate`/`gst_amount`, and the invoice header totals (`sub_total`, `sgst_amount`, `cgst_amount`, `igst_amount`, `net_total`) become the **sum of the lines**, split CGST+SGST (same state, Karnataka) or IGST (other state). The header columns stay, so existing reports, PDFs and GL posting keep working.
4. **Quotation → invoice:** converting an accepted quotation copies every line as-is (product, description, HSN/SAC, unit, quantity, unit price, discount, GST) into a new draft invoice for the same client, stores `quotation_id` on the invoice, and marks the quotation as invoiced so it cannot be converted twice. Partial invoicing (advance / balance) is done by editing quantities or adding an "Advance" line on the draft.
5. **Totals are calculated on the server** from the lines on every save (never trusted from the browser) and rounded per line to 2 decimals.
6. **Delivery challans** keep using the same product rows, so supplied goods, quotes and invoices all point at one item list.

### Database changes needed for the mapping (later phases)

* New `client_quotation_items` (quotation lines, fields as above) and quotation columns `valid_until`, `quote_status` (draft / sent / accepted / rejected / expired / invoiced), `sub_total`, `discount_total`, `gst_total`, `grand_total`.
* `invoice_details`: add `product_id` (FK, nullable), `unit`, `discount_percent`, `gst_rate`, `gst_amount`.
* `invoice`: add `quotation_id` (FK, nullable), `due_date`.

---

## 7. Database changes

* `m261105_000001_product_catalogue` — creates `product_category`, adds the columns above to `delivery_product_details` with indexes and the category foreign key. Additive only; guarded so it is safe to re-run.
* The database was built from a SQL dump, so the `migration` history table was missing and all 20 existing migrations looked pending. They are already reflected in the schema; they were recorded as applied with `php yii migrate/mark m261101_000001_add_lead_converted_dropped_dates` before running the new migration. **Production needs the same one-time `migrate/mark` before `migrate`.**

---

## 8. Implementation order

1. **Product Management** (this phase) — catalogue, categories, search API for the quotation picker.
2. Navigation — sidebar reduced to Dashboard + 7 modules, `HIDDEN_MODULES.md`.
3. Leads tweaks (source, area, site visit) and client 360° page.
4. Quotation line items (products, qty in the item unit, discount, per-line GST, totals, statuses, expiry, PDF) and quotation → invoice, following section 6.
5. Invoice product picker and per-line GST (section 6), due date, overdue / partial status.
6. Employee / HR review (mostly existing).
7. CRM dashboard with real figures; integration and full regression testing.
