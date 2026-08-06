<?php

namespace Cleverplugins\SEOBooster;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_Helper
 *
 * Helper utilities for LLM SEO functionality.
 *
 * @package Cleverplugins\SEOBooster
 * @since 6.1.26
 */
class LLM_Helper {

	/**
	 * Get post language with Polylang/WPML support.
	 *
	 * @since 6.1.26
	 * @param int $post_id Post ID.
	 * @return string Language code in WordPress locale format.
	 */
	public static function get_post_language( $post_id ) {
		$detected_locale  = null;
		$detection_method = '';

		// Try Polylang first (if available)
		if ( function_exists( 'pll_get_post_language' ) ) {
			// Try to get locale directly if Polylang supports it
			$polylang_locale = pll_get_post_language( $post_id, 'locale' );
			if ( $polylang_locale ) {
				$detected_locale  = $polylang_locale;
				$detection_method = 'Polylang (locale)';
			} else {
				// Fallback: get language code and convert
				$polylang_code = pll_get_post_language( $post_id );
				if ( $polylang_code ) {
					$detected_locale  = self::convert_language_code_to_locale( $polylang_code );
					$detection_method = 'Polylang (code)';
				}
			}
		}

		// Fallback to Polylang current language
		if ( ! $detected_locale && function_exists( 'pll_current_language' ) ) {
			$polylang_current = pll_current_language( 'locale' );
			if ( $polylang_current ) {
				$detected_locale  = $polylang_current;
				$detection_method = 'Polylang (current locale)';
			} else {
				// Try with slug if locale not available
				$polylang_slug = pll_current_language();
				if ( $polylang_slug ) {
					$detected_locale  = self::convert_language_code_to_locale( $polylang_slug );
					$detection_method = 'Polylang (current code)';
				}
			}
		}

		// Try WPML post language details (modern WPML hook)
		if ( ! $detected_locale && has_filter( 'wpml_post_language_details' ) ) {
			$lang_details = apply_filters( 'wpml_post_language_details', null, $post_id );
			if ( $lang_details ) {
				// Handle both object and array formats
				$locale = is_object( $lang_details ) ? ( $lang_details->locale ?? null ) : ( $lang_details['locale'] ?? null );
				if ( $locale ) {
					$detected_locale  = $locale;
					$detection_method = 'WPML (post locale)';
				} else {
					// If locale not available, try language_code
					$language_code = is_object( $lang_details ) ? ( $lang_details->language_code ?? null ) : ( $lang_details['language_code'] ?? null );
					if ( $language_code ) {
						$detected_locale  = self::convert_language_code_to_locale( $language_code );
						$detection_method = 'WPML (post code)';
					}
				}
			}
		}

		// Fallback to WPML current site language (modern WPML hook)
		if ( ! $detected_locale && has_filter( 'wpml_current_language' ) ) {
			$current_lang = apply_filters( 'wpml_current_language', null );
			if ( $current_lang ) {
				$detected_locale  = self::convert_language_code_to_locale( $current_lang );
				$detection_method = 'WPML (current)';
			}
		}

		// Fallback to WordPress site locale
		if ( ! $detected_locale ) {
			$detected_locale  = get_locale();
			$detection_method = 'WordPress (get_locale)';
		}

		// Normalize the locale
		$final_locale = self::normalize_locale( $detected_locale );

		return $final_locale;
	}

	/**
	 * Map of ISO language codes to WordPress locales.
	 *
	 * @since 7.3.3
	 * @return array<string, string>
	 */
	private static function get_language_code_map() {
		return array(
			'en' => 'en_US',
			'da' => 'da_DK',
			'sv' => 'sv_SE',
			'no' => 'nb_NO',
			'nb' => 'nb_NO',
			'nn' => 'nn_NO',
			'fi' => 'fi',
			'de' => 'de_DE',
			'fr' => 'fr_FR',
			'es' => 'es_ES',
			'it' => 'it_IT',
			'pt' => 'pt_PT',
			'nl' => 'nl_NL',
			'pl' => 'pl_PL',
			'ru' => 'ru_RU',
			'cs' => 'cs_CZ',
			'sk' => 'sk_SK',
			'hu' => 'hu_HU',
			'ro' => 'ro_RO',
			'bg' => 'bg_BG',
			'hr' => 'hr',
			'sl' => 'sl_SI',
			'et' => 'et',
			'lv' => 'lv',
			'lt' => 'lt_LT',
			'el' => 'el',
			'tr' => 'tr_TR',
			'uk' => 'uk',
			'ar' => 'ar',
			'he' => 'he_IL',
			'hi' => 'hi_IN',
			'ja' => 'ja',
			'ko' => 'ko_KR',
			'zh' => 'zh_CN',
			'th' => 'th',
			'vi' => 'vi',
			'id' => 'id_ID',
			'ms' => 'ms_MY',
			'ca' => 'ca',
			'eu' => 'eu',
			'gl' => 'gl_ES',
			'is' => 'is_IS',
			'af' => 'af',
			'sw' => 'sw',
		);
	}

	/**
	 * Convert WPML/Polylang 2-letter language code to WordPress locale format.
	 *
	 * Unknown codes fall back to the site locale (never silently to en_US).
	 *
	 * @since 6.1.26
	 * @param string $language_code 2-letter language code (e.g., 'en', 'da', 'fr').
	 * @return string WordPress locale format (e.g., 'en_US', 'da_DK').
	 */
	private static function convert_language_code_to_locale( $language_code ) {
		$language_code = strtolower( trim( (string) $language_code ) );
		if ( $language_code === '' ) {
			return get_locale();
		}

		$code_to_locale = self::get_language_code_map();

		if ( isset( $code_to_locale[ $language_code ] ) ) {
			return $code_to_locale[ $language_code ];
		}

		return get_locale();
	}

	/**
	 * Normalize locale format.
	 *
	 * @since 6.1.26
	 * @param string $locale WordPress locale.
	 * @return string Normalized locale.
	 */
	private static function normalize_locale( $locale ) {
		$locale = trim( (string) $locale );
		if ( $locale === '' ) {
			return get_locale();
		}

		// Bare ISO codes → full locales from the shared map.
		$code_map = self::get_language_code_map();
		if ( isset( $code_map[ $locale ] ) ) {
			return $code_map[ $locale ];
		}

		$normalizations = array(
			'en_GB' => 'en_US',
			'no_NO' => 'nb_NO',
			'nn'    => 'nn_NO',
			'nb'    => 'nb_NO',
		);

		return $normalizations[ $locale ] ?? $locale;
	}

	/**
	 * Get language name from locale code.
	 *
	 * Names are English identifiers for AI prompts (not translated UI strings).
	 *
	 * @since 6.1.26
	 * @param string $locale WordPress locale code (e.g., 'da_DK', 'en_US').
	 * @return string Language name (e.g., 'Danish', 'English').
	 */
	public static function get_language_name( $locale ) {
		$language_names = array(
			'da_DK' => 'Danish',
			'en_US' => 'English',
			'sv_SE' => 'Swedish',
			'nb_NO' => 'Norwegian',
			'nn_NO' => 'Norwegian Nynorsk',
			'no_NO' => 'Norwegian',
			'fi'    => 'Finnish',
			'de_DE' => 'German',
			'de_AT' => 'German',
			'de_CH' => 'German',
			'fr_FR' => 'French',
			'fr_BE' => 'French',
			'fr_CA' => 'French',
			'es_ES' => 'Spanish',
			'es_MX' => 'Spanish',
			'es_AR' => 'Spanish',
			'it_IT' => 'Italian',
			'pt_PT' => 'Portuguese',
			'pt_BR' => 'Portuguese',
			'nl_NL' => 'Dutch',
			'nl_BE' => 'Dutch',
			'pl_PL' => 'Polish',
			'ru_RU' => 'Russian',
			'cs_CZ' => 'Czech',
			'sk_SK' => 'Slovak',
			'hu_HU' => 'Hungarian',
			'ro_RO' => 'Romanian',
			'bg_BG' => 'Bulgarian',
			'hr'    => 'Croatian',
			'sl_SI' => 'Slovenian',
			'et'    => 'Estonian',
			'lv'    => 'Latvian',
			'lt_LT' => 'Lithuanian',
			'el'    => 'Greek',
			'tr_TR' => 'Turkish',
			'uk'    => 'Ukrainian',
			'ar'    => 'Arabic',
			'he_IL' => 'Hebrew',
			'hi_IN' => 'Hindi',
			'ja'    => 'Japanese',
			'ko_KR' => 'Korean',
			'zh_CN' => 'Chinese',
			'zh_TW' => 'Chinese',
			'th'    => 'Thai',
			'vi'    => 'Vietnamese',
			'id_ID' => 'Indonesian',
			'ms_MY' => 'Malay',
			'ca'    => 'Catalan',
			'eu'    => 'Basque',
			'gl_ES' => 'Galician',
			'is_IS' => 'Icelandic',
			'af'    => 'Afrikaans',
			'sw'    => 'Swahili',
		);

		if ( isset( $language_names[ $locale ] ) ) {
			return $language_names[ $locale ];
		}

		// Try language portion only (e.g. de_CH → de_DE map miss → "de").
		$parts = explode( '_', $locale );
		if ( count( $parts ) > 1 ) {
			$code_map = self::get_language_code_map();
			$lang     = strtolower( $parts[0] );
			if ( isset( $code_map[ $lang ] ) && isset( $language_names[ $code_map[ $lang ] ] ) ) {
				return $language_names[ $code_map[ $lang ] ];
			}
		}

		return $locale;
	}

	/**
	 * Ensure a string is valid UTF-8 safe for JSON encoding (AI connectors).
	 *
	 * Invalid sequences are stripped; never returns bytes that break json_encode.
	 *
	 * @since 7.3.3
	 * @param mixed $value Input value (cast to string).
	 * @return string Valid UTF-8 string.
	 */
	public static function ensure_utf8( $value ) {
		$string = (string) $value;
		if ( $string === '' ) {
			return '';
		}

		// Null bytes break JSON and some HTTP clients.
		$string = str_replace( "\0", '', $string );

		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$checked = wp_check_invalid_utf8( $string, true );
			if ( is_string( $checked ) ) {
				$string = $checked;
			}
		}

		if ( function_exists( 'mb_check_encoding' ) && mb_check_encoding( $string, 'UTF-8' ) ) {
			return $string;
		}

		if ( function_exists( 'mb_convert_encoding' ) ) {
			$converted = @mb_convert_encoding( $string, 'UTF-8', 'UTF-8' );
			if ( is_string( $converted ) ) {
				$string = $converted;
			}
		} elseif ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'UTF-8//IGNORE', $string );
			if ( is_string( $converted ) ) {
				$string = $converted;
			}
		}

		// Last resort: strip non-UTF-8 via json_encode round-trip with substitute.
		$encoded = wp_json_encode( $string );
		if ( false === $encoded ) {
			$string  = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $string );
			$encoded = wp_json_encode( $string );
		}
		if ( is_string( $encoded ) ) {
			$decoded = json_decode( $encoded );
			if ( is_string( $decoded ) ) {
				return $decoded;
			}
		}

		return $string;
	}

	/**
	 * Ensure every string in an array is valid UTF-8.
	 *
	 * @since 7.3.3
	 * @param array $values List of values.
	 * @return array
	 */
	public static function ensure_utf8_array( array $values ) {
		$out = array();
		foreach ( $values as $key => $value ) {
			if ( is_array( $value ) ) {
				$out[ $key ] = self::ensure_utf8_array( $value );
			} elseif ( is_string( $value ) ) {
				$out[ $key ] = self::ensure_utf8( $value );
			} else {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Get post description (excerpt or generated).
	 *
	 * @since 6.1.26
	 * @param \WP_Post $post Post object.
	 * @return string Post description.
	 */
	public static function get_post_description( $post ) {
		if ( ! empty( $post->post_excerpt ) ) {
			return $post->post_excerpt;
		}

		// Generate from content
		$content = wp_strip_all_tags( $post->post_content );
		return wp_trim_words( $content, 25, '...' );
	}

	/**
	 * Get GSC keywords for a post.
	 *
	 * @since 6.1.26
	 * @param int $post_id Post ID.
	 * @return array Array of GSC keywords.
	 */
	public static function get_gsc_keywords( $post_id ) {
		global $wpdb;

		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT k.query FROM {$wpdb->prefix}sb2_query_keywords AS k
             WHERE k.page = %s 
             AND LENGTH(k.query) >= 3
             ORDER BY LENGTH(k.query) DESC
             LIMIT 20",
				$permalink
			),
			ARRAY_A
		);

		return array_column( $results, 'query' );
	}

	/**
	 * Get focus keywords for a post.
	 *
	 * @since 6.1.26
	 * @param int $post_id Post ID.
	 * @return array Array of focus keywords.
	 */
	public static function get_focus_keywords( $post_id ) {
		$plugin_keywords = Google_API::get_focus_keywords( $post_id );

		if ( empty( $plugin_keywords ) ) {
			return array();
		}

		return $plugin_keywords;
	}

	/**
	 * Get saved suggestions for a post.
	 *
	 * @since 6.1.26
	 * @param int $post_id Post ID.
	 * @return array|false Saved suggestions or false if not found.
	 */
	public static function get_saved_suggestions( $post_id ) {
		$saved = get_post_meta( $post_id, '_llm_seo_last_result', true );

		if ( empty( $saved ) || ! is_array( $saved ) ) {
			return false;
		}

		return $saved;
	}

	/**
	 * Whether the WordPress AI Client environment is ready (WP 7+).
	 *
	 * @since 7.0.4
	 * @return bool
	 */
	public static function wp_ai_environment_ready() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		if ( function_exists( 'wp_supports_ai' ) && ! self::call_optional_wp_function( 'wp_supports_ai' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Dynamically invoke an optional WordPress 7.0+ function.
	 *
	 * SEO Booster supports WordPress 6.8+, where the WordPress AI Client
	 * functions (wp_ai_client_prompt(), wp_supports_ai(), (array) self::call_optional_wp_function( 'wp_get_connectors' ))
	 * do not exist. Callers guard usage with feature detection; dispatching the
	 * call dynamically keeps any direct reference to a 7.0-only function out of
	 * the codebase, so it can never fatal on 6.8.
	 *
	 * @since 7.2.4
	 * @param string $function Global function name.
	 * @param mixed  ...$args  Arguments to pass to the function.
	 * @return mixed|null Result, or null when the function is unavailable.
	 */
	private static function call_optional_wp_function( $function, ...$args ) {
		if ( ! function_exists( $function ) ) {
			return null;
		}

		return $function( ...$args );
	}

	/**
	 * Returns a WordPress AI prompt builder, or null when AI is unavailable.
	 *
	 * Thin accessor around the optional WordPress 7.0+ wp_ai_client_prompt()
	 * function. Callers must confirm availability (see wp_ai_is_available())
	 * before chaining builder methods on the result.
	 *
	 * @since 7.2.4
	 * @param string $prompt Prompt text.
	 * @return mixed|null Prompt builder instance, or null on WordPress < 7.0.
	 */
	public static function ai_prompt( $prompt ) {
		return self::call_optional_wp_function( 'wp_ai_client_prompt', $prompt );
	}

	/**
	 * Record and send a WordPress Connectors AI request.
	 *
	 * Logs metadata only. Prompt content, URLs, IDs, and credentials are never
	 * included in the debug log entry.
	 *
	 * @since 7.4.0
	 * @param mixed  $builder Prepared WordPress AI prompt builder.
	 * @param string $source  Fixed feature identifier for the request origin.
	 * @return mixed AI response.
	 */
	public static function generate_ai_text( $builder, $source ) {
		self::log_ai_request( 'wordpress-connectors', $source );

		return $builder->generate_text();
	}

	/**
	 * Record an outbound AI generation request without sensitive request data.
	 *
	 * @since 7.4.0
	 * @param string $provider AI transport or provider identifier.
	 * @param string $source   Fixed feature identifier for the request origin.
	 * @return void
	 */
	public static function log_ai_request( $provider, $source ) {
		$provider = sanitize_key( $provider );
		$source   = sanitize_key( $source );

		if ( '' === $provider ) {
			$provider = 'unknown';
		}
		if ( '' === $source ) {
			$source = 'unknown';
		}

		Utils::log(
			sprintf(
				'AI request sent: provider=%s source=%s',
				$provider,
				$source
			),
			5
		);
	}

	/**
	 * Whether at least one AI provider is configured in Settings → Connectors.
	 *
	 * @since 7.0.4
	 * @return bool
	 */
	public static function wp_ai_has_configured_provider() {
		if ( ! class_exists( '\WordPress\AiClient\AiClient' ) ) {
			return false;
		}

		try {
			$registry = \WordPress\AiClient\AiClient::defaultRegistry();
			if ( ! method_exists( $registry, 'getRegisteredProviderIds' ) || ! method_exists( $registry, 'isProviderConfigured' ) ) {
				return false;
			}

			foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
				if ( $registry->isProviderConfigured( $provider_id ) ) {
					return true;
				}
			}
		} catch ( \Throwable $e ) {
			// Fall through to connector option check.
		}

		if ( function_exists( 'wp_get_connectors' ) ) {
			foreach ( (array) self::call_optional_wp_function( 'wp_get_connectors' ) as $connector_data ) {
				if ( ( $connector_data['type'] ?? '' ) !== 'ai_provider' ) {
					continue;
				}
				$setting_name = $connector_data['authentication']['setting_name'] ?? '';
				if ( $setting_name && get_option( $setting_name, '' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Whether the current setup supports text generation (official WP 7 feature detection).
	 *
	 * @since 7.0.4
	 * @return bool
	 */
	public static function wp_ai_supports_text_generation() {
		if ( ! self::wp_ai_environment_ready() ) {
			return false;
		}

		if ( ! self::wp_ai_has_configured_provider() ) {
			return false;
		}

		$builder = self::ai_prompt( 'test' );
		if ( null === $builder ) {
			return false;
		}

		return (bool) $builder->is_supported_for_text_generation();
	}

	/**
	 * Whether WordPress AI UI (Tools, metabox) should be enabled.
	 *
	 * Uses environment + configured connector only. Stricter support checks run at generation time.
	 *
	 * @since 7.0.4
	 * @return bool
	 */
	public static function wp_ai_ui_ready() {
		return self::wp_ai_environment_ready() && self::wp_ai_has_configured_provider();
	}

	/**
	 * Whether WordPress AI can be used for text generation (all official checks pass).
	 *
	 * @since 7.0.4
	 * @return bool
	 */
	public static function wp_ai_is_available() {
		return self::wp_ai_supports_text_generation();
	}

	/**
	 * User-facing reason when WordPress AI is not available.
	 *
	 * @since 7.0.4
	 * @return string
	 */
	public static function wp_ai_unavailable_message() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return __(
				'WordPress AI is not available. Use WordPress 7 or later.',
				'seo-booster'
			);
		}

		if ( function_exists( 'wp_supports_ai' ) && ! self::call_optional_wp_function( 'wp_supports_ai' ) ) {
			return __(
				'AI features are disabled in this environment. On local sites, check that AI is not turned off (for example WP_AI_SUPPORT in wp-config.php).',
				'seo-booster'
			);
		}

		if ( ! self::wp_ai_has_configured_provider() ) {
			return __(
				'No AI connector is configured. Go to Settings → Connectors and connect a provider.',
				'seo-booster'
			);
		}

		if ( ! self::wp_ai_supports_text_generation() ) {
			return __(
				'No configured AI model supports text generation for this request. Check Settings → Connectors.',
				'seo-booster'
			);
		}

		return __(
			'WordPress AI is not available. Check Settings → Connectors.',
			'seo-booster'
		);
	}

	/**
	 * Provider IDs that only support text (no image/vision input via WordPress AI Client).
	 *
	 * @since 7.0.4
	 * @return string[]
	 */
	public static function wp_ai_text_only_provider_ids() {
		$ids = array( 'deepseek' );

		/**
		 * Filter text-only AI provider IDs for image metadata notices.
		 *
		 * @since 7.0.4
		 * @param string[] $ids Provider IDs.
		 */
		return apply_filters( 'sb_ai_text_only_provider_ids', $ids );
	}

	/**
	 * Provider IDs suitable for image metadata (vision or multimodal chat).
	 *
	 * @since 7.0.4
	 * @return string[]
	 */
	public static function wp_ai_vision_capable_provider_ids() {
		$ids = array( 'openai', 'google', 'anthropic' );

		/**
		 * Filter vision-capable AI provider IDs for image metadata.
		 *
		 * @since 7.0.4
		 * @param string[] $ids Provider IDs.
		 */
		return apply_filters( 'sb_ai_vision_capable_provider_ids', $ids );
	}

	/**
	 * Configured AI provider IDs (registry + connector options).
	 *
	 * @since 7.0.4
	 * @return string[]
	 */
	public static function wp_ai_get_configured_provider_ids() {
		$configured = array();

		if ( class_exists( '\WordPress\AiClient\AiClient' ) ) {
			try {
				$registry = \WordPress\AiClient\AiClient::defaultRegistry();
				if ( method_exists( $registry, 'getRegisteredProviderIds' ) && method_exists( $registry, 'isProviderConfigured' ) ) {
					foreach ( $registry->getRegisteredProviderIds() as $provider_id ) {
						if ( $registry->isProviderConfigured( $provider_id ) ) {
							$configured[] = $provider_id;
						}
					}
				}
			} catch ( \Throwable $e ) {
				// Fall through.
			}
		}

		if ( function_exists( 'wp_get_connectors' ) ) {
			foreach ( (array) self::call_optional_wp_function( 'wp_get_connectors' ) as $connector_id => $connector_data ) {
				if ( ( $connector_data['type'] ?? '' ) !== 'ai_provider' ) {
					continue;
				}
				$setting_name = $connector_data['authentication']['setting_name'] ?? '';
				if ( $setting_name && get_option( $setting_name, '' ) && ! in_array( $connector_id, $configured, true ) ) {
					$configured[] = $connector_id;
				}
			}
		}

		return array_values( array_unique( $configured ) );
	}

	/**
	 * Whether a configured vision-capable connector exists (e.g. OpenAI).
	 *
	 * @since 7.0.4
	 * @return bool
	 */
	public static function wp_ai_has_vision_capable_connector_configured() {
		$vision_ids = self::wp_ai_vision_capable_provider_ids();
		foreach ( self::wp_ai_get_configured_provider_ids() as $provider_id ) {
			if ( in_array( $provider_id, $vision_ids, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether only text-only connectors are configured (e.g. DeepSeek alone).
	 *
	 * @since 7.0.4
	 * @return bool
	 */
	public static function wp_ai_only_text_only_connectors_configured() {
		$configured = self::wp_ai_get_configured_provider_ids();
		if ( empty( $configured ) ) {
			return false;
		}

		$text_only = self::wp_ai_text_only_provider_ids();
		foreach ( $configured as $provider_id ) {
			if ( ! in_array( $provider_id, $text_only, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Human-readable label for a connector ID.
	 *
	 * @since 7.0.4
	 * @param string $provider_id Provider ID.
	 * @return string
	 */
	public static function wp_ai_provider_label( $provider_id ) {
		if ( function_exists( 'wp_get_connectors' ) ) {
			$connectors = (array) self::call_optional_wp_function( 'wp_get_connectors' );
			if ( isset( $connectors[ $provider_id ]['name'] ) && $connectors[ $provider_id ]['name'] ) {
				return $connectors[ $provider_id ]['name'];
			}
		}

		return ucwords( str_replace( array( '-', '_' ), ' ', $provider_id ) );
	}

	/**
	 * Whether image metadata tools can run with the WordPress AI provider.
	 *
	 * Requires a vision-capable connector and a model that supports text generation.
	 *
	 * @since 7.0.4
	 * @return bool
	 */
	public static function wp_ai_image_tools_can_process() {
		if ( ! self::wp_ai_environment_ready() || ! self::wp_ai_has_configured_provider() ) {
			return false;
		}

		if ( ! self::wp_ai_has_vision_capable_connector_configured() ) {
			return false;
		}

		return self::wp_ai_supports_text_generation();
	}

	/**
	 * Normalize a raw AI provider value to a canonical option string.
	 *
	 * Accepts form slugs and legacy values case-insensitively, e.g. "wordpress",
	 * "WordPress", and legacy "openai" all map to "WordPress".
	 *
	 * @since 7.4.0
	 * @param mixed $provider Raw option or form value.
	 * @return string One of disabled, WordPress, seobooster, or empty string if unknown.
	 */
	public static function normalize_ai_provider( $provider ) {
		if ( ! is_string( $provider ) ) {
			return '';
		}

		$lower = strtolower( trim( $provider ) );

		if ( 'disabled' === $lower ) {
			return 'disabled';
		}

		// Form radio historically posted "wordpress"; legacy installs used "openai".
		if ( 'wordpress' === $lower || 'openai' === $lower ) {
			return 'WordPress';
		}

		if ( 'seobooster' === $lower ) {
			return 'seobooster';
		}

		return '';
	}

	/**
	 * Effective AI provider for UI and runtime (normalized from the stored option).
	 *
	 * Treats unavailable Credits selection and legacy values the same way as Settings.
	 *
	 * @since 7.2.3
	 * @return string One of disabled, WordPress, seobooster.
	 */
	public static function get_selected_ai_provider() {
		$provider = self::normalize_ai_provider( get_option( 'seobooster_ai_provider', 'disabled' ) );

		if ( '' === $provider ) {
			return 'disabled';
		}

		if ( 'seobooster' === $provider && ! Credits_Service::is_ai_provider_available() ) {
			return 'disabled';
		}

		return $provider;
	}

	/**
	 * Optional user-facing hint pointing to SEO Booster Credits (when released).
	 *
	 * @since 7.0.5
	 * @return string Empty when credits provider is not publicly available.
	 */
	public static function ai_credits_option_hint() {
		if ( ! Credits_Service::is_ai_provider_available() ) {
			return '';
		}

		return __( ' or use SEO Booster Credits in SEO Booster Settings.', 'seo-booster' );
	}

	/**
	 * Notice context for the Tools image metadata UI.
	 *
	 * @since 7.0.4
	 * @return array{message: string, type: string}
	 */
	public static function wp_ai_image_metadata_notice_context() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return array(
				'message' => self::wp_ai_unavailable_message(),
				'type'    => 'unavailable',
			);
		}

		if ( function_exists( 'wp_supports_ai' ) && ! self::call_optional_wp_function( 'wp_supports_ai' ) ) {
			return array(
				'message' => self::wp_ai_unavailable_message(),
				'type'    => 'environment',
			);
		}

		if ( ! self::wp_ai_has_configured_provider() ) {
			return array(
				'message' => __(
					'No AI connector is configured. Connect a vision-capable provider (such as OpenAI) under Settings → Connectors.',
					'seo-booster'
				) . self::ai_credits_option_hint(),
				'type'    => 'no_connector',
			);
		}

		if ( self::wp_ai_only_text_only_connectors_configured() ) {
			$configured     = self::wp_ai_get_configured_provider_ids();
			$labels         = array_map( array( __CLASS__, 'wp_ai_provider_label' ), $configured );
			$connector_list = implode( ', ', $labels );

			if ( count( $configured ) === 1 && in_array( 'deepseek', $configured, true ) ) {
				return array(
					'message' => __(
						'DeepSeek is a text-only connector: it cannot analyze image pixels. SEO Booster needs a vision-capable model to write alt text from the actual image (not just the file URL). Connect OpenAI under Settings → Connectors.',
						'seo-booster'
					) . self::ai_credits_option_hint(),
					'type'    => 'deepseek_only',
				);
			}

			return array(
				'message' => sprintf(
					/* translators: %s: comma-separated connector names */
					__(
						'%s only supports text generation and cannot analyze images for alt text. Connect a vision-capable provider (such as OpenAI) under Settings → Connectors.',
						'seo-booster'
					) . self::ai_credits_option_hint(),
					$connector_list
				),
				'type'    => 'text_only_only',
			);
		}

		if ( ! self::wp_ai_has_vision_capable_connector_configured() ) {
			return array(
				'message' => __(
					'No vision-capable connector is configured. Image metadata needs a provider that can interpret images (such as OpenAI), not text-only APIs. Add one under Settings → Connectors.',
					'seo-booster'
				) . self::ai_credits_option_hint(),
				'type'    => 'no_vision_connector',
			);
		}

		if ( ! self::wp_ai_supports_text_generation() ) {
			return array(
				'message' => __(
					'A vision connector is connected, but WordPress could not find a model for text generation (check your API key and that this site can reach the provider). Open Settings → Connectors to verify the connection.',
					'seo-booster'
				),
				'type'    => 'support_check_failed',
			);
		}

		return array(
			'message' => '',
			'type'    => 'ok',
		);
	}

	/**
	 * User-facing notice when image metadata batch cannot run (WordPress provider).
	 *
	 * @since 7.0.4
	 * @return string
	 */
	public static function wp_ai_image_metadata_unavailable_message() {
		$context = self::wp_ai_image_metadata_notice_context();
		return $context['message'];
	}
}
