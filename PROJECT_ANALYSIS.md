# Project Analysis — CRM for Green Divine

Prepared 2026-11-05 (leads section added 2026-11-06) as Phase 1 of `new-feature.md`. This is the roadmap for turning the Yii2 application (Project B) into Green Divine's CRM, using their current website admin (Project A) and their business as the functional reference.

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

## 2. Reference admin panels (Project A) — audit

There are two Green Divine admins. The **business admin** is the real reference for CRM features; the website admin only manages the public site.

### 2a. Business admin — `__admin/greendivine/template` (main reference)

A local copy is kept at `__admin/` in this repo (git-ignored — it contains the live database connection). Plain PHP + MySQL (`leads`, `lead_notes`, `lead_attachments`, `project`, `project_notes`, `users`, `payment_history`, `daily_payments`, `expenses`, `office_expenses`, `inventory`, `attendance`, `tasks`, `contact_clicks` …), ~41,000 lines, JSON endpoints called from the pages, a mobile-app API and Firebase push notifications.

| Menu | What it does |
|---|---|
| Dashboard | Counts, today's follow-ups and meetings |
| Follow Ups | Follow-ups due by date and by employee; one page per salesperson (hard-coded files per employee) |
| Leads | View Leads (cards with tabs All / New / Hot / Warm / Cold), Quoted Leads, per-source pages (Google, Meta, IndiaMART, Just Dial), Dropped, Qualified |
| Projects | A lead marked **Qualified** automatically becomes a project (lead notes are copied); project list, completed, site visits, site updates, payments per project |
| Employees | Salary, manage, attendance |
| Inventory | Stock, add stock, suppliers, reports |
| Payments / Expenses | Payment history, daily payment tracker, office expenses |
| Quotation | VG calculator and turf (grass area) calculator that produce a quote |
| Other | Tasks, Exotel call log, WhatsApp, website click stats (call / WhatsApp clicks), ad-campaign lead capture (vertical garden ads form) |

### 2b. Website admin — `current-live/greendivine/admin`

Flat-file CMS (no database) for the public website: products (name, category, description, price label, was-price, features, specs, images), categories, blogs, FAQs, landing pages and other page content. Used as the reference for **Product Management** (section 5).

---

## 2.1 Leads — reference vs Yii2

### Reference (business admin) lead model

| Field | Notes |
|---|---|
| client_name, contact, email, client_city | Contact details (phone is the key field — leads come from calls / WhatsApp / ads) |
| lead_source | Google, Meta, IndiaMART, Just Dial, Reference, Walk-in, Other (+ website / ads forms) |
| title | **Products of interest**, multi-select: Artificial Grass, Artificial Vertical Garden, Sports Turf, Artificial Plants, Creepers and Bunches, Wooden Flooring, Vinyl Flooring, Wallpapers |
| requirement, wall_size, installation_time | What they want, approximate size, when they want it installed |
| status | New → Cold / Warm / **Hot Prospects** → **Qualified** (creates a project) or **Dropped** |
| sales_employee_id, meeting_employee_id, quotation_employee_id | Three owners: salesperson, site-visit person, quotation person |
| next_followup_date, meeting_date | Next call and site-visit date |
| is_quote_shared | "Quote is shared?" switch → Quoted Leads list |
| lead_notes (note_type), lead_attachments | Timeline notes and files |

Gaps in the reference: no validation, no permissions per page (hard-coded employee pages), API key in page source, SQL built by string concatenation, duplicated pages per employee.

### Yii2 leads today

Leads list / My / Assigned / Converted / Dropped, follow-ups with date-time and status (lead_followers), attachments, lead master (contacts import), upload from Excel, convert to client, role-based access (LeadAccess), pipeline status Open → Got Requirement → Gave Quotation → Waiting for Payment → Converted / Dropped, priority, single "assigned to".

### Gap and decisions

| Need (from reference) | Yii2 today | Decision |
|---|---|---|
| Lead source | Only "mode" of contact (Mail, Call, Skype…) | **Add** `lead_source` (+ detail, e.g. who referred) with Green Divine's sources; filter and counts by source |
| Hot / Warm / Cold | `priority` Low / Medium / High | **Reuse** `priority`, shown as **Lead temperature: Cold / Warm / Hot** (same column, same values) |
| Products of interest (multi) | Free-text "Project" | **Add** `lead_product_interest` linked to **product categories** (same list as Product Management) |
| Size / area, installation timing, requirement | — / — / description | **Add** `site_area` + `area_unit`, `expected_install_date`; description labelled **Requirement** |
| Site visit (meeting) date + person | Meeting attended date only | **Add** `site_visit_date`, `site_visit_by` |
| Quotation person | — | **Add** `quotation_by` (sales person stays `assigned_to`) |
| Quote shared | Status "Gave Quotation" | Keep the status now; link real quotations to leads in the Quotation phase |
| Qualified → project | Converted → client | Keep **Converted → client** (CRM flow Leads → Clients → Quotations); projects module is hidden |
| Notes timeline | Follow-ups with description | Keep follow-ups (already richer: date-time, owner, status) |
| Call / WhatsApp | — | **Add** one-tap Call and WhatsApp links on the lead page |
| Per-employee pages | My / Assigned lists + "assigned to" filter | Keep (no hard-coded pages) |
| Exotel, push notifications, website click stats, ad-form intake | — | Later (integrations); listed as future work |
| IT-company fields (Skype, value in dollars, industry, B2B stages) | Present | Keep the data; value shown in ₹, industry / offering / stage moved to a collapsed "More details" section |

---

## 2.2 Smart leads

All rules live in `backend/components/LeadInsight.php` and are calculated from existing data (nothing extra is stored).

| Feature | Where | How it works |
|---|---|---|
| **Lead score 0–100** | Lead page (ring), lead lists (badge), Today board | Hot +25 / Warm +12 · stage: requirement +10, quotation +20, waiting payment +30 · products of interest +5, area +5, requirement +5, value/budget +5 · site visit done +10 / booked +5 · installation wanted ≤ 30 days +10 / ≤ 90 days +5 · activity ≤ 7 days +10, > 30 days −10, > 60 days −20 · follow-up overdue −10, none scheduled −5. Bands: 60+ High, 35–59 Medium, below 35 Low. Converted = 100, dropped = 0 |
| **Next best actions** | Lead page | Most urgent first: owner deactivated / missing → reassign; follow-up overdue (days) / due today; no follow-up scheduled; installation wanted within 14 days; site visit today / tomorrow; warm/hot lead without a site visit; site visit done but no quotation (and no quotation owner); quotation shared 3+ days ago with no update; no activity for 30+ days; missing products or area for the estimate |
| **Duplicate detection** | Live on the lead form (as you type) + lead page + warning after save | Same phone (last 10 digits of every number in the field — old records hold several numbers separated by `;`) or same email, across leads and clients. Names and links are hidden for leads the user cannot open. Never blocks saving — repeat enquiries are normal |
| **Value estimate** | Lead page + "Estimate from products & area" button on the form (fills the estimated value) | Site area × active catalogue unit prices of each product of interest, only for products sold by a compatible unit (area: Sq.ft / Sq.m; length: Rft / Ft / Mtr / Cm, converted). Shows low – high and a typical value per product category |
| **Today's Follow-ups** (`lead/today`) | Leads menu | Overdue (last 60 days), due today, site visits this week, next 7 days, warm/hot leads with no follow-up, and stale follow-ups (over 60 days) kept separate. One-tap Call / WhatsApp. Managers pick any employee; others see their own leads |
| **Lead Insights** (`lead/insights`) | Leads menu (needs Manage Leads) | Leads, open, hot, converted, dropped and win rate by **source**, **product of interest** and **temperature** for a date range |

Lead visibility now also includes the **site-visit** and **quotation** owners (`LeadAccess::involvedCondition`).

**Data note:** the catalogue prices imported from the website (e.g. ₹2,625 for a balcony grass mat) look like per-item prices, not per Sq.ft. Estimates are only as good as the unit prices — Green Divine should confirm the real per-unit rates in Products.

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
| Leads Management | Leads, follow-ups, followers, lead master, conversion, upload | **Available** — needs Green Divine fields: source, temperature, products of interest, area, site visit, quotation owner (section 2.1) |

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

### Implemented (2026-11-11)

* **Shared line engine** `backend/components/LineItems.php` (validation, per-line maths, CGST/SGST/IGST split, totals by rate, amount in words) and the **line-item editor** `views/_ui/_line_items.php` + `js/line-items.js` (product search, fills description / HSN / unit / price / GST, live totals, whole-number quantities for count units, phone layout).
* **Quotations:** Items section on Add / Edit quotation (GST type, valid until, items, terms, notes — Green Divine's 70 % / 30 % terms by default). With items the estimation cost is the computed total. Status Draft / Sent / Accepted / Rejected / Invoiced (+ Expired when past validity), Duplicate, items table on the quotation page, and an items page in the quotation PDF (lines, GST summary, total in words, bank, terms, notes, signatory). Quotes without items work as before (estimate by hand + PDF).
* **Invoices:** domestic invoices (intra / inter-state) use the same editor with GST per line; header SGST / CGST / IGST amounts are the line sums, so lists, reports and ledger posting keep working. International / foreign-currency invoices and invoices saved with the classic editor keep the classic editor. Preview / print / PDF show a GST column and CGST/SGST or IGST per rate.
* **Quotation → invoice:** an Accepted quote with items has **Create invoice**; the invoice form opens with the client, title and items copied. Saving marks the quote Invoiced (locked); discarding the draft or cancelling the invoice puts it back to Accepted.

Invoice GST type always follows the client (state); a quote may be set to another GST type for a site in another state.

### Database changes for the mapping

Done in `m261111_000001_quotation_items_line_gst`: new `client_quotation_items`; `client_quotation` + `quote_status`, `valid_until`, `tax_mode`, `sub_total`, `discount_total`, `taxable_total`, `gst_total`, `grand_total`, `terms`, `notes`, `invoice_id`; `invoice_details` + `product_id`, `unit`, `discount_percent`, `gst_rate`, `gst_amount`; `invoice` + `quotation_id`. Still to add: `invoice.due_date` (overdue status).

---

## 7. Database changes

* `m261105_000001_product_catalogue` — creates `product_category`, adds the columns above to `delivery_product_details` with indexes and the category foreign key. Additive only; guarded so it is safe to re-run.
* `m261106_000001_lead_crm_fields` — adds to `lead`: `lead_source`, `source_detail`, `site_area`, `area_unit`, `expected_install_date`, `site_visit_date`, `site_visit_by`, `quotation_by` (indexed), and creates `lead_product_interest` (lead ↔ product category, unique pair, FKs with cascade). Additive only; existing leads keep working (source is required for new leads only). `priority` is reused as lead temperature (1 Cold, 2 Warm, 3 Hot).
* `m261111_000001_quotation_items_line_gst` — quotation line items, quotation status / totals, per-line GST on invoice lines, quotation link on invoices (section 6). Additive only.
* The database was built from a SQL dump, so the `migration` history table was missing and all 20 existing migrations looked pending. They are already reflected in the schema; they were recorded as applied with `php yii migrate/mark m261101_000001_add_lead_converted_dropped_dates` before running the new migration. **Production needs the same one-time `migrate/mark` before `migrate`.**

---

## 8. Implementation order

1. ✅ **Product Management** — catalogue, categories, unit pricing, search API for the quotation picker.
2. ✅ Navigation — sidebar reduced to Dashboard + 7 modules, `HIDDEN_MODULES.md`.
3. ✅ **Leads** for Green Divine (section 2.1): source, temperature, products of interest, area, installation date, site visit, quotation owner, Call / WhatsApp, list filters; then smart leads (section 2.2): score, next best actions, duplicate detection, value estimate, Today board, insights. Next for leads: website/ad form intake, client 360° page.
4. ✅ Quotation line items (products, qty in the item unit, discount, per-line GST, totals, statuses, expiry, PDF) and quotation → invoice (section 6).
5. ✅ Invoice product picker and per-line GST (section 6). Next: due date, overdue / partial status.
6. Employee / HR review (mostly existing).
7. ✅ **UI redesign** (section 9) and a Green Divine sales dashboard. Next: integration and full regression testing.

---

## 9. UI theme (2026-11-11)

The look follows the old Green Divine admin (`__admin/greendivine/template`): white sidebar with the green **Green Divine** wordmark, section labels (Sales / Catalogue / Team) and coloured icons; white top bar with lead search (a phone number searches by contact), user menu and a red **Logout** button; a title strip with breadcrumbs on every page; light grey canvas, white rounded cards, violet (#7366FF) actions, Rubik font, 40 px inputs.

* **One reusable file:** `backend/web/css/gd-theme.css`, loaded last by `views/layouts/main.php` (replaces the layout's inline styles). All colours are tokens at the top (`--gd-*`); they also feed the older `--app-*` (site.css / page-ui.css) and Bootstrap `--bs-*` variables, so every module follows. Covers sidebar, top bar, title strip, cards, buttons, inputs, tables, tabs, pills, dropdowns, modals, SweetAlert2, **Select2**, **DatePicker**, **TimePicker**, plus reusable dashboard pieces (`.gd-stats`, `.gd-list`, `.gd-bar`, `.gd-periods`) and a chip selector (`.gd-chips`).
* **Pickers:** `backend\components\FormPickers::date()`, `::time()`, `::select()` give the standard kartik DatePicker / TimePicker (`kartik-v/yii2-widget-timepicker`, 24 h, 5-minute steps, empty until picked) / Select2 settings. Lead and follow-up time fields now use the TimePicker.
* **Layout:** brand from `params['app.name']`; sidebar sections from `params['sidebar.sections']`; breadcrumbs from `$this->params['breadcrumbs']` or Home / module / page title. A page `<h1>` in `.ui-head` with the same text as the page title moves into the title strip.
* **Dashboard:** in Green Divine mode `site/index` shows sales figures (`backend\components\GdDashboard`, `views/site/index-gd.php`) for Today / 1 Week / 1 Month / 3 Months / 6 Months / All Time: new leads, follow-ups due (+ overdue), hot leads, conversions, open and won quotation value, invoiced, money to collect; follow-ups due with call buttons, leads by source, recent quotations, newest leads. Lead figures follow lead access; quotation / invoice blocks show only with access. The IT dashboard (domains, AMC, tasks) returns when `crm.simple` is off.
* Login page: `login.companyName` Green Divine, accent #7366FF.

### 9.1 Audit (2026-11-12)

Automated sweep of all 66 pages reachable from the menu (desktop + phone) plus an interactive check that opened every Select2 (69), date picker (29) and time picker (4) on 41 form / filter pages. Result: no PHP or JS errors, no sideways scroll on phones, no native date/time inputs, no plain dropdowns over 5 options.

* Converted to Select2: line-item **unit** (searchable) and **GST** (quotes + invoices), product unit and GST, employee branch / employment type / department / team, leave status filters, attendance team / branch / year / month, leave-settings duration, GST month report, lead page status.
* Green Divine mode: client list and client page show sales (section above in `HIDDEN_MODULES.md`), quotation list shows quote status and ₹ amount, live attendance and employee page drop task / timesheet parts, no GST popup for B2C buyers. "Developer" labels read "Employee".
* Fixed: client page invoice *Type* read the GST place-of-supply field (always showed Tax Invoice); removed a Font Awesome 4 link that 404'd on every page; asset URLs now carry `?v=<mtime>` (`assetManager.appendTimestamp`) so browsers load updated CSS / JS.
* **Document prefixes are configurable** in `backend/config/params.php`: `numbering.quote`, `numbering.invoice`, `numbering.employee`. They still hold the previous company's values (QIT, MAE, MAEINT, MAEMC) — set Green Divine's before the first real quotation / invoice.


### 9.2 Pagination (2026-11-12)

* **AJAX paging for every list:** `backend/web/js/ajax-pager.js` (loaded by the layout) handles pager and column-sort links inside `#main-content`: it fetches the page and swaps only that list's box (`[data-ajax-list]`, else the `.card` / `.grid-view` holding the table), updates the address (`pushState`, so refresh, bookmarks and Back / Forward work) and falls back to a normal load on any error. Lists inside a Pjax container (quotations, attendance) keep their own Pjax. Add `data-no-ajax` to a link to opt out.
* Client page: invoices and quotations are paged (20 each, `inv-page` / `q-page`); the tiles above still total everything.
* Tested on all 28 list pages: page 2 over AJAX with new rows, URL + active page update, Back, row menus after the swap, `page=99999`, filters kept in pager links, sorting.

### 9.3 Green Divine data reset (2026-11-12)

* All data of the previous company was removed (128 tables; backup outside the repo in `../db_backup_before_greendivine_wipe/`, one gzip file per table + `schema_only.sql`, plus the old images and uploads). Kept: migrations, currencies, shift time, RBAC items, payroll allowance types, ledger categories and the 15 system ledgers (balances zeroed; the sales ledger is now "Sales", `AccountingUtility` also accepts the old "IT Sale").
* Branding: `backend/web/images/logo.png` (Green Divine logo from greendivine.in), `letterhead.png` (invoice background), `favicon.png`; company details in `params['company']` read through `backend\components\Company` by the quotation PDF, invoice, payslip and challan templates. GSTIN and e-mail are empty until filled in.
* Numbering: invoices `GD/26-27/001` (export `GDEX`), quotations `GDQ/26-27/001`, employee codes `GD001`.
* All tables converted to **utf8mb4** (`m261112_000001_tables_utf8mb4`; ₹ and Indian-language text were being lost in latin1) and the connection charset is set in `common/config/main.php`.
* Demo data: 24 catalogue items, 8 clients, 14 leads with follow-ups, 8 quotations (draft / sent / accepted / invoiced), 3 invoices (paid, 70% advance, unpaid), 6 employees, fixed-date Karnataka holidays 2026–27, bank placeholder. People and contacts are fictitious (`@example.com`). GST rates / HSN codes on the catalogue are samples — check them.
