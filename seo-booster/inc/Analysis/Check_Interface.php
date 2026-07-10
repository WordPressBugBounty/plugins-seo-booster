<?php

namespace Cleverplugins\SEOBooster\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for grouped SEO analysis checks.
 *
 * @since 7.1.0
 */
interface Check_Interface {

	/**
	 * Unique group name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Run checks and populate the result set.
	 *
	 * @param Content_Context $context Analysis context.
	 * @param Html_Document   $document Parsed HTML document.
	 * @param Result_Set      $results Result collector.
	 * @return void
	 */
	public function run( Content_Context $context, Html_Document $document, Result_Set $results );
}
