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
        if ( empty( $company_name ) )          $errors[] = 'Company name is required.';
        if ( empty( $company_reg_number ) )   $errors[] = 'Company registration number is required.';
        if ( empty( $registered_address ) )   $errors[] = 'Registered address is required.';
        if ( empty( $registered_postcode ) )  $errors[] = 'Registered postcode is required.';
        if ( empty( $registered_city_state ) ) $errors[] = 'Registered city/state is required.';
        if ( empty( $registered_country ) )   $errors[] = 'Registered country is required.';
        if ( empty( $telephone ) )            $errors[] = 'Telephone number is required.';
        if ( empty( $website ) )              $errors[] = 'Website address is required.';
        if ( empty( $email ) || ! is_email( $email ) ) $errors[] = 'A valid email address is required.';
        if ( empty( $org_categories ) )       $errors[] = 'Please select at least one organisation category.';
        if ( ! in_array( $aum, array( 'below_100b', 'rm100b_above', 'none' ), true ) ) {
            $errors[] = 'Please select an AUM option.';
        }
        if ( empty( $rep_name ) )             $errors[] = 'Representative name is required.';
        if ( empty( $rep_nric_passport ) )    $errors[] = 'Representative NRIC/Passport number is required.';
        if ( empty( $rep_nationality ) )      $errors[] = 'Representative nationality is required.';
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

        // Supporting document (optional)
        $supporting_document_file = '';
        if ( ! empty( $_FILES['supporting_document_file']['name'] ) ) {
            $sup_file     = $_FILES['supporting_document_file'];
            $sup_ext      = strtolower( pathinfo( $sup_file['name'], PATHINFO_EXTENSION ) );

            if ( ! isset( $allowed_mime_types[ $sup_ext ] ) ) {
                wp_send_json_error( array( 'message' => 'Supporting document: invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG.' ) );
            }
            if ( $sup_file['size'] > 5 * 1024 * 1024 ) {
                wp_send_json_error( array( 'message' => 'Supporting document: file size must not exceed 5MB.' ) );
            }
            $sup_finfo = wp_check_filetype_and_ext( $sup_file['tmp_name'], $sup_file['name'] );
            if ( ! $sup_finfo['type'] || ! in_array( $sup_finfo['type'], $allowed_mime_types, true ) ) {
                wp_send_json_error( array( 'message' => 'Supporting document: file type verification failed.' ) );
            }
            $sup_safe_filename = wp_unique_filename( $dest_dir, sanitize_file_name( $sup_file['name'] ) );
            $sup_dest_path     = $dest_dir . '/' . $sup_safe_filename;
            if ( ! move_uploaded_file( $sup_file['tmp_name'], $sup_dest_path ) ) {
                wp_send_json_error( array( 'message' => 'Supporting document upload failed. Please try again.' ) );
            }
            $supporting_document_file = 'iicm-membership/' . date( 'Y/m' ) . '/' . $sup_safe_filename;
        }

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
            'supporting_document_file'  => $supporting_document_file,
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
