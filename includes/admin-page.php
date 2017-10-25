<?php

namespace CDevelopers\compile;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

class Admin_Page
{
    function __construct()
    {
        $page = new WP_Admin_Page( Utils::SETTINGS );
        $page->set_args( array(
            'parent'      => 'options-general.php',
            'title'       => __('Style settings', LANG),
            'menu'        => __('Style settings', LANG),
            'validate'    => array($this, 'validate_options'),
            'permissions' => 'manage_options',
            'tab_sections' => array(
                'tab1' => __( 'Fonts', LANG ), // 'Шрифты',
                'tab2' => __( 'Compile', LANG ), // Компиляция
            ),
            'callback' => array(
                'tab1' => array(__NAMESPACE__ . '\Fonts', 'fonts_tab'),
                'tab2' => array(__NAMESPACE__ . '\Compile', 'compile_tab'),
            ),
            'columns'     => 2,
            ) );
    }

    /**
     * Основное содержимое страницы
     *
     * @access
     *     must be public for the WordPress
     */
    function page_render() {
    }

    function metabox2_callback() {
        $data = array(
            // id or name - required
            array(
                'id'    => 'example_0',
                'type'  => 'text',
                'label' => 'TextField',
                'desc'  => 'This is example text field',
                ),
             array(
                'id'    => 'example_1',
                'type'  => 'select',
                'label' => 'Select',
                'options' => array(
                    // simples first (not else)
                    'key_option5' => 'option5',
                    'option1' => array(
                        'key_option2' => 'option2',
                        'key_option3' => 'option3',
                        'key_option4' => 'option4'),
                    ),
                ),
            array(
                'id'    => 'example_2',
                'type'  => 'checkbox',
                'label' => 'Checkbox',
                ),
            );

        $form = new WP_Admin_Forms( $data, $is_table = true, $args = array(
            // Defaults:
            // 'admin_page'  => true,
            // 'item_wrap'   => array('<p>', '</p>'),
            // 'form_wrap'   => array('', ''),
            // 'label_tag'   => 'th',
            // 'hide_desc'   => false,
            ) );
        echo $form->render();

        submit_button( 'Сохранить', 'primary right', 'save_changes' );
        echo '<div class="clear"></div>';
    }

    function compile_tab()
    {
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

    function fonts()
    {
        $active = array('theme-fonts' => isset(parent::$settings['theme-fonts']) ?
            parent::$settings['theme-fonts'] : array());

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
            'id' => 'theme-fonts',
            'name' => 'theme-fonts][',
            'type' => 'select',
            'label' => 'Подключить внешние шрифты',
            'options' => $gfonts,
            'multiple' => 'multiple',
            'size' => '10',
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
        $inputs = array_map_recursive( 'sanitize_text_field', $inputs );
        $inputs = array_filter_recursive($inputs);

        if( isset($inputs['theme-fonts']) ) {
            set_theme_mod( 'theme-fonts', $inputs['theme-fonts'] );
        }
        else {
            remove_theme_mod( 'theme-fonts' );
        }
    }
}
new Admin_Page();
