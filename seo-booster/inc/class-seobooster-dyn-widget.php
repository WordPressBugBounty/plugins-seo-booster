<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Seobooster_Dyn_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'sb2_dynwidget',
			__( 'SEO Booster - Dynamic Links', 'seo-booster' ),
			array( 'description' => __( 'List of links to other pages with anchor text detected from search engines.', 'seo-booster' ) )
		);
	}

	public function widget( $args, $instance ) {
		global $seobooster2, $wpdb;
		extract( $args );
		$title      = apply_filters( 'widget_title', $instance['title'] );
		$listtype   = $instance['listtype'];
		$limit      = $instance['limit'];
		if (isset($instance['showvisits'])) {
			$showvisits = $instance['showvisits'];
		}
		else {
			$showvisits = false;
		}

		$output = $before_widget;
		if ( ! empty( $title ) ) {
			$output .= $before_title . $title . $after_title;
		}

		global $wp_query;

		$currurl = $seobooster2->seobooster_currenturl();
		$currurl = strtok( $currurl, '?' ); // Strips parameters

		if ( ! is_int( $limit ) ) {
			$limit = '10';
		}

		$sqlignore = $seobooster2->seobooster_generateignorelist();

		if ( 'hightraffic' === $listtype ) {
			// high traffic
			$query = "SELECT DISTINCT lp,kw,lastvisit,visits FROM {$wpdb->prefix}sb2_kw WHERE $sqlignore ig='0' AND kw<>'#' AND lp<>'$currurl' AND engine<>'Internal Search' group by kw ORDER BY visits DESC LIMIT $limit;";
		}

		if ( 'lowtraffic' === $listtype ) {
			// low traffic
			$query = "SELECT DISTINCT lp,kw,lastvisit,visits FROM {$wpdb->prefix}sb2_kw WHERE $sqlignore ig='0' AND kw<>'#' AND lp<>'$currurl' AND engine<>'Internal Search' GROUP BY kw  ORDER BY visits ASC LIMIT $limit;";
		}

		if ( 'latest' === $listtype ) {
			// show latest keywords
			$query = "SELECT DISTINCT lp,kw,lastvisit,visits FROM {$wpdb->prefix}sb2_kw WHERE $sqlignore ig='0' AND kw<>'#' AND lp<>'$currurl' AND engine<>'Internal Search' GROUP BY kw ORDER BY lastvisit ASC LIMIT $limit;";
		}

		$posthits = $wpdb->get_results( $query, ARRAY_A );

		if ( $posthits ) {
			$output .= '<ul>';
			foreach ( $posthits as $ph ) {
				$permalink = '';
				$visits    = $ph['visits'];
				$permalink = $ph['lp'];
				if ( $permalink ) {
					$output .= "<li><a href='" . esc_url( $permalink ) . "'>" . esc_attr(  stripslashes( $ph['kw'] ) ) . '</a>';

					if ( $showvisits ) {
						$output .= " ($visits)";
					}

					$output .= '</li>';
				}
			}
			$output .= '</ul>';
			$output .= $after_widget;
		} else {
			// no hits - do not show widget
			$output = '';
		}
		echo wp_kses( $output, wp_allowed_protocols() );
	}

	/**
	 * update.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	public
	 * @param	mixed	$new_instance	
	 * @param	mixed	$old_instance	
	 * @return	mixed
	 */
	public function update( $new_instance, $old_instance ) {
		$instance             = array();
		$instance['title']    = wp_strip_all_tags( $new_instance['title'] );
		$instance['listtype'] = wp_strip_all_tags( $new_instance['listtype'] );
		$instance['limit']    = intval( wp_strip_all_tags( $new_instance['limit'] ) );
		if ( isset( $new_instance['showvisits'] ) ) {
			$instance['showvisits'] = wp_strip_all_tags( $new_instance['showvisits'] );
		}
		return $instance;
	}

	/**
	 * form.
	 *
	 * @author	Lars Koudal
	 * @since	v0.0.1
	 * @version	v1.0.0	Wednesday, March 27th, 2024.
	 * @access	public
	 * @param	mixed	$instance	
	 * @return	void
	 */
	public function form( $instance ) {

		if ( isset( $instance['title'] ) ) {
			$title = $instance['title'];
		} else {
			$title = __( 'Internal Links', 'seo-booster' );
		}

		if ( isset( $instance['listtype'] ) ) {
			$listtype = $instance['listtype'];
		} else {
			$listtype = 'hightraffic';
		}

		if ( isset( $instance['limit'] ) ) {
			$limit = $instance['limit'];
		} else {
			$limit = '10';
		}

		if ( '0' === $limit ) {
			$limit = '10';
		}

		if ( isset( $instance['showvisits'] ) ) {
			$showvisits = $instance['showvisits'];
		} else {
			$showvisits = '';
		}

		?>


			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'seo-booster' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
				<br />
				<small><?php esc_html_e( 'The Widget title.', 'seo-booster' ); ?></small>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'Limit:', 'seo-booster' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="text" value="<?php echo esc_attr( $limit ); ?>" />
				<br />
				<small><?php esc_html_e( 'The maximum amount of links. Defaults to 10.', 'seo-booster' ); ?></small>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'listtype' ) ); ?>"><?php esc_html_e( 'What to show:', 'seo-booster' ); ?></label>
				<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'listtype' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'listtype' ) ); ?>">
					<option value="hightraffic"
				<?php
				if ( 'hightraffic' === $listtype ) {
					echo ' selected="selected"';
				}
				?>
					><?php esc_html_e( 'Pages with the most SEO traffic', 'seo-booster' ); ?></option>
					<option value="lowtraffic"
					<?php
					if ( 'lowtraffic' === $listtype ) {
						echo ' selected="selected"';
					}
					?>
					><?php esc_html_e( 'Pages with little SEO traffic', 'seo-booster' ); ?></option>

					<option value="latest"
					<?php
					if ( 'latest' === $listtype ) {
						echo ' selected="selected"';
					}
					?>
					><?php esc_html_e( 'Show the latest keywords first', 'seo-booster' ); ?></option>

				</select>

				<br />
				<small>Choosing to show links to most trafficked paged can boost them even further. If you want to help along low performing keywords, you should show the pages with little traffic.</small>
			</p>



			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'showvisits' ) ); ?>">Show number of visits:</label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'showvisits' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'showvisits' ) ); ?>" type="checkbox" value="on"
				<?php
				if ( 'on' === $showvisits ) {
					echo ' checked';
				}
				?>
				/>

				<br />
				<small>Turn on showing number of visits after each keyword.</small>
			</p>


			<?php
	}

}
