<?php
// role.php - Halaman Daftar Peran (Role List) - Controller dan View

// === 1. PENCEGAHAN OUTPUT BUFFERS (Hanya untuk konsistensi, walaupun tidak dipakai untuk redirect) ===
// Kita hilangkan ob_start() karena kita tidak akan memanggil header() di sini lagi.

require_once './src/role.php'; 
require_once './src/security.php'; 

$current_page = basename(__FILE__); // role.php
$err = '';
// Ambil pesan sukses yang mungkin ada dari request sebelumnya
$msg = $_GET['msg'] ?? ''; 
$form_url = 'role_form.php'; 

// === 2. INCLUDE HEADER (INISIALISASI $auth & $db TERJADI DI SINI) ===
include './header.php'; 

// =========================================================================
// RBAC CHECK & HANDLE ACTION (DELETE) - HILANGKAN REDIRECT
// =========================================================================

if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    
    // Wajib cek otorisasi DELETE sebelum memproses
    try {
        // $auth kini sudah tersedia setelah include header.php
        $auth->authorize($current_page, 'delete');
    } catch (Exception $e) {
        // Jika Authorize gagal, kita simpan pesan error ($err)
        $err = "Akses Ditolak: " . $e->getMessage();
    }

    if (empty($err)) {
        $encrypted_id = $_GET['id'] ?? '';
        $role_id = decrypt_id($encrypted_id);

        if ($role_id !== false && $role_id > 0) {
            
            try {
                if (Role::delete($role_id, $auth)) { 
                    // JIKA SUKSES, KITA TIDAK MELAKUKAN REDIRECT (header())
                    // KITA CUKUP MENGISI VARIABEL $msg UNTUK DITAMPILKAN DI VIEW
                    $msg = "Role berhasil dihapus (Soft Delete).";
                    
                    // Hapus action=delete dan id dari URL agar tidak terhapus lagi saat refresh
                    $_GET['action'] = null;
                    $_GET['id'] = null;

                } else {
                    $err = "Gagal menghapus Role. ID Role mungkin tidak ditemukan atau sudah dihapus.";
                }
            } catch (Exception $e) {
                $err = $e->getMessage();
            }
        } else {
            $err = "ID Role tidak valid atau gagal didekripsi.";
        }
    }
}

// =========================================================================
// FETCH DATA
// =========================================================================

// Otorisasi VIEW 
$auth->authorize($current_page, 'view'); 

$can_add = $auth->can($current_page, 'add');
$can_edit = $auth->can($current_page, 'edit');
$can_delete = $auth->can($current_page, 'delete');
$can_access_form = $auth->can($current_page, 'edit');

// Data diambil setelah potensi penghapusan
$roles = Role::findAll();
?>

<div id="content-wrapper">
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Daftar Peran</li>
        </ol>

        <div class="card mb-3 shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i> Manajemen Peran Pengguna</h5>
            </div>
            <div class="card-body">
                
                <?php if(strlen($err) > 1) :?><div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($err);?></div><?php endif?>
                <?php if(strlen($msg) > 1) :?><div class="alert alert-success text-center my-3" role="alert"> <strong>Sukses! </strong> <?php echo htmlspecialchars($msg);?></div><?php endif?>

                <div class="d-flex justify-content-end mb-3">
                    <?php if ($can_add): ?>
                        <a href="<?php echo $form_url; ?>" class="btn btn-primary">
                            <i class="fas fa-plus mr-1"></i> Tambah Role Baru
                        </a>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="dataTable" width="100%" cellspacing="0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 25%;">Nama Peran (Role Name)</th>
                                <th style="width: 45%;">Deskripsi</th>
                                <th class="text-center" style="width: 25%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; ?>
                            <?php if (!empty($roles)): ?>
                                <?php foreach($roles as $role): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($role->role_name); ?></td>
                                    
                                    <td><?php echo htmlspecialchars($role->role_desc ?? ''); ?></td>
                                    
                                    <td class="text-center">
                                        
                                        <?php if ($can_access_form): ?>
										<a href="access_form.php?id=<?php echo encrypt_id($role->role_id); ?>" class="btn btn-sm btn-info mr-1" title="Atur Akses">
                                            <i class="fas fa-shield-alt"></i> Akses
                                        </a>
                                        <?php endif; ?>

                                        <?php if ($can_edit): ?>
                                        <a href="<?php echo $form_url; ?>?id=<?php echo encrypt_id($role->role_id); ?>" class="btn btn-sm btn-warning mr-1" title="Ubah Role">
                                            <i class="fas fa-edit"></i> Ubah
                                        </a>
                                        <?php endif; ?>

                                        <?php if ($can_delete): ?>
                                        <button type="button" 
                                           class="btn btn-sm btn-danger" 
                                           data-toggle="modal" 
                                           data-target="#deleteConfirmationModal"
                                           data-encrypted-id="<?php echo encrypt_id($role->role_id); ?>"
                                           data-role-name="<?php echo htmlspecialchars($role->role_name); ?>"
                                           title="Hapus Role">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                        <?php endif; ?>

                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Tidak ada data Peran aktif yang ditemukan.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel"><i class="fas fa-exclamation-triangle mr-2"></i> Konfirmasi Penghapusan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Anda yakin ingin menghapus peran **<span id="roleNamePlaceholder"></span>**?
                    Penghapusan ini tidak dapat dibatalkan (Soft Delete).
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <a href="#" id="confirmDeleteButton" class="btn btn-danger">Ya, Hapus</a>
                </div>
            </div>
        </div>
    </div>
    <?php include './footer.php'; ?> 
    <script>
        $(document).ready(function () {
            $('#deleteConfirmationModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var encryptedId = button.data('encrypted-id');
                var roleName = button.data('role-name');

                var modal = $(this);
                modal.find('#roleNamePlaceholder').text(roleName);

                var confirmDeleteButton = modal.find('#confirmDeleteButton');
                // Target URL tetap menggunakan action=delete&id=encrypted_id
                confirmDeleteButton.attr('href', '?action=delete&id=' + encryptedId);
            });
        });
    </script>


</div>