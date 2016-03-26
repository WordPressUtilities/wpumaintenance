<!DOCTYPE HTML>
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
