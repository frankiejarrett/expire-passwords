<?php

namespace Expire_Passwords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class List_Table {

	/**
	 * Plugin instance
	 *
	 * @var Expire_Passwords_Plugin
	 */
	private $plugin;

	/**
	 * Class constructor
	 */
	public function __construct( \Expire_Passwords_Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_head', array( $this, 'admin_css' ) );
		add_filter( 'manage_users_columns', array( $this, 'users_column' ) );
		add_action( 'manage_users_custom_column', array( $this, 'render_users_column' ), 10, 3 );
	}

	/**
	 * Print custom CSS styles for the users.php screen
	 *
	 * @action admin_head
	 *
	 * @return void
	 */
	public function admin_css() {
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) || 'users' !== $screen->id ) {
			return;
		}
		?>
		<style type="text/css">
			.fixed .column-<?php echo esc_html( $this->plugin->prefix ) ?> {
				width: 150px;
			}
			@media screen and (max-width: 782px) {
				.fixed .column-<?php echo esc_html( $this->plugin->prefix ) ?> {
					display: none;
				}
			}
			.<?php echo esc_html( $this->plugin->prefix ) ?>-is-expired {
				color: #a00;
			}
		</style>
		<?php
	}

	/**
	 * Add a custom column to the Users list table
	 *
	 * @filter manage_users_columns
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function users_column( $columns ) {
		$columns[ $this->plugin->prefix ] = esc_html__( 'Password Reset', 'expire-passwords' );

		return $columns;
	}

	/**
	 * Add content to the custom column in the Users list table
	 *
	 * @action manage_users_custom_column
	 *
	 * @param string $value
	 * @param string $column_name
	 * @param int    $user_id
	 *
	 * @return string
	 */
	public function render_users_column( $value, $column_name, $user_id ) {
		if ( $this->plugin->prefix === $column_name ) {
			$reset = $this->plugin->get_user_meta( $user_id );

			if ( ! $this->plugin->has_expirable_role( $user_id ) || ! $reset ) {
				$value = '&mdash;';
			} else {
				$time_diff = sprintf( __( '%1$s ago', 'expire-passwords' ), human_time_diff( $reset, time() ) );

				if ( $this->plugin->is_password_expired( $user_id ) ) {
					$value = sprintf( '<span class="%s-is-expired">%s</span>', esc_attr( $this->plugin->prefix ), esc_html( $time_diff ) );
				} else {
					$value = sprintf( '<span class="%s-not-expired">%s</span>', esc_attr( $this->plugin->prefix ), esc_html( $time_diff ) );
				}
			}
		}

		return $value; // xss ok
	}

}
