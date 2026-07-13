<?php

namespace Cleverplugins\SEOBooster\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical severity levels for SEO analysis results.
 *
 * @since 7.1.0
 */
class Severity {

	public const ERROR          = 'error';
	public const WARNING        = 'warning';
	public const OPPORTUNITY    = 'opportunity';
	public const GOOD           = 'good';
	public const NOT_APPLICABLE = 'not_applicable';

	/**
	 * Map analysis severity to database severity values.
	 *
	 * @param string $severity Analysis severity constant.
	 * @return string Database severity.
	 */
	public static function to_db( $severity ) {
		$mapping = array(
			self::ERROR          => 'critical',
			self::WARNING        => 'high',
			self::OPPORTUNITY    => 'opportunity',
			self::GOOD           => 'good',
			self::NOT_APPLICABLE => 'not_applicable',
			// Legacy values from older analyses.
			'critical'           => 'critical',
			'high'               => 'high',
			'medium'             => 'medium',
			'low'                => 'low',
			'improvement'        => 'medium',
		);

		return $mapping[ $severity ] ?? 'medium';
	}

	/**
	 * Map legacy issue severities from add_issue() calls.
	 *
	 * @param string $severity Raw severity passed to add_issue().
	 * @return string Normalized analysis severity.
	 */
	public static function normalize_issue_severity( $severity ) {
		if ( $severity === self::ERROR || $severity === 'critical' ) {
			return self::ERROR;
		}

		if ( $severity === self::WARNING || $severity === 'warning' || $severity === 'high' ) {
			return self::WARNING;
		}

		if ( $severity === self::OPPORTUNITY || $severity === 'low' || $severity === 'improvement' ) {
			return self::OPPORTUNITY;
		}

		return self::WARNING;
	}

	/**
	 * Score deduction percentage for a severity bucket.
	 *
	 * @param string $severity Analysis severity.
	 * @return float Deduction percentage.
	 */
	public static function score_deduction( $severity ) {
		switch ( $severity ) {
			case self::ERROR:
				return 5.0;
			case self::WARNING:
				return 2.5;
			case self::OPPORTUNITY:
				return 1.0;
			default:
				return 0.0;
		}
	}

	/**
	 * Maximum total deduction cap per bucket.
	 *
	 * @param string $severity Analysis severity.
	 * @return float Cap percentage.
	 */
	public static function score_deduction_cap( $severity ) {
		switch ( $severity ) {
			case self::ERROR:
				return 50.0;
			case self::WARNING:
				return 30.0;
			case self::OPPORTUNITY:
				return 20.0;
			default:
				return 0.0;
		}
	}

	/**
	 * DB severity values that count as actionable issues (score impact, issue totals).
	 *
	 * @return string[]
	 */
	public static function actionable_db_severities() {
		return array( 'critical', 'error', 'high', 'warning', 'medium' );
	}

	/**
	 * DB severity values for the opportunity/suggestion bucket.
	 *
	 * @return string[]
	 */
	public static function opportunity_db_severities() {
		return array( self::OPPORTUNITY, 'low' );
	}

	/**
	 * SQL IN (...) fragment for actionable severities.
	 *
	 * @param string $column Column expression, e.g. i.severity.
	 * @return string
	 */
	public static function sql_in_actionable( $column = 'i.severity' ) {
		$list = array_map( 'esc_sql', self::actionable_db_severities() );
		return $column . " IN ('" . implode( "','", $list ) . "')";
	}

	/**
	 * SQL IN (...) fragment for opportunity severities.
	 *
	 * @param string $column Column expression, e.g. i.severity.
	 * @return string
	 */
	public static function sql_in_opportunity( $column = 'i.severity' ) {
		$list = array_map( 'esc_sql', self::opportunity_db_severities() );
		return $column . " IN ('" . implode( "','", $list ) . "')";
	}

	/**
	 * Whether a DB severity should count toward issue totals.
	 *
	 * @param string $db_severity Database severity value.
	 * @return bool
	 */
	public static function counts_as_issue( $db_severity ) {
		return in_array( $db_severity, self::actionable_db_severities(), true );
	}

	/**
	 * Whether a DB severity is shown in the passed/good group.
	 *
	 * @param string $db_severity Database severity value.
	 * @return bool
	 */
	public static function is_good_bucket( $db_severity ) {
		return $db_severity === self::GOOD;
	}

	/**
	 * Whether a DB severity is shown in the not-applicable group.
	 *
	 * @param string $db_severity Database severity value.
	 * @return bool
	 */
	public static function is_not_applicable_bucket( $db_severity ) {
		return $db_severity === self::NOT_APPLICABLE;
	}

	/**
	 * Whether a DB severity is shown in the opportunity/suggestion group.
	 *
	 * @param string $db_severity Database severity value.
	 * @return bool
	 */
	public static function is_opportunity_bucket( $db_severity ) {
		return in_array( $db_severity, array( self::OPPORTUNITY, 'low' ), true );
	}
}
