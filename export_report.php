<?php
// export_pdf.php - Endpoint untuk menampilkan halaman Work Order untuk cetak manual (FINAL)

// =========================================================
// 1. PASTIKAN LIBRARY DAN FUNGSI DIPANGGIL
// =========================================================
require_once __DIR__ . '/src/pdf_reportgenerator.php';
require_once __DIR__ . '/src/security.php'; // WAJIB: Untuk dekripsi ID
// WAJIB: Memuat sesi dan objek $auth
require_once __DIR__ . '/src/Auth.php'; // Asumsi Auth.php berada di src/
include __DIR__ . '/header.php'; // Asumsi header.php memuat session_start() dan $auth

// Matikan output buffering jika masih aktif dari file sebelumnya (Setelah header dimuat)
if (ob_get_level()) {
    ob_end_clean();
}

// === RBAC CHECK: AKSES HALAMAN UNTUK MENCETAK (AKSI VIEW) ===
$rbac_target = 'export_pdf.php'; 
// Asumsi, izin cetak/ekspor dianggap sebagai izin 'view' atau 'export'
if (!isset($auth) || !$auth->can($rbac_target, 'view')) {    
    // Jika akses ditolak, tampilkan pesan error atau alihkan
    header('Content-Type: text/plain');
    http_response_code(403); // Forbidden
    die("Akses Ditolak: Anda tidak memiliki izin untuk mencetak Work Order ini.");
}

// =========================================================
// 2. AMBIL ID DARI URL, DEKRIPSI, DAN PROSES
// =========================================================
$encrypted_id = $_GET['id'] ?? '';
$wo_id = 0;

if (!empty($encrypted_id)) {
    try {
        // Coba dekripsi ID
        $wo_id = decrypt_id($encrypted_id); 
    } catch (Exception $e) {
        // Jika dekripsi gagal, wo_id tetap 0
        error_log("Dekripsi ID WO gagal: " . $e->getMessage());
    }
}

if ($wo_id > 0) {
    // Panggil fungsi utama untuk generate dan tampilkan HTML di browser.
    generateWorkOrderPdf($wo_id);
} else {
    // Jika ID tidak valid (setelah dekripsi) atau tidak ditemukan.
    header('Content-Type: text/plain');
    http_response_code(400); // Bad Request
    die("ID Work Order tidak valid, gagal didekripsi, atau tidak ditemukan.");
}

// Pastikan tidak ada output lain setelah ini
exit;?>