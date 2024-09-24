<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'update_plugins' ) ) {
	wp_die( esc_html__( 'You are not allowed to update plugins on this blog.', 'seo-booster' ) );
}
global  $wpdb, $seobooster2;


if ( isset( $_POST['page'] ) && 'sb2_settings' === sanitize_text_field( $_POST['page'] ) ) {

	$nonce = sanitize_text_field( $_REQUEST['_wpnonce'] );
	if ( ! wp_verify_nonce( $nonce, 'seobooster_save_settings' ) ) {
		die( 'Security check' );
	}

	// @todo Yes - I need to review the settings API.

	if ( isset( $_POST['seobooster_internal_linking'] ) ) {
		update_option( 'seobooster_internal_linking', sanitize_text_field( $_POST['seobooster_internal_linking'] ) );
	} else {
		delete_option( 'seobooster_internal_linking' );
	}


	if ( isset( $_POST['seobooster_replace_kw_multiple'] ) ) {
		update_option( 'seobooster_replace_kw_multiple', sanitize_text_field( $_POST['seobooster_replace_kw_multiple'] ) );
	} else {
		delete_option( 'seobooster_replace_kw_multiple' );
	}

	if ( isset( $_POST['seobooster_dynamic_tag_taxonomy'] ) ) {
		update_option( 'seobooster_dynamic_tag_taxonomy', intval( $_POST['seobooster_dynamic_tag_taxonomy'] ) );
	}
	if ( isset( $_POST['seobooster_dynamic_tag_maximum'] ) ) {
		update_option( 'seobooster_dynamic_tag_maximum', intval( $_POST['seobooster_dynamic_tag_maximum'] ) );
	}
	if ( isset( $_POST['seobooster_dynamic_tag_minlength'] ) ) {
		update_option( 'seobooster_dynamic_tag_minlength', intval( $_POST['seobooster_dynamic_tag_minlength'] ) );
	}
	if ( isset( $_POST['seobooster_dynamic_tag_maxlength'] ) ) {
		update_option( 'seobooster_dynamic_tag_maxlength', intval( $_POST['seobooster_dynamic_tag_maxlength'] ) );
	}
	if ( isset( $_POST['seobooster_pagespeed_api_key'] ) ) {
		update_option( 'seobooster_pagespeed_api_key', sanitize_text_field( $_POST['seobooster_pagespeed_api_key'] ) );
	}
	if ( isset( $_POST['seobooster_pagespeed_url_limit'] ) ) {
		update_option( 'seobooster_pagespeed_url_limit', sanitize_text_field( $_POST['seobooster_pagespeed_url_limit'] ) );
	}

	if ( isset( $_POST['seobooster_enable_pagespeed'] ) ) {
		update_option( 'seobooster_enable_pagespeed', sanitize_key( $_POST['seobooster_enable_pagespeed'] ) );
	} else {
		delete_option( 'seobooster_enable_pagespeed' );
	}

	if ( isset( $_POST['seobooster_replace_kw_limit'] ) ) {
		update_option( 'seobooster_replace_kw_limit', intval( $_POST['seobooster_replace_kw_limit'] ) );
	}

	if ( isset( $_POST['seobooster_dynamic_tagging_related'] ) ) {
		update_option( 'seobooster_dynamic_tagging_related', sanitize_key( $_POST['seobooster_dynamic_tagging_related'] ) );
	} else {
		delete_option( 'seobooster_dynamic_tagging_related' );
	}


	if ( isset( $_POST['seobooster_ignore_internal_searches'] ) ) {
		update_option( 'seobooster_ignore_internal_searches', sanitize_key( $_POST['seobooster_ignore_internal_searches'] ) );
	} else {
		delete_option( 'seobooster_ignore_internal_searches' );
	}


	if ( isset( $_POST['seobooster_delete_deactivate'] ) ) {
		update_option( 'seobooster_delete_deactivate', sanitize_key( $_POST['seobooster_delete_deactivate'] ) );
	} else {
		delete_option( 'seobooster_delete_deactivate' );
	}


	if ( isset( $_POST['seobooster_dynamic_tagging'] ) ) {
		update_option( 'seobooster_dynamic_tagging', sanitize_key( $_POST['seobooster_dynamic_tagging'] ) );
	} else {
		delete_option( 'seobooster_dynamic_tagging' );
	}


	if ( isset( $_POST['seobooster_dynamic_tag_assigncpts'] ) ) {
		update_option( 'seobooster_dynamic_tag_assigncpts', sanitize_key( $_POST['seobooster_dynamic_tag_assigncpts'] ) );
	} else {
		delete_option( 'seobooster_dynamic_tag_assigncpts' );
	}


	if ( isset( $_POST['seobooster_weekly_email'] ) ) {
		update_option( 'seobooster_weekly_email', sanitize_text_field( $_POST['seobooster_weekly_email'] ) );
	} else {
		delete_option( 'seobooster_weekly_email' );
	}


	if ( isset( $_POST['seobooster_weekly_email_recipient'] ) ) {
		update_option( 'seobooster_weekly_email_recipient', sanitize_text_field( $_POST['seobooster_weekly_email_recipient'] ) );
	} else {
		delete_option( 'seobooster_weekly_email_recipient' );
	}


	if ( isset( $_POST['seobooster_debug_logging'] ) ) {
		update_option( 'seobooster_debug_logging', sanitize_text_field( $_POST['seobooster_debug_logging'] ) );
	} else {
		delete_option( 'seobooster_debug_logging' );
	}


	if ( isset( $_POST['seobooster_fof_monitoring'] ) ) {
		update_option( 'seobooster_fof_monitoring', sanitize_text_field( $_POST['seobooster_fof_monitoring'] ) );
	} else {
		delete_option( 'seobooster_fof_monitoring' );
	}

	if ( isset( $_POST['seobooster_backlinks_ignore'] ) ) {
		update_option( 'seobooster_backlinks_ignore', sanitize_textarea_field( $_POST['seobooster_backlinks_ignore'] ) );
	}
	if ( isset( $_POST['seobooster_ignorelist'] ) ) {
		update_option( 'seobooster_ignorelist', sanitize_text_field( $_POST['seobooster_ignorelist'] ) );
	}
	if ( isset( $_POST['seobooster_fof_ignore'] ) ) {
		update_option( 'seobooster_fof_ignore', sanitize_text_field( $_POST['seobooster_fof_ignore'] ) );
	}
	// WooCommerce

	if ( isset( $_POST['seobooster_woocommerce'] ) ) {
		update_option( 'seobooster_woocommerce', sanitize_text_field( $_POST['seobooster_woocommerce'] ) );
	} else {
		delete_option( 'seobooster_woocommerce' );
	}
}


// Trimming old keywords
if ( isset( $_POST['delete_old_kws'] ) && $_POST['delete_old_kws'] ) {
	$oldkws = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sb2_kw WHERE lastvisit < DATE_SUB(NOW(), INTERVAL 90 DAY) LIMIT 10000;" );
	if ( $oldkws ) {
		foreach ( $oldkws as $oldkw ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}sb2_kw where id=%d LIMIT 1;", $oldkw->id ) );
		}
	}
}

// Running DB updates
if ( isset( $_POST['submit_dbupdates'] ) && $_POST['submit_dbupdates'] ) {
	// start migrate old data process..
	self::seobooster_activate( false );
	// Running only on current installation.
	self::do_seobooster_maintenance();
}

// Resets and starts the guided tour again.
if ( isset( $_POST['reset_guided_tours'] ) && $_POST['reset_guided_tours'] ) {
	$pointer   = 'sbp_tour_pointer';
	$dismissed = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );
	// finds and unsets the pointer if it has been set.
	$key = array_search( $pointer, $dismissed, true );
	if ( false !== $key ) {
		unset( $dismissed[ $key ] );
	}
	update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', $dismissed );
}

global  $seobooster_fs;
$dynamic_keywords                  = get_option( 'seobooster_dynamic_keywords' );
$seobooster_woocommerce            = get_option( 'seobooster_woocommerce' );
$seobooster_replace_kw_limit       = get_option( 'seobooster_replace_kw_limit', 10 );
$seobooster_internal_linking       = get_option( 'seobooster_internal_linking' );
$replace_kw_multiple               = get_option( 'seobooster_replace_kw_multiple' );
$ignore_internal_searches          = get_option( 'seobooster_ignore_internal_searches' );
$dynamic_tagging                   = get_option( 'seobooster_dynamic_tagging' );
$dynamic_tag_tax                   = get_option( 'seobooster_dynamic_tag_taxonomy' );
$dynamic_tag_assigncpts            = get_option( 'seobooster_dynamic_tag_assigncpts' );
$dynamic_tag_max                   = get_option( 'seobooster_dynamic_tag_maximum' );
$dynamic_tag_minlength             = get_option( 'seobooster_dynamic_tag_minlength' );
$dynamic_tag_maxlength             = get_option( 'seobooster_dynamic_tag_maxlength' );
$dynamic_tagging_related           = get_option( 'seobooster_dynamic_tagging_related' );
$seobooster_weekly_email           = get_option( 'seobooster_weekly_email' );
$seobooster_weekly_email_recipient = get_option( 'seobooster_weekly_email_recipient' );
$debug_logging                     = get_option( 'seobooster_debug_logging' );
$fof_monitoring                    = get_option( 'seobooster_fof_monitoring' );
$seobooster_delete_deactivate      = get_option( 'seobooster_delete_deactivate' );

$seobooster_backlinks_ignore = get_option( 'seobooster_backlinks_ignore' );
$seobooster_backlinks_ignore = preg_replace( '/,+/', ',', $seobooster_backlinks_ignore );
$seobooster_backlinks_ignore = strtolower( $seobooster_backlinks_ignore );

$ignorelist = get_option( 'seobooster_ignorelist' );
$ig_arr     = explode( ',', $ignorelist );

if ( $ig_arr ) {
	$newarr = array();
	foreach ( $ig_arr as $ia ) {
		$newarr[] = trim( $ia );
	}
	$ig_arr = $newarr;
}

$ignorelist = implode( ',', $ig_arr );
$ignorelist = strtolower( $ignorelist );

$seobooster_fof_ignore = get_option( 'seobooster_fof_ignore' );

$seobooster_fof_ignore = preg_replace( '/,+/', ',', $seobooster_fof_ignore );
$seobooster_fof_ignore = strtolower( $seobooster_fof_ignore );
?>
<div class="wrap">
<div class="sbpheader">
<?php
require_once SEOBOOSTER_PLUGINPATH . 'inc/adminheader.php';
// Contains general info
?>
<h1>
<?php
esc_html_e( 'Settings', 'seo-booster' );
?>
- SEO Booster v.
<?php
	echo esc_html( SEOBOOSTER_VERSION );
?>
</h1></div><!-- .sbpheader -->

<form method="post" id="seobooster_settings_form">
<table class="form-table">
<tbody>

<tr valign="top">
<th colspan="2"><h2>
<?php
esc_html_e( 'Keywords', 'seo-booster' );
?>
</h2>
</th>
</tr>


<tr valign="top">
<th scope="row" valign="top">
<?php
esc_html_e( 'Filter out keywords', 'seo-booster' );
?>
</th>
<td>
<textarea id="seobooster_ignorelist" name="seobooster_ignorelist" class="large-text code"><?php echo nl2br( esc_html( $ignorelist ) ); ?></textarea>
<p class="description">
<?php
esc_html_e( 'Enter keywords to be filtered out. Separate keywords with comma.', 'seo-booster' );
?>
</p>
<p class="description">
<?php
esc_html_e( 'Note: Works as wildcard search - if you enter "widgets" all keywords containing "widgets" will be ignored.', 'seo-booster' );
?>
</p>
</td>
</tr><tr valign="top">
<th scope="row" valign="top">
<?php
esc_html_e( 'Ignore internal searches', 'seo-booster' );
?>
</th>
<td>
<fieldset>
<legend class="screen-reader-text"><span>
<?php
esc_html_e( 'Do not monitor regular searches on your website.', 'seo-booster' );
?>
</span></legend>
<label for="seobooster_ignore_internal_searches">
<input type="checkbox" id="seobooster_ignore_internal_searches" name="seobooster_ignore_internal_searches" value="on" 
<?php
if ( $ignore_internal_searches ) {
	echo " checked='checked'";
}
?>
/>
<?php
esc_html_e( 'Do not monitor searches made directly on your site', 'seo-booster' );
?>
</label><p class="description">
<?php
esc_html_e( 'People searching internally on your site and clicking the results can also help gather keyword information. Turn this on to ignore.', 'seo-booster' );
?>
</p>

</fieldset>

</td>
</tr>

<tr valign="top">
<th colspan="2">
<h2>
<?php
esc_html_e( 'Backlinks', 'seo-booster' );
?>
</h2>
</th>
</tr>
<tr valign="top">
<th scope="row" valign="top">
<?php
esc_html_e( 'Ignore backlink sources', 'seo-booster' );
?>
</th>
<td>
<textarea id="seobooster_backlinks_ignore" name="seobooster_backlinks_ignore" class="large-text code">
<?php
echo esc_html( $seobooster_backlinks_ignore );
?>
</textarea>
<p class="description">
<?php
esc_html_e( 'Enter any URL or domain extension to not show up in the backlinks list - eg .ru', 'seo-booster' );
?>
</p>
<p class="description">
<?php
esc_html_e( 'Separate with a comma.', 'seo-booster' );
?>
</p>
</td>
</tr>

<tr valign="top">
<th colspan="2">
<h2>
<?php
esc_html_e( 'Automatic Tagging', 'seo-booster' );
?>
</h2>
</th>
</tr>

<tr valign="top">
<th scope="row" valign="top">
<?php
esc_html_e( 'Enable/Disable', 'seo-booster' );
?>
</th>
<td>
<input type="checkbox" id="seobooster_dynamic_tagging" name="seobooster_dynamic_tagging" value="on" 
<?php
if ( $dynamic_tagging ) {
	echo " checked='checked'";
}
?>
/>
<p class="description"><label for="seobooster_dynamic_tagging">
<?php
esc_html_e( 'Tags can be created automatically using the search terms that visitors use. This works with both internal searches and visitors from search engines.', 'seo-booster' );
?>
</label></p>

</td>
</tr>

<tr valign="top" class="taggingrelated 
<?php
if ( ! $dynamic_tagging ) {
	echo ' muted';
}
?>
"><th scope="row" valign="top">
<?php
	esc_html_e( 'Tag Related Posts:', 'seo-booster' );
?>
	</th>
	<td><input type="checkbox" id="seobooster_dynamic_tagging_related" name="seobooster_dynamic_tagging_related" value="on" 
	<?php
	if ( $dynamic_tagging_related ) {
		echo " checked='checked'";
	}
	?>
	/><p class="description"><label for="seobooster_dynamic_tagging_related">
	<?php
	esc_html_e( 'SEO Booster will attempt to find related posts and also tag with the search term.', 'seo-booster' );
	?>
	</label></p></td>
	</tr><tr valign="top" class="taggingrelated 
	<?php
	if ( ! $dynamic_tagging ) {
		echo ' muted';
	}
	?>
	"><th scope="row" valign="top">
	<?php
	esc_html_e( 'Choose Taxonomy:', 'seo-booster' );
	?>
	</th>
	<td>
	<?php
	$customtaxonomies  = get_taxonomies(
		array(
			'public'   => true,
			'_builtin' => false,
		),
		'objects',
		'and'
	);
	$builtintaxonomies = get_taxonomies(
		array(
			'public'   => true,
			'_builtin' => true,
		),
		'objects',
		'and'
	);
	$taxonomies        = array_merge( $builtintaxonomies, $customtaxonomies );
	?>
	<select name="seobooster_dynamic_tag_taxonomy" id="seobooster_dynamic_tag_taxonomy">
	<?php
	if ( $taxonomies ) {
		foreach ( $taxonomies as $name => $atax ) {
			echo "<option name='" . esc_attr( $name ) . "' value ='" . esc_attr( $name ) . "'";
			if ( $dynamic_tag_tax === $name ) {
				echo " selected='selected' ";
			}
			echo '>' . esc_attr( $atax->labels->name ) . ' (' . esc_attr( $name ) . ')</option>';
		}
	}
	?>
	</select><p class="description">
	<?php
	esc_html_e( 'If you use Custom Post Types on your site, they might not be configured to use the taxonomy you have chosen above.', 'seo-booster' );
	?>
	</p><p class="description">
	<?php
	esc_html_e( "By clicking this checkbox, the taxonomy is properly 'assigned'.", 'seo-booster' );
	?>
	</p></td>
	</tr>
	<tr valign="top" class="taggingrelated 
	<?php
	if ( ! $dynamic_tagging ) {
		echo ' muted';
	}
	?>
	"><th scope="row" valign="top">
	<?php
	esc_html_e( 'Assign Custom Post Types:', 'seo-booster' );
	?>
	</th><td>
	<?php
	$cpts    = get_post_types(
		array(
			'public'             => true,
			'publicly_queryable' => true,
		),
		'names',
		'and'
	);
	$cpts    = array_merge( $cpts, array( 'page' ) );
	$cptlist = implode( ', ', $cpts );
	$cptlist = trim( $cptlist, ',' );
	?>
	<input type="checkbox" id="seobooster_dynamic_tag_assigncpts" name="seobooster_dynamic_tag_assigncpts" value="on" 
	<?php
	if ( $dynamic_tag_assigncpts ) {
		echo " checked='checked'";
	}
	?>
	/><p class="description"><label for="seobooster_dynamic_tag_assigncpts">
	<?php
	esc_html_e( 'Turning this on ensures that the chosen taxonomy will be used on all custom post types listed below.', 'seo-booster' );
	?>
	</label></p>
	<p class="description">
	<?php
	esc_html_e( 'Custom Post Types', 'seo-booster' );
	?>
	: <strong>
	<?php
	echo esc_html( $cptlist );
	?>
</strong>.</p>
	</td>
	</tr>
	<tr valign="top" class="taggingrelated 
	<?php
	if ( ! $dynamic_tagging ) {
		echo ' muted';
	}
	?>
	"><th scope="row" valign="top">
	<?php
	esc_html_e( 'Minimum length of term', 'seo-booster' );
	?>
	</th>
	<td>
	<input type="number" id="seobooster_dynamic_tag_minlength" name="seobooster_dynamic_tag_minlength" value="
	<?php
	echo esc_attr( $dynamic_tag_minlength );
	?>
	" class="small-text" step="1" min="1" />
	<p class="description">
	<?php
	esc_html_e( 'If the number of characters in the query is less than the specified number, no tag (taxonomy term) will be created.', 'seo-booster' );
	?>
	</p>
	</td>
	</tr>

	<tr valign="top" class="taggingrelated
	<?php
	if ( ! $dynamic_tagging ) {
		echo ' muted';
	}
	?>
	">
	<th scope="row" valign="top">
	<?php
	esc_html_e( 'Maximum length of term', 'seo-booster' );
	?>
	</th>
	<td>
	<input type="number" id="seobooster_dynamic_tag_maxlength" name="seobooster_dynamic_tag_maxlength" value="
	<?php
	echo esc_attr( $dynamic_tag_maxlength );
	?>
	" class="small-text" step="1" min="1" />
	<p class="description"><label for="seobooster_dynamic_tag_maxlength">
	<?php
	esc_html_e( 'If the number of characters in the query is MORE than the specified number, no tag (taxonomy term) will be created.', 'seo-booster' );
	?>
	</label></p>
	</td>
	</tr>
	<tr valign="top" class="taggingrelated
	<?php
	if ( ! $dynamic_tagging ) {
		echo ' muted';
	}
	?>
	">
	<th scope="row" valign="top">
	<?php
	esc_html_e( 'Maximum tags per post', 'seo-booster' );
	?>
	</th>
	<td>
	<input type="number" id="seobooster_dynamic_tag_maximum" name="seobooster_dynamic_tag_maximum" value="
	<?php
	echo esc_attr( $dynamic_tag_max );
	?>
	" class="small-text" step="1" min="1" />
	</td>
	</tr>
	<tr valign="top" id="autolinks">
	<th colspan="2">
	<h2>
	<?php
	esc_html_e( 'Automatic Links', 'seo-booster' );
	?>
	</h2>
	</th>
	</tr>
	<tr valign="top">
	<th scope="row" valign="top">
	<?php
	esc_html_e( 'Enable/Disable', 'seo-booster' );
	?>
	</th>
	<td>
	<fieldset>
	<legend class="screen-reader-text"><span>
	<?php
	esc_html_e( 'Change keywords in text to links to relevant pages on your site.', 'seo-booster' );
	?>
	</span></legend>
	<label for="seobooster_internal_linking">
	<input type="checkbox" id="seobooster_internal_linking" name="seobooster_internal_linking" value="on" 
	<?php
	if ( $seobooster_internal_linking ) {
		echo " checked='checked'";
	}
	?>
	/>
	<p class="description">
	<?php
	esc_html_e( 'Change keywords in text to links to relevant pages on your site.', 'seo-booster' );
	?>
	</p>
	</label>
	</fieldset>
	</td>
	</tr>
	<tr valign="top">
	<th scope="row" valign="top">
	<?php
	esc_html_e( 'Repeat Keywords', 'seo-booster' );
	?>
	</th>
	<td>
	<fieldset>
	<legend class="screen-reader-text"><span>
	<?php
	esc_html_e( 'If the same word is used multiple times, only the first occurence will be replaced by a link.', 'seo-booster' );
	?>
	</span></legend>
	<label for="seobooster_replace_kw_multiple">
	<input type="checkbox" id="seobooster_replace_kw_multiple" name="seobooster_replace_kw_multiple" value="on"
	<?php
	if ( $replace_kw_multiple ) {
		echo " checked='checked'";
	}
	?>
	/>
	<p class="description">
	<?php
	esc_html_e( 'This will allow the same keyword and URL to be used repeatedly. Usually, if the same word is used multiple times, only the first occurence will be replaced by a link.', 'seo-booster' );
	?>
	</p>
	</label>
	</fieldset>
	</td>
	</tr>
	<tr valign="top">
	<th scope="row" valign="top">
	<?php
	esc_html_e( 'Maximum Replacements', 'seo-booster' );
	?>
	</th>
	<td>
	<input type="number" id="seobooster_replace_kw_limit" name="seobooster_replace_kw_limit" value="
	<?php
	echo esc_attr( $seobooster_replace_kw_limit );
	?>
	" class="small-text" step="1" min="1" max="25" />
	<p class="description"><label for="seobooster_replace_kw_limit">
	<?php
	esc_html_e( 'Maximum number of links created per post. It does not include current links in the content.', 'seo-booster' );
	?>
	</label></p>
	</td>
	</tr>
	<?php
	// premium only
	?>
	<tr valign="top">
		<th scope="row" valign="top">WooCommerce</th>
		<td>
		<fieldset>
		<legend class="screen-reader-text"><span>Enable keywords in WPForo</span></legend>
		<label for="seobooster_woocommerce">
		<input type="checkbox" id="seobooster_woocommerce" name="seobooster_woocommerce" value="on" 
		<?php
		if ( $seobooster_woocommerce ) {
			echo " checked='checked'";
		}
		?>
		/>
		<p class="description">Enables keywords to links in WooCommerce product descriptions.</a></p>
		</label>
		</fieldset>
		</td>
		</tr>					
		<tr valign="top">
		<th colspan="2">
		<h2>
		<?php
		esc_html_e( 'Weekly Email Reports', 'seo-booster' );
		?>
		</h2>
							</th>
		</tr>
		<tr valign="top">
		<th scope="row" valign="top">
		<?php
		esc_html_e( 'Enable/Disable', 'seo-booster' );
		?>
		</th>
		<td>
		<input type="checkbox" id="seobooster_weekly_email" name="seobooster_weekly_email" value="on" 
		<?php
		if ( 'on' === $seobooster_weekly_email ) {
			echo " checked='checked'";
		}
		?>
		/>
		<p class="description"><label for="seobooster_weekly_email">
		<?php
		esc_html_e( 'Send a weekly email with information of keyword stats, new backlinks and 404 errors detected the past week.', 'seo-booster' );
		?>
		</label></p>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row" valign="top"><?php esc_html_e( 'Email recipient', 'seo-booster' ); ?>
		</th>
		<td>
		<input type="text" id="seobooster_weekly_email_recipient" name="seobooster_weekly_email_recipient" value="
		<?php
		echo esc_attr( $seobooster_weekly_email_recipient );
		?>
		" class="regular-text">
		<p class="description"><label for="seobooster_weekly_email_recipient"><?php esc_html_e( 'Email recipent.', 'seo-booster' ); ?></label></p>
		</td>
		</tr>
		<tr valign="top">
		<th colspan="2"><h2><?php esc_html_e( '404 Errors', 'seo-booster' ); ?></h2></th>
		</tr>
		<tr valign="top">
		<th scope="row" valign="top"><p><?php esc_html_e( 'Enable/Disable', 'seo-booster' ); ?></p>
		</th>
		<td> <input type="checkbox" id="seobooster_fof_monitoring" name="seobooster_fof_monitoring" value="on" 
		<?php
		if ( 'on' === $fof_monitoring ) {
			echo " checked='checked'";
		}
		?>
		/>
		<p class="description"><label for="seobooster_fof_monitoring"><?php esc_html_e( 'If turned on, 404 errors will be monitored.', 'seo-booster' ); ?></label></p>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row" valign="top"><?php esc_html_e( 'Ignore links', 'seo-booster' ); ?> </th>
		<td>
		<textarea id="seobooster_fof_ignore" name="seobooster_fof_ignore" class="large-text code"><?php echo esc_html( $seobooster_fof_ignore ); ?></textarea>
		<p class="description">
		<?php
		esc_html_e( 'Here you can instruct the plugin to ignore 404 errors on URLs that match.', 'seo-booster' );
		?>
		</p>
		<p class="description">
		<?php
		esc_html_e( 'For example if you want to ignore 404 errors URLs with', 'seo-booster' );
		?>
		<code>well-known.tar.gz</code> or <code>.php.suspected</code></p><p class="description">
		<?php
		esc_html_e( 'Separate with a comma.', 'seo-booster' );
		?>
		</p>
		</td>
		</tr>
		<tr valign="top">
		<th colspan="2">
		<h2>
		<?php
		esc_html_e( 'Debug Logging', 'seo-booster' );
		?>
		</h2>
		</th>
		</tr>
		<tr valign="top">
		<th scope="row" valign="top">
		<p>
		<?php
		esc_html_e( 'Enable/Disable', 'seo-booster' );
		?>
		</p>
		</th>
		<td>
		<input type="checkbox" id="seobooster_debug_logging" name="seobooster_debug_logging" value="on" 
		<?php
		if ( 'on' === $debug_logging ) {
			echo " checked='checked'";
		}
		?>
		/>
		<p class="description"><label for="seobooster_debug_logging">
		<?php
		esc_html_e( 'If turned on, the log will have debug information that can be helpful for finding errors or configuration issues.', 'seo-booster' );
		?>
		</label></p>
		</td>
		</tr>
		<?php
		// premium only
		?>
		<tr valign="top">
		<th colspan="2">
		<hr>
		</th>
		</tr>
		<tr valign="top">
		<th scope="row" valign="top">
		<?php
		esc_html_e( 'Delete data on deactivate:', 'seo-booster' );
		?>
		</th>
		<td>
		<input type="checkbox" id="seobooster_delete_deactivate" name="seobooster_delete_deactivate" value="on"
		<?php
		if ( 'on' === $seobooster_delete_deactivate ) {
			echo " checked='checked'";
		}
		?>
		/>
		<p class="description"><label for="seobooster_delete_deactivate">
		<?php
		esc_html_e( 'Turn this on to delete all data when deactivating the plugin. This cannot be undone.', 'seo-booster' );
		?>
		</label></p>
		<p class="description">
		<?php
		esc_html_e( 'WordPress Multisite users: Careful! Turning this on and deactivating the plugin deletes ALL SEO Booster database tables on ALL sites.', 'seo-booster' );
		?>
		</p>
		</td>
		</tr>
		<tr>
		<td colspan="2">
		<input type="hidden" name="page" value="sb2_settings">

		<?php
		submit_button();
		?>
		</td>
		</tr>
		</tbody>
		</table>
		<?php
		wp_nonce_field( 'seobooster_save_settings' );
		?>
		</form>
		<hr>
		<h3>
		<?php
		esc_html_e( 'Tools', 'seo-booster' );
		?>
		</h3>
		<form method="post">
		<?php
		wp_nonce_field( 'seobooster_do_actions' );
		?>
		<table class="form-table">
		<tbody>
		<tr valign="top">
		<th scope="row" valign="top">
		<?php
		esc_html_e( 'Database Update', 'seo-booster' );
		?>
		</th>
		<td>
		<?php
		submit_button( __( 'Run Update Database', 'seo-booster' ), 'secondary', 'submit_dbupdates' );
		?>
		<label class="description" for="submit">
		<?php
		esc_html_e( 'If you need to manually run the database updates. No need to use unless directed by support.', 'seo-booster' );
		?>
		</label>
		</td>
		</tr>

		<tr valign="top">
		<th scope="row" valign="top">
		<?php
		esc_html_e( 'Guided Tour', 'seo-booster' );
		?>
		</th>
		<td>
		<?php
		submit_button( __( 'See Guided Tour', 'seo-booster' ), 'secondary', 'reset_guided_tours' );
		?>
		<p class="description" for="reset_guided_tours">
		<?php
		esc_html_e( 'Click to start the Guided Tour again.', 'seo-booster' );
		?>
		</p>
		</td>
		</tr>
		<tr valign="top">
		<th scope="row" valign="top">
		<?php
		esc_html_e( 'Account Reset', 'seo-booster' );
		?>
		</th>
		<td>
		<a href="<?php echo esc_url( $seobooster_fs->get_reconnect_url() ); ?>">
							<?php
							esc_html_e( 'Click to Reset', 'seo-booster' );
							?>
		</a>
		<p class="description" for="reset_account_details">
		<?php
		esc_html_e( 'Click to reset and start registration process again.', 'seo-booster' );
		?>
		</p>
		</td>
		</tr>
		</tbody>
		</table>
		</form>
		</div>
