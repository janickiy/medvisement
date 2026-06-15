<?php
/*
Plugin Name: Custom Login Logo
description: Change the default WordPress logo by uploading your site logo for the login page.
Version: 1.1.10
Author: Hakik Zaman
Author URI: https://github.com/hakikz
Text Domain: ideal-wp-login-logo-changer
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
*
* Exit if accessed directly
*
**/
if (!defined('ABSPATH')) {
    exit;
}



/*Defining Constant*/
define("IWLLC_VERSION", '1.1.10');

/*Add body class for options page*/
function iwllc_admin_body_class( $classes ) {
	global $pagenow;
	$screen = get_current_screen(); 

	if ( in_array( $pagenow, array( 'options-general.php' ), true ) && $screen->id === 'settings_page_change_login_logo' ) {
		$classes .= ' idllc-option-page';
	}

	return $classes;
}

add_filter( 'admin_body_class', 'iwllc_admin_body_class' );

/*Adding Styles for the option page*/
function iwllc_styles_option_page(){
	global $pagenow;
	$screen = get_current_screen(); 

	if ( in_array( $pagenow, array( 'options-general.php' ), true ) &&  $screen->id === 'settings_page_change_login_logo' ) {
		?>
			<style type="text/css">
				.idllc-option-page table.form-table tbody {
				    background-color: #fff;
				}

				.idllc-option-page table.form-table tbody tr:not(:last-child) {
				    border-bottom: 1px solid #eee;
				}

				.idllc-option-page table.form-table tbody th {
				    padding: 15px 10px;
				}

			</style>
		<?php
	}
}
add_action( 'admin_head', 'iwllc_styles_option_page' );


/* Settings to manage WP login logo */
function iwllc_register_custom_logo_settings() 
{
   register_setting( 'iwllc_change_login_options_group', 'iwllc_wp_logo_url', 'sanitize_url');
   register_setting( 'iwllc_change_login_options_group', 'iwllc_wp_set_bg', 'sanitize_text_field');
   register_setting( 'iwllc_change_login_options_group', 'iwllc_wp_bg_color', 'sanitize_hex_color');
   register_setting( 'iwllc_change_login_options_group', 'iwllc_wp_bg_img_url', 'sanitize_url');
   register_setting( 'iwllc_change_login_options_group', 'iwllc_wp_logo_link', 'sanitize_url');
   register_setting( 'iwllc_change_login_options_group', 'iwllc_wp_link_color', 'sanitize_hex_color');
   register_setting( 'iwllc_change_login_options_group', 'iwllc_wp_link_hover_color', 'sanitize_hex_color');
   register_setting( 'iwllc_change_login_options_group', 'iwllc_wp_logo_width', 'sanitize_text_field');
   register_setting( 'iwllc_change_login_options_group', 'iwllc_wp_logo_height', 'sanitize_text_field');
}
add_action( 'admin_init', 'iwllc_register_custom_logo_settings' );


function iwllc_register_login_logo_setting_page() {
  add_options_page('Custom Login Logo', 'Custom Login Logo', 'manage_options', 'change_login_logo', 'iwllc_change_wordpress_login_logo');
}
add_action('admin_menu', 'iwllc_register_login_logo_setting_page');

function iwllc_change_wordpress_login_logo(){
	wp_enqueue_script('jquery');
	wp_enqueue_media();

	$cur_logo = esc_attr( get_option('iwllc_wp_logo_url', '') );
	// Getting the ID of the selected image
	$cur_logo_id = attachment_url_to_postid( esc_url( $cur_logo ) );
	$cur_bg_img = esc_attr( get_option('iwllc_wp_bg_img_url', '') );
	// Getting the ID of the selected image
	$cur_bg_id = attachment_url_to_postid( esc_url( $cur_bg_img ) );

	do_action('iwllc_settings_start');

	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Custom Login Logo Settings', 'ideal-wp-login-logo-changer' ); ?></h1>
		<p><?php echo esc_html__( 'Customize the login page\'s logo, background, link color, and more.', 'ideal-wp-login-logo-changer' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'iwllc_change_login_options_group' ); ?>
			<?php do_settings_sections( 'iwllc_change_login_options_group' ); ?>
			<table class="form-table">

				<!-- Logo Section -->
				<?php if( $cur_logo !== "" && $cur_logo_id !== 0 ): ?>
				<tr valign="top">
					<th>Current Logo</th>
					<td>
						<?php echo wp_get_attachment_image( $cur_logo_id, array('220', '220'), "", array( "class" => "img-responsive iwllc_current_logo" ) );  ?>
					</td>
				</tr>
				<?php endif; ?>
				
				<tr valign="top">
					<th scope="row"><?php echo esc_html__( 'Set Logo', 'ideal-wp-login-logo-changer' ); ?> </th>
					<td>
						<input type="text" id="iwllc_wp_logo_url" name="iwllc_wp_logo_url" value="<?php echo esc_attr( get_option('iwllc_wp_logo_url') ); ?>" />
						<input type="button" name="iwllc-upload-btn" id="iwllc-upload-btn" class="button-secondary iwllc-logo" value="<?php echo esc_html__( 'Upload Logo', 'ideal-wp-login-logo-changer' ) ?>">
						<p class="description"><i><?php echo esc_html__( 'This Image will be displayed in Login Page', 'ideal-wp-login-logo-changer' ) ?></i></p>
					</td>
				</tr>

				<!-- Choose Background Type Section -->
				<tr valign="top">
					<th scope="row"><?php echo esc_html__( 'Background Type', 'ideal-wp-login-logo-changer' ); ?></th>
					<td>
						<select class="iwllc_wp_bg_select" name="iwllc_wp_set_bg">
							<option value="color" <?php echo ( empty( get_option('iwllc_wp_set_bg') ) || get_option('iwllc_wp_set_bg') === 'color' ) ? 'selected' : ''  ?> >Color</option>
							<option value="image" <?php echo ( get_option('iwllc_wp_set_bg') === 'image' ) ? 'selected' : ''  ?>>Image</option>
						</select>
						<p class="description"><i><?php echo esc_html__( 'Default type is `Color`', 'ideal-wp-login-logo-changer' ) ?></i></p>
					</td>
				</tr>

				<!-- Background Color Section -->
				<tr class="type_color" valign="top">
					<th scope="row"><?php echo esc_html__( 'Background Color', 'ideal-wp-login-logo-changer' ); ?> </th>
					<td>
						<input type="text" class="iwllc_wp_bg_color" id="iwllc_wp_bg_color" name="iwllc_wp_bg_color" value="<?php echo esc_attr( get_option('iwllc_wp_bg_color', '#f0f0f1') ); ?>" data-default-color="#f0f0f1" />
						<p class="description"><i><?php echo esc_html__( 'Set your desired color, to change the login page background color', 'ideal-wp-login-logo-changer' ) ?></i></p>
					</td>
				</tr>


				<!-- Background Image Section -->
				<?php if( $cur_bg_img !== "" && $cur_bg_id !== 0 ): ?>
				<tr class="type_image" valign="top">
					<th>Current Background Image</th>
					<td>
						<?php echo wp_get_attachment_image( $cur_bg_id, array('220', '220'), "", array( "class" => "img-responsive iwllc_current_bg" ) );  ?>
					</td>
				</tr>
				<?php endif; ?>


				<tr class="type_image" valign="top">
					<th scope="row"><?php echo esc_html__( 'Background Image', 'ideal-wp-login-logo-changer' ); ?> </th>
					<td>
						<input type="text" id="iwllc_wp_bg_img_url" name="iwllc_wp_bg_img_url" value="<?php echo esc_attr( get_option('iwllc_wp_bg_img_url') ); ?>" />
						<input type="button" name="iwllc-upload-btn" id="iwllc-upload-btn" class="button-secondary iwllc-bg" value="<?php echo esc_html__( 'Upload Background', 'ideal-wp-login-logo-changer' ) ?>">
						<p class="description"><i><?php echo esc_html__( 'This Image will be displayed as background image of Login Page', 'ideal-wp-login-logo-changer' ) ?></i></p>
					</td>
				</tr>

				<!-- Logo Custom Link Section -->
				<tr valign="top">
					<th scope="row"><?php echo esc_html__( 'Your Logo Link', 'ideal-wp-login-logo-changer' ); ?> </th>
					<td>
						<input class="regular-text" type="url" id="iwllc_wp_logo_link" name="iwllc_wp_logo_link" value="<?php echo esc_attr( get_option('iwllc_wp_logo_link') ); ?>" />
						<p class="description"><i><?php echo esc_html__( 'Set your desired link, to redirect after clicking on your logo', 'ideal-wp-login-logo-changer' ) ?></i></p>
					</td>
				</tr>

				<!-- Link Color Section -->
				<tr valign="top">
					<th scope="row"><?php echo esc_html__( 'Link Color', 'ideal-wp-login-logo-changer' ); ?> </th>
					<td>
						<input type="text" class="iwllc_wp_link_color" id="iwllc_wp_link_color" name="iwllc_wp_link_color" value="<?php echo esc_attr( get_option('iwllc_wp_link_color', '#50575e') ); ?>" data-default-color="#50575e" />
						<p class="description"><i><?php echo esc_html__( 'Set your desired color, to change the login page link color', 'ideal-wp-login-logo-changer' ) ?></i></p>
					</td>
				</tr>

				<!-- Link Hover Color Section -->
				<tr valign="top">
					<th scope="row"><?php echo esc_html__( 'Link Hover Color', 'ideal-wp-login-logo-changer' ); ?> </th>
					<td>
						<input type="text" class="iwllc_wp_link_hover_color" id="iwllc_wp_link_hover_color" name="iwllc_wp_link_hover_color" value="<?php echo esc_attr( get_option('iwllc_wp_link_hover_color', '#135e96') ); ?>" data-default-color="#135e96" />
						<p class="description"><i><?php echo esc_html__( 'Set your desired color, to change the login page link hover color', 'ideal-wp-login-logo-changer' ) ?></i></p>
					</td>
				</tr>

				<!-- Logo Width Settings Section -->
				<tr valign="top">
					<th scope="row"><?php echo esc_html__( 'Width', 'ideal-wp-login-logo-changer' ); ?></th>
					<td>
						<input class="small-text" type="number" name="iwllc_wp_logo_width" value="<?php echo esc_attr( get_option('iwllc_wp_logo_width') ); ?>" placeholder="100" /> %
						<p class="description"><i><?php echo esc_html__( 'Default width is 100%', 'ideal-wp-login-logo-changer' ) ?></i></p>
					</td>
				</tr>

				<!-- Logo Height Settings Section -->
				<tr valign="top">
					<th scope="row"><?php echo esc_html__( 'Height', 'ideal-wp-login-logo-changer' ); ?></th>
					<td>
						<input class="small-text" type="number" name="iwllc_wp_logo_height" value="<?php echo esc_attr( get_option('iwllc_wp_logo_height') ); ?>" placeholder="100" /> px					
						<p class="description"><i><?php echo esc_html__( 'Default height is 100px', 'ideal-wp-login-logo-changer' ) ?></i></p>
					</td>
				</tr>
			</table>
			<p class="submit submitbox change_login_logo-setting-btn">
				<?php 
					submit_button( __( 'Save Settings', 'ideal-wp-login-logo-changer' ), 'primary', 'change_login_logo-save-settings', false);
					// Making Nonce URL for Reset Link

	                $current_page = 'change_login_logo';

	                $reset_url_args = array(
	                    'action'   => 'reset',
	                    '_wpnonce' => wp_create_nonce( 'change_login_logo-settings' ),
	                );

	                $action_url_args = array(
	                    'page'    => $current_page,
	                );

	                $reset_url  = add_query_arg( wp_parse_args( $reset_url_args, $action_url_args ), admin_url( 'options-general.php' ) );

				?>
				<a onclick="return confirm('<?php esc_html_e( 'Are you sure to reset?', 'ideal-wp-login-logo-changer' ) ?>')" class="submitdelete" href="<?php echo esc_url( $reset_url ) ?>"><?php esc_attr_e( 'Reset Settings', 'ideal-wp-login-logo-changer' ); ?></a>
			</p>
		</form>
	</div>
	<?php
}

/* Adding Backend Scripts */

function iwllc_backend_scripts(){
	$screen = get_current_screen(); 
	if ($screen->id === 'settings_page_change_login_logo') {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker');
		wp_enqueue_script( 'iwllc-backend', plugins_url( '/admin/backend.js', __FILE__ ), array('jquery'), IWLLC_VERSION, 'true' );
		$values = array(
			'bg_type' => get_option('iwllc_wp_set_bg', 'color')
		);
		wp_localize_script( 'iwllc-backend', 'iwllc_admin', $values );
	}
}
add_action('admin_enqueue_scripts', 'iwllc_backend_scripts');



/* Custom WordPress admin login header logo */
function iwllc_wordpress_custom_login_logo() {
    $logo_url=get_option('iwllc_wp_logo_url');

    $bg_type = get_option('iwllc_wp_set_bg', 'color');

    $bg_color=get_option('iwllc_wp_bg_color', '#f0f0f1');

    $link_color=get_option('iwllc_wp_link_color', '#50575e');

    $link_hover_color=get_option('iwllc_wp_link_hover_color', '#135e96');

    $bg_img_url=get_option('iwllc_wp_bg_img_url');

    $iwllc_wp_logo_height=get_option('iwllc_wp_logo_height');

    $iwllc_wp_logo_width=get_option('iwllc_wp_logo_width');

	if(empty($iwllc_wp_logo_height))
	{
		$iwllc_wp_logo_height='100px';
	}
	else
	{
		$iwllc_wp_logo_height.='px';
	}
	if(empty($iwllc_wp_logo_width))
	{
		$iwllc_wp_logo_width='100%';
	}	
	else
	{
		$iwllc_wp_logo_width.='px';
	}

	$style = '<style type="text/css">';
		if(!empty($logo_url)){
			$style .=    'h1 a { 
					background-image:url('.$logo_url.') !important;
					height:'.$iwllc_wp_logo_height.' !important;
					width:'.$iwllc_wp_logo_width.' !important;
					background-size:100% !important;
					line-height:inherit !important;
					}';
		}

		if(!empty($bg_img_url) && $bg_type === "image"){
			$style .=    'body.login.login-action-login{
					background-image:url('.$bg_img_url.') !important;
					background-repeat: no-repeat;
					background-size: 100%;
					background-position: center center;
				}';
		}


		$style .= ' body.login.login-action-login{

				background-color: '.$bg_color.';

			}';

		// Link Color CSS
		$style .= 'body.iwllc_loaded p#nav a, body.iwllc_loaded p#backtoblog a{

				color: '.$link_color.';

			}
		';

		// Link Hover Color CSS
		$style .= 'body.iwllc_loaded p#nav a:hover, body.iwllc_loaded p#backtoblog a:hover{

				color: '.$link_hover_color.';

			}
		';

	$style .= '</style>';

    echo wp_kses( $style, array(
    	"style" => array()
    ) );
}
add_action( 'login_head', 'iwllc_wordpress_custom_login_logo' );

/* Add action links to plugin list*/
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'iwllc_add_change_wordpress_login_logo_action_links' );
function iwllc_add_change_wordpress_login_logo_action_links ( $links ) {
	$settings_link = array(
		 '<a href="' . admin_url( 'options-general.php?page=change_login_logo' ) . '">Logo Settings</a>'
	);
	return array_merge( $links, $settings_link );
}

/* Changing the logo link from wordpress.org to the site */
function iwllc_login_url() {  

	$link = esc_attr( get_option('iwllc_wp_logo_link') );

	return $link ? $link : home_url(); 
}
add_filter( 'login_headerurl', 'iwllc_login_url' );

/* Reset the settings */
function iwllc_reset_settings() {
	if( isset( $_GET['action'] ) && 'reset' === $_GET['action']){

		if( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'change_login_logo-settings' ) ){
			//In our file that handles the request, verify the nonce.
	        delete_option('iwllc_wp_logo_url');
	        delete_option('iwllc_wp_set_bg');
	        delete_option('iwllc_wp_bg_color');
	        delete_option('iwllc_wp_bg_img_url');
	        delete_option('iwllc_wp_logo_link');
	        delete_option('iwllc_wp_link_color');
	        delete_option('iwllc_wp_link_hover_color');
	        delete_option('iwllc_wp_logo_width');
	        delete_option('iwllc_wp_logo_height');
	        wp_safe_redirect( admin_url( 'options-general.php?page=change_login_logo' ) );
	        exit();
		}
		else{
			die( esc_html__( 'Security check', 'ideal-wp-login-logo-changer' ) ); 
		}
	}
}

add_action( 'iwllc_settings_start', 'iwllc_reset_settings' );


function iwllc_add_class_login_page( $classes ) {
    $classes[] = "iwllc_loaded";
    return $classes;
}

add_filter( 'login_body_class', 'iwllc_add_class_login_page' );

?>