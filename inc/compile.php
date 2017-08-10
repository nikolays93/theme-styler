<?php
namespace SCSS;

class Compile extends ThemeCompiler {
	const CACHE = 'cache';

	const SCSS_DEFAULT_DIR = 'assets/scss/';
  const ASSETS_DEFAULT_DIR = 'assets/';

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

  private function __construct($foo = null){
  	self::$scss_cache = get_option( self::CACHE, array() );

  	add_action('init', array($this, 'find_and_compile'));
    add_action('admin_init', array($this, 'find_and_compile'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_compiled_styles'), 999 );

    if( ! empty($_GET['scss_updated']) )
      add_action( 'admin_notices', array(__CLASS__, 'admin_notice_styles_updated') );
  }

  static function scss_class_instance(){
    if( ! self::$scss_class ){
      self::$scss_class = new \scssc();
      self::$scss_class->setImportPaths(function($path) {
        if (!file_exists( parent::$dir . apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR ) . $path) )
          return null;

        return parent::$dir . apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR ) . $path;
      });

      if( !defined('SCRIPT_DEBUG') && ! SCRIPT_DEBUG )
        self::$scss_class->setFormatter('scss_formatter_compressed');
    }

    return self::$scss_class;
  }
  static function is_allow_compile(){
    $cuser = wp_get_current_user();
    if( isset($cuser->caps['administrator']) && $cuser->caps['administrator'] == true )
      return true;

    if(!empty($_GET['update_scss']) && !empty($_GET['pwd'])){
      if( ! empty(parent::$settings['disallow-compile-url']) )
        return false;

      if( ! empty(parent::$settings['compile-url-password']) ){
        if( parent::$settings['compile-url-password'] == $_GET['pwd'])
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
  static function admin_notice_styles_updated() {
    ?>
    <div class="notice notice-success is-dismissible">
      <p> Стили успешно обновлены! </p>
    </div>
    <?php
  }

  function find_style_scss(){
    $tpl_dir = get_template_directory() . '/';
    $custom_style = trim( apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR ), '/') . 'style.scss';

    $styles = array(
      'style.scss'        => parent::$dir . 'style.scss',
      'assets/style.scss' => parent::$dir . 'assets/style.scss',
      $custom_style       => parent::$dir . $custom_style,
      );

    foreach ($styles as $path => $style) {
      if( file_exists($style) ){
        self::$style_path = $path;
        return array( $path );
      }
    }
    return array();
  }

  function find_assets_scss($is_force_compile){
    $assets = array();
    if( $is_force_compile || !empty(parent::$settings['assets-compile']) ){
      // Ищем доп. файлы
      if( $handle = @opendir(parent::$dir . apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR )) ){
        while (false !== ($file = readdir( $handle ))){
          if( strpos($file, '.scss') && substr($file, 0, 1) !== '_' )
            $assets[] = apply_filters( 'SCSS_DIR', self::SCSS_DEFAULT_DIR ) . $file;
        }
      }

    }

    return $assets;
  }

  function compile_scss($finded){
    $old_cache = self::$scss_cache;

    if( ! array($finded) || sizeof($finded) < 1 )
      return;

    foreach ($finded as $path) {
      if(!empty(parent::$settings['disable-style-compile']) && self::$style_path == $path)
        continue;

      $need_compile = ! isset(self::$scss_cache[$path]) ||
        self::$scss_cache[$path] !== filemtime(parent::$dir . $path);

      if ( $need_compile ){
        $uncompilde = parent::remove_cyrillic(file_get_contents(parent::$dir . $path));
        if( $compiled = self::scss_class_instance()->compile( $uncompilde ) ){
          // Сохраняем в корень если это style.css,
          // иначе сохраняем в указанную или дефолтную директорию файлов scss
          $out_dir = ($path == self::$style_path) ? parent::$dir :
            parent::$dir . apply_filters( 'ASSETS_DIR', self::ASSETS_DEFAULT_DIR );
          // Меняем расширение scss на css
          $out_file = $out_dir . basename($path, '.scss') . '.css';
          // записываем в файл
          if( @file_put_contents( $out_file, $compiled ) === false ){
            if( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG )
              echo 'Не удалось записать файл ' . $out_file;

            parent::write_debug( 'Не удалось записать файл', $out_file );
          }

          // определяем кэш для записи
          self::$scss_cache[$path] = filemtime(parent::$dir . $path);
        }
      }
    }

    if( self::$scss_cache != $old_cache )
      update_option( self::CACHE, self::$scss_cache );
  }

  function enqueue_compiled_styles(){
    if( isset(self::$scss_cache) && is_array(self::$scss_cache) ){
      foreach (self::$scss_cache as $path => $ver) {

        $ass_fld = ( !empty(parent::$settings['assets-scss-path']) ) ?
          parent::$settings['assets-scss-path'] : self::SCSS_DEFAULT_DIR;
        $flr = ( !empty(parent::$settings['assets-path']) ) ?
          parent::$settings['assets-path'] : self::ASSETS_DEFAULT_DIR;

        // Если не стоит пункт подключить style.css
        if($path == self::$style_path && empty(parent::$settings['enqueue-compiled-style']) )
          continue;

        // Если не стоит пункт подключить доп. файлы
        if($path != self::$style_path && empty(parent::$settings['enqueue-assets-style']) )
          continue;

        $path = str_replace( array($ass_fld, 'scss'), array($flr, 'css'), $path);
        wp_enqueue_style( basename($path, '.css'), get_template_directory_uri() . '/' . $path, false, $ver );
      }
    }
  }

  function find_and_compile(){
    if( ! self::is_allow_compile() )
      return;

    $cuser = wp_get_current_user();
    $is_force_compile = !empty($_GET['update_scss']) && !empty($cuser->caps['administrator']);
    if( $is_force_compile )
      self::$scss_cache = array();

    if( !is_admin() || is_admin() && !empty($_GET['update_scss']) ){
      $finded = array_merge( $this->find_assets_scss($is_force_compile), $this->find_style_scss() );
      $this->compile_scss($finded);
    }

    $actual_link  = isset($_SERVER['HTTPS']) ? "https" : "http";
    $actual_link .= "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    if( $is_force_compile ){
      if( wp_redirect( str_replace('update_scss=1', 'scss_updated=1', $actual_link) ) )
        exit;
    }
  }

  static function admin_settings_page_compile(){
    $data = array(
      array(
        'id' => 'enqueue-compiled-style',
        'type' => 'checkbox',
        'label' => 'Подключить файл стилей',
        'desc' => 'Подключить заранее скомпилированный файл style.css',
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
        'id' => 'assets-compile',
        'type' => 'checkbox',
        'label' => 'Проверять изменения стилей',
        'desc' => 'Проверять изменения в доп. файлах (На время разработки шаблона)',
        ),
      array(
        'id' => 'disable-style-compile',
        'type' => 'checkbox',
        'label' => 'Не компилировать style.scss',
        'desc' => 'не компилировать файл style.scss. <br><small>Если не выбрано файл будет найден в корневом каталоге темы, в папке "assets" или фильтром "SCSS_DIR" который по умолчанию указывает на "assets/scss" и скомпилирован</small>',
        ),
      );

    WPForm::render( $data, WPForm::active(parent::SETTINGS, false, true), true,
      array('admin_page' => parent::SETTINGS)
      );

    submit_button();
  }
}
