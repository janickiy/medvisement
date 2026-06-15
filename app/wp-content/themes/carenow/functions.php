<?php
/**
 * themesflat functions and definitions
 *
 * @package carenow
 */

/*ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);*/

use MedviseSubscriptions\Subscriber\Subscriber;

define( 'THEMESFLAT_DIR', trailingslashit( get_template_directory() )) ;
define( 'THEMESFLAT_LINK', trailingslashit( get_template_directory_uri() ) );
define( 'THEMESFLAT_PROTOCOL' , (is_ssl()) ? 'https' : 'http' );
if ( ! function_exists( 'themesflat_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function themesflat_setup() {

    /*
     * Make theme available for translation.
     * Translations can be filed in the /languages/ directory.
     * If you're building a theme based on burger, use a find and replace
     * to change 'carenow' to the name of your theme in all the template files
     */
    load_theme_textdomain( 'carenow', THEMESFLAT_DIR . '/languages' );

    // Add default posts and comments RSS feed links to head.
    add_theme_support( 'automatic-feed-links' );

    // Content width
    global $content_width;
    if ( ! isset( $content_width ) ) {
        $content_width = 1200; /* pixels */
    }

    /*
     * Let WordPress manage the document title.
     * By adding theme support, we declare that this theme does not use a
     * hard-coded <title> tag in the document head, and expect WordPress to
     * provide it for us.
     */
    add_theme_support( 'title-tag' );

    /*
     * Enable support for Post Thumbnails on posts and pages.
     *
     * @link http://codex.wordpress.org/Function_Reference/add_theme_support#Post_Thumbnails
     */
    add_theme_support( 'post-thumbnails' ); 
    add_image_size( 'themesflat-blog', 1170, 684, true );

    //Get thumbnail url
    function themesflat_thumbnail_url($size){
        global $post;
        if( $size== '' ) {
            $url = wp_get_attachment_url( get_post_thumbnail_id($post->ID) );
            return esc_url($url);
        } else {
            $url = wp_get_attachment_image_src( get_post_thumbnail_id(get_the_ID()), $size);
            return esc_url($url[0]);
        }
    }

    // This theme uses wp_nav_menu() in one location.
    register_nav_menus( array(
        'primary' => esc_html__( 'Primary Menu', 'carenow' ),
        'topbar' => esc_html__( 'Topbar Menu', 'carenow' )
    ) );

    /*
     * Switch default core markup for search form, comment form, and comments
     * to output valid HTML5.
     */
    add_theme_support( 'html5', array(
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption',
    ) );

    /*
     * Enable support for Post Formats.
     * See http://codex.wordpress.org/Post_Formats
     */
    add_theme_support( 'post-formats', array(
        'aside', 'image', 'gallery', 'video', 'quote', 'link', 'audio'
    ) );

    // Set up the WordPress core custom background feature.
    $args = array(
        'default-color' => 'ffffff',
        'default-image' => '',
    );

    add_theme_support( 'custom-background', $args );
    add_theme_support( 'custom-header', $args );

    // Custom stylesheet to the TinyMCE/block visual editor
    add_theme_support( 'editor-styles' );
    function themesflat_add_editor_styles() {
        add_editor_style( 'css/editor-style.css' );
    }
    add_action( 'admin_init', 'themesflat_add_editor_styles' );

}
endif; // themesflat_setup

add_action( 'after_setup_theme', 'themesflat_setup' );

function medvisement_enqueue_block_editor_styles() {
    $editor_style_path = THEMESFLAT_DIR . 'css/editor-style.css';
    if ( file_exists( $editor_style_path ) ) {
        wp_enqueue_style(
            'medvisement-block-editor-style',
            THEMESFLAT_LINK . 'css/editor-style.css',
            array(),
            filemtime( $editor_style_path )
        );
    }
}
add_action( 'enqueue_block_editor_assets', 'medvisement_enqueue_block_editor_styles', 5 );

function themesflat_wpfilesystem() {
    include_once (ABSPATH . '/wp-admin/includes/file.php');
    WP_Filesystem();
}

/**
 * Register widget area.
 *
 * @link http://codex.wordpress.org/Function_Reference/register_sidebar
 */
function themesflat_widgets_init() {

    register_sidebar( array(
        'name'          => esc_html__( 'Sidebar Blog', 'carenow' ),
        'id'            => 'blog-sidebar',
        'description'   => esc_html__( 'Add widgets here to appear in your sidebar Blog Sidebar.', 'carenow' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ) );     

    //Widget footer
    register_sidebar( array(
        'name'          => esc_html__( 'Footer Widget Area 1', 'carenow' ),
        'id'            => 'footer-1',
        'description'   => esc_html__( 'Add widgets here to appear in your sidebar Footer area 1.', 'carenow' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ) );

    register_sidebar( array(
        'name'          => esc_html__( 'Footer Widget Area 2', 'carenow' ),
        'id'            => 'footer-2',
        'description'   => esc_html__( 'Add widgets here to appear in your sidebar Footer area 2.', 'carenow' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ) );

    register_sidebar( array(
        'name'          => esc_html__( 'Footer Widget Area 3', 'carenow' ),
        'id'            => 'footer-3',
        'description'   => esc_html__( 'Add widgets here to appear in your sidebar Footer area 3.', 'carenow' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ) );

    register_sidebar( array(
        'name'          => esc_html__( 'Footer Widget Area 4', 'carenow' ),
        'id'            => 'footer-4',
        'description'   => esc_html__( 'Add widgets here to appear in your sidebar Footer widget area 4.', 'carenow' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ) );

    register_sidebar( array(
        'name'          => esc_html__( 'Sidebar Toggler', 'carenow' ),
        'id'            => 'aside-toggler-sidebar',
        'description'   => esc_html__( 'Add widgets here to appear in your sidebar toggler.', 'carenow' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ) );
        
}
add_action( 'widgets_init', 'themesflat_widgets_init' );

function themesflat_get_style($style) {
    return str_replace('italic', 'i', $style);
}

// Скрываем виджеты в админке
function medvise_remove_dashboard_meta() {

	// Новости
	remove_meta_box( 'dashboard_primary', 'dashboard', 'normal' );

	// Быстрый черновик
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );

	// На виду
	remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );

	//remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
	//remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );
	//remove_meta_box( 'dashboard_secondary', 'dashboard', 'normal' );
	//remove_meta_box( 'dashboard_incoming_links', 'dashboard', 'normal' );
	//*remove_meta_box( 'dashboard_recent_drafts', 'dashboard', 'side' );
	//*remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
}

add_action( 'admin_init', 'medvise_remove_dashboard_meta' );

/**
 * Enqueue scripts and styles.
 */

function themesflat_scripts() {
    $theme_details = wp_get_theme();
    $theme_version = $theme_details->get( 'Version' );
	$themesflat_main_css_version = file_exists( THEMESFLAT_DIR . 'css/main.css' )
		? filemtime( THEMESFLAT_DIR . 'css/main.css' )
		: $theme_version;
	$themesflat_main_js_version = file_exists( THEMESFLAT_DIR . 'js/main.js' )
		? filemtime( THEMESFLAT_DIR . 'js/main.js' )
		: $theme_version;

	$asset_file           = include( THEMESFLAT_DIR . 'build/script.asset.php' );
	$front_manifest = json_decode( file_get_contents( THEMESFLAT_DIR . 'dist/assets-manifest.json' ), true );

    // Theme stylesheet.
    wp_enqueue_style( 'style', THEMESFLAT_LINK . 'style.css', false, $theme_version );
    wp_enqueue_style('bootstrap', THEMESFLAT_LINK . 'css/bootstrap.css', false, '5.0.2');
    wp_enqueue_style( 'icon-carenow', THEMESFLAT_LINK . 'css/icon-carenow.css', false, $theme_version );
    wp_enqueue_style( 'icon-carenow-medical', THEMESFLAT_LINK . 'css/icon-carenow-medical.css', false, $theme_version );
    wp_enqueue_style( 'owl-carousel', THEMESFLAT_LINK . 'css/owl.carousel.css', false, $theme_version );
    wp_enqueue_style( 'themesflat-animated', THEMESFLAT_LINK . 'css/animated.css', false, $theme_version );
    wp_enqueue_style( 'themesflat-main', THEMESFLAT_LINK . 'css/main.css', ['bootstrap'], $themesflat_main_css_version );
    wp_enqueue_style( 'themesflat-inline-css', THEMESFLAT_LINK . 'css/inline-css.css', ['bootstrap'], $theme_version );

    wp_enqueue_script(
        'additional-script',
        THEMESFLAT_LINK . 'build/script.js',
        $asset_file['dependencies'],
        $asset_file['version']
    );

    wp_enqueue_style( 'fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css', false, '6.4.2' );

    // Load the html5 shiv..    
    wp_enqueue_script( 'html5shiv', THEMESFLAT_LINK . 'js/html5shiv.js', array('jquery'), '3.7.0' ,true);   
    wp_enqueue_script( 'matchmedia', THEMESFLAT_LINK . 'js/matchMedia.js', array('jquery'),'1.2',true);

    wp_enqueue_script('bootstrap', THEMESFLAT_LINK . 'js/bootstrap.bundle.min.js', array('jquery'), '5.0.2', true);
    wp_enqueue_script( 'owl-carousel', THEMESFLAT_LINK . 'js/owl.carousel.js', array('jquery'),'2.3.4',true);
    wp_enqueue_script( 'parallax', THEMESFLAT_LINK . 'js/parallax.js', array('jquery'),'2.6.0',true);
    wp_enqueue_script( 'js-cookie', 'https://cdn.jsdelivr.net/npm/js-cookie@3.0.5/dist/js.cookie.min.js', array(), '3.0.5', true);
	wp_enqueue_script( 'fslightbox', THEMESFLAT_LINK . 'js/fslightbox.js', array(), '3.6.0', true);

    if ( themesflat_get_opt('enable_smooth_scroll') == 1 ) {
       wp_enqueue_script( 'smoothscroll', THEMESFLAT_LINK . 'js/smoothscroll.js', array(),'1.2.1',true);
    }
    
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply', array(),'2.0.4',true );
    }    

    wp_enqueue_style( 'themesflat-responsive', THEMESFLAT_LINK . 'css/responsive.css' );

	if ( ! empty( $front_manifest ) ) {
		wp_enqueue_style( 'app', THEMESFLAT_LINK . "dist/{$front_manifest['styles.css']}", array( 'themesflat-responsive' ) );
	}

	// Load the main js
	wp_enqueue_script( 'themesflat-main', THEMESFLAT_LINK . 'js/main.js', array(), $themesflat_main_js_version );

	if ( is_account_page() ) {
		wp_enqueue_script( 'jquery-validation', THEMESFLAT_LINK . 'js/jquery-validation/jquery.validate.min.js',
			array(), '1.19.5', true );
		wp_enqueue_script( 'jquery-validation-ru',
			THEMESFLAT_LINK . 'js/jquery-validation/localization/messages_ru.js',
			array(), '1.19.5', true );
	}

	if ( is_rtl() ) {
		wp_enqueue_style( 'themesflat-rtl-css', THEMESFLAT_LINK . 'css/rtl.css' );
	}

    wp_enqueue_script( 'article-access-js', THEMESFLAT_LINK . 'js/articleAccess.js', array('jquery'), $theme_version, true );
    wp_enqueue_style( 'article-access-css', THEMESFLAT_LINK . 'css/article-access.css' );

    wp_enqueue_script( 'specialty-access-js', THEMESFLAT_LINK . 'js/specialtyAccess.js', array('jquery'), $theme_version, true );
    wp_enqueue_style( 'specialty-access-css', THEMESFLAT_LINK . 'css/specialty-access.css' );
}

add_action( 'wp_enqueue_scripts', 'themesflat_scripts' );

// Helpers
require THEMESFLAT_DIR . 'inc/helpers.php';

// Struct
require THEMESFLAT_DIR . 'inc/structure.php';

// Breadcrumbs additions.
require THEMESFLAT_DIR . 'inc/breadcrumb.php';

// Custom template tags for this theme.
require THEMESFLAT_DIR . 'inc/template-tags.php';

// Plugin Activation
require_once THEMESFLAT_DIR . 'inc/plugins/plugins.php';

require_once THEMESFLAT_DIR . "inc/options/options-definition.php";

// Load Customizer Style
function themesflat_load_customizer_style() { 
    wp_enqueue_script( 'wp-plupload' );
    wp_enqueue_style( 'plugin-install' ); 
    wp_enqueue_script('jquery-ui');

    wp_register_style('themesflat-customizer', THEMESFLAT_LINK .'css/admin/customizer.css', false, '1.0.0' );
    wp_enqueue_style('themesflat-customizer' ); 
    wp_enqueue_style('themesflat-alpha-color-picker', THEMESFLAT_LINK .'css/admin/alpha-color-picker.css', false, '1.0.0' );
    wp_enqueue_style('themesflat-admin', THEMESFLAT_LINK .'css/admin/style.css', false, '1.0.3' );
    wp_enqueue_script('themesflat-alpha-color-picker', THEMESFLAT_LINK . 'js/admin/alpha-color-picker.js', array('wp-color-picker'),'2.1.2',true);
    wp_enqueue_script('themesflat-customizer', THEMESFLAT_LINK .'js/admin/customizer.js', array( 'jquery','customize-preview' ), '', true );
    wp_enqueue_script('themesflat-multi-image', THEMESFLAT_LINK . 'js/admin/multi-image.js', array('jquery','customize-preview'),'', true );
}
add_action( 'customize_controls_enqueue_scripts', 'themesflat_load_customizer_style' );
add_action( 'admin_enqueue_scripts', 'themesflat_load_customizer_style' );


//Свой код
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );

require THEMESFLAT_DIR . 'vendor/autoload.php';

add_action( 'after_setup_theme', 'crb_load' );
function crb_load() {
    require_once( 'vendor/autoload.php' );
    \Carbon_Fields\Carbon_Fields::boot();
}

require_once(THEMESFLAT_DIR . 'inc/post.php');
require_once(THEMESFLAT_DIR . 'inc/post_types_taxonomies.php');
require_once(THEMESFLAT_DIR . 'inc/ep/Version_Medvisement.php');
require_once(THEMESFLAT_DIR . 'inc/elasticpress.php');
require_once (THEMESFLAT_DIR . 'inc/security.php');
require_once (THEMESFLAT_DIR . 'inc/user-profile.php');
require_once (THEMESFLAT_DIR . 'inc/posts-fields.php');
require_once (THEMESFLAT_DIR . 'inc/product-fields.php');
require_once (THEMESFLAT_DIR . 'inc/terms-fields.php');
require_once (THEMESFLAT_DIR . 'inc/woocommerce-setup.php');
require_once (THEMESFLAT_DIR . 'inc/referral.php');
require_once (THEMESFLAT_DIR . 'inc/share-article.php');
require_once (THEMESFLAT_DIR . 'inc/redirect-manager.php');

// TinyMCE плагин для копипаста из ворда
function med_mce_options($init, $editor_id = '') {
	$init['plugins'] .= ',powerpaste';
	return $init;
}
add_filter( 'tiny_mce_before_init', 'med_mce_options', 20, 2 );

// Contact Form 7
add_filter('wpcf7_autop_or_not', '__return_false');

// Показываем СЕО спойлер для тех, кто не авторизован/нет подписки
add_action( 'wp_head', function () {

    if ( ! is_single() ) {
        return true;
    }

	global $post;

	if ( Subscriber::hasAccess($post) && is_user_logged_in() ) {
		return true;
	}
	?>
	<style type="text/css">
        .wp-block-medvise-seodetails-medvise {
            display: block !important;
        }
	</style>
	<?php
} );

// Заменяем хук для емейлов
function medvise_replace_email_header_hook(){
	remove_action( 'woocommerce_email_header', array( WC()->mailer(), 'email_header' ) );
	add_action( 'woocommerce_email_header', 'medvise_woocommerce_email_header', 10, 2 );
}
add_action( 'init', 'medvise_replace_email_header_hook' );

function medvise_woocommerce_email_header( $email_heading, $email ) {
	$template = 'emails/email-header.php';
	wc_get_template( $template, array( 'email_heading' => $email_heading, 'email_id' => $email->id ) );

}

function medvise_remember_me_checked_by_default( $errors, $redirect_to  ) {
	$_POST['rememberme'] = 1;

    return $errors;
}
add_filter( 'wp_login_errors', 'medvise_remember_me_checked_by_default', 10, 2 );

// cf7 feedback form handler
require THEMESFLAT_DIR . 'inc/plugins/cf7.php';

// copypaste protection
require THEMESFLAT_DIR . 'inc/copy-protect.php';

/*
    для заболеваний берем киртинку предпросмотра og:image из
    Yoast -> настройки -> основый сайта -> изображение сайта 
*/
function alter_existing_opengraph_image( $image ) {
	if ( isset(get_queried_object()->post_type) && get_queried_object()->post_type == 'disease' ) {
        $yoast_social = get_option('wpseo_social');
        if ( $yoast_social && ! empty( $yoast_social['og_default_image'] ) ) {
            $image = $yoast_social['og_default_image'];
        }
    }
    return $image;
}
add_filter( 'wpseo_opengraph_image', 'alter_existing_opengraph_image' );

require THEMESFLAT_DIR . 'inc/Lemmatize.php';
