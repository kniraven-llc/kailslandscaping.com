<?php
declare(strict_types=1);

/*
    Admin requests page.

    This page shows request/job cards.

    It handles:
    - manually adding a new request/job for an existing client
    - viewing current requests
    - searching requests
    - filtering requests without page refresh
    - sorting requests without page refresh
    - opening one request detail page

    It does not handle:
    - the main attention dashboard
    - editing one request
    - request comments/timeline
    - quote/invoice creation
    - quote/invoice editing

    Those belong to:
    - /admin.php
    - /admin_request_detail.php
    - /admin_request_edit.php
    - /admin_document_edit.php
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Requests';

adminRequireLogin('Requests Login');

$messages = [];
$errors = [];

/*
    Preserve old direct links safely.

    Old links like /admin_requests.php?id=123 should now open
    the planned request detail page.
*/
$legacyRequestId = (int)($_GET['id'] ?? 0);

if ($legacyRequestId > 0) {
    redirectTo('/admin_request_detail.php?id=' . $legacyRequestId);
}

function requestListText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function requestListTextarea(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    return $value;
}

function requestListNullableString(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    return $value;
}

function requestListDateDisplay($value): string
{
    if ($value === null || trim((string)$value) === '') {
        return '—';
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return '—';
    }

    return date('M j, Y', $timestamp);
}

function requestListDateTimeDisplay($value): string
{
    if ($value === null || trim((string)$value) === '') {
        return '—';
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return '—';
    }

    return date('M j, Y g:i A', $timestamp);
}

function requestListDateSortValue($value): int
{
    if ($value === null || trim((string)$value) === '') {
        return 0;
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return 0;
    }

    return $timestamp;
}

function requestListMoney($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return '$' . number_format((float)$value, 2);
}

function requestListRequestNumber(array $request): string
{
    $requestNumber = trim((string)($request['request_number'] ?? ''));

    if ($requestNumber !== '') {
        return $requestNumber;
    }

    return 'Request #' . (string)($request['id'] ?? '');
}

function requestListServiceName(array $request): string
{
    $customServiceName = trim((string)($request['custom_service_name'] ?? ''));

    if ($customServiceName !== '') {
        return $customServiceName;
    }

    $serviceTitle = trim((string)($request['service_title'] ?? ''));

    if ($serviceTitle !== '') {
        return $serviceTitle;
    }

    return 'Outdoor Service';
}

function requestListJobTitle(array $request): string
{
    $jobTitle = trim((string)($request['job_title'] ?? ''));

    if ($jobTitle !== '') {
        return $jobTitle;
    }

    return requestListServiceName($request);
}

function requestListStatusClass(string $status): string
{
    $statusClass = strtolower($status);
    $statusClass = str_replace(' ', '-', $statusClass);
    $statusClass = preg_replace('/[^a-z0-9-]/', '', $statusClass);

    return 'request-status request-status-' . $statusClass;
}

function requestListRequestAddress(array $request): string
{
    $addressParts = [];

    $streetAddress = trim((string)($request['property_address'] ?? ''));

    if ($streetAddress !== '') {
        $addressParts[] = $streetAddress;
    }

    $cityStateZip = trim(
        trim((string)($request['property_city'] ?? ''))
        . ', '
        . trim((string)($request['property_state'] ?? ''))
        . ' '
        . trim((string)($request['property_zip_code'] ?? ''))
    );

    $cityStateZip = trim($cityStateZip, ', ');

    if ($cityStateZip !== '') {
        $addressParts[] = $cityStateZip;
    }

    return implode(' · ', $addressParts);
}

function requestListIsInactive(array $request): bool
{
    $status = (string)($request['request_status'] ?? '');

    if ((int)($request['is_archived'] ?? 0) === 1) {
        return true;
    }

    return in_array($status, ['Completed', 'Cancelled', 'Archived'], true);
}

function requestListAttentionBadges(array $request): array
{
    $badges = [];

    if (requestListIsInactive($request)) {
        $badges[] = [
            'label' => 'Inactive',
            'class' => 'text-bg-secondary',
        ];

        return $badges;
    }

    $customerUpdateCount = (int)($request['customer_update_count'] ?? 0);

    if ($customerUpdateCount > 0) {
        $badges[] = [
            'label' => 'Customer Update',
            'class' => 'text-bg-info text-dark',
        ];
    }

    $nextActionDueAt = (string)($request['next_action_due_at'] ?? '');
    $scheduledStart = (string)($request['scheduled_start'] ?? '');
    $completedAt = (string)($request['completed_at'] ?? '');
    $status = (string)($request['request_status'] ?? '');

    if ($nextActionDueAt !== '' && strtotime($nextActionDueAt) !== false && strtotime($nextActionDueAt) <= time()) {
        $badges[] = [
            'label' => 'Overdue',
            'class' => 'text-bg-danger',
        ];
    }

    if (
        $status === 'Scheduled'
        && $scheduledStart !== ''
        && $completedAt === ''
        && strtotime($scheduledStart) !== false
        && strtotime($scheduledStart) <= time()
    ) {
        $badges[] = [
            'label' => 'Scheduled Time Passed',
            'class' => 'text-bg-danger',
        ];
    }

    if ($status === 'New') {
        $badges[] = [
            'label' => 'New',
            'class' => 'text-bg-warning text-dark',
        ];
    }

    if (trim((string)($request['next_action'] ?? '')) === '') {
        $badges[] = [
            'label' => 'No Next Action',
            'class' => 'text-bg-warning text-dark',
        ];
    }

    return $badges;
}

function requestListHasAttention(array $request): bool
{
    if (requestListIsInactive($request)) {
        return false;
    }

    foreach (requestListAttentionBadges($request) as $badge) {
        if ((string)$badge['label'] !== '') {
            return true;
        }
    }

    return false;
}

function requestListAttentionRank(array $request): int
{
    if (requestListIsInactive($request)) {
        return 9;
    }

    if ((int)($request['customer_update_count'] ?? 0) > 0) {
        return 0;
    }

    $nextActionDueAt = (string)($request['next_action_due_at'] ?? '');
    $scheduledStart = (string)($request['scheduled_start'] ?? '');
    $completedAt = (string)($request['completed_at'] ?? '');
    $status = (string)($request['request_status'] ?? '');

    if ($nextActionDueAt !== '' && strtotime($nextActionDueAt) !== false && strtotime($nextActionDueAt) <= time()) {
        return 1;
    }

    if (
        $status === 'Scheduled'
        && $scheduledStart !== ''
        && $completedAt === ''
        && strtotime($scheduledStart) !== false
        && strtotime($scheduledStart) <= time()
    ) {
        return 1;
    }

    if ($status === 'New') {
        return 2;
    }

    if (trim((string)($request['next_action'] ?? '')) === '') {
        return 3;
    }

    return 4;
}

function getRequestListStatusOptions(array $requests): array
{
    $statuses = [];

    foreach ($requests as $request) {
        $status = trim((string)($request['request_status'] ?? ''));

        if ($status !== '') {
            $statuses[$status] = $status;
        }
    }

    $preferredOrder = [
        'New',
        'Quoted',
        'Scheduled',
        'In Progress',
        'Completed',
        'Cancelled',
        'Archived',
    ];

    $orderedStatuses = [];

    foreach ($preferredOrder as $status) {
        if (isset($statuses[$status])) {
            $orderedStatuses[] = $status;
            unset($statuses[$status]);
        }
    }

    foreach ($statuses as $status) {
        $orderedStatuses[] = $status;
    }

    return $orderedStatuses;
}

function getRequestListServiceOptions(array $requests): array
{
    $services = [];

    foreach ($requests as $request) {
        $serviceName = requestListServiceName($request);

        if ($serviceName !== '') {
            $services[$serviceName] = $serviceName;
        }
    }

    natcasesort($services);

    return array_values($services);
}

function getRequestListCityOptions(array $requests): array
{
    $cities = [];

    foreach ($requests as $request) {
        $city = trim((string)($request['property_city'] ?? ''));

        if ($city !== '') {
            $cities[$city] = $city;
        }
    }

    natcasesort($cities);

    return array_values($cities);
}

function requestListGenerateRequestNumber(mysqli $connection): string
{
    $datePart = date('Ymd');

    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $randomPart = strtoupper(bin2hex(random_bytes(3)));
        $requestNumber = 'KR-' . $datePart . '-' . $randomPart;

        $statement = $connection->prepare(
            'SELECT id
             FROM quote_requests
             WHERE request_number = ?
             LIMIT 1'
        );

        $statement->bind_param('s', $requestNumber);
        $statement->execute();

        $result = $statement->get_result();

        if (!$result->fetch_assoc()) {
            return $requestNumber;
        }
    }

    return 'KR-' . $datePart . '-' . strtoupper(bin2hex(random_bytes(5)));
}

function requestListGeneratePublicAccessToken(mysqli $connection): string
{
    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $token = bin2hex(random_bytes(24));

        $statement = $connection->prepare(
            'SELECT id
             FROM quote_requests
             WHERE public_access_token = ?
             LIMIT 1'
        );

        $statement->bind_param('s', $token);
        $statement->execute();

        $result = $statement->get_result();

        if (!$result->fetch_assoc()) {
            return $token;
        }
    }

    return bin2hex(random_bytes(32));
}

function requestListGetClientsForSelect(): array
{
    $connection = getDatabaseConnection();

    $sql = '
        SELECT
            id,
            full_name,
            phone,
            email,
            street_address,
            city,
            state,
            zip_code
        FROM clients
        WHERE is_active = 1
        ORDER BY full_name ASC
        LIMIT 1000
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

function requestListGetServicesForSelect(): array
{
    $connection = getDatabaseConnection();

    $sql = '
        SELECT
            id,
            service_title
        FROM services
        WHERE is_active = 1
        ORDER BY sort_order ASC, service_title ASC
        LIMIT 500
    ';

    $result = $connection->query($sql);

    if (!$result) {
        return [];
    }

    $services = [];

    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    return $services;
}

function requestListClientSelectLabel(array $client): string
{
    $label = trim((string)($client['full_name'] ?? ''));

    $details = [];

    if (trim((string)($client['phone'] ?? '')) !== '') {
        $details[] = trim((string)$client['phone']);
    }

    if (trim((string)($client['email'] ?? '')) !== '') {
        $details[] = trim((string)$client['email']);
    }

    $city = trim((string)($client['city'] ?? ''));

    if ($city !== '') {
        $details[] = $city;
    }

    if (!empty($details)) {
        $label .= ' — ' . implode(' · ', $details);
    }

    return $label;
}

function defaultNewRequestValues(): array
{
    return [
        'client_id' => '',
        'job_title' => '',
        'requested_service_id' => '',
        'custom_service_name' => '',
        'project_details' => '',
        'property_address' => '',
        'property_city' => '',
        'property_state' => 'WI',
        'property_zip_code' => '',
        'next_action' => 'Review request and contact customer',
        'next_action_due_at' => date('Y-m-d\TH:i', strtotime('+1 day')),
        'next_action_notes' => '',
        'internal_notes' => '',
    ];
}

function requestListSaveNewRequestRecord(array $postedData, array &$errors): int
{
    $connection = getDatabaseConnection();

    $clientId = (int)($postedData['client_id'] ?? 0);
    $jobTitle = requestListNullableString(requestListText((string)($postedData['job_title'] ?? '')));
    $requestedServiceIdRaw = trim((string)($postedData['requested_service_id'] ?? ''));
    $requestedServiceId = $requestedServiceIdRaw === '' ? null : (int)$requestedServiceIdRaw;
    $customServiceName = requestListNullableString(requestListText((string)($postedData['custom_service_name'] ?? '')));
    $projectDetails = requestListNullableString(requestListTextarea((string)($postedData['project_details'] ?? '')));
    $propertyAddress = requestListNullableString(requestListText((string)($postedData['property_address'] ?? '')));
    $propertyCity = requestListNullableString(requestListText((string)($postedData['property_city'] ?? '')));
    $propertyState = requestListNullableString(requestListText((string)($postedData['property_state'] ?? 'WI'))) ?? 'WI';
    $propertyZipCode = requestListNullableString(requestListText((string)($postedData['property_zip_code'] ?? '')));
    $nextAction = requestListNullableString(requestListText((string)($postedData['next_action'] ?? '')));
    $nextActionDueAtRaw = trim((string)($postedData['next_action_due_at'] ?? ''));
    $nextActionNotes = requestListNullableString(requestListTextarea((string)($postedData['next_action_notes'] ?? '')));
    $internalNotes = requestListNullableString(requestListTextarea((string)($postedData['internal_notes'] ?? '')));

    $nextActionDueAt = null;

    if ($clientId <= 0) {
        $errors[] = 'Choose a client for this request.';
    } else {
        $clientStatement = $connection->prepare(
            'SELECT id
             FROM clients
             WHERE id = ?
               AND is_active = 1
             LIMIT 1'
        );

        $clientStatement->bind_param('i', $clientId);
        $clientStatement->execute();

        $clientResult = $clientStatement->get_result();

        if (!$clientResult->fetch_assoc()) {
            $errors[] = 'The selected client was not found or is inactive.';
        }
    }

    if ($jobTitle === null) {
        $errors[] = 'Enter a job title.';
    }

    if ($requestedServiceId !== null && $requestedServiceId <= 0) {
        $errors[] = 'Selected service is invalid.';
    }

    if ($projectDetails === null) {
        $errors[] = 'Enter request details.';
    }

    if ($nextActionDueAtRaw !== '') {
        $timestamp = strtotime($nextActionDueAtRaw);

        if ($timestamp === false) {
            $errors[] = 'Next action due date is invalid.';
        } else {
            $nextActionDueAt = date('Y-m-d H:i:s', $timestamp);
        }
    }

    if (!empty($errors)) {
        return 0;
    }

    $requestNumber = requestListGenerateRequestNumber($connection);
    $publicAccessToken = requestListGeneratePublicAccessToken($connection);
    $requestStatus = 'New';
    $requestSource = 'Admin';
    $isArchived = 0;

    $statement = $connection->prepare(
        'INSERT INTO quote_requests
            (
                request_number,
                public_access_token,
                client_id,
                request_status,
                request_source,
                job_title,
                requested_service_id,
                custom_service_name,
                project_details,
                property_address,
                property_city,
                property_state,
                property_zip_code,
                next_action,
                next_action_due_at,
                next_action_notes,
                internal_notes,
                is_archived
            )
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $statement->bind_param(
        'ssisssissssssssssi',
        $requestNumber,
        $publicAccessToken,
        $clientId,
        $requestStatus,
        $requestSource,
        $jobTitle,
        $requestedServiceId,
        $customServiceName,
        $projectDetails,
        $propertyAddress,
        $propertyCity,
        $propertyState,
        $propertyZipCode,
        $nextAction,
        $nextActionDueAt,
        $nextActionNotes,
        $internalNotes,
        $isArchived
    );

    $statement->execute();

    return (int)$connection->insert_id;
}

function getRequestListRows(): array
{
    $connection = getDatabaseConnection();

    $sql = '
        SELECT
            qr.id,
            qr.request_number,
            qr.client_id,
            qr.request_status,
            qr.is_archived,
            qr.job_title,
            qr.request_source,
            qr.requested_service_id,
            qr.custom_service_name,
            qr.project_details,
            qr.property_address,
            qr.property_city,
            qr.property_state,
            qr.property_zip_code,
            qr.scheduled_start,
            qr.scheduled_end,
            qr.completed_at,
            qr.quoted_price,
            qr.final_price,
            qr.next_action,
            qr.next_action_due_at,
            qr.next_action_notes,
            qr.last_admin_reviewed_at,
            qr.last_queue_action_at,
            qr.created_at,
            qr.updated_at,

            c.full_name,
            c.phone,
            c.email,

            s.service_title,

            (
                SELECT COUNT(*)
                FROM quote_request_comments qrc
                WHERE qrc.quote_request_id = qr.id
            ) AS comment_count,

            (
                SELECT COUNT(*)
                FROM quote_request_comments qrc
                WHERE qrc.quote_request_id = qr.id
                  AND qrc.author_type = "customer"
                  AND (
                      qr.last_admin_reviewed_at IS NULL
                      OR qrc.created_at > qr.last_admin_reviewed_at
                  )
            ) AS customer_update_count,

            (
                SELECT COUNT(*)
                FROM client_documents cd
                WHERE cd.quote_request_id = qr.id
                  AND cd.document_type = "quote"
            ) AS quote_count,

            (
                SELECT COUNT(*)
                FROM client_documents cd
                WHERE cd.quote_request_id = qr.id
                  AND cd.document_type IN ("receipt", "invoice")
            ) AS invoice_count

        FROM quote_requests qr
        INNER JOIN clients c
            ON c.id = qr.client_id
        LEFT JOIN services s
            ON s.id = qr.requested_service_id
        ORDER BY
            qr.is_archived ASC,
            CASE
                WHEN qr.request_status IN ("Completed", "Cancelled", "Archived") THEN 1
                ELSE 0
            END ASC,
            qr.created_at DESC,
            qr.id DESC
        LIMIT 750
    ';

    $result = $connection->query($sql);

    if (!$result) {
        return [];
    }

    $requests = [];

    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    return $requests;
}

function requestListStats(array $requests): array
{
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'needs_attention' => 0,
        'customer_updates' => 0,
        'overdue' => 0,
        'missing_next_action' => 0,
    ];

    foreach ($requests as $request) {
        $stats['total']++;

        $isInactive = requestListIsInactive($request);

        if ($isInactive) {
            $stats['inactive']++;
        } else {
            $stats['active']++;
        }

        if (requestListHasAttention($request)) {
            $stats['needs_attention']++;
        }

        if ((int)($request['customer_update_count'] ?? 0) > 0) {
            $stats['customer_updates']++;
        }

        if (!$isInactive) {
            $nextActionDueAt = (string)($request['next_action_due_at'] ?? '');
            $scheduledStart = (string)($request['scheduled_start'] ?? '');
            $completedAt = (string)($request['completed_at'] ?? '');
            $status = (string)($request['request_status'] ?? '');

            if ($nextActionDueAt !== '' && strtotime($nextActionDueAt) !== false && strtotime($nextActionDueAt) <= time()) {
                $stats['overdue']++;
            }

            if (
                $status === 'Scheduled'
                && $scheduledStart !== ''
                && $completedAt === ''
                && strtotime($scheduledStart) !== false
                && strtotime($scheduledStart) <= time()
            ) {
                $stats['overdue']++;
            }

            if (trim((string)($request['next_action'] ?? '')) === '') {
                $stats['missing_next_action']++;
            }
        }
    }

    return $stats;
}

$requestRows = [];
$clientOptions = [];
$serviceSelectOptions = [];
$newRequestValues = defaultNewRequestValues();
$showAddRequestForm = false;

if (isset($_GET['saved'])) {
    $messages[] = 'Request saved.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formName = (string)($_POST['form_name'] ?? '');
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    } else {
        try {
            if ($formName === 'create_request') {
                $newRequestValues = array_merge($newRequestValues, [
                    'client_id' => (string)($_POST['client_id'] ?? ''),
                    'job_title' => (string)($_POST['job_title'] ?? ''),
                    'requested_service_id' => (string)($_POST['requested_service_id'] ?? ''),
                    'custom_service_name' => (string)($_POST['custom_service_name'] ?? ''),
                    'project_details' => (string)($_POST['project_details'] ?? ''),
                    'property_address' => (string)($_POST['property_address'] ?? ''),
                    'property_city' => (string)($_POST['property_city'] ?? ''),
                    'property_state' => (string)($_POST['property_state'] ?? 'WI'),
                    'property_zip_code' => (string)($_POST['property_zip_code'] ?? ''),
                    'next_action' => (string)($_POST['next_action'] ?? ''),
                    'next_action_due_at' => (string)($_POST['next_action_due_at'] ?? ''),
                    'next_action_notes' => (string)($_POST['next_action_notes'] ?? ''),
                    'internal_notes' => (string)($_POST['internal_notes'] ?? ''),
                ]);

                $requestId = requestListSaveNewRequestRecord($_POST, $errors);

                if ($requestId > 0 && empty($errors)) {
                    redirectTo('/admin_request_detail.php?id=' . $requestId . '&created=1');
                }

                $showAddRequestForm = true;
            }
        } catch (Throwable $exception) {
            $showAddRequestForm = true;

            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'The request could not be saved.';
            }
        }
    }
}

try {
    $clientOptions = requestListGetClientsForSelect();
    $serviceSelectOptions = requestListGetServicesForSelect();
    $requestRows = getRequestListRows();
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'Requests could not be loaded.';
    }
}

$statusOptions = getRequestListStatusOptions($requestRows);
$serviceOptions = getRequestListServiceOptions($requestRows);
$cityOptions = getRequestListCityOptions($requestRows);
$stats = requestListStats($requestRows);

$initialSearch = requestListText((string)($_GET['search'] ?? ''));
$initialStatus = requestListText((string)($_GET['status'] ?? ''));
$initialShow = requestListText((string)($_GET['show'] ?? 'active'));

if (!in_array($initialShow, ['active', 'needs_attention', 'inactive', 'all'], true)) {
    $initialShow = 'active';
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<style>
    .request-filter-panel {
        position: sticky;
        top: 0.5rem;
        z-index: 10;
    }

    .request-card-click-area {
        color: inherit;
        text-decoration: none;
    }

    .request-card-click-area:hover {
        color: inherit;
        text-decoration: none;
    }

    .request-status-counts {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .request-status-count {
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 999px;
        padding: 0.25rem 0.65rem;
        font-size: 0.875rem;
    }

    .request-client-select-option-help {
        color: var(--bs-secondary-color, #adb5bd);
    }
</style>

<main class="site-section">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('requests'); ?>

        <div class="mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                Admin
            </p>

            <h1 class="fw-bold">
                Requests
            </h1>

            <p class="text-muted mb-0">
                Add requests for existing clients, find current requests, sort by urgency, and open the specific request that needs work.
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

        <details class="card p-4 mb-4" id="add-request" <?php echo $showAddRequestForm ? 'open' : ''; ?>>
            <summary class="h4 mb-3">
                Add New Request / Job
            </summary>

            <p class="text-muted">
                Create a new request for an existing client. To add a brand-new customer first, use the client page.
            </p>

            <?php if (empty($clientOptions)): ?>
                <div class="alert alert-warning">
                    No active clients are available. Add a client before creating a request.
                </div>

                <a href="/admin_clients.php#add-client" class="btn btn-light">
                    Add New Client
                </a>
            <?php else: ?>
                <form method="post" action="/admin_requests.php#add-request">
                    <input type="hidden" name="form_name" value="create_request">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">

                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label for="client_id" class="form-label">
                                Client
                            </label>

                            <select class="form-control" id="client_id" name="client_id" required>
                                <option value="">Choose a client...</option>

                                <?php foreach ($clientOptions as $clientOption): ?>
                                    <option
                                        value="<?php echo escapeHtml((string)$clientOption['id']); ?>"
                                        <?php echo (string)$newRequestValues['client_id'] === (string)$clientOption['id'] ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeHtml(requestListClientSelectLabel($clientOption)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="form-text">
                                Requests must be tied to a client.
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <label for="job_title" class="form-label">
                                Job Title
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="job_title"
                                name="job_title"
                                value="<?php echo escapeHtml((string)$newRequestValues['job_title']); ?>"
                                placeholder="Example: Spring yard cleanup"
                                required
                            >
                        </div>

                        <div class="col-lg-6">
                            <label for="requested_service_id" class="form-label">
                                Service
                            </label>

                            <select class="form-control" id="requested_service_id" name="requested_service_id">
                                <option value="">No service selected</option>

                                <?php foreach ($serviceSelectOptions as $serviceOption): ?>
                                    <option
                                        value="<?php echo escapeHtml((string)$serviceOption['id']); ?>"
                                        <?php echo (string)$newRequestValues['requested_service_id'] === (string)$serviceOption['id'] ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeHtml((string)$serviceOption['service_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="form-text">
                                Use a listed service when possible.
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <label for="custom_service_name" class="form-label">
                                Custom Service Name
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="custom_service_name"
                                name="custom_service_name"
                                value="<?php echo escapeHtml((string)$newRequestValues['custom_service_name']); ?>"
                                placeholder="Example: Brush removal"
                            >

                            <div class="form-text">
                                Use this when the service does not fit the saved service list.
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="project_details" class="form-label">
                                Request Details
                            </label>

                            <textarea
                                class="form-control"
                                id="project_details"
                                name="project_details"
                                rows="4"
                                required
                            ><?php echo escapeHtml((string)$newRequestValues['project_details']); ?></textarea>
                        </div>

                        <div class="col-lg-6">
                            <label for="property_address" class="form-label">
                                Job Street Address
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="property_address"
                                name="property_address"
                                value="<?php echo escapeHtml((string)$newRequestValues['property_address']); ?>"
                            >
                        </div>

                        <div class="col-md-5 col-lg-3">
                            <label for="property_city" class="form-label">
                                City
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="property_city"
                                name="property_city"
                                value="<?php echo escapeHtml((string)$newRequestValues['property_city']); ?>"
                            >
                        </div>

                        <div class="col-md-3 col-lg-1">
                            <label for="property_state" class="form-label">
                                State
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="property_state"
                                name="property_state"
                                value="<?php echo escapeHtml((string)$newRequestValues['property_state']); ?>"
                            >
                        </div>

                        <div class="col-md-4 col-lg-2">
                            <label for="property_zip_code" class="form-label">
                                ZIP
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="property_zip_code"
                                name="property_zip_code"
                                value="<?php echo escapeHtml((string)$newRequestValues['property_zip_code']); ?>"
                            >
                        </div>

                        <div class="col-lg-6">
                            <label for="next_action" class="form-label">
                                Next Action
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="next_action"
                                name="next_action"
                                value="<?php echo escapeHtml((string)$newRequestValues['next_action']); ?>"
                            >

                            <div class="form-text">
                                This keeps the request from being forgotten.
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <label for="next_action_due_at" class="form-label">
                                Next Action Due
                            </label>

                            <input
                                type="datetime-local"
                                class="form-control"
                                id="next_action_due_at"
                                name="next_action_due_at"
                                value="<?php echo escapeHtml((string)$newRequestValues['next_action_due_at']); ?>"
                            >
                        </div>

                        <div class="col-lg-6">
                            <label for="next_action_notes" class="form-label">
                                Next Action Notes
                            </label>

                            <textarea
                                class="form-control"
                                id="next_action_notes"
                                name="next_action_notes"
                                rows="3"
                            ><?php echo escapeHtml((string)$newRequestValues['next_action_notes']); ?></textarea>
                        </div>

                        <div class="col-lg-6">
                            <label for="internal_notes" class="form-label">
                                Internal Admin Notes
                            </label>

                            <textarea
                                class="form-control"
                                id="internal_notes"
                                name="internal_notes"
                                rows="3"
                            ><?php echo escapeHtml((string)$newRequestValues['internal_notes']); ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-light">
                            Save New Request
                        </button>

                        <a href="/admin_clients.php#add-client" class="btn btn-outline-light">
                            Add New Client First
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </details>

        <section class="card p-4 mb-4">
            <div class="request-status-counts">
                <span class="request-status-count">Active: <?php echo escapeHtml((string)$stats['active']); ?></span>
                <span class="request-status-count">Needs Attention: <?php echo escapeHtml((string)$stats['needs_attention']); ?></span>
                <span class="request-status-count">Customer Updates: <?php echo escapeHtml((string)$stats['customer_updates']); ?></span>
                <span class="request-status-count">Overdue: <?php echo escapeHtml((string)$stats['overdue']); ?></span>
                <span class="request-status-count">Missing Next Action: <?php echo escapeHtml((string)$stats['missing_next_action']); ?></span>
                <span class="request-status-count">Inactive: <?php echo escapeHtml((string)$stats['inactive']); ?></span>
                <span class="request-status-count">Total: <?php echo escapeHtml((string)$stats['total']); ?></span>
            </div>
        </section>

        <section class="card p-4 mb-4 request-filter-panel">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label for="requestSearchInput" class="form-label">
                        Search Requests
                    </label>
                    <input
                        type="search"
                        class="form-control"
                        id="requestSearchInput"
                        placeholder="Request number, client, phone, email, service, city..."
                        value="<?php echo escapeHtml($initialSearch); ?>"
                        autocomplete="off"
                    >
                </div>

                <div class="col-md-6 col-lg-2">
                    <label for="requestShowFilter" class="form-label">
                        Show
                    </label>
                    <select class="form-control" id="requestShowFilter">
                        <option value="active" <?php echo $initialShow === 'active' ? 'selected' : ''; ?>>
                            Active Requests
                        </option>
                        <option value="needs_attention" <?php echo $initialShow === 'needs_attention' ? 'selected' : ''; ?>>
                            Needs Attention
                        </option>
                        <option value="inactive" <?php echo $initialShow === 'inactive' ? 'selected' : ''; ?>>
                            Completed / Inactive
                        </option>
                        <option value="all" <?php echo $initialShow === 'all' ? 'selected' : ''; ?>>
                            All Requests
                        </option>
                    </select>
                </div>

                <div class="col-md-6 col-lg-2">
                    <label for="requestStatusFilter" class="form-label">
                        Status
                    </label>
                    <select class="form-control" id="requestStatusFilter">
                        <option value="">All Statuses</option>
                        <?php foreach ($statusOptions as $statusOption): ?>
                            <option
                                value="<?php echo escapeHtml($statusOption); ?>"
                                <?php echo $initialStatus === $statusOption ? 'selected' : ''; ?>
                            >
                                <?php echo escapeHtml($statusOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 col-lg-2">
                    <label for="requestServiceFilter" class="form-label">
                        Service
                    </label>
                    <select class="form-control" id="requestServiceFilter">
                        <option value="">All Services</option>
                        <?php foreach ($serviceOptions as $serviceOption): ?>
                            <option value="<?php echo escapeHtml($serviceOption); ?>">
                                <?php echo escapeHtml($serviceOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 col-lg-2">
                    <label for="requestCityFilter" class="form-label">
                        City
                    </label>
                    <select class="form-control" id="requestCityFilter">
                        <option value="">All Cities</option>
                        <?php foreach ($cityOptions as $cityOption): ?>
                            <option value="<?php echo escapeHtml($cityOption); ?>">
                                <?php echo escapeHtml($cityOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 col-lg-3">
                    <label for="requestSortSelect" class="form-label">
                        Sort By
                    </label>
                    <select class="form-control" id="requestSortSelect">
                        <option value="attention" selected>Most urgent first</option>
                        <option value="created">Newest request first</option>
                        <option value="scheduled">Scheduled date</option>
                        <option value="client">Client A-Z</option>
                    </select>
                </div>

                <div class="col-md-6 col-lg-3">
                    <button type="button" class="btn btn-outline-light w-100" id="clearRequestFiltersButton">
                        Clear Filters
                    </button>
                </div>

                <div class="col-lg-6">
                    <div class="text-muted" id="requestResultCount">
                        Showing requests...
                    </div>
                </div>
            </div>
        </section>

        <section aria-label="Request cards">
            <div class="request-card-list" id="requestCardList">
                <?php if (empty($requestRows)): ?>
                    <div class="card p-4">
                        <h2 class="h5">
                            No requests found.
                        </h2>

                        <p class="text-muted mb-0">
                            Requests will appear here after customers submit the public form or after requests are created.
                        </p>
                    </div>
                <?php endif; ?>

                <?php foreach ($requestRows as $request): ?>
                    <?php
                    $requestId = (int)$request['id'];
                    $requestNumber = requestListRequestNumber($request);
                    $serviceName = requestListServiceName($request);
                    $jobTitle = requestListJobTitle($request);
                    $status = trim((string)($request['request_status'] ?? 'New'));
                    $clientName = trim((string)($request['full_name'] ?? 'Unknown Client'));
                    $requestAddress = requestListRequestAddress($request);
                    $nextAction = trim((string)($request['next_action'] ?? ''));
                    $price = (float)($request['final_price'] ?? 0.00);

                    if ($price <= 0) {
                        $price = (float)($request['quoted_price'] ?? 0.00);
                    }

                    $isInactive = requestListIsInactive($request);
                    $hasAttention = requestListHasAttention($request);
                    $attentionRank = requestListAttentionRank($request);
                    $createdSortValue = requestListDateSortValue($request['created_at'] ?? null);
                    $scheduledSortValue = requestListDateSortValue($request['scheduled_start'] ?? null);
                    $badges = requestListAttentionBadges($request);
                    $detailUrl = '/admin_request_detail.php?id=' . urlencode((string)$requestId);

                    $searchText = strtolower(
                        $requestNumber
                        . ' '
                        . $jobTitle
                        . ' '
                        . $serviceName
                        . ' '
                        . $status
                        . ' '
                        . $clientName
                        . ' '
                        . (string)($request['phone'] ?? '')
                        . ' '
                        . (string)($request['email'] ?? '')
                        . ' '
                        . $requestAddress
                        . ' '
                        . (string)($request['project_details'] ?? '')
                        . ' '
                        . (string)($request['next_action'] ?? '')
                    );
                    ?>

                    <article
                        class="card request-summary-card p-4"
                        data-request-card
                        data-search-text="<?php echo escapeHtml($searchText); ?>"
                        data-status="<?php echo escapeHtml($status); ?>"
                        data-service="<?php echo escapeHtml($serviceName); ?>"
                        data-city="<?php echo escapeHtml(trim((string)($request['property_city'] ?? ''))); ?>"
                        data-is-inactive="<?php echo $isInactive ? '1' : '0'; ?>"
                        data-needs-attention="<?php echo $hasAttention ? '1' : '0'; ?>"
                        data-urgency="<?php echo escapeHtml((string)$attentionRank); ?>"
                        data-created="<?php echo escapeHtml((string)$createdSortValue); ?>"
                        data-scheduled="<?php echo escapeHtml((string)$scheduledSortValue); ?>"
                        data-client="<?php echo escapeHtml(strtolower($clientName)); ?>"
                        <?php echo $isInactive ? 'hidden' : ''; ?>
                    >
                        <a
                            href="<?php echo escapeHtml($detailUrl); ?>"
                            class="request-card-click-area"
                            aria-label="Open request detail for <?php echo escapeHtml($requestNumber); ?>"
                        >
                            <div class="request-summary-header">
                                <div>
                                    <p class="kails-text-yellow fw-bold text-uppercase mb-1">
                                        <?php echo escapeHtml($requestNumber); ?>
                                    </p>

                                    <h2 class="h4 mb-1">
                                        <?php echo escapeHtml($jobTitle); ?>
                                    </h2>

                                    <p class="text-muted mb-0">
                                        <?php echo escapeHtml($clientName); ?>
                                        · <?php echo escapeHtml($serviceName); ?>
                                        <?php if ((string)($request['phone'] ?? '') !== ''): ?>
                                            · <?php echo escapeHtml((string)$request['phone']); ?>
                                        <?php endif; ?>
                                        <?php if ((string)($request['email'] ?? '') !== ''): ?>
                                            · <?php echo escapeHtml((string)$request['email']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="request-summary-status">
                                    <span class="<?php echo escapeHtml(requestListStatusClass($status)); ?>">
                                        <?php echo escapeHtml($status); ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($requestAddress !== ''): ?>
                                <p class="request-summary-details mt-3 mb-0">
                                    <?php echo escapeHtml($requestAddress); ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($badges)): ?>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <?php foreach ($badges as $badge): ?>
                                        <span class="badge rounded-pill <?php echo escapeHtml($badge['class']); ?>">
                                            <?php echo escapeHtml($badge['label']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="request-summary-meta mt-3">
                                <div>
                                    <strong>Next Action:</strong>
                                    <?php echo $nextAction !== '' ? escapeHtml($nextAction) : 'Needs one'; ?>
                                </div>

                                <div>
                                    <strong>Due:</strong>
                                    <?php echo escapeHtml(requestListDateTimeDisplay($request['next_action_due_at'] ?? null)); ?>
                                </div>

                                <div>
                                    <strong>Scheduled:</strong>
                                    <?php echo escapeHtml(requestListDateTimeDisplay($request['scheduled_start'] ?? null)); ?>
                                </div>

                                <div>
                                    <strong>Price:</strong>
                                    <?php echo escapeHtml(requestListMoney($price)); ?>
                                </div>
                            </div>

                            <div class="request-summary-meta mt-3">
                                <div>
                                    <strong>Comments:</strong>
                                    <?php echo escapeHtml((string)($request['comment_count'] ?? 0)); ?>
                                </div>

                                <div>
                                    <strong>Quotes:</strong>
                                    <?php echo escapeHtml((string)($request['quote_count'] ?? 0)); ?>
                                </div>

                                <div>
                                    <strong>Invoices:</strong>
                                    <?php echo escapeHtml((string)($request['invoice_count'] ?? 0)); ?>
                                </div>

                                <div>
                                    <strong>Created:</strong>
                                    <?php echo escapeHtml(requestListDateDisplay($request['created_at'] ?? null)); ?>
                                </div>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('requestSearchInput');
    const showFilter = document.getElementById('requestShowFilter');
    const statusFilter = document.getElementById('requestStatusFilter');
    const serviceFilter = document.getElementById('requestServiceFilter');
    const cityFilter = document.getElementById('requestCityFilter');
    const sortSelect = document.getElementById('requestSortSelect');
    const clearButton = document.getElementById('clearRequestFiltersButton');
    const resultCount = document.getElementById('requestResultCount');
    const cardList = document.getElementById('requestCardList');

    if (
        !searchInput
        || !showFilter
        || !statusFilter
        || !serviceFilter
        || !cityFilter
        || !sortSelect
        || !clearButton
        || !resultCount
        || !cardList
    ) {
        return;
    }

    const cards = Array.from(cardList.querySelectorAll('[data-request-card]'));

    function normalizeText(value) {
        return String(value || '').toLowerCase().trim();
    }

    function cardMatchesShowFilter(card, showValue) {
        const isInactive = card.dataset.isInactive === '1';
        const needsAttention = card.dataset.needsAttention === '1';

        if (showValue === 'active') {
            return !isInactive;
        }

        if (showValue === 'needs_attention') {
            return needsAttention;
        }

        if (showValue === 'inactive') {
            return isInactive;
        }

        return true;
    }

    function compareText(firstValue, secondValue) {
        return String(firstValue || '').localeCompare(String(secondValue || ''));
    }

    function sortCards(cardsToSort, sortValue) {
        cardsToSort.sort(function (firstCard, secondCard) {
            if (sortValue === 'created') {
                return Number(secondCard.dataset.created || 0) - Number(firstCard.dataset.created || 0);
            }

            if (sortValue === 'scheduled') {
                const firstScheduled = Number(firstCard.dataset.scheduled || 0);
                const secondScheduled = Number(secondCard.dataset.scheduled || 0);

                if (firstScheduled === 0 && secondScheduled > 0) {
                    return 1;
                }

                if (secondScheduled === 0 && firstScheduled > 0) {
                    return -1;
                }

                return firstScheduled - secondScheduled;
            }

            if (sortValue === 'client') {
                return compareText(firstCard.dataset.client, secondCard.dataset.client);
            }

            const urgencyCompare = Number(firstCard.dataset.urgency || 9) - Number(secondCard.dataset.urgency || 9);

            if (urgencyCompare !== 0) {
                return urgencyCompare;
            }

            return Number(secondCard.dataset.created || 0) - Number(firstCard.dataset.created || 0);
        });
    }

    function updateRequestCards() {
        const searchValue = normalizeText(searchInput.value);
        const showValue = showFilter.value;
        const statusValue = statusFilter.value;
        const serviceValue = serviceFilter.value;
        const cityValue = cityFilter.value;
        const sortValue = sortSelect.value;

        let visibleCount = 0;

        sortCards(cards, sortValue);

        cards.forEach(function (card) {
            const searchText = normalizeText(card.dataset.searchText);
            const matchesSearch = searchValue === '' || searchText.includes(searchValue);
            const matchesShow = cardMatchesShowFilter(card, showValue);
            const matchesStatus = statusValue === '' || card.dataset.status === statusValue;
            const matchesService = serviceValue === '' || card.dataset.service === serviceValue;
            const matchesCity = cityValue === '' || card.dataset.city === cityValue;
            const shouldShow = matchesSearch && matchesShow && matchesStatus && matchesService && matchesCity;

            card.hidden = !shouldShow;

            if (shouldShow) {
                visibleCount++;
            }

            cardList.appendChild(card);
        });

        resultCount.textContent = 'Showing ' + visibleCount + ' of ' + cards.length + ' requests.';
    }

    searchInput.addEventListener('input', updateRequestCards);
    showFilter.addEventListener('change', updateRequestCards);
    statusFilter.addEventListener('change', updateRequestCards);
    serviceFilter.addEventListener('change', updateRequestCards);
    cityFilter.addEventListener('change', updateRequestCards);
    sortSelect.addEventListener('change', updateRequestCards);

    clearButton.addEventListener('click', function () {
        searchInput.value = '';
        showFilter.value = 'active';
        statusFilter.value = '';
        serviceFilter.value = '';
        cityFilter.value = '';
        sortSelect.value = 'attention';
        updateRequestCards();
    });

    updateRequestCards();
});
</script>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';