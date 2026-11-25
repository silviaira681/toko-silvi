<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAuth();
requireRole(['admin', 'kasir']);

function verifikasiEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function verifikasiRupiah(mixed $value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function verifikasiTanggal(string $value): string
{
    $timestamp = strtotime($value);
    $months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    return date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp) - 1] . ' ' . date('Y', $timestamp);
}

function verifikasiBuktiTersedia(mixed $proofPath): bool
{
    $proofPath = (string) $proofPath;
    if (!preg_match('~\Auploads/payment_proofs/[a-f0-9]{32}\.(jpg|png|webp|pdf)\z~', $proofPath)) {
        return false;
    }

    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $proofPath);
    return is_file($absolutePath);
}

$user = authUser();
$notice = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);
$pageError = null;
$pdo = null;

try {
    $pdo = tokokuPdo();
    ensurePaymentMethodSchema($pdo);
    ensurePaymentVerificationSchema($pdo);
    ensureCustomerCheckoutSchema($pdo);
} catch (PDOException $exception) {
    error_log('Tokoku payment verification database error: ' . $exception->getMessage());
    $pageError = 'Data verifikasi belum dapat dimuat. Pastikan database tersedia.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('Koneksi database belum tersedia.');
        }
        if (!verifyAuthCsrf()) {
            throw new RuntimeException('Sesi formulir tidak valid. Muat ulang halaman.');
        }

        $transactionId = filter_var($_POST['id_transaksi'] ?? null, FILTER_VALIDATE_INT);
        $action = (string) ($_POST['action'] ?? '');
        if (!$transactionId || $transactionId < 1 || !in_array($action, ['verify', 'reject'], true)) {
            throw new RuntimeException('Permintaan verifikasi tidak valid.');
        }

        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'SELECT status_pembayaran, bukti_pembayaran
                 FROM tbl_transaksi
                 WHERE id_transaksi = ?
                 FOR UPDATE'
            );
            $statement->execute([$transactionId]);
            $transaction = $statement->fetch();
            if (!$transaction) {
                throw new RuntimeException('Transaksi tidak ditemukan.');
            }
            if ($transaction['status_pembayaran'] !== 'Menunggu Verifikasi') {
                throw new RuntimeException('Transaksi ini sudah diproses sebelumnya.');
            }

            $verifier = ucfirst(authRole()) . ': ' . (string) $user['username'];
            if ($action === 'verify') {
                if (!verifikasiBuktiTersedia($transaction['bukti_pembayaran'])) {
                    throw new RuntimeException('Bukti pembayaran belum tersedia.');
                }
                $statement = $pdo->prepare(
                    "UPDATE tbl_transaksi
                     SET status_pembayaran = 'Terverifikasi',
                         diverifikasi_oleh = ?,
                         diverifikasi_pada = NOW(),
                         catatan_verifikasi = NULL
                     WHERE id_transaksi = ? AND status_pembayaran = 'Menunggu Verifikasi'"
                );
                $statement->execute([$verifier, $transactionId]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException('Status transaksi berubah. Muat ulang antrean verifikasi.');
                }
            } else {
                $reason = trim((string) ($_POST['catatan_verifikasi'] ?? ''));
                if ($reason === '' || mb_strlen($reason) > 255) {
                    throw new RuntimeException('Alasan penolakan wajib diisi (maksimal 255 karakter).');
                }

                $statement = $pdo->prepare(
                    'SELECT id_produk, jumlah
                     FROM tbl_detail_transaksi
                     WHERE id_transaksi = ?
                     ORDER BY id_produk'
                );
                $statement->execute([$transactionId]);
                $details = $statement->fetchAll();
                $restoreStock = $pdo->prepare(
                    'UPDATE tbl_produk
                     SET stok = stok + ?
                     WHERE id_produk = ? AND stok <= 2147483647 - ?'
                );
                foreach ($details as $detail) {
                    $quantity = (int) $detail['jumlah'];
                    $restoreStock->execute([$quantity, (int) $detail['id_produk'], $quantity]);
                    if ($restoreStock->rowCount() !== 1) {
                        throw new RuntimeException('Stok produk tidak dapat dipulihkan. Hubungi admin sebelum menolak transaksi.');
                    }
                }

                $statement = $pdo->prepare(
                    "UPDATE tbl_transaksi
                     SET status_pembayaran = 'Ditolak',
                         diverifikasi_oleh = ?,
                         diverifikasi_pada = NOW(),
                         catatan_verifikasi = ?
                     WHERE id_transaksi = ? AND status_pembayaran = 'Menunggu Verifikasi'"
                );
                $statement->execute([$verifier, $reason, $transactionId]);
                if ($statement->rowCount() !== 1) {
                    throw new RuntimeException('Status transaksi berubah. Muat ulang antrean verifikasi.');
                }
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $_SESSION['notice'] = [
            'type' => 'success',
            'text' => $action === 'verify'
                ? 'Pembayaran transaksi #' . $transactionId . ' berhasil diverifikasi.'
                : 'Transaksi #' . $transactionId . ' ditolak dan stok dikembalikan.',
        ];
        header('Location: verifikasi.php');
        exit;
    } catch (PDOException $exception) {
        error_log('Tokoku payment verification action error: ' . $exception->getMessage());
        $_SESSION['notice'] = [
            'type' => 'error',
            'text' => 'Status pembayaran gagal disimpan. Periksa database dan coba lagi.',
        ];
    } catch (RuntimeException $exception) {
        $_SESSION['notice'] = ['type' => 'error', 'text' => $exception->getMessage()];
    }

    header('Location: verifikasi.php');
    exit;
}

$pendingPayments = [];
$paymentDetails = [];
if ($pdo instanceof PDO && $pageError === null) {
    try {
        $pendingPayments = $pdo->query(
            "SELECT t.id_transaksi, t.tanggal, t.total_belanja, t.metode_pembayaran,
                    t.bukti_pembayaran, p.nama_pelanggan, p.kota
             FROM tbl_transaksi AS t
             JOIN tbl_pelanggan AS p ON p.id_pelanggan = t.id_pelanggan
             WHERE t.status_pembayaran = 'Menunggu Verifikasi'
             ORDER BY t.tanggal ASC, t.id_transaksi ASC"
        )->fetchAll();

        if ($pendingPayments) {
            $transactionIds = array_map(
                static fn (array $payment): int => (int) $payment['id_transaksi'],
                $pendingPayments
            );
            $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
            $statement = $pdo->prepare(
                'SELECT d.id_transaksi, pr.nama_produk, d.jumlah, d.subtotal
                 FROM tbl_detail_transaksi AS d
                 JOIN tbl_produk AS pr ON pr.id_produk = d.id_produk
                 WHERE d.id_transaksi IN (' . $placeholders . ')
                 ORDER BY d.id_transaksi, d.id_detail'
            );
            $statement->execute($transactionIds);
            foreach ($statement->fetchAll() as $detail) {
                $paymentDetails[(int) $detail['id_transaksi']][] = $detail;
            }
        }
    } catch (PDOException $exception) {
        error_log('Tokoku pending payment list error: ' . $exception->getMessage());
        $pageError = 'Antrean verifikasi gagal dimuat. Silakan coba lagi.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Pembayaran — TokoKita</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="<?= authRole() === 'kasir' ? 'kasir.php' : 'index.php?page=dashboard' ?>" aria-label="TokoKita">
            <span class="brand-mark">t<span>.</span></span>
            <span class="brand-copy">tokokita<small>STORE MANAGEMENT</small></span>
        </a>
        <div class="nav-caption">PEMBAYARAN</div>
        <nav class="navigation" aria-label="Navigasi pembayaran">
            <a class="nav-link active" href="verifikasi.php"><span class="nav-icon">✓</span>Verifikasi pembayaran</a>
            <?php if (authRole() === 'kasir'): ?>
                <a class="nav-link" href="kasir.php"><span class="nav-icon">▤</span>Kembali ke kasir</a>
                <a class="nav-link" href="index.php?page=reports"><span class="nav-icon">▥</span>Laporan</a>
            <?php else: ?>
                <a class="nav-link" href="index.php?page=transactions"><span class="nav-icon">▤</span>Transaksi</a>
                <a class="nav-link" href="index.php?page=payments"><span class="nav-icon">▣</span>Pengaturan pembayaran</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom">
            <div class="store-card">
                <span class="store-avatar"><?= verifikasiEscape(mb_strtoupper(mb_substr($user['username'], 0, 1))) ?></span>
                <span><strong><?= verifikasiEscape($user['username']) ?></strong><small><?= ucfirst(authRole()) ?></small></span>
            </div>
            <div class="sidebar-foot">SISTEM INFORMASI PENJUALAN<br>© <?= date('Y') ?> TOKOKITA</div>
        </div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">TokoKita <span>/</span> <strong>Verifikasi pembayaran</strong></div>
            <div class="topbar-right">
                <span class="today-date"><?= verifikasiEscape($user['username']) ?> · <?= ucfirst(authRole()) ?></span>
                <form method="post" action="logout.php" class="top-logout-form">
                    <input type="hidden" name="csrf_token" value="<?= verifikasiEscape(authCsrfToken()) ?>">
                    <button class="logout-button" type="submit">Logout <span>↗</span></button>
                </form>
            </div>
        </header>
        <div class="page-content verification-page">
            <?php if ($pageError): ?><div class="alert error" role="alert"><?= verifikasiEscape($pageError) ?></div><?php endif; ?>
            <?php if ($notice): ?><div class="alert <?= verifikasiEscape($notice['type']) ?>" role="status"><?= verifikasiEscape($notice['text']) ?></div><?php endif; ?>
            <section class="management-hero">
                <div class="management-hero-copy">
                    <span class="hero-icon">✓</span>
                    <div><p class="eyebrow">PESANAN ONLINE</p><h1>Verifikasi pembayaran</h1><p class="muted">Periksa bukti transfer atau QRIS sebelum menyetujui pesanan.</p></div>
                </div>
            </section>
            <section class="panel payment-verification-panel">
                <div class="panel-heading">
                    <div><h2>Menunggu verifikasi</h2><p class="muted"><?= number_format(count($pendingPayments), 0, ',', '.') ?> transaksi belum diproses.</p></div>
                </div>
                <?php if ($pendingPayments): ?>
                    <div class="payment-verification-list">
                        <?php foreach ($pendingPayments as $payment): ?>
                            <?php $transactionId = (int) $payment['id_transaksi']; ?>
                            <?php $proofAvailable = verifikasiBuktiTersedia($payment['bukti_pembayaran']); ?>
                            <article class="payment-verification-card">
                                <div class="verification-card-heading">
                                    <div><span class="transaction-id">#<?= $transactionId ?></span><h3><?= verifikasiEscape($payment['nama_pelanggan']) ?></h3><p><?= verifikasiEscape($payment['kota'] ?? 'Kota tidak diisi') ?> · <?= verifikasiEscape(verifikasiTanggal($payment['tanggal'])) ?></p></div>
                                    <strong><?= verifikasiRupiah($payment['total_belanja']) ?></strong>
                                </div>
                                <p class="verification-method">Metode pembayaran: <strong><?= verifikasiEscape($payment['metode_pembayaran']) ?></strong></p>
                                <?php if ($proofAvailable): ?>
                                    <a class="button button-light verification-proof-link" href="bukti_pembayaran.php?id=<?= $transactionId ?>" target="_blank" rel="noopener">Lihat bukti pembayaran ↗</a>
                                <?php else: ?>
                                    <p class="verification-proof-missing" role="alert">Bukti pembayaran tidak ditemukan. Transaksi tidak dapat disetujui.</p>
                                <?php endif; ?>
                                <?php if (!empty($paymentDetails[$transactionId])): ?>
                                    <div class="table-wrap">
                                        <table class="verification-details-table">
                                            <thead><tr><th>PRODUK</th><th>JUMLAH</th><th>SUBTOTAL</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($paymentDetails[$transactionId] as $detail): ?>
                                                <tr><td><?= verifikasiEscape($detail['nama_produk']) ?></td><td><?= number_format((int) $detail['jumlah'], 0, ',', '.') ?></td><td><?= verifikasiRupiah($detail['subtotal']) ?></td></tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <div class="verification-actions">
                                    <?php if ($proofAvailable): ?>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= verifikasiEscape(authCsrfToken()) ?>">
                                            <input type="hidden" name="id_transaksi" value="<?= $transactionId ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <button class="button button-primary" type="submit">Verifikasi pembayaran</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" class="verification-reject-form">
                                        <input type="hidden" name="csrf_token" value="<?= verifikasiEscape(authCsrfToken()) ?>">
                                        <input type="hidden" name="id_transaksi" value="<?= $transactionId ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <label for="reason-<?= $transactionId ?>">Alasan penolakan</label>
                                        <input id="reason-<?= $transactionId ?>" name="catatan_verifikasi" maxlength="255" placeholder="Contoh: nominal bukti tidak sesuai" required>
                                        <button class="button button-light verification-reject-button" type="submit">Tolak &amp; kembalikan stok</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">Tidak ada pembayaran yang menunggu verifikasi.</div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
</body>
</html>
