<?php
declare(strict_types=1);

/*
    Shared page head.

    This file starts the HTML page.
    It loads Bootstrap 5, the structure CSS file, and the theme CSS file.
    It also prints database-controlled theme color variables.
*/

$pageTitle = $pageTitle ?? 'Page';

$fullPageTitle = $pageTitle . ' | ' . SITE_NAME;
$metaDescription = contentValue('site_meta_description');
$themeColorOverrides = themeCssVariableOverrides();
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo escapeHtml($fullPageTitle); ?></title>
    <meta name="description" content="<?php echo escapeHtml($metaDescription); ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/structure.css" rel="stylesheet">
    <link href="/assets/css/theme.css" rel="stylesheet">

    <?php if ($themeColorOverrides !== ''): ?>
        <style>
<?php echo $themeColorOverrides . "\n"; ?>
        </style>
    <?php endif; ?>
</head>
<body>