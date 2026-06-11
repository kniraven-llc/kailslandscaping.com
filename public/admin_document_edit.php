<?php
declare(strict_types=1);

/*
    Admin document edit page.

    This page creates and edits one quote or invoice.

    Planned routes:
    - Create quote:
      /admin_document_edit.php?type=quote&request_id=123

    - Create invoice:
      /admin_document_edit.php?type=invoice&request_id=123

    - Edit quote/invoice:
      /admin_document_edit.php?id=456

    - Print quote/invoice:
      /document_print.php?id=456

    Scope:
    This page edits only the document itself:
    - status
    - dates
    - title
    - overall job name
    - itemized lines
    - discount
    - tax
    - invoice payment received

    It does not edit:
    - business identity
    - customer records
    - request/project details
    - global footer/payment notes
*/

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/config/initialize.php';
require_once PROJECT_ROOT . '/src/Admin/admin_auth.php';
require_once PROJECT_ROOT . '/src/Admin/admin_crm_navigation.php';

$pageTitle = 'Edit Document';

adminRequireLogin('Document Login');

$messages = [];
$errors = [];

function documentEditText(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function documentEditNullableString(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    return $value;
}

function documentEditDecimal(string $value): float
{
    $value = trim($value);
    $value = str_replace(['$', ',', '%'], '', $value);

    if ($value === '' || !is_numeric($value)) {
        return 0.00;
    }

    return round((float)$value, 2);
}

function documentEditPercentToRate(string $value): float
{
    $value = trim($value);
    $value = str_replace(['%', ','], '', $value);

    if ($value === '' || !is_numeric($value)) {
        return 0.0000;
    }

    return round(((float)$value) / 100, 4);
}

function documentEditRateToPercent($value): string
{
    if ($value === null || $value === '') {
        return '0.00';
    }

    return number_format(((float)$value) * 100, 2, '.', '');
}

function documentEditDecimalForInput($value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    return number_format((float)$value, 2, '.', '');
}

function documentEditMoney($value): string
{
    if ($value === null || $value === '') {
        return '$0.00';
    }

    return '$' . number_format((float)$value, 2);
}

function documentEditDateForInput($value): string
{
    if ($value === null || trim((string)$value) === '') {
        return '';
    }

    $timestamp = strtotime((string)$value);

    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function documentEditDateDisplay($value): string
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

function documentEditDateTimeDisplay($value): string
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

function documentEditBindParams(mysqli_stmt $statement, string $types, array $params): void
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

function documentEditSelectedValue(string $currentValue, string $optionValue): string
{
    return $currentValue === $optionValue ? 'selected' : '';
}

function documentEditCleanType(string $documentType): string
{
    $documentType = strtolower(trim($documentType));

    if ($documentType === 'quote') {
        return 'quote';
    }

    if ($documentType === 'receipt') {
        return 'invoice';
    }

    if ($documentType === 'invoice') {
        return 'invoice';
    }

    return 'invoice';
}

function documentEditTypeLabel(string $documentType): string
{
    return documentEditCleanType($documentType) === 'quote' ? 'Quote' : 'Invoice';
}

function documentEditStatusClass(string $status): string
{
    $statusClass = strtolower($status);
    $statusClass = str_replace(' ', '-', $statusClass);
    $statusClass = preg_replace('/[^a-z0-9-]/', '', $statusClass);

    return 'request-status request-status-' . $statusClass;
}

function documentEditGeneratedNumber(string $documentType): string
{
    $documentType = documentEditCleanType($documentType);

    if ($documentType === 'quote') {
        $prefix = contentValue('quote_number_prefix');

        if ($prefix === '') {
            $prefix = 'KQ-';
        }
    } else {
        $prefix = contentValue('invoice_number_prefix');

        if ($prefix === '') {
            $prefix = contentValue('receipt_number_prefix');
        }

        if ($prefix === '') {
            $prefix = 'KI-';
        }
    }

    $prefix = preg_replace('/[^A-Za-z0-9-]/', '', $prefix);

    if ($prefix === '') {
        $prefix = $documentType === 'quote' ? 'KQ-' : 'KI-';
    }

    return $prefix . date('Ymd-His') . '-' . random_int(100, 999);
}

function documentEditDefaultTitle(string $documentType): string
{
    $documentType = documentEditCleanType($documentType);

    if ($documentType === 'quote') {
        $title = contentValue('quote_default_title');

        return $title !== '' ? $title : 'Quote';
    }

    $title = contentValue('invoice_default_title');

    if ($title === '') {
        $title = contentValue('receipt_default_title');
    }

    return $title !== '' ? $title : 'Invoice';
}

function documentEditDefaultFooterNote(string $documentType): string
{
    $documentType = documentEditCleanType($documentType);

    if ($documentType === 'quote') {
        return contentValue('quote_footer_note');
    }

    $note = contentValue('invoice_footer_note');

    if ($note === '') {
        $note = contentValue('receipt_footer_note');
    }

    return $note;
}

function documentEditDefaultPaymentNote(string $documentType): string
{
    $documentType = documentEditCleanType($documentType);

    if ($documentType === 'quote') {
        return contentValue('quote_payment_note');
    }

    $note = contentValue('invoice_payment_note');

    if ($note === '') {
        $note = contentValue('receipt_payment_note');
    }

    return $note;
}

function documentEditStatusOptions(): array
{
    try {
        $connection = getDatabaseConnection();

        $result = $connection->query(
            'SELECT status_name
             FROM document_status_options
             ORDER BY sort_order ASC, status_name ASC'
        );

        if ($result) {
            $statuses = [];

            while ($row = $result->fetch_assoc()) {
                $statusName = trim((string)($row['status_name'] ?? ''));

                if ($statusName !== '') {
                    $statuses[] = $statusName;
                }
            }

            if (!empty($statuses)) {
                return $statuses;
            }
        }
    } catch (Throwable $exception) {
        // Use fallback options below.
    }

    return [
        'Draft',
        'Sent',
        'Accepted',
        'Paid',
        'Cancelled',
        'Archived',
    ];
}

function documentEditPaymentMethodOptions(): array
{
    try {
        $connection = getDatabaseConnection();

        $result = $connection->query(
            'SELECT method_name
             FROM payment_method_options
             ORDER BY sort_order ASC, method_name ASC'
        );

        if ($result) {
            $methods = [];

            while ($row = $result->fetch_assoc()) {
                $methodName = trim((string)($row['method_name'] ?? ''));

                if ($methodName !== '') {
                    $methods[] = $methodName;
                }
            }

            if (!empty($methods)) {
                return $methods;
            }
        }
    } catch (Throwable $exception) {
        // Use fallback options below.
    }

    return [
        'Not Recorded',
        'Cash',
        'Check',
        'Credit Card',
        'Debit Card',
        'Bank Transfer',
        'Other',
    ];
}

function documentEditDefaultRecord(string $documentType = 'invoice'): array
{
    $documentType = documentEditCleanType($documentType);

    return [
        'id' => 0,
        'document_type' => $documentType,
        'document_number' => documentEditGeneratedNumber($documentType),
        'client_id' => 0,
        'quote_request_id' => 0,
        'document_status' => 'Draft',
        'payment_method' => 'Not Recorded',
        'issue_date' => date('Y-m-d'),
        'due_date' => '',
        'paid_date' => '',

        'business_name' => contentValue('business_name'),
        'business_phone' => contentValue('business_phone_display'),
        'business_email' => contentValue('business_email'),
        'business_website' => contentValue('business_website'),

        'client_name' => '',
        'client_phone' => '',
        'client_email' => '',
        'client_street_address' => '',
        'client_city' => '',
        'client_state' => 'WI',
        'client_zip_code' => '',

        'document_title' => documentEditDefaultTitle($documentType),
        'service_summary' => '',

        'subtotal_amount' => '0.00',
        'discount_amount' => '0.00',
        'tax_rate' => '0.0000',
        'tax_amount' => '0.00',
        'total_amount' => '0.00',
        'amount_paid' => '0.00',
        'balance_due' => '0.00',

        'public_notes' => '',
        'internal_notes' => '',
        'footer_note' => documentEditDefaultFooterNote($documentType),
        'payment_note' => documentEditDefaultPaymentNote($documentType),

        'created_at' => '',
        'updated_at' => '',
    ];
}

function documentEditServiceNameFromRequest(array $request): string
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

function getDocumentEditRequest(int $requestId): ?array
{
    if ($requestId <= 0) {
        return null;
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            qr.id,
            qr.request_number,
            qr.client_id,
            qr.request_status,
            qr.job_title,
            qr.requested_service_id,
            qr.custom_service_name,
            qr.project_details,
            qr.public_notes,
            qr.property_address,
            qr.property_city,
            qr.property_state,
            qr.property_zip_code,
            qr.quoted_price,
            qr.final_price,
            c.full_name,
            c.phone,
            c.email,
            c.street_address,
            c.city,
            c.state,
            c.zip_code,
            s.service_title
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

function getDocumentEditRecord(int $documentId): ?array
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
    $record = $result->fetch_assoc();

    if (!$record) {
        return null;
    }

    $record['document_type'] = documentEditCleanType((string)$record['document_type']);

    return $record;
}

function getDocumentEditItems(int $documentId): array
{
    if ($documentId <= 0) {
        return [];
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'SELECT
            id,
            document_id,
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

function documentEditInsertLineItem(
    int $documentId,
    string $itemDescription,
    float $quantity,
    string $unitName,
    float $unitPrice,
    int $sortOrder
): void {
    if ($documentId <= 0) {
        return;
    }

    $itemDescription = documentEditText($itemDescription);

    if ($itemDescription === '') {
        return;
    }

    if ($quantity <= 0) {
        $quantity = 1.00;
    }

    $unitName = documentEditText($unitName);

    if ($unitName === '') {
        $unitName = 'each';
    }

    $unitPrice = round($unitPrice, 2);
    $lineTotal = round($quantity * $unitPrice, 2);

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'INSERT INTO client_document_items
            (
                document_id,
                item_description,
                quantity,
                unit_name,
                unit_price,
                line_total,
                sort_order
            )
         VALUES
            (?, ?, ?, ?, ?, ?, ?)'
    );

    $statement->bind_param(
        'isdsddi',
        $documentId,
        $itemDescription,
        $quantity,
        $unitName,
        $unitPrice,
        $lineTotal,
        $sortOrder
    );

    $statement->execute();
}

function documentEditRecalculateTotals(int $documentId): void
{
    if ($documentId <= 0) {
        return;
    }

    $connection = getDatabaseConnection();

    $subtotalStatement = $connection->prepare(
        'SELECT COALESCE(SUM(line_total), 0.00) AS subtotal_amount
         FROM client_document_items
         WHERE document_id = ?'
    );

    $subtotalStatement->bind_param('i', $documentId);
    $subtotalStatement->execute();

    $subtotalRow = $subtotalStatement->get_result()->fetch_assoc();
    $subtotalAmount = round((float)($subtotalRow['subtotal_amount'] ?? 0.00), 2);

    $document = getDocumentEditRecord($documentId);

    if ($document === null) {
        return;
    }

    $discountAmount = max(0.00, round((float)($document['discount_amount'] ?? 0.00), 2));

    if ($discountAmount > $subtotalAmount) {
        $discountAmount = $subtotalAmount;
    }

    $taxRate = max(0.0000, round((float)($document['tax_rate'] ?? 0.0000), 4));
    $taxableAmount = max(0.00, $subtotalAmount - $discountAmount);
    $taxAmount = round($taxableAmount * $taxRate, 2);
    $totalAmount = round($taxableAmount + $taxAmount, 2);
    $amountPaid = max(0.00, round((float)($document['amount_paid'] ?? 0.00), 2));

    if (documentEditCleanType((string)($document['document_type'] ?? 'invoice')) === 'quote') {
        $amountPaid = 0.00;
    }

    $balanceDue = round($totalAmount - $amountPaid, 2);

    $updateStatement = $connection->prepare(
        'UPDATE client_documents
         SET
            subtotal_amount = ?,
            discount_amount = ?,
            tax_amount = ?,
            total_amount = ?,
            amount_paid = ?,
            balance_due = ?
         WHERE id = ?'
    );

    $updateStatement->bind_param(
        'ddddddi',
        $subtotalAmount,
        $discountAmount,
        $taxAmount,
        $totalAmount,
        $amountPaid,
        $balanceDue,
        $documentId
    );

    $updateStatement->execute();
}

function documentEditCreateFromRequest(int $requestId, string $documentType): int
{
    $documentType = documentEditCleanType($documentType);
    $request = getDocumentEditRequest($requestId);

    if ($request === null) {
        throw new RuntimeException('Request not found.');
    }

    $connection = getDatabaseConnection();

    $documentNumber = documentEditGeneratedNumber($documentType);
    $clientId = (int)$request['client_id'];
    $quoteRequestId = (int)$request['id'];
    $documentStatus = 'Draft';
    $paymentMethod = 'Not Recorded';
    $issueDate = date('Y-m-d');
    $dueDate = null;
    $paidDate = null;

    $businessName = contentValue('business_name');
    $businessPhone = contentValue('business_phone_display');
    $businessEmail = contentValue('business_email');
    $businessWebsite = contentValue('business_website');

    $clientName = (string)$request['full_name'];
    $clientPhone = documentEditNullableString((string)($request['phone'] ?? ''));
    $clientEmail = documentEditNullableString((string)($request['email'] ?? ''));

    $clientStreetAddress = documentEditNullableString((string)($request['property_address'] ?? ''));

    if ($clientStreetAddress === null) {
        $clientStreetAddress = documentEditNullableString((string)($request['street_address'] ?? ''));
    }

    $clientCity = documentEditNullableString((string)($request['property_city'] ?? ''));

    if ($clientCity === null) {
        $clientCity = documentEditNullableString((string)($request['city'] ?? ''));
    }

    $clientState = documentEditNullableString((string)($request['property_state'] ?? ''));

    if ($clientState === null) {
        $clientState = documentEditNullableString((string)($request['state'] ?? 'WI')) ?? 'WI';
    }

    $clientZipCode = documentEditNullableString((string)($request['property_zip_code'] ?? ''));

    if ($clientZipCode === null) {
        $clientZipCode = documentEditNullableString((string)($request['zip_code'] ?? ''));
    }

    $documentTitle = documentEditDefaultTitle($documentType);
    $serviceSummary = documentEditServiceNameFromRequest($request);

    $discountAmount = 0.00;
    $taxRate = 0.0000;
    $amountPaid = 0.00;

    $publicNotes = documentEditNullableString((string)($request['public_notes'] ?? ''));

    if ($publicNotes === null) {
        $publicNotes = documentEditNullableString((string)($request['project_details'] ?? ''));
    }

    $internalNotes = null;
    $footerNote = documentEditDefaultFooterNote($documentType);
    $paymentNote = documentEditDefaultPaymentNote($documentType);

    $connection->begin_transaction();

    try {
        $statement = $connection->prepare(
            'INSERT INTO client_documents
                (
                    document_number,
                    client_id,
                    quote_request_id,
                    document_type,
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
                    discount_amount,
                    tax_rate,
                    amount_paid,
                    public_notes,
                    internal_notes,
                    footer_note,
                    payment_note
                )
             VALUES
                (
                    ?,
                    NULLIF(?, 0),
                    NULLIF(?, 0),
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?
                )'
        );

        $params = [
            $documentNumber,
            $clientId,
            $quoteRequestId,
            $documentType,
            $documentStatus,
            $paymentMethod,
            $issueDate,
            $dueDate,
            $paidDate,
            $businessName,
            $businessPhone,
            $businessEmail,
            $businessWebsite,
            $clientName,
            $clientPhone,
            $clientEmail,
            $clientStreetAddress,
            $clientCity,
            $clientState,
            $clientZipCode,
            $documentTitle,
            $serviceSummary,
            $discountAmount,
            $taxRate,
            $amountPaid,
            $publicNotes,
            $internalNotes,
            $footerNote,
            $paymentNote,
        ];

        documentEditBindParams(
            $statement,
            'sii' . str_repeat('s', 19) . 'dddssss',
            $params
        );

        $statement->execute();

        $documentId = (int)$connection->insert_id;

        $requestItems = [];

        $itemsStatement = $connection->prepare(
            'SELECT
                item_description,
                quantity,
                unit_name,
                unit_price,
                sort_order
             FROM quote_request_items
             WHERE quote_request_id = ?
             ORDER BY sort_order ASC, id ASC'
        );

        $itemsStatement->bind_param('i', $requestId);
        $itemsStatement->execute();

        $itemsResult = $itemsStatement->get_result();

        while ($item = $itemsResult->fetch_assoc()) {
            $requestItems[] = $item;
        }

        if (!empty($requestItems)) {
            foreach ($requestItems as $item) {
                documentEditInsertLineItem(
                    $documentId,
                    (string)($item['item_description'] ?? ''),
                    (float)($item['quantity'] ?? 1),
                    (string)($item['unit_name'] ?? 'each'),
                    (float)($item['unit_price'] ?? 0.00),
                    (int)($item['sort_order'] ?? 10)
                );
            }
        } else {
            $price = (float)($request['final_price'] ?? 0.00);

            if ($price <= 0) {
                $price = (float)($request['quoted_price'] ?? 0.00);
            }

            documentEditInsertLineItem(
                $documentId,
                $serviceSummary,
                1.00,
                'job',
                $price,
                10
            );
        }

        documentEditRecalculateTotals($documentId);

        $connection->commit();

        return $documentId;
    } catch (Throwable $exception) {
        $connection->rollback();
        throw $exception;
    }
}

function documentEditSaveRecord(array $postedData, array &$errors): int
{
    $documentId = (int)($postedData['document_id'] ?? 0);

    if ($documentId <= 0) {
        $errors[] = 'Document ID is missing.';
        return 0;
    }

    $existingDocument = getDocumentEditRecord($documentId);

    if ($existingDocument === null) {
        $errors[] = 'Document not found.';
        return 0;
    }

    $documentType = documentEditCleanType((string)($postedData['document_type'] ?? $existingDocument['document_type']));
    $documentNumber = documentEditText((string)($postedData['document_number'] ?? $existingDocument['document_number']));

    if ($documentNumber === '') {
        $documentNumber = documentEditGeneratedNumber($documentType);
    }

    $documentStatus = documentEditText((string)($postedData['document_status'] ?? 'Draft'));
    $issueDate = documentEditDateForInput((string)($postedData['issue_date'] ?? date('Y-m-d')));

    if ($issueDate === '') {
        $issueDate = date('Y-m-d');
    }

    $dueDate = documentEditNullableString(documentEditDateForInput((string)($postedData['due_date'] ?? '')));
    $documentTitle = documentEditText((string)($postedData['document_title'] ?? ''));

    if ($documentTitle === '') {
        $documentTitle = documentEditTypeLabel($documentType);
    }

    $serviceSummary = documentEditNullableString((string)($postedData['service_summary'] ?? ''));

    $discountAmount = documentEditDecimal((string)($postedData['discount_amount'] ?? ''));
    $taxRate = documentEditPercentToRate((string)($postedData['tax_rate_percent'] ?? ''));

    $paymentMethod = 'Not Recorded';
    $paidDate = null;
    $amountPaid = 0.00;

    if ($documentType === 'invoice') {
        $paymentMethod = documentEditText((string)($postedData['payment_method'] ?? 'Not Recorded'));
        $paidDate = documentEditNullableString(documentEditDateForInput((string)($postedData['paid_date'] ?? '')));
        $amountPaid = documentEditDecimal((string)($postedData['amount_paid'] ?? ''));

        if (!in_array($paymentMethod, documentEditPaymentMethodOptions(), true)) {
            $errors[] = 'Payment method is invalid.';
        }
    }

    if (!in_array($documentStatus, documentEditStatusOptions(), true)) {
        $errors[] = 'Document status is invalid.';
    }

    if (!empty($errors)) {
        return 0;
    }

    $connection = getDatabaseConnection();

    $statement = $connection->prepare(
        'UPDATE client_documents
         SET
            document_number = ?,
            document_type = ?,
            document_status = ?,
            payment_method = ?,
            issue_date = ?,
            due_date = ?,
            paid_date = ?,
            document_title = ?,
            service_summary = ?,
            discount_amount = ?,
            tax_rate = ?,
            amount_paid = ?
         WHERE id = ?'
    );

    $statement->bind_param(
        'sssssssssdddi',
        $documentNumber,
        $documentType,
        $documentStatus,
        $paymentMethod,
        $issueDate,
        $dueDate,
        $paidDate,
        $documentTitle,
        $serviceSummary,
        $discountAmount,
        $taxRate,
        $amountPaid,
        $documentId
    );

    $statement->execute();

    return $documentId;
}

function documentEditSaveItems(int $documentId, array $postedData): void
{
    if ($documentId <= 0) {
        return;
    }

    $connection = getDatabaseConnection();

    $items = $postedData['items'] ?? [];

    if (is_array($items)) {
        foreach ($items as $itemId => $itemData) {
            $itemId = (int)$itemId;

            if ($itemId <= 0 || !is_array($itemData)) {
                continue;
            }

            $deleteItem = !empty($itemData['delete_item']);
            $itemDescription = documentEditText((string)($itemData['item_description'] ?? ''));

            if ($deleteItem || $itemDescription === '') {
                $deleteStatement = $connection->prepare(
                    'DELETE FROM client_document_items
                     WHERE id = ? AND document_id = ?'
                );

                $deleteStatement->bind_param('ii', $itemId, $documentId);
                $deleteStatement->execute();

                continue;
            }

            $quantity = documentEditDecimal((string)($itemData['quantity'] ?? '1'));

            if ($quantity <= 0) {
                $quantity = 1.00;
            }

            $unitName = documentEditText((string)($itemData['unit_name'] ?? 'each'));

            if ($unitName === '') {
                $unitName = 'each';
            }

            $unitPrice = documentEditDecimal((string)($itemData['unit_price'] ?? '0'));
            $lineTotal = round($quantity * $unitPrice, 2);
            $sortOrder = (int)($itemData['sort_order'] ?? 0);

            $statement = $connection->prepare(
                'UPDATE client_document_items
                 SET
                    item_description = ?,
                    quantity = ?,
                    unit_name = ?,
                    unit_price = ?,
                    line_total = ?,
                    sort_order = ?
                 WHERE id = ? AND document_id = ?'
            );

            $statement->bind_param(
                'sdsddiii',
                $itemDescription,
                $quantity,
                $unitName,
                $unitPrice,
                $lineTotal,
                $sortOrder,
                $itemId,
                $documentId
            );

            $statement->execute();
        }
    }

    $newItems = $postedData['new_items'] ?? [];

    if (is_array($newItems)) {
        $sortOrder = 100;

        foreach ($newItems as $newItem) {
            if (!is_array($newItem)) {
                continue;
            }

            $newDescription = documentEditText((string)($newItem['item_description'] ?? ''));

            if ($newDescription === '') {
                continue;
            }

            $quantity = documentEditDecimal((string)($newItem['quantity'] ?? '1'));

            if ($quantity <= 0) {
                $quantity = 1.00;
            }

            $unitName = documentEditText((string)($newItem['unit_name'] ?? 'each'));

            if ($unitName === '') {
                $unitName = 'each';
            }

            $unitPrice = documentEditDecimal((string)($newItem['unit_price'] ?? '0'));

            documentEditInsertLineItem(
                $documentId,
                $newDescription,
                $quantity,
                $unitName,
                $unitPrice,
                $sortOrder
            );

            $sortOrder += 10;
        }
    }
}

function documentEditMergePostedRecord(array $document, array $postedData): array
{
    $editableFields = [
        'document_type',
        'document_number',
        'document_status',
        'payment_method',
        'issue_date',
        'due_date',
        'paid_date',
        'document_title',
        'service_summary',
    ];

    foreach ($editableFields as $key) {
        if (array_key_exists($key, $postedData)) {
            $document[$key] = $postedData[$key];
        }
    }

    $document['id'] = (int)($postedData['document_id'] ?? $document['id']);
    $document['tax_rate'] = documentEditPercentToRate((string)($postedData['tax_rate_percent'] ?? '0'));
    $document['discount_amount'] = documentEditDecimal((string)($postedData['discount_amount'] ?? '0'));
    $document['amount_paid'] = documentEditDecimal((string)($postedData['amount_paid'] ?? '0'));

    return $document;
}

function documentEditRenderItemRow(array $item): void
{
    $itemId = (int)($item['id'] ?? 0);
    ?>

    <div class="document-line-row admin-inner-panel mb-3" data-line-row data-saved-line-row>
        <input type="hidden" name="items[<?php echo escapeHtml((string)$itemId); ?>][sort_order]" value="<?php echo escapeHtml((string)($item['sort_order'] ?? 0)); ?>">
        <input type="hidden" name="items[<?php echo escapeHtml((string)$itemId); ?>][delete_item]" value="0" data-line-delete-field>

        <div class="document-line-grid">
            <div>
                <label class="form-label" for="item_description_<?php echo escapeHtml((string)$itemId); ?>">
                    Line Item
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="item_description_<?php echo escapeHtml((string)$itemId); ?>"
                    name="items[<?php echo escapeHtml((string)$itemId); ?>][item_description]"
                    value="<?php echo escapeHtml((string)($item['item_description'] ?? '')); ?>"
                    data-line-description
                >
            </div>

            <div>
                <label class="form-label" for="quantity_<?php echo escapeHtml((string)$itemId); ?>">
                    Qty
                </label>

                <input
                    type="number"
                    class="form-control"
                    id="quantity_<?php echo escapeHtml((string)$itemId); ?>"
                    name="items[<?php echo escapeHtml((string)$itemId); ?>][quantity]"
                    step="0.01"
                    min="0"
                    value="<?php echo escapeHtml(documentEditDecimalForInput($item['quantity'] ?? 1)); ?>"
                    data-line-quantity
                >
            </div>

            <div>
                <label class="form-label" for="unit_name_<?php echo escapeHtml((string)$itemId); ?>">
                    Unit
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="unit_name_<?php echo escapeHtml((string)$itemId); ?>"
                    name="items[<?php echo escapeHtml((string)$itemId); ?>][unit_name]"
                    value="<?php echo escapeHtml((string)($item['unit_name'] ?? 'each')); ?>"
                >
            </div>

            <div>
                <label class="form-label" for="unit_price_<?php echo escapeHtml((string)$itemId); ?>">
                    Unit Price
                </label>

                <input
                    type="number"
                    class="form-control"
                    id="unit_price_<?php echo escapeHtml((string)$itemId); ?>"
                    name="items[<?php echo escapeHtml((string)$itemId); ?>][unit_price]"
                    step="0.01"
                    min="0"
                    value="<?php echo escapeHtml(documentEditDecimalForInput($item['unit_price'] ?? 0)); ?>"
                    data-line-unit-price
                >
            </div>

            <div class="document-line-action">
                <button type="button" class="btn btn-outline-light remove-saved-line-item-button" data-saved-line-remove-button>
                    Remove
                </button>
            </div>
        </div>

        <p class="text-muted mb-0 mt-3">
            Line total:
            <strong data-line-total><?php echo escapeHtml(documentEditMoney($item['line_total'] ?? 0)); ?></strong>
        </p>

        <p class="document-remove-message text-muted mb-0 mt-2" data-delete-message>
            This saved line will be removed when you save.
        </p>
    </div>

    <?php
}

$documentId = (int)($_GET['id'] ?? $_POST['document_id'] ?? 0);
$requestId = (int)($_GET['request_id'] ?? 0);
$documentType = documentEditCleanType((string)($_GET['type'] ?? 'invoice'));

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $documentId <= 0 && $requestId > 0) {
    try {
        $newDocumentId = documentEditCreateFromRequest($requestId, $documentType);
        redirectTo('/admin_document_edit.php?id=' . $newDocumentId . '&created=1');
    } catch (Throwable $exception) {
        $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

        if ($showDetailedErrors) {
            $errors[] = $exception->getMessage();
        } else {
            $errors[] = 'The document could not be created from this request.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $documentId <= 0 && $requestId <= 0) {
    $errors[] = 'Open this page from a specific request or document.';
}

if (isset($_GET['created'])) {
    $messages[] = 'Document created.';
}

if (isset($_GET['saved'])) {
    $messages[] = 'Document saved.';
}

$document = documentEditDefaultRecord($documentType);
$items = [];
$statusOptions = documentEditStatusOptions();
$paymentMethodOptions = documentEditPaymentMethodOptions();
$linkedRequest = null;
$expectedRequestPrice = 0.00;

try {
    if ($documentId > 0) {
        $loadedDocument = getDocumentEditRecord($documentId);

        if ($loadedDocument === null) {
            $errors[] = 'Document not found.';
        } else {
            $document = $loadedDocument;
            $documentType = documentEditCleanType((string)$document['document_type']);
            $items = getDocumentEditItems($documentId);

            if ((int)($document['quote_request_id'] ?? 0) > 0) {
                $linkedRequest = getDocumentEditRequest((int)$document['quote_request_id']);

                if ($linkedRequest !== null) {
                    $expectedRequestPrice = (float)($linkedRequest['final_price'] ?? 0.00);

                    if ($expectedRequestPrice <= 0) {
                        $expectedRequestPrice = (float)($linkedRequest['quoted_price'] ?? 0.00);
                    }
                }
            }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_name'] ?? '') === 'save_document') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!isValidCsrfToken($submittedToken)) {
        $errors[] = 'Security token failed. Refresh the page and try again.';
    } else {
        try {
            $savedDocumentId = documentEditSaveRecord($_POST, $errors);

            if ($savedDocumentId > 0 && empty($errors)) {
                documentEditSaveItems($savedDocumentId, $_POST);
                documentEditRecalculateTotals($savedDocumentId);

                redirectTo('/admin_document_edit.php?id=' . $savedDocumentId . '&saved=1');
            }

            $document = documentEditMergePostedRecord($document, $_POST);
        } catch (Throwable $exception) {
            $document = documentEditMergePostedRecord($document, $_POST);

            $showDetailedErrors = (bool)($environmentSettings['show_detailed_errors'] ?? false);

            if ($showDetailedErrors) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'The document could not be saved.';
            }
        }
    }
}

$documentId = (int)($document['id'] ?? 0);
$documentType = documentEditCleanType((string)($document['document_type'] ?? $documentType));
$documentLabel = documentEditTypeLabel($documentType);
$isInvoice = $documentType === 'invoice';
$requestIdForLinks = (int)($document['quote_request_id'] ?? 0);

require_once PROJECT_ROOT . '/src/Layout/head.php';
require_once PROJECT_ROOT . '/src/Layout/navigation.php';
?>

<style>
    .document-line-grid {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) minmax(90px, 120px) minmax(110px, 140px) minmax(130px, 160px) minmax(95px, 120px);
        gap: 1rem;
        align-items: end;
    }

    .document-line-action {
        min-height: 38px;
        display: flex;
        align-items: center;
    }

    .document-job-grid {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) minmax(220px, 320px);
        gap: 1rem;
        align-items: end;
    }

    .document-input-preview-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(180px, 1fr));
        gap: 1rem;
        align-items: start;
    }

    .document-total-stack {
        display: grid;
        gap: 0.65rem;
        margin-top: 1rem;
    }

    .document-total-line {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        padding-bottom: 0.4rem;
    }

    .document-total-preview {
        border: 1px solid var(--kails-border-color, rgba(255, 255, 255, 0.15));
        border-radius: 0.75rem;
        padding: 1rem;
        min-height: 100%;
    }

    .document-total-preview strong {
        display: block;
        font-size: 1.75rem;
        line-height: 1.1;
    }

    .document-warning-panel {
        display: none;
    }

    .document-warning-panel.is-visible {
        display: block;
    }

    .document-remove-message {
        display: none;
    }

    .document-line-row.is-marked-for-delete {
        opacity: 0.58;
    }

    .document-line-row.is-marked-for-delete .document-remove-message {
        display: block;
    }

    .document-line-row.is-marked-for-delete input:not([type="hidden"]) {
        pointer-events: none;
    }

    @media (max-width: 991.98px) {
        .document-line-grid,
        .document-input-preview-grid,
        .document-job-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 575.98px) {
        .document-line-grid,
        .document-input-preview-grid,
        .document-job-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="site-section">
    <div class="container">
        <?php adminRenderSecurityWarning(); ?>
        <?php renderAdminCrmNavigation('requests'); ?>

        <div class="mb-4">
            <p class="kails-text-yellow fw-bold text-uppercase mb-2">
                <?php echo escapeHtml($documentLabel); ?>
            </p>

            <h1 class="fw-bold">
                <?php echo escapeHtml((string)($document['document_number'] ?? 'New Document')); ?>
            </h1>

            <p class="text-muted mb-0">
                Edit the document status, job name, itemized lines, totals, and invoice payment details.
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

        <div class="d-flex flex-wrap gap-2 mb-4">
            <?php if ($requestIdForLinks > 0): ?>
                <a href="/admin_request_detail.php?id=<?php echo escapeHtml((string)$requestIdForLinks); ?>" class="btn btn-outline-light">
                    Back to Request Detail
                </a>
            <?php else: ?>
                <a href="/admin_requests.php" class="btn btn-outline-light">
                    Back to Requests
                </a>
            <?php endif; ?>

            <?php if ($documentId > 0): ?>
                <a href="/document_print.php?id=<?php echo escapeHtml((string)$documentId); ?>" class="btn btn-outline-light" target="_blank">
                    Print <?php echo escapeHtml($documentLabel); ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($documentId > 0): ?>
            <section class="card p-4 mb-4">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <h2 class="h4 mb-2">
                            Document Summary
                        </h2>

                        <p class="kails-text-yellow fw-bold text-uppercase mb-1">
                            <?php echo escapeHtml($documentLabel); ?>
                        </p>

                        <h3 class="h5 mb-2">
                            <?php echo escapeHtml((string)($document['document_title'] ?? $documentLabel)); ?>
                        </h3>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="<?php echo escapeHtml(documentEditStatusClass((string)($document['document_status'] ?? 'Draft'))); ?>">
                                <?php echo escapeHtml((string)($document['document_status'] ?? 'Draft')); ?>
                            </span>
                        </div>

                        <dl class="row mb-0">
                            <dt class="col-sm-4">Customer</dt>
                            <dd class="col-sm-8"><?php echo escapeHtml((string)($document['client_name'] ?? '—')); ?></dd>

                            <dt class="col-sm-4">Overall Job</dt>
                            <dd class="col-sm-8">
                                <?php echo trim((string)($document['service_summary'] ?? '')) !== '' ? escapeHtml((string)$document['service_summary']) : '—'; ?>
                            </dd>

                            <dt class="col-sm-4">Issue Date</dt>
                            <dd class="col-sm-8"><?php echo escapeHtml(documentEditDateDisplay($document['issue_date'] ?? null)); ?></dd>

                            <dt class="col-sm-4">Updated</dt>
                            <dd class="col-sm-8"><?php echo escapeHtml(documentEditDateTimeDisplay($document['updated_at'] ?? null)); ?></dd>

                            <?php if ($expectedRequestPrice > 0): ?>
                                <dt class="col-sm-4">Request Price</dt>
                                <dd class="col-sm-8"><?php echo escapeHtml(documentEditMoney($expectedRequestPrice)); ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>

                    <div class="col-lg-4">
                        <div class="admin-inner-panel h-100">
                            <h2 class="h5 mb-3">
                                Live Totals
                            </h2>

                            <div class="request-summary-meta">
                                <div><strong>Subtotal:</strong> <span data-preview-subtotal><?php echo escapeHtml(documentEditMoney($document['subtotal_amount'] ?? 0)); ?></span></div>
                                <div><strong>Discount:</strong> <span data-preview-discount><?php echo escapeHtml(documentEditMoney($document['discount_amount'] ?? 0)); ?></span></div>
                                <div><strong>Tax:</strong> <span data-preview-tax><?php echo escapeHtml(documentEditMoney($document['tax_amount'] ?? 0)); ?></span></div>
                                <div><strong>Total:</strong> <span data-preview-total><?php echo escapeHtml(documentEditMoney($document['total_amount'] ?? 0)); ?></span></div>

                                <?php if ($isInvoice): ?>
                                    <div><strong>Payment Received:</strong> <span data-preview-paid><?php echo escapeHtml(documentEditMoney($document['amount_paid'] ?? 0)); ?></span></div>
                                    <div><strong>Balance Due:</strong> <span data-preview-balance><?php echo escapeHtml(documentEditMoney($document['balance_due'] ?? 0)); ?></span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <form
                method="post"
                action="/admin_document_edit.php"
                id="documentEditForm"
                data-document-type="<?php echo escapeHtml($documentType); ?>"
                data-expected-request-price="<?php echo escapeHtml(number_format($expectedRequestPrice, 2, '.', '')); ?>"
            >
                <input type="hidden" name="form_name" value="save_document">
                <input type="hidden" name="csrf_token" value="<?php echo escapeHtml(getCsrfToken()); ?>">
                <input type="hidden" name="document_id" value="<?php echo escapeHtml((string)$documentId); ?>">
                <input type="hidden" name="document_type" value="<?php echo escapeHtml($documentType); ?>">

                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Document Details
                    </h2>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">
                                Type
                            </label>

                            <div class="form-control">
                                <?php echo escapeHtml($documentLabel); ?>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label for="document_status" class="form-label">
                                Status
                            </label>

                            <select class="form-control" id="document_status" name="document_status">
                                <?php foreach ($statusOptions as $statusOption): ?>
                                    <option
                                        value="<?php echo escapeHtml($statusOption); ?>"
                                        <?php echo documentEditSelectedValue((string)($document['document_status'] ?? 'Draft'), $statusOption); ?>
                                    >
                                        <?php echo escapeHtml($statusOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="document_number" class="form-label">
                                Document Number
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="document_number"
                                name="document_number"
                                value="<?php echo escapeHtml((string)($document['document_number'] ?? '')); ?>"
                            >
                        </div>

                        <div class="col-md-3">
                            <label for="issue_date" class="form-label">
                                Issue Date
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                id="issue_date"
                                name="issue_date"
                                value="<?php echo escapeHtml(documentEditDateForInput($document['issue_date'] ?? date('Y-m-d'))); ?>"
                            >
                        </div>

                        <div class="col-md-8">
                            <label for="document_title" class="form-label">
                                Document Title
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="document_title"
                                name="document_title"
                                value="<?php echo escapeHtml((string)($document['document_title'] ?? '')); ?>"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label for="due_date" class="form-label">
                                Due Date
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                id="due_date"
                                name="due_date"
                                value="<?php echo escapeHtml(documentEditDateForInput($document['due_date'] ?? null)); ?>"
                            >
                        </div>

                        <?php if ($isInvoice): ?>
                            <div class="col-md-4">
                                <label for="paid_date" class="form-label">
                                    Paid Date
                                </label>

                                <input
                                    type="date"
                                    class="form-control"
                                    id="paid_date"
                                    name="paid_date"
                                    value="<?php echo escapeHtml(documentEditDateForInput($document['paid_date'] ?? null)); ?>"
                                >
                            </div>

                            <div class="col-md-4">
                                <label for="payment_method" class="form-label">
                                    Payment Method
                                </label>

                                <select class="form-control" id="payment_method" name="payment_method">
                                    <?php foreach ($paymentMethodOptions as $paymentMethodOption): ?>
                                        <option
                                            value="<?php echo escapeHtml($paymentMethodOption); ?>"
                                            <?php echo documentEditSelectedValue((string)($document['payment_method'] ?? 'Not Recorded'), $paymentMethodOption); ?>
                                        >
                                            <?php echo escapeHtml($paymentMethodOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card p-4 mb-4">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="h4 mb-2">
                                Job and Line Items
                            </h2>

                            <p class="text-muted mb-0">
                                The job name labels the work. The itemized lines are the priced rows that make up the <?php echo escapeHtml(strtolower($documentLabel)); ?> total.
                            </p>
                        </div>

                        <div>
                            <button type="button" class="btn btn-outline-light" id="addLineItemButton">
                                Add Another Line
                            </button>
                        </div>
                    </div>

                    <div class="admin-inner-panel mb-4">
                        <div class="document-job-grid">
                            <div>
                                <label for="service_summary" class="form-label">
                                    Overall Job Name
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="service_summary"
                                    name="service_summary"
                                    value="<?php echo escapeHtml((string)($document['service_summary'] ?? '')); ?>"
                                    placeholder="Example: Yard clean up"
                                >

                                <div class="form-text">
                                    This names the overall job. It does not add money by itself.
                                </div>
                            </div>

                            <div class="document-total-preview">
                                <span class="text-muted d-block mb-1">
                                    Itemized Total Preview
                                </span>

                                <strong data-job-total-preview>
                                    <?php echo escapeHtml(documentEditMoney($document['subtotal_amount'] ?? 0)); ?>
                                </strong>

                                <span class="text-muted d-block mt-1">
                                    Sum of all active line items before discount and tax.
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="card p-3 mb-3 document-warning-panel" id="lineItemWarning">
                        <p class="mb-0">
                            <strong class="kails-text-yellow">Check line items:</strong>
                            The itemized subtotal is <span data-warning-subtotal>$0.00</span>, but the request price is <span data-warning-expected>$0.00</span>.
                            This is fine if you intentionally changed the price, but otherwise update the line items.
                        </p>
                    </div>

                    <?php if (empty($items)): ?>
                        <div class="card p-3 mb-3">
                            No saved line items have been added yet. Use <strong>Add Another Line</strong> to add the first priced row.
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php documentEditRenderItemRow($item); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div id="newLineItemsContainer"></div>

                    <p class="text-muted mb-0">
                        Blank new lines are ignored when you save.
                    </p>
                </section>

                <section class="card p-4 mb-4">
                    <h2 class="h4 mb-3">
                        Totals<?php echo $isInvoice ? ' and Payment' : ''; ?>
                    </h2>

                    <div class="document-input-preview-grid mb-4">
                        <div>
                            <label for="discount_amount" class="form-label">
                                Discount Amount
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                id="discount_amount"
                                name="discount_amount"
                                step="0.01"
                                min="0"
                                value="<?php echo escapeHtml(documentEditDecimalForInput($document['discount_amount'] ?? 0)); ?>"
                                data-discount-input
                            >

                            <div class="document-total-stack">
                                <div class="document-total-line">
                                    <strong>Subtotal</strong>
                                    <span data-subtotal-output><?php echo escapeHtml(documentEditMoney($document['subtotal_amount'] ?? 0)); ?></span>
                                </div>

                                <div class="document-total-line">
                                    <strong>Discount</strong>
                                    <span data-discount-output><?php echo escapeHtml(documentEditMoney($document['discount_amount'] ?? 0)); ?></span>
                                </div>

                                <div class="document-total-line">
                                    <strong><?php echo $isInvoice ? 'Invoice Total' : 'Quote Total'; ?></strong>
                                    <span data-total-output><?php echo escapeHtml(documentEditMoney($document['total_amount'] ?? 0)); ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="tax_rate_percent" class="form-label">
                                Tax Rate %
                            </label>

                            <input
                                type="number"
                                class="form-control"
                                id="tax_rate_percent"
                                name="tax_rate_percent"
                                step="0.01"
                                min="0"
                                value="<?php echo escapeHtml(documentEditRateToPercent($document['tax_rate'] ?? 0)); ?>"
                                data-tax-rate-input
                            >

                            <div class="document-total-stack">
                                <div class="document-total-line">
                                    <strong>Taxable Amount</strong>
                                    <span data-taxable-output>$0.00</span>
                                </div>

                                <div class="document-total-line">
                                    <strong>Tax</strong>
                                    <span data-tax-output><?php echo escapeHtml(documentEditMoney($document['tax_amount'] ?? 0)); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if ($isInvoice): ?>
                            <div>
                                <label for="amount_paid" class="form-label">
                                    Payment Received
                                </label>

                                <input
                                    type="number"
                                    class="form-control"
                                    id="amount_paid"
                                    name="amount_paid"
                                    step="0.01"
                                    min="0"
                                    value="<?php echo escapeHtml(documentEditDecimalForInput($document['amount_paid'] ?? 0)); ?>"
                                    data-amount-paid-input
                                >

                                <div class="form-text">
                                    Money already paid toward this invoice. Leave 0.00 if unpaid.
                                </div>

                                <div class="document-total-stack">
                                    <div class="document-total-line">
                                        <strong>Payment Received</strong>
                                        <span data-paid-output><?php echo escapeHtml(documentEditMoney($document['amount_paid'] ?? 0)); ?></span>
                                    </div>

                                    <div class="document-total-line">
                                        <strong>Balance Due</strong>
                                        <span data-balance-output><?php echo escapeHtml(documentEditMoney($document['balance_due'] ?? 0)); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card p-4">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <div>
                            <h2 class="h5 mb-1">
                                Save <?php echo escapeHtml($documentLabel); ?>
                            </h2>

                            <p class="text-muted mb-0">
                                Saving stores the current job name, line items, tax, total<?php echo $isInvoice ? ', payment received, and balance due' : ''; ?>.
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($requestIdForLinks > 0): ?>
                                <a href="/admin_request_detail.php?id=<?php echo escapeHtml((string)$requestIdForLinks); ?>" class="btn btn-outline-light">
                                    Cancel
                                </a>
                            <?php else: ?>
                                <a href="/admin_requests.php" class="btn btn-outline-light">
                                    Cancel
                                </a>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-light">
                                Save <?php echo escapeHtml($documentLabel); ?>
                            </button>
                        </div>
                    </div>
                </section>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('documentEditForm');
    const addLineItemButton = document.getElementById('addLineItemButton');
    const newLineItemsContainer = document.getElementById('newLineItemsContainer');

    function parseMoney(value) {
        const cleanValue = String(value || '').replace(/[$,%\s,]/g, '');
        const parsedValue = Number.parseFloat(cleanValue);

        if (Number.isNaN(parsedValue)) {
            return 0;
        }

        return parsedValue;
    }

    function formatMoney(value) {
        return '$' + Number(value || 0).toFixed(2);
    }

    function setText(selector, value) {
        const elements = document.querySelectorAll(selector);

        elements.forEach(function (element) {
            element.textContent = value;
        });
    }

    function calculateLineRow(row) {
        const descriptionInput = row.querySelector('[data-line-description]');
        const quantityInput = row.querySelector('[data-line-quantity]');
        const unitPriceInput = row.querySelector('[data-line-unit-price]');
        const deleteField = row.querySelector('[data-line-delete-field]');
        const lineTotalOutput = row.querySelector('[data-line-total]');

        const description = descriptionInput ? String(descriptionInput.value || '').trim() : '';

        if (deleteField && deleteField.value === '1') {
            if (lineTotalOutput) {
                lineTotalOutput.textContent = formatMoney(0);
            }

            return 0;
        }

        if (description === '') {
            if (lineTotalOutput) {
                lineTotalOutput.textContent = formatMoney(0);
            }

            return 0;
        }

        const quantity = parseMoney(quantityInput ? quantityInput.value : 0);
        const unitPrice = parseMoney(unitPriceInput ? unitPriceInput.value : 0);
        const lineTotal = Math.max(0, quantity) * Math.max(0, unitPrice);

        if (lineTotalOutput) {
            lineTotalOutput.textContent = formatMoney(lineTotal);
        }

        return lineTotal;
    }

    function updateLiveTotals() {
        if (!form) {
            return;
        }

        const lineRows = Array.from(document.querySelectorAll('[data-line-row]'));
        let subtotal = 0;

        lineRows.forEach(function (row) {
            subtotal += calculateLineRow(row);
        });

        const discountInput = document.querySelector('[data-discount-input]');
        const taxRateInput = document.querySelector('[data-tax-rate-input]');
        const amountPaidInput = document.querySelector('[data-amount-paid-input]');

        let discount = parseMoney(discountInput ? discountInput.value : 0);

        if (discount < 0) {
            discount = 0;
        }

        if (discount > subtotal) {
            discount = subtotal;
        }

        const taxRate = Math.max(0, parseMoney(taxRateInput ? taxRateInput.value : 0)) / 100;
        const taxableAmount = Math.max(0, subtotal - discount);
        const tax = taxableAmount * taxRate;
        const total = taxableAmount + tax;
        const isInvoice = form.dataset.documentType === 'invoice';
        const paid = isInvoice ? Math.max(0, parseMoney(amountPaidInput ? amountPaidInput.value : 0)) : 0;
        const balance = Math.max(0, total - paid);

        setText('[data-job-total-preview]', formatMoney(subtotal));
        setText('[data-subtotal-output]', formatMoney(subtotal));
        setText('[data-discount-output]', formatMoney(discount));
        setText('[data-taxable-output]', formatMoney(taxableAmount));
        setText('[data-tax-output]', formatMoney(tax));
        setText('[data-total-output]', formatMoney(total));
        setText('[data-paid-output]', formatMoney(paid));
        setText('[data-balance-output]', formatMoney(balance));

        setText('[data-preview-subtotal]', formatMoney(subtotal));
        setText('[data-preview-discount]', formatMoney(discount));
        setText('[data-preview-tax]', formatMoney(tax));
        setText('[data-preview-total]', formatMoney(total));
        setText('[data-preview-paid]', formatMoney(paid));
        setText('[data-preview-balance]', formatMoney(balance));

        const expectedRequestPrice = parseMoney(form.dataset.expectedRequestPrice || 0);
        const warningPanel = document.getElementById('lineItemWarning');

        if (warningPanel) {
            const shouldShowWarning = expectedRequestPrice > 0 && Math.abs(subtotal - expectedRequestPrice) >= 0.01;

            warningPanel.classList.toggle('is-visible', shouldShowWarning);

            setText('[data-warning-subtotal]', formatMoney(subtotal));
            setText('[data-warning-expected]', formatMoney(expectedRequestPrice));
        }
    }

    function markSavedLineForDeletion(button) {
        const row = button.closest('[data-saved-line-row]');

        if (!row) {
            return;
        }

        const deleteField = row.querySelector('[data-line-delete-field]');

        if (!deleteField) {
            return;
        }

        const isCurrentlyMarked = deleteField.value === '1';
        const shouldDelete = !isCurrentlyMarked;

        deleteField.value = shouldDelete ? '1' : '0';
        row.classList.toggle('is-marked-for-delete', shouldDelete);
        button.textContent = shouldDelete ? 'Undo Remove' : 'Remove';

        updateLiveTotals();
    }

    if (addLineItemButton && newLineItemsContainer) {
        let newLineItemIndex = 0;

        addLineItemButton.addEventListener('click', function () {
            const rowWrapper = document.createElement('div');
            rowWrapper.className = 'document-line-row admin-inner-panel mb-3';
            rowWrapper.setAttribute('data-new-line-item', '');
            rowWrapper.setAttribute('data-line-row', '');

            rowWrapper.innerHTML = `
                <div class="document-line-grid">
                    <div>
                        <label class="form-label">
                            Line Item
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="new_items[${newLineItemIndex}][item_description]"
                            placeholder="Example: Lawn mowing"
                            data-line-description
                        >
                    </div>

                    <div>
                        <label class="form-label">
                            Qty
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            name="new_items[${newLineItemIndex}][quantity]"
                            step="0.01"
                            min="0"
                            value="1.00"
                            data-line-quantity
                        >
                    </div>

                    <div>
                        <label class="form-label">
                            Unit
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="new_items[${newLineItemIndex}][unit_name]"
                            value="each"
                        >
                    </div>

                    <div>
                        <label class="form-label">
                            Unit Price
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            name="new_items[${newLineItemIndex}][unit_price]"
                            step="0.01"
                            min="0"
                            data-line-unit-price
                        >
                    </div>

                    <div class="document-line-action">
                        <button type="button" class="btn btn-outline-light remove-new-line-item-button">
                            Remove
                        </button>
                    </div>
                </div>

                <p class="text-muted mb-0 mt-3">
                    Line total:
                    <strong data-line-total>$0.00</strong>
                </p>
            `;

            newLineItemsContainer.appendChild(rowWrapper);
            newLineItemIndex++;
            updateLiveTotals();
        });

        newLineItemsContainer.addEventListener('click', function (event) {
            const target = event.target;

            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (!target.classList.contains('remove-new-line-item-button')) {
                return;
            }

            const row = target.closest('[data-new-line-item]');

            if (row) {
                row.remove();
                updateLiveTotals();
            }
        });
    }

    document.addEventListener('click', function (event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (!target.matches('[data-saved-line-remove-button]')) {
            return;
        }

        markSavedLineForDeletion(target);
    });

    document.addEventListener('input', function (event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (
            target.matches('[data-line-description]')
            || target.matches('[data-line-quantity]')
            || target.matches('[data-line-unit-price]')
            || target.matches('[data-discount-input]')
            || target.matches('[data-tax-rate-input]')
            || target.matches('[data-amount-paid-input]')
        ) {
            updateLiveTotals();
        }
    });

    updateLiveTotals();

    if (!form) {
        return;
    }

    let formChanged = false;

    form.addEventListener('change', function () {
        formChanged = true;
    });

    form.addEventListener('input', function () {
        formChanged = true;
    });

    form.addEventListener('click', function (event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (
            target.matches('[data-saved-line-remove-button]')
            || target.matches('.remove-new-line-item-button')
            || target.matches('#addLineItemButton')
        ) {
            formChanged = true;
        }
    });

    form.addEventListener('submit', function () {
        formChanged = false;
    });

    window.addEventListener('beforeunload', function (event) {
        if (!formChanged) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    });
});
</script>

<?php
require_once PROJECT_ROOT . '/src/Layout/footer.php';