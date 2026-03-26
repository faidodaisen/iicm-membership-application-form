<?php
/**
 * Export handler — CSV and Excel (xlsx) exports of application data.
 *
 * @package IICM_Membership
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class IICM_Export {

    /** Column headers (matching DB column order). */
    private $headers = [
        'ID', 'Company Name', 'Reg Number', 'Registered Address',
        'Reg Postcode', 'Reg City/State', 'Reg Country',
        'Correspondence Address', 'Corr Postcode', 'Corr City/State', 'Corr Country',
        'Telephone', 'Email', 'Website',
        'Org Categories', 'Org Category Other', 'AUM',
        'Membership Type', 'Membership Tier',
        'Rep Name', 'Rep NRIC/Passport', 'Rep Nationality', 'Rep Designation',
        'Rep Email', 'Rep Address', 'Rep Postcode', 'Rep City/State', 'Rep Country',
        'Rep Office Tel', 'Rep Handphone',
        'Declarant Name', 'Declarant Designation',
        'Declaration Agreed', 'Declaration Agreed At',
        'Company Profile File', 'Status', 'Admin Notes',
        'Submitted At', 'Updated At',
    ];

    /**
     * Export all matching rows as CSV (UTF-8 BOM).
     * Sends headers + output and exits.
     *
     * @param string $status 'all' or a valid status value.
     */
    public function export_csv( $status = 'all' ) {
        // Build filename: iicm-applications-all-2026-03-17.csv
        $filename = 'iicm-applications-' . sanitize_key( $status ) . '-' . gmdate( 'Y-m-d' ) . '.csv';

        // Send HTTP headers
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // UTF-8 BOM for Excel compatibility
        fwrite( $output, "\xEF\xBB\xBF" );

        // Header row
        fputcsv( $output, $this->headers );

        // Data rows — DB expects '' for "all records", not 'all'
        $db   = new IICM_Database();
        $rows = $db->get_all_for_export( $status === 'all' ? '' : $status );

        foreach ( $rows as $row ) {
            fputcsv( $output, $this->row_to_array( $row ) );
        }

        fclose( $output );
        exit;
    }

    /**
     * Export all matching rows as Excel (.xlsx).
     * Sends headers + output and exits.
     *
     * @param string $status 'all' or a valid status value.
     */
    public function export_excel( $status = 'all' ) {
        $filename = 'iicm-applications-' . sanitize_key( $status ) . '-' . gmdate( 'Y-m-d' ) . '.xlsx';

        $db   = new IICM_Database();
        $rows = $db->get_all_for_export( $status === 'all' ? '' : $status );

        // Build 2D array: first row = headers
        $data = [ $this->headers ];
        foreach ( $rows as $row ) {
            $data[] = $this->row_to_array( $row );
        }

        // Use SimpleXLSXGen to stream the file
        \Shuchkin\SimpleXLSXGen::fromArray( $data )->downloadAs( $filename );
    }

    /**
     * Convert a DB row object to a flat array matching $this->headers order.
     *
     * @param object $row DB row.
     * @return array
     */
    private function row_to_array( $row ) {
        // Decode org_categories JSON to readable string
        $categories = '';
        if ( ! empty( $row->org_categories ) ) {
            $decoded    = json_decode( $row->org_categories, true );
            $categories = is_array( $decoded ) ? implode( ', ', $decoded ) : $row->org_categories;
        }

        return [
            $row->id,
            $row->company_name,
            $row->company_reg_number,
            $row->registered_address,
            $row->registered_postcode,
            $row->registered_city_state,
            $row->registered_country,
            $row->correspondence_address,
            $row->correspondence_postcode,
            $row->correspondence_city_state,
            $row->correspondence_country,
            $row->telephone,
            $row->email,
            $row->website,
            $categories,
            $row->org_category_other,
            $row->aum,
            $row->membership_type,
            $row->membership_tier,
            $row->rep_name,
            $row->rep_nric_passport,
            $row->rep_nationality,
            $row->rep_designation,
            $row->rep_email,
            $row->rep_address,
            $row->rep_postcode,
            $row->rep_city_state,
            $row->rep_country,
            $row->rep_office_tel,
            $row->rep_handphone,
            $row->declarant_name,
            $row->declarant_designation,
            $row->declaration_agreed ? 'Yes' : 'No',
            $row->declaration_agreed_at,
            $row->company_profile_file,
            $row->status,
            $row->admin_notes,
            $row->submitted_at,
            $row->updated_at,
        ];
    }
}
