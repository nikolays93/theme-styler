<?php
namespace SCSS;

class Fonts extends ThemeCompiler {
  public static $fonts_list = array();
  public static $count = 0;

  /* Singleton Class */
  private function __clone() {}
  private function __wakeup() {}

  private static $instance = null;
  public static function get_instance() {
  	if ( ! isset( self::$instance ) )
  		self::$instance = new self;

  	return self::$instance;
  }

  private function __construct(){
  	self::$fonts_list = include SCSS_DIR . '/inc/fonts-list.php';

  	add_action('wp_enqueue_scripts', array($this, 'enqueue_fonts'), 10 );
  }

  function enqueue_fonts(){
    $ffaces = '';
    foreach (get_theme_mod( 'theme-fonts', array() ) as $font_name) {
      if( self::$count != 0 )
        $ffaces .= "|";

      $ffaces .= self::$fonts_list[$font_name]['font-face'] . ":400,700";
      self::$count++;
    }
    if($ffaces)
      wp_enqueue_style( 'google_fonts',
        'https://fonts.googleapis.com/css?family='.$ffaces.'&subset=cyrillic' );
  }

  static function admin_settings_page_fonts(){
    // echo "<pre>";
    // var_dump( self::$fonts_list );
    // echo "</pre>";
    $active = array('theme-fonts' => isset(parent::$settings['theme-fonts']) ?
      parent::$settings['theme-fonts'] : array());

    $gfonts = array();
    $allfonts = array('' => 'Системный шрифт');

    foreach (self::$fonts_list as $id => $face) {
      if( isset($face['font-face']) ){
        $gfonts[$id] = str_replace('+', ' ', $face['font-face']);
        if( in_array($id, $active['theme-fonts']) )
          $allfonts[$id] = str_replace('+', ' ', $face['font-face']);
      }
      else {
        $allfonts[$id] = $id;
      }

    }

    WPForm::render(
      array(
        'id' => 'theme-fonts',
        'name' => 'theme-fonts][',
        'type' => 'select',
        'label' => 'Подключить внешние шрифты',
        'options' => $gfonts,
        'multiple' => 'multiple',
        'size' => '10',
        ),
      $active,
      true,
      array('admin_page' => parent::SETTINGS)
      );
    WPForm::render(
      array(
        array(
          'id' => 'theme][body][family',
          'type' => 'select',
          'label' => 'Основной шрифт',
          'options' => $allfonts,
          'desc' => 'body'
          ),
        array(
          'id' => 'theme][headings][family',
          'type' => 'select',
          'label' => 'Заголовки',
          'options' => $allfonts,
          'desc' => 'h1, h2, h3, h4, h5, h6'
          ),
        array(
          'id' => 'theme][primary][family',
          'type' => 'select',
          'label' => 'Главный текст',
          'options' => $allfonts,
          'desc' => '.text-primary',
          ),
        array(
          'id' => 'theme][secondary][family',
          'type' => 'select',
          'label' => 'Второстепенный текст',
          'options' => $allfonts,
          'desc' => '.text-secondary',
          ),
        ),
      WPForm::active(parent::$settings, false, parent::SETTINGS),
      true,
      array('admin_page' => parent::SETTINGS)
      );

    submit_button();
  }
}
