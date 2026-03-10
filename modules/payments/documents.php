<!-- C:\xampp\htdocs\Morgill_eTA-2\modules\payments\documents.php -->
<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../core/bootstrap.php';
@include_once __DIR__ . '/../../vendor/autoload.php';

function ensurePaymentDocumentTable(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS payment_documents (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        application_id INT UNSIGNED NOT NULL,
        payment_id VARCHAR(100) NOT NULL,
        reference VARCHAR(50) NOT NULL,
        receipt_file VARCHAR(255) NOT NULL,
        form_pdf_file VARCHAR(255) DEFAULT NULL,
        amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        currency VARCHAR(10) NOT NULL DEFAULT 'USD',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payment_docs_app (application_id),
        INDEX idx_payment_docs_reference (reference),
        CONSTRAINT fk_payment_docs_app FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function buildAbsolutePath(string $relativePath): string {
    // Go up two levels from /modules/payments to the project root.
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
}
function ensureDir(string $relativeDir): void {
    $abs = buildAbsolutePath($relativeDir);
    if (!is_dir($abs)) {
        mkdir($abs, 0755, true);
    }
}

function getPdfBaseCss(): string {
    return "
        @page { margin: 100px 50px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #333; }
        .header { position: fixed; top: -70px; left: 0px; right: 0px; height: 50px; text-align: center; }
        .header .brand { font-size: 10pt; color: #555; }
        .header h1 { margin: 0; font-size: 18pt; color: #000; }
        .footer { position: fixed; bottom: -50px; left: 0px; right: 0px; height: 50px; font-size: 8pt; text-align: center; color: #777; }
        .footer .page:after { content: counter(page, decimal); }
        .content-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .content-table th, .content-table td { border: 1px solid #ddd; padding: 8px; text-align: left; word-wrap: break-word; }
        .content-table th { background-color: #f2f2f2; font-weight: bold; }
        .summary-table { width: 100%; margin-bottom: 20px; }
        .summary-table td { padding: 4px 0; }
        .summary-table .label { font-weight: bold; width: 180px; }
        .section-title { font-size: 14pt; font-weight: bold; color: #000; border-bottom: 2px solid #333; padding-bottom: 5px; margin-top: 25px; margin-bottom: 15px; }
        .page-break { page-break-after: always; }
        .signature-block { margin-top: 80px; }
        .signature-line { border-top: 1px solid #000; width: 250px; margin-top: 40px; }
        .signature-label { font-size: 9pt; color: #555; }
    ";
}

function buildPdfHtml(string $title, string $brandName, string $contentHtml): string {
    $css = getPdfBaseCss();
    $year = date('Y');
    $brandNameEsc = htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$titleEsc}</title>
    <style>{$css}</style>
</head>
<body>
    <div class="header">
        <div class="brand">{$brandNameEsc}</div>
        <h1>{$titleEsc}</h1>
    </div>
    <div class="footer">
        &copy; {$year} {$brandNameEsc}. All rights reserved. | Page <span class="page"></span>
    </div>
    <main>
        {$contentHtml}
    </main>
</body>
</html>
HTML;
}

function generatePdfWithDompdf(string $absolutePath, string $htmlContent): void {
    if (!class_exists(Dompdf::class)) {
        error_log('Dompdf class not found. Please run "composer require dompdf/dompdf".');
        // Fallback to a simple text file
        file_put_contents($absolutePath, strip_tags($htmlContent));
        return;
    }

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($htmlContent);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    file_put_contents($absolutePath, $dompdf->output());
}

function getReceiptHtml(string $reference, string $paymentId, int $applicationId, float $amount, string $currency, array $application): string {
    $amountFormatted = number_format($amount, 2) . ' ' . strtoupper($currency);
    $date = date('Y-m-d H:i:s');
    return <<<HTML
<table class="summary-table">
    <tr><td class="label">Reference Number:</td><td>{$reference}</td></tr>
    <tr><td class="label">Payment ID:</td><td>{$paymentId}</td></tr>
    <tr><td class="label">Application ID:</td><td>{$applicationId}</td></tr>
    <tr><td class="label">Amount Paid:</td><td>{$amountFormatted}</td></tr>
    <tr><td class="label">Travel Mode:</td><td>{$application['travel_mode']}</td></tr>
    <tr><td class="label">Total Travellers:</td><td>{$application['total_travellers']}</td></tr>
    <tr><td class="label">Date of Payment:</td><td>{$date}</td></tr>
</table>
<p>This document confirms the receipt of your payment. Your application is now being processed.</p>
HTML;
}

function getFormDetailsHtml(string $reference, int $applicationId, array $application, array $travellers, bool $isSubmissionDoc = false): string {
    $html = '<table class="summary-table">';
    $html .= '<tr><td class="label">Reference Number:</td><td>' . htmlspecialchars($reference) . '</td></tr>';
    $html .= '<tr><td class="label">Application ID:</td><td>' . $applicationId . '</td></tr>';
    $html .= '<tr><td class="label">Status:</td><td>' . htmlspecialchars(ucfirst($application['status'] ?? '-')) . '</td></tr>';
    $html .= '<tr><td class="label">Travel Mode:</td><td>' . htmlspecialchars(ucfirst($application['travel_mode'] ?? '-')) . '</td></tr>';
    $html .= '<tr><td class="label">Total Travellers:</td><td>' . htmlspecialchars($application['total_travellers'] ?? '-') . '</td></tr>';
    if ($isSubmissionDoc) {
        $html .= '<tr><td class="label">Submitted At:</td><td>' . date('Y-m-d H:i:s') . '</td></tr>';
    }
    $html .= '</table>';

    foreach ($travellers as $idx => $t) {
        $n = $idx + 1;
        $fullName = htmlspecialchars(trim(($t['first_name'] ?? '') . ' ' . ($t['middle_name'] ?? '') . ' ' . ($t['last_name'] ?? '')));
        $html .= '<div class="section-title">Traveller ' . $n . ': ' . $fullName . '</div>';
        $html .= '<table class="content-table">';
        $html .= '<tr><th>Field</th><th>Details</th></tr>';
        $html .= '<tr><td>Email</td><td>' . htmlspecialchars($t['email'] ?? '-') . '</td></tr>';
        $html .= '<tr><td>Phone</td><td>' . htmlspecialchars($t['phone'] ?? '-') . '</td></tr>';
        $html .= '<tr><td>Date of Birth</td><td>' . htmlspecialchars($t['date_of_birth'] ?? '-') . '</td></tr>';
        $html .= '<tr><td>Gender</td><td>' . htmlspecialchars(ucfirst($t['gender'] ?? '-')) . '</td></tr>';
        $html .= '<tr><td>Nationality</td><td>' . htmlspecialchars($t['nationality'] ?? '-') . '</td></tr>';
        $html .= '<tr><td>Passport Number</td><td>' . htmlspecialchars($t['passport_number'] ?? '-') . '</td></tr>';
        $html .= '<tr><td>Passport Expiry</td><td>' . htmlspecialchars($t['passport_expiry'] ?? '-') . '</td></tr>';
        $html .= '<tr><td>Residential Address</td><td>' . htmlspecialchars(trim(($t['address_line'] ?? '') . ' ' . ($t['street_number'] ?? ''))) . '</td></tr>';
        $html .= '<tr><td>City / Country</td><td>' . htmlspecialchars(($t['city'] ?? '-') . ' / ' . ($t['country'] ?? '-')) . '</td></tr>';
        $html .= '<tr><td>Occupation</td><td>' . htmlspecialchars($t['occupation'] ?? '-') . '</td></tr>';
        $html .= '</table>';
    }

    $html .= '<div class="signature-block">';
    $html .= '<p>I, the applicant, hereby declare that the information provided is true, complete, and correct to the best of my knowledge and belief.</p>';
    $html .= '<div class="signature-line"></div>';
    $html .= '<div class="signature-label">Signature</div>';
    $html .= '</div>';

    return $html;
}

function generatePaymentDocuments(PDO $db, int $applicationId, string $reference, string $paymentId, float $amount, string $currency = 'USD'): array {
    ensureDir('storage/receipts');
    ensureDir('storage/forms');
    $travellersStmt = $db->prepare("SELECT * FROM travellers WHERE application_id = :id ORDER BY traveller_number");
    $travellersStmt->execute([':id' => $applicationId]);
    $travellers = $travellersStmt->fetchAll();
    $appStmt = $db->prepare("SELECT * FROM applications WHERE id = :id LIMIT 1");
    $appStmt->execute([':id' => $applicationId]);
    $application = (array)$appStmt->fetch();
    $safeRef = preg_replace('/[^A-Za-z0-9\-]/', '', $reference) ?: ('APP-' . $applicationId);
    $stamp = date('YmdHis');
    $receiptRel = 'storage/receipts/receipt-' . $safeRef . '-' . $stamp . '.pdf';
    $formRel = 'storage/forms/form-' . $safeRef . '-' . $stamp . '.pdf';

    $brandName = defined('FROM_NAME') && FROM_NAME !== '' ? FROM_NAME : 'Application Team';

    // Generate Receipt PDF
    $receiptHtmlContent = getReceiptHtml($reference, $paymentId, $applicationId, $amount, $currency, $application);
    $receiptFullHtml = buildPdfHtml('Payment Receipt', $brandName, $receiptHtmlContent);
    generatePdfWithDompdf(buildAbsolutePath($receiptRel), $receiptFullHtml);

    // Generate Form PDF
    $formHtmlContent = getFormDetailsHtml($reference, $applicationId, $application, $travellers);
    $formFullHtml = buildPdfHtml('Application Form Details', $brandName, $formHtmlContent);
    generatePdfWithDompdf(buildAbsolutePath($formRel), $formFullHtml);

    return [
        'receipt_rel' => $receiptRel,
        'form_rel' => $formRel,
        'receipt_abs' => buildAbsolutePath($receiptRel),
        'form_abs' => buildAbsolutePath($formRel),
    ];
}
function generateFormDetailsDocument(PDO $db, int $applicationId, string $reference): array {
    ensureDir('storage/forms');
    $travellersStmt = $db->prepare("SELECT * FROM travellers WHERE application_id = :id ORDER BY traveller_number");
    $travellersStmt->execute([':id' => $applicationId]);
    $travellers = $travellersStmt->fetchAll();
    $appStmt = $db->prepare("SELECT * FROM applications WHERE id = :id LIMIT 1");
    $appStmt->execute([':id' => $applicationId]);
    $application = (array)$appStmt->fetch();
    $safeRef = preg_replace('/[^A-Za-z0-9\-]/', '', $reference) ?: ('APP-' . $applicationId);
    $stamp = date('YmdHis');
    $formRel = 'storage/forms/form-submission-' . $safeRef . '-' . $stamp . '.pdf';

    $brandName = defined('FROM_NAME') && FROM_NAME !== '' ? FROM_NAME : 'Application Team';
    $formHtmlContent = getFormDetailsHtml($reference, $applicationId, $application, $travellers, true);
    $formFullHtml = buildPdfHtml('Application Submission Summary', $brandName, $formHtmlContent);
    generatePdfWithDompdf(buildAbsolutePath($formRel), $formFullHtml);

    return [
        'form_rel' => $formRel,
        'form_abs' => buildAbsolutePath($formRel),
    ];
}
