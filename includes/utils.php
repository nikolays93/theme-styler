<?php

namespace CDevelopers\compile;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

class Utils {
    const SETTINGS = 'compiler';

    private $settings = array();
    private static $_instance = null;
    private function __construct() {}
    private function __clone() {}

    static function activate() { add_option( self::SETTINGS, array() ); }
    static function uninstall() { delete_option(self::SETTINGS); }

    /**
     * Если вы не знаете что это за класс вам нечего здесь делать
     */
    public static function get_instance()
    {
        if( ! self::$_instance ) {
            self::$_instance = new self();
            self::$_instance->initialize();
        }

        return self::$_instance;
    }

    private function initialize()
    {
        load_plugin_textdomain( '_plugin', false, DIR . '/languages/' );
        $this->settings = get_option( self::SETTINGS, array() );
        self::include_required_classes();

        add_action('wp_enqueue_scripts', array(__NAMESPACE__ . '\Utils', 'enqueue_fonts'), 10 );
        if( ! $this->get('disable-nodes') ){
            add_action( 'admin_bar_menu', array($this, 'add_scss_menu'), 99 );
        }
    }

    private static function include_required_classes()
    {
        $classes = array(
            'scssc'                               => '/scss.inc.php',
            __NAMESPACE__ . '\WP_Admin_Page'      => '/wp-admin-page.php',
            __NAMESPACE__ . '\WP_Admin_Forms'     => '/wp-admin-forms.php',
            );

        foreach ($classes as $classname => $dir) {
            if( ! class_exists($classname) ) {
                require_once DIR_CLASSES . $dir;
            }
        }

        // includes
        require_once DIR . '/includes/admin-page.php';
        // require_once DIR . '/includes/fonts.php';
        require_once DIR . '/includes/compile.php';
    }

    /**
     * Простой способ получить настройку из $this->settings (Если в файле используется лишь один раз)
     */
    public static function _get( $prop_name )
    {
        $self = self::get_instance();
        $self->get( $prop_name );
    }

    /**
     * Получает настройку из $this->settings
     */
    public function get( $prop_name )
    {
        if( 'all' === $prop_name ) {
            if( $this->settings )
                return $this->settings;

            return false;
        }

        return isset( $this->settings[ $prop_name ] ) ? $this->settings[ $prop_name ] : false;
    }

    /**
     * Записываем ошибку
     */
    public static function write_debug( $msg, $dir )
    {
        if( ! defined('WP_DEBUG_LOG') || ! WP_DEBUG_LOG )
            return;

        $dir = str_replace(DIR, '', $dir);
        $msg = str_replace(DIR, '', $msg);

        $date = new \DateTime();
        $date_str = $date->format(\DateTime::W3C);

        $handle = fopen(DIR . "/debug.log", "a+");
        fwrite($handle, "[{$date_str}] {$msg} ({$dir})\r\n");
        fclose($handle);
    }

    /**
     * Загружаем файл если существует
     */
    public static function load_file_if_exists( $file_array )
    {
        $cant_be_loaded = __('The file %s can not be included', LANG);
        if( is_array( $file_array ) ) {
            foreach ( $file_array as $id => $path ) {
                if ( ! is_readable( $path ) ) {
                    self::write_debug(sprintf($cant_be_loaded, $path), __FILE__);
                    continue;
                }

                include_once( $path );
            }
        }
        else {
            if ( ! is_readable( $file_array ) ) {
                self::write_debug(sprintf($cant_be_loaded, $file_array), __FILE__);
                return false;
            }

            include_once( $file_array );
        }
    }

    static function get_settings( $filename )
    {
        return self::load_file_if_exists( DIR . '/includes/settings/' . $filename );
    }

    static function remove_cyrillic($str){
        $pattern = "/[\x{0410}-\x{042F}]+.*[\x{0410}-\x{042F}]+/iu";
        $str = preg_replace( $pattern, "", $str );

        return $str;
    }

    function add_scss_menu( $wp_admin_bar )
    {
        if( ! current_user_can( 'edit_pages' ) ) {
            return;
        }

        $args = array(
            'id'    => 'SCSS',
            'title' => __( 'SCSS', LANG ),
        );
        $wp_admin_bar->add_node( $args );

        $args = array(
            'id'     => 'force_scss',
            'title'  => __('Update SCSS Cache', LANG), //'Обновить кэш всех стилей',
            'parent' => 'SCSS',
            'href' => '?update_scss=1',
        );
        $wp_admin_bar->add_node( $args );

        $args = array(
            'id'     => 'change_scss_dir',
            'title'  => __('SCSS compile settings', LANG), //'Настройки компилирования SCSS',
            'parent' => 'SCSS',
            'href' => get_home_url() . '/wp-admin/options-general.php?page=' . self::SETTINGS,
        );
        $wp_admin_bar->add_node( $args );
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