<?php
declare(strict_types=1);

/*
    Printable quote/invoice page.

    Planned route:
    - Print quote/invoice:
      /document_print.php?id=456

    This page reads from:
    - client_documents
    - client_document_items

    It does not edit records.

    Important:
    The screen preview is styled as an 8.5in x 11in letter page.
    The print footer is fixed to the bottom of each printed page so it does not create a useless extra page by itself.
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';

$pageTitle = 'Print Document';

adminRequireLogin('Document Print Login');

$errors = [];

function documentPrintMoney($value): string
{
    if ($value === null || $value === '') {
        return '$0.00';
    }

    return '$' . number_format((float)$value, 2);
}

function documentPrintDate($value): string
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

function documentPrintCleanType(string $documentType): string
{
    $documentType = strtolower(trim($documentType));

    if ($documentType === 'quote') {
        return 'quote';
    }

    return 'invoice';
}

function documentPrintTypeLabel(string $documentType): string
{
    return documentPrintCleanType($documentType) === 'quote' ? 'Quote' : 'Invoice';
}

function documentPrintSanitizeRichHtml(string $html): string
{
    if (function_exists('sanitizeRichHtml')) {
        return sanitizeRichHtml($html);
    }

    return strip_tags($html, '<p><br><strong><b><em><i><ul><ol><li>');
}

function getPrintableDocument(int $documentId): ?array
{
    if ($documentId <= 0) {
        return null;
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            id,
            document_type,
            document_number,
            client_id,
            quote_request_id,
            document_status,
            payment_method,
            issue_date,
            due_date,
            paid_date,
            business_name,
            business_phone,
            business_email,
            business_website,
            client_name,
            client_phone,
            client_email,
            client_street_address,
            client_city,
            client_state,
            client_zip_code,
            document_title,
            service_summary,
            subtotal_amount,
            discount_amount,
            tax_rate,
            tax_amount,
            total_amount,
            amount_paid,
            balance_due,
            public_notes,
            internal_notes,
            footer_note,
            payment_note,
            created_at,
            updated_at
         FROM client_documents
         WHERE id = ?
         LIMIT 1'
    );

    $statement->bind_param('i', $documentId);
    $statement->execute();

    $result = $statement->get_result();
    $document = $result->fetch_assoc();

    if (!$document) {
        return null;
    }

    $document['document_type'] = documentPrintCleanType((string)$document['document_type']);

    return $document;
}

function getPrintableDocumentItems(int $documentId): array
{
    if ($documentId <= 0) {
        return [];
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            id,
            item_description,
            quantity,
            unit_name,
            unit_price,
            line_total,
            sort_order
         FROM client_document_items
         WHERE document_id = ?
         ORDER BY sort_order ASC, id ASC'
    );

    $statement->bind_param('i', $documentId);
    $statement->execute();

    $result = $statement->get_result();
    $items = [];

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    return $items;
}

function documentPrintClientAddress(array $document): string
{
    $lines = [];

    $street = trim((string)($document['client_street_address'] ?? ''));

    if ($street !== '') {
        $lines[] = $street;
    }

    $city = trim((string)($document['client_city'] ?? ''));
    $state = trim((string)($document['client_state'] ?? ''));
    $zip = trim((string)($document['client_zip_code'] ?? ''));

    $cityStateZip = '';

    if ($city !== '') {
        $cityStateZip .= $city;
    }

    if ($state !== '') {
        $cityStateZip .= $cityStateZip !== '' ? ', ' . $state : $state;
    }

    if ($zip !== '') {
        $cityStateZip .= $cityStateZip !== '' ? ' ' . $zip : $zip;
    }

    if ($cityStateZip !== '') {
        $lines[] = $cityStateZip;
    }

    return implode("\n", $lines);
}

function documentPrintBusinessContact(array $document): array
{
    $lines = [];

    $phone = trim((string)($document['business_phone'] ?? ''));

    if ($phone !== '') {
        $lines[] = $phone;
    }

    $email = trim((string)($document['business_email'] ?? ''));

    if ($email !== '') {
        $lines[] = $email;
    }

    $website = trim((string)($document['business_website'] ?? ''));

    if ($website !== '') {
        $lines[] = $website;
    }

    return $lines;
}

$documentId = (int)($_GET['id'] ?? 0);
$document = null;
$items = [];

try {
    if ($documentId <= 0) {
        $errors[] = 'Document ID is missing.';
    } else {
        $document = getPrintableDocument($documentId);

        if ($document === null) {
            $errors[] = 'Document not found.';
        } else {
            $items = getPrintableDocumentItems($documentId);
        }
    }
} catch (Throwable $exception) {
    $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

    if ($showDetailedErrors) {
        $errors[] = $exception->getMessage();
    } else {
        $errors[] = 'The document could not be loaded.';
    }
}

$documentType = $document !== null ? documentPrintCleanType((string)$document['document_type']) : 'invoice';
$documentLabel = documentPrintTypeLabel($documentType);
$isInvoice = $documentType === 'invoice';
$requestId = $document !== null ? (int)($document['quote_request_id'] ?? 0) : 0;

require_once PROJECT_ROOT . '/src/Layout/head.php';
?>

<style>
    :root {
        --print-paper-width: 8.5in;
        --print-paper-height: 11in;
        --print-paper-padding: 0.42in;
        --print-footer-height: 0.42in;
        --print-ink: #111827;
        --print-muted: #4b5563;
        --print-faint: #6b7280;
        --print-line: #d1d5db;
        --print-heavy-line: #111827;
        --print-page-bg: #ffffff;
        --print-screen-bg: #d1d5db;
    }

    body {
        background: var(--print-screen-bg);
        color: var(--print-ink);
    }

    .print-toolbar {
        background: #050505;
        border-bottom: 3px solid #f2cc16;
        color: #ffffff;
        padding: 1rem 0;
    }

    .print-toolbar-inner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .print-page-preview-label {
        max-width: var(--print-paper-width);
        margin: 1rem auto 0;
        color: #111827;
        font-size: 0.9rem;
        text-align: center;
    }

    .print-document-shell {
        width: var(--print-paper-width);
        margin: 1rem auto 2rem;
    }

    .print-document {
        width: var(--print-paper-width);
        min-height: var(--print-paper-height);
        box-sizing: border-box;
        background: var(--print-page-bg);
        color: var(--print-ink);
        box-shadow: 0 18px 45px rgba(0, 0, 0, 0.32);
        padding: var(--print-paper-padding);
        padding-bottom: calc(var(--print-paper-padding) + var(--print-footer-height));
        overflow: visible;
        position: relative;
    }

    .print-document * {
        box-sizing: border-box;
        color: inherit;
    }

    .print-header {
        display: grid;
        grid-template-columns: 1.2fr 0.8fr;
        gap: 0.3in;
        border-bottom: 3px solid var(--print-heavy-line);
        padding-bottom: 0.14in;
        margin-bottom: 0.18in;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .print-business-name {
        font-size: 20pt;
        font-weight: 800;
        line-height: 1.1;
        margin: 0 0 0.06in;
    }

    .print-business-contact {
        margin: 0;
        color: var(--print-muted);
        font-size: 9.5pt;
        line-height: 1.35;
    }

    .print-document-title {
        text-align: right;
    }

    .print-document-title h1 {
        font-size: 24pt;
        font-weight: 900;
        letter-spacing: 0.08em;
        line-height: 1;
        text-transform: uppercase;
        margin: 0 0 0.06in;
    }

    .print-document-number {
        color: var(--print-muted);
        margin: 0 0 0.05in;
        font-size: 9pt;
    }

    .print-status-pill {
        display: inline-block;
        border: 1px solid var(--print-heavy-line);
        border-radius: 999px;
        padding: 0.035in 0.1in;
        font-size: 8pt;
        font-weight: 700;
    }

    .print-section {
        margin-bottom: 0.18in;
    }

    .print-two-column {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.3in;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .print-section-title {
        color: var(--print-ink);
        font-size: 8pt;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        border-bottom: 1px solid var(--print-line);
        padding-bottom: 0.045in;
        margin: 0 0 0.075in;
        break-after: avoid;
        page-break-after: avoid;
    }

    .print-field {
        margin-bottom: 0.065in;
    }

    .print-field-label {
        color: var(--print-muted);
        display: block;
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 0.015in;
    }

    .print-field-value {
        white-space: pre-line;
        font-size: 9.3pt;
        line-height: 1.3;
    }

    .print-items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0.07in;
        table-layout: fixed;
    }

    .print-items-table thead {
        display: table-header-group;
    }

    .print-items-table tfoot {
        display: table-footer-group;
    }

    .print-items-table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .print-items-table th,
    .print-items-table td {
        border-bottom: 1px solid var(--print-line);
        padding: 0.065in 0.05in;
        vertical-align: top;
        font-size: 8.8pt;
        line-height: 1.25;
    }

    .print-items-table th {
        color: var(--print-ink);
        font-size: 7.5pt;
        font-weight: 800;
        text-align: left;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-top: 2px solid var(--print-heavy-line);
        border-bottom: 2px solid var(--print-heavy-line);
    }

    .print-items-table .description-column {
        width: 43%;
    }

    .print-items-table .quantity-column {
        width: 12%;
    }

    .print-items-table .unit-column {
        width: 15%;
    }

    .print-items-table .price-column,
    .print-items-table .total-column {
        width: 15%;
    }

    .print-items-table .number-cell {
        text-align: right;
        white-space: nowrap;
    }

    .print-totals {
        display: flex;
        justify-content: flex-end;
        margin-top: 0.1in;
        break-inside: avoid;
        page-break-inside: avoid;
    }

    .print-totals-table {
        width: 3.45in;
        border-collapse: collapse;
    }

    .print-totals-table th,
    .print-totals-table td {
        border-bottom: 1px solid var(--print-line);
        padding: 0.05in;
        font-size: 9pt;
    }

    .print-totals-table th {
        text-align: left;
        font-weight: 800;
    }

    .print-totals-table td {
        text-align: right;
        white-space: nowrap;
    }

    .print-total-row th,
    .print-total-row td {
        border-top: 2px solid var(--print-heavy-line);
        border-bottom: 2px solid var(--print-heavy-line);
        font-size: 10pt;
        font-weight: 900;
    }

    .print-notes {
        border-top: 1px solid var(--print-line);
        padding-top: 0.1in;
        margin-top: 0.14in;
    }

    .print-note-block {
        margin-bottom: 0.11in;
        break-inside: auto;
        page-break-inside: auto;
    }

    .print-note-block:last-child {
        margin-bottom: 0;
    }

    .print-note-content {
        color: var(--print-muted);
        font-size: 8.5pt;
        line-height: 1.38;
    }

    .print-note-content p {
        margin-top: 0;
        margin-bottom: 0.08in;
    }

    .print-note-content p:last-child {
        margin-bottom: 0;
    }

    .print-footer {
        border-top: 1px solid var(--print-heavy-line);
        color: var(--print-muted);
        font-size: 8pt;
        line-height: 1.25;
        text-align: center;
        padding-top: 0.08in;
        margin-top: 0.16in;
    }

    .print-error-shell {
        max-width: 900px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    @media print {
        @page {
            size: letter;
            margin: 0.42in;
        }

        html,
        body {
            width: auto;
            min-height: auto;
            margin: 0;
            padding: 0;
            background: var(--print-page-bg);
        }

        .print-toolbar,
        .print-page-preview-label,
        .site-header,
        .site-footer,
        nav,
        footer:not(.print-footer) {
            display: none !important;
        }

        .print-document-shell {
            width: auto;
            margin: 0;
            padding: 0;
        }

        .print-document {
            width: auto;
            min-height: auto;
            margin: 0;
            padding: 0;
            padding-bottom: 0.52in;
            box-shadow: none;
            overflow: visible;
        }

        .print-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 0.35in;
            margin: 0;
            padding-top: 0.07in;
            background: #ffffff;
        }

        .print-header,
        .print-two-column,
        .print-totals {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .print-section-title {
            break-after: avoid;
            page-break-after: avoid;
        }

        .print-items-table tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .print-notes,
        .print-note-block {
            break-inside: auto;
            page-break-inside: auto;
        }
    }

    @media screen and (max-width: 9in) {
        .print-document-shell,
        .print-page-preview-label {
            width: calc(100vw - 2rem);
            max-width: var(--print-paper-width);
        }

        .print-document {
            width: 100%;
            min-height: auto;
            padding: 1rem;
        }

        .print-header,
        .print-two-column {
            grid-template-columns: 1fr;
        }

        .print-document-title {
            text-align: left;
        }

        .print-totals {
            justify-content: stretch;
        }

        .print-totals-table {
            width: 100%;
        }

        .print-items-table {
            table-layout: auto;
        }

        .print-items-table th,
        .print-items-table td {
            font-size: 0.85rem;
        }
    }
</style>

<div class="print-toolbar">
    <div class="container print-toolbar-inner">
        <div>
            <strong><?php echo escapeHtml($documentLabel); ?> Print Preview</strong>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($requestId > 0): ?>
                <a href="/admin_request_detail.php?id=<?php echo escapeHtml((string)$requestId); ?>" class="btn btn-outline-light">
                    Back to Request
                </a>
            <?php endif; ?>

            <?php if ($documentId > 0): ?>
                <a href="/admin_document_edit.php?id=<?php echo escapeHtml((string)$documentId); ?>" class="btn btn-outline-light">
                    Edit <?php echo escapeHtml($documentLabel); ?>
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-light" onclick="window.print();">
                Print
            </button>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <main class="print-error-shell">
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">
                <?php echo escapeHtml($error); ?>
            </div>
        <?php endforeach; ?>
    </main>
<?php elseif ($document !== null): ?>
    <div class="print-page-preview-label">
        Letter paper preview: 8.5in × 11in
    </div>

    <main class="print-document-shell">
        <article class="print-document">
            <header class="print-header">
                <div>
                    <p class="print-business-name">
                        <?php echo escapeHtml((string)($document['business_name'] ?? '')); ?>
                    </p>

                    <?php $businessContactLines = documentPrintBusinessContact($document); ?>

                    <?php if (!empty($businessContactLines)): ?>
                        <p class="print-business-contact">
                            <?php echo nl2br(escapeHtml(implode("\n", $businessContactLines))); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="print-document-title">
                    <h1><?php echo escapeHtml($documentLabel); ?></h1>

                    <p class="print-document-number">
                        <?php echo escapeHtml((string)($document['document_number'] ?? '')); ?>
                    </p>

                    <p class="print-document-number">
                        Status:
                        <span class="print-status-pill">
                            <?php echo escapeHtml((string)($document['document_status'] ?? 'Draft')); ?>
                        </span>
                    </p>
                </div>
            </header>

            <section class="print-section print-two-column">
                <div>
                    <h2 class="print-section-title">
                        Customer
                    </h2>

                    <div class="print-field">
                        <span class="print-field-label">Name</span>
                        <span class="print-field-value"><?php echo escapeHtml((string)($document['client_name'] ?? '—')); ?></span>
                    </div>

                    <?php $clientAddress = documentPrintClientAddress($document); ?>

                    <?php if ($clientAddress !== ''): ?>
                        <div class="print-field">
                            <span class="print-field-label">Address</span>
                            <span class="print-field-value"><?php echo nl2br(escapeHtml($clientAddress)); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (trim((string)($document['client_phone'] ?? '')) !== ''): ?>
                        <div class="print-field">
                            <span class="print-field-label">Phone</span>
                            <span class="print-field-value"><?php echo escapeHtml((string)$document['client_phone']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (trim((string)($document['client_email'] ?? '')) !== ''): ?>
                        <div class="print-field">
                            <span class="print-field-label">Email</span>
                            <span class="print-field-value"><?php echo escapeHtml((string)$document['client_email']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <h2 class="print-section-title">
                        Document Details
                    </h2>

                    <div class="print-field">
                        <span class="print-field-label">Title</span>
                        <span class="print-field-value"><?php echo escapeHtml((string)($document['document_title'] ?? $documentLabel)); ?></span>
                    </div>

                    <?php if (trim((string)($document['service_summary'] ?? '')) !== ''): ?>
                        <div class="print-field">
                            <span class="print-field-label">Overall Job</span>
                            <span class="print-field-value"><?php echo escapeHtml((string)$document['service_summary']); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="print-field">
                        <span class="print-field-label">Issue Date</span>
                        <span class="print-field-value"><?php echo escapeHtml(documentPrintDate($document['issue_date'] ?? null)); ?></span>
                    </div>

                    <?php if (trim((string)($document['due_date'] ?? '')) !== ''): ?>
                        <div class="print-field">
                            <span class="print-field-label">Due Date</span>
                            <span class="print-field-value"><?php echo escapeHtml(documentPrintDate($document['due_date'] ?? null)); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($isInvoice && trim((string)($document['paid_date'] ?? '')) !== ''): ?>
                        <div class="print-field">
                            <span class="print-field-label">Paid Date</span>
                            <span class="print-field-value"><?php echo escapeHtml(documentPrintDate($document['paid_date'] ?? null)); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($isInvoice && trim((string)($document['payment_method'] ?? '')) !== ''): ?>
                        <div class="print-field">
                            <span class="print-field-label">Payment Method</span>
                            <span class="print-field-value"><?php echo escapeHtml((string)$document['payment_method']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="print-section">
                <h2 class="print-section-title">
                    Itemized Lines
                </h2>

                <table class="print-items-table">
                    <thead>
                        <tr>
                            <th class="description-column">Description</th>
                            <th class="quantity-column number-cell">Qty</th>
                            <th class="unit-column">Unit</th>
                            <th class="price-column number-cell">Unit Price</th>
                            <th class="total-column number-cell">Line Total</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="5">
                                    No line items saved.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo escapeHtml((string)($item['item_description'] ?? '')); ?></td>
                                    <td class="number-cell"><?php echo escapeHtml(number_format((float)($item['quantity'] ?? 0), 2)); ?></td>
                                    <td><?php echo escapeHtml((string)($item['unit_name'] ?? '')); ?></td>
                                    <td class="number-cell"><?php echo escapeHtml(documentPrintMoney($item['unit_price'] ?? 0)); ?></td>
                                    <td class="number-cell"><?php echo escapeHtml(documentPrintMoney($item['line_total'] ?? 0)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="print-totals">
                    <table class="print-totals-table">
                        <tbody>
                            <tr>
                                <th>Subtotal</th>
                                <td><?php echo escapeHtml(documentPrintMoney($document['subtotal_amount'] ?? 0)); ?></td>
                            </tr>

                            <?php if ((float)($document['discount_amount'] ?? 0) > 0): ?>
                                <tr>
                                    <th>Discount</th>
                                    <td>-<?php echo escapeHtml(documentPrintMoney($document['discount_amount'] ?? 0)); ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ((float)($document['tax_amount'] ?? 0) > 0): ?>
                                <tr>
                                    <th>Tax</th>
                                    <td><?php echo escapeHtml(documentPrintMoney($document['tax_amount'] ?? 0)); ?></td>
                                </tr>
                            <?php endif; ?>

                            <tr class="print-total-row">
                                <th><?php echo $isInvoice ? 'Invoice Total' : 'Quote Total'; ?></th>
                                <td><?php echo escapeHtml(documentPrintMoney($document['total_amount'] ?? 0)); ?></td>
                            </tr>

                            <?php if ($isInvoice): ?>
                                <tr>
                                    <th>Payment Received</th>
                                    <td><?php echo escapeHtml(documentPrintMoney($document['amount_paid'] ?? 0)); ?></td>
                                </tr>

                                <tr class="print-total-row">
                                    <th>Balance Due</th>
                                    <td><?php echo escapeHtml(documentPrintMoney($document['balance_due'] ?? 0)); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php
                $hasPublicNotes = trim((string)($document['public_notes'] ?? '')) !== '';
                $hasFooterNote = trim((string)($document['footer_note'] ?? '')) !== '';
                $hasPaymentNote = trim((string)($document['payment_note'] ?? '')) !== '';
            ?>

            <?php if ($hasPublicNotes || $hasFooterNote || $hasPaymentNote): ?>
                <section class="print-notes">
                    <?php if ($hasPublicNotes): ?>
                        <div class="print-note-block">
                            <h2 class="print-section-title">
                                Notes
                            </h2>

                            <div class="print-note-content">
                                <?php echo nl2br(escapeHtml((string)$document['public_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasPaymentNote): ?>
                        <div class="print-note-block">
                            <h2 class="print-section-title">
                                Payment
                            </h2>

                            <div class="print-note-content">
                                <?php echo documentPrintSanitizeRichHtml((string)$document['payment_note']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasFooterNote): ?>
                        <div class="print-note-block">
                            <h2 class="print-section-title">
                                Additional Information
                            </h2>

                            <div class="print-note-content">
                                <?php echo documentPrintSanitizeRichHtml((string)$document['footer_note']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <footer class="print-footer">
                <?php echo escapeHtml((string)($document['business_name'] ?? '')); ?>

                <?php if (trim((string)($document['business_phone'] ?? '')) !== ''): ?>
                    · <?php echo escapeHtml((string)$document['business_phone']); ?>
                <?php endif; ?>

                <?php if (trim((string)($document['business_email'] ?? '')) !== ''): ?>
                    · <?php echo escapeHtml((string)$document['business_email']); ?>
                <?php endif; ?>
            </footer>
        </article>
    </main>
<?php endif; ?>

</body>
</html>