<?php
namespace CDevelopers\compile;


// check if is allow compile
// find files
// find difference
// compile
// enqueue_compiled_styles

class ThemeCompiler
{
    const CACHE = '_scss';

    private static $scssc;
    static $relative;
    static $is_force;

    function __construct()
    {
        self::$is_force = isset( $_GET[ apply_filters('compile_force_name', 'update_scss' ) ] );
    }

    static function admin_notice_styles_updated() {
        echo '<div class="notice notice-success is-dismissible">';
        echo sprintf('<p>%s</p>', __('SCSS Styles updated.', LANG)); // Стили успешно обновлены!
        echo '</div>';
    }

    function get_compiler_instance()
    {
        if( ! $scssc = self::$scssc ) {
            $scssc = new \scssc();

            if( ! defined('SCRIPT_DEBUG') || ! SCRIPT_DEBUG ) {
                $scssc->setFormatter('scss_formatter_compressed');
            }
        }

        return $scssc;
    }

    function is_allow()
    {
        if( current_user_can( apply_filters( 'compile_capability', 'administrator' ) ) ) {
            return true;
        }

        if( ! empty( $_GET['update_scss'] ) && ! empty( $_GET['pwd'] ) ) {
            $utils = Utils::get_instance();
            if( ! $utils->get( 'disallow-compile-url' ) ) {
                if( $pwd = $utils->get('compile-url-password') ){
                    if( $pwd === $_GET['pwd'] )
                        return true;
                }
            // else {
            //     $_user = get_user_by( 'ID', apply_filters('scss_admin_id', 1) );
            //     if ( ! is_wp_error( wp_authenticate($_user->user_login, $_GET['pwd']) ) )
            //         return true;
            // }
            }
        }

        return false;
    }

    function get_pathes_cache()
    {
        if( ! $this->cache )
            $this->cache = get_option( self::CACHE );

        return $this->cache;
    }

    function find_styles()
    {
        // if( self::$is_force || ! $paths = get_paths_cache() ) {
        // }

        // return $paths;
        $result = array();

        $dir = Utils::get_plugin_dir('assets');
        $dh = opendir( $dir );
        while ( false !== ($file = readdir($dh)) ) {
            if ($file == '.' || $file == '..') continue;

            $file = realpath( $dir . '/assets/' .basename($file) );
            $info = pathinfo( $file );

            if ( isset($info['extension']) && $info['extension'] == 'scss' && substr($info['filename'], 0, 1) !== '_' ) {
                self::$relative = $info['dirname'];
                $result[] = $file;
            }
        }
        closedir($dh);
        return $result;
    }

    function check_difference()
    {
    }

    function enqueue()
    {
    }

    function compile_scss($finded)
    {
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

    function enqueue_compiled_styles()
    {
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
}

$ThemeCompiler = new ThemeCompiler();
if( (! is_admin() || is_admin() && ! empty($_GET['update_scss'])) && $ThemeCompiler->is_allow() ) {
    if( $ThemeCompiler->check_difference() ) {
    }

    $patches = $ThemeCompiler->find_styles();
    $scssc = $ThemeCompiler->get_compiler_instance();

    foreach ($patches as $path) {
        $scssc->setImportPaths( array(dirname($path)) );
        file_put_contents(
            dirname($path) . '/' . basename($path, '.scss') . '.css',
            $scssc->compile( file_get_contents( $path ) ) );
    }

    $scssc->addImportPath( get_template_directory() );
    $style = $scssc->compile( file_get_contents( get_template_directory() . '/style.scss' ) );
    file_put_contents( get_template_directory() . '/style.css', $style );
}

if( $ThemeCompiler::$is_force ) {
    $actual_link  = isset($_SERVER['HTTPS']) ? "https" : "http";
    $actual_link .= "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    if( self::$is_force && wp_redirect( str_replace('update_scss=1', 'scss_updated=1', $actual_link) ) ) {
        exit;
    }
}
