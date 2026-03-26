<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Deactivator {
    public static function deactivate() {
        flush_rewrite_rules();
    }
}
