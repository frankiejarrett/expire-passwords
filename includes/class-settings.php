<?php

namespace Expire_Passwords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Expire_Passwords_Plugin as Plugin;

class Settings {

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'submenu_page' ) );
		add_action( 'admin_init', array( $this, 'init' ) );
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ) );
	}

	/**
	 * Add custom submenu page under the Users menu
	 *
	 * @action admin_menu
	 *
	 * @return null
	 */
	public function submenu_page() {
		add_submenu_page(
			'users.php',
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			'manage_options',
			'expire_passwords',
			array( $this, 'render_submenu_page' )
		);
	}

	/**
	 * Content for the custom submenu page under the Users menu
	 *
	 * @see self::add_submenu_page()
	 *
	 * @return null
	 */
	public function render_submenu_page() {
		?>
		<div class="wrap">

			<h2><?php esc_html_e( 'Expire Passwords', 'expire-passwords' ) ?></h2>

			<form method="post" action="options.php">

				<?php settings_fields( Plugin::$prefix . '_settings_page' ) ?>

				<?php do_settings_sections( Plugin::$prefix . '_settings_page' ) ?>

				<?php submit_button() ?>

			</form>

		</div>
		<?php
	}

	/**
	 * Register custom setting sections and fields
	 *
	 * @action admin_init
	 *
	 * @return null
	 */
	public function init() {
		register_setting(
			Plugin::$prefix . '_settings_page',
			Plugin::$prefix . '_settings'
		);

		add_settings_section(
			Plugin::$prefix . '_settings_page_section',
			null,
			array( $this, 'render_section' ),
			Plugin::$prefix . '_settings_page'
		);

		add_settings_field(
			Plugin::$prefix . '_settings_field_limit',
			esc_html__( 'Require password reset every', 'expire-passwords' ),
			array( $this, 'render_field_limit' ),
			Plugin::$prefix . '_settings_page',
			Plugin::$prefix . '_settings_page_section'
		);

		add_settings_field(
			Plugin::$prefix . '_settings_field_roles',
			esc_html__( 'For users in these roles', 'expire-passwords' ),
			array( $this, 'render_field_roles' ),
			Plugin::$prefix . '_settings_page',
			Plugin::$prefix . '_settings_page_section'
		);
	}

	/**
	 * Content for the custom settings section
	 *
	 * @see self::init()
	 *
	 * @return null
	 */
	public function render_section() {
		?>
		<p>
			<?php esc_html_e( 'Require certain users to change their passwords on a regular basis.', 'expire-passwords' ) ?>
		</p>
		<?php
	}

	/**
	 * Content for the limit setting field
	 *
	 * @see self::init()
	 *
	 * @return null
	 */
	public function render_field_limit() {
		$options = get_option( Plugin::$prefix . '_settings' );
		$value   = isset( $options['limit'] ) ? $options['limit'] : null;
		?>
		<input type="number" min="1" max="365" maxlength="3" name="<?php printf( '%s_settings[limit]', Plugin::$prefix ) ?>" placeholder="<?php echo esc_attr( Plugin::$default_limit ) ?>" value="<?php echo esc_attr( $value ) ?>">
		<?php
		esc_html_e( 'days', 'expire-passwords' );
	}

	/**
	 * Content for the roles setting field
	 *
	 * @see self::init()
	 *
	 * @return null
	 */
	public function render_field_roles() {
		$options = get_option( Plugin::$prefix . '_settings' );
		$roles   = get_editable_roles();

		foreach ( $roles as $role => $role_data ) :
			$name  = sanitize_key( $role );

			if ( empty( $options ) ) {
				// Select all roles except admins by default if not set
				$value = ( 'administrator' === $role ) ? 0 : 1;
			} else {
				$value = empty( $options['roles'][ $name ] ) ? 0 : 1;
			}
			?>
			<p>
				<input type="checkbox" name="<?php printf( '%s_settings[roles][%s]', Plugin::$prefix, esc_attr( $name ) ) ?>" id="<?php printf( '%s_settings[roles][%s]', Plugin::$prefix, esc_attr( $name ) ) ?>" <?php checked( $value, 1 ) ?> value="1">
				<label for="<?php printf( '%s_settings[roles][%s]', Plugin::$prefix, esc_attr( $name ) ) ?>"><?php echo esc_html( $role_data['name'] ) ?></label>
			</p>
			<?php
		endforeach;
	}

	/**
	 * Plugin review call-to-action text for the admin footer
	 *
	 * @filter admin_footer_text
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function admin_footer_text( $text ) {
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) || 'users_page_expire_passwords' !== $screen->id ) {
			return $text;
		}

		$text = sprintf(
			__( 'Do you like the %1$s plugin? Please consider %2$s on %3$s', 'expire-passwords' ),
			esc_html__( 'Expire Passwords', 'expire-passwords' ),
			sprintf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( 'https://wordpress.org/support/view/plugin-reviews/expire-passwords#postform' ),
				__( 'leaving a &#9733;&#9733;&#9733;&#9733;&#9733; review', 'expire-passwords' )
			),
			sprintf(
				'<a href="%s" target="_blank">WordPress.org</a>',
				esc_url( 'https://wordpress.org/plugins/expire-passwords/' )
			)
		);

		return $text;
	}

}