<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Writing Outline Class
 *
 * Handles AI-powered writing outline generation with step-by-step progress feedback.
 *
 * @since 6.1.26
 */
class AI_Writing_Outline {


	public static function init() {
		add_action( 'wp_ajax_sb_seo_get_writing_outline', array( __CLASS__, 'ajax_get_writing_outline' ) );
		add_action( 'wp_ajax_sb_seo_get_saved_outline', array( __CLASS__, 'ajax_get_saved_outline' ) );
		add_action( 'wp_ajax_sb_seo_generate_article_from_outline', array( __CLASS__, 'ajax_generate_article_from_outline' ) );
	}

	public static function ajax_get_writing_outline() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}

		$post_id     = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$user_prompt = isset( $_POST['user_prompt'] ) ? sanitize_textarea_field( $_POST['user_prompt'] ) : '';
		$step        = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : 'start';

		if ( ! $post_id || ! $user_prompt ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'seo-booster' ) ) );
		}

		// Process in steps
		$result = self::process_outline_step( $post_id, $user_prompt, $step );
		wp_send_json_success( $result );
	}

	private static function process_outline_step( $post_id, $user_prompt, $step ) {
		$start_time = microtime( true );

		switch ( $step ) {
			case 'start':
				return array(
					'message'   => __( 'Preparing prompt...', 'seo-booster' ),
					'next_step' => 'build_context',
					'elapsed'   => round( microtime( true ) - $start_time, 2 ),
				);

			case 'build_context':
				$context = self::build_context( $post_id );
				return array(
					'message'   => __( 'Building context...', 'seo-booster' ),
					'next_step' => 'send_to_ai',
					'context'   => $context,
					'elapsed'   => round( microtime( true ) - $start_time, 2 ),
				);

			case 'send_to_ai':
				return array(
					'message'   => __( 'Sending request to AI...', 'seo-booster' ),
					'next_step' => 'wait_response',
					'elapsed'   => round( microtime( true ) - $start_time, 2 ),
				);

			case 'wait_response':
				$outline = self::generate_outline( $post_id, $user_prompt );
				// Persist outline for future visits
				$saved = array(
					'outline'      => $outline,
					'prompt'       => $user_prompt,
					'generated_at' => current_time( 'mysql' ),
				);
				update_post_meta( $post_id, '_sb_ai_writing_outline', $saved );
				return array(
					'message'   => __( 'Receiving response...', 'seo-booster' ),
					'next_step' => null,
					'outline'   => $outline,
					'elapsed'   => round( microtime( true ) - $start_time, 2 ),
				);
		}
	}

	private static function build_context( $post_id ) {
		$post         = get_post( $post_id );
		$site_url     = get_home_url();
		$site_name    = get_option( 'seobooster_site_name', get_bloginfo( 'name' ) );
		$site_tagline = get_option( 'seobooster_site_tagline', get_bloginfo( 'description' ) );

		$context = array(
			'site_name'    => $site_name,
			'site_tagline' => $site_tagline,
			'site_url'     => $site_url,
			'post_title'   => $post->post_title,
			'post_type'    => $post->post_type,
		);

		// Add post URL if published
		if ( $post->post_status === 'publish' ) {
			$context['post_url'] = get_permalink( $post_id );
		}

		// Add categories/taxonomies
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		foreach ( $taxonomies as $tax ) {
			$terms = wp_get_post_terms( $post_id, $tax );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$context['taxonomies'][ $tax ] = array_map(
					function ( $t ) {
						return $t->name;
					},
					$terms
				);
			}
		}

		// Add Yoast SEO fields if available
		$yoast_title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
		$yoast_desc  = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		if ( ! empty( $yoast_title ) ) {
			$context['yoast_title'] = $yoast_title;
		}
		if ( ! empty( $yoast_desc ) ) {
			$context['yoast_description'] = $yoast_desc;
		}

		return $context;
	}

	private static function generate_outline( $post_id, $user_prompt ) {
		$context = self::build_context( $post_id );

		// Build full prompt with context
		$system_prompt = sprintf(
			'You are a professional content writer helping with %s (%s). Site URL: %s. Create a detailed article outline.',
			$context['site_name'],
			$context['site_tagline'],
			$context['site_url']
		);

		if ( isset( $context['post_url'] ) ) {
			$system_prompt .= ' Current page: ' . $context['post_url'];
		}

		if ( isset( $context['categories'] ) ) {
			$categories     = wp_list_pluck( $context['categories'], 'name' );
			$system_prompt .= ' Categories: ' . implode( ', ', $categories );
		}

		$full_prompt = $system_prompt . "\n\nUser request: " . $user_prompt;

		$ai_provider = LLM_Helper::get_selected_ai_provider();
		if ( $ai_provider === 'disabled' ) {
			throw new \Exception( __( 'AI provider is disabled', 'seo-booster' ) );
		}

		if ( $ai_provider === 'WordPress' ) {
			if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
				throw new \Exception( __( 'WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster' ) );
			}
			if ( ! LLM_Helper::wp_ai_is_available() ) {
				throw new \Exception( LLM_Helper::wp_ai_unavailable_message() );
			}
			$text = LLM_Helper::ai_prompt( $full_prompt )
				->using_system_instruction( __( 'You are an expert content strategist. Return only a clean, markdown outline (H2/H3 bullets) with no extra commentary.', 'seo-booster' ) )
				->generate_text();
			return is_string( $text ) ? $text : '';
		}

		throw new \Exception( __( 'Unsupported AI provider. Use WordPress (Connectors) or SEO Booster Credits.', 'seo-booster' ) );
	}

	/**
	 * Return saved outline for a post
	 */
	public static function ajax_get_saved_outline() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post ID', 'seo-booster' ) ) );
		}
		$saved = get_post_meta( $post_id, '_sb_ai_writing_outline', true );
		if ( empty( $saved ) ) {
			wp_send_json_success( array( 'outline' => null ) );
		}
		wp_send_json_success( $saved );
	}

	/**
	 * Generate full article from outline via AI and return strict JSON
	 */
	public static function ajax_generate_article_from_outline() {
		check_ajax_referer( 'sb_seo_metabox_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'seo-booster' ) ) );
		}
		$post_id  = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$outline  = isset( $_POST['outline'] ) ? wp_kses_post( $_POST['outline'] ) : '';
		$language = isset( $_POST['language'] ) ? sanitize_text_field( $_POST['language'] ) : '';
		if ( ! $post_id || empty( $outline ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'seo-booster' ) ) );
		}

		try {
			$result = self::generate_article( $post_id, $outline, $language );
			wp_send_json_success( $result );
		} catch ( \Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	private static function generate_article( $post_id, $outline, $language ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( __( 'Post not found', 'seo-booster' ) );
		}

		$context = self::build_context( $post_id );
		if ( empty( $language ) ) {
			$language = LLM_Helper::get_post_language( $post_id );
		}

		$system_prompt = 'You are an expert content writer. Generate a complete article from the provided outline. Return ONLY a strict JSON object with keys "title" and "content_html". The "content_html" must be valid HTML (paragraphs, headings, lists) and must not include scripts or external resources. Do NOT include an H1 in the content. The article content starts at <h2> and uses <h2> and <h3> only. Do not repeat the title within the content. Adhere to the grammatical conventions and natural casing of the specified language.';
		$user_prompt   = sprintf(
			"Language: %s\nSite: %s (%s)\nPost type: %s\n\nOutline:\n%s\n\nStrict JSON format:\n{\n  \"title\": \"...\",\n  \"content_html\": \"<p>...HTML...</p>\"\n}",
			$language,
			$context['site_name'] ?? get_bloginfo( 'name' ),
			$context['site_tagline'] ?? get_bloginfo( 'description' ),
			$post->post_type,
			$outline
		);

		$provider = LLM_Helper::get_selected_ai_provider();
		if ( $provider === 'disabled' ) {
			throw new \Exception( __( 'AI provider is disabled', 'seo-booster' ) );
		}

		if ( $provider === 'WordPress' ) {
			if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
				throw new \Exception( __( 'WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster' ) );
			}
			if ( ! LLM_Helper::wp_ai_is_available() ) {
				throw new \Exception( LLM_Helper::wp_ai_unavailable_message() );
			}
			$response_text = LLM_Helper::ai_prompt( $user_prompt )
				->using_system_instruction( $system_prompt )
				->generate_text();
			$response_text = preg_replace( '#^```(?:json)?\s*|\s*```$#', '', trim( (string) $response_text ) );
			$parsed        = json_decode( $response_text, true );
			if ( json_last_error() !== JSON_ERROR_NONE || empty( $parsed['title'] ) || empty( $parsed['content_html'] ) ) {
				throw new \Exception( __( 'Invalid AI response format', 'seo-booster' ) );
			}
			return array(
				'title'        => sanitize_text_field( $parsed['title'] ),
				'content_html' => wp_kses_post( $parsed['content_html'] ),
			);
		}

		throw new \Exception( __( 'Unsupported AI provider. Use WordPress (Connectors) or SEO Booster Credits.', 'seo-booster' ) );
	}

	/**
	 * Check credits before outline/article AI runs.
	 *
	 * TODO (Credits launch): Replace no-op with Credits_Service balance checks when
	 * sb_credits_ai_provider_available is enabled. Keep Connectors path unchanged.
	 *
	 * @return bool
	 */
	public static function check_credits() {
		return true;
	}

	/**
	 * Deduct credits after a successful outline/article AI request.
	 *
	 * TODO (Credits launch): Call Credits_Service to deduct balance; return false when insufficient.
	 *
	 * @param int $amount Amount of credits to deduct.
	 * @return bool
	 */
	public static function deduct_credits( $amount = 2 ) {
		return true;
	}
}
