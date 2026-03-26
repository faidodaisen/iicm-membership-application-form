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
