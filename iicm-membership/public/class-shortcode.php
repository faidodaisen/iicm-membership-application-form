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
                            <label for="registered_postcode">Postcode <span class="iicm-required">*</span></label>
                            <input type="text" id="registered_postcode" name="registered_postcode" required>
                        </div>
                        <div class="iicm-field">
                            <label for="registered_city_state">City / State <span class="iicm-required">*</span></label>
                            <input type="text" id="registered_city_state" name="registered_city_state" required>
                        </div>
                        <div class="iicm-field">
                            <label for="registered_country">Country <span class="iicm-required">*</span></label>
                            <input type="text" id="registered_country" name="registered_country" required>
                        </div>
                    </div>

                    <div class="iicm-field">
                        <div class="iicm-correspondence-header">
                            <label for="correspondence_address">Correspondence Address</label>
                            <label class="iicm-checkbox-label">
                                <input type="checkbox" id="same_as_registered" name="same_as_registered">
                                Same as registered address
                            </label>
                        </div>
                        <div id="correspondence-fields">
                            <textarea id="correspondence_address" name="correspondence_address" rows="3"></textarea>
                            <div class="iicm-row-3" style="margin-top:12px;">
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
                    </div>

                    <div class="iicm-row-2">
                        <div class="iicm-field">
                            <label for="telephone">Telephone No <span class="iicm-required">*</span></label>
                            <input type="text" id="telephone" name="telephone" required>
                        </div>
                        <div class="iicm-field">
                            <label for="email">Email Address <span class="iicm-required">*</span></label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>

                    <div class="iicm-field">
                        <label for="website">Website Address <span class="iicm-required">*</span></label>
                        <input type="url" id="website" name="website" placeholder="https://" required>
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
                            <label for="rep_nric_passport">NRIC / Passport Number <span class="iicm-required">*</span></label>
                            <input type="text" id="rep_nric_passport" name="rep_nric_passport" required>
                        </div>
                    </div>

                    <div class="iicm-row-2">
                        <div class="iicm-field">
                            <label for="rep_nationality">Nationality <span class="iicm-required">*</span></label>
                            <input type="text" id="rep_nationality" name="rep_nationality" required>
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

                    <div class="iicm-field">
                        <label for="supporting_document_file">Supporting Document</label>
                        <div class="iicm-file-upload-wrap">
                            <input type="file" id="supporting_document_file" name="supporting_document_file"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
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
