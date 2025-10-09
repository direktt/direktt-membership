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

// Custom Database Table
register_activation_hook( __FILE__, 'direktt_membership_create_issued_database_table' );
register_activation_hook( __FILE__, 'direktt_membership_create_used_database_table' );

// Settings Page
add_action( 'direktt_setup_settings_pages', 'direktt_membership_setup_settings_page' );

// Setup menus
add_action( 'direktt_setup_admin_menu', 'direktt_membership_setup_menu' );

// Custom Post Type
add_action( 'init', 'direktt_membership_register_cpt' );

// Membership Packages Meta Boxes
add_action( 'add_meta_boxes', 'direktt_membership_packages_add_custom_box' );
add_action( 'save_post', 'save_direktt_membership_package_meta' );

// Membership Profile Tool Setup
add_action( 'direktt_setup_profile_tools', 'direktt_membership_setup_profile_tool' );

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

function direktt_membership_create_issued_database_table() {
	// Table for issued coupons
	global $wpdb;

	$table_name = $wpdb->prefix . 'direktt_membership_issued';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
  			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            membership_package_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            direktt_receiver_user_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            issue_time timestamp NOT NULL,
            activation_time timestamp DEFAULT NULL,
            expiry_time timestamp DEFAULT NULL,
            activated boolean DEFAULT NULL,
            membership_guid varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            valid boolean DEFAULT TRUE,
  			PRIMARY KEY (ID),
  			KEY membership_package_id (membership_package_id),
            KEY direktt_receiver_user_id (direktt_receiver_user_id),
            KEY issue_time (issue_time),
            KEY membership_guid (membership_guid)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql );

	$the_default_timestamp_query = "ALTER TABLE $table_name MODIFY COLUMN issue_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;";

	$wpdb->query( $the_default_timestamp_query );
}

function direktt_membership_create_used_database_table() {
	// Table for used coupons
	global $wpdb;

	$table_name = $wpdb->prefix . 'direktt_membership_used';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
  			ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            issued_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  			direktt_validator_user_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            usage_time timestamp NOT NULL,
  			PRIMARY KEY (ID),
  			KEY issued_id (issued_id),
            KEY direktt_validator_user_id (direktt_validator_user_id),
            KEY usage_time (usage_time)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 $charset_collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	$the_default_timestamp_query = "ALTER TABLE $table_name MODIFY COLUMN usage_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;";

	$wpdb->query( $the_default_timestamp_query );
}

function direktt_membership_setup_settings_page() {
	Direktt::add_settings_page(
		array(
			'id'       => 'membership',
			'label'    => esc_html__( 'Membership Settings', 'direktt-membership' ),
			'callback' => 'direktt_membership_settings',
			'priority' => 1,
		)
	);
}

function direktt_membership_settings() {
	// Success message flag
	$success = false;

	// Handle form submission
	if (
		$_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['direktt_admin_membership_nonce'] )
		&& wp_verify_nonce( $_POST['direktt_admin_membership_nonce'], 'direktt_admin_membership_save' )
	) {
		// Sanitize and update options
		update_option( 'direktt_membership_validation_slug', isset( $_POST['direktt_membership_validation_slug'] ) ? sanitize_text_field( $_POST['direktt_membership_validation_slug'] ) : '' );

		update_option( 'direktt_membership_issue_categories', isset( $_POST['direktt_membership_issue_categories'] ) ? intval( $_POST['direktt_membership_issue_categories'] ) : 0 );
		update_option( 'direktt_membership_issue_tags', isset( $_POST['direktt_membership_issue_tags'] ) ? intval( $_POST['direktt_membership_issue_tags'] ) : 0 );

		update_option( 'direktt_membership_user_issuance', isset( $_POST['direktt_membership_user_issuance'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_user_issuance_template', isset( $_POST['direktt_membership_user_issuance_template'] ) ? intval( $_POST['direktt_membership_user_issuance_template'] ) : 0 );
        update_option( 'direktt_membership_admin_issuance', isset( $_POST['direktt_membership_admin_issuance'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_admin_issuance_template', isset( $_POST['direktt_membership_admin_issuance_template'] ) ? intval( $_POST['direktt_membership_admin_issuance_template'] ) : 0 );

		update_option( 'direktt_membership_user_activation', isset( $_POST['direktt_membership_user_activation'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_user_activation_template', isset( $_POST['direktt_membership_user_activation_template'] ) ? intval( $_POST['direktt_membership_user_activation_template'] ) : 0 );
        update_option( 'direktt_membership_admin_activation', isset( $_POST['direktt_membership_admin_activation'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_admin_activation_template', isset( $_POST['direktt_membership_admin_activation_template'] ) ? intval( $_POST['direktt_membership_admin_activation_template'] ) : 0 );

		update_option( 'direktt_membership_user_usage', isset( $_POST['direktt_membership_user_usage'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_user_usage_template', isset( $_POST['direktt_membership_user_usage_template'] ) ? intval( $_POST['direktt_membership_user_usage_template'] ) : 0 );
        update_option( 'direktt_membership_admin_usage', isset( $_POST['direktt_membership_admin_usage'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_admin_usage_template', isset( $_POST['direktt_membership_admin_usage_template'] ) ? intval( $_POST['direktt_membership_admin_usage_template'] ) : 0 );

		$success = true;
	}

	// Load stored values
	$validation_slug = get_option( 'direktt_membership_validation_slug' );

	$issue_categories = get_option( 'direktt_membership_issue_categories', 0 );
	$issue_tags       = get_option( 'direktt_membership_issue_tags', 0 );
	
	$membership_user_issuance           = get_option( 'direktt_membership_user_issuance', 'no' ) === 'yes';
    $membership_user_issuance_template  = intval( get_option( 'direktt_membership_user_issuance_template', 0 ) );
    $membership_admin_issuance          = get_option( 'direktt_membership_admin_issuance', 'no' ) === 'yes';
    $membership_admin_issuance_template = intval( get_option( 'direktt_membership_admin_issuance_template', 0 ) );

	$membership_user_activation           = get_option( 'direktt_membership_user_activation', 'no' ) === 'yes';
    $membership_user_activation_template  = intval( get_option( 'direktt_membership_user_activation_template', 0 ) );
    $membership_admin_activation          = get_option( 'direktt_membership_admin_activation', 'no' ) === 'yes';
    $membership_admin_activation_template = intval( get_option( 'direktt_membership_admin_activation_template', 0 ) );

	$membership_user_usage           = get_option( 'direktt_membership_user_usage', 'no' ) === 'yes';
    $membership_user_usage_template  = intval( get_option( 'direktt_membership_user_usage_template', 0 ) );
    $membership_admin_usage          = get_option( 'direktt_membership_admin_usage', 'no' ) === 'yes';
    $membership_admin_usage_template = intval( get_option( 'direktt_membership_admin_usage_template', 0 ) );

	$all_categories = Direktt_User::get_all_user_categories();
	$all_tags       = Direktt_User::get_all_user_tags();

	// Query for template posts
    $template_args  = array(
        'post_type'      => 'direkttmtemplates',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => array(
            array(
                'key'     => 'direkttMTType',
                'value'   => array( 'all', 'none' ),
                'compare' => 'IN',
            ),
        ),
    );
    $template_posts = get_posts( $template_args );

	?>
	<div class="wrap">
		<?php if ( $success ) : ?>
			<div class="notice notice-success">
				<p><?php esc_html_e( 'Settings saved successfully.', 'direktt-membership' ); ?></p>
			</div>
		<?php endif; ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'direktt_admin_membership_save', 'direktt_admin_membership_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row"><label for="direktt_membership_validation_slug"><?php echo esc_html__( 'Membership Validation Page Slug', 'direktt-membership' ); ?></label></th>
					<td>
						<input type="text" name="direktt_membership_validation_slug" id="direktt_membership_validation_slug" value="<?php echo esc_attr( $validation_slug ); ?>" size="80" />
						<p class="description"><?php esc_html_e( 'Slug of the page with the Membership Validation shortcode', 'direktt-membership' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="direktt_membership_issue_categories"><?php esc_html_e( 'Users to Issue/Validate Memberships', 'direktt-membership' ); ?></label></th>
					<td>
						<select name="direktt_membership_issue_categories" id="direktt_membership_issue_categories">
							<option value="0"><?php echo esc_html__( 'Select Category', 'direktt-membership' ); ?></option>
							<?php foreach ( $all_categories as $category ) : ?>
								<option value="<?php echo esc_attr( $category['value'] ); ?>" <?php selected( $issue_categories, $category['value'] ); ?>>
									<?php echo esc_html( $category['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Users belonging to this category will be able to Issue/Validate Memberships.', 'direktt-membership' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="direktt_membership_issue_tags"><?php esc_html_e( 'Users to Issue/Validate Memberships', 'direktt-membership' ); ?></label></th>
					<td>
						<select name="direktt_membership_issue_tags" id="direktt_membership_issue_tags">
							<option value="0"><?php echo esc_html__( 'Select Tag', 'direktt-membership' ); ?></option>
							<?php foreach ( $all_tags as $tag ) : ?>
								<option value="<?php echo esc_attr( $tag['value'] ); ?>" <?php selected( $issue_tags, $tag['value'] ); ?>>
									<?php echo esc_html( $tag['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Users with this tag will be able to Issue/Validate Memberships.', 'direktt-membership' ); ?></p>
					</td>
				</tr>
				<tr>
                    <th scope="row"><label for="direktt_membership_user_issuance"><?php echo esc_html__( 'Send to Subscriber on Membership Issuance', 'direktt-membership' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_membership_user_issuance" id="direktt_membership_user_issuance" value="yes" <?php checked( $membership_user_issuance ); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_membership_user_issuance_template"><?php echo esc_html__( 'Subscriber Message Template on Membership Issuance', 'direktt-membership' ); ?></label></th>
                    <td>
                        <select name="direktt_membership_user_issuance_template" id="direktt_membership_user_issuance_template">
                            <option value="0"><?php echo esc_html__( 'Select Template', 'direktt-membership' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $membership_user_issuance_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Description TODO.', 'direktt-membership' ); ?></p>
                    </td>
                </tr>
				<tr>
                    <th scope="row"><label for="direktt_membership_admin_issuance"><?php echo esc_html__( 'Send to Admin on Membership Issuance', 'direktt-membership' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_membership_admin_issuance" id="direktt_membership_admin_issuance" value="yes" <?php checked( $membership_admin_issuance ); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_membership_admin_issuance_template"><?php echo esc_html__( 'Admin Message Template on Membership Issuance', 'direktt-membership' ); ?></label></th>
                    <td>
                        <select name="direktt_membership_admin_issuance_template" id="direktt_membership_admin_issuance_template">
                            <option value="0"><?php echo esc_html__( 'Select Template', 'direktt-membership' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $membership_admin_issuance_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Description TODO.', 'direktt-membership' ); ?></p>
                    </td>
                </tr>
				<tr>
                    <th scope="row"><label for="direktt_membership_user_activation"><?php echo esc_html__( 'Send to Subscriber on Membership Activation', 'direktt-membership' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_membership_user_activation" id="direktt_membership_user_activation" value="yes" <?php checked( $membership_user_activation ); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_membership_user_activation_template"><?php echo esc_html__( 'Subscriber Message Template on Membership Activation', 'direktt-membership' ); ?></label></th>
                    <td>
                        <select name="direktt_membership_user_activation_template" id="direktt_membership_user_activation_template">
                            <option value="0"><?php echo esc_html__( 'Select Template', 'direktt-membership' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $membership_user_activation_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Description TODO.', 'direktt-membership' ); ?></p>
                    </td>
                </tr>
				<tr>
                    <th scope="row"><label for="direktt_membership_admin_activation"><?php echo esc_html__( 'Send to Admin on Membership Activation', 'direktt-membership' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_membership_admin_activation" id="direktt_membership_admin_activation" value="yes" <?php checked( $membership_admin_activation ); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_membership_admin_activation_template"><?php echo esc_html__( 'Admin Message Template on Membership Activation', 'direktt-membership' ); ?></label></th>
                    <td>
                        <select name="direktt_membership_admin_activation_template" id="direktt_membership_admin_activation_template">
                            <option value="0"><?php echo esc_html__( 'Select Template', 'direktt-membership' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $membership_admin_activation_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Description TODO.', 'direktt-membership' ); ?></p>
                    </td>
                </tr>
				<tr>
                    <th scope="row"><label for="direktt_membership_user_usage"><?php echo esc_html__( 'Send to Subscriber on Membership Usage', 'direktt-membership' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_membership_user_usage" id="direktt_membership_user_usage" value="yes" <?php checked( $membership_user_usage ); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_membership_user_usage_template"><?php echo esc_html__( 'Subscriber Message Template on Membership Usage', 'direktt-membership' ); ?></label></th>
                    <td>
                        <select name="direktt_membership_user_usage_template" id="direktt_membership_user_usage_template">
                            <option value="0"><?php echo esc_html__( 'Select Template', 'direktt-membership' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $membership_user_usage_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Description TODO.', 'direktt-membership' ); ?></p>
                    </td>
                </tr>
				<tr>
                    <th scope="row"><label for="direktt_membership_admin_usage"><?php echo esc_html__( 'Send to Admin on Membership Usage', 'direktt-membership' ); ?></label></th>
                    <td>
                        <input type="checkbox" name="direktt_membership_admin_usage" id="direktt_membership_admin_usage" value="yes" <?php checked( $membership_admin_usage ); ?> />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="direktt_membership_admin_usage_template"><?php echo esc_html__( 'Admin Message Template on Membership Usage', 'direktt-membership' ); ?></label></th>
                    <td>
                        <select name="direktt_membership_admin_usage_template" id="direktt_membership_admin_usage_template">
                            <option value="0"><?php echo esc_html__( 'Select Template', 'direktt-membership' ); ?></option>
                            <?php foreach ( $template_posts as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>" <?php selected( $membership_admin_usage_template, $post->ID ); ?>>
                                    <?php echo esc_html( $post->post_title ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php echo esc_html__( 'Description TODO.', 'direktt-membership' ); ?></p>
                    </td>
                </tr>
			</table>

			<?php submit_button( esc_html__( 'Save Settings', 'direktt-membership' ) ); ?>
		</form>
	</div>
	<?php
}

function direktt_membership_setup_menu() {
	add_submenu_page(
		'direktt-dashboard',
		esc_html__( 'Membership Packages', 'direktt-membership' ),
		esc_html__( 'Membership Packages', 'direktt-membership' ),
		'edit_posts',
		'edit.php?post_type=direkttmpackages',
		null,
		10
	);
}

function direktt_membership_register_cpt() {
	$labels = array(
		'name'               => esc_html__( 'Membership Packages', 'direktt-membership' ),
		'singular_name'      => esc_html__( 'Membership Package', 'direktt-membership' ),
		'menu_name'          => esc_html__( 'Direktt', 'direktt-membership' ),
		'all_items'          => esc_html__( 'Membership Packages', 'direktt-membership' ),
		'view_item'          => esc_html__( 'View Package', 'direktt-membership' ),
		'add_new_item'       => esc_html__( 'Add New Package', 'direktt-membership' ),
		'add_new'            => esc_html__( 'Add New', 'direktt-membership' ),
		'edit_item'          => esc_html__( 'Edit Package', 'direktt-membership' ),
		'update_item'        => esc_html__( 'Update Package', 'direktt-membership' ),
		'search_items'       => esc_html__( 'Search Packages', 'direktt-membership' ),
		'not_found'          => esc_html__( 'Not Found', 'direktt-membership' ),
		'not_found_in_trash' => esc_html__( 'Not found in Trash', 'direktt-membership' ),
	);

	$args = array(
		'label'               => esc_html__( 'packages', 'direktt-membership' ),
		'description'         => esc_html__( 'Membership Packages', 'direktt-membership' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor' ),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 10,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => false,
		'publicly_queryable'  => false,
		'capability_type'     => 'post',
		'capabilities'        => array(),
		'show_in_rest'        => false,
	);

	register_post_type( 'direkttmpackages', $args );
}

function direktt_membership_packages_add_custom_box() {
	add_meta_box(
		'direktt_membership_packages_mb',
		esc_html__( 'Package Properties', 'direktt-membership' ),
		'direktt_membership_packages_render_custom_box',
		'direkttmpackages',
		'normal',
		'high'
	);

	add_meta_box(
		'direktt_membership_packages_reports_mb',
		esc_html__( 'CSV Reports', 'direktt-membership' ),
		'direktt_membership_packages_render_reports_meta_box',
		'direkttmpackages',
		'normal',
		'high'
	);
}

function direktt_membership_packages_render_custom_box( $post ) {
	$type      = get_post_meta( $post->ID, 'direktt_membership_package_type', true );
	$validity  = get_post_meta( $post->ID, 'direktt_membership_package_validity', true );
	$max_usage = get_post_meta( $post->ID, 'direktt_membership_package_max_usage', true );

	wp_nonce_field( 'direktt_membership_save', 'direktt_membership_nonce' );
	?>

	<table class="direktt-profile-data-membership-tool-table">
		<tr>
			<th scope="row"><label for="direktt_membership_package_type"><?php echo esc_html__( 'Package Type', 'direktt-membership' ); ?></label></th>
			<td>
				<select name="direktt_membership_package_type" id="direktt_membership_package_type">
					<option value="0" <?php selected( $type, '0' ); ?>><?php echo esc_html__( 'Time Based', 'direktt-membership' ); ?></option>
					<option value="1" <?php selected( $type, '1' ); ?>><?php echo esc_html__( 'Usage Based', 'direktt-membership' ); ?></option>
				</select>
				<p class="description"><?php echo esc_html__( 'Time based - Duration of access is based on time (e.g., 30 days)', 'direktt-membership' ); ?></p>
				<p class="description"><?php echo esc_html__( 'Usage based - Duration of access is based on usage (e.g., 10 uses)', 'direktt-membership' ); ?></p>
			</td>
		</tr>
		<tr id="direktt_membership_package_max_usage_row">
			<th scope="row"><label for="direktt_membership_package_max_usage"><?php echo esc_html__( 'Max Usage', 'direktt-membership' ); ?></label></th>
			<td>
				<input type="number" name="direktt_membership_package_max_usage" id="direktt_membership_package_max_usage" value="<?php echo esc_attr( $max_usage ); ?>" min="0" />
				<p class="description"><?php echo esc_html__( 'Number of times the membership can be used (0 - unlimited).', 'direktt-membership' ); ?></p>
			</td>
		</tr>
		<tr id="direktt_membership_package_validity_row">
			<th scope="row"><label for="direktt_membership_package_validity"><?php echo esc_html__( 'Validity (days)', 'direktt-membership' ); ?></label></th>
			<td>
				<input type="number" name="direktt_membership_package_validity" id="direktt_membership_package_validity" value="<?php echo esc_attr( $validity ); ?>" min="0" />
				<p class="description"><?php echo esc_html__( 'Number of days the membership is valid after activation (0 - unlimited).', 'direktt-membership' ); ?></p>
			</td>
		</tr>
	</table>
	<script>
		jQuery(document).ready(function($) {
			function toggleFields() {
				var type = $( '#direktt_membership_package_type' ).val();
				if ( type === '0' ) {
					$( '#direktt_membership_package_max_usage_row' ).hide();
					$( '#direktt_membership_package_validity_row' ).show();
				} else if ( type === '1' ) {
					$( '#direktt_membership_package_max_usage_row' ).show();
					$( '#direktt_membership_package_validity_row' ).hide();
				}
			}

			toggleFields();

			$( '#direktt_membership_package_type' ).on( 'change', function() {
				toggleFields();
			});
		});
	</script>
	<?php
}

function direktt_membership_packages_render_reports_meta_box( $post ) {
	// Security nonce
	wp_nonce_field( 'direktt_reports_meta_box', 'direktt_reports_meta_box_nonce' );

	// Use esc to be safe
	$post_id = intval( $post->ID );
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="direktt-report-range"><?php echo esc_html__( 'Range', 'direktt-membership' ); ?></label></th>
			<td>
				<select id="direktt-report-range" name="direktt_report_range">
					<option value="7"><?php echo esc_html__( 'Last 7 days', 'direktt-membership' ); ?></option>
					<option value="30"><?php echo esc_html__( 'Last 30 days', 'direktt-membership' ); ?></option>
					<option value="90"><?php echo esc_html__( 'Last 90 days', 'direktt-membership' ); ?></option>
					<option value="custom"><?php echo esc_html__( 'Custom date range', 'direktt-membership' ); ?></option>
				</select>
			</td>
		</tr>
		<tr style="display: none;" id="direktt-custom-dates">
			<th scope="row"><label for="direktt-date-from"><?php echo esc_html__( 'From - To', 'direktt-membership' ); ?></label></th>
			<td>
				<input type="date" id="direktt-date-from" name="direktt_date_from" />
				<?php echo esc_html__( '-', 'direktt-membership' ); ?>
				<input type="date" id="direktt-date-to" name="direktt_date_to" />
			</td>
		</tr>
	</table>

	<p>
		<button type="button" class="button" id="direktt-generate-issued"><?php echo esc_html__( 'Generate Issued Report', 'direktt-membership' ); ?></button>
		<button type="button" class="button" id="direktt-generate-used"><?php echo esc_html__( 'Generate Used Report', 'direktt-membership' ); ?></button>
	</p>

	<input type="hidden" id="direktt-post-id" value="<?php echo esc_attr( $post_id ); ?>" />
	<script>
		jQuery(document).ready(function($) {
			// toggle custom date inputs
			$( '#direktt-report-range' ).on('change', function() {
				if ( $( this ).val() === 'custom' ) {
					$( '#direktt-custom-dates' ).show();
				} else {
					$( '#direktt-custom-dates' ).hide();
				}
			});

			// helper to collect data
			function collectReportData(type) {
				var post_id = $( '#direktt-post-id' ).val();
				var nonce = $( 'input[name="direktt_reports_meta_box_nonce"]' ).val();
				var range = $( '#direktt-report-range' ).val();
				var from = $( '#direktt-date-from' ).val();
				var to = $( '#direktt-date-to' ).val();

				var ajaxData = {
					action: type === 'issued' ? 'direktt_membership_get_issued_report' : 'direktt_membership_get_used_report',
					post_id: post_id,
					range: range,
					nonce: nonce
				};

				if ( range === 'custom' ) {
					ajaxData.from = from;
					ajaxData.to = to;
				}

				return ajaxData;
			}

			// Bind buttons
			$( '#direktt-generate-issued' ).off( 'click' ).on( 'click', function( event ) {
				event.preventDefault();
				var data = collectReportData( 'issued' );
				// Basic client-side validation for custom range
				if ( data.range === 'custom' ) {
					if ( ! data.from || ! data.to ) {
						alert("<?php echo esc_js( __( 'Please select both From and To dates for a custom range.', 'direktt-membership' ) ); ?>");
						return;
					}
					if ( data.from > data.to ) {
						alert("<?php echo esc_js( __( 'The From date cannot be later than the To date.', 'direktt-membership' ) ); ?>");
						return;
					}
				}

				$( this ).prop( 'disabled', true );
				$( this ).text( "<?php echo esc_js( __( 'Generating report...', 'direktt-membership' ) ); ?>" );
				$.ajax({
					url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
					method: 'POST',
					data: data,
					success: function( response ) {
						if ( response.success ) {
							window.location.href = response.data.url;
						} else {
							alert( response.data );
						}
					},
					error: function() {
						alert("<?php echo esc_js( __( 'There was an error.', 'direktt-membership' ) ); ?>");
					}
				}).always(function() {
					$( '#direktt-generate-issued' ).prop( 'disabled', false );
					$( '#direktt-generate-issued' ).text( "<?php echo esc_js( __( 'Generate Issued Report', 'direktt-membership' ) ); ?>" );
				});
			});

			$( '#direktt-generate-used' ).off( 'click' ).on( 'click', function( event ) {
				event.preventDefault();
				var data = collectReportData( 'used' );
				if ( data.range === 'custom' ) {
					if ( ! data.from || ! data.to ) {
						alert( "<?php echo esc_js( __( 'Please select both From and To dates for a custom range.', 'direktt-membership' ) ); ?>" );
						return;
					}
					if ( data.from > data.to ) {
						alert( "<?php echo esc_js( __( 'The From date cannot be later than the To date.', 'direktt-membership' ) ); ?>" );
						return;
					}
				}

				$( this ).prop( 'disabled', true );
				$( this ).text( "<?php echo esc_js( __( 'Generating report...', 'direktt-membership' ) ); ?>" );
				$.ajax({
					url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
					method: 'POST',
					data: data,
					success: function(response) {
						if (response.success) {
							window.location.href = response.data.url;
						} else {
							alert(response.data);
						}
					},
					error: function() {
						alert("<?php echo esc_js( __( 'There was an error.', 'direktt-membership' ) ); ?>");
					}
				}).always(function() {
					$( '#direktt-generate-used' ).prop( 'disabled', false );
					$( '#direktt-generate-used' ).text( "<?php echo esc_js( __( 'Generate Used Report', 'direktt-membership' ) ); ?>" );
				});
			});
		});
	</script>
	<?php
}

function save_direktt_membership_package_meta( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['post_type'] ) || $_POST['post_type'] !== 'direkttmpackages' ) {
		return;
	}

	if ( ! isset( $_POST['direktt_membership_nonce'] ) || ! wp_verify_nonce( $_POST['direktt_membership_nonce'], 'direktt_membership_save' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['direktt_membership_package_type'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_membership_package_type',
			sanitize_text_field( $_POST['direktt_membership_package_type'] )
		);
	}

	if ( isset( $_POST['direktt_membership_package_max_usage'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_membership_package_max_usage',
			intval( $_POST['direktt_membership_package_max_usage'] )
		);
	}

	if ( isset( $_POST['direktt_membership_package_validity'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_membership_package_validity',
			intval( $_POST['direktt_membership_package_validity'] )
		);
	}
}

function direktt_membership_get_used_count( $issue_id ) {
	global $wpdb;
	$used_table = $wpdb->prefix . 'direktt_membership_used';
	$used_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM $used_table WHERE issued_id = %s",
			$issue_id
		)
	);
	return intval( $used_count );
}

function direktt_membership_setup_profile_tool() {
	$issue_categories = intval( get_option( 'direktt_membership_issue_categories', 0 ) );
	$issue_tags       = intval( get_option( 'direktt_membership_issue_tags', 0 ) );

	if ( $issue_categories !== 0 ) {
		$category      = get_term( $issue_categories, 'direkttusercategories' );
		$category_slug = $category ? $category->slug : '';
	} else {
		$category_slug = '';
	}

	if ( $issue_tags !== 0 ) {
		$tag      = get_term( $issue_tags, 'direkttusertags' );
		$tag_slug = $tag ? $tag->slug : '';
	} else {
		$tag_slug = '';
	}

	Direktt_Profile::add_profile_tool(
		array(
			'id'         => 'membership-tool',
			'label'      => esc_html__( 'Membership', 'direktt-membership' ),
			'callback'   => 'direktt_membership_render_profile_tool',
			'categories' => $category_slug ? array( $category_slug ) : array(),
			'tags'       => $tag_slug ? array( $tag_slug ) : array(),
			'priority'   => 2,
		)
	);
}

function direktt_membership_render_profile_tool() {
	direktt_membership_render_assign_membership_packages();
	direktt_membership_render_membership_packages();
}

function direktt_membership_render_membership_packages() {
	?>
	<div id="direktt-membership-packages-wrapper">
		<h3><?php echo esc_html__( 'Membership Packages', 'direktt-membership' ); ?></h3>
		<?php
		$filter_options = array(
			'all'    => esc_html__( 'All', 'direktt-membership' ),
			'active' => esc_html__( 'Active', 'direktt-membership' ),
		);
		?>
		<div class="direktt-membership-filter-wrapper">
			<select name="direktt-membership-filter" id="direktt-membership-filter">
				<?php foreach ( $filter_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>">
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div id="direktt-membership-packages-all">
			<p><?php echo esc_html__( 'Displaying all memberships...', 'direktt-membership' ); ?></p>
		</div>

		<div id="direktt-membership-packages-active" style="display: none;">
			<p><?php echo esc_html__( 'Displaying active memberships...', 'direktt-membership' ); ?></p>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function () {
				const filterSelect = document.getElementById('direktt-membership-filter');
				const allDiv = document.getElementById('direktt-membership-packages-all');
				const activeDiv = document.getElementById('direktt-membership-packages-active');

				filterSelect.addEventListener('change', function () {
					const selectedValue = filterSelect.value;

					if (selectedValue === 'active') {
						allDiv.style.display = 'none';
						activeDiv.style.display = 'block';
					} else {
						allDiv.style.display = 'block';
						activeDiv.style.display = 'none';
					}
				});
			});
		</script>
	</div>
	<?php
}

function direktt_membership_render_assign_membership_packages() {
	$args = array(
		'post_type'      => 'direkttmpackages',
		'post_status'    => array( 'publish' ),
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	$membership_packages = get_posts( $args );

	echo '<div id="direktt-assign-membership-packages-wrapper">';
	echo '<h3>' . esc_html__( 'Assign Membership Packages', 'direktt-membership' ) . '</h3>';
	if ( empty( $membership_packages ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'There is no existing membership packages.', 'direktt-membership' ) . '</p></div>';
	} else {
		echo '<table class="direktt-membership-packages-table"><thead><tr>';
		echo '<th><strong>' . esc_html__( 'Name', 'direktt-membership' ) . '</strong></th>';
		echo '<th>' . esc_html__( 'Type', 'direktt-membership' ) . '</th>';
		echo '<th>' . esc_html__( 'Validity', 'direktt-membership' ) . '</th>';
		echo '<th>' . esc_html__( 'Max Usage', 'direktt-membership' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $membership_packages as $package ) {
            if ( ! $package ) {
                continue;
            } else {
                if ( $package->post_status !== 'publish' ) {
                    continue;
                }
            }
			
			$package_name = esc_html( $package->post_title );
			$type         = get_post_meta( $package->ID, 'direktt_membership_package_type', true );
			$validity     = intval( get_post_meta( $package->ID, 'direktt_membership_package_validity', true ) );
			if ( ! $validity ) {
				$validity = 0;
			}
			$max_usage    = intval( get_post_meta( $package->ID, 'direktt_membership_package_max_usage', true ) );
			if ( ! $max_usage ) {
				$max_usage = 0;
			}

			echo '<tr>';
				echo '<td class="direktt-membership-package-name"><strong>' . $package_name . '</strong></td>';
				echo '<td class="direktt-membership-package-type">' . ( $type === '0' ? esc_html__( 'Time Based', 'direktt-membership' ) : esc_html__( 'Usage Based', 'direktt-membership' ) ) . '</td>';
				echo '<td class="direktt-membership-package-validity">' . ( $type === '0' ? esc_html( $validity ) . esc_html__( ' days', 'direktt-membership' ) : esc_html( '/' ) ) . '</td>';
				echo '<td class="direktt-membership-package-max-usage">' . ( $type === '1' ? esc_html( $max_usage ) . esc_html__( ' usages', 'direktt-membership' ) : esc_html( '/' ) ) . '</td>';
			echo '</tr>';
			echo '<tr class="direktt-membership-actions">';
				echo '<td colspan="4">';
					echo '<button class="button" data-package-id="' . esc_attr( $package->ID ) . '">' . esc_html__( 'Assign Membership', 'direktt-membership' ) . '</button>';
				echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo Direktt_Public::direktt_render_confirm_popup( 'direktt-assign-membership-package-confirm', __( 'Are you sure you want to assign this membership package?', 'direktt-membership' ) );
		?>
		<script>
			jQuery( document ).ready( function($) {
				$( '.direktt-membership-actions .button' ).off( 'click' ).on( 'click', function( event ) {
					event.preventDefault();
					var packageId = $( this ).data( 'package-id' );
					var packageName = $( this ).closest( 'tr' ).prev( 'tr' ).find( '.direktt-membership-package-name strong' ).text();
					$( '#direktt-assign-membership-package-confirm .direktt-popup-text' ).text( "<?php echo esc_js( __( 'Are you sure you want to assign the membership package:', 'direktt-membership' ) ); ?> " + packageName + "<?php echo esc_html( '?' ); ?>" );
					$( '#direktt-assign-membership-package-confirm' ).addClass( 'direktt-popup-on' );
				});

				$( '#direktt-assign-membership-package-confirm .direktt-popup-yes' ).off( 'click' ).on( 'click', function( event ) {
					event.preventDefault();
					// confirm
				});

				$( '#direktt-assign-membership-package-confirm .direktt-popup-no' ).off( 'click' ).on( 'click', function( event ) {
					event.preventDefault();
					$( '#direktt-assign-membership-package-confirm' ).removeClass( 'direktt-popup-on' );
				});
			});
		</script>
		<?php
	}
	echo '</div>';
}