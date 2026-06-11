<?php
declare(strict_types=1);

/*
    Printable business card generator.

    This page creates print-ready sheets of business cards.

    Standard business card size:
    3.5 inches wide by 2 inches tall.

    Standard letter sheet:
    8.5 inches wide by 11 inches tall.

    Standard layout:
    10 cards per page.
    2 columns.
    5 rows.
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Business Cards';

adminRequireLogin('Business Cards Login');

$messages = [];
$errors = [];

function businessCardCleanText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function businessCardNormalizeUrlValue(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (str_starts_with($value, '/')) {
        return $value;
    }

    if (preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $value)) {
        return $value;
    }

    return 'https://' . $value;
}

function businessCardAbsoluteUrl(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    if (preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $url)) {
        return $url;
    }

    if (!str_starts_with($url, '/')) {
        return businessCardNormalizeUrlValue($url);
    }

    $website = trim(contentValue('business_website'));

    if ($website === '') {
        return $url;
    }

    $website = businessCardNormalizeUrlValue($website);
    $parts = parse_url($website);

    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return $url;
    }

    return $parts['scheme'] . '://' . $parts['host'] . $url;
}

function businessCardSettingDefinitions(): array
{
    return [
        'business_card_headline' => [
            'label' => 'Business Card Headline',
            'help' => 'Main headline printed on the business card. If blank, the business name is used.',
            'placeholder' => 'Example: Kail’s Landscaping',
            'max_length' => 160,
        ],
        'business_card_tagline' => [
            'label' => 'Business Card Tagline',
            'help' => 'Short tagline printed under the headline. If blank, the main business tagline is used.',
            'placeholder' => 'Example: Reliable lawn care and outdoor services.',
            'max_length' => 200,
        ],
        'business_card_services_line' => [
            'label' => 'Business Card Services Line',
            'help' => 'Short list of services printed on the card.',
            'placeholder' => 'Example: Mowing · Cleanups · Power Washing · Gutters',
            'max_length' => 220,
        ],
        'business_card_qr_url' => [
            'label' => 'Business Card QR Code URL',
            'help' => 'Website or landing page the QR code should open. If blank, the main business website is used.',
            'placeholder' => 'Example: https://kailslandscaping.com',
            'max_length' => 255,
        ],
    ];
}

function businessCardLoadSettingValues(array $settingDefinitions): array
{
    $values = [];

    foreach ($settingDefinitions as $contentKey => $settingDefinition) {
        $values[$contentKey] = contentValue((string)$contentKey);
    }

    return $values;
}

function businessCardUpsertContentValue(
    mysqli $connection,
    string $contentKey,
    string $contentValue,
    array $contentRows,
    array $settingDefinitions
): void {
    $existingRow = $contentRows[$contentKey] ?? [];

    $settingDefinition = $settingDefinitions[$contentKey] ?? [
        'label' => $contentKey,
    ];

    $contentLabel = (string)(
        $existingRow['content_label']
        ?? $existingRow['label']
        ?? $settingDefinition['label']
        ?? $contentKey
    );

    $contentType = (string)(
        $existingRow['content_type']
        ?? $existingRow['type']
        ?? 'text'
    );

    $sortOrder = (int)($existingRow['sort_order'] ?? 0);

    $statement = $connection->prepare(
        'INSERT INTO site_content
            (
                content_key,
                content_label,
                content_type,
                content_value,
                sort_order
            )
         VALUES
            (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            content_value = VALUES(content_value),
            content_label = VALUES(content_label),
            content_type = VALUES(content_type),
            sort_order = VALUES(sort_order)'
    );

    $statement->bind_param(
        'ssssi',
        $contentKey,
        $contentLabel,
        $contentType,
        $contentValue,
        $sortOrder
    );

    $statement->execute();
}

function businessCardQrImageUrl(string $qrUrl, int $size = 220): string
{
    $cleanQrUrl = businessCardAbsoluteUrl($qrUrl);

    if ($cleanQrUrl === '') {
        $cleanQrUrl = businessCardAbsoluteUrl(contentValue('business_website'));
    }

    /*
        This uses a public QR image endpoint because this project does not
        currently include a local QR-code library.

        The data sent is only the public business-card URL, usually:
        https://kailslandscaping.com
    */
    return 'https://api.qrserver.com/v1/create-qr-code/?size='
        . rawurlencode((string)$size . 'x' . (string)$size)
        . '&data='
        . rawurlencode($cleanQrUrl);
}

function businessCardPrintCount(): int
{
    $count = (int)($_GET['count'] ?? 10);

    if ($count < 1) {
        return 10;
    }

    if ($count > 100) {
        return 100;
    }

    return $count;
}

function businessCardCleanDisplayWebsite(string $website): string
{
    $website = trim($website);
    $website = preg_replace('/^https?:\/\//i', '', $website);
    $website = rtrim((string)$website, '/');

    return $website;
}

function businessCardRenderOneCard(
    string $businessName,
    string $tagline,
    string $servicesLine,
    string $phoneDisplay,
    string $email,
    string $website,
    string $serviceArea,
    string $logoSrc,
    string $personImageSrc,
    string $personImageAlt,
    bool $showSelfie,
    bool $showQr,
    string $qrImageUrl,
    string $qrUrl
): void {
    ?>
    <article class="print-business-card">
        <div class="business-card-bg-mark"></div>

        <div class="business-card-left">
            <div class="business-card-logo-wrap">
                <?php if ($logoSrc !== ''): ?>
                    <img
                        src="<?php echo escapeHtml($logoSrc); ?>"
                        alt="<?php echo escapeHtml($businessName); ?>"
                        class="business-card-logo"
                        data-card-logo
                    >
                <?php endif; ?>
            </div>

            <div class="business-card-main-text">
                <h2 data-card-business-name>
                    <?php echo escapeHtml($businessName); ?>
                </h2>

                <p
                    class="business-card-tagline"
                    data-card-tagline
                    <?php echo $tagline === '' ? 'hidden' : ''; ?>
                >
                    <?php echo escapeHtml($tagline); ?>
                </p>

                <p
                    class="business-card-services"
                    data-card-services
                    <?php echo $servicesLine === '' ? 'hidden' : ''; ?>
                >
                    <?php echo escapeHtml($servicesLine); ?>
                </p>
            </div>

            <div class="business-card-contact">
                <p
                    data-card-phone-row
                    <?php echo $phoneDisplay === '' ? 'hidden' : ''; ?>
                >
                    <strong>Phone:</strong>
                    <span data-card-phone><?php echo escapeHtml($phoneDisplay); ?></span>
                </p>

                <p
                    data-card-email-row
                    <?php echo $email === '' ? 'hidden' : ''; ?>
                >
                    <strong>Email:</strong>
                    <span data-card-email><?php echo escapeHtml($email); ?></span>
                </p>

                <p
                    data-card-website-row
                    <?php echo $website === '' ? 'hidden' : ''; ?>
                >
                    <strong>Web:</strong>
                    <span data-card-website><?php echo escapeHtml(businessCardCleanDisplayWebsite($website)); ?></span>
                </p>

                <p
                    data-card-area-row
                    <?php echo $serviceArea === '' ? 'hidden' : ''; ?>
                >
                    <strong>Area:</strong>
                    <span data-card-area><?php echo escapeHtml($serviceArea); ?></span>
                </p>
            </div>
        </div>

        <div class="business-card-right">
            <div
                class="business-card-person-wrap"
                data-card-person-wrap
                <?php echo (!$showSelfie || $personImageSrc === '') ? 'hidden' : ''; ?>
            >
                <?php if ($personImageSrc !== ''): ?>
                    <img
                        src="<?php echo escapeHtml($personImageSrc); ?>"
                        alt="<?php echo escapeHtml($personImageAlt); ?>"
                        class="business-card-person"
                        data-card-person
                    >
                <?php endif; ?>
            </div>

            <div
                class="business-card-qr-wrap"
                data-card-qr-wrap
                <?php echo !$showQr ? 'hidden' : ''; ?>
            >
                <img
                    src="<?php echo escapeHtml($qrImageUrl); ?>"
                    alt="QR code for <?php echo escapeHtml($qrUrl); ?>"
                    class="business-card-qr"
                    data-card-qr-image
                >
                <p>Scan for website</p>
            </div>
        </div>
    </article>
    <?php
}

$settingDefinitions = businessCardSettingDefinitions();
$settingValues = businessCardLoadSettingValues($settingDefinitions);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'save_business_card_settings') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    $postedContent = $_POST['content'] ?? [];

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    }

    if (!is_array($postedContent)) {
        $postedContent = [];
    }

    $submittedSettings = [];

    foreach ($settingDefinitions as $contentKey => $settingDefinition) {
        $value = businessCardCleanText((string)($postedContent[$contentKey] ?? ''));

        if ($contentKey === 'business_card_qr_url') {
            $value = businessCardNormalizeUrlValue($value);
        }

        $maxLength = (int)($settingDefinition['max_length'] ?? 255);

        if (mb_strlen($value) > $maxLength) {
            $errors[] = (string)$settingDefinition['label'] . ' must be ' . $maxLength . ' characters or fewer.';
        }

        $submittedSettings[$contentKey] = $value;
    }

    $submittedQrUrl = (string)($submittedSettings['business_card_qr_url'] ?? '');

    if (
        $submittedQrUrl !== ''
        && !str_starts_with($submittedQrUrl, '/')
        && !filter_var($submittedQrUrl, FILTER_VALIDATE_URL)
    ) {
        $errors[] = 'Business Card QR Code URL must be a valid URL or a site path like /contact.';
    }

    if (empty($errors)) {
        try {
            $connection = getDatabaseConnection();
            $contentRows = getEditableSiteContentRows();

            foreach ($submittedSettings as $contentKey => $contentValue) {
                businessCardUpsertContentValue(
                    $connection,
                    (string)$contentKey,
                    (string)$contentValue,
                    $contentRows,
                    $settingDefinitions
                );
            }

            redirectTo('/business_cards.php?saved=1');
        } catch (Throwable $exception) {
            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'Business card settings could not be saved.';
            }
        }
    }

    if (!empty($errors)) {
        foreach ($submittedSettings as $contentKey => $contentValue) {
            $settingValues[$contentKey] = $contentValue;
        }
    }
}

if (isset($_GET['saved'])) {
    $messages[] = 'Business card settings saved.';
}

$cardCount = businessCardPrintCount();

$showSelfie = ($_GET['show_selfie'] ?? '1') === '1';
$showQr = ($_GET['show_qr'] ?? '1') === '1';

$businessNameFallback = contentValue('business_name');
$taglineFallback = contentValue('business_tagline');

$businessName = $settingValues['business_card_headline'] !== ''
    ? $settingValues['business_card_headline']
    : $businessNameFallback;

$tagline = $settingValues['business_card_tagline'] !== ''
    ? $settingValues['business_card_tagline']
    : $taglineFallback;

$servicesLine = $settingValues['business_card_services_line'];
$phoneDisplay = contentValue('business_phone_display');
$email = contentValue('business_email');
$website = contentValue('business_website');
$serviceArea = contentValue('business_service_area');
$logoSrc = contentValue('business_logo_src');
$personImageSrc = contentValue('business_person_image_src');
$personImageAlt = contentValue('business_person_image_alt');

$qrUrl = $settingValues['business_card_qr_url'] !== ''
    ? $settingValues['business_card_qr_url']
    : $website;

$qrAbsoluteUrl = businessCardAbsoluteUrl($qrUrl);
$qrImageUrl = businessCardQrImageUrl($qrUrl);

$cardNumbers = range(1, $cardCount);
$cardPages = array_chunk($cardNumbers, 10);

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<style>
    .business-card-generator {
        --business-card-paper-width: 8.5in;
        --business-card-paper-height: 11in;
        --business-card-width: 3.5in;
        --business-card-height: 2in;
        --business-card-paper-margin-y: 0.5in;
        --business-card-paper-margin-x: 0.75in;
    }

    .business-card-options-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr));
        gap: 1rem;
        align-items: start;
    }

    .business-card-option-actions {
        grid-column: 1 / -1;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: flex-end;
        align-items: center;
    }

    .business-card-admin-note,
    .business-card-preview-help {
        border: 1px solid var(--kails-border-color, rgba(242, 204, 22, 0.55));
        background: var(--kails-card-bg, rgba(255, 255, 255, 0.03));
        color: inherit;
    }

    .business-card-admin-note p,
    .business-card-preview-help p {
        color: inherit;
    }

    .business-card-preview-help {
        text-align: center;
    }

    .business-card-print-sheet {
        display: grid;
        gap: 2rem;
        justify-content: center;
        overflow-x: auto;
        padding-bottom: 2rem;
    }

    .business-card-paper {
        width: var(--business-card-paper-width);
        height: var(--business-card-paper-height);
        box-sizing: border-box;
        background: #ffffff;
        color: #111827;
        display: grid;
        grid-template-columns: repeat(2, var(--business-card-width));
        grid-template-rows: repeat(5, var(--business-card-height));
        gap: 0;
        padding: var(--business-card-paper-margin-y) var(--business-card-paper-margin-x);
        box-shadow: 0 18px 45px rgba(0, 0, 0, 0.35);
        page-break-after: always;
        break-after: page;
    }

    .business-card-paper:last-child {
        page-break-after: auto;
        break-after: auto;
    }

    .business-card-empty-slot {
        width: var(--business-card-width);
        height: var(--business-card-height);
        box-sizing: border-box;
        border: 1px dashed rgba(0, 0, 0, 0.12);
        background: #ffffff;
    }

    .print-business-card {
        width: var(--business-card-width);
        height: var(--business-card-height);
        box-sizing: border-box;
        position: relative;
        overflow: hidden;
        display: grid;
        grid-template-columns: 1fr 0.9in;
        gap: 0.06in;
        padding: 0.13in 0.14in;
        background:
            radial-gradient(circle at top right, rgba(151, 182, 42, 0.32), transparent 45%),
            linear-gradient(135deg, #050605 0%, #111a0d 58%, #263b16 100%);
        border: 1px solid rgba(0, 0, 0, 0.35);
        color: #ffffff;
    }

    .business-card-bg-mark {
        position: absolute;
        inset: auto -0.2in -0.26in auto;
        width: 1.6in;
        height: 1.6in;
        border-radius: 999px;
        background: rgba(242, 204, 22, 0.08);
        pointer-events: none;
    }

    .business-card-left,
    .business-card-right {
        position: relative;
        z-index: 1;
        min-width: 0;
    }

    .business-card-left {
        display: grid;
        grid-template-rows: auto 1fr auto;
        gap: 0.04in;
    }

    .business-card-logo-wrap {
        height: 0.26in;
        display: flex;
        align-items: flex-start;
    }

    .business-card-logo {
        max-width: 0.62in;
        max-height: 0.25in;
        object-fit: contain;
    }

    .business-card-main-text h2 {
        color: #ffffff;
        font-size: 0.19in;
        font-weight: 900;
        line-height: 1.02;
        margin: 0 0 0.025in;
    }

    .business-card-tagline {
        color: #f2cc16;
        font-size: 0.073in;
        font-weight: 800;
        line-height: 1.15;
        margin: 0 0 0.025in;
    }

    .business-card-services {
        color: #ffffff;
        font-size: 0.067in;
        font-weight: 700;
        line-height: 1.15;
        margin: 0;
    }

    .business-card-contact {
        display: grid;
        gap: 0.012in;
    }

    .business-card-contact p {
        color: #ffffff;
        font-size: 0.067in;
        line-height: 1.08;
        margin: 0;
    }

    .business-card-contact strong {
        color: #f2cc16;
        font-weight: 900;
    }

    .business-card-right {
        display: grid;
        grid-template-rows: 1fr auto;
        justify-items: end;
        align-items: end;
    }

    .business-card-person-wrap {
        width: 0.88in;
        height: 1.08in;
        align-self: start;
        justify-self: end;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        overflow: hidden;
    }

    .business-card-person {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        object-position: center bottom;
    }

    .business-card-qr-wrap {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 0.045in;
        padding: 0.035in;
        width: 0.57in;
        display: grid;
        justify-items: center;
        gap: 0.015in;
    }

    .business-card-qr {
        width: 0.48in;
        height: 0.48in;
        display: block;
    }

    .business-card-qr-wrap p {
        color: #111827;
        font-size: 0.043in;
        font-weight: 800;
        line-height: 1;
        margin: 0;
        text-align: center;
    }

    @media (max-width: 991.98px) {
        .business-card-options-grid {
            grid-template-columns: 1fr 1fr;
        }

        .business-card-option-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 575.98px) {
        .business-card-options-grid {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        @page {
            size: letter;
            margin: 0;
        }

        html,
        body {
            background: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .print-hide,
        .site-header,
        .site-footer,
        nav,
        footer {
            display: none !important;
        }

        .business-card-generator {
            margin: 0 !important;
            padding: 0 !important;
        }

        .business-card-generator .container {
            width: auto !important;
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .business-card-print-sheet {
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
        }

        .business-card-paper {
            width: 8.5in !important;
            height: 11in !important;
            margin: 0 !important;
            padding: 0.5in 0.75in !important;
            box-shadow: none !important;
            page-break-after: always;
            break-after: page;
        }

        .business-card-paper:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .print-business-card {
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
    }
</style>

<main
    class="site-section business-card-generator"
    data-business-card-generator
    data-business-name-fallback="<?php echo escapeHtml($businessNameFallback); ?>"
    data-tagline-fallback="<?php echo escapeHtml($taglineFallback); ?>"
    data-phone="<?php echo escapeHtml($phoneDisplay); ?>"
    data-email="<?php echo escapeHtml($email); ?>"
    data-website="<?php echo escapeHtml($website); ?>"
    data-service-area="<?php echo escapeHtml($serviceArea); ?>"
    data-logo-src="<?php echo escapeHtml($logoSrc); ?>"
    data-person-image-src="<?php echo escapeHtml($personImageSrc); ?>"
    data-person-image-alt="<?php echo escapeHtml($personImageAlt); ?>"
>
    <div class="container">
        <div class="print-hide">
            <?php adminRenderSecurityWarning(); ?>
            <?php renderAdminCrmNavigation('business_cards'); ?>
        </div>

        <div class="print-hide mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                Admin Tool
            </p>

            <h1 class="fw-bold">
                Business Card Generator
            </h1>

            <p class="text-muted">
                Edit business card text, review the live letter-paper preview below, then use the print button.
                Print at 100% scale with browser margins set to none/default if possible.
            </p>

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

            <div class="card p-4 mb-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-2">
                            Business Card Settings
                        </h2>

                        <p class="text-muted mb-0">
                            These settings control only the text and QR code used on the printed business cards.
                            The preview updates as you type.
                        </p>
                    </div>

                    <div>
                        <a href="/admin_website.php#admin-business-identity" class="btn btn-outline-light">
                            Edit Business Contact Info
                        </a>
                    </div>
                </div>

                <form method="post" action="/business_cards.php" id="businessCardSettingsForm">
                    <input type="hidden" name="form_name" value="save_business_card_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">

                    <div class="row g-3">
                        <?php foreach ($settingDefinitions as $contentKey => $settingDefinition): ?>
                            <div class="col-lg-6">
                                <label for="<?php echo escapeHtml($contentKey); ?>" class="form-label">
                                    <?php echo escapeHtml((string)$settingDefinition['label']); ?>
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="<?php echo escapeHtml($contentKey); ?>"
                                    name="content[<?php echo escapeHtml($contentKey); ?>]"
                                    value="<?php echo escapeHtml((string)($settingValues[$contentKey] ?? '')); ?>"
                                    maxlength="<?php echo escapeHtml((string)($settingDefinition['max_length'] ?? 255)); ?>"
                                    placeholder="<?php echo escapeHtml((string)($settingDefinition['placeholder'] ?? '')); ?>"
                                    data-business-card-live-input
                                >

                                <div class="form-text">
                                    <?php echo escapeHtml((string)$settingDefinition['help']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-light">
                            Save Business Card Settings
                        </button>

                        <a href="/business_cards.php" class="btn btn-outline-light">
                            Reset Preview
                        </a>
                    </div>
                </form>
            </div>

            <div class="card p-4 mb-4">
                <h2 class="h4 mb-3">
                    Print Options
                </h2>

                <form id="businessCardPrintOptionsForm">
                    <div class="business-card-options-grid">
                        <div>
                            <label for="count" class="form-label">
                                Number of Cards
                            </label>

                            <select class="form-control" id="count" name="count" data-business-card-live-input>
                                <?php foreach ([10, 20, 30, 40, 50, 100] as $countOption): ?>
                                    <option
                                        value="<?php echo escapeHtml((string)$countOption); ?>"
                                        <?php echo $cardCount === $countOption ? 'selected' : ''; ?>
                                    >
                                        <?php echo escapeHtml((string)$countOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="form-text">
                                10 cards fit on one standard letter page.
                            </div>
                        </div>

                        <div>
                            <label for="show_selfie" class="form-label">
                                Person Image
                            </label>

                            <select class="form-control" id="show_selfie" name="show_selfie" data-business-card-live-input>
                                <option value="1" <?php echo $showSelfie ? 'selected' : ''; ?>>
                                    Show person image
                                </option>

                                <option value="0" <?php echo !$showSelfie ? 'selected' : ''; ?>>
                                    Hide person image
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="show_qr" class="form-label">
                                QR Code
                            </label>

                            <select class="form-control" id="show_qr" name="show_qr" data-business-card-live-input>
                                <option value="1" <?php echo $showQr ? 'selected' : ''; ?>>
                                    Show QR code
                                </option>

                                <option value="0" <?php echo !$showQr ? 'selected' : ''; ?>>
                                    Hide QR code
                                </option>
                            </select>
                        </div>

                        <div class="business-card-option-actions">
                            <button type="button" class="btn btn-light" onclick="window.print();">
                                Print Cards
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card p-4 mb-4 business-card-admin-note">
                <div class="row g-3 align-items-start">
                    <div class="col-lg-8">
                        <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                            Business Card Editing Note
                        </p>

                        <p class="mb-0">
                            Business card headline, tagline, services line, and QR URL are edited here.
                            Business contact info, logo, and person image are edited in the website editor.
                        </p>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-grid gap-2">
                            <a href="/admin_website.php#admin-business-identity" class="btn btn-outline-light">
                                Edit Business Identity
                            </a>

                            <a href="/admin_website.php#admin-images" class="btn btn-outline-light">
                                Edit Website Images
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card p-3 mb-3 business-card-preview-help">
                <p class="mb-0">
                    Letter paper preview: each sheet is 8.5in × 11in with 10 business cards.
                </p>
            </div>
        </div>

        <template id="businessCardTemplate">
            <?php
                businessCardRenderOneCard(
                    $businessName,
                    $tagline,
                    $servicesLine,
                    $phoneDisplay,
                    $email,
                    $website,
                    $serviceArea,
                    $logoSrc,
                    $personImageSrc,
                    $personImageAlt,
                    $showSelfie,
                    $showQr,
                    $qrImageUrl,
                    $qrAbsoluteUrl
                );
            ?>
        </template>

        <section class="business-card-print-sheet" aria-label="Printable business cards" data-business-card-print-sheet>
            <?php foreach ($cardPages as $cardPage): ?>
                <div class="business-card-paper">
                    <?php foreach ($cardPage as $cardNumber): ?>
                        <?php
                            businessCardRenderOneCard(
                                $businessName,
                                $tagline,
                                $servicesLine,
                                $phoneDisplay,
                                $email,
                                $website,
                                $serviceArea,
                                $logoSrc,
                                $personImageSrc,
                                $personImageAlt,
                                $showSelfie,
                                $showQr,
                                $qrImageUrl,
                                $qrAbsoluteUrl
                            );
                        ?>
                    <?php endforeach; ?>

                    <?php for ($emptySlot = count($cardPage); $emptySlot < 10; $emptySlot++): ?>
                        <div class="business-card-empty-slot"></div>
                    <?php endfor; ?>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const generator = document.querySelector('[data-business-card-generator]');
    const printSheet = document.querySelector('[data-business-card-print-sheet]');
    const cardTemplate = document.getElementById('businessCardTemplate');

    const headlineInput = document.getElementById('business_card_headline');
    const taglineInput = document.getElementById('business_card_tagline');
    const servicesInput = document.getElementById('business_card_services_line');
    const qrUrlInput = document.getElementById('business_card_qr_url');

    const countInput = document.getElementById('count');
    const showSelfieInput = document.getElementById('show_selfie');
    const showQrInput = document.getElementById('show_qr');

    if (!generator || !printSheet || !cardTemplate) {
        return;
    }

    function cleanDisplayWebsite(value) {
        return String(value || '')
            .replace(/^https?:\/\//i, '')
            .replace(/\/+$/g, '');
    }

    function normalizeUrl(value) {
        let cleanValue = String(value || '').trim();

        if (cleanValue === '') {
            return '';
        }

        if (cleanValue.startsWith('/')) {
            const website = String(generator.dataset.website || '').trim();

            try {
                const baseUrl = new URL(website);
                return baseUrl.origin + cleanValue;
            } catch (error) {
                return cleanValue;
            }
        }

        if (!/^[a-z][a-z0-9+\-.]*:\/\//i.test(cleanValue)) {
            cleanValue = 'https://' + cleanValue;
        }

        return cleanValue;
    }

    function qrImageUrl(value) {
        const normalizedUrl = normalizeUrl(value);

        return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data='
            + encodeURIComponent(normalizedUrl);
    }

    function setHidden(element, shouldHide) {
        if (!element) {
            return;
        }

        element.hidden = shouldHide;
    }

    function setTextAndVisibility(element, value) {
        if (!element) {
            return;
        }

        const cleanValue = String(value || '').trim();

        element.textContent = cleanValue;
        element.hidden = cleanValue === '';
    }

    function getPreviewValues() {
        const businessFallback = String(generator.dataset.businessNameFallback || '').trim();
        const taglineFallback = String(generator.dataset.taglineFallback || '').trim();
        const website = String(generator.dataset.website || '').trim();

        const headlineValue = headlineInput ? String(headlineInput.value || '').trim() : '';
        const taglineValue = taglineInput ? String(taglineInput.value || '').trim() : '';
        const servicesValue = servicesInput ? String(servicesInput.value || '').trim() : '';
        const qrUrlValue = qrUrlInput ? String(qrUrlInput.value || '').trim() : '';

        let cardCount = countInput ? parseInt(countInput.value, 10) : 10;

        if (Number.isNaN(cardCount) || cardCount < 1) {
            cardCount = 10;
        }

        if (cardCount > 100) {
            cardCount = 100;
        }

        const showSelfie = showSelfieInput ? showSelfieInput.value === '1' : true;
        const showQr = showQrInput ? showQrInput.value === '1' : true;

        const finalQrUrl = qrUrlValue !== '' ? qrUrlValue : website;
        const finalAbsoluteQrUrl = normalizeUrl(finalQrUrl);

        return {
            businessName: headlineValue !== '' ? headlineValue : businessFallback,
            tagline: taglineValue !== '' ? taglineValue : taglineFallback,
            servicesLine: servicesValue,
            phone: String(generator.dataset.phone || '').trim(),
            email: String(generator.dataset.email || '').trim(),
            website: website,
            serviceArea: String(generator.dataset.serviceArea || '').trim(),
            logoSrc: String(generator.dataset.logoSrc || '').trim(),
            personImageSrc: String(generator.dataset.personImageSrc || '').trim(),
            personImageAlt: String(generator.dataset.personImageAlt || '').trim(),
            qrUrl: finalQrUrl,
            qrAbsoluteUrl: finalAbsoluteQrUrl,
            qrImageUrl: qrImageUrl(finalQrUrl),
            cardCount: cardCount,
            showSelfie: showSelfie,
            showQr: showQr
        };
    }

    function updateCard(card, values) {
        const businessNameElement = card.querySelector('[data-card-business-name]');
        const taglineElement = card.querySelector('[data-card-tagline]');
        const servicesElement = card.querySelector('[data-card-services]');

        const phoneRow = card.querySelector('[data-card-phone-row]');
        const emailRow = card.querySelector('[data-card-email-row]');
        const websiteRow = card.querySelector('[data-card-website-row]');
        const areaRow = card.querySelector('[data-card-area-row]');

        const phoneElement = card.querySelector('[data-card-phone]');
        const emailElement = card.querySelector('[data-card-email]');
        const websiteElement = card.querySelector('[data-card-website]');
        const areaElement = card.querySelector('[data-card-area]');

        const personWrap = card.querySelector('[data-card-person-wrap]');
        const personImage = card.querySelector('[data-card-person]');

        const qrWrap = card.querySelector('[data-card-qr-wrap]');
        const qrImage = card.querySelector('[data-card-qr-image]');

        if (businessNameElement) {
            businessNameElement.textContent = values.businessName;
        }

        setTextAndVisibility(taglineElement, values.tagline);
        setTextAndVisibility(servicesElement, values.servicesLine);

        if (phoneElement) {
            phoneElement.textContent = values.phone;
        }

        if (emailElement) {
            emailElement.textContent = values.email;
        }

        if (websiteElement) {
            websiteElement.textContent = cleanDisplayWebsite(values.website);
        }

        if (areaElement) {
            areaElement.textContent = values.serviceArea;
        }

        setHidden(phoneRow, values.phone === '');
        setHidden(emailRow, values.email === '');
        setHidden(websiteRow, values.website === '');
        setHidden(areaRow, values.serviceArea === '');

        if (personImage && values.personImageSrc !== '') {
            personImage.src = values.personImageSrc;
            personImage.alt = values.personImageAlt;
        }

        setHidden(personWrap, !values.showSelfie || values.personImageSrc === '');

        if (qrImage) {
            qrImage.src = values.qrImageUrl;
            qrImage.alt = 'QR code for ' + values.qrAbsoluteUrl;
        }

        setHidden(qrWrap, !values.showQr);
    }

    function createCard(values) {
        const fragment = cardTemplate.content.cloneNode(true);
        const card = fragment.querySelector('.print-business-card');

        if (card) {
            updateCard(card, values);
        }

        return fragment;
    }

    function createEmptySlot() {
        const emptySlot = document.createElement('div');
        emptySlot.className = 'business-card-empty-slot';

        return emptySlot;
    }

    function buildPreview() {
        const values = getPreviewValues();
        const pageCount = Math.ceil(values.cardCount / 10);

        printSheet.innerHTML = '';

        let cardsCreated = 0;

        for (let pageIndex = 0; pageIndex < pageCount; pageIndex++) {
            const paper = document.createElement('div');
            paper.className = 'business-card-paper';

            for (let slotIndex = 0; slotIndex < 10; slotIndex++) {
                if (cardsCreated < values.cardCount) {
                    paper.appendChild(createCard(values));
                    cardsCreated++;
                } else {
                    paper.appendChild(createEmptySlot());
                }
            }

            printSheet.appendChild(paper);
        }
    }

    const liveInputs = document.querySelectorAll('[data-business-card-live-input]');

    liveInputs.forEach(function (input) {
        input.addEventListener('input', buildPreview);
        input.addEventListener('change', buildPreview);
    });

    buildPreview();
});
</script>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';