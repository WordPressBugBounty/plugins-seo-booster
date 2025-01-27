<?php
if (! defined('ABSPATH')) {
    exit;
}

use Cleverplugins\SEOBooster\Utils;
?>
<div class="wrap">

    <div id="scroll-navbar">
        <div class="scroll-navbar-inner">
            <?php
            echo '<span style="margin-right:10px;"><img src="' . esc_url(SEOBOOSTER_PLUGINURL . 'images/sbsquaretrans.png') . '" height="40" class="SEO Booster logo" alt="SEO Booster"></span>'; 
            ?>
             <select id="section-select"></select>
            <a href="#" id="back-to-top" class="back-to-top alignright"><?php esc_html_e('Back to Top', 'seo-booster'); ?></a>
           
        </div>
    </div>




    <?php
    // Initialize TOC array
    $toc = [];



    /**
     * add_to_toc.
     *
     * @author	Unknown
     * @since	v0.0.1
     * @version	v1.0.0	Tuesday, August 20th, 2024.
     * @param	mixed	&$toc 	
     * @param	mixed	$id   	
     * @param	mixed	$text 	
     * @param	mixed	$group	Default: null
     * @return	void
     */
    function add_to_toc(&$toc, $id, $text, $group = null)
    {
        if ($group) {
            // Ensure the group exists and is properly initialized
            if (!isset($toc[$group])) {
                $toc[$group] = [
                    'text' => $group,
                    'children' => []
                ];
            }
            // Add the item to the group's children
            $toc[$group]['children'][] = ['id' => $id, 'text' => $text];
        } else {
            // Add the item to the main TOC
            $toc[] = ['id' => $id, 'text' => $text];
        }
    }
    // Collect TOC items

    add_to_toc($toc, 'top-pages', __('Top Pages', 'seo-booster'), __('Top Pages', 'seo-booster'));
    add_to_toc($toc, 'new-keywords', __('New Keywords', 'seo-booster'), __('Keyword Analysis', 'seo-booster'));
    add_to_toc($toc, 'top-performing-keywords', __('Top Performing Keywords', 'seo-booster'), __('Keyword Analysis', 'seo-booster'));
    add_to_toc($toc, 'most-improved-keywords', __('Keywords with Most Improvements', 'seo-booster'), __('Keyword Analysis', 'seo-booster'));
    add_to_toc($toc, 'declining-keywords', __('Keywords with Declining Performance', 'seo-booster'), __('Keyword Analysis', 'seo-booster'));
    add_to_toc($toc, 'long-tail-keywords', __('Long-Tail Keyword Opportunities', 'seo-booster'), __('Keyword Analysis', 'seo-booster'));
    add_to_toc($toc, 'inactive-keywords', __('Keywords Not Getting Traffic Anymore', 'seo-booster'), __('Keyword Analysis', 'seo-booster'));
    add_to_toc($toc, 'keyword-canabalisations', __('Keyword Terms Leading to Different Pages', 'seo-booster'), __('Keyword Analysis', 'seo-booster'));
    add_to_toc($toc, 'ctr-improvement-opportunities', __('CTR Improvement Opportunities', 'seo-booster'), __('Query Analysis', 'seo-booster'));
    add_to_toc($toc, '404-missing-pages', __('Missing 404 Pages', 'seo-booster'), __('Error Analysis', 'seo-booster'));
    add_to_toc($toc, 'question-queries', __('Question-Based Queries', 'seo-booster'), __('Query Analysis', 'seo-booster'));

    ?>
    <div class="report">
        <div class="reportheader">
            <div class="reportintro col">
                <h1><?php esc_html_e('SEO Booster Report Dashboard', 'seo-booster'); ?></h1>

                <p><?php esc_html_e('This report offers a deep dive into the key aspects of your website\'s search engine optimization, providing actionable insights to help you enhance your online visibility.', 'seo-booster'); ?></p>

                <p><?php esc_html_e('We\'ve grouped the findings into clear categories, allowing you to easily navigate through the various sections, such as', 'seo-booster'); ?>
                    <a href="#new-keywords"><?php esc_html_e('New Keywords', 'seo-booster'); ?></a>
                    <?php esc_html_e('and', 'seo-booster'); ?>
                    <a href="#top-pages"><?php esc_html_e('Top Pages', 'seo-booster'); ?></a>.
                </p>

                <p><?php esc_html_e('Whether you\'re looking to capitalize on new keyword opportunities, improve your click-through rates, or identify areas where performance is declining, this report is your guide to optimizing your site\'s SEO strategy.', 'seo-booster'); ?></p>
            </div>

            <div class="col reporttoc">
                <?php if (!empty($toc)) : ?>
                    <div class="toc" id="toc">
                        <h2><?php echo esc_html__('Table of Contents', 'seo-booster'); ?></h2>
                        <?php foreach ($toc as $key => $item) : ?>
                            <?php if (isset($item['children'])) : ?>
                                <h4><?php echo esc_html($item['text']); ?></h4>
                                <ul>
                                    <?php foreach ($item['children'] as $child) : ?>
                                        <li>
                                            <a href="#<?php echo esc_attr($child['id']); ?>">
                                                <?php echo esc_html($child['text']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif (is_int($key)) : ?>
                                <h4>
                                    <a href="#<?php echo esc_attr($item['id']); ?>">
                                        <?php echo esc_html($item['text']); ?>
                                    </a>
                                </h4>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>


        </div>




        <!-- Report Sections -->






        <div class="report-section">
            <div class="title">
                <h2 id="top-pages"><?php echo esc_html__('Top Pages', 'seo-booster'); ?></h2>
                <a href="#top-pages" class="quick-link">#</a>
            </div>
            <div class="content">
            <div class="description">
            <p><?php esc_html_e('This report shows the top performing pages on your website.', 'seo-booster'); ?></p>
            </div>
            <div class="table-container" data-table-id="top-pages">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>


        <div class="report-section">
            <div class="title">
                <h2 id="new-keywords"><?php echo esc_html__('New Keywords', 'seo-booster'); ?></h2>
                <a href="#new-keywords" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the new keywords that show up in our history for the first time in the last 30 days.', 'seo-booster'); ?></p>
                </div>
                <div class="table-container" data-table-id="new-keywords">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>



        <div class="report-section">
            <div class="title">
                <h2 id="keyword-canabalisations"><?php echo esc_html__('Keyword Terms Leading to Different Pages', 'seo-booster'); ?></h2>
                <a href="#keyword-canabalisations" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the keyword terms that lead to different pages on your website. This can help you identify pages that are competing for the same keywords and optimize them accordingly.', 'seo-booster'); ?></p>
                </div>
                <div class="table-container" data-table-id="keyword-canabalisations">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>






        <div class="report-section">
            <div class="title">
                <h2 id="inactive-keywords"><?php echo esc_html__('Keywords Not Getting Traffic Anymore', 'seo-booster'); ?></h2>
                <a href="#inactive-keywords" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the keywords that are not getting traffic anymore. This can be due to a change in the content or a change in the search intent.', 'seo-booster'); ?></p>
                </div>
                <div class="table-container" data-table-id="inactive-keywords">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>



        <div class="report-section">
            <?php
            $days = 30;
            ?>
            <div class="title">
                <h2 id="most-improved-keywords"><?php echo esc_html__('Keywords with Most Improvements', 'seo-booster'); ?></h2>
                <a href="#most-improved-keywords" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the keywords that have improved the most in the last 30 days.', 'seo-booster'); ?></p>
                </div>
                <div class="table-container" data-table-id="most-improved-keywords">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>



        <div class="report-section">
            <div class="title">
                <h2 id="top-performing-keywords"><?php echo esc_html__('Top Performing Keywords', 'seo-booster'); ?></h2>
                <a href="#top-performing-keywords" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the top performing keywords on your website.', 'seo-booster'); ?></p>
                </div>
                <div class="table-container" data-table-id="top-performing-keywords">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-section">
            <div class="title">
                <h2 id="declining-keywords"><?php echo esc_html__('Keywords with Declining Performance', 'seo-booster'); ?></h2>
                <a href="#declining-keywords" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the keywords that are declining in performance. This can help you identify pages that are not performing well and optimize them accordingly.', 'seo-booster'); ?></p>
                </div>
                <div class="table-container" data-table-id="declining-keywords">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>



        <div class="report-section">
            <div class="title">
                <h2 id="long-tail-keywords"><?php echo esc_html__('Long-Tail Keyword Opportunities', 'seo-booster'); ?></h2>
                <a href="#long-tail-keywords" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the long-tail keyword opportunities on your website. These are keywords that are not very competitive and have a good chance of ranking higher in the search results.', 'seo-booster'); ?></p>
                </div>
                <div class="table-container" data-table-id="long-tail-keywords">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>


        <!-- Report Sections -->
        <div class="report-section">
            <div class="title">
                <h2 id="question-queries"><?php echo esc_html__('Question-Based Queries', 'seo-booster'); ?></h2>
                <a href="#question-queries" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the question-based queries on your website. These are queries that can be used to give you inspiration for new content or to optimize existing content.', 'seo-booster'); ?></p>
                </div>
                <div class="table-container" data-table-id="question-queries">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-section">
            <div class="title">
                <h2 id="ctr-improvement-opportunities"><?php echo esc_html__('CTR Improvement Opportunities', 'seo-booster'); ?></h2>
                <a href="#ctr-improvement-opportunities" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the CTR improvement opportunities on your website.', 'seo-booster'); ?></p>
                <div class="table-container" data-table-id="ctr-improvement-opportunities">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-section">
            <div class="title">
                <h2 id="404-missing-pages"><?php echo esc_html__('404 Errors - Missing Pages', 'seo-booster'); ?></h2>
                <a href="#404-missing-pages" class="quick-link">#</a>
            </div>
            <div class="content">
                <div class="description">
                    <p><?php esc_html_e('This report shows the 404 errors on your website. This is either because you are linking to the wrong page or because the page has been deleted. It could also be an external link that is broken.', 'seo-booster'); ?></p>
                </div>
                <div class="table-container" data-table-id="404-missing-pages">
                    <div class="table-loading">
                        <span class="spinner is-active"></span>
                        <p><?php esc_html_e('Loading data...', 'seo-booster'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php if (isset($report_meta)) : ?>
            <div class="report-generation-info">
                <h3><?php echo esc_html__('Report Generation Information', 'seo-booster'); ?></h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Report', 'seo-booster'); ?></th>
                            <th><?php echo esc_html__('Last Generated', 'seo-booster'); ?></th>
                            <th><?php echo esc_html__('Generation Time', 'seo-booster'); ?></th>
                            <th><?php echo esc_html__('Next Update', 'seo-booster'); ?></th>
                            <th><?php echo esc_html__('Time Until Next Update', 'seo-booster'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_meta as $key => $meta) : ?>
                            <tr>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $meta['generated_at'])); ?></td>
                                <td><?php echo esc_html(number_format_i18n($meta['generation_time'], 2)) . ' ' . esc_html__('seconds', 'seo-booster'); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $meta['next_update'])); ?></td>
                                <td><?php echo esc_html(human_time_diff(current_time('timestamp'), $meta['next_update'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>