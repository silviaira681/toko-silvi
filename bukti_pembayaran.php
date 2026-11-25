<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAuth();

$transactionId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$transactionId || $transactionId < 1) {
    http_response_code(400);
    exit('Nomor transaksi tidak valid.');
}

try {
    $pdo = tokokuPdo();
    ensurePaymentMethodSchema($pdo);
    ensurePaymentVerificationSchema($pdo);
    $statement = $pdo->prepare(
        'SELECT id_pelanggan, bukti_pembayaran
         FROM tbl_transaksi
         WHERE id_transaksi = ?
         LIMIT 1'
    );
    $statement->execute([$transactionId]);
    $transaction = $statement->fetch();
} catch (PDOException $exception) {
    error_log('Tokoku payment proof read error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Bukti pembayaran belum dapat dimuat. Periksa koneksi database.');
}

if (!$transaction) {
    http_response_code(404);
    exit('Transaksi tidak ditemukan.');
}

if (
    authRole() === 'pelanggan'
    && (int) (authUser()['id_pelanggan'] ?? 0) !== (int) $transaction['id_pelanggan']
) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ke bukti pembayaran ini.');
}

$proofPath = (string) ($transaction['bukti_pembayaran'] ?? '');
if (!preg_match('~\Auploads/payment_proofs/[a-f0-9]{32}\.(jpg|png|webp|pdf)\z~', $proofPath, $matches)) {
    http_response_code(404);
    exit('Bukti pembayaran tidak ditemukan.');
}

$absolutePath = __DIR__ . DIRECTORY_SEPARATOR
    . str_replace('/', DIRECTORY_SEPARATOR, $proofPath);
if (!is_file($absolutePath)) {
    http_response_code(404);
    exit('File bukti pembayaran tidak ditemukan.');
}

$contentTypes = [
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'pdf' => 'application/pdf',
];
header('Content-Type: ' . $contentTypes[$matches[1]]);
header('Content-Disposition: inline; filename="bukti-pembayaran-' . $transactionId . '.' . $matches[1] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . (string) filesize($absolutePath));
readfile($absolutePath);
exit;
