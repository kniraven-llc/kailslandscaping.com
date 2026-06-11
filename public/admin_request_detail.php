<?php
declare(strict_types=1);

/*
    Admin request detail page.

    This page shows one request/job.

    It handles:
    - viewing request details
    - viewing client information
    - viewing the customer-facing request link
    - adding admin-only comments
    - adding customer-facing admin comments
    - marking customer updates reviewed
    - viewing quote/invoice cards for this request

    It does not handle:
    - editing request fields
    - editing client fields
    - editing quote/invoice details

    Those belong to:
    - /admin_request_edit.php?id=123
    - /admin_client_edit.php?id=123
    - /admin_document_edit.php?id=456
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Request Detail';

adminRequireLogin('Request Detail Login');

$messages = [];
$errors = [];

function requestDetailDateDisplay($value): string
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

function requestDetailDateTimeDisplay($value): string
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

function requestDetailMoney($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return '$' . number_format((float)$value, 2);
}

function requestDetailCleanText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function requestDetailTextarea(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    return $value;
}

function requestDetailRequestNumber(array $request): string
{
    $requestNumber = trim((string)($request['request_number'] ?? ''));

    if ($requestNumber !== '') {
        return $requestNumber;
    }

    return 'Request #' . (string)($request['id'] ?? '');
}

function requestDetailServiceName(array $request): string
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

function requestDetailJobTitle(array $request): string
{
    $jobTitle = trim((string)($request['job_title'] ?? ''));

    if ($jobTitle !== '') {
        return $jobTitle;
    }

    return requestDetailServiceName($request);
}

function requestDetailStatusClass(string $status): string
{
    $statusClass = strtolower($status);
    $statusClass = str_replace(' ', '-', $statusClass);
    $statusClass = preg_replace('/[^a-z0-9-]/', '', $statusClass);

    return 'request-status request-status-' . $statusClass;
}

function requestDetailFullRequestAddress(array $request): string
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

function requestDetailFullClientAddress(array $request): string
{
    $addressParts = [];

    $streetAddress = trim((string)($request['client_street_address'] ?? ''));

    if ($streetAddress !== '') {
        $addressParts[] = $streetAddress;
    }

    $cityStateZip = trim(
        trim((string)($request['client_city'] ?? ''))
        . ', '
        . trim((string)($request['client_state'] ?? ''))
        . ' '
        . trim((string)($request['client_zip_code'] ?? ''))
    );

    $cityStateZip = trim($cityStateZip, ', ');

    if ($cityStateZip !== '') {
        $addressParts[] = $cityStateZip;
    }

    return implode(' · ', $addressParts);
}

function requestDetailIsInactive(array $request): bool
{
    $status = (string)($request['request_status'] ?? '');

    if ((int)($request['is_archived'] ?? 0) === 1) {
        return true;
    }

    return in_array($status, ['Completed', 'Cancelled', 'Archived'], true);
}

function requestDetailAttentionBadges(array $request): array
{
    $badges = [];

    if (requestDetailIsInactive($request)) {
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

function requestDetailDocumentTypeLabel(string $documentType): string
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

function requestDetailDocumentNumber(array $document): string
{
    $documentNumber = trim((string)($document['document_number'] ?? ''));

    if ($documentNumber !== '') {
        return $documentNumber;
    }

    return 'Document #' . (string)($document['id'] ?? '');
}

function requestDetailCurrentAdminName(): string
{
    if (!function_exists('adminCurrentUser')) {
        return 'Admin';
    }

    $currentUser = adminCurrentUser();

    if (!is_array($currentUser)) {
        return 'Admin';
    }

    $displayName = trim((string)($currentUser['display_name'] ?? ''));

    if ($displayName !== '') {
        return $displayName;
    }

    $username = trim((string)($currentUser['username'] ?? ''));

    if ($username !== '') {
        return $username;
    }

    return 'Admin';
}

function requestDetailGeneratedRequestNumber(int $requestId, $createdAt): string
{
    $timestamp = strtotime((string)$createdAt);

    if ($timestamp === false) {
        $timestamp = time();
    }

    return 'KR-' . date('Ymd', $timestamp) . '-' . str_pad((string)$requestId, 4, '0', STR_PAD_LEFT);
}

function requestDetailCustomerUrl(array $request): string
{
    $requestNumber = trim((string)($request['request_number'] ?? ''));
    $token = trim((string)($request['public_access_token'] ?? ''));

    if ($requestNumber === '' || $token === '') {
        return '';
    }

    $path = '/request-update.php?request=' . rawurlencode($requestNumber) . '&key=' . rawurlencode($token);

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));

    if ($host === '') {
        return $path;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';

    return $scheme . '://' . $host . $path;
}

function getRequestDetailRecord(int $requestId): ?array
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
            qr.preferred_contact_method AS request_preferred_contact_method,
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
            c.phone_normalized,
            c.email,
            c.preferred_contact_method AS client_preferred_contact_method,
            c.street_address AS client_street_address,
            c.city AS client_city,
            c.state AS client_state,
            c.zip_code AS client_zip_code,
            c.notes AS client_notes,

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

function getRequestDetailComments(int $requestId): array
{
    if ($requestId <= 0) {
        return [];
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            id,
            quote_request_id,
            author_type,
            author_name,
            visibility,
            comment_text,
            created_at
         FROM quote_request_comments
         WHERE quote_request_id = ?
         ORDER BY created_at DESC, id DESC'
    );

    $statement->bind_param('i', $requestId);
    $statement->execute();

    $result = $statement->get_result();
    $comments = [];

    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }

    return $comments;
}

function getRequestDetailDocuments(int $requestId): array
{
    if ($requestId <= 0) {
        return [];
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            id,
            client_id,
            quote_request_id,
            document_type,
            document_number,
            document_status,
            document_title,
            service_summary,
            issue_date,
            due_date,
            total_amount,
            balance_due,
            created_at,
            updated_at
         FROM client_documents
         WHERE quote_request_id = ?
         ORDER BY
            CASE
                WHEN document_type = "quote" THEN 0
                ELSE 1
            END ASC,
            created_at DESC,
            id DESC'
    );

    $statement->bind_param('i', $requestId);
    $statement->execute();

    $result = $statement->get_result();
    $documents = [];

    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    return $documents;
}

function addRequestDetailAdminComment(int $requestId, array $postedData, array &$errors): void
{
    if ($requestId <= 0) {
        $errors[] = 'Request ID is missing.';
        return;
    }

    $visibility = requestDetailCleanText((string)($postedData['visibility'] ?? 'internal'));
    $commentText = requestDetailTextarea((string)($postedData['comment_text'] ?? ''));

    if (!in_array($visibility, ['internal', 'customer'], true)) {
        $errors[] = 'Comment visibility is invalid.';
    }

    if ($commentText === '') {
        $errors[] = 'Comment text is required.';
    }

    if (!empty($errors)) {
        return;
    }

    $connection = getDatabaseConnection();

    $authorType = 'admin';
    $authorName = requestDetailCurrentAdminName();

    $statement = $connection->prepare(
        'INSERT INTO quote_request_comments
            (
                quote_request_id,
                author_type,
                author_name,
                visibility,
                comment_text
            )
         VALUES
            (?, ?, ?, ?, ?)'
    );

    $statement->bind_param(
        'issss',
        $requestId,
        $authorType,
        $authorName,
        $visibility,
        $commentText
    );

    $statement->execute();

    $updateStatement = $connection->prepare(
        'UPDATE quote_requests
         SET
            last_admin_reviewed_at = NOW(),
            last_queue_action_at = NOW()
         WHERE id = ?'
    );

    $updateStatement->bind_param('i', $requestId);
    $updateStatement->execute();
}

function markRequestDetailReviewed(int $requestId): void
{
    if ($requestId <= 0) {
        return;
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'UPDATE quote_requests
         SET
            last_admin_reviewed_at = NOW(),
            last_queue_action_at = NOW()
         WHERE id = ?'
    );

    $statement->bind_param('i', $requestId);
    $statement->execute();
}

function ensureRequestDetailCustomerAccess(int $requestId): void
{
    if ($requestId <= 0) {
        return;
    }

    $connection = getDatabaseConnection();

    $selectStatement = $connection->prepare(
        'SELECT
            id,
            request_number,
            public_access_token,
            created_at
         FROM quote_requests
         WHERE id = ?
         LIMIT 1'
    );

    $selectStatement->bind_param('i', $requestId);
    $selectStatement->execute();

    $request = $selectStatement->get_result()->fetch_assoc();

    if (!$request) {
        return;
    }

    $requestNumber = trim((string)($request['request_number'] ?? ''));
    $token = trim((string)($request['public_access_token'] ?? ''));

    if ($requestNumber === '') {
        $requestNumber = requestDetailGeneratedRequestNumber($requestId, $request['created_at'] ?? null);
    }

    if ($token === '') {
        $token = bin2hex(random_bytes(32));
    }

    $updateStatement = $connection->prepare(
        'UPDATE quote_requests
         SET
            request_number = ?,
            public_access_token = ?
         WHERE id = ?'
    );

    $updateStatement->bind_param(
        'ssi',
        $requestNumber,
        $token,
        $requestId
    );

    $updateStatement->execute();
}

function renderRequestDetailDocumentCard(array $document): void
{
    $documentId = (int)($document['id'] ?? 0);
    $documentTypeLabel = requestDetailDocumentTypeLabel((string)($document['document_type'] ?? ''));
    $documentNumber = requestDetailDocumentNumber($document);
    $documentTitle = trim((string)($document['document_title'] ?? ''));

    if ($documentTitle === '') {
        $documentTitle = $documentTypeLabel . ' Document';
    }

    $editUrl = '/admin_document_edit.php?id=' . urlencode((string)$documentId);
    $printUrl = '/document_print.php?id=' . urlencode((string)$documentId);
    ?>

    <article class="admin-inner-panel">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <p class="kails-text-yellow fw-bold text-uppercase mb-1">
                    <?php echo escapeHtml($documentTypeLabel); ?>
                </p>

                <h3 class="h5 mb-1">
                    <?php echo escapeHtml($documentTitle); ?>
                </h3>

                <p class="text-muted mb-0">
                    <?php echo escapeHtml($documentNumber); ?>
                    · Status: <?php echo escapeHtml((string)($document['document_status'] ?? 'Draft')); ?>
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-start">
                <a href="<?php echo escapeHtml($editUrl); ?>" class="btn btn-light">
                    Edit
                </a>

                <a href="<?php echo escapeHtml($printUrl); ?>" class="btn btn-outline-light">
                    Print
                </a>
            </div>
        </div>

        <div class="request-summary-meta mt-3">
            <div>
                <strong>Total:</strong>
                <?php echo escapeHtml(requestDetailMoney($document['total_amount'] ?? null)); ?>
            </div>

            <div>
                <strong>Balance:</strong>
                <?php echo escapeHtml(requestDetailMoney($document['balance_due'] ?? null)); ?>
            </div>

            <div>
                <strong>Issued:</strong>
                <?php echo escapeHtml(requestDetailDateDisplay($document['issue_date'] ?? null)); ?>
            </div>

            <div>
                <strong>Due:</strong>
                <?php echo escapeHtml(requestDetailDateDisplay($document['due_date'] ?? null)); ?>
            </div>
        </div>
    </article>

    <?php
}

function renderRequestDetailComment(array $comment): void
{
    $authorType = trim((string)($comment['author_type'] ?? ''));
    $authorName = trim((string)($comment['author_name'] ?? ''));

    if ($authorName === '') {
        $authorName = $authorType === 'customer' ? 'Customer' : 'Admin';
    }

    $visibility = trim((string)($comment['visibility'] ?? 'internal'));
    $visibilityLabel = $visibility === 'customer' ? 'Customer-Facing' : 'Admin Only';
    $visibilityClass = $visibility === 'customer' ? 'text-bg-info text-dark' : 'text-bg-secondary';
    $authorClass = $authorType === 'customer' ? 'text-bg-warning text-dark' : 'text-bg-light text-dark';
    ?>

    <article class="admin-inner-panel">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3">
            <div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="badge rounded-pill <?php echo escapeHtml($authorClass); ?>">
                        <?php echo escapeHtml(ucfirst($authorType !== '' ? $authorType : 'admin')); ?>
                    </span>

                    <span class="badge rounded-pill <?php echo escapeHtml($visibilityClass); ?>">
                        <?php echo escapeHtml($visibilityLabel); ?>
                    </span>
                </div>

                <h3 class="h6 mb-0">
                    <?php echo escapeHtml($authorName); ?>
                </h3>
            </div>

            <div class="text-muted small">
                <?php echo escapeHtml(requestDetailDateTimeDisplay($comment['created_at'] ?? null)); ?>
            </div>
        </div>

        <p class="mb-0">
            <?php echo nl2br(escapeHtml((string)($comment['comment_text'] ?? ''))); ?>
        </p>
    </article>

    <?php
}

$requestId = (int)($_GET['id'] ?? $_POST['request_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formName = (string)($_POST['form_name'] ?? '');
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    } else {
        try {
            if ($formName === 'add_admin_comment') {
                addRequestDetailAdminComment($requestId, $_POST, $errors);

                if (empty($errors)) {
                    redirectTo('/admin_request_detail.php?id=' . $requestId . '&comment_saved=1#request-comments');
                }
            }

            if ($formName === 'mark_reviewed') {
                markRequestDetailReviewed($requestId);
                redirectTo('/admin_request_detail.php?id=' . $requestId . '&reviewed=1#request-comments');
            }

            if ($formName === 'generate_customer_link') {
                ensureRequestDetailCustomerAccess($requestId);
                redirectTo('/admin_request_detail.php?id=' . $requestId . '&customer_link_created=1#customer-access');
            }
        } catch (Throwable $exception) {
            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'The request update could not be saved.';
            }
        }
    }
}

if (isset($_GET['comment_saved'])) {
    $messages[] = 'Comment saved.';
}

if (isset($_GET['reviewed'])) {
    $messages[] = 'Customer updates marked reviewed.';
}

if (isset($_GET['customer_link_created'])) {
    $messages[] = 'Customer access link is ready.';
}

if (isset($_GET['saved'])) {
    $messages[] = 'Request saved.';
}

$request = null;
$comments = [];
$documents = [];

try {
    $request = getRequestDetailRecord($requestId);

    if ($request === null) {
        $errors[] = 'Request not found.';
    } else {
        $comments = getRequestDetailComments($requestId);
        $documents = getRequestDetailDocuments($requestId);
    }
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'Request detail could not be loaded.';
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<style>
    .request-detail-copy-input {
        font-family: monospace;
        font-size: 0.9rem;
    }

    .request-detail-token {
        word-break: break-all;
        font-family: monospace;
        font-size: 0.875rem;
    }
</style>

<main class="site-section">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('requests'); ?>

        <div class="mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                Request Detail
            </p>

            <h1 class="fw-bold">
                <?php echo $request !== null ? escapeHtml(requestDetailRequestNumber($request)) : 'Request Not Found'; ?>
            </h1>

            <p class="text-muted mb-0">
                Review this request, add comments, copy the customer link, and manage related quotes or invoices.
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
            $requestNumber = requestDetailRequestNumber($request);
            $jobTitle = requestDetailJobTitle($request);
            $serviceName = requestDetailServiceName($request);
            $status = trim((string)($request['request_status'] ?? 'New'));
            $requestAddress = requestDetailFullRequestAddress($request);
            $clientAddress = requestDetailFullClientAddress($request);
            $customerUrl = requestDetailCustomerUrl($request);
            $badges = requestDetailAttentionBadges($request);
            $clientId = (int)($request['client_id'] ?? 0);
            $price = (float)($request['final_price'] ?? 0.00);

            if ($price <= 0) {
                $price = (float)($request['quoted_price'] ?? 0.00);
            }
            ?>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="/admin_requests.php" class="btn btn-outline-light">
                    Back to Requests
                </a>

                <a href="/admin_client_detail.php?id=<?php echo escapeHtml((string)$clientId); ?>" class="btn btn-outline-light">
                    Client Detail
                </a>

                <a href="/admin_request_edit.php?id=<?php echo escapeHtml((string)$requestId); ?>" class="btn btn-light">
                    Edit Request
                </a>
            </div>

            <section class="card p-4 mb-4">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <p class="kails-text-yellow fw-bold text-uppercase mb-1">
                                    <?php echo escapeHtml($requestNumber); ?>
                                </p>

                                <h2 class="h3 mb-2">
                                    <?php echo escapeHtml($jobTitle); ?>
                                </h2>

                                <div class="d-flex flex-wrap gap-2">
                                    <span class="<?php echo escapeHtml(requestDetailStatusClass($status)); ?>">
                                        <?php echo escapeHtml($status); ?>
                                    </span>

                                    <?php foreach ($badges as $badge): ?>
                                        <span class="badge rounded-pill <?php echo escapeHtml($badge['class']); ?>">
                                            <?php echo escapeHtml($badge['label']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <dl class="row mb-0">
                                <dt class="col-sm-4">Service</dt>
                                <dd class="col-sm-8"><?php echo escapeHtml($serviceName); ?></dd>

                                <dt class="col-sm-4">Request Source</dt>
                                <dd class="col-sm-8">
                                    <?php echo trim((string)($request['request_source'] ?? '')) !== '' ? escapeHtml((string)$request['request_source']) : '—'; ?>
                                </dd>

                                <dt class="col-sm-4">Request Address</dt>
                                <dd class="col-sm-8">
                                    <?php echo $requestAddress !== '' ? escapeHtml($requestAddress) : '—'; ?>
                                </dd>

                                <dt class="col-sm-4">Preferred Contact</dt>
                                <dd class="col-sm-8">
                                    <?php echo trim((string)($request['request_preferred_contact_method'] ?? '')) !== '' ? escapeHtml((string)$request['request_preferred_contact_method']) : '—'; ?>
                                </dd>

                                <dt class="col-sm-4">Created</dt>
                                <dd class="col-sm-8"><?php echo escapeHtml(requestDetailDateTimeDisplay($request['created_at'] ?? null)); ?></dd>

                                <dt class="col-sm-4">Updated</dt>
                                <dd class="col-sm-8"><?php echo escapeHtml(requestDetailDateTimeDisplay($request['updated_at'] ?? null)); ?></dd>
                            </dl>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="admin-inner-panel h-100">
                            <h2 class="h4 mb-3">
                                Current Work Status
                            </h2>

                            <div class="request-summary-meta">
                                <div>
                                    <strong>Next Action:</strong>
                                    <?php echo trim((string)($request['next_action'] ?? '')) !== '' ? escapeHtml((string)$request['next_action']) : 'Needs one'; ?>
                                </div>

                                <div>
                                    <strong>Due:</strong>
                                    <?php echo escapeHtml(requestDetailDateTimeDisplay($request['next_action_due_at'] ?? null)); ?>
                                </div>

                                <div>
                                    <strong>Scheduled:</strong>
                                    <?php echo escapeHtml(requestDetailDateTimeDisplay($request['scheduled_start'] ?? null)); ?>
                                </div>

                                <div>
                                    <strong>Completed:</strong>
                                    <?php echo escapeHtml(requestDetailDateTimeDisplay($request['completed_at'] ?? null)); ?>
                                </div>

                                <div>
                                    <strong>Price:</strong>
                                    <?php echo escapeHtml(requestDetailMoney($price)); ?>
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
                                    <strong>Comments:</strong>
                                    <?php echo escapeHtml((string)($request['comment_count'] ?? 0)); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (trim((string)($request['project_details'] ?? '')) !== ''): ?>
                        <div class="col-12">
                            <div class="admin-inner-panel">
                                <h2 class="h5">
                                    Project Details
                                </h2>

                                <p class="mb-0">
                                    <?php echo nl2br(escapeHtml((string)$request['project_details'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (trim((string)($request['next_action_notes'] ?? '')) !== ''): ?>
                        <div class="col-12">
                            <div class="admin-inner-panel">
                                <h2 class="h5">
                                    Next Action Notes
                                </h2>

                                <p class="mb-0">
                                    <?php echo nl2br(escapeHtml((string)$request['next_action_notes'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (trim((string)($request['public_notes'] ?? '')) !== '' || trim((string)($request['internal_notes'] ?? '')) !== ''): ?>
                        <div class="col-12">
                            <div class="row g-3">
                                <?php if (trim((string)($request['public_notes'] ?? '')) !== ''): ?>
                                    <div class="col-lg-6">
                                        <div class="admin-inner-panel h-100">
                                            <h2 class="h5">
                                                Public Notes
                                            </h2>

                                            <p class="mb-0">
                                                <?php echo nl2br(escapeHtml((string)$request['public_notes'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (trim((string)($request['internal_notes'] ?? '')) !== ''): ?>
                                    <div class="col-lg-6">
                                        <div class="admin-inner-panel h-100">
                                            <h2 class="h5">
                                                Internal Notes
                                            </h2>

                                            <p class="mb-0">
                                                <?php echo nl2br(escapeHtml((string)$request['internal_notes'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card p-4 mb-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h2 class="h4 mb-2">
                            Client
                        </h2>

                        <p class="mb-1">
                            <strong><?php echo escapeHtml((string)$request['full_name']); ?></strong>
                        </p>

                        <p class="text-muted mb-0">
                            <?php if (trim((string)($request['phone'] ?? '')) !== ''): ?>
                                <a href="tel:<?php echo escapeHtml(preg_replace('/\D+/', '', (string)$request['phone'])); ?>">
                                    <?php echo escapeHtml((string)$request['phone']); ?>
                                </a>
                            <?php endif; ?>

                            <?php if (trim((string)($request['email'] ?? '')) !== ''): ?>
                                <?php echo trim((string)($request['phone'] ?? '')) !== '' ? ' · ' : ''; ?>
                                <a href="mailto:<?php echo escapeHtml((string)$request['email']); ?>">
                                    <?php echo escapeHtml((string)$request['email']); ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($clientAddress !== ''): ?>
                                <br><?php echo escapeHtml($clientAddress); ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div>
                        <a href="/admin_client_detail.php?id=<?php echo escapeHtml((string)$clientId); ?>" class="btn btn-outline-light">
                            Open Client
                        </a>
                    </div>
                </div>
            </section>

            <section class="card p-4 mb-4" id="customer-access">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                    <div>
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Customer Access
                        </p>

                        <h2 class="h4 mb-1">
                            Customer-facing request link
                        </h2>

                        <p class="text-muted mb-0">
                            Give this link to the customer so they can view this request and add more details.
                        </p>
                    </div>

                    <?php if ($customerUrl === ''): ?>
                        <form method="post" action="/admin_request_detail.php">
                            <input type="hidden" name="form_name" value="generate_customer_link">
                            <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                            <input type="hidden" name="request_id" value="<?php echo escapeHtml((string)$requestId); ?>">

                            <button type="submit" class="btn btn-light">
                                Create Customer Link
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if ($customerUrl === ''): ?>
                    <div class="alert alert-warning mb-0">
                        This request does not have a customer access link yet.
                    </div>
                <?php else: ?>
                    <label for="customerAccessUrl" class="form-label">
                        Customer Link
                    </label>

                    <div class="input-group mb-3">
                        <input
                            type="text"
                            class="form-control request-detail-copy-input"
                            id="customerAccessUrl"
                            value="<?php echo escapeHtml($customerUrl); ?>"
                            readonly
                        >

                        <button type="button" class="btn btn-outline-light" id="copyCustomerAccessUrlButton">
                            Copy
                        </button>
                    </div>

                    <div class="request-summary-meta">
                        <div>
                            <strong>Request Number:</strong>
                            <?php echo escapeHtml((string)$request['request_number']); ?>
                        </div>

                        <div>
                            <strong>Token:</strong>
                            <span class="request-detail-token">
                                <?php echo escapeHtml((string)$request['public_access_token']); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card p-4 mb-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                    <div>
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Quotes / Invoices
                        </p>

                        <h2 class="h4 mb-1">
                            Documents for this request
                        </h2>

                        <p class="text-muted mb-0">
                            Create or edit the quote and invoice documents linked to this request.
                        </p>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <a href="/admin_document_edit.php?type=quote&request_id=<?php echo escapeHtml((string)$requestId); ?>" class="btn btn-outline-light">
                            Create Quote
                        </a>

                        <a href="/admin_document_edit.php?type=invoice&request_id=<?php echo escapeHtml((string)$requestId); ?>" class="btn btn-light">
                            Create Invoice
                        </a>
                    </div>
                </div>

                <?php if (empty($documents)): ?>
                    <div class="alert alert-info mb-0">
                        No quote or invoice documents have been created for this request yet.
                    </div>
                <?php else: ?>
                    <div class="request-card-list">
                        <?php foreach ($documents as $document): ?>
                            <?php renderRequestDetailDocumentCard($document); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card p-4 mb-4" id="request-comments">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                    <div>
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Comments / Timeline
                        </p>

                        <h2 class="h4 mb-1">
                            Request conversation and notes
                        </h2>

                        <p class="text-muted mb-0">
                            Admin-only comments stay internal. Customer-facing comments can be shown to the customer.
                        </p>
                    </div>

                    <form method="post" action="/admin_request_detail.php">
                        <input type="hidden" name="form_name" value="mark_reviewed">
                        <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                        <input type="hidden" name="request_id" value="<?php echo escapeHtml((string)$requestId); ?>">

                        <button type="submit" class="btn btn-outline-light">
                            Mark Customer Updates Reviewed
                        </button>
                    </form>
                </div>

                <form method="post" action="/admin_request_detail.php#request-comments" class="admin-inner-panel mb-4">
                    <input type="hidden" name="form_name" value="add_admin_comment">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                    <input type="hidden" name="request_id" value="<?php echo escapeHtml((string)$requestId); ?>">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="visibility" class="form-label">
                                Comment Type
                            </label>

                            <select class="form-control" id="visibility" name="visibility">
                                <option value="internal">Admin Only</option>
                                <option value="customer">Customer-Facing</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="comment_text" class="form-label">
                                Comment
                            </label>

                            <textarea
                                class="form-control"
                                id="comment_text"
                                name="comment_text"
                                rows="4"
                                required
                            ></textarea>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-light">
                            Save Comment
                        </button>
                    </div>
                </form>

                <?php if (empty($comments)): ?>
                    <div class="alert alert-info mb-0">
                        No comments have been added yet.
                    </div>
                <?php else: ?>
                    <div class="request-card-list">
                        <?php foreach ($comments as $comment): ?>
                            <?php renderRequestDetailComment($comment); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const copyButton = document.getElementById('copyCustomerAccessUrlButton');
    const urlInput = document.getElementById('customerAccessUrl');

    if (!copyButton || !urlInput) {
        return;
    }

    function showCopyStatus(message, isError) {
        copyButton.textContent = message;

        if (isError) {
            copyButton.classList.remove('btn-outline-light');
            copyButton.classList.add('btn-danger');
        } else {
            copyButton.classList.remove('btn-outline-light');
            copyButton.classList.add('btn-success');
        }

        setTimeout(function () {
            copyButton.textContent = 'Copy';
            copyButton.classList.remove('btn-success', 'btn-danger');
            copyButton.classList.add('btn-outline-light');
        }, 1500);
    }

    function fallbackCopyText(textToCopy) {
        const temporaryTextarea = document.createElement('textarea');

        temporaryTextarea.value = textToCopy;
        temporaryTextarea.setAttribute('readonly', 'readonly');

        temporaryTextarea.style.position = 'fixed';
        temporaryTextarea.style.top = '0';
        temporaryTextarea.style.left = '-9999px';
        temporaryTextarea.style.opacity = '0';

        document.body.appendChild(temporaryTextarea);

        temporaryTextarea.focus();
        temporaryTextarea.select();

        let copied = false;

        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(temporaryTextarea);

        return copied;
    }

    async function copyTextToClipboard(textToCopy) {
        /*
            The modern clipboard tool only works on secure pages.
            Secure pages are usually HTTPS or localhost.
        */
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(textToCopy);
                return true;
            } catch (error) {
                // Fall through to the older copy method below.
            }
        }

        return fallbackCopyText(textToCopy);
    }

    copyButton.addEventListener('click', async function () {
        const textToCopy = urlInput.value.trim();

        if (textToCopy === '') {
            showCopyStatus('No Link', true);
            return;
        }

        const copied = await copyTextToClipboard(textToCopy);

        if (copied) {
            showCopyStatus('Copied', false);
        } else {
            /*
                Select the visible input as a last resort.
                This makes it easy for the admin to press Ctrl+C manually.
            */
            urlInput.focus();
            urlInput.select();
            urlInput.setSelectionRange(0, textToCopy.length);

            showCopyStatus('Copy Failed', true);
        }
    });
});
</script>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';