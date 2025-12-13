<?php
// work_order_list.php - KODE PRODUKSI FINAL (Koreksi Logic Redirect dan Modal)

// Pastikan error reporting dimatikan/diatur sesuai standar produksi Anda
ini_set('display_errors', 0); 
error_reporting(0);

// --- ASUMSI FILE INI MENGURUS SESSION, AUTH, DAN KONEKSI DB ---
require_once 'src/work_order_functions.php';
require_once 'src/security.php'; 
// ASUMSI: File ini mengandung inisialisasi $auth dan $db
include 'header.php'; 

$rbac_target = 'work_order_list.php'; 

// Pengecekan RBAC 0: Akses Halaman VIEW utama
if (!isset($auth) || !$auth->can($rbac_target, 'view')) { 
    // Jika tidak punya izin 'view', alihkan ke dashboard
    header('Location: ./dashboard.php?err=' . urlencode('Akses Ditolak: Anda tidak memiliki izin untuk melihat Daftar Work Order.'));
    exit();
}

// Cek hak akses CRUD
$can_add_wo = $auth->can($rbac_target, 'add');
$can_edit_wo = $auth->can($rbac_target, 'edit');
$can_delete_wo = $auth->can($rbac_target, 'delete');

// Inisialisasi pesan dari URL (untuk pesan dari form.php)
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// --- LOGIKA SOFT DELETE WO (Menggunakan Enkripsi) ---
if (isset($_GET['delete_id'])) {
    $encrypted_id = $_GET['delete_id'];
    
    // Hapus ID dari URL agar tidak diproses lagi saat refresh/muat ulang
    // NOTE: Logika ini lebih baik diubah menjadi REDIRECT agar URL bersih dan mencegah re-submit.
    // Tetapi jika Anda ingin mempertahankan gaya current page processing, ini OK.
    unset($_GET['delete_id']); 

    // 1. Pengecekan RBAC untuk Delete
    if (!$can_delete_wo) {
        $err = "Akses Ditolak: Anda tidak memiliki hak untuk menghapus Work Order.";
    } else {
        $wo_id = 0;
        try {
            // 2. Dekripsi ID
            $wo_id = (int) decrypt_id($encrypted_id); 
        } catch (Exception $e) {
             $err = "ID Work Order tidak valid atau gagal didekripsi.";
             $wo_id = 0;
        }

        if ($wo_id > 0) {
            // 3. Lakukan Soft Delete (status_wo = 1)
            $result = softDeleteWorkOrder($wo_id); 
            // Ambil pesan dari hasil fungsi
            $msg = $result['success'] ?? $msg;
            $err = $result['error'] ?? $err;
        } else {
            $err = $err ?: "ID Work Order yang dikirim tidak valid.";
        }
    }
    
    // TIDAK ADA REDIRECT PENUH di sini. Halaman akan dimuat ulang dengan pesan $msg / $err yang sudah diatur.
}
?>

<div id="content-wrapper">
    <div class="container-fluid">
        
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Daftar Work Order</li>
        </ol>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">📋 Daftar Work Order</h3>
            
            <div class="d-flex gap-2">
                <?php // Pengecekan RBAC untuk Tombol Tambah ?>
                <?php if ($can_add_wo): ?>
                    <a href="work_order.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Buat Work Order Baru
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if(strlen($err) > 1) :?><div class="alert alert-danger my-3" role="alert"><strong>Gagal!</strong> <?php echo htmlspecialchars($err);?></div><?php endif?>
        <?php if(strlen($msg) > 1) :?><div class="alert alert-success my-3" role="alert"><strong>Berhasil!</strong> <?php echo htmlspecialchars($msg);?></div><?php endif?>

        <div class="card mb-3 shadow">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-clipboard-list"></i> Data Work Order
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="workOrderTable" class="table table-bordered table-striped" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>No.</th> 
                                <th>Updated</th> 
                                <th>Request Type</th> 
                                <th>Request Detail</th> 
                                <th>Latest Notes</th> 
                                <th>Status</th> 
                                <th>Priority</th> 
                                <th>Action</th> 
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php // Modal Delete Diletakkan di sini, di luar container-fluid tapi sebelum footer utama ?>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Menonaktifkan WO</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin **menonaktifkan** Work Order #<strong id="woIdToDelete"></strong>? Aksi ini adalah soft delete, **status_wo akan menjadi 1** dan WO tidak akan muncul di daftar aktif.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <a id="confirmDeleteButton" href="#" class="btn btn-danger">Ya, Nonaktifkan</a>
            </div>
        </div>
    </div>
</div>

<?php include './footer.php'; ?>
<script>
// =========================================================
// FUNGSI FALLBACK: Digunakan oleh tombol 'onclick' dari AJAX
// =========================================================
// Fungsi ini harus didefinisikan jika tombol di ajax/ajax_work_order_list.php 
// masih menggunakan onclick="showDeleteModal(...)"
function showDeleteModal(encryptedId, woId) {
    const modal = $('#deleteModal');
    
    // Mengisi placeholder ID Work Order
    modal.find('#woIdToDelete').text(woId);

    // Mengatur link hapus ke work_order_list.php?delete_id=encrypted_id
    modal.find('#confirmDeleteButton').attr('href', 'work_order_list.php?delete_id=' + encryptedId);

    // Menampilkan modal secara manual menggunakan Bootstrap JS API
    modal.modal('show');
}
// =========================================================
// KONFIGURASI DATATABLES
// =========================================================
$(document).ready(function() {
    const table = $('#workOrderTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            // PENTING: File AJAX ini harus memfilter data dengan "WHERE status_wo = 0"
            url: 'ajax/ajax_work_order_list.php',
            type: 'GET',
        },
        order: [[0, 'desc']], 
        columns: [
            { data: 0, title: 'No.', width: '5%', orderable: true, 
                render: function (data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { data: 1, title: 'Updated', width: '10%' },
            { data: 2, title: 'Request Type', width: '15%' },
            { data: 3, title: 'Request Detail', width: '20%', orderable: false },
            { data: 4, title: 'Latest Notes', width: '20%', orderable: false },
            { data: 5, title: 'Status', width: '10%' },
            { data: 6, title: 'Priority', width: '10%' },
            { data: 7, title: 'Action', orderable: false, searchable: false, width: '10%' } 
        ]
    });

    // --- HANDLE DELETE BUTTON (Modal Hapus) MENGGUNAKAN show.bs.modal ---
    // NOTE: Bagian ini akan otomatis terpanggil JIKA tombol di AJAX menggunakan data-toggle="modal"
    // Namun, karena tombol di AJAX menggunakan onclick, Anda harus mengandalkan fungsi showDeleteModal() di atas.
    /*
    $('#deleteModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        
        // Mengambil data dari atribut data-* tombol. 
        var encryptedId = button.data('encrypted-id'); 
        var woId = button.data('wo-id'); 

        var modal = $(this);
        // Mengisi placeholder ID Work Order
        modal.find('#woIdToDelete').text(woId);

        var confirmDeleteButton = modal.find('#confirmDeleteButton');
        // Mengatur link hapus ke work_order_list.php?delete_id=encrypted_id
        confirmDeleteButton.attr('href', 'work_order_list.php?delete_id=' + encryptedId);
    });
    */
    // KODE ASLI DI ATAS DINONAKTIFKAN KARENA KONFLIK DENGAN onclick.
    // Fungsi showDeleteModal() yang baru sudah menangani logika ini.
});
</script>