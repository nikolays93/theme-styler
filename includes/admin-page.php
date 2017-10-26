<?php

namespace CDevelopers\style;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

class Admin_Page
{
    function __construct()
    {
        $page = new WP_Admin_Page( Utils::SETTINGS );
        $page->set_args( array(
            'parent'      => 'options-general.php',
            'title'       => __('Style settings', TS_LANG),
            'menu'        => __('Style settings', TS_LANG),
            'validate'    => array($this, 'validate_options'),
            'permissions' => 'manage_options',
            'tab_sections' => array(
                'tab1' => __( 'Fonts', TS_LANG ), // 'Шрифты',
                'tab2' => __( 'Compile', TS_LANG ), // Компиляция
            ),
            'callback' => array(
                'tab1' => array($this, 'fonts_tab'),
                'tab2' => array($this, 'compile_tab'),
            ),
            'columns'     => 1,
            ) );
    }

    function compile_tab()
    {
        $data = array(
            // array(
            //     'id' => 'enqueue-assets-style',
            //     'type' => 'checkbox',
            //     'label' => 'Подключить доп. стили',
            //     'desc' => 'Подключить дополнительные стили из ниже указаной директории (Если предыдущий пункт выключен будет подключать только закэшированные данные)',
            //     ),
            array(
                'id' => 'compile-url-password',
                'type' => 'text',
                'label' => 'Использовать пароль',
                'desc' => 'Оставьте пустым чтобы разрешить пользоваться паролем администратора.<br>Компиляция будет доступна по адресу: <a href="'.get_home_url().'/update_scss=1&pwd=">'.get_home_url().'/'.apply_filters('compile_force_name', 'update_scss' ).'=1&pwd=*</a>',
                ),
            // array(
            //     'id' => 'assets-path',
            //     'type' => 'text',
            //     'label' => 'Путь к доп. файлам',
            //     'desc' => 'Папка в активной теме предназначенная для дополнительных файлов стилей. (По умолчанию: assets/) - относительно папки с активной темой',
            //     'default' => 'assets/',
            //     ),
            // array(
            //     'id' => 'assets-scss-path',
            //     'type' => 'text',
            //     'label' => 'Путь к файлам SCSS',
            //     'desc' => 'Папка в активной теме предназначенная для SCSS файлов. (По умолчанию: assets/scss/) - относительно папки с активной темой',
            //     'default' => 'assets/scss/',
            //     ),
            array(
                'id' => 'disable-style-compile',
                'type' => 'checkbox',
                'label' => 'Не компилировать style.scss',
                'desc' => 'не компилировать файл style.scss. <br><small>Если не выбрано файл будет найден в корневом каталоге темы, в папке "assets" или фильтром "SCSS_DIR" который по умолчанию указывает на "assets/scss" и скомпилирован</small>',
                ),
            );


        $form = new WP_Admin_Forms( $data, $is_table = true, $args = array() );
        echo $form->render();

        submit_button();
    }

    function fonts_tab()
    {
        echo "В разработке";
        return '';
        $active = array('theme-fonts' => Utils::get('theme-fonts', array()) );

        $gfonts = array();
        $allfonts = array('' => 'Системный шрифт');
        $fonts_list = Utils::get_settings( 'fonts-list.php' );
        foreach ($fonts_list as $id => $face) {
            if( isset($face['font-face']) ){
                $gfonts[$id] = str_replace('+', ' ', $face['font-face']);
                if( in_array($id, $active['theme-fonts']) )
                    $allfonts[$id] = str_replace('+', ' ', $face['font-face']);
            }
            else {
                $allfonts[$id] = $id;
            }
        }

        $data = array(
            'id' => ' ',
            'type' => 'select',
            'label' => 'Подключить внешние шрифты',
            'options' => $gfonts,
            'custom_attributes' => array(
                'multiple' => 'multiple',
                'size' => '10',
                )
        );

        $form = new WP_Admin_Forms( $data, $is_table = true, $args = array(
            'sub_name'    => 'theme-fonts',
        ) );

        echo $form->render();
        /*
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
        */

        submit_button();
    }

    function validate_options( $inputs ) {
        $inputs = WP_Admin_Page::array_map_recursive( 'sanitize_text_field', $inputs );
        $inputs = WP_Admin_Page::array_filter_recursive($inputs);

        if( isset($inputs['theme-fonts']) ) {
            set_theme_mod( 'theme-fonts', $inputs['theme-fonts'] );
        }
        else {
            remove_theme_mod( 'theme-fonts' );
        }

        return $inputs;
    }
}
new Admin_Page();
