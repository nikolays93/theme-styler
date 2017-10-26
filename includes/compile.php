<?php
namespace CDevelopers\style;


// check if is allow compile
// find files
// find difference
// compile
// enqueue_compiled_styles

// class ThemeCompiler
// {
//     const CACHE = '_scss';

//     private static $scssc;
//     static $relative;
//     static $is_force;

//     private $cache;

//     function __construct()
//     {

//         self::$is_force = isset( $_GET[ apply_filters('compile_force_name', 'update_scss' ) ] );
//     }

//     static function admin_notice_styles_updated() {
//         echo '<div class="notice notice-success is-dismissible">';
//         echo sprintf('<p>%s</p>', __('SCSS Styles updated.', LANG)); // Стили успешно обновлены!
//         echo '</div>';
//     }


//     function enqueue()
//     {
//     }

//     function compile_scss($finded)
//     {
//         $old_cache = self::$scss_cache;

//         if( ! array($finded) || sizeof($finded) < 1 )
//             return;

//         foreach ($finded as $path) {
//             if(!empty(parent::$settings['disable-style-compile']) && self::$style_path == $path)
//                 continue;

//                 $need_compile = ! isset(self::$scss_cache[$path]) ||
//                 self::$scss_cache[$path] !== filemtime(parent::$dir . $path);

//                 if ( $need_compile ){
//                     $uncompilde = parent::remove_cyrillic(file_get_contents(parent::$dir . $path));
//                     if( $compiled = self::scss_class_instance()->compile( $uncompilde ) ){
//                         // Сохраняем в корень если это style.css,
//                         // иначе сохраняем в указанную или дефолтную директорию файлов scss
//                         $out_dir = ($path == self::$style_path) ? parent::$dir :
//                         parent::$dir . apply_filters( 'ASSETS_DIR', self::ASSETS_DEFAULT_DIR );
//                         // Меняем расширение scss на css
//                         $out_file = $out_dir . basename($path, '.scss') . '.css';
//                         // записываем в файл
//                         if( @file_put_contents( $out_file, $compiled ) === false ){
//                             if( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG )
//                                 echo 'Не удалось записать файл ' . $out_file;

//                             parent::write_debug( 'Не удалось записать файл', $out_file );
//                         }

//                         // определяем кэш для записи
//                         self::$scss_cache[$path] = filemtime(parent::$dir . $path);
//                     }
//                 }
//             }

//             if( self::$scss_cache != $old_cache )
//                 update_option( self::CACHE, self::$scss_cache );
//     }

//     function enqueue_compiled_styles()
//     {
//         if( isset(self::$scss_cache) && is_array(self::$scss_cache) ){
//             foreach (self::$scss_cache as $path => $ver) {

//                 $ass_fld = ( !empty(parent::$settings['assets-scss-path']) ) ?
//                 parent::$settings['assets-scss-path'] : self::SCSS_DEFAULT_DIR;
//                 $flr = ( !empty(parent::$settings['assets-path']) ) ?
//                 parent::$settings['assets-path'] : self::ASSETS_DEFAULT_DIR;

//                 // Если не стоит пункт подключить style.css
//                 if($path == self::$style_path && empty(parent::$settings['enqueue-compiled-style']) )
//                     continue;

//                 // Если не стоит пункт подключить доп. файлы
//                     if($path != self::$style_path && empty(parent::$settings['enqueue-assets-style']) )
//                         continue;

//                         $path = str_replace( array($ass_fld, 'scss'), array($flr, 'css'), $path);
//                         wp_enqueue_style( basename($path, '.css'), get_template_directory_uri() . '/' . $path, false, $ver );
//                     }
//                 }
//     }
// }

// $ThemeCompiler = new ThemeCompiler();
// if( (! is_admin() || is_admin() && ! empty($_GET['update_scss'])) && $ThemeCompiler->is_allow() ) {
//     if( $ThemeCompiler->check_difference() ) {
//     }

//     $patches = $ThemeCompiler->find_styles( Utils::get_plugin_dir('assets') );
//     var_dump( $patches );
//     return;
//     $scssc = $ThemeCompiler->get_compiler_instance();

//     foreach ($patches as $path) {
//         $scssc->setImportPaths( array(dirname($path)) );
//         file_put_contents(
//             dirname($path) . '/' . basename($path, '.scss') . '.css',
//             $scssc->compile( file_get_contents( $path ) ) );
//     }

// }

// if( $ThemeCompiler::$is_force ) {
//     $actual_link  = isset($_SERVER['HTTPS']) ? "https" : "http";
//     $actual_link .= "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
//     if( self::$is_force && wp_redirect( str_replace('update_scss=1', 'scss_updated=1', $actual_link) ) ) {
//         exit;
//     }
// }

class compile
{
    const CACHE = 'scss';

    public $arrFiles;
    static $scssc, $is_force;

    function __construct()
    {
        self::$is_force = isset( $_GET[ apply_filters('compile_force_name', 'update_scss' ) ] );
    }

    private function get_compiler_instance()
    {
        if( ! $scssc = self::$scssc ) {
            $scssc = new \scssc();

            if( ! defined('SCRIPT_DEBUG') || ! SCRIPT_DEBUG ) {
                $scssc->setFormatter('scss_formatter_compressed');
            }
        }

        return $scssc;
    }

    public function is_allow()
    {
        if( current_user_can( apply_filters( 'compile_capability', 'administrator' ) ) ) {
            return true;
        }

        if( self::$is_force ) {
            return ( ! empty( $_GET['pwd'] ) && $_GET['pwd'] == Utils::get('compile-url-password') );
        }

        return false;
    }

    function get_finded_patches()
    {
        return is_array($this->arrFiles) ? $this->arrFiles : array();
    }

    public function set_patches($path, $arrFiles = array())
    {
        if(is_file( $path )){
            $this->arrFiles[ str_replace(ABSPATH, '', $path) ] = filemtime( $path );
            return;
        }

        $dh = opendir($path);
        while ( false !== ($file = readdir($dh)) ) {
            if ($file == '.' || $file == '..') continue;

            $file = realpath($path . '/' . basename($file));
            $info = pathinfo($file);

            if ( isset($info['extension'])
                && in_array($info['extension'], array('scss', 'sass'))
                && 0 !== strpos($info['filename'], '_') ) {
                $this->arrFiles[ str_replace(ABSPATH, '', $file) ] = filemtime( $file );
            }
            elseif ( $file != '.' && $file != '..' && is_dir($file) ) {
                self::set_patches($file, $arrFiles);
            }
        }
        closedir($dh);
        return $arrFiles;
    }

    function check_difference($path, $old_cache)
    {
        return !isset( $old_cache[ $path ] ) || $old_cache[ $path ] !== filemtime(ABSPATH . $path);
    }

    function update()
    {
        $scssc = $this->get_compiler_instance();

        if( ! is_array($this->arrFiles) )
            return;

        // echo "<pre>";
        $old_cache = get_option(self::CACHE);

        foreach ($this->arrFiles as $file => $time) {
            $path = ABSPATH . $file;

            if( $this->check_difference($file, $old_cache) ) {
                $scssc->setImportPaths( array(dirname($path)) );
                $writed = file_put_contents( dirname($path) . '/' . basename($path, '.scss') . '.css',
                    $scssc->compile( file_get_contents( $path ) ) );

                $this->arrFiles[ $file ] = filemtime($path);
            }
        }

        update_option(self::CACHE, $this->arrFiles);
    }

    function enqueue()
    {
        if( ! is_array($this->arrFiles) )
            return;

        foreach ($this->arrFiles as $file => $time) {
            wp_enqueue_style( basename($file, '.scss'), '/' . str_replace('.scss', '.css', $file), array(), $time );
        }
    }
}


function compile_styles(){
    $compile = new Compile();
    if( $compile->is_allow() ) {
        $compile->set_patches( get_template_directory() . '/style.scss' );
        $compile->set_patches( Utils::get_plugin_dir('assets') );

        $compile->update();
        $compile->enqueue();
    }
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\compile_styles' );