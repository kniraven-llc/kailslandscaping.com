<?php
declare(strict_types=1);

/*
    Request Status Settings page.

    The request workflow steps are fixed by the application:
    - New
    - Quoted
    - Scheduled
    - In Progress
    - Completed
    - Cancelled
    - Archived

    This page only allows the admin to update response timing
    for statuses where a day-based response rule makes sense.

    Editable:
    - response days for New
    - response days for Quoted
    - response days for In Progress

    Read-only display:
    - what each workflow step means
    - what the usual next step is
    - whether the status uses response timing, scheduled timing, or final-status behavior

    Locked:
    - status names
    - display labels
    - status meanings
    - next-step text
    - workflow order
    - active/final behavior
    - Scheduled timing
    - Completed/Cancelled/Archived final behavior
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Request Status Settings';

adminRequireLogin('Request Status Settings Login');

$pageErrors = [];
$pageMessages = [];
$statusRules = [];

function requestStatusSettingsBindParams(mysqli_stmt $statement, string $types, array $params): void
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

function requestStatusSettingsTableExists(mysqli $connection, string $tableName): bool
{
    $statement = $connection->prepare(
        'SELECT COUNT(*) AS table_count
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1'
    );

    $statement->bind_param('s', $tableName);
    $statement->execute();

    $result = $statement->get_result();
    $row = $result->fetch_assoc();

    return (int)($row['table_count'] ?? 0) > 0;
}

function requestStatusSettingsCanonicalStatusNames(): array
{
    return [
        'New',
        'Quoted',
        'Scheduled',
        'In Progress',
        'Completed',
        'Cancelled',
        'Archived',
    ];
}

function requestStatusSettingsDayBasedStatusNames(): array
{
    return [
        'New',
        'Quoted',
        'In Progress',
    ];
}

function requestStatusSettingsIsDayBasedStatus(string $statusName): bool
{
    return in_array($statusName, requestStatusSettingsDayBasedStatusNames(), true);
}

function requestStatusSettingsIsScheduledStatus(string $statusName): bool
{
    return $statusName === 'Scheduled';
}

function requestStatusSettingsIsTerminalStatus(string $statusName): bool
{
    return in_array($statusName, ['Completed', 'Cancelled', 'Archived'], true);
}

function requestStatusSettingsStatusTypeLabel(string $statusName): string
{
    if (requestStatusSettingsIsDayBasedStatus($statusName)) {
        return 'Response Timer';
    }

    if (requestStatusSettingsIsScheduledStatus($statusName)) {
        return 'Scheduled Timing';
    }

    if (requestStatusSettingsIsTerminalStatus($statusName)) {
        return 'Final Status';
    }

    return 'Fixed Step';
}

function requestStatusSettingsStatusTypeHelp(string $statusName): string
{
    if (requestStatusSettingsIsDayBasedStatus($statusName)) {
        return 'This status uses the Days to Respond setting below.';
    }

    if (requestStatusSettingsIsScheduledStatus($statusName)) {
        return 'This status is controlled by the scheduled work date and time.';
    }

    if (requestStatusSettingsIsTerminalStatus($statusName)) {
        return 'This status ends active work and does not need a response timer.';
    }

    return 'This status is part of the fixed workflow.';
}

function requestStatusSettingsDefaultMeaning(string $statusName): string
{
    return match ($statusName) {
        'New' => 'A request came in and has not been turned into a quote, schedule, cancellation, or archive yet.',
        'Quoted' => 'The customer has been given a price or estimate, but the job is not scheduled yet.',
        'Scheduled' => 'The work is on the schedule.',
        'In Progress' => 'The work has started but is not finished.',
        'Completed' => 'The work is finished.',
        'Cancelled' => 'The customer cancelled, declined, or the job will not happen.',
        'Archived' => 'The request is old or inactive and hidden from normal active lists.',
        default => 'This is a fixed request workflow step.',
    };
}

function requestStatusSettingsDefaultNextStep(string $statusName): string
{
    return match ($statusName) {
        'New' => 'Review the request, contact the customer, gather details, and quote if possible.',
        'Quoted' => 'Follow up until the customer schedules the work, declines, or cancels.',
        'Scheduled' => 'Complete the work at the scheduled time, then move it to Completed.',
        'In Progress' => 'Finish the work or update the request if something is blocking completion.',
        'Completed' => 'Create the invoice, record payment, and archive later if desired.',
        'Cancelled' => 'No active work is needed unless the customer reaches out again.',
        'Archived' => 'No active work is needed.',
        default => 'Review the request and decide the next action.',
    };
}

function getDefaultRequestStatusRules(): array
{
    return [
        [
            'status_name' => 'New',
            'status_label' => 'New',
            'status_description' => requestStatusSettingsDefaultMeaning('New'),
            'next_step_help' => requestStatusSettingsDefaultNextStep('New'),
            'overdue_after_days' => 1,
            'sort_order' => 10,
            'is_active' => 1,
            'track_overdue' => 1,
        ],
        [
            'status_name' => 'Quoted',
            'status_label' => 'Quoted',
            'status_description' => requestStatusSettingsDefaultMeaning('Quoted'),
            'next_step_help' => requestStatusSettingsDefaultNextStep('Quoted'),
            'overdue_after_days' => 3,
            'sort_order' => 20,
            'is_active' => 1,
            'track_overdue' => 1,
        ],
        [
            'status_name' => 'Scheduled',
            'status_label' => 'Scheduled',
            'status_description' => requestStatusSettingsDefaultMeaning('Scheduled'),
            'next_step_help' => requestStatusSettingsDefaultNextStep('Scheduled'),
            'overdue_after_days' => null,
            'sort_order' => 30,
            'is_active' => 1,
            'track_overdue' => 1,
        ],
        [
            'status_name' => 'In Progress',
            'status_label' => 'In Progress',
            'status_description' => requestStatusSettingsDefaultMeaning('In Progress'),
            'next_step_help' => requestStatusSettingsDefaultNextStep('In Progress'),
            'overdue_after_days' => 1,
            'sort_order' => 40,
            'is_active' => 1,
            'track_overdue' => 1,
        ],
        [
            'status_name' => 'Completed',
            'status_label' => 'Completed',
            'status_description' => requestStatusSettingsDefaultMeaning('Completed'),
            'next_step_help' => requestStatusSettingsDefaultNextStep('Completed'),
            'overdue_after_days' => null,
            'sort_order' => 50,
            'is_active' => 1,
            'track_overdue' => 0,
        ],
        [
            'status_name' => 'Cancelled',
            'status_label' => 'Cancelled',
            'status_description' => requestStatusSettingsDefaultMeaning('Cancelled'),
            'next_step_help' => requestStatusSettingsDefaultNextStep('Cancelled'),
            'overdue_after_days' => null,
            'sort_order' => 60,
            'is_active' => 1,
            'track_overdue' => 0,
        ],
        [
            'status_name' => 'Archived',
            'status_label' => 'Archived',
            'status_description' => requestStatusSettingsDefaultMeaning('Archived'),
            'next_step_help' => requestStatusSettingsDefaultNextStep('Archived'),
            'overdue_after_days' => null,
            'sort_order' => 70,
            'is_active' => 1,
            'track_overdue' => 0,
        ],
    ];
}

function ensureDefaultRequestStatusRules(mysqli $connection): void
{
    $defaultRules = getDefaultRequestStatusRules();

    $insertStatement = $connection->prepare(
        'INSERT INTO request_status_rules
            (
                status_name,
                status_label,
                status_description,
                next_step_help,
                overdue_after_days,
                sort_order,
                is_active,
                track_overdue
            )
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            status_name = status_name'
    );

    foreach ($defaultRules as $rule) {
        $statusName = (string)$rule['status_name'];
        $statusLabel = (string)$rule['status_label'];
        $statusDescription = (string)$rule['status_description'];
        $nextStepHelp = (string)$rule['next_step_help'];
        $overdueAfterDays = $rule['overdue_after_days'];
        $sortOrder = (int)$rule['sort_order'];
        $isActive = (int)$rule['is_active'];
        $trackOverdue = (int)$rule['track_overdue'];

        $insertStatement->bind_param(
            'ssssiiii',
            $statusName,
            $statusLabel,
            $statusDescription,
            $nextStepHelp,
            $overdueAfterDays,
            $sortOrder,
            $isActive,
            $trackOverdue
        );

        $insertStatement->execute();
    }

    /*
        Keep fixed workflow data locked.

        This page only changes overdue_after_days for statuses where a
        day-based response timer makes sense.
    */
    $normalizeStatement = $connection->prepare(
        'UPDATE request_status_rules
         SET
            status_label = ?,
            status_description = ?,
            next_step_help = ?,
            sort_order = ?,
            is_active = 1,
            track_overdue = ?
         WHERE status_name = ?
         LIMIT 1'
    );

    foreach ($defaultRules as $rule) {
        $statusName = (string)$rule['status_name'];
        $statusLabel = (string)$rule['status_label'];
        $statusDescription = (string)$rule['status_description'];
        $nextStepHelp = (string)$rule['next_step_help'];
        $sortOrder = (int)$rule['sort_order'];
        $trackOverdue = (int)$rule['track_overdue'];

        $normalizeStatement->bind_param(
            'sssiis',
            $statusLabel,
            $statusDescription,
            $nextStepHelp,
            $sortOrder,
            $trackOverdue,
            $statusName
        );

        $normalizeStatement->execute();
    }

    $connection->query(
        'UPDATE request_status_rules
         SET overdue_after_days = NULL
         WHERE status_name IN ("Scheduled", "Completed", "Cancelled", "Archived")'
    );

    $defaultDaysStatement = $connection->prepare(
        'UPDATE request_status_rules
         SET overdue_after_days = ?
         WHERE status_name = ?
           AND overdue_after_days IS NULL
         LIMIT 1'
    );

    foreach ($defaultRules as $rule) {
        $statusName = (string)$rule['status_name'];

        if (!requestStatusSettingsIsDayBasedStatus($statusName)) {
            continue;
        }

        $defaultDays = (int)$rule['overdue_after_days'];

        $defaultDaysStatement->bind_param('is', $defaultDays, $statusName);
        $defaultDaysStatement->execute();
    }
}

function loadRequestStatusRules(mysqli $connection): array
{
    $canonicalNames = requestStatusSettingsCanonicalStatusNames();

    if (empty($canonicalNames)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($canonicalNames), '?'));

    $statement = $connection->prepare(
        'SELECT
            id,
            status_name,
            status_label,
            status_description,
            next_step_help,
            overdue_after_days,
            sort_order,
            is_active,
            track_overdue,
            updated_at
         FROM request_status_rules
         WHERE status_name IN (' . $placeholders . ')'
    );

    requestStatusSettingsBindParams(
        $statement,
        str_repeat('s', count($canonicalNames)),
        $canonicalNames
    );

    $statement->execute();

    $rulesResult = $statement->get_result();
    $rulesByStatusName = [];

    while ($rule = $rulesResult->fetch_assoc()) {
        $rulesByStatusName[(string)$rule['status_name']] = $rule;
    }

    $orderedRules = [];

    foreach ($canonicalNames as $statusName) {
        if (isset($rulesByStatusName[$statusName])) {
            $orderedRules[] = $rulesByStatusName[$statusName];
        }
    }

    return $orderedRules;
}

function syncRequestStatusOptions(mysqli $connection): void
{
    /*
        This is kept for backward compatibility only.

        If request_status_options still exists, keep it synced.
        If it does not exist, skip it because the newer workflow uses
        request_status_rules as the source of truth.
    */
    if (!requestStatusSettingsTableExists($connection, 'request_status_options')) {
        return;
    }

    $syncStatement = $connection->prepare(
        'INSERT INTO request_status_options
            (
                status_name,
                sort_order
            )
         SELECT
            status_name,
            sort_order
         FROM request_status_rules
         WHERE is_active = 1
           AND status_name IN ("New", "Quoted", "Scheduled", "In Progress", "Completed", "Cancelled", "Archived")
         ON DUPLICATE KEY UPDATE
            sort_order = VALUES(sort_order)'
    );

    $syncStatement->execute();
}

try {
    $connection = getDatabaseConnection();

    ensureDefaultRequestStatusRules($connection);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'request_status_settings') {
        $submittedToken = (string)($_POST['csrf_token'] ?? '');

        if (!isValidCsrfToken($submittedToken)) {
            $pageErrors[] = 'Security check failed. Please refresh the page and try again.';
        }

        $postedDays = $_POST['overdue_after_days'] ?? [];

        if (!is_array($postedDays)) {
            $postedDays = [];
        }

        $updates = [];

        if (empty($pageErrors)) {
            foreach (requestStatusSettingsDayBasedStatusNames() as $statusName) {
                $daysRaw = trim((string)($postedDays[$statusName] ?? ''));

                if ($daysRaw === '') {
                    $pageErrors[] = $statusName . ' needs a response-days value.';
                    continue;
                }

                if (!ctype_digit($daysRaw)) {
                    $pageErrors[] = $statusName . ' response days must be a whole number.';
                    continue;
                }

                $days = (int)$daysRaw;

                if ($days < 0 || $days > 365) {
                    $pageErrors[] = $statusName . ' response days must be between 0 and 365.';
                    continue;
                }

                $updates[$statusName] = $days;
            }
        }

        if (empty($pageErrors)) {
            $connection->begin_transaction();

            try {
                $updateStatement = $connection->prepare(
                    'UPDATE request_status_rules
                     SET overdue_after_days = ?
                     WHERE status_name = ?
                     LIMIT 1'
                );

                foreach ($updates as $statusName => $days) {
                    $updateStatement->bind_param('is', $days, $statusName);
                    $updateStatement->execute();
                }

                ensureDefaultRequestStatusRules($connection);
                syncRequestStatusOptions($connection);

                $connection->commit();

                $pageMessages[] = 'Response timing settings were saved.';
            } catch (Throwable $exception) {
                $connection->rollback();

                throw $exception;
            }
        }
    }

    $statusRules = loadRequestStatusRules($connection);
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $pageErrors[] = $exception->getMessage();
    } else {
        $pageErrors[] = 'The request status settings page could not be loaded.';
    }
}

$editableTimerCount = 0;
$fixedRuleCount = 0;
$finalStatusCount = 0;

foreach ($statusRules as $rule) {
    $statusName = (string)($rule['status_name'] ?? '');

    if (requestStatusSettingsIsDayBasedStatus($statusName)) {
        $editableTimerCount++;
    } else {
        $fixedRuleCount++;
    }

    if (requestStatusSettingsIsTerminalStatus($statusName)) {
        $finalStatusCount++;
    }
}

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<style>
    .status-settings-page {
        --status-page-solid-bg: var(--kails-background-color, var(--kails-bg-black, #060707));
        --status-panel-solid-bg: var(--kails-dark-green, #10170f);
        --status-card-bg: var(--kails-card-bg, rgba(255, 255, 255, 0.035));
        --status-card-bg-strong: var(--kails-card-bg-strong, rgba(255, 255, 255, 0.055));
        --status-text: var(--kails-text-color, var(--kails-white-text, #f0f2ed));
        --status-muted: var(--kails-muted-text-color, rgba(240, 242, 237, 0.76));
        --status-accent: var(--kails-primary-color, var(--kails-primary-yellow, #f2cc16));
        --status-accent-2: var(--kails-secondary-color, var(--kails-primary-green, #97b62a));
        --status-border: var(--kails-border-color, rgba(242, 204, 22, 0.55));
        --status-success: var(--kails-success-color, #2fb36d);
        --status-off: var(--kails-danger-color, #ff4d5e);

        color: var(--status-text);
    }

    .status-settings-page h1,
    .status-settings-page h2,
    .status-settings-page h3,
    .status-settings-page p,
    .status-settings-page label,
    .status-settings-page div,
    .status-settings-page span,
    .status-settings-page strong {
        color: inherit;
    }

    .status-settings-page .text-muted,
    .status-muted {
        color: var(--status-muted) !important;
    }

    .status-page-header {
        align-items: flex-start;
    }

    .status-action-bar {
        align-self: flex-start;
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    .status-action-bar .btn,
    .status-save-bar .btn {
        align-items: center;
        display: inline-flex;
        font-size: 0.9rem;
        font-weight: 900;
        justify-content: center;
        line-height: 1.15;
        min-height: 2.2rem;
        padding: 0.4rem 0.7rem;
        white-space: nowrap;
    }

    .status-action-bar .btn-light,
    .status-save-bar .btn-light {
        background: var(--status-accent);
        border-color: var(--status-accent);
        color: #111111;
    }

    .status-action-bar .btn-outline-light,
    .status-save-bar .btn-outline-light {
        background: transparent;
        border-color: var(--status-accent);
        color: var(--status-accent);
    }

    .status-action-bar .btn-outline-light:hover,
    .status-save-bar .btn-outline-light:hover {
        background: var(--status-accent);
        color: #111111;
    }

    .status-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr));
        gap: 1rem;
    }

    .status-card,
    .status-rule-card,
    .status-info-card,
    .status-save-bar,
    .status-workflow-overview {
        border: 1px solid var(--status-border);
        background:
            radial-gradient(circle at top right, rgba(242, 204, 22, 0.08), transparent 38%),
            var(--status-card-bg);
        border-radius: 0.85rem;
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.25);
        color: var(--status-text);
    }

    .status-card {
        padding: 1.25rem;
    }

    .status-card-label {
        color: var(--status-accent);
        font-size: 0.85rem;
        font-weight: 900;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .status-card-number {
        font-size: 2.2rem;
        font-weight: 900;
        line-height: 1.1;
    }

    .status-info-card {
        padding: 1.25rem;
    }

    .status-info-card h2 {
        color: var(--status-accent);
    }

    .status-info-card ul {
        margin-bottom: 0;
    }

    .status-workflow-overview {
        overflow: hidden;
    }

    .status-workflow-header {
        border-bottom: 1px solid var(--status-border);
        background:
            linear-gradient(90deg, rgba(242, 204, 22, 0.08), transparent),
            var(--status-card-bg-strong);
        padding: 1rem;
    }

    .status-step-strip {
        display: grid;
        grid-template-columns: repeat(7, minmax(130px, 1fr));
        gap: 0.75rem;
        overflow-x: auto;
        padding: 1rem;
    }

    .status-step-mini-card {
        border: 1px solid rgba(240, 242, 237, 0.14);
        background: var(--status-card-bg-strong);
        border-radius: 0.75rem;
        min-width: 130px;
        padding: 0.85rem;
    }

    .status-step-number {
        align-items: center;
        background: var(--status-accent);
        border-radius: 999px;
        color: #111111 !important;
        display: inline-flex;
        font-size: 0.78rem;
        font-weight: 900;
        height: 1.55rem;
        justify-content: center;
        margin-bottom: 0.45rem;
        width: 1.55rem;
    }

    .status-step-mini-card h3 {
        font-size: 0.98rem;
        font-weight: 900;
        margin: 0 0 0.2rem;
    }

    .status-step-mini-card p {
        color: var(--status-muted);
        font-size: 0.85rem;
        line-height: 1.3;
        margin: 0;
    }

    .status-rule-card {
        overflow: hidden;
    }

    .status-rule-header {
        border-bottom: 1px solid var(--status-border);
        background:
            linear-gradient(90deg, rgba(242, 204, 22, 0.08), transparent),
            var(--status-card-bg-strong);
        padding: 1rem;
    }

    .status-rule-body {
        padding: 1rem;
    }

    .status-pill-row {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .status-state-pill {
        align-items: center;
        border: 1px solid currentColor;
        border-radius: 0.55rem;
        display: inline-flex;
        font-size: 0.82rem;
        font-weight: 900;
        gap: 0.35rem;
        line-height: 1.1;
        min-height: 2rem;
        padding: 0.35rem 0.65rem;
        white-space: nowrap;
    }

    .status-state-pill::before {
        border-radius: 999px;
        content: "";
        display: inline-block;
        height: 0.55rem;
        width: 0.55rem;
    }

    .status-state-pill-on {
        background: rgba(47, 179, 109, 0.16);
        color: var(--status-success);
    }

    .status-state-pill-on::before {
        background: var(--status-success);
    }

    .status-state-pill-off {
        background: rgba(255, 77, 94, 0.12);
        color: var(--status-off);
    }

    .status-state-pill-off::before {
        background: var(--status-off);
    }

    .status-state-pill-fixed {
        background: rgba(242, 204, 22, 0.12);
        color: var(--status-accent);
    }

    .status-state-pill-fixed::before {
        background: var(--status-accent);
    }

    .status-rule-key {
        color: var(--status-muted);
        font-size: 0.9rem;
    }

    .status-rule-grid {
        display: grid;
        grid-template-columns: minmax(260px, 0.8fr) minmax(300px, 1.2fr);
        gap: 1rem;
        align-items: stretch;
    }

    .status-timer-panel,
    .status-meaning-panel,
    .status-next-step-panel,
    .status-locked-panel {
        border: 1px solid rgba(240, 242, 237, 0.14);
        background: var(--status-card-bg-strong);
        border-radius: 0.65rem;
        padding: 0.9rem;
    }

    .status-timer-panel {
        display: grid;
        align-content: start;
        gap: 0.65rem;
    }

    .status-panel-label {
        color: var(--status-accent);
        display: block;
        font-size: 0.78rem;
        font-weight: 900;
        letter-spacing: 0.04em;
        margin-bottom: 0.35rem;
        text-transform: uppercase;
    }

    .status-locked-panel strong,
    .status-meaning-panel strong,
    .status-next-step-panel strong {
        color: var(--status-accent);
    }

    .status-meaning-grid {
        display: grid;
        gap: 0.75rem;
    }

    .status-timer-input-row {
        display: grid;
        grid-template-columns: minmax(90px, 120px) 1fr;
        gap: 0.75rem;
        align-items: center;
    }

    .status-timer-input-row input {
        font-size: 1.2rem;
        font-weight: 900;
        text-align: center;
    }

    .status-settings-page .form-text {
        color: var(--status-muted);
    }

    .status-save-bar {
        background:
            linear-gradient(90deg, rgba(242, 204, 22, 0.08), rgba(151, 182, 42, 0.05)),
            var(--status-panel-solid-bg);
        bottom: 0.75rem;
        margin-top: 1.5rem;
        padding: 0.75rem 1rem;
        position: sticky;
        z-index: 30;
    }

    .status-save-bar-inner {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: space-between;
    }

    .status-save-bar strong {
        color: var(--status-accent);
    }

    .status-save-bar .small {
        line-height: 1.25;
    }

    @media (max-width: 1199.98px) {
        .status-step-strip {
            grid-template-columns: repeat(7, minmax(145px, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .status-page-header {
            display: grid !important;
            grid-template-columns: 1fr;
        }

        .status-summary-grid,
        .status-rule-grid {
            grid-template-columns: 1fr;
        }

        .status-action-bar {
            justify-content: flex-start;
        }
    }

    @media (max-width: 575.98px) {
        .status-save-bar-inner,
        .status-action-bar,
        .status-timer-input-row {
            display: grid;
            grid-template-columns: 1fr;
        }

        .status-save-bar .btn,
        .status-action-bar .btn {
            width: 100%;
        }

        .status-timer-input-row input {
            text-align: left;
        }
    }
</style>

<main class="site-section status-settings-page">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('status_settings'); ?>

        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4 status-page-header">
            <div>
                <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                    Request Settings
                </p>

                <h1 class="fw-bold mb-2">
                    Request Response Timing
                </h1>

                <p class="lead status-muted mb-0">
                    Review the fixed request workflow and set how many days can pass before active requests need follow-up.
                </p>
            </div>

            <div class="status-action-bar">
                <a href="/admin_requests.php" class="btn btn-light">
                    Back to Requests
                </a>

                <a href="/admin.php" class="btn btn-outline-light">
                    Main Admin
                </a>

                <a href="/admin_system_check.php" class="btn btn-outline-light">
                    System Check
                </a>
            </div>
        </div>

        <?php if (!empty($pageMessages)): ?>
            <div class="alert alert-success">
                <?php foreach ($pageMessages as $pageMessage): ?>
                    <p class="mb-0">
                        <?php echo escapeHtml($pageMessage); ?>
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($pageErrors)): ?>
            <div class="alert alert-danger">
                <h2 class="h5 mb-2">Please Fix This</h2>

                <ul class="mb-0">
                    <?php foreach ($pageErrors as $pageError): ?>
                        <li><?php echo escapeHtml($pageError); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="status-summary-grid mb-4">
            <div class="status-card">
                <div class="status-card-label">
                    Fixed Workflow Steps
                </div>

                <div class="status-card-number">
                    <?php echo escapeHtml((string)count($statusRules)); ?>
                </div>

                <p class="status-muted mb-0">
                    Status names, meanings, and order are locked.
                </p>
            </div>

            <div class="status-card">
                <div class="status-card-label">
                    Editable Timers
                </div>

                <div class="status-card-number">
                    <?php echo escapeHtml((string)$editableTimerCount); ?>
                </div>

                <p class="status-muted mb-0">
                    New, Quoted, and In Progress use response days.
                </p>
            </div>

            <div class="status-card">
                <div class="status-card-label">
                    Fixed Rules
                </div>

                <div class="status-card-number">
                    <?php echo escapeHtml((string)$fixedRuleCount); ?>
                </div>

                <p class="status-muted mb-0">
                    Scheduled and final statuses are not day-based.
                </p>
            </div>
        </div>

        <div class="status-info-card mb-4">
            <h2 class="h5 mb-2">
                What This Page Controls
            </h2>

            <p class="mb-2">
                This page only controls response timing. It does not change the request workflow itself.
            </p>

            <ul>
                <li><strong>New, Quoted, and In Progress</strong> use day-based response timers.</li>
                <li><strong>Scheduled</strong> is handled by the scheduled work date and time.</li>
                <li><strong>Completed, Cancelled, and Archived</strong> are final statuses and do not need response timers.</li>
            </ul>
        </div>

        <?php if (!empty($statusRules)): ?>
            <section class="status-workflow-overview mb-4">
                <div class="status-workflow-header">
                    <h2 class="h4 mb-1">
                        Fixed Request Workflow
                    </h2>

                    <p class="status-muted mb-0">
                        These are the steps requests move through. The cards below explain what each step means.
                    </p>
                </div>

                <div class="status-step-strip">
                    <?php foreach ($statusRules as $index => $rule): ?>
                        <?php
                        $statusName = (string)$rule['status_name'];
                        $statusDescription = (string)($rule['status_description'] ?? '');

                        if ($statusDescription === '') {
                            $statusDescription = requestStatusSettingsDefaultMeaning($statusName);
                        }
                        ?>

                        <div class="status-step-mini-card">
                            <span class="status-step-number">
                                <?php echo escapeHtml((string)($index + 1)); ?>
                            </span>

                            <h3>
                                <?php echo escapeHtml($statusName); ?>
                            </h3>

                            <p>
                                <?php echo escapeHtml($statusDescription); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <form method="post" action="/admin_request_status_settings.php">
                <input type="hidden" name="form_name" value="request_status_settings">
                <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">

                <div class="d-flex flex-column gap-4">
                    <?php foreach ($statusRules as $index => $rule): ?>
                        <?php
                        $statusName = (string)$rule['status_name'];
                        $statusDescription = (string)($rule['status_description'] ?? '');
                        $nextStepHelp = (string)($rule['next_step_help'] ?? '');
                        $overdueAfterDays = $rule['overdue_after_days'];

                        if ($statusDescription === '') {
                            $statusDescription = requestStatusSettingsDefaultMeaning($statusName);
                        }

                        if ($nextStepHelp === '') {
                            $nextStepHelp = requestStatusSettingsDefaultNextStep($statusName);
                        }

                        $isDayBasedStatus = requestStatusSettingsIsDayBasedStatus($statusName);
                        $isScheduledStatus = requestStatusSettingsIsScheduledStatus($statusName);
                        $isTerminalStatus = requestStatusSettingsIsTerminalStatus($statusName);
                        ?>

                        <section class="status-rule-card">
                            <div class="status-rule-header">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                    <div>
                                        <div class="status-rule-key mb-1">
                                            Step <?php echo escapeHtml((string)($index + 1)); ?> of <?php echo escapeHtml((string)count($statusRules)); ?>
                                        </div>

                                        <h2 class="h4 mb-1">
                                            <?php echo escapeHtml($statusName); ?>
                                        </h2>

                                        <div class="status-rule-key">
                                            <?php echo escapeHtml(requestStatusSettingsStatusTypeHelp($statusName)); ?>
                                        </div>
                                    </div>

                                    <div class="status-pill-row">
                                        <span class="status-state-pill status-state-pill-fixed">
                                            Fixed Step
                                        </span>

                                        <?php if ($isDayBasedStatus): ?>
                                            <span class="status-state-pill status-state-pill-on">
                                                Response Timer
                                            </span>
                                        <?php elseif ($isScheduledStatus): ?>
                                            <span class="status-state-pill status-state-pill-fixed">
                                                Scheduled Timing
                                            </span>
                                        <?php elseif ($isTerminalStatus): ?>
                                            <span class="status-state-pill status-state-pill-off">
                                                Final Status
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="status-rule-body">
                                <div class="status-rule-grid">
                                    <div>
                                        <?php if ($isDayBasedStatus): ?>
                                            <div class="status-timer-panel">
                                                <div>
                                                    <span class="status-panel-label">
                                                        Editable Response Timer
                                                    </span>

                                                    <label for="overdue_after_days_<?php echo escapeHtml($statusName); ?>" class="form-label">
                                                        Days to Respond
                                                    </label>

                                                    <div class="status-timer-input-row">
                                                        <input
                                                            type="number"
                                                            class="form-control"
                                                            id="overdue_after_days_<?php echo escapeHtml($statusName); ?>"
                                                            name="overdue_after_days[<?php echo escapeHtml($statusName); ?>]"
                                                            value="<?php echo escapeHtml((string)((int)$overdueAfterDays)); ?>"
                                                            min="0"
                                                            max="365"
                                                            required
                                                        >

                                                        <div class="form-text">
                                                            After this many days, this status needs admin attention.
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="status-locked-panel">
                                                    <p class="mb-0">
                                                        Requests in <strong><?php echo escapeHtml($statusName); ?></strong>
                                                        should be followed up within this many days.
                                                    </p>
                                                </div>
                                            </div>
                                        <?php elseif ($isScheduledStatus): ?>
                                            <div class="status-locked-panel">
                                                <span class="status-panel-label">
                                                    Locked Timing Rule
                                                </span>

                                                <p class="mb-0">
                                                    <strong>Scheduled</strong> does not use a response-days value.
                                                    It is controlled by the scheduled work date and time.
                                                </p>
                                            </div>
                                        <?php elseif ($isTerminalStatus): ?>
                                            <div class="status-locked-panel">
                                                <span class="status-panel-label">
                                                    Locked Final Rule
                                                </span>

                                                <p class="mb-0">
                                                    <strong><?php echo escapeHtml($statusName); ?></strong>
                                                    is a final status and does not need follow-up timing.
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <div class="status-locked-panel">
                                                <span class="status-panel-label">
                                                    Locked Rule
                                                </span>

                                                <p class="mb-0">
                                                    This fixed status does not have editable timing.
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="status-meaning-grid">
                                        <div class="status-meaning-panel">
                                            <span class="status-panel-label">
                                                What This Step Means
                                            </span>

                                            <p class="mb-0">
                                                <?php echo escapeHtml($statusDescription); ?>
                                            </p>
                                        </div>

                                        <div class="status-next-step-panel">
                                            <span class="status-panel-label">
                                                Usual Next Step
                                            </span>

                                            <p class="mb-0">
                                                <?php echo escapeHtml($nextStepHelp); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <div class="status-save-bar">
                    <div class="status-save-bar-inner">
                        <div>
                            <strong>Save response timing?</strong>

                            <div class="status-muted small">
                                Only day-based response timers will be updated. Workflow steps and meanings stay locked.
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="/admin_requests.php" class="btn btn-outline-light">
                                Cancel
                            </a>

                            <button type="submit" class="btn btn-light">
                                Save Response Timing
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <div class="status-info-card">
                <h2 class="h5 mb-2">
                    No Status Rules Found
                </h2>

                <p class="mb-0">
                    No request status rules were loaded. Check the database table named request_status_rules.
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';