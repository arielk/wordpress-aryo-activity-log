<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AAL_Privacy
 * @since 2.1.0
 */
class AAL_Privacy {

	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_action( 'admin_init', [ $this, 'add_privacy_policy_content' ] );
	}

	public function register_exporter( $exporters ) {
		$exporters['activity-log'] = array(
			'exporter_friendly_name' => __( 'Activity Log Plugin' ),
			'callback' => [ $this, 'wp_exporter' ],
		);
		return $exporters;
	}

	public function wp_exporter( $email_address, $page = 1 ) {
		$number = 500; // Limit us to avoid timing out
		$page = (int) $page;

		$export_items = array();

		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return [
				'data' => [],
				'done' => true,
			];
		}

		global $wpdb;

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT SQL_CALC_FOUND_ROWS * FROM $wpdb->activity_log
			WHERE `user_id` = %d
			ORDER BY `hist_time` ASC
			LIMIT %d, %d;",
			$user->ID, $page, $number
		) );

		$found_rows = $wpdb->get_var( 'SELECT FOUND_ROWS();' );

		$group_id = 'activity-log';
		$group_label = __( 'Activity Log' );

		foreach ( $items as $item ) {
			$item_id = "activity-log-{$item->histid}";
			$created = date( 'Y-m-d H:i:s', $item->hist_time );
			$data = [
				[
					'name' => __( 'Time' ),
					'value' => get_date_from_gmt( $created, 'Y/m/d h:i:s A' ),
				],
				[
					'name' => __( 'Action' ),
					'value' => $this->get_action_label( $item->action ),
				],
				[
					'name' => __( 'Object Type' ),
					'value' => $item->object_type,
				],
				[
					'name' => __( 'Object Subtype' ),
					'value' => $item->object_subtype,
				],
				[
					'name' => __( 'Description' ),
					'value' => $item->object_name,
				],
				[
					'name' => __( 'IP' ),
					'value' => $item->hist_ip,
				],
			];

			$export_items[] = [
				'group_id' => $group_id,
				'group_label' => $group_label,
				'item_id' => $item_id,
				'data' => $data,
			];
		} // End foreach().

		// Tell core if we have more comments to work on still
		$done = $found_rows < $number;
		return [
			'data' => $export_items,
			'done' => $done,
		];
	}

	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf( __( 'If you are a registered user, we save your content activity like create/update/delete posts and comments.' ) );

		wp_add_privacy_policy_content(
			__( 'Activity Log', '' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	public function get_action_label( $action ) {
		return ucwords( str_replace( '_', ' ', __( $action, 'aryo-activity-log' ) ) );
	}
}
