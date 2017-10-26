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

define('TS_LANG', basename(__DIR__));

class Utils {
    const SETTINGS = 'compiler';

    private static $initialized;
    private static $settings;
    private static $_instance;
    private function __construct() {}
    private function __clone() {}

    static function activate() { add_option( self::SETTINGS, array() ); }
    static function uninstall() { delete_option(self::SETTINGS); }

    public static function initialize()
    {
        if( self::$initialized ) {
            return false;
        }

        load_plugin_textdomain( TS_LANG, false, basename(__DIR__) . '/languages/' );
        self::include_required_classes();

        add_action('wp_enqueue_scripts', array(__NAMESPACE__ . '\Utils', 'enqueue_fonts'), 10 );

        if( apply_filters( 'compile_show_admin_bar_menu', true ) ) {
            add_action( 'admin_bar_menu', array(__CLASS__, 'add_scss_menu'), 99 );
        }

        self::$initialized = true;
    }

    static function add_scss_menu( $wp_admin_bar )
    {
        if( ! current_user_can( 'edit_pages' ) ) {
            return;
        }

        $args = array(
            'id'    => 'SCSS',
            'title' => __( 'SCSS', TS_LANG ),
        );
        $wp_admin_bar->add_node( $args );

        $args = array(
            'id'     => 'force_scss',
            'title'  => __('Update SCSS Cache', TS_LANG), //'Обновить кэш всех стилей',
            'parent' => 'SCSS',
            'href' => '?update_scss=1',
        );
        $wp_admin_bar->add_node( $args );

        $args = array(
            'id'     => 'change_scss_dir',
            'title'  => __('SCSS compile settings', TS_LANG), //'Настройки компилирования SCSS',
            'parent' => 'SCSS',
            'href' => get_home_url() . '/wp-admin/options-general.php?page=' . self::SETTINGS,
        );
        $wp_admin_bar->add_node( $args );
    }

    private static function include_required_classes()
    {
        $classes = array(
            'scssc'                               => '/scss.inc.php',
            __NAMESPACE__ . '\WP_Admin_Page'      => '/wp-admin-page.php',
            __NAMESPACE__ . '\WP_Admin_Forms'     => '/wp-admin-forms.php',
            );

        foreach ($classes as $classname => $path) {
            if( ! class_exists($classname) ) {
                require_once self::get_plugin_dir('classes') . $path;
            }
        }

        // includes
        require_once __DIR__ . '/includes/admin-page.php';
        require_once __DIR__ . '/includes/compile.php';
    }

    public static function get_plugin_dir( $path = false )
    {
        $result = __DIR__;

        switch ( $path ) {
            case 'classes': $result .= '/includes/classes'; break;
            case 'settings': $result .= '/includes/settings'; break;
            default: $result .= '/' . $path;
        }

        return $result;
    }

    public static function get_plugin_url( $path = false )
    {
        $result = plugins_url(basename(__DIR__) );

        switch ( $path ) {
            default: $result .= '/' . $path;
        }
    }

    /**
     * Получает настройку из self::$settings или из кэша или из базы данных
     */
    public static function get( $prop_name, $default = false )
    {
        if( ! self::$settings )
            self::$settings = get_option( self::SETTINGS, array() );

        if( 'all' === $prop_name ) {
            if( is_array(self::$settings) && count(self::$settings) )
                return $this->settings;

            return $default;
        }

        return isset( self::$settings[ $prop_name ] ) ? self::$settings[ $prop_name ] : $default;
    }

    /**
     * Записываем ошибку
     */
    public static function write_debug( $msg, $dir )
    {
        if( ! defined('WP_DEBUG_LOG') || ! WP_DEBUG_LOG )
            return;

        $dir = str_replace(__DIR__, '', $dir);
        $msg = str_replace(__DIR__, '', $msg);

        $date = new \DateTime();
        $date_str = $date->format(\DateTime::W3C);

        $handle = fopen(__DIR__ . "/debug.log", "a+");
        fwrite($handle, "[{$date_str}] {$msg} ({$dir})\r\n");
        fclose($handle);
    }

    /**
     * Загружаем файл если существует
     */
    public static function load_file_if_exists( $file_array )
    {
        $cant_be_loaded = __('The file %s can not be included', TS_LANG);
        if( is_array( $file_array ) ) {
            $result = array();
            foreach ( $file_array as $id => $path ) {
                if ( ! is_readable( $path ) ) {
                    self::write_debug(sprintf($cant_be_loaded, $path), __FILE__);
                    continue;
                }

                $result[] = include_once( $path );
            }
        }
        else {
            if ( ! is_readable( $file_array ) ) {
                self::write_debug(sprintf($cant_be_loaded, $file_array), __FILE__);
                return false;
            }

            $result = include_once( $file_array );
        }

        return $result;
    }

    static function get_settings( $filename )
    {

        return self::load_file_if_exists( self::get_plugin_dir('settings') . '/' . $filename );
    }

    static function remove_cyrillic($str){
        $pattern = "/[\x{0410}-\x{042F}]+.*[\x{0410}-\x{042F}]+/iu";
        $str = preg_replace( $pattern, "", $str );

        return $str;
    }

    static function enqueue_fonts(){
        $ffaces = '';
        $i = 0;

        foreach (get_theme_mod( 'theme-fonts', array() ) as $font_name) {
            if( $i ) $ffaces .= "|";

            $ffaces .= self::$fonts_list[$font_name]['font-face'] . ":400,700";
            $i++;
        }

        if( $ffaces ) {
            wp_enqueue_style( 'google_fonts',
                'https://fonts.googleapis.com/css?family='.$ffaces.'&subset=cyrillic' );
        }
    }
}

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Utils', 'activate' ) );
register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Utils', 'uninstall' ) );
// register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Utils', 'deactivate' ) );

add_action( 'plugins_loaded', array( __NAMESPACE__ . '\Utils', 'initialize' ), 10 );
