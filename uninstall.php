<?php
defined('ABSPATH') || die;
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/* Delete options */
$options = array(
    'wpumaintenance_options'
);
foreach ($options as $opt) {
    delete_option($opt);
    delete_site_option($opt);
}
