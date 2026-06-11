<?php
declare(strict_types=1);

/*
    Shared website navigation.

    This file creates the top menu.
    The text and logo path come from the editable content table.
*/

$navbarLogoSrc = contentValue('navbar_logo_src');
$navbarBrandText = contentValue('navbar_brand_text');
?>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/">
            <img
                src="<?php echo escapeHtml($navbarLogoSrc); ?>"
                alt="<?php echo escapeHtml($navbarBrandText); ?> logo"
                class="navbar-logo"
            >
            <span class="d-none d-sm-inline">
                <?php echo escapeHtml($navbarBrandText); ?>
            </span>
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavigation"
            aria-controls="mainNavigation"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavigation">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/">
                        <?php echo escapeHtml(contentValue('nav_home_text')); ?>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/#services">
                        <?php echo escapeHtml(contentValue('nav_services_text')); ?>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/#about">
                        <?php echo escapeHtml(contentValue('nav_about_text')); ?>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/#contact">
                        <?php echo escapeHtml(contentValue('nav_contact_text')); ?>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>