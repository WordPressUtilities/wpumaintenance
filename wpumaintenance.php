<?php

/*
Plugin Name: WPU Maintenance Page
Description: Adds a maintenance page for non logged-in users
Version: 1.0.3
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Contributors: @ScreenFeedFr
*/

class WPUMaintenance {

    private $setting_values = array();
    private $plugin_version = '1.0.3';

    public function __construct() {
        add_action('plugins_loaded', array(&$this,
            'plugins_loaded'
        ), 99);
        add_action('init', array(&$this,
            'init'
        ), 99);
        add_action('template_redirect', array(&$this,
            'template_redirect'
        ), 99);
    }

    public function plugins_loaded() {

        $this->options = array(
            'id' => 'wpumaintenance',
            'opt_id' => 'wpumaintenance_has_maintenance',
            'plugin_minlevel' => 'manage_options',
            'plugin_publicname' => 'WPU Maintenance',
            'plugin_menutype' => 'options-general.php',
            'plugin_basename' => 'wpumaintenance'
        );

        load_plugin_textdomain($this->options['id'], false, dirname(plugin_basename(__FILE__)) . '/lang/');
        $this->options['plugin_menuname'] = __('Maintenance mode', 'wpumaintenance');

        $this->options = apply_filters('wpumaintenance_options', $this->options);

        $help_page_content = '';
        $maintenance_template = $this->get_maintenance_template();
        if ($maintenance_template) {
            $maintenance_template = str_replace(ABSPATH, '', $maintenance_template);
            $help_page_content = sprintf(__('This template is in use : %s', 'wpumaintenance'), $maintenance_template);
        }

        $this->settings_details = array(
            # Admin page
            'create_page' => true,
            'plugin_basename' => plugin_basename(__FILE__),
            # Default
            'plugin_id' => $this->options['id'],
            'plugin_name' => $this->options['plugin_publicname'],
            'option_id' => 'wpumaintenance_options',
            'sections' => array(
                'settings' => array(
                    'name' => __('Settings', 'wpumaintenance')
                )
            )
        );
        $this->settings = array(
            'enabled' => array(
                'default' => '0',
                'type' => 'checkbox',
                'label' => __('Enable', 'wpumaintenance'),
                'label_check' => __('Enable maintenance mode', 'wpumaintenance')
            ),
            'disabled_users' => array(
                'default' => '1',
                'type' => 'checkbox',
                'label' => __('Disable for users', 'wpumaintenance'),
                'label_check' => __('Disable maintenance mode for logged-in users', 'wpumaintenance')
            ),
            'authorized_ips' => array(
                'label' => __('Authorize these IPs', 'wpumaintenance'),
                'default' => '',
                'type' => 'textarea'
            ),
            'page_content' => array(
                'label' => __('Page content', 'wpumaintenance'),
                'default' => '',
                'type' => 'textarea',
                'help' => $help_page_content
            )
        );
        include dirname(__FILE__) . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        $settings_obj = new \wpumaintenance\WPUBaseSettings($this->settings_details, $this->settings);

        $this->settings_values = $settings_obj->get_settings();
        foreach ($this->settings as $setting_id => $setting) {
            if (!isset($this->settings_values[$setting_id])) {
                $this->settings_values[$setting_id] = $setting['default'];
            }
        }
        $this->settings_values = apply_filters('wpumaintenance__settings_values', $this->settings_values);

        /* Auto-update */
        include dirname(__FILE__) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpumaintenance\WPUBaseUpdate(
            'WordPressUtilities',
            'wpumaintenance',
            $this->plugin_version);
    }

    public function init() {

        /* Admin bar */
        if (current_user_can($this->options['plugin_minlevel'])) {
            add_action('admin_bar_menu', array(&$this,
                'add_toolbar_menu_items'
            ), 100);
        }

        if (is_admin()) {
            add_action('admin_head', array(&$this,
                'add_toolbar_menu_items__class'
            ), 100);
            return;
        }
        add_action('wp_head', array(&$this,
            'add_toolbar_menu_items__class'
        ), 100);
    }

    public function template_redirect() {
        if ($this->has_maintenance() || (is_user_logged_in() && isset($_POST['demo-wpu-maintenance']))) {
            $this->launch_maintenance();
        }
    }

    /**
     * Check if has maintenance
     * @return boolean has maintenance
     */
    public function has_maintenance() {
        global $pagenow;
        if ($this->settings_values['enabled'] != '1') {
            return false;
        }

        // Don't launch if CLI
        if (function_exists('php_sapi_name') && php_sapi_name() == 'cli') {
            return false;
        }

        // Don't launch if user is logged in
        $disable_loggedin = $this->settings_values['disabled_users'];
        if ($disable_loggedin == '1' && is_user_logged_in()) {
            return false;
        }

        // Don't launch if in admin
        if (is_admin()) {
            return false;
        }

        // Don't launch if login page
        if ($pagenow == 'wp-login.php') {
            return false;
        }

        // Check authorized ips
        $opt_ips = $this->settings_values['authorized_ips'];
        $opt_ips = str_replace(' ', '', $opt_ips);
        $opt_ips = str_replace(array(
            ';',
            ',',
            "\n",
            "\r"
        ), '###', $opt_ips);
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
    public function get_ip() {

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
    public function add_toolbar_menu_items($admin_bar) {
        $opt = $this->settings_values['enabled'];

        $admin_bar->add_node(array(
            'id' => $this->options['opt_id'] . 'menubar-link',
            'title' => $this->options['plugin_menuname'] . ' : ' . ($opt == '0' ? __('Off', 'wpumaintenance') : __('On', 'wpumaintenance')),
            'href' => admin_url($this->options['plugin_menutype'] . '?page=' . $this->options['plugin_basename']),
            'meta' => array(
                'title' => $this->options['plugin_publicname']
            )
        ));
    }

    public function add_toolbar_menu_items__class() {
        $opt = $this->settings_values['enabled'];
        if ($opt == '1' && is_admin_bar_showing()) {
            echo '<style>';
            echo 'li#wp-admin-bar-' . $this->options['opt_id'] . 'menubar-link{background-color:#006600!important;}';
            echo '</style>';
        }
    }

    public function get_page_content() {
        $opt_content = trim($this->settings_values['page_content']);
        if (empty($opt_content)) {
            $opt_content = sprintf(__('%s is in maintenance mode.', 'wpumaintenance'), '<strong>' . get_bloginfo('name') . '</strong>');
        }
        return wpautop($opt_content);
    }

    public function launch_maintenance() {

        // Try to include a template file
        $maintenance_template = $this->get_maintenance_template();
        if ($maintenance_template) {
            $this->header_503();
            include $maintenance_template;
            die;
        }
        // Or include the default maintenance page

        /* Page title */
        $page_title = apply_filters('wpumaintenance_pagetitle', get_bloginfo('name'));

        /* Default content */
        $default_content = '<h1>' . $page_title . '</h1>';
        $default_sentence = $this->get_page_content();
        $default_content .= $default_sentence;

        // Use WordPress handler if available
        if (function_exists('_default_wp_die_handler')) {
            _default_wp_die_handler($default_content, $page_title, array('response' => 503));
        }

        $this->header_503();
        include dirname(__FILE__) . '/includes/maintenance.php';
        die;
    }

    public function header_503() {
        header('HTTP/1.1 503 Service Temporarily Unavailable');
        header('Status: 503 Service Temporarily Unavailable');
        header('Retry-After: 300'); //300 seconds
    }

    public function get_maintenance_template() {
        $maintenanceFilenames = array(
            'maintenance.php',
            'maintenance.html',
            'index.html'
        );

        // Search in root dir
        $folders = array(ABSPATH);

        // Add theme if available
        $theme_dir = get_stylesheet_directory() . '/wpumaintenance';
        if (is_dir($theme_dir)) {
            $folders[] = $theme_dir;
        }

        foreach ($folders as $dir) {
            foreach ($maintenanceFilenames as $filename) {
                $filepath = $dir . '/' . $filename;
                if (file_exists($filepath)) {
                    return $filepath;
                }
            }
        }

        return false;
    }

}

$WPUMaintenance = new WPUMaintenance();
