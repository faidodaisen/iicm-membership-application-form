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
            supporting_document_file VARCHAR(500),
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
