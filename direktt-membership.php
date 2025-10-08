<?php

/**
 * Plugin Name: Direktt Membership
 * Description: Direktt Membership Plugin
 * Version: 1.0.0
 * Author: Direktt
 * Author URI: https://direktt.com/
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', 'direktt_membership_activation_check', -20 );

function direktt_membership_activation_check() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$required_plugin = 'direktt-plugin/direktt.php';

	if ( ! is_plugin_active( $required_plugin ) ) {

		add_action(
			'after_plugin_row_direktt-membership/direktt-membership.php',
			function ( $plugin_file, $plugin_data, $status ) {
				$colspan = 3;
				?>
			<tr class="plugin-update-tr">
				<td colspan="<?php echo esc_attr( $colspan ); ?>" style="box-shadow: none;">
					<div style="color: #b32d2e; font-weight: bold;">
						<?php esc_html_e( 'Direktt Membership requires the Direktt WordPress Plugin to be active. Please activate Direktt WordPress Plugin first.', 'direktt-membership' ); ?>
					</div>
				</td>
			</tr>
				<?php
			},
			10,
			3
		);

		deactivate_plugins( plugin_basename( __FILE__ ) );
	}
}