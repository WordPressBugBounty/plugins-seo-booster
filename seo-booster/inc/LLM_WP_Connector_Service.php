<?php

namespace Cleverplugins\SEOBooster;

use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LLM_WP_Connector_Service
 *
 * Generates SEO suggestions using WordPress 7 AI Connectors (wp_ai_client_prompt).
 * No API keys stored in plugin; credentials from Settings → Connectors.
 *
 * @package Cleverplugins\SEOBooster
 */
class LLM_WP_Connector_Service {

	/** Prompt variant: metabox-style suggestions (7 titles/descriptions). */
	public const VARIANT_FULL = 'full';

	/** Prompt variant: bulk Tools — one title and description, faster output. */
	public const VARIANT_BULK = 'bulk';

	/** Prompt variant: page assistant Q&A with optional actions. */
	public const VARIANT_ASSISTANT = 'assistant';

	/** Default HTTP timeout (seconds) for bulk AI requests. */
	public const BULK_REQUEST_TIMEOUT = 90.0;

	/** Default HTTP timeout (seconds) for assistant Ask requests. */
	public const ASSISTANT_REQUEST_TIMEOUT = 60.0;

	/** Number of title/description suggestions for the full metabox prompt. */
	public const FULL_SUGGESTION_COUNT = 7;

	/**
	 * Generate SEO suggestions for a post using WordPress AI Client.
	 *
	 * @param int    $post_id                 Post ID.
	 * @param string $condensed_content       Condensed content from LLM_Content_Condenser.
	 * @param string $language                Post language code.
	 * @param string $local_analysis_summary  Optional local SEO analysis summary.
	 * @param array  $options                 Optional: variant (full|bulk), timeout (float seconds), source (log identifier).
	 * @return array Result with titles, descriptions, and metadata.
	 * @throws \Exception If API call fails or response is invalid.
	 */
	public function generate_suggestions( $post_id, $condensed_content, $language, $local_analysis_summary = '', array $options = array() ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			throw new \Exception( esc_html__( 'WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster' ) );
		}

		if ( ! LLM_Helper::wp_ai_is_available() ) {
			throw new \Exception( esc_html( LLM_Helper::wp_ai_unavailable_message() ) );
		}

		$condensed_content      = LLM_Helper::ensure_utf8( $condensed_content );
		$local_analysis_summary = LLM_Helper::ensure_utf8( $local_analysis_summary );

		$variant = isset( $options['variant'] ) ? (string) $options['variant'] : self::VARIANT_FULL;
		if ( self::VARIANT_BULK === $variant ) {
			$prompt = $this->build_bulk_meta_prompt( $post_id, $condensed_content, $language, $options );
		} else {
			$prompt = $this->build_prompt( $post_id, $condensed_content, $language, $local_analysis_summary );
		}

		$prompt = apply_filters( 'seobooster_ai_prompt_template', $prompt, $post_id, $condensed_content, $language );
		$prompt = LLM_Helper::ensure_utf8( $prompt );

		$response_text = $this->execute_ai_prompt( $prompt, $post_id, $variant, $options );

		if ( is_wp_error( $response_text ) ) {
			Utils::log( 'WP Connector AI: ' . $response_text->get_error_message(), 2 );
			throw new \Exception( esc_html( $response_text->get_error_message() ) );
		}

		if ( ! is_string( $response_text ) || trim( $response_text ) === '' ) {
			throw new \Exception( esc_html__( 'Empty response from AI. Check Settings → Connectors.', 'seo-booster' ) );
		}

		$fields_needed = array( 'title', 'description' );
		if ( self::VARIANT_BULK === $variant && isset( $options['fields_needed'] ) && is_array( $options['fields_needed'] ) ) {
			$needed = array_values( array_intersect( $options['fields_needed'], array( 'title', 'description' ) ) );
			if ( ! empty( $needed ) ) {
				$fields_needed = $needed;
			}
		}

		$result = $this->parse_response( $response_text, $fields_needed );
		$this->store_results( $post_id, $result, $language );
		Utils::log( 'Created SEO suggestions for post ID ' . $post_id . ' using WordPress Connectors.', 5 );

		return $result;
	}

	/**
	 * Run wp_ai_client_prompt with a configurable HTTP timeout.
	 *
	 * WordPress 7 defaults to 30 seconds; bulk Tools requests use a longer window.
	 * System instruction stays English (technical JSON constraint) so admin UI
	 * locale does not fight the post content language in the user prompt.
	 *
	 * @param string $prompt  User prompt.
	 * @param int    $post_id Post ID (for filters).
	 * @param string $variant Prompt variant (full|bulk).
	 * @param array  $options Caller options (may include timeout).
	 * @return string|WP_Error
	 */
	private function execute_ai_prompt( $prompt, $post_id, $variant, array $options ) {
		$default_timeout = 30.0;
		if ( self::VARIANT_BULK === $variant ) {
			$default_timeout = self::BULK_REQUEST_TIMEOUT;
		} elseif ( self::VARIANT_ASSISTANT === $variant ) {
			$default_timeout = self::ASSISTANT_REQUEST_TIMEOUT;
		}
		if ( isset( $options['timeout'] ) && is_numeric( $options['timeout'] ) ) {
			$default_timeout = (float) $options['timeout'];
		}

		/**
		 * Filters the HTTP timeout in seconds for SEO Booster AI requests.
		 *
		 * @since 7.2.2
		 *
		 * @param float  $timeout  Timeout in seconds.
		 * @param int    $post_id  Post ID.
		 * @param string $variant  Prompt variant (full|bulk|assistant).
		 */
		$timeout = (float) apply_filters( 'seobooster_ai_request_timeout', $default_timeout, $post_id, $variant );

		$builder = LLM_Helper::ai_prompt( $prompt )
			->using_system_instruction( 'You are an SEO expert. Return only valid JSON with no surrounding text or markdown.' );

		if ( class_exists( RequestOptions::class ) && $timeout > 0 ) {
			$builder = $builder->using_request_options(
				RequestOptions::fromArray(
					array(
						RequestOptions::KEY_TIMEOUT => $timeout,
					)
				)
			);
		}

		$source = isset( $options['source'] ) ? (string) $options['source'] : 'seo-metabox';
		if ( self::VARIANT_ASSISTANT === $variant && empty( $options['source'] ) ) {
			$source = 'page-assistant';
		} elseif ( self::VARIANT_BULK === $variant && empty( $options['source'] ) ) {
			$source = 'bulk-meta';
		}

		return LLM_Helper::generate_ai_text( $builder, $source );
	}

	/**
	 * Build a lighter prompt for bulk meta generation (one title and/or description).
	 *
	 * @param int    $post_id           Post ID.
	 * @param string $condensed_content Condensed content.
	 * @param string $language          Language code.
	 * @param array  $options           fields_needed, gsc_limit.
	 * @return string Formatted prompt.
	 */
	private function build_bulk_meta_prompt( $post_id, $condensed_content, $language, array $options = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( esc_html__( 'Post not found', 'seo-booster' ) );
		}

		$fields_needed = isset( $options['fields_needed'] ) && is_array( $options['fields_needed'] )
			? array_values( array_intersect( $options['fields_needed'], array( 'title', 'description' ) ) )
			: array( 'title', 'description' );

		if ( empty( $fields_needed ) ) {
			$fields_needed = array( 'title', 'description' );
		}

		$gsc_limit      = isset( $options['gsc_limit'] ) ? max( 1, (int) $options['gsc_limit'] ) : 10;
		$gsc_keywords   = LLM_Helper::ensure_utf8_array( $this->get_gsc_keywords( $post_id, $gsc_limit, true ) );
		$focus_keywords = LLM_Helper::ensure_utf8_array( $this->get_focus_keywords( $post_id ) );

		// A cluster of related Search Console queries this page already ranks
		// for. These take priority as inspiration, but the AI is told to choose
		// the best representatives rather than stuff every variation.
		$seed_queries = array();
		if ( ! empty( $options['seed_queries'] ) && is_array( $options['seed_queries'] ) ) {
			foreach ( $options['seed_queries'] as $seed_query ) {
				$seed_query = sanitize_text_field( (string) $seed_query );
				if ( '' !== $seed_query ) {
					$seed_queries[] = LLM_Helper::ensure_utf8( $seed_query );
				}
			}
			$seed_queries = array_values( array_unique( $seed_queries ) );
		}

		if ( ! empty( $options['focus_seed_query'] ) ) {
			$seed = sanitize_text_field( (string) $options['focus_seed_query'] );
			if ( '' !== $seed ) {
				array_unshift( $seed_queries, LLM_Helper::ensure_utf8( $seed ) );
				$seed_queries = array_values( array_unique( $seed_queries ) );
			}
		}

		if ( ! empty( $seed_queries ) ) {
			$focus_keywords = array_values( array_unique( array_merge( $seed_queries, $focus_keywords ) ) );
			$gsc_keywords   = array_values( array_unique( array_merge( $seed_queries, $gsc_keywords ) ) );
		}
		$description   = LLM_Helper::ensure_utf8( $this->get_post_description( $post ) );
		$language_name = LLM_Helper::get_language_name( $language );
		$current_meta  = Google_API::get_seo_title_and_description( $post_id );
		$current_title = LLM_Helper::ensure_utf8( isset( $current_meta['title'] ) ? (string) $current_meta['title'] : '' );
		$current_desc  = LLM_Helper::ensure_utf8( isset( $current_meta['description'] ) ? (string) $current_meta['description'] : '' );
		$post_title    = LLM_Helper::ensure_utf8( $post->post_title );

		$task_lines = array();
		if ( in_array( 'title', $fields_needed, true ) ) {
			$task_lines[] = '- SEO title: 55–80 characters, compelling, keyword-aware.';
		}
		if ( in_array( 'description', $fields_needed, true ) ) {
			$task_lines[] = '- Meta description: 150–175 characters, encourages clicks.';
		}

		$json_lines = array();
		if ( in_array( 'title', $fields_needed, true ) ) {
			$json_lines[] = '  "title": "SEO title here"';
		}
		if ( in_array( 'description', $fields_needed, true ) ) {
			$json_lines[] = '  "description": "Meta description here"';
		}

		$focus_line = '';
		if ( ! empty( $focus_keywords ) ) {
			$focus_line = '- Prefer focus keyword(s) near the start: ' . implode( ', ', $focus_keywords ) . "\n";
		}

		$cluster_block = '';
		if ( ! empty( $seed_queries ) ) {
			$cluster_list   = array_slice( $seed_queries, 0, 15 );
			$cluster_block  = "Search Console queries this page already ranks for (most important first):\n- "
				. implode( "\n- ", $cluster_list ) . "\n";
			$cluster_block .= "- These queries are usually variations of the same intent. Choose the 1–2 most representative for the title and weave in others only where they read naturally. Do NOT cram every keyword in, and do NOT write generic or templated copy. Write naturally for humans.\n";
		}

		$cannibal_block = $this->build_cannibalization_prompt_block( $post_id );

		$existing_meta_block = '';
		if ( '' !== $current_title || '' !== $current_desc ) {
			$existing_meta_block = sprintf(
				"Current SEO title: %s\nCurrent meta description: %s\n",
				'' !== $current_title ? $current_title : '(empty)',
				'' !== $current_desc ? $current_desc : '(empty)'
			);
		}

		$generate_what = 1 === count( $fields_needed )
			? ( 'one SEO ' . $fields_needed[0] )
			: 'one SEO title and one meta description';

		$prompt = sprintf(
			'Write %s for this published %s in %s (locale: %s).

Page title: %s
Excerpt/summary: %s
Content: %s
Focus keywords: %s
GSC keywords: %s
URL: %s
%s%s%sRequirements:
%s
- Write ONLY in %s.
%sReturn ONLY this JSON (no markdown):
{
%s
}',
			$generate_what,
			$post->post_type,
			$language_name,
			$language,
			$post_title,
			$description,
			$condensed_content,
			implode( ', ', $focus_keywords ),
			implode( ', ', $gsc_keywords ),
			get_permalink( $post_id ),
			$existing_meta_block,
			$cluster_block,
			$cannibal_block,
			implode( "\n", $task_lines ),
			$language_name,
			$focus_line,
			implode( ",\n", $json_lines )
		);

		return $prompt;
	}

	/**
	 * Build the prompt for the AI (metabox full suggestions).
	 *
	 * @param int    $post_id                 Post ID.
	 * @param string $condensed_content       Condensed content.
	 * @param string $language                Language code.
	 * @param string $local_analysis_summary  Optional local analysis summary.
	 * @return string Formatted prompt.
	 */
	private function build_prompt( $post_id, $condensed_content, $language, $local_analysis_summary = '' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( esc_html__( 'Post not found', 'seo-booster' ) );
		}

		$seo_plugin_data = Google_API::get_seo_title_and_description( $post_id );
		$categories      = LLM_Helper::ensure_utf8_array( wp_get_post_categories( $post_id, array( 'fields' => 'names' ) ) );
		$tags            = LLM_Helper::ensure_utf8_array( wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ) );
		$gsc_keywords    = LLM_Helper::ensure_utf8_array( $this->get_gsc_keywords( $post_id, 10, true ) );
		$focus_keywords  = LLM_Helper::ensure_utf8_array( $this->get_focus_keywords( $post_id ) );
		$description     = LLM_Helper::ensure_utf8( $this->get_post_description( $post ) );
		$language_name   = LLM_Helper::get_language_name( $language );
		$post_title      = LLM_Helper::ensure_utf8( $post->post_title );
		$current_title   = LLM_Helper::ensure_utf8( isset( $seo_plugin_data['title'] ) ? (string) $seo_plugin_data['title'] : '' );
		$current_desc    = LLM_Helper::ensure_utf8( isset( $seo_plugin_data['description'] ) ? (string) $seo_plugin_data['description'] : '' );
		$count           = self::FULL_SUGGESTION_COUNT;

		$focus_keyword_instructions = '';
		if ( ! empty( $focus_keywords ) ) {
			$focus_keyword_list         = implode( ', ', $focus_keywords );
			$focus_keyword_instructions = sprintf(
				"\n### FOCUS KEYWORD REQUIREMENTS:\n- The following focus keyword(s) have been set: %s\n- These focus keyword(s) MUST be included in most titles and meta descriptions following best SEO guidelines\n- Place focus keywords naturally, preferably near the beginning of titles when possible\n- Maintain readability and avoid keyword stuffing\n- While most suggestions should include the focus keyword(s), some variations may omit them for creative diversity\n- Follow SEO best practices: ensure keywords flow naturally and enhance rather than detract from the user experience",
				$focus_keyword_list
			);
		}

		$existing_meta_block = '';
		if ( '' !== $current_title || '' !== $current_desc ) {
			$existing_meta_block = sprintf(
				"\nCurrent SEO title: %s\nCurrent meta description: %s",
				'' !== $current_title ? $current_title : '(empty)',
				'' !== $current_desc ? $current_desc : '(empty)'
			);
		}

		$cannibal_block = $this->build_cannibalization_prompt_block( $post_id );

		$title_lines = array();
		$desc_lines  = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$title_lines[] = '    "Optimized title ' . $i . '"';
			$desc_lines[]  = '    "Meta description ' . $i . '"';
		}

		$prompt = sprintf(
			'Act as an SEO expert. You are an expert SEO specialist with deep knowledge of search engine optimization, content marketing, and user engagement. Your task is to analyze the provided content and generate high-quality SEO suggestions that will improve search rankings and click-through rates.

### CONTENT TO ANALYZE:
Title: %s
Description: %s
Content: %s
Categories: %s
Tags: %s
Target Keywords: %s
GSC Keywords (ranked by traffic; prefer these): %s
URL: %s
Post Type: %s
Post Language: %s (locale: %s)%s%s%s

### TASK:
Generate %d optimized SEO title suggestions and %d meta description suggestions in %s that will:
1. Improve search engine rankings
2. Increase click-through rates from search results
3. Accurately represent the content
4. Include relevant keywords naturally
5. Appeal to the target audience
6. Try to keep the focus keyword if set in front of the title and early in the description.
7. Differ from the current SEO title and meta description when those fields are already set.
### OUTPUT FORMAT:
Return your suggestions in this exact JSON format. All content must be in %s:
{
  "titles": [
%s
  ],
  "descriptions": [
%s
  ],
  "keywords": [
    "primary keyword 1",
    "secondary keyword 2",
    "long-tail keyword 3",
    "semantic keyword 4",
    "LSI keyword 5"
  ]
}

### LANGUAGE REQUIREMENT (CRITICAL - MUST FOLLOW):
- The post language is: %s (locale: %s)
- ALL titles, descriptions, and keywords MUST be written in %s
- Do NOT use English or any other language unless the post language itself is English
- The language is explicitly set to %s - you MUST respect this
- If you return content in the wrong language, the suggestions will be unusable
- Write naturally in %s as a native speaker would
- This is the MOST IMPORTANT requirement - language accuracy is critical

### CRITICAL REQUIREMENTS:
- Titles must be 55-80 characters (do NOT include character count in output)
- Meta descriptions must be 150-175 characters (do NOT include character count in output)
- Make titles compelling and click-worthy while including primary keywords
- Vary the structure and opening words of titles and descriptions to avoid repetition
- Create descriptions that encourage clicks and clearly communicate value
- Use the traffic-ranked GSC keywords as inspiration for titles and descriptions
- Suggest additional relevant keywords that could drive new traffic
- Focus on user intent and search behavior
- Ensure all suggestions are unique and offer different value propositions
- Do NOT suggest URL changes (content is already published)
- Content may be condensed but contains sufficient information for analysis

### QUALITY STANDARDS:
- Each title should have a different approach (question, benefit, urgency, etc.)
- Descriptions should highlight different aspects or benefits
- Keywords should be relevant and searchable
- All suggestions must be original and not duplicate existing content
- Prioritize user experience and search engine guidelines

Return ONLY the JSON object with no additional text, explanations, or formatting.',
			$post_title,
			$description,
			$condensed_content,
			implode( ', ', $categories ),
			implode( ', ', $tags ),
			implode( ', ', $focus_keywords ),
			implode( ', ', $gsc_keywords ),
			get_permalink( $post_id ),
			$post->post_type,
			$language_name,
			$language,
			$focus_keyword_instructions,
			$existing_meta_block,
			$cannibal_block,
			$count,
			$count,
			$language_name,
			$language_name,
			implode( ",\n", $title_lines ),
			implode( ",\n", $desc_lines ),
			$language_name,
			$language,
			$language_name,
			$language_name,
			$language_name
		);

		if ( ! empty( $local_analysis_summary ) ) {
			$prompt .= "\n### EXISTING SEO ANALYSIS (from this site):\n" . LLM_Helper::ensure_utf8( $local_analysis_summary ) . "\n";
		}

		return $prompt;
	}

	/**
	 * Build a compact cannibalization avoid-list for AI prompts.
	 *
	 * Lists queries where another page leads on clicks, with the winning URL
	 * (and title when resolvable). Empty string when nothing relevant.
	 *
	 * @param int $post_id Post ID.
	 * @return string Prompt block or empty string.
	 */
	private function build_cannibalization_prompt_block( $post_id ) {
		$items = $this->get_cannibalization_avoid_list( $post_id, 5 );
		if ( empty( $items ) ) {
			return '';
		}

		$lines = array();
		foreach ( $items as $item ) {
			$line = '- "' . $item['query'] . '" → prefer ' . $item['leader_url'];
			if ( ! empty( $item['leader_title'] ) ) {
				$line .= ' ("' . $item['leader_title'] . '")';
			}
			$lines[] = $line;
		}

		return "\n### KEYWORD CANNIBALIZATION (do not target as primary):\n"
			. "Other pages already lead for these queries. Do NOT make them the primary focus of titles/descriptions for this page; differentiate this page's angle instead.\n"
			. implode( "\n", $lines ) . "\n";
	}

	/**
	 * Queries where this page competes but is not the click leader.
	 *
	 * @param int $post_id Post ID.
	 * @param int $limit   Max items.
	 * @return array<int, array{query: string, leader_url: string, leader_title: string}>
	 */
	private function get_cannibalization_avoid_list( $post_id, $limit = 5 ) {
		global $wpdb;

		$url = get_permalink( $post_id );
		if ( ! $url ) {
			return array();
		}

		$limit = max( 1, (int) $limit );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only GSC aggregate for AI context.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					qk.query,
					qk.page,
					COALESCE(SUM(qkh.clicks), 0) as clicks,
					COALESCE(SUM(qkh.impressions), 0) as impressions
				FROM {$wpdb->prefix}sb2_query_keywords AS qk
				LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS qkh
					ON qk.id = qkh.query_keywords_id
					AND qkh.date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
				WHERE qk.query IN (
					SELECT DISTINCT query
					FROM {$wpdb->prefix}sb2_query_keywords
					WHERE page = %s
				)
				GROUP BY qk.query, qk.page
				ORDER BY impressions DESC, clicks DESC
				LIMIT 80",
				(int) GSC_History::INSIGHT_WINDOW_DAYS,
				$url
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$by_query = array();
		foreach ( $rows as $row ) {
			$query_text = LLM_Helper::ensure_utf8( (string) $row['query'] );
			$page       = (string) $row['page'];
			if ( '' === $query_text || '' === $page ) {
				continue;
			}
			if ( ! isset( $by_query[ $query_text ] ) ) {
				$by_query[ $query_text ] = array();
			}
			$by_query[ $query_text ][] = array(
				'page'        => $page,
				'clicks'      => (int) $row['clicks'],
				'impressions' => (int) $row['impressions'],
			);
		}

		$avoid = array();
		foreach ( $by_query as $query_text => $pages ) {
			if ( count( $pages ) < 2 ) {
				continue;
			}

			$has_current = false;
			$leader      = null;
			foreach ( $pages as $page_data ) {
				if ( $page_data['page'] === $url ) {
					$has_current = true;
				}
				if ( null === $leader
					|| $page_data['clicks'] > $leader['clicks']
					|| ( $page_data['clicks'] === $leader['clicks'] && $page_data['impressions'] > $leader['impressions'] )
				) {
					$leader = $page_data;
				}
			}

			if ( ! $has_current || ! $leader || $leader['page'] === $url ) {
				continue;
			}

			$leader_title = '';
			$leader_id    = url_to_postid( $leader['page'] );
			if ( $leader_id ) {
				$leader_title = LLM_Helper::ensure_utf8( get_the_title( $leader_id ) );
			}

			$avoid[] = array(
				'query'        => $query_text,
				'leader_url'   => LLM_Helper::ensure_utf8( $leader['page'] ),
				'leader_title' => $leader_title,
				'impressions'  => $leader['impressions'],
			);
		}

		usort(
			$avoid,
			static function ( $a, $b ) {
				return $b['impressions'] <=> $a['impressions'];
			}
		);

		$avoid = array_slice( $avoid, 0, $limit );

		return array_map(
			static function ( $item ) {
				return array(
					'query'        => $item['query'],
					'leader_url'   => $item['leader_url'],
					'leader_title' => $item['leader_title'],
				);
			},
			$avoid
		);
	}

	/**
	 * Answer a page-scoped assistant question with optional actions and meta suggestions.
	 *
	 * @since 7.4.0
	 * @param int    $post_id                 Post ID.
	 * @param string $question                User question.
	 * @param string $condensed_content       Condensed content.
	 * @param string $language                Locale / language code.
	 * @param string $local_analysis_summary  Optional analysis summary.
	 * @param array  $options                 Optional timeout.
	 * @return array{answer: string, titles: array, descriptions: array, actions: array, status: string}
	 * @throws \Exception If API call fails or response is invalid.
	 */
	public function generate_assistant_reply( $post_id, $question, $condensed_content, $language, $local_analysis_summary = '', array $options = array() ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			throw new \Exception( esc_html__( 'WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster' ) );
		}

		if ( ! LLM_Helper::wp_ai_is_available() ) {
			throw new \Exception( esc_html( LLM_Helper::wp_ai_unavailable_message() ) );
		}

		$question               = LLM_Helper::ensure_utf8( sanitize_text_field( (string) $question ) );
		$condensed_content      = LLM_Helper::ensure_utf8( $condensed_content );
		$local_analysis_summary = LLM_Helper::ensure_utf8( $local_analysis_summary );

		if ( '' === $question ) {
			throw new \Exception( esc_html__( 'Please enter a question.', 'seo-booster' ) );
		}

		$prompt = $this->build_assistant_prompt( $post_id, $question, $condensed_content, $language, $local_analysis_summary );
		$prompt = apply_filters( 'seobooster_ai_assistant_prompt', $prompt, $post_id, $question, $language );
		$prompt = LLM_Helper::ensure_utf8( $prompt );

		$response_text = $this->execute_ai_prompt( $prompt, $post_id, self::VARIANT_ASSISTANT, $options );

		if ( is_wp_error( $response_text ) ) {
			Utils::log( 'WP Connector AI assistant: ' . $response_text->get_error_message(), 2 );
			throw new \Exception( esc_html( $response_text->get_error_message() ) );
		}

		if ( ! is_string( $response_text ) || trim( $response_text ) === '' ) {
			throw new \Exception( esc_html__( 'Empty response from AI. Check Settings → Connectors.', 'seo-booster' ) );
		}

		$result = $this->parse_assistant_response( $response_text );

		if ( ! empty( $result['titles'] ) || ! empty( $result['descriptions'] ) ) {
			// Keep shared suggestion store in sync even when only one field list is filled.
			$store = $result;
			if ( empty( $store['titles'] ) ) {
				$store['titles'] = array();
			}
			if ( empty( $store['descriptions'] ) ) {
				$store['descriptions'] = array();
			}
			$this->store_results( $post_id, $store, $language );
		}

		Utils::log( 'AI page assistant replied for post ID ' . (int) $post_id . '.', 5 );

		return $result;
	}

	/**
	 * Build the page assistant prompt.
	 *
	 * @param int    $post_id                 Post ID.
	 * @param string $question                User question.
	 * @param string $condensed_content       Condensed content.
	 * @param string $language                Locale.
	 * @param string $local_analysis_summary  Analysis summary.
	 * @return string
	 */
	private function build_assistant_prompt( $post_id, $question, $condensed_content, $language, $local_analysis_summary ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \Exception( esc_html__( 'Post not found', 'seo-booster' ) );
		}

		$seo_plugin_data = Google_API::get_seo_title_and_description( $post_id );
		$gsc_keywords    = LLM_Helper::ensure_utf8_array( $this->get_gsc_keywords( $post_id, 15, true ) );
		$focus_keywords  = LLM_Helper::ensure_utf8_array( $this->get_focus_keywords( $post_id ) );
		$language_name   = LLM_Helper::get_language_name( $language );
		$post_title      = LLM_Helper::ensure_utf8( $post->post_title );
		$current_title   = LLM_Helper::ensure_utf8( isset( $seo_plugin_data['title'] ) ? (string) $seo_plugin_data['title'] : '' );
		$current_desc    = LLM_Helper::ensure_utf8( isset( $seo_plugin_data['description'] ) ? (string) $seo_plugin_data['description'] : '' );
		$permalink       = get_permalink( $post_id );
		$cannibal_block  = $this->build_cannibalization_prompt_block( $post_id );

		$analysis_block = '';
		if ( '' !== $local_analysis_summary ) {
			$analysis_block = "\n### SEO ANALYSIS\n" . $local_analysis_summary . "\n";
		}

		return sprintf(
			'You are an SEO assistant for one WordPress page. Answer the editor\'s question using ONLY the context below. Be concise and practical.

### PAGE CONTEXT
Title: %s
Post type: %s
URL: %s
Language: %s (locale: %s)
Current SEO title: %s
Current meta description: %s
Focus keywords: %s
GSC keywords (by traffic): %s
Content summary: %s
%s%s
### USER QUESTION
%s

### OUTPUT (JSON only)
{
  "answer": "Plain-text answer in %s. Use short paragraphs or bullet lines with - . No markdown code fences.",
  "titles": [],
  "descriptions": [],
  "actions": []
}

Rules:
- Always include a non-empty "answer" string in %s.
- Fill "titles" and "descriptions" (3–7 each) only when the question asks to rewrite or suggest SEO title/meta; otherwise use empty arrays.
- "actions" is an array of objects. Allowed types only:
  - {"type":"set_focus_keyword","label":"…","payload":{"keyword":"…"}}
  - {"type":"create_autolink","label":"…","payload":{"keyword":"…","target_url":"%s"}}
- Propose actions only when clearly useful for the question. Prefer target_url = this page URL for create_autolink.
- Do not invent GSC data that is not listed above.
- Write answer (and any titles/descriptions) in %s.',
			$post_title,
			$post->post_type,
			$permalink ? $permalink : '',
			$language_name,
			$language,
			'' !== $current_title ? $current_title : '(empty)',
			'' !== $current_desc ? $current_desc : '(empty)',
			! empty( $focus_keywords ) ? implode( ', ', $focus_keywords ) : '(none)',
			! empty( $gsc_keywords ) ? implode( ', ', $gsc_keywords ) : '(none)',
			$condensed_content,
			$analysis_block,
			$cannibal_block,
			$question,
			$language_name,
			$language_name,
			$permalink ? $permalink : '',
			$language_name
		);
	}

	/**
	 * Parse assistant JSON response.
	 *
	 * @param string $response_text Raw AI text.
	 * @return array{answer: string, titles: array, descriptions: array, actions: array, status: string, keywords: array}
	 * @throws \Exception On invalid JSON or missing answer.
	 */
	private function parse_assistant_response( $response_text ) {
		if ( ! is_string( $response_text ) ) {
			throw new \Exception( esc_html__( 'Invalid AI response format', 'seo-booster' ) );
		}

		$response_text = preg_replace( '#^```(?:json)?\s*|\s*```$#', '', trim( $response_text ) );
		$result_data   = json_decode( $response_text, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $result_data ) ) {
			Utils::log( 'WP Connector AI assistant: Invalid JSON - ' . substr( $response_text, 0, 500 ), 2 );
			throw new \Exception( esc_html__( 'Unable to parse AI response content', 'seo-booster' ) );
		}

		$answer = isset( $result_data['answer'] ) ? LLM_Helper::ensure_utf8( (string) $result_data['answer'] ) : '';
		$answer = trim( $answer );
		if ( '' === $answer ) {
			throw new \Exception( esc_html__( 'Invalid response: missing answer', 'seo-booster' ) );
		}

		$titles = array();
		if ( isset( $result_data['titles'] ) && is_array( $result_data['titles'] ) ) {
			foreach ( $result_data['titles'] as $title ) {
				$title = LLM_Helper::ensure_utf8( sanitize_text_field( (string) $title ) );
				if ( '' !== $title ) {
					$titles[] = $title;
				}
			}
		}

		$descriptions = array();
		if ( isset( $result_data['descriptions'] ) && is_array( $result_data['descriptions'] ) ) {
			foreach ( $result_data['descriptions'] as $description ) {
				$description = LLM_Helper::ensure_utf8( sanitize_textarea_field( (string) $description ) );
				if ( '' !== $description ) {
					$descriptions[] = $description;
				}
			}
		}

		$actions = $this->sanitize_assistant_actions(
			isset( $result_data['actions'] ) && is_array( $result_data['actions'] ) ? $result_data['actions'] : array()
		);

		return array(
			'answer'       => $answer,
			'titles'       => $titles,
			'descriptions' => $descriptions,
			'actions'      => $actions,
			'keywords'     => array(),
			'status'       => 'completed',
		);
	}

	/**
	 * Sanitize action objects from the assistant.
	 *
	 * @param array $raw Raw actions.
	 * @return array<int, array{type: string, label: string, payload: array}>
	 */
	private function sanitize_assistant_actions( array $raw ) {
		$allowed = array( 'set_focus_keyword', 'create_autolink' );
		$out     = array();

		foreach ( array_slice( $raw, 0, 8 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '';
			if ( ! in_array( $type, $allowed, true ) ) {
				continue;
			}
			$label   = isset( $item['label'] ) ? LLM_Helper::ensure_utf8( sanitize_text_field( (string) $item['label'] ) ) : '';
			$payload = isset( $item['payload'] ) && is_array( $item['payload'] ) ? $item['payload'] : array();

			$clean_payload = array();
			if ( 'set_focus_keyword' === $type ) {
				$keyword = isset( $payload['keyword'] ) ? LLM_Helper::ensure_utf8( sanitize_text_field( (string) $payload['keyword'] ) ) : '';
				if ( '' === $keyword ) {
					continue;
				}
				$clean_payload['keyword'] = $keyword;
				if ( '' === $label ) {
					$label = sprintf(
						/* translators: %s: focus keyword */
						__( 'Set focus keyword: %s', 'seo-booster' ),
						$keyword
					);
				}
			} elseif ( 'create_autolink' === $type ) {
				$keyword    = isset( $payload['keyword'] ) ? LLM_Helper::ensure_utf8( sanitize_text_field( (string) $payload['keyword'] ) ) : '';
				$target_url = isset( $payload['target_url'] ) ? esc_url_raw( (string) $payload['target_url'] ) : '';
				if ( '' === $keyword || '' === $target_url ) {
					continue;
				}
				$clean_payload['keyword']    = $keyword;
				$clean_payload['target_url'] = LLM_Helper::ensure_utf8( $target_url );
				if ( '' === $label ) {
					$label = sprintf(
						/* translators: %s: keyword phrase */
						__( 'Create autolink: %s', 'seo-booster' ),
						$keyword
					);
				}
			}

			$out[] = array(
				'type'    => $type,
				'label'   => $label,
				'payload' => $clean_payload,
			);
		}

		return $out;
	}

	/**
	 * Parse AI response text into titles, descriptions, keywords.
	 *
	 * @param string $response_text Raw response from generate_text().
	 * @param array  $fields_needed Which fields to require (title, description).
	 * @return array Parsed result.
	 * @throws \Exception If response format is invalid.
	 */
	private function parse_response( $response_text, array $fields_needed = array( 'title', 'description' ) ) {
		if ( is_wp_error( $response_text ) ) {
			throw new \Exception( esc_html( $response_text->get_error_message() ) );
		}

		if ( ! is_string( $response_text ) ) {
			throw new \Exception( esc_html__( 'Invalid AI response format', 'seo-booster' ) );
		}

		// Strip possible markdown code blocks.
		$response_text = preg_replace( '#^```(?:json)?\s*|\s*```$#', '', trim( $response_text ) );
		$result_data   = json_decode( $response_text, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Utils::log( 'WP Connector AI: Invalid JSON in response - ' . substr( $response_text, 0, 500 ), 2 );
			throw new \Exception( esc_html__( 'Unable to parse AI response content', 'seo-booster' ) );
		}

		$titles       = isset( $result_data['titles'] ) && is_array( $result_data['titles'] ) ? $result_data['titles'] : array();
		$descriptions = isset( $result_data['descriptions'] ) && is_array( $result_data['descriptions'] ) ? $result_data['descriptions'] : array();
		$keywords     = isset( $result_data['keywords'] ) && is_array( $result_data['keywords'] ) ? $result_data['keywords'] : array();

		if ( empty( $titles ) && ! empty( $result_data['title'] ) && is_string( $result_data['title'] ) ) {
			$titles = array( $result_data['title'] );
		}
		if ( empty( $descriptions ) && ! empty( $result_data['description'] ) && is_string( $result_data['description'] ) ) {
			$descriptions = array( $result_data['description'] );
		}

		$need_title       = in_array( 'title', $fields_needed, true );
		$need_description = in_array( 'description', $fields_needed, true );

		if ( ( $need_title && empty( $titles ) ) || ( $need_description && empty( $descriptions ) ) ) {
			throw new \Exception( esc_html__( 'Invalid response: missing titles or descriptions', 'seo-booster' ) );
		}

		return array(
			'titles'       => $titles,
			'descriptions' => $descriptions,
			'keywords'     => $keywords,
			'status'       => 'completed',
		);
	}

	/**
	 * Store results in post meta.
	 *
	 * @param int    $post_id  Post ID.
	 * @param array  $result   Parsed result.
	 * @param string $language Language code.
	 */
	private function store_results( $post_id, $result, $language ) {
		$compact_result = array(
			'titles'       => $result['titles'],
			'descriptions' => $result['descriptions'],
			'status'       => $result['status'],
			'language'     => $language,
			'timestamp'    => time(),
			'provider'     => 'wordpress',
		);
		update_post_meta( $post_id, '_llm_seo_last_result', $compact_result );
	}

	/**
	 * Fetch GSC queries for a post.
	 *
	 * @param int  $post_id           Post ID.
	 * @param int  $limit             Max queries.
	 * @param bool $order_by_traffic  When true, order by clicks/impressions.
	 * @return array<int, string>
	 */
	private function get_gsc_keywords( $post_id, $limit = 20, $order_by_traffic = false ) {
		global $wpdb;
		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			return array();
		}

		$limit = max( 1, (int) $limit );

		if ( $order_by_traffic ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only GSC lookup for AI prompt.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT k.query
                 FROM {$wpdb->prefix}sb2_query_keywords AS k
                 LEFT JOIN {$wpdb->prefix}sb2_query_keywords_history AS h ON k.id = h.query_keywords_id
                 WHERE k.page = %s
                 AND CHAR_LENGTH(k.query) >= 3
                 GROUP BY k.id, k.query
                 ORDER BY COALESCE(SUM(h.clicks), 0) DESC, COALESCE(SUM(h.impressions), 0) DESC
                 LIMIT %d",
					$permalink,
					$limit
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only GSC lookup for AI prompt.
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT k.query FROM {$wpdb->prefix}sb2_query_keywords AS k
                 WHERE k.page = %s
                 AND CHAR_LENGTH(k.query) >= 3
                 ORDER BY CHAR_LENGTH(k.query) DESC
                 LIMIT %d",
					$permalink,
					$limit
				),
				ARRAY_A
			);
		}

		return array_column( $results ? $results : array(), 'query' );
	}

	private function get_focus_keywords( $post_id ) {
		$plugin_keywords = Google_API::get_focus_keywords( $post_id );
		return is_array( $plugin_keywords ) ? $plugin_keywords : array();
	}

	private function get_post_description( $post ) {
		if ( ! empty( $post->post_excerpt ) ) {
			return $post->post_excerpt;
		}
		$content = wp_strip_all_tags( $post->post_content );
		return wp_trim_words( $content, 25, '...' );
	}

	/**
	 * Answer a site-scoped dashboard assistant question.
	 *
	 * @since 7.4.0
	 * @param string $question User question.
	 * @param array  $brief    Site brief from AI_Site_Assistant::build_site_brief().
	 * @param string $language Locale / language code.
	 * @param string $intent   Detected intent key.
	 * @param array  $options  Optional timeout.
	 * @return array{answer: string, confidence: string, items: array, next_steps: array, unsupported: bool, feature_idea: string, status: string}
	 * @throws \Exception If API call fails or response is invalid.
	 */
	public function generate_site_assistant_reply( $question, array $brief, $language, $intent = 'prioritize', array $options = array() ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			throw new \Exception( esc_html__( 'WordPress AI is not available. Use WordPress 7 or later and configure Settings → Connectors.', 'seo-booster' ) );
		}

		if ( ! LLM_Helper::wp_ai_is_available() ) {
			throw new \Exception( esc_html( LLM_Helper::wp_ai_unavailable_message() ) );
		}

		$question = LLM_Helper::ensure_utf8( sanitize_text_field( (string) $question ) );
		if ( '' === $question ) {
			throw new \Exception( esc_html__( 'Please enter a question.', 'seo-booster' ) );
		}

		$language = is_string( $language ) && '' !== $language ? $language : get_locale();
		$intent   = sanitize_key( (string) $intent );
		$prompt   = $this->build_site_assistant_prompt( $question, $brief, $language, $intent );
		$prompt   = apply_filters( 'seobooster_ai_site_assistant_prompt', $prompt, $question, $language, $intent );
		$prompt   = LLM_Helper::ensure_utf8( $prompt );

		$options['source'] = 'site-assistant';
		$response_text     = $this->execute_ai_prompt( $prompt, 0, self::VARIANT_ASSISTANT, $options );

		if ( is_wp_error( $response_text ) ) {
			Utils::log( 'WP Connector site assistant: ' . $response_text->get_error_message(), 2 );
			throw new \Exception( esc_html( $response_text->get_error_message() ) );
		}

		if ( ! is_string( $response_text ) || trim( $response_text ) === '' ) {
			throw new \Exception( esc_html__( 'Empty response from AI. Check Settings → Connectors.', 'seo-booster' ) );
		}

		$result = $this->parse_site_assistant_response( $response_text );
		Utils::log( 'AI site assistant replied (intent ' . $intent . ').', 5 );

		return $result;
	}

	/**
	 * Build the site assistant prompt.
	 *
	 * @param string $question User question.
	 * @param array  $brief    Site brief.
	 * @param string $language Locale.
	 * @param string $intent   Intent key.
	 * @return string
	 */
	private function build_site_assistant_prompt( $question, array $brief, $language, $intent ) {
		$language_name = LLM_Helper::get_language_name( $language );
		$brief_json    = wp_json_encode( $brief, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $brief_json ) ) {
			$brief_json = '{}';
		}

		return sprintf(
			'You are an SEO advisor for a WordPress site using the SEO Booster plugin. Answer the admin\'s question.

### DUAL GROUNDING RULES
- Site metrics, rankings, clicks, impressions, positions, and page URLs: use ONLY the SITE BRIEF JSON below. Never invent numbers or URLs.
- Plugin usage and howto: treat the compact handbook inside the brief as the authoritative and complete description of SEO Booster settings, scanner checks, and capabilities. Do NOT state that a setting, scanner check, tool, or capability exists unless it appears in the handbook. If the handbook does not list it, say it is not available or that you are not certain, and point to the documentation or support URL. Give the exact admin path and concise numbered steps when available. Do not mention the handbook itself.
- General SEO concepts (CTR, focus keyword, cannibalization): short explanations are OK; tie back to a concrete next step in SEO Booster when possible.
- You cannot recrawl Google, change hosting, audit competitors, or run Tools automatically. Set unsupported=true for those action requests.
- If data is thin or missing, say so and suggest importing Search Console data or running SEO analysis.

### INTENT
%s

### SITE BRIEF (JSON)
%s

### USER QUESTION
%s

### OUTPUT (JSON only)
{
  "answer": "Plain-text answer in %s. Short paragraphs or bullet lines with - . No markdown code fences.",
  "confidence": "high|medium|low",
  "items": [
    {"url": "https://…", "title": "…", "why": "…", "tool": "issues|focus|meta|image|gsc-opportunities|content-decay|llms|entity-map|settings-ai|none"}
  ],
  "next_steps": ["…"],
  "unsupported": false,
  "feature_idea": ""
}

Rules:
- Always include a non-empty "answer" in %s.
- Prefer 3–8 items when prioritizing site work; for help/howto intents items may be empty.
- Only include URLs that appear in the SITE BRIEF. tool must be one of the allowed keys or none.
- confidence low when data is thin, handbook is silent, or the question is vague.
- unsupported true when asking for actions SEO Booster cannot perform.
- feature_idea: short note only when the user wants a capability the plugin lacks (so we can improve the product).
- Write answer and why/next_steps text in %s.',
			$intent,
			$brief_json,
			$question,
			$language_name,
			$language_name,
			$language_name
		);
	}

	/**
	 * Parse site assistant JSON response.
	 *
	 * @param string $response_text Raw AI text.
	 * @return array{answer: string, confidence: string, items: array, next_steps: array, unsupported: bool, feature_idea: string, status: string}
	 * @throws \Exception On invalid JSON or missing answer.
	 */
	private function parse_site_assistant_response( $response_text ) {
		if ( ! is_string( $response_text ) ) {
			throw new \Exception( esc_html__( 'Invalid AI response format', 'seo-booster' ) );
		}

		$response_text = preg_replace( '#^```(?:json)?\s*|\s*```$#', '', trim( $response_text ) );
		$result_data   = json_decode( $response_text, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $result_data ) ) {
			Utils::log( 'WP Connector site assistant: Invalid JSON - ' . substr( $response_text, 0, 500 ), 2 );
			throw new \Exception( esc_html__( 'Unable to parse AI response content', 'seo-booster' ) );
		}

		$answer = isset( $result_data['answer'] ) ? LLM_Helper::ensure_utf8( (string) $result_data['answer'] ) : '';
		$answer = trim( $answer );
		if ( '' === $answer ) {
			throw new \Exception( esc_html__( 'Invalid response: missing answer', 'seo-booster' ) );
		}

		$confidence = isset( $result_data['confidence'] ) ? sanitize_key( (string) $result_data['confidence'] ) : 'medium';
		if ( ! in_array( $confidence, array( 'high', 'medium', 'low' ), true ) ) {
			$confidence = 'medium';
		}

		$allowed_tools = array(
			'issues',
			'focus',
			'meta',
			'image',
			'gsc-opportunities',
			'content-decay',
			'llms',
			'entity-map',
			'settings-ai',
			'none',
		);

		$items = array();
		if ( isset( $result_data['items'] ) && is_array( $result_data['items'] ) ) {
			foreach ( array_slice( $result_data['items'], 0, 10 ) as $raw ) {
				if ( ! is_array( $raw ) ) {
					continue;
				}
				$url   = isset( $raw['url'] ) ? esc_url_raw( (string) $raw['url'] ) : '';
				$title = isset( $raw['title'] ) ? LLM_Helper::ensure_utf8( sanitize_text_field( (string) $raw['title'] ) ) : '';
				$why   = isset( $raw['why'] ) ? LLM_Helper::ensure_utf8( sanitize_text_field( (string) $raw['why'] ) ) : '';
				$tool  = isset( $raw['tool'] ) ? sanitize_key( str_replace( '_', '-', (string) $raw['tool'] ) ) : 'none';
				if ( ! in_array( $tool, $allowed_tools, true ) ) {
					$tool = 'none';
				}
				if ( '' === $url && '' === $title && '' === $why ) {
					continue;
				}
				$items[] = array(
					'url'   => LLM_Helper::ensure_utf8( $url ),
					'title' => $title,
					'why'   => $why,
					'tool'  => $tool,
				);
			}
		}

		$next_steps = array();
		if ( isset( $result_data['next_steps'] ) && is_array( $result_data['next_steps'] ) ) {
			foreach ( array_slice( $result_data['next_steps'], 0, 6 ) as $step ) {
				$step = LLM_Helper::ensure_utf8( sanitize_text_field( (string) $step ) );
				if ( '' !== $step ) {
					$next_steps[] = $step;
				}
			}
		}

		$feature_idea = isset( $result_data['feature_idea'] ) ? LLM_Helper::ensure_utf8( sanitize_text_field( (string) $result_data['feature_idea'] ) ) : '';

		return array(
			'answer'       => $answer,
			'confidence'   => $confidence,
			'items'        => $items,
			'next_steps'   => $next_steps,
			'unsupported'  => ! empty( $result_data['unsupported'] ),
			'feature_idea' => $feature_idea,
			'status'       => 'completed',
		);
	}
}
