<?php
declare(strict_types=1);

/*
    Admin request edit page.

    This page edits one existing request/job.

    It handles:
    - request status
    - request service/title/details
    - request property address
    - schedule and completed date
    - price fields
    - next action fields
    - public/internal notes
    - archive flag

    It does not handle:
    - comments/timeline
    - customer access link
    - quote/invoice creation
    - quote/invoice editing
    - client editing

    Those belong to:
    - /admin_request_detail.php?id=123
    - /admin_document_edit.php
    - /admin_client_edit.php?id=123
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Edit Request';

adminRequireLogin('Edit Request Login');

$messages = [];
$errors = [];

function requestEditText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function requestEditTextarea(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    return $value;
}

function requestEditNullableString(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    return $value;
}

function requestEditNullableMoney(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $value = str_replace(['$', ','], '', $value);

    if (!is_numeric($value)) {
        return null;
    }

    return number_format((float)$value, 2, '.', '');
}

function requestEditDateTimeLocalValue($value): string
{
    if ($value === null || trim((string)$value) === '') {
        return '';
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
}

function requestEditDateDisplay($value): string
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

function requestEditDateTimeDisplay($value): string
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

function requestEditMoneyDisplay($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    return number_format((float)$value, 2, '.', '');
}

function requestEditRequestNumber(array $request): string
{
    $requestNumber = trim((string)($request['request_number'] ?? ''));

    if ($requestNumber !== '') {
        return $requestNumber;
    }

    return 'Request #' . (string)($request['id'] ?? '');
}

function requestEditServiceName(array $request): string
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

function requestEditJobTitle(array $request): string
{
    $jobTitle = trim((string)($request['job_title'] ?? ''));

    if ($jobTitle !== '') {
        return $jobTitle;
    }

    return requestEditServiceName($request);
}

function requestEditFullAddress(array $request): string
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

function requestEditStatusClass(string $status): string
{
    $statusClass = strtolower($status);
    $statusClass = str_replace(' ', '-', $statusClass);
    $statusClass = preg_replace('/[^a-z0-9-]/', '', $statusClass);

    return 'request-status request-status-' . $statusClass;
}

function requestEditSelectedValue(string $currentValue, string $optionValue): string
{
    return $currentValue === $optionValue ? 'selected' : '';
}

function requestEditCheckedValue(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function requestEditDateTimeToDatabase(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function getRequestEditPreferredContactMethodOptions(): array
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

function getRequestEditStatusOptions(): array
{
    $connection = getDatabaseConnection();

    try {
        $result = $connection->query(
            'SELECT
                status_name,
                status_label
             FROM request_status_rules
             WHERE is_active = 1
             ORDER BY sort_order ASC, status_name ASC'
        );

        if ($result) {
            $statuses = [];

            while ($row = $result->fetch_assoc()) {
                $statuses[] = [
                    'value' => (string)$row['status_name'],
                    'label' => (string)($row['status_label'] ?? $row['status_name']),
                ];
            }

            if (!empty($statuses)) {
                return $statuses;
            }
        }
    } catch (Throwable $exception) {
        // Use fallback statuses below.
    }

    return [
        ['value' => 'New', 'label' => 'New'],
        ['value' => 'Quoted', 'label' => 'Quoted'],
        ['value' => 'Scheduled', 'label' => 'Scheduled'],
        ['value' => 'In Progress', 'label' => 'In Progress'],
        ['value' => 'Completed', 'label' => 'Completed'],
        ['value' => 'Cancelled', 'label' => 'Cancelled'],
        ['value' => 'Archived', 'label' => 'Archived'],
    ];
}

function getRequestEditClients(): array
{
    $connection = getDatabaseConnection();

    $result = $connection->query(
        'SELECT
            id,
            full_name,
            phone,
            email,
            city,
            is_active
         FROM clients
         ORDER BY is_active DESC, full_name ASC
         LIMIT 1000'
    );

    if (!$result) {
        return [];
    }

    $clients = [];

    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }

    return $clients;
}

function getRequestEditServices(): array
{
    $connection = getDatabaseConnection();

    $result = $connection->query(
        'SELECT
            id,
            service_title,
            is_active,
            sort_order
         FROM services
         ORDER BY is_active DESC, sort_order ASC, service_title ASC'
    );

    if (!$result) {
        return [];
    }

    $services = [];

    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    return $services;
}

function getRequestEditRecord(int $requestId): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            qr.id,
            qr.request_number,
            qr.public_access_token,
            qr.client_id,
            qr.request_status,
            qr.is_archived,
            qr.job_title,
            qr.request_source,
            qr.requested_service_id,
            qr.custom_service_name,
            qr.project_details,
            qr.public_notes,
            qr.internal_notes,
            qr.property_address,
            qr.property_city,
            qr.property_state,
            qr.property_zip_code,
            qr.preferred_contact_method,
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
                FROM client_documents cd
                WHERE cd.quote_request_id = qr.id
            ) AS document_count

         FROM quote_requests qr
         INNER JOIN clients c
            ON c.id = qr.client_id
         LEFT JOIN services s
            ON s.id = qr.requested_service_id
         WHERE qr.id = ?
         LIMIT 1'
    );

    $statement->bind_param('i', $requestId);
    $statement->execute();

    $result = $statement->get_result();
    $request = $result->fetch_assoc();

    if (!$request) {
        return null;
    }

    return $request;
}

function requestEditMergePostedValues(array $request, array $postedData): array
{
    $request['client_id'] = (int)($postedData['client_id'] ?? $request['client_id']);
    $request['request_status'] = (string)($postedData['request_status'] ?? $request['request_status']);
    $request['is_archived'] = !empty($postedData['is_archived']) ? 1 : 0;
    $request['job_title'] = (string)($postedData['job_title'] ?? $request['job_title']);
    $request['request_source'] = (string)($postedData['request_source'] ?? $request['request_source']);
    $request['requested_service_id'] = (int)($postedData['requested_service_id'] ?? $request['requested_service_id']);
    $request['custom_service_name'] = (string)($postedData['custom_service_name'] ?? $request['custom_service_name']);
    $request['preferred_contact_method'] = (string)($postedData['preferred_contact_method'] ?? $request['preferred_contact_method']);
    $request['project_details'] = (string)($postedData['project_details'] ?? $request['project_details']);
    $request['public_notes'] = (string)($postedData['public_notes'] ?? $request['public_notes']);
    $request['internal_notes'] = (string)($postedData['internal_notes'] ?? $request['internal_notes']);
    $request['property_address'] = (string)($postedData['property_address'] ?? $request['property_address']);
    $request['property_city'] = (string)($postedData['property_city'] ?? $request['property_city']);
    $request['property_state'] = (string)($postedData['property_state'] ?? $request['property_state']);
    $request['property_zip_code'] = (string)($postedData['property_zip_code'] ?? $request['property_zip_code']);
    $request['scheduled_start'] = requestEditDateTimeToDatabase((string)($postedData['scheduled_start'] ?? ''));
    $request['scheduled_end'] = requestEditDateTimeToDatabase((string)($postedData['scheduled_end'] ?? ''));
    $request['completed_at'] = requestEditDateTimeToDatabase((string)($postedData['completed_at'] ?? ''));
    $request['quoted_price'] = requestEditNullableMoney((string)($postedData['quoted_price'] ?? ''));
    $request['final_price'] = requestEditNullableMoney((string)($postedData['final_price'] ?? ''));
    $request['next_action'] = (string)($postedData['next_action'] ?? $request['next_action']);
    $request['next_action_due_at'] = requestEditDateTimeToDatabase((string)($postedData['next_action_due_at'] ?? ''));
    $request['next_action_notes'] = (string)($postedData['next_action_notes'] ?? $request['next_action_notes']);

    return $request;
}

function updateRequestEditRecord(int $requestId, array $postedData, array &$errors): void
{
    if ($requestId <= 0) {
        $errors[] = 'Request ID is missing.';
        return;
    }

    $clientId = (int)($postedData['client_id'] ?? 0);
    $requestStatus = requestEditText((string)($postedData['request_status'] ?? 'New'));
    $isArchived = !empty($postedData['is_archived']) ? 1 : 0;

    if ($requestStatus === 'Archived') {
        $isArchived = 1;
    }

    $jobTitle = requestEditNullableString((string)($postedData['job_title'] ?? ''));
    $requestSource = requestEditNullableString((string)($postedData['request_source'] ?? ''));
    $requestedServiceId = (int)($postedData['requested_service_id'] ?? 0);
    $customServiceName = requestEditNullableString((string)($postedData['custom_service_name'] ?? ''));
    $preferredContactMethod = requestEditNullableString((string)($postedData['preferred_contact_method'] ?? ''));

    $projectDetails = requestEditTextarea((string)($postedData['project_details'] ?? ''));
    $publicNotes = requestEditNullableString(requestEditTextarea((string)($postedData['public_notes'] ?? '')));
    $internalNotes = requestEditNullableString(requestEditTextarea((string)($postedData['internal_notes'] ?? '')));

    $propertyAddress = requestEditNullableString((string)($postedData['property_address'] ?? ''));
    $propertyCity = requestEditNullableString((string)($postedData['property_city'] ?? ''));
    $propertyState = requestEditNullableString((string)($postedData['property_state'] ?? 'WI')) ?? 'WI';
    $propertyZipCode = requestEditNullableString((string)($postedData['property_zip_code'] ?? ''));

    $scheduledStart = requestEditDateTimeToDatabase((string)($postedData['scheduled_start'] ?? ''));
    $scheduledEnd = requestEditDateTimeToDatabase((string)($postedData['scheduled_end'] ?? ''));
    $completedAt = requestEditDateTimeToDatabase((string)($postedData['completed_at'] ?? ''));

    $quotedPrice = requestEditNullableMoney((string)($postedData['quoted_price'] ?? ''));
    $finalPrice = requestEditNullableMoney((string)($postedData['final_price'] ?? ''));

    $nextAction = requestEditNullableString((string)($postedData['next_action'] ?? ''));
    $nextActionDueAt = requestEditDateTimeToDatabase((string)($postedData['next_action_due_at'] ?? ''));
    $nextActionNotes = requestEditNullableString(requestEditTextarea((string)($postedData['next_action_notes'] ?? '')));

    $allowedContactMethods = array_keys(getRequestEditPreferredContactMethodOptions());
    $allowedStatuses = array_map(
        static fn (array $status): string => (string)$status['value'],
        getRequestEditStatusOptions()
    );

    if ($clientId <= 0) {
        $errors[] = 'Client is required.';
    }

    if (!in_array($requestStatus, $allowedStatuses, true)) {
        $errors[] = 'Request status is invalid.';
    }

    if ($preferredContactMethod !== null && !in_array($preferredContactMethod, $allowedContactMethods, true)) {
        $errors[] = 'Preferred contact method is invalid.';
    }

    if ($requestedServiceId <= 0 && $customServiceName === null) {
        $errors[] = 'Choose a service or enter a custom service name.';
    }

    if ($projectDetails === '') {
        $errors[] = 'Project details are required.';
    }

    if ((string)($postedData['quoted_price'] ?? '') !== '' && $quotedPrice === null) {
        $errors[] = 'Quoted price must be a valid number.';
    }

    if ((string)($postedData['final_price'] ?? '') !== '' && $finalPrice === null) {
        $errors[] = 'Final price must be a valid number.';
    }

    if ($scheduledStart !== null && $scheduledEnd !== null && strtotime($scheduledEnd) < strtotime($scheduledStart)) {
        $errors[] = 'Scheduled end cannot be before scheduled start.';
    }

    if (!empty($errors)) {
        return;
    }

    $requestedServiceIdValue = $requestedServiceId > 0 ? $requestedServiceId : null;

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'UPDATE quote_requests
         SET
            client_id = ?,
            request_status = ?,
            is_archived = ?,
            job_title = ?,
            request_source = ?,
            requested_service_id = ?,
            custom_service_name = ?,
            preferred_contact_method = ?,
            project_details = ?,
            public_notes = ?,
            internal_notes = ?,
            property_address = ?,
            property_city = ?,
            property_state = ?,
            property_zip_code = ?,
            scheduled_start = ?,
            scheduled_end = ?,
            completed_at = ?,
            quoted_price = ?,
            final_price = ?,
            next_action = ?,
            next_action_due_at = ?,
            next_action_notes = ?,
            last_queue_action_at = NOW()
         WHERE id = ?'
    );

    $statement->bind_param(
        'isississsssssssssssssssi',
        $clientId,
        $requestStatus,
        $isArchived,
        $jobTitle,
        $requestSource,
        $requestedServiceIdValue,
        $customServiceName,
        $preferredContactMethod,
        $projectDetails,
        $publicNotes,
        $internalNotes,
        $propertyAddress,
        $propertyCity,
        $propertyState,
        $propertyZipCode,
        $scheduledStart,
        $scheduledEnd,
        $completedAt,
        $quotedPrice,
        $finalPrice,
        $nextAction,
        $nextActionDueAt,
        $nextActionNotes,
        $requestId
    );

    $statement->execute();
}

$requestId = (int)($_GET['id'] ?? $_POST['request_id'] ?? 0);
$request = null;
$clients = [];
$services = [];
$statusOptions = [];
$preferredContactMethodOptions = getRequestEditPreferredContactMethodOptions();

try {
    $request = getRequestEditRecord($requestId);

    if ($request === null) {
        $errors[] = 'Request not found.';
    }

    $clients = getRequestEditClients();
    $services = getRequestEditServices();
    $statusOptions = getRequestEditStatusOptions();
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'Request edit page could not be loaded.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'save_request') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    } elseif ($request !== null) {
        try {
            updateRequestEditRecord($requestId, $_POST, $errors);

            if (empty($errors)) {
                redirectTo('/admin_request_detail.php?id=' . $requestId . '&saved=1');
            }

            $request = requestEditMergePostedValues($request, $_POST);
        } catch (Throwable $exception) {
            $request = requestEditMergePostedValues($request, $_POST);

            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'Request could not be saved.';
            }
        }
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<main class="site-section">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('requests'); ?>

        <div class="mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                Edit Request
            </p>

            <h1 class="fw-bold">
                <?php echo $request !== null ? escapeHtml(requestEditRequestNumber($request)) : 'Request Not Found'; ?>
            </h1>

            <p class="text-muted mb-0">
                Edit the core request details. Comments, customer link, quotes, and invoices stay on the request detail page.
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

        <?php if ($request === null): ?>
            <div class="card p-4">
                <h2 class="h5">
                    No request loaded.
                </h2>

                <p class="text-muted">
                    Go back to the request list and choose a request.
                </p>

                <a href="/admin_requests.php" class="btn btn-light">
                    Back to Requests
                </a>
            </div>
        <?php else: ?>
            <?php
            $requestNumber = requestEditRequestNumber($request);
            $jobTitle = requestEditJobTitle($request);
            $serviceName = requestEditServiceName($request);
            $status = trim((string)($request['request_status'] ?? 'New'));
            $requestAddress = requestEditFullAddress($request);
            ?>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="/admin_request_detail.php?id=<?php echo escapeHtml((string)$requestId); ?>" class="btn btn-outline-light">
                    Back to Request Detail
                </a>

                <a href="/admin_requests.php" class="btn btn-outline-light">
                    Back to Requests
                </a>

                <a href="/admin_client_detail.php?id=<?php echo escapeHtml((string)$request['client_id']); ?>" class="btn btn-outline-light">
                    Client Detail
                </a>
            </div>

            <section class="card p-4 mb-4">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <h2 class="h4 mb-2">
                            Current Summary
                        </h2>

                        <p class="kails-text-yellow fw-bold text-uppercase mb-1">
                            <?php echo escapeHtml($requestNumber); ?>
                        </p>

                        <h3 class="h5 mb-2">
                            <?php echo escapeHtml($jobTitle); ?>
                        </h3>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="<?php echo escapeHtml(requestEditStatusClass($status)); ?>">
                                <?php echo escapeHtml($status); ?>
                            </span>
                        </div>

                        <dl class="row mb-0">
                            <dt class="col-sm-4">Client</dt>
                            <dd class="col-sm-8"><?php echo escapeHtml((string)$request['full_name']); ?></dd>

                            <dt class="col-sm-4">Service</dt>
                            <dd class="col-sm-8"><?php echo escapeHtml($serviceName); ?></dd>

                            <dt class="col-sm-4">Address</dt>
                            <dd class="col-sm-8"><?php echo $requestAddress !== '' ? escapeHtml($requestAddress) : '—'; ?></dd>

                            <dt class="col-sm-4">Created</dt>
                            <dd class="col-sm-8"><?php echo escapeHtml(requestEditDateTimeDisplay($request['created_at'] ?? null)); ?></dd>

                            <dt class="col-sm-4">Updated</dt>
                            <dd class="col-sm-8"><?php echo escapeHtml(requestEditDateTimeDisplay($request['updated_at'] ?? null)); ?></dd>
                        </dl>
                    </div>

                    <div class="col-lg-4">
                        <div class="admin-inner-panel h-100">
                            <h2 class="h5 mb-3">
                                Linked Records
                            </h2>

                            <div class="request-summary-meta">
                                <div><strong>Comments:</strong> <?php echo escapeHtml((string)($request['comment_count'] ?? 0)); ?></div>
                                <div><strong>Documents:</strong> <?php echo escapeHtml((string)($request['document_count'] ?? 0)); ?></div>
                                <div><strong>Last Queue Action:</strong> <?php echo escapeHtml(requestEditDateTimeDisplay($request['last_queue_action_at'] ?? null)); ?></div>
                                <div><strong>Customer Link:</strong> <?php echo trim((string)($request['public_access_token'] ?? '')) !== '' ? 'Ready' : 'Not created'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <form method="post" action="/admin_request_edit.php" id="requestEditForm">
                <input type="hidden" name="form_name" value="save_request">
                <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                <input type="hidden" name="request_id" value="<?php echo escapeHtml((string)$requestId); ?>">

                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Basic Request Information
                    </h2>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="client_id" class="form-label">
                                Client
                            </label>

                            <select class="form-control" id="client_id" name="client_id" required>
                                <option value="0">Choose Client</option>

                                <?php foreach ($clients as $client): ?>
                                    <?php
                                    $clientLabel = (string)$client['full_name'];

                                    if ((string)($client['phone'] ?? '') !== '') {
                                        $clientLabel .= ' · ' . (string)$client['phone'];
                                    } elseif ((string)($client['email'] ?? '') !== '') {
                                        $clientLabel .= ' · ' . (string)$client['email'];
                                    }

                                    if ((int)($client['is_active'] ?? 0) !== 1) {
                                        $clientLabel .= ' - inactive';
                                    }
                                    ?>

                                    <option
                                        value="<?php echo escapeHtml((string)$client['id']); ?>"
                                        <?php echo ((int)$request['client_id'] === (int)$client['id']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeHtml($clientLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="request_status" class="form-label">
                                Status
                            </label>

                            <select class="form-control" id="request_status" name="request_status" required>
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <option
                                        value="<?php echo escapeHtml((string)$statusOption['value']); ?>"
                                        <?php echo requestEditSelectedValue((string)$request['request_status'], (string)$statusOption['value']); ?>
                                    >
                                        <?php echo escapeHtml((string)$statusOption['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="is_archived"
                                    name="is_archived"
                                    value="1"
                                    <?php echo requestEditCheckedValue((int)($request['is_archived'] ?? 0) === 1); ?>
                                >
                                <label class="form-check-label" for="is_archived">
                                    Archive this request
                                </label>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label for="job_title" class="form-label">
                                Request / Job Title
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="job_title"
                                name="job_title"
                                value="<?php echo escapeHtml((string)($request['job_title'] ?? '')); ?>"
                                placeholder="Example: Spring yard cleanup"
                            >
                        </div>

                        <div class="col-md-4">
                            <label for="request_source" class="form-label">
                                Request Source
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="request_source"
                                name="request_source"
                                value="<?php echo escapeHtml((string)($request['request_source'] ?? '')); ?>"
                                placeholder="Website Form, Phone Call, Text, Referral..."
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="requested_service_id" class="form-label">
                                Service
                            </label>

                            <select class="form-control" id="requested_service_id" name="requested_service_id">
                                <option value="0">Use custom service name</option>

                                <?php foreach ($services as $service): ?>
                                    <?php
                                    $serviceLabel = (string)$service['service_title'];

                                    if ((int)($service['is_active'] ?? 0) !== 1) {
                                        $serviceLabel .= ' - inactive';
                                    }
                                    ?>

                                    <option
                                        value="<?php echo escapeHtml((string)$service['id']); ?>"
                                        <?php echo ((int)($request['requested_service_id'] ?? 0) === (int)$service['id']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeHtml($serviceLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="custom_service_name" class="form-label">
                                Custom Service Name
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="custom_service_name"
                                name="custom_service_name"
                                value="<?php echo escapeHtml((string)($request['custom_service_name'] ?? '')); ?>"
                                placeholder="Use this if no service fits."
                            >
                        </div>

                        <div class="col-md-4">
                            <label for="preferred_contact_method" class="form-label">
                                Preferred Contact Method
                            </label>

                            <select class="form-control" id="preferred_contact_method" name="preferred_contact_method">
                                <?php foreach ($preferredContactMethodOptions as $methodValue => $methodLabel): ?>
                                    <option
                                        value="<?php echo escapeHtml($methodValue); ?>"
                                        <?php echo requestEditSelectedValue((string)($request['preferred_contact_method'] ?? ''), $methodValue); ?>
                                    >
                                        <?php echo escapeHtml($methodLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="project_details" class="form-label">
                                Project Details
                            </label>

                            <textarea
                                class="form-control"
                                id="project_details"
                                name="project_details"
                                rows="6"
                                required
                            ><?php echo escapeHtml((string)($request['project_details'] ?? '')); ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Property Address
                    </h2>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="property_address" class="form-label">
                                Street Address
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="property_address"
                                name="property_address"
                                value="<?php echo escapeHtml((string)($request['property_address'] ?? '')); ?>"
                            >
                        </div>

                        <div class="col-md-5">
                            <label for="property_city" class="form-label">
                                City
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="property_city"
                                name="property_city"
                                value="<?php echo escapeHtml((string)($request['property_city'] ?? '')); ?>"
                            >
                        </div>

                        <div class="col-md-3">
                            <label for="property_state" class="form-label">
                                State
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="property_state"
                                name="property_state"
                                value="<?php echo escapeHtml((string)($request['property_state'] ?? 'WI')); ?>"
                            >
                        </div>

                        <div class="col-md-3">
                            <label for="property_zip_code" class="form-label">
                                ZIP
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="property_zip_code"
                                name="property_zip_code"
                                value="<?php echo escapeHtml((string)($request['property_zip_code'] ?? '')); ?>"
                            >
                        </div>
                    </div>
                </section>

                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Schedule and Pricing
                    </h2>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="scheduled_start" class="form-label">
                                Scheduled Start
                            </label>

                            <input
                                type="datetime-local"
                                class="form-control"
                                id="scheduled_start"
                                name="scheduled_start"
                                value="<?php echo escapeHtml(requestEditDateTimeLocalValue($request['scheduled_start'] ?? null)); ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label for="scheduled_end" class="form-label">
                                Scheduled End
                            </label>

                            <input
                                type="datetime-local"
                                class="form-control"
                                id="scheduled_end"
                                name="scheduled_end"
                                value="<?php echo escapeHtml(requestEditDateTimeLocalValue($request['scheduled_end'] ?? null)); ?>"
                            >
                        </div>

                        <div class="col-md-4">
                            <label for="completed_at" class="form-label">
                                Completed At
                            </label>

                            <input
                                type="datetime-local"
                                class="form-control"
                                id="completed_at"
                                name="completed_at"
                                value="<?php echo escapeHtml(requestEditDateTimeLocalValue($request['completed_at'] ?? null)); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="quoted_price" class="form-label">
                                Quoted Price
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                id="quoted_price"
                                name="quoted_price"
                                step="0.01"
                                min="0"
                                value="<?php echo escapeHtml(requestEditMoneyDisplay($request['quoted_price'] ?? null)); ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="final_price" class="form-label">
                                Final Price
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                id="final_price"
                                name="final_price"
                                step="0.01"
                                min="0"
                                value="<?php echo escapeHtml(requestEditMoneyDisplay($request['final_price'] ?? null)); ?>"
                            >
                        </div>
                    </div>
                </section>

                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Next Action
                    </h2>

                    <p class="text-muted">
                        Use this to make sure the request never gets forgotten. The main admin page uses this information to decide what needs attention.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="next_action" class="form-label">
                                Next Action
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="next_action"
                                name="next_action"
                                value="<?php echo escapeHtml((string)($request['next_action'] ?? '')); ?>"
                                placeholder="Example: Call customer to confirm details"
                            >
                        </div>

                        <div class="col-md-5">
                            <label for="next_action_due_at" class="form-label">
                                Next Action Due
                            </label>

                            <input
                                type="datetime-local"
                                class="form-control"
                                id="next_action_due_at"
                                name="next_action_due_at"
                                value="<?php echo escapeHtml(requestEditDateTimeLocalValue($request['next_action_due_at'] ?? null)); ?>"
                            >
                        </div>

                        <div class="col-12">
                            <label for="next_action_notes" class="form-label">
                                Next Action Notes
                            </label>

                            <textarea
                                class="form-control"
                                id="next_action_notes"
                                name="next_action_notes"
                                rows="3"
                            ><?php echo escapeHtml((string)($request['next_action_notes'] ?? '')); ?></textarea>
                        </div>
                    </div>
                </section>

                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Notes
                    </h2>

                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label for="public_notes" class="form-label">
                                Public Notes
                            </label>

                            <textarea
                                class="form-control"
                                id="public_notes"
                                name="public_notes"
                                rows="5"
                            ><?php echo escapeHtml((string)($request['public_notes'] ?? '')); ?></textarea>

                            <div class="form-text">
                                Notes that may be safe to show or reuse for the customer.
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <label for="internal_notes" class="form-label">
                                Internal Notes
                            </label>

                            <textarea
                                class="form-control"
                                id="internal_notes"
                                name="internal_notes"
                                rows="5"
                            ><?php echo escapeHtml((string)($request['internal_notes'] ?? '')); ?></textarea>

                            <div class="form-text">
                                Admin-only notes. These are not customer-facing.
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card p-4">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <div>
                            <h2 class="h5 mb-1">
                                Save Request
                            </h2>

                            <p class="text-muted mb-0">
                                Saving returns you to the request detail page.
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="/admin_request_detail.php?id=<?php echo escapeHtml((string)$requestId); ?>" class="btn btn-outline-light">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-light">
                                Save Request
                            </button>
                        </div>
                    </div>
                </section>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('requestEditForm');

    if (!form) {
        return;
    }

    let formChanged = false;

    form.addEventListener('change', function () {
        formChanged = true;
    });

    form.addEventListener('input', function () {
        formChanged = true;
    });

    form.addEventListener('submit', function () {
        formChanged = false;
    });

    window.addEventListener('beforeunload', function (event) {
        if (!formChanged) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
});
</script>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';