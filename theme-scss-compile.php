<?php

/*
Plugin Name: Обновление SCSS стилей
Plugin URI: https://github.com/nikolays93
Description:
Version: 0.0.1
Author: NikolayS93
Author URI: https://vk.com/nikolays_93
Author EMAIL: nikolayS93@ya.ru
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * Хуки плагина:
 * $pageslug . _after_title (default empty hook)
 * $pageslug . _before_form_inputs (default empty hook)
 * $pageslug . _inside_page_content
 * $pageslug . _inside_side_container
 * $pageslug . _inside_advanced_container
 * $pageslug . _after_form_inputs (default empty hook)
 * $pageslug . _after_page_wrap (default empty hook)
 *
 * Фильтры плагина:
 * "get_{DOMAIN}_option_name" - имя опции плагина
 * "get_{DOMAIN}_option" - значение опции плагина
 * "load_{DOMAIN}_file_if_exists" - информация полученная с файла
 * "get_{DOMAIN}_plugin_dir" - Дирректория плагина (доступ к файлам сервера)
 * "get_{DOMAIN}_plugin_url" - УРЛ плагина (доступ к внешним файлам)
 *
 * $pageslug . _form_action - Аттрибут action формы на странице настроек плагина
 * $pageslug . _form_method - Аттрибут method формы на странице настроек плагина
 *
 * theme-css-style-path - Изменить путь к файлу style.css
 */

namespace CDevelopers\SCSS;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

const PLUGIN_DIR = __DIR__;
const DOMAIN = 'theme-scss-compile';

// Нужно подключить заранее для активации и деактивации плагина @see activate(), uninstall();
require __DIR__ . '/utils.php';

class Plugin
{
    private static $initialized;
    private function __construct() {}

    static function activate() { add_option( Utils::get_option_name(), array(
        'check_changes' => 'loggedin',
    ) ); }
    static function uninstall() { delete_option( Utils::get_option_name() ); }

    public static function initialize()
    {
        if( self::$initialized )
            return false;

        load_plugin_textdomain( DOMAIN, false, basename(PLUGIN_DIR) . '/languages/' );
        self::include_required_files();
        self::_filters();
        self::_actions();

        self::$initialized = true;
    }

    /**
     * Подключение файлов нужных для работы плагина
     */
    private static function include_required_files()
    {
        $include = Utils::get_plugin_dir('includes');
        $libs    = Utils::get_plugin_dir('libs');

        $classes = array(
            'Leafo\ScssPhp\Version'           => $libs . '/scssphp/scss.inc.php',
            __NAMESPACE__ . '\WP_Admin_Page'  => $libs . '/wp-admin-page.php',
            __NAMESPACE__ . '\WP_Admin_Forms' => $libs . '/wp-admin-forms.php',
        );

        foreach ($classes as $classname => $path) {
            if( ! class_exists($classname) ) {
                Utils::load_file_if_exists( $path );
            }
            else {
                Utils::write_debug(__('Duplicate class ' . $classname, DOMAIN), __FILE__);
            }
        }

        // includes
        Utils::load_file_if_exists( $include . '/admin-settings-page.php' );
        Utils::load_file_if_exists( $include . '/theme-compile.php' );
    }

    private static function _filters(){
        add_filter( 'scss-content-filter', array(__CLASS__, 'exclude_cyr'), 10, 1 );
    }

    private static function _actions()
    {
        add_action( 'wp_enqueue_scripts', array(__CLASS__, 'compile_styles'), 2 );
        add_action( 'wp_enqueue_scripts', array(__CLASS__, 'enqueue_styles'), 9999 );
    }

    static function exclude_cyr( $content )
    {
        $cyrilic = "/[\x{0410}-\x{042F}]+.*[\x{0410}-\x{042F}]+/iu";
        $content = preg_replace( $cyrilic, "", $content );

        return $content;
    }

    static function compile_styles() {
        $compile = new ThemeCompile();
        if( $compile->is_allow() ) {
            $pathes = apply_filters('ThemeCompilePatches', array(
                get_template_directory() . '/style.scss',
            ));

            foreach ($pathes as $path) {
                $compile->set_patch( $path );
            }

            // $compile->set_patch( Utils::get_plugin_dir('assets') );
            $compile->update();
        }
    }

    static function enqueue_styles()
    {
        if( !Utils::get('enqueue_styles') )
            return;

        $_styletime = Utils::get('stylemtime');
        $styletime = isset($_styletime[ '/style.css' ]) ? $_styletime[ '/style.css' ] : false;
        wp_enqueue_style( 'compiled-styles', Utils::get_stylesheet_url(), array(), $styletime );
    }
}

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'activate' ) );
register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'uninstall' ) );
// register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( __NAMESPACE__ . '\Plugin', 'initialize' ), 10 );
