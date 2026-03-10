<?php
/**
 * backend/ajax/confirm_submission.php
 * User confirm screen par details verify karke proceed kare to
 * form-submitted confirmation email bhejna.
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../forms/send_email.php';
require_once __DIR__ . '/../payments/documents.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request.');
}

function ensureFormAccessTokenTable(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS `form_access_tokens` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `traveller_id` int(10) unsigned NOT NULL,
        `application_id` int(10) unsigned NOT NULL,
        `token` varchar(64) NOT NULL,
        `form_number` varchar(50) DEFAULT NULL,
        `form_country` varchar(50) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `expires_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `token` (`token`),
        KEY `traveller_id` (`traveller_id`),
        KEY `application_id` (`application_id`),
        CONSTRAINT `form_access_tokens_ibfk_1` FOREIGN KEY (`traveller_id`) REFERENCES `travellers` (`id`) ON DELETE CASCADE,
        CONSTRAINT `form_access_tokens_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    jsonResponse(false, 'Security token invalid. Please refresh and try again.');
}

$applicationId = (int)($_SESSION['application_id'] ?? 0);
if ($applicationId <= 0) {
    jsonResponse(false, 'Session expired. Please start again.');
}

$db = getDB();

$appStmt = $db->prepare("SELECT reference, total_travellers FROM applications WHERE id=:id LIMIT 1");
$appStmt->execute([':id' => $applicationId]);
$app = (array)$appStmt->fetch();
if (empty($app)) {
    jsonResponse(false, 'Application not found.');
}

$reference = (string)($app['reference'] ?? ($_SESSION['application_ref'] ?? ''));
$totalTravellers = max(1, (int)($app['total_travellers'] ?? 1));

// Ensure sab travellers declaration tak complete ho chuke hon.
$doneStmt = $db->prepare("SELECT COUNT(*) FROM travellers WHERE application_id=:id AND decl_accurate=1 AND decl_terms=1 AND step_completed='declaration'");
$doneStmt->execute([':id' => $applicationId]);
$doneCount = (int)$doneStmt->fetchColumn();
if ($doneCount < $totalTravellers) {
    jsonResponse(false, 'Please complete all traveller details before confirmation.');
}

$travellersStmt = $db->prepare("SELECT * FROM travellers WHERE application_id=:id ORDER BY traveller_number");
$travellersStmt->execute([':id' => $applicationId]);
$travellers = $travellersStmt->fetchAll();
if (empty($travellers)) {
    jsonResponse(false, 'Traveller details not found.');
}

ensureFormAccessTokenTable($db);

$primaryTraveller = $travellers[0];
$accessToken = bin2hex(random_bytes(24)); // 48 chars hex

// Insert the token for the primary traveller
$tokenStmt = $db->prepare(
    "INSERT INTO form_access_tokens (traveller_id, application_id, token, form_number, form_country)
     VALUES (:traveller_id, :application_id, :token, :form_number, :form_country)"
);
$tokenStmt->execute([
    ':traveller_id' => $primaryTraveller['id'],
    ':application_id' => $applicationId,
    ':token' => $accessToken,
    ':form_number' => $reference,
    ':form_country' => (string)($_SESSION['form_country'] ?? 'Canada'),
]);

ensureSystemEmailLogTable($db);
$alreadySentStmt = $db->prepare("SELECT COUNT(*) FROM system_email_logs WHERE application_id=:application_id AND email_type='form_submitted' AND send_status='sent'");
$alreadySentStmt->execute([':application_id' => $applicationId]);
$alreadySent = (int)$alreadySentStmt->fetchColumn() > 0;

if ($alreadySent) {
    jsonResponse(true, 'Details confirmed. Confirmation email already sent.', ['email_sent' => false, 'already_sent' => true]);
}

$docs = generateFormDetailsDocument($db, $applicationId, $reference);
$primary = $travellers[0];
$country = (string)($_SESSION['form_country'] ?? 'Canada');

[$sent, $mailError] = sendFormSubmittedEmail([
    'reference' => $reference,
    'country' => $country,
    'primary_email' => $primary['email'] ?? '',
    'primary_name' => trim(($primary['first_name'] ?? '') . ' ' . ($primary['last_name'] ?? '')),
    'access_token' => $accessToken,
], $travellers, [
    ['path' => $docs['form_abs'], 'name' => 'form-details-' . $reference . '.pdf'],
]);

logSystemEmail(
    $db,
    $applicationId,
    $reference,
    'form_submitted',
    (string)($primary['email'] ?? ''),
    'Application Received | Ref ' . $reference,
    $sent,
    $mailError,
    $docs['form_rel']
);

if (!$sent) {
    jsonResponse(true, 'Details confirmed. Email could not be sent right now.', [
        'email_sent' => false,
        'already_sent' => false,
        'email_error' => $mailError ?: 'SMTP error.',
    ]);
}

jsonResponse(true, 'Details confirmed. Confirmation email sent successfully.', [
    'email_sent' => true,
    'already_sent' => false,
]);
