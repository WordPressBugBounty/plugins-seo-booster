<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seobooster_Keywords_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'seobooster_keywords_widget',
			__( 'SEO Booster - Incoming Keywords', 'seo-booster' ),
			array( 'description' => 'Shows keywords used to find the current page. Widget does not display if no terms is found.' )
		);
	}
	public function widget( $args, $instance ) {
		global $seobooster2, $wp_query, $wpdb;

		extract( $args );
		$title    = apply_filters( 'widget_title', $instance['title'] );
		$currurl  = $seobooster2->seobooster_currenturl();
		$currurl  = strtok( $currurl, '?' ); // Strips parameters
		$keywords = $seobooster2->list_keywords( 10, $currurl );

		// If no keywords found, back again, no need to show the widget then..
		if ( ! $keywords ) {
			return;
		}

		$output  = '';
		$output .= $before_widget;
		if ( ! empty( $title ) ) {
			$output .= $before_title . esc_attr( $title ) . $after_title;
		}
		$output .= '<div style="padding:20px;">' . $keywords . '</div>'; 
		$output .= $after_widget;
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
		$instance = array();
		$instance['title'] = wp_strip_all_tags( $new_instance['title'] );
		$instance['limit'] = intval( wp_strip_all_tags( $new_instance['limit'] ) );
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
		global $seobooster2;
		if ( isset( $instance['title'] ) ) {
			$title = $instance['title'];
		} else {
			$title = __( 'Tagged With', 'seo-booster' );
		}
		if ( isset( $instance['limit'] ) ) {
			$limit = $instance['limit'];
		} else {
			$limit = '10';
		}

		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'seo-booster' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'Limit:', 'seo-booster' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="text" value="<?php echo esc_attr( $limit ); ?>" />
			<br />
			<small>The maximum amount of links. Defaults to 10.</small>
		</p>

		<?php
	}

}
