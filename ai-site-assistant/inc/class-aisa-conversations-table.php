<?php
/**
 * Conversations list (AI Assistant → Conversations).
 *
 * @package ai-site-assistant
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Conversations table.
 */
class AISA_Conversations_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'conversation',
				'plural'   => 'conversations',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Filters from the request: view (up|down|handoff) and search text.
	 *
	 * @return array{view:string,s:string}
	 */
	public static function current_filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$view = isset( $_REQUEST['view'] ) ? sanitize_key( $_REQUEST['view'] ) : '';
		$s    = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		// phpcs:enable
		return array(
			'view' => in_array( $view, array( 'up', 'down', 'handoff' ), true ) ? $view : '',
			's'    => $s,
		);
	}

	/**
	 * Current filters as query args (for the export link).
	 *
	 * @return array
	 */
	public function filter_args() {
		return array_filter( self::current_filters() );
	}

	/**
	 * WHERE clause for the filters.
	 *
	 * @param array $f Filters.
	 * @return string
	 */
	private static function where( $f ) {
		global $wpdb;
		$where = array( '1=1' );
		if ( 'up' === $f['view'] ) {
			$where[] = 'rating > 0';
		} elseif ( 'down' === $f['view'] ) {
			$where[] = 'rating < 0';
		} elseif ( 'handoff' === $f['view'] ) {
			$where[] = 'handoff = 1';
		}
		if ( '' !== $f['s'] ) {
			$like    = '%' . $wpdb->esc_like( $f['s'] ) . '%';
			$where[] = $wpdb->prepare( '(question LIKE %s OR answer LIKE %s)', $like, $like );
		}
		return implode( ' AND ', $where );
	}

	/**
	 * Fetch rows.
	 *
	 * @param array $f      Filters.
	 * @param int   $limit  Rows (0 = all).
	 * @param int   $offset Offset.
	 * @return array[]
	 */
	public static function query( $f, $limit, $offset ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . aisa_table( 'conversations' ) . ' WHERE ' . self::where( $f ) . ' ORDER BY id DESC';
		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}
		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Count rows.
	 *
	 * @param array $f Filters.
	 * @return int
	 */
	public static function count( $f ) {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . aisa_table( 'conversations' ) . ' WHERE ' . self::where( $f ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox">',
			'question' => __( 'Question and answer', 'ai-site-assistant' ),
			'rating'   => __( 'Rating', 'ai-site-assistant' ),
			'page'     => __( 'Asked on', 'ai-site-assistant' ),
			'date'     => __( 'Date', 'ai-site-assistant' ),
		);
	}

	/**
	 * Views.
	 *
	 * @return array
	 */
	protected function get_views() {
		$cur   = self::current_filters()['view'];
		$base  = admin_url( 'admin.php?page=aisa-conversations' );
		$views = array(
			''        => __( 'All', 'ai-site-assistant' ),
			'handoff' => __( 'Handed off', 'ai-site-assistant' ),
			'up'      => '👍',
			'down'    => '👎',
		);
		$out   = array();
		foreach ( $views as $v => $label ) {
			$n          = self::count(
				array(
					'view' => $v,
					's'    => '',
				)
			);
			$out[ '' === $v ? 'all' : $v ] = sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( '' === $v ? $base : add_query_arg( 'view', $v, $base ) ), $cur === $v ? ' class="current" aria-current="page"' : '', esc_html( $label ), $n );
		}
		return $out;
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'ai-site-assistant' ) );
	}

	/**
	 * Delete selected rows.
	 */
	public function process_bulk_action() {
		global $wpdb;
		if ( 'delete' !== $this->current_action() || empty( $_REQUEST['ids'] ) ) {
			return;
		}
		check_admin_referer( 'bulk-conversations' );
		$ids = implode( ',', array_map( 'absint', (array) $_REQUEST['ids'] ) );
		$wpdb->query( 'DELETE FROM ' . aisa_table( 'conversations' ) . " WHERE id IN ($ids)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Load the page of rows.
	 */
	public function prepare_items() {
		$per  = 20;
		$f    = self::current_filters();
		$page = $this->get_pagenum();
		$this->set_pagination_args(
			array(
				'total_items' => self::count( $f ),
				'per_page'    => $per,
			)
		);
		$this->items           = self::query( $f, $per, ( $page - 1 ) * $per );
		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d">', (int) $item['id'] );
	}

	/**
	 * Question with the answer and sources folded underneath.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_question( $item ) {
		$sources = json_decode( $item['sources'], true );
		$links   = array();
		foreach ( is_array( $sources ) ? $sources : array() as $i => $url ) {
			$links[] = $url ? sprintf( '<a href="%s" target="_blank" rel="noopener">[%d]</a>', esc_url( $url ), $i + 1 ) : sprintf( '[%d] %s', $i + 1, esc_html__( 'snippet', 'ai-site-assistant' ) );
		}
		$meta = array_filter(
			array(
				$item['handoff'] ? '<span class="aisa-tag">' . esc_html__( 'handed off', 'ai-site-assistant' ) . '</span>' : '',
				esc_html( $item['model'] ),
				$item['latency_ms'] ? esc_html( number_format_i18n( $item['latency_ms'] / 1000, 1 ) . ' s' ) : '',
				$item['tokens'] ? esc_html( sprintf( '%s tokens', number_format_i18n( $item['tokens'] ) ) ) : '',
			)
		);
		return sprintf(
			'<strong>%s</strong><details class="aisa-answer"><summary>%s</summary><div class="aisa-pre">%s</div>%s</details><div class="aisa-meta">%s</div>',
			esc_html( $item['question'] ),
			esc_html( wp_trim_words( $item['answer'], 18, '…' ) ),
			esc_html( $item['answer'] ),
			$links ? '<p>' . esc_html__( 'Sources:', 'ai-site-assistant' ) . ' ' . implode( ' ', $links ) . '</p>' : '',
			implode( ' · ', $meta )
		);
	}

	/**
	 * Rating.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_rating( $item ) {
		$r = (int) $item['rating'];
		return $r > 0 ? '👍' : ( $r < 0 ? '👎' : '–' );
	}

	/**
	 * Page the question was asked on.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_page( $item ) {
		if ( ! $item['page'] ) {
			return '–';
		}
		$path = wp_parse_url( $item['page'], PHP_URL_PATH );
		return sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $item['page'] ), esc_html( $path ? $path : '/' ) );
	}

	/**
	 * Date.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_date( $item ) {
		$ts = strtotime( $item['created_at'] . ' UTC' );
		return sprintf( '<span title="%s">%s</span>', esc_attr( wp_date( 'Y-m-d H:i', $ts ) ), esc_html( sprintf( /* translators: %s: time ago */ __( '%s ago', 'ai-site-assistant' ), human_time_diff( $ts ) ) ) );
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No conversations yet.', 'ai-site-assistant' );
	}
}
