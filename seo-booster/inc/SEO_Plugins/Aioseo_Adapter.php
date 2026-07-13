<?php

namespace Cleverplugins\SEOBooster\SEO_Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All in One SEO v4+ adapter (custom aioseo_posts table).
 *
 * @package Cleverplugins\SEOBooster\SEO_Plugins
 * @since 7.2.5
 */
class Aioseo_Adapter implements SEO_Plugin_Adapter_Interface {

	/**
	 * @return string
	 */
	public function get_slug() {
		return 'aioseo';
	}

	/**
	 * @return string
	 */
	public function get_label() {
		return 'All in One SEO';
	}

	/**
	 * @return string
	 */
	public function get_plugin_file() {
		return 'all-in-one-seo-pack/all_in_one_seo_pack.php';
	}

	/**
	 * @return bool
	 */
	public function is_active() {
		return class_exists( 'AIOSEO' ) && function_exists( 'aioseo' );
	}

	/**
	 * @return bool
	 */
	public function supports_bulk_write() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function supports_focus_keyword() {
		return true;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public function read_post_seo( $post_id ) {
		$record = $this->get_aioseo_post( $post_id );
		if ( ! $record ) {
			return array(
				'title'       => '',
				'description' => '',
			);
		}

		return array(
			'title'       => sanitize_text_field( (string) ( $record->title ?? '' ) ),
			'description' => sanitize_textarea_field( (string) ( $record->description ?? '' ) ),
		);
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public function write_post_title( $post_id, $title ) {
		$this->update_aioseo_post_fields(
			$post_id,
			array(
				'title' => sanitize_text_field( $title ),
			)
		);
	}

	/**
	 * @param int    $post_id     Post ID.
	 * @param string $description Description.
	 * @return void
	 */
	public function write_post_description( $post_id, $description ) {
		$this->update_aioseo_post_fields(
			$post_id,
			array(
				'description' => sanitize_textarea_field( $description ),
			)
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public function read_focus_keywords( $post_id ) {
		$record = $this->get_aioseo_post( $post_id );
		if ( ! $record || empty( $record->keywords ) ) {
			return array();
		}

		$keywords = explode( ',', (string) $record->keywords );

		return array_values(
			array_filter(
				array_map(
					static function ( $keyword ) {
						return sanitize_text_field( trim( $keyword ) );
					},
					$keywords
				)
			)
		);
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $keyword Keyword.
	 * @return void
	 */
	public function write_focus_keyword( $post_id, $keyword, $append_if_missing = false ) {
		$keywords = sanitize_text_field( $keyword );
		if ( $append_if_missing ) {
			$existing = $this->read_focus_keywords( $post_id );
			if ( ! empty( $existing ) && ! in_array( trim( $keyword ), $existing, true ) ) {
				array_unshift( $existing, trim( $keyword ) );
				$keywords = implode( ', ', array_unique( $existing ) );
			}
		}

		$this->update_aioseo_post_fields(
			$post_id,
			array(
				'keywords' => $keywords,
			)
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_focus_keyword( $post_id ) {
		$this->update_aioseo_post_fields(
			$post_id,
			array(
				'keywords' => '',
			)
		);
	}

	/**
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo( $term_id ) {
		$record = $this->get_aioseo_term( $term_id );
		if ( ! $record ) {
			return array(
				'title'       => '',
				'description' => '',
			);
		}

		return array(
			'title'       => sanitize_text_field( (string) ( $record->title ?? '' ) ),
			'description' => sanitize_textarea_field( (string) ( $record->description ?? '' ) ),
		);
	}

	/**
	 * @param int    $term_id Term ID.
	 * @param string $title   Title.
	 * @return void
	 */
	public function write_term_title( $term_id, $title ) {
		$this->update_aioseo_term_fields(
			$term_id,
			array(
				'title' => sanitize_text_field( $title ),
			)
		);
	}

	/**
	 * @param int    $term_id     Term ID.
	 * @param string $description Description.
	 * @return void
	 */
	public function write_term_description( $term_id, $description ) {
		$this->update_aioseo_term_fields(
			$term_id,
			array(
				'description' => sanitize_textarea_field( $description ),
			)
		);
	}

	/**
	 * @param int $term_id Term ID.
	 * @return string[]
	 */
	public function read_focus_keywords_for_term( $term_id ) {
		$record = $this->get_aioseo_term( $term_id );
		if ( ! $record || empty( $record->keywords ) ) {
			return array();
		}

		$keywords = explode( ',', (string) $record->keywords );

		return array_values(
			array_filter(
				array_map(
					static function ( $keyword ) {
						return sanitize_text_field( trim( $keyword ) );
					},
					$keywords
				)
			)
		);
	}

	/**
	 * @param int    $term_id           Term ID.
	 * @param string $keyword           Keyword.
	 * @param bool   $append_if_missing Prepend when missing.
	 * @return void
	 */
	public function write_focus_keyword_for_term( $term_id, $keyword, $append_if_missing = false ) {
		$keywords = sanitize_text_field( $keyword );
		if ( $append_if_missing ) {
			$existing = $this->read_focus_keywords_for_term( $term_id );
			if ( ! empty( $existing ) && ! in_array( trim( $keyword ), $existing, true ) ) {
				array_unshift( $existing, trim( $keyword ) );
				$keywords = implode( ', ', array_unique( $existing ) );
			}
		}

		$this->update_aioseo_term_fields(
			$term_id,
			array(
				'keywords' => $keywords,
			)
		);
	}

	/**
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function delete_focus_keyword_for_term( $term_id ) {
		$this->update_aioseo_term_fields(
			$term_id,
			array(
				'keywords' => '',
			)
		);
	}

	/**
	 * @param string $field_type title|description|both
	 * @return array{}
	 */
	public function get_term_meta_keys( $field_type = 'both' ) {
		return array();
	}

	/**
	 * AIOSEO stores data in a custom table; postmeta keys are not used for reads/writes.
	 *
	 * @param string $field_type title|description|both
	 * @return array{}
	 */
	public function get_meta_keys( $field_type = 'both' ) {
		return array();
	}

	/**
	 * @return string|null
	 */
	public function get_focus_keyword_meta_key() {
		return null;
	}

	/**
	 * @return array<string, string>
	 */
	public function get_editor_field_selectors() {
		return array(
			'title'         => '#aioseo-title',
			'description'   => '#aioseo-description',
			'focus_keyword' => '#aioseo-keyphrase',
		);
	}

	/**
	 * @param string $field      title|description
	 * @param string $value      Value.
	 * @param int    $exclude_id Exclude post ID.
	 * @return object[]
	 */
	public function find_duplicate_posts( $field, $value, $exclude_id ) {
		global $wpdb;

		if ( $value === '' || ! in_array( $field, array( 'title', 'description' ), true ) ) {
			return array();
		}

		$column = $field === 'title' ? 'title' : 'description';
		$table  = $wpdb->prefix . 'aioseo_posts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( $table_exists !== $table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, 'post_seo' AS type
				FROM {$wpdb->posts} p
				INNER JOIN {$table} ap ON p.ID = ap.post_id
				WHERE ap.{$column} = %s
				AND p.ID != %d
				AND p.post_status = 'publish'",
				$value,
				$exclude_id
			)
		);
	}

	public function find_duplicate_terms( $field, $value, $exclude_id ) {
		global $wpdb;

		if ( $value === '' || ! in_array( $field, array( 'title', 'description' ), true ) ) {
			return array();
		}

		$column = $field === 'title' ? 'title' : 'description';
		$table  = $wpdb->prefix . 'aioseo_terms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table
			)
		);

		if ( $table_exists !== $table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, tt.taxonomy, 'term_seo' AS type
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				INNER JOIN {$table} at ON t.term_id = at.term_id
				WHERE at.{$column} = %s
				AND t.term_id != %d",
				$value,
				$exclude_id
			)
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	private function get_aioseo_post( $post_id ) {
		if ( ! $this->is_active() ) {
			return null;
		}

		if ( class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
			return \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );
		}

		if ( function_exists( 'aioseo' ) ) {
			$aioseo = aioseo();
			if ( is_object( $aioseo ) && isset( $aioseo->helpers ) && method_exists( $aioseo->helpers, 'getPost' ) ) {
				return $aioseo->helpers->getPost( $post_id );
			}
		}

		return null;
	}

	/**
	 * @param int $term_id Term ID.
	 * @return object|null
	 */
	private function get_aioseo_term( $term_id ) {
		if ( ! $this->is_active() ) {
			return null;
		}

		if ( class_exists( '\AIOSEO\Plugin\Pro\Models\Term' ) ) {
			return \AIOSEO\Plugin\Pro\Models\Term::getTerm( $term_id );
		}

		if ( class_exists( '\AIOSEO\Plugin\Common\Models\Term' ) ) {
			return \AIOSEO\Plugin\Common\Models\Term::getTerm( $term_id );
		}

		return $this->get_aioseo_term_row( $term_id );
	}

	/**
	 * Read a term row from aioseo_terms when no Term model is available.
	 *
	 * @param int $term_id Term ID.
	 * @return object|null
	 */
	private function get_aioseo_term_row( $term_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_terms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT term_id, title, description, keywords FROM {$table} WHERE term_id = %d LIMIT 1",
				(int) $term_id
			)
		);
	}

	/**
	 * Upsert term SEO fields into aioseo_terms when no Term model is available.
	 *
	 * @param int                  $term_id Term ID.
	 * @param array<string, mixed> $fields  Fields to update.
	 * @return void
	 */
	private function upsert_aioseo_term_row( $term_id, array $fields ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aioseo_terms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$term_id = (int) $term_id;
		$row     = $this->get_aioseo_term_row( $term_id );
		$data    = array(
			'term_id' => $term_id,
		);

		foreach ( array( 'title', 'description', 'keywords' ) as $column ) {
			if ( array_key_exists( $column, $fields ) ) {
				$data[ $column ] = $fields[ $column ];
			} elseif ( $row && isset( $row->{$column} ) ) {
				$data[ $column ] = $row->{$column};
			} else {
				$data[ $column ] = '';
			}
		}

		if ( $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'title'       => $data['title'],
					'description' => $data['description'],
					'keywords'    => $data['keywords'],
				),
				array( 'term_id' => $term_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$table,
			array(
				'term_id'     => $term_id,
				'title'       => $data['title'],
				'description' => $data['description'],
				'keywords'    => $data['keywords'],
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $fields  Fields to update.
	 * @return void
	 */
	private function update_aioseo_post_fields( $post_id, array $fields ) {
		$record = $this->get_aioseo_post( $post_id );
		if ( ! $record ) {
			if ( class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
				$record          = new \AIOSEO\Plugin\Common\Models\Post();
				$record->post_id = (int) $post_id;
			} else {
				return;
			}
		}

		foreach ( $fields as $key => $value ) {
			$record->{$key} = $value;
		}

		if ( method_exists( $record, 'save' ) ) {
			$record->save();
		}
	}

	/**
	 * @param int                  $term_id Term ID.
	 * @param array<string, mixed> $fields  Fields to update.
	 * @return void
	 */
	private function update_aioseo_term_fields( $term_id, array $fields ) {
		$record = $this->get_aioseo_term( $term_id );

		if ( $record && method_exists( $record, 'save' ) ) {
			foreach ( $fields as $key => $value ) {
				$record->{$key} = $value;
			}

			$record->save();
			return;
		}

		if ( class_exists( '\AIOSEO\Plugin\Pro\Models\Term' ) || class_exists( '\AIOSEO\Plugin\Common\Models\Term' ) ) {
			$model_class = class_exists( '\AIOSEO\Plugin\Pro\Models\Term' )
				? '\AIOSEO\Plugin\Pro\Models\Term'
				: '\AIOSEO\Plugin\Common\Models\Term';

			$record          = new $model_class();
			$record->term_id = (int) $term_id;

			foreach ( $fields as $key => $value ) {
				$record->{$key} = $value;
			}

			if ( method_exists( $record, 'save' ) ) {
				$record->save();
			}

			return;
		}

		$this->upsert_aioseo_term_row( $term_id, $fields );
	}

	/**
	 * @return string|null
	 */
	public function get_focus_keyword_term_meta_key() {
		return null;
	}
}
