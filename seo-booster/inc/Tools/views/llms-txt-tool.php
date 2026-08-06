<?php

/**
 * llms.txt tool view.
 *
 * @package Cleverplugins\SEOBooster\Tools
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
$pinned_ids = ( isset( $settings['pinned_post_ids'] ) && is_array( $settings['pinned_post_ids'] ) ? array_map( 'absint', $settings['pinned_post_ids'] ) : array() );
$show_entity_map_upsell = true;
if ( function_exists( 'Cleverplugins\\SEOBooster\\seobooster_fs' ) ) {
}
$docs_url = \Cleverplugins\SEOBooster\Utils::generate_cp_web_link( 'tools_llms', '/docs/tools/llms-txt/' );
?>
<div class="sb-tools-tool sb-tools-llms-txt">
	<div class="sb-tools-tool-header">
		<h2><?php 
esc_html_e( 'llms.txt generator', 'seo-booster' );
?></h2>
		<p class="sb-tools-docs-link">
			<a href="<?php 
echo esc_url( $docs_url );
?>" target="_blank" rel="noopener noreferrer">
				<?php 
esc_html_e( 'Documentation', 'seo-booster' );
?>
				<span class="screen-reader-text"><?php 
esc_html_e( '(opens in a new tab)', 'seo-booster' );
?></span>
			</a>
		</p>
	</div>
	<p class="description">
		<?php 
esc_html_e( 'Build a curated llms.txt file for AI crawlers and answer engines. SEO Booster generates the content for you. It does not upload a physical file to your server unless you download and place one yourself.', 'seo-booster' );
?>
	</p>

	<?php 
if ( $show_entity_map_upsell ) {
    ?>
		<div class="notice notice-info inline">
			<p>
				<?php 
    esc_html_e( 'Pro: Publish a structured Entity Map (/entitymap.json + /entitymap.html) so AI systems understand your organization and content relationships, beyond the llms.txt index.', 'seo-booster' );
    ?>
				<a href="<?php 
    echo esc_url( admin_url( 'admin.php?page=sb2_tools&tab=entity-map' ) );
    ?>"><?php 
    esc_html_e( 'See Entity Map', 'seo-booster' );
    ?></a>
			</p>
		</div>
	<?php 
}
?>

	<?php 
if ( !empty( $physical_status['exists'] ) ) {
    ?>
		<div class="notice notice-warning inline sb-tools-llms-physical-notice">
			<p>
				<?php 
    printf( 
        /* translators: %s: absolute file path */
        esc_html__( 'A physical llms.txt file already exists at %s. Many hosts serve that file directly and will ignore WordPress dynamic generation until you remove or rename the physical file.', 'seo-booster' ),
        '<code>' . esc_html( $physical_status['path'] ) . '</code>'
     );
    ?>
			</p>
			<?php 
    if ( !empty( $physical_status['preview'] ) ) {
        ?>
			<p class="description"><?php 
        echo esc_html( $physical_status['preview'] );
        ?></p>
			<?php 
    }
    ?>
			<p class="description"><?php 
    esc_html_e( 'Use Download below to replace it manually, or remove the physical file to let SEO Booster serve /llms.txt dynamically.', 'seo-booster' );
    ?></p>
		</div>
	<?php 
} else {
    ?>
		<div class="notice notice-info inline">
			<p><?php 
    esc_html_e( 'No physical llms.txt file was found in your WordPress root. When enabled, SEO Booster serves /llms.txt dynamically via WordPress (recommended: stays in sync when you publish content).', 'seo-booster' );
    ?></p>
		</div>
	<?php 
}
?>

	<form id="sb-tools-llms-form" class="sb-tools-llms-form">
		<table class="form-table sb-tools-form-table" role="presentation">
			<tr>
				<th scope="row"><?php 
esc_html_e( 'Publish on site', 'seo-booster' );
?></th>
				<td>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="enabled" value="1" <?php 
checked( !empty( $settings['enabled'] ) );
?> <?php 
disabled( !empty( $physical_shadows ) );
?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php 
esc_html_e( 'Serve /llms.txt dynamically at yoursite.com/llms.txt (virtual file, no FTP upload)', 'seo-booster' );
?></span>
					</label>
					<?php 
if ( !empty( $physical_shadows ) ) {
    ?>
					<p class="description"><?php 
    esc_html_e( 'Disabled while a physical llms.txt shadows the URL. Remove the physical file first, or use Download and upload manually.', 'seo-booster' );
    ?></p>
					<?php 
} else {
    ?>
					<p class="description"><?php 
    esc_html_e( 'Uses a WordPress rewrite rule (same approach as robots.txt). Content refreshes when you update posts or save these settings.', 'seo-booster' );
    ?></p>
					<?php 
}
?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php 
esc_html_e( 'Discovery signals', 'seo-booster' );
?></th>
				<td>
					<div class="sb-toggle-group">
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" name="robots_llms" value="1" <?php 
checked( !empty( $settings['robots_llms'] ) );
?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span class="sb-toggle-text"><?php 
esc_html_e( 'Add LLMS: line to robots.txt', 'seo-booster' );
?></span>
						</label>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" name="head_links" value="1" <?php 
checked( !empty( $settings['head_links'] ) );
?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span class="sb-toggle-text"><?php 
esc_html_e( 'Add alternate link in HTML head (front page)', 'seo-booster' );
?></span>
						</label>
						<label class="sb-toggle-label">
							<div class="sb-toggle-switch">
								<input type="checkbox" name="http_headers" value="1" <?php 
checked( !empty( $settings['http_headers'] ) );
?> />
								<span class="sb-toggle-slider"></span>
							</div>
							<span class="sb-toggle-text"><?php 
esc_html_e( 'Send HTTP Link header (front page)', 'seo-booster' );
?></span>
						</label>
					</div>
					<p class="description"><?php 
esc_html_e( 'Helps AI crawlers discover your llms.txt file. Only active when dynamic serving is enabled.', 'seo-booster' );
?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sb-llms-intro"><?php 
esc_html_e( 'Introduction', 'seo-booster' );
?></label></th>
				<td>
					<textarea id="sb-llms-intro" name="intro" class="large-text" rows="3"><?php 
echo esc_textarea( $settings['intro'] );
?></textarea>
					<p class="description"><?php 
esc_html_e( 'Short site description shown at the top of the file (Markdown blockquote).', 'seo-booster' );
?></p>
					<p class="sb-tools-secondary-action">
						<button type="button" class="button" id="sb-tools-llms-ai-suggest" <?php 
disabled( !$ai_available );
?>>
							<?php 
esc_html_e( 'Generate with AI', 'seo-booster' );
?>
						</button>
						<?php 
if ( !$ai_available ) {
    ?>
							<span class="description"><?php 
    echo esc_html( $ai_message );
    ?></span>
						<?php 
}
?>
					</p>
					<div id="sb-tools-llms-ai-results" class="sb-tools-llms-ai-results" style="display:none;"></div>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php 
esc_html_e( 'Content types', 'seo-booster' );
?></th>
				<td>
					<div class="sb-toggle-group sb-tools-content-types">
						<?php 
foreach ( $post_types as $type => $object ) {
    ?>
							<label class="sb-toggle-label">
								<div class="sb-toggle-switch">
									<input type="checkbox" name="post_types[]" value="<?php 
    echo esc_attr( $type );
    ?>" <?php 
    checked( in_array( $type, (array) $settings['post_types'], true ) );
    ?> />
									<span class="sb-toggle-slider"></span>
								</div>
								<span class="sb-toggle-text"><?php 
    echo esc_html( $object->labels->name );
    ?></span>
							</label>
						<?php 
}
?>
					</div>
					<p class="description"><?php 
esc_html_e( 'Builder templates and internal types are hidden. Only public content types are listed.', 'seo-booster' );
?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sb-llms-max"><?php 
esc_html_e( 'Max links', 'seo-booster' );
?></label></th>
				<td>
					<input type="number" id="sb-llms-max" name="max_links" min="1" max="100" value="<?php 
echo esc_attr( (int) $settings['max_links'] );
?>" />
					<p class="description"><?php 
esc_html_e( 'Keep this focused (10–30 pillar pages). Prefers top GSC pages and AI bot traffic when data exists.', 'seo-booster' );
?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sb-llms-cache-ttl"><?php 
esc_html_e( 'Cache TTL', 'seo-booster' );
?></label></th>
				<td>
					<input type="number" id="sb-llms-cache-ttl" name="cache_ttl" min="60" max="86400" value="<?php 
echo esc_attr( (int) ($settings['cache_ttl'] ?? 43200) );
?>" />
					<p class="description"><?php 
esc_html_e( 'Seconds to cache generated llms.txt (60–86400). Cleared when you save settings or update curated content.', 'seo-booster' );
?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php 
esc_html_e( 'FAQ section', 'seo-booster' );
?></th>
				<td>
					<label class="sb-toggle-label">
						<div class="sb-toggle-switch">
							<input type="checkbox" name="include_faq" value="1" <?php 
checked( !empty( $settings['include_faq'] ) );
?> />
							<span class="sb-toggle-slider"></span>
						</div>
						<span class="sb-toggle-text"><?php 
esc_html_e( 'Include FAQ blocks from Yoast or Rank Math in llms.txt', 'seo-booster' );
?></span>
					</label>
					<p style="margin-top:10px;">
						<label for="sb-llms-faq-max"><?php 
esc_html_e( 'Max FAQ items', 'seo-booster' );
?></label>
						<input type="number" id="sb-llms-faq-max" name="faq_max_items" min="1" max="50" value="<?php 
echo esc_attr( (int) ($settings['faq_max_items'] ?? 10) );
?>" />
					</p>
				</td>
			</tr>
		</table>

		<?php 
foreach ( $pinned_ids as $pinned_id ) {
    ?>
			<input type="hidden" name="pinned_post_ids[]" value="<?php 
    echo esc_attr( (string) $pinned_id );
    ?>" class="sb-llms-pinned-input" data-post-id="<?php 
    echo esc_attr( (string) $pinned_id );
    ?>" />
		<?php 
}
?>

		<h3><?php 
esc_html_e( 'Directory scanner', 'seo-booster' );
?></h3>
		<p class="description"><?php 
esc_html_e( 'Include or exclude URL directories from llms.txt curation. Auto respects blocked paths such as author, tag, and feed archives.', 'seo-booster' );
?></p>
		<table class="widefat striped sb-tools-llms-directories">
			<thead>
				<tr>
					<th><?php 
esc_html_e( 'Directory', 'seo-booster' );
?></th>
					<th><?php 
esc_html_e( 'Count', 'seo-booster' );
?></th>
					<th><?php 
esc_html_e( 'Post types', 'seo-booster' );
?></th>
					<th><?php 
esc_html_e( 'Example', 'seo-booster' );
?></th>
					<th><?php 
esc_html_e( 'Status', 'seo-booster' );
?></th>
				</tr>
			</thead>
			<tbody>
				<?php 
if ( empty( $directories ) ) {
    ?>
					<tr><td colspan="5"><?php 
    esc_html_e( 'No published URLs found for the selected content types.', 'seo-booster' );
    ?></td></tr>
				<?php 
} else {
    ?>
					<?php 
    foreach ( $directories as $row ) {
        ?>
						<?php 
        $dir = $row['directory'];
        $rule = $row['rule'];
        $effective = ( $rule !== 'auto' ? $rule : $row['auto_status'] );
        $md_url = ( !empty( $row['example_md'] ) ? $row['example_md'] : '' );
        ?>
						<tr>
							<td><code><?php 
        echo esc_html( $dir );
        ?></code><br /><span class="description"><?php 
        echo esc_html( sprintf( 
            /* translators: %s: effective rule */
            __( 'Effective: %s', 'seo-booster' ),
            $effective
         ) );
        ?></span></td>
							<td><?php 
        echo esc_html( (string) $row['count'] );
        ?></td>
							<td><code><?php 
        echo esc_html( $row['post_types'] );
        ?></code></td>
							<td>
								<a href="<?php 
        echo esc_url( $row['example_url'] );
        ?>" target="_blank" rel="noopener noreferrer"><?php 
        esc_html_e( 'HTML', 'seo-booster' );
        ?></a>
								<?php 
        if ( $md_url ) {
            ?>
									· <a href="<?php 
            echo esc_url( $md_url );
            ?>" target="_blank" rel="noopener noreferrer"><?php 
            esc_html_e( '.md', 'seo-booster' );
            ?></a>
								<?php 
        }
        ?>
							</td>
							<td>
								<select name="directory_rules[<?php 
        echo esc_attr( $dir );
        ?>]">
									<option value="auto" <?php 
        selected( $rule, 'auto' );
        ?>><?php 
        esc_html_e( 'Auto', 'seo-booster' );
        ?></option>
									<option value="include" <?php 
        selected( $rule, 'include' );
        ?>><?php 
        esc_html_e( 'Include', 'seo-booster' );
        ?></option>
									<option value="exclude" <?php 
        selected( $rule, 'exclude' );
        ?>><?php 
        esc_html_e( 'Exclude', 'seo-booster' );
        ?></option>
								</select>
							</td>
						</tr>
					<?php 
    }
    ?>
				<?php 
}
?>
			</tbody>
		</table>

		<?php 
if ( !empty( $show_markdown ) ) {
    \Cleverplugins\SEOBooster\Tools\Tools_Markdown::render_settings_partial();
}
?>

		<h3><?php 
esc_html_e( 'AI bot crawl gaps', 'seo-booster' );
?></h3>
		<p class="description"><?php 
esc_html_e( 'Pages with significant AI bot traffic that are not in your current llms.txt selection. Pin important pages to force inclusion.', 'seo-booster' );
?></p>
		<table class="widefat striped" id="sb-tools-llms-bot-gaps">
			<thead>
				<tr>
					<th><?php 
esc_html_e( 'Page', 'seo-booster' );
?></th>
					<th><?php 
esc_html_e( 'Bot visits (30d)', 'seo-booster' );
?></th>
					<th><?php 
esc_html_e( 'GSC clicks', 'seo-booster' );
?></th>
					<th><?php 
esc_html_e( 'Pinned', 'seo-booster' );
?></th>
				</tr>
			</thead>
			<tbody>
				<?php 
if ( empty( $bot_gaps ) ) {
    ?>
					<tr><td colspan="4"><?php 
    esc_html_e( 'No crawl gaps found, or bot tracking has no mapped content hits yet.', 'seo-booster' );
    ?></td></tr>
				<?php 
} else {
    ?>
					<?php 
    foreach ( $bot_gaps as $gap ) {
        ?>
						<?php 
        $is_pinned = in_array( (int) $gap['post_id'], $pinned_ids, true );
        ?>
						<tr data-post-id="<?php 
        echo esc_attr( (string) $gap['post_id'] );
        ?>">
							<td>
								<a href="<?php 
        echo esc_url( $gap['url'] );
        ?>" target="_blank" rel="noopener noreferrer"><?php 
        echo esc_html( $gap['title'] );
        ?></a>
							</td>
							<td><?php 
        echo esc_html( (string) $gap['bot_visits'] );
        ?></td>
							<td><?php 
        echo esc_html( (string) $gap['gsc_clicks'] );
        ?></td>
							<td>
								<button type="button" class="button sb-llms-pin-toggle" data-post-id="<?php 
        echo esc_attr( (string) $gap['post_id'] );
        ?>" data-pinned="<?php 
        echo ( $is_pinned ? '1' : '0' );
        ?>">
									<?php 
        echo ( $is_pinned ? esc_html__( 'Unpin', 'seo-booster' ) : esc_html__( 'Pin', 'seo-booster' ) );
        ?>
								</button>
							</td>
						</tr>
					<?php 
    }
    ?>
				<?php 
}
?>
			</tbody>
		</table>

		<p class="sb-tools-llms-primary-actions">
			<button type="submit" class="button button-primary"><?php 
esc_html_e( 'Save settings', 'seo-booster' );
?></button>
			<button type="button" class="button" id="sb-tools-llms-download"><?php 
esc_html_e( 'Download llms.txt', 'seo-booster' );
?></button>
			<?php 
if ( !empty( $settings['enabled'] ) && empty( $physical_shadows ) ) {
    ?>
				<a class="button" href="<?php 
    echo esc_url( $file_url );
    ?>" target="_blank" rel="noopener noreferrer"><?php 
    esc_html_e( 'View live /llms.txt', 'seo-booster' );
    ?></a>
			<?php 
}
?>
		</p>
	</form>

	<h3><?php 
esc_html_e( 'Preview', 'seo-booster' );
?></h3>
	<p class="description"><?php 
esc_html_e( 'This is what SEO Booster would publish. Edit settings above and save to refresh.', 'seo-booster' );
?></p>
	<pre id="sb-tools-llms-preview" class="sb-tools-llms-preview"><?php 
echo esc_html( $preview );
?></pre>
</div>
