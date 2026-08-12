<?php
/**
 * Covers BDCT_Ajax::save_session() — server-side duration recomputation, capping, the
 * minimum-session-length skip, and the tracked-role/malformed-input rejections.
 */
class AjaxSaveSessionTest extends WP_Ajax_UnitTestCase {

	private $editor_id;

	public function set_up() {
		parent::set_up();

		update_option( 'bdct_track_roles', array( 'editor' ) );
		update_option( 'bdct_min_session_sec', 30 );

		$this->editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->editor_id );
		$_POST['nonce'] = wp_create_nonce( 'bdct_nonce' );
	}

	/**
	 * @return array Decoded JSON response from BDCT_Ajax::save_session().
	 */
	private function post_session( string $started, string $ended, $forged_duration_sec = null ): array {
		$_POST['started_at'] = $started;
		$_POST['ended_at']   = $ended;
		if ( null !== $forged_duration_sec ) {
			$_POST['duration_sec'] = $forged_duration_sec;
		}
		try {
			$this->_handleAjax( 'bdct_save_session' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected — wp_send_json_*() dies after echoing the response.
		}
		return json_decode( $this->_last_response, true );
	}

	private function session_row( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bdct_time_sessions WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	public function test_duration_is_recomputed_from_timestamps_not_the_client_value() {
		$started = gmdate( 'Y-m-d H:i:s', time() - 120 );
		$ended   = gmdate( 'Y-m-d H:i:s', time() );

		$res = $this->post_session( $started, $ended, 99999 ); // forged duration_sec in the POST body

		$this->assertTrue( $res['success'] );
		$row = $this->session_row( (int) $res['data']['id'] );
		$this->assertSame( 120, (int) $row['duration_sec'] );
	}

	public function test_duration_is_capped_at_86400_seconds() {
		$started = gmdate( 'Y-m-d H:i:s', time() - 200000 ); // well over 24h
		$ended   = gmdate( 'Y-m-d H:i:s', time() );

		$res = $this->post_session( $started, $ended );

		$this->assertTrue( $res['success'] );
		$row = $this->session_row( (int) $res['data']['id'] );
		$this->assertSame( 86400, (int) $row['duration_sec'] );
	}

	public function test_session_under_minimum_length_is_skipped_and_not_stored() {
		global $wpdb;
		$started = gmdate( 'Y-m-d H:i:s', time() - 5 ); // 5s, under the 30s minimum
		$ended   = gmdate( 'Y-m-d H:i:s', time() );

		$count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bdct_time_sessions" );
		$res          = $this->post_session( $started, $ended );
		$count_after  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bdct_time_sessions" );

		$this->assertTrue( $res['success'] );
		$this->assertTrue( $res['data']['skipped'] );
		$this->assertSame( $count_before, $count_after );
	}

	public function test_untracked_role_is_rejected_with_403() {
		update_option( 'bdct_track_roles', array( 'administrator' ) ); // editor no longer tracked
		$started = gmdate( 'Y-m-d H:i:s', time() - 60 );
		$ended   = gmdate( 'Y-m-d H:i:s', time() );

		$res = $this->post_session( $started, $ended );

		$this->assertFalse( $res['success'] );
		$this->assertSame( 'not_tracked', $res['data'] );
	}

	public function test_malformed_datetime_is_rejected_with_400() {
		$res = $this->post_session( 'not-a-date', 'also-not-a-date' );

		$this->assertFalse( $res['success'] );
		$this->assertSame( 'invalid_datetime', $res['data'] );
	}
}
