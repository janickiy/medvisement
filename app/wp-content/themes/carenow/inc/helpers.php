<?php
/**
 * Return the built-in header styles for this theme
 *
 * @return  array
 */
Class themesflat_options_helpers {
    public function themesflat_recognize_control_class( $name ) {
        $segments = explode( '-', $name );
        $segments = array_map( 'ucfirst', $segments );

        return implode( '', $segments );
    }
}

function themesflat_get_class_for_custom($vc_class = '',$themesflat_class='') {
    if (!empty($vc_class)) {
        if (function_exists('vc_shortcode_custom_css_class')) {
            $vcclass = vc_shortcode_custom_css_class( $vc_class, '' );
        }
    }
    else {
        $vcclass = $themesflat_class;
    }
    return $vcclass;
}

function themesflat_shortcode_default_id(){
    return array(
                'type'       => 'textfield',
                'param_name' => 'default_id',
                'group' => esc_html__( 'Design Options', 'carenow' ),
                'value' => 'themesflat_'.current_time('timestamp'),
                'std' => 'themesflat_'.current_time('timestamp')
                );
}

function themesflat_add_icons($icon_name='fa',$url='') {
    $icons = '';
    if ($url != '') {
       $fontContent = wp_remote_get( $url, array('sslverify'   => false) );
       if (!is_wp_error($fontContent)){
           $pattern = sprintf('/\.([\A%s].*?)\:/',$icon_name);
           preg_match_all($pattern, $fontContent['body'],$tmp_icons);
           $icons = $tmp_icons[1];
       }
    }

    return $icons;
}

function themesflat_check_isset($control) {
    return isset($control) ? $control : '';
}

function themesflat_render_box_control($name,$control=array(),$id='') {
    add_action('admin_enqueue_scripts','themesflat_admin_color_picker');
    $default = array(
        'margin-top' => '',
        'margin-bottom' => '',
        'margin-left' => '',
        'margin-right' => '',
        'padding-top' => '',
        'padding-bottom' => '',
        'padding-left' => '',
        'padding-right' => '',
        'border-top-width' => '',
        'border-bottom-width' => '',
        'border-left-width' => '',
        'border-right-width' => ''
        );
    $controls = themesflat_decode($control);
    if (!is_array($controls)) {
        $controls = array();
    }
    $controls = array_merge($default,$controls);
    ?>
    <div class="themesflat_box_control">
        <div class="themesflat_box_position">
            <div class="themesflat_box_margin">
                <label class="themesflat_box_label"><?php echo esc_html('Margin');?></label>
                <input placeholder="-" data-position='margin-top' value ="<?php themesflat_esc_attr(($controls['margin-top']));?>" class="top" type="text"/>
                <input placeholder="-" data-position='margin-bottom' value ="<?php themesflat_esc_attr(($controls['margin-bottom']));?>" class="bottom" type="text"/>
                <input placeholder="-" data-position='margin-left' value ="<?php themesflat_esc_attr(($controls['margin-left']));?>" class="left" type="text"/>
                <input placeholder="-" data-position='margin-right' value ="<?php themesflat_esc_attr(($controls['margin-right']));?>" class="right" type="text"/>
            </div>

            <div class="themesflat_box_padding">
                <label class="themesflat_box_label"><?php echo esc_html('Padding');?></label>
                <input placeholder="-" data-position='padding-top' value ="<?php themesflat_esc_attr(($controls['padding-top']));?>" class="top" type="text"/>
                <input placeholder="-" data-position='padding-bottom' value ="<?php themesflat_esc_attr(($controls['padding-bottom']));?>" class="bottom" type="text"/>
                <input placeholder="-" data-position='padding-left' value ="<?php themesflat_esc_attr(($controls['padding-left']));?>" class="left" type="text"/>
                <input placeholder="-" data-position='padding-right' value ="<?php themesflat_esc_attr(($controls['padding-right']));?>" class="right" type="text"/>
            </div>

            <div class="themesflat_box_border">
                <label class="themesflat_box_label"><?php echo esc_html('Border');?></label>
                <input placeholder="-" data-position='border-top-width' value ="<?php themesflat_esc_attr(($controls['border-top-width']));?>" class="top" type="text"/>
                <input placeholder="-" data-position='border-bottom-width' value ="<?php themesflat_esc_attr(($controls['border-bottom-width']));?>" class="bottom" type="text"/>
                <input placeholder="-" data-position='border-left-width' value ="<?php themesflat_esc_attr(($controls['border-left-width']));?>" class="left" type="text"/>
                <input placeholder="-" data-position='border-right-width' value ="<?php themesflat_esc_attr(($controls['border-right-width']));?>" class="right" type="text"/>
            </div>
            <div class="themesflat_control_logo"></div>
        </div>
    </div>
    <input name="<?php echo esc_attr($name);?>" data-customize-setting-link="<?php echo  esc_attr($id);?>" value="<?php echo esc_attr(json_encode($controls));?>" type="hidden"/>
    <?php
}

function themesflat_color_picker_control($title,$control) {
    $output = '<span class="themesflat-options-control-title">'. esc_attr($title).'</span>
                <div class="background-color">
                    <div class="themesflat-options-control-color-picker">
                        <div class="themesflat-options-control-inputs">
                            <input type="text" class="themesflat-color-picker" id="'. esc_attr( $control['name'] ) .'-color" name="'. esc_attr($control['name']).'" data-default-color value="'. esc_attr( $control['color'] ) .'" />
                        </div>
                    </div>
                </div>';
    return $output;
}

function themesflat_available_icons($name = 'icon' ) {
    $icon_types = array ($name.'_type'=>'fontawesome',$name.'_fontawesome' => '',$name.'_openiconic' => '',$name.'_typicons' => '',$name.'_entypo' => '',$name.'_linecons' => '',$name.'_monosocial' => '',$name.'_material' => '',$name.'_simpleline' => '',$name.'_ionicons' => '',$name.'_eleganticons' => '',$name.'_themifyicons' => '',$name.'_icomoon' => '');
    return  $icon_types;
}

function themesflat_custom_button_color($color = '') {
    $color = $color == '' ? themesflat_get_opt( 'primary_color' ) : $color;
    return $color;
}

function themesflat_render_post($blog_layout,$readmore = '[...]',$length='') {
    if ($length != '') {
        global $themesflat_length;
        $themesflat_length = $length;
        add_filter('excerpt_length','themesflat_special_excerpt',1000);
    }
    $button_type = array(
        'blog-grid' => 'no-background',
        'blog-list' => '',
        );
    $_button_type = $button_type[$blog_layout];
    if( strpos( get_the_content(), 'more-link' ) === false ) {
        add_filter( 'excerpt_more', 'themesflat_excerpt_not_more' );
        the_excerpt();
        if ($readmore != '[...]') {
            echo '<div class="themesflat-button-container"><a class="themesflat-button themesflat-archive '. esc_attr($_button_type).'" href="'.get_the_permalink().'" rel="nofollow">'.$readmore.'</a></div>';
            add_filter( 'excerpt_more', 'themesflat_excerpt_more' );
        }
    }
    else {
        if ($readmore != '[...]') {
            the_content('[...]');
            echo '<div class="themesflat-button-container"><a class="themesflat-button themesflat-archive '. esc_attr($_button_type).'" href="'.get_the_permalink().'" rel="nofollow">'.$readmore.'</a></div>';
        }
        else {
            the_content($readmore);
        }
    }
}

function themesflat_excerpt_more( $more ) {
    return '';
}

function themesflat_excerpt_not_more( $more ) {
    return '';
}

function themesflat_remove_more_link_scroll( $link ) {
    $link = preg_replace( '|#more-[0-9]+|', '', $link );

    return $link;
}
add_filter( 'the_content_more_link', 'themesflat_remove_more_link_scroll' );

function themesflat_special_excerpt($length) {
    global $themesflat_length;
    return $themesflat_length;
}

function themesflat_predefined_header_styles() {
    static $styles;

    if ( empty( $styles ) ) {
        $styles = apply_filters( 'themesflat/header_styles', array(
            'header-v1' => esc_html__( 'Classic', 'carenow' ),
            'header-v2' => esc_html__( 'Header style 02', 'carenow' ),
            'header-v4' => esc_html__( 'Modern', 'carenow' ),
        ) );
    }

    return $styles;
}

/**
 * Render header style this theme
 *
 * @return  array
 */
function themesflat_render_header_styles() {
    static $header_style;

    if ( themesflat_meta( 'custom_header' ) == 1 ) {
        $header_style = themesflat_meta( 'header_style' );
    } else {
        $header_style = get_theme_mod( 'header_style', 'Header-v1' );
    }

    return $header_style;
}

function themesflat_available_social_icons() {
    $icons = apply_filters( 'themesflat_available_icons', array(
        'twitter'        => array( 'iclass' => 'twitter', 'title' => 'Twitter','share_link' => THEMESFLAT_PROTOCOL . '://twitter.com/intent/tweet?url=' ),
        'facebook-f'       => array( 'iclass' => 'facebook-f', 'title' => 'Facebook','share_link'=> THEMESFLAT_PROTOCOL . '://www.facebook.com/sharer/sharer.php?u=' ),
        'google-plus-g'    => array( 'iclass' => 'google-plus-g', 'title' => 'Google Plus','share_link'=> THEMESFLAT_PROTOCOL . '://plus.google.com/share?url=' ),
        'pinterest'      => array( 'iclass' => 'pinterest', 'title' => 'Pinterest','share_link' => THEMESFLAT_PROTOCOL . '://pinterest.com/pin/create/bookmarklet/?url=' ),
        'instagram'      => array( 'iclass' => 'instagram', 'title' => 'Instagram','share_link' => 'https://www.instagram.com/?url=' ),
        'youtube'        => array( 'iclass' => 'youtube', 'title' => 'Youtube','share_link' =>'' ),
        'vimeo'          => array( 'iclass' => 'vimeo-square', 'title' => 'Vimeo','share_link' =>'' ),
        'linkedin'       => array( 'iclass' => 'linkedin', 'title' => 'LinkedIn','share_link' => THEMESFLAT_PROTOCOL . '://www.linkedin.com/shareArticle?url=' ),
        'behance'        => array( 'iclass' => 'behance', 'title' => 'Behance','share_link' =>'' ),
        'bitcoin'        => array( 'iclass' => 'bitcoin', 'title' => 'Bitcoin','share_link' =>'' ),
        'bitbucket'      => array( 'iclass' => 'bitbucket', 'title' => 'BitBucket','share_link' => '' ),
        'codepen'        => array( 'iclass' => 'codepen', 'title' => 'Codepen','share_link' =>'' ),
        'delicious'      => array( 'iclass' => 'delicious', 'title' => 'Delicious','share_link' => THEMESFLAT_PROTOCOL . '://delicious.com/save?url='),
        'deviantart'     => array( 'iclass' => 'deviantart', 'title' => 'DeviantArt','share_link' =>'' ),
        'digg'           => array( 'iclass' => 'digg', 'title' => 'Digg','share_link' =>'http://digg.com/submit?url=' ),
        'dribbble'       => array( 'iclass' => 'dribbble', 'title' => 'Dribbble','share_link' =>'' ),
        'flickr'         => array( 'iclass' => 'flickr', 'title' => 'Flickr','share_link' => ''),
        'foursquare'     => array( 'iclass' => 'foursquare', 'title' => 'Foursquare','share_link' => ''),
        'github'         => array( 'iclass' => 'github-alt', 'title' => 'Github','share_link' => ''),
        'jsfiddle'       => array( 'iclass' => 'jsfiddle', 'title' => 'JSFiddle','share_link' => ''),
        'reddit'         => array( 'iclass' => 'reddit', 'title' => 'Reddit','share_link' => THEMESFLAT_PROTOCOL . '://reddit.com/submit?url='),
        'skype'          => array( 'iclass' => 'skype', 'title' => 'Skype','share_link' => THEMESFLAT_PROTOCOL . '://web.skype.com/share?url='),
        'slack'          => array( 'iclass' => 'slack', 'title' => 'Slack','share_link' => ''),
        'soundcloud'     => array( 'iclass' => 'soundcloud', 'title' => 'SoundCloud','share_link' => ''),
        'spotify'        => array( 'iclass' => 'spotify', 'title' => 'Spotify','share_link' => ''),
        'stack-exchange' => array( 'iclass' => 'stack-exchange', 'title' => 'Stack Exchange','share_link' => ''),
        'stack-overflow' => array( 'iclass' => 'stack-overflow', 'title' => 'Stach Overflow','share_link' => ''),
        'steam'          => array( 'iclass' => 'steam', 'title' => 'Steam','share_link' => ''),
        'stumbleupon'    => array( 'iclass' => 'stumbleupon', 'title' => 'Stumbleupon','share_link' => 'http://www.stumbleupon.com/submit?url='),
        'tumblr'         => array( 'iclass' => 'tumblr', 'title' => 'Tumblr','share_link' => THEMESFLAT_PROTOCOL . '://www.tumblr.com/widgets/share/tool?canonicalUrl=')
    ) );

    $icons['__ordering__'] = array_keys( $icons );

    return $icons;
}

/**
 * Menu fallback
 */
function themesflat_menu_fallback() {
    echo '<ul id="menu-main" class="menu">
    <li>
    <a class="menu-fallback" href="' . esc_url(admin_url('nav-menus.php')) . '">' . esc_html__( 'Create a Menu', 'carenow' ) . '</a></li></ul>';
}

function themesflat_esc_attr($attr) {
    echo esc_attr($attr);
}

function themesflat_esc_html($attr) {
    echo esc_html($attr);
}

/**
 * Change the excerpt length
 */
function themesflat_excerpt_length( $length ) {
    $excerpt = themesflat_get_opt('blog_archive_post_excepts_length');
    return $excerpt;
}
add_filter( 'excerpt_length', 'themesflat_excerpt_length', 999 );

/**
 * Blog layout
 */
function themesflat_blog_layout() {
    switch (get_post_type()) {
        case 'post':
            $layout = themesflat_get_opt('sidebar_layout');
            break;
        case 'portfolios':
            $layout = themesflat_get_opt('portfolios_layout');
            break;
        case 'project':
            $layout = themesflat_get_opt('project_layout');
            break;
        case 'services':
            $layout = themesflat_get_opt('services_layout');
            break;
        case 'gallery':
            $layout = themesflat_get_opt('gallery_layout');
            break;
        default:
            $layout = themesflat_get_opt('page_sidebar_layout');
            break;

    }

    if (is_search()) {
        $layout = themesflat_get_opt('sidebar_layout');
    }

    return $layout;
}

function themesflat_font_style($style) {
    if (strlen($style) > 3) {
        switch (substr($style, 0,3)) {
            case 'reg':
                $a[0] = '400';
                $a[1] = 'normal';
            break;
            case 'ita':
                $a[0] = '400';
                $a[1] = 'italic';
            break;
            default:
                $a[0] = substr($style, 0,3);
                $a[1] = substr($style, 3);
            break;
        }

    }
    else {
        $a[0] = $style;
        $a[1] = 'normal';
    }
    return $a;
}

if ( version_compare( $GLOBALS['wp_version'], '4.1', '<' ) ) :
    /**
     * Filters wp_title to print a neat <title> tag based on what is being viewed.
     *
     * @param string $title Default title text for current view.
     * @param string $sep Optional separator.
     * @return string The filtered title.
     */
    function themesflat_wp_title( $title, $sep ) {
        if ( is_feed() ) {
            return $title;
        }

        global $page, $paged;

        // Add the blog name
        $title .= get_bloginfo( 'name', 'display' );

        // Add the blog description for the home/front page.
        $site_description = get_bloginfo( 'description', 'display' );
        if ( $site_description && ( is_home() || is_front_page() ) ) {
            $title .= " $sep $site_description";
        }

        // Add a page number if necessary:
        if ( ( $paged >= 2 || $page >= 2 ) && ! is_404() ) {
            $title .= " $sep " . sprintf( esc_html__( 'Page %s', 'carenow' ), max( $paged, $page ) );
        }

        return $title;
    }

    add_filter( 'wp_title', 'themesflat_wp_title', 10, 2 );

    /**
     * Title shim for sites older than WordPress 4.1.
     *
     * @link https://make.wordpress.org/core/2014/10/29/title-tags-in-4-1/
     * @todo Remove this function when WordPress 4.3 is released.
     */
    if ( ! function_exists( '_wp_render_title_tag' ) ) {
        function themesflat_render_title() {
            ?>
            <title><?php wp_title( '|', true, 'right' ); ?></title>
            <?php
        }
        add_action( 'wp_head', 'themesflat_render_title' );
    }

endif;

function themesflat_hex2rgba($color, $opacity = false) {

    $default = 'rgb(0,0,0)';

    //Return default if no color provided
    if(empty($color))
          return $default;

    //Sanitize $color if "#" is provided
    if ($color[0] == '#' ) {
        $color = substr( $color, 1 );
    }

    //Check if color has 6 or 3 characters and get values
    if (strlen($color) == 6) {
            $hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
    } elseif ( strlen( $color ) == 3 ) {
            $hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
    } else {
            return $default;
    }

    //Convert hexadec to rgb
    $rgb =  array_map('hexdec', $hex);

    //Check if opacity is set(rgba or rgb)
    if($opacity){
        if(abs($opacity) > 1)
            $opacity = 1.0;
        $output = 'rgba('.implode(",",$rgb).','.$opacity.')';
    } else {
        $output = 'rgb('.implode(",",$rgb).')';
    }

    //Return rgb(a) color string
    return $output;
}

function themesflat_render_box_position($class,$box_control,$custom_string='') {
    $css = esc_attr($class) .'{';
    if (is_array($box_control)) {
        foreach ($box_control as $key => $value) {
            if ( $value !='') {
                $css .= esc_attr($key) .':'.esc_attr(str_replace("px","",$value)).'px; ';
            }
        }
    }
    $css .= esc_attr($custom_string);
    $css .= '}';

    wp_add_inline_style( 'themesflat-inline-css', $css );
}

function themesflat_render_style($class,$custom_string=''){
    $css = esc_attr($class) .'{';
    if (is_array($custom_string)) {
        foreach ($custom_string as $key => $value) {
            if ( $value !='') {
                $css .= esc_attr($key) .':'.esc_attr($value);
            }
        }
    }
    else {
        $css .= esc_attr($custom_string);
    }
    $css .= '}';
    add_action( 'wp_enqueue_scripts', 'themesflat_add_custom_styles',10,$css );
}

function themesflat_add_custom_styles($custom) {
    echo 'inhere';
    wp_add_inline_style( 'themesflat-inline-css', '.testimagebox{}' );
}

function themesflat_render_attrs($atts,$echo = true) {
    $attr = '';
    if (is_array($atts)) {
        foreach ($atts as $key => $value) {
            if ( $value !='') {
                $attr .= $key . '="' . esc_attr( $value ) . '" ';
            }
        }
    }
    if ($echo == true) {
        echo esc_attr($attr);
    }
    return $attr;
}

function themesflat_decode($value) {
    if (!is_array($value)) {
        $decoded_value = json_decode(str_replace('&quot;', '"',  $value) , true );
    }
    else {
        $decoded_value = $value;
    }
    return $decoded_value;
}

function themesflat_dynamic_sidebar($sidebar) {
    if ( is_active_sidebar ( $sidebar ) ) {
        dynamic_sidebar( $sidebar );
    }
}

/**
 * Get post meta, using rwmb_meta() function from Meta Box class
 */
function themesflat_meta( $key,$ID = '') {
    global $post;
    if ( $ID =='' && !is_null($post)) :
        return get_post_meta( $post->ID,$key, true );
    else:
        return get_post_meta($ID,$key,true);
    endif;
}

function themesflat_get_opt( $key ) {
    return get_theme_mod( $key, themesflat_customize_default( $key ) );
}

function themesflat_acf_opt($key,$ID='') {
    if( function_exists('get_field')) {
        $acf_field = get_field($key);
    }else{
        $acf_field = '';
    }

    if ( function_exists( 'get_field' ) && isset($acf_field) ) {
        if ( is_array($acf_field) ) {
            $values = '';
            foreach ($acf_field as $value) {
                $values .= $value ;
            }
            if ( empty($values) ) {
                return themesflat_get_opt( $key );
            } else {
                return themesflat_acf_get_field($key);
            }
        } else if ( empty($acf_field) ){
            return themesflat_customize_default( $key );
        } else {
            return themesflat_acf_get_field($key);
        }

    } else {
        return themesflat_get_opt( $key );
    }
}

function themesflat_get_field_acf($key,$ID='') {
    if( function_exists('get_field')) {
        $acf_field = get_field($key, $ID);
    }else{
        $acf_field = '';
    }
    return $acf_field;
}

add_filter('acf/load_field/name=my_field_name', 'themesflat_acf_get_field');
function themesflat_acf_get_field($key) {
    return get_field($key);
}

function themesflat_load_page_menu($params) {
    if ( themesflat_meta( 'enable_custom_navigator' ) == 1 && themesflat_meta('menu_location_primary') != false ) {
        if ($params['theme_location'] == 'primary') {
            $params['menu'] = (int)themesflat_meta('menu_location_primary');
        }
    }
    return ($params);
}

add_filter( 'wp_nav_menu_args', 'themesflat_load_page_menu' );

function themesflat_preload( $preload ) {
    switch ( $preload ) {
        case 'preload-1':
            return printf('<div class="loader-icon"></div>');
            break;
        case 'preload-2':
            return printf('<div class="spin-load-holder"><span class="spin-load-1"></span></div>');
            break;
        case 'preload-3':
            return printf(' 
                <div class="load-holder" style="height: 105px">
                    <div class="cssload-loader">
                        <div class="cssload-side"></div>
                        <div class="cssload-side"></div>
                        <div class="cssload-side"></div>
                        <div class="cssload-side"></div>
                        <div class="cssload-side"></div>
                        <div class="cssload-side"></div>
                        <div class="cssload-side"></div>
                        <div class="cssload-side"></div>
                    </div>
                </div>');
            break;
        case 'preload-4':
            return printf(
                '<div class="load-holder" style="height: 105px">
                    <div class="sk-circle">
                      <div class="sk-circle1 sk-child"></div>
                      <div class="sk-circle2 sk-child"></div>
                      <div class="sk-circle3 sk-child"></div>
                      <div class="sk-circle4 sk-child"></div>
                      <div class="sk-circle5 sk-child"></div>
                      <div class="sk-circle6 sk-child"></div>
                      <div class="sk-circle7 sk-child"></div>
                      <div class="sk-circle8 sk-child"></div>
                      <div class="sk-circle9 sk-child"></div>
                      <div class="sk-circle10 sk-child"></div>
                      <div class="sk-circle11 sk-child"></div>
                      <div class="sk-circle12 sk-child"></div>
                    </div>
                </div>' );
            break;
        case 'preload-5':
            return printf('<div class="load-holder"><span class="load"></span></div>');
            break;
        case 'preload-6':
            return printf('<div class="pulse-loader"><div class="double-bounce3"></div><div class="double-bounce4"></div></div>');
            break;
        case 'preload-7':
            return printf('<div class="saquare-loader-1"></div>');
            break;
        case 'preload-8':
            return printf(
                '<div class="line-loader">
                    <div class="rect1"></div>
                    <div class="rect2"></div>
                    <div class="rect3"></div>
                    <div class="rect4"></div>
                    <div class="rect5"></div>
                </div>');
            break;
        default:
            return printf('<div class="loader-icon"></div>');
            break;
    }
}

function themesflat_preload_hook(){
    // Preloader
    if (themesflat_get_opt('enable_preload') == 1): ?>
    <div id="preloader">
        <div class="row loader">
            <?php themesflat_preload( themesflat_get_opt('preload') ); ?>
        </div>
    </div>
    <?php endif;

    if ( themesflat_get_opt( 'go_top') == 1 ) : ?>
        <!-- Go Top -->
        <a class="go-top">
            <i class="fa fa-arrow-up" aria-hidden="true"></i>
        </a>
    <?php endif;

    get_template_part( 'tpl/header/aside-toggler');
}
add_action( 'wp_body_open', 'themesflat_preload_hook' );

/* Themesflat Language Switch */
if (! function_exists( 'themesflat_language_switch' )) {
    function themesflat_language_switch(){ ?>
        <div class="flat-language languages">
            <?php if ( ! empty($languages_sidebar) ): ?>
                <?php themesflat_dynamic_sidebar('languages-sidebar'); ?>
            <?php else: ?>
            <ul class="unstyled">
                <li class="current">
                    <a href="?lang=en" class="lang_sel_sel">
                        <span class="languages-before-icon fa fa-globe"></span><?php echo esc_html__("English",'carenow');?><i class="fa fa-chevron-down" aria-hidden="true"></i>
                    </a>
                    <ul class="unstyled-child">
                       <li class="icl-en">
                            <a href="?lang=en" class="lang_sel_sel">
                             <?php echo esc_html__("English",'carenow');?>
                            </a>
                       </li>
                       <li class="icl-fr fr">
                            <a href="?lang=fr" class="lang_sel_other">
                             <?php echo esc_html__("French",'carenow');?>
                            </a>
                       </li>
                       <li class="icl-ge ge">
                            <a href="?lang=it" class="lang_sel_other">
                                <?php echo esc_html__("German",'carenow');?>
                            </a>
                       </li>
                    </ul>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    <?php }
}

function themesflat_kses_allowed_html() {
    $allowed_tags = array(
        'a' => array(
            'class' => array(),
            'href'  => array(),
            'rel'   => array(),
            'title' => array(),
        ),
        'abbr' => array(
            'class' => array(),
            'title' => array(),
        ),
        'b' => array(),
        'blockquote' => array(
            'class' => array(),
            'cite'  => array(),
        ),
        'cite' => array(
            'class' => array(),
            'title' => array(),
        ),
        'code' => array(
            'class' => array(),
        ),
        'del' => array(
            'datetime' => array(),
            'title' => array(),
        ),
        'dd' => array(),
        'div' => array(
            'class' => array(),
            'title' => array(),
            'style' => array(),
        ),
        'dl' => array(
            'class' => array(),
        ),
        'dt' => array(
            'class' => array(),
        ),
        'em' => array(
            'class' => array(),
        ),
        'h1' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h2' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h3' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h4' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h5' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h6' => array(
            'class' => array(),
            'style' => array(),
        ),
        'i' => array(
            'class' => array(),
        ),
        'img' => array(
            'alt'    => array(),
            'class'  => array(),
            'height' => array(),
            'src'    => array(),
            'width'  => array(),
        ),
        'li' => array(
            'class' => array(),
            'style' => array(),
        ),
        'ol' => array(
            'class' => array(),
        ),
        'p' => array(
            'class' => array(),
            'style' => array(),
        ),
        'q' => array(
            'cite' => array(),
            'title' => array(),
            'class' => array(),
        ),
        'span' => array(
            'class' => array(),
            'title' => array(),
            'style' => array(),
        ),
        'strike' => array(
            'class' => array(),
        ),
        'strong' => array(
            'class' => array(),
        ),
        'ul' => array(
            'class' => array(),
            'style' => array(),
        ),
        'input' => array(
            'class' => array(),
            'id' => array(),
            'type' => array(),
            'value' => array(),
            'data-customize-setting-link' => array(),
            'placeholder' => array(),
            'name' => array(),
            'tabindex' => array(),
            'size' => array(),
            'aria-required' => array(),
        ),
        'label' => array(
            'class' => array(),
            'style' => array(),
            'for' => array(),
        ),
    );
    return $allowed_tags;
}

function themesflat_change_post_types_slug( $args, $post_type ) {
   if ( 'portfolios' === $post_type ) {
      $args['rewrite']['slug'] = themesflat_get_opt('portfolio_slug');
   }
   if ( 'services' === $post_type ) {
      $args['rewrite']['slug'] = themesflat_get_opt('services_slug');
   }
   if ( 'project' === $post_type ) {
      $args['rewrite']['slug'] = themesflat_get_opt('project_slug');
   }
   if ( 'gallery' === $post_type ) {
      $args['rewrite']['slug'] = themesflat_get_opt('gallery_slug');
   }
   if ( 'doctor' === $post_type ) {
      $args['rewrite']['slug'] = themesflat_get_opt('doctor_slug');
   }
   return $args;
}
add_filter( 'register_post_type_args', 'themesflat_change_post_types_slug', 10, 2 );

add_filter( 'post_type_archive_title', 'themesflat_filter_name_posttype', 10, 2 );
function themesflat_filter_name_posttype( $post_type_name, $post_type ){
    if ( 'portfolios' === $post_type ) {
        $post_type_name = themesflat_get_opt('portfolio_name');
    }
    if ( 'services' === $post_type && themesflat_get_opt('services_name') ) {
        $post_type_name = themesflat_get_opt('services_name');
    }
    if ( 'project' === $post_type && themesflat_get_opt('project_name') ) {
        $post_type_name = themesflat_get_opt('project_name');
    }
    if ( 'gallery' === $post_type && themesflat_get_opt('gallery_name') ) {
        $post_type_name = themesflat_get_opt('gallery_name');
    }
    if ( 'doctor' === $post_type && themesflat_get_opt('doctor_name') ) {
        $post_type_name = themesflat_get_opt('doctor_name');
    }
    return $post_type_name;
}

function themesflat_change_archive_titles($orig_title) {
    global $post;
    if ($post) {
        $post_type = $post->post_type;
    }else {
        $post_type = '';
    }

    $types = array(
        array(
            'post_type' => 'portfolios',
            'title' => themesflat_get_opt('portfolio_name')
        ),
        array(
            'post_type' => 'services',
            'title' => themesflat_get_opt('services_name')
        ),
        array(
            'post_type' => 'project',
            'title' => themesflat_get_opt('project_name')
        ),
        array(
            'post_type' => 'gallery',
            'title' => themesflat_get_opt('gallery_name')
        ),
        array(
            'post_type' => 'doctor',
            'title' => themesflat_get_opt('doctor_name')
        ),
    );

    if ( is_post_type_archive() ) {
        foreach ( $types as $k => $v) {
            if ( in_array($post_type, $types[$k])) {
            return $types[$k]['title'];
            }
        }

    } else {
        return $orig_title;
    }
}
add_filter('wp_title', 'themesflat_change_archive_titles');

function themesflat_layout_draganddrop($blocks) {
    if ( ! is_array( $blocks ) ) {
        $blocks = explode( ',', $blocks );
    }
    $blocks = array_combine( $blocks, $blocks );
    return $blocks;
}

function themesflat_custom_search_form($atts)
{
    // Какие фильтры включены в поиске
	$filters = [];
	if (!empty($atts['filters']))
		$filters = explode(',', $atts['filters']);

    $user_settings = null;
    if ( isset( $_COOKIE["es_search_settings"] ) ) {
        $user_settings = json_decode( wp_unslash($_COOKIE["es_search_settings"]), true);
    }

	$section_names_map = [
		'disease_article' => 'Статьи',
		'substance'       => 'Инструкции',
		'custom_quiz'     => 'Опросники'
	];
    // Клин. реки пока что доступны только персоналу сайта
    if ( current_user_can('manage_options') ) {
        $section_names_map['disease_clinical-guidelines'] = 'Клинические рекомендации';
    }

	$section_name         = [];

    // Данные из шорткода
	if ( ! empty( $atts['section_name'] ) ) {
		$section_name = explode( ',', $atts['section_name'] );

        // Убираем секции, по которым нет поиска
        foreach ( $section_names_map as $k => $v ) {
            if ( ! in_array($k, $section_name) ) {
                unset( $section_names_map[$k] );
            }
        }
	}

    // Берем отмеченные из пользовательских настроек (в куках)
	if ( in_array( 'section_name', $filters) && ! empty( $user_settings['section_name'] ) ) {
		$section_name = $user_settings['section_name'];
	}

    // Берем отмеченные из параметров URL
	if ( ! empty( $_GET['section_name'] ) ) {
		$section_name = $_GET['section_name'];

        // Если ищем по опросникам - оставляем только их
        if ( in_array( 'custom_quiz', $section_name ) ) {
	        $section_names_map = [
		        'custom_quiz'     => 'Опросники'
            ];
        }
        else {
            unset( $section_names_map['custom_quiz'] );
        }
	}

    $specialties = [];
	if (!empty($atts['specialties']))
		$specialties = explode(',', $atts['specialties']);

	if (!empty($_GET['specialties']))
		$specialties = $_GET['specialties'];

    ob_start();
    ?>
    <form role="search" method="get" class="search-form" action="<?= home_url('/'); ?>">

        <div class="search-form__main">
            <label>
                <input type="search" value="<?= get_search_query(); ?>" name="s" class="s" placeholder="Введите запрос"/>

	            <?php if ( in_array( 'section_name', $filters ) || is_search() ): ?>
                    <button class="search-form__settings" type="button"><i class="fa-solid fa-sliders"></i></button>
	            <?php endif; ?>

                <i class="search-form__loading"></i>
            </label>
            <button type="submit" class="search-submit"><i class="carenow-icon-search-01"></i></button>
        </div>

        <div class="search-form-filters" style="<?= ! isset($user_settings['visible']) || $user_settings['visible'] === true ? '' : 'display: none;'; ?>">

            <div class="search-form-filters__row search-form-filters__row_post_type"
                 style="<?= ( in_array( 'section_name', $filters ) || is_search() ) ? '' : 'display:none;'; ?>">
                <strong>Искать в разделах:</strong>
		        <?php foreach ( $section_names_map as $k => $v ): ?>
                    <label>
                        <input type="checkbox" name="section_name[]" value="<?= $k; ?>"
					        <?= in_array( $k, $section_name ) || empty( $section_name ) ? 'checked="checked"' : ''; ?>>
				        <?= $v; ?>
                    </label>
		        <?php endforeach; ?>
            </div>

	        <?php if ( ! empty( $specialties ) ): ?>
                <div class="search-form-filters__row search-form-filters__row_specialties" style="display:none;">
			        <?php foreach ( $specialties as $specialties_item ): ?>
                            <input type="hidden" name="specialties[]" value="<?= $specialties_item; ?>">
			        <?php endforeach; ?>
                </div>
	        <?php endif; ?>

        </div>
    </form>

    <?php
    return ob_get_clean();
}

add_filter('get_search_form', 'themesflat_custom_search_form');
add_shortcode('med_search_form', 'themesflat_custom_search_form');

function themesflat_categories_postcount_filter ($variable) {
    $variable = str_replace('</a> (', '<span> (', $variable);
    $variable = str_replace(')', ')</span></a>', $variable);
    return $variable;
}
add_filter('wp_list_categories','themesflat_categories_postcount_filter');

function themesflat_archive_postcount_filter ($variable) {
    $variable = str_replace('</a>&nbsp;(', '<span> (', $variable);
    $variable = str_replace(')', ')</span></a>', $variable);
    return $variable;
}
add_filter('get_archives_link', 'themesflat_archive_postcount_filter');

function themesflat_get_page_titles() {
    $title = '';

    if ( ! is_archive() ) {
        if ( is_home() ) {
            if ( ! is_front_page() && $page_for_posts = get_option( 'page_for_posts' ) ) {
                $title = get_post_meta( $page_for_posts, 'custom_title', true );
                if ( empty( $title ) ) {
                    $title = get_the_title( $page_for_posts );
                }
            }
            if ( is_front_page() ) {
                if (themesflat_get_opt('blog_featured_title') != '') {
                    $title = themesflat_get_opt('blog_featured_title');
                }else {
                    $title = esc_html__('Blog', 'carenow');
                }
            }
        }
        elseif ( is_page() ) {
            $title = get_post_meta( get_the_ID(), 'custom_title', true );
            if ( ! $title ) {
                $title = get_the_title();
            }
        } elseif ( is_404() ) {
            $title = esc_html__( '404', 'carenow' );
        } elseif ( is_search() ) {
            $title = sprintf( esc_html__( 'Search results for &#8220;%s&#8221;', 'carenow' ), get_search_query() );
        } else {
            $title = get_post_meta( get_the_ID(), 'custom_title', true );
            if ( ! $title ) {
                $title = get_the_title();
            }

            if (is_single() && get_post_type() == 'post' && themesflat_get_opt('blog_featured_title') != ''){
                $title = themesflat_get_opt('blog_featured_title');
            } elseif(is_single() && get_post_type() == 'services' && themesflat_get_opt('services_featured_title') != ''){
                $title = themesflat_get_opt('services_featured_title');
            } elseif(is_single() && get_post_type() == 'project' && themesflat_get_opt('project_featured_title') != ''){
                $title = themesflat_get_opt('project_featured_title');
            } elseif(is_single() && get_post_type() == 'portfolios' && themesflat_get_opt('portfolios_featured_title') != ''){
                $title = themesflat_get_opt('portfolios_featured_title');
            } elseif(is_single() && get_post_type() == 'doctor' && themesflat_get_opt('doctor_featured_title') != ''){
                $title = themesflat_get_opt('doctor_featured_title');
            }
        }
    } else {
        if ( is_author() ) {
            the_post();
            $title = sprintf( esc_html__( 'Author: %s', 'carenow' ), get_the_author() );
            rewind_posts();
        } elseif ( is_day() ) {
            $title = sprintf( esc_html__( 'Daily: %s', 'carenow' ), get_the_date() );
        } elseif ( is_month() ) {
            $title = sprintf( esc_html__( 'Monthly: %s', 'carenow' ), get_the_date( 'F Y' ) );
        } elseif ( is_year() ) {
            $title = sprintf( esc_html__( 'Yearly: %s', 'carenow' ), get_the_date( 'Y' ) );
        } elseif ( is_search() ) {
            $title = sprintf( esc_html__( 'Search results for &#8220;%s&#8221;', 'carenow' ), get_search_query() );
        } elseif ( is_post_type_archive('services') ) {
            $title = post_type_archive_title('', false);
            if (themesflat_get_opt('services_name') != '') {
                $title = themesflat_get_opt('services_name');
            }
        } elseif ( is_post_type_archive('project') ) {
            $title = post_type_archive_title('', false);
            if (themesflat_get_opt('project_name') != '') {
                $title = themesflat_get_opt('project_name');
            }
        } elseif ( is_post_type_archive('portfolios') ) {
            $title = post_type_archive_title('', false);
            if (themesflat_get_opt('portfolio_name') != '') {
                $title = themesflat_get_opt('portfolio_name');
            }
        } elseif ( is_post_type_archive('doctor') ) {
            $title = post_type_archive_title('', false);
            if (themesflat_get_opt('doctor_name') != '') {
                $title = themesflat_get_opt('doctor_name');
            }
        } elseif ( is_post_type_archive('emsb_service') ) {
            $title = esc_html__('Book Appointment','carenow');
        } elseif ( is_tax() || is_category() || is_tag() ) {
            $title = single_term_title( '', false );
        } else {
            $title = esc_html( wp_title('',FALSE) );
        }
    }

    return array(
        'title' => $title,
    );
}

function themesflat_svg( $icon ) {
    $svg = array(
        'cart' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 34 34">
                        <path d="M6.7,30.2c0,1.1,0.9,2.1,2.1,2.1s2.1-0.9,2.1-2.1c0-1.1-0.9-2.1-2.1-2.1S6.7,29,6.7,30.2z M25.3,30.2
                        c0,1.1,0.9,2.1,2.1,2.1s2.1-0.9,2.1-2.1c0-1.1-0.9-2.1-2.1-2.1S25.3,29,25.3,30.2z M0.5,4.4c0,0.6,0.5,1,1,1h2.1l1.3,5.5l1.8,9
                        c0,0.1,0,0.1,0,0.2l-1.1,4.7c-0.1,0.3,0,0.6,0.2,0.9c0.2,0.2,0.5,0.4,0.8,0.4h23.5c0.6,0,1-0.5,1-1c0-0.6-0.5-1-1-1H8l0.5-2.1
                        c0.1,0,0.2,0.1,0.3,0.1h18.9c1.1,0,1.8-0.2,2.4-1.6l3.4-10.3c0.6-1.8-0.7-2.6-1.8-2.6H6.7c-0.2,0-0.3,0.1-0.5,0.1L5.5,4.1
                        c-0.1-0.5-0.5-0.8-1-0.8h-3C0.9,3.3,0.5,3.8,0.5,4.4z M6.8,9.5h24.6l-3.3,10.1c0,0.1-0.1,0.2-0.1,0.2c-0.1,0-0.2,0-0.3,0H8.8v-0.2
                        l0-0.2L6.8,9.5z"/>
                    </svg>',
        'search' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 34 34">
                        <path d="M20.3,0.9c-7.2,0-13,5.8-13,13c0,3.1,1.1,5.9,2.9,8.2l-8.6,8.6c-0.5,0.5-0.5,1.4,0,2s1.4,0.5,2,0l8.6-8.6
                        c2.2,1.8,5.1,2.9,8.2,2.9c7.2,0,13-5.8,13-13S27.5,0.9,20.3,0.9z M20.3,24.9c-6.1,0-11-4.9-11-11s4.9-11,11-11s11,4.9,11,11
                        S26.4,24.9,20.3,24.9z"/>
                    </svg>',
        'menu' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                        <path d="M28.5,4.5h-27C0.7,4.5,0,3.8,0,3s0.7-1.5,1.5-1.5h27C29.3,1.5,30,2.2,30,3S29.3,4.5,28.5,4.5z
                         M15,13.5H1.5C0.7,13.5,0,12.8,0,12s0.7-1.5,1.5-1.5H15c0.8,0,1.5,0.7,1.5,1.5S15.8,13.5,15,13.5z M28.5,22.5h-27
                        C0.7,22.5,0,21.8,0,21s0.7-1.5,1.5-1.5h27c0.8,0,1.5,0.7,1.5,1.5S29.3,22.5,28.5,22.5z"/>
                    </svg>',
        'wishlist' => '<svg version="1.0" xmlns="http://www.w3.org/2000/svg"
                             width="240.000000pt" height="220.000000pt" viewBox="0 0 240.000000 220.000000">
                            <g transform="translate(0.000000,220.000000) scale(0.100000,-0.100000)" stroke="none">
                            <path d="M487 2185 c-160 -33 -322 -145 -400 -276 -61 -104 -81 -184 -81 -324
                            0 -109 3 -128 33 -215 54 -159 152 -316 319 -512 52 -62 263 -282 469 -488
                            l373 -375 373 375 c206 206 417 426 469 488 167 196 265 353 319 512 30 87 33
                            106 33 215 0 95 -4 134 -22 190 -30 96 -86 187 -155 252 -74 68 -140 106 -242
                            140 -67 22 -99 26 -195 27 -128 0 -198 -14 -301 -63 -83 -39 -188 -126 -236
                            -195 -21 -31 -40 -55 -43 -56 -3 0 -17 20 -33 44 -62 98 -196 196 -328 240
                            -103 35 -248 43 -352 21z m258 -99 c174 -41 315 -157 406 -331 24 -46 46 -85
                            49 -85 3 0 18 26 34 58 67 137 168 245 286 306 90 46 151 61 250 61 301 -2
                            508 -188 527 -475 15 -226 -114 -465 -451 -832 -144 -157 -631 -638 -646 -638
                            -15 0 -500 479 -646 638 -262 285 -402 503 -441 685 -27 127 -3 285 59 386
                            111 179 358 277 573 227z"/>
                            </g>
                            </svg>',
    );

    if ( array_key_exists( $icon, $svg) ) {
        return $svg[$icon];
    } else {
        return null;
    }
}

// Ищем, начинается ли строка с какого-то значения из массива
function in_array_starts_with( $string, $haystack ): bool {
	foreach ( (array) $haystack as $item ) {
		if ( str_starts_with( $string, $item ) ) {
			return true;
		}
	}

	return false;
}

// Очистка HTML без слипания текста для ElasticSearch
function es_clean_html($html) {
	$blockTags = [
		'address', 'article', 'aside', 'blockquote', 'canvas', 'dd', 'div',
		'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main',
		'nav', 'noscript', 'ol', 'output', 'p', 'pre', 'section', 'table',
		'tfoot', 'ul', 'video', 'tr', 'td', 'th'
	];

	$pattern = '/<\/?(' . implode('|', $blockTags) . ')[^>]*>/i';
	$html = preg_replace($pattern, ' ', $html);

	// Дополнительно заменяем <br> на пробел
	$html = preg_replace('/<br\s*\/?>/i', ' ', $html);

	// Удаляем все оставшиеся HTML-теги
	$text = strip_tags($html);

	// Преобразуем множественные пробелы в один
	$text = preg_replace('/\s+/u', ' ', $text);

	// Обрезаем пробелы по краям
	$text = trim($text);

	return $text;
}

// 'балл', 'балла', 'баллов'
function plural_russian( $endings, $number ) {
	$cases = [ 2, 0, 1, 1, 1, 2 ];
	$n     = $number;

	return sprintf( $endings[ ( $n % 100 > 4 && $n % 100 < 20 ) ? 2 : $cases[ min( $n % 10, 5 ) ] ], $n );
}