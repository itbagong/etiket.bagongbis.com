<?php
// export_pdf.php - Endpoint untuk menampilkan halaman Work Order untuk cetak manual (FINAL)

// Matikan output buffering jika masih aktif dari file sebelumnya
if (ob_get_level()) {
    ob_end_clean();
}

// =========================================================
// 1. PASTIKAN LIBRARY DAN FUNGSI DIPANGGIL
// =========================================================
require_once __DIR__ . '/src/pdf_generator.php';
require_once __DIR__ . '/src/security.php'; // WAJIB: Untuk dekripsi ID

// ASUMSI: Jika perlu autentikasi, tempatkan logika Auth di sini.
// session_start();
// ...

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
exit;