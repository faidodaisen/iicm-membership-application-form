<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Admin_Dashboard {

    /**
     * Hooked to admin_init — handles export before any HTML output is sent.
     */
    public static function maybe_export() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'iicm-membership' ) return;
        if ( ! isset( $_GET['iicm_export'] ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        check_admin_referer( 'iicm_export' );
        self::handle_export( sanitize_text_field( $_GET['iicm_export'] ) );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Route to detail view
        if ( isset( $_GET['view'] ) && $_GET['view'] === 'detail' && ! empty( $_GET['id'] ) ) {
            IICM_Admin_Detail::render( intval( $_GET['id'] ) );
            return;
        }

        $db             = new IICM_Database();
        $counts         = $db->count_by_status();
        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $search         = isset( $_GET['s'] )       ? sanitize_text_field( $_GET['s'] )       : '';
        $page_num       = isset( $_GET['paged'] )   ? max( 1, intval( $_GET['paged'] ) )       : 1;
        $per_page       = 20;
        $result         = $db->get_list( $current_status, $search, $per_page, $page_num );
        $items          = $result['items'];
        $total          = $result['total'];
        $total_pages    = (int) ceil( $total / $per_page );
        $base_url       = admin_url( 'admin.php?page=iicm-membership' );

        $cat_labels = array(
            'public_retirement'    => 'Public Retirement',
            'private_retirement'   => 'Private Retirement',
            'corporate_retirement' => 'Corporate Retirement',
            'insurance'            => 'Insurance',
            'fund_management'      => 'Fund Management',
            'unit_trust'           => 'Unit Trust',
            'sovereign_wealth'     => 'Sovereign Wealth',
            'public_listed'        => 'Public Listed',
            'corporate_private'    => 'Corporate/Private',
            'association'          => 'Association',
            'foreign_entities'     => 'Foreign Entities',
            'educational'          => 'Educational',
            'others'               => 'Others',
        );
        ?>
        <div class="iicm-admin-wrap">
            <div class="iicm-admin-topbar">
                <h1 class="iicm-admin-page-title">Applications</h1>
                <div class="iicm-export-btns">
                    <?php
                    $ep      = $current_status ? '&filter_status=' . urlencode( $current_status ) : '';
                    $nonce   = wp_create_nonce( 'iicm_export' );
                    ?>
                    <a href="<?php echo esc_url( $base_url . '&iicm_export=csv' . $ep . '&_wpnonce=' . $nonce ); ?>" class="iicm-admin-btn">&#8681; Export CSV</a>
                    <a href="<?php echo esc_url( $base_url . '&iicm_export=excel' . $ep . '&_wpnonce=' . $nonce ); ?>" class="iicm-admin-btn iicm-btn-orange">&#8681; Export Excel</a>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="iicm-stat-cards">
                <?php
                $cards = array(
                    array( 'key' => '',           'label' => 'Total',      'color' => 'blue',   'count' => $counts['total'] ),
                    array( 'key' => 'processing', 'label' => 'Processing', 'color' => 'orange', 'count' => $counts['processing'] ),
                    array( 'key' => 'approved',   'label' => 'Approved',   'color' => 'green',  'count' => $counts['approved'] ),
                    array( 'key' => 'rejected',   'label' => 'Rejected',   'color' => 'red',    'count' => $counts['rejected'] ),
                );
                foreach ( $cards as $card ) :
                    $card_url = $base_url . ( $card['key'] ? '&status=' . $card['key'] : '' );
                    $active   = ( $current_status === $card['key'] ) ? ' iicm-stat-active' : '';
                ?>
                    <a href="<?php echo esc_url( $card_url ); ?>"
                       class="iicm-stat-card iicm-stat-<?php echo esc_attr( $card['color'] . $active ); ?>">
                        <span class="iicm-stat-count"><?php echo intval( $card['count'] ); ?></span>
                        <span class="iicm-stat-label"><?php echo esc_html( $card['label'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Filter Pills + Search -->
            <div class="iicm-filter-bar">
                <div class="iicm-filter-pills">
                    <?php
                    $pills = array(
                        '' => 'All (' . $counts['total'] . ')',
                        'pending'    => 'Pending (' . $counts['pending'] . ')',
                        'processing' => 'Processing (' . $counts['processing'] . ')',
                        'approved'   => 'Approved (' . $counts['approved'] . ')',
                        'rejected'   => 'Rejected (' . $counts['rejected'] . ')',
                    );
                    foreach ( $pills as $ps => $pl ) :
                        $pill_url = $base_url . ( $ps ? '&status=' . $ps : '' ) . ( $search ? '&s=' . urlencode( $search ) : '' );
                        $active   = ( $current_status === $ps ) ? ' iicm-pill-active' : '';
                    ?>
                        <a href="<?php echo esc_url( $pill_url ); ?>"
                           class="iicm-pill<?php echo esc_attr( $active ); ?>"><?php echo esc_html( $pl ); ?></a>
                    <?php endforeach; ?>
                </div>
                <form method="get" class="iicm-search-form">
                    <input type="hidden" name="page" value="iicm-membership">
                    <?php if ( $current_status ) : ?>
                        <input type="hidden" name="status" value="<?php echo esc_attr( $current_status ); ?>">
                    <?php endif; ?>
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="Search company or reg. no&hellip;" class="iicm-search-input">
                    <button type="submit" class="iicm-admin-btn">Search</button>
                </form>
            </div>

            <!-- Table -->
            <div class="iicm-admin-card iicm-table-card">
                <?php if ( empty( $items ) ) : ?>
                    <p class="iicm-empty-state">No applications found.</p>
                <?php else : ?>
                <table class="iicm-apps-table">
                    <thead>
                        <tr>
                            <th>Company</th><th>Category</th><th>Membership</th>
                            <th>Status</th><th>Submitted</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $items as $item ) :
                        $cats_arr = json_decode( $item->org_categories, true );
                        $cat_str  = '';
                        if ( is_array( $cats_arr ) ) {
                            $out = array();
                            foreach ( $cats_arr as $c ) {
                                $out[] = isset( $cat_labels[ $c ] ) ? $cat_labels[ $c ] : $c;
                            }
                            $cat_str = implode( ', ', $out );
                        }
                        $cat_display = mb_strlen( $cat_str ) > 40 ? mb_substr( $cat_str, 0, 37 ) . '&hellip;' : $cat_str;

                        $membership_display = '&mdash;';
                        if ( ! empty( $item->membership_type ) ) {
                            $tm = array( 'ordinary' => 'Ordinary', 'associate' => 'Associate' );
                            $tt = array( 'tier1' => 'T1', 'tier2' => 'T2', 'tier3' => 'T3', 'local' => 'Local', 'foreign' => 'Foreign' );
                            $tl = isset( $tm[ $item->membership_type ] ) ? $tm[ $item->membership_type ] : $item->membership_type;
                            $tl .= ( ! empty( $item->membership_tier ) && isset( $tt[ $item->membership_tier ] ) )
                                   ? ' &middot; ' . $tt[ $item->membership_tier ] : '';
                            $membership_display = '<span class="iicm-membership-badge">' . esc_html( $tl ) . '</span>';
                        }

                        $detail_url = $base_url . '&view=detail&id=' . intval( $item->id );
                    ?>
                        <tr>
                            <td class="iicm-col-company">
                                <span class="iicm-company-name"><?php echo esc_html( $item->company_name ); ?></span>
                                <span class="iicm-reg-number"><?php echo esc_html( $item->company_reg_number ); ?></span>
                            </td>
                            <td title="<?php echo esc_attr( $cat_str ); ?>"><?php echo wp_kses_post( $cat_display ); ?></td>
                            <td><?php echo wp_kses_post( $membership_display ); ?></td>
                            <td><span class="iicm-status-badge iicm-status-<?php echo esc_attr( $item->status ); ?>"><?php echo esc_html( ucfirst( $item->status ) ); ?></span></td>
                            <td><?php echo esc_html( date_i18n( 'd M Y', strtotime( $item->submitted_at ) ) ); ?></td>
                            <td><a href="<?php echo esc_url( $detail_url ); ?>" class="iicm-view-btn">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if ( $total_pages > 1 ) : ?>
                <div class="iicm-pagination">
                    <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                        $pg_url = $base_url
                                  . ( $current_status ? '&status=' . $current_status : '' )
                                  . ( $search ? '&s=' . urlencode( $search ) : '' )
                                  . '&paged=' . $p;
                        $active = ( $p === $page_num ) ? ' iicm-page-active' : '';
                    ?>
                        <a href="<?php echo esc_url( $pg_url ); ?>"
                           class="iicm-page-btn<?php echo esc_attr( $active ); ?>"><?php echo intval( $p ); ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function handle_export( string $format ) {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized.' );
        $status         = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : 'all';
        $allowed_statuses = [ 'all', 'pending', 'processing', 'approved', 'rejected' ];
        $status           = in_array( $status, $allowed_statuses, true ) ? $status : 'all';
        $exporter = new IICM_Export();
        if ( $format === 'csv' ) {
            $exporter->export_csv( $status );
        } else {
            $exporter->export_excel( $status );
        }
        exit;
    }
}
