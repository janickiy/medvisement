<?php
/**
 * @package Author Filters
 */
/*
/*
Plugin Name: Author Filters
Version: 3.5.6
Description: Author filters plugin integrates an author filter drop down to sort listing with respect to an author on post, page, custom post type in administration.
Author: Clarion Technologies
Author URI: https://www.clariontech.com/
Plugin URI: https://wordpress.org/plugins/author-filters
Text Domain: author-filters
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Clarion Technologies; either version 2
of the License, or (at your option) any later version.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Clarion Technologies.

Copyright 2005-2018  Clarion Technologies.
*/
defined('ABSPATH') or die('Direct Access Restricted!'); // Check point to restrict direct access to the file

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

define( 'AUTHOR_FILTER__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
  
register_activation_hook( __FILE__, array( 'authorFilters', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'authorFilters', 'plugin_deactivation' ) );  

require_once( AUTHOR_FILTER__PLUGIN_DIR . 'class.authorfilter.php' );

add_action( 'init', array( 'authorFilters', 'init' ) );