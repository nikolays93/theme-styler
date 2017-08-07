<?php
/*
Plugin Name: WordPress Theme Compiler
Plugin URI: https://github.com/nikolays93/wp-compiler
Description: Компилирует файлы SCSS (в будущем предполагается компилировать JS)
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

class StyleCompiler {
  const SETTINGS = 'compiler';
  const OPTION = 'scss';
  const CACHE = 'cache';

  const SCSS_DEFAULT_DIR = 'assets/scss/';
  const ASSETS_DEFAULT_DIR = 'assets/';

  public static $settings = array();
  public static $dir;
  public static $scss_class;
  public static $style_path;
  public static $scss_cache;

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
    self::load_classes();
    self::$dir = get_template_directory() . '/';
    self::$settings = get_option( self::SETTINGS, array() );
    self::$scss_cache = get_option( self::CACHE, array() );

    add_action('wp_enqueue_scripts', array($this, 'find_and_compile'), 999 );

    if( ! is_admin() ){
      if(empty(self::$settings['disable-nodes']))
        add_action( 'admin_bar_menu', array($this, 'add_scss_menu'), 99 );

      return;
    }

    $page = new WPAdminPageRender(
      self::SETTINGS,
      array(
        'parent' => 'options-general.php',
        'title' => __('Настройки стилей'),
        'menu' => __('Настройки стилей'),
        // 'tab_sections' => array('tab1' => 'title1', 'tab2' => 'title2')
        ),
      array($this, 'admin_settings_page')
      // array(
      //   'tab1' => array($this, 'admin_settings_page'),
      //   'tab2' => array($this, 'admin_settings_page_tab2'),
      //   )
      );
  }

  private static function load_classes(){
    require_once SCSS_DIR . '/inc/class-wp-admin-page-render.php';
    require_once SCSS_DIR . '/inc/class-wp-form-render.php';

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

  function find_style_scss(){
    $tpl_dir = get_template_directory() . '/';
    $custom_style = trim( apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR ), '/') . 'style.scss';

    $styles = array(
      'style.scss'        => self::$dir . 'style.scss',
      'assets/style.scss' => self::$dir . 'assets/style.scss',
      $custom_style       => self::$dir . $custom_style,
      );

    foreach ($styles as $path => $style) {
      if( file_exists($style) ){
        self::$style_path = $path;
        return array( $path );
      }
    }
    return array();
  }

  function find_assets_scss(){
    $assets = array();

    $cuser = wp_get_current_user();
    $is_admin_update = !empty($_GET['update_scss']) && !empty($cuser->caps['administrator']);
    if( $is_admin_update || !empty(self::$settings['assets-compile']) ){
      // Ищем доп. файлы
      if( $handle = @opendir(self::$dir . apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR )) ){
        while (false !== ($file = readdir( $handle ))){
          if( strpos($file, '.scss') && substr($file, 0, 1) !== '_' ) 
            $assets[] = apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR ) . $file;
        }
      }

    }

    return $assets;
  }

  static function scss_class_instance(){
    if( ! self::$scss_class ){
      self::$scss_class = new \scssc();
      self::$scss_class->setImportPaths(function($path) {
        if (!file_exists( self::$dir . apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR ) . $path) )
          return null;

        return self::$dir . apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR ) . $path;
      });

      if( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG )
        self::$scss_class->setFormatter('scss_formatter_compressed');
    }

    return self::$scss_class;
  }

  function compile_scss($finded){
    $old_cache = self::$scss_cache;

    if( ! array($finded) || sizeof($finded) < 1 )
      return;

    foreach ($finded as $path) {
      if(!empty(self::$settings['disable-style-compile']) && self::$style_path == $path)
        continue;

      $need_compile =  ! isset(self::$scss_cache[$path]) || self::$scss_cache[$path] !== filemtime(self::$dir . $path);
      if ( $need_compile ){
        $uncompilde = self::remove_cyrillic(file_get_contents(self::$dir . $path));
        if( $compiled = self::scss_class_instance()->compile( $uncompilde ) ){
          // Сохраняем в корень если это style.css,
          // иначе сохраняем в указанную или дефолтную директорию файлов scss
          $out_dir = ($path == self::$style_path) ? self::$dir :
            self::$dir . apply_filters( 'ASSETS_DIR', self::ASSETS_DEFAULT_DIR );
          // Меняем расширение scss на css
          $out_file = $out_dir . basename($path, '.scss') . '.css';
          // записываем в файл
          if( @file_put_contents( $out_file, $compiled ) === false ){
            if( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG )
              echo 'Не удалось записать файл ' . $out_file;

            self::write_debug( 'Не удалось записать файл', $out_file );
          }

          // определяем кэш для записи
          self::$scss_cache[$path] = filemtime(self::$dir . $path);
        }
      }
    }

    if( self::$scss_cache != $old_cache )
      update_option( self::CACHE, self::$scss_cache );
  }

  function enqueue_compiled_styles(){
    if( isset(self::$scss_cache) && is_array(self::$scss_cache) ){
      foreach (self::$scss_cache as $path => $ver) {

        $ass_fld = ( !empty(self::$settings['assets-scss-path']) ) ?
          self::$settings['assets-scss-path'] : self::SCSS_DEFAULT_DIR;
        $flr = ( !empty(self::$settings['assets-path']) ) ?
          self::$settings['assets-path'] : self::ASSETS_DEFAULT_DIR;

        // Если не стоит пункт подключить style.css
        if($path == self::$style_path && empty(self::$settings['enqueue-compiled-style']) )
          continue;

        // Если не стоит пункт подключить доп. файлы
        if($path != self::$style_path && empty(self::$settings['enqueue-assets-style']) )
          continue;

        $path = str_replace( array($ass_fld, 'scss'), array($flr, 'css'), $path);
        wp_enqueue_style( basename($path, '.css'), get_template_directory_uri() . '/' . $path, false, $ver );
      }
    }
  }

  static function is_allow_compile(){
    $cuser = wp_get_current_user();
    if( isset($cuser->caps['administrator']) && $cuser->caps['administrator'] == true )
      return true;

    if(!empty($_GET['update_scss']) && !empty($_GET['pwd'])){
      if( ! empty(self::$settings['disallow-compile-url']) )
        return false;

      if( ! empty(self::$settings['compile-url-password']) ){
        if( self::$settings['compile-url-password'] == $_GET['pwd'])
          return true;
      }
      else {
        $_user = get_user_by( 'ID', apply_filters('scss_admin_id', 1) );
        if ( ! is_wp_error( wp_authenticate($_user->user_login, $_GET['pwd']) ) )
          return true;
      }
    }
    return false;
  }

  function find_and_compile(){
    if( ! self::is_allow_compile() )
      return;

    $finded = array_merge( $this->find_style_scss(), $this->find_assets_scss() );
    $this->compile_scss($finded);
    $this->enqueue_compiled_styles();
    // echo "<pre>";
    // var_dump($exists);
    // echo "</pre>";
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
      'href' => get_home_url() . '?update_scss=1',
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

  function admin_settings_page(){
    $data = array(
      array(
        'id' => 'enqueue-compiled-style',
        'type' => 'checkbox',
        'label' => 'Подключить файл стилей',
        'desc' => 'Подключить заранее скомпилированный файл style.css',
        ),
      array(
        'id' => 'assets-compile',
        'type' => 'checkbox',
        'label' => 'Проверять изменения стилей',
        'desc' => 'Проверять изменения в доп. файлах (На время разработки шаблона)',
        ),
      array(
        'id' => 'enqueue-assets-style',
        'type' => 'checkbox',
        'label' => 'Подключить доп. стили',
        'desc' => 'Подключить дополнительные стили из ниже указаной директории (Если предыдущий пункт выключен будет подключать только закэшированные данные)',
        ),
      array(
        'id' => 'disallow-compile-url',
        'type' => 'checkbox',
        'label' => 'Запретить прямую ссылку',
        'desc' => 'Запретить доступ к адресу компиляции с паролем',
        ),
      array(
        'id' => 'disable-nodes',
        'type' => 'checkbox',
        'label' => 'Не показывать пункт SCSS',
        'desc' => 'Не показывать пункт SCSS в админ баре (сверху) ',
        ),
      array(
        'id' => 'compile-url-password',
        'type' => 'text',
        'label' => 'Использовать пароль',
        'desc' => 'Оставьте пустым чтобы разрешить пользоваться паролем администратора.<br>Компиляция будет доступна по адресу: <a href="'.get_home_url().'/update_scss=1&pwd=">'.get_home_url().'/update_scss=1&pwd=*</a>',
        ),
      /**
       * @todo : test it (assets-path, assets-scss-path)
       */
      array(
        'id' => 'assets-path',
        'type' => 'text',
        'label' => 'Путь к доп. файлам',
        'desc' => 'Папка в активной теме предназначенная для дополнительных файлов стилей. (По умолчанию: assets/) - относительно папки с активной темой',
        'default' => 'assets/',
        ),
      array(
        'id' => 'assets-scss-path',
        'type' => 'text',
        'label' => 'Путь к файлам SCSS',
        'desc' => 'Папка в активной теме предназначенная для SCSS файлов. (По умолчанию: assets/scss/) - относительно папки с активной темой',
        'default' => 'assets/scss/',
        ),
      array(
        'id' => 'disable-style-compile',
        'type' => 'checkbox',
        'label' => 'Не компилировать style.scss',
        'desc' => 'не компилировать файл style.scss. <br><small>Если не выбрано файл будет найден в корневом каталоге темы, в папке "assets" или фильтром "SCSS_DIR" который по умолчанию указывает на "assets/scss" и скомпилирован</small>',
        ),
      );

    WPForm::render( $data, WPForm::active(self::SETTINGS, false, true), true,
      array('admin_page' => self::SETTINGS)
      );

    submit_button();
  }

  // function admin_settings_page_tab2(){
  //   echo "Page 2";

  //   submit_button( __('Save') );
  // }
}

add_action( 'plugins_loaded', array('SCSS\StyleCompiler', 'get_instance') );
register_activation_hook( __FILE__, array( 'SCSS\StyleCompiler', 'activate' ) );
register_uninstall_hook( __FILE__, array( 'SCSS\StyleCompiler', 'uninstall' ) );