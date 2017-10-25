<?php

/*
Plugin Name: WordPress Theme Styler
Plugin URI: https://github.com/nikolays93/theme-styler
Description: Компилирует файлы SCSS
Version: 1.4 beta
Author: NikolayS93
Author URI: https://vk.com/nikolays_93
Author EMAIL: nikolayS93@ya.ru
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

namespace CDevelopers\compile;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

define('DIR', rtrim( plugin_dir_path( __FILE__ ), '/') );
define('DIR_CLASSES', DIR . '/includes/classes' );
// define('URL', rtrim(plugins_url(basename(__DIR__)), '/') );
// define('URL_ASSETS', URL . '/includes/assets' );
define('LANG', basename(__DIR__));

require_once DIR . '/includes/utils.php';

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Utils', 'activate' ) );
register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Utils', 'uninstall' ) );
// register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Utils', 'deactivate' ) );

add_action( 'plugins_loaded', array( __NAMESPACE__ . '\Utils', 'get_instance' ), 10 );
