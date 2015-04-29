<?php

class Expire_Passwords_List_Table {

	/**
	 * Fire hooks
	 *
	 * @action init
	 *
	 * @return void
	 */
	public static function load() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		add_action( 'admin_head', array( __CLASS__, 'admin_css' ) );
		add_filter( 'manage_users_columns', array( __CLASS__, 'users_column' ) );
		add_action( 'manage_users_custom_column', array( __CLASS__, 'render_users_column' ), 10, 3 );
	}

	/**
	 * Print custom CSS styles for the users.php screen
	 *
	 * @action admin_head
	 *
	 * @return void
	 */
	public static function admin_css() {
		$screen = get_current_screen();

		if ( ! isset( $screen->id ) || 'users' !== $screen->id ) {
			return;
		}
		?>
		<style type="text/css">
			.fixed .column-<?php echo esc_html( Expire_Passwords::PREFIX ) ?> {
				width: 150px;
			}
			@media screen and (max-width: 782px) {
				.fixed .column-<?php echo esc_html( Expire_Passwords::PREFIX ) ?> {
					display: none;
				}
			}
			.<?php echo esc_html( Expire_Passwords::PREFIX ) ?>-is-expired {
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
	public static function users_column( $columns ) {
		$columns[ Expire_Passwords::PREFIX ] = esc_html__( 'Password Reset', 'expire-passwords' );

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
	public static function render_users_column( $value, $column_name, $user_id ) {
		if ( Expire_Passwords::PREFIX === $column_name ) {
			$reset = Expire_Passwords::get_user_meta( $user_id );

			if ( ! Expire_Passwords::has_expirable_role( $user_id ) || ! $reset ) {
				$value = '&mdash;';
			} else {
				$time_diff = sprintf( __( '%1$s ago', 'expire-passwords' ), human_time_diff( $reset, time() ) );

				if ( Expire_Passwords::is_password_expired( $user_id ) ) {
					$value = sprintf( '<span class="%s-is-expired">%s</span>', esc_attr( Expire_Passwords::PREFIX ), esc_html( $time_diff ) );
				} else {
					$value = sprintf( '<span class="%s-not-expired">%s</span>', esc_attr( Expire_Passwords::PREFIX ), esc_html( $time_diff ) );
				}
			}
		}

		return $value; // xss ok
	}

}
