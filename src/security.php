<?php
// src/Security.php - Mengandung fungsi keamanan utama

// =========================================================
// ⚠️ KONSTANTA KEAMANAN WAJIB DIATUR DAN DIRAHASIAKAN ⚠️
// GANTI NILAI-NILAI INI DENGAN STRING UNIK DAN PANJANG!
// Pastikan ini didefinisikan hanya sekali.
// =========================================================
if (!defined('ENC_KEY')) {
    // Kunci Enkripsi (Wajib 32 karakter untuk AES-256)
    define('ENC_KEY', 'Ini_Adalah_Kunci_Rahasia_Utama_32Byte'); 
}

if (!defined('ENC_METHOD')) {
    // Metode Enkripsi yang Kuat
    define('ENC_METHOD', 'aes-256-cbc'); 
}

// ---------------------------------------------------------
// 1. FUNGSI ENKRIPSI ID (Pencegahan IDOR di URL)
// ---------------------------------------------------------

/**
 * Mengenkripsi ID numerik menjadi string yang aman untuk URL.
 * @param int $id ID yang akan dienkripsi (misal: WO_ID)
 * @return string ID terenkripsi yang aman untuk URL
 */
function encrypt_id($id) {
    if (empty($id)) {
        return '';
    }
    
    $plain_id = (string)$id; 
    
    // Inisialisasi Vector (IV) yang unik
    $iv_length = openssl_cipher_iv_length(ENC_METHOD);
    $iv = openssl_random_pseudo_bytes($iv_length);
    
    // Enkripsi
    $encrypted = openssl_encrypt($plain_id, ENC_METHOD, ENC_KEY, 0, $iv);
    
    // Gabungkan data terenkripsi dan IV, lalu konversi ke Base64 yang aman untuk URL
    // strtr mengganti +/ = dengan - _ , agar aman di URL
    return strtr(base64_encode($encrypted . '::' . $iv), '+/=', '-_,');
}

/**
 * Mendekripsi string URL menjadi ID numerik asli.
 * @param string $encrypted_id ID terenkripsi dari URL
 * @return int ID numerik asli (0 jika gagal atau tidak valid)
 */
function decrypt_id($encrypted_id) {
    if (empty($encrypted_id)) {
        return 0;
    }
    
    // Ubah format URL-safe kembali ke Base64 standar
    $data = strtr($encrypted_id, '-_,', '+/=');
    
    // Decode Base64
    $decoded = base64_decode($data);
    
    // Pisahkan data terenkripsi dan IV
    @list($encrypted_data, $iv) = explode('::', $decoded, 2);
    
    // Cek integritas
    if (empty($encrypted_data) || empty($iv)) {
        return 0;
    }
    
    // Dekripsi
    $decrypted = openssl_decrypt($encrypted_data, ENC_METHOD, ENC_KEY, 0, $iv);
    
    // Kembalikan sebagai integer
    return (int)$decrypted;
}

// ---------------------------------------------------------
// 2. FUNGSI CEK KEPEMILIKAN DATA (Pencegahan IDOR Akses)
// ---------------------------------------------------------

/**
 * Cek kepemilikan Work Order menggunakan Prepared Statements.
 * * Fungsi ini harus digunakan bersama dengan Auth check lainnya 
 * untuk menentukan apakah pengguna memiliki hak akses ke WO tersebut 
 * (sebagai requester atau sebagai Admin/Teknisi).
 * * @param int $wo_id ID Work Order yang telah didekripsi
 * @param int $user_id ID pengguna yang sedang login
 * @param mysqli $db Objek koneksi database (diperlukan object mysqli)
 * @return bool True jika pengguna adalah Requester dari WO tersebut
 */
function check_wo_ownership($wo_id, $user_id, $db) {
    // ⚠️ PERIKSA NAMA KOLOM: Pastikan kolom yang menyimpan ID user adalah 'requester_user_id'
    $sql = "SELECT wo_id FROM work_order WHERE wo_id = ? AND requester_user_id = ?";
    
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        // Logging error database di sini
        return false;
    }
    
    // "ii" -> dua integer (wo_id dan user_id)
    $stmt->bind_param("ii", $wo_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    $is_owner = $stmt->num_rows > 0;
    $stmt->close();
    
    return $is_owner;
}