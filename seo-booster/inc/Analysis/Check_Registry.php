<?php

namespace Cleverplugins\SEOBooster\Analysis;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of analysis check groups and progressive steps.
 *
 * @since 7.1.0
 */
class Check_Registry {

	/**
	 * @var Check_Interface[]
	 */
	private $checks = array();

	/**
	 * @param Check_Interface $check Check group.
	 * @return void
	 */
	public function register( Check_Interface $check ) {
		$this->checks[ $check->get_name() ] = $check;
	}

	/**
	 * Run all registered checks.
	 *
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	public function run_all( Content_Context $context, Html_Document $document, Result_Set $results ) {
		foreach ( $this->checks as $check ) {
			$check->run( $context, $document, $results );
		}
	}

	/**
	 * Run checks for a progressive analyze_step key.
	 *
	 * @param string          $step Step name.
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @return string|null Next step or null when done.
	 */
	public function run_step( $step, Content_Context $context, Html_Document $document, Result_Set $results ) {
		switch ( $step ) {
			case 'headers':
				$this->run_named( array( 'meta', 'content' ), $context, $document, $results );
				return 'links';

			case 'links':
				$this->run_named( array( 'links' ), $context, $document, $results );
				return 'content';

			case 'content':
				$this->run_named( array( 'content', 'images', 'duplicates' ), $context, $document, $results );
				return 'gsc';

			case 'gsc':
				$this->run_named( array( 'gsc' ), $context, $document, $results );
				return 'cache';

			default:
				return null;
		}
	}

	/**
	 * @param string[]        $names Check group names.
	 * @param Content_Context $context Context.
	 * @param Html_Document   $document Document.
	 * @param Result_Set      $results Results.
	 * @return void
	 */
	private function run_named( array $names, Content_Context $context, Html_Document $document, Result_Set $results ) {
		foreach ( $names as $name ) {
			if ( isset( $this->checks[ $name ] ) ) {
				$this->checks[ $name ]->run( $context, $document, $results );
			}
		}
	}
}
