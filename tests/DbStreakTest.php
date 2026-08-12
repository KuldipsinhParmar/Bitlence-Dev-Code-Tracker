<?php
/**
 * Covers BDCT_DB::get_streak() — the most date-math-heavy, regression-prone logic in the plugin.
 */
class DbStreakTest extends WP_UnitTestCase {

	private function insert_summary( int $user_id, string $date, int $total_sec ): void {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'bdct_daily_summary',
			array(
				'user_id'      => $user_id,
				'summary_date' => $date,
				'total_sec'    => $total_sec,
			),
			array( '%d', '%s', '%d' )
		);
	}

	private function days_ago( int $n ): string {
		$today = current_time( 'Y-m-d' );
		return wp_date( 'Y-m-d', strtotime( "-{$n} days", strtotime( $today ) ) );
	}

	public function test_no_activity_returns_zero() {
		$user_id = self::factory()->user->create();
		$this->assertSame( 0, BDCT_DB::get_streak( $user_id ) );
	}

	public function test_five_consecutive_days_ending_today() {
		$user_id = self::factory()->user->create();
		for ( $i = 0; $i < 5; $i++ ) {
			$this->insert_summary( $user_id, $this->days_ago( $i ), 600 );
		}
		$this->assertSame( 5, BDCT_DB::get_streak( $user_id ) );
	}

	public function test_streak_counts_from_yesterday_when_today_has_no_row() {
		$user_id = self::factory()->user->create();
		for ( $i = 1; $i <= 3; $i++ ) {
			$this->insert_summary( $user_id, $this->days_ago( $i ), 600 );
		}
		$this->assertSame( 3, BDCT_DB::get_streak( $user_id ) );
	}

	public function test_gap_of_two_or_more_days_breaks_the_streak() {
		$user_id = self::factory()->user->create();
		// Most recent activity is two days ago — neither "today" nor "yesterday".
		$this->insert_summary( $user_id, $this->days_ago( 2 ), 600 );
		$this->insert_summary( $user_id, $this->days_ago( 3 ), 600 );
		$this->assertSame( 0, BDCT_DB::get_streak( $user_id ) );
	}

	public function test_zero_second_day_does_not_count_as_active() {
		$user_id = self::factory()->user->create();
		$this->insert_summary( $user_id, $this->days_ago( 0 ), 0 );   // today, but no real activity
		$this->insert_summary( $user_id, $this->days_ago( 1 ), 600 ); // yesterday, real activity
		$this->insert_summary( $user_id, $this->days_ago( 2 ), 600 ); // day before, real activity
		$this->assertSame( 2, BDCT_DB::get_streak( $user_id ) );
	}

	public function test_a_broken_chain_stops_counting_at_the_gap() {
		$user_id = self::factory()->user->create();
		$this->insert_summary( $user_id, $this->days_ago( 0 ), 600 );
		$this->insert_summary( $user_id, $this->days_ago( 1 ), 600 );
		// gap at days_ago(2) — no row inserted
		$this->insert_summary( $user_id, $this->days_ago( 3 ), 600 );
		$this->assertSame( 2, BDCT_DB::get_streak( $user_id ) );
	}

	public function test_streak_is_isolated_per_user() {
		$user_a = self::factory()->user->create();
		$user_b = self::factory()->user->create();
		$this->insert_summary( $user_a, $this->days_ago( 0 ), 600 );
		$this->assertSame( 1, BDCT_DB::get_streak( $user_a ) );
		$this->assertSame( 0, BDCT_DB::get_streak( $user_b ) );
	}
}
