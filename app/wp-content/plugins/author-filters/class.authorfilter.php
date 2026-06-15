<?php
/**
 *
 * Author filters plugin integrates an author filter drop down to sort listing with respect to an author on post, page, custom post type in administration.
 *
 * @since 3.5.6
 *
 * @see authorFilters
 * @link /author-filters
 */


class authorFilters{

        private static $initiated = false;
        
        public static function init() {
            if ( ! self::$initiated ) {
                self::init_hooks();
            }
        }

    /**
     * Initializes WordPress hooks
     */
    private static function init_hooks() {
            self::$initiated = true;
            add_action('pre_get_posts', array('authorFilters', 'author_filters_query'));
            add_action('restrict_manage_posts', array('authorFilters', 'author_filters_admin'));
    }
    
    /*
        Function: author_filters_post_types
        Description:  
            * function is attached to get an array of the page and post excluding the other post types.
            
    */
    
    public static function author_filters_post_types() {

        $args = array(
            'public' => true,
            '_builtin' => true
        );

        $output = ''; // names or objects, note names is the default
        $operator = 'or'; // 'and' or 'or'

        $post_types_arrays = get_post_types($args, $output, $operator); //get & store posty types supported by the system

        $types_array = array(); //initialise an array

        $exclude_post_types = array('attachment', 'revision', 'nav_menu_item'); //array of post types to be excluded from array of post types of system

        foreach ($post_types_arrays as $post_types_aray) { 
            
            if (!in_array($post_types_aray->name, $exclude_post_types)) {
            
                array_push($types_array, $post_types_aray->name);
            
            }
            
        } 

        return $types_array;
    }

    /*
        Function: author_filters_admin
        Description:  
            * function is attached to restrict_manage_posts hook of core system 
            * generates user drop-down select box
            * defining the filter that will be used so we can select posts by 'author'
    */
    
    public static function author_filters_admin() {

        $types_array = self::author_filters_post_types();

        //execute only on the 'post' or 'page' content type
        global $post_type;

        if (in_array($post_type, $types_array)) {
            //get a listing of all users that are 'author' or above
            $user_args = array(
                'show_option_all' => 'All Users',
                'orderby' => 'display_name',
                'order' => 'ASC',
                'name' => 'author_admin_filter',
                'who' => 'authors',
                'include_selected' => true
            );

            //determine if we have selected a user to be filtered by already
            if (isset($_GET['author_admin_filter'])) {
                //set the selected value to the value of the author
                $user_args['selected'] = (int) sanitize_text_field($_GET['author_admin_filter']);
            }

            wp_dropdown_users($user_args); //display the users as a drop down
        }
    }  

    /*
        Function: author_filters_query
        Description:  
            * function is attached to pre_get_posts hook of core system 
            * restrict the posts or pages by an additional author filter
    */
    
    public static function author_filters_query($query) {

        global $post_type, $pagenow;
        
        $types_array = self::author_filters_post_types();

        //if we are currently on the edit screen of the post or page type listings

        if ('edit.php' === $pagenow && (in_array($post_type, $types_array))) {

            if (isset($_GET['author_admin_filter'])) {

                //set the query variable for 'author' to the desired value
                $author_id = sanitize_text_field($_GET['author_admin_filter']);

                //if the author is not 0 (meaning all)
                if (0 !== $author_id) {
                    $query->query_vars['author'] = $author_id;
                }
            }
        }
    } 


    /*
        Function: plugin_activation
        Description:  
            * function is attached to plugin activation hook, 
            * performs pre-configuration activities
    */

    public static function plugin_activation() {

        // Clear the permalinks after the post type has been registered
        flush_rewrite_rules();
    }

    /*
        Function: plugin_deactivation
        Description:  
            * function is attached to plugin deactivation hook, 
            * a call to flush_rewrite_rules function is done to Clear the permalinks to remove rewrite rules
    */
    
    public static function plugin_deactivation() {

        // Clear the permalinks to remove our post type's rules
        flush_rewrite_rules();
    }

}
?>