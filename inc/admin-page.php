<?php
namespace SCSS_COMPILER;

$page = new WPAdminPageRender( COMPILER_OPT,
  array(
    'parent' => 'options-general.php',
    'title' => __('Настройки компиляции проекта'),
    'menu' => __('Компиляция'),
    ), 'SCSS_COMPILER\_render_page' );

function _render_page(){
	$data = array(
		array(
			'id' => 'scss-auto-compile',
			'type' => 'checkbox',
			'label' => 'Автокомпиляция',
			'desc' => 'По умолчанию автокомпиляция работает только с style.scss используя кэширование (Не компилируется если файл не изменялся с последней компиляции)',
			),
		array(
			'id' => 'scss-toolbar',
			'type' => 'checkbox',
			'label' => 'Скрыть пункт меню',
			'desc' => 'Не показывать меню компиляции в верхнем меню WordPress (toolbar\'е)',
			),
		array(
			'id' => 'scss-assets',
			'type' => 'text',
			'label' => 'Путь к доп. файлам',
			'desc' => 'Папка в активной теме предназначенная для дополнительных файлов стилей. (По умолчанию: /assets/)',
			'default' => '/assets/',
			),
		array(
			'id' => 'scss-assets-scss',
			'type' => 'text',
			'label' => 'Путь к файлам SCSS',
			'desc' => 'Папка в активной теме предназначенная для SCSS файлов. (По умолчанию: /assets/scss/)',
			'default' => '/assets/scss/',
			),
		);
    

	WPForm::render( apply_filters( 'SCSS_COMPILER\dt_admin_options', $data ),
		WPForm::active(COMPILER_OPT, false, true),
		true,
		array('clear_value' => false)
		);

	submit_button();
}
// $args = array(
//       array(
//         'id'        => 'scss-auto-compile',
//         'title'     => 'Автоматически компилировать SCSS',
//         'type'      => 'checkbox',
//         'desc'      => '',
//         'placeholder' => '/'
//         ),
//       array(
//         'scss-add-toolbar-node'
//         ),
//       array(
//         'id' => 'scss-dir',
//         'desc' => '<br> Ищет файл style.scss в указаной папке и сохраняет в корень темы style.css или style.min.css в зависимости от debug параметра <a href="https://codex.wordpress.org/Debugging_in_WordPress" target="_blank">(?)</a><br>Для отключения удалите значение.'
//         ),
//       );
//     $this->register_section('dp-scss', 'Главная', '', $args);
