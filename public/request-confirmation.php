<?php
declare(strict_types=1);

/*
    Request confirmation page.

    This page shows the customer that their quote request was received.

    The page requires both:
    - request number
    - private access token

    This prevents someone from viewing a request by guessing the request number.
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';

$pageTitle = 'Request Confirmation';

$requestNumber = trim((string)($_GET['request'] ?? ''));
$publicAccessToken = trim((string)($_GET['key'] ?? ''));

$request = null;
$pageError = '';

function cleanRequestLookupValue(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', '', $value);

    return $value ?? '';
}

$requestNumber = cleanRequestLookupValue($requestNumber);
$publicAccessToken = cleanRequestLookupValue($publicAccessToken);

if ($requestNumber === '' || $publicAccessToken === '') {
    $pageError = 'This confirmation link is missing required information.';
} elseif (mb_strlen($requestNumber) > 40 || mb_strlen($publicAccessToken) > 128) {
    $pageError = 'This confirmation link is not valid.';
} else {
    try {
        $connection = getDatabaseConnection();

        $requestStatement = $connection->prepare(
            'SELECT
                quote_requests.id,
                quote_requests.request_number,
                quote_requests.public_access_token,
                quote_requests.request_status,
                quote_requests.request_source,
                quote_requests.project_details,
                quote_requests.property_city,
                quote_requests.property_state,
                quote_requests.property_zip_code,
                quote_requests.preferred_contact_method,
                quote_requests.next_action,
                quote_requests.next_action_due_at,
                quote_requests.created_at,
                clients.full_name,
                clients.phone,
                clients.email,
                services.service_title
             FROM quote_requests
             INNER JOIN clients
                ON quote_requests.client_id = clients.id
             LEFT JOIN services
                ON quote_requests.requested_service_id = services.id
             WHERE quote_requests.request_number = ?
               AND quote_requests.public_access_token = ?
             LIMIT 1'
        );

        $requestStatement->bind_param('ss', $requestNumber, $publicAccessToken);
        $requestStatement->execute();

        $requestResult = $requestStatement->get_result();
        $request = $requestResult->fetch_assoc();

        if (!$request) {
            $pageError = 'This confirmation link is not valid or the request could not be found.';
        }
    } catch (Throwable $exception) {
        $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

        if ($showDetailedErrors) {
            $pageError = $exception->getMessage();
        } else {
            $pageError = 'The request confirmation could not be loaded. Please contact Kail directly.';
        }
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<main>
    <section class="site-section">
        <div class="container">
            <?php if ($pageError !== ''): ?>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="alert alert-danger shadow-sm">
                            <h1 class="h4 mb-2">Request Confirmation Unavailable</h1>
                            <p class="mb-0">
                                <?php echo escapeHtml($pageError); ?>
                            </p>
                        </div>

                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h2 class="h5">Need help?</h2>
                                <p class="mb-3">
                                    Please contact Kail directly and include your name, phone number, email address, and what service you requested.
                                </p>

                                <div class="rich-text-block mb-0">
                                    <?php echo richContentHtml('quick_contact_body'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php
                $serviceTitle = (string)($request['service_title'] ?? '');

                if ($serviceTitle === '') {
                    $serviceTitle = 'Service not listed';
                }

                $cityParts = [];

                if (!empty($request['property_city'])) {
                    $cityParts[] = (string)$request['property_city'];
                }

                if (!empty($request['property_state'])) {
                    $cityParts[] = (string)$request['property_state'];
                }

                if (!empty($request['property_zip_code'])) {
                    $cityParts[] = (string)$request['property_zip_code'];
                }

                $cityDisplay = implode(', ', $cityParts);

                if ($cityDisplay === '') {
                    $cityDisplay = 'Not provided';
                }

                $phoneDisplay = (string)($request['phone'] ?? '');

                if ($phoneDisplay === '') {
                    $phoneDisplay = 'Not provided';
                }

                $emailDisplay = (string)($request['email'] ?? '');

                if ($emailDisplay === '') {
                    $emailDisplay = 'Not provided';
                }

                $preferredContactMethod = (string)($request['preferred_contact_method'] ?? '');

                if ($preferredContactMethod === '') {
                    $preferredContactMethod = 'No preference';
                }

                $submittedAt = '';

                if (!empty($request['created_at'])) {
                    $submittedAt = date('F j, Y g:i A', strtotime((string)$request['created_at']));
                }

                if ($submittedAt === '') {
                    $submittedAt = 'Recently submitted';
                }

                $updateUrl = '/request-update.php?request=' . urlencode((string)$request['request_number']) . '&key=' . urlencode((string)$request['public_access_token']);
                ?>

                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <div class="alert alert-success shadow-sm">
                            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                                Request Received
                            </p>

                            <h1 class="h2 mb-3">
                                Your request was received.
                            </h1>

                            <p class="lead mb-0">
                                Kail will review your request and contact you using the contact information you provided.
                            </p>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                    <div>
                                        <h2 class="h4 mb-1">Request Number</h2>
                                        <p class="mb-0 text-muted">
                                            Please save this number in case you need to ask about your request later.
                                        </p>
                                    </div>

                                    <div class="text-md-end">
                                        <div class="display-6 fw-bold">
                                            <?php echo escapeHtml((string)$request['request_number']); ?>
                                        </div>
                                        <div class="text-muted">
                                            Submitted <?php echo escapeHtml($submittedAt); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h2 class="h4 mb-0">Request Summary</h2>
                            </div>

                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="text-muted small">Name</div>
                                            <div class="fw-semibold">
                                                <?php echo escapeHtml((string)$request['full_name']); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="text-muted small">Service Requested</div>
                                            <div class="fw-semibold">
                                                <?php echo escapeHtml($serviceTitle); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="text-muted small">Phone</div>
                                            <div class="fw-semibold">
                                                <?php echo escapeHtml($phoneDisplay); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="text-muted small">Email</div>
                                            <div class="fw-semibold">
                                                <?php echo escapeHtml($emailDisplay); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="text-muted small">City / Area</div>
                                            <div class="fw-semibold">
                                                <?php echo escapeHtml($cityDisplay); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <div class="text-muted small">Preferred Contact Method</div>
                                            <div class="fw-semibold">
                                                <?php echo escapeHtml($preferredContactMethod); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="border rounded-3 p-3">
                                            <div class="text-muted small mb-1">Project Details</div>
                                            <div class="white-space-pre-line">
                                                <?php echo nl2br(escapeHtml((string)$request['project_details'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h2 class="h4 mb-0">What Happens Next?</h2>
                            </div>

                            <div class="card-body">
                                <ol class="mb-0">
                                    <li class="mb-2">
                                        Kail reviews your request.
                                    </li>
                                    <li class="mb-2">
                                        Kail contacts you by phone, email, or either method based on what you selected.
                                    </li>
                                    <li class="mb-2">
                                        Kail confirms details, gives a quote when possible, and schedules the work if you approve.
                                    </li>
                                    <li>
                                        Please save your request number:
                                        <strong><?php echo escapeHtml((string)$request['request_number']); ?></strong>
                                    </li>
                                </ol>
                            </div>
                        </div>

                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h2 class="h5">Need to add more details?</h2>

                                <p class="mb-3">
                                    A customer update page will use this same private link system. For now, contact Kail directly if you need to add details to this request.
                                </p>

                                <div class="d-flex flex-wrap gap-2">
                                    <a href="/" class="btn btn-success">
                                        Return to Home
                                    </a>

                                    <a href="<?php echo escapeHtml($updateUrl); ?>" class="btn btn-outline-secondary">
                                        Add Details Later
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';