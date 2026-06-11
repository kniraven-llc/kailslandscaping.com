<?php
declare(strict_types=1);

/*
    Admin client edit page.

    This page edits one existing client.

    Client list:
    /admin_clients.php

    Client detail:
    /admin_client_detail.php?id=123
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Edit Client';

adminRequireLogin('Edit Client Login');

$messages = [];
$errors = [];

function clientEditText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function clientEditTextarea(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    return $value;
}

function clientEditNullableString(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    return $value;
}

function clientEditNormalizePhoneNumber(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone);

    if ($digits === null || $digits === '') {
        return null;
    }

    if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        $digits = substr($digits, 1);
    }

    if (strlen($digits) < 7) {
        return null;
    }

    return $digits;
}

function clientEditDateDisplay($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return '—';
    }

    return date('M j, Y', $timestamp);
}

function clientEditStatusBadge(array $client): string
{
    if ((int)($client['is_active'] ?? 0) === 1) {
        return '<span class="request-status request-status-completed">Active Client</span>';
    }

    return '<span class="request-status request-status-archived">Inactive Client</span>';
}

function clientEditFullAddress(array $client): string
{
    $addressParts = [];

    $streetAddress = trim((string)($client['street_address'] ?? ''));

    if ($streetAddress !== '') {
        $addressParts[] = $streetAddress;
    }

    $cityStateZip = trim(
        trim((string)($client['city'] ?? ''))
        . ', '
        . trim((string)($client['state'] ?? ''))
        . ' '
        . trim((string)($client['zip_code'] ?? ''))
    );

    $cityStateZip = trim($cityStateZip, ', ');

    if ($cityStateZip !== '') {
        $addressParts[] = $cityStateZip;
    }

    return implode(' · ', $addressParts);
}

function getClientEditPreferredContactMethodOptions(): array
{
    return [
        '' => 'No preference recorded',
        'Phone' => 'Phone',
        'Email' => 'Email',
        'Either' => 'Either',
        'Phone Call' => 'Phone Call - legacy',
        'Text Message' => 'Text Message - legacy',
        'Any' => 'Any - legacy',
    ];
}

function clientEditSelectedValue(string $currentValue, string $optionValue): string
{
    return $currentValue === $optionValue ? 'selected' : '';
}

function clientEditCheckedValue(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function getClientEditRecord(int $clientId): ?array
{
    if ($clientId <= 0) {
        return null;
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            id,
            full_name,
            phone,
            phone_normalized,
            email,
            preferred_contact_method,
            street_address,
            city,
            state,
            zip_code,
            notes,
            is_active,
            created_at,
            updated_at
         FROM clients
         WHERE id = ?
         LIMIT 1'
    );

    $statement->bind_param('i', $clientId);
    $statement->execute();

    $result = $statement->get_result();
    $client = $result->fetch_assoc();

    if (!$client) {
        return null;
    }

    return $client;
}

function getClientEditRequestCounts(int $clientId): array
{
    if ($clientId <= 0) {
        return [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
        ];
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            COUNT(*) AS total_count,
            SUM(
                CASE
                    WHEN is_archived = 0
                     AND request_status NOT IN ("Completed", "Cancelled", "Archived")
                    THEN 1
                    ELSE 0
                END
            ) AS active_count,
            SUM(
                CASE
                    WHEN is_archived = 1
                      OR request_status IN ("Completed", "Cancelled", "Archived")
                    THEN 1
                    ELSE 0
                END
            ) AS inactive_count
         FROM quote_requests
         WHERE client_id = ?'
    );

    $statement->bind_param('i', $clientId);
    $statement->execute();

    $result = $statement->get_result();
    $row = $result->fetch_assoc();

    return [
        'total' => (int)($row['total_count'] ?? 0),
        'active' => (int)($row['active_count'] ?? 0),
        'inactive' => (int)($row['inactive_count'] ?? 0),
    ];
}

function getClientEditDocumentCount(int $clientId): int
{
    if ($clientId <= 0) {
        return 0;
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT COUNT(*) AS total_count
         FROM client_documents
         WHERE client_id = ?'
    );

    $statement->bind_param('i', $clientId);
    $statement->execute();

    $result = $statement->get_result();
    $row = $result->fetch_assoc();

    return (int)($row['total_count'] ?? 0);
}

function updateClientEditRecord(int $clientId, array $postedData, array &$errors): void
{
    if ($clientId <= 0) {
        $errors[] = 'Client ID is missing.';
        return;
    }

    $connection = getDatabaseConnection();

    $fullName = clientEditText((string)($postedData['full_name'] ?? ''));
    $phone = clientEditNullableString((string)($postedData['phone'] ?? ''));
    $phoneNormalized = $phone === null ? null : clientEditNormalizePhoneNumber($phone);
    $email = clientEditNullableString((string)($postedData['email'] ?? ''));
    $preferredContactMethod = clientEditNullableString((string)($postedData['preferred_contact_method'] ?? ''));
    $streetAddress = clientEditNullableString((string)($postedData['street_address'] ?? ''));
    $city = clientEditNullableString((string)($postedData['city'] ?? ''));
    $state = clientEditNullableString((string)($postedData['state'] ?? 'WI')) ?? 'WI';
    $zipCode = clientEditNullableString((string)($postedData['zip_code'] ?? ''));
    $notes = clientEditNullableString(clientEditTextarea((string)($postedData['notes'] ?? '')));
    $isActive = !empty($postedData['is_active']) ? 1 : 0;

    if ($fullName === '') {
        $errors[] = 'Client name is required.';
    }

    if ($phone === null && $email === null) {
        $errors[] = 'Enter at least a phone number or an email address.';
    }

    if ($phone !== null && $phoneNormalized === null) {
        $errors[] = 'Enter a valid phone number.';
    }

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is invalid.';
    }

    if (!array_key_exists((string)($preferredContactMethod ?? ''), getClientEditPreferredContactMethodOptions())) {
        $errors[] = 'Preferred contact method is invalid.';
    }

    if (!empty($errors)) {
        return;
    }

    $statement = $connection->prepare(
        'UPDATE clients
         SET
            full_name = ?,
            phone = ?,
            phone_normalized = ?,
            email = ?,
            preferred_contact_method = ?,
            street_address = ?,
            city = ?,
            state = ?,
            zip_code = ?,
            notes = ?,
            is_active = ?
         WHERE id = ?'
    );

    $statement->bind_param(
        'ssssssssssii',
        $fullName,
        $phone,
        $phoneNormalized,
        $email,
        $preferredContactMethod,
        $streetAddress,
        $city,
        $state,
        $zipCode,
        $notes,
        $isActive,
        $clientId
    );

    $statement->execute();
}

function clientEditMergePostedValues(array $client, array $postedData): array
{
    $client['full_name'] = (string)($postedData['full_name'] ?? $client['full_name']);
    $client['phone'] = (string)($postedData['phone'] ?? $client['phone']);
    $client['email'] = (string)($postedData['email'] ?? $client['email']);
    $client['preferred_contact_method'] = (string)($postedData['preferred_contact_method'] ?? $client['preferred_contact_method']);
    $client['street_address'] = (string)($postedData['street_address'] ?? $client['street_address']);
    $client['city'] = (string)($postedData['city'] ?? $client['city']);
    $client['state'] = (string)($postedData['state'] ?? $client['state']);
    $client['zip_code'] = (string)($postedData['zip_code'] ?? $client['zip_code']);
    $client['notes'] = (string)($postedData['notes'] ?? $client['notes']);
    $client['is_active'] = !empty($postedData['is_active']) ? 1 : 0;

    return $client;
}

$clientId = (int)($_GET['id'] ?? $_POST['client_id'] ?? 0);
$client = null;
$requestCounts = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
];
$documentCount = 0;

try {
    $client = getClientEditRecord($clientId);

    if ($client === null) {
        $errors[] = 'Client not found.';
    }
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'Client could not be loaded.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'save_client') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    } elseif ($client !== null) {
        try {
            updateClientEditRecord($clientId, $_POST, $errors);

            if (empty($errors)) {
                redirectTo('/admin_client_detail.php?id=' . $clientId . '&saved=1');
            }

            $client = clientEditMergePostedValues($client, $_POST);
        } catch (Throwable $exception) {
            $client = clientEditMergePostedValues($client, $_POST);

            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'Client could not be saved.';
            }
        }
    }
}

try {
    if ($client !== null) {
        $requestCounts = getClientEditRequestCounts($clientId);
        $documentCount = getClientEditDocumentCount($clientId);
    }
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'Client summary could not be loaded.';
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<main class="site-section">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('clients'); ?>

        <div class="mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                Edit Client
            </p>

            <h1 class="fw-bold">
                <?php echo $client !== null ? escapeHtml((string)$client['full_name']) : 'Client Not Found'; ?>
            </h1>

            <p class="text-muted mb-0">
                Update the client’s contact information, address, notes, and active status.
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

        <?php if ($client === null): ?>
            <div class="card p-4">
                <h2 class="h5">
                    No client loaded.
                </h2>

                <p class="text-muted">
                    Go back to the client list and choose a client.
                </p>

                <a href="/admin_clients.php" class="btn btn-light">
                    Back to Clients
                </a>
            </div>
        <?php else: ?>
            <?php $clientAddress = clientEditFullAddress($client); ?>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="/admin_client_detail.php?id=<?php echo escapeHtml((string)$clientId); ?>" class="btn btn-outline-light">
                    Back to Client Detail
                </a>

                <a href="/admin_clients.php" class="btn btn-outline-light">
                    Back to Clients
                </a>
            </div>

            <section class="card p-4 mb-4">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h2 class="h4 mb-3">
                            Current Client Summary
                        </h2>

                        <p class="mb-3">
                            <?php echo clientEditStatusBadge($client); ?>
                        </p>

                        <dl class="row mb-0">
                            <dt class="col-sm-4">Current Address</dt>
                            <dd class="col-sm-8">
                                <?php echo $clientAddress !== '' ? escapeHtml($clientAddress) : '—'; ?>
                            </dd>

                            <dt class="col-sm-4">Created</dt>
                            <dd class="col-sm-8">
                                <?php echo escapeHtml(clientEditDateDisplay($client['created_at'] ?? null)); ?>
                            </dd>

                            <dt class="col-sm-4">Updated</dt>
                            <dd class="col-sm-8">
                                <?php echo escapeHtml(clientEditDateDisplay($client['updated_at'] ?? null)); ?>
                            </dd>
                        </dl>
                    </div>

                    <div class="col-lg-5">
                        <div class="admin-inner-panel h-100">
                            <h2 class="h4 mb-3">
                                Linked Records
                            </h2>

                            <div class="request-summary-meta">
                                <div><strong>Active Requests:</strong> <?php echo escapeHtml((string)$requestCounts['active']); ?></div>
                                <div><strong>Inactive Requests:</strong> <?php echo escapeHtml((string)$requestCounts['inactive']); ?></div>
                                <div><strong>Total Requests:</strong> <?php echo escapeHtml((string)$requestCounts['total']); ?></div>
                                <div><strong>Documents:</strong> <?php echo escapeHtml((string)$documentCount); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <form method="post" action="/admin_client_edit.php">
                <input type="hidden" name="form_name" value="save_client">
                <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                <input type="hidden" name="client_id" value="<?php echo escapeHtml((string)$clientId); ?>">

                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Contact Information
                    </h2>

                    <p class="text-muted">
                        Keep this simple and accurate. Jobs and requests can still have their own job address.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">
                                Client Name
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="full_name"
                                name="full_name"
                                value="<?php echo escapeHtml((string)$client['full_name']); ?>"
                                required
                            >
                        </div>

                        <div class="col-md-3">
                            <label for="phone" class="form-label">
                                Phone
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?php echo escapeHtml((string)($client['phone'] ?? '')); ?>"
                            >
                            <div class="form-text">
                                Phone or email is required.
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label for="email" class="form-label">
                                Email
                            </label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?php echo escapeHtml((string)($client['email'] ?? '')); ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label for="preferred_contact_method" class="form-label">
                                Preferred Contact Method
                            </label>
                            <select class="form-control" id="preferred_contact_method" name="preferred_contact_method">
                                <?php foreach (getClientEditPreferredContactMethodOptions() as $methodValue => $methodLabel): ?>
                                    <option
                                        value="<?php echo escapeHtml($methodValue); ?>"
                                        <?php echo clientEditSelectedValue((string)($client['preferred_contact_method'] ?? ''), $methodValue); ?>
                                    >
                                        <?php echo escapeHtml($methodLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label for="street_address" class="form-label">
                                Street Address
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="street_address"
                                name="street_address"
                                value="<?php echo escapeHtml((string)($client['street_address'] ?? '')); ?>"
                            >
                        </div>

                        <div class="col-md-5">
                            <label for="city" class="form-label">
                                City
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="city"
                                name="city"
                                value="<?php echo escapeHtml((string)($client['city'] ?? '')); ?>"
                            >
                        </div>

                        <div class="col-md-2">
                            <label for="state" class="form-label">
                                State
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="state"
                                name="state"
                                value="<?php echo escapeHtml((string)($client['state'] ?? 'WI')); ?>"
                            >
                        </div>

                        <div class="col-md-3">
                            <label for="zip_code" class="form-label">
                                ZIP
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="zip_code"
                                name="zip_code"
                                value="<?php echo escapeHtml((string)($client['zip_code'] ?? '')); ?>"
                            >
                        </div>

                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    <?php echo clientEditCheckedValue((int)($client['is_active'] ?? 0) === 1); ?>
                                >
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label">
                                Client Notes
                            </label>
                            <textarea
                                class="form-control"
                                id="notes"
                                name="notes"
                                rows="5"
                            ><?php echo escapeHtml((string)($client['notes'] ?? '')); ?></textarea>
                            <div class="form-text">
                                These are internal notes for the admin. They are not shown to the customer.
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card p-4">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <div>
                            <h2 class="h5 mb-1">
                                Save Changes
                            </h2>

                            <p class="text-muted mb-0">
                                Saving returns you to the client detail page.
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="/admin_client_detail.php?id=<?php echo escapeHtml((string)$clientId); ?>" class="btn btn-outline-light">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-light">
                                Save Client
                            </button>
                        </div>
                    </div>
                </section>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';