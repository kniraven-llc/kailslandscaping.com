<?php
declare(strict_types=1);

/*
    Admin clients page.

    This page shows client cards, lets the admin search/filter/sort them
    without refreshing the page, and lets the admin manually add a client.

    Client detail and client editing are handled by separate pages:
    - /admin_client_detail.php?id=123
    - /admin_client_edit.php?id=123
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Clients';

adminRequireLogin('Clients Login');

$messages = [];
$errors = [];

function clientText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function clientTextarea(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    return $value;
}

function clientNullableString(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    return $value;
}

function clientNormalizePhoneNumber(string $phone): ?string
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

function clientDateDisplay($value): string
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

function clientDateSortValue($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return 0;
    }

    return $timestamp;
}

function clientBindParams(mysqli_stmt $statement, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $bindArgs = [];
    $bindArgs[] = $types;

    foreach ($params as $key => $value) {
        $bindArgs[] = &$params[$key];
    }

    call_user_func_array([$statement, 'bind_param'], $bindArgs);
}

function clientStatusBadge(bool $isActive): string
{
    if ($isActive) {
        return '<span class="request-status request-status-completed">Active Client</span>';
    }

    return '<span class="request-status request-status-archived">Inactive Client</span>';
}

function clientFirstName(string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName));

    if (!is_array($parts) || empty($parts)) {
        return '';
    }

    return strtolower((string)$parts[0]);
}

function clientLastName(string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName));

    if (!is_array($parts) || empty($parts)) {
        return '';
    }

    return strtolower((string)$parts[count($parts) - 1]);
}

function clientFullAddress(array $client): string
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

function getPreferredContactMethodOptions(): array
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

function defaultNewClientValues(): array
{
    return [
        'full_name' => '',
        'phone' => '',
        'email' => '',
        'preferred_contact_method' => '',
        'street_address' => '',
        'city' => '',
        'state' => 'WI',
        'zip_code' => '',
        'notes' => '',
    ];
}

function saveNewClientRecord(array $postedData, array &$errors): int
{
    $connection = getDatabaseConnection();

    $fullName = clientText((string)($postedData['full_name'] ?? ''));
    $phone = clientNullableString((string)($postedData['phone'] ?? ''));
    $phoneNormalized = $phone === null ? null : clientNormalizePhoneNumber($phone);
    $email = clientNullableString((string)($postedData['email'] ?? ''));
    $preferredContactMethod = clientNullableString((string)($postedData['preferred_contact_method'] ?? ''));
    $streetAddress = clientNullableString((string)($postedData['street_address'] ?? ''));
    $city = clientNullableString((string)($postedData['city'] ?? ''));
    $state = clientNullableString((string)($postedData['state'] ?? 'WI')) ?? 'WI';
    $zipCode = clientNullableString((string)($postedData['zip_code'] ?? ''));
    $notes = clientNullableString(clientTextarea((string)($postedData['notes'] ?? '')));
    $isActive = 1;

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

    if (!array_key_exists((string)($preferredContactMethod ?? ''), getPreferredContactMethodOptions())) {
        $errors[] = 'Preferred contact method is invalid.';
    }

    if (!empty($errors)) {
        return 0;
    }

    $statement = $connection->prepare(
        'INSERT INTO clients
            (
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
                is_active
            )
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $statement->bind_param(
        'ssssssssssi',
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
        $isActive
    );

    $statement->execute();

    return (int)$connection->insert_id;
}

function getClientCards(): array
{
    $connection = getDatabaseConnection();

    $sql = '
        SELECT
            c.id,
            c.full_name,
            c.phone,
            c.phone_normalized,
            c.email,
            c.preferred_contact_method,
            c.street_address,
            c.city,
            c.state,
            c.zip_code,
            c.notes,
            c.is_active,
            c.created_at,
            c.updated_at,

            COALESCE(rs.total_request_count, 0) AS total_request_count,
            COALESCE(rs.active_request_count, 0) AS active_request_count,
            COALESCE(rs.new_count, 0) AS new_count,
            COALESCE(rs.quoted_count, 0) AS quoted_count,
            COALESCE(rs.scheduled_count, 0) AS scheduled_count,
            COALESCE(rs.in_progress_count, 0) AS in_progress_count,
            COALESCE(rs.completed_count, 0) AS completed_count,
            COALESCE(rs.cancelled_count, 0) AS cancelled_count,
            COALESCE(rs.archived_count, 0) AS archived_count,
            COALESCE(rs.other_active_count, 0) AS other_active_count,

            COALESCE(rs.overdue_count, 0) AS overdue_count,
            COALESCE(rs.missing_next_action_count, 0) AS missing_next_action_count,
            COALESCE(us.customer_update_count, 0) AS customer_update_count,

            rs.most_recent_request_at,
            rs.most_recent_request_created_at,

            COALESCE(rs.highest_attention_rank, 9) AS highest_attention_rank
        FROM clients c
        LEFT JOIN (
            SELECT
                qr.client_id,

                COUNT(*) AS total_request_count,

                SUM(
                    CASE
                        WHEN qr.is_archived = 0
                         AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
                        THEN 1
                        ELSE 0
                    END
                ) AS active_request_count,

                SUM(CASE WHEN qr.is_archived = 0 AND qr.request_status = "New" THEN 1 ELSE 0 END) AS new_count,
                SUM(CASE WHEN qr.is_archived = 0 AND qr.request_status = "Quoted" THEN 1 ELSE 0 END) AS quoted_count,
                SUM(CASE WHEN qr.is_archived = 0 AND qr.request_status = "Scheduled" THEN 1 ELSE 0 END) AS scheduled_count,
                SUM(CASE WHEN qr.is_archived = 0 AND qr.request_status = "In Progress" THEN 1 ELSE 0 END) AS in_progress_count,
                SUM(CASE WHEN qr.is_archived = 0 AND qr.request_status = "Completed" THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN qr.is_archived = 0 AND qr.request_status = "Cancelled" THEN 1 ELSE 0 END) AS cancelled_count,

                SUM(
                    CASE
                        WHEN qr.is_archived = 1
                          OR qr.request_status = "Archived"
                        THEN 1
                        ELSE 0
                    END
                ) AS archived_count,

                SUM(
                    CASE
                        WHEN qr.is_archived = 0
                         AND qr.request_status NOT IN ("New", "Quoted", "Scheduled", "In Progress", "Completed", "Cancelled", "Archived")
                        THEN 1
                        ELSE 0
                    END
                ) AS other_active_count,

                SUM(
                    CASE
                        WHEN qr.is_archived = 0
                         AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
                         AND (
                            (
                                qr.next_action_due_at IS NOT NULL
                                AND qr.next_action_due_at <= NOW()
                            )
                            OR (
                                qr.request_status = "Scheduled"
                                AND qr.scheduled_start IS NOT NULL
                                AND qr.scheduled_start <= NOW()
                                AND qr.completed_at IS NULL
                            )
                         )
                        THEN 1
                        ELSE 0
                    END
                ) AS overdue_count,

                SUM(
                    CASE
                        WHEN qr.is_archived = 0
                         AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
                         AND (
                            qr.next_action IS NULL
                            OR qr.next_action = ""
                         )
                        THEN 1
                        ELSE 0
                    END
                ) AS missing_next_action_count,

                MAX(qr.updated_at) AS most_recent_request_at,
                MAX(qr.created_at) AS most_recent_request_created_at,

                MIN(
                    CASE
                        WHEN qr.is_archived = 0
                         AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
                         AND EXISTS (
                            SELECT 1
                            FROM quote_request_comments qrc
                            WHERE qrc.quote_request_id = qr.id
                              AND qrc.author_type = "customer"
                              AND (
                                  qr.last_admin_reviewed_at IS NULL
                                  OR qrc.created_at > qr.last_admin_reviewed_at
                              )
                         )
                        THEN 0

                        WHEN qr.is_archived = 0
                         AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
                         AND (
                            (
                                qr.next_action_due_at IS NOT NULL
                                AND qr.next_action_due_at <= NOW()
                            )
                            OR (
                                qr.request_status = "Scheduled"
                                AND qr.scheduled_start IS NOT NULL
                                AND qr.scheduled_start <= NOW()
                                AND qr.completed_at IS NULL
                            )
                         )
                        THEN 1

                        WHEN qr.is_archived = 0
                         AND qr.request_status = "New"
                        THEN 2

                        WHEN qr.is_archived = 0
                         AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
                         AND (
                            qr.next_action IS NULL
                            OR qr.next_action = ""
                         )
                        THEN 3

                        WHEN qr.is_archived = 0
                         AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
                        THEN 4

                        ELSE 8
                    END
                ) AS highest_attention_rank
            FROM quote_requests qr
            GROUP BY qr.client_id
        ) rs
            ON rs.client_id = c.id
        LEFT JOIN (
            SELECT
                qr.client_id,
                COUNT(DISTINCT qrc.id) AS customer_update_count
            FROM quote_requests qr
            INNER JOIN quote_request_comments qrc
                ON qrc.quote_request_id = qr.id
            WHERE qrc.author_type = "customer"
              AND (
                    qr.last_admin_reviewed_at IS NULL
                    OR qrc.created_at > qr.last_admin_reviewed_at
              )
            GROUP BY qr.client_id
        ) us
            ON us.client_id = c.id
        ORDER BY
            COALESCE(rs.highest_attention_rank, 9) ASC,
            COALESCE(rs.most_recent_request_created_at, c.created_at) DESC,
            c.full_name ASC
        LIMIT 500
    ';

    $result = $connection->query($sql);

    if (!$result) {
        return [];
    }

    $clients = [];

    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }

    return $clients;
}

$newClientValues = defaultNewClientValues();
$showAddClientForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formName = (string)($_POST['form_name'] ?? '');
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    } else {
        try {
            if ($formName === 'create_client') {
                $newClientValues = array_merge($newClientValues, [
                    'full_name' => (string)($_POST['full_name'] ?? ''),
                    'phone' => (string)($_POST['phone'] ?? ''),
                    'email' => (string)($_POST['email'] ?? ''),
                    'preferred_contact_method' => (string)($_POST['preferred_contact_method'] ?? ''),
                    'street_address' => (string)($_POST['street_address'] ?? ''),
                    'city' => (string)($_POST['city'] ?? ''),
                    'state' => (string)($_POST['state'] ?? 'WI'),
                    'zip_code' => (string)($_POST['zip_code'] ?? ''),
                    'notes' => (string)($_POST['notes'] ?? ''),
                ]);

                $clientId = saveNewClientRecord($_POST, $errors);

                if ($clientId > 0 && empty($errors)) {
                    redirectTo('/admin_client_detail.php?id=' . $clientId . '&created=1');
                }

                $showAddClientForm = true;
            }
        } catch (Throwable $exception) {
            $showAddClientForm = true;

            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'The client could not be saved.';
            }
        }
    }
}

$clientCards = [];

try {
    $clientCards = getClientCards();
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'Clients could not be loaded.';
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<style>
    .client-filter-panel {
        position: sticky;
        top: 0.5rem;
        z-index: 10;
    }

    .client-status-counts {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .client-status-count {
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 999px;
        padding: 0.25rem 0.65rem;
        font-size: 0.875rem;
    }

    .client-card-click-area {
        color: inherit;
        text-decoration: none;
    }

    .client-card-click-area:hover {
        color: inherit;
        text-decoration: none;
    }

    .client-hidden {
        display: none !important;
    }
</style>

<main class="site-section">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('clients'); ?>

        <div class="mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                Admin
            </p>

            <h1 class="fw-bold">
                Clients
            </h1>

            <p class="text-muted mb-0">
                Find customers, see which clients need attention, review request status counts, and add new clients.
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

        <details class="card p-4 mb-4" id="add-client" <?php echo $showAddClientForm ? 'open' : ''; ?>>
            <summary class="h4 mb-3">
                Add New Client
            </summary>

            <p class="text-muted">
                Add the client here. After saving, you will go to the client detail page.
            </p>

            <form method="post" action="/admin_clients.php">
                <input type="hidden" name="form_name" value="create_client">
                <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">

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
                            value="<?php echo escapeHtml((string)$newClientValues['full_name']); ?>"
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
                            value="<?php echo escapeHtml((string)$newClientValues['phone']); ?>"
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
                            value="<?php echo escapeHtml((string)$newClientValues['email']); ?>"
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="preferred_contact_method" class="form-label">
                            Preferred Contact Method
                        </label>
                        <select class="form-control" id="preferred_contact_method" name="preferred_contact_method">
                            <?php foreach (getPreferredContactMethodOptions() as $methodValue => $methodLabel): ?>
                                <option
                                    value="<?php echo escapeHtml($methodValue); ?>"
                                    <?php echo (string)$newClientValues['preferred_contact_method'] === $methodValue ? 'selected' : ''; ?>
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
                            value="<?php echo escapeHtml((string)$newClientValues['street_address']); ?>"
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
                            value="<?php echo escapeHtml((string)$newClientValues['city']); ?>"
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
                            value="<?php echo escapeHtml((string)$newClientValues['state']); ?>"
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
                            value="<?php echo escapeHtml((string)$newClientValues['zip_code']); ?>"
                        >
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">
                            Client Notes
                        </label>
                        <textarea
                            class="form-control"
                            id="notes"
                            name="notes"
                            rows="3"
                        ><?php echo escapeHtml((string)$newClientValues['notes']); ?></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-light">
                        Save New Client
                    </button>
                </div>
            </form>
        </details>

        <section class="card p-4 mb-4 client-filter-panel">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label for="clientSearchInput" class="form-label">
                        Search Clients
                    </label>
                    <input
                        type="search"
                        class="form-control"
                        id="clientSearchInput"
                        placeholder="Name, phone, email, address, city..."
                        autocomplete="off"
                    >
                </div>

                <div class="col-md-6 col-lg-3">
                    <label for="clientVisibilityFilter" class="form-label">
                        Show
                    </label>
                    <select class="form-control" id="clientVisibilityFilter">
                        <option value="active" selected>Clients with active requests</option>
                        <option value="needs_attention">Clients needing attention</option>
                        <option value="no_active">Clients without active requests</option>
                        <option value="all">All clients</option>
                    </select>
                </div>

                <div class="col-md-6 col-lg-3">
                    <label for="clientSortSelect" class="form-label">
                        Sort By
                    </label>
                    <select class="form-control" id="clientSortSelect">
                        <option value="attention" selected>Most urgent first</option>
                        <option value="recent">Most recent request</option>
                        <option value="first_name">First name A-Z</option>
                        <option value="last_name">Last name A-Z</option>
                    </select>
                </div>

                <div class="col-lg-2">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-light" id="clearClientFiltersButton">
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-3 text-muted" id="clientResultCount">
                Showing clients...
            </div>
        </section>

        <section aria-label="Client cards">
            <div class="request-card-list" id="clientCardList">
                <?php if (empty($clientCards)): ?>
                    <div class="card p-4">
                        <h2 class="h5">
                            No clients found.
                        </h2>

                        <p class="mb-0 text-muted">
                            Add a new client to get started.
                        </p>
                    </div>
                <?php endif; ?>

                <?php foreach ($clientCards as $client): ?>
                    <?php
                    $clientId = (int)$client['id'];
                    $clientName = (string)$client['full_name'];
                    $clientAddress = clientFullAddress($client);
                    $activeRequestCount = (int)$client['active_request_count'];
                    $totalRequestCount = (int)$client['total_request_count'];
                    $attentionRank = (int)$client['highest_attention_rank'];
                    $customerUpdateCount = (int)$client['customer_update_count'];
                    $overdueCount = (int)$client['overdue_count'];
                    $missingNextActionCount = (int)$client['missing_next_action_count'];
                    $newCount = (int)$client['new_count'];
                    $recentSortValue = clientDateSortValue($client['most_recent_request_created_at'] ?? $client['created_at']);
                    $searchText = strtolower(
                        $clientName
                        . ' '
                        . (string)($client['phone'] ?? '')
                        . ' '
                        . (string)($client['phone_normalized'] ?? '')
                        . ' '
                        . (string)($client['email'] ?? '')
                        . ' '
                        . $clientAddress
                        . ' '
                        . (string)($client['notes'] ?? '')
                    );

                    $hasAttention = $attentionRank < 4 || $customerUpdateCount > 0 || $overdueCount > 0 || $missingNextActionCount > 0;
                    ?>

                    <article
                        class="card request-summary-card p-4"
                        data-client-card
                        data-search-text="<?php echo escapeHtml($searchText); ?>"
                        data-active-requests="<?php echo escapeHtml((string)$activeRequestCount); ?>"
                        data-needs-attention="<?php echo $hasAttention ? '1' : '0'; ?>"
                        data-urgency="<?php echo escapeHtml((string)$attentionRank); ?>"
                        data-recent="<?php echo escapeHtml((string)$recentSortValue); ?>"
                        data-first-name="<?php echo escapeHtml(clientFirstName($clientName)); ?>"
                        data-last-name="<?php echo escapeHtml(clientLastName($clientName)); ?>"
                        <?php echo $activeRequestCount > 0 ? '' : 'hidden'; ?>
                    >
                        <a
                            href="/admin_client_detail.php?id=<?php echo escapeHtml((string)$clientId); ?>"
                            class="client-card-click-area"
                            aria-label="Open client detail for <?php echo escapeHtml($clientName); ?>"
                        >
                            <div class="request-summary-header">
                                <div>
                                    <h2 class="h4 mb-1">
                                        <?php echo escapeHtml($clientName); ?>
                                    </h2>

                                    <p class="text-muted mb-0">
                                        <?php if ((string)($client['phone'] ?? '') !== ''): ?>
                                            <?php echo escapeHtml((string)$client['phone']); ?>
                                        <?php endif; ?>

                                        <?php if ((string)($client['email'] ?? '') !== ''): ?>
                                            <?php echo (string)($client['phone'] ?? '') !== '' ? ' · ' : ''; ?>
                                            <?php echo escapeHtml((string)$client['email']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="request-summary-status">
                                    <?php echo clientStatusBadge((int)$client['is_active'] === 1); ?>
                                </div>
                            </div>

                            <?php if ($clientAddress !== ''): ?>
                                <p class="request-summary-details mt-3 mb-0">
                                    <?php echo escapeHtml($clientAddress); ?>
                                </p>
                            <?php endif; ?>

                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <?php if ($customerUpdateCount > 0): ?>
                                    <span class="badge rounded-pill text-bg-info text-dark">
                                        Customer Updates: <?php echo escapeHtml((string)$customerUpdateCount); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($overdueCount > 0): ?>
                                    <span class="badge rounded-pill text-bg-danger">
                                        Overdue: <?php echo escapeHtml((string)$overdueCount); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($missingNextActionCount > 0): ?>
                                    <span class="badge rounded-pill text-bg-warning text-dark">
                                        Missing Next Action: <?php echo escapeHtml((string)$missingNextActionCount); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($newCount > 0): ?>
                                    <span class="badge rounded-pill text-bg-warning text-dark">
                                        New: <?php echo escapeHtml((string)$newCount); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($activeRequestCount === 0): ?>
                                    <span class="badge rounded-pill text-bg-secondary">
                                        No Active Requests
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="request-summary-meta mt-3">
                                <div>
                                    <strong>Active Requests:</strong>
                                    <?php echo escapeHtml((string)$activeRequestCount); ?>
                                </div>

                                <div>
                                    <strong>Total Requests:</strong>
                                    <?php echo escapeHtml((string)$totalRequestCount); ?>
                                </div>

                                <div>
                                    <strong>Last Request:</strong>
                                    <?php echo escapeHtml(clientDateDisplay($client['most_recent_request_at'])); ?>
                                </div>

                                <div>
                                    <strong>Preferred Contact:</strong>
                                    <?php echo (string)($client['preferred_contact_method'] ?? '') !== '' ? escapeHtml((string)$client['preferred_contact_method']) : '—'; ?>
                                </div>
                            </div>

                            <div class="client-status-counts mt-3">
                                <span class="client-status-count">New: <?php echo escapeHtml((string)$client['new_count']); ?></span>
                                <span class="client-status-count">Quoted: <?php echo escapeHtml((string)$client['quoted_count']); ?></span>
                                <span class="client-status-count">Scheduled: <?php echo escapeHtml((string)$client['scheduled_count']); ?></span>
                                <span class="client-status-count">In Progress: <?php echo escapeHtml((string)$client['in_progress_count']); ?></span>
                                <span class="client-status-count">Completed: <?php echo escapeHtml((string)$client['completed_count']); ?></span>
                                <span class="client-status-count">Cancelled: <?php echo escapeHtml((string)$client['cancelled_count']); ?></span>
                                <span class="client-status-count">Archived: <?php echo escapeHtml((string)$client['archived_count']); ?></span>

                                <?php if ((int)$client['other_active_count'] > 0): ?>
                                    <span class="client-status-count">Other Active: <?php echo escapeHtml((string)$client['other_active_count']); ?></span>
                                <?php endif; ?>
                            </div>
                        </a>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a href="/admin_client_detail.php?id=<?php echo escapeHtml((string)$clientId); ?>" class="btn btn-light">
                                Open Client
                            </a>

                            <?php if ((string)($client['phone'] ?? '') !== ''): ?>
                                <a href="tel:<?php echo escapeHtml(preg_replace('/\D+/', '', (string)$client['phone'])); ?>" class="btn btn-outline-light">
                                    Call
                                </a>
                            <?php endif; ?>

                            <?php if ((string)($client['email'] ?? '') !== ''): ?>
                                <a href="mailto:<?php echo escapeHtml((string)$client['email']); ?>" class="btn btn-outline-light">
                                    Email
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('clientSearchInput');
    const visibilityFilter = document.getElementById('clientVisibilityFilter');
    const sortSelect = document.getElementById('clientSortSelect');
    const clearButton = document.getElementById('clearClientFiltersButton');
    const resultCount = document.getElementById('clientResultCount');
    const cardList = document.getElementById('clientCardList');

    if (!searchInput || !visibilityFilter || !sortSelect || !clearButton || !resultCount || !cardList) {
        return;
    }

    const cards = Array.from(cardList.querySelectorAll('[data-client-card]'));

    function normalizeText(value) {
        return String(value || '').toLowerCase().trim();
    }

    function cardMatchesVisibility(card, visibilityValue) {
        const activeRequests = Number(card.dataset.activeRequests || 0);
        const needsAttention = card.dataset.needsAttention === '1';

        if (visibilityValue === 'active') {
            return activeRequests > 0;
        }

        if (visibilityValue === 'needs_attention') {
            return needsAttention;
        }

        if (visibilityValue === 'no_active') {
            return activeRequests === 0;
        }

        return true;
    }

    function compareText(firstValue, secondValue) {
        return String(firstValue || '').localeCompare(String(secondValue || ''));
    }

    function sortCards(cardsToSort, sortValue) {
        cardsToSort.sort(function (firstCard, secondCard) {
            if (sortValue === 'first_name') {
                return compareText(firstCard.dataset.firstName, secondCard.dataset.firstName);
            }

            if (sortValue === 'last_name') {
                const lastNameCompare = compareText(firstCard.dataset.lastName, secondCard.dataset.lastName);

                if (lastNameCompare !== 0) {
                    return lastNameCompare;
                }

                return compareText(firstCard.dataset.firstName, secondCard.dataset.firstName);
            }

            if (sortValue === 'recent') {
                return Number(secondCard.dataset.recent || 0) - Number(firstCard.dataset.recent || 0);
            }

            const urgencyCompare = Number(firstCard.dataset.urgency || 9) - Number(secondCard.dataset.urgency || 9);

            if (urgencyCompare !== 0) {
                return urgencyCompare;
            }

            return Number(secondCard.dataset.recent || 0) - Number(firstCard.dataset.recent || 0);
        });
    }

    function updateClientCards() {
        const searchValue = normalizeText(searchInput.value);
        const visibilityValue = visibilityFilter.value;
        const sortValue = sortSelect.value;

        let visibleCount = 0;

        sortCards(cards, sortValue);

        cards.forEach(function (card) {
            const searchText = normalizeText(card.dataset.searchText);
            const matchesSearch = searchValue === '' || searchText.includes(searchValue);
            const matchesVisibility = cardMatchesVisibility(card, visibilityValue);
            const shouldShow = matchesSearch && matchesVisibility;

            card.hidden = !shouldShow;

            if (shouldShow) {
                visibleCount++;
            }

            cardList.appendChild(card);
        });

        resultCount.textContent = 'Showing ' + visibleCount + ' of ' + cards.length + ' clients.';
    }

    searchInput.addEventListener('input', updateClientCards);
    visibilityFilter.addEventListener('change', updateClientCards);
    sortSelect.addEventListener('change', updateClientCards);

    clearButton.addEventListener('click', function () {
        searchInput.value = '';
        visibilityFilter.value = 'active';
        sortSelect.value = 'attention';
        updateClientCards();
    });

    updateClientCards();
});
</script>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';