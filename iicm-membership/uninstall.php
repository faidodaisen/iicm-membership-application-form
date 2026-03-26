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
