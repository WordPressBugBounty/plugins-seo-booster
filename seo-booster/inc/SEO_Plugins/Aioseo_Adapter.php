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
		if ( function_exists( 'aioseo' ) ) {
			return true;
		}

		return class_exists( '\AIOSEO\Plugin\AIOSEO' ) || ( defined( 'AIOSEO_VERSION' ) && class_exists( 'AIOSEO' ) );
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
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public function read_post_seo_resolved( $post_id ) {
		$raw = $this->read_post_seo( $post_id );
		if ( ! function_exists( 'aioseo' ) ) {
			return $raw;
		}

		try {
			$aioseo = aioseo();
			if ( ! is_object( $aioseo ) || ! isset( $aioseo->meta ) || ! is_object( $aioseo->meta ) ) {
				return $raw;
			}

			$meta  = $aioseo->meta;
			$title = '';
			$desc  = '';

			if ( isset( $meta->title ) && is_object( $meta->title ) && method_exists( $meta->title, 'getTitle' ) ) {
				$title = (string) $meta->title->getTitle( (int) $post_id );
			}
			if ( isset( $meta->description ) && is_object( $meta->description ) && method_exists( $meta->description, 'getDescription' ) ) {
				$desc = (string) $meta->description->getDescription( (int) $post_id );
			}

			if ( $title !== '' || $desc !== '' ) {
				return array(
					'title'       => sanitize_text_field( $title !== '' ? $title : $raw['title'] ),
					'description' => sanitize_textarea_field( $desc !== '' ? $desc : $raw['description'] ),
				);
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Keep raw.
		}

		return $raw;
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

		return $this->parse_keyword_list( (string) $record->keywords );
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $keyword Keyword.
	 * @return void
	 */
	public function write_focus_keyword( $post_id, $keyword, $append_if_missing = false ) {
		$keywords = $this->resolve_aioseo_keywords_value(
			$this->read_focus_keywords( $post_id ),
			$keyword,
			$append_if_missing
		);

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
	 * @param int $term_id Term ID.
	 * @return array{title: string, description: string}
	 */
	public function read_term_seo_resolved( $term_id ) {
		return $this->read_term_seo( $term_id );
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

		return $this->parse_keyword_list( (string) $record->keywords );
	}

	/**
	 * @param int    $term_id           Term ID.
	 * @param string $keyword           Keyword.
	 * @param bool   $append_if_missing Prepend when missing.
	 * @return void
	 */
	public function write_focus_keyword_for_term( $term_id, $keyword, $append_if_missing = false ) {
		$keywords = $this->resolve_aioseo_keywords_value(
			$this->read_focus_keywords_for_term( $term_id ),
			$keyword,
			$append_if_missing
		);

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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column from hardcoded title|description allowlist; table from prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column from hardcoded title|description allowlist; table from prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix + hardcoded slug; values use placeholders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT term_id, title, description, keywords FROM {$table} WHERE term_id = %d LIMIT 1",
				(int) $term_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

	/**
	 * Build comma-separated keywords for AIOSEO, preserving siblings on append.
	 *
	 * @param string[] $existing          Existing keywords.
	 * @param string   $keyword           New primary keyword.
	 * @param bool     $append_if_missing When true, prepend if missing; keep list if already present.
	 * @return string
	 */
	private function resolve_aioseo_keywords_value( array $existing, $keyword, $append_if_missing ) {
		$keyword = sanitize_text_field( trim( (string) $keyword ) );
		if ( ! $append_if_missing ) {
			return $keyword;
		}

		if ( $keyword === '' ) {
			return implode( ', ', $existing );
		}

		if ( in_array( $keyword, $existing, true ) ) {
			return implode( ', ', $existing );
		}

		array_unshift( $existing, $keyword );

		return implode( ', ', array_values( array_unique( $existing ) ) );
	}

	/**
	 * @param string $raw Comma-separated keywords.
	 * @return string[]
	 */
	private function parse_keyword_list( $raw ) {
		$raw = trim( (string) $raw );
		if ( $raw === '' ) {
			return array();
		}

		$keywords = preg_split( '/\s*,\s*/', $raw );

		return array_values(
			array_filter(
				array_map(
					static function ( $keyword ) {
						return sanitize_text_field( trim( (string) $keyword ) );
					},
					is_array( $keywords ) ? $keywords : array()
				)
			)
		);
	}
}
