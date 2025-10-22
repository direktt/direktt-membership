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

$direktt_customer_review_plugin_version = "1.0.0";
$direktt_customer_review_github_update_cache_allowed = false;

require_once plugin_dir_path( __FILE__ ) . 'direktt-github-updater/class-direktt-github-updater.php';

$direktt_customer_review_plugin_github_updater  = new Direktt_Github_Updater( 
    $direktt_customer_review_plugin_version, 
    'direktt-customer-review/direktt-customer-review.php',
    'https://raw.githubusercontent.com/direktt/direktt-customer-review/master/info.json',
    'direktt_customer_review_github_updater',
    $direktt_customer_review_github_update_cache_allowed );

add_filter( 'plugins_api', array( $direktt_customer_review_plugin_github_updater, 'github_info' ), 20, 3 );
add_filter( 'site_transient_update_plugins', array( $direktt_customer_review_plugin_github_updater, 'github_update' ));
add_filter( 'upgrader_process_complete', array( $direktt_customer_review_plugin_github_updater, 'purge'), 10, 2 );

add_action( 'plugins_loaded', 'direktt_membership_activation_check', -20 );

// Custom Database Table
register_activation_hook( __FILE__, 'direktt_membership_create_issued_database_table' );
register_activation_hook( __FILE__, 'direktt_membership_create_used_database_table' );

// Settings Page
add_action( 'direktt_setup_settings_pages', 'direktt_membership_setup_settings_page' );

// Enqueue admin scripts
add_action( 'admin_enqueue_scripts', 'direktt_membership_enqueue_scripts' );

// Setup menus
add_action( 'direktt_setup_admin_menu', 'direktt_membership_setup_menu' );

// Custom Post Type
add_action( 'init', 'direktt_membership_register_cpt' );

// Membership Packages Meta Boxes
add_action( 'add_meta_boxes', 'direktt_membership_packages_add_custom_box' );
add_action( 'save_post', 'save_direktt_membership_package_meta' );

// Reports AJAX handlers
add_action( 'wp_ajax_direktt_membership_get_issued_report', 'handle_direktt_membership_get_issued_report' );
add_action( 'wp_ajax_direktt_membership_get_used_report', 'handle_direktt_membership_get_used_report' );

// Membership Profile Tool Setup
add_action( 'direktt_setup_profile_tools', 'direktt_membership_setup_profile_tool' );

// Assign Membership Package AJAX Handler
add_action( 'wp_ajax_direktt_assign_membership_package', 'handle_direktt_assign_membership_package' );

// Activate Membership AJAX Handler
add_action( 'wp_ajax_direktt_activate_membership', 'handle_direktt_activate_membership' );

// Invalidate Membership AJAX Handler
add_action( 'wp_ajax_direktt_invalidate_membership', 'handle_direktt_invalidate_membership' );

// Record Membership Usage AJAX Handler
add_action( 'wp_ajax_direktt_record_membership_usage', 'handle_direktt_record_membership_usage' );

// User tool shortcode
add_shortcode( 'direktt_membership_tool', 'direktt_membership_tool_shortcode' );

// Enqueue front-end scripts for membership tool
add_action( 'wp_enqueue_scripts', 'direktt_membership_enqueue_fe_scripts' );

// Membership validation shortcode
add_shortcode( 'direktt_membership_validation', 'direktt_membership_validation_shortcode' );

// Highlight submenu when on partner/coupon group edit screen
add_action( 'parent_file', 'direktt_membership_highlight_submenu' );

function direktt_membership_activation_check() {
	if (! function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $required_plugin = 'direktt/direktt.php';
    $is_required_active = is_plugin_active($required_plugin)
        || (is_multisite() && is_plugin_active_for_network($required_plugin));

    if (! $is_required_active) {
        // Deactivate this plugin
        deactivate_plugins(plugin_basename(__FILE__));

        // Prevent the “Plugin activated.” notice
        if (isset($_GET['activate'])) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, just removing a query var.
            unset($_GET['activate']);
        }

        // Show an error notice for this request
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__( 'Direktt Membership activation failed: The Direktt WordPress Plugin must be active first.', 'direktt-membership' )
                . '</p></div>';
        });

        // Optionally also show the inline row message in the plugins list
        add_action(
            'after_plugin_row_direktt-membership/direktt-membership.php',
            function () {
                echo '<tr class="plugin-update-tr"><td colspan="3" style="box-shadow:none;">'
                    . '<div style="color:#b32d2e;font-weight:bold;">'
                    . esc_html__( 'Direktt Membership requires the Direktt WordPress Plugin to be active. Please activate it first.', 'direktt-membership' )
                    . '</div></td></tr>';
            },
            10,
            0
        );
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
			direktt_assigner_user_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            direktt_reciever_user_id varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            issue_time timestamp NOT NULL,
            activation_time timestamp DEFAULT NULL,
            expiry_time timestamp DEFAULT NULL,
            activated boolean DEFAULT NULL,
            membership_guid varchar(256) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
            valid boolean DEFAULT TRUE,
  			PRIMARY KEY (ID),
  			KEY membership_package_id (membership_package_id),
			KEY direktt_assigner_user_id (direktt_assigner_user_id),
            KEY direktt_reciever_user_id (direktt_reciever_user_id),
            KEY issue_time (issue_time),
            KEY membership_guid (membership_guid)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql );

	$wpdb->query( $wpdb->prepare( "ALTER TABLE $table_name MODIFY COLUMN issue_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table_name is built from $wpdb->prefix + literal string.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct database query is acceptable here since schema changes cannot be performed via WordPress APIs or $wpdb helper functions. This runs only on plugin activation.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is irrelevant for schema modifications. The query changes table structure, not data, so wp_cache_* functions are not applicable.
	// WordPress.DB.DirectDatabaseQuery.SchemaChange: Schema changes are discouraged in normal runtime, but this code executes only once during plugin activation to ensure correct table structure.
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

	$wpdb->query( $wpdb->prepare( "ALTER TABLE $table_name MODIFY COLUMN usage_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $table_name is built from $wpdb->prefix + literal string.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct database query is acceptable here since schema changes cannot be performed via WordPress APIs or $wpdb helper functions. This runs only on plugin activation.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is irrelevant for schema modifications. The query changes table structure, not data, so wp_cache_* functions are not applicable.
	// WordPress.DB.DirectDatabaseQuery.SchemaChange: Schema changes are discouraged in normal runtime, but this code executes only once during plugin activation to ensure correct table structure.
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

function direktt_membership_enqueue_scripts( $hook ) {
	if ( $hook === 'direktt_page_direktt-settings' && isset( $_GET['subpage' ] ) && sanitize_text_field( wp_unslash( $_GET['subpage'] ) ) === 'membership' ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, subpage based router for enqueuing scripts.
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script(
			'qr-code-styling', // Handle
			plugin_dir_url( __FILE__ ) . 'assets/js/qr-code-styling.js', // Source
			array(), // Dependencies (none in this case)
			filetime( plugin_dir_path( __FILE__ ) . 'assets/js/qr-code-styling.js' ), // Version based on file modification time
			true // Load in the footer
		);
	}
}


function direktt_membership_settings() {
	// Success message flag
	$success = false;

	// Handle form submission
	if (
		isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['direktt_admin_membership_nonce'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_admin_membership_nonce'] ) ), 'direktt_admin_membership_save' )
	) {
		// Sanitize and update options
		update_option( 'direktt_membership_validation_slug', isset( $_POST['direktt_membership_validation_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['direktt_membership_validation_slug'] ) ) : '' );

		update_option( 'direktt_membership_issue_categories', isset( $_POST['direktt_membership_issue_categories'] ) ? intval( wp_unslash( $_POST['direktt_membership_issue_categories'] ) ) : 0 );
		update_option( 'direktt_membership_issue_tags', isset( $_POST['direktt_membership_issue_tags'] ) ? intval( wp_unslash( $_POST['direktt_membership_issue_tags'] ) ) : 0 );

		update_option( 'direktt_membership_qr_code_image', isset( $_POST['direktt_membership_qr_code_image'] ) ? esc_url_raw( wp_unslash( $_POST['direktt_membership_qr_code_image'] ) ) : '' );
		update_option( 'direktt_membership_qr_code_color', isset( $_POST['direktt_membership_qr_code_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['direktt_membership_qr_code_color'] ) ) : '#000000' );
		update_option( 'direktt_membership_qr_code_bg_color', isset( $_POST['direktt_membership_qr_code_bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['direktt_membership_qr_code_bg_color'] ) ) : '#ffffff' );

		update_option( 'direktt_membership_user_issuance', isset( $_POST['direktt_membership_user_issuance'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_user_issuance_template', isset( $_POST['direktt_membership_user_issuance_template'] ) ? intval( wp_unslash( $_POST['direktt_membership_user_issuance_template'] ) ) : 0 );
        update_option( 'direktt_membership_admin_issuance', isset( $_POST['direktt_membership_admin_issuance'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_admin_issuance_template', isset( $_POST['direktt_membership_admin_issuance_template'] ) ? intval( wp_unslash( $_POST['direktt_membership_admin_issuance_template'] ) ) : 0 );

		update_option( 'direktt_membership_user_activation', isset( $_POST['direktt_membership_user_activation'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_user_activation_template', isset( $_POST['direktt_membership_user_activation_template'] ) ? intval( wp_unslash( $_POST['direktt_membership_user_activation_template'] ) ) : 0 );
        update_option( 'direktt_membership_admin_activation', isset( $_POST['direktt_membership_admin_activation'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_admin_activation_template', isset( $_POST['direktt_membership_admin_activation_template'] ) ? intval( wp_unslash( $_POST['direktt_membership_admin_activation_template'] ) ) : 0 );

		update_option( 'direktt_membership_user_usage', isset( $_POST['direktt_membership_user_usage'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_user_usage_template', isset( $_POST['direktt_membership_user_usage_template'] ) ? intval( wp_unslash( $_POST['direktt_membership_user_usage_template'] ) ) : 0 );
        update_option( 'direktt_membership_admin_usage', isset( $_POST['direktt_membership_admin_usage'] ) ? 'yes' : 'no' );
        update_option( 'direktt_membership_admin_usage_template', isset( $_POST['direktt_membership_admin_usage_template'] ) ? intval( wp_unslash( $_POST['direktt_membership_admin_usage_template'] ) ) : 0 );

		$success = true;
	}

	// Load stored values
	$validation_slug = get_option( 'direktt_membership_validation_slug' );

	$issue_categories = get_option( 'direktt_membership_issue_categories', 0 );
	$issue_tags       = get_option( 'direktt_membership_issue_tags', 0 );

	$qr_code_image    = get_option( 'direktt_membership_qr_code_image', '' );
	$qr_code_color    = get_option( 'direktt_membership_qr_code_color', '#000000' );
	$qr_code_bg_color = get_option( 'direktt_membership_qr_code_bg_color', '#ffffff' );

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
        'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- - Justification: bounded, cached, selective query on small dataset
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
					<th scope="row"><label for="direktt_membership_qr_code_image"><?php echo esc_html__( 'QR Code Logo', 'direktt-membership' ); ?></label></th>
					<td>
						<input type="text" id="direktt_membership_qr_code_image" name="direktt_membership_qr_code_image" value="<?php echo esc_attr( $qr_code_image ?? '' ); ?>" />
						<input type="button" id="direktt_membership_qr_code_image_button" class="button" value="<?php echo esc_html__( 'Choose Image', 'direktt-membership' ); ?>" />
						<p class="description"><?php echo esc_html__( 'Optional Logo/Image to Display at Center of QR Code', 'direktt-membership' ); ?></p>
						<script>
							jQuery( document ).ready(function($) {
								var mediaUploader;

								$( '#direktt_membership_qr_code_image_button' ).click(function(e) {
									e.preventDefault();

									// If the uploader object has already been created, reopen it
									if (mediaUploader) {
										mediaUploader.open();
										return;
									}

									// Create the media uploader
									mediaUploader = wp.media.frames.file_frame = wp.media({
										title: '<?php echo esc_js( __( 'Choose Image', 'direktt-membership' ) ); ?>',
										button: {
											text: '<?php echo esc_js( __( 'Choose Image', 'direktt-membership' ) ); ?>'
										},
										multiple: false
									});

									// When an image is selected, run a callback
									mediaUploader.on( 'select', function() {
										var attachment = mediaUploader.state().get( 'selection' ).first().toJSON();
										$( '#direktt_membership_qr_code_image' ).val( attachment.url );
										$( '#direktt_membership_qr_code_image' ).trigger( 'change' );
									});

									// Open the uploader dialog
									mediaUploader.open();
								});
							});
						</script>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="direktt_membership_qr_code_color"><?php echo esc_html__( 'QR Code Color', 'direktt-membership' ); ?></label></th>
					<td>
						<input type="text" id="direktt_membership_qr_code_color" name="direktt_membership_qr_code_color" value="<?php echo esc_attr( $qr_code_color ?? '#000000' ); ?>" />
						<p class="description"><?php echo esc_html__( 'Optional Color of Dots in the QR Code', 'direktt-membership' ); ?></p>
						<script>
							jQuery( document ).ready( function($) {
								$( '#direktt_membership_qr_code_color' ).wpColorPicker();
							});
						</script>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="direktt_membership_qr_code_bg_color"><?php echo esc_html__( 'QR Code Background Color', 'direktt-membership' ); ?></label></th>
					<td>
						<input type="text" id="direktt_membership_qr_code_bg_color" name="direktt_membership_qr_code_bg_color" value="<?php echo esc_attr( $qr_code_bg_color ?? '#ffffff' ); ?>" />
						<p class="description"><?php echo esc_html__( 'Optional Color of the QR Code Background.', 'direktt-membership' ); ?></p>
						<script>
							jQuery( document ).ready(function($) {
								$( '#direktt_membership_qr_code_bg_color' ).wpColorPicker();
							});
						</script>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="direktt-membership-qr-code-canvas-wrapper"><?php echo esc_html__( 'QR Code Preview', 'direktt-membership' ); ?></label></th>
					<td>
						<div class="direktt-membership-qr-code-canvas-wrapper">
							<div id="direktt-membership-qr-code-canvas"></div>
						</div>
						<?php
						$actionObject = array(
							'action' => array(
								'type'    => 'link',
								'params'  => array(
									'url'    => 'direktt.com',
									'target' => 'browser',
								),
								'retVars' => array(),
							),
						);
						?>
						<script type="text/javascript">
							const qrCode = new QRCodeStyling({
								width: 350,
								height: 350,
								type: "svg",
								data: '<?php echo wp_json_encode( $actionObject ); ?>',
								image: '<?php echo $qr_code_image ? esc_js( $qr_code_image ) : ''; ?>',
								dotsOptions: {
									color: '<?php echo $qr_code_color ? esc_js( $qr_code_color ) : '#000000'; ?>',
									type: "rounded"
								},
								backgroundOptions: {
									color: '<?php echo $qr_code_bg_color ? esc_js( $qr_code_bg_color ) : '#ffffff'; ?>',
								},
								imageOptions: {
									crossOrigin: "anonymous",
									margin: 20
								}
							});

							qrCode.append(document.getElementById("direktt-membership-qr-code-canvas"));

							jQuery(document).ready(function($) {
								$('#direktt_membership_qr_code_image').on('change', function() {
									var newQrCode = new QRCodeStyling({
										width: 350,
										height: 350,
										type: "svg",
										data: '<?php echo wp_json_encode( $actionObject ); ?>',
										image: $( '#direktt_membership_qr_code_image' ).val() ? $( '#direktt_membership_qr_code_image' ).val() : '',
										dotsOptions: {
											color: $( '#direktt_membership_qr_code_color' ).val() ? $( '#direktt_membership_qr_code_color' ).val() : '#000000',
											type: "rounded"
										},
										backgroundOptions: {
											color: $( '#direktt_membership_qr_code_bg_color' ).val() ? $( '#direktt_membership_qr_code_bg_color' ).val() : '#ffffff',
										},
										imageOptions: {
											crossOrigin: "anonymous",
											margin: 20
										}
									});

									$( '#direktt-membership-qr-code-canvas' ).empty();
									newQrCode.append( document.getElementById( "direktt-membership-qr-code-canvas" ) );
								});
								$( '#direktt_membership_qr_code_color' ).wpColorPicker({
									change: function( event, ui ) {
										let color = ui.color.toString();

										var newQrCode = new QRCodeStyling({
											width: 350,
											height: 350,
											type: "svg",
											data: '<?php echo wp_json_encode( $actionObject ); ?>',
											image: $( '#direktt_membership_qr_code_image' ).val() ? $( '#direktt_membership_qr_code_image' ).val() : '',
											dotsOptions: {
												color: color,
												type: "rounded"
											},
											backgroundOptions: {
												color: $( '#direktt_membership_qr_code_bg_color' ).val() ? $( '#direktt_membership_qr_code_bg_color' ).val() : '#ffffff',
											},
											imageOptions: {
												crossOrigin: "anonymous",
												margin: 20
											}
										});

										$( '#direktt-membership-qr-code-canvas' ).empty();
										newQrCode.append( document.getElementById( "direktt-membership-qr-code-canvas" ) );
									}
								});
								$( '#direktt_membership_qr_code_bg_color' ).wpColorPicker({
									change: function( event, ui ) {
										let color = ui.color.toString();

										var newQrCode = new QRCodeStyling({
											width: 350,
											height: 350,
											type: "svg",
											data: '<?php echo wp_json_encode( $actionObject ); ?>',
											image: $( '#direktt_membership_qr_code_image' ).val() ? $( '#direktt_membership_qr_code_image' ).val() : '',
											dotsOptions: {
												color: $( '#direktt_membership_qr_code_color' ).val() ? $( '#direktt_membership_qr_code_color' ).val() : '#000000',
												type: "rounded"
											},
											backgroundOptions: {
												color: color,
											},
											imageOptions: {
												crossOrigin: "anonymous",
												margin: 20
											}
										});

										$( '#direktt-membership-qr-code-canvas' ).empty();
										newQrCode.append( document.getElementById( "direktt-membership-qr-code-canvas" ) );
									}
								});
							});
						</script>
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
		<div class="direktt-membership-reports">
			<h2><?php echo esc_html__( 'Generate Membership Reports', 'direktt-membership' ); ?></h2>
			<table>
				<?php wp_nonce_field( 'direktt_membership_reports', 'direktt_membership_reports_nonce' ); ?>
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
				<tr>
					<td colspan="2">
						<p>
							<button type="button" class="button" id="direktt-generate-issued"><?php echo esc_html__( 'Generate Issued Report', 'direktt-membership' ); ?></button>
							<button type="button" class="button" id="direktt-generate-used"><?php echo esc_html__( 'Generate Used Report', 'direktt-membership' ); ?></button>
						</p>

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
									var nonce = $( 'input[name="direktt_membership_reports_nonce"]' ).val();
									var range = $( '#direktt-report-range' ).val();
									var from = $( '#direktt-date-from' ).val();
									var to = $( '#direktt-date-to' ).val();

									var ajaxData = {
										action: type === 'issued' ? 'direktt_membership_get_issued_report' : 'direktt_membership_get_used_report',
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
					</td>
				</tr>
			</table>
		</div>
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

function handle_direktt_membership_get_issued_report() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'direktt_membership_reports' ) ) {
        wp_send_json_error( esc_html__( 'Invalid nonce.', 'direktt-membership' ) );
        wp_die();
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( esc_html__( 'Unauthorized.', 'direktt-membership' ) );
        wp_die();
    }

    if ( ! isset( $_POST['range'] ) ) {
        wp_send_json_error( esc_html__( 'Data error.', 'direktt-membership' ) );
        wp_die();
    }

    global $wpdb;

    $post_id      = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0; // used as partner_id
    $range        = sanitize_text_field( wp_unslash( $_POST['range'] ) );
    $issued_table = $wpdb->prefix . 'direktt_membership_issued';

    if ( in_array( $range, array( '7', '30', '90' ), true ) ) {
        $days  = intval( $range );
        $where = $wpdb->prepare( 'issue_time >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
    } elseif ( $range === 'custom' ) {
        if ( ! isset( $_POST['from'], $_POST['to'] ) ) {
            wp_send_json_error( esc_html__( 'Data error.', 'direktt-membership' ) );
            wp_die();
        }
        $from  = sanitize_text_field( wp_unslash( $_POST['from'] ) ); // format: Y-m-d or Y-m-d H:i:s
        $to    = sanitize_text_field( wp_unslash( $_POST['to'] ) );
        $where = $wpdb->prepare( 'issue_time BETWEEN %s AND %s', $from, $to );
    }

    // Get issued memberships
	$memberships = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $issued_table WHERE $where" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string, $where is built from literal string + sanitized inputs.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.

    if ( empty( $memberships ) ) {
        wp_send_json_error( esc_html__( 'No data found.', 'direktt-membership' ) );
        wp_die();
    }

    // Load WP_Filesystem
    if ( ! function_exists( 'get_filesystem_method' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
    }

    global $wp_filesystem;
    $method = get_filesystem_method();
    if ( 'direct' === $method ) {
        WP_Filesystem();
    }

    // Prepare CSV content in an array format (no need for fopen())
    $csv_content = '';

    // Headers
    $headers = array(
        'ID',
        'Package Name',
        'Reciever Display Name',
        'Activated',
        'Time of Issue',
        'Time of Activation',
        'Expires on',
        'Usages left',
        'Valid',
    );

    // Add headers to the CSV content
    $csv_content .= implode( ',', $headers ) . "\n";

    foreach ( $memberships as $membership ) {
        $package_name  = get_the_title( intval( $membership->membership_package_id ) );
        $profile_user  = Direktt_User::get_user_by_subscription_id( $membership->direktt_reciever_user_id );
        $reciever_name = $profile_user['direktt_display_name'];

        $type = get_post_meta( intval( $membership->membership_package_id ), 'direktt_membership_package_type', true );
        if ( $type === '1' ) { // usage based
            $max_usage = get_post_meta( intval( $membership->membership_package_id ), 'direktt_membership_package_max_usage', true );
            if ( $max_usage === 0 ) {
                $usages_left = 'unlimited';
            } else {
                $used_count = direktt_membership_get_used_count( $membership->ID );
                $usages_left = $max_usage - $used_count;
            }
        } else {
            $usages_left = '/';
        }
        $max_usage = get_post_meta( intval( $membership->membership_package_id ), 'direktt_membership_package_max_usage', true );

        $line = array(
            $membership->ID,
            $package_name,
            $reciever_name,
            $membership->activated == 1 ? 'true' : 'false',
            $membership->issue_time,
            $membership->activation_time ?? '/',
            $membership->expiry_time ?? '/',
            $usages_left ?? '/',
            $membership->valid == 1 ? 'true' : 'false',
        );

        // Add each row to the CSV content
        $csv_content .= implode( ',', $line ) . "\n";
    }

    // Save to uploads directory using WP_Filesystem
    $upload_dir = wp_upload_dir();
    $filename   = 'issued_report_' . time() . '.csv';
    $filepath   = $upload_dir['path'] . '/' . $filename;
    $fileurl    = $upload_dir['url'] . '/' . $filename;

    // Write CSV content to the file using WP_Filesystem
    if ( ! $wp_filesystem->put_contents( $filepath, $csv_content, FS_CHMOD_FILE ) ) {
        wp_send_json_error( esc_html__( 'Error saving the file.', 'direktt-membership' ) );
        wp_die();
    }

    wp_send_json_success( array( 'url' => $fileurl ) );
    wp_die();
}

function handle_direktt_membership_get_used_report() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'direktt_membership_reports' ) ) {
		wp_send_json_error( esc_html__( 'Invalid nonce.', 'direktt-membership' ) );
		wp_die();
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Unauthorized.', 'direktt-membership' ) );
		wp_die();
	}

	if ( ! isset( $_POST['range'] ) ) {
		wp_send_json_error( esc_html__( 'Data error.', 'direktt-membership' ) );
		wp_die();
	}

	global $wpdb;

	$post_id       = isset( $_POST['post_id'] ) ? intval( wp_unslash( $_POST['post_id'] ) ) : 0;
	$range         = sanitize_text_field( wp_unslash( $_POST['range'] ) );
	$issued_table  = $wpdb->prefix . 'direktt_membership_issued';
	$used_table    = $wpdb->prefix . 'direktt_membership_used';

	if ( in_array( $range, array( '7', '30', '90' ), true ) ) {
		$days           = intval( $range );
		$date_condition = $wpdb->prepare( 'usage_time >= DATE_SUB(NOW(), INTERVAL %d DAY)', $days );
	} elseif ( $range === 'custom' ) {
		if ( ! isset( $_POST['from'], $_POST['to'] ) ) {
			wp_send_json_error( esc_html__( 'Data error.', 'direktt-membership' ) );
			wp_die();
		}
		$from           = sanitize_text_field( wp_unslash( $_POST['from'] ) );
		$to             = sanitize_text_field( wp_unslash( $_POST['to'] ) );
		$date_condition = $wpdb->prepare( 'usage_time BETWEEN %s AND %s', $from, $to );
	}

	// --- Used ---
	$usages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $used_table WHERE $date_condition" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	// Justifications for phpcs ignores:
	// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $used_table is built from $wpdb->prefix + literal string, $date_condition is built from literal string + sanitized inputs.
	// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
	// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.

	if ( empty( $usages ) ) {
		wp_send_json_error( esc_html__( 'No usage data found.', 'direktt-membership' ) );
		wp_die();
	}

	// --- Prepare CSV Content (in memory) ---
	$csv_content = '';

	$headers = array(
		'ID',
		'Package Name',
		'Time of Usage',
		'Reciever Display Name',
		'Validator Display Name',
	);

	// Add headers
	$csv_content .= implode( ',', $headers ) . "\n";

	// Add usage rows
	foreach ( $usages as $usage ) {
		$issued_record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $issued_table WHERE ID = %d", intval( $usage->issued_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Justifications for phpcs ignores:
		// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
		// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_row() is the official WordPress method for this.
		// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.

		if ( ! $issued_record ) {
			continue;
		}

		$package_name = get_the_title( intval( $issued_record->membership_package_id ) );

		$profile_user_reciever = Direktt_User::get_user_by_subscription_id( $issued_record->direktt_reciever_user_id );
		$reciever_name         = $profile_user_reciever['direktt_display_name'];

		$profile_user_validator = Direktt_User::get_user_by_subscription_id( $usage->direktt_validator_user_id );
		$validator_name         = $profile_user_validator['direktt_display_name'];

		$line = array(
			$usage->ID,
			$package_name,
			$usage->usage_time,
			$reciever_name,
			$validator_name,
		);

		$csv_content .= implode( ',', $line ) . "\n";
	}

	// --- Prepare to save with WP_Filesystem ---
	if ( ! function_exists( 'get_filesystem_method' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	global $wp_filesystem;
	if ( ! is_object( $wp_filesystem ) ) {
		WP_Filesystem();
	}

	$upload_dir = wp_upload_dir();
	$filename   = 'used_report_' . time() . '.csv';
	$filepath   = $upload_dir['path'] . '/' . $filename;
	$fileurl    = $upload_dir['url'] . '/' . $filename;

	// Save file using WP_Filesystem
	$write_success = $wp_filesystem->put_contents( $filepath, $csv_content, FS_CHMOD_FILE );

	if ( ! $write_success ) {
		wp_send_json_error( esc_html__( 'Failed to save report file.', 'direktt-membership' ) );
		wp_die();
	}

	wp_send_json_success( array( 'url' => $fileurl ) );
	wp_die();
}

function save_direktt_membership_package_meta( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['post_type'] ) || $_POST['post_type'] !== 'direkttmpackages' ) {
		return;
	}

	if ( ! isset( $_POST['direktt_membership_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['direktt_membership_nonce'] ) ), 'direktt_membership_save' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['direktt_membership_package_type'] ) ) {
		update_post_meta(
			$post_id,
			'direktt_membership_package_type',
			sanitize_text_field( wp_unslash( $_POST['direktt_membership_package_type'] ) )
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
		$used_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $used_table WHERE issued_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Justifications for phpcs ignores:
			// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $used_table is built from $wpdb->prefix + literal string.
			// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_var() is the official WordPress method for this.
			// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
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
	if ( isset( $_GET['success_flag'] ) && $_GET['success_flag'] === '1' ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, only a flag for displaying a message.
		echo '<div class="notice"><p>' . esc_html__( 'Membership package assigned successfully.', 'direktt-membership' ) . '</p></div>';
	}
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'view_details' ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, action based router for content rendering.
		direktt_membership_render_view_details( isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '' ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, used for content rendering.
		$back_url = remove_query_arg( array( 'action', 'id', 'success_flag_activate', 'success_flag_invalidate', 'success_flag_record_usage' ) );
		echo ' <a href="' . esc_url( $back_url ) . '" class="button">' . esc_html__( 'Back to Memberships', 'direktt-membership' ) . '</a>';
		return;
	}
	direktt_membership_render_assign_membership_packages( isset( $_GET['subscriptionId'] ) ? sanitize_text_field( wp_unslash( $_GET['subscriptionId'] ) ) : '' ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, used for content rendering.
	direktt_membership_render_membership_packages( isset( $_GET['subscriptionId'] ) ? sanitize_text_field( wp_unslash( $_GET['subscriptionId'] ) ) : '' ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, used for content rendering.
}

function direktt_membership_render_membership_packages( $subscription_id ) {
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
			<?php
			$all_memberships = direktt_get_all_user_memberships( sanitize_text_field( $subscription_id ) );
			if ( empty( $all_memberships ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'No memberships found.', 'direktt-membership' ) . '</p></div>';
			} else {
				?>
				<table>
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Package Name', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Active', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Issued', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Activated', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Expires', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Usages left', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Valid', 'direktt-membership' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach( $all_memberships as $membership ) {
							$package_id   = intval( $membership['id'] );
							$package_name = esc_html( get_the_title( $package_id ) );
							$type         = get_post_meta( $package_id, 'direktt_membership_package_type', true );
							$max_usage    = get_post_meta( $package_id, 'direktt_membership_package_max_usage', true );

							if ( ! $max_usage ) {
								$max_usage = 0;
							}

							$used_count  = direktt_membership_get_used_count( $membership['issued_id'] );
							$usages_left = $max_usage === 0 ? esc_html__( 'Unlimited', 'direktt-membership' ) : max( 0, $max_usage - $used_count ); 
							?>
							<tr>
								<td><?php echo esc_html( $package_name ); ?></td>
								<td><?php echo $membership['activated'] ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
								<td><?php echo esc_html( human_time_diff( strtotime( $membership['issue_time'] ) ) ) . esc_html__( ' ago', 'direktt-membership' ); ?></td>
								<td>
									<?php
									if ( $membership['activation_time'] ) {
										$activation_time = strtotime( $membership['activation_time'] );
										$current_time = strtotime( current_time( 'mysql' ) );
										echo esc_html( human_time_diff( $activation_time, $current_time ) ) . esc_html__( ' ago', 'direktt-membership' );
									} else {
										echo esc_html( '/' );
									}
									?>
								</td>
								<td>
									<?php
									if ( $membership['expiry_time'] ) {
										$expiry_time = strtotime( $membership['expiry_time'] );
										$current_time = strtotime( current_time( 'mysql' ) );

										if ( $expiry_time > $current_time ) {
											echo esc_html__( 'in ', 'direktt-membership' ) . esc_html( human_time_diff( $current_time, $expiry_time ) );
										} else {
											echo esc_html__( 'expired ', 'direktt-membership' ) . esc_html( human_time_diff( $expiry_time, $current_time ) ) . esc_html__( ' ago', 'direktt-membership' );
										}
									} else {
										echo esc_html( '/' );
									}
									?>
								</td>
								<td><?php echo $type === '1' ? esc_html( $usages_left ) : esc_html( '/' ); ?></td>
								<td><?php echo $membership['valid'] ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
							</tr>
							<tr>
								<td colspan="7">
									<?php
									$redirect_url = remove_query_arg( array( 'success_flag' ) );
									$redirect_url = add_query_arg( array( 'action' => 'view_details', 'id' => $membership['issued_id'] ), $redirect_url );
									?>
									<a href="<?php echo esc_url( $redirect_url ); ?>" class="button"><?php echo esc_html__( 'View Details', 'direktt-membership' ); ?></a>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				<?php
			}
			?>
		</div>

		<div id="direktt-membership-packages-active" style="display: none;">
			<?php
			$active_memberships = direktt_get_active_user_memberships( $subscription_id );
			if ( empty( $active_memberships ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'No memberships found.', 'direktt-membership' ) . '</p></div>';
			} else {
				?>
				<table>
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Package Name', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Active', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Issued', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Activated', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Expires', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Usages left', 'direktt-membership' ); ?></th>
							<th><?php echo esc_html__( 'Valid', 'direktt-membership' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach( $active_memberships as $active_membership ) {
							$package_id   = intval( $active_membership['id'] );
							$package_name = esc_html( get_the_title( $package_id ) );
							$type         = get_post_meta( $package_id, 'direktt_membership_package_type', true );
							$max_usage    = get_post_meta( $package_id, 'direktt_membership_package_max_usage', true );

							if ( ! $max_usage ) {
								$max_usage = 0;
							}

							$used_count  = direktt_membership_get_used_count( $active_membership['issued_id'] );
							$usages_left = $max_usage === 0 ? esc_html__( 'Unlimited', 'direktt-membership' ) : max( 0, $max_usage - $used_count );
							?>
							<tr>
								<td><?php echo esc_html( $package_name ); ?></td>
								<td><?php echo $active_membership['activated'] ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
								<td><?php echo esc_html( human_time_diff( strtotime( $active_membership['issue_time'] ) ) ) . esc_html__( ' ago', 'direktt-membership' ); ?></td>
								<td>
									<?php
									if ( $membership['activation_time'] ) {
										$activation_time = strtotime( $membership['activation_time'] );
										$current_time = strtotime( current_time( 'mysql' ) );
										echo esc_html( human_time_diff( $activation_time, $current_time ) ) . esc_html__( ' ago', 'direktt-membership' );
									} else {
										echo esc_html( '/' );
									}
									?>
								</td>
								<td>
									<?php
									if ( $active_membership['expiry_time'] ) {
										$expiry_time = strtotime( $active_membership['expiry_time'] );
										$current_time = strtotime( current_time( 'mysql' ) );

										if ( $expiry_time > $current_time ) {
											echo esc_html__( 'in ', 'direktt-membership' ) . esc_html( human_time_diff( $current_time, $expiry_time ) );
										} else {
											echo esc_html__( 'expired ', 'direktt-membership' ) . esc_html( human_time_diff( $expiry_time, $current_time ) ) . esc_html__( ' ago', 'direktt-membership' );
										}
									} else {
										echo esc_html( '/' );
									}
									?>
								</td>
								<td><?php echo $type === '1' ? esc_html( $usages_left ) : esc_html( '/' ); ?></td>
								<td><?php echo $active_membership['valid'] ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
							</tr>
							<tr>
								<td colspan="7">
									<?php
									$redirect_url = remove_query_arg( array( 'success_flag' ) );
									$redirect_url = add_query_arg( array( 'action' => 'view_details', 'id' => $active_membership['issued_id'] ), $redirect_url );
									?>
									<a href="<?php echo esc_url( $redirect_url ); ?>" class="button"><?php echo esc_html__( 'View Details', 'direktt-membership' ); ?></a>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				<?php
			}
			?>
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

function direktt_membership_render_assign_membership_packages( $reciever_id ) {
	$args = array(
		'post_type'      => 'direkttmpackages',
		'post_status'    => array( 'publish' ),
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	$membership_packages = get_posts( $args );

	global $direktt_user;
	$subscription_id = $direktt_user['direktt_user_id'];

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
				echo '<td class="direktt-membership-package-name"><strong>' . esc_html( $package_name ) . '</strong></td>';
				echo '<td class="direktt-membership-package-type">' . ( $type === '0' ? esc_html__( 'Time Based', 'direktt-membership' ) : esc_html__( 'Usage Based', 'direktt-membership' ) ) . '</td>';
				echo '<td class="direktt-membership-package-validity">' . ( $type === '0' ? esc_html( $validity ) . esc_html__( ' day(s)', 'direktt-membership' ) : esc_html( '/' ) ) . '</td>';
				echo '<td class="direktt-membership-package-max-usage">' . ( $type === '1' ? esc_html( $max_usage ) . esc_html__( ' usage(s)', 'direktt-membership' ) : esc_html( '/' ) ) . '</td>';
			echo '</tr>';
			echo '<tr class="direktt-membership-actions">';
				echo '<td colspan="4">';
					echo '<button class="button" data-package-id="' . esc_attr( $package->ID ) . '">' . esc_html__( 'Assign Membership', 'direktt-membership' ) . '</button>';
				echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		$allowed_html = wp_kses_allowed_html( 'post' );
		echo wp_kses( Direktt_Public::direktt_render_confirm_popup( 'direktt-assign-membership-package-confirm', __( 'Are you sure you want to assign this membership package?', 'direktt-membership' ) ), $allowed_html );
		echo wp_kses( Direktt_Public::direktt_render_alert_popup( 'direktt-membership-alert', '' ), $allowed_html );
		echo wp_kses( Direktt_Public::direktt_render_loader( __( 'Please don\'t leave this page until the process is complete.', 'direktt-membership' ) ), $allowed_html );
		wp_nonce_field( 'direktt_assign_membership_package_nonce', 'direktt_assign_membership_package_nonce_field' );
		?>
		<script>
			jQuery( document ).ready( function($) {
				$( '.direktt-membership-actions .button' ).off( 'click' ).on( 'click', function( event ) {
					event.preventDefault();
					var packageId = $( this ).data( 'package-id' );
					var packageName = $( this ).closest( 'tr' ).prev( 'tr' ).find( '.direktt-membership-package-name strong' ).text();
					$( '#direktt-assign-membership-package-confirm .direktt-popup-text' ).text( "<?php echo esc_js( __( 'Are you sure you want to assign the membership package:', 'direktt-membership' ) ); ?> " + packageName + "<?php echo esc_html( '?' ); ?>" );
					$( '#direktt-assign-membership-package-confirm' ).addClass( 'direktt-popup-on' );
					$( '#direktt-assign-membership-package-confirm .direktt-popup-yes' ).data( 'package-id', packageId );
				});

				$( '#direktt-assign-membership-package-confirm .direktt-popup-yes' ).off( 'click' ).on( 'click', function( event ) {
					event.preventDefault();
					var packageId = $( this ).data( 'package-id' );
					$( '#direktt-assign-membership-package-confirm' ).removeClass( 'direktt-popup-on' );
					$( '.direktt-loader-overlay' ).fadeIn();
					$.ajax({
						url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
						method: 'POST',
						data: {
							action: 'direktt_assign_membership_package',
							package_id: packageId,
							assigner_id: '<?php echo esc_js( $subscription_id ); ?>',
							reciever_id: '<?php echo esc_js( $reciever_id ); ?>',
							nonce: $( '#direktt_assign_membership_package_nonce_field' ).val()
						},
						success: function( response ) {
							if ( response.success ) {
								window.location.href = '<?php echo esc_url_raw( add_query_arg( 'success_flag', '1' ) ); ?>';
							} else {
								$( '#direktt-membership-alert' ).addClass( 'direktt-popup-on' );
								$( '#direktt-membership-alert .direktt-popup-text' ).text( response.data );
								$( '.direktt-loader-overlay' ).fadeOut();
							}
						},
						error: function() {
							$( '#direktt-membership-alert' ).addClass( 'direktt-popup-on' );
							$( '#direktt-membership-alert .direktt-popup-text' ).text( "<?php echo esc_js( __( 'There was an error assigning the membership package.', 'direktt-membership' ) ); ?>" );
							$( '.direktt-loader-overlay' ).fadeOut();
						}
					});
				});

				$( '#direktt-assign-membership-package-confirm .direktt-popup-no' ).off( 'click' ).on( 'click', function( event ) {
					event.preventDefault();
					$( '#direktt-assign-membership-package-confirm' ).removeClass( 'direktt-popup-on' );
				});

				$( '#direktt-membership-alert .direktt-popup-ok' ).off( 'click' ).on( 'click', function( event ) {
					event.preventDefault();
					$( '#direktt-membership-alert' ).removeClass( 'direktt-popup-on' );
				});
			});
		</script>
		<?php
	}
	echo '</div>';
}

function handle_direktt_assign_membership_package() {
	if ( isset( $_POST['nonce'], $_POST['package_id'], $_POST['assigner_id'], $_POST['reciever_id'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'direktt_assign_membership_package_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'direktt-membership' ) );
			wp_die();
		}

		global $wpdb;
		$table = $wpdb->prefix . 'direktt_membership_issued';

		$package_id  = intval( wp_unslash( $_POST['package_id'] ) );
		$assigner_id = sanitize_text_field( wp_unslash( $_POST['assigner_id'] ) );
		$reciever_id = sanitize_text_field( wp_unslash( $_POST['reciever_id'] ) );

		$membership_package = get_post( $package_id );
		if ( ! $membership_package || $membership_package->post_status !== 'publish' ) {
			wp_send_json_error( esc_html__( 'Invalid membership package.', 'direktt-membership' ) );
			wp_die();
		}

		$type      = get_post_meta( $package_id, 'direktt_membership_package_type', true );
		$activated = $type === '1' ? 1 : 0;
		$guid      = wp_generate_uuid4();

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database insert is required here to add a record to a custom plugin table; $wpdb->insert() is the official safe WordPress method using prepared statements and proper data escaping.
			$table,
			array(
				'membership_package_id'    => (string) $package_id,
				'direktt_assigner_user_id' => $assigner_id,
				'direktt_reciever_user_id' => $reciever_id,
				'membership_guid'          => $guid,
				'activated'                => $activated,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
			)
		);

		if ( $inserted ) {
			$membership_user_issuance           = get_option( 'direktt_membership_user_issuance', 'no' ) === 'yes';
			$membership_user_issuance_template  = intval( get_option( 'direktt_membership_user_issuance_template', 0 ) );
			$membership_admin_issuance          = get_option( 'direktt_membership_admin_issuance', 'no' ) === 'yes';
			$membership_admin_issuance_template = intval( get_option( 'direktt_membership_admin_issuance_template', 0 ) );

			if ( $membership_user_issuance && $membership_user_issuance_template !== 0 ) {
				Direktt_Message::send_message_template(
                    array( $reciever_id ),
                    $membership_user_issuance_template,
					array()
                );
			}

			if ( $membership_admin_issuance && $membership_admin_issuance_template !== 0 ) {
				Direktt_Message::send_message_template_to_admin(
                    $membership_admin_issuance_template,
                    array()
                );
			}

			wp_send_json_success();
			wp_die();
		} else {
			wp_send_json_error( esc_html__( 'Failed to assign membership package.', 'direktt-membership' ) );
			wp_die();
		}
	} else {
		wp_send_json_error( esc_html__( 'Invalid request.', 'direktt-membership' ) );
		wp_die();
	}
}

function direktt_get_all_user_memberships( $subscription_id ) {
	global $wpdb;
	$issued_table = $wpdb->prefix . 'direktt_membership_issued';

	$memberships = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $issued_table WHERE direktt_reciever_user_id = %s ORDER BY valid DESC, activated DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Justifications for phpcs ignores:
			// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
			// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
			// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
			$subscription_id
		)
	);

	$membership_data = array();

	if ( empty( $memberships ) ) {
		return $membership_data;
	}

	foreach ( $memberships as $membership ) {
		$membership_post = get_post( intval( $membership->membership_package_id ) );
		if ( ! $membership_post || $membership_post->post_status !== 'publish' ) {
			continue;
		}

		$membership_data[] = array(
			'issued_id'        => intval( $membership->ID ),
			'id'               => intval( $membership->membership_package_id ),
			'assigner_user_id' => esc_html( $membership->direktt_assigner_user_id ),
			'issue_time'       => esc_html( $membership->issue_time ),
			'activation_time'  => esc_html( $membership->activation_time ),
			'expiry_time'      => esc_html( $membership->expiry_time ),
			'activated'        => intval( $membership->activated ),
			'valid'            => intval( $membership->valid ),
		);
	}

	return $membership_data;
}

function direktt_get_active_user_memberships( $subscription_id ) {
	global $wpdb;
	$issued_table = $wpdb->prefix . 'direktt_membership_issued';

	$memberships = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $issued_table WHERE direktt_reciever_user_id = %s AND activated = 1 AND valid = 1 ORDER BY activated DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Justifications for phpcs ignores:
			// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
			// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
			// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
			$subscription_id
		)
	);

	$membership_data = array();

	if ( empty( $memberships ) ) {
		return $membership_data;
	}

	foreach ( $memberships as $membership ) {
		$membership_post = get_post( intval( $membership->membership_package_id ) );
		if ( ! $membership_post || $membership_post->post_status !== 'publish' ) {
			continue;
		}

		$membership_data[] = array(
			'issued_id'        => intval( $membership->ID ),
			'id'               => intval( $membership->membership_package_id ),
			'assigner_user_id' => esc_html( $membership->direktt_assigner_user_id ),
			'issue_time'       => esc_html( $membership->issue_time ),
			'activation_time'  => esc_html( $membership->activation_time ),
			'expiry_time'      => esc_html( $membership->expiry_time ),
			'activated'        => intval( $membership->activated ),
			'valid'            => intval( $membership->valid ),
		);
	}

	return $membership_data;
}

function direktt_membership_render_view_details( $id ) {
	global $wpdb;
	$issued_table = $wpdb->prefix . 'direktt_membership_issued';

	global $direktt_user;
	$subscription_id = $direktt_user['direktt_user_id'];

	$membership = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $issued_table WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Justifications for phpcs ignores:
			// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
			// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
			// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
			intval( $id )
		)
	);

	if ( ! $membership ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Membership not found.', 'direktt-membership' ) . '</p></div>';
	} else {
		if ( isset( $_GET['success_flag_activate'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, only a flag for displaying a message.
			$success_flag_activate = sanitize_text_field( wp_unslash( $_GET['success_flag_activate'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, only a flag for displaying a message.
			if ( $success_flag_activate === '1' ) {
				echo '<div class="notice"><p>' . esc_html__( 'Membership activated successfully.', 'direktt-membership' ) . '</p></div>';
			}
		}
		if ( isset( $_GET['success_flag_invalidate'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, only a flag for displaying a message.
			$success_flag_invalidate = sanitize_text_field( wp_unslash( $_GET['success_flag_invalidate'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, only a flag for displaying a message.
			if ( $success_flag_invalidate === '1' ) {
				echo '<div class="notice"><p>' . esc_html__( 'Membership invalidated successfully.', 'direktt-membership' ) . '</p></div>';
			}
		}
		if ( isset( $_GET['success_flag_record_usage'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, only a flag for displaying a message.
			$success_flag_record_usage = sanitize_text_field( wp_unslash( $_GET['success_flag_record_usage'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, only a flag for displaying a message.
			if ( $success_flag_record_usage === '1' ) {
				echo '<div class="notice"><p>' . esc_html__( 'Membership used successfully.', 'direktt-membership' ) . '</p></div>';
			}
		}
		$type = get_post_meta( intval( $membership->membership_package_id ), 'direktt_membership_package_type', true );
		if ( $type === '0' ) {
			?>
			<table>
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Package Name', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Active', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Issued', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Activated', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Expires', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Valid', 'direktt-membership' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html( get_the_title( intval( $membership->membership_package_id ) ) ); ?></td>
						<td><?php echo $membership->activated ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
						<td><?php echo esc_html( human_time_diff( strtotime( $membership->issue_time ) ) ) . esc_html__( ' ago', 'direktt-membership' ); ?></td>
						<td>
							<?php
							if ( $membership->activation_time ) {
								$activation_time = strtotime( $membership->activation_time );
								$current_time = strtotime( current_time( 'mysql' ) );
								echo esc_html( human_time_diff( $activation_time, $current_time ) ) . esc_html__( ' ago', 'direktt-membership' );
							} else {
								echo esc_html( '/' );
							}
							?>
						</td>
						<td>
							<?php
							if ( $membership->expiry_time ) {
								$expiry_time  = strtotime( $membership->expiry_time );
								$current_time = strtotime( current_time( 'mysql' ) );

								if ( $expiry_time > $current_time ) {
									echo esc_html__( 'in ', 'direktt-membership' ) . esc_html( human_time_diff( $current_time, $expiry_time ) );
								} else {
									echo esc_html__( 'expired ', 'direktt-membership' ) . esc_html( human_time_diff( $expiry_time, $current_time ) ) . esc_html__( ' ago', 'direktt-membership' );
								}
							} else {
								echo esc_html( '/' );
							}
							?>
						</td>
						<td><?php echo $membership->valid ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
					</tr>
					<tr>
						<td colspan="6">
							<?php
							if ( ! $membership->valid ) {
								?>
								<div class="notice notice-error"><p><?php echo esc_html__( 'This membership is invalidated.', 'direktt-membership' ); ?></p></div>
								<?php
							} else {
								if ( ! $membership->activated ) {
									?>
									<button class="button" id="direktt-membership-activate"><?php echo esc_html__( 'Activate Membership', 'direktt-membership' ); ?> </button>
									<?php
									$allowed_html = wp_kses_allowed_html( 'post' );
									echo wp_kses( Direktt_Public::direktt_render_confirm_popup( 'direktt-membership-activate-confirm', __( 'Are you sure you want to activate this membership?', 'direktt-membership' ) ), $allowed_html );
									echo wp_kses( Direktt_Public::direktt_render_alert_popup( 'direktt-membership-activate-alert', '' ), $allowed_html );
									?>
									<script>
									jQuery( document ).ready( function($) {
										$( '#direktt-membership-activate' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '#direktt-membership-activate-confirm' ).addClass( 'direktt-popup-on' );
										});

										$( '#direktt-membership-activate-confirm .direktt-popup-yes' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '.direktt-loader-overlay' ).fadeIn();
											$( '#direktt-membership-activate-confirm' ).removeClass( 'direktt-popup-on' );
											$.ajax({
												url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
												method: 'POST',
												data: {
													action: 'direktt_activate_membership',
													issued_id: '<?php echo esc_js( intval( $membership->ID ) ); ?>',
													nonce: '<?php echo esc_js( wp_create_nonce( 'direktt_activate_membership_nonce' ) ); ?>'
												},
												success: function( response ) {
													if ( response.success ) {
														<?php
														$redirect_url = remove_query_arg( array( 'success_flag_record_usage', 'success_flag_invalidate' ) );
														$redirect_url = add_query_arg( 'success_flag_activate', '1', $redirect_url );
														?>
														window.location.href = '<?php echo esc_url_raw( $redirect_url ); ?>';
													} else {
														$( '#direktt-membership-activate-alert' ).addClass( 'direktt-popup-on' );
														$( '#direktt-membership-activate-alert .direktt-popup-text' ).text( response.data );
														$( '.direktt-loader-overlay' ).fadeOut();
													}
												},
												error: function() {
													$( '#direktt-membership-activate-alert' ).addClass( 'direktt-popup-on' );
													$( '#direktt-membership-activate-alert .direktt-popup-text' ).text( "<?php echo esc_js( __( 'There was an error activating the membership.', 'direktt-membership' ) ); ?>" );
													$( '.direktt-loader-overlay' ).fadeOut();
												}
											});
										});

										$( '#direktt-membership-activate-confirm .direktt-popup-no' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '#direktt-membership-activate-confirm' ).removeClass( 'direktt-popup-on' );
										});

										$( '#direktt-membership-activate-alert .direktt-popup-ok' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '#direktt-membership-activate-alert' ).removeClass( 'direktt-popup-on' );
										});
									});
									</script>
									<?php
								} else {
									if ( strtotime( $membership->expiry_time ) < strtotime( current_time( 'mysql' ) ) ) {
										?>
										<div class="notice notice-error"><p><?php echo esc_html__( 'This membership has expired.', 'direktt-membership' ); ?></p></div>
										<?php
									} else {
										?>
										<div class="notice"><p><?php echo esc_html__( 'This membership is currently active.', 'direktt-membership' ); ?></p></div>
										<?php
									}
								}
								?>
								<button class="button button-red button" id="direktt-membership-invalidate"><?php echo esc_html__( 'Invalidate Membership', 'direktt-membership' ); ?> </button>
								<?php
								$allowed_html = wp_kses_allowed_html( 'post' );
								echo wp_kses( Direktt_Public::direktt_render_loader( __( 'Please don\'t leave this page until the process is complete.', 'direktt-membership' ) ), $allowed_html );
								echo wp_kses( Direktt_Public::direktt_render_confirm_popup( 'direktt-membership-invalidate-confirm', __( 'Are you sure you want to invalidate this membership?', 'direktt-membership' ) ), $allowed_html );
								echo wp_kses( Direktt_Public::direktt_render_alert_popup( 'direktt-membership-invalidate-alert', '' ), $allowed_html );
								?>
								<script>
									jQuery( document ).ready( function($) {
										$( '#direktt-membership-invalidate' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '#direktt-membership-invalidate-confirm' ).addClass( 'direktt-popup-on' );
										});

										$( '#direktt-membership-invalidate-confirm .direktt-popup-yes' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '.direktt-loader-overlay' ).fadeIn();
											$( '#direktt-membership-invalidate-confirm' ).removeClass( 'direktt-popup-on' );
											$.ajax({
												url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
												method: 'POST',
												data: {
													action: 'direktt_invalidate_membership',
													issued_id: '<?php echo esc_js( intval( $membership->ID ) ); ?>',
													nonce: '<?php echo esc_js( wp_create_nonce( 'direktt_invalidate_membership_nonce' ) ); ?>'
												},
												success: function( response ) {
													if ( response.success ) {
														<?php
														$redirect_url = remove_query_arg( array( 'success_flag_activate', 'success_flag_record_usage' ) );
														$redirect_url = add_query_arg( array( 'success_flag_invalidate' => '1' ), $redirect_url );
														?>
														window.location.href = '<?php echo esc_url_raw( $redirect_url ); ?>';
													} else {
														$( '#direktt-membership-invalidate-alert' ).addClass( 'direktt-popup-on' );
														$( '#direktt-membership-invalidate-alert .direktt-popup-text' ).text( response.data );
														$( '.direktt-loader-overlay' ).fadeOut();
													}
												},
												error: function() {
													$( '#direktt-membership-invalidate-alert' ).addClass( 'direktt-popup-on' );
													$( '#direktt-membership-invalidate-alert .direktt-popup-text' ).text( "<?php echo esc_js( __( 'There was an error invalidating the membership.', 'direktt-membership' ) ); ?>" );
													$( '.direktt-loader-overlay' ).fadeOut();
												}
											});
										});

										$( '#direktt-membership-invalidate-confirm .direktt-popup-no' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '#direktt-membership-invalidate-confirm' ).removeClass( 'direktt-popup-on' );
										});

										$( '#direktt-membership-invalidate-alert .direktt-popup-ok' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '#direktt-membership-invalidate-alert' ).removeClass( 'direktt-popup-on' );
										});
									});
								</script>
								<?php
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
		} else {
			?>
			<table>
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Package Name', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Issued', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Usages left', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Valid', 'direktt-membership' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html( get_the_title( intval( $membership->membership_package_id ) ) ); ?></td>
						<td><?php echo esc_html( human_time_diff( strtotime( $membership->issue_time ) ) ) . esc_html__( ' ago', 'direktt-membership' ); ?></td>
						<td>
							<?php
							$max_usage = get_post_meta( intval( $membership->membership_package_id ), 'direktt_membership_package_max_usage', true );
							if ( ! $max_usage ) {
								$max_usage = 0;
							}
							if ( $max_usage === 0 ) {
								echo esc_html__( 'Unlimited', 'direktt-membership' );
							} else {
								$max_usage = intval( $max_usage );
								$used_count = direktt_membership_get_used_count( intval( $id ) );
								$usages_left = $max_usage - $used_count;
								echo esc_html( $usages_left );
							}
							?>
						</td>
						<td><?php echo $membership->valid ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
					</tr>
					<tr>
						<td colspan="4">
							<?php
							if ( ! $membership->valid ) {
								?>
								<div class="notice notice-error"><p><?php echo esc_html__( 'This membership is invalidated.', 'direktt-membership' ); ?></p></div>
								<?php
							} else {
								if ( $usages_left > 0 || $max_usage === 0 ) {
									?>
									<button class="button" id="direktt-membership-record-usage"><?php echo esc_html__( 'Record Usage', 'direktt-membership' ); ?> </button>
									<?php
									$allowed_html = wp_kses_allowed_html( 'post' );
									echo wp_kses( Direktt_Public::direktt_render_confirm_popup( 'direktt-membership-record-usage-confirm', __( 'Are you sure you want to record usage for this membership?', 'direktt-membership' ) ), $allowed_html );
									echo wp_kses( Direktt_Public::direktt_render_alert_popup( 'direktt-membership-record-usage-alert', '' ), $allowed_html );
									?>
									<script>
									jQuery( document ).ready( function($) {
										$( '#direktt-membership-record-usage' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '#direktt-membership-record-usage-confirm' ).addClass( 'direktt-popup-on' );
										});

										$( '#direktt-membership-record-usage-confirm .direktt-popup-yes' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '.direktt-loader-overlay' ).fadeIn();
											$( '#direktt-membership-record-usage-confirm' ).removeClass( 'direktt-popup-on' );
											$.ajax({
												url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
												method: 'POST',
												data: {
													action: 'direktt_record_membership_usage',
													issued_id: '<?php echo esc_js( intval( $membership->ID ) ); ?>',
													subscription_id: '<?php echo esc_js( $subscription_id ); ?>',
													nonce: '<?php echo esc_js( wp_create_nonce( 'direktt_record_membership_usage_nonce' ) ); ?>'
												},
												success: function( response ) {
													if ( response.success ) {
														<?php
														$redirect_url = remove_query_arg( array( 'success_flag_invalidate', 'success_flag_activate' ) );
														$redirect_url = add_query_arg( 'success_flag_record_usage', '1', $redirect_url );
														?>
														window.location.href = '<?php echo esc_url_raw( $redirect_url ); ?>';
													} else {
														$( '#direktt-membership-record-usage-alert' ).addClass( 'direktt-popup-on' );
														$( '#direktt-membership-record-usage-alert .direktt-popup-text' ).text( response.data );
														$( '.direktt-loader-overlay' ).fadeOut();
													}
												},
												error: function() {
													$( '#direktt-membership-record-usage-alert' ).addClass( 'direktt-popup-on' );
													$( '#direktt-membership-record-usage-alert .direktt-popup-text' ).text( "<?php echo esc_js( __( 'There was an error recording the usage.', 'direktt-membership' ) ); ?>" );
													$( '.direktt-loader-overlay' ).fadeOut();
												}
											});
										});

										$( '#direktt-membership-record-usage-confirm .direktt-popup-no' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '#direktt-membership-record-usage-confirm' ).removeClass( 'direktt-popup-on' );
										});

										$( '#direktt-membership-record-usage-alert .direktt-popup-ok' ).off( 'click' ).on( 'click', function( event ) {
											event.preventDefault();
											$( '#direktt-membership-record-usage-alert' ).removeClass( 'direktt-popup-on' );
										});
									});
									</script>
									<button class="button button-red button" id="direktt-membership-invalidate"><?php echo esc_html__( 'Invalidate Membership', 'direktt-membership' ); ?> </button>
									<?php
									$allowed_html = wp_kses_allowed_html( 'post' );
									echo wp_kses( Direktt_Public::direktt_render_loader( __( 'Please don\'t leave this page until the process is complete.', 'direktt-membership' ) ), $allowed_html );
									echo wp_kses( Direktt_Public::direktt_render_confirm_popup( 'direktt-membership-invalidate-confirm', __( 'Are you sure you want to invalidate this membership?', 'direktt-membership' ) ), $allowed_html );
									echo wp_kses( Direktt_Public::direktt_render_alert_popup( 'direktt-membership-invalidate-alert', '' ), $allowed_html );
									?>
									<script>
										jQuery( document ).ready( function($) {
											$( '#direktt-membership-invalidate' ).off( 'click' ).on( 'click', function( event ) {
												event.preventDefault();
												$( '#direktt-membership-invalidate-confirm' ).addClass( 'direktt-popup-on' );
											});

											$( '#direktt-membership-invalidate-confirm .direktt-popup-yes' ).off( 'click' ).on( 'click', function( event ) {
												event.preventDefault();
												$( '.direktt-loader-overlay' ).fadeIn();
												$( '#direktt-membership-invalidate-confirm' ).removeClass( 'direktt-popup-on' );
												$.ajax({
													url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
													method: 'POST',
													data: {
														action: 'direktt_invalidate_membership',
														issued_id: '<?php echo esc_js( intval( $membership->ID ) ); ?>',
														nonce: '<?php echo esc_js( wp_create_nonce( 'direktt_invalidate_membership_nonce' ) ); ?>'
													},
													success: function( response ) {
														if ( response.success ) {
															<?php
															$redirect_url = remove_query_arg( array( 'success_flag_activate', 'success_flag_record_usage' ) );
															$redirect_url = add_query_arg( array( 'success_flag_invalidate' => '1' ), $redirect_url );
															?>
															window.location.href = '<?php echo esc_url_raw( $redirect_url ); ?>';
														} else {
															$( '#direktt-membership-invalidate-alert' ).addClass( 'direktt-popup-on' );
															$( '#direktt-membership-invalidate-alert .direktt-popup-text' ).text( response.data );
															$( '.direktt-loader-overlay' ).fadeOut();
														}
													},
													error: function() {
														$( '#direktt-membership-invalidate-alert' ).addClass( 'direktt-popup-on' );
														$( '#direktt-membership-invalidate-alert .direktt-popup-text' ).text( "<?php echo esc_js( __( 'There was an error invalidating the membership.', 'direktt-membership' ) ); ?>" );
														$( '.direktt-loader-overlay' ).fadeOut();
													}
												});
											});

											$( '#direktt-membership-invalidate-confirm .direktt-popup-no' ).off( 'click' ).on( 'click', function( event ) {
												event.preventDefault();
												$( '#direktt-membership-invalidate-confirm' ).removeClass( 'direktt-popup-on' );
											});

											$( '#direktt-membership-invalidate-alert .direktt-popup-ok' ).off( 'click' ).on( 'click', function( event ) {
												event.preventDefault();
												$( '#direktt-membership-invalidate-alert' ).removeClass( 'direktt-popup-on' );
											});
										});
									</script>
									<?php
								} else {
									?>
									<div class="notice notice-error"><p><?php echo esc_html__( 'This membership has no usages left.', 'direktt-membership' ); ?></p></div>
									<?php
								}
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
		}
	}
}

function handle_direktt_activate_membership() {
	if ( isset( $_POST['nonce'], $_POST['issued_id'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'direktt_activate_membership_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'direktt-membership' ) );
			wp_die();
		}

		global $wpdb;
		$issued_table = $wpdb->prefix . 'direktt_membership_issued';

		$issued_id = intval( wp_unslash( $_POST['issued_id'] ) );

		$membership = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $issued_table WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				// Justifications for phpcs ignores:
				// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
				// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
				// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
				$issued_id
			)
		);

		if ( ! $membership ) {
			wp_send_json_error( esc_html__( 'Membership not found.', 'direktt-membership' ) );
			wp_die();
		}

		if ( intval( $membership->activated ) === 1 ) {
			wp_send_json_error( esc_html__( 'Membership is already activated.', 'direktt-membership' ) );
			wp_die();
		}

		$package_id = intval( $membership->membership_package_id );

		$activation_time = current_time( 'mysql' );
		$validity        = intval( get_post_meta( $package_id, 'direktt_membership_package_validity', true ) );
		$expiry_time     = $validity > 0 ? gmdate( 'Y-m-d H:i:s', strtotime( $activation_time . ' + ' . $validity . ' days' ) ) : null;
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Justifications for phpcs ignores:
			// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct database update is required for this custom plugin table; $wpdb->update() safely uses prepared statements.
			// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not needed because we want to update the table immediately with live data.
			$issued_table,
			array(
				'activated'       => 1,
				'activation_time' => $activation_time,
				'expiry_time'     => $expiry_time,
			),
			array( 'ID' => $issued_id ),
			array(
				'%d',
				'%s',
				$expiry_time ? '%s' : null,
			),
			array( '%d' )
		);

		if ( $updated !== false ) {
			$membership_user_activation           = get_option( 'direktt_membership_user_activation', 'no' ) === 'yes';
			$membership_user_activation_template  = intval( get_option( 'direktt_membership_user_activation_template', 0 ) );
			$membership_admin_activation          = get_option( 'direktt_membership_admin_activation', 'no' ) === 'yes';
			$membership_admin_activation_template = intval( get_option( 'direktt_membership_admin_activation_template', 0 ) );

			$reciever_id = $membership->direktt_reciever_user_id;

			if ( $membership_user_activation && $membership_user_activation_template !== 0 ) {
				Direktt_Message::send_message_template(
                    array( $reciever_id ),
                    $membership_user_activation_template,
					array()
                );
			}

			if ( $membership_admin_activation && $membership_admin_activation_template !== 0 ) {
				Direktt_Message::send_message_template_to_admin(
                    $membership_admin_activation_template,
                    array()
                );
			}
			wp_send_json_success();
			wp_die();
		} else {
			wp_send_json_error( esc_html__( 'Failed to activate membership.', 'direktt-membership' ) );
			wp_die();
		}
	}
}

function handle_direktt_invalidate_membership() {
	if ( isset( $_POST['nonce'], $_POST['issued_id'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'direktt_invalidate_membership_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'direktt-membership' ) );
			wp_die();
		}

		global $wpdb;
		$issued_table = $wpdb->prefix . 'direktt_membership_issued';

		$issued_id = intval( wp_unslash( $_POST['issued_id'] ) );

		$membership = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $issued_table WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				// Justifications for phpcs ignores:
				// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
				// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_row() is the official WordPress method for this.
				// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
				$issued_id
			)
		);

		if ( ! $membership ) {
			wp_send_json_error( esc_html__( 'Membership not found.', 'direktt-membership' ) );
			wp_die();
		}

		if ( intval( $membership->valid ) === 0 ) {
			wp_send_json_error( esc_html__( 'Membership is already invalidated.', 'direktt-membership' ) );
			wp_die();
		}

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Justifications for phpcs ignores:
			// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct database update is required for this custom plugin table; $wpdb->update() safely uses prepared statements.
			// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not needed because we want to update the table immediately with live data.
			$issued_table,
			array(
				'valid' => 0,
			),
			array( 'ID' => $issued_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( $updated !== false ) {
			wp_send_json_success();
			wp_die();
		} else {
			wp_send_json_error( esc_html__( 'Failed to invalidate membership.', 'direktt-membership' ) );
			wp_die();
		}
	}
}

function handle_direktt_record_membership_usage() {
	if ( isset( $_POST['nonce'], $_POST['issued_id'], $_POST['subscription_id'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'direktt_record_membership_usage_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'direktt-membership' ) );
			wp_die();
		}

		global $wpdb;
		$issued_table = $wpdb->prefix . 'direktt_membership_issued';
		$usage_table  = $wpdb->prefix . 'direktt_membership_used';

		$issued_id = intval( wp_unslash( $_POST['issued_id'] ) );
		$subscription_id = sanitize_text_field( wp_unslash( $_POST['subscription_id'] ) );

		$membership = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $issued_table WHERE ID = %d",// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

				// Justifications for phpcs ignores:
				// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
				// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_row() is the official WordPress method for this.
				// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
				$issued_id
			)
		);

		if ( ! $membership ) {
			wp_send_json_error( esc_html__( 'Membership not found.', 'direktt-membership' ) );
			wp_die();
		}

		if ( intval( $membership->valid ) === 0 ) {
			wp_send_json_error( esc_html__( 'Membership is invalidated.', 'direktt-membership' ) );
			wp_die();
		}

		$package_id = intval( $membership->membership_package_id );

		$max_usage = get_post_meta( $package_id, 'direktt_membership_package_max_usage', true );
		if ( ! $max_usage ) {
			$max_usage = 0;
		}
		$max_usage = intval( $max_usage );

		if ( $max_usage > 0 ) {
			$used_count = direktt_membership_get_used_count( $issued_id );
			if ( $used_count >= $max_usage ) {
				wp_send_json_error( esc_html__( 'No usages left for this membership.', 'direktt-membership' ) );
				wp_die();
			}
		}
		$usage_time = current_time( 'mysql' );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct database insert is required here to add a record to a custom plugin table; $wpdb->insert() is the official safe WordPress method using prepared statements and proper data escaping.
			$usage_table,
			array(
				'issued_id'                 => $issued_id,
				'direktt_validator_user_id' => $subscription_id,
				'usage_time'                => $usage_time,
			),
			array(
				'%d',
				'%s',
				'%s',
			)
		);

		if ( $inserted ) {
			$membership_user_usage          = get_option( 'direktt_membership_user_usage', 'no' ) === 'yes';
			$membership_user_usage_template  = intval( get_option( 'direktt_membership_user_usage_template', 0 ) );
			$membership_admin_usage          = get_option( 'direktt_membership_admin_usage', 'no' ) === 'yes';
			$membership_admin_usage_template = intval( get_option( 'direktt_membership_admin_usage_template', 0 ) );

			$reciever_id = $membership->direktt_reciever_user_id;

			if ( $membership_user_usage && $membership_user_usage_template !== 0 ) {
				Direktt_Message::send_message_template(
                    array( $reciever_id ),
                    $membership_user_usage_template,
					array()
                );
			}

			if ( $membership_admin_usage && $membership_admin_usage_template !== 0 ) {
				Direktt_Message::send_message_template_to_admin(
                    $membership_admin_usage_template,
                    array()
                );
			}
			
			wp_send_json_success();
			wp_die();
		} else {
			wp_send_json_error( esc_html__( 'Failed to record usage.', 'direktt-membership' ) );
			wp_die();
		}
	}
}

function direktt_membership_enqueue_fe_scripts() {
	global $enqueue_direktt_member_scripts;
	if ( $enqueue_direktt_member_scripts ) {
		wp_enqueue_script(
			'qr-code-styling', // Handle
			plugin_dir_url( __FILE__ ) . 'assets/js/qr-code-styling.js', // Source
			array(), // Dependencies (none in this case)
			filetime( plugin_dir_path( __FILE__ ) . 'assets/js/qr-code-styling.js' ), // Version based on file modification time
			true // Load in the footer
		);
	}
}

function direktt_membership_tool_shortcode() {
	global $direktt_user;
	if ( ! $direktt_user ) {
		ob_start();
		echo '<div id="direktt-profile-wrapper">';
		echo '<div id="direktt-profile">';
		echo '<div id="direktt-profile-data" class="direktt-profile-data-membership-tool direktt-service">';
		echo '<div class="notice notice-error"><p>' . esc_html__( 'You need to be logged in to access the Membership Tool.', 'direktt-membership' ) . '</p></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}

	if ( isset( $_GET['action'] ) && $_GET['action'] === 'view_details' ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, just an action based router for content rendering.
		global $enqueue_direktt_member_scripts;
		$enqueue_direktt_member_scripts = true;
		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, just an action based router for content rendering.
		return direktt_membership_render_view_details_shortcode( $id );
	}

	ob_start();
	echo '<div id="direktt-profile-wrapper">';
	echo '<div id="direktt-profile">';
	echo '<div id="direktt-profile-data" class="direktt-profile-data-membership-tool direktt-service">';
	direktt_membership_render_membership_packages( $direktt_user['direktt_user_id'] );
	echo '</div>';
	echo '</div>';
	echo '</div>';
	return ob_get_clean();
}

function direktt_membership_render_view_details_shortcode( $id ) {
	ob_start();
	echo '<div id="direktt-profile-wrapper">';
	echo '<div id="direktt-profile">';
	echo '<div id="direktt-profile-data" class="direktt-profile-data-membership-tool direktt-service">';
	global $wpdb;
	$issued_table = $wpdb->prefix . 'direktt_membership_issued';

	global $direktt_user;
	$subscription_id = $direktt_user['direktt_user_id'];

	$membership = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $issued_table WHERE ID = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Justifications for phpcs ignores:
			// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
			// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
			// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
			intval( $id )
		)
	);

	if ( ! $membership ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Membership not found.', 'direktt-membership' ) . '</p></div>';
	} else {
		$type = get_post_meta( intval( $membership->membership_package_id ), 'direktt_membership_package_type', true );

		$qr_code_image    = get_option( 'direktt_membership_qr_code_image', '' );
		$qr_code_color    = get_option( 'direktt_membership_qr_code_color', '#000000' );
		$qr_code_bg_color = get_option( 'direktt_membership_qr_code_bg_color', '#ffffff' );

		$validation_slug = get_option( 'direktt_membership_validation_slug', '' );
		$validation_url  = site_url( $validation_slug, 'https' );
		if ( $type === '0' ) {
			?>
			<table>
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Package Name', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Active', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Issued', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Activated', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Expires', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Valid', 'direktt-membership' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html( get_the_title( intval( $membership->membership_package_id ) ) ); ?></td>
						<td><?php echo $membership->activated ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
						<td><?php echo esc_html( human_time_diff( strtotime( $membership->issue_time ) ) ) . esc_html__( ' ago', 'direktt-membership' ); ?></td>
						<td>
							<?php
							if ( $membership->activation_time ) {
								$activation_time = strtotime( $membership->activation_time );
								$current_time = strtotime( current_time( 'mysql' ) );
								echo esc_html( human_time_diff( $activation_time, $current_time ) ) . esc_html__( ' ago', 'direktt-membership' );
							} else {
								echo esc_html( '/' );
							}
							?>
						</td>
						<td>
							<?php
							if ( $membership->expiry_time ) {
								$expiry_time  = strtotime( $membership->expiry_time );
								$current_time = strtotime( current_time( 'mysql' ) );

								if ( $expiry_time > $current_time ) {
									echo esc_html__( 'in ', 'direktt-membership' ) . esc_html( human_time_diff( $current_time, $expiry_time ) );
								} else {
									echo esc_html__( 'expired ', 'direktt-membership' ) . esc_html( human_time_diff( $expiry_time, $current_time ) ) . esc_html__( ' ago', 'direktt-membership' );
								}
							} else {
								echo esc_html( '/' );
							}
							?>
						</td>
						<td><?php echo $membership->valid ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
					</tr>
					<tr>
						<td colspan="6">
							<?php
							if ( ! $membership->valid ) {
								?>
								<div class="notice notice-error"><p><?php echo esc_html__( 'This membership is invalidated.', 'direktt-membership' ); ?></p></div>
								<?php
							} else {
								if ( ! ( strtotime( $membership->expiry_time ) < strtotime( current_time( 'mysql' ) ) ) ) {
									?>
									<div class="notice"><p><?php echo esc_html__( 'This membership is currently active.', 'direktt-membership' ); ?></p></div>
									<?php
								} else {
									if ( ! $membership->activated ) {
										$actionObject = array(
											'action' => array(
												'type'    => 'link',
												'params'  => array(
													'url'    => $validation_url,
													'target' => 'app',
												),
												'retVars' => array(
													'membership_guid' => $membership->membership_guid,
												),
											),
										);
										?>
										<div id="direktt-membership-qr-code-canvas"></div>
										<script type="text/javascript">
											const qrCode = new QRCodeStyling({
												width: 350,
												height: 350,
												type: "svg",
												data: '<?php echo wp_json_encode( $actionObject ); ?>',
												image: '<?php echo $qr_code_image ? esc_js( $qr_code_image ) : ''; ?>',
												dotsOptions: {
													color: '<?php echo $qr_code_color ? esc_js( $qr_code_color ) : '#000000'; ?>',
													type: "rounded"
												},
												backgroundOptions: {
													color: '<?php echo $qr_code_bg_color ? esc_js( $qr_code_bg_color ) : '#ffffff'; ?>',
												},
												imageOptions: {
													crossOrigin: "anonymous",
													margin: 20
												}
											});

											qrCode.append(document.getElementById("direktt-membership-qr-code-canvas"));
										</script>
										<?php
									} else {
										?>
										<div class="notice notice-error"><p><?php echo esc_html__( 'This membership has expired.', 'direktt-membership' ); ?></p></div>
										<?php
									}
								}
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
		} else {
			?>
			<table>
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Package Name', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Issued', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Usages left', 'direktt-membership' ); ?></th>
						<th><?php echo esc_html__( 'Valid', 'direktt-membership' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html( get_the_title( intval( $membership->membership_package_id ) ) ); ?></td>
						<td><?php echo esc_html( human_time_diff( strtotime( $membership->issue_time ) ) ) . esc_html__( ' ago', 'direktt-membership' ); ?></td>
						<td>
							<?php
							$max_usage = get_post_meta( intval( $membership->membership_package_id ), 'direktt_membership_package_max_usage', true );
							if ( ! $max_usage ) {
								$max_usage = 0;
							}
							if ( $max_usage === 0 ) {
								echo esc_html__( 'Unlimited', 'direktt-membership' );
							} else {
								$max_usage = intval( $max_usage );
								$used_count = direktt_membership_get_used_count( intval( $id ) );
								$usages_left = $max_usage - $used_count;
								echo esc_html( $usages_left );
							}
							?>
						</td>
						<td><?php echo $membership->valid ? esc_html__( 'Yes', 'direktt-membership' ) : esc_html__( 'No', 'direktt-membership' ); ?></td>
					</tr>
					<tr>
						<td colspan="4">
							<?php
							if ( ! $membership->valid ) {
								?>
								<div class="notice notice-error"><p><?php echo esc_html__( 'This membership is invalidated.', 'direktt-membership' ); ?></p></div>
								<?php
							} else {
								if ( $usages_left > 0 || $max_usage === 0 ) {
									$actionObject = array(
										'action' => array(
											'type'    => 'link',
											'params'  => array(
												'url'    => $validation_url,
												'target' => 'app',
											),
											'retVars' => array(
												'membership_guid' => $membership->membership_guid,
											),
										),
									);
									?>
									<div id="direktt-membership-qr-code-canvas"></div>
									<script type="text/javascript">
										const qrCode = new QRCodeStyling({
											width: 350,
											height: 350,
											type: "svg",
											data: '<?php echo wp_json_encode( $actionObject ); ?>',
											image: '<?php echo $qr_code_image ? esc_js( $qr_code_image ) : ''; ?>',
											dotsOptions: {
												color: '<?php echo $qr_code_color ? esc_js( $qr_code_color ) : '#000000'; ?>',
												type: "rounded"
											},
											backgroundOptions: {
												color: '<?php echo $qr_code_bg_color ? esc_js( $qr_code_bg_color ) : '#ffffff'; ?>',
											},
											imageOptions: {
												crossOrigin: "anonymous",
												margin: 20
											}
										});

										qrCode.append(document.getElementById("direktt-membership-qr-code-canvas"));
									</script>
									<?php
								} else {
									?>
									<div class="notice notice-error"><p><?php echo esc_html__( 'This membership has no usages left.', 'direktt-membership' ); ?></p></div>
									<?php
								}
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<?php
		}
	}

	$back_url = remove_query_arg( array( 'action', 'id' ) );
	echo ' <a href="' . esc_url( $back_url ) . '" class="button">' . esc_html__( 'Back to Memberships', 'direktt-membership' ) . '</a>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	return ob_get_clean();
}

function direktt_membership_user_can_validate() {
	global $direktt_user;

	if ( class_exists( 'Direktt_User' ) && Direktt_User::is_direktt_admin() ) {
		return true;
	}

	$issue_categories = get_option( 'direktt_membership_issue_categories', 0 );
	$issue_tags       = get_option( 'direktt_membership_issue_tags', 0 );

	$category_slug = '';
	$tag_slug      = '';

	if ( $issue_categories !== 0 ) {
		$category      = get_term( $issue_categories, 'direkttusercategories' );
		$category_slug = $category ? $category->slug : '';
	}

	if ( $issue_tags !== 0 ) {
		$tag      = get_term( $issue_tags, 'direkttusertags' );
		$tag_slug = $tag ? $tag->slug : '';
	}

	// Check via provided function
	if ( class_exists( 'Direktt_User' ) && Direktt_User::has_direktt_taxonomies( $direktt_user, $category_slug ? array( $category_slug ) : array(), $tag_slug ? array( $tag_slug ) : array() ) ) {
		return true;
	}
	return false;
}

function direktt_membership_validation_shortcode() {
	ob_start();
	echo '<div id="direktt-profile-wrapper">';
	echo '<div id="direktt-profile">';
	echo '<div id="direktt-profile-data" class="direktt-profile-data-membership-tool direktt-service">';
	if ( ! direktt_membership_user_can_validate() ) {	
		echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to validate memberships.', 'direktt-membership' ) . '</p></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}

	if ( ! isset( $_GET['membership_guid'] ) || empty( $_GET['membership_guid'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, used for content rendering.
		echo '<div class="notice notice-error"><p>' . esc_html__( 'No membership GUID provided.', 'direktt-membership' ) . '</p></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}
	global $wpdb;
	$issued_table = $wpdb->prefix . 'direktt_membership_issued';
	$membership_guid = sanitize_text_field( wp_unslash( $_GET['membership_guid'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Justification: not a form processing, used for content rendering.

	$membership = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $issued_table WHERE membership_guid = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Justifications for phpcs ignores:
			// WordPress.DB.PreparedSQL.InterpolatedNotPrepared: $issued_table is built from $wpdb->prefix + literal string.
			// WordPress.DB.DirectDatabaseQuery.DirectQuery: Direct query is necessary because we're fetching data from a custom plugin table; $wpdb->get_results() is the official WordPress method for this.
			// WordPress.DB.DirectDatabaseQuery.NoCaching: Caching is not used here because we want fresh data each time; object caching is not necessary for this query.
			$membership_guid
		)
	);

	if ( ! $membership ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Membership not found.', 'direktt-membership' ) . '</p></div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		return ob_get_clean();
	}

	add_action( 'wp_enqueue_scripts', function() {
		if ( ! wp_script_is( 'jquery' ) ) {
			wp_enqueue_script( 'jquery' );
		}
	});

	direktt_membership_render_view_details( $membership->ID );
	echo '</div>';
	echo '</div>';
	echo '</div>';
	return ob_get_clean();
}

function direktt_membership_highlight_submenu( $parent_file ) {
	global $submenu_file, $current_screen, $pagenow;

	if ( $pagenow === 'post.php' || $pagenow === 'post-new.php' ) {
		if ( $current_screen->post_type === 'direkttmpackages' ) {
			$submenu_file  = 'edit.php?post_type=direkttmpackages';
			$parent_file = 'direktt-dashboard';
		}
	}

	return $parent_file;
}