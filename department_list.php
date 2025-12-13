<?php
// department_list.php - Daftar Departemen (FINAL KOREKSI)

// === PENGHAPUSAN ob_start() ===
// ob_start() dihapus karena kita tidak lagi melakukan redirect di tengah skrip.

require_once 'src/Database.php';
require_once 'src/Department.php';
require_once 'src/security.php'; 

// Masukkan header LEBIH AWAL untuk memastikan $auth sudah ada
include './header.php'; 

// === RBAC CHECK 0: Akses Halaman VIEW ===
$current_page = 'department_list.php';

// Inisialisasi variabel pesan dari URL (untuk pesan dari form.php)
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

try {
    // Pastikan user punya hak 'view'
    $auth->authorize($current_page, 'view');
} catch (Exception $e) {
    // Jika tidak punya izin 'view', alihkan ke dashboard
    header('Location: ./dashboard.php');
    exit();
}
// ========================================

// Cek izin manajemen untuk kontrol tampilan Form dan tombol Edit/Hapus
$can_add = $auth->can($current_page, 'add');
$can_edit = $auth->can($current_page, 'edit');
$can_delete = $auth->can($current_page, 'delete');


// =========================================================================
// 1. TANGANI REQUEST SOFT DELETE (Mendekripsi ID) - TANPA REDIRECT
// =========================================================================

if (isset($_GET['delete_id'])) {
    $encrypted_id = $_GET['delete_id'];
    $id = 0;
    
    // Hilangkan delete_id dari URL agar tidak terhapus lagi saat refresh
    unset($_GET['delete_id']); 

    try {
        $id = (int) decrypt_id($encrypted_id); 
    } catch (Exception $e) {
        $err = "ID Departemen tidak valid atau gagal didekripsi.";
        $id = 0;
    }

    // Jika ID berhasil didekripsi (ID > 0)
    if ($id > 0) {
        
        // RBAC CHECK: Pengecekan Hapus
        if (!$can_delete) {
            $err = "Akses Ditolak: Anda tidak memiliki izin untuk menonaktifkan departemen.";
        } else {
            try {
                // Menggunakan softDelete() dari model Department
                if (Department::softDelete($id)) {
                    $msg = "Departemen berhasil dinonaktifkan (soft deleted).";
                } else {
                    if (Department::find($id)) {
                        $err = "Gagal menonaktifkan departemen. Coba lagi.";
                    } else {
                        $err = "Departemen tidak ditemukan atau gagal dinonaktifkan.";
                    }
                }
            } catch (Exception $e) {
                $err = "Gagal menonaktifkan departemen: " . $e->getMessage();
            }
        }
    } else {
        // Jika ID tidak valid dari hasil dekripsi
        $err = $err ?: "ID Departemen yang dikirim tidak valid.";
    }
    
    // NOTE: Tidak ada redirect di sini. Halaman akan dimuat ulang dengan pesan $msg / $err.
}

// =========================================================================
// LANJUTKAN PEMUATAN HALAMAN NORMAL
// =========================================================================

// Department::findAll() hanya akan mengambil departemen dengan status = 1 (Aktif)
$departments = Department::findAll();
?>

<div id="content-wrapper">
    <div class="container-fluid">

        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Daftar Departemen</li>
        </ol>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">🧾 Manajemen Departemen</h3>
        </div>
        
        <?php if(strlen($err) > 1) :?>
            <div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($err);?></div>
        <?php endif?>
        <?php if(strlen($msg) > 1) :?>
            <div class="alert alert-success text-center my-3" role="alert"> <strong>Sukses! </strong> <?php echo htmlspecialchars($msg);?></div>
        <?php endif?>

        <?php if ($can_add): ?>
        <div class="card mb-3 shadow">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Tambah Departemen Baru</h5>
            </div>
            <div class="card-body">
                <form id="addDepartmentForm" method="POST" action="department_form.php" class="p-0">
                    <input type="hidden" name="save_department" value="1"> 
                    <input type="hidden" name="department_id" value="0"> 
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Departemen</label>
                            <input type="text" name="department_name" class="form-control" required placeholder="Contoh: Teknologi Informasi">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Singkatan</label>
                            <input type="text" name="department_abb" class="form-control" required placeholder="Contoh: TI / IT">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="department_status" class="form-control">
                                <option value="0">Aktif</option>
                                <option value="1">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="fas fa-save me-1"></i> Tambah</button>
                        <button type="button" class="btn btn-secondary" onclick="$('#addDepartmentForm')[0].reset();"><i class="fas fa-undo me-1"></i> Reset</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="card mb-3 shadow">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">Daftar Data Departemen</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="dataTable" class="table table-bordered table-striped" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Singkatan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($departments as $dept): 
                                $encrypted_id = encrypt_id($dept->id);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($dept->id); ?></td>
                                <td><?php echo htmlspecialchars($dept->name); ?></td>
                                <td><?php echo htmlspecialchars($dept->abbreviation); ?></td>
                                <td>
                                    <?php 
                                        $status_class = $dept->status == 0 ? 'bg-success' : 'bg-danger';
                                        $status_text = $dept->status == 0 ? 'Aktif' : 'Nonaktif';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <?php if ($can_edit): ?>
                                    <button class="btn btn-sm btn-warning editBtn me-1" 
                                            data-id="<?php echo $dept->id; ?>"
                                            data-name="<?php echo htmlspecialchars($dept->name); ?>"
                                            data-abb="<?php echo htmlspecialchars($dept->abbreviation); ?>"
                                            data-status="<?php echo $dept->status; ?>"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-warning me-1" disabled><i class="fas fa-edit"></i></button>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete): ?>
                                    <button class="btn btn-sm btn-danger" 
                                            data-toggle="modal" 
                                            data-target="#deleteModal"
                                            data-encrypted-id="<?php echo $encrypted_id; ?>"
                                            data-department-name="<?php echo htmlspecialchars($dept->name); ?>"
                                            title="Hapus (Nonaktifkan)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-danger" disabled><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                    
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit me-2"></i> Edit Departemen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editDepartmentForm" method="POST" action="department_form.php">
                <input type="hidden" name="department_id" id="modal_department_id">
                <input type="hidden" name="save_department" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Departemen</label>
                        <input type="text" name="department_name" id="modal_department_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Singkatan</label>
                        <input type="text" name="department_abb" id="modal_department_abb" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="department_status" id="modal_department_status" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
                </div>
            </form>
            </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Menonaktifkan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin **menonaktifkan** Departemen: **<strong id="departmentNameToDelete"></strong>**? Aksi ini akan mengubah status menjadi Nonaktif (Soft Delete).
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <a id="confirmDeleteButton" href="#" class="btn btn-danger">Nonaktifkan</a>
            </div>
        </div>
    </div>
</div>

<?php include './footer.php'; ?> 

<script>
// Menghapus fungsi showDeleteModal() yang inline. Kita menggunakan event listener.
$(document).ready(function() {
    // Inisialisasi DataTables
    if ($.fn.DataTable) {
        $('#dataTable').DataTable(); 
    }
    
    // --- 1. HANDLE EDIT BUTTON CLICK (Modal Edit) ---
    $('#dataTable').on('click', '.editBtn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const abb = $(this).data('abb');
        const status = $(this).data('status');
        
        // Mengisi formulir di dalam modal
        $('#modal_department_id').val(id); 
        $('#modal_department_name').val(name);
        $('#modal_department_abb').val(abb);
        $('#modal_department_status').val(status);
        
        // Memunculkan Modal Bootstrap secara manual
        $('#editModal').modal('show');
    });

    // --- 2. HANDLE DELETE BUTTON (Modal Hapus) MENGGUNAKAN show.bs.modal ---
    $('#deleteModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var encryptedId = button.data('encrypted-id'); // Mengambil dari data-* tombol
        var departmentName = button.data('department-name');

        var modal = $(this);
        // Mengisi placeholder nama departemen
        modal.find('#departmentNameToDelete').text(departmentName);

        var confirmDeleteButton = modal.find('#confirmDeleteButton');
        // Mengatur link hapus ke department_list.php?delete_id=encrypted_id
        confirmDeleteButton.attr('href', 'department_list.php?delete_id=' + encryptedId);
    });
});
</script>

</div>