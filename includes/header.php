<?php
/**
 * Header Template
 */

$current_path = str_replace('\\', '/', $_SERVER['PHP_SELF']);
$is_dashboard_view = strpos($current_path, '/dashboard/') !== false
    || in_array(($page_title ?? ''), ['About', 'Contact'], true);
$body_class = '';
if (in_array(($page_title ?? ''), ['Login', 'Register'], true)) {
    $body_class = 'auth-body';
}
if (($page_title ?? '') === 'Home') {
    $body_class = 'home-body';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' | ' : ''; ?><?php echo SITE_NAME; ?></title>

    <!-- Core stylesheet (tokens + reset + base) -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/layout.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/components.css">

    <?php if ($is_dashboard_view): ?>
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/pages/dashboard.css">
    <?php endif; ?>

    <?php if (isset($page_title) && $page_title === 'Home'): ?>
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/pages/home.css">
    <?php endif; ?>
    <?php if (isset($page_title) && $page_title === 'About'): ?>
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/pages/about.css">
    <?php endif; ?>
    <?php if (isset($page_title) && $page_title === 'Contact'): ?>
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/pages/contact.css">
    <?php endif; ?>
    <?php if (isset($page_title) && in_array($page_title, ['Login', 'Register'], true)): ?>
        <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/pages/auth.css">
    <?php endif; ?>

    <!-- Font Awesome (icons only) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body<?php echo $body_class ? ' class="' . $body_class . '"' : ''; ?>>
