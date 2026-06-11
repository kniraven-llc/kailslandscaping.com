<?php
declare(strict_types=1);

/*
    Admin website editor page.

    This page lets the admin edit text, upload replacement images,
    change button text and destinations, manage service cards,
    use simple rich text editing where it makes sense,
    and change theme colors.

    It also lets the admin edit structured business information used by:
    - the public website
    - business cards
    - receipts

    It does not let the admin change layout, spacing, sizing, or structure.
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Website Editor';

adminRequireLogin('Website Editor Login');

$messages = [];
$errors = [];

function adminHelpIcon(string $helpText): string
{
    if ($helpText === '') {
        return '';
    }

    return '<span class="badge rounded-pill kails-bg-yellow ms-2 admin-help-icon" role="button" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="' . escapeHtml($helpText) . '">?</span>';
}

function adminRichTextEditor(
    string $fieldId,
    string $fieldName,
    string $value,
    string $helpText = ''
): string {
    $safeFieldId = escapeHtml($fieldId);
    $safeFieldName = escapeHtml($fieldName);
    $safeHtml = sanitizeRichHtml($value);
    $safeTextareaValue = escapeHtml($safeHtml);
    $safeHelpText = escapeHtml($helpText);

    return <<<HTML
<div class="admin-rich-editor" data-rich-editor>
    <div class="admin-rich-toolbar" aria-label="Text formatting toolbar">
        <button type="button" class="btn btn-sm btn-outline-light" data-rich-command="bold">Bold</button>
        <button type="button" class="btn btn-sm btn-outline-light" data-rich-command="italic">Italic</button>
        <button type="button" class="btn btn-sm btn-outline-light" data-rich-command="underline">Underline</button>
        <button type="button" class="btn btn-sm btn-outline-light" data-rich-command="insertUnorderedList">Bullets</button>
        <button type="button" class="btn btn-sm btn-outline-light" data-rich-command="insertOrderedList">Numbered</button>
        <button type="button" class="btn btn-sm btn-outline-light" data-rich-command="createLink">Link</button>
        <button type="button" class="btn btn-sm btn-outline-light" data-rich-command="unlink">Remove Link</button>
        <button type="button" class="btn btn-sm btn-outline-light" data-rich-command="removeFormat">Clear Format</button>
    </div>

    <div
        class="admin-rich-editable"
        contenteditable="true"
        data-rich-editor-box
        data-rich-hidden-input="{$safeFieldId}"
    >{$safeHtml}</div>

    <textarea
        class="visually-hidden"
        id="{$safeFieldId}"
        name="{$safeFieldName}"
        data-rich-hidden-textarea
    >{$safeTextareaValue}</textarea>

    <div class="admin-help-text">
        {$safeHelpText}
    </div>
</div>
HTML;
}

function adminUploadErrorMessage(int $uploadErrorCode): string
{
    return match ($uploadErrorCode) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file is too large.',
        UPLOAD_ERR_PARTIAL => 'The file only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload folder.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the file upload.',
        default => 'The file upload failed.',
    };
}

function isValidButtonDestination(string $href): bool
{
    if ($href === '') {
        return false;
    }

    $allowedStarts = [
        '#',
        '/',
        'https://',
        'http://',
        'tel:',
        'mailto:',
    ];

    foreach ($allowedStarts as $allowedStart) {
        if (str_starts_with($href, $allowedStart)) {
            return true;
        }
    }

    return false;
}

function getAdminImageUploadSettings(): array
{
    return [
        'site_logo' => [
            'label' => 'Website / Business Logo',
            'help' => 'This replaces the logo used in the navbar, hero section, business cards, and receipts.',
            'input_name' => 'site_logo_upload',
            'current_key' => 'business_logo_src',
            'content_keys' => [
                'navbar_logo_src',
                'hero_logo_src',
                'business_logo_src',
            ],
            'base_public_path' => '/assets/images/kails-logo',
            'fallback_public_path' => '/assets/images/kails-logo.png',
            'old_base_public_paths' => [
                '/assets/images/uploads/site-logo',
            ],
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'allowed_extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            'accept' => '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp',
            'requirements' => [
                'Allowed file types: PNG, JPG/JPEG, or WebP.',
                'PNG or WebP is best when the logo has transparency.',
                'JPG is okay only when the logo has a solid background.',
                'Recommended size: 1536px wide × 1024px tall or similar.',
                'Recommended aspect ratio: 1.5:1 to 5:1.',
                'Minimum size: 500px wide × 150px tall.',
                'Maximum size: 2400px wide × 1600px tall.',
                'Maximum file size: 3MB.',
            ],
            'min_width' => 500,
            'min_height' => 150,
            'max_width' => 2400,
            'max_height' => 1600,
            'min_ratio' => 1.5,
            'max_ratio' => 5.0,
            'max_file_size' => 3 * 1024 * 1024,
        ],
        'hero_person' => [
            'label' => 'Homepage Hero Main Image',
            'help' => 'This replaces the main image shown near the top of the homepage. Use a landscape image so it fills the hero card cleanly.',
            'input_name' => 'hero_person_upload',
            'current_key' => 'business_person_image_src',
            'content_keys' => [
                'hero_selfie_src',
                'business_person_image_src',
            ],
            'base_public_path' => '/assets/images/kail-selfie',
            'fallback_public_path' => '/assets/images/kail-selfie.png',
            'old_base_public_paths' => [
                '/assets/images/uploads/hero-person',
            ],
            'allowed_mime_types' => [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            'allowed_extensions' => [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            'accept' => '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp',
            'requirements' => [
                'Allowed file types: PNG, JPG/JPEG, or WebP.',
                'Use a landscape image, not a portrait image.',
                'Recommended size: 1200px wide × 800px tall.',
                'Recommended aspect ratio: 3:2 or 4:3.',
                'Acceptable aspect ratio: 1.25:1 to 1.8:1.',
                'Keep the main subject near the center of the image.',
                'Avoid placing important details in the bottom-right corner because the logo overlay appears there.',
                'Minimum size: 900px wide × 600px tall.',
                'Maximum size: 2400px wide × 1600px tall.',
                'Maximum file size: 3MB.',
            ],
            'min_width' => 900,
            'min_height' => 600,
            'max_width' => 2400,
            'max_height' => 1600,
            'min_ratio' => 1.25,
            'max_ratio' => 1.8,
            'max_file_size' => 3 * 1024 * 1024,
        ],
        'favicon' => [
            'label' => 'Browser Tab Icon / Favicon',
            'help' => 'This replaces the small icon shown in the browser tab and bookmarks.',
            'input_name' => 'favicon_upload',
            'content_keys' => [],
            'fixed_public_path' => '/favicon.ico',
            'accept' => '.ico,image/x-icon,image/vnd.microsoft.icon',
            'requirements' => [
                'Allowed file type: ICO only.',
                'Recommended size: a favicon.ico file that includes 16×16, 32×32, and 48×48 sizes.',
                'Maximum file size: 256KB.',
                'The browser may cache favicons strongly. Refresh or clear browser cache if it does not update right away.',
            ],
            'allowed_extensions' => [
                'ico',
            ],
            'max_file_size' => 256 * 1024,
            'is_favicon' => true,
        ],
    ];
}

function getServiceImageUploadSettings(int $serviceId): array
{
    return [
        'label' => 'Service Image',
        'help' => 'Optional image shown at the top of this service card.',
        'input_name' => 'service_image_' . $serviceId,
        'base_public_path' => '/assets/images/uploads/services/service-' . $serviceId,
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'allowed_extensions' => [
            'jpg',
            'jpeg',
            'png',
            'webp',
        ],
        'accept' => '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp',
        'requirements' => [
            'Allowed file types: PNG, JPG/JPEG, or WebP.',
            'Recommended size: 1200px wide × 800px tall.',
            'Recommended aspect ratio: 1.25:1 to 1.8:1.',
            'Minimum size: 600px wide × 400px tall.',
            'Maximum size: 2000px wide × 1400px tall.',
            'Maximum file size: 1MB.',
            'Landscape photos work best.',
        ],
        'min_width' => 600,
        'min_height' => 400,
        'max_width' => 2000,
        'max_height' => 1400,
        'min_ratio' => 1.25,
        'max_ratio' => 1.8,
        'max_file_size' => 1 * 1024 * 1024,
    ];
}

function adminPublicPathToFilePath(string $publicPath): string
{
    $publicPath = trim($publicPath);

    if ($publicPath === '') {
        return PROJECT_ROOT . '/public';
    }

    $pathOnly = explode('?', $publicPath, 2)[0];
    $pathOnly = '/' . ltrim($pathOnly, '/');

    return PROJECT_ROOT . '/public' . $pathOnly;
}

function adminUploadedImageExtensionFromMime(string $mimeType): string
{
    return match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
}

function adminCurrentImagePublicPath(array $imageSettings): string
{
    $currentKey = (string)($imageSettings['current_key'] ?? '');

    if ($currentKey !== '') {
        $currentPath = contentValue($currentKey);

        if ($currentPath !== '') {
            return $currentPath;
        }
    }

    $fallbackPath = (string)($imageSettings['fallback_public_path'] ?? '');

    if ($fallbackPath !== '') {
        return $fallbackPath;
    }

    $fixedPublicPath = (string)($imageSettings['fixed_public_path'] ?? '');

    if ($fixedPublicPath !== '') {
        return $fixedPublicPath;
    }

    $basePublicPath = (string)($imageSettings['base_public_path'] ?? '');

    if ($basePublicPath === '') {
        return '';
    }

    $extensions = $imageSettings['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp'];

    if (!is_array($extensions)) {
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    }

    foreach ($extensions as $extension) {
        $extension = strtolower((string)$extension);
        $possiblePublicPath = $basePublicPath . '.' . $extension;

        if (is_file(adminPublicPathToFilePath($possiblePublicPath))) {
            return $possiblePublicPath;
        }
    }

    return '';
}

function adminDeleteFileIfSafe(string $publicPath): void
{
    $filePath = adminPublicPathToFilePath($publicPath);
    $publicRoot = realpath(PROJECT_ROOT . '/public');
    $targetDirectory = realpath(dirname($filePath));

    if ($publicRoot === false || $targetDirectory === false) {
        return;
    }

    if (!str_starts_with($targetDirectory, $publicRoot)) {
        return;
    }

    if (is_file($filePath)) {
        unlink($filePath);
    }
}

function adminDeleteExistingImageVariants(array $imageSettings): void
{
    $extensions = $imageSettings['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'webp'];

    if (!is_array($extensions)) {
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    }

    $fixedPublicPath = (string)($imageSettings['fixed_public_path'] ?? '');

    if ($fixedPublicPath !== '') {
        adminDeleteFileIfSafe($fixedPublicPath);
        return;
    }

    $basePaths = [];
    $basePublicPath = (string)($imageSettings['base_public_path'] ?? '');

    if ($basePublicPath !== '') {
        $basePaths[] = $basePublicPath;
    }

    $oldBasePaths = $imageSettings['old_base_public_paths'] ?? [];

    if (is_array($oldBasePaths)) {
        foreach ($oldBasePaths as $oldBasePath) {
            $oldBasePath = (string)$oldBasePath;

            if ($oldBasePath !== '') {
                $basePaths[] = $oldBasePath;
            }
        }
    }

    foreach (array_unique($basePaths) as $pathBase) {
        foreach ($extensions as $extension) {
            $extension = strtolower((string)$extension);
            adminDeleteFileIfSafe($pathBase . '.' . $extension);
        }
    }
}

function adminMoveUploadedFile(string $temporaryPath, string $publicPath, array $imageSettings, array &$errors): ?string
{
    $fullSavePath = adminPublicPathToFilePath($publicPath);
    $saveDirectory = dirname($fullSavePath);

    if (!is_dir($saveDirectory)) {
        mkdir($saveDirectory, 0775, true);
    }

    if (!is_dir($saveDirectory) || !is_writable($saveDirectory)) {
        $errors[] = (string)$imageSettings['label'] . ': The image upload folder is not writable.';
        return null;
    }

    $extension = strtolower(pathinfo($fullSavePath, PATHINFO_EXTENSION));
    $temporarySavePath = $saveDirectory . DIRECTORY_SEPARATOR . '.upload-' . bin2hex(random_bytes(8)) . '.' . $extension;

    if (!move_uploaded_file($temporaryPath, $temporarySavePath)) {
        $errors[] = (string)$imageSettings['label'] . ': The uploaded file could not be saved.';
        return null;
    }

    adminDeleteExistingImageVariants($imageSettings);

    if (!rename($temporarySavePath, $fullSavePath)) {
        if (is_file($temporarySavePath)) {
            unlink($temporarySavePath);
        }

        $errors[] = (string)$imageSettings['label'] . ': The uploaded file could not replace the old file.';
        return null;
    }

    chmod($fullSavePath, 0664);

    return $publicPath;
}

function saveUploadedAdminImage(array $imageSettings, array &$errors): ?string
{
    $inputName = (string)$imageSettings['input_name'];

    if (!isset($_FILES[$inputName])) {
        return null;
    }

    $uploadedFile = $_FILES[$inputName];

    if (!is_array($uploadedFile)) {
        return null;
    }

    $uploadError = (int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($uploadError !== UPLOAD_ERR_OK) {
        $errors[] = (string)$imageSettings['label'] . ': ' . adminUploadErrorMessage($uploadError);
        return null;
    }

    $temporaryPath = (string)($uploadedFile['tmp_name'] ?? '');
    $originalName = (string)($uploadedFile['name'] ?? '');
    $fileSize = (int)($uploadedFile['size'] ?? 0);

    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        $errors[] = (string)$imageSettings['label'] . ': The uploaded file could not be verified.';
        return null;
    }

    if ($fileSize > (int)$imageSettings['max_file_size']) {
        $errors[] = (string)$imageSettings['label'] . ': The file is too large.';
        return null;
    }

    if (!empty($imageSettings['is_favicon'])) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'ico') {
            $errors[] = (string)$imageSettings['label'] . ': Only .ico files are allowed for the favicon.';
            return null;
        }

        $fileHandle = fopen($temporaryPath, 'rb');
        $headerBytes = $fileHandle ? fread($fileHandle, 4) : false;

        if ($fileHandle) {
            fclose($fileHandle);
        }

        if ($headerBytes !== "\x00\x00\x01\x00" && $headerBytes !== "\x00\x00\x02\x00") {
            $errors[] = (string)$imageSettings['label'] . ': The uploaded favicon must be a valid ICO file.';
            return null;
        }

        return adminMoveUploadedFile(
            $temporaryPath,
            (string)$imageSettings['fixed_public_path'],
            $imageSettings,
            $errors
        );
    }

    $imageInfo = @getimagesize($temporaryPath);

    if ($imageInfo === false) {
        $errors[] = (string)$imageSettings['label'] . ': The uploaded file must be an image.';
        return null;
    }

    $width = (int)($imageInfo[0] ?? 0);
    $height = (int)($imageInfo[1] ?? 0);
    $mimeType = (string)($imageInfo['mime'] ?? '');

    $allowedMimeTypes = $imageSettings['allowed_mime_types'] ?? [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    if (!is_array($allowedMimeTypes)) {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
    }

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        $errors[] = (string)$imageSettings['label'] . ': Only JPG, PNG, and WebP images are allowed.';
        return null;
    }

    if ($width < (int)$imageSettings['min_width'] || $height < (int)$imageSettings['min_height']) {
        $errors[] = (string)$imageSettings['label'] . ': The image is too small.';
        return null;
    }

    if ($width > (int)$imageSettings['max_width'] || $height > (int)$imageSettings['max_height']) {
        $errors[] = (string)$imageSettings['label'] . ': The image is too large in width or height.';
        return null;
    }

    $aspectRatio = $width / $height;

    if ($aspectRatio < (float)$imageSettings['min_ratio'] || $aspectRatio > (float)$imageSettings['max_ratio']) {
        $errors[] = (string)$imageSettings['label'] . ': The image shape does not fit this part of the website.';
        return null;
    }

    $extension = adminUploadedImageExtensionFromMime($mimeType);

    if ($extension === '') {
        $errors[] = (string)$imageSettings['label'] . ': The uploaded image type is not supported.';
        return null;
    }

    $basePublicPath = (string)($imageSettings['base_public_path'] ?? '');

    if ($basePublicPath === '') {
        $errors[] = (string)$imageSettings['label'] . ': The image save path is missing.';
        return null;
    }

    $publicPath = $basePublicPath . '.' . $extension;

    return adminMoveUploadedFile($temporaryPath, $publicPath, $imageSettings, $errors);
}

function upsertContentValue(
    mysqli $connection,
    string $contentKey,
    string $contentValue,
    array $contentRows
): void {
    $row = $contentRows[$contentKey] ?? [
        'label' => $contentKey,
        'type' => 'text',
        'sort_order' => 0,
    ];

    $contentLabel = (string)($row['label'] ?? $contentKey);
    $contentType = (string)($row['type'] ?? 'text');
    $sortOrder = (int)($row['sort_order'] ?? 0);

    $statement = $connection->prepare(
        'INSERT INTO site_content
            (content_key, content_label, content_type, content_value, sort_order)
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

function upsertButtonValue(
    mysqli $connection,
    string $buttonKey,
    string $buttonText,
    string $buttonHref,
    array $buttonRows
): void {
    $row = $buttonRows[$buttonKey] ?? [
        'label' => $buttonKey,
        'help' => '',
        'sort_order' => 0,
    ];

    $buttonLabel = (string)($row['label'] ?? $buttonKey);
    $buttonHelp = (string)($row['help'] ?? '');
    $sortOrder = (int)($row['sort_order'] ?? 0);

    $statement = $connection->prepare(
        'INSERT INTO site_buttons
            (button_key, button_label, button_help, button_text, button_href, sort_order)
         VALUES
            (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            button_label = VALUES(button_label),
            button_help = VALUES(button_help),
            button_text = VALUES(button_text),
            button_href = VALUES(button_href),
            sort_order = VALUES(sort_order)'
    );

    $statement->bind_param(
        'sssssi',
        $buttonKey,
        $buttonLabel,
        $buttonHelp,
        $buttonText,
        $buttonHref,
        $sortOrder
    );

    $statement->execute();
}

function getAdminContentGroups(): array
{
    return [
        'Business Identity / Contact' => [
            'id' => 'admin-business-identity',
            'description' => 'Core business information used by the website, business cards, receipts, and printable tools.',
            'keys' => [
                'business_name',
                'business_owner_display_name',
                'business_legal_owner_name',
                'business_phone_display',
                'business_phone_digits',
                'business_email',
                'business_website',
                'business_service_area',
                'business_hours',
                'business_tagline',
                'business_person_image_alt',
            ],
        ],
        'Receipt Settings' => [
            'id' => 'admin-receipt-settings',
            'description' => 'Default receipt text used by the receipt generator and printable receipts.',
            'keys' => [
                'receipt_number_prefix',
                'receipt_default_title',
                'receipt_footer_note',
                'receipt_payment_note',
            ],
        ],
        'Navigation' => [
            'id' => 'admin-navigation',
            'description' => 'Text shown in the top menu.',
            'keys' => [
                'navbar_brand_text',
                'nav_home_text',
                'nav_services_text',
                'nav_about_text',
                'nav_contact_text',
            ],
        ],
        'Hero Section' => [
            'id' => 'admin-hero',
            'description' => 'The first section visitors see when they open the homepage.',
            'keys' => [
                'hero_eyebrow',
                'hero_title',
                'hero_lead',
                'hero_selfie_alt',
                'hero_logo_alt',
            ],
            'buttons' => [
                'hero_primary',
                'hero_secondary',
            ],
        ],
        'Quick Contact Box' => [
            'id' => 'admin-quick-contact',
            'description' => 'The contact box shown near the top of the homepage.',
            'keys' => [
                'quick_contact_title',
                'quick_contact_body',
                'quick_contact_footer',
            ],
        ],
        'Services Section' => [
            'id' => 'admin-services',
            'description' => 'The heading and button around the service cards.',
            'keys' => [
                'services_eyebrow',
                'services_title',
                'services_intro',
            ],
            'buttons' => [
                'services_cta',
            ],
        ],
        'About Section' => [
            'id' => 'admin-about',
            'description' => 'The company explanation and trust-building bullets.',
            'keys' => [
                'about_eyebrow',
                'about_title',
                'about_body_1',
                'about_body_2',
                'why_choose_title',
                'why_choose_item_1',
                'why_choose_item_2',
                'why_choose_item_3',
                'why_choose_item_4',
            ],
        ],
        'Contact Form' => [
            'id' => 'admin-contact-form',
            'description' => 'Labels and text shown in the quote request form.',
            'keys' => [
                'contact_eyebrow',
                'contact_title',
                'contact_intro',
                'form_name_label',
                'form_phone_label',
                'form_email_label',
                'form_service_label',
                'form_service_placeholder',
                'form_message_label',
                'form_submit_text',
                'quote_form_success_message',
                'quote_form_error_message',
            ],
        ],
        'Footer' => [
            'id' => 'admin-footer',
            'description' => 'Text shown at the bottom of the website.',
            'keys' => [
                'footer_copyright_suffix',
                'footer_tagline',
            ],
        ],
        'SEO' => [
            'id' => 'admin-seo',
            'description' => 'Basic search engine and browser metadata.',
            'keys' => [
                'site_meta_description',
            ],
        ],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'save_admin_settings') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    } else {
        try {
            $connection = getDatabaseConnection();
            $contentRows = getEditableSiteContentRows();
            $buttonRows = getEditableButtonRows();

            $postedExistingServices = $_POST['services'] ?? [];
            $postedDeleteServices = $_POST['delete_services'] ?? [];
            $postedNewService = $_POST['new_service'] ?? [];

            $activeServiceCount = 0;

            if (is_array($postedExistingServices)) {
                foreach ($postedExistingServices as $serviceId => $serviceData) {
                    $serviceId = (int)$serviceId;

                    if ($serviceId <= 0) {
                        continue;
                    }

                    if (is_array($postedDeleteServices) && isset($postedDeleteServices[$serviceId])) {
                        continue;
                    }

                    if (!empty($serviceData['is_active'])) {
                        $activeServiceCount++;
                    }
                }
            }

            if (is_array($postedNewService)) {
                $newTitle = trim((string)($postedNewService['service_title'] ?? ''));
                $newDescriptionPlain = trim(strip_tags((string)($postedNewService['service_description'] ?? '')));

                if ($newTitle !== '' || $newDescriptionPlain !== '') {
                    if (!empty($postedNewService['is_active'])) {
                        $activeServiceCount++;
                    }
                }
            }

            if ($activeServiceCount < 1) {
                $errors[] = 'At least one service must be active.';
            }

            $postedButtons = $_POST['buttons'] ?? [];

            if (is_array($postedButtons)) {
                foreach ($buttonRows as $buttonKey => $buttonRow) {
                    $buttonData = $postedButtons[$buttonKey] ?? [];

                    if (!is_array($buttonData)) {
                        continue;
                    }

                    $buttonText = trim((string)($buttonData['text'] ?? ''));
                    $buttonHref = trim((string)($buttonData['href'] ?? ''));

                    if ($buttonText === '') {
                        $errors[] = (string)$buttonRow['label'] . ': Button text cannot be blank.';
                    }

                    if (!isValidButtonDestination($buttonHref)) {
                        $errors[] = (string)$buttonRow['label'] . ': Button destination must start with #, /, https://, http://, tel:, or mailto:.';
                    }
                }
            }

            if (empty($errors)) {
                $postedContent = $_POST['content'] ?? [];

                if (is_array($postedContent)) {
                    foreach ($contentRows as $key => $row) {
                        if (($row['type'] ?? '') === 'image_path') {
                            continue;
                        }

                        $value = (string)($postedContent[$key] ?? '');

                        if (($row['type'] ?? '') === 'richtext') {
                            $value = sanitizeRichHtml($value);
                        }

                        upsertContentValue($connection, (string)$key, $value, $contentRows);
                    }
                }

                if (is_array($postedButtons)) {
                    foreach ($buttonRows as $buttonKey => $buttonRow) {
                        $buttonData = $postedButtons[$buttonKey] ?? [];

                        if (!is_array($buttonData)) {
                            continue;
                        }

                        $buttonText = trim((string)($buttonData['text'] ?? ''));
                        $buttonHref = trim((string)($buttonData['href'] ?? ''));

                        upsertButtonValue($connection, (string)$buttonKey, $buttonText, $buttonHref, $buttonRows);
                    }
                }

                $themeRows = getEditableThemeColorRows();
                $postedThemeColors = $_POST['theme_colors'] ?? [];

                if (is_array($postedThemeColors)) {
                    $themeStatement = $connection->prepare(
                        'INSERT INTO theme_colors
                            (color_key, css_variable, color_label, color_value, sort_order)
                         VALUES
                            (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                            color_value = VALUES(color_value),
                            css_variable = VALUES(css_variable),
                            color_label = VALUES(color_label),
                            sort_order = VALUES(sort_order)'
                    );

                    foreach ($themeRows as $key => $row) {
                        $colorValue = strtoupper((string)($postedThemeColors[$key] ?? ''));

                        if (!preg_match('/^#[0-9A-F]{6}$/', $colorValue)) {
                            $errors[] = 'Invalid color value for ' . (string)($row['label'] ?? $key) . '.';
                            continue;
                        }

                        $cssVariable = (string)($row['css_variable'] ?? '');
                        $label = (string)($row['label'] ?? $key);
                        $sortOrder = (int)($row['sort_order'] ?? 0);

                        $themeStatement->bind_param(
                            'ssssi',
                            $key,
                            $cssVariable,
                            $label,
                            $colorValue,
                            $sortOrder
                        );

                        $themeStatement->execute();
                    }
                }

                foreach (getAdminImageUploadSettings() as $imageSettings) {
                    $uploadedImagePath = saveUploadedAdminImage($imageSettings, $errors);

                    if ($uploadedImagePath === null) {
                        continue;
                    }

                    $imageContentKeys = $imageSettings['content_keys'] ?? [];

                    if (is_array($imageContentKeys)) {
                        foreach ($imageContentKeys as $contentKey) {
                            upsertContentValue($connection, (string)$contentKey, $uploadedImagePath, $contentRows);
                        }
                    }
                }

                if (is_array($postedDeleteServices)) {
                    foreach ($postedDeleteServices as $serviceId => $deleteValue) {
                        $serviceId = (int)$serviceId;

                        if ($serviceId <= 0) {
                            continue;
                        }

                        $deleteStatement = $connection->prepare('DELETE FROM services WHERE id = ?');
                        $deleteStatement->bind_param('i', $serviceId);
                        $deleteStatement->execute();
                    }
                }

                if (is_array($postedExistingServices)) {
                    foreach ($postedExistingServices as $serviceId => $serviceData) {
                        $serviceId = (int)$serviceId;

                        if ($serviceId <= 0) {
                            continue;
                        }

                        if (is_array($postedDeleteServices) && isset($postedDeleteServices[$serviceId])) {
                            continue;
                        }

                        if (!is_array($serviceData)) {
                            continue;
                        }

                        $serviceTitle = trim((string)($serviceData['service_title'] ?? ''));
                        $serviceDescription = sanitizeRichHtml((string)($serviceData['service_description'] ?? ''));
                        $serviceImageAlt = trim((string)($serviceData['service_image_alt'] ?? ''));
                        $isActive = !empty($serviceData['is_active']) ? 1 : 0;
                        $sortOrder = (int)($serviceData['sort_order'] ?? 0);

                        if ($serviceTitle === '') {
                            $serviceTitle = 'Untitled Service';
                        }

                        if ($serviceDescription === '') {
                            $serviceDescription = '<p>Service description coming soon.</p>';
                        }

                        $updateStatement = $connection->prepare(
                            'UPDATE services
                             SET service_title = ?,
                                 service_description = ?,
                                 service_image_alt = ?,
                                 is_active = ?,
                                 sort_order = ?
                             WHERE id = ?'
                        );

                        $updateStatement->bind_param(
                            'sssiii',
                            $serviceTitle,
                            $serviceDescription,
                            $serviceImageAlt,
                            $isActive,
                            $sortOrder,
                            $serviceId
                        );

                        $updateStatement->execute();

                        $serviceImageSettings = getServiceImageUploadSettings($serviceId);

                        if (!empty($serviceData['remove_image'])) {
                            adminDeleteExistingImageVariants($serviceImageSettings);

                            $emptyPath = null;
                            $removeImageStatement = $connection->prepare(
                                'UPDATE services
                                 SET service_image_path = ?
                                 WHERE id = ?'
                            );
                            $removeImageStatement->bind_param('si', $emptyPath, $serviceId);
                            $removeImageStatement->execute();
                        }

                        $uploadedServiceImagePath = saveUploadedAdminImage($serviceImageSettings, $errors);

                        if ($uploadedServiceImagePath !== null) {
                            $imageUpdateStatement = $connection->prepare(
                                'UPDATE services
                                 SET service_image_path = ?
                                 WHERE id = ?'
                            );
                            $imageUpdateStatement->bind_param('si', $uploadedServiceImagePath, $serviceId);
                            $imageUpdateStatement->execute();
                        }
                    }
                }

                if (is_array($postedNewService)) {
                    $totalServicesResult = $connection->query('SELECT COUNT(*) AS total_services FROM services');
                    $totalServicesRow = $totalServicesResult->fetch_assoc();
                    $totalServices = (int)($totalServicesRow['total_services'] ?? 0);

                    $newTitle = trim((string)($postedNewService['service_title'] ?? ''));
                    $newDescription = sanitizeRichHtml((string)($postedNewService['service_description'] ?? ''));
                    $newDescriptionPlain = trim(strip_tags($newDescription));
                    $newImageAlt = trim((string)($postedNewService['service_image_alt'] ?? ''));
                    $newIsActive = !empty($postedNewService['is_active']) ? 1 : 0;
                    $newSortOrder = (int)($postedNewService['sort_order'] ?? 0);

                    if ($newTitle !== '' || $newDescriptionPlain !== '') {
                        if ($totalServices >= 9) {
                            $errors[] = 'You can only have up to 9 services.';
                        } else {
                            if ($newTitle === '') {
                                $newTitle = 'Untitled Service';
                            }

                            if ($newDescription === '') {
                                $newDescription = '<p>Service description coming soon.</p>';
                            }

                            if ($newSortOrder <= 0) {
                                $newSortOrder = ($totalServices + 1) * 10;
                            }

                            $newImagePath = null;

                            $insertStatement = $connection->prepare(
                                'INSERT INTO services
                                    (service_title, service_description, service_image_path, service_image_alt, is_active, sort_order)
                                 VALUES
                                    (?, ?, ?, ?, ?, ?)'
                            );

                            $insertStatement->bind_param(
                                'ssssii',
                                $newTitle,
                                $newDescription,
                                $newImagePath,
                                $newImageAlt,
                                $newIsActive,
                                $newSortOrder
                            );

                            $insertStatement->execute();
                            $newServiceId = (int)$connection->insert_id;

                            $serviceImageSettings = getServiceImageUploadSettings($newServiceId);
                            $serviceImageSettings['input_name'] = 'new_service_image';

                            $uploadedServiceImagePath = saveUploadedAdminImage($serviceImageSettings, $errors);

                            if ($uploadedServiceImagePath !== null) {
                                $imageUpdateStatement = $connection->prepare(
                                    'UPDATE services
                                     SET service_image_path = ?
                                     WHERE id = ?'
                                );
                                $imageUpdateStatement->bind_param('si', $uploadedServiceImagePath, $newServiceId);
                                $imageUpdateStatement->execute();
                            }
                        }
                    }
                }

                if (empty($errors)) {
                    redirectTo('/admin_website.php?saved=1');
                }
            }
        } catch (Throwable $exception) {
            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'Admin save failed.';
            }
        }
    }
}

if (isset($_GET['saved'])) {
    $messages[] = 'Website settings saved.';
}

$contentRows = getEditableSiteContentRows();
$themeRows = getEditableThemeColorRows();
$buttonRows = getEditableButtonRows();
$serviceRows = getAllServiceRows();
$imageUploadSettings = getAdminImageUploadSettings();

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<main class="site-section">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('website_editor'); ?>

        <div class="admin-layout">
            <aside class="admin-sidebar">
                <div class="admin-sidebar-card">
                    <h2 class="h5">Website Editor Sections</h2>
                    <nav class="admin-menu">
                        <a href="#admin-images">Images</a>
                        <a href="#admin-theme">Theme Colors</a>
                        <a href="#admin-business-identity">Business Identity</a>
                        <a href="#admin-receipt-settings">Receipt Settings</a>
                        <a href="#admin-navigation">Navigation</a>
                        <a href="#admin-hero">Hero</a>
                        <a href="#admin-quick-contact">Quick Contact</a>
                        <a href="#admin-services">Services</a>
                        <a href="#admin-service-cards">Service Cards</a>
                        <a href="#admin-about">About</a>
                        <a href="#admin-contact-form">Contact Form</a>
                        <a href="#admin-footer">Footer</a>
                        <a href="#admin-seo">SEO</a>
                    </nav>
                </div>
            </aside>

            <div class="admin-editor">
                <div class="mb-4">
                    <p class="kails-text-yellow fw-bold text-uppercase mb-2">Admin</p>
                    <h1 class="fw-bold">Website Content & Theme Editor</h1>
                    <p class="text-muted">
                        Edit website text, replace images, manage service cards, update button links,
                        change theme colors, and manage the business information used by receipts and business cards.
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

                <form method="post" action="/admin_website.php" enctype="multipart/form-data">
                    <input type="hidden" name="form_name" value="save_admin_settings">
                    <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">

                    <div class="card p-3 mb-4" style="position: sticky; top: 0rem; z-index: 20;">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div>
                                <button type="submit" class="btn btn-light">
                                    Save Website Settings
                                </button>
                            </div>
                        </div>
                    </div>

                    <section id="admin-images" class="card admin-section p-4 mb-4">
                        <h2 class="h4 mb-2">Website Images</h2>
                        <p class="text-muted">
                            Upload new images here. The preview changes immediately after you choose a file so you can judge it before saving.
                            The site replaces the old image after you click Save Website Settings.
                        </p>

                        <div class="row g-4">
                            <?php foreach ($imageUploadSettings as $imageSettings): ?>
                                <?php
                                $currentImagePath = adminCurrentImagePublicPath($imageSettings);
                                $previewImagePath = $currentImagePath;

                                if ($previewImagePath !== '') {
                                    $previewImagePath .= (strpos($previewImagePath, '?') === false ? '?' : '&') . 'v=' . time();
                                }
                                ?>

                                <div class="col-lg-6">
                                    <div class="admin-inner-panel h-100">
                                        <h3 class="h5">
                                            <?php echo escapeHtml((string)$imageSettings['label']); ?>
                                            <?php echo adminHelpIcon((string)$imageSettings['help']); ?>
                                        </h3>

                                        <?php if ($currentImagePath !== ''): ?>
                                            <div class="admin-image-preview mb-3">
                                                <img
                                                    src="<?php echo escapeHtml($previewImagePath); ?>"
                                                    alt="<?php echo escapeHtml((string)$imageSettings['label']); ?>"
                                                    class="img-fluid"
                                                >
                                            </div>
                                        <?php endif; ?>

                                        <label for="<?php echo escapeHtml((string)$imageSettings['input_name']); ?>" class="form-label">
                                            Upload Replacement Image
                                        </label>

                                        <input
                                            type="file"
                                            class="form-control"
                                            id="<?php echo escapeHtml((string)$imageSettings['input_name']); ?>"
                                            name="<?php echo escapeHtml((string)$imageSettings['input_name']); ?>"
                                            accept="<?php echo escapeHtml((string)($imageSettings['accept'] ?? '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp')); ?>"
                                        >

                                        <div class="admin-help-text mt-3">
                                            <p class="fw-bold mb-2">Image Requirements</p>
                                            <ul class="mb-0">
                                                <?php foreach ($imageSettings['requirements'] as $requirement): ?>
                                                    <li><?php echo escapeHtml((string)$requirement); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section id="admin-theme" class="card admin-section p-4 mb-4">
                        <h2 class="h4 mb-2">Theme Colors</h2>
                        <p class="text-muted">
                            These labels describe what the color controls, not what the color must be.
                            For example, the primary action color can be yellow, orange, blue, or anything else that fits the brand.
                        </p>

                        <div class="row g-3">
                            <?php foreach ($themeRows as $key => $row): ?>
                                <?php $textInputId = 'theme_text_' . (string)$key; ?>

                                <div class="col-md-6 col-lg-4">
                                    <label for="theme_<?php echo escapeHtml((string)$key); ?>" class="form-label">
                                        <?php echo escapeHtml((string)$row['label']); ?>
                                        <?php echo adminHelpIcon(themeColorHelpText((string)$key)); ?>
                                    </label>

                                    <div class="input-group">
                                        <input
                                            type="color"
                                            class="form-control form-control-color"
                                            id="theme_<?php echo escapeHtml((string)$key); ?>"
                                            name="theme_colors[<?php echo escapeHtml((string)$key); ?>]"
                                            value="<?php echo escapeHtml((string)$row['value']); ?>"
                                            data-color-preview-target="<?php echo escapeHtml($textInputId); ?>"
                                        >
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="<?php echo escapeHtml($textInputId); ?>"
                                            value="<?php echo escapeHtml((string)$row['value']); ?>"
                                            readonly
                                        >
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <?php foreach (getAdminContentGroups() as $groupTitle => $group): ?>
                        <section id="<?php echo escapeHtml((string)$group['id']); ?>" class="card admin-section p-4 mb-4">
                            <h2 class="h4 mb-2"><?php echo escapeHtml((string)$groupTitle); ?></h2>

                            <p class="text-muted">
                                <?php echo escapeHtml((string)$group['description']); ?>
                            </p>

                            <div class="row g-3">
                                <?php foreach ($group['keys'] as $key): ?>
                                    <?php
                                    if (!isset($contentRows[$key])) {
                                        continue;
                                    }

                                    $row = $contentRows[$key];

                                    if (($row['type'] ?? '') === 'image_path') {
                                        continue;
                                    }
                                    ?>

                                    <div class="col-12">
                                        <label for="content_<?php echo escapeHtml((string)$key); ?>" class="form-label">
                                            <?php echo escapeHtml((string)$row['label']); ?>
                                            <?php echo adminHelpIcon(siteContentHelpText((string)$key)); ?>
                                        </label>

                                        <?php if (($row['type'] ?? '') === 'richtext'): ?>
                                            <?php
                                            echo adminRichTextEditor(
                                                'content_' . (string)$key,
                                                'content[' . (string)$key . ']',
                                                (string)$row['value'],
                                                siteContentHelpText((string)$key)
                                            );
                                            ?>
                                        <?php elseif (($row['type'] ?? '') === 'textarea'): ?>
                                            <textarea
                                                class="form-control"
                                                id="content_<?php echo escapeHtml((string)$key); ?>"
                                                name="content[<?php echo escapeHtml((string)$key); ?>]"
                                                rows="3"
                                            ><?php echo escapeHtml((string)$row['value']); ?></textarea>

                                            <div class="admin-help-text">
                                                <?php echo escapeHtml(siteContentHelpText((string)$key)); ?>
                                            </div>
                                        <?php else: ?>
                                            <input
                                                type="text"
                                                class="form-control"
                                                id="content_<?php echo escapeHtml((string)$key); ?>"
                                                name="content[<?php echo escapeHtml((string)$key); ?>]"
                                                value="<?php echo escapeHtml((string)$row['value']); ?>"
                                            >

                                            <div class="admin-help-text">
                                                <?php echo escapeHtml(siteContentHelpText((string)$key)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (!empty($group['buttons'])): ?>
                                    <?php foreach ($group['buttons'] as $buttonKey): ?>
                                        <?php
                                        if (!isset($buttonRows[$buttonKey])) {
                                            continue;
                                        }

                                        $buttonRow = $buttonRows[$buttonKey];
                                        ?>

                                        <div class="col-12">
                                            <div class="admin-inner-panel">
                                                <h3 class="h5">
                                                    <?php echo escapeHtml((string)$buttonRow['label']); ?>
                                                    <?php echo adminHelpIcon((string)$buttonRow['help']); ?>
                                                </h3>

                                                <p class="admin-help-text">
                                                    <?php echo escapeHtml((string)$buttonRow['help']); ?>
                                                </p>

                                                <div class="row g-3">
                                                    <div class="col-md-5">
                                                        <label for="button_<?php echo escapeHtml((string)$buttonKey); ?>_text" class="form-label">
                                                            Button Text
                                                            <?php echo adminHelpIcon('This is the text visitors see on the button. Make sure it matches where the button sends them.'); ?>
                                                        </label>
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            id="button_<?php echo escapeHtml((string)$buttonKey); ?>_text"
                                                            name="buttons[<?php echo escapeHtml((string)$buttonKey); ?>][text]"
                                                            value="<?php echo escapeHtml((string)$buttonRow['text']); ?>"
                                                        >
                                                        <div class="admin-help-text">
                                                            Example: Request a Quote, View Services, Call Now, or View My Work.
                                                        </div>
                                                    </div>

                                                    <div class="col-md-7">
                                                        <label for="button_<?php echo escapeHtml((string)$buttonKey); ?>_href" class="form-label">
                                                            Button Destination
                                                            <?php echo adminHelpIcon(buttonDestinationHelpText()); ?>
                                                        </label>
                                                        <input
                                                            type="text"
                                                            class="form-control"
                                                            id="button_<?php echo escapeHtml((string)$buttonKey); ?>_href"
                                                            name="buttons[<?php echo escapeHtml((string)$buttonKey); ?>][href]"
                                                            value="<?php echo escapeHtml((string)$buttonRow['href']); ?>"
                                                        >
                                                        <div class="admin-help-text">
                                                            <?php echo escapeHtml(buttonDestinationHelpText()); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>

                    <section id="admin-service-cards" class="card admin-section p-4 mb-4">
                        <h2 class="h4 mb-2">Service Cards</h2>
                        <p class="text-muted">
                            Add, edit, hide, remove, and reorder service cards. The homepage shows active services in sort order.
                            You can have a maximum of 9 services. Service images are optional.
                        </p>

                        <?php foreach ($serviceRows as $service): ?>
                            <?php
                            $serviceId = (int)$service['id'];
                            $serviceImagePath = (string)($service['service_image_path'] ?? '');
                            $serviceImagePreviewPath = $serviceImagePath;

                            if ($serviceImagePreviewPath !== '') {
                                $serviceImagePreviewPath .= (strpos($serviceImagePreviewPath, '?') === false ? '?' : '&') . 'v=' . time();
                            }

                            $serviceImageSettings = getServiceImageUploadSettings($serviceId);
                            ?>

                            <div class="service-admin-card admin-inner-panel mb-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                    <h3 class="h5 mb-0">
                                        Service #<?php echo escapeHtml((string)$serviceId); ?>:
                                        <?php echo escapeHtml((string)$service['service_title']); ?>
                                    </h3>

                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="delete_service_<?php echo escapeHtml((string)$serviceId); ?>"
                                            name="delete_services[<?php echo escapeHtml((string)$serviceId); ?>]"
                                            value="1"
                                        >
                                        <label class="form-check-label" for="delete_service_<?php echo escapeHtml((string)$serviceId); ?>">
                                            Remove this service
                                        </label>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label for="service_title_<?php echo escapeHtml((string)$serviceId); ?>" class="form-label">
                                            Service Title
                                            <?php echo adminHelpIcon('This is the name shown at the top of the service card. Example: Yard Clean Up, Residential Mowing, Power Washing.'); ?>
                                        </label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="service_title_<?php echo escapeHtml((string)$serviceId); ?>"
                                            name="services[<?php echo escapeHtml((string)$serviceId); ?>][service_title]"
                                            value="<?php echo escapeHtml((string)$service['service_title']); ?>"
                                        >
                                    </div>

                                    <div class="col-md-4">
                                        <label for="service_sort_<?php echo escapeHtml((string)$serviceId); ?>" class="form-label">
                                            Sort Order
                                            <?php echo adminHelpIcon('Lower numbers appear first. Use 10, 20, 30, etc. so you can insert services between them later.'); ?>
                                        </label>
                                        <input
                                            type="number"
                                            class="form-control"
                                            id="service_sort_<?php echo escapeHtml((string)$serviceId); ?>"
                                            name="services[<?php echo escapeHtml((string)$serviceId); ?>][sort_order]"
                                            value="<?php echo escapeHtml((string)$service['sort_order']); ?>"
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label for="service_description_<?php echo escapeHtml((string)$serviceId); ?>" class="form-label">
                                            Service Description
                                            <?php echo adminHelpIcon('Short description shown inside the service card. Explain what the customer gets. Basic formatting is allowed here.'); ?>
                                        </label>

                                        <?php
                                        echo adminRichTextEditor(
                                            'service_description_' . (string)$serviceId,
                                            'services[' . (string)$serviceId . '][service_description]',
                                            (string)$service['service_description'],
                                            'Description shown inside this service card. Use short paragraphs, bold text, or bullet points when helpful.'
                                        );
                                        ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="service_image_<?php echo escapeHtml((string)$serviceId); ?>" class="form-label">
                                            Optional Service Image
                                            <?php echo adminHelpIcon((string)$serviceImageSettings['help']); ?>
                                        </label>

                                        <?php if ($serviceImagePath !== ''): ?>
                                            <div class="admin-image-preview mb-3">
                                                <img
                                                    src="<?php echo escapeHtml($serviceImagePreviewPath); ?>"
                                                    alt="<?php echo escapeHtml((string)($service['service_image_alt'] ?? 'Service image')); ?>"
                                                    class="img-fluid"
                                                >
                                            </div>

                                            <div class="form-check mb-3">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    id="remove_service_image_<?php echo escapeHtml((string)$serviceId); ?>"
                                                    name="services[<?php echo escapeHtml((string)$serviceId); ?>][remove_image]"
                                                    value="1"
                                                >
                                                <label class="form-check-label" for="remove_service_image_<?php echo escapeHtml((string)$serviceId); ?>">
                                                    Remove current image
                                                </label>
                                            </div>
                                        <?php endif; ?>

                                        <input
                                            type="file"
                                            class="form-control"
                                            id="service_image_<?php echo escapeHtml((string)$serviceId); ?>"
                                            name="<?php echo escapeHtml((string)$serviceImageSettings['input_name']); ?>"
                                            accept="<?php echo escapeHtml((string)($serviceImageSettings['accept'] ?? '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp')); ?>"
                                        >

                                        <div class="admin-help-text mt-2">
                                            Recommended: 1200px × 800px. Landscape image. Max file size: 1MB.
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="service_image_alt_<?php echo escapeHtml((string)$serviceId); ?>" class="form-label">
                                            Service Image Description
                                            <?php echo adminHelpIcon('Short description of the service image for screen readers. Required when using an image. Example: "Freshly mowed backyard lawn."'); ?>
                                        </label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="service_image_alt_<?php echo escapeHtml((string)$serviceId); ?>"
                                            name="services[<?php echo escapeHtml((string)$serviceId); ?>][service_image_alt]"
                                            value="<?php echo escapeHtml((string)($service['service_image_alt'] ?? '')); ?>"
                                        >

                                        <div class="form-check mt-4">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="service_active_<?php echo escapeHtml((string)$serviceId); ?>"
                                                name="services[<?php echo escapeHtml((string)$serviceId); ?>][is_active]"
                                                value="1"
                                                <?php echo ((int)$service['is_active'] === 1) ? 'checked' : ''; ?>
                                            >
                                            <label class="form-check-label" for="service_active_<?php echo escapeHtml((string)$serviceId); ?>">
                                                Show this service on the homepage
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($serviceRows) < 9): ?>
                            <div class="admin-inner-panel">
                                <h3 class="h5">Add New Service</h3>
                                <p class="text-muted">
                                    Fill this out to add another service card. Leave it blank if you do not want to add one right now.
                                </p>

                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label for="new_service_title" class="form-label">New Service Title</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="new_service_title"
                                            name="new_service[service_title]"
                                        >
                                    </div>

                                    <div class="col-md-4">
                                        <label for="new_service_sort" class="form-label">Sort Order</label>
                                        <input
                                            type="number"
                                            class="form-control"
                                            id="new_service_sort"
                                            name="new_service[sort_order]"
                                            value="<?php echo escapeHtml((string)((count($serviceRows) + 1) * 10)); ?>"
                                        >
                                    </div>

                                    <div class="col-12">
                                        <label for="new_service_description" class="form-label">New Service Description</label>

                                        <?php
                                        echo adminRichTextEditor(
                                            'new_service_description',
                                            'new_service[service_description]',
                                            '',
                                            'Description shown inside the new service card. Use short paragraphs, bold text, or bullet points when helpful.'
                                        );
                                        ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="new_service_image" class="form-label">Optional Service Image</label>
                                        <input
                                            type="file"
                                            class="form-control"
                                            id="new_service_image"
                                            name="new_service_image"
                                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        >
                                        <div class="admin-help-text mt-2">
                                            Recommended: 1200px × 800px. Landscape image. Max file size: 1MB.
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="new_service_image_alt" class="form-label">New Service Image Description</label>
                                        <input
                                            type="text"
                                            class="form-control"
                                            id="new_service_image_alt"
                                            name="new_service[service_image_alt]"
                                        >

                                        <div class="form-check mt-4">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                id="new_service_active"
                                                name="new_service[is_active]"
                                                value="1"
                                                checked
                                            >
                                            <label class="form-check-label" for="new_service_active">
                                                Show this service on the homepage
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                You already have 9 services. Remove a service before adding another one.
                            </div>
                        <?php endif; ?>
                    </section>

                    <button type="submit" class="btn btn-light btn-lg">
                        Save Website Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInputs = document.querySelectorAll('input[type="file"]');

    fileInputs.forEach(function (fileInput) {
        fileInput.addEventListener('change', function () {
            const selectedFile = fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;

            if (!selectedFile) {
                return;
            }

            const panel = fileInput.closest('.admin-inner-panel') || fileInput.closest('.card');

            if (!panel) {
                return;
            }

            let previewBox = panel.querySelector('.admin-image-preview');

            if (!previewBox) {
                previewBox = document.createElement('div');
                previewBox.className = 'admin-image-preview mb-3';

                const label = panel.querySelector('label[for="' + fileInput.id + '"]');

                if (label) {
                    panel.insertBefore(previewBox, label);
                } else {
                    panel.insertBefore(previewBox, fileInput);
                }
            }

            let previewImage = previewBox.querySelector('img');

            if (!previewImage) {
                previewImage = document.createElement('img');
                previewImage.className = 'img-fluid';
                previewImage.alt = 'Selected image preview';
                previewBox.appendChild(previewImage);
            }

            const previewUrl = URL.createObjectURL(selectedFile);
            previewImage.src = previewUrl;

            let notice = panel.querySelector('[data-upload-preview-notice]');

            if (!notice) {
                notice = document.createElement('div');
                notice.className = 'alert alert-warning mt-3 mb-0';
                notice.setAttribute('data-upload-preview-notice', 'true');
                fileInput.insertAdjacentElement('afterend', notice);
            }

            notice.textContent = 'Previewing selected file: ' + selectedFile.name + '. This will not replace the live image until you click Save Website Settings.';
        });
    });
});
</script>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';