<?php
declare(strict_types=1);

/*
    Customer-facing request page.

    This page lets a customer view one request and add more details.

    Security rule:
    The customer must have both:
    - request number
    - public access token

    A request number alone is not enough.
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';

$pageTitle = 'Request Update';

$messages = [];
$errors = [];

function customerRequestCleanText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function customerRequestTextarea(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    return $value;
}

function customerRequestDateDisplay($value): string
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

function customerRequestDateTimeDisplay($value): string
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

function customerRequestServiceName(array $request): string
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

function customerRequestJobTitle(array $request): string
{
    $jobTitle = trim((string)($request['job_title'] ?? ''));

    if ($jobTitle !== '') {
        return $jobTitle;
    }

    return customerRequestServiceName($request);
}

function customerRequestStatusClass(string $status): string
{
    $statusClass = strtolower($status);
    $statusClass = str_replace(' ', '-', $statusClass);
    $statusClass = preg_replace('/[^a-z0-9-]/', '', $statusClass);

    return 'request-status request-status-' . $statusClass;
}

function customerRequestStatusMessage(string $status): string
{
    return match ($status) {
        'New' => 'Your request has been received and is waiting for review.',
        'Quoted' => 'A quote or estimate has been prepared or sent.',
        'Scheduled' => 'Your work has been scheduled.',
        'In Progress' => 'Your work is currently in progress.',
        'Completed' => 'This request has been marked completed.',
        'Cancelled' => 'This request has been cancelled.',
        'Archived' => 'This request is no longer active.',
        default => 'This request is being reviewed.',
    };
}

function customerRequestFullAddress(array $request): string
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

function getCustomerRequestRecord(string $requestNumber, string $publicAccessToken): ?array
{
    if ($requestNumber === '' || $publicAccessToken === '') {
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
            qr.property_address,
            qr.property_city,
            qr.property_state,
            qr.property_zip_code,
            qr.preferred_contact_method,
            qr.scheduled_start,
            qr.scheduled_end,
            qr.completed_at,
            qr.created_at,
            qr.updated_at,

            c.full_name,
            c.phone,
            c.email,

            s.service_title

         FROM quote_requests qr
         INNER JOIN clients c
            ON c.id = qr.client_id
         LEFT JOIN services s
            ON s.id = qr.requested_service_id
         WHERE qr.request_number = ?
           AND qr.public_access_token = ?
         LIMIT 1'
    );

    $statement->bind_param('ss', $requestNumber, $publicAccessToken);
    $statement->execute();

    $result = $statement->get_result();
    $request = $result->fetch_assoc();

    if (!$request) {
        return null;
    }

    return $request;
}

function getCustomerRequestComments(int $requestId): array
{
    if ($requestId <= 0) {
        return [];
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            id,
            author_type,
            author_name,
            visibility,
            comment_text,
            created_at
         FROM quote_request_comments
         WHERE quote_request_id = ?
           AND visibility = "customer"
         ORDER BY created_at ASC, id ASC'
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

function addCustomerRequestComment(int $requestId, array $request, array $postedData, array &$errors): void
{
    if ($requestId <= 0) {
        $errors[] = 'Request is missing.';
        return;
    }

    $commentText = customerRequestTextarea((string)($postedData['comment_text'] ?? ''));

    if ($commentText === '') {
        $errors[] = 'Please enter the details you want to add.';
    }

    if (strlen($commentText) > 5000) {
        $errors[] = 'Your update is too long. Please keep it under 5,000 characters.';
    }

    if (!empty($errors)) {
        return;
    }

    $connection = getDatabaseConnection();

    $authorType = 'customer';
    $authorName = trim((string)($request['full_name'] ?? ''));

    if ($authorName === '') {
        $authorName = 'Customer';
    }

    $visibility = 'customer';

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
            last_queue_action_at = NOW()
         WHERE id = ?'
    );

    $updateStatement->bind_param('i', $requestId);
    $updateStatement->execute();
}

function renderCustomerRequestComment(array $comment): void
{
    $authorType = trim((string)($comment['author_type'] ?? ''));
    $authorName = trim((string)($comment['author_name'] ?? ''));

    if ($authorName === '') {
        $authorName = $authorType === 'customer' ? 'Customer' : 'Kail’s Landscaping';
    }

    $authorLabel = $authorType === 'customer' ? 'Customer' : 'Kail’s Landscaping';
    $badgeClass = $authorType === 'customer' ? 'text-bg-warning text-dark' : 'text-bg-success';
    ?>

    <article class="admin-inner-panel">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
            <div>
                <span class="badge rounded-pill <?php echo escapeHtml($badgeClass); ?>">
                    <?php echo escapeHtml($authorLabel); ?>
                </span>

                <h3 class="h6 mt-2 mb-0">
                    <?php echo escapeHtml($authorName); ?>
                </h3>
            </div>

            <div class="text-muted small">
                <?php echo escapeHtml(customerRequestDateTimeDisplay($comment['created_at'] ?? null)); ?>
            </div>
        </div>

        <p class="mb-0">
            <?php echo nl2br(escapeHtml((string)($comment['comment_text'] ?? ''))); ?>
        </p>
    </article>

    <?php
}

$requestNumber = customerRequestCleanText((string)($_GET['request'] ?? $_POST['request'] ?? ''));
$publicAccessToken = customerRequestCleanText((string)($_GET['key'] ?? $_POST['key'] ?? ''));

$request = null;
$comments = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'customer_add_comment') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security check failed. Refresh the page and try again.';
    } else {
        try {
            $request = getCustomerRequestRecord($requestNumber, $publicAccessToken);

            if ($request === null) {
                $errors[] = 'Request not found. Check the link and try again.';
            } else {
                addCustomerRequestComment((int)$request['id'], $request, $_POST, $errors);

                if (empty($errors)) {
                    redirectTo(
                        '/request-update.php?request='
                        . rawurlencode($requestNumber)
                        . '&key='
                        . rawurlencode($publicAccessToken)
                        . '&updated=1#request-comments'
                    );
                }
            }
        } catch (Throwable $exception) {
            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'Your update could not be saved.';
            }
        }
    }
}

if (isset($_GET['updated'])) {
    $messages[] = 'Your update was added.';
}

try {
    if ($request === null) {
        $request = getCustomerRequestRecord($requestNumber, $publicAccessToken);
    }

    if ($request !== null) {
        $comments = getCustomerRequestComments((int)$request['id']);
    }
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'The request could not be loaded.';
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<main class="site-section">
    <div class="container">
        <div class="mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                Request Status
            </p>

            <h1 class="fw-bold">
                <?php echo $request !== null ? escapeHtml((string)$request['request_number']) : 'Request Not Found'; ?>
            </h1>

            <p class="text-muted mb-0">
                View your request status and add more details for Kail’s Landscaping.
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
            <section class="card p-4">
                <h2 class="h4">
                    We could not find that request.
                </h2>

                <p class="text-muted">
                    The request number and access key must both match. Please use the exact link you were given.
                </p>

                <a href="/#contact" class="btn btn-light">
                    Request a Quote
                </a>
            </section>
        <?php else: ?>
            <?php
            $status = trim((string)($request['request_status'] ?? 'New'));
            $jobTitle = customerRequestJobTitle($request);
            $serviceName = customerRequestServiceName($request);
            $requestAddress = customerRequestFullAddress($request);
            ?>

            <section class="card p-4 mb-4">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-1">
                            <?php echo escapeHtml((string)$request['request_number']); ?>
                        </p>

                        <h2 class="h3 mb-2">
                            <?php echo escapeHtml($jobTitle); ?>
                        </h2>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="<?php echo escapeHtml(customerRequestStatusClass($status)); ?>">
                                <?php echo escapeHtml($status); ?>
                            </span>
                        </div>

                        <p class="text-muted mb-0">
                            <?php echo escapeHtml(customerRequestStatusMessage($status)); ?>
                        </p>
                    </div>

                    <div class="col-lg-4">
                        <div class="admin-inner-panel h-100">
                            <h2 class="h5 mb-3">
                                Important Dates
                            </h2>

                            <div class="request-summary-meta">
                                <div>
                                    <strong>Received:</strong>
                                    <?php echo escapeHtml(customerRequestDateDisplay($request['created_at'] ?? null)); ?>
                                </div>

                                <div>
                                    <strong>Last Updated:</strong>
                                    <?php echo escapeHtml(customerRequestDateDisplay($request['updated_at'] ?? null)); ?>
                                </div>

                                <div>
                                    <strong>Scheduled:</strong>
                                    <?php echo escapeHtml(customerRequestDateTimeDisplay($request['scheduled_start'] ?? null)); ?>
                                </div>

                                <div>
                                    <strong>Completed:</strong>
                                    <?php echo escapeHtml(customerRequestDateTimeDisplay($request['completed_at'] ?? null)); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card p-4 mb-4">
                <h2 class="h4 mb-3">
                    Request Summary
                </h2>

                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt>
                    <dd class="col-sm-8"><?php echo escapeHtml((string)$request['full_name']); ?></dd>

                    <dt class="col-sm-4">Phone</dt>
                    <dd class="col-sm-8">
                        <?php echo trim((string)($request['phone'] ?? '')) !== '' ? escapeHtml((string)$request['phone']) : '—'; ?>
                    </dd>

                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8">
                        <?php echo trim((string)($request['email'] ?? '')) !== '' ? escapeHtml((string)$request['email']) : '—'; ?>
                    </dd>

                    <dt class="col-sm-4">Service Requested</dt>
                    <dd class="col-sm-8"><?php echo escapeHtml($serviceName); ?></dd>

                    <dt class="col-sm-4">Preferred Contact</dt>
                    <dd class="col-sm-8">
                        <?php echo trim((string)($request['preferred_contact_method'] ?? '')) !== '' ? escapeHtml((string)$request['preferred_contact_method']) : '—'; ?>
                    </dd>

                    <dt class="col-sm-4">Property / Area</dt>
                    <dd class="col-sm-8">
                        <?php echo $requestAddress !== '' ? escapeHtml($requestAddress) : '—'; ?>
                    </dd>
                </dl>
            </section>

            <?php if (trim((string)($request['project_details'] ?? '')) !== ''): ?>
                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Project Details
                    </h2>

                    <p class="mb-0">
                        <?php echo nl2br(escapeHtml((string)$request['project_details'])); ?>
                    </p>
                </section>
            <?php endif; ?>

            <?php if (trim((string)($request['public_notes'] ?? '')) !== ''): ?>
                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Notes from Kail’s Landscaping
                    </h2>

                    <p class="mb-0">
                        <?php echo nl2br(escapeHtml((string)$request['public_notes'])); ?>
                    </p>
                </section>
            <?php endif; ?>

            <section class="card p-4 mb-4" id="add-details">
                <h2 class="h4 mb-2">
                    Add More Details
                </h2>

                <p class="text-muted">
                    Use this if you forgot something, need to correct a detail, or want to add more information about the job.
                </p>

                <form method="post" action="/request-update.php?request=<?php echo rawurlencode($requestNumber); ?>&key=<?php echo rawurlencode($publicAccessToken); ?>#request-comments">
                    <input type="hidden" name="form_name" value="customer_add_comment">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                    <input type="hidden" name="request" value="<?php echo escapeHtml($requestNumber); ?>">
                    <input type="hidden" name="key" value="<?php echo escapeHtml($publicAccessToken); ?>">

                    <div class="mb-3">
                        <label for="comment_text" class="form-label">
                            Details to Add
                        </label>

                        <textarea
                            class="form-control"
                            id="comment_text"
                            name="comment_text"
                            rows="5"
                            maxlength="5000"
                            required
                        ></textarea>
                    </div>

                    <button type="submit" class="btn btn-light">
                        Send Update
                    </button>
                </form>
            </section>

            <section class="card p-4" id="request-comments">
                <h2 class="h4 mb-3">
                    Request Updates
                </h2>

                <?php if (empty($comments)): ?>
                    <div class="alert alert-info mb-0">
                        No updates have been added yet.
                    </div>
                <?php else: ?>
                    <div class="request-card-list">
                        <?php foreach ($comments as $comment): ?>
                            <?php renderCustomerRequestComment($comment); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';