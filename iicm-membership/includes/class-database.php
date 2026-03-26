<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class IICM_Database {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'iicm_applications';
    }

    /** Insert a new application. Returns inserted ID or false. */
    public function insert( array $data ) {
        global $wpdb;
        $result = $wpdb->insert( $this->table, $data );
        return $result ? $wpdb->insert_id : false;
    }

    /** Get a single application by ID. */
    public function get_by_id( int $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id )
        );
    }

    /**
     * Check if company_reg_number has an active application.
     * Active = pending, processing, or approved.
     */
    public function has_active_application( string $reg_number ): bool {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table}
                 WHERE company_reg_number = %s
                 AND status IN ('pending','processing','approved')",
                $reg_number
            )
        );
        return (int) $count > 0;
    }

    /**
     * Get paginated list.
     * Returns [ 'items' => [], 'total' => int ]
     */
    public function get_list( string $status = '', string $search = '', int $per_page = 20, int $page = 1 ): array {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        $where  = 'WHERE 1=1';
        $args   = array();

        if ( ! empty( $status ) ) {
            $where .= ' AND status = %s';
            $args[] = $status;
        }
        if ( ! empty( $search ) ) {
            $where .= ' AND (company_name LIKE %s OR company_reg_number LIKE %s)';
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
            $args[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $count_sql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $total = (int) ( empty( $args )
            ? $wpdb->get_var( $count_sql )
            : $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) );

        $args[] = $per_page;
        $args[] = $offset;
        $sql    = "SELECT * FROM {$this->table} {$where} ORDER BY submitted_at DESC LIMIT %d OFFSET %d";
        $items  = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );

        return array( 'items' => $items ? $items : array(), 'total' => $total );
    }

    /** Get all records filtered by status only (for export — ignores search). */
    public function get_all_for_export( string $status = '' ): array {
        global $wpdb;
        if ( ! empty( $status ) ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table} WHERE status = %s ORDER BY submitted_at DESC",
                    $status
                )
            );
        }
        return $wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY submitted_at DESC" );
    }

    /** Count applications grouped by status. */
    public function count_by_status(): array {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) AS cnt FROM {$this->table} GROUP BY status"
        );
        $counts = array(
            'total'      => 0,
            'pending'    => 0,
            'processing' => 0,
            'approved'   => 0,
            'rejected'   => 0,
        );
        foreach ( $results as $row ) {
            if ( isset( $counts[ $row->status ] ) ) {
                $counts[ $row->status ] = (int) $row->cnt;
            }
            $counts['total'] += (int) $row->cnt;
        }
        return $counts;
    }

    /** Update an application record by ID. */
    public function update( int $id, array $data ): bool {
        global $wpdb;
        $data['updated_at'] = current_time( 'mysql' );
        $result = $wpdb->update( $this->table, $data, array( 'id' => $id ) );
        return $result !== false;
    }
}
