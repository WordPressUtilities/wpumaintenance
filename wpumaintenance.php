<?php

/*
Plugin Name: WPU Maintenance page
Description: Adds a maintenance page for non logged-in users
Version: 0.5
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Contributors: @ScreenFeedFr
*/

class WPUWaitingPage {
    function __construct() {

        $this->options = array(
            'id' => 'wpumaintenance',
            'opt_id' => 'wpumaintenance_has_maintenance',
            'plugin_publicname' => 'WPU Maintenance',
            'plugin_menutype' => 'options-general.php',
            'plugin_basename' => 'wpumaintenance'
        );

        load_plugin_textdomain($this->options['id'], false, dirname(plugin_basename(__FILE__)) . '/lang/');

        $this->options['plugin_menuname'] = __('Maintenance mode', $this->options['id']);
        $this->opt_id = $this->options['opt_id'];

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

        if ($this->has_maintenance()) {
            $this->launch_maintenance();
        }
    }

    /**
     * Check if has maintenance
     * @return boolean has maintenance
     */
    function has_maintenance() {
        global $pagenow;
        if (get_option($this->opt_id) != '1') {
            return false;
        }

        // Dont if user is logged in
        if (is_user_logged_in()) {
            return false;
        }

        // Dont launch if in admin
        if (is_admin()) {
            return false;
        }

        // Dont launch if login page
        if ($pagenow == 'wp-login.php') {
            return false;
        }

        // Check authorized ips
        $opt_ips = get_option($this->opt_id . '-authorized-ips');
        $opt_ips = str_replace(array(
            ' '
        ) , '', $opt_ips);
        $authorized_ips = explode(',', $opt_ips);

        // If no IPs are authorized : maintenance
        if (empty($authorized_ips) || $authorized_ips[0] == '') {
            return true;
        }

        $my_ip = $this->get_ip();
        return !in_array($my_ip, $authorized_ips);
    }

    /**
     * Get IP Address
     *
     * @return  string $ip_address
     * Src: http://stackoverflow.com/a/6718472
     */
    function get_ip() {

        $ips_name = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ips_name as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ips = array_map('trim', explode(',', $_SERVER[$key]));
                foreach ($ips as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return '';
    }

    /**
     * Add menu items to toolbar
     *
     * @param unknown $admin_bar
     */
    function add_toolbar_menu_items($admin_bar) {
        $opt = get_option($this->opt_id);

        $admin_bar->add_menu(array(
            'id' => $this->opt_id . 'menubar-link',
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
        if (empty($_POST)) {
            return;
        }

        if (!wp_verify_nonce($_POST[$this->opt_id . '-noncefield'], $this->opt_id . '-nonceaction')) {
            return;
        }

        if (isset($_POST[$this->opt_id]) && in_array($_POST[$this->opt_id], array(
            '0',
            '1'
        ))) {
            update_option($this->opt_id, $_POST[$this->opt_id]);
        }
        if (isset($_POST[$this->opt_id . '-authorized-ips'])) {
            update_option($this->opt_id . '-authorized-ips', $_POST[$this->opt_id . '-authorized-ips']);
        }
    }

    function content_admin_page() {

        $opt = get_option($this->opt_id);
        echo '<h1>' . $this->options['plugin_menuname'] . '</h1>';
        echo '<form action="" method="post">';

        echo '<p><label>' . __('Activate maintenance mode : ', $this->options['id']) . '</label><br />';
        echo '<select name="' . $this->opt_id . '" id="' . $this->opt_id . '">
    <option ' . ($opt == '0' ? 'selected' : '') . ' value="0">' . __('No', $this->options['id']) . '</option>
    <option ' . ($opt == '1' ? 'selected' : '') . ' value="1">' . __('Yes', $this->options['id']) . '</option>
</select></p>';

        $opt_ips = get_option($this->opt_id . '-authorized-ips');
        echo '<p><label>' . __('Authorize these IPs:', $this->options['id']) . '</label><br />' . '<input type="text" name="' . $this->opt_id . '-authorized-ips" value="' . esc_attr($opt_ips) . '" />' . '</p>';

        echo wp_nonce_field($this->opt_id . '-nonceaction', $this->opt_id . '-noncefield', 1, 0);
        submit_button(__('Save', $this->options['id']));
    }

    function launch_maintenance() {

        // Try to include a HTML file
        $maintenanceFilenames = array(
            'maintenance.php',
            'maintenance.html',
            'index.html'
        );

        // Search in theme
        $theme_dir = get_template_directory() . '/wpumaintenance';
        if (is_dir($theme_dir)) {
            $this->include_file_if_exists($theme_dir, $maintenanceFilenames);
        }

        // Search in the root folder
        $this->include_file_if_exists(ABSPATH, $maintenanceFilenames);

        // Or include the default maintenance page
        include dirname(__FILE__) . '/includes/maintenance.php';
        die;
    }

    function include_file_if_exists($dir, $filenames) {
        foreach ($filenames as $filename) {
            $filepath = $dir . '/' . $filename;
            if (file_exists($filepath)) {
                include $filepath;
                die;
            }
        }
    }
}

add_action('init', 'init_wpuwaitingpage');
function init_wpuwaitingpage() {
    $WPUWaitingPage = new WPUWaitingPage();
}
