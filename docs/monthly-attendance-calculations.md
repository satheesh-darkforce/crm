# Monthly Attendance — Summary Column Calculations

This document explains how the eight summary columns on the **Monthly Attendance**
grid are calculated:

> **Total Leave · Total Abs · Total Late · Late LOP · Leave/Abs LOP · Total LOP · CL Applied · CL Backup**

- **Live code (Yii2):** `backend/views/attendance/monthly-attendance.php` (per‑developer totals loop)
- **Source of truth (Yii1):** `maestro_erp/protected/components/Utility.php`
  (`get_leave_late`, `get_late_lop`, `get_absent`, `get_cl_taken`) and
  `protected/views/attendance/monthlyattendance.php`

Every column is computed **per developer**, by looping over that developer's
`daily_attendance` rows for the selected month and accumulating a running total.

---

## 1. Where the numbers come from

The totals are built from the **`daily_attendance`** table (one row per developer
per day). The fields that drive the math:

| Field | Meaning |
|-------|---------|
| `leave_type` | Day classification (see codes below) |
| `is_present` | `1` = the person physically attended, `0` = did not |
| `is_holiday` | `1` = company holiday — **these rows are skipped entirely** |
| `morning_leave` / `evening_leave` | `1` = that half of the day was on leave |
| `morning_late` / `evening_late` | `1` = late check‑in / early check‑out for that half |
| `total_lop` | `1` = the day is a loss‑of‑pay day |
| `cl_debited` | Casual Leave consumed for the day: `0`, `0.5`, or `1` |

**`cl_backup`** is *not* in `daily_attendance` — it is a stored field on the
**`developer`** record.

### Leave type codes (`leave_type`)

| Code | Label | Badge |
|------|-------|-------|
| 1 | Present | P |
| 2 | On Duty | OD |
| 3 | Absent | AB |
| 4 | Holiday | H |
| 5 | Late | LA |
| 6 | Permission | PE |
| 7 | Leave | L |
| 8 | Half Day | HD |

> **Holiday rule:** the loop does `if ($da->is_holiday) continue;` — any row flagged
> as a holiday is excluded from **all** of the totals below.

---

## 2. Column‑by‑column

In the formulas below, each rule is evaluated **once per day** and the matching
amount is **added to the running total** for the month.

### Total Leave
Counts how many leave days the developer took (whole + half), regardless of whether
they were covered by Casual Leave or not.

| Add | Condition |
|-----|-----------|
| **+1** | `leave_type = 7` (Leave) **and** `total_lop = 1` **and** `is_present = 0` |
| **+0.5** | `leave_type = 8` (Half Day) **and** `morning_leave = 1` **and** `is_present = 1` |
| **+0.5** | `leave_type = 8` (Half Day) **and** `evening_leave = 1` **and** `is_present = 1` |

*(Yii1: `Utility::get_leave_late(2, …)`)*

### Total Abs
Counts the leave/absence days that are **not** backed by Casual Leave — i.e. the
days that actually cost the employee.

| Add | Condition |
|-----|-----------|
| **+1** | `leave_type = 7` **and** `total_lop = 1` **and** `is_present = 0` **and** `cl_debited = 0` |
| **+0.5** | `leave_type = 8` **and** `morning_leave = 1` **and** `is_present = 1` |
| **+0.5** | `leave_type = 8` **and** `evening_leave = 1` **and** `is_present = 1` |
| **+1** | `leave_type = 3` (Absent) |

*(Yii1: `Utility::get_absent(…)`)*

> The key difference from **Total Leave** is the `cl_debited = 0` check on the full‑day
> leave: if Casual Leave covered the day, it is **excluded** from Total Abs (it shows
> up under **CL Applied** instead).

### Total Late
Counts late instances. Each half‑day lateness = **1**, so a day that is late both
morning *and* evening contributes **2**.

```
if (morning_late = 1 OR evening_late = 1)
    Total Late += morning_late + evening_late
```

*(Yii1: `Utility::get_leave_late(1, …)`)*

### Late LOP
The loss‑of‑pay caused by lateness. Two late instances are penalised as one LOP day,
so each late instance = half a day.

```
Late LOP = Total Late × 0.5
```

*(Yii1: `Utility::get_late_lop(1, …)`)*

### Leave/Abs LOP
The loss‑of‑pay caused by leaves/absences. This column is simply **equal to Total Abs**
(the same value, reused as the "leave/absence" portion of LOP).

```
Leave/Abs LOP = Total Abs
```

*(Yii1 reuses `Utility::get_absent(…)` for this column.)*

### Total LOP
The grand total of loss‑of‑pay days = absence LOP + lateness LOP.

```
Total LOP = Total Abs + Late LOP
          = Leave/Abs LOP + Late LOP
```

*(Yii1: `get_absent(…) + get_late_lop(1, …)`)*

### CL Applied
How much **Casual Leave** the developer consumed in the month (whole + half),
counted only on days where `cl_debited > 0`.

| Add | Condition |
|-----|-----------|
| **+1** | `leave_type = 7` **and** `cl_debited = 1` **and** `is_present = 0` |
| **+0.5** | `leave_type = 8` **and** `morning_leave = 1` **and** `is_present = 1` **and** `cl_debited = 0.5` |
| **+0.5** | `leave_type = 8` **and** `evening_leave = 1` **and** `is_present = 1` **and** `cl_debited = 0.5` |

*(Yii1: `Utility::get_cl_taken(…)`)*

### CL Backup
The developer's accumulated Casual Leave balance. This is **not** computed from the
daily rows — it is read directly from the `developer.cl_backup` field and displayed
as‑is.

---

## 3. How the columns relate to each other

```
                       ┌─ covered by Casual Leave  ──►  CL Applied
A leave/absence day ───┤
                       └─ not covered (cl_debited=0) ─►  Total Abs ─► Leave/Abs LOP ─┐
                                                                                     ├─► Total LOP
A late instance ──────────────► Total Late ─► Late LOP (× 0.5) ──────────────────────┘
```

- **Total Leave** counts *all* leave days; each leave day then flows to **either**
  **CL Applied** (if Casual Leave paid for it) **or** **Total Abs** (if it was unpaid).
- **Total Abs** also includes full Absent days (`leave_type = 3`).
- **Late LOP** = lateness ÷ 2.
- **Total LOP** = **Leave/Abs LOP** + **Late LOP** — this is the figure payroll uses
  to dock pay.
- **CL Backup** is informational (remaining balance), independent of the month's tally.

---

## 4. Worked example

Suppose in one month a developer has these non‑holiday days:

| Day | leave_type | is_present | total_lop | cl_debited | m_leave | e_leave | m_late | e_late |
|-----|-----------|-----------|-----------|-----------|---------|---------|--------|--------|
| 1 | 7 (Leave) | 0 | 1 | 1 (CL) | – | – | 0 | 0 |
| 2 | 7 (Leave) | 0 | 1 | 0 | – | – | 0 | 0 |
| 3 | 8 (Half)  | 1 | – | 0.5 (CL) | 1 | 0 | 0 | 0 |
| 4 | 3 (Absent)| 0 | 1 | 0 | – | – | 0 | 0 |
| 5 | 1 (Present)| 1 | 0 | 0 | – | – | 1 | 1 |
| 6 | 1 (Present)| 1 | 0 | 0 | – | – | 1 | 0 |

Resulting totals:

| Column | Value | Why |
|--------|-------|-----|
| **Total Leave** | **2.5** | Day1 (+1) + Day2 (+1) + Day3 morning half (+0.5) |
| **Total Abs** | **2.5** | Day2 (+1, no CL) + Day3 morning half (+0.5) + Day4 Absent (+1). Day1 is excluded because CL covered it (`cl_debited = 1`). |
| **Total Late** | **3** | Day5 (1 + 1) + Day6 (1 + 0) |
| **Late LOP** | **1.5** | 3 × 0.5 |
| **Leave/Abs LOP** | **2.5** | mirrors Total Abs |
| **Total LOP** | **4.0** | Total Abs (2.5) + Late LOP (1.5) |
| **CL Applied** | **1.5** | Day1 full CL (+1) + Day3 half CL (+0.5) |
| **CL Backup** | from `developer.cl_backup` | stored balance, not computed |

> **Note on Day 3 (CL‑covered half‑day):** the Half‑Day rules in `get_absent` add
> `+0.5` for a Half‑Day regardless of `cl_debited`, so a CL‑covered half‑day is counted
> in *both* **CL Applied** (+0.5) and **Total Abs** (+0.5). This is exactly how the Yii1
> source behaves and is reproduced faithfully in Yii2 — flagged here so the overlap
> isn't mistaken for a bug.

---

## 5. Notes / parity caveats

- **Holidays are always excluded** (`is_holiday = 1` rows are skipped) before any column is tallied.
- The grid summary's **Total Late** counts only `morning_late + evening_late`. The Yii1
  *payroll/payslip* table additionally adds `getMonthlyTakenPermission()` (count of
  `is_permission = 1` days) to its "Total Late" figure. The on‑screen **grid** summary
  does **not** include permissions in Total Late — only the payroll computation does.
- All fractional results are displayed trimmed (e.g. `2.5`, `3`, `0`) — trailing
  `.0` / zeros are stripped for readability.
- These same formulas are re‑applied to the **previous month** inside the non‑admin
  payroll section at the bottom of the page.
</content>
</invoke>
