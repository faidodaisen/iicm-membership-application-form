# IICM Membership Application Plugin — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WordPress plugin that digitizes IICM Berhad's membership application process — a multi-step public form, custom admin dashboard, email notifications, CSV/Excel export, and a fee information shortcode.

**Architecture:** Vanilla PHP WordPress plugin with no Composer dependency. Public-facing multi-step wizard via shortcode, custom admin UI (no WP_List_Table), AJAX for admin status updates. All data stored in a single custom DB table `wp_iicm_applications`.

**Tech Stack:** PHP 7.4+, WordPress 5.8+, vanilla JS (ES5-compatible), CSS3, `dbDelta()` for DB, `wp_mail()` for email, `fputcsv()` for CSV, bundled `SimpleXLSXGen` (single-file, no Composer) for Excel.

**Spec:** `docs/superpowers/specs/2026-03-17-iicm-membership-plugin-design.md`

---

## File Map

```
iicm-membership/
├── iicm-membership.php              # Bootstrap: constants, includes, register hooks
├── uninstall.php                    # Drop table + delete options + delete uploads dir
├── includes/
│   ├── class-activator.php          # register_activation_hook: dbDelta, set default options
│   ├── class-deactivator.php        # register_deactivation_hook: flush_rewrite_rules
│   ├── class-database.php           # All DB CRUD: insert, get_by_id, get_list, update, count_by_status
│   ├── class-form-handler.php       # AJAX handler for form submit: validate, sanitize, file upload, save, trigger email
│   ├── class-email.php              # send_admin_email(), send_applicant_email() via wp_mail HTML
│   ├── class-export.php             # export_csv(), export_excel() — streams download
│   └── lib/
│       └── SimpleXLSXGen.php        # Bundled single-file Excel writer (shuchkin/simplexlsxgen)
├── admin/
│   ├── class-admin.php              # Admin menu registration + asset enqueue + Settings page
│   ├── class-admin-dashboard.php    # Entries list page (custom UI): stat cards, filter pills, table, pagination
│   ├── class-admin-detail.php       # Single entry view + AJAX iicm_update_application handler
│   └── assets/
│       ├── admin.css                # Custom admin UI styles (no WP defaults)
│       └── admin.js                 # Tier conditional logic + AJAX update (uses textContent, no innerHTML)
└── public/
    ├── class-shortcode.php          # [iicm_membership_form] + [iicm_membership_fees]
    └── assets/
        ├── form.css                 # Multi-step wizard styles (IICM brand: #0078D4 / #E87722)
        └── form.js                  # Step navigation, client-side validation, AJAX submit
```

---

## Chunk 1: Plugin Scaffold + DB Activation

**Files:**
- Create: `iicm-membership/iicm-membership.php`
- Create: `iicm-membership/uninstall.php`
- Create: `iicm-membership/includes/class-activator.php`
- Create: `iicm-membership/includes/class-deactivator.php`

### Task 1: Create plugin directory structure

- [ ] **Step 1: Create directories**

```bash
mkdir -p /c/laragon/www/wordpress/wp-content/plugins/iicm-membership/includes/lib
mkdir -p /c/laragon/www/wordpress/wp-content/plugins/iicm-membership/admin/assets
mkdir -p /c/laragon/www/wordpress/wp-content/plugins/iicm-membership/public/assets
```

- [ ] **Step 2: Verify**

```bash
find /c/laragon/www/wordpress/wp-content/plugins/iicm-membership -type d
```

Expected: 6 directories listed.

### Task 2: Main bootstrap file

- [ ] **Step 1: Create `iicm-membership/iicm-membership.php`**

```php
<?php
/**
 * Plugin Name:  IICM Membership Application
 * Plugin URI:   https://fidodesign.net
 * Description:  Online membership application form for IICM Berhad with admin dashboard.
 * Version:      1.0.0
 * Author:       fidodesign
 * Author URI:   https://fidodesign.net
 * License:      GPL-2.0+
 * Text Domain:  iicm-membership
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'IICM_MEMBERSHIP_VERSION',    '1.0.0' );
define( 'IICM_MEMBERSHIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IICM_MEMBERSHIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'includes/class-activator.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'includes/class-database.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'includes/class-email.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'includes/lib/SimpleXLSXGen.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'includes/class-export.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'includes/class-form-handler.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'admin/class-admin.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'admin/class-admin-dashboard.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'admin/class-admin-detail.php';
require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'public/class-shortcode.php';

register_activation_hook( __FILE__, array( 'IICM_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IICM_Deactivator', 'deactivate' ) );

new IICM_Admin();
new IICM_Shortcode();
new IICM_Form_Handler();
new IICM_Admin_Detail();
```

- [ ] **Step 2: Activate plugin in WordPress admin. Confirm no PHP fatal errors.**

### Task 3: Activator — create DB table + default options

- [ ] **Step 1: Create `includes/class-activator.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Activator {

    public static function activate() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'iicm_applications';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_name VARCHAR(255) NOT NULL,
            company_reg_number VARCHAR(100) NOT NULL,
            registered_address TEXT,
            registered_postcode VARCHAR(20),
            registered_city_state VARCHAR(100),
            registered_country VARCHAR(100),
            correspondence_address TEXT,
            correspondence_postcode VARCHAR(20),
            correspondence_city_state VARCHAR(100),
            correspondence_country VARCHAR(100),
            telephone VARCHAR(50),
            email VARCHAR(255) NOT NULL,
            website VARCHAR(255),
            org_categories TEXT NOT NULL,
            org_category_other VARCHAR(255),
            aum VARCHAR(50),
            membership_type VARCHAR(50),
            membership_tier VARCHAR(50),
            rep_name VARCHAR(255) NOT NULL,
            rep_nric_passport VARCHAR(100),
            rep_nationality VARCHAR(100),
            rep_designation VARCHAR(100),
            rep_email VARCHAR(255),
            rep_address TEXT,
            rep_postcode VARCHAR(20),
            rep_city_state VARCHAR(100),
            rep_country VARCHAR(100),
            rep_office_tel VARCHAR(50),
            rep_handphone VARCHAR(50),
            declarant_name VARCHAR(255),
            declarant_designation VARCHAR(100),
            declaration_agreed TINYINT(1) DEFAULT 0,
            declaration_agreed_at DATETIME,
            company_profile_file VARCHAR(500),
            status VARCHAR(20) DEFAULT 'pending',
            admin_notes TEXT,
            submitted_at DATETIME NOT NULL,
            updated_at DATETIME,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'iicm_membership_version', IICM_MEMBERSHIP_VERSION );

        if ( ! get_option( 'iicm_admin_emails' ) ) {
            update_option( 'iicm_admin_emails', 'admin@iicm.org.my' );
        }
        if ( ! get_option( 'iicm_email_sender_name' ) ) {
            update_option( 'iicm_email_sender_name', 'IICM Berhad' );
        }
        if ( ! get_option( 'iicm_email_sender_from' ) ) {
            update_option( 'iicm_email_sender_from', 'admin@iicm.org.my' );
        }
    }
}
```

- [ ] **Step 2: Deactivate and reactivate plugin. Verify `wp_iicm_applications` table created.**

```bash
# WP-CLI (if available):
wp db query "DESCRIBE wp_iicm_applications;" --path=/c/laragon/www/wordpress
# Or check via phpMyAdmin
```

### Task 4: Deactivator

- [ ] **Step 1: Create `includes/class-deactivator.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Deactivator {
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
```

### Task 5: Uninstall

- [ ] **Step 1: Create `uninstall.php`**

```php
<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'iicm_applications';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

delete_option( 'iicm_admin_emails' );
delete_option( 'iicm_email_sender_name' );
delete_option( 'iicm_email_sender_from' );
delete_option( 'iicm_membership_version' );

$upload_dir = wp_upload_dir();
$iicm_dir   = $upload_dir['basedir'] . '/iicm-membership';
if ( is_dir( $iicm_dir ) ) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $iicm_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ( $files as $fileinfo ) {
        $fileinfo->isDir() ? rmdir( $fileinfo->getRealPath() ) : unlink( $fileinfo->getRealPath() );
    }
    rmdir( $iicm_dir );
}
```

- [ ] **Step 2: Commit**

```bash
cd /c/laragon/www/wordpress/wp-content/plugins/iicm-membership
git init
git add .
git commit -m "feat: plugin scaffold, DB activation, uninstall"
```

---

## Chunk 2: Database Layer

**Files:**
- Create: `iicm-membership/includes/class-database.php`

### Task 6: Database class

- [ ] **Step 1: Create `includes/class-database.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Database {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'iicm_applications';
    }

    /** Insert a new application. Returns inserted ID or false. */
    public function insert( array $data ) {
        global $wpdb;
        $result = $wpdb->insert( $this->table, $data );
        return $result ? $wpdb->insert_id : false;
    }

    /** Get a single application by ID. */
    public function get_by_id( int $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id )
        );
    }

    /**
     * Check if company_reg_number has an active application.
     * Active = pending, processing, or approved.
     */
    public function has_active_application( string $reg_number ): bool {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE company_reg_number = %s
                 AND status IN ('pending','processing','approved')",
                $reg_number
            )
        );
        return (int) $count > 0;
    }

    /**
     * Get paginated list.
     * Returns [ 'items' => [], 'total' => int ]
     */
    public function get_list( string $status = '', string $search = '', int $per_page = 20, int $page = 1 ): array {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        $where  = 'WHERE 1=1';
        $args   = array();

        if ( ! empty( $status ) ) {
            $where .= ' AND status = %s';
            $args[] = $status;
        }
        if ( ! empty( $search ) ) {
            $where .= ' AND (company_name LIKE %s OR company_reg_number LIKE %s)';
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $count_sql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $total = (int) ( empty( $args )
            ? $wpdb->get_var( $count_sql )
            : $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) );

        $args[] = $per_page;
        $args[] = $offset;
        $sql    = "SELECT * FROM {$this->table} {$where} ORDER BY submitted_at DESC LIMIT %d OFFSET %d";
        $items  = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

        return array( 'items' => $items ? $items : array(), 'total' => $total );
    }

    /** Get all records filtered by status only (for export — ignores search). */
    public function get_all_for_export( string $status = '' ): array {
        global $wpdb;
        if ( ! empty( $status ) ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE status = %s ORDER BY submitted_at DESC",
                    $status
                )
            );
        }
        return $wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY submitted_at DESC" );
    }

    /** Count applications grouped by status. */
    public function count_by_status(): array {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) AS cnt FROM {$this->table} GROUP BY status"
        );
        $counts = array(
            'total'      => 0,
            'pending'    => 0,
            'processing' => 0,
            'approved'   => 0,
            'rejected'   => 0,
        );
        foreach ( $results as $row ) {
            if ( isset( $counts[ $row->status ] ) ) {
                $counts[ $row->status ] = (int) $row->cnt;
            }
            $counts['total'] += (int) $row->cnt;
        }
        return $counts;
    }

    /** Update an application record by ID. */
    public function update( int $id, array $data ): bool {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql' );
        $result = $wpdb->update( $this->table, $data, array( 'id' => $id ) );
        return $result !== false;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/class-database.php
git commit -m "feat: database CRUD layer"
```

---

## Chunk 3: Frontend Form

**Files:**
- Create: `iicm-membership/public/class-shortcode.php`
- Create: `iicm-membership/public/assets/form.css`
- Create: `iicm-membership/public/assets/form.js`

### Task 7: Shortcode class

- [ ] **Step 1: Create `public/class-shortcode.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Shortcode {

    public function __construct() {
        add_shortcode( 'iicm_membership_form', array( $this, 'render_form' ) );
        add_shortcode( 'iicm_membership_fees', array( $this, 'render_fees' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        wp_register_style(
            'iicm-form',
            IICM_MEMBERSHIP_PLUGIN_URL . 'public/assets/form.css',
            array(),
            IICM_MEMBERSHIP_VERSION
        );
        wp_register_script(
            'iicm-form',
            IICM_MEMBERSHIP_PLUGIN_URL . 'public/assets/form.js',
            array(),
            IICM_MEMBERSHIP_VERSION,
            true
        );
    }

    public function render_form() {
        wp_enqueue_style( 'iicm-form' );
        wp_enqueue_script( 'iicm-form' );
        wp_localize_script( 'iicm-form', 'iicmForm', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'iicm_submit_application' ),
        ) );

        ob_start();
        ?>
        <div class="iicm-form-wrap">

            <!-- Step Indicator -->
            <div class="iicm-steps">
                <div class="iicm-step active" data-step="1">
                    <span class="iicm-step-num">1</span>
                    <span class="iicm-step-label">Organisation Profile</span>
                </div>
                <div class="iicm-step-divider"></div>
                <div class="iicm-step" data-step="2">
                    <span class="iicm-step-num">2</span>
                    <span class="iicm-step-label">Nominated Representative</span>
                </div>
                <div class="iicm-step-divider"></div>
                <div class="iicm-step" data-step="3">
                    <span class="iicm-step-num">3</span>
                    <span class="iicm-step-label">Declaration</span>
                </div>
            </div>

            <form id="iicm-application-form" novalidate>
                <?php wp_nonce_field( 'iicm_submit_application', 'iicm_nonce' ); ?>

                <!-- ===== STEP 1: Organisation Profile ===== -->
                <div class="iicm-form-step" data-step="1">
                    <h2 class="iicm-section-title">Organisation Profile</h2>

                    <div class="iicm-field">
                        <label for="company_name">Company Name <span class="iicm-required">*</span></label>
                        <input type="text" id="company_name" name="company_name" required>
                    </div>

                    <div class="iicm-field">
                        <label for="company_reg_number">Company Registration Number <span class="iicm-required">*</span></label>
                        <input type="text" id="company_reg_number" name="company_reg_number" required>
                    </div>

                    <div class="iicm-field">
                        <label for="registered_address">Registered Address <span class="iicm-required">*</span></label>
                        <textarea id="registered_address" name="registered_address" rows="3" required></textarea>
                    </div>

                    <div class="iicm-row-3">
                        <div class="iicm-field">
                            <label for="registered_postcode">Postcode</label>
                            <input type="text" id="registered_postcode" name="registered_postcode">
                        </div>
                        <div class="iicm-field">
                            <label for="registered_city_state">City / State</label>
                            <input type="text" id="registered_city_state" name="registered_city_state">
                        </div>
                        <div class="iicm-field">
                            <label for="registered_country">Country</label>
                            <input type="text" id="registered_country" name="registered_country">
                        </div>
                    </div>

                    <div class="iicm-field iicm-same-address-wrap">
                        <label class="iicm-checkbox-label">
                            <input type="checkbox" id="same_as_registered" name="same_as_registered">
                            Same as registered address (Correspondence)
                        </label>
                    </div>

                    <div id="correspondence-fields">
                        <div class="iicm-field">
                            <label for="correspondence_address">Correspondence Address</label>
                            <textarea id="correspondence_address" name="correspondence_address" rows="3"></textarea>
                        </div>
                        <div class="iicm-row-3">
                            <div class="iicm-field">
                                <label for="correspondence_postcode">Postcode</label>
                                <input type="text" id="correspondence_postcode" name="correspondence_postcode">
                            </div>
                            <div class="iicm-field">
                                <label for="correspondence_city_state">City / State</label>
                                <input type="text" id="correspondence_city_state" name="correspondence_city_state">
                            </div>
                            <div class="iicm-field">
                                <label for="correspondence_country">Country</label>
                                <input type="text" id="correspondence_country" name="correspondence_country">
                            </div>
                        </div>
                    </div>

                    <div class="iicm-row-2">
                        <div class="iicm-field">
                            <label for="telephone">Telephone No</label>
                            <input type="text" id="telephone" name="telephone">
                        </div>
                        <div class="iicm-field">
                            <label for="email">Email Address <span class="iicm-required">*</span></label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>

                    <div class="iicm-field">
                        <label for="website">Website Address</label>
                        <input type="url" id="website" name="website" placeholder="https://">
                    </div>

                    <div class="iicm-field">
                        <label>Category of Organisation <span class="iicm-required">*</span></label>
                        <div class="iicm-checkbox-group">
                            <?php
                            $categories = array(
                                'public_retirement'    => 'Public retirement / pension / superannuation plan',
                                'private_retirement'   => 'Private retirement / pension / superannuation plan',
                                'corporate_retirement' => 'Corporate retirement / pension / superannuation plan',
                                'insurance'            => 'Insurance',
                                'fund_management'      => 'Fund management / asset management',
                                'unit_trust'           => 'Unit trust / other collective investment vehicle',
                                'sovereign_wealth'     => 'Sovereign wealth fund',
                                'public_listed'        => 'Public Listed Company',
                                'corporate_private'    => 'Corporate / Private Company',
                                'association'          => 'Association',
                                'foreign_entities'     => 'Foreign Entities',
                                'educational'          => 'Educational Institutions',
                                'others'               => 'Others',
                            );
                            foreach ( $categories as $value => $label ) : ?>
                                <label class="iicm-checkbox-label">
                                    <input type="checkbox" name="org_categories[]" value="<?php echo esc_attr( $value ); ?>">
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div id="org-category-other-wrap" style="display:none; margin-top:8px;">
                            <input type="text" id="org_category_other" name="org_category_other" placeholder="Please specify">
                        </div>
                    </div>

                    <div class="iicm-field">
                        <label>Assets Under Management (AUM) <span class="iicm-required">*</span></label>
                        <div class="iicm-radio-group">
                            <label class="iicm-radio-label">
                                <input type="radio" name="aum" value="below_100b" required>
                                Below RM100 billion
                            </label>
                            <label class="iicm-radio-label">
                                <input type="radio" name="aum" value="rm100b_above">
                                RM100 billion and above
                            </label>
                            <label class="iicm-radio-label">
                                <input type="radio" name="aum" value="none">
                                None
                            </label>
                        </div>
                    </div>

                    <div class="iicm-form-nav">
                        <button type="button" class="iicm-btn iicm-btn-next">Next: Nominated Representative &rarr;</button>
                    </div>
                </div>

                <!-- ===== STEP 2: Nominated Representative ===== -->
                <div class="iicm-form-step" data-step="2" style="display:none;">
                    <h2 class="iicm-section-title">Nominated Representative</h2>

                    <div class="iicm-row-2">
                        <div class="iicm-field">
                            <label for="rep_name">Name <span class="iicm-required">*</span></label>
                            <input type="text" id="rep_name" name="rep_name" required>
                        </div>
                        <div class="iicm-field">
                            <label for="rep_nric_passport">NRIC / Passport Number</label>
                            <input type="text" id="rep_nric_passport" name="rep_nric_passport">
                        </div>
                    </div>

                    <div class="iicm-row-2">
                        <div class="iicm-field">
                            <label for="rep_nationality">Nationality</label>
                            <input type="text" id="rep_nationality" name="rep_nationality">
                        </div>
                        <div class="iicm-field">
                            <label for="rep_designation">Designation <span class="iicm-required">*</span></label>
                            <input type="text" id="rep_designation" name="rep_designation" required>
                        </div>
                    </div>

                    <div class="iicm-field">
                        <label for="rep_email">Email Address <span class="iicm-required">*</span></label>
                        <input type="email" id="rep_email" name="rep_email" required>
                    </div>

                    <div class="iicm-field">
                        <label for="rep_address">Correspondence Address</label>
                        <textarea id="rep_address" name="rep_address" rows="3"></textarea>
                    </div>

                    <div class="iicm-row-3">
                        <div class="iicm-field">
                            <label for="rep_postcode">Postcode</label>
                            <input type="text" id="rep_postcode" name="rep_postcode">
                        </div>
                        <div class="iicm-field">
                            <label for="rep_city_state">City / State</label>
                            <input type="text" id="rep_city_state" name="rep_city_state">
                        </div>
                        <div class="iicm-field">
                            <label for="rep_country">Country</label>
                            <input type="text" id="rep_country" name="rep_country">
                        </div>
                    </div>

                    <div class="iicm-row-2">
                        <div class="iicm-field">
                            <label for="rep_office_tel">Office Tel No</label>
                            <input type="text" id="rep_office_tel" name="rep_office_tel">
                        </div>
                        <div class="iicm-field">
                            <label for="rep_handphone">Handphone No</label>
                            <input type="text" id="rep_handphone" name="rep_handphone">
                        </div>
                    </div>

                    <div class="iicm-form-nav">
                        <button type="button" class="iicm-btn iicm-btn-secondary iicm-btn-back">&larr; Back</button>
                        <button type="button" class="iicm-btn iicm-btn-next">Next: Declaration &rarr;</button>
                    </div>
                </div>

                <!-- ===== STEP 3: Declaration ===== -->
                <div class="iicm-form-step" data-step="3" style="display:none;">
                    <h2 class="iicm-section-title">Declaration</h2>

                    <div class="iicm-declaration-box">
                        <p>I hereby declare and confirm to the best of my knowledge that the above information is true and correct.</p>
                    </div>

                    <div class="iicm-field">
                        <label class="iicm-checkbox-label">
                            <input type="checkbox" id="declaration_agreed" name="declaration_agreed" value="1" required>
                            I agree to the above declaration <span class="iicm-required">*</span>
                        </label>
                    </div>

                    <div class="iicm-row-2">
                        <div class="iicm-field">
                            <label for="declarant_name">Authorised Signatory Name <span class="iicm-required">*</span></label>
                            <input type="text" id="declarant_name" name="declarant_name" required>
                        </div>
                        <div class="iicm-field">
                            <label for="declarant_designation">Designation <span class="iicm-required">*</span></label>
                            <input type="text" id="declarant_designation" name="declarant_designation" required>
                        </div>
                    </div>

                    <div class="iicm-field">
                        <label for="company_profile_file">Company Profile <span class="iicm-required">*</span></label>
                        <div class="iicm-file-upload-wrap">
                            <input type="file" id="company_profile_file" name="company_profile_file"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            <p class="iicm-field-hint">Accepted: PDF, DOC, DOCX, JPG, PNG — max 5MB</p>
                        </div>
                    </div>

                    <div id="iicm-form-error" class="iicm-notice iicm-notice-error" style="display:none;"></div>

                    <div class="iicm-form-nav">
                        <button type="button" class="iicm-btn iicm-btn-secondary iicm-btn-back">&larr; Back</button>
                        <button type="submit" class="iicm-btn iicm-btn-submit" id="iicm-submit-btn">Submit Application</button>
                    </div>
                </div>
            </form>

            <div id="iicm-success-message" style="display:none;" class="iicm-success-wrap">
                <div class="iicm-success-icon">&#10003;</div>
                <h2>Application Submitted</h2>
                <p id="iicm-success-text"></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_fees() {
        ob_start();
        ?>
        <div class="iicm-fees-wrap">
            <h3>Ordinary Members</h3>
            <table class="iicm-fees-table">
                <thead>
                    <tr><th>Tier</th><th>Joining Fee</th><th>Annual Fee</th><th>Total</th></tr>
                </thead>
                <tbody>
                    <tr><td>Tier 1 (AUM &ge; RM100 billion)</td><td>RM5,000</td><td>RM30,000</td><td>RM35,000</td></tr>
                    <tr><td>Tier 2 (AUM &lt; RM100 billion)</td><td>RM3,000</td><td>RM15,000</td><td>RM18,000</td></tr>
                    <tr><td>Tier 3 (Industry groups)</td><td>RM3,000</td><td>RM5,000</td><td>RM8,000</td></tr>
                </tbody>
            </table>

            <h3>Associate Members</h3>
            <table class="iicm-fees-table">
                <thead>
                    <tr><th>Category</th><th>Joining Fee</th><th>Annual Fee</th><th>Total</th></tr>
                </thead>
                <tbody>
                    <tr><td>Local Entity</td><td>RM3,000</td><td>RM5,000</td><td>RM8,000</td></tr>
                    <tr><td>Foreign Entity</td><td>RM3,000</td><td>RM10,000</td><td>RM13,000</td></tr>
                </tbody>
            </table>

            <p class="iicm-fees-note">All payments made payable to IICM Berhad. Payment details provided upon Board approval.</p>
        </div>
        <?php
        return ob_get_clean();
    }
}
```

### Task 8: Form CSS

- [ ] **Step 1: Create `public/assets/form.css`**

```css
/* IICM Membership Form — Brand: #0078D4 / #E87722 */

.iicm-form-wrap {
    max-width: 760px;
    margin: 40px auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 15px;
    color: #1a1a2e;
}

/* Step indicator */
.iicm-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 36px;
}
.iicm-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    opacity: 0.45;
    transition: opacity 0.2s;
}
.iicm-step.active, .iicm-step.done { opacity: 1; }
.iicm-step-num {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #e0edf8;
    color: #0078D4;
    font-weight: 700;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #c2d9f0;
    transition: background 0.2s, color 0.2s;
}
.iicm-step.active .iicm-step-num,
.iicm-step.done .iicm-step-num {
    background: #0078D4;
    color: #fff;
    border-color: #0078D4;
}
.iicm-step-label {
    font-size: 12px;
    font-weight: 600;
    color: #444;
    white-space: nowrap;
}
.iicm-step-divider {
    flex: 1;
    height: 2px;
    background: #d0e4f5;
    margin: 0 8px 20px;
    min-width: 30px;
}

/* Form card */
.iicm-form-step {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 20px rgba(0,120,212,0.08);
    padding: 36px 40px;
}
.iicm-section-title {
    font-size: 20px;
    font-weight: 700;
    color: #0078D4;
    margin: 0 0 28px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e8f3fc;
}

/* Fields */
.iicm-field { margin-bottom: 20px; }
.iicm-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #333;
    font-size: 14px;
}
.iicm-required { color: #E87722; }
.iicm-field input[type="text"],
.iicm-field input[type="email"],
.iicm-field input[type="url"],
.iicm-field textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #d0d9e4;
    border-radius: 6px;
    font-size: 15px;
    color: #1a1a2e;
    background: #fafcff;
    box-sizing: border-box;
    transition: border-color 0.15s, box-shadow 0.15s;
    font-family: inherit;
}
.iicm-field input:focus,
.iicm-field textarea:focus {
    outline: none;
    border-color: #0078D4;
    box-shadow: 0 0 0 3px rgba(0,120,212,0.12);
    background: #fff;
}
.iicm-field input.iicm-error,
.iicm-field textarea.iicm-error { border-color: #d93025; }
.iicm-field-hint { color: #777; font-size: 12px; margin-top: 4px; }

/* Grid */
.iicm-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.iicm-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
@media (max-width: 600px) {
    .iicm-row-2, .iicm-row-3 { grid-template-columns: 1fr; }
    .iicm-form-step { padding: 24px 18px; }
}

/* Checkboxes / Radios */
.iicm-checkbox-group,
.iicm-radio-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 14px 16px;
    background: #fafcff;
    border: 1.5px solid #d0d9e4;
    border-radius: 6px;
}
.iicm-checkbox-label,
.iicm-radio-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: normal !important;
    cursor: pointer;
}
.iicm-checkbox-label input,
.iicm-radio-label input {
    width: 16px;
    height: 16px;
    accent-color: #0078D4;
    cursor: pointer;
    flex-shrink: 0;
}

/* Declaration */
.iicm-declaration-box {
    background: #f0f7ff;
    border-left: 4px solid #0078D4;
    padding: 16px 20px;
    border-radius: 0 6px 6px 0;
    margin-bottom: 20px;
    font-size: 14px;
    color: #333;
    line-height: 1.6;
}
.iicm-declaration-box p { margin: 0; }

/* Nav buttons */
.iicm-form-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid #eef2f8;
}
.iicm-btn {
    padding: 11px 28px;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    font-family: inherit;
}
.iicm-btn:active { transform: scale(0.98); }
.iicm-btn-next,
.iicm-btn-submit { background: #0078D4; color: #fff; margin-left: auto; }
.iicm-btn-next:hover,
.iicm-btn-submit:hover { background: #005fa3; }
.iicm-btn-secondary { background: #f0f4f8; color: #555; }
.iicm-btn-secondary:hover { background: #e0e8f0; }
.iicm-btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* Notices */
.iicm-notice { padding: 12px 16px; border-radius: 6px; font-size: 14px; margin-bottom: 16px; }
.iicm-notice-error { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }

/* Success */
.iicm-success-wrap {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 20px rgba(0,120,212,0.08);
    padding: 60px 40px;
    text-align: center;
}
.iicm-success-icon {
    width: 64px; height: 64px;
    background: #0078D4; color: #fff;
    font-size: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 24px;
}
.iicm-success-wrap h2 { color: #0078D4; font-size: 24px; margin-bottom: 12px; }
.iicm-success-wrap p { color: #555; font-size: 16px; max-width: 480px; margin: 0 auto; line-height: 1.6; }

/* Fee table */
.iicm-fees-wrap { max-width: 700px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.iicm-fees-wrap h3 { color: #0078D4; margin-bottom: 12px; font-size: 17px; }
.iicm-fees-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; font-size: 14px; }
.iicm-fees-table th { background: #0078D4; color: #fff; padding: 10px 14px; text-align: left; font-weight: 600; }
.iicm-fees-table td { padding: 10px 14px; border-bottom: 1px solid #e8edf3; }
.iicm-fees-table tr:nth-child(even) td { background: #f5f9ff; }
.iicm-fees-note { font-size: 13px; color: #666; font-style: italic; border-top: 1px solid #e8edf3; padding-top: 12px; }
```

### Task 9: Form JS

- [ ] **Step 1: Create `public/assets/form.js`**

```javascript
(function () {
    'use strict';

    var currentStep = 1;
    var totalSteps  = 3;

    function getStep(n) {
        return document.querySelector('.iicm-form-step[data-step="' + n + '"]');
    }
    function getStepIndicator(n) {
        return document.querySelector('.iicm-step[data-step="' + n + '"]');
    }

    function showStep(n) {
        for (var i = 1; i <= totalSteps; i++) {
            var step = getStep(i);
            if (step) step.style.display = (i === n) ? '' : 'none';
        }
        updateStepIndicators(n);
        currentStep = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function updateStepIndicators(active) {
        for (var i = 1; i <= totalSteps; i++) {
            var ind = getStepIndicator(i);
            if (!ind) continue;
            ind.classList.remove('active', 'done');
            var numEl = ind.querySelector('.iicm-step-num');
            if (i === active) {
                ind.classList.add('active');
                if (numEl) numEl.textContent = String(i);
            } else if (i < active) {
                ind.classList.add('done');
                if (numEl) numEl.textContent = '\u2713';
            } else {
                if (numEl) numEl.textContent = String(i);
            }
        }
    }

    function validateStep(n) {
        var step = getStep(n);
        if (!step) return true;
        var valid = true;
        clearErrors(step);

        var required = step.querySelectorAll('[required]');
        for (var i = 0; i < required.length; i++) {
            var el = required[i];
            if (el.type === 'checkbox' && !el.checked) {
                showFieldError(el, 'This field is required.');
                valid = false;
            } else if (el.type === 'file') {
                if (!el.files || el.files.length === 0) {
                    showFieldError(el, 'Please upload your company profile.');
                    valid = false;
                } else {
                    var file    = el.files[0];
                    var ext     = file.name.split('.').pop().toLowerCase();
                    var allowed = ['pdf','doc','docx','jpg','jpeg','png'];
                    if (allowed.indexOf(ext) === -1) {
                        showFieldError(el, 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG.');
                        valid = false;
                    } else if (file.size > 5 * 1024 * 1024) {
                        showFieldError(el, 'File size must not exceed 5MB.');
                        valid = false;
                    }
                }
            } else if (el.type !== 'radio' && el.value.trim() === '') {
                showFieldError(el, 'This field is required.');
                valid = false;
            }
        }

        if (n === 1) {
            // AUM radio
            var aumRadios = step.querySelectorAll('input[name="aum"]');
            var aumChecked = false;
            for (var r = 0; r < aumRadios.length; r++) {
                if (aumRadios[r].checked) { aumChecked = true; break; }
            }
            if (!aumChecked) {
                var radioGroup = step.querySelector('.iicm-radio-group');
                if (radioGroup) {
                    var span = document.createElement('span');
                    span.className = 'iicm-radio-error';
                    span.style.cssText = 'color:#d93025;font-size:12px;display:block;margin-top:4px;';
                    span.textContent = 'Please select an AUM option.';
                    radioGroup.parentNode.appendChild(span);
                }
                valid = false;
            }

            // At least one category
            var catChecked = step.querySelectorAll('input[name="org_categories[]"]:checked');
            if (catChecked.length === 0) {
                var cbGroup = step.querySelector('.iicm-checkbox-group');
                if (cbGroup) {
                    var span2 = document.createElement('span');
                    span2.className = 'iicm-cat-error';
                    span2.style.cssText = 'color:#d93025;font-size:12px;display:block;margin-top:4px;';
                    span2.textContent = 'Please select at least one category.';
                    cbGroup.parentNode.appendChild(span2);
                }
                valid = false;
            }

            // Email format
            var emailEl = step.querySelector('input[name="email"]');
            if (emailEl && emailEl.value.trim() !== '' && !isValidEmail(emailEl.value)) {
                showFieldError(emailEl, 'Please enter a valid email address.');
                valid = false;
            }
        }

        if (n === 2) {
            var repEmail = step.querySelector('input[name="rep_email"]');
            if (repEmail && repEmail.value.trim() !== '' && !isValidEmail(repEmail.value)) {
                showFieldError(repEmail, 'Please enter a valid email address.');
                valid = false;
            }
        }

        return valid;
    }

    function isValidEmail(val) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
    }

    function showFieldError(el, msg) {
        el.classList.add('iicm-error');
        var span = document.createElement('span');
        span.className = 'iicm-field-error-msg';
        span.style.cssText = 'color:#d93025;font-size:12px;display:block;margin-top:4px;';
        span.textContent = msg;
        if (el.parentNode) el.parentNode.appendChild(span);
    }

    function clearErrors(container) {
        var errEls = container.querySelectorAll('.iicm-field-error-msg, .iicm-radio-error, .iicm-cat-error');
        for (var i = 0; i < errEls.length; i++) errEls[i].parentNode.removeChild(errEls[i]);
        var errInputs = container.querySelectorAll('.iicm-error');
        for (var j = 0; j < errInputs.length; j++) errInputs[j].classList.remove('iicm-error');
    }

    function initSameAddress() {
        var cb        = document.getElementById('same_as_registered');
        var corrFields= document.getElementById('correspondence-fields');
        if (!cb || !corrFields) return;
        cb.addEventListener('change', function () {
            if (cb.checked) {
                corrFields.style.display = 'none';
                var map = {
                    'correspondence_address':    'registered_address',
                    'correspondence_postcode':   'registered_postcode',
                    'correspondence_city_state': 'registered_city_state',
                    'correspondence_country':    'registered_country',
                };
                Object.keys(map).forEach(function (target) {
                    var src  = document.getElementById(map[target]);
                    var dest = document.getElementById(target);
                    if (src && dest) dest.value = src.value;
                });
            } else {
                corrFields.style.display = '';
            }
        });
    }

    function initOthersToggle() {
        var otherCb   = document.querySelector('input[name="org_categories[]"][value="others"]');
        var otherWrap = document.getElementById('org-category-other-wrap');
        if (!otherCb || !otherWrap) return;
        otherCb.addEventListener('change', function () {
            otherWrap.style.display = otherCb.checked ? '' : 'none';
        });
    }

    function submitForm(form) {
        var submitBtn = document.getElementById('iicm-submit-btn');
        var errorDiv  = document.getElementById('iicm-form-error');
        submitBtn.disabled    = true;
        submitBtn.textContent = 'Submitting\u2026';
        if (errorDiv) errorDiv.style.display = 'none';

        var formData = new FormData(form);
        formData.append('action', 'iicm_submit_application');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', iicmForm.ajaxurl, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            submitBtn.disabled    = false;
            submitBtn.textContent = 'Submit Application';
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        var wrap = form.closest('.iicm-form-wrap');
                        wrap.querySelector('#iicm-application-form').style.display = 'none';
                        wrap.querySelector('.iicm-steps').style.display = 'none';
                        var successWrap = document.getElementById('iicm-success-message');
                        var successText = document.getElementById('iicm-success-text');
                        if (successText) successText.textContent = res.data.message;
                        if (successWrap) successWrap.style.display = '';
                    } else {
                        if (errorDiv) {
                            errorDiv.textContent = res.data.message || 'Submission failed. Please try again.';
                            errorDiv.style.display = '';
                        }
                    }
                } catch (e) {
                    if (errorDiv) {
                        errorDiv.textContent = 'An unexpected error occurred. Please try again.';
                        errorDiv.style.display = '';
                    }
                }
            } else {
                if (errorDiv) {
                    errorDiv.textContent = 'Server error. Please try again.';
                    errorDiv.style.display = '';
                }
            }
        };
        xhr.send(formData);
    }

    function init() {
        var form = document.getElementById('iicm-application-form');
        if (!form) return;

        initSameAddress();
        initOthersToggle();

        var nextBtns = form.querySelectorAll('.iicm-btn-next');
        for (var i = 0; i < nextBtns.length; i++) {
            nextBtns[i].addEventListener('click', function () {
                if (validateStep(currentStep)) showStep(currentStep + 1);
            });
        }

        var backBtns = form.querySelectorAll('.iicm-btn-back');
        for (var j = 0; j < backBtns.length; j++) {
            backBtns[j].addEventListener('click', function () {
                showStep(currentStep - 1);
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (validateStep(3)) submitForm(form);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

- [ ] **Step 2: Commit**

```bash
git add public/
git commit -m "feat: public form shortcode, CSS wizard, JS step navigation"
```

---

## Chunk 4: Form Handler + Email Notifications

**Files:**
- Create: `iicm-membership/includes/class-form-handler.php`
- Create: `iicm-membership/includes/class-email.php`

### Task 10: Form handler — AJAX, validation, file upload, DB save

- [ ] **Step 1: Create `includes/class-form-handler.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Form_Handler {

    public function __construct() {
        add_action( 'wp_ajax_iicm_submit_application',        array( $this, 'handle_submission' ) );
        add_action( 'wp_ajax_nopriv_iicm_submit_application', array( $this, 'handle_submission' ) );
    }

    public function handle_submission() {
        if ( ! check_ajax_referer( 'iicm_submit_application', 'iicm_nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security verification failed. Please refresh and try again.' ) );
        }

        // Sanitize
        $company_name              = sanitize_text_field( wp_unslash( $_POST['company_name']              ?? '' ) );
        $company_reg_number        = sanitize_text_field( wp_unslash( $_POST['company_reg_number']        ?? '' ) );
        $registered_address        = sanitize_textarea_field( wp_unslash( $_POST['registered_address']    ?? '' ) );
        $registered_postcode       = sanitize_text_field( wp_unslash( $_POST['registered_postcode']       ?? '' ) );
        $registered_city_state     = sanitize_text_field( wp_unslash( $_POST['registered_city_state']     ?? '' ) );
        $registered_country        = sanitize_text_field( wp_unslash( $_POST['registered_country']        ?? '' ) );
        $correspondence_address    = sanitize_textarea_field( wp_unslash( $_POST['correspondence_address']    ?? '' ) );
        $correspondence_postcode   = sanitize_text_field( wp_unslash( $_POST['correspondence_postcode']       ?? '' ) );
        $correspondence_city_state = sanitize_text_field( wp_unslash( $_POST['correspondence_city_state']     ?? '' ) );
        $correspondence_country    = sanitize_text_field( wp_unslash( $_POST['correspondence_country']        ?? '' ) );
        $telephone                 = sanitize_text_field( wp_unslash( $_POST['telephone']                 ?? '' ) );
        $email                     = sanitize_email( wp_unslash( $_POST['email']                         ?? '' ) );
        $website                   = esc_url_raw( wp_unslash( $_POST['website']                          ?? '' ) );

        $raw_cats       = ( isset( $_POST['org_categories'] ) && is_array( $_POST['org_categories'] ) )
                          ? $_POST['org_categories'] : array();
        $org_categories = array_map( 'sanitize_text_field', $raw_cats );
        $org_category_other = sanitize_text_field( wp_unslash( $_POST['org_category_other'] ?? '' ) );
        $aum                = sanitize_text_field( wp_unslash( $_POST['aum']                ?? '' ) );

        $rep_name          = sanitize_text_field( wp_unslash( $_POST['rep_name']          ?? '' ) );
        $rep_nric_passport = sanitize_text_field( wp_unslash( $_POST['rep_nric_passport'] ?? '' ) );
        $rep_nationality   = sanitize_text_field( wp_unslash( $_POST['rep_nationality']   ?? '' ) );
        $rep_designation   = sanitize_text_field( wp_unslash( $_POST['rep_designation']   ?? '' ) );
        $rep_email         = sanitize_email( wp_unslash( $_POST['rep_email']              ?? '' ) );
        $rep_address       = sanitize_textarea_field( wp_unslash( $_POST['rep_address']   ?? '' ) );
        $rep_postcode      = sanitize_text_field( wp_unslash( $_POST['rep_postcode']      ?? '' ) );
        $rep_city_state    = sanitize_text_field( wp_unslash( $_POST['rep_city_state']    ?? '' ) );
        $rep_country       = sanitize_text_field( wp_unslash( $_POST['rep_country']       ?? '' ) );
        $rep_office_tel    = sanitize_text_field( wp_unslash( $_POST['rep_office_tel']    ?? '' ) );
        $rep_handphone     = sanitize_text_field( wp_unslash( $_POST['rep_handphone']     ?? '' ) );

        $declarant_name        = sanitize_text_field( wp_unslash( $_POST['declarant_name']        ?? '' ) );
        $declarant_designation = sanitize_text_field( wp_unslash( $_POST['declarant_designation'] ?? '' ) );
        $declaration_agreed    = isset( $_POST['declaration_agreed'] ) ? 1 : 0;

        // Server-side validation
        $errors = array();
        if ( empty( $company_name ) )       $errors[] = 'Company name is required.';
        if ( empty( $company_reg_number ) ) $errors[] = 'Company registration number is required.';
        if ( empty( $registered_address ) ) $errors[] = 'Registered address is required.';
        if ( empty( $email ) || ! is_email( $email ) ) $errors[] = 'A valid email address is required.';
        if ( empty( $org_categories ) )     $errors[] = 'Please select at least one organisation category.';
        if ( ! in_array( $aum, array( 'below_100b', 'rm100b_above', 'none' ), true ) ) {
            $errors[] = 'Please select an AUM option.';
        }
        if ( empty( $rep_name ) )             $errors[] = 'Representative name is required.';
        if ( empty( $rep_designation ) )      $errors[] = 'Representative designation is required.';
        if ( empty( $rep_email ) || ! is_email( $rep_email ) ) $errors[] = 'A valid representative email is required.';
        if ( ! $declaration_agreed )          $errors[] = 'You must agree to the declaration.';
        if ( empty( $declarant_name ) )       $errors[] = 'Authorised signatory name is required.';
        if ( empty( $declarant_designation ) ) $errors[] = 'Authorised signatory designation is required.';

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
        }

        // Duplicate check
        $db = new IICM_Database();
        if ( $db->has_active_application( $company_reg_number ) ) {
            wp_send_json_error( array(
                'message' => 'An application for this company registration number already exists. Please contact admin@iicm.org.my for assistance.'
            ) );
        }

        // File upload
        if ( empty( $_FILES['company_profile_file']['name'] ) ) {
            wp_send_json_error( array( 'message' => 'Company profile file is required.' ) );
        }

        $allowed_mime_types = array(
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        );

        $file     = $_FILES['company_profile_file'];
        $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( ! isset( $allowed_mime_types[ $file_ext ] ) ) {
            wp_send_json_error( array( 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG.' ) );
        }
        if ( $file['size'] > 5 * 1024 * 1024 ) {
            wp_send_json_error( array( 'message' => 'File size must not exceed 5MB.' ) );
        }

        $finfo = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        if ( ! $finfo['type'] || ! in_array( $finfo['type'], $allowed_mime_types, true ) ) {
            wp_send_json_error( array( 'message' => 'File type verification failed.' ) );
        }

        $upload_dir    = wp_upload_dir();
        $dest_dir      = $upload_dir['basedir'] . '/iicm-membership/' . date( 'Y/m' );
        wp_mkdir_p( $dest_dir );
        $safe_filename = wp_unique_filename( $dest_dir, sanitize_file_name( $file['name'] ) );
        $dest_path     = $dest_dir . '/' . $safe_filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
            wp_send_json_error( array( 'message' => 'File upload failed. Please try again.' ) );
        }

        $company_profile_file = 'iicm-membership/' . date( 'Y/m' ) . '/' . $safe_filename;

        // Insert
        $application_id = $db->insert( array(
            'company_name'              => $company_name,
            'company_reg_number'        => $company_reg_number,
            'registered_address'        => $registered_address,
            'registered_postcode'       => $registered_postcode,
            'registered_city_state'     => $registered_city_state,
            'registered_country'        => $registered_country,
            'correspondence_address'    => $correspondence_address,
            'correspondence_postcode'   => $correspondence_postcode,
            'correspondence_city_state' => $correspondence_city_state,
            'correspondence_country'    => $correspondence_country,
            'telephone'                 => $telephone,
            'email'                     => $email,
            'website'                   => $website,
            'org_categories'            => wp_json_encode( $org_categories ),
            'org_category_other'        => $org_category_other,
            'aum'                       => $aum,
            'rep_name'                  => $rep_name,
            'rep_nric_passport'         => $rep_nric_passport,
            'rep_nationality'           => $rep_nationality,
            'rep_designation'           => $rep_designation,
            'rep_email'                 => $rep_email,
            'rep_address'               => $rep_address,
            'rep_postcode'              => $rep_postcode,
            'rep_city_state'            => $rep_city_state,
            'rep_country'               => $rep_country,
            'rep_office_tel'            => $rep_office_tel,
            'rep_handphone'             => $rep_handphone,
            'declarant_name'            => $declarant_name,
            'declarant_designation'     => $declarant_designation,
            'declaration_agreed'        => $declaration_agreed,
            'declaration_agreed_at'     => $declaration_agreed ? current_time( 'mysql' ) : null,
            'company_profile_file'      => $company_profile_file,
            'status'                    => 'pending',
            'submitted_at'              => current_time( 'mysql' ),
        ) );

        if ( ! $application_id ) {
            wp_send_json_error( array( 'message' => 'Failed to save your application. Please try again.' ) );
        }

        $application = $db->get_by_id( $application_id );
        $mailer      = new IICM_Email();
        $mailer->send_admin_email( $application );
        $mailer->send_applicant_email( $application );

        wp_send_json_success( array(
            'message' => sprintf(
                'Thank you, %s. Your membership application has been submitted successfully. We will be in touch shortly.',
                sanitize_text_field( $company_name )
            ),
        ) );
    }
}
```

### Task 11: Email class

- [ ] **Step 1: Create `includes/class-email.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Email {

    private $sender_name;
    private $sender_from;

    public function __construct() {
        $this->sender_name = get_option( 'iicm_email_sender_name', 'IICM Berhad' );
        $this->sender_from = get_option( 'iicm_email_sender_from', 'admin@iicm.org.my' );
    }

    private function set_html_mail_filters() {
        $sender_name = $this->sender_name;
        $sender_from = $this->sender_from;
        add_filter( 'wp_mail_content_type', function () { return 'text/html'; } );
        add_filter( 'wp_mail_from',         function () use ( $sender_from ) { return $sender_from; } );
        add_filter( 'wp_mail_from_name',    function () use ( $sender_name ) { return $sender_name; } );
    }

    private function remove_html_mail_filters() {
        remove_all_filters( 'wp_mail_content_type' );
        remove_all_filters( 'wp_mail_from' );
        remove_all_filters( 'wp_mail_from_name' );
    }

    private function decode_categories( string $json_string ): string {
        $cats = json_decode( $json_string, true );
        if ( ! is_array( $cats ) ) return '';
        $labels = array(
            'public_retirement'    => 'Public Retirement/Pension Plan',
            'private_retirement'   => 'Private Retirement/Pension Plan',
            'corporate_retirement' => 'Corporate Retirement/Pension Plan',
            'insurance'            => 'Insurance',
            'fund_management'      => 'Fund Management/Asset Management',
            'unit_trust'           => 'Unit Trust/CIV',
            'sovereign_wealth'     => 'Sovereign Wealth Fund',
            'public_listed'        => 'Public Listed Company',
            'corporate_private'    => 'Corporate/Private Company',
            'association'          => 'Association',
            'foreign_entities'     => 'Foreign Entities',
            'educational'          => 'Educational Institutions',
            'others'               => 'Others',
        );
        $out = array();
        foreach ( $cats as $c ) {
            $out[] = isset( $labels[ $c ] ) ? $labels[ $c ] : esc_html( $c );
        }
        return implode( ', ', $out );
    }

    private function aum_label( string $aum ): string {
        $map = array(
            'below_100b'   => 'Below RM100 billion',
            'rm100b_above' => 'RM100 billion and above',
            'none'         => 'None',
        );
        return $map[ $aum ] ?? $aum;
    }

    private function email_wrapper( string $content ): string {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f4f7fb;margin:0;padding:20px;}
.wrap{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);}
.hdr{background:#0078D4;padding:28px 32px;color:#fff;}
.hdr h1{margin:0;font-size:20px;font-weight:700;}
.body{padding:28px 32px;color:#333;}
.body p{line-height:1.7;margin:0 0 14px;}
table.info{width:100%;border-collapse:collapse;margin:16px 0;}
table.info th{background:#f0f7ff;color:#0078D4;text-align:left;padding:8px 12px;font-size:13px;border:1px solid #dde8f5;}
table.info td{padding:8px 12px;font-size:14px;border:1px solid #dde8f5;color:#333;}
.footer{background:#f9fbff;padding:16px 32px;font-size:12px;color:#888;border-top:1px solid #e8edf5;}
.btn{display:inline-block;background:#0078D4;color:#fff;padding:10px 24px;border-radius:5px;text-decoration:none;font-weight:600;font-size:14px;margin-top:8px;}
</style></head><body><div class="wrap">' . $content . '</div></body></html>';
    }

    public function send_admin_email( $application ) {
        $raw    = get_option( 'iicm_admin_emails', 'admin@iicm.org.my' );
        $emails = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
        if ( empty( $emails ) ) return;

        $subject    = 'New Membership Application — ' . esc_html( $application->company_name );
        $detail_url = admin_url( 'admin.php?page=iicm-membership&view=detail&id=' . intval( $application->id ) );

        $body = $this->email_wrapper(
            '<div class="hdr"><h1>New Membership Application</h1></div>
<div class="body">
<p>A new membership application has been submitted.</p>
<table class="info">
<tr><th>Company Name</th><td>' . esc_html( $application->company_name ) . '</td></tr>
<tr><th>Registration No</th><td>' . esc_html( $application->company_reg_number ) . '</td></tr>
<tr><th>Category</th><td>' . esc_html( $this->decode_categories( $application->org_categories ) ) . '</td></tr>
<tr><th>AUM</th><td>' . esc_html( $this->aum_label( $application->aum ) ) . '</td></tr>
<tr><th>Representative</th><td>' . esc_html( $application->rep_name ) . '</td></tr>
<tr><th>Rep Email</th><td>' . esc_html( $application->rep_email ) . '</td></tr>
<tr><th>Submitted</th><td>' . esc_html( $application->submitted_at ) . '</td></tr>
</table>
<a href="' . esc_url( $detail_url ) . '" class="btn">View Application in Dashboard</a>
</div>
<div class="footer">IICM Berhad Membership System</div>'
        );

        $this->set_html_mail_filters();
        foreach ( $emails as $to ) {
            wp_mail( sanitize_email( $to ), $subject, $body );
        }
        $this->remove_html_mail_filters();
    }

    public function send_applicant_email( $application ) {
        if ( empty( $application->email ) ) return;

        $subject = 'IICM Membership Application — Confirmation';
        $body = $this->email_wrapper(
            '<div class="hdr"><h1>Application Received</h1></div>
<div class="body">
<p>Dear ' . esc_html( $application->company_name ) . ',</p>
<p>Thank you for submitting your membership application to IICM Berhad. We have received your application and our team will review it shortly.</p>
<table class="info">
<tr><th>Company Name</th><td>' . esc_html( $application->company_name ) . '</td></tr>
<tr><th>Registration No</th><td>' . esc_html( $application->company_reg_number ) . '</td></tr>
<tr><th>Category</th><td>' . esc_html( $this->decode_categories( $application->org_categories ) ) . '</td></tr>
<tr><th>AUM</th><td>' . esc_html( $this->aum_label( $application->aum ) ) . '</td></tr>
<tr><th>Representative</th><td>' . esc_html( $application->rep_name ) . '</td></tr>
<tr><th>Submitted</th><td>' . esc_html( $application->submitted_at ) . '</td></tr>
</table>
<p>Our team will review your application and be in touch with you regarding the next steps.</p>
<p>If you have any questions, please contact us at <a href="mailto:admin@iicm.org.my">admin@iicm.org.my</a>.</p>
</div>
<div class="footer">IICM Berhad &mdash; The Investment Industry Council of Malaysia</div>'
        );

        $this->set_html_mail_filters();
        wp_mail( $application->email, $subject, $body );
        $this->remove_html_mail_filters();
    }
}
```

- [ ] **Step 2: Test form end-to-end:**
  1. Place `[iicm_membership_form]` on a WP page
  2. Fill all three steps, upload a PDF, submit
  3. Verify record in `wp_iicm_applications` via phpMyAdmin
  4. Verify admin email + applicant email received (MailHog on Laragon: `localhost:8025`)

- [ ] **Step 3: Commit**

```bash
git add includes/class-form-handler.php includes/class-email.php
git commit -m "feat: AJAX form handler, server validation, file upload, email notifications"
```

---

## Chunk 5: Admin Dashboard

**Files:**
- Create: `iicm-membership/admin/class-admin.php`
- Create: `iicm-membership/admin/class-admin-dashboard.php`
- Create: `iicm-membership/admin/assets/admin.css`

### Task 12: Admin menu + Settings page

- [ ] **Step 1: Create `admin/class-admin.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function register_menu() {
        add_menu_page(
            'IICM Membership',
            'IICM Membership',
            'manage_options',
            'iicm-membership',
            array( 'IICM_Admin_Dashboard', 'render' ),
            'dashicons-groups',
            30
        );
        add_submenu_page(
            'iicm-membership',
            'Applications',
            'Applications',
            'manage_options',
            'iicm-membership',
            array( 'IICM_Admin_Dashboard', 'render' )
        );
        add_submenu_page(
            'iicm-membership',
            'Settings',
            'Settings',
            'manage_options',
            'iicm-membership-settings',
            array( $this, 'render_settings' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'iicm-membership' ) === false ) return;

        wp_enqueue_style(
            'iicm-admin',
            IICM_MEMBERSHIP_PLUGIN_URL . 'admin/assets/admin.css',
            array(),
            IICM_MEMBERSHIP_VERSION
        );
        wp_enqueue_script(
            'iicm-admin',
            IICM_MEMBERSHIP_PLUGIN_URL . 'admin/assets/admin.js',
            array(),
            IICM_MEMBERSHIP_VERSION,
            true
        );
        wp_localize_script( 'iicm-admin', 'iicmAdmin', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'iicm_update_application' ),
        ) );
    }

    public function render_settings() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        if ( isset( $_POST['iicm_settings_nonce'] ) &&
             wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['iicm_settings_nonce'] ) ), 'iicm_save_settings' ) ) {
            update_option( 'iicm_admin_emails',      sanitize_text_field( wp_unslash( $_POST['iicm_admin_emails']      ?? '' ) ) );
            update_option( 'iicm_email_sender_name', sanitize_text_field( wp_unslash( $_POST['iicm_email_sender_name'] ?? '' ) ) );
            update_option( 'iicm_email_sender_from', sanitize_email( wp_unslash( $_POST['iicm_email_sender_from']      ?? '' ) ) );
            echo '<div class="iicm-admin-notice iicm-notice-success" style="display:block;">Settings saved.</div>';
        }
        ?>
        <div class="iicm-admin-wrap">
            <h1 class="iicm-admin-page-title">Settings</h1>
            <div class="iicm-admin-card">
                <form method="post">
                    <?php wp_nonce_field( 'iicm_save_settings', 'iicm_settings_nonce' ); ?>
                    <div class="iicm-settings-field">
                        <label>Admin Email(s)</label>
                        <input type="text" name="iicm_admin_emails"
                               value="<?php echo esc_attr( get_option( 'iicm_admin_emails', 'admin@iicm.org.my' ) ); ?>">
                        <p class="iicm-settings-hint">Separate multiple emails with commas.</p>
                    </div>
                    <div class="iicm-settings-field">
                        <label>Email Sender Name</label>
                        <input type="text" name="iicm_email_sender_name"
                               value="<?php echo esc_attr( get_option( 'iicm_email_sender_name', 'IICM Berhad' ) ); ?>">
                    </div>
                    <div class="iicm-settings-field">
                        <label>Email From Address</label>
                        <input type="email" name="iicm_email_sender_from"
                               value="<?php echo esc_attr( get_option( 'iicm_email_sender_from', 'admin@iicm.org.my' ) ); ?>">
                    </div>
                    <button type="submit" class="iicm-admin-btn">Save Settings</button>
                </form>
            </div>
        </div>
        <?php
    }
}
```

### Task 13: Admin dashboard list page

- [ ] **Step 1: Create `admin/class-admin-dashboard.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Admin_Dashboard {

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Route to detail view
        if ( isset( $_GET['view'] ) && $_GET['view'] === 'detail' && ! empty( $_GET['id'] ) ) {
            IICM_Admin_Detail::render( intval( $_GET['id'] ) );
            return;
        }

        // Handle export
        if ( isset( $_GET['iicm_export'] ) ) {
            self::handle_export( sanitize_text_field( $_GET['iicm_export'] ) );
            return;
        }

        $db             = new IICM_Database();
        $counts         = $db->count_by_status();
        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $search         = isset( $_GET['s'] )       ? sanitize_text_field( $_GET['s'] )       : '';
        $page_num       = isset( $_GET['paged'] )   ? max( 1, intval( $_GET['paged'] ) )       : 1;
        $per_page       = 20;
        $result         = $db->get_list( $current_status, $search, $per_page, $page_num );
        $items          = $result['items'];
        $total          = $result['total'];
        $total_pages    = (int) ceil( $total / $per_page );
        $base_url       = admin_url( 'admin.php?page=iicm-membership' );

        $cat_labels = array(
            'public_retirement'    => 'Public Retirement',
            'private_retirement'   => 'Private Retirement',
            'corporate_retirement' => 'Corporate Retirement',
            'insurance'            => 'Insurance',
            'fund_management'      => 'Fund Management',
            'unit_trust'           => 'Unit Trust',
            'sovereign_wealth'     => 'Sovereign Wealth',
            'public_listed'        => 'Public Listed',
            'corporate_private'    => 'Corporate/Private',
            'association'          => 'Association',
            'foreign_entities'     => 'Foreign Entities',
            'educational'          => 'Educational',
            'others'               => 'Others',
        );
        ?>
        <div class="iicm-admin-wrap">
            <div class="iicm-admin-topbar">
                <h1 class="iicm-admin-page-title">Applications</h1>
                <div class="iicm-export-btns">
                    <?php $ep = $current_status ? '&filter_status=' . urlencode( $current_status ) : ''; ?>
                    <a href="<?php echo esc_url( $base_url . '&iicm_export=csv' . $ep ); ?>" class="iicm-admin-btn">&#8681; Export CSV</a>
                    <a href="<?php echo esc_url( $base_url . '&iicm_export=excel' . $ep ); ?>" class="iicm-admin-btn iicm-btn-orange">&#8681; Export Excel</a>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="iicm-stat-cards">
                <?php
                $cards = array(
                    array( 'key' => '',           'label' => 'Total',      'color' => 'blue',   'count' => $counts['total'] ),
                    array( 'key' => 'processing', 'label' => 'Processing', 'color' => 'orange', 'count' => $counts['processing'] ),
                    array( 'key' => 'approved',   'label' => 'Approved',   'color' => 'green',  'count' => $counts['approved'] ),
                    array( 'key' => 'rejected',   'label' => 'Rejected',   'color' => 'red',    'count' => $counts['rejected'] ),
                );
                foreach ( $cards as $card ) :
                    $card_url = $base_url . ( $card['key'] ? '&status=' . $card['key'] : '' );
                    $active   = ( $current_status === $card['key'] ) ? ' iicm-stat-active' : '';
                ?>
                    <a href="<?php echo esc_url( $card_url ); ?>"
                       class="iicm-stat-card iicm-stat-<?php echo esc_attr( $card['color'] . $active ); ?>">
                        <span class="iicm-stat-count"><?php echo intval( $card['count'] ); ?></span>
                        <span class="iicm-stat-label"><?php echo esc_html( $card['label'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Filter Pills + Search -->
            <div class="iicm-filter-bar">
                <div class="iicm-filter-pills">
                    <?php
                    $pills = array(
                        '' => 'All (' . $counts['total'] . ')',
                        'pending'    => 'Pending (' . $counts['pending'] . ')',
                        'processing' => 'Processing (' . $counts['processing'] . ')',
                        'approved'   => 'Approved (' . $counts['approved'] . ')',
                        'rejected'   => 'Rejected (' . $counts['rejected'] . ')',
                    );
                    foreach ( $pills as $ps => $pl ) :
                        $pill_url = $base_url . ( $ps ? '&status=' . $ps : '' ) . ( $search ? '&s=' . urlencode( $search ) : '' );
                        $active   = ( $current_status === $ps ) ? ' iicm-pill-active' : '';
                    ?>
                        <a href="<?php echo esc_url( $pill_url ); ?>"
                           class="iicm-pill<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $pl ); ?></a>
                    <?php endforeach; ?>
                </div>
                <form method="get" class="iicm-search-form">
                    <input type="hidden" name="page" value="iicm-membership">
                    <?php if ( $current_status ) : ?>
                        <input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
                    <?php endif; ?>
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="Search company or reg. no&hellip;" class="iicm-search-input">
                    <button type="submit" class="iicm-admin-btn">Search</button>
                </form>
            </div>

            <!-- Table -->
            <div class="iicm-admin-card iicm-table-card">
                <?php if ( empty( $items ) ) : ?>
                    <p class="iicm-empty-state">No applications found.</p>
                <?php else : ?>
                <table class="iicm-apps-table">
                    <thead>
                        <tr>
                            <th>Company</th><th>Category</th><th>Membership</th>
                            <th>Status</th><th>Submitted</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $items as $item ) :
                        $cats_arr = json_decode( $item->org_categories, true );
                        $cat_str  = '';
                        if ( is_array( $cats_arr ) ) {
                            $out = array();
                            foreach ( $cats_arr as $c ) {
                                $out[] = isset( $cat_labels[ $c ] ) ? $cat_labels[ $c ] : $c;
                            }
                            $cat_str = implode( ', ', $out );
                        }
                        $cat_display = mb_strlen( $cat_str ) > 40 ? mb_substr( $cat_str, 0, 37 ) . '&hellip;' : $cat_str;

                        $membership_display = '&mdash;';
                        if ( ! empty( $item->membership_type ) ) {
                            $tm = array( 'ordinary' => 'Ordinary', 'associate' => 'Associate' );
                            $tt = array( 'tier1' => 'T1', 'tier2' => 'T2', 'tier3' => 'T3', 'local' => 'Local', 'foreign' => 'Foreign' );
                            $tl = isset( $tm[ $item->membership_type ] ) ? $tm[ $item->membership_type ] : $item->membership_type;
                            $tl .= ( ! empty( $item->membership_tier ) && isset( $tt[ $item->membership_tier ] ) )
                                   ? ' &middot; ' . $tt[ $item->membership_tier ] : '';
                            $membership_display = '<span class="iicm-membership-badge">' . esc_html( $tl ) . '</span>';
                        }

                        $detail_url = $base_url . '&view=detail&id=' . intval( $item->id );
                    ?>
                        <tr>
                            <td class="iicm-col-company">
                                <span class="iicm-company-name"><?php echo esc_html( $item->company_name ); ?></span>
                                <span class="iicm-reg-number"><?php echo esc_html( $item->company_reg_number ); ?></span>
                            </td>
                            <td title="<?php echo esc_attr( $cat_str ); ?>"><?php echo wp_kses_post( $cat_display ); ?></td>
                            <td><?php echo wp_kses_post( $membership_display ); ?></td>
                            <td><span class="iicm-status-badge iicm-status-<?php echo esc_attr( $item->status ); ?>"><?php echo esc_html( ucfirst( $item->status ) ); ?></span></td>
                            <td><?php echo esc_html( date_i18n( 'd M Y', strtotime( $item->submitted_at ) ) ); ?></td>
                            <td><a href="<?php echo esc_url( $detail_url ); ?>" class="iicm-view-btn">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if ( $total_pages > 1 ) : ?>
                <div class="iicm-pagination">
                    <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                        $pg_url = $base_url
                                  . ( $current_status ? '&status=' . $current_status : '' )
                                  . ( $search ? '&s=' . urlencode( $search ) : '' )
                                  . '&paged=' . $p;
                        $active = ( $p === $page_num ) ? ' iicm-page-active' : '';
                    ?>
                        <a href="<?php echo esc_url( $pg_url ); ?>"
                           class="iicm-page-btn<?php echo esc_attr( $active ); ?>"><?php echo intval( $p ); ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function handle_export( string $format ) {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        $status   = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
        $db       = new IICM_Database();
        $items    = $db->get_all_for_export( $status );
        $exporter = new IICM_Export();
        if ( $format === 'csv' ) {
            $exporter->export_csv( $items, $status );
        } else {
            $exporter->export_excel( $items, $status );
        }
        exit;
    }
}
```

### Task 14: Admin CSS

- [ ] **Step 1: Create `admin/assets/admin.css`**

```css
/* IICM Admin Dashboard — Custom UI (no WP_List_Table defaults) */

.iicm-admin-wrap {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    max-width: 1200px;
    padding: 24px 20px;
    color: #1a1a2e;
}
.iicm-admin-topbar {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;
}
.iicm-admin-page-title { font-size: 22px; font-weight: 700; color: #0078D4; margin: 0; }
.iicm-export-btns { display: flex; gap: 10px; }

.iicm-admin-btn {
    display: inline-block; background: #0078D4; color: #fff;
    padding: 9px 18px; border-radius: 6px; font-size: 13px; font-weight: 600;
    border: none; cursor: pointer; text-decoration: none;
    transition: background 0.15s; font-family: inherit;
}
.iicm-admin-btn:hover { background: #005fa3; color: #fff; }
.iicm-btn-orange { background: #E87722; }
.iicm-btn-orange:hover { background: #c85f10; color: #fff; }

/* Stat Cards */
.iicm-stat-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.iicm-stat-card {
    background: #fff; border-radius: 8px; padding: 20px 24px;
    display: flex; flex-direction: column; gap: 6px;
    box-shadow: 0 1px 8px rgba(0,0,0,.06); text-decoration: none; color: inherit;
    border-left: 4px solid transparent; transition: box-shadow 0.15s, transform 0.1s;
}
.iicm-stat-card:hover { box-shadow: 0 4px 16px rgba(0,120,212,.12); transform: translateY(-1px); }
.iicm-stat-blue   { border-left-color: #0078D4; }
.iicm-stat-orange { border-left-color: #E87722; }
.iicm-stat-green  { border-left-color: #16a34a; }
.iicm-stat-red    { border-left-color: #dc2626; }
.iicm-stat-active { background: #f0f7ff; box-shadow: 0 0 0 2px #0078D4; }
.iicm-stat-count { font-size: 32px; font-weight: 800; color: #1a1a2e; line-height: 1; }
.iicm-stat-label { font-size: 13px; color: #777; font-weight: 500; }

/* Filter bar */
.iicm-filter-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
.iicm-filter-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.iicm-pill { padding: 6px 16px; border-radius: 20px; background: #f0f4f8; color: #555; font-size: 13px; font-weight: 600; text-decoration: none; transition: background 0.15s; }
.iicm-pill:hover { background: #dde8f5; color: #0078D4; }
.iicm-pill-active { background: #0078D4; color: #fff; }
.iicm-pill-active:hover { background: #005fa3; color: #fff; }
.iicm-search-form { display: flex; gap: 8px; align-items: center; }
.iicm-search-input { padding: 8px 12px; border: 1.5px solid #d0d9e4; border-radius: 6px; font-size: 13px; width: 220px; font-family: inherit; }
.iicm-search-input:focus { outline: none; border-color: #0078D4; box-shadow: 0 0 0 3px rgba(0,120,212,.1); }

/* Card + Table */
.iicm-admin-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 8px rgba(0,0,0,.06); overflow: hidden; padding: 24px; }
.iicm-table-card { padding: 0; margin-bottom: 24px; }
.iicm-apps-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.iicm-apps-table thead tr { background: #f8fafd; border-bottom: 2px solid #e8edf5; }
.iicm-apps-table th { padding: 12px 16px; text-align: left; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #666; }
.iicm-apps-table td { padding: 14px 16px; border-bottom: 1px solid #f0f3f8; vertical-align: middle; }
.iicm-apps-table tbody tr:nth-child(even) td { background: #fafbff; }
.iicm-apps-table tbody tr:hover td { background: #f0f7ff; }
.iicm-company-name { display: block; font-weight: 700; color: #1a1a2e; }
.iicm-reg-number   { display: block; font-size: 12px; color: #888; margin-top: 2px; }

/* Status badges */
.iicm-status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
.iicm-status-pending    { background: #fff7ed; color: #c2580e; }
.iicm-status-processing { background: #eff6ff; color: #1d4ed8; }
.iicm-status-approved   { background: #f0fdf4; color: #15803d; }
.iicm-status-rejected   { background: #fef2f2; color: #b91c1c; }
.iicm-membership-badge  { display: inline-block; padding: 3px 10px; border-radius: 10px; background: #f0f7ff; color: #0078D4; font-size: 12px; font-weight: 600; }
.iicm-view-btn          { display: inline-block; padding: 6px 16px; background: #0078D4; color: #fff; border-radius: 5px; font-size: 13px; font-weight: 600; text-decoration: none; transition: background 0.15s; }
.iicm-view-btn:hover    { background: #005fa3; color: #fff; }
.iicm-empty-state       { padding: 40px; text-align: center; color: #888; font-size: 15px; }

/* Pagination */
.iicm-pagination { padding: 16px; display: flex; gap: 6px; justify-content: flex-end; border-top: 1px solid #f0f3f8; }
.iicm-page-btn        { display: inline-block; padding: 6px 12px; border-radius: 5px; background: #f0f4f8; color: #555; font-size: 13px; font-weight: 600; text-decoration: none; transition: background 0.15s; }
.iicm-page-btn:hover  { background: #dde8f5; }
.iicm-page-active     { background: #0078D4; color: #fff; }
.iicm-page-active:hover { background: #005fa3; color: #fff; }

/* Detail page */
.iicm-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; padding: 24px; }
.iicm-detail-section-title { font-size: 14px; font-weight: 700; color: #0078D4; text-transform: uppercase; letter-spacing: .5px; margin: 0 0 12px; padding-bottom: 8px; border-bottom: 2px solid #e8f3fc; }
.iicm-detail-row   { display: flex; margin-bottom: 8px; font-size: 14px; }
.iicm-detail-label { min-width: 160px; font-weight: 600; color: #666; flex-shrink: 0; }
.iicm-detail-value { color: #1a1a2e; }

/* Admin panel (detail page) */
.iicm-admin-panel        { padding: 24px; border-top: 2px solid #e8f3fc; background: #f8fafd; }
.iicm-admin-panel-title  { font-size: 16px; font-weight: 700; color: #0078D4; margin: 0 0 16px; }
.iicm-admin-field        { margin-bottom: 16px; }
.iicm-admin-field label  { display: block; font-weight: 600; font-size: 13px; color: #555; margin-bottom: 6px; }
.iicm-admin-field select,
.iicm-admin-field textarea { width: 100%; max-width: 400px; padding: 9px 12px; border: 1.5px solid #d0d9e4; border-radius: 6px; font-size: 14px; font-family: inherit; background: #fff; }
.iicm-admin-field textarea { max-width: 100%; min-height: 80px; }
.iicm-admin-row-inline   { display: flex; gap: 16px; align-items: flex-start; }
.iicm-admin-row-inline .iicm-admin-field { flex: 1; }

/* Inline notices */
.iicm-admin-notice   { padding: 10px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; margin-bottom: 12px; display: none; }
.iicm-notice-success { background: #f0fdf4; color: #15803d; border: 1px solid #86efac; }
.iicm-notice-error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fca5a5; }

/* Back link */
.iicm-back-link { display: inline-flex; align-items: center; gap: 6px; color: #0078D4; font-size: 13px; font-weight: 600; text-decoration: none; margin-bottom: 16px; }
.iicm-back-link:hover { color: #005fa3; }

/* Settings */
.iicm-settings-field       { margin-bottom: 20px; max-width: 480px; }
.iicm-settings-field label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #333; }
.iicm-settings-field input { width: 100%; padding: 9px 12px; border: 1.5px solid #d0d9e4; border-radius: 6px; font-size: 14px; font-family: inherit; box-sizing: border-box; }
.iicm-settings-hint        { font-size: 12px; color: #888; margin-top: 4px; }
```

- [ ] **Step 2: Commit**

```bash
git add admin/class-admin.php admin/class-admin-dashboard.php admin/assets/admin.css
git commit -m "feat: admin menu, dashboard list, settings, CSS"
```

---

## Chunk 6: Admin Detail Page + AJAX Update

**Files:**
- Create: `iicm-membership/admin/class-admin-detail.php`
- Create: `iicm-membership/admin/assets/admin.js`

### Task 15: Admin detail page

- [ ] **Step 1: Create `admin/class-admin-detail.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Admin_Detail {

    public function __construct() {
        add_action( 'wp_ajax_iicm_update_application', array( $this, 'handle_update' ) );
    }

    public static function render( int $id ) {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $db   = new IICM_Database();
        $item = $db->get_by_id( $id );

        if ( ! $item ) {
            echo '<div class="iicm-admin-wrap"><p>Application not found.</p></div>';
            return;
        }

        $upload_dir = wp_upload_dir();
        $file_url   = ! empty( $item->company_profile_file )
                      ? $upload_dir['baseurl'] . '/' . $item->company_profile_file : '';
        $file_name  = ! empty( $item->company_profile_file ) ? basename( $item->company_profile_file ) : '';

        $cats_arr   = json_decode( $item->org_categories, true );
        $cat_labels = array(
            'public_retirement'    => 'Public Retirement/Pension Plan',
            'private_retirement'   => 'Private Retirement/Pension Plan',
            'corporate_retirement' => 'Corporate Retirement/Pension Plan',
            'insurance'            => 'Insurance',
            'fund_management'      => 'Fund Management/Asset Management',
            'unit_trust'           => 'Unit Trust/CIV',
            'sovereign_wealth'     => 'Sovereign Wealth Fund',
            'public_listed'        => 'Public Listed Company',
            'corporate_private'    => 'Corporate/Private Company',
            'association'          => 'Association',
            'foreign_entities'     => 'Foreign Entities',
            'educational'          => 'Educational Institutions',
            'others'               => 'Others',
        );
        $cat_display = '';
        if ( is_array( $cats_arr ) ) {
            $out = array();
            foreach ( $cats_arr as $c ) {
                $out[] = isset( $cat_labels[ $c ] ) ? $cat_labels[ $c ] : $c;
            }
            $cat_display = implode( ', ', $out );
        }

        $aum_map = array(
            'below_100b'   => 'Below RM100 billion',
            'rm100b_above' => 'RM100 billion and above',
            'none'         => 'None',
        );

        $base_url = admin_url( 'admin.php?page=iicm-membership' );
        ?>
        <div class="iicm-admin-wrap">
            <a href="<?php echo esc_url( $base_url ); ?>" class="iicm-back-link">&#8592; Back to Applications</a>

            <div class="iicm-admin-topbar">
                <h1 class="iicm-admin-page-title"><?php echo esc_html( $item->company_name ); ?></h1>
                <span class="iicm-status-badge iicm-status-<?php echo esc_attr( $item->status ); ?>">
                    <?php echo esc_html( ucfirst( $item->status ) ); ?>
                </span>
            </div>

            <div class="iicm-admin-card" style="margin-bottom:24px; padding:0;">
                <div class="iicm-detail-grid">

                    <!-- Org Profile -->
                    <div>
                        <h3 class="iicm-detail-section-title">Organisation Profile</h3>
                        <?php
                        $org_fields = array(
                            'Company Name'           => $item->company_name,
                            'Registration Number'    => $item->company_reg_number,
                            'Registered Address'     => $item->registered_address,
                            'Postcode'               => $item->registered_postcode,
                            'City / State'           => $item->registered_city_state,
                            'Country'                => $item->registered_country,
                            'Correspondence Address' => $item->correspondence_address,
                            'Corr. Postcode'         => $item->correspondence_postcode,
                            'Corr. City / State'     => $item->correspondence_city_state,
                            'Corr. Country'          => $item->correspondence_country,
                            'Telephone'              => $item->telephone,
                            'Email'                  => $item->email,
                            'Website'                => $item->website,
                            'Category'               => $cat_display,
                            'Category (Others)'      => $item->org_category_other,
                            'AUM'                    => isset( $aum_map[ $item->aum ] ) ? $aum_map[ $item->aum ] : $item->aum,
                        );
                        foreach ( $org_fields as $label => $value ) :
                            if ( (string) $value === '' ) continue;
                        ?>
                            <div class="iicm-detail-row">
                                <span class="iicm-detail-label"><?php echo esc_html( $label ); ?></span>
                                <span class="iicm-detail-value">
                                <?php if ( $label === 'Website' ) : ?>
                                    <a href="<?php echo esc_url( $value ); ?>" target="_blank"><?php echo esc_html( $value ); ?></a>
                                <?php elseif ( $label === 'Email' ) : ?>
                                    <a href="mailto:<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></a>
                                <?php else : ?>
                                    <?php echo nl2br( esc_html( $value ) ); ?>
                                <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Representative + Declaration -->
                    <div>
                        <h3 class="iicm-detail-section-title">Nominated Representative</h3>
                        <?php
                        $rep_fields = array(
                            'Name'          => $item->rep_name,
                            'NRIC/Passport' => $item->rep_nric_passport,
                            'Nationality'   => $item->rep_nationality,
                            'Designation'   => $item->rep_designation,
                            'Email'         => $item->rep_email,
                            'Address'       => $item->rep_address,
                            'Postcode'      => $item->rep_postcode,
                            'City / State'  => $item->rep_city_state,
                            'Country'       => $item->rep_country,
                            'Office Tel'    => $item->rep_office_tel,
                            'Handphone'     => $item->rep_handphone,
                        );
                        foreach ( $rep_fields as $label => $value ) :
                            if ( (string) $value === '' ) continue;
                        ?>
                            <div class="iicm-detail-row">
                                <span class="iicm-detail-label"><?php echo esc_html( $label ); ?></span>
                                <span class="iicm-detail-value">
                                <?php if ( $label === 'Email' ) : ?>
                                    <a href="mailto:<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></a>
                                <?php else : ?>
                                    <?php echo nl2br( esc_html( $value ) ); ?>
                                <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>

                        <h3 class="iicm-detail-section-title" style="margin-top:24px;">Declaration</h3>
                        <div class="iicm-detail-row">
                            <span class="iicm-detail-label">Authorised Signatory</span>
                            <span class="iicm-detail-value"><?php echo esc_html( $item->declarant_name ); ?></span>
                        </div>
                        <div class="iicm-detail-row">
                            <span class="iicm-detail-label">Designation</span>
                            <span class="iicm-detail-value"><?php echo esc_html( $item->declarant_designation ); ?></span>
                        </div>
                        <div class="iicm-detail-row">
                            <span class="iicm-detail-label">Declaration Agreed</span>
                            <span class="iicm-detail-value">
                                <?php echo $item->declaration_agreed
                                    ? esc_html( 'Yes — ' . $item->declaration_agreed_at )
                                    : 'No'; ?>
                            </span>
                        </div>
                        <?php if ( $file_url ) : ?>
                        <div class="iicm-detail-row">
                            <span class="iicm-detail-label">Company Profile</span>
                            <span class="iicm-detail-value">
                                <a href="<?php echo esc_url( $file_url ); ?>" target="_blank"
                                   class="iicm-admin-btn" style="font-size:12px;padding:5px 12px;">
                                    &#8681; <?php echo esc_html( $file_name ); ?>
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Admin Management Panel -->
                <div class="iicm-admin-panel">
                    <h3 class="iicm-admin-panel-title">Admin Management</h3>
                    <div id="iicm-update-notice" class="iicm-admin-notice"></div>

                    <div class="iicm-admin-row-inline">
                        <div class="iicm-admin-field">
                            <label for="iicm-membership-type">Membership Type</label>
                            <select id="iicm-membership-type">
                                <option value="">&#8212; Not set &#8212;</option>
                                <option value="ordinary"  <?php selected( $item->membership_type, 'ordinary' ); ?>>Ordinary</option>
                                <option value="associate" <?php selected( $item->membership_type, 'associate' ); ?>>Associate</option>
                            </select>
                        </div>
                        <div class="iicm-admin-field">
                            <label for="iicm-membership-tier">Membership Tier</label>
                            <select id="iicm-membership-tier">
                                <option value="">&#8212; Not set &#8212;</option>
                                <optgroup label="Ordinary" id="iicm-tier-ordinary">
                                    <option value="tier1" <?php selected( $item->membership_tier, 'tier1' ); ?>>Tier 1 (AUM &ge; RM100b)</option>
                                    <option value="tier2" <?php selected( $item->membership_tier, 'tier2' ); ?>>Tier 2 (AUM &lt; RM100b)</option>
                                    <option value="tier3" <?php selected( $item->membership_tier, 'tier3' ); ?>>Tier 3 (Industry groups)</option>
                                </optgroup>
                                <optgroup label="Associate" id="iicm-tier-associate">
                                    <option value="local"   <?php selected( $item->membership_tier, 'local' );   ?>>Local Entity</option>
                                    <option value="foreign" <?php selected( $item->membership_tier, 'foreign' ); ?>>Foreign Entity</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="iicm-admin-field">
                            <label for="iicm-status-select">Status</label>
                            <select id="iicm-status-select">
                                <option value="pending"    <?php selected( $item->status, 'pending' );    ?>>Pending</option>
                                <option value="processing" <?php selected( $item->status, 'processing' ); ?>>Processing</option>
                                <option value="approved"   <?php selected( $item->status, 'approved' );   ?>>Approved</option>
                                <option value="rejected"   <?php selected( $item->status, 'rejected' );   ?>>Rejected</option>
                            </select>
                        </div>
                    </div>

                    <div class="iicm-admin-field">
                        <label for="iicm-admin-notes">Admin Notes</label>
                        <textarea id="iicm-admin-notes" rows="4"><?php echo esc_textarea( $item->admin_notes ); ?></textarea>
                    </div>

                    <button class="iicm-admin-btn" id="iicm-update-btn"
                            data-id="<?php echo intval( $item->id ); ?>">Update</button>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_update() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }
        if ( ! check_ajax_referer( 'iicm_update_application', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed.' ) );
        }

        $id              = intval( $_POST['id']              ?? 0 );
        $status          = sanitize_text_field( $_POST['status']          ?? '' );
        $membership_type = sanitize_text_field( $_POST['membership_type'] ?? '' );
        $membership_tier = sanitize_text_field( $_POST['membership_tier'] ?? '' );
        $admin_notes     = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid application ID.' ) );
        }
        if ( ! in_array( $status, array( 'pending', 'processing', 'approved', 'rejected' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid status value.' ) );
        }

        $db     = new IICM_Database();
        $result = $db->update( $id, array(
            'status'          => $status,
            'membership_type' => $membership_type,
            'membership_tier' => $membership_tier,
            'admin_notes'     => $admin_notes,
        ) );

        if ( $result ) {
            wp_send_json_success( array( 'message' => 'Updated successfully.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Update failed. Please try again.' ) );
        }
    }
}
```

### Task 16: Admin JS

**Security note:** All notice text is set via `textContent` (never `innerHTML`) to prevent XSS from server response data.

- [ ] **Step 1: Create `admin/assets/admin.js`**

```javascript
(function () {
    'use strict';

    /* ---- Membership tier conditional show/hide ---- */
    function initTierConditional() {
        var typeSelect      = document.getElementById('iicm-membership-type');
        var tierSelect      = document.getElementById('iicm-membership-tier');
        var tierOrdinary    = document.getElementById('iicm-tier-ordinary');
        var tierAssociate   = document.getElementById('iicm-tier-associate');
        if (!typeSelect || !tierSelect) return;

        function updateTierOptions() {
            var val = typeSelect.value;
            if (val === 'ordinary') {
                if (tierOrdinary)  tierOrdinary.style.display  = '';
                if (tierAssociate) tierAssociate.style.display = 'none';
                var cur = tierSelect.value;
                if (cur === 'local' || cur === 'foreign') tierSelect.value = '';
            } else if (val === 'associate') {
                if (tierOrdinary)  tierOrdinary.style.display  = 'none';
                if (tierAssociate) tierAssociate.style.display = '';
                var cur2 = tierSelect.value;
                if (cur2 === 'tier1' || cur2 === 'tier2' || cur2 === 'tier3') tierSelect.value = '';
            } else {
                if (tierOrdinary)  tierOrdinary.style.display  = '';
                if (tierAssociate) tierAssociate.style.display = '';
            }
        }

        typeSelect.addEventListener('change', updateTierOptions);
        updateTierOptions();
    }

    /* ---- AJAX update (textContent only — no innerHTML) ---- */
    function initUpdateBtn() {
        var btn = document.getElementById('iicm-update-btn');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var id             = btn.getAttribute('data-id');
            var statusEl       = document.getElementById('iicm-status-select');
            var typeEl         = document.getElementById('iicm-membership-type');
            var tierEl         = document.getElementById('iicm-membership-tier');
            var notesEl        = document.getElementById('iicm-admin-notes');
            var noticeEl       = document.getElementById('iicm-update-notice');

            var status         = statusEl ? statusEl.value         : '';
            var membershipType = typeEl   ? typeEl.value           : '';
            var membershipTier = tierEl   ? tierEl.value           : '';
            var adminNotes     = notesEl  ? notesEl.value          : '';

            if (noticeEl) {
                noticeEl.textContent = '';
                noticeEl.className   = 'iicm-admin-notice';
                noticeEl.style.display = 'none';
            }

            btn.disabled      = true;
            btn.textContent   = 'Saving\u2026';

            var params = new URLSearchParams();
            params.append('action',          'iicm_update_application');
            params.append('nonce',           iicmAdmin.nonce);
            params.append('id',              id);
            params.append('status',          status);
            params.append('membership_type', membershipType);
            params.append('membership_tier', membershipTier);
            params.append('admin_notes',     adminNotes);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', iicmAdmin.ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                btn.disabled    = false;
                btn.textContent = 'Update';

                var message = '';
                var success = false;
                try {
                    var res = JSON.parse(xhr.responseText);
                    success = !!res.success;
                    message = (res.data && res.data.message) ? res.data.message : (success ? 'Updated.' : 'Error.');
                } catch (e) {
                    message = 'An unexpected error occurred.';
                }

                if (noticeEl) {
                    noticeEl.textContent   = message; // textContent only — safe
                    noticeEl.className     = 'iicm-admin-notice ' + (success ? 'iicm-notice-success' : 'iicm-notice-error');
                    noticeEl.style.display = 'block';
                }

                // Update the status badge without innerHTML
                if (success) {
                    var badge = document.querySelector('.iicm-admin-topbar .iicm-status-badge');
                    if (badge && status) {
                        badge.className    = 'iicm-status-badge iicm-status-' + status;
                        badge.textContent  = status.charAt(0).toUpperCase() + status.slice(1);
                    }
                }
            };

            xhr.send(params.toString());
        });
    }

    function init() {
        initTierConditional();
        initUpdateBtn();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

- [ ] **Step 2: Test:**
  1. Click "View" on any application in admin list
  2. Verify all data renders correctly
  3. Change Membership Type — verify tier options filter appropriately
  4. Change status, click Update — verify green success notice appears (no page reload)
  5. Verify updated status badge in page header changes instantly

- [ ] **Step 3: Commit**

```bash
git add admin/class-admin-detail.php admin/assets/admin.js
git commit -m "feat: admin detail page, AJAX status/membership update, tier conditional"
```

---

## Chunk 7: Export (CSV + Excel)

**Files:**
- Create: `iicm-membership/includes/lib/SimpleXLSXGen.php` (download)
- Create: `iicm-membership/includes/class-export.php`

### Task 17: Download SimpleXLSXGen

- [ ] **Step 1: Download single-file library**

```bash
curl -L -o /c/laragon/www/wordpress/wp-content/plugins/iicm-membership/includes/lib/SimpleXLSXGen.php \
  https://raw.githubusercontent.com/shuchkin/simplexlsxgen/master/src/SimpleXLSXGen.php
```

If no internet access, copy `SimpleXLSXGen.php` manually from the [shuchkin/simplexlsxgen GitHub repo](https://github.com/shuchkin/simplexlsxgen).

- [ ] **Step 2: Verify it contains the class**

```bash
head -5 /c/laragon/www/wordpress/wp-content/plugins/iicm-membership/includes/lib/SimpleXLSXGen.php
```

Expected: namespace declaration and `class SimpleXLSXGen`.

- [ ] **Step 3: Note the namespace at the top of the file.** It will be either `Shuchkin\SimpleXLSXGen` or just `SimpleXLSXGen` — adjust the call in class-export.php accordingly.

### Task 18: Export class

- [ ] **Step 1: Create `includes/class-export.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Export {

    private function get_headers(): array {
        return array(
            'ID', 'Company Name', 'Reg Number',
            'Registered Address', 'Reg Postcode', 'Reg City/State', 'Reg Country',
            'Correspondence Address', 'Corr Postcode', 'Corr City/State', 'Corr Country',
            'Telephone', 'Email', 'Website',
            'Org Categories (JSON)', 'Org Category Other', 'AUM',
            'Membership Type', 'Membership Tier',
            'Rep Name', 'Rep NRIC/Passport', 'Rep Nationality', 'Rep Designation',
            'Rep Email', 'Rep Address', 'Rep Postcode', 'Rep City/State', 'Rep Country',
            'Rep Office Tel', 'Rep Handphone',
            'Declarant Name', 'Declarant Designation',
            'Declaration Agreed', 'Declaration Agreed At',
            'Company Profile File', 'Status', 'Admin Notes',
            'Submitted At', 'Updated At',
        );
    }

    private function row_to_array( $item ): array {
        return array(
            $item->id,
            $item->company_name,
            $item->company_reg_number,
            $item->registered_address,
            $item->registered_postcode,
            $item->registered_city_state,
            $item->registered_country,
            $item->correspondence_address,
            $item->correspondence_postcode,
            $item->correspondence_city_state,
            $item->correspondence_country,
            $item->telephone,
            $item->email,
            $item->website,
            $item->org_categories,
            $item->org_category_other,
            $item->aum,
            $item->membership_type,
            $item->membership_tier,
            $item->rep_name,
            $item->rep_nric_passport,
            $item->rep_nationality,
            $item->rep_designation,
            $item->rep_email,
            $item->rep_address,
            $item->rep_postcode,
            $item->rep_city_state,
            $item->rep_country,
            $item->rep_office_tel,
            $item->rep_handphone,
            $item->declarant_name,
            $item->declarant_designation,
            $item->declaration_agreed ? 'Yes' : 'No',
            $item->declaration_agreed_at,
            $item->company_profile_file,
            $item->status,
            $item->admin_notes,
            $item->submitted_at,
            $item->updated_at,
        );
    }

    public function export_csv( array $items, string $status = '' ) {
        $status_slug = $status ?: 'all';
        $filename    = 'iicm-applications-' . $status_slug . '-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        fputs( $output, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel
        fputcsv( $output, $this->get_headers() );
        foreach ( $items as $item ) {
            fputcsv( $output, $this->row_to_array( $item ) );
        }
        fclose( $output );
    }

    public function export_excel( array $items, string $status = '' ) {
        $status_slug = $status ?: 'all';
        $filename    = 'iicm-applications-' . $status_slug . '-' . date( 'Y-m-d' ) . '.xlsx';

        $rows   = array( $this->get_headers() );
        foreach ( $items as $item ) {
            $rows[] = $this->row_to_array( $item );
        }

        // Adjust namespace based on SimpleXLSXGen.php header
        // If namespace is Shuchkin: \Shuchkin\SimpleXLSXGen::fromArray( $rows )->downloadAs( $filename );
        // If no namespace:          SimpleXLSXGen::fromArray( $rows )->downloadAs( $filename );
        \Shuchkin\SimpleXLSXGen::fromArray( $rows )->downloadAs( $filename );
    }
}
```

- [ ] **Step 2: Test exports:**
  1. Go to admin Applications, set filter to "Approved"
  2. Click "Export CSV" — filename should be `iicm-applications-approved-YYYY-MM-DD.csv`, records only approved
  3. Click "Export Excel" — verify `.xlsx` opens in Excel/LibreOffice
  4. Remove filter (All) — export should include all records
  5. Filename should be `iicm-applications-all-YYYY-MM-DD.csv`

- [ ] **Step 3: Commit**

```bash
git add includes/lib/SimpleXLSXGen.php includes/class-export.php
git commit -m "feat: CSV and Excel export"
```

---

## Chunk 8: Integration Smoke Test

### Task 19: Full end-to-end checklist

Run through manually on Laragon localhost. Check each item:

**Public Form:**
- [ ] `[iicm_membership_form]` shortcode renders on a page
- [ ] Step 1 → 2 → 3 navigation works; back navigation works
- [ ] Step indicators update (active / done checkmark)
- [ ] "Same as registered address" copies fields and hides correspondence section
- [ ] "Others" category checkbox reveals the text input
- [ ] Required field validation blocks advancing with empty fields
- [ ] AUM radio required validation works
- [ ] File type validation rejects `.exe`, accepts `.pdf`
- [ ] File size validation rejects files over 5MB
- [ ] Submitting duplicate reg number (status pending/processing/approved) shows error message
- [ ] Rejected reg number allows resubmission
- [ ] Successful submission shows thank-you message with company name
- [ ] Admin email received with application summary and dashboard link
- [ ] Applicant email received with confirmation summary

**Admin Dashboard:**
- [ ] "IICM Membership" menu appears in WP sidebar with dashicon
- [ ] Stat cards show correct counts; clicking filters the list
- [ ] Filter pills work; active pill is highlighted blue
- [ ] Search by company name returns correct results
- [ ] Search by reg number returns correct results
- [ ] "View" button opens detail page
- [ ] All submitted data displays correctly on detail page
- [ ] Company profile file download link opens the file
- [ ] Membership Type dropdown: Ordinary hides Associate tier options; Associate hides Ordinary tiers
- [ ] AJAX Update saves all fields — success notice appears inline (no page reload)
- [ ] Status badge in page header updates instantly after save
- [ ] Settings page saves admin email, sender name, sender from address

**Export:**
- [ ] CSV export respects active status filter; filename includes status slug
- [ ] Excel export downloads valid `.xlsx`; opens correctly in Excel/LibreOffice
- [ ] "All" filter exports all records

**Uninstall:**
- [ ] Deactivate → Reactivate: data preserved, table intact
- [ ] Delete plugin: `wp_iicm_applications` dropped, all options deleted, uploads directory removed

### Task 20: Final commit

- [ ] **Step 1:**

```bash
git add .
git commit -m "feat: IICM Membership Application Plugin v1.0.0 — complete"
```

---

## Quick Reference: All Files

| File | Purpose |
|---|---|
| `iicm-membership.php` | Bootstrap, constants, include chain, hook registration |
| `uninstall.php` | Drop table, delete options, delete uploads on plugin delete |
| `includes/class-activator.php` | `dbDelta()` table creation, default options |
| `includes/class-deactivator.php` | `flush_rewrite_rules()` |
| `includes/class-database.php` | All DB operations: insert, get_by_id, get_list, get_all_for_export, count_by_status, update, has_active_application |
| `includes/class-form-handler.php` | AJAX `iicm_submit_application`: nonce, sanitize, validate, dedupe, file upload, insert, email |
| `includes/class-email.php` | HTML emails via `wp_mail()`: admin + applicant |
| `includes/class-export.php` | CSV (`fputcsv`) + Excel (SimpleXLSXGen) streaming download |
| `includes/lib/SimpleXLSXGen.php` | Bundled single-file Excel writer |
| `admin/class-admin.php` | Menu registration, asset enqueue, Settings page |
| `admin/class-admin-dashboard.php` | Stat cards, filter pills, table, pagination, export routing |
| `admin/class-admin-detail.php` | Single entry view + AJAX `iicm_update_application` |
| `admin/assets/admin.css` | Full custom admin UI styles (no WP defaults) |
| `admin/assets/admin.js` | Tier conditional + AJAX update (textContent only, XSS-safe) |
| `public/class-shortcode.php` | `[iicm_membership_form]` + `[iicm_membership_fees]` |
| `public/assets/form.css` | Multi-step wizard styles (IICM brand) |
| `public/assets/form.js` | Step navigation, client-side validation, AJAX submit |
