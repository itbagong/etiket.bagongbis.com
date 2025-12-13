<?php
// category_form.php - Gabungan Logika CRUD Kategori & Subkategori (Updated: RBAC Check)
ob_start();
// Pastikan header di-include di awal
include 'header.php'; // NOTES: Asumsi header.php sudah menginisialisasi $auth

require_once 'src/Database.php';
require_once 'src/Category.php';
require_once 'src/Subcategory.php';

$catModel = new Category();
$subModel = new Subcategory();

$err = '';
$msg = '';

// === CREATE / UPDATE CATEGORY (Dari form inline di category_list) ===
if (isset($_POST['save_category'])) {
    
    // SERVER-SIDE RBAC CHECK
    $cat_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    
    if ($cat_id > 0) {
        // Jika sedang EDIT, cek izin 'edit'
        if (!$auth->can('category_form.php', 'edit')) {
            $err = "Akses Ditolak: Anda tidak memiliki izin untuk mengubah Kategori.";
        }
    } else {
        // Jika sedang MENAMBAH, cek izin 'add'
        if (!$auth->can('category_form.php', 'add')) {
            $err = "Akses Ditolak: Anda tidak memiliki izin untuk menambah Kategori.";
        }
    }
    
    $cat_name = trim($_POST['category_name']);
    
    if ($err) {
        // Jika RBAC gagal, lanjut ke blok error di bawah
    } elseif ($cat_name == '') {
        $err = 'Nama kategori tidak boleh kosong.';
    } else {
        try {
            // Jika RBAC dan Validasi OK, proses simpan
            $catModel->save($cat_name, $cat_id);
            $msg = $cat_id > 0 ? 'Kategori berhasil diperbarui!' : 'Kategori berhasil ditambahkan!';
            
            header('Location: category_list.php?msg=' . urlencode($msg));
            exit();

        } catch(Exception $e) {
            $err = $e->getMessage();
        }
    }
}

// === CREATE / UPDATE SUBCATEGORY (Dari form inline di category_list) ===
if (isset($_POST['save_subcategory'])) {
    
    // SERVER-SIDE RBAC CHECK
    $sub_id = isset($_POST['subcategory_id']) ? intval($_POST['subcategory_id']) : 0;

    if ($sub_id > 0) {
        // Jika sedang EDIT, cek izin 'edit'
        if (!$auth->can('category_form.php', 'edit')) {
            $err = "Akses Ditolak: Anda tidak memiliki izin untuk mengubah Subkategori.";
        }
    } else {
        // Jika sedang MENAMBAH, cek izin 'add'
        if (!$auth->can('category_form.php', 'add')) {
            $err = "Akses Ditolak: Anda tidak memiliki izin untuk menambah Subkategori.";
        }
    }

    $sub_name = trim($_POST['subcategory_name']);
    $category_id = intval($_POST['category_id']);

    if ($err) {
        // Jika RBAC gagal, lanjut ke blok error di bawah
    } elseif ($sub_name == '' || $category_id == 0) {
        $err = 'Nama subkategori dan kategori wajib diisi.';
    } else {
        try {
            // Jika RBAC dan Validasi OK, proses simpan
            $subModel->save($sub_name, $category_id, $sub_id);
            $msg = $sub_id > 0 ? 'Subkategori berhasil diperbarui!' : 'Subkategori berhasil ditambahkan!';
            
            header('Location: category_list.php?msg=' . urlencode($msg));
            exit();

        } catch(Exception $e) {
            $err = $e->getMessage();
        }
    }
}

// Jika ada error dari POST (baik karena validasi maupun RBAC), tampilkan halaman error
if ($err) {
    // Tampilkan pesan error dan berhenti
    ?>
    <div id="content-wrapper">
        <div class="container-fluid">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                <li class="breadcrumb-item active">Category Form Error</li>
            </ol>
            <div class="alert alert-danger text-center"><strong>Gagal!</strong> <?= htmlspecialchars($err) ?></div>
            <p class="text-center"><a href="category_list.php" class="btn btn-secondary">Kembali ke Daftar Kategori</a></p>
        </div>
        <?php include 'footer.php'; ?>
    </div>
    <?php
    exit();
}

// Jika tidak ada request POST yang diproses atau ada error, 
// redirect ke list.
header('Location: category_list.php');
exit();
?>