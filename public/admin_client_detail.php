<?php
declare(strict_types=1);

/*
    Admin client detail page.

    This page shows one client, their contact information,
    request/job status counts, and all requests linked to that client.

    Editing the client is handled by:
    /admin_client_edit.php?id=123

    Request details are handled by:
    /admin_request_detail.php?id=123
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Client Detail';

adminRequireLogin('Client Detail Login');

$messages = [];
$errors = [];

function clientDetailDateDisplay($value): string
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

function clientDetailDateTimeDisplay($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return '—';
    }

    return date('M j, Y g:i A', $timestamp);
}

function clientDetailMoney($value): string
{
    return '$' . number_format((float)$value, 2);
}

function clientDetailRequestNumber(array $request): string
{
    $requestNumber = trim((string)($request['request_number'] ?? ''));

    if ($requestNumber !== '') {
        return $requestNumber;
    }

    return 'Request #' . (string)($request['id'] ?? '');
}

function clientDetailServiceName(array $request): string
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

function clientDetailStatusClass(string $status): string
{
    $statusClass = strtolower($status);
    $statusClass = str_replace(' ', '-', $statusClass);
    $statusClass = preg_replace('/[^a-z0-9-]/', '', $statusClass);

    return 'request-status request-status-' . $statusClass;
}

function clientDetailClientStatusBadge(array $client): string
{
    if ((int)($client['is_active'] ?? 0) === 1) {
        return '<span class="request-status request-status-completed">Active Client</span>';
    }

    return '<span class="request-status request-status-archived">Inactive Client</span>';
}

function clientDetailFullAddress(array $client): string
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

function clientDetailRequestFullAddress(array $request): string
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

function clientDetailIsInactiveRequest(array $request): bool
{
    $status = (string)($request['request_status'] ?? '');

    if ((int)($request['is_archived'] ?? 0) === 1) {
        return true;
    }

    return in_array($status, ['Completed', 'Cancelled', 'Archived'], true);
}

function clientDetailAttentionBadges(array $request): array
{
    $badges = [];

    if (clientDetailIsInactiveRequest($request)) {
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

function getClientDetailRecord(int $clientId): ?array
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

function getClientDetailRequests(int $clientId): array
{
    if ($clientId <= 0) {
        return [];
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
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
            ) AS invoice_count,

            CASE
                WHEN qr.is_archived = 1
                  OR qr.request_status IN ("Completed", "Cancelled", "Archived")
                THEN 9

                WHEN EXISTS (
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

                WHEN (
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

                WHEN qr.request_status = "New"
                THEN 2

                WHEN qr.next_action IS NULL
                  OR qr.next_action = ""
                THEN 3

                ELSE 4
            END AS attention_rank

         FROM quote_requests qr
         LEFT JOIN services s
            ON s.id = qr.requested_service_id
         WHERE qr.client_id = ?
         ORDER BY
            CASE
                WHEN qr.is_archived = 1
                  OR qr.request_status IN ("Completed", "Cancelled", "Archived")
                THEN 1
                ELSE 0
            END ASC,
            attention_rank ASC,
            COALESCE(qr.updated_at, qr.created_at) DESC,
            qr.id DESC
         LIMIT 250'
    );

    $statement->bind_param('i', $clientId);
    $statement->execute();

    $result = $statement->get_result();

    $requests = [];

    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    return $requests;
}

function clientDetailRequestStats(array $requests): array
{
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'needs_attention' => 0,
        'customer_updates' => 0,
        'overdue' => 0,
        'missing_next_action' => 0,
        'New' => 0,
        'Quoted' => 0,
        'Scheduled' => 0,
        'In Progress' => 0,
        'Completed' => 0,
        'Cancelled' => 0,
        'Archived' => 0,
        'Other' => 0,
    ];

    foreach ($requests as $request) {
        $stats['total']++;

        $status = (string)($request['request_status'] ?? '');
        $isInactive = clientDetailIsInactiveRequest($request);

        if ($isInactive) {
            $stats['inactive']++;
        } else {
            $stats['active']++;
        }

        if (array_key_exists($status, $stats)) {
            $stats[$status]++;
        } elseif ($status !== '') {
            $stats['Other']++;
        }

        if ((int)($request['customer_update_count'] ?? 0) > 0) {
            $stats['customer_updates']++;
        }

        if (!empty(clientDetailAttentionBadges($request)) && !$isInactive) {
            $stats['needs_attention']++;
        }

        $nextActionDueAt = (string)($request['next_action_due_at'] ?? '');
        $scheduledStart = (string)($request['scheduled_start'] ?? '');

        if (
            !$isInactive
            && (
                ($nextActionDueAt !== '' && strtotime($nextActionDueAt) !== false && strtotime($nextActionDueAt) <= time())
                || (
                    $status === 'Scheduled'
                    && $scheduledStart !== ''
                    && strtotime($scheduledStart) !== false
                    && strtotime($scheduledStart) <= time()
                    && (string)($request['completed_at'] ?? '') === ''
                )
            )
        ) {
            $stats['overdue']++;
        }

        if (
            !$isInactive
            && trim((string)($request['next_action'] ?? '')) === ''
        ) {
            $stats['missing_next_action']++;
        }
    }

    return $stats;
}

function clientDetailRenderRequestCard(array $request): void
{
    $requestId = (int)($request['id'] ?? 0);
    $requestNumber = clientDetailRequestNumber($request);
    $serviceName = clientDetailServiceName($request);
    $jobTitle = trim((string)($request['job_title'] ?? ''));

    if ($jobTitle === '') {
        $jobTitle = $serviceName;
    }

    $status = trim((string)($request['request_status'] ?? 'New'));
    $requestAddress = clientDetailRequestFullAddress($request);
    $nextAction = trim((string)($request['next_action'] ?? ''));
    $price = (float)($request['final_price'] ?? 0.00);

    if ($price <= 0) {
        $price = (float)($request['quoted_price'] ?? 0.00);
    }

    $requestDetailUrl = '/admin_request_detail.php?id=' . urlencode((string)$requestId);
    $badges = clientDetailAttentionBadges($request);
    ?>

    <article class="card request-summary-card p-4">
        <a href="<?php echo escapeHtml($requestDetailUrl); ?>" class="text-decoration-none">
            <div class="request-summary-header">
                <div>
                    <p class="kails-text-yellow fw-bold text-uppercase mb-1">
                        <?php echo escapeHtml($requestNumber); ?>
                    </p>

                    <h3 class="h5 mb-1">
                        <?php echo escapeHtml($jobTitle); ?>
                    </h3>

                    <p class="text-muted mb-0">
                        <?php echo escapeHtml($serviceName); ?>
                        · Updated <?php echo escapeHtml(clientDetailDateDisplay($request['updated_at'] ?? null)); ?>
                    </p>
                </div>

                <div class="request-summary-status">
                    <span class="<?php echo escapeHtml(clientDetailStatusClass($status)); ?>">
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
                    <?php echo escapeHtml(clientDetailDateTimeDisplay($request['next_action_due_at'] ?? null)); ?>
                </div>

                <div>
                    <strong>Scheduled:</strong>
                    <?php echo escapeHtml(clientDetailDateTimeDisplay($request['scheduled_start'] ?? null)); ?>
                </div>

                <div>
                    <strong>Price:</strong>
                    <?php echo escapeHtml(clientDetailMoney($price)); ?>
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
                    <?php echo escapeHtml(clientDetailDateDisplay($request['created_at'] ?? null)); ?>
                </div>
            </div>
        </a>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="<?php echo escapeHtml($requestDetailUrl); ?>" class="btn btn-light">
                Open Request
            </a>
        </div>
    </article>

    <?php
}

$clientId = (int)($_GET['id'] ?? 0);
$client = null;
$requests = [];
$requestStats = clientDetailRequestStats([]);

if (isset($_GET['created'])) {
    $messages[] = 'Client created.';
}

if (isset($_GET['saved'])) {
    $messages[] = 'Client saved.';
}

try {
    $client = getClientDetailRecord($clientId);

    if ($client === null) {
        $errors[] = 'Client not found.';
    } else {
        $requests = getClientDetailRequests($clientId);
        $requestStats = clientDetailRequestStats($requests);
    }
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'Client detail could not be loaded.';
    }
}

$activeRequests = [];
$inactiveRequests = [];

foreach ($requests as $request) {
    if (clientDetailIsInactiveRequest($request)) {
        $inactiveRequests[] = $request;
    } else {
        $activeRequests[] = $request;
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
                Client Detail
            </p>

            <h1 class="fw-bold">
                <?php echo $client !== null ? escapeHtml((string)$client['full_name']) : 'Client Not Found'; ?>
            </h1>

            <p class="text-muted mb-0">
                Review this client, see all related requests, and open the request that needs attention.
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
            <?php $clientAddress = clientDetailFullAddress($client); ?>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="/admin_clients.php" class="btn btn-outline-light">
                    Back to Clients
                </a>

                <a href="/admin_client_edit.php?id=<?php echo escapeHtml((string)$clientId); ?>" class="btn btn-light">
                    Edit Client
                </a>
            </div>

            <section class="card p-4 mb-4">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <h2 class="h4 mb-2">
                                    Client Information
                                </h2>

                                <p class="mb-2">
                                    <?php echo clientDetailClientStatusBadge($client); ?>
                                </p>
                            </div>

                            <dl class="row mb-0">
                                <dt class="col-sm-4">Name</dt>
                                <dd class="col-sm-8"><?php echo escapeHtml((string)$client['full_name']); ?></dd>

                                <dt class="col-sm-4">Phone</dt>
                                <dd class="col-sm-8">
                                    <?php if ((string)($client['phone'] ?? '') !== ''): ?>
                                        <a href="tel:<?php echo escapeHtml(preg_replace('/\D+/', '', (string)$client['phone'])); ?>">
                                            <?php echo escapeHtml((string)$client['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-sm-4">Email</dt>
                                <dd class="col-sm-8">
                                    <?php if ((string)($client['email'] ?? '') !== ''): ?>
                                        <a href="mailto:<?php echo escapeHtml((string)$client['email']); ?>">
                                            <?php echo escapeHtml((string)$client['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-sm-4">Preferred Contact</dt>
                                <dd class="col-sm-8">
                                    <?php echo (string)($client['preferred_contact_method'] ?? '') !== '' ? escapeHtml((string)$client['preferred_contact_method']) : '—'; ?>
                                </dd>

                                <dt class="col-sm-4">Address</dt>
                                <dd class="col-sm-8">
                                    <?php echo $clientAddress !== '' ? escapeHtml($clientAddress) : '—'; ?>
                                </dd>

                                <dt class="col-sm-4">Created</dt>
                                <dd class="col-sm-8"><?php echo escapeHtml(clientDetailDateDisplay($client['created_at'] ?? null)); ?></dd>

                                <dt class="col-sm-4">Updated</dt>
                                <dd class="col-sm-8"><?php echo escapeHtml(clientDetailDateDisplay($client['updated_at'] ?? null)); ?></dd>
                            </dl>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="admin-inner-panel h-100">
                            <h2 class="h4 mb-3">
                                Request Summary
                            </h2>

                            <div class="request-summary-meta">
                                <div><strong>Needs Attention:</strong> <?php echo escapeHtml((string)$requestStats['needs_attention']); ?></div>
                                <div><strong>Customer Updates:</strong> <?php echo escapeHtml((string)$requestStats['customer_updates']); ?></div>
                                <div><strong>Overdue:</strong> <?php echo escapeHtml((string)$requestStats['overdue']); ?></div>
                                <div><strong>Missing Next Action:</strong> <?php echo escapeHtml((string)$requestStats['missing_next_action']); ?></div>
                                <div><strong>Active Requests:</strong> <?php echo escapeHtml((string)$requestStats['active']); ?></div>
                                <div><strong>Total Requests:</strong> <?php echo escapeHtml((string)$requestStats['total']); ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (trim((string)($client['notes'] ?? '')) !== ''): ?>
                        <div class="col-12">
                            <div class="admin-inner-panel">
                                <h2 class="h5">
                                    Client Notes
                                </h2>

                                <p class="mb-0">
                                    <?php echo nl2br(escapeHtml((string)$client['notes'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card p-4 mb-4">
                <h2 class="h4 mb-3">
                    Request Status Counts
                </h2>

                <div class="d-flex flex-wrap gap-2">
                    <span class="badge rounded-pill text-bg-warning text-dark">New: <?php echo escapeHtml((string)$requestStats['New']); ?></span>
                    <span class="badge rounded-pill text-bg-secondary">Quoted: <?php echo escapeHtml((string)$requestStats['Quoted']); ?></span>
                    <span class="badge rounded-pill text-bg-primary">Scheduled: <?php echo escapeHtml((string)$requestStats['Scheduled']); ?></span>
                    <span class="badge rounded-pill text-bg-info text-dark">In Progress: <?php echo escapeHtml((string)$requestStats['In Progress']); ?></span>
                    <span class="badge rounded-pill text-bg-success">Completed: <?php echo escapeHtml((string)$requestStats['Completed']); ?></span>
                    <span class="badge rounded-pill text-bg-danger">Cancelled: <?php echo escapeHtml((string)$requestStats['Cancelled']); ?></span>
                    <span class="badge rounded-pill text-bg-dark">Archived: <?php echo escapeHtml((string)$requestStats['Archived']); ?></span>

                    <?php if ((int)$requestStats['Other'] > 0): ?>
                        <span class="badge rounded-pill text-bg-secondary">Other: <?php echo escapeHtml((string)$requestStats['Other']); ?></span>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card p-4 mb-4">
                <div class="mb-4">
                    <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                        Active Requests
                    </p>

                    <h2 class="h4 mb-1">
                        Requests needing work or follow-up
                    </h2>

                    <p class="text-muted mb-0">
                        These are sorted by what needs attention first, then by the most recently updated request.
                    </p>
                </div>

                <?php if (empty($activeRequests)): ?>
                    <div class="alert alert-success mb-0">
                        This client has no active requests.
                    </div>
                <?php else: ?>
                    <div class="request-card-list">
                        <?php foreach ($activeRequests as $request): ?>
                            <?php clientDetailRenderRequestCard($request); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card p-4">
                <div class="mb-4">
                    <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                        Completed / Inactive Requests
                    </p>

                    <h2 class="h4 mb-1">
                        Older or closed work
                    </h2>

                    <p class="text-muted mb-0">
                        Completed, cancelled, and archived requests are kept here so they do not distract from active work.
                    </p>
                </div>

                <?php if (empty($inactiveRequests)): ?>
                    <div class="alert alert-info mb-0">
                        This client has no completed, cancelled, or archived requests.
                    </div>
                <?php else: ?>
                    <div class="request-card-list">
                        <?php foreach ($inactiveRequests as $request): ?>
                            <?php clientDetailRenderRequestCard($request); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';