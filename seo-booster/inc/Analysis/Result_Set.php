<?php

namespace Cleverplugins\SEOBooster\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects and deduplicates SEO analysis results.
 *
 * @since 7.1.0
 */
class Result_Set {

	/**
	 * @var array<string, array>
	 */
	private $items = array();

	/**
	 * @var array<string, mixed>
	 */
	private $meta = array();

	/**
	 * @var int|null
	 */
	private $score = null;

	/**
	 * Add an error-level issue.
	 *
	 * @param string     $key Issue key.
	 * @param string     $message Message.
	 * @param array|null $extra_data Extra data.
	 * @return void
	 */
	public function add_error( $key, $message, $extra_data = null ) {
		$this->add( $key, $message, Severity::ERROR, $extra_data );
	}

	/**
	 * Add a warning-level issue.
	 *
	 * @param string     $key Issue key.
	 * @param string     $message Message.
	 * @param array|null $extra_data Extra data.
	 * @return void
	 */
	public function add_warning( $key, $message, $extra_data = null ) {
		$this->add( $key, $message, Severity::WARNING, $extra_data );
	}

	/**
	 * Add an opportunity/suggestion (no score penalty).
	 *
	 * @param string     $key Issue key.
	 * @param string     $message Message.
	 * @param array|null $extra_data Extra data.
	 * @return void
	 */
	public function add_opportunity( $key, $message, $extra_data = null ) {
		$this->add( $key, $message, Severity::OPPORTUNITY, $extra_data );
	}

	/**
	 * Add a passed/good check.
	 *
	 * @param string     $key Issue key.
	 * @param string     $message Message.
	 * @param array|null $extra_data Extra data (e.g. ai_readiness tag).
	 * @return void
	 */
	public function add_good( $key, $message, $extra_data = null ) {
		$this->add( $key, $message, Severity::GOOD, $extra_data );
	}

	/**
	 * Add a not-applicable check (skipped, e.g. GSC not connected).
	 *
	 * @param string $key Issue key.
	 * @param string $message Message.
	 * @return void
	 */
	public function add_not_applicable( $key, $message ) {
		$this->add( $key, $message, Severity::NOT_APPLICABLE, null );
	}

	/**
	 * Backward-compatible alias for warnings stored as improvements.
	 *
	 * @param string     $key Issue key.
	 * @param string     $message Message.
	 * @param array|null $extra_data Extra data.
	 * @return void
	 */
	public function add_improvement( $key, $message, $extra_data = null ) {
		$this->add_opportunity( $key, $message, $extra_data );
	}

	/**
	 * Backward-compatible add_issue with severity parameter.
	 *
	 * @param string     $key Issue key.
	 * @param string     $message Message.
	 * @param string     $severity Raw severity.
	 * @param array|null $extra_data Extra data.
	 * @return void
	 */
	public function add_issue( $key, $message, $severity = Severity::WARNING, $extra_data = null ) {
		$normalized = Severity::normalize_issue_severity( $severity );

		if ( $normalized === Severity::ERROR ) {
			$this->add_error( $key, $message, $extra_data );
			return;
		}

		if ( $normalized === Severity::OPPORTUNITY ) {
			$this->add_opportunity( $key, $message, $extra_data );
			return;
		}

		$this->add_warning( $key, $message, $extra_data );
	}

	/**
	 * Store arbitrary metadata on the result set.
	 *
	 * @param string $key Meta key.
	 * @param mixed  $value Meta value.
	 * @return void
	 */
	public function set_meta( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	/**
	 * Get metadata value.
	 *
	 * @param string $key Meta key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_meta( $key, $default = null ) {
		return $this->meta[ $key ] ?? $default;
	}

	/**
	 * Calculate and store score from collected items.
	 *
	 * @return void
	 */
	public function calculate_score() {
		$error_count       = 0;
		$warning_count     = 0;
		$opportunity_count = 0;

		foreach ( $this->items as $item ) {
			switch ( $item['severity'] ) {
				case Severity::ERROR:
					++$error_count;
					break;
				case Severity::WARNING:
					++$warning_count;
					break;
				case Severity::OPPORTUNITY:
					++$opportunity_count;
					break;
			}
		}

		$error_deduction       = min( Severity::score_deduction_cap( Severity::ERROR ), $error_count * Severity::score_deduction( Severity::ERROR ) );
		$warning_deduction     = min( Severity::score_deduction_cap( Severity::WARNING ), $warning_count * Severity::score_deduction( Severity::WARNING ) );
		$opportunity_deduction = min( Severity::score_deduction_cap( Severity::OPPORTUNITY ), $opportunity_count * Severity::score_deduction( Severity::OPPORTUNITY ) );

		$score       = 100 - $error_deduction - $warning_deduction - $opportunity_deduction;
		$this->score = max( 0, min( 100, (int) round( $score ) ) );
	}

	/**
	 * Export to legacy array shape used by SEO_Issues_Manager and metabox JS.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array() {
		$issues         = array();
		$improvements   = array();
		$opportunities  = array();
		$good           = array();
		$not_applicable = array();

		foreach ( $this->items as $item ) {
			$row = array(
				'key'      => $item['key'],
				'message'  => $item['message'],
				'severity' => $item['severity'],
			);

			if ( ! empty( $item['extra_data'] ) ) {
				$row['extra_data'] = $item['extra_data'];
			}

			switch ( $item['severity'] ) {
				case Severity::ERROR:
				case Severity::WARNING:
					$issues[] = $row;
					break;
				case Severity::OPPORTUNITY:
					$opportunities[] = $row;
					$improvements[]  = $row;
					break;
				case Severity::GOOD:
					$good[] = $row;
					break;
				case Severity::NOT_APPLICABLE:
					$not_applicable[] = $row;
					break;
			}
		}

		if ( null === $this->score ) {
			$this->calculate_score();
		}

		$output = array(
			'score'          => $this->score,
			'issues'         => $issues,
			'improvements'   => $improvements,
			'opportunities'  => $opportunities,
			'good'           => $good,
			'not_applicable' => $not_applicable,
		);

		if ( ! empty( $this->meta ) ) {
			$output['metadata'] = $this->meta;
		}

		return $output;
	}

	/**
	 * Add or replace an item by key.
	 *
	 * @param string     $key Issue key.
	 * @param string     $message Message.
	 * @param string     $severity Severity constant.
	 * @param array|null $extra_data Extra data.
	 * @return void
	 */
	private function add( $key, $message, $severity, $extra_data ) {
		if ( empty( $key ) ) {
			return;
		}

		$item = array(
			'key'      => $key,
			'message'  => $message,
			'severity' => $severity,
		);

		if ( null !== $extra_data ) {
			$item['extra_data'] = $extra_data;
		}

		$this->items[ $key ] = $item;
	}
}
