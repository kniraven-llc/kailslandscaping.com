<?php
declare(strict_types=1);

/*
    Admin System Check page.

    This page checks whether the planned CRM/request system pieces exist.

    It checks:
    - database connection
    - required database tables
    - required database columns
    - important request/document data health
    - required public PHP pages
    - required shared source files
    - unexpected public PHP files not in the approved route plan

    This page does not change the database.
    It is a safe read-only check page.

    Performance note:
    This version checks information_schema in bulk instead of running
    one database query for every table and every column.
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Admin System Check';

adminRequireLogin('Admin System Check Login');

$errors = [];
$checks = [];
$summary = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
];

function systemCheckAdd(array &$checks, string $group, string $label, string $status, string $details): void
{
    $checks[] = [
        'group' => $group,
        'label' => $label,
        'status' => $status,
        'details' => $details,
    ];
}

function systemCheckStatusClass(string $status): string
{
    return match ($status) {
        'pass' => 'system-check-status-pass',
        'warning' => 'system-check-status-warning',
        'fail' => 'system-check-status-fail',
        default => 'system-check-status-unknown',
    };
}

function systemCheckStatusLabel(string $status): string
{
    return match ($status) {
        'pass' => 'Pass',
        'warning' => 'Review',
        'fail' => 'Fix',
        default => 'Unknown',
    };
}

function systemCheckBindParams(mysqli_stmt $statement, string $types, array $params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $bindArgs = [];
    $bindArgs[] = $types;

    foreach ($params as $key => $value) {
        $bindArgs[] = &$params[$key];
    }

    call_user_func_array([$statement, 'bind_param'], $bindArgs);
}

function systemCheckGetDatabaseName(mysqli $connection): string
{
    $result = $connection->query('SELECT DATABASE() AS database_name');

    if (!$result) {
        return '';
    }

    $row = $result->fetch_assoc();

    return (string)($row['database_name'] ?? '');
}

function systemCheckBuildPlaceholders(int $count): string
{
    return implode(',', array_fill(0, $count, '?'));
}

function systemCheckFetchExistingTables(mysqli $connection, string $databaseName, array $tableNames): array
{
    if ($databaseName === '' || empty($tableNames)) {
        return [];
    }

    $placeholders = systemCheckBuildPlaceholders(count($tableNames));

    $statement = $connection->prepare(
        'SELECT TABLE_NAME
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME IN (' . $placeholders . ')'
    );

    $params = array_merge([$databaseName], array_values($tableNames));
    $types = 's' . str_repeat('s', count($tableNames));

    systemCheckBindParams($statement, $types, $params);
    $statement->execute();

    $result = $statement->get_result();
    $existingTables = [];

    while ($row = $result->fetch_assoc()) {
        $existingTables[(string)$row['TABLE_NAME']] = true;
    }

    return $existingTables;
}

function systemCheckFetchExistingColumns(mysqli $connection, string $databaseName, array $tableNames): array
{
    if ($databaseName === '' || empty($tableNames)) {
        return [];
    }

    $placeholders = systemCheckBuildPlaceholders(count($tableNames));

    $statement = $connection->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME IN (' . $placeholders . ')'
    );

    $params = array_merge([$databaseName], array_values($tableNames));
    $types = 's' . str_repeat('s', count($tableNames));

    systemCheckBindParams($statement, $types, $params);
    $statement->execute();

    $result = $statement->get_result();
    $existingColumns = [];

    while ($row = $result->fetch_assoc()) {
        $tableName = (string)$row['TABLE_NAME'];
        $columnName = (string)$row['COLUMN_NAME'];

        if (!isset($existingColumns[$tableName])) {
            $existingColumns[$tableName] = [];
        }

        $existingColumns[$tableName][$columnName] = true;
    }

    return $existingColumns;
}

function systemCheckTableExists(array $existingTables, string $tableName): bool
{
    return !empty($existingTables[$tableName]);
}

function systemCheckColumnExists(array $existingColumns, string $tableName, string $columnName): bool
{
    return !empty($existingColumns[$tableName][$columnName]);
}

function systemCheckHasColumns(array $existingTables, array $existingColumns, string $tableName, array $requiredColumns): bool
{
    if (!systemCheckTableExists($existingTables, $tableName)) {
        return false;
    }

    foreach ($requiredColumns as $columnName) {
        if (!systemCheckColumnExists($existingColumns, $tableName, $columnName)) {
            return false;
        }
    }

    return true;
}

function systemCheckFetchOneRow(mysqli $connection, string $sql): array
{
    $result = $connection->query($sql);

    if (!$result) {
        return [];
    }

    $row = $result->fetch_assoc();

    if (!is_array($row)) {
        return [];
    }

    return $row;
}

function systemCheckFileStatus(string $filePath): bool
{
    return file_exists($filePath) && is_file($filePath);
}

function systemCheckFetchPublicPhpFiles(string $publicDirectory): array
{
    $publicDirectory = rtrim($publicDirectory, '/\\');
    $paths = glob($publicDirectory . '/*.php');

    if ($paths === false) {
        return [];
    }

    $fileNames = [];

    foreach ($paths as $path) {
        if (is_file($path)) {
            $fileNames[] = basename($path);
        }
    }

    sort($fileNames, SORT_NATURAL | SORT_FLAG_CASE);

    return $fileNames;
}

try {
    $connection = getDatabaseConnection();
    $databaseName = systemCheckGetDatabaseName($connection);

    if ($databaseName === '') {
        $errors[] = 'Could not detect the active database name.';
    } else {
        systemCheckAdd(
            $checks,
            'Database',
            'Active database',
            'pass',
            'Connected to database: ' . $databaseName
        );
    }

    $requiredTables = [
        'clients',
        'quote_requests',
        'quote_request_items',
        'client_documents',
        'client_document_items',
        'services',
        'site_content',
        'site_buttons',
        'theme_colors',
        'request_status_rules',
        'quote_request_comments',
    ];

    $requiredColumns = [
        'clients' => [
            'id',
            'full_name',
            'phone',
            'phone_normalized',
            'email',
            'street_address',
            'city',
            'state',
            'zip_code',
            'notes',
            'preferred_contact_method',
            'is_active',
            'created_at',
            'updated_at',
        ],
        'quote_requests' => [
            'id',
            'request_number',
            'public_access_token',
            'client_id',
            'request_status',
            'request_source',
            'requested_service_id',
            'custom_service_name',
            'project_details',
            'public_notes',
            'internal_notes',
            'property_address',
            'property_city',
            'property_state',
            'property_zip_code',
            'preferred_contact_method',
            'scheduled_start',
            'scheduled_end',
            'completed_at',
            'quoted_price',
            'estimated_cost',
            'final_price',
            'next_action',
            'next_action_due_at',
            'next_action_notes',
            'last_admin_reviewed_at',
            'last_queue_action_at',
            'is_archived',
            'created_at',
            'updated_at',
        ],
        'quote_request_items' => [
            'id',
            'quote_request_id',
            'item_type',
            'item_description',
            'quantity',
            'unit_name',
            'unit_price',
            'line_total',
            'unit_cost',
            'total_cost',
            'sort_order',
            'created_at',
            'updated_at',
        ],
        'client_documents' => [
            'id',
            'document_type',
            'document_number',
            'client_id',
            'quote_request_id',
            'document_status',
            'payment_method',
            'issue_date',
            'due_date',
            'paid_date',
            'business_name',
            'business_phone',
            'business_email',
            'business_website',
            'client_name',
            'client_phone',
            'client_email',
            'client_street_address',
            'client_city',
            'client_state',
            'client_zip_code',
            'document_title',
            'service_summary',
            'subtotal_amount',
            'discount_amount',
            'tax_rate',
            'tax_amount',
            'total_amount',
            'amount_paid',
            'balance_due',
            'public_notes',
            'internal_notes',
            'footer_note',
            'payment_note',
            'created_at',
            'updated_at',
        ],
        'client_document_items' => [
            'id',
            'document_id',
            'item_description',
            'quantity',
            'unit_name',
            'unit_price',
            'line_total',
            'sort_order',
            'created_at',
            'updated_at',
        ],
        'request_status_rules' => [
            'id',
            'status_name',
            'status_label',
            'status_description',
            'next_step_help',
            'overdue_after_days',
            'sort_order',
            'is_active',
            'track_overdue',
        ],
        'quote_request_comments' => [
            'id',
            'quote_request_id',
            'author_type',
            'author_name',
            'visibility',
            'comment_text',
            'created_at',
        ],
    ];

    $allTablesToInspect = array_values(array_unique(array_merge(
        $requiredTables,
        array_keys($requiredColumns)
    )));

    $existingTables = [];
    $existingColumns = [];

    if ($databaseName !== '') {
        $existingTables = systemCheckFetchExistingTables($connection, $databaseName, $allTablesToInspect);
        $existingColumns = systemCheckFetchExistingColumns($connection, $databaseName, $allTablesToInspect);
    }

    foreach ($requiredTables as $tableName) {
        $tableExists = systemCheckTableExists($existingTables, $tableName);

        systemCheckAdd(
            $checks,
            'Database Tables',
            $tableName,
            $tableExists ? 'pass' : 'fail',
            $tableExists
                ? 'Table exists.'
                : 'Missing table. Run or review the related migration.'
        );
    }

    foreach ($requiredColumns as $tableName => $columnNames) {
        $tableExists = systemCheckTableExists($existingTables, $tableName);

        foreach ($columnNames as $columnName) {
            $columnExists = $tableExists && systemCheckColumnExists($existingColumns, $tableName, $columnName);

            systemCheckAdd(
                $checks,
                'Database Columns',
                $tableName . '.' . $columnName,
                $columnExists ? 'pass' : 'fail',
                $columnExists
                    ? 'Column exists.'
                    : 'Missing column. Run or review the related migration.'
            );
        }
    }

    if (systemCheckTableExists($existingTables, 'request_status_rules')) {
        if (systemCheckHasColumns($existingTables, $existingColumns, 'request_status_rules', ['is_active', 'track_overdue'])) {
            $statusRuleSummary = systemCheckFetchOneRow(
                $connection,
                'SELECT
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_status_count,
                    SUM(CASE WHEN is_active = 1 AND track_overdue = 1 THEN 1 ELSE 0 END) AS tracked_status_count
                 FROM request_status_rules'
            );

            $activeStatusCount = (int)($statusRuleSummary['active_status_count'] ?? 0);
            $trackedStatusCount = (int)($statusRuleSummary['tracked_status_count'] ?? 0);

            systemCheckAdd(
                $checks,
                'Status Rules',
                'Active request status rules',
                $activeStatusCount > 0 ? 'pass' : 'fail',
                $activeStatusCount > 0
                    ? 'Found ' . $activeStatusCount . ' active status rule(s).'
                    : 'No active status rules found.'
            );

            systemCheckAdd(
                $checks,
                'Status Rules',
                'Overdue tracking rules',
                $trackedStatusCount > 0 ? 'pass' : 'warning',
                $trackedStatusCount > 0
                    ? 'Found ' . $trackedStatusCount . ' status rule(s) that track overdue requests.'
                    : 'No statuses are currently set to track overdue requests.'
            );
        } else {
            systemCheckAdd(
                $checks,
                'Status Rules',
                'Status rule checks',
                'warning',
                'Skipped because one or more request_status_rules columns are missing.'
            );
        }
    }

    if (systemCheckHasColumns(
        $existingTables,
        $existingColumns,
        'quote_requests',
        [
            'request_number',
            'public_access_token',
            'next_action',
            'is_archived',
            'request_status',
        ]
    )) {
        $requestDataSummary = systemCheckFetchOneRow(
            $connection,
            'SELECT
                SUM(CASE
                    WHEN request_number IS NULL OR request_number = ""
                    THEN 1 ELSE 0
                END) AS missing_request_numbers,

                SUM(CASE
                    WHEN public_access_token IS NULL OR public_access_token = ""
                    THEN 1 ELSE 0
                END) AS missing_tokens,

                SUM(CASE
                    WHEN is_archived = 0
                     AND request_status NOT IN ("Completed", "Cancelled", "Archived")
                     AND (next_action IS NULL OR next_action = "")
                    THEN 1 ELSE 0
                END) AS missing_next_actions
             FROM quote_requests'
        );

        $missingRequestNumbers = (int)($requestDataSummary['missing_request_numbers'] ?? 0);
        $missingTokens = (int)($requestDataSummary['missing_tokens'] ?? 0);
        $missingQueueActions = (int)($requestDataSummary['missing_next_actions'] ?? 0);

        systemCheckAdd(
            $checks,
            'Request Data',
            'Requests missing request numbers',
            $missingRequestNumbers === 0 ? 'pass' : 'warning',
            $missingRequestNumbers === 0
                ? 'No requests are missing request numbers.'
                : $missingRequestNumbers . ' request(s) are missing request numbers.'
        );

        systemCheckAdd(
            $checks,
            'Request Data',
            'Requests missing public access tokens',
            $missingTokens === 0 ? 'pass' : 'warning',
            $missingTokens === 0
                ? 'No requests are missing customer access tokens.'
                : $missingTokens . ' request(s) are missing customer access tokens.'
        );

        systemCheckAdd(
            $checks,
            'Request Data',
            'Active requests missing next action',
            $missingQueueActions === 0 ? 'pass' : 'warning',
            $missingQueueActions === 0
                ? 'No active requests are missing a next action.'
                : $missingQueueActions . ' active request(s) are missing a next action.'
        );
    } else {
        systemCheckAdd(
            $checks,
            'Request Data',
            'Request data checks',
            'warning',
            'Skipped because one or more required quote_requests columns are missing.'
        );
    }

    if (systemCheckHasColumns(
        $existingTables,
        $existingColumns,
        'client_documents',
        ['document_type', 'document_number', 'quote_request_id']
    )) {
        $documentDataSummary = systemCheckFetchOneRow(
            $connection,
            'SELECT
                SUM(CASE
                    WHEN document_number IS NULL OR document_number = ""
                    THEN 1 ELSE 0
                END) AS missing_document_numbers,

                SUM(CASE
                    WHEN document_type = "receipt"
                    THEN 1 ELSE 0
                END) AS old_receipt_type_count,

                SUM(CASE
                    WHEN quote_request_id IS NULL OR quote_request_id = 0
                    THEN 1 ELSE 0
                END) AS unlinked_document_count
             FROM client_documents'
        );

        $missingDocumentNumbers = (int)($documentDataSummary['missing_document_numbers'] ?? 0);
        $oldReceiptTypeCount = (int)($documentDataSummary['old_receipt_type_count'] ?? 0);
        $unlinkedDocumentCount = (int)($documentDataSummary['unlinked_document_count'] ?? 0);

        systemCheckAdd(
            $checks,
            'Document Data',
            'Documents missing document numbers',
            $missingDocumentNumbers === 0 ? 'pass' : 'warning',
            $missingDocumentNumbers === 0
                ? 'No documents are missing document numbers.'
                : $missingDocumentNumbers . ' document(s) are missing document numbers.'
        );

        systemCheckAdd(
            $checks,
            'Document Data',
            'Documents still using old receipt type',
            $oldReceiptTypeCount === 0 ? 'pass' : 'warning',
            $oldReceiptTypeCount === 0
                ? 'No documents are using the old receipt document type.'
                : $oldReceiptTypeCount . ' document(s) still use document_type = receipt. New workflow should use invoice.'
        );

        systemCheckAdd(
            $checks,
            'Document Data',
            'Documents not linked to a request',
            $unlinkedDocumentCount === 0 ? 'pass' : 'warning',
            $unlinkedDocumentCount === 0
                ? 'All documents are linked to requests.'
                : $unlinkedDocumentCount . ' document(s) are not linked to a request.'
        );
    } else {
        systemCheckAdd(
            $checks,
            'Document Data',
            'Document data checks',
            'warning',
            'Skipped because one or more required client_documents columns are missing.'
        );
    }

    $requiredPublicFiles = [
        'index.php' => 'Public homepage and request intake entry point',
        'request-confirmation.php' => 'Customer request confirmation page',
        'request-update.php' => 'Customer-facing request status and comment page',

        'admin.php' => 'Main admin attention dashboard',
        'admin_clients.php' => 'Client list and search page',
        'admin_client_detail.php' => 'Single client detail page',
        'admin_client_edit.php' => 'Single client edit page',
        'admin_requests.php' => 'Request/job list page',
        'admin_request_detail.php' => 'Single request detail, comments, customer link, and document hub',
        'admin_request_edit.php' => 'Single request edit page',
        'admin_document_edit.php' => 'Quote/invoice create and edit page',
        'document_print.php' => 'Quote/invoice print page',

        'business_cards.php' => 'Business card generator',
        'admin_website.php' => 'Website content and theme editor',
        'admin_account.php' => 'Admin account settings page',
        'admin_system_check.php' => 'System check page',
    ];

    foreach ($requiredPublicFiles as $fileName => $fileDescription) {
        $filePath = PROJECT_ROOT . '/public/' . $fileName;
        $fileExists = systemCheckFileStatus($filePath);

        systemCheckAdd(
            $checks,
            'Required Public PHP Files',
            $fileName,
            $fileExists ? 'pass' : 'fail',
            $fileExists
                ? $fileDescription . ' exists.'
                : $fileDescription . ' is missing from /public.'
        );
    }

    $optionalPublicFiles = [
        'admin_request_new.php' => 'Optional separate manual request intake page',
        'admin_request_status_settings.php' => 'Optional request status rule settings page',
    ];

    foreach ($optionalPublicFiles as $fileName => $fileDescription) {
        $filePath = PROJECT_ROOT . '/public/' . $fileName;
        $fileExists = systemCheckFileStatus($filePath);

        systemCheckAdd(
            $checks,
            'Optional Public PHP Files',
            $fileName,
            $fileExists ? 'pass' : 'warning',
            $fileExists
                ? $fileDescription . ' exists.'
                : $fileDescription . ' is not present. This is okay if the feature was folded into another planned page.'
        );
    }

    $approvedPublicFiles = array_values(array_unique(array_merge(
        array_keys($requiredPublicFiles),
        array_keys($optionalPublicFiles)
    )));

    $publicPhpFiles = systemCheckFetchPublicPhpFiles(PROJECT_ROOT . '/public');
    $unexpectedPublicFiles = array_values(array_diff($publicPhpFiles, $approvedPublicFiles));

    systemCheckAdd(
        $checks,
        'Public Directory Scan',
        'Unexpected public PHP files',
        empty($unexpectedPublicFiles) ? 'pass' : 'warning',
        empty($unexpectedPublicFiles)
            ? 'No extra PHP files found in /public outside the approved route plan.'
            : 'Review these extra PHP files in /public: ' . implode(', ', $unexpectedPublicFiles)
    );

    $requiredSourceFiles = [
        'src/Admin/admin_auth.php' => 'Admin authentication helper',
        'src/Admin/admin_crm_navigation.php' => 'Shared CRM admin navigation partial',
        'src/Content/site_content.php' => 'Site content helper',
        'src/Database/database_connection.php' => 'Database connection helper',
        'src/Layout/head.php' => 'Shared page head',
        'src/Layout/navigation.php' => 'Shared site navigation',
        'src/Layout/footer.php' => 'Shared page footer',
        'src/Session/session.php' => 'Shared session helper',
    ];

    foreach ($requiredSourceFiles as $fileName => $fileDescription) {
        $filePath = PROJECT_ROOT . '/' . $fileName;
        $fileExists = systemCheckFileStatus($filePath);

        systemCheckAdd(
            $checks,
            'Required Shared Source Files',
            $fileName,
            $fileExists ? 'pass' : 'fail',
            $fileExists
                ? $fileDescription . ' exists.'
                : $fileDescription . ' is missing.'
        );
    }
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'The system check could not be completed.';
    }
}

foreach ($checks as $check) {
    if ($check['status'] === 'pass') {
        $summary['passed']++;
    } elseif ($check['status'] === 'warning') {
        $summary['warnings']++;
    } elseif ($check['status'] === 'fail') {
        $summary['failed']++;
    }
}

$checksByGroup = [];

foreach ($checks as $check) {
    $groupName = (string)$check['group'];

    if (!isset($checksByGroup[$groupName])) {
        $checksByGroup[$groupName] = [];
    }

    $checksByGroup[$groupName][] = $check;
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<style>
    .system-check-page {
        --system-check-page-bg: var(--kails-background-color, var(--kails-bg-black, #060707));
        --system-check-card-bg: var(--kails-card-bg, rgba(255, 255, 255, 0.035));
        --system-check-card-bg-strong: var(--kails-card-bg-strong, rgba(255, 255, 255, 0.055));
        --system-check-text: var(--kails-text-color, var(--kails-white-text, #f0f2ed));
        --system-check-muted: var(--kails-muted-text-color, rgba(240, 242, 237, 0.78));
        --system-check-accent: var(--kails-primary-color, var(--kails-primary-yellow, #f2cc16));
        --system-check-accent-2: var(--kails-secondary-color, var(--kails-primary-green, #97b62a));
        --system-check-border: var(--kails-border-color, rgba(242, 204, 22, 0.55));
        --system-check-pass: var(--kails-success-color, #2fb36d);
        --system-check-warning: var(--kails-warning-color, var(--kails-primary-yellow, #f2cc16));
        --system-check-fail: var(--kails-danger-color, #ff4d5e);

        color: var(--system-check-text);
    }

    .system-check-page h1,
    .system-check-page h2,
    .system-check-page h3,
    .system-check-page p,
    .system-check-page td,
    .system-check-page th,
    .system-check-page strong,
    .system-check-page span,
    .system-check-page div {
        color: inherit;
    }

    .system-check-muted,
    .system-check-page .text-muted,
    .system-check-summary-label,
    .system-check-summary-help,
    .system-check-help-card p,
    .system-check-table td:nth-child(3) {
        color: var(--system-check-muted) !important;
    }

    .system-check-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(160px, 1fr));
        gap: 1rem;
    }

    .system-check-summary-card,
    .system-check-section-card,
    .system-check-help-card {
        border: 1px solid var(--system-check-border);
        background:
            radial-gradient(circle at top right, rgba(242, 204, 22, 0.08), transparent 38%),
            var(--system-check-card-bg);
        color: var(--system-check-text);
        border-radius: 0.75rem;
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.25);
    }

    .system-check-summary-card {
        padding: 1.25rem;
        min-height: 8rem;
    }

    .system-check-summary-label {
        font-size: 0.9rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .system-check-summary-number {
        color: var(--system-check-text);
        font-size: 2.4rem;
        font-weight: 900;
        line-height: 1.1;
        text-shadow: 0 2px 0 rgba(0, 0, 0, 0.2);
    }

    .system-check-summary-pass {
        border-color: var(--system-check-pass);
    }

    .system-check-summary-pass .system-check-summary-label {
        color: var(--system-check-pass) !important;
    }

    .system-check-summary-warning {
        border-color: var(--system-check-warning);
    }

    .system-check-summary-warning .system-check-summary-label {
        color: var(--system-check-warning) !important;
    }

    .system-check-summary-fail {
        border-color: var(--system-check-fail);
    }

    .system-check-summary-fail .system-check-summary-label {
        color: var(--system-check-fail) !important;
    }

    .system-check-help-card {
        padding: 1rem;
    }

    .system-check-help-card h2 {
        color: var(--system-check-accent);
    }

    .system-check-section-card {
        overflow: hidden;
    }

    .system-check-section-header {
        border-bottom: 1px solid var(--system-check-border);
        background:
            linear-gradient(90deg, rgba(242, 204, 22, 0.08), transparent),
            var(--system-check-card-bg-strong);
        padding: 1rem;
    }

    .system-check-section-header h2 {
        color: var(--system-check-text);
    }

    .system-check-table {
        width: 100%;
        border-collapse: collapse;
        color: var(--system-check-text);
    }

    .system-check-table th,
    .system-check-table td {
        border-bottom: 1px solid rgba(240, 242, 237, 0.14);
        padding: 0.8rem 1rem;
        vertical-align: top;
    }

    .system-check-table th {
        background: rgba(0, 0, 0, 0.18);
        color: var(--system-check-accent) !important;
        font-size: 0.82rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .system-check-table tr:nth-child(even) td {
        background: rgba(255, 255, 255, 0.025);
    }

    .system-check-table tr:hover td {
        background: rgba(242, 204, 22, 0.075);
    }

    .system-check-table tr:last-child td {
        border-bottom: 0;
    }

    .system-check-status-pill {
        border: 1px solid currentColor;
        border-radius: 999px;
        display: inline-block;
        font-size: 0.8rem;
        font-weight: 900;
        min-width: 4.75rem;
        padding: 0.25rem 0.6rem;
        text-align: center;
    }

    .system-check-status-pass {
        background: rgba(47, 179, 109, 0.16);
        color: var(--system-check-pass) !important;
    }

    .system-check-status-warning {
        background: rgba(242, 204, 22, 0.16);
        color: var(--system-check-warning) !important;
    }

    .system-check-status-fail {
        background: rgba(255, 77, 94, 0.16);
        color: var(--system-check-fail) !important;
    }

    .system-check-status-unknown {
        background: rgba(255, 255, 255, 0.12);
        color: var(--system-check-muted) !important;
    }

    .system-check-link-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .system-check-link-grid .btn {
        border-color: var(--system-check-border);
    }

    .system-check-link-grid .btn-light {
        background: var(--system-check-accent);
        border-color: var(--system-check-accent);
        color: #111111;
        font-weight: 900;
    }

    .system-check-link-grid .btn-outline-light {
        color: var(--system-check-accent);
        border-color: var(--system-check-accent);
        font-weight: 800;
    }

    .system-check-link-grid .btn-outline-light:hover {
        background: var(--system-check-accent);
        color: #111111;
    }

    @media (max-width: 767.98px) {
        .system-check-summary-grid {
            grid-template-columns: 1fr;
        }

        .system-check-table th:nth-child(3),
        .system-check-table td:nth-child(3) {
            display: none;
        }
    }
</style>

<main class="system-check-page">
    <section class="site-section">
        <div class="container">
            <?php adminRenderSecurityWarning(); ?>
            <?php renderAdminCrmNavigation('system_check'); ?>

            <div class="mb-4">
                <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                    Admin System Check
                </p>

                <h1 class="fw-bold mb-2">
                    CRM Setup Check
                </h1>

                <p class="lead system-check-muted mb-0">
                    Check whether the current planned admin, request, customer, document, and website tools are installed correctly.
                </p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h2 class="h5 mb-2">System Check Error</h2>

                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo escapeHtml($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="system-check-summary-grid mb-4">
                <div class="system-check-summary-card system-check-summary-pass">
                    <div class="system-check-summary-label">
                        Passed
                    </div>

                    <div class="system-check-summary-number">
                        <?php echo escapeHtml((string)$summary['passed']); ?>
                    </div>

                    <div class="system-check-summary-help">
                        Items found and ready.
                    </div>
                </div>

                <div class="system-check-summary-card system-check-summary-warning">
                    <div class="system-check-summary-label">
                        Review
                    </div>

                    <div class="system-check-summary-number">
                        <?php echo escapeHtml((string)$summary['warnings']); ?>
                    </div>

                    <div class="system-check-summary-help">
                        Works, but should be reviewed.
                    </div>
                </div>

                <div class="system-check-summary-card system-check-summary-fail">
                    <div class="system-check-summary-label">
                        Fix
                    </div>

                    <div class="system-check-summary-number">
                        <?php echo escapeHtml((string)$summary['failed']); ?>
                    </div>

                    <div class="system-check-summary-help">
                        Missing required setup.
                    </div>
                </div>
            </div>

            <div class="system-check-help-card mb-4">
                <h2 class="h5 mb-2">
                    How to Use This Page
                </h2>

                <p class="mb-2">
                    Green means the item is ready. Yellow means the system can still work, but the item should be reviewed. Red means something required is missing.
                </p>

                <p class="mb-0">
                    This page is read-only. It does not repair data or change the database.
                </p>
            </div>

            <?php foreach ($checksByGroup as $groupName => $groupChecks): ?>
                <section class="system-check-section-card mb-4">
                    <div class="system-check-section-header">
                        <h2 class="h4 mb-0">
                            <?php echo escapeHtml($groupName); ?>
                        </h2>
                    </div>

                    <div class="table-responsive">
                        <table class="system-check-table">
                            <thead>
                                <tr>
                                    <th style="width: 130px;">Status</th>
                                    <th>Item</th>
                                    <th>Details</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($groupChecks as $check): ?>
                                    <tr>
                                        <td>
                                            <span class="system-check-status-pill <?php echo escapeHtml(systemCheckStatusClass((string)$check['status'])); ?>">
                                                <?php echo escapeHtml(systemCheckStatusLabel((string)$check['status'])); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <strong><?php echo escapeHtml((string)$check['label']); ?></strong>
                                        </td>

                                        <td>
                                            <?php echo escapeHtml((string)$check['details']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>

            <section class="system-check-section-card">
                <div class="system-check-section-header">
                    <h2 class="h4 mb-0">
                        Useful Admin Links
                    </h2>
                </div>

                <div class="p-3">
                    <div class="system-check-link-grid">
                        <a href="/admin.php" class="btn btn-light">
                            Main Admin
                        </a>

                        <a href="/admin_clients.php" class="btn btn-outline-light">
                            Clients
                        </a>

                        <a href="/admin_requests.php" class="btn btn-outline-light">
                            Requests
                        </a>

                        <a href="/business_cards.php" class="btn btn-outline-light">
                            Business Cards
                        </a>

                        <a href="/admin_website.php" class="btn btn-outline-light">
                            Website Editor
                        </a>

                        <a href="/admin_account.php" class="btn btn-outline-light">
                            Account
                        </a>

                        <a href="/admin_system_check.php" class="btn btn-outline-light">
                            System Check
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </section>
</main>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';