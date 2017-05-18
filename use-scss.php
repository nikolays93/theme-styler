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

function remove_cyrillic_filter($str){
  $pattern = "/[\x{0410}-\x{042F}]+.*[\x{0410}-\x{042F}]+/iu";
  $str = preg_replace( $pattern, "", $str );

  return $str;
}

function use_scss(){
  $suffix = '.min';
  if( is_wp_debug() !== false )
    $suffix = '';

  $options = get_option( DT_PLUGIN_NAME );
  $scss_dir = $options['use-scss'];
  $scss_cache = get_option( 'scss_cache' );

  // from
  $file = get_template_directory() . $scss_dir . 'style.scss';
  // to, suffix maybe has .min
  $out_file = '/style'.$suffix.'.css';
  $role = isset(wp_get_current_user()->roles[0]) ? wp_get_current_user()->roles[0] : '';
  if($role == 'administrator'){
    if( ! file_exists( $file ) )
      return null;

    if ( isset($_GET['force_scss']) || filemtime($file) !== $scss_cache ){
      $scss = new scssc();
      $scss->setImportPaths(function($path) {
        if (!file_exists( apply_filters( 'SCSS_DIR', get_template_directory() . '/assets/' ).$path) )
          return null;
        return apply_filters( 'SCSS_DIR', get_template_directory() . '/assets/' ).$path;
      });

      if(!is_wp_debug())
        $scss->setFormatter('scss_formatter_compressed');

      $compiled = $scss->compile( remove_cyrillic_filter(file_get_contents($file)) );
      if(!empty($compiled)){
        file_put_contents(get_template_directory().$out_file, $compiled );
        update_option( 'scss_cache', filemtime($file) );
        $scss_cache = filemtime($file);
      }
    }
  } // is user admin
}
// add_action('wp_enqueue_scripts', 'use_scss', 999 );

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

// $dir = opendir (__DIR__);
// while (false !== ($file = readdir($dir))) {
//       if (strpos($file, '.scss') ) {
//             var_dump($file);
//       }
// }