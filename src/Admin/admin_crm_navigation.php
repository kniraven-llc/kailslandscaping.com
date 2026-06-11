<?php
declare(strict_types=1);

/*
    Shared admin navigation.

    This renders one compact admin navigation bar.

    Pages using this file should NOT also call adminRenderTopLinks().
    Otherwise the page will show two admin menus.

    Final main admin navigation:
    - Admin Home
    - Clients
    - Requests
    - Business Cards
    - Website
    - System Check
    - Account
    - Log Out

    Example:
    require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';
    renderAdminCrmNavigation('requests');
*/

function adminCrmNavigationCurrentUser(): ?array
{
    if (!function_exists('adminCurrentUser')) {
        return null;
    }

    $currentUser = adminCurrentUser();

    if (!is_array($currentUser)) {
        return null;
    }

    return $currentUser;
}

function adminCrmNavigationCurrentUserLabel(): string
{
    $currentUser = adminCrmNavigationCurrentUser();

    if ($currentUser === null) {
        return 'Signed in';
    }

    $displayName = trim((string)($currentUser['display_name'] ?? ''));

    if ($displayName === '') {
        $displayName = trim((string)($currentUser['username'] ?? 'Admin'));
    }

    $roleName = trim((string)($currentUser['role_name'] ?? ''));

    if ($roleName !== '') {
        return $displayName . ' (' . $roleName . ')';
    }

    return $displayName;
}

function adminCrmNavigationButtonClass(bool $isActive): string
{
    return $isActive ? 'btn-light' : 'btn-outline-light';
}

function adminCrmNavigationIsActive(string $activePage, string $itemKey, array $aliases = []): bool
{
    if ($activePage === $itemKey) {
        return true;
    }

    return in_array($activePage, $aliases, true);
}

function renderAdminCrmNavigationButton(
    string $activePage,
    string $key,
    string $label,
    string $href,
    array $aliases = []
): void {
    $buttonClass = adminCrmNavigationButtonClass(
        adminCrmNavigationIsActive($activePage, $key, $aliases)
    );
    ?>

    <a href="<?php echo escapeHtml($href); ?>" class="btn btn-sm <?php echo escapeHtml($buttonClass); ?>">
        <?php echo escapeHtml($label); ?>
    </a>

    <?php
}

function renderAdminCrmNavigation(string $activePage): void
{
    $navigationItems = [
        [
            'key' => 'dashboard',
            'label' => 'Admin Home',
            'href' => '/admin.php',
            'aliases' => [
                'home',
                'admin_home',
            ],
        ],
        [
            'key' => 'clients',
            'label' => 'Clients',
            'href' => '/admin_clients.php',
            'aliases' => [
                'client_new',
                'client_detail',
                'client_edit',
            ],
        ],
        [
            'key' => 'requests',
            'label' => 'Requests',
            'href' => '/admin_requests.php',
            'aliases' => [
                'request_new',
                'request_detail',
                'request_edit',
            ],
        ],
        [
            'key' => 'business_cards',
            'label' => 'Business Cards',
            'href' => '/business_cards.php',
            'aliases' => [],
        ],
        [
            'key' => 'website_editor',
            'label' => 'Website',
            'href' => '/admin_website.php',
            'aliases' => [
                'website',
            ],
        ],
        [
            'key' => 'system_check',
            'label' => 'System Check',
            'href' => '/admin_system_check.php',
            'aliases' => [
                'system',
            ],
        ],
        [
            'key' => 'account',
            'label' => 'Account',
            'href' => '/admin_account.php',
            'aliases' => [],
        ],
    ];
    ?>

    <nav class="card p-2 p-md-3 mb-4 print-hide" aria-label="Admin navigation">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="kails-text-yellow fw-bold text-uppercase me-1">
                    Admin
                </span>

                <?php foreach ($navigationItems as $item): ?>
                    <?php
                    renderAdminCrmNavigationButton(
                        $activePage,
                        (string)$item['key'],
                        (string)$item['label'],
                        (string)$item['href'],
                        is_array($item['aliases']) ? $item['aliases'] : []
                    );
                    ?>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge rounded-pill border text-light px-3 py-2">
                    <?php echo escapeHtml(adminCrmNavigationCurrentUserLabel()); ?>
                </span>

                <form method="post" action="/admin.php" class="d-inline">
                    <input type="hidden" name="form_name" value="admin_logout">

                    <?php if (function_exists('getCsrfToken')): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                    <?php endif; ?>

                    <button type="submit" class="btn btn-sm btn-outline-light">
                        Log Out
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <?php
}