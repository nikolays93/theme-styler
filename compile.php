<?php
/*
Plugin Name: WordPress Theme Compiler
Plugin URI: https://github.com/nikolays93/wp-compiler
Description: Компилирует файлы SCSS
Version: 1.3 beta
Author: NikolayS93
Author URI: https://vk.com/nikolays_93
Author EMAIL: nikolayS93@ya.ru
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
namespace SCSS;

/**
 * @todo : проверить ошибки фильтра SCSS_DIR
 * @todo : Аддон easy-bootstrap
 */

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

// Использовать пароль администратора с ID = 5 (По умолчанию: 1)
// add_filter('scss_admin_id', function(){ return 5; });

class ThemeCompiler {
  const SETTINGS = 'compiler';
  const OPTION = 'scss';

  public static $settings = array();
  public static $dir;

  /* Singleton Class */
  private function __clone() {}
  private function __wakeup() {}

  private static $instance = null;
  public static function get_instance() {
    if ( ! isset( self::$instance ) )
      self::$instance = new self;

    return self::$instance;
  }

  public static function activate(){ add_option( self::SETTINGS, array() ); }
  public static function uninstall(){ delete_option(self::SETTINGS); }

  private function __construct() {
    define('SCSS_DIR', rtrim( plugin_dir_path( __FILE__ ), '/') );
    self::$dir = get_template_directory() . '/';
    self::$settings = get_option( self::SETTINGS, array() );
    self::load_classes();

    if(empty(self::$settings['disable-nodes']))
      add_action( 'admin_bar_menu', array($this, 'add_scss_menu'), 99 );

    if( ! is_admin() )
      return;

    $this->set_admin_page();
  }

  private static function load_classes(){
    require_once SCSS_DIR . '/inc/class-wp-admin-page-render.php';
    require_once SCSS_DIR . '/inc/class-wp-form-render.php';

    require_once SCSS_DIR . '/inc/fonts.php';
    require_once SCSS_DIR . '/inc/compile.php';

    Fonts::get_instance();
    Compile::get_instance();

    if( ! class_exists('scssc') )
      require_once SCSS_DIR . '/inc/scss.inc.php';
  }

  public static function write_debug($msg, $dir){
    if(!defined('DTOOLS_DEBUG') || !DTOOLS_DEBUG)
      return;

    $dir = str_replace(SCSS_DIR, '', $dir);
    $msg = str_replace(SCSS_DIR, '', $msg);

    $date = new \DateTime();
    $date_str = $date->format(\DateTime::RSS);

    $handle = fopen(SCSS_DIR . "/debug.log", "a+");
    fwrite($handle, "[{$date_str}] {$msg} ({$dir})\r\n");
    fclose($handle);
  }

  static function remove_cyrillic($str){
    $pattern = "/[\x{0410}-\x{042F}]+.*[\x{0410}-\x{042F}]+/iu";
    $str = preg_replace( $pattern, "", $str );

    return $str;
  }

  /**************************************** Admin Functions ***************************************/
  function set_admin_page(){
    $page = new WPAdminPageRender(
      self::SETTINGS,
      array(
        'parent' => 'options-general.php',
        'title' => __('Настройки стилей'),
        'menu' => __('Настройки стилей'),
        'tab_sections' => array('tab1' => 'Шрифты', 'tab2' => 'Компиляция')
        ),
      array(
        'tab1' => array('SCSS\Fonts', 'admin_settings_page_fonts'),
        'tab2' => array('SCSS\Compile', 'admin_settings_page_compile'),
        ),
      self::SETTINGS,
      array($this, 'admin_settings_validate')
      );
  }

  function add_scss_menu( $wp_admin_bar ) {
    $args = array(
      'id'    => 'SCSS',
      'title' => 'SCSS',
      );
    $wp_admin_bar->add_node( $args );

    $args = array(
      'id'     => 'force_scss',
      'title'  => 'Обновить кэш всех стилей',
      'parent' => 'SCSS',
      'href' => '?update_scss=1',
      );
    $wp_admin_bar->add_node( $args );

    $args = array(
      'id'     => 'change_scss_dir',
      'title'  => 'Настройки компилирования SCSS',
      'parent' => 'SCSS',
      'href' => get_home_url() . '/wp-admin/options-general.php?page=' . self::SETTINGS,
      );
    $wp_admin_bar->add_node( $args );
  }

  function admin_settings_validate( $inputs ){
    // $debug = array();
    // $debug['before'] = $inputs;

    // default_filters
    $inputs = array_map_recursive( 'sanitize_text_field', $inputs );
    $inputs = array_filter_recursive($inputs);

    if( isset($inputs['theme-fonts']) )
      set_theme_mod( 'theme-fonts', $inputs['theme-fonts'] );
    else
      remove_theme_mod( 'theme-fonts' );

    // $debug['after'] = $inputs;
    // file_put_contents(__DIR__.'/valid.log', print_r($debug, 1));

    return $inputs;
  }
}

add_action( 'plugins_loaded', array('SCSS\ThemeCompiler', 'get_instance') );
register_activation_hook( __FILE__, array( 'SCSS\ThemeCompiler', 'activate' ) );
register_uninstall_hook( __FILE__, array( 'SCSS\ThemeCompiler', 'uninstall' ) );
