<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Email {

    private $sender_name;
    private $sender_from;

    public function __construct() {
        $this->sender_name = get_option( 'iicm_email_sender_name', 'IICM Berhad' );
        $this->sender_from = get_option( 'iicm_email_sender_from', 'admin@iicm.org.my' );
    }

    private $filter_content_type;
    private $filter_from;
    private $filter_from_name;

    private function set_html_mail_filters() {
        $sender_name = $this->sender_name;
        $sender_from = $this->sender_from;
        $this->filter_content_type = function () { return 'text/html'; };
        $this->filter_from         = function () use ( $sender_from ) { return $sender_from; };
        $this->filter_from_name    = function () use ( $sender_name ) { return $sender_name; };
        add_filter( 'wp_mail_content_type', $this->filter_content_type );
        add_filter( 'wp_mail_from',         $this->filter_from );
        add_filter( 'wp_mail_from_name',    $this->filter_from_name );
    }

    private function remove_html_mail_filters() {
        remove_filter( 'wp_mail_content_type', $this->filter_content_type );
        remove_filter( 'wp_mail_from',         $this->filter_from );
        remove_filter( 'wp_mail_from_name',    $this->filter_from_name );
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
