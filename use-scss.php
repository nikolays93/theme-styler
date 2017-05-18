<?php
/*
Plugin Name: undefined
Plugin URI:
Description: Плагин добавляет новые возможности в WordPress.
Version: 
Author: NikolayS93
Author URI: https://vk.com/nikolays_93
*/
/*  Copyright 2016  NikolayS93  (email: NikolayS93@ya.ru)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

// todo: add compile js
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

if(is_admin()){
  require_once COMPILER_PLUG_DIR . '/inc/wp-admin-page-render/class-wp-admin-page-render.php';
  require_once COMPILER_PLUG_DIR . '/inc/wp-form-render/class-wp-form-render.php';
  require_once COMPILER_PLUG_DIR . '/inc/admin-page.php';
}
else {
  require_once COMPILER_PLUG_DIR . '/inc/scss.inc.php';
}

//  - scss-cahce
//  - scss-dir
//  - scss-auto-compile
function use_scss(){
  $options = get_option( COMPILER_OPT );

  if( !current_user_can( apply_filters( 'scss_allow_role', 'administrator' ) ) ){
    if( isset($_GET['force_scss']) )
      wp_die( 'К сожалению, вам не разрешено компилировать SCSS.' );
    return;
  }
  
  if( isset($options['scss-auto-compile']) || isset($_GET['force_scss']) )
    return;

  $tpl_dir = get_template_directory();
  /**
   * Find Style SCSS 
   */
  $exists = array();
  $scss_styles = array(
    $tpl_dir . '/style.scss',
    $tpl_dir . '/assets/style.scss',
    SCSS_DEFAULT_DIR . 'style.scss');

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
   */
  $compiler_loaded = false;
  $old_cache = $scss_cache = get_option( SCSS_CACHE );
  foreach ($exists as $exist) {
    $cache_key = str_replace($tpl_dir, '', $exist);

    if ( !isset($scss_cache[$cache_key]) || $scss_cache[$cache_key] !== filemtime($exist) ){
      if( $compiler_loaded === false ){
        $scss = new scssc();
        $scss->setImportPaths(function($path) {
          if (!file_exists( apply_filters( 'SCSS_DIR', SCSS_DEFAULT_DIR ) . $path) )
            return null;
          return apply_filters( 'SCSS_DIR', SCSS_DEFAULT_DIR ) . $path;
        });
        if(!is_wp_debug())
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
add_action('wp_enqueue_scripts', 'use_scss', 999 );

$options = get_option( COMPILER_OPT );

if(!isset($options['scss-toolbar']))
  add_action( 'admin_bar_menu', 'add_scss_menu', 99 );

function add_scss_menu( $wp_admin_bar ) {

  $args = array(
    'id'    => 'SCSS',
    'title' => 'SCSS',
  );
  $wp_admin_bar->add_node( $args );

  $args = array(
    'id'     => 'force_scss',
    'title'  => 'Принудительная компиляция SCSS',
    'parent' => 'SCSS',
    'href' => '?force-scss=1',
  );
  $wp_admin_bar->add_node( $args );

  $args = array(
    'id'     => 'change_scss_dir',
    'title'  => 'Изменить папку style.scss',
    'parent' => 'SCSS',
    'href' => '/wp-admin/options-general.php?page=advanced-options&tab=scripts',
  );
  $wp_admin_bar->add_node( $args );
}