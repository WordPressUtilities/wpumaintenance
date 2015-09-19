<?php
global $WPUWaitingPage;
/* Page title */
$page_title = apply_filters('wpumaintenance_pagetitle', get_bloginfo('name'));
/* Default content */
$default_content = '<h1>' . $page_title . '</h1>';
$default_sentence = $WPUWaitingPage->get_page_content();
$default_content.= ($default_sentence == false) ? '<p>' . sprintf(__('%s is in maintenance mode.', 'wpumaintenance') , '<strong>' . get_bloginfo('name') . '</strong>') . '</p>' : $default_sentence;
?><!DOCTYPE HTML>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8" />
    <title><?php echo $page_title; ?></title>
    <?php do_action('wpumaintenance_head'); ?>
</head>
<body class="wpumaintenance-page"><?php
do_action('wpumaintenance_header');
echo apply_filters('wpumaintenance_content', $default_content);
do_action('wpumaintenance_footer');
?></body>
</html>
