<?php
// team_list.php - Daftar Tim (List) - KODE DIKOREKSI

require_once './src/team.php'; 
require_once './src/Database.php'; 
require_once './src/security.php'; 

$current_page = basename(__FILE__); // team_list.php
$err = $_GET['err'] ?? ''; // Ambil pesan dari URL
$msg = $_GET['msg'] ?? '';

// === 1. INCLUDE HEADER (INISIALISASI $auth & $db TERJADI DI SINI) ===
include './header.php'; 

// =========================================================================
// 2. TANGANI REQUEST HAPUS (SOFT DELETE) - TANPA REDIRECT (Seperti role.php)
// =========================================================================

if (isset($_GET['delete_id'])) {
    
    // --- RBAC CHECK 2: Otorisasi DELETE ---
    try {
        $auth->authorize($current_page, 'delete'); 
    } catch (Exception $e) {
        $err = "Akses Ditolak: " . $e->getMessage();
    }
    
    if (empty($err)) {
        $encrypted_id = $_GET['delete_id'];
        $delete_id = decrypt_id($encrypted_id); // <--- DEKRIPSI ID
        
        if ($delete_id === false || $delete_id <= 0) {
            $err = "ID Tim tidak valid atau gagal didekripsi.";
        } else {
            try {
                // PANGGIL SOFT DELETE
                if (Team::softDelete($delete_id)) { 
                    $msg = "Tim berhasil diarsipkan (Soft Deleted).";
                    // Hapus action=delete dan id dari URL agar tidak terhapus lagi saat refresh
                    $_GET['delete_id'] = null;
                } else {
                    $err = "Tim tidak ditemukan atau gagal diarsipkan."; 
                }
            } catch (Exception $e) {
                $err = "Gagal mengarsipkan tim: " . $e->getMessage();
            }
        }
    }
}

// =========================================================================
// 3. FETCH DATA
// =========================================================================

// Otorisasi VIEW (Sudah dipanggil di role.php, kita pastikan di sini juga)
// Meskipun otorisasi di header sudah memastikan user logged in, ini memastikan VIEW
try {
    $auth->authorize($current_page, 'view'); 
} catch (Exception $e) {
    // Tangani jika otorisasi VIEW gagal setelah include header
    $err = "Akses Ditolak untuk melihat halaman: " . $e->getMessage();
    // Kita tetap lanjutkan ke VIEW dengan data kosong jika gagal otorisasi
}

// Cek izin spesifik untuk kontrol tombol dan aksi
$can_add = $auth->can($current_page, 'add');
$can_edit = $auth->can($current_page, 'edit');
$can_delete = $auth->can($current_page, 'delete');

// Ambil data setelah potensi penghapusan
$teams = Team::findAll();
?>
<div id="content-wrapper">

    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Daftar Tim</li>
        </ol>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">👥 Daftar Tim TIK</h3>
            <?php if ($can_add): // Kontrol tombol Tambah Tim Baru ?>
                <a class="btn btn-primary btn-sm" href="./team_form.php"><i class="fas fa-plus me-1"></i> Tim Baru</a>
            <?php endif; ?>
        </div>
        
        <?php if(strlen($err) > 1) :?><div class="alert alert-danger my-3" role="alert"><strong>Gagal! </strong><?php echo htmlspecialchars($err);?></div><?php endif?>
        <?php if(strlen($msg) > 1) :?><div class="alert alert-success my-3" role="alert"><strong>Sukses! </strong><?php echo htmlspecialchars($msg);?></div><?php endif?>

        <div class="card mb-3 shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped" id="dataTable" width="100%" cellspacing="0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Created at</th>
                                <th style="width: 250px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($teams as $team):?>
                            <tr>
                                <td><?php echo htmlspecialchars($team->name ?? '') ?></td>
                                <?php $date = new DateTime($team->created_at ?? 'now');?>
                                <td><?php echo $date->format('d-m-Y H:i:s')?> </td>
                                <td class="text-center">
                                    
                                    <?php if ($can_edit): ?>
                                    <a href="./team_form.php?id=<?php echo encrypt_id($team->id) ?>" class="btn btn-warning btn-sm me-1" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete): ?>
                                    <button 
                                        type="button" 
                                        class="btn btn-danger btn-sm me-1" 
                                        data-toggle="modal" 
                                        data-target="#deleteModal"
                                        data-encrypted-id="<?php echo encrypt_id($team->id); ?>"
                                        data-team-name="<?php echo htmlspecialchars($team->name ?? ''); ?>"
                                        title="Arsipkan"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach?>
                            <?php if (empty($teams)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">Tidak ada data Tim aktif yang ditemukan.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Arsip Tim</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Apakah Anda yakin ingin mengarsipkan (Soft Delete) tim **<strong id="teamNameToDelete"></strong>**? Tim tidak akan muncul di daftar aktif lagi.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <a id="confirmDeleteButton" href="#" class="btn btn-danger">Ya, Arsipkan</a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include './footer.php'; ?> 

    <script>
    // PERBAIKAN: Menggunakan event 'show.bs.modal' seperti di role.php
    $(document).ready(function () {
        $('#deleteModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var encryptedId = button.data('encrypted-id');
            var teamName = button.data('team-name');

            var modal = $(this);
            modal.find('#teamNameToDelete').text(teamName);

            var confirmDeleteButton = modal.find('#confirmDeleteButton');
            // Target URL: team_list.php?delete_id=encrypted_id
            confirmDeleteButton.attr('href', 'team_list.php?delete_id=' + encryptedId);
        });
    });
    // Inisialisasi DataTables sudah ada di footer.php atau script bawaan.
    </script>
</div>