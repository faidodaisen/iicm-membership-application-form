<?php
/**
 * Plugin Name:  IICM Membership Application
 * Plugin URI:   https://fidodesign.net
 * Description:  Online membership application form for IICM Berhad with admin dashboard.
 * Version:      1.0.2
 * Author:       fidodesign
 * Author URI:   https://fidodesign.net
 * License:      GPL-2.0+
 * Text Domain:  iicm-membership
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'IICM_MEMBERSHIP_VERSION', '1.0.2' );
define( 'IICM_MEMBERSHIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IICM_MEMBERSHIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once IICM_MEMBERSHIP_PLUGIN_DIR . 'includes/class-updater.php';
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

// Auto-run DB upgrade when plugin version changes (e.g. after auto-update from GitHub).
add_action( 'plugins_loaded', function () {
    if ( get_option( 'iicm_membership_version' ) !== IICM_MEMBERSHIP_VERSION ) {
        IICM_Activator::activate();
    }
} );

new IICM_Admin();
new IICM_Shortcode();
new IICM_Form_Handler();
new IICM_Admin_Detail();

if ( is_admin() ) {
    new IICM_GitHub_Updater( __FILE__ );
}
