<?php
$args = array(
      array(
        'id'        => 'scss-auto-compile',
        'title'     => 'Автоматически компилировать SCSS',
        'type'      => 'checkbox',
        'desc'      => '',
        'placeholder' => '/'
        ),
      array(
        'scss-add-toolbar-node'
        ),
      array(
        'id' => 'scss-dir',
        'desc' => '<br> Ищет файл style.scss в указаной папке и сохраняет в корень темы style.css или style.min.css в зависимости от debug параметра <a href="https://codex.wordpress.org/Debugging_in_WordPress" target="_blank">(?)</a><br>Для отключения удалите значение.'
        ),
      );
    $this->register_section('dp-scss', 'Главная', '', $args);
