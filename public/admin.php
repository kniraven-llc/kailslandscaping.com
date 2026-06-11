<?php
declare(strict_types=1);

/*
    Main admin home page.

    This page shows the work that needs attention right now.

    The website editor was moved to:
    /admin_website.php

    This page should be the first admin page Kail sees.
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Admin Home';

adminRequireLogin('Admin Login');

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'admin_logout') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (function_exists('isValidCsrfToken') && !isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    } else {
        adminClearLoggedInUser();
        redirectTo('/admin.php');
    }
}

function adminDashboardDateDisplay($value): string
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

function adminDashboardDateTimeDisplay($value): string
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

function adminDashboardMoney($value): string
{
    return '$' . number_format((float)$value, 2);
}

function adminDashboardRequestNumber(array $request): string
{
    $requestNumber = trim((string)($request['request_number'] ?? ''));

    if ($requestNumber !== '') {
        return $requestNumber;
    }

    return 'Request #' . (string)($request['id'] ?? '');
}

function adminDashboardDocumentNumber(array $document): string
{
    $documentNumber = trim((string)($document['document_number'] ?? ''));

    if ($documentNumber !== '') {
        return $documentNumber;
    }

    return 'Document #' . (string)($document['id'] ?? '');
}

function adminDashboardServiceName(array $request): string
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

function adminDashboardDocumentTypeLabel(string $documentType): string
{
    $documentType = strtolower(trim($documentType));

    if ($documentType === 'quote') {
        return 'Quote';
    }

    if ($documentType === 'invoice' || $documentType === 'receipt') {
        return 'Invoice';
    }

    return 'Document';
}

function adminDashboardStatusClass(string $status): string
{
    $statusClass = strtolower($status);
    $statusClass = str_replace(' ', '-', $statusClass);
    $statusClass = preg_replace('/[^a-z0-9-]/', '', $statusClass);

    return 'request-status request-status-' . $statusClass;
}

function adminDashboardCount(mysqli $connection, string $sql): int
{
    $result = $connection->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int)($row['total_count'] ?? 0);
}

function adminDashboardLoadStats(mysqli $connection): array
{
    return [
        'needs_attention' => adminDashboardCount(
            $connection,
            'SELECT COUNT(DISTINCT qr.id) AS total_count
             FROM quote_requests qr
             WHERE qr.is_archived = 0
               AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
               AND (
                    qr.request_status = "New"
                    OR qr.next_action IS NULL
                    OR qr.next_action = ""
                    OR (
                        qr.next_action_due_at IS NOT NULL
                        AND qr.next_action_due_at <= NOW()
                    )
                    OR (
                        qr.request_status = "Scheduled"
                        AND qr.scheduled_start IS NOT NULL
                        AND qr.scheduled_start <= NOW()
                        AND qr.completed_at IS NULL
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM quote_request_comments qrc
                        WHERE qrc.quote_request_id = qr.id
                          AND qrc.author_type = "customer"
                          AND (
                              qr.last_admin_reviewed_at IS NULL
                              OR qrc.created_at > qr.last_admin_reviewed_at
                          )
                    )
               )'
        ),
        'customer_updates' => adminDashboardCount(
            $connection,
            'SELECT COUNT(DISTINCT qr.id) AS total_count
             FROM quote_requests qr
             INNER JOIN quote_request_comments qrc
                ON qrc.quote_request_id = qr.id
             WHERE qr.is_archived = 0
               AND qrc.author_type = "customer"
               AND (
                    qr.last_admin_reviewed_at IS NULL
                    OR qrc.created_at > qr.last_admin_reviewed_at
               )'
        ),
        'overdue' => adminDashboardCount(
            $connection,
            'SELECT COUNT(DISTINCT qr.id) AS total_count
             FROM quote_requests qr
             WHERE qr.is_archived = 0
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
               )'
        ),
        'new_requests' => adminDashboardCount(
            $connection,
            'SELECT COUNT(*) AS total_count
             FROM quote_requests
             WHERE is_archived = 0
               AND request_status = "New"'
        ),
        'missing_next_action' => adminDashboardCount(
            $connection,
            'SELECT COUNT(*) AS total_count
             FROM quote_requests
             WHERE is_archived = 0
               AND request_status NOT IN ("Completed", "Cancelled", "Archived")
               AND (
                    next_action IS NULL
                    OR next_action = ""
               )'
        ),
        'need_invoice' => adminDashboardCount(
            $connection,
            'SELECT COUNT(*) AS total_count
             FROM quote_requests qr
             WHERE qr.is_archived = 0
               AND qr.request_status = "Completed"
               AND NOT EXISTS (
                    SELECT 1
                    FROM client_documents cd
                    WHERE cd.quote_request_id = qr.id
                      AND cd.document_type IN ("receipt", "invoice")
               )'
        ),
        'scheduled_today' => adminDashboardCount(
            $connection,
            'SELECT COUNT(*) AS total_count
             FROM quote_requests
             WHERE is_archived = 0
               AND request_status = "Scheduled"
               AND scheduled_start IS NOT NULL
               AND DATE(scheduled_start) = CURDATE()'
        ),
        'draft_documents' => adminDashboardCount(
            $connection,
            'SELECT COUNT(*) AS total_count
             FROM client_documents
             WHERE LOWER(document_status) = "draft"'
        ),
        'active_requests' => adminDashboardCount(
            $connection,
            'SELECT COUNT(*) AS total_count
             FROM quote_requests
             WHERE is_archived = 0
               AND request_status NOT IN ("Completed", "Cancelled", "Archived")'
        ),
    ];
}

function adminDashboardLoadRequestRows(mysqli $connection, string $whereSql, string $orderSql, int $limit = 10): array
{
    $limit = max(1, min($limit, 25));

    $sql = '
        SELECT
            qr.id,
            qr.request_number,
            qr.client_id,
            qr.request_status,
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
        LEFT JOIN clients c
            ON c.id = qr.client_id
        LEFT JOIN services s
            ON s.id = qr.requested_service_id
        WHERE ' . $whereSql . '
        ORDER BY ' . $orderSql . '
        LIMIT ' . $limit;

    $result = $connection->query($sql);

    if (!$result) {
        return [];
    }

    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function adminDashboardLoadImmediateRequests(mysqli $connection): array
{
    return adminDashboardLoadRequestRows(
        $connection,
        'qr.is_archived = 0
         AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
         AND (
            qr.request_status = "New"
            OR qr.next_action IS NULL
            OR qr.next_action = ""
            OR (
                qr.next_action_due_at IS NOT NULL
                AND qr.next_action_due_at <= NOW()
            )
            OR (
                qr.request_status = "Scheduled"
                AND qr.scheduled_start IS NOT NULL
                AND qr.scheduled_start <= NOW()
                AND qr.completed_at IS NULL
            )
            OR EXISTS (
                SELECT 1
                FROM quote_request_comments qrc
                WHERE qrc.quote_request_id = qr.id
                  AND qrc.author_type = "customer"
                  AND (
                      qr.last_admin_reviewed_at IS NULL
                      OR qrc.created_at > qr.last_admin_reviewed_at
                  )
            )
         )',
        'customer_update_count DESC,
         CASE
            WHEN qr.next_action_due_at IS NOT NULL AND qr.next_action_due_at <= NOW() THEN 0
            WHEN qr.request_status = "Scheduled" AND qr.scheduled_start IS NOT NULL AND qr.scheduled_start <= NOW() AND qr.completed_at IS NULL THEN 1
            WHEN qr.request_status = "New" THEN 2
            WHEN qr.next_action IS NULL OR qr.next_action = "" THEN 3
            ELSE 4
         END ASC,
         COALESCE(qr.next_action_due_at, qr.scheduled_start, qr.created_at) ASC,
         qr.created_at DESC',
        12
    );
}

function adminDashboardLoadCustomerUpdateRequests(mysqli $connection): array
{
    return adminDashboardLoadRequestRows(
        $connection,
        'qr.is_archived = 0
         AND EXISTS (
            SELECT 1
            FROM quote_request_comments qrc
            WHERE qrc.quote_request_id = qr.id
              AND qrc.author_type = "customer"
              AND (
                  qr.last_admin_reviewed_at IS NULL
                  OR qrc.created_at > qr.last_admin_reviewed_at
              )
         )',
        'customer_update_count DESC,
         qr.updated_at DESC,
         qr.created_at DESC',
        8
    );
}

function adminDashboardLoadOverdueRequests(mysqli $connection): array
{
    return adminDashboardLoadRequestRows(
        $connection,
        'qr.is_archived = 0
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
         )',
        'COALESCE(qr.next_action_due_at, qr.scheduled_start, qr.created_at) ASC,
         qr.created_at DESC',
        8
    );
}

function adminDashboardLoadNewRequests(mysqli $connection): array
{
    return adminDashboardLoadRequestRows(
        $connection,
        'qr.is_archived = 0
         AND qr.request_status = "New"',
        'qr.created_at ASC',
        8
    );
}

function adminDashboardLoadMissingActionRequests(mysqli $connection): array
{
    return adminDashboardLoadRequestRows(
        $connection,
        'qr.is_archived = 0
         AND qr.request_status NOT IN ("Completed", "Cancelled", "Archived")
         AND (
            qr.next_action IS NULL
            OR qr.next_action = ""
         )',
        'qr.created_at ASC',
        8
    );
}

function adminDashboardLoadNeedInvoiceRequests(mysqli $connection): array
{
    return adminDashboardLoadRequestRows(
        $connection,
        'qr.is_archived = 0
         AND qr.request_status = "Completed"
         AND NOT EXISTS (
            SELECT 1
            FROM client_documents cd
            WHERE cd.quote_request_id = qr.id
              AND cd.document_type IN ("receipt", "invoice")
         )',
        'COALESCE(qr.completed_at, qr.updated_at, qr.created_at) ASC',
        8
    );
}

function adminDashboardLoadUpcomingRequests(mysqli $connection): array
{
    return adminDashboardLoadRequestRows(
        $connection,
        'qr.is_archived = 0
         AND qr.request_status = "Scheduled"
         AND qr.scheduled_start IS NOT NULL
         AND qr.completed_at IS NULL',
        'qr.scheduled_start ASC',
        8
    );
}

function adminDashboardLoadDraftDocuments(mysqli $connection): array
{
    $sql = '
        SELECT
            cd.id,
            cd.document_type,
            cd.document_number,
            cd.document_status,
            cd.document_title,
            cd.service_summary,
            cd.issue_date,
            cd.due_date,
            cd.total_amount,
            cd.balance_due,
            cd.quote_request_id,
            cd.client_id,
            cd.created_at,
            cd.updated_at,
            c.full_name,
            qr.request_number,
            qr.request_status,
            qr.job_title
        FROM client_documents cd
        LEFT JOIN clients c
            ON c.id = cd.client_id
        LEFT JOIN quote_requests qr
            ON qr.id = cd.quote_request_id
        WHERE LOWER(cd.document_status) = "draft"
        ORDER BY cd.updated_at ASC, cd.created_at ASC
        LIMIT 8';

    $result = $connection->query($sql);

    if (!$result) {
        return [];
    }

    $documents = [];

    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    return $documents;
}

function adminDashboardAttentionBadges(array $request): array
{
    $badges = [];

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

function adminDashboardRenderRequestCard(array $request, string $sectionType = 'attention'): void
{
    $requestId = (int)($request['id'] ?? 0);
    $clientId = (int)($request['client_id'] ?? 0);
    $requestNumber = adminDashboardRequestNumber($request);
    $clientName = trim((string)($request['full_name'] ?? 'Unknown Client'));
    $serviceName = adminDashboardServiceName($request);
    $jobTitle = trim((string)($request['job_title'] ?? ''));

    if ($jobTitle === '') {
        $jobTitle = $serviceName;
    }

    $status = trim((string)($request['request_status'] ?? 'New'));
    $nextAction = trim((string)($request['next_action'] ?? ''));
    $city = trim((string)($request['property_city'] ?? ''));
    $price = (float)($request['final_price'] ?? 0.00);

    if ($price <= 0) {
        $price = (float)($request['quoted_price'] ?? 0.00);
    }

    $requestDetailUrl = '/admin_request_detail.php?id=' . urlencode((string)$requestId);
    $requestEditUrl = '/admin_request_edit.php?id=' . urlencode((string)$requestId);
    $clientDetailUrl = '/admin_client_detail.php?id=' . urlencode((string)$clientId);
    $invoiceCreateUrl = '/admin_document_edit.php?type=invoice&request_id=' . urlencode((string)$requestId);

    $mainUrl = $requestDetailUrl;
    $mainButtonText = 'Handle Now';

    if ($sectionType === 'invoice') {
        $mainUrl = $invoiceCreateUrl;
        $mainButtonText = 'Create Invoice';
    }

    $badges = adminDashboardAttentionBadges($request);
    ?>

    <article class="card request-summary-card p-4">
        <div class="request-summary-header">
            <div>
                <p class="kails-text-yellow fw-bold text-uppercase mb-1">
                    <?php echo escapeHtml($requestNumber); ?>
                </p>

                <h3 class="h5 mb-1">
                    <a href="<?php echo escapeHtml($requestDetailUrl); ?>" class="text-decoration-none">
                        <?php echo escapeHtml($jobTitle); ?>
                    </a>
                </h3>

                <p class="text-muted mb-0">
                    <?php echo escapeHtml($clientName); ?>
                    · <?php echo escapeHtml($serviceName); ?>
                    <?php if ($city !== ''): ?>
                        · <?php echo escapeHtml($city); ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="request-summary-status">
                <span class="<?php echo escapeHtml(adminDashboardStatusClass($status)); ?>">
                    <?php echo escapeHtml($status); ?>
                </span>
            </div>
        </div>

        <?php if (!empty($badges)): ?>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php foreach ($badges as $badge): ?>
                    <span class="badge rounded-pill <?php echo escapeHtml($badge['class']); ?>">
                        <?php echo escapeHtml($badge['label']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="request-summary-meta">
            <div>
                <strong>Next Action:</strong>
                <?php echo $nextAction !== '' ? escapeHtml($nextAction) : 'Needs one'; ?>
            </div>

            <div>
                <strong>Due:</strong>
                <?php echo escapeHtml(adminDashboardDateTimeDisplay($request['next_action_due_at'])); ?>
            </div>

            <div>
                <strong>Scheduled:</strong>
                <?php echo escapeHtml(adminDashboardDateTimeDisplay($request['scheduled_start'])); ?>
            </div>

            <div>
                <strong>Price:</strong>
                <?php echo escapeHtml(adminDashboardMoney($price)); ?>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="<?php echo escapeHtml($mainUrl); ?>" class="btn btn-light">
                <?php echo escapeHtml($mainButtonText); ?>
            </a>

            <?php if ($sectionType === 'invoice'): ?>
                <a href="<?php echo escapeHtml($requestDetailUrl); ?>" class="btn btn-outline-light">
                    Request Detail
                </a>
            <?php endif; ?>

            <a href="<?php echo escapeHtml($requestEditUrl); ?>" class="btn btn-outline-light">
                Edit Request
            </a>

            <a href="<?php echo escapeHtml($clientDetailUrl); ?>" class="btn btn-outline-light">
                Client Detail
            </a>
        </div>
    </article>

    <?php
}

function adminDashboardRenderDocumentCard(array $document): void
{
    $documentId = (int)($document['id'] ?? 0);
    $requestId = (int)($document['quote_request_id'] ?? 0);
    $clientId = (int)($document['client_id'] ?? 0);

    $documentNumber = adminDashboardDocumentNumber($document);
    $documentTypeLabel = adminDashboardDocumentTypeLabel((string)($document['document_type'] ?? ''));
    $documentTitle = trim((string)($document['document_title'] ?? ''));

    if ($documentTitle === '') {
        $documentTitle = $documentTypeLabel . ' Draft';
    }

    $clientName = trim((string)($document['full_name'] ?? 'Unknown Client'));
    $serviceSummary = trim((string)($document['service_summary'] ?? ''));

    if ($serviceSummary === '') {
        $serviceSummary = trim((string)($document['job_title'] ?? 'Outdoor Service'));
    }

    $editUrl = '/admin_document_edit.php?id=' . urlencode((string)$documentId);
    $requestDetailUrl = '/admin_request_detail.php?id=' . urlencode((string)$requestId);
    $clientDetailUrl = '/admin_client_detail.php?id=' . urlencode((string)$clientId);
    ?>

    <article class="card request-summary-card p-4">
        <div class="request-summary-header">
            <div>
                <p class="kails-text-yellow fw-bold text-uppercase mb-1">
                    <?php echo escapeHtml($documentTypeLabel); ?>
                </p>

                <h3 class="h5 mb-1">
                    <a href="<?php echo escapeHtml($editUrl); ?>" class="text-decoration-none">
                        <?php echo escapeHtml($documentTitle); ?>
                    </a>
                </h3>

                <p class="text-muted mb-0">
                    <?php echo escapeHtml($documentNumber); ?>
                    · <?php echo escapeHtml($clientName); ?>
                    · <?php echo escapeHtml($serviceSummary); ?>
                </p>
            </div>

            <div class="request-summary-status">
                <span class="request-status request-status-new">
                    Draft
                </span>
            </div>
        </div>

        <div class="request-summary-meta">
            <div>
                <strong>Total:</strong>
                <?php echo escapeHtml(adminDashboardMoney($document['total_amount'] ?? 0)); ?>
            </div>

            <div>
                <strong>Balance:</strong>
                <?php echo escapeHtml(adminDashboardMoney($document['balance_due'] ?? 0)); ?>
            </div>

            <div>
                <strong>Issued:</strong>
                <?php echo escapeHtml(adminDashboardDateDisplay($document['issue_date'] ?? null)); ?>
            </div>

            <div>
                <strong>Updated:</strong>
                <?php echo escapeHtml(adminDashboardDateDisplay($document['updated_at'] ?? null)); ?>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="<?php echo escapeHtml($editUrl); ?>" class="btn btn-light">
                Continue Editing
            </a>

            <?php if ($requestId > 0): ?>
                <a href="<?php echo escapeHtml($requestDetailUrl); ?>" class="btn btn-outline-light">
                    Request Detail
                </a>
            <?php endif; ?>

            <?php if ($clientId > 0): ?>
                <a href="<?php echo escapeHtml($clientDetailUrl); ?>" class="btn btn-outline-light">
                    Client Detail
                </a>
            <?php endif; ?>
        </div>
    </article>

    <?php
}

function adminDashboardJumpItems(array $stats): array
{
    return [
        [
            'href' => '#immediate-attention',
            'label' => 'Immediate',
            'full_label' => 'Immediate Attention',
            'count' => (int)$stats['needs_attention'],
            'tone' => 'warning',
        ],
        [
            'href' => '#customer-updates',
            'label' => 'Updates',
            'full_label' => 'Customer Updates',
            'count' => (int)$stats['customer_updates'],
            'tone' => 'info',
        ],
        [
            'href' => '#overdue-requests',
            'label' => 'Overdue',
            'full_label' => 'Overdue',
            'count' => (int)$stats['overdue'],
            'tone' => 'danger',
        ],
        [
            'href' => '#new-requests',
            'label' => 'New',
            'full_label' => 'New Requests',
            'count' => (int)$stats['new_requests'],
            'tone' => 'warning',
        ],
        [
            'href' => '#missing-next-actions',
            'label' => 'No Next Step',
            'full_label' => 'Missing Next Action',
            'count' => (int)$stats['missing_next_action'],
            'tone' => 'warning',
        ],
        [
            'href' => '#need-invoice',
            'label' => 'Invoice',
            'full_label' => 'Needs Invoice',
            'count' => (int)$stats['need_invoice'],
            'tone' => 'primary',
        ],
        [
            'href' => '#scheduled-work',
            'label' => 'Scheduled',
            'full_label' => 'Scheduled Work',
            'count' => (int)$stats['scheduled_today'],
            'tone' => 'secondary',
        ],
        [
            'href' => '#draft-documents',
            'label' => 'Drafts',
            'full_label' => 'Draft Documents',
            'count' => (int)$stats['draft_documents'],
            'tone' => 'secondary',
        ],
    ];
}

function adminDashboardRenderJumpMenu(array $stats, string $layout = 'desktop'): void
{
    $items = adminDashboardJumpItems($stats);

    if ($layout === 'mobile') {
        ?>
        <div class="card p-3 mb-4 d-lg-none admin-attention-mobile-menu">
            <p class="kails-text-yellow fw-bold text-uppercase small mb-2">
                Jump to attention section
            </p>

            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($items as $item): ?>
                    <a href="<?php echo escapeHtml((string)$item['href']); ?>" class="btn btn-sm btn-outline-light">
                        <?php echo escapeHtml((string)$item['label']); ?>
                        <span class="badge text-bg-<?php echo escapeHtml((string)$item['tone']); ?> ms-1">
                            <?php echo escapeHtml((string)$item['count']); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return;
    }
    ?>

    <div class="card p-3">
        <p class="kails-text-yellow fw-bold text-uppercase small mb-2">
            Needs Attention
        </p>

        <nav class="d-grid gap-2" aria-label="Admin attention sections">
            <?php foreach ($items as $item): ?>
                <a href="<?php echo escapeHtml((string)$item['href']); ?>" class="btn btn-sm btn-outline-light d-flex justify-content-between align-items-center">
                    <span><?php echo escapeHtml((string)$item['full_label']); ?></span>
                    <span class="badge text-bg-<?php echo escapeHtml((string)$item['tone']); ?>">
                        <?php echo escapeHtml((string)$item['count']); ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <?php
}

$stats = [
    'needs_attention' => 0,
    'customer_updates' => 0,
    'overdue' => 0,
    'new_requests' => 0,
    'missing_next_action' => 0,
    'need_invoice' => 0,
    'scheduled_today' => 0,
    'draft_documents' => 0,
    'active_requests' => 0,
];

$immediateRequests = [];
$customerUpdateRequests = [];
$overdueRequests = [];
$newRequests = [];
$missingActionRequests = [];
$needInvoiceRequests = [];
$upcomingRequests = [];
$draftDocuments = [];

try {
    $connection = getDatabaseConnection();

    $stats = adminDashboardLoadStats($connection);
    $immediateRequests = adminDashboardLoadImmediateRequests($connection);
    $customerUpdateRequests = adminDashboardLoadCustomerUpdateRequests($connection);
    $overdueRequests = adminDashboardLoadOverdueRequests($connection);
    $newRequests = adminDashboardLoadNewRequests($connection);
    $missingActionRequests = adminDashboardLoadMissingActionRequests($connection);
    $needInvoiceRequests = adminDashboardLoadNeedInvoiceRequests($connection);
    $upcomingRequests = adminDashboardLoadUpcomingRequests($connection);
    $draftDocuments = adminDashboardLoadDraftDocuments($connection);
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'The admin dashboard could not be loaded.';
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<style>
    html {
        scroll-behavior: smooth;
    }

    #immediate-attention,
    #customer-updates,
    #overdue-requests,
    #new-requests,
    #missing-next-actions,
    #need-invoice,
    #scheduled-work,
    #draft-documents {
        scroll-margin-top: 1rem;
    }

    .admin-attention-sidebar-column {
        position: sticky;
        top: 1rem;
        align-self: flex-start;
        max-height: calc(100vh - 2rem);
        overflow-y: auto;
        z-index: 5;
    }

    .admin-attention-mobile-menu {
        position: sticky;
        top: 0.5rem;
        z-index: 10;
    }
</style>

<main class="site-section">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('dashboard'); ?>

        <div class="mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                Admin Home
            </p>

            <h1 class="fw-bold">
                What Needs Attention?
            </h1>

            <p class="text-muted mb-0">
                Start here. Use the attention menu to jump between customer updates, overdue work, new requests, missing next actions, invoices, scheduled work, and draft documents.
            </p>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <a href="/admin_request_status_settings.php" class="btn btn-sm btn-outline-light">
                    Request Response Timing
                </a>
            </div>
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

        <?php adminDashboardRenderJumpMenu($stats, 'mobile'); ?>

        <div class="row g-4 align-items-start">
            <div class="col-lg-3 d-none d-lg-block admin-attention-sidebar-column">
                <?php adminDashboardRenderJumpMenu($stats, 'desktop'); ?>
            </div>

            <div class="col-lg-9">
                <section class="card p-4 mb-4" id="immediate-attention">
                    <div class="mb-4">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Immediate Attention
                        </p>

                        <h2 class="h4 mb-1">
                            Handle these first
                        </h2>

                        <p class="text-muted mb-0">
                            These are sorted by customer updates, overdue items, new requests, missing next actions, and due dates.
                        </p>
                    </div>

                    <?php if (empty($immediateRequests)): ?>
                        <div class="alert alert-success mb-0">
                            No urgent requests need attention right now.
                        </div>
                    <?php else: ?>
                        <div class="request-card-list">
                            <?php foreach ($immediateRequests as $request): ?>
                                <?php adminDashboardRenderRequestCard($request, 'attention'); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card p-4 mb-4" id="customer-updates">
                    <div class="mb-4">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Customer Updates
                        </p>

                        <h2 class="h4 mb-1">
                            Customers waiting for review
                        </h2>

                        <p class="text-muted mb-0">
                            These requests have customer comments that have not been reviewed yet.
                        </p>
                    </div>

                    <?php if (empty($customerUpdateRequests)): ?>
                        <div class="alert alert-success mb-0">
                            No customer updates are waiting for review.
                        </div>
                    <?php else: ?>
                        <div class="request-card-list">
                            <?php foreach ($customerUpdateRequests as $request): ?>
                                <?php adminDashboardRenderRequestCard($request, 'attention'); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card p-4 mb-4" id="overdue-requests">
                    <div class="mb-4">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Overdue
                        </p>

                        <h2 class="h4 mb-1">
                            Past due requests and jobs
                        </h2>

                        <p class="text-muted mb-0">
                            These requests are past their next action due date or their scheduled time has passed.
                        </p>
                    </div>

                    <?php if (empty($overdueRequests)): ?>
                        <div class="alert alert-success mb-0">
                            No requests or jobs are overdue.
                        </div>
                    <?php else: ?>
                        <div class="request-card-list">
                            <?php foreach ($overdueRequests as $request): ?>
                                <?php adminDashboardRenderRequestCard($request, 'attention'); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card p-4 mb-4" id="new-requests">
                    <div class="mb-4">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            New Requests
                        </p>

                        <h2 class="h4 mb-1">
                            New requests that need review
                        </h2>

                        <p class="text-muted mb-0">
                            These are requests that came in and have not moved past New yet.
                        </p>
                    </div>

                    <?php if (empty($newRequests)): ?>
                        <div class="alert alert-success mb-0">
                            No new requests are waiting.
                        </div>
                    <?php else: ?>
                        <div class="request-card-list">
                            <?php foreach ($newRequests as $request): ?>
                                <?php adminDashboardRenderRequestCard($request, 'attention'); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card p-4 mb-4" id="missing-next-actions">
                    <div class="mb-4">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Missing Next Action
                        </p>

                        <h2 class="h4 mb-1">
                            Requests without a next step
                        </h2>

                        <p class="text-muted mb-0">
                            These active requests need a clear next action so they do not get forgotten.
                        </p>
                    </div>

                    <?php if (empty($missingActionRequests)): ?>
                        <div class="alert alert-success mb-0">
                            Every active request has a next action.
                        </div>
                    <?php else: ?>
                        <div class="request-card-list">
                            <?php foreach ($missingActionRequests as $request): ?>
                                <?php adminDashboardRenderRequestCard($request, 'attention'); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card p-4 mb-4" id="need-invoice">
                    <div class="mb-4">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Billing
                        </p>

                        <h2 class="h4 mb-1">
                            Completed jobs needing invoice
                        </h2>

                        <p class="text-muted mb-0">
                            These jobs are marked completed but do not have an invoice yet.
                        </p>
                    </div>

                    <?php if (empty($needInvoiceRequests)): ?>
                        <div class="alert alert-success mb-0">
                            No completed jobs are waiting for an invoice.
                        </div>
                    <?php else: ?>
                        <div class="request-card-list">
                            <?php foreach ($needInvoiceRequests as $request): ?>
                                <?php adminDashboardRenderRequestCard($request, 'invoice'); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card p-4 mb-4" id="scheduled-work">
                    <div class="mb-4">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Schedule
                        </p>

                        <h2 class="h4 mb-1">
                            Upcoming scheduled work
                        </h2>

                        <p class="text-muted mb-0">
                            These are the next scheduled jobs.
                        </p>
                    </div>

                    <?php if (empty($upcomingRequests)): ?>
                        <div class="alert alert-info mb-0">
                            No scheduled jobs are currently listed.
                        </div>
                    <?php else: ?>
                        <div class="request-card-list">
                            <?php foreach ($upcomingRequests as $request): ?>
                                <?php adminDashboardRenderRequestCard($request, 'scheduled'); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="card p-4 mb-4" id="draft-documents">
                    <div class="mb-4">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Draft Documents
                        </p>

                        <h2 class="h4 mb-1">
                            Quotes and invoices still in draft
                        </h2>

                        <p class="text-muted mb-0">
                            These documents were started but are still marked as drafts.
                        </p>
                    </div>

                    <?php if (empty($draftDocuments)): ?>
                        <div class="alert alert-success mb-0">
                            No draft documents need attention.
                        </div>
                    <?php else: ?>
                        <div class="request-card-list">
                            <?php foreach ($draftDocuments as $document): ?>
                                <?php adminDashboardRenderDocumentCard($document); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</main>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';