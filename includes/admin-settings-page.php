<?php

namespace CDevelopers\SCSS;

if ( ! defined( 'ABSPATH' ) )
    exit; // disable direct access

class AdminSettingsPage
{
    function __construct()
    {
        $page = new WP_Admin_Page( Utils::get_option_name() );
        $page->set_args( array(
            'parent'      => 'options-general.php',
            'title'       => __('Update theme styles', DOMAIN),
            'menu'        => __('Update styles', DOMAIN),
            'callback'    => array($this, 'page_render'),
            // 'validate'    => array($this, 'validate_options'),
            'permissions' => 'manage_options',
            'tab_sections'=> null,
            'columns'     => 1,
            ) );

        $page->set_assets( array($this, '_assets') );

        // $page->add_metabox( 'metabox1', 'metabox1', array($this, 'metabox1_callback'), $position = 'side');
        // $page->add_metabox( 'metabox2', 'metabox2', array($this, 'metabox2_callback'), $position = 'side');
        // $page->set_metaboxes();
    }

    function _assets()
    {
        // wp_enqueue_style();
        // wp_enqueue_script();
    }

    /**
     * Основное содержимое страницы
     *
     * @access
     *     must be public for the WordPress
     */
    function page_render()
    {
        global $wp_roles;

        $data = array(
            array(
                'id'    => 'check_changes',
                'type'  => 'select',
                'label' => __('Check changes', DOMAIN),
                'options' => array(
                    'loggedin' => __('Logged in', DOMAIN),
                    'always'   => __('Always', DOMAIN),
                    ''         => __('Disable', DOMAIN),
                ),
            ),
            array(
                'id'    => 'stylemtime',
                'type'  => 'hidden',
            ),
        );

        if( 'loggedin' === Utils::get( 'check_changes', false ) ) {
            $all_roles = $wp_roles->roles;
            $roles = array();
            foreach ($all_roles as $keyrole => $role) {
                if( ! isset($role['name']) ) continue;
                $roles[ $keyrole ] = __( $role['name'] );
            }

            $data[] = array(
                'id'    => 'permissions',
                'type'  => 'select',
                'label' => __('Permissions'),
                'options' => $roles, // get all permisions
                'desc'  => 'Must have user permissions for check updates',
            );
            $data[] = array(
                'id'    => 'pwd',
                'type'  => 'text',
                'label' => __('Use password'),
                'desc'  => 'Use ?update={password} for update in "Logged in" mode',
            );
        }

        $form = new WP_Admin_Forms( $data, true );
        echo $form->render();

        submit_button( 'Сохранить', 'primary right', 'save_changes' );
        echo '<div class="clear"></div>';

        printf( '<input type="hidden" name="page" value="%s" />', $_REQUEST['page'] );
    }
}
new AdminSettingsPage();
