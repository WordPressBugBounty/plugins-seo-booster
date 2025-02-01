<?php

namespace Cleverplugins\SEOBooster;
// don't load directly
if (!defined('ABSPATH')) {
    exit;
}

// Verify user capabilities
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'seo-booster'));
}
?>
<div class="wrap">
    <?php
    echo wp_kses_post(Utils::show_plugin_headline(esc_html__('Automatic keywords to links', 'seo-booster'), true));
    ?>
    <div id="sb2_autolink_add" class="card">
        <h2><?php
            esc_html_e('Add new link', 'seo-booster');
            ?></h2>

        <p><?php
            esc_html_e('Enter keyword and which URL the keyword should link til. Works with internal and external links.', 'seo-booster');
            ?></p>


<form method="get" id="sb2_autolink_add_form" style="display: flex; flex-wrap: wrap; align-items: center; width: 100%; flex-direction: column;">
    <div style="width: 100%; margin-bottom: 10px;">
        <label for="newkeyword" style="display: block; margin-bottom: 5px;">
            <?php esc_html_e('Keyword', 'seo-booster'); ?>
        </label>
        <input name="newkeyword" type="text" id="newkeyword" placeholder="<?php esc_html_e('Enter keyword', 'seo-booster'); ?>" value="" style="width: 100%;" required>
    </div>

    <div style="width: 100%; margin-bottom: 10px;">
        <label for="targeturl" style="display: block; margin-bottom: 5px;">
            <?php esc_html_e('Target URL', 'seo-booster'); ?>
        </label>
        <input name="targeturl" type="url" id="targeturl" placeholder="https://" value="" style="width: 100%;" required>
    </div>

    <div style="width: 100%;">
        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_html_e('Add Keyword', 'seo-booster'); ?>" style="width: 100%;">
    </div>

    <input name="action" type="hidden" value="ajax_add_keyword" />
    <?php wp_nonce_field('add-keyword-nonce', '_ajax_sb2_add_keyword_nonce'); ?>
</form>


        <div id="addkwresponse" style="margin-top: 10px;"></div>
        <div class="kwaddspinner" style="display:none; margin-top: 10px;">
            <div class="bounce1"></div>
            <div class="bounce2"></div>
            <div class="bounce3"></div>
        </div>
    </div>
    <div id="sb2fof" class="clearfix clear">
        <div>
            <p><?php
                esc_html_e('Add keywords or phrases that should be changed in your posts to links.', 'seo-booster');
                ?></p>

        </div>
    </div>
    <?php
    global  $wpdb;
    $duplicate_ids = $wpdb->get_col("SELECT t1.id FROM {$wpdb->prefix}sb2_autolink t1 INNER JOIN {$wpdb->prefix}sb2_autolink t2 ON t1.id > t2.id AND t1.keyword = t2.keyword");
    $deleted_dupes = $wpdb->delete(
        $wpdb->prefix . 'sb2_autolink',
        array(
            'id' => $duplicate_ids,
        ),
        array('%d')
    );

    if ($deleted_dupes > 0) {
    ?>

        <div class="notice notice-info">
            <p>
                <?php
                // translators:
                echo  esc_html(sprintf(__('<code>%s</code> duplicate keyword links were removed automatically. ', 'seo-booster'), number_format_i18n($deleted_dupes)));
                ?>
            </p>
            <p><input type="button" class="button button-secondary" value="<?php esc_html_e('Click to reload page', 'seo-booster'); ?>" onClick="window.location.reload()"></p>
        </div>
    <?php
    }

    $lookupkwlimit = 1000;
    $stepcount = 0;

    $stepcount = 0;
    $internal_linking = get_option('seobooster_internal_linking');

    if (!$internal_linking) {
    ?>
        <div class="notices notice-info">
            <p>
                <?php
                esc_html_e('Automatic Linking is turned off! Turn on the option in the settings page.', 'seo-booster');
                ?>
            </p>
        </div>
    <?php
    }

    ?>


    <form id="urls-filter" method="get">
        <?php
        $page = isset($_REQUEST['page']) ? sanitize_text_field(wp_unslash($_REQUEST['page'])) : '';
        ?>
        <input type="hidden" name="page" value="<?php echo esc_attr($page); ?>" />
        <?php

        if (isset($_REQUEST['order'])) {
        ?>
            <input type="hidden" name="order" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['order']))); ?>" />
        <?php
        }

        if (isset($_REQUEST['orderby'])) {
        ?>
            <input type="hidden" name="orderby" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_REQUEST['orderby']))); ?>" />
        <?php
        }
        $autolink_list_table->search_box(__('Search', 'seo-booster'), 'search_id');
        $autolink_list_table->display();
        ?>
    </form>
</div>