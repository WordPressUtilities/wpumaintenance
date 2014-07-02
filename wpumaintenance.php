<?php

/*
Plugin Name: WPU Maintenance page
Description: Adds a maintenance page for non logged-in users
Version: 0.3
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Contributors: @ScreenFeedFr
*/

class WPUWaitingPage
{
    function __construct() {

        $this->options = array(
            'id' => 'wpumaintenance',
            'opt_id' => 'wpumaintenance_has_maintenance',
            'plugin_publicname' => 'WPU Maintenance',
            'plugin_menutype' => 'options-general.php',
            'plugin_basename' => 'wpumaintenance'
        );

        $this->options['plugin_menuname'] = __('Maintenance mode', $this->options['id']);
        $this->opt_id = $this->options['opt_id'];
        load_plugin_textdomain($this->options['id'], false, dirname(plugin_basename(__FILE__)) . '/lang/');

        if (is_admin()) {
            add_action('admin_menu', array(&$this,
                'set_admin_page'
            ));
            add_action('admin_bar_menu', array(&$this,
                'add_toolbar_menu_items'
            ) , 100);
            add_action('admin_init', array(&$this,
                'content_admin_page_postAction'
            ));
        }

        // Dont launch if in admin, or if user is logged in
        global $pagenow;
        $has_maintenance = get_option($this->opt_id) == '1';
        if (!is_admin() && !is_user_logged_in() && $pagenow != 'wp-login.php' && $has_maintenance) {
            $this->launch_maintenance();
        }
    }

    /**
     * Add menu items to toolbar
     *
     * @param unknown $admin_bar
     */
    function add_toolbar_menu_items($admin_bar) {
        $opt = get_option($this->opt_id);

        $admin_bar->add_menu(array(
            'id' => 'wpu-options-menubar-link',
            'title' => $this->options['plugin_menuname'] . ' : ' . ($opt == '0' ? __('Off', $this->options['id']) : __('On', $this->options['id'])) ,
            'href' => admin_url($this->options['plugin_menutype'] . '?page=' . $this->options['plugin_basename']) ,
            'meta' => array(
                'title' => $this->options['plugin_publicname'],
            ) ,
        ));
    }

    function set_admin_page() {
        add_submenu_page($this->options['plugin_menutype'], $this->options['plugin_publicname'], $this->options['plugin_menuname'], 'manage_options', $this->options['plugin_basename'], array(&$this,
            'content_admin_page'
        ));
    }

    function content_admin_page_postAction() {
        if (isset($_POST[$this->opt_id]) && in_array($_POST[$this->opt_id], array(
            '0',
            '1'
        ))) {
            update_option($this->opt_id, $_POST[$this->opt_id]);
        }
    }

    function content_admin_page() {

        $opt = get_option($this->opt_id);
        echo '<h1>Maintenance mode</h1>';
        echo '<form action="" method="post">';
        echo '<p><label>' . __('Activate maintenance mode : ', $this->options['id']) . '</label>';
        echo '<select name="' . $this->opt_id . '" id="' . $this->opt_id . '">
    <option ' . ($opt == '0' ? 'selected' : '') . ' value="0">' . __('No', $this->options['id']) . '</option>
    <option ' . ($opt == '1' ? 'selected' : '') . ' value="1">' . __('Yes', $this->options['id']) . '</option>
</select></p>';
        echo '<button class="button" type="submit">' . __('Save', $this->options['id']) . '</button></form>';
    }

    function launch_maintenance() {

        // Try to include a HTML file
        $maintenanceFilenames = array(
            'maintenance.html',
            'index.html'
        );
        foreach ($maintenanceFilenames as $filename) {
            $filepath = ABSPATH . '/' . $filename;
            if (file_exists($filepath)) {
                include $filepath;
                die;
            }
        }

        // Or include the default maintenance page
        include dirname(__FILE__) . '/includes/maintenance.php';
        die;
    }
}

add_action('init', 'init_wpuwaitingpage');
function init_wpuwaitingpage() {
    $WPUWaitingPage = new WPUWaitingPage();
}
