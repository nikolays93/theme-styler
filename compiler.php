<?php
/*
Plugin Name: WordPress компилятор скриптов и стилей
Plugin URI: https://github.com/nikolays93/wp-compiler
Description: Компилирует файлы SCSS (в будущем предполагается компилировать JS)
Version: 1.1b
Author: NikolayS93
Author URI: https://vk.com/nikolays_93
Author EMAIL: nikolayS93@ya.ru
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// TODO: add compile js

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

if(!function_exists('is_wp_debug')){
  function is_wp_debug(){
    if( WP_DEBUG ){
      if( defined('WP_DEBUG_DISPLAY') && false === WP_DEBUG_DISPLAY ){
        return false;
      }
      return true;
    }
    return false;
  }
}

if(!function_exists('remove_cyrillic_filter')){
  function remove_cyrillic_filter($str){
    $pattern = "/[\x{0410}-\x{042F}]+.*[\x{0410}-\x{042F}]+/iu";
    $str = preg_replace( $pattern, "", $str );

    return $str;
  }
}

define('COMPILER_OPT', 'wp-compiler');
define('COMPILER_PLUG_DIR', plugin_dir_path( __FILE__ ) );
define('SCSS_OPTION', 'scss');
define('SCSS_CACHE', 'scss-cache');
define('SCSS_DEFAULT_DIR', get_template_directory() . '/assets/scss/');
define('ASSETS_DEFAULT_DIR', get_template_directory() . '/assets/');

register_activation_hook(__FILE__, function(){
    $defaults = array(
      'scss-auto-compile' => 'on',
      );

    add_option( COMPILER_OPT, $defaults );
});

if(is_admin()){
  require_once COMPILER_PLUG_DIR . '/inc/class-wp-admin-page-render.php';
  require_once COMPILER_PLUG_DIR . '/inc/class-wp-form-render.php';

  $page = new SCSS_COMPILER\WPAdminPageRender( COMPILER_OPT,
  array(
    'parent' => 'options-general.php',
    'title' => __('Настройки компиляции проекта'),
    'menu' => __('Компиляция'),
    ), '_render_page' );
}
if( !is_admin() || isset($_GET['force_scss']) ){
  require_once COMPILER_PLUG_DIR . '/inc/scss.inc.php';

  add_action('wp_enqueue_scripts', 'use_scss', 999 );
}

$options = get_option( COMPILER_OPT );

if(!isset($options['scss-toolbar']))
  add_action( 'admin_bar_menu', 'add_scss_menu', 99 );

if(isset($options['scss-assets-scss'])){
  add_filter( 'SCSS_DIR', 'get_scss_dir', 5 );
  function get_scss_dir($val){
    $opt = get_option( COMPILER_OPT );
    $dir = get_template_directory() . '/' . $opt['scss-assets-scss'];

    return (is_dir($dir)) ? $dir : $val;
  }
}
if(isset($options['scss-assets'])){
  add_filter( 'ASSETS_DIR', 'get_assets_dir', 5 );
  function get_assets_dir($val){
    $opt = get_option( COMPILER_OPT );
    $dir = get_template_directory() . '/' . $opt['scss-assets'];
    
    return (is_dir($dir)) ? $dir : $val;
  }
}

/**
 * has_filters:
 *
 * scss_allow_role ('administrator') - кому разрешено компилировать
 * SCSS_DIR (SCSS_DEFAULT_DIR) - папка с доп. файлами .scss
 * assets_auto_compile (false) - компилировать доп. скрипты\стили
 * ASSETS_DIR (ASSETS_DEFAULT_DIR) - папка с доп. скриптами\стилями
 * scss_debug - принудительный сжатый вывод
 */

/**
 * Compile
 */
function use_scss(){
  $options = get_option( COMPILER_OPT );
  $old_cache = $scss_cache = get_option( SCSS_CACHE );

  /**
   * Attach SCSS
   */
  if( $options['attach_scss'] ){
    if( is_array($scss_cache) ){
      $css_urls = array_keys($scss_cache);
      foreach ($css_urls as $css_url) {
        $ass_fld = ( isset($options['scss-assets-scss']) ) ? $options['scss-assets-scss'] : 'assets/scss/';
        $flr = ( isset($options['scss-assets']) ) ? $options['scss-assets'] : 'assets/';

        if($css_url != '/style.scss'){
          $css_url = str_replace( array($ass_fld, 'scss'), array($flr, 'css'), $css_url);

          wp_enqueue_style( basename($css_url, '.css'), get_template_directory_uri() . $css_url );
        }
      }
    }
  }

  if( !current_user_can( apply_filters( 'scss_allow_role', 'administrator' ) ) ){
    if( isset($_GET['force_scss']) )
      wp_die( 'К сожалению, вам не разрешено компилировать SCSS.' );
    return;
  }
  
  if( !isset($_GET['force_scss']) ){
    if( !isset($options['scss-auto-compile']) )
      return;
  }

  $tpl_dir = get_template_directory();
  /**
   * Find Style SCSS 
   */
  $exists = array();
  $scss_styles = array(
    $tpl_dir . '/style.scss',
    $tpl_dir . '/assets/style.scss',
    apply_filters( 'SCSS_DIR', SCSS_DEFAULT_DIR ) . 'style.scss');

  foreach ($scss_styles as $scss_style) {
    if( file_exists($scss_style) ){
      $exists = array($scss_style);
      break;
    }
  }
  
  /**
   * Find Assets Files
   */
  if( isset($_GET['force_scss']) || apply_filters('assets_auto_compile', false ) ){
    $handle = opendir(apply_filters( 'SCSS_DIR', SCSS_DEFAULT_DIR ));
    while (false !== ($file = readdir( $handle ))){
      if( strpos($file, '.scss') && substr($file, 0, 1) !== '_' ) 
        $exists[] = apply_filters( 'SCSS_DIR', SCSS_DEFAULT_DIR ) . $file;
    }
  }

  if( sizeof($exists) < 1 )
    return;
  
  /**
   * Compile SCSS
   * 
   * @todo : style.min.css
   */
  $compiler_loaded = false;
  foreach ($exists as $exist) {
    $cache_key = str_replace($tpl_dir, '', $exist);

    if ( isset($_GET['force_scss']) || !isset($scss_cache[$cache_key]) || $scss_cache[$cache_key] !== filemtime($exist) ){
      if( $compiler_loaded === false ){
        $scss = new scssc();
        $scss->setImportPaths(function($path) {
          if (!file_exists( apply_filters( 'SCSS_DIR', SCSS_DEFAULT_DIR ) . $path) )
            return null;
          return apply_filters( 'SCSS_DIR', SCSS_DEFAULT_DIR ) . $path;
        });
        if( !is_wp_debug() || apply_filters( 'scss_debug', false ) )
          $scss->setFormatter('scss_formatter_compressed');

        $compiler_loaded = true;
      }

      $compiled = $scss->compile( remove_cyrillic_filter(file_get_contents($exist)) );
     
      if(!empty($compiled)){
        $out_dir = ($exist == $scss_style) ? $tpl_dir . '/' : apply_filters( 'ASSETS_DIR', ASSETS_DEFAULT_DIR );
        $out_file = $out_dir . basename($cache_key, '.scss') . '.css';
        file_put_contents( $out_file, $compiled );
        $scss_cache[$cache_key] = filemtime($exist);
        unset($compiled);
      }
    }
  }

  /**
   * Update Cache
   */
  if( $scss_cache != $old_cache )
    update_option( SCSS_CACHE, $scss_cache );
}

/**
 * Toolbar
 */
function add_scss_menu( $wp_admin_bar ) {
  $args = array(
    'id'    => 'SCSS',
    'title' => 'SCSS',
  );
  $wp_admin_bar->add_node( $args );

  $args = array(
    'id'     => 'force_scss',
    'title'  => 'Компилировать SCSS',
    'parent' => 'SCSS',
    'href' => '?force_scss=1',
  );
  $wp_admin_bar->add_node( $args );

  $args = array(
    'id'     => 'change_scss_dir',
    'title'  => 'Настройки компилирования SCSS',
    'parent' => 'SCSS',
    'href' => '/wp-admin/options-general.php?page=advanced-options&tab=scripts',
  );
  $wp_admin_bar->add_node( $args );
}

/**
 * Admin Page
 */
function _render_page(){
  $data = array(
    array(
      'id' => 'scss-auto-compile',
      'type' => 'checkbox',
      'label' => 'Автокомпиляция',
      'desc' => 'По умолчанию автокомпиляция работает только с style.scss используя кэширование (Не компилируется если файл не изменялся с последней компиляции)',
      ),
    array(
      'id' => 'scss-toolbar',
      'type' => 'checkbox',
      'label' => 'Скрыть пункт меню',
      'desc' => 'Не показывать меню компиляции в верхнем меню WordPress (toolbar\'е)',
      ),
    array(
      'id' => 'scss-assets',
      'type' => 'text',
      'label' => 'Путь к доп. файлам',
      'desc' => 'Папка в активной теме предназначенная для дополнительных файлов стилей. (По умолчанию: assets/) - относительно папки с активной темой',
      'default' => 'assets/',
      ),
    array(
      'id' => 'scss-assets-scss',
      'type' => 'text',
      'label' => 'Путь к файлам SCSS',
      'desc' => 'Папка в активной теме предназначенная для SCSS файлов. (По умолчанию: assets/scss/) - относительно папки с активной темой',
      'default' => 'assets/scss/',
      ),
    array(
      'id' => 'attach_scss',
      'type' => 'checkbox',
      'label' => 'Подключить найденые SCSS',
      'desc' => 'Подключить найденые вышеупомянутые scss файлы',
      ),
    );
    

  SCSS_COMPILER\WPForm::render( apply_filters( 'SCSS_COMPILER\dt_admin_options', $data ),
    SCSS_COMPILER\WPForm::active(COMPILER_OPT, false, true),
    true,
    array('clear_value' => false)
    );

  submit_button();
}