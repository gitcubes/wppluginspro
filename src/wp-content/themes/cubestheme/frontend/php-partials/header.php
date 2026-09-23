<?php include_once 'head.php'; ?>

<header class="fixed-top" role="banner">
    <div class="container">
        <div class="header-content">
            <a class="brand" href="index.php" aria-label="WP Plugins Pro home">
                <span class="brand-logo-stack">
                    <img class="brand-logo brand-logo--light" src="img/logo.svg" alt="WP Plugins Pro">
                    <img class="brand-logo brand-logo--dark" src="img/logo-dark.svg" alt="" aria-hidden="true">
                </span>
            </a>

            <nav class="nav" aria-label="Primary navigation">
                <a href="plugins.php">Plugins</a>
                <a href="suites.php">Suites</a>
                <a href="blog.php">Blog</a>
                <a href="pricing.php">Pricing</a>
                <a href="docs.php">Docs</a>
                <a href="docs.php">Get support</a>
            </nav>

            <div class="header-actions">
                <button class="icon-button" type="button" aria-label="User account">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M19 20a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" />
                    </svg>
                </button>
                <button class="cta-button" type="button">Register</button>
            </div>

            <button class="menu-toggle" id="menuToggle" type="button" aria-expanded="false" aria-controls="mobileMenu"
                aria-label="Open navigation">
                <span class="menu-toggle-box" aria-hidden="true">
                    <span class="menu-toggle-line"></span>
                    <span class="menu-toggle-line"></span>
                    <span class="menu-toggle-line"></span>
                </span>
            </button>

            <div class="mobile-menu" id="mobileMenu">
                <nav class="mobile-panel" aria-label="Mobile navigation">
                    <a class="mobile-link" href="plugins.php">Plugins</a>
                    <a class="mobile-link" href="suites.php">Suites</a>
                    <a class="mobile-link" href="blog.php">Blog</a>
                    <a class="mobile-link" href="pricing.php">Pricing</a>
                    <a class="mobile-link" href="docs.php">Docs</a>
                    <a class="mobile-link" href="docs.php">Get support</a>
                    <div class="mobile-actions">
                        <button class="icon-button" type="button" aria-label="User account">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M19 20a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" />
                                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" />
                            </svg>
                        </button>
                        <button class="cta-button" type="button">Register</button>
                    </div>
                </nav>
            </div>
        </div>
    </div>
</header>

<main id="main-content" class="site-main">
