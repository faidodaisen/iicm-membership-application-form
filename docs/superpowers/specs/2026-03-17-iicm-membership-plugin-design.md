# IICM Membership Application Plugin — Design Spec
**Date:** 2026-03-17
**Author:** fidodesign (https://fidodesign.net)
**Plugin Name:** IICM Membership Application
**Plugin Slug:** iicm-membership
**Client:** IICM Berhad (Registration No: 202501018937 / 1620351-U)

---

## 1. Overview

A WordPress plugin that digitizes the IICM Berhad membership application process. Applicants submit their membership application via a multi-step online form (embedded via shortcode). Admin staff view, manage, and export submissions through a custom admin dashboard.

### Scope
- Public-facing multi-step registration form (shortcode)
- Custom admin dashboard to view entries, update status, export data
- Email notifications to admin and applicant on submission
- Fee information display (shortcode)

### Out of Scope (V1)
- Online payment / payment gateway integration
- Applicant login / member portal
- Board approval workflow automation
- Status update email to applicant when admin changes status

---

## 2. Plugin Metadata

```php
Plugin Name:  IICM Membership Application
Plugin URI:   https://fidodesign.net
Description:  Online membership application form for IICM Berhad with admin dashboard.
Version:      1.0.0
Author:       fidodesign
Author URI:   https://fidodesign.net
License:      GPL-2.0+
Text Domain:  iicm-membership
```

---

## 3. File Structure

```
iicm-membership/
├── iicm-membership.php              # Main plugin bootstrap file
├── uninstall.php                    # Cleanup on uninstall
├── includes/
│   ├── class-activator.php          # Activation hook — create DB table
│   ├── class-deactivator.php        # Deactivation hook
│   ├── class-form-handler.php       # Form submission processing & validation
│   ├── class-database.php           # DB read/write/query helpers
│   ├── class-email.php              # Email notification logic
│   └── class-export.php             # CSV and Excel export
├── admin/
│   ├── class-admin.php              # Admin menu registration
│   ├── class-admin-dashboard.php    # Entries list page (custom UI)
│   ├── class-admin-detail.php       # Single entry view + status update
│   └── assets/
│       ├── admin.css                # Custom admin styles
│       └── admin.js                 # Admin interactivity (status update, etc.)
└── public/
    ├── class-shortcode.php          # Shortcode registration & rendering
    └── assets/
        ├── form.css                 # Form styles (clean white, IICM brand)
        └── form.js                  # Multi-step wizard logic & validation
```

---

## 4. Database

### Custom Table: `wp_iicm_applications`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | |
| `company_name` | VARCHAR(255) NOT NULL | |
| `company_reg_number` | VARCHAR(100) NOT NULL | |
| `registered_address` | TEXT | |
| `registered_postcode` | VARCHAR(20) | |
| `registered_city_state` | VARCHAR(100) | |
| `registered_country` | VARCHAR(100) | |
| `correspondence_address` | TEXT | |
| `correspondence_postcode` | VARCHAR(20) | |
| `correspondence_city_state` | VARCHAR(100) | |
| `correspondence_country` | VARCHAR(100) | |
| `telephone` | VARCHAR(50) | |
| `email` | VARCHAR(255) NOT NULL | |
| `website` | VARCHAR(255) | |
| `org_categories` | TEXT NOT NULL | JSON-encoded array of selected category values (e.g. `["public_retirement","insurance"]`) |
| `org_category_other` | VARCHAR(255) | If "Others" selected, the specified text |
| `aum` | VARCHAR(50) | `'below_100b'`, `'rm100b_above'`, `'none'` |
| `membership_type` | VARCHAR(50) | Set by admin on detail page: `'ordinary'` or `'associate'` |
| `membership_tier` | VARCHAR(50) | Set by admin on detail page: `'tier1'`, `'tier2'`, `'tier3'`, `'local'`, `'foreign'` |
| `rep_name` | VARCHAR(255) NOT NULL | Nominated representative |
| `rep_nric_passport` | VARCHAR(100) | |
| `rep_nationality` | VARCHAR(100) | |
| `rep_designation` | VARCHAR(100) | |
| `rep_email` | VARCHAR(255) | |
| `rep_address` | TEXT | |
| `rep_postcode` | VARCHAR(20) | |
| `rep_city_state` | VARCHAR(100) | |
| `rep_country` | VARCHAR(100) | |
| `rep_office_tel` | VARCHAR(50) | |
| `rep_handphone` | VARCHAR(50) | |
| `declarant_name` | VARCHAR(255) | Authorised signatory name |
| `declarant_designation` | VARCHAR(100) | |
| `declaration_agreed` | TINYINT(1) DEFAULT 0 | 1 = applicant ticked declaration checkbox |
| `declaration_agreed_at` | DATETIME | Timestamp when declaration was agreed |
| `company_profile_file` | VARCHAR(500) | WordPress uploads-relative path (e.g. `iicm-membership/2026/03/file.pdf`). Full URL reconstructed via `wp_upload_dir()['baseurl']` at render time. |
| `status` | VARCHAR(20) DEFAULT 'pending' | Values: `pending`, `processing`, `approved`, `rejected` |
| `admin_notes` | TEXT | Internal notes by admin (set on detail page) |
| `submitted_at` | DATETIME NOT NULL | Populated by PHP: `current_time('mysql')` at submission |
| `updated_at` | DATETIME | Updated by PHP: `current_time('mysql')` on any DB update |

Table created on plugin activation using `dbDelta()`.

---

## 5. Frontend Form

### Shortcode
```
[iicm_membership_form]
```

### Design
- **Style:** Clean White with IICM brand colors
  - Primary: `#0078D4` (IICM Blue)
  - Accent: `#E87722` (IICM Orange)
  - Required asterisk: orange (`#E87722`)
- **Typography:** System UI stack (`-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`)
- **Layout:** Centered card, max-width 760px, white background, subtle box-shadow, rounded corners (8px)

### Multi-Step Wizard

**Header (persistent across steps):**
- IICM logo / wordmark
- Numbered step indicator: `[1] Organisation Profile → [2] Nominated Representative → [3] Declaration`
- Active step highlighted in blue, completed steps show checkmark

**Step 1 — Organisation Profile**
- Company Name (text, required)
- Company Registration Number (text, required)
- Registered Address (textarea, required)
- Postcode / City State / Country (3 inline fields)
- Correspondence Address (textarea, optional — with "Same as registered address" checkbox)
- Correspondence Postcode / City State / Country
- Telephone No (text)
- Email (email, required)
- Website Address (url, optional)
- Category of Organisation (checkbox group, select all that apply):
  - Public retirement/pension/superannuation plan
  - Private retirement/pension/superannuation plan
  - Corporate retirement/pension/superannuation plan
  - Insurance
  - Fund management/asset management
  - Unit trust/other collective investment vehicle
  - Sovereign wealth fund
  - Public Listed Company
  - Corporate/Private Company
  - Association
  - Foreign Entities
  - Educational Institutions
  - Others (with text input to specify)
- Assets Under Management / AUM (radio, required):
  - Below RM100 billion
  - RM100 billion and above
  - None

**Step 2 — Nominated Representative**
- Name (text, required)
- NRIC / Passport Number (text)
- Nationality (text)
- Designation (text, required)
- Email (email, required)
- Correspondence Address (textarea)
- Postcode / City State / Country (3 inline fields)
- Office Tel No (text)
- Handphone No (text)

**Step 3 — Declaration & Submit**
- Declaration text block (read-only):
  > "I hereby declare and confirm to the best of my knowledge that the above information is true and correct."
- Checkbox: "I agree to the above declaration" (required)
- Authorised Signatory Name (text, required)
- Designation (text, required)
- File Upload: Company Profile (accept: PDF, DOC, DOCX, JPG, PNG — max 5MB, required)
- Submit button: "Submit Application"

### Duplicate Submission Handling

- On submission, check if `company_reg_number` already exists in DB with status `pending`, `processing`, or `approved`.
- If duplicate found: reject submission and display error: "An application for this company registration number already exists. Please contact `admin@iicm.org.my` for assistance."
- If `rejected` status exists for same reg number: allow resubmission (creates new record).

### Validation
- Client-side (JS): Required field check, email format, file type/size — before allowing step navigation and submission
- Server-side (PHP): Re-validate all fields, nonce verification, file upload sanitization

### Post-Submit
- Display thank you message on same page:
  > "Thank you, [Company Name]. Your membership application has been submitted successfully. We will be in touch shortly."
- Send email to admin and applicant (see Section 7)
- Store submission in database with status `pending`

---

## 6. Admin Dashboard

### Menu Location
- WordPress Admin → **IICM Membership** (top-level menu with dashicon)
  - → **Applications** (entries list — default)
  - → **Settings** (admin email config)

### Applications List Page (`class-admin-dashboard.php`)

**Design: Custom UI (non-WP_List_Table)**

**Stat Cards (row of 4):**
| Total | Processing | Approved | Rejected |
- Each card: colored left border (blue / orange / green / red), large count number, label
- Clickable — clicking a stat card filters the list to that status

**Filter Pills + Search:**

- Pills: All | Pending | Processing | Approved | Rejected (with counts)
- Search input (searches company name and registration number)
- Active pill highlighted in blue

**Entries Table:**
| Column | Content |
|---|---|
| Company | Company name (bold) + registration number (small, gray) |
| Category | First value from decoded `org_categories` array, comma-joined if multiple (e.g. "Public Retirement, Insurance") — truncated to 40 chars with ellipsis if longer |
| Membership | If `membership_type` is blank: show "—" (em dash). Otherwise badge: "Ordinary · T1", "Associate · Local", etc. |
| Status | Colored pill badge: Pending / Processing / Approved / Rejected |
| Submitted | Date submitted |
| Action | "View" button (blue, rounded) |

- Alternating row background (#fafafa)
- Pagination: 20 entries per page
- No default WP styles — fully custom CSS

**Export Buttons (top-right):**
- "Export CSV" (blue button)
- "Export Excel" (orange button)
- Export respects the **active status filter** (e.g. filtering to "Approved" exports only approved entries). If filter is "All", all records are exported.
- Export does **not** respect the search query — it always exports the full filtered status group.
- Filename format: `iicm-applications-all-2026-03-17.csv` / `iicm-applications-approved-2026-03-17.xlsx`

### Single Entry View Page (`class-admin-detail.php`)

Accessible via "View" button on list. Shows:
- All submitted form data (read-only, organized by section: Organisation Profile, Nominated Representative, Declaration)
- Company profile file: download link rendered as `wp_upload_dir()['baseurl'] . '/' . $record->company_profile_file`
- **Admin Management section:**
  - Membership Type dropdown: Ordinary / Associate (admin sets this — not collected from form)
  - Membership Tier dropdown: Tier 1 / Tier 2 / Tier 3 / Local Entity / Foreign Entity (contextual based on type)
  - Status dropdown: Pending / Processing / Approved / Rejected
  - Admin Notes textarea
  - "Update" button — submits via AJAX
    - AJAX action hook: `iicm_update_application`
    - Request: `{ action, nonce, id, status, membership_type, membership_tier, admin_notes }`
    - Response (JSON): `{ success: true }` or `{ success: false, message: "..." }`
    - On success: show inline green notice "Updated successfully." No page reload.
    - On failure: show inline red notice with error message.
- Back to list link

### Settings Page

Stored as WordPress options. Option keys:

| Setting              | Option Key                | Default                        |
| -------------------- | ------------------------- | ------------------------------ |
| Admin email(s)       | `iicm_admin_emails`       | `admin@iicm.org.my`            |
| Sender name          | `iicm_email_sender_name`  | `IICM Berhad`                  |
| Sender from address  | `iicm_email_sender_from`  | `admin@iicm.org.my`            |

---

## 7. Email Notifications

### On Form Submission

**To Admin:**
- **Subject:** `New Membership Application — [Company Name]`
- **Body:** Company name, reg number, category, AUM, membership type, rep name, rep email, date submitted, link to view in dashboard

**To Applicant:**
- **Subject:** `IICM Membership Application — Confirmation`
- **Body:** Thank you message, summary of submitted info, note that IICM will review and get in touch, contact: admin@iicm.org.my

Both emails sent via WordPress `wp_mail()` as **HTML** (`Content-Type: text/html`). Set via `add_filter('wp_mail_content_type', ...)` scoped per send, reset after. Sender name and from address use `iicm_email_sender_name` and `iicm_email_sender_from` options via `wp_mail_from` and `wp_mail_from_name` filters.

---

## 8. Fee Information Shortcode

```
[iicm_membership_fees]
```

Renders a styled HTML table showing:

**Ordinary Members:**
| Tier | Joining Fee | Annual Fee | Total |
| Tier 1 (AUM ≥ RM100b) | RM5,000 | RM30,000 | RM35,000 |
| Tier 2 (AUM < RM100b) | RM3,000 | RM15,000 | RM18,000 |
| Tier 3 (Industry groups) | RM3,000 | RM5,000 | RM8,000 |

**Associate Members:**
| Category | Joining Fee | Annual Fee | Total |
| Local Entity | RM3,000 | RM5,000 | RM8,000 |
| Foreign Entity | RM3,000 | RM10,000 | RM13,000 |

Note: "All payments made payable to IICM Berhad. Payment details provided upon Board approval."

---

## 9. Export

### CSV Export
- Uses PHP `fputcsv()`
- All columns from database table
- Filename: `iicm-applications-[status]-[date].csv`

### Excel Export
- Uses PHP to generate `.xlsx` via a lightweight library (e.g., PhpSpreadsheet or SimpleXLSXGen — no Composer required option preferred)
- Same columns as CSV
- Filename: `iicm-applications-[status]-[date].xlsx`

---

## 10. Security

- All form submissions protected with WordPress nonce (`wp_nonce_field` / `wp_verify_nonce`)
- All DB inputs sanitized: text fields → `sanitize_text_field()`, email → `sanitize_email()`, textarea → `sanitize_textarea_field()`, URLs → `esc_url_raw()`, `org_categories` array → each value sanitized with `sanitize_text_field()` individually before `wp_json_encode()`, `org_category_other` → `sanitize_text_field()`
- File uploads: whitelist allowed MIME types, store outside webroot or use WP uploads directory with randomized filename
- Admin pages: `current_user_can('manage_options')` capability check on all admin actions
- AJAX actions: nonce verification + capability check
- All output escaped with `esc_html()`, `esc_attr()`, `esc_url()`

---

## 11. Activation / Deactivation / Uninstall

### Activation (`class-activator.php`)

- Create `wp_iicm_applications` table using `dbDelta()` if not exists
- Store plugin version in option `iicm_membership_version`
- Set default options: `iicm_admin_emails` = `admin@iicm.org.my`, `iicm_email_sender_name` = `IICM Berhad`, `iicm_email_sender_from` = `admin@iicm.org.my`

### Deactivation (`class-deactivator.php`)

- No destructive action. Data and options are preserved.
- Flush rewrite rules.

### Uninstall (`uninstall.php`)

- Only runs when admin clicks "Delete" in WP plugins page
- Drop `wp_iicm_applications` table
- Delete all plugin options (`iicm_admin_emails`, `iicm_email_sender_name`, `iicm_email_sender_from`, `iicm_membership_version`)
- Delete uploaded files directory: `wp-content/uploads/iicm-membership/`
- Guard with `defined('WP_UNINSTALL_PLUGIN')` check

---

## 12. WordPress Compatibility

- Minimum WordPress: 5.8
- Minimum PHP: 7.4
- Tested up to: WordPress 6.7
- No external API dependencies
- No Composer required (all dependencies bundled if any)

---

## 13. Membership Logic (Reference Only)

Membership type is determined by admin after reviewing submission — the form collects enough data (category + AUM) for admin to determine applicable tier. The form does NOT auto-assign membership type.

| Member Type | Who | Tiers |
|---|---|---|
| Ordinary | Malaysian institutional investors (per MCII definition) | Tier 1 (AUM ≥ RM100b), Tier 2 (AUM < RM100b), Tier 3 (Industry groups) |
| Associate | PLCs, private companies, associations, foreign entities, educational institutions | Local Entity, Foreign Entity |

---

*Spec written by fidodesign. Based on IICM Berhad Membership Application Form (effective 1 January 2026, updated 09022026).*
