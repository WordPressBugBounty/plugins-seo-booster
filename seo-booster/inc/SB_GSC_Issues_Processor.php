<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SB_GSC_Issues_Processor
 *
 * Handles processing, storage, and management of Google Search Console URL inspection data.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.2.0
 */
class SB_GSC_Issues_Processor {

	/**
	 * Initialize the processor.
	 *
	 * @since 6.2.0
	 * @return void
	 */
	public static function init() {
		// No longer needed - GSC checks are integrated into SEO_Analysis
	}

	/**
	 * Categorize GSC issue as actionable, informational, or intentional.
	 *
	 * @since 6.2.0
	 * @param array $inspection_data Inspection data from database.
	 * @param array $structured_data_issues Structured data issues array.
	 * @return array Category information.
	 */
	public static function categorize_issue( $inspection_data, $structured_data_issues = array() ) {
		$category          = 'actionable';
		$reason            = '';
		$needs_ai_analysis = true;

		// Check for intentional noindex
		if ( isset( $inspection_data['indexing_state'] ) && $inspection_data['indexing_state'] === 'BLOCKED_BY_META_TAG' ) {
			// Check if this is intentional (user has marked similar pages as intentional)
			// For now, mark as actionable - user can mark as intentional later
			$category = 'actionable';
			$reason   = 'Page is blocked by meta tag - review if this is intentional';
		}

		// Canonical tags are always informational
		if ( isset( $inspection_data['canonical_issue'] ) ) {
			$category          = 'informational';
			$reason            = 'Canonical tag detected - review to ensure it\'s correct';
			$needs_ai_analysis = false;
		}

		// Structured data errors are actionable
		if ( ! empty( $structured_data_issues ) ) {
			$has_errors = false;
			foreach ( $structured_data_issues as $issue ) {
				if ( isset( $issue['severity'] ) && $issue['severity'] === 'ERROR' ) {
					$has_errors = true;
					break;
				}
			}
			if ( $has_errors ) {
				$category          = 'actionable';
				$reason            = 'Structured data errors detected';
				$needs_ai_analysis = true;
			}
		}

		// Coverage failures are actionable
		if ( isset( $inspection_data['coverage_state'] ) && $inspection_data['coverage_state'] === 'FAIL' ) {
			$category          = 'actionable';
			$reason            = 'Page coverage failed - requires attention';
			$needs_ai_analysis = true;
		}

		return array(
			'category'          => $category,
			'reason'            => $reason,
			'needs_ai_analysis' => $needs_ai_analysis,
		);
	}
}

SB_GSC_Issues_Processor::init();
