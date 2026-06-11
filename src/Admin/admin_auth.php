<?php
declare(strict_types=1);

/*
    Shared admin authentication helper.

    This file is loaded by admin pages after config/initialize.php.

    It handles:
    - admin login using the admin_users database table
    - separate admin accounts
    - logout
    - shared admin login screen
    - shared admin page guard
    - shared admin navigation links

    Passwords are stored as password hashes only.
*/

function adminCurrentUserId(): int
{
    return (int)($_SESSION['kails_admin_user_id'] ?? 0);
}

function adminCurrentUsername(): string
{
    return (string)($_SESSION['kails_admin_username'] ?? '');
}

function adminCurrentDisplayName(): string
{
    return (string)($_SESSION['kails_admin_display_name'] ?? '');
}

function adminCurrentRole(): string
{
    return (string)($_SESSION['kails_admin_role'] ?? '');
}

function adminIsLoggedIn(): bool
{
    return adminCurrentUserId() > 0;
}

function adminFetchUserByUsername(string $username): ?array
{
    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            id,
            username,
            display_name,
            role_name,
            password_hash,
            is_active,
            last_login_at
         FROM admin_users
         WHERE username = ?
         LIMIT 1'
    );

    $statement->bind_param('s', $username);
    $statement->execute();

    $result = $statement->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        return null;
    }

    return $user;
}

function adminFetchUserById(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            id,
            username,
            display_name,
            role_name,
            password_hash,
            is_active,
            last_login_at
         FROM admin_users
         WHERE id = ?
         LIMIT 1'
    );

    $statement->bind_param('i', $userId);
    $statement->execute();

    $result = $statement->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        return null;
    }

    return $user;
}

function adminCurrentUser(): ?array
{
    return adminFetchUserById(adminCurrentUserId());
}

function adminSetLoggedInUser(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['kails_admin_user_id'] = (int)$user['id'];
    $_SESSION['kails_admin_username'] = (string)$user['username'];
    $_SESSION['kails_admin_display_name'] = (string)$user['display_name'];
    $_SESSION['kails_admin_role'] = (string)$user['role_name'];
}

function adminClearLoggedInUser(): void
{
    unset($_SESSION['kails_admin_user_id']);
    unset($_SESSION['kails_admin_username']);
    unset($_SESSION['kails_admin_display_name']);
    unset($_SESSION['kails_admin_role']);
}

function adminUpdateLastLogin(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'UPDATE admin_users
         SET last_login_at = NOW()
         WHERE id = ?'
    );

    $statement->bind_param('i', $userId);
    $statement->execute();
}

function adminUpdatePassword(int $userId, string $newPassword): void
{
    if ($userId <= 0) {
        throw new RuntimeException('Invalid admin user.');
    }

    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'UPDATE admin_users
         SET password_hash = ?
         WHERE id = ?'
    );

    $statement->bind_param('si', $newPasswordHash, $userId);
    $statement->execute();
}

function adminLogoutIfRequested(): void
{
    if (!isset($_GET['logout'])) {
        return;
    }

    adminClearLoggedInUser();
    redirectTo('/admin.php');
}

function adminLoginErrors(): array
{
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $errors;
    }

    if (($_POST['form_name'] ?? '') !== 'admin_login') {
        return $errors;
    }

    $username = strtolower(trim((string)($_POST['admin_username'] ?? '')));
    $submittedPassword = (string)($_POST['admin_password'] ?? '');

    if ($username === '') {
        $errors[] = 'Enter your username.';
        return $errors;
    }

    if ($submittedPassword === '') {
        $errors[] = 'Enter your password.';
        return $errors;
    }

    try {
        $user = adminFetchUserByUsername($username);

        if (!$user) {
            $errors[] = 'Invalid username or password.';
            return $errors;
        }

        if ((int)$user['is_active'] !== 1) {
            $errors[] = 'This admin account is inactive.';
            return $errors;
        }

        if (!password_verify($submittedPassword, (string)$user['password_hash'])) {
            $errors[] = 'Invalid username or password.';
            return $errors;
        }

        adminSetLoggedInUser($user);
        adminUpdateLastLogin((int)$user['id']);

        $redirectTo = (string)($_POST['redirect_to'] ?? '/admin.php');

        if ($redirectTo === '' || !str_starts_with($redirectTo, '/')) {
            $redirectTo = '/admin.php';
        }

        redirectTo($redirectTo);
    } catch (Throwable $exception) {
        $errors[] = 'Admin login failed.';
    }

    return $errors;
}

function adminRenderLoginPage(string $pageTitle = 'Admin Login'): void
{
    $errors = adminLoginErrors();

    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin.php', PHP_URL_PATH);
    $redirectTo = is_string($currentPath) && $currentPath !== '' ? $currentPath : '/admin.php';

    require_once PROJECT_ROOT . '/src/Layout/head.php';
    require_once PROJECT_ROOT . '/src/Layout/navigation.php';
    ?>

    <main class="site-section">
        <div class="container">
            <div class="form-wrapper">
                <div class="card p-4">
                    <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                        Admin
                    </p>

                    <h1 class="h3 mb-3">
                        <?php echo escapeHtml($pageTitle); ?>
                    </h1>

                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger">
                            <?php echo escapeHtml($error); ?>
                        </div>
                    <?php endforeach; ?>

                    <form method="post" action="<?php echo escapeHtml($redirectTo); ?>">
                        <input type="hidden" name="form_name" value="admin_login">
                        <input type="hidden" name="redirect_to" value="<?php echo escapeHtml($redirectTo); ?>">

                        <div class="mb-3">
                            <label for="admin_username" class="form-label">
                                Username
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="admin_username"
                                name="admin_username"
                                required
                                autocomplete="username"
                            >
                        </div>

                        <div class="mb-3">
                            <label for="admin_password" class="form-label">
                                Password
                            </label>
                            <input
                                type="password"
                                class="form-control"
                                id="admin_password"
                                name="admin_password"
                                required
                                autocomplete="current-password"
                            >
                        </div>

                        <button type="submit" class="btn btn-light">
                            Log In
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php
    require_once PROJECT_ROOT . '/src/Layout/footer.php';
}

function adminRequireLogin(string $pageTitle = 'Admin Login'): void
{
    adminLogoutIfRequested();

    if (adminIsLoggedIn()) {
        return;
    }

    adminRenderLoginPage($pageTitle);
    exit;
}

function adminRenderSecurityWarning(): void
{
    if (adminIsLoggedIn()) {
        return;
    }

    ?>
    <div class="alert alert-warning">
        You are not logged in.
    </div>
    <?php
}

function adminActiveButtonClass(string $activePage, array $matchingPages): string
{
    return in_array($activePage, $matchingPages, true) ? 'btn-light' : 'btn-outline-light';
}

function adminRenderTopLinks(string $activePage): void
{
    $contentActiveClass = adminActiveButtonClass($activePage, ['content', 'website']);
    $clientsActiveClass = adminActiveButtonClass($activePage, ['clients']);
    $requestsActiveClass = adminActiveButtonClass($activePage, ['requests', 'jobs']);
    $documentsActiveClass = adminActiveButtonClass($activePage, ['documents', 'receipts', 'quotes']);
    $businessCardsActiveClass = adminActiveButtonClass($activePage, ['business_cards']);
    $accountActiveClass = adminActiveButtonClass($activePage, ['account']);

    $displayName = adminCurrentDisplayName();
    $roleName = adminCurrentRole();

    ?>
    <div class="admin-top-links mb-4">
        <div class="admin-top-links-primary">
            <a href="/admin.php" class="btn <?php echo escapeHtml($contentActiveClass); ?>">
                Website Editor
            </a>

            <a href="/admin_clients.php" class="btn <?php echo escapeHtml($clientsActiveClass); ?>">
                Clients
            </a>

            <a href="/admin_requests.php" class="btn <?php echo escapeHtml($requestsActiveClass); ?>">
                Requests / Jobs
            </a>

            <a href="/admin_documents.php" class="btn <?php echo escapeHtml($documentsActiveClass); ?>">
                Quotes & Receipts
            </a>

            <a href="/business_cards.php" class="btn <?php echo escapeHtml($businessCardsActiveClass); ?>">
                Business Cards
            </a>

            <a href="/admin_account.php" class="btn <?php echo escapeHtml($accountActiveClass); ?>">
                Account
            </a>
        </div>

        <div class="admin-top-links-account">
            <div class="admin-user-pill">
                Signed in as
                <strong><?php echo escapeHtml($displayName); ?></strong>
                <?php if ($roleName !== ''): ?>
                    <span class="text-muted">
                        (<?php echo escapeHtml($roleName); ?>)
                    </span>
                <?php endif; ?>
            </div>

            <a href="/admin.php?logout=1" class="btn btn-outline-light">
                Log Out
            </a>
        </div>
    </div>
    <?php
}