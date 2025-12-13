<?php
// category_list.php - Daftar Kategori & Subkategori (Koreksi Logic Redirect)

require_once 'src/Database.php';
require_once 'src/Category.php';
require_once 'src/Subcategory.php';
require_once 'src/security.php'; 

// Masukkan header LEBIH AWAL
include 'header.php'; 

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// =========================================================================
// 1. TANGANI REQUEST SOFT DELETE KATEGORI (MENGGUNAKAN ENKRIPSI ID)
// =========================================================================
if (isset($_GET['delete_category'])) {
    
    $encrypted_id = $_GET['delete_category'];
    unset($_GET['delete_category']); 
    
    $cat_id = 0;
    try {
        $cat_id = (int) decrypt_id($encrypted_id); 
    } catch (Exception $e) {
        $err = "ID Kategori tidak valid atau gagal didekripsi.";
        $cat_id = 0;
    }
    
    // RBAC CHECK: Menggunakan izin 'delete'
    if (!$auth->can('category_list.php', 'delete')) {
        $err = "Akses Ditolak: Anda tidak memiliki izin untuk menonaktifkan kategori.";
    } elseif ($cat_id <= 0) {
        $err = $err ?: "ID Kategori tidak valid.";
    } else {
        try {
            // Panggil Soft Delete (mengubah status=1)
            if (Category::delete($cat_id)) {
                $msg = "Kategori berhasil dinonaktifkan (Soft Delete).";
            } else {
                $err = "Kategori gagal dinonaktifkan atau tidak ditemukan.";
            }
        } catch(Exception $e) {
             $err = "Gagal menonaktifkan kategori: " . $e->getMessage();
        }
    }
}

// =========================================================================
// 1. TANGANI REQUEST SOFT DELETE SUBKATEGORI (MENGGUNAKAN ENKRIPSI ID)
// =========================================================================
if (isset($_GET['delete_subcategory'])) {
    
    $encrypted_id = $_GET['delete_subcategory'];
    unset($_GET['delete_subcategory']); 
    
    $sub_id = 0;
    try {
        $sub_id = (int) decrypt_id($encrypted_id); 
    } catch (Exception $e) {
        $err = "ID Subkategori tidak valid atau gagal didekripsi.";
        $sub_id = 0;
    }
    
    // RBAC CHECK: Menggunakan izin 'delete'
    if (!$auth->can('category_list.php', 'delete')) {    
        $err = "Akses Ditolak: Anda tidak memiliki izin untuk menonaktifkan subkategori.";
    } elseif ($sub_id <= 0) {
        $err = $err ?: "ID Subkategori tidak valid.";
    } else {
        try {
            // Panggil Soft Delete (mengubah status=1)
            if (Subcategory::delete($sub_id)) {
                $msg = "Subkategori berhasil dinonaktifkan (Soft Delete).";
            } else {
                $err = "Subkategori gagal dinonaktifkan atau tidak ditemukan.";
            }
        } catch(Exception $e) {
            $err = "Gagal menonaktifkan subkategori: " . $e->getMessage();
        }
    }
}


// =========================================================================
// LANJUTKAN PEMUATAN HALAMAN NORMAL
// =========================================================================

// === RBAC CHECK 3: AKSES HALAMAN VIEW ===
if (!isset($auth) || !$auth->can('category_list.php', 'view')) {    
    header('Location: ./dashboard.php?err=' . urlencode('Akses Ditolak: Anda tidak memiliki izin untuk melihat manajemen Kategori.'));
    exit();
}

$conn = Database::getInstance();
$categories = Category::findAll();    
$subcategories = Subcategory::findAllJoined();

// Cek izin manage untuk kontrol tampilan
$can_add_category = $auth->can('category_form.php', 'add');
$can_edit_category = $auth->can('category_form.php', 'edit');
$can_delete_category = $auth->can('category_list.php', 'delete'); 

$can_add_subcategory = $auth->can('category_form.php', 'add');
$can_edit_subcategory = $auth->can('category_form.php', 'edit');
$can_delete_subcategory = $auth->can('category_list.php', 'delete'); 
?>

<div id="content-wrapper">
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
            <li class="breadcrumb-item active">Category Management</li>
        </ol>

        <?php if ($err): ?>
            <div class="alert alert-danger text-center"><strong>Gagal!</strong> <?= htmlspecialchars($err) ?></div>
        <?php elseif ($msg): ?>
            <div class="alert alert-success text-center"><strong>Sukses!</strong> <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="card mb-4 shadow">
            <div class="card-header bg-primary text-white"><strong>Kategori</strong> <i class="fas fa-layer-group"></i></div>
            <div class="card-body">
                
                <?php if ($can_add_category || $can_edit_category): ?>
                <form method="POST" action="category_form.php" class="form-inline mb-3">
                    <input type="hidden" name="category_id" id="category_id">
                    <input type="text" name="category_name" id="category_name" class="form-control mr-2" placeholder="Nama kategori" required>
                    <button type="submit" name="save_category" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary ml-2" onclick="resetCategoryForm()">Batal</button>
                </form>
                <?php else: ?>
                <p class="alert alert-info">Anda tidak memiliki izin untuk menambah atau mengubah Kategori.</p>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableCategory" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Kategori</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no=1; while($cat = $categories->fetch_assoc()):    
                                $encrypted_cat_id = encrypt_id($cat['id']);
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($cat['name']) ?></td>
                                <td>
                                    <?php if ($can_edit_category): ?>
                                    <button class="btn btn-sm btn-warning editCat me-1"    
                                            data-id="<?= $cat['id'] ?>"    
                                            data-name="<?= htmlspecialchars($cat['name']) ?>">Edit</button>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete_category): ?>    
                                    <button class="btn btn-sm btn-danger"    
                                            data-toggle="modal"
                                            data-target="#deleteModal"
                                            data-type="category"
                                            data-encrypted-id="<?= $encrypted_cat_id ?>"
                                            data-name="<?= htmlspecialchars($cat['name']) ?>">Nonaktifkan</button>
                                    <?php endif; ?>
                                    
                                    <?php if (!$can_edit_category && !$can_delete_category): ?>
                                    <span class="text-muted">No Action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4 shadow">
            <div class="card-header bg-info text-white"><strong>Subkategori</strong> <i class="fas fa-tags"></i></div>
            <div class="card-body">
                
                <?php if ($can_add_subcategory || $can_edit_subcategory): ?>
                <form method="POST" action="category_form.php" class="form-inline mb-3">
                    <input type="hidden" name="subcategory_id" id="subcategory_id">
                    
                    <select name="category_id" id="sub_category_id" class="form-control mr-2" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php
                        $catList = Category::findAll();    
                        while($c = $catList->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    
                    <input type="text" name="subcategory_name" id="subcategory_name" class="form-control mr-2" placeholder="Nama subkategori" required>
                    <button type="submit" name="save_subcategory" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-secondary ml-2" onclick="resetSubcategoryForm()">Batal</button>
                </form>
                <?php else: ?>
                <p class="alert alert-info">Anda tidak memiliki izin untuk menambah atau mengubah Subkategori.</p>
                <?php endif; ?>


                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="tableSubCategory" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kategori</th>
                                <th>Subkategori</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no=1; while($sub = $subcategories->fetch_assoc()):    
                                $encrypted_sub_id = encrypt_id($sub['id']);
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($sub['category_name']) ?></td>
                                <td><?= htmlspecialchars($sub['name']) ?></td>
                                <td>
                                    <?php if ($can_edit_subcategory): ?>
                                    <button class="btn btn-sm btn-warning editSub me-1"    
                                            data-id="<?= $sub['id'] ?>"    
                                            data-name="<?= htmlspecialchars($sub['name']) ?>"
                                            data-category="<?= $sub['category_id'] ?>">Edit</button>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_delete_subcategory): ?>    
                                    <button class="btn btn-sm btn-danger"    
                                            data-toggle="modal"
                                            data-target="#deleteModal"
                                            data-type="subcategory"
                                            data-encrypted-id="<?= $encrypted_sub_id ?>"
                                            data-name="<?= htmlspecialchars($sub['name']) ?>">Nonaktifkan</button>
                                    <?php endif; ?>
                                    
                                    <?php if (!$can_edit_subcategory && !$can_delete_subcategory): ?>
                                    <span class="text-muted">No Action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php // include 'footer.php'; <--- BARIS INI DIHAPUS, KARENA DI BAWAH SUDAH ADA PEMANGGILAN KEDUA ?>
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
                Apakah Anda yakin ingin **menonaktifkan** <span id="itemTypeToDelete"></span>: <strong id="itemNameToDelete"></strong>?    
                Aksi ini adalah **Soft Delete**, yang akan mengubah kolom `status` menjadi **1**. Item yang sudah nonaktif tidak akan ditampilkan di daftar ini.
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
// Mengubah 'const' menjadi 'var' di sini untuk jaga-jaga, meskipun masalah utama adalah pemuatan ganda.
// Pastikan tidak ada deklarasi 'const BASE_URL' di file footer.php Anda.

function resetCategoryForm() {
    $('#category_id').val('');
    $('#category_name').val('');
}
function resetSubcategoryForm() {
    $('#subcategory_id').val('');
    $('#subcategory_name').val('');
    $('#sub_category_id').val('');
}

$(document).ready(function(){
    $('#tableCategory').DataTable();
    $('#tableSubCategory').DataTable();

    // Logika Edit Kategori
    $('.editCat').click(function(){
        $('#category_id').val($(this).data('id'));
        $('#category_name').val($(this).data('name')).focus();
    });

    // Logika Edit Subkategori
    $('.editSub').click(function(){
        $('#subcategory_id').val($(this).data('id'));
        $('#subcategory_name').val($(this).data('name'));
        $('#sub_category_id').val($(this).data('category'));
        $('#subcategory_name').focus();
    });
    
    // --- HANDLE DELETE BUTTON (Modal Hapus) MENGGUNAKAN show.bs.modal ---
    $('#deleteModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        
        var type = button.data('type'); 
        var encryptedId = button.data('encrypted-id');    
        var name = button.data('name');    

        var typeLabel = (type === 'category') ? 'Kategori' : 'Subkategori';
        
        var deleteUrl = (type === 'category')    
                            ? `category_list.php?delete_category=${encryptedId}`    
                            : `category_list.php?delete_subcategory=${encryptedId}`;

        var modal = $(this);
        modal.find('#itemTypeToDelete').text(typeLabel);
        modal.find('#itemNameToDelete').text(name);

        var confirmDeleteButton = modal.find('#confirmDeleteButton');
        confirmDeleteButton.attr('href', deleteUrl);
    });
});
</script>