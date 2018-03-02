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
        $stylemtime = filemtime( get_template_directory() . '/style.css' );
        if( Utils::get('stylemtime') && $stylemtime >= Utils::get('stylemtime') ) {
            if( $stylemtime > Utils::get('stylemtime') )
                Utils::write_debug( __('style fixed by the user'), __FILE__ );

            return false;
        }


        if( 'always' === Utils::get( 'check_changes' ) ) {
            return true;
        }

        if( current_user_can( apply_filters( 'theme-scss-compile-capability',
            Utils::get( 'permissions', 'administrator' ) ) ) ) {
            return true;
        }

        $var_update = apply_filters('theme-scss-compile-var-password', 'update' );
        return !empty( $_GET[ $var_update ] ) && $_GET[ $var_update ] == Utils::get('compile-url-password');
    }

    /**
     * Ищет файлы в указанной директории
     * @param string $path  Директория, так же может принимать путь к файлу
     */
    public function set_patch($path)
    {
        // Если в $patch сразу передан 1 файл, отправляем его в массив файлов и выходим из рекурсии
        if( is_readable($path) && is_file($path) ) {
            $this->arrFiles[] = str_replace(ABSPATH, '', $path);
        }
        else {
            $dh = opendir($path);
            while ( false !== ($file = readdir($dh)) ) {
                if ( in_array($file, ['.', '..']) ) continue;

                $file = realpath($path . '/' . basename($file));
                $info = pathinfo($file);

                if( self::valid_scss_filename( $info ) ) {
                    $this->arrFiles[] = str_replace(ABSPATH, '', $path);
                }
                elseif( $file != '.' && $file != '..' && is_dir($file) ) {
                    $this->set_patch($file);
                }
            }
            closedir($dh);
        }
    }

    function update()
    {
        $scssc = $this->get_scssc_instance();

        foreach ($this->arrFiles as $file ) {
            $this->scss_content .= file_get_contents( ABSPATH . $file );
        }

        $content = apply_filters( 'scss-content-filter', $this->scss_content );
        file_put_contents(get_template_directory() . '/style.css', $scssc->compile($content));

        Utils::set('stylemtime', filemtime(get_template_directory() . '/style.css') );
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