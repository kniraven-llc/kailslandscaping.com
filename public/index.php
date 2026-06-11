<?php
declare(strict_types=1);

/*
    Home page for the website.

    This file loads the site setup file first.
    Then it loads the shared page pieces:
    head, navigation, page content, and footer.

    The page structure stays in this file.
    The editable text, buttons, services, image paths, and theme colors come from the database.

    The quote request form stores requests in the database.
    It does not send email.

    When a customer submits the form, this page now:
    - cleans and validates the form
    - normalizes the phone number
    - finds or creates the client
    - creates a request number
    - creates a private request access token
    - sets the first action queue fields
    - sends the customer to the request confirmation page

    Customers can also use the Check Existing Request section.
    They must enter both:
    - Request Number
    - Access Key

    A request number alone is not enough.
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';

$pageTitle = 'Home';

$services = getActiveServiceRows();

$quoteFormErrors = [];
$quoteFormWasSubmitted = false;
$quoteFormSaved = isset($_GET['request_saved']);

$quoteFormValues = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'service' => '',
    'property_city' => '',
    'preferred_contact_method' => '',
    'message' => '',
];

function cleanFormText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function cleanFormTextarea(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    return $value;
}

function nullableString(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    return $value;
}

function normalizePhoneNumber(string $phone): ?string
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

function getValidServiceIds(array $services): array
{
    $serviceIds = [];

    foreach ($services as $service) {
        $serviceId = (int)($service['id'] ?? 0);

        if ($serviceId > 0) {
            $serviceIds[] = $serviceId;
        }
    }

    return $serviceIds;
}

function createPublicAccessToken(): string
{
    return bin2hex(random_bytes(32));
}

function createRequestNumber(int $requestId): string
{
    return 'KR-' . date('Ymd') . '-' . str_pad((string)$requestId, 4, '0', STR_PAD_LEFT);
}

function findOrCreateClient(
    mysqli $connection,
    string $fullName,
    ?string $phone,
    ?string $phoneNormalized,
    ?string $email,
    ?string $city,
    ?string $preferredContactMethod
): int {
    $clientId = 0;

    if ($phoneNormalized !== null || $email !== null) {
        $findStatement = $connection->prepare(
            'SELECT id
             FROM clients
             WHERE
                (email = ? AND email IS NOT NULL AND email <> "")
                OR
                (phone_normalized = ? AND phone_normalized IS NOT NULL AND phone_normalized <> "")
             ORDER BY updated_at DESC
             LIMIT 1'
        );

        $findStatement->bind_param('ss', $email, $phoneNormalized);
        $findStatement->execute();

        $findResult = $findStatement->get_result();
        $foundClient = $findResult->fetch_assoc();

        if ($foundClient) {
            $clientId = (int)$foundClient['id'];
        }
    }

    if ($clientId > 0) {
        $updateStatement = $connection->prepare(
            'UPDATE clients
             SET
                full_name = ?,
                phone = COALESCE(NULLIF(?, ""), phone),
                phone_normalized = COALESCE(NULLIF(?, ""), phone_normalized),
                email = COALESCE(NULLIF(?, ""), email),
                city = COALESCE(NULLIF(?, ""), city),
                state = "WI",
                preferred_contact_method = COALESCE(NULLIF(?, ""), preferred_contact_method)
             WHERE id = ?'
        );

        $phoneValue = $phone ?? '';
        $phoneNormalizedValue = $phoneNormalized ?? '';
        $emailValue = $email ?? '';
        $cityValue = $city ?? '';
        $preferredContactMethodValue = $preferredContactMethod ?? '';

        $updateStatement->bind_param(
            'ssssssi',
            $fullName,
            $phoneValue,
            $phoneNormalizedValue,
            $emailValue,
            $cityValue,
            $preferredContactMethodValue,
            $clientId
        );

        $updateStatement->execute();

        return $clientId;
    }

    $insertStatement = $connection->prepare(
        'INSERT INTO clients
            (
                full_name,
                phone,
                phone_normalized,
                email,
                city,
                state,
                preferred_contact_method
            )
         VALUES
            (?, ?, ?, ?, ?, "WI", ?)'
    );

    $insertStatement->bind_param(
        'ssssss',
        $fullName,
        $phone,
        $phoneNormalized,
        $email,
        $city,
        $preferredContactMethod
    );

    $insertStatement->execute();

    return (int)$connection->insert_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'quote_request') {
    $quoteFormWasSubmitted = true;

    $quoteFormValues = [
        'name' => cleanFormText((string)($_POST['name'] ?? '')),
        'phone' => cleanFormText((string)($_POST['phone'] ?? '')),
        'email' => cleanFormText((string)($_POST['email'] ?? '')),
        'service' => cleanFormText((string)($_POST['service'] ?? '')),
        'property_city' => cleanFormText((string)($_POST['property_city'] ?? '')),
        'preferred_contact_method' => cleanFormText((string)($_POST['preferred_contact_method'] ?? '')),
        'message' => cleanFormTextarea((string)($_POST['message'] ?? '')),
    ];

    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $honeypotValue = trim((string)($_POST['website_url'] ?? ''));

    $phoneNormalized = null;

    if ($quoteFormValues['phone'] !== '') {
        $phoneNormalized = normalizePhoneNumber($quoteFormValues['phone']);
    }

    if (!isValidCsrfToken($submittedToken)) {
        $quoteFormErrors[] = 'Security check failed. Please refresh the page and try again.';
    }

    if ($honeypotValue !== '') {
        $quoteFormErrors[] = 'The form could not be submitted.';
    }

    if ($quoteFormValues['name'] === '') {
        $quoteFormErrors[] = 'Please enter your name.';
    }

    if ($quoteFormValues['phone'] === '' && $quoteFormValues['email'] === '') {
        $quoteFormErrors[] = 'Please enter either a phone number or an email address.';
    }

    if ($quoteFormValues['phone'] !== '' && $phoneNormalized === null) {
        $quoteFormErrors[] = 'Please enter a valid phone number.';
    }

    if ($quoteFormValues['email'] !== '' && !filter_var($quoteFormValues['email'], FILTER_VALIDATE_EMAIL)) {
        $quoteFormErrors[] = 'Please enter a valid email address.';
    }

    if ($quoteFormValues['service'] === '') {
        $quoteFormErrors[] = 'Please choose a service.';
    }

    if ($quoteFormValues['message'] === '') {
        $quoteFormErrors[] = 'Please enter the project details.';
    }

    if (mb_strlen($quoteFormValues['name']) > 160) {
        $quoteFormErrors[] = 'Name must be 160 characters or fewer.';
    }

    if (mb_strlen($quoteFormValues['phone']) > 40) {
        $quoteFormErrors[] = 'Phone number must be 40 characters or fewer.';
    }

    if (mb_strlen($quoteFormValues['email']) > 160) {
        $quoteFormErrors[] = 'Email must be 160 characters or fewer.';
    }

    if (mb_strlen($quoteFormValues['property_city']) > 120) {
        $quoteFormErrors[] = 'City / area must be 120 characters or fewer.';
    }

    if (mb_strlen($quoteFormValues['message']) > 5000) {
        $quoteFormErrors[] = 'Project details must be 5,000 characters or fewer.';
    }

    $allowedPreferredContactMethods = ['', 'Phone', 'Email', 'Either'];

    if (!in_array($quoteFormValues['preferred_contact_method'], $allowedPreferredContactMethods, true)) {
        $quoteFormErrors[] = 'Please choose a valid preferred contact method.';
    }

    if ($quoteFormValues['preferred_contact_method'] === 'Phone' && $quoteFormValues['phone'] === '') {
        $quoteFormErrors[] = 'Please enter a phone number if you prefer to be contacted by phone.';
    }

    if ($quoteFormValues['preferred_contact_method'] === 'Email' && $quoteFormValues['email'] === '') {
        $quoteFormErrors[] = 'Please enter an email address if you prefer to be contacted by email.';
    }

    $requestedServiceId = (int)$quoteFormValues['service'];
    $validServiceIds = getValidServiceIds($services);

    if (!in_array($requestedServiceId, $validServiceIds, true)) {
        $quoteFormErrors[] = 'Please choose a valid service.';
    }

    if (empty($quoteFormErrors)) {
        $connection = null;

        try {
            $connection = getDatabaseConnection();
            $connection->begin_transaction();

            $phoneValue = nullableString($quoteFormValues['phone']);
            $emailValue = nullableString($quoteFormValues['email']);
            $propertyCity = nullableString($quoteFormValues['property_city']);
            $preferredContactMethod = nullableString($quoteFormValues['preferred_contact_method']);

            $clientId = findOrCreateClient(
                $connection,
                $quoteFormValues['name'],
                $phoneValue,
                $phoneNormalized,
                $emailValue,
                $propertyCity,
                $preferredContactMethod
            );

            $publicAccessToken = createPublicAccessToken();

            $requestSource = 'Website Form';
            $requestStatus = 'New';
            $projectDetails = $quoteFormValues['message'];
            $nextAction = 'Review request and contact customer';

            $requestStatement = $connection->prepare(
                'INSERT INTO quote_requests
                    (
                        public_access_token,
                        client_id,
                        request_source,
                        request_status,
                        requested_service_id,
                        project_details,
                        property_city,
                        preferred_contact_method,
                        next_action,
                        next_action_due_at,
                        last_queue_action_at
                    )
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())'
            );

            $requestStatement->bind_param(
                'sississss',
                $publicAccessToken,
                $clientId,
                $requestSource,
                $requestStatus,
                $requestedServiceId,
                $projectDetails,
                $propertyCity,
                $preferredContactMethod,
                $nextAction
            );

            $requestStatement->execute();

            $requestId = (int)$connection->insert_id;
            $requestNumber = createRequestNumber($requestId);

            $requestNumberStatement = $connection->prepare(
                'UPDATE quote_requests
                 SET request_number = ?
                 WHERE id = ?
                 LIMIT 1'
            );

            $requestNumberStatement->bind_param('si', $requestNumber, $requestId);
            $requestNumberStatement->execute();

            $connection->commit();

            $confirmationUrl = '/request-confirmation.php?request=' . urlencode($requestNumber) . '&key=' . urlencode($publicAccessToken);

            header('Location: ' . $confirmationUrl);
            exit;
        } catch (Throwable $exception) {
            if ($connection instanceof mysqli) {
                try {
                    $connection->rollback();
                } catch (Throwable $rollbackException) {
                    // The save already failed. Do not show a second error to the visitor.
                }
            }

            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $quoteFormErrors[] = $exception->getMessage();
            } else {
                $quoteFormErrors[] = 'The request could not be saved. Please call or email Kail directly.';
            }
        }
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<main>
    <section class="hero-section text-white">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <div class="hero-content">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            <?php echo escapeHtml(contentValue('hero_eyebrow')); ?>
                        </p>

                        <h1 class="hero-title fw-bold">
                            <?php echo escapeHtml(contentValue('hero_title')); ?>
                        </h1>

                        <div class="lead mt-3 rich-text-block">
                            <?php echo richContentHtml('hero_lead'); ?>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <a href="<?php echo escapeHtml(buttonValue('hero_primary', 'href')); ?>" class="btn btn-light btn-lg">
                                <?php echo escapeHtml(buttonValue('hero_primary', 'text')); ?>
                            </a>
                            <a href="<?php echo escapeHtml(buttonValue('hero_secondary', 'href')); ?>" class="btn btn-outline-light btn-lg">
                                <?php echo escapeHtml(buttonValue('hero_secondary', 'text')); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="hero-quick-contact p-4 mb-3">
                        <h2 class="h4 mb-3">
                            <?php echo escapeHtml(contentValue('quick_contact_title')); ?>
                        </h2>

                        <div class="mb-2 rich-text-block">
                            <?php echo richContentHtml('quick_contact_body'); ?>
                        </div>

                        <p class="mb-0 fw-semibold">
                            <?php echo escapeHtml(contentValue('quick_contact_footer')); ?>
                        </p>
                    </div>

                    <div class="hero-visual-card">
                        <img
                            src="<?php echo escapeHtml(contentValue('hero_selfie_src')); ?>"
                            alt="<?php echo escapeHtml(contentValue('hero_selfie_alt')); ?>"
                            class="hero-selfie"
                        >

                        <!--
                        <div class="hero-logo-overlay">
                            <img
                                src="<?php echo escapeHtml(contentValue('hero_logo_src')); ?>"
                                alt="<?php echo escapeHtml(contentValue('hero_logo_alt')); ?>"
                                class="hero-logo-overlay-image"
                            >
                        </div>
                        -->
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="services" class="site-section">
        <div class="container">
            <div class="text-center mb-5">
                <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                    <?php echo escapeHtml(contentValue('services_eyebrow')); ?>
                </p>

                <h2 class="fw-bold">
                    <?php echo escapeHtml(contentValue('services_title')); ?>
                </h2>

                <div class="text-muted mb-0 rich-text-block">
                    <?php echo richContentHtml('services_intro'); ?>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($services as $service): ?>
                    <?php
                    $serviceImagePath = (string)($service['service_image_path'] ?? '');
                    $serviceImageAlt = (string)($service['service_image_alt'] ?? $service['service_title'] ?? 'Service image');
                    ?>

                    <div class="col-md-6 col-lg-4">
                        <div class="card equal-height-card shadow-sm service-card">
                            <?php if ($serviceImagePath !== ''): ?>
                                <img
                                    src="<?php echo escapeHtml($serviceImagePath); ?>"
                                    alt="<?php echo escapeHtml($serviceImageAlt); ?>"
                                    class="service-card-image"
                                >
                            <?php endif; ?>

                            <div class="card-body">
                                <h3 class="h5 card-title">
                                    <?php echo escapeHtml((string)$service['service_title']); ?>
                                </h3>

                                <div class="card-text rich-text-block">
                                    <?php echo sanitizeRichHtml((string)$service['service_description']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <a href="<?php echo escapeHtml(buttonValue('services_cta', 'href')); ?>" class="btn btn-light btn-lg">
                    <?php echo escapeHtml(buttonValue('services_cta', 'text')); ?>
                </a>
            </div>
        </div>
    </section>

    <section id="about" class="site-section bg-light">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                        <?php echo escapeHtml(contentValue('about_eyebrow')); ?>
                    </p>

                    <h2 class="fw-bold">
                        <?php echo escapeHtml(contentValue('about_title')); ?>
                    </h2>

                    <div class="rich-text-block">
                        <?php echo richContentHtml('about_body_1'); ?>
                    </div>

                    <div class="mb-0 rich-text-block">
                        <?php echo richContentHtml('about_body_2'); ?>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="border rounded-3 p-4 bg-white shadow-sm">
                        <h3 class="h5">
                            <?php echo escapeHtml(contentValue('why_choose_title')); ?>
                        </h3>

                        <ul class="mb-0">
                            <li><?php echo escapeHtml(contentValue('why_choose_item_1')); ?></li>
                            <li><?php echo escapeHtml(contentValue('why_choose_item_2')); ?></li>
                            <li><?php echo escapeHtml(contentValue('why_choose_item_3')); ?></li>
                            <li><?php echo escapeHtml(contentValue('why_choose_item_4')); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="contact" class="site-section">
        <div class="container">
            <div class="text-center mb-4">
                <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                    <?php echo escapeHtml(contentValue('contact_eyebrow')); ?>
                </p>

                <h2 class="fw-bold">
                    <?php echo escapeHtml(contentValue('contact_title')); ?>
                </h2>

                <div class="text-muted mb-0 rich-text-block">
                    <?php echo richContentHtml('contact_intro'); ?>
                </div>
            </div>

            <div class="form-wrapper">
                <?php if ($quoteFormSaved): ?>
                    <div class="alert alert-success rich-text-block">
                        <?php echo richContentHtml('quote_form_success_message'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($quoteFormErrors)): ?>
                    <div class="alert alert-danger">
                        <div class="rich-text-block mb-2">
                            <?php echo richContentHtml('quote_form_error_message'); ?>
                        </div>

                        <ul class="mb-0">
                            <?php foreach ($quoteFormErrors as $quoteFormError): ?>
                                <li><?php echo escapeHtml($quoteFormError); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form class="card shadow-sm p-4" method="post" action="/#contact">
                    <input type="hidden" name="form_name" value="quote_request">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">

                    <div class="visually-hidden" aria-hidden="true">
                        <label for="website_url">Website</label>
                        <input
                            type="text"
                            id="website_url"
                            name="website_url"
                            tabindex="-1"
                            autocomplete="off"
                        >
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">
                                <?php echo escapeHtml(contentValue('form_name_label')); ?>
                                <span class="kails-text-yellow">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                name="name"
                                value="<?php echo escapeHtml($quoteFormValues['name']); ?>"
                                maxlength="160"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label for="property_city" class="form-label">
                                City / Area
                            </label>
                            <select class="form-control" id="property_city" name="property_city">
                                <option value="">Choose a city / area...</option>
                                <?php
                                $cityOptions = ['DeForest', 'Windsor', 'Sun Prairie', 'Other'];
                                foreach ($cityOptions as $cityOption):
                                ?>
                                    <option
                                        value="<?php echo escapeHtml($cityOption); ?>"
                                        <?php echo ($quoteFormValues['property_city'] === $cityOption) ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeHtml($cityOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">
                                <?php echo escapeHtml(contentValue('form_phone_label')); ?>
                            </label>
                            <input
                                type="tel"
                                class="form-control"
                                id="phone"
                                name="phone"
                                value="<?php echo escapeHtml($quoteFormValues['phone']); ?>"
                                maxlength="40"
                            >
                            <div class="form-text">
                                Provide a phone number or email address.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                <?php echo escapeHtml(contentValue('form_email_label')); ?>
                            </label>
                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                value="<?php echo escapeHtml($quoteFormValues['email']); ?>"
                                maxlength="160"
                            >
                            <div class="form-text">
                                Provide an email address or phone number.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="preferred_contact_method" class="form-label">
                                Preferred Contact Method
                            </label>
                            <select class="form-control" id="preferred_contact_method" name="preferred_contact_method">
                                <option value="">No preference</option>
                                <?php
                                $contactMethodOptions = ['Phone', 'Email', 'Either'];
                                foreach ($contactMethodOptions as $contactMethodOption):
                                ?>
                                    <option
                                        value="<?php echo escapeHtml($contactMethodOption); ?>"
                                        <?php echo ($quoteFormValues['preferred_contact_method'] === $contactMethodOption) ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeHtml($contactMethodOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="service" class="form-label">
                                <?php echo escapeHtml(contentValue('form_service_label')); ?>
                                <span class="kails-text-yellow">*</span>
                            </label>
                            <select class="form-control" id="service" name="service" required>
                                <option value="">
                                    <?php echo escapeHtml(contentValue('form_service_placeholder')); ?>
                                </option>

                                <?php foreach ($services as $service): ?>
                                    <?php $serviceId = (string)$service['id']; ?>
                                    <option
                                        value="<?php echo escapeHtml($serviceId); ?>"
                                        <?php echo ($quoteFormValues['service'] === $serviceId) ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeHtml((string)$service['service_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="message" class="form-label">
                                <?php echo escapeHtml(contentValue('form_message_label')); ?>
                                <span class="kails-text-yellow">*</span>
                            </label>
                            <textarea
                                class="form-control"
                                id="message"
                                name="message"
                                rows="5"
                                maxlength="5000"
                                required
                            ><?php echo escapeHtml($quoteFormValues['message']); ?></textarea>
                            <div class="form-text">
                                Include what you need done, the approximate area, timing, and any important details.
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-lg">
                                <?php echo escapeHtml(contentValue('form_submit_text')); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section id="check-request" class="site-section bg-light">
        <div class="container">
            <div class="text-center mb-4">
                <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                    Existing Request
                </p>

                <h2 class="fw-bold">
                    Check an Existing Request
                </h2>

                <p class="text-muted mb-0">
                    Enter your request number and access key to view your request or add more details.
                </p>
            </div>

            <div class="form-wrapper">
                <form class="card shadow-sm p-4" method="get" action="/request-update.php">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="existing_request_number" class="form-label">
                                Request Number
                                <span class="kails-text-yellow">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="existing_request_number"
                                name="request"
                                placeholder="Example: KR-20260517-0001"
                                maxlength="40"
                                required
                            >

                            <div class="form-text">
                                This was shown after you submitted your request.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="existing_request_key" class="form-label">
                                Access Key
                                <span class="kails-text-yellow">*</span>
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="existing_request_key"
                                name="key"
                                placeholder="Paste your access key here"
                                maxlength="128"
                                required
                            >

                            <div class="form-text">
                                This keeps your request private. The request number alone is not enough.
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-light btn-lg">
                                Check Request
                            </button>
                        </div>
                    </div>
                </form>

                <div class="card shadow-sm p-3 mt-3 mb-0">
                    <p class="mb-0">
                        <strong class="kails-text-yellow">Missing your access key?</strong>
                        Contact Kail’s Landscaping and provide your request number, name, phone number, or email address.
                    </p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';