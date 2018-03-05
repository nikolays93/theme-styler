<?php

namespace CDevelopers\SCSS;

class ThemeCompile
{
    private $arrFiles = array();
    private static $scssc;
    private $scss_content;

    function __construct(){}

    /**
     * Получаем инструмент для компиляции scss файлов
     * @url http://leafo.github.io/scssphp/
     */
    private function get_scssc_instance()
    {
        if( ! self::$scssc ) {
            self::$scssc = new \Leafo\ScssPhp\Compiler();
            self::$scssc->setImportPaths( get_template_directory() );

            if( ! defined('SCRIPT_DEBUG') || ! SCRIPT_DEBUG ) {
                self::$scssc->setFormatter('scss_formatter_compressed');
            }
        }

        return self::$scssc;
    }

    /**
     * Проверяет подходит ли файл по расширению и названию
     * @param  array $info Информация о файле @see pathinfo()
     * @return bool
     */
    static function valid_scss_filename( $info )
    {
        return  isset($info['extension']) &&
                in_array($info['extension'], array('scss', 'sass')) &&
                0 !== strpos($info['filename'], '_');
    }

    /**
     * Проверяет разрешено ли компилировать
     * @return bool
     */
    public function is_allow()
    {
        if( 'always' === Utils::get( 'check_changes' ) ) {
            return true;
        }

        if( 'loggedin' === Utils::get( 'check_changes' ) ) {
            if( current_user_can( apply_filters( 'theme-scss-compile-capability',
                Utils::get( 'permissions', 'administrator' ) ) ) )
            return true;
        }

        $update_key = apply_filters('theme-scss-compile-var-password', 'update' );
        return !empty( $_GET[ $update_key ] ) && $_GET[ $update_key ] == Utils::get('compile-url-password');
    }

    /**
     * Ищет файлы в указанной директории
     * @param string $path  Директория, так же может принимать путь к файлу
     */
    public function set_patch($path)
    {
        // Если в $patch сразу передан 1 файл, отправляем его в массив файлов и выходим из рекурсии
        if( ! is_readable($path) )
            return false;

        if( is_file($path) ) {
            $this->arrFiles[ str_replace(get_template_directory(), '', $path) ] = filemtime( $path );
        }

        if( is_dir($path) ) {
            $dh = opendir($path);
            while ( false !== ($file = readdir($dh)) ) {
                if ( in_array($file, ['.', '..']) ) continue;

                $file = realpath($path . '/' . basename($file));
                $info = pathinfo($file);

                if( self::valid_scss_filename( $info ) ) {
                    $this->arrFiles[ str_replace(get_template_directory(), '', $path) ] = filemtime( $path );
                }
                elseif( $file != '.' && $file != '..' && is_dir($file) ) {
                    $this->set_patch($file);
                }
            }
            closedir($dh);
        }
    }

    function is_changed() {
        $files = Utils::get('stylemtime', false);
        if( !is_array($files) )
            return true;

        foreach ($files as $file => $filemtime) {
            if( $file == '/style.css' ) continue;
            $filename = get_template_directory() . $file;

            if( !is_file($filename) || $filemtime != filemtime( $filename ) )
                return true;
        }

        return false;
    }

    function update()
    {
        if( ! self::is_changed() )
            return false;

        $scssc = $this->get_scssc_instance();

        /**
         * @todo varname refactoring
         */
        $stylesheet_path = Utils::get_stylesheet_path();
        $files = Utils::get('stylemtime');
        if( is_file( $stylesheet_path ) &&
            isset($files['/style.css']) &&
            filemtime( $stylesheet_path ) > $files['/style.css'] )
        {
            Utils::write_debug( __('style fixed by the user'), __FILE__ );
            return false;
        }

        foreach ($this->arrFiles as $file => $filemtime) {
            $this->scss_content .= file_get_contents( get_template_directory() . $file );
        }

        $content = apply_filters( 'scss-content-filter', $this->scss_content );
        file_put_contents($stylesheet_path, $scssc->compile($content));

        $this->arrFiles[ '/style.css' ] = filemtime( $stylesheet_path );
        Utils::set('stylemtime', $this->arrFiles );
    }
}

// add_action('admin_bar_menu', 'add_update_style_button', 999);
// function add_update_style_button( $wp_admin_bar ) {
//     $wp_admin_bar->add_node( array(
//         'id'    => 'scss-update',
//         'title' => __('Обновить стили'),
//         'href'  => add_query_arg( 'scss_upd', 1 ),
//         'meta'  => array(
//             'title' => 'Скомпилировать css стили из scss',
//         ),
//     ) );
// }

// for ex.
// $tc = new ThemeCompile();
// if( $tc->is_allow() ) {
// $tc->set_patch( get_template_directory() . '/style.scss' );
// $tc->update();
// }