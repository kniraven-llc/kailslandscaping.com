<?php
declare(strict_types=1);

/*
    Admin account page.

    This page lets the signed-in admin user review their account
    and update their own password.

    Admin users are stored in the admin_users database table.
    Passwords are stored as password hashes only.
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Admin Account';

adminRequireLogin('Admin Account Login');

$messages = [];
$errors = [];

function adminAccountDateDisplay($value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return 'Not recorded yet.';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('M j, Y g:i A', $timestamp);
}

function adminAccountPasswordHasLetter(string $password): bool
{
    return preg_match('/[A-Za-z]/', $password) === 1;
}

function adminAccountPasswordHasNumber(string $password): bool
{
    return preg_match('/[0-9]/', $password) === 1;
}

function adminAccountPasswordHasSymbol(string $password): bool
{
    return preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

$currentUser = adminCurrentUser();

if ($currentUser === null || (int)($currentUser['is_active'] ?? 0) !== 1) {
    adminClearLoggedInUser();
    redirectTo('/admin.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'change_password') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    }

    if ($currentPassword === '') {
        $errors[] = 'Enter your current password.';
    }

    if ($newPassword === '') {
        $errors[] = 'Enter a new password.';
    }

    if ($confirmPassword === '') {
        $errors[] = 'Confirm your new password.';
    }

    if ($newPassword !== '' && strlen($newPassword) < 12) {
        $errors[] = 'New password must be at least 12 characters long.';
    }

    if ($newPassword !== '' && !adminAccountPasswordHasLetter($newPassword)) {
        $errors[] = 'New password must include at least one letter.';
    }

    if ($newPassword !== '' && !adminAccountPasswordHasNumber($newPassword)) {
        $errors[] = 'New password must include at least one number.';
    }

    if ($newPassword !== '' && !adminAccountPasswordHasSymbol($newPassword)) {
        $errors[] = 'New password must include at least one symbol.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation password do not match.';
    }

    if ($newPassword !== '' && $currentPassword !== '' && $newPassword === $currentPassword) {
        $errors[] = 'New password must be different from your current password.';
    }

    if ($newPassword !== '' && str_contains(strtolower($newPassword), strtolower((string)$currentUser['username']))) {
        $errors[] = 'New password should not contain your username.';
    }

    if (empty($errors) && !password_verify($currentPassword, (string)$currentUser['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }

    if (empty($errors)) {
        try {
            adminUpdatePassword((int)$currentUser['id'], $newPassword);

            $messages[] = 'Password updated successfully. Use the new password the next time you log in.';

            $currentUser = adminCurrentUser();
        } catch (Throwable $exception) {
            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'Password could not be updated.';
            }
        }
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<style>
    .admin-account-page {
        --account-card-bg: var(--kails-card-bg, rgba(255, 255, 255, 0.035));
        --account-card-bg-strong: var(--kails-card-bg-strong, rgba(255, 255, 255, 0.055));
        --account-text: var(--kails-text-color, var(--kails-white-text, #f0f2ed));
        --account-muted: var(--kails-muted-text-color, rgba(240, 242, 237, 0.76));
        --account-accent: var(--kails-primary-color, var(--kails-primary-yellow, #f2cc16));
        --account-border: var(--kails-border-color, rgba(242, 204, 22, 0.55));
        --account-success: var(--kails-success-color, #2fb36d);
        --account-warning: var(--kails-warning-color, #f2cc16);
        --account-danger: var(--kails-danger-color, #ff4d5e);

        color: var(--account-text);
    }

    .admin-account-page h1,
    .admin-account-page h2,
    .admin-account-page h3,
    .admin-account-page p,
    .admin-account-page dt,
    .admin-account-page dd,
    .admin-account-page label,
    .admin-account-page div,
    .admin-account-page span,
    .admin-account-page strong {
        color: inherit;
    }

    .admin-account-page .text-muted,
    .account-muted {
        color: var(--account-muted) !important;
    }

    .account-grid {
        display: grid;
        grid-template-columns: minmax(280px, 0.85fr) minmax(320px, 1.15fr);
        gap: 1.5rem;
        align-items: start;
    }

    .account-card {
        border: 1px solid var(--account-border);
        background:
            radial-gradient(circle at top right, rgba(242, 204, 22, 0.08), transparent 38%),
            var(--account-card-bg);
        border-radius: 0.85rem;
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.25);
        padding: 1.5rem;
    }

    .account-card-header {
        border-bottom: 1px solid rgba(240, 242, 237, 0.14);
        margin-bottom: 1rem;
        padding-bottom: 1rem;
    }

    .account-card-header h2 {
        margin-bottom: 0.35rem;
    }

    .account-detail-list {
        display: grid;
        gap: 0.85rem;
        margin: 0;
    }

    .account-detail-list dt {
        color: var(--account-accent);
        font-size: 0.82rem;
        font-weight: 900;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .account-detail-list dd {
        margin: 0;
        word-break: break-word;
    }

    .account-role-pill,
    .account-status-pill {
        border: 1px solid currentColor;
        border-radius: 999px;
        display: inline-flex;
        font-size: 0.85rem;
        font-weight: 900;
        padding: 0.25rem 0.7rem;
    }

    .account-role-pill {
        color: var(--account-accent);
        background: rgba(242, 204, 22, 0.12);
    }

    .account-status-pill {
        color: var(--account-success);
        background: rgba(47, 179, 109, 0.14);
    }

    .account-security-list {
        display: grid;
        gap: 0.6rem;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .account-security-list li {
        border: 1px solid rgba(240, 242, 237, 0.12);
        background: var(--account-card-bg-strong);
        border-radius: 0.65rem;
        color: var(--account-muted);
        padding: 0.7rem 0.8rem;
    }

    .password-input-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 0.5rem;
        align-items: center;
    }

    .password-input-row .form-control {
        min-width: 0;
    }

    .password-toggle-button {
        border: 1px solid var(--account-accent);
        background: transparent;
        color: var(--account-accent);
        border-radius: 0.5rem;
        font-weight: 900;
        min-height: 38px;
        padding: 0 0.85rem;
    }

    .password-toggle-button:hover,
    .password-toggle-button:focus {
        background: var(--account-accent);
        color: #111111;
    }

    .password-check-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(150px, 1fr));
        gap: 0.5rem;
        margin-top: 0.85rem;
    }

    .password-check {
        border: 1px solid rgba(240, 242, 237, 0.16);
        background: var(--account-card-bg-strong);
        border-radius: 0.55rem;
        color: var(--account-muted);
        font-size: 0.9rem;
        font-weight: 700;
        padding: 0.55rem 0.7rem;
    }

    .password-check.is-valid {
        border-color: var(--account-success);
        color: var(--account-success);
    }

    .password-check.is-invalid {
        border-color: rgba(240, 242, 237, 0.16);
        color: var(--account-muted);
    }

    .password-match-message {
        font-weight: 800;
        margin-top: 0.6rem;
    }

    .password-match-message.is-valid {
        color: var(--account-success);
    }

    .password-match-message.is-invalid {
        color: var(--account-danger);
    }

    .account-form-actions {
        border-top: 1px solid rgba(240, 242, 237, 0.14);
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        justify-content: space-between;
        margin-top: 1.25rem;
        padding-top: 1.25rem;
    }

    .account-form-actions .btn-light {
        background: var(--account-accent);
        border-color: var(--account-accent);
        color: #111111;
        font-weight: 900;
    }

    .account-form-actions .btn-outline-light {
        border-color: var(--account-accent);
        color: var(--account-accent);
        font-weight: 900;
    }

    .account-form-actions .btn-outline-light:hover,
    .account-form-actions .btn-outline-light:focus {
        background: var(--account-accent);
        color: #111111;
    }

    @media (max-width: 991.98px) {
        .account-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 575.98px) {
        .password-input-row,
        .password-check-grid {
            grid-template-columns: 1fr;
        }

        .account-form-actions {
            display: grid;
            grid-template-columns: 1fr;
        }

        .account-form-actions .btn {
            width: 100%;
        }
    }
</style>

<main class="site-section admin-account-page">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('account'); ?>

        <div class="mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                Admin Account
            </p>

            <h1 class="fw-bold">
                Account Settings
            </h1>

            <p class="lead account-muted mb-0">
                Review the signed-in admin account and update the password when needed.
            </p>
        </div>

        <?php foreach ($messages as $message): ?>
            <div class="alert alert-success">
                <?php echo escapeHtml($message); ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">
                <?php echo escapeHtml($error); ?>
            </div>
        <?php endforeach; ?>

        <div class="account-grid">
            <div class="d-grid gap-4">
                <section class="account-card">
                    <div class="account-card-header">
                        <h2 class="h4">
                            Signed-In User
                        </h2>

                        <p class="account-muted mb-0">
                            This is the account currently using the admin tools.
                        </p>
                    </div>

                    <dl class="account-detail-list">
                        <div>
                            <dt>Username</dt>
                            <dd><?php echo escapeHtml((string)$currentUser['username']); ?></dd>
                        </div>

                        <div>
                            <dt>Display Name</dt>
                            <dd><?php echo escapeHtml((string)$currentUser['display_name']); ?></dd>
                        </div>

                        <div>
                            <dt>Role</dt>
                            <dd>
                                <span class="account-role-pill">
                                    <?php echo escapeHtml((string)$currentUser['role_name']); ?>
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt>Account Status</dt>
                            <dd>
                                <span class="account-status-pill">
                                    Active
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt>Last Login</dt>
                            <dd>
                                <?php echo escapeHtml(adminAccountDateDisplay($currentUser['last_login_at'] ?? '')); ?>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="account-card">
                    <div class="account-card-header">
                        <h2 class="h4">
                            Password Rules
                        </h2>

                        <p class="account-muted mb-0">
                            Use a password that is hard to guess and different from other accounts.
                        </p>
                    </div>

                    <ul class="account-security-list">
                        <li>Use at least 12 characters.</li>
                        <li>Include letters, numbers, and symbols.</li>
                        <li>Do not reuse an old or personal password.</li>
                        <li>Do not share the admin password with customers or helpers.</li>
                    </ul>
                </section>
            </div>

            <section class="account-card">
                <div class="account-card-header">
                    <h2 class="h4">
                        Change Password
                    </h2>

                    <p class="account-muted mb-0">
                        Changing the password updates the saved password hash in the admin users table.
                    </p>
                </div>

                <form method="post" action="/admin_account.php" id="changePasswordForm" novalidate>
                    <input type="hidden" name="form_name" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">

                    <div class="mb-3">
                        <label for="current_password" class="form-label">
                            Current Password
                        </label>

                        <div class="password-input-row">
                            <input
                                type="password"
                                class="form-control"
                                id="current_password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                            >

                            <button type="button" class="password-toggle-button" data-password-toggle="current_password">
                                Show
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">
                            New Password
                        </label>

                        <div class="password-input-row">
                            <input
                                type="password"
                                class="form-control"
                                id="new_password"
                                name="new_password"
                                required
                                minlength="12"
                                autocomplete="new-password"
                                data-new-password
                                data-username="<?php echo escapeHtml((string)$currentUser['username']); ?>"
                            >

                            <button type="button" class="password-toggle-button" data-password-toggle="new_password">
                                Show
                            </button>
                        </div>

                        <div class="password-check-grid" aria-label="Password requirements">
                            <div class="password-check is-invalid" data-password-check="length">
                                12+ characters
                            </div>

                            <div class="password-check is-invalid" data-password-check="letter">
                                Has a letter
                            </div>

                            <div class="password-check is-invalid" data-password-check="number">
                                Has a number
                            </div>

                            <div class="password-check is-invalid" data-password-check="symbol">
                                Has a symbol
                            </div>

                            <div class="password-check is-invalid" data-password-check="username">
                                Does not use username
                            </div>

                            <div class="password-check is-invalid" data-password-check="different">
                                Different from current
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">
                            Confirm New Password
                        </label>

                        <div class="password-input-row">
                            <input
                                type="password"
                                class="form-control"
                                id="confirm_password"
                                name="confirm_password"
                                required
                                minlength="12"
                                autocomplete="new-password"
                                data-confirm-password
                            >

                            <button type="button" class="password-toggle-button" data-password-toggle="confirm_password">
                                Show
                            </button>
                        </div>

                        <div class="password-match-message is-invalid" data-password-match-message>
                            Passwords have not been confirmed yet.
                        </div>
                    </div>

                    <div class="account-form-actions">
                        <a href="/admin.php" class="btn btn-outline-light">
                            Back to Main Admin
                        </a>

                        <button type="submit" class="btn btn-light btn-lg">
                            Update Password
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('changePasswordForm');
    const currentPasswordInput = document.getElementById('current_password');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const matchMessage = document.querySelector('[data-password-match-message]');

    function setCheckStatus(name, isValid) {
        const checkElement = document.querySelector('[data-password-check="' + name + '"]');

        if (!checkElement) {
            return;
        }

        checkElement.classList.toggle('is-valid', isValid);
        checkElement.classList.toggle('is-invalid', !isValid);
    }

    function updatePasswordPreview() {
        if (!newPasswordInput || !confirmPasswordInput || !currentPasswordInput) {
            return;
        }

        const currentPassword = String(currentPasswordInput.value || '');
        const newPassword = String(newPasswordInput.value || '');
        const confirmPassword = String(confirmPasswordInput.value || '');
        const username = String(newPasswordInput.dataset.username || '').toLowerCase();

        const hasLength = newPassword.length >= 12;
        const hasLetter = /[A-Za-z]/.test(newPassword);
        const hasNumber = /[0-9]/.test(newPassword);
        const hasSymbol = /[^A-Za-z0-9]/.test(newPassword);
        const avoidsUsername = username === '' || !newPassword.toLowerCase().includes(username);
        const differentFromCurrent = currentPassword === '' || newPassword !== currentPassword;
        const passwordsMatch = newPassword !== '' && newPassword === confirmPassword;

        setCheckStatus('length', hasLength);
        setCheckStatus('letter', hasLetter);
        setCheckStatus('number', hasNumber);
        setCheckStatus('symbol', hasSymbol);
        setCheckStatus('username', avoidsUsername);
        setCheckStatus('different', differentFromCurrent);

        if (!matchMessage) {
            return;
        }

        matchMessage.classList.toggle('is-valid', passwordsMatch);
        matchMessage.classList.toggle('is-invalid', !passwordsMatch);

        if (confirmPassword === '') {
            matchMessage.textContent = 'Passwords have not been confirmed yet.';
        } else if (passwordsMatch) {
            matchMessage.textContent = 'New password and confirmation match.';
        } else {
            matchMessage.textContent = 'New password and confirmation do not match.';
        }
    }

    document.addEventListener('click', function (event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const inputId = target.getAttribute('data-password-toggle');

        if (!inputId) {
            return;
        }

        const input = document.getElementById(inputId);

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const shouldShow = input.type === 'password';

        input.type = shouldShow ? 'text' : 'password';
        target.textContent = shouldShow ? 'Hide' : 'Show';
    });

    [currentPasswordInput, newPasswordInput, confirmPasswordInput].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener('input', updatePasswordPreview);
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            updatePasswordPreview();
        });
    }

    updatePasswordPreview();
});
</script>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';