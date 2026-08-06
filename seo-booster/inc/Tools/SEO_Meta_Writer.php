<?php

namespace Cleverplugins\SEOBooster\Tools;

use Cleverplugins\SEOBooster\SEO_Plugin_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read/write SEO title and description via the SEO plugin adapter registry.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
class SEO_Meta_Writer {

	const BACKUP_META_KEY = '_sb_bulk_meta_backup';

	const FOCUSKW_BACKUP_META_KEY = '_sb_bulk_focuskw_backup';

	const FOCUSKW_LAST_BATCH_USER_META = 'sb_tools_focuskw_last_batch_ids';

	/** Maximum SEO title length (characters). */
	const MAX_TITLE_LENGTH = 80;

	/** Maximum meta description length (characters). */
	const MAX_DESCRIPTION_LENGTH = 175;

	/**
	 * Supported plugin slug or null.
	 *
	 * @return string|null yoast|rankmath|aioseo|seopress|seoframework|null
	 */
	public static function get_target() {
		if ( ! SEO_Plugin_Registry::supports_bulk_write() ) {
			return null;
		}

		return SEO_Plugin_Registry::get_active_slug();
	}

	/**
	 * Human-readable plugin name for notices.
	 *
	 * @return string
	 */
	public static function get_target_label() {
		return SEO_Plugin_Registry::get_active_label();
	}

	/**
	 * @return array{title_key: string, description_key: string}|array{}
	 */
	public static function get_meta_keys() {
		if ( ! self::get_target() ) {
			return array();
		}

		return SEO_Plugin_Registry::get_meta_keys( 'both' );
	}

	/**
	 * Read current SEO meta for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{title: string, description: string}
	 */
	public static function read( $post_id ) {
		return SEO_Plugin_Registry::read_post_seo( $post_id );
	}

	/**
	 * Store current values before bulk write.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $batch_id Batch ID.
	 * @return void
	 */
	public static function backup( $post_id, $batch_id = '' ) {
		$current = self::read( $post_id );

		update_post_meta(
			$post_id,
			self::BACKUP_META_KEY,
			array(
				'title'       => $current['title'],
				'description' => $current['description'],
				'timestamp'   => time(),
				'batch_id'    => sanitize_text_field( $batch_id ),
			)
		);
	}

	/**
	 * Restore backed-up values for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function restore_post( $post_id ) {
		$backup = get_post_meta( $post_id, self::BACKUP_META_KEY, true );
		if ( ! is_array( $backup ) ) {
			return false;
		}

		if ( ! self::get_target() ) {
			return false;
		}

		if ( array_key_exists( 'title', $backup ) ) {
			SEO_Plugin_Registry::write_post_title( $post_id, sanitize_text_field( (string) $backup['title'] ) );
		}
		if ( array_key_exists( 'description', $backup ) ) {
			SEO_Plugin_Registry::write_post_description( $post_id, sanitize_textarea_field( (string) $backup['description'] ) );
		}

		delete_post_meta( $post_id, self::BACKUP_META_KEY );

		return true;
	}

	/**
	 * Restore all posts from the user's last bulk meta batch.
	 *
	 * @return array{restored: int, skipped: int}
	 */
	public static function restore_last_batch() {
		$post_ids = get_user_meta( get_current_user_id(), 'sb_tools_meta_last_batch_ids', true );
		if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
			return array(
				'restored' => 0,
				'skipped'  => 0,
			);
		}

		$restored = 0;
		$skipped  = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				++$skipped;
				continue;
			}
			if ( self::restore_post( $post_id ) ) {
				++$restored;
			} else {
				++$skipped;
			}
		}

		delete_user_meta( get_current_user_id(), 'sb_tools_meta_last_batch_ids' );
		delete_user_meta( get_current_user_id(), 'sb_tools_meta_last_batch_at' );

		return array(
			'restored' => $restored,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Persist last batch post IDs for revert.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return void
	 */
	public static function remember_last_batch( array $post_ids ) {
		update_user_meta( get_current_user_id(), 'sb_tools_meta_last_batch_ids', array_values( array_map( 'intval', $post_ids ) ) );
		update_user_meta( get_current_user_id(), 'sb_tools_meta_last_batch_at', time() );
	}

	/**
	 * Whether the user has a revertable batch.
	 *
	 * @return bool
	 */
	public static function has_revertable_batch() {
		$ids = get_user_meta( get_current_user_id(), 'sb_tools_meta_last_batch_ids', true );
		return is_array( $ids ) && ! empty( $ids );
	}

	/**
	 * Fields that can be written given apply settings and overwrite mode.
	 *
	 * @param int   $post_id      Post ID.
	 * @param array $apply_fields title, description booleans.
	 * @param bool  $overwrite    Overwrite non-empty values.
	 * @return array<string, true> Keys: title and/or description.
	 */
	public static function get_writable_fields( $post_id, array $apply_fields, $overwrite = false ) {
		$current  = static::read( $post_id );
		$writable = array();

		if ( ! empty( $apply_fields['title'] ) ) {
			if ( $overwrite || trim( $current['title'] ) === '' ) {
				$writable['title'] = true;
			}
		}

		if ( ! empty( $apply_fields['description'] ) ) {
			if ( $overwrite || trim( $current['description'] ) === '' ) {
				$writable['description'] = true;
			}
		}

		return $writable;
	}

	/**
	 * Truncate SEO title to a safe maximum length at a word boundary.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	public static function truncate_title( $text, $max = self::MAX_TITLE_LENGTH ) {
		return self::truncate_at_word_boundary( $text, $max );
	}

	/**
	 * Truncate meta description to a safe maximum length at a word boundary.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	public static function truncate_description( $text, $max = self::MAX_DESCRIPTION_LENGTH ) {
		return self::truncate_at_word_boundary( $text, $max );
	}

	/**
	 * @param string $text Text.
	 * @param int    $max  Max characters.
	 * @return string
	 */
	private static function truncate_at_word_boundary( $text, $max ) {
		$text = trim( (string) $text );
		if ( $text === '' ) {
			return '';
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) && function_exists( 'mb_strrpos' ) ) {
			if ( mb_strlen( $text ) <= $max ) {
				return $text;
			}

			$truncated  = mb_substr( $text, 0, $max );
			$last_space = mb_strrpos( $truncated, ' ' );
			if ( $last_space !== false && $last_space > (int) ( $max * 0.6 ) ) {
				$truncated = mb_substr( $truncated, 0, $last_space );
			}

			return rtrim( $truncated, '.,;:!?-' );
		}

		if ( strlen( $text ) <= $max ) {
			return $text;
		}

		$truncated  = substr( $text, 0, $max );
		$last_space = strrpos( $truncated, ' ' );
		if ( $last_space !== false && $last_space > (int) ( $max * 0.6 ) ) {
			$truncated = substr( $truncated, 0, $last_space );
		}

		return rtrim( $truncated, '.,;:!?-' );
	}

	/**
	 * Apply generated values to SEO plugin fields.
	 *
	 * @param int   $post_id        Post ID.
	 * @param array $values         title, description keys.
	 * @param array $apply_fields   title, description booleans.
	 * @param bool  $overwrite      Overwrite non-empty values.
	 * @return array Values written (after).
	 * @throws \Exception When nothing could be applied.
	 */
	public static function write( $post_id, array $values, array $apply_fields, $overwrite = false ) {
		if ( ! self::get_target() ) {
			throw new \Exception( esc_html__( 'No supported SEO plugin is active.', 'seo-booster' ) );
		}

		$before = self::read( $post_id );
		$after  = array();

		if ( ! empty( $apply_fields['title'] ) && isset( $values['title'] ) && $values['title'] !== '' ) {
			$current = trim( $before['title'] );
			if ( $overwrite || $current === '' ) {
				$title = sanitize_text_field( self::truncate_title( $values['title'] ) );
				SEO_Plugin_Registry::write_post_title( $post_id, $title );
				$after['title'] = $title;
			}
		}

		if ( ! empty( $apply_fields['description'] ) && isset( $values['description'] ) && $values['description'] !== '' ) {
			$current = trim( $before['description'] );
			if ( $overwrite || $current === '' ) {
				$description = sanitize_textarea_field( self::truncate_description( $values['description'] ) );
				SEO_Plugin_Registry::write_post_description( $post_id, $description );
				$after['description'] = $description;
			}
		}

		if ( empty( $after ) ) {
			throw new \Exception( esc_html__( 'No metadata was saved for this item. Enable overwrite or choose empty fields.', 'seo-booster' ) );
		}

		return $after;
	}

	/**
	 * Focus keyword meta key for the active SEO plugin (postmeta-backed plugins only).
	 *
	 * @return string|null
	 */
	public static function get_focus_keyword_meta_key() {
		return SEO_Plugin_Registry::get_focus_keyword_meta_key();
	}

	/**
	 * Read focus keyword for a post (first keyword only for multi-value plugins).
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function read_focus_keyword( $post_id ) {
		$keywords = SEO_Plugin_Registry::read_focus_keywords( $post_id );
		if ( empty( $keywords ) || ! is_array( $keywords ) ) {
			return '';
		}

		return trim( (string) $keywords[0] );
	}

	/**
	 * Store focus keyword before bulk write.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $batch_id Batch ID.
	 * @return void
	 */
	public static function backup_focus_keyword( $post_id, $batch_id = '' ) {
		update_post_meta(
			$post_id,
			self::FOCUSKW_BACKUP_META_KEY,
			array(
				'focus_keyword' => self::read_focus_keyword( $post_id ),
				'timestamp'     => time(),
				'batch_id'      => sanitize_text_field( $batch_id ),
			)
		);
	}

	/**
	 * Restore backed-up focus keyword for one post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function restore_focus_keyword_post( $post_id ) {
		$backup = get_post_meta( $post_id, self::FOCUSKW_BACKUP_META_KEY, true );
		if ( ! is_array( $backup ) ) {
			return false;
		}

		if ( ! SEO_Plugin_Registry::supports_focus_keyword() ) {
			return false;
		}

		if ( array_key_exists( 'focus_keyword', $backup ) ) {
			SEO_Plugin_Registry::restore_focus_keyword( $post_id, (string) $backup['focus_keyword'] );
		}

		delete_post_meta( $post_id, self::FOCUSKW_BACKUP_META_KEY );

		return true;
	}

	/**
	 * Restore all posts from the user's last focus-keyword bulk batch.
	 *
	 * @return array{restored: int, skipped: int}
	 */
	public static function restore_focus_keyword_last_batch() {
		$post_ids = get_user_meta( get_current_user_id(), self::FOCUSKW_LAST_BATCH_USER_META, true );
		if ( ! is_array( $post_ids ) || empty( $post_ids ) ) {
			return array(
				'restored' => 0,
				'skipped'  => 0,
			);
		}

		$restored = 0;
		$skipped  = 0;

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				++$skipped;
				continue;
			}
			if ( self::restore_focus_keyword_post( $post_id ) ) {
				++$restored;
			} else {
				++$skipped;
			}
		}

		delete_user_meta( get_current_user_id(), self::FOCUSKW_LAST_BATCH_USER_META );
		delete_user_meta( get_current_user_id(), 'sb_tools_focuskw_last_batch_at' );

		return array(
			'restored' => $restored,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Persist last focus-keyword batch post IDs for revert.
	 *
	 * @param int[] $post_ids Post IDs.
	 * @return void
	 */
	public static function remember_focus_keyword_last_batch( array $post_ids ) {
		update_user_meta(
			get_current_user_id(),
			self::FOCUSKW_LAST_BATCH_USER_META,
			array_values( array_map( 'intval', $post_ids ) )
		);
		update_user_meta( get_current_user_id(), 'sb_tools_focuskw_last_batch_at', time() );
	}

	/**
	 * Whether the user has a revertable focus-keyword batch.
	 *
	 * @return bool
	 */
	public static function has_revertable_focus_keyword_batch() {
		$ids = get_user_meta( get_current_user_id(), self::FOCUSKW_LAST_BATCH_USER_META, true );
		return is_array( $ids ) && ! empty( $ids );
	}

	/**
	 * Write focus keyword when field is empty (unless overwrite).
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $keyword   Keyword to write.
	 * @param bool   $overwrite Overwrite non-empty values.
	 * @return string Written keyword or empty if skipped.
	 * @throws \Exception When nothing could be applied.
	 */
	public static function write_focus_keyword( $post_id, $keyword, $overwrite = false ) {
		if ( ! SEO_Plugin_Registry::supports_focus_keyword() ) {
			throw new \Exception( esc_html__( 'No supported SEO plugin is active.', 'seo-booster' ) );
		}

		$keyword = sanitize_text_field( trim( (string) $keyword ) );
		if ( $keyword === '' ) {
			throw new \Exception( esc_html__( 'Focus keyword is empty.', 'seo-booster' ) );
		}

		$current = self::read_focus_keyword( $post_id );
		if ( ! $overwrite && trim( $current ) !== '' ) {
			throw new \Exception( esc_html__( 'Focus keyword already set. Enable overwrite to replace.', 'seo-booster' ) );
		}

		SEO_Plugin_Registry::write_focus_keyword( $post_id, $keyword );

		return $keyword;
	}
}
