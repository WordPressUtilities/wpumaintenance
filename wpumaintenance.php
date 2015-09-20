<?php

/*
Plugin Name: WPU Maintenance page
Description: Adds a maintenance page for non logged-in users
Version: 0.8
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Contributors: @ScreenFeedFr
*/

class WPUWaitingPage {

    function __construct() {
        add_action('init', array(&$this,
            'init'
        ) , 99);
    }

    function init() {

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

        /* Admin bar */
        add_action('admin_bar_menu', array(&$this,
            'add_toolbar_menu_items'
        ) , 100);

        if (is_admin()) {
            add_action('admin_head', array(&$this,
                'add_toolbar_menu_items__class'
            ) , 100);

            add_action('admin_menu', array(&$this,
                'set_admin_page'
            ));

            add_action('admin_init', array(&$this,
                'content_admin_page_postAction'
            ));
            return;
        }
        else {
            add_action('wp_head', array(&$this,
                'add_toolbar_menu_items__class'
            ) , 100);
        }
        if ($this->has_maintenance() || (is_user_logged_in() && isset($_POST['demo-wpu-maintenance']))) {
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

        // Dont launch if user is logged in
        $disable_loggedin = get_option($this->opt_id . '-disable-loggedin');
        if ($disable_loggedin != '1' && is_user_logged_in()) {
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
        $opt_ips = str_replace(' ', '', $opt_ips);
        $opt_ips = str_replace(array(
            ';',
            ',',
            "\n"
        ) , '###', $opt_ips);
        $authorized_ips = explode('###', $opt_ips);

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

        $admin_bar->add_node(array(
            'id' => $this->opt_id . 'menubar-link',
            'title' => $this->options['plugin_menuname'] . ' : ' . ($opt == '0' ? __('Off', $this->options['id']) : __('On', $this->options['id'])) ,
            'href' => admin_url($this->options['plugin_menutype'] . '?page=' . $this->options['plugin_basename']) ,
            'meta' => array(
                'title' => $this->options['plugin_publicname'],
            ) ,
        ));
    }

    function add_toolbar_menu_items__class() {
        $opt = get_option($this->opt_id);
        if ($opt == '1' && is_admin_bar_showing()) {
            echo '<style>';
            echo 'li#wp-admin-bar-' . $this->opt_id . 'menubar-link{background-color:#006600!important;}';
            echo '</style>';
        }
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

        if (!isset($_POST[$this->opt_id . '-noncefield']) || !wp_verify_nonce($_POST[$this->opt_id . '-noncefield'], $this->opt_id . '-nonceaction')) {
            return;
        }

        $this->update_option_from($_POST, $this->opt_id, 'select');
        $this->update_option_from($_POST, $this->opt_id . '-disable-loggedin', 'select');
        $this->update_option_from($_POST, $this->opt_id . '-authorized-ips');
        $this->update_option_from($_POST, $this->opt_id . '-page-content');
    }

    function content_admin_page() {

        $opt = get_option($this->opt_id);
        echo '<h1>' . $this->options['plugin_menuname'] . '</h1>';
        echo '<form action="" method="post">';

        echo $this->get_field($this->opt_id, __('Enable maintenance mode : ', $this->options['id']) , 'select');
        echo $this->get_field($this->opt_id . '-disable-loggedin', __('Disable for logged-in users:', $this->options['id']) , 'select');
        echo $this->get_field($this->opt_id . '-authorized-ips', __('Authorize these IPs:', $this->options['id']));
        echo $this->get_field($this->opt_id . '-page-content', __('Page content:', $this->options['id']) , 'textarea');

        echo wp_nonce_field($this->opt_id . '-nonceaction', $this->opt_id . '-noncefield', 1, 0);
        submit_button(__('Save', $this->options['id']));
        echo '</form>';
        echo '<hr />';
        echo '<form target="_blank" action="' . get_page_link() . '" method="post">';
        echo '<input type="hidden" name="demo-wpu-maintenance" value="1" />';
        submit_button(__('Preview', $this->options['id']) , 'secondary');
        echo '</form>';
    }

    function get_page_content() {
        $opt_content = trim(get_option($this->opt_id . '-page-content'));
        if (empty($opt_content)) {
            return false;
        }
        return wpautop($opt_content);
    }

    function launch_maintenance() {

        // Try to include a HTML file
        $maintenanceFilenames = array(
            'maintenance.php',
            'maintenance.html',
            'index.html'
        );

        // Search in theme
        $theme_dir = get_stylesheet_directory() . '/wpumaintenance';
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

    /* ----------------------------------------------------------
      Options
    ---------------------------------------------------------- */

    function get_field($id, $label = '', $type = false) {
        $opt_content = get_option($id);
        $return = '<p><label for="' . $id . '">' . $label . '</label><br />';
        switch ($type) {
            case 'select':
                $return.= '<select name="' . $id . '" id="' . $id . '">
                <option ' . selected($opt_content, '0', false) . ' value="0">' . __('No', $this->options['id']) . '</option>
                <option ' . selected($opt_content, '1', false) . ' value="1">' . __('Yes', $this->options['id']) . '</option>
            </select>';
            break;
            case 'textarea':
                $return.= '<textarea id="' . $id . '" name="' . $id . '">' . esc_html($opt_content) . '</textarea>';
            break;
            default:
                $return.= '<input type="text" id="' . $id . '" name="' . $id . '" value="' . esc_attr($opt_content) . '" />';
        }
        $return.= '</p>';
        return $return;
    }

    function update_option_from($from, $id, $type = false) {
        $select_values = array(
            '0',
            '1'
        );
        switch ($type) {
            case 'select':
                if (isset($from[$id]) && in_array($from[$id], $select_values)) {
                    update_option($id, $from[$id]);
                }
            break;
            default:
                if (isset($from[$id])) {
                    update_option($id, $from[$id]);
                }
        }
    }
}

$WPUWaitingPage = new WPUWaitingPage();
