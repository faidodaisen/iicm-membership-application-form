<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init',            array( 'IICM_Admin_Dashboard', 'maybe_export' ) );
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
        add_submenu_page(
            'iicm-membership',
            'Help &amp; Guide',
            'Help &amp; Guide',
            'manage_options',
            'iicm-membership-help',
            array( $this, 'render_help' )
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

    public function render_help() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="iicm-admin-wrap">
            <h1 class="iicm-admin-page-title">Help &amp; Guide</h1>

            <div class="iicm-admin-card" style="margin-bottom:24px;">
                <h2 style="margin-top:0;font-size:16px;color:#0078D4;">&#128196; Shortcodes</h2>
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <thead>
                        <tr style="background:#f0f7ff;">
                            <th style="text-align:left;padding:10px 14px;border:1px solid #dde8f5;color:#0078D4;">Shortcode</th>
                            <th style="text-align:left;padding:10px 14px;border:1px solid #dde8f5;color:#0078D4;">Description</th>
                            <th style="text-align:left;padding:10px 14px;border:1px solid #dde8f5;color:#0078D4;">Usage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding:10px 14px;border:1px solid #dde8f5;font-family:monospace;background:#f9fbff;">[iicm_membership_form]</td>
                            <td style="padding:10px 14px;border:1px solid #dde8f5;">Displays the multi-step membership application form.</td>
                            <td style="padding:10px 14px;border:1px solid #dde8f5;">Paste into any Page content area or block editor using a Shortcode block.</td>
                        </tr>
                        <tr style="background:#fafafa;">
                            <td style="padding:10px 14px;border:1px solid #dde8f5;font-family:monospace;background:#f9fbff;">[iicm_membership_fees]</td>
                            <td style="padding:10px 14px;border:1px solid #dde8f5;">Displays the membership fee schedule table (Ordinary &amp; Associate tiers).</td>
                            <td style="padding:10px 14px;border:1px solid #dde8f5;">Paste into any Page or Post where you want the fee table to appear.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="iicm-admin-card" style="margin-bottom:24px;">
                <h2 style="margin-top:0;font-size:16px;color:#0078D4;">&#9654; Quick Setup Steps</h2>
                <ol style="font-size:14px;line-height:2;padding-left:20px;margin:0;">
                    <li>Go to <strong>Settings &rarr; Configure</strong> your admin notification email, sender name, and sender address.</li>
                    <li>Create a new Page (e.g. <em>Membership Application</em>) and paste <code>[iicm_membership_form]</code> into the content.</li>
                    <li>Optionally create another Page (e.g. <em>Membership Fees</em>) and paste <code>[iicm_membership_fees]</code>.</li>
                    <li>Publish both pages and link them from your site navigation.</li>
                    <li>When applications arrive, review them under <strong>IICM Membership &rarr; Applications</strong>.</li>
                </ol>
            </div>

            <div class="iicm-admin-card" style="margin-bottom:24px;">
                <h2 style="margin-top:0;font-size:16px;color:#0078D4;">&#128203; Managing Applications</h2>
                <ul style="font-size:14px;line-height:2;padding-left:20px;margin:0;">
                    <li>The <strong>Applications</strong> list shows all submissions with status badges: <strong>Pending</strong>, <strong>Processing</strong>, <strong>Approved</strong>, <strong>Rejected</strong>.</li>
                    <li>Use the filter pills or stat cards at the top to narrow the list by status.</li>
                    <li>Use the search box to find a company by name or registration number.</li>
                    <li>Click <strong>View</strong> on any row to open the full application detail.</li>
                    <li>On the detail page, set the <strong>Membership Type</strong> (Ordinary / Associate), <strong>Membership Tier</strong>, <strong>Status</strong>, and add internal <strong>Admin Notes</strong>, then click <strong>Update</strong>.</li>
                </ul>
            </div>

            <div class="iicm-admin-card" style="margin-bottom:24px;">
                <h2 style="margin-top:0;font-size:16px;color:#0078D4;">&#128229; Exporting Data</h2>
                <ul style="font-size:14px;line-height:2;padding-left:20px;margin:0;">
                    <li>On the Applications list page, use <strong>Export CSV</strong> or <strong>Export Excel</strong> buttons (top right).</li>
                    <li>Export respects the active <strong>status filter</strong> — e.g. filter to &ldquo;Approved&rdquo; first to export only approved applications.</li>
                    <li>If no filter is active (&ldquo;All&rdquo;), all records are exported.</li>
                    <li>The exported file includes all application fields including uploaded file paths.</li>
                </ul>
            </div>

            <div class="iicm-admin-card">
                <h2 style="margin-top:0;font-size:16px;color:#0078D4;">&#128274; Application Status Flow</h2>
                <p style="font-size:14px;margin:0 0 12px;">Status is managed manually by admin on each application&rsquo;s detail page.</p>
                <div style="font-size:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="iicm-status-badge iicm-status-pending">Pending</span>
                    <span style="color:#999;">&rarr;</span>
                    <span class="iicm-status-badge iicm-status-processing">Processing</span>
                    <span style="color:#999;">&rarr;</span>
                    <span class="iicm-status-badge iicm-status-approved">Approved</span>
                    <span style="color:#bbb;font-size:12px;margin-left:4px;">or</span>
                    <span class="iicm-status-badge iicm-status-rejected">Rejected</span>
                </div>
                <p style="font-size:13px;color:#888;margin:14px 0 0;">Note: If a company&rsquo;s application is <strong>Rejected</strong>, they may resubmit. If it is <strong>Pending</strong>, <strong>Processing</strong>, or <strong>Approved</strong>, a duplicate submission will be blocked automatically.</p>
            </div>

        </div>
        <?php
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
