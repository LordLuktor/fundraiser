<?php
/**
 * Template Manager for frontend templates.
 *
 * @package FundraiserPro
 */

namespace FundraiserPro;

/**
 * TemplateManager class.
 */
class TemplateManager {

	/**
	 * Load template.
	 *
	 * @param string $template Template path.
	 * @return string Modified template path.
	 */
	public function load_template( $template ) {
		global $post;

		if ( ! $post ) {
			return $template;
		}

		// Check for our post types
		$post_types = array( 'fundraiser_campaign', 'fundraiser_raffle' );

		if ( in_array( $post->post_type, $post_types, true ) ) {
			return $this->get_custom_template( $template, $post->post_type );
		}

		return $template;
	}

	/**
	 * Load single template.
	 *
	 * @param string $template Template path.
	 * @return string Modified template path.
	 */
	public function load_single_template( $template ) {
		global $post;

		if ( ! $post ) {
			return $template;
		}

		if ( 'fundraiser_campaign' === $post->post_type ) {
			$custom_template = $this->locate_template( 'single-campaign.php' );
			return $custom_template ? $custom_template : $template;
		}

		if ( 'fundraiser_raffle' === $post->post_type ) {
			$custom_template = $this->locate_template( 'single-raffle.php' );
			return $custom_template ? $custom_template : $template;
		}

		return $template;
	}

	/**
	 * Load archive template.
	 *
	 * @param string $template Template path.
	 * @return string Modified template path.
	 */
	public function load_archive_template( $template ) {
		if ( is_post_type_archive( 'fundraiser_campaign' ) ) {
			$custom_template = $this->locate_template( 'archive-campaigns.php' );
			return $custom_template ? $custom_template : $template;
		}

		if ( is_post_type_archive( 'fundraiser_raffle' ) ) {
			$custom_template = $this->locate_template( 'archive-raffles.php' );
			return $custom_template ? $custom_template : $template;
		}

		return $template;
	}

	/**
	 * Get custom template.
	 *
	 * @param string $template  Default template.
	 * @param string $post_type Post type.
	 * @return string Template path.
	 */
	private function get_custom_template( $template, $post_type ) {
		$custom_template = $this->locate_template( $post_type . '.php' );
		return $custom_template ? $custom_template : $template;
	}

	/**
	 * Locate template.
	 *
	 * @param string $template_name Template name.
	 * @return string|false Template path or false.
	 */
	private function locate_template( $template_name ) {
		// Check theme override
		$theme_template = locate_template( array(
			'fundraiser-pro/' . $template_name,
			$template_name,
		) );

		if ( $theme_template ) {
			return $theme_template;
		}

		// Check plugin templates
		$plugin_template = FUNDRAISER_PRO_PATH . 'templates/' . $template_name;

		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return false;
	}

	/**
	 * Get template part.
	 *
	 * @param string $slug Template slug.
	 * @param string $name Template name.
	 * @param array  $args Template arguments.
	 */
	public static function get_template_part( $slug, $name = '', $args = array() ) {
		$template = '';

		if ( $name ) {
			$template = locate_template( array(
				"fundraiser-pro/{$slug}-{$name}.php",
				"{$slug}-{$name}.php",
			) );
		}

		if ( ! $template ) {
			$template = locate_template( array(
				"fundraiser-pro/{$slug}.php",
				"{$slug}.php",
			) );
		}

		if ( ! $template ) {
			$template = FUNDRAISER_PRO_PATH . "templates/{$slug}.php";
			if ( $name && file_exists( FUNDRAISER_PRO_PATH . "templates/{$slug}-{$name}.php" ) ) {
				$template = FUNDRAISER_PRO_PATH . "templates/{$slug}-{$name}.php";
			}
		}

		if ( file_exists( $template ) ) {
			extract( $args );
			include $template;
		}
	}
}
