<?php
// menu.php - DAFTAR MENU (REVISI FINAL UNTUK BOOTSTRAP 4 MODAL FIX)
ob_start();

// PASTIKAN SEMUA FILE CORE DIMUAT DI SINI
require_once './src/menu.php';
require_once './src/Database.php';
require_once './src/security.php';

// Pastikan Auth dimuat, karena digunakan di header.php dan di sini
require_once './src/Auth.php'; 

include './header.php';

$rbac_target = 'menu.php';

// KONTROL AKSES VIEW:
if (!isset($auth)) {
    // Inisialisasi Auth jika belum ada (walaupun seharusnya sudah ada dari header)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $auth = new Auth($_SESSION['user']->role_id ?? 0, Database::getInstance());
}

$auth->authorize($rbac_target, 'view');

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// --- HANDLE SOFT DELETE ---
if (isset($_GET['delete_id'])) {
    $current_msg = '';
    $current_err = '';
    
    if (!$auth->can($rbac_target, 'delete')) {
        $current_err = "Akses Ditolak: Anda tidak memiliki hak untuk menonaktifkan Menu.";
    } else {
        $encrypted_id = $_GET['delete_id'];
        $delete_id = decrypt_id($encrypted_id);
        
        if ($delete_id > 0) {
            try {
                $menu_to_delete = Menu::find($delete_id, true);
                if ($menu_to_delete) {
                    $menu_name_safe = htmlspecialchars($menu_to_delete->menu_name);
                    
                    // Lakukan soft delete
                    $menu_to_delete->softDelete(); 
                    
                    $current_msg = "Menu **{$menu_name_safe}** berhasil dinonaktifkan (Soft Delete).";
                } else {
                    $current_err = "Menu tidak ditemukan (ID: {$delete_id}).";
                }
            } catch (Exception $e) {
                $current_err = "Gagal menonaktifkan Menu: " . $e->getMessage();
            }
        } else {
            $current_err = "ID Menu tidak valid atau terenkripsi dengan salah.";
        }
    }
    
    header('Location: menu.php?msg=' . urlencode($current_msg) . '&err=' . urlencode($current_err));
    exit();
}

// --- AMBIL DATA ---
$menus = Menu::findAll(true); 

// Buat peta ID ke Nama Menu untuk kolom Parent
$menu_map = [];
foreach ($menus as $menu_item) {
    $menu_map[$menu_item->menu_id] = $menu_item->menu_name;
}
?>
<div id="content-wrapper">
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Daftar Menu</li>
        </ol>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">☰ Manajemen Menu Sistem</h3>
            <?php if ($auth->can($rbac_target, 'add')): ?>
                <a class="btn btn-primary btn-sm" href="./menu_form.php"><i class="fas fa-plus me-1"></i> Menu Baru</a>
            <?php endif; ?>
        </div>
        
        <?php if(strlen($err) > 1) :?><div class="alert alert-danger my-3" role="alert"><strong>Gagal!</strong> <?php echo htmlspecialchars($err);?></div><?php endif?>
        <?php if(strlen($msg) > 1) :?><div class="alert alert-success my-3" role="alert"><strong>Berhasil!</strong> <?php echo htmlspecialchars($msg);?></div><?php endif?>

        <div class="card mb-3 shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped" id="dataTable" width="100%" cellspacing="0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Order</th>
                                <th>Nama</th>
                                <th>Menu Induk</th>
                                <th>URL (Akses RBAC)</th>
                                <th>Icon</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($menus as $menu):
                                $encrypted_menu_id = encrypt_id($menu->menu_id);
                                
                                $is_child = !empty($menu->parent_id);
                                $row_class = $is_child ? 'table-info' : '';
                                if ($menu->status == 1) {
                                    $row_class = 'table-secondary text-muted';
                                }
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><?php echo htmlspecialchars($menu->menu_id ?? '') ?></td>
                                <td><?php echo htmlspecialchars($menu->menu_order ?? '') ?></td>
                                <td>
                                    <?php echo $is_child ? '<i class="fas fa-level-up-alt fa-rotate-90 me-2 text-primary"></i>' : ''; ?>
                                    <strong><?php echo htmlspecialchars($menu->menu_name ?? '') ?></strong>
                                </td>
                                <td>
                                    <?php 
                                        if ($is_child) {
                                            $parent_name = $menu_map[$menu->parent_id] ?? 'Unknown Parent';
                                            echo "<span class='badge badge-info text-dark'>" . htmlspecialchars($parent_name) . "</span>"; // Bootstrap 4: badge-info
                                        } else {
                                            echo "<span class='badge badge-success text-white'>Utama</span>"; // Bootstrap 4: badge-success
                                        }
                                    ?>
                                </td>
                                <td><code><?php echo htmlspecialchars($menu->menu_url ?? '') ?></code></td>
                                <td><i class="<?php echo htmlspecialchars($menu->menu_icon ?? '') ?>"></i> <code><?php echo htmlspecialchars($menu->menu_icon ?? '') ?></code></td>
                                <td>
                                    <?php
                                    $status_text = ($menu->status == 0) ? 'Aktif' : 'Nonaktif';
                                    $status_class = ($menu->status == 0) ? 'badge badge-success text-white' : 'badge badge-danger text-white'; // Bootstrap 4: badge-success/danger
                                    echo "<span class='{$status_class}'>" . $status_text . "</span>";
                                    ?>
                                </td>
                                <td>
                                    <?php if ($auth->can($rbac_target, 'edit')): ?>
                                        <a href="./menu_form.php?id=<?php echo $encrypted_menu_id; ?>" class="btn btn-warning btn-sm mr-1" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    if ($auth->can($rbac_target, 'delete') && $menu->status == 0):
                                    ?>
                                        <button type="button"
                                            class="btn btn-danger btn-sm"
                                            data-toggle="modal"
                                            data-target="#deleteModal"
                                            data-menu-id="<?php echo $encrypted_menu_id; ?>"
                                            data-menu-name="<?php echo htmlspecialchars($menu->menu_name ?? ''); ?>"
                                            title="Nonaktifkan Menu">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach?>
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
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle mr-2"></i> Konfirmasi Nonaktifkan Menu</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin **menonaktifkan** menu <strong id="menuNameToDelete"></strong>? Statusnya akan berubah menjadi **Tidak Aktif**.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <a id="confirmDeleteButton" href="#" class="btn btn-danger">Ya, Nonaktifkan</a>
            </div>
        </div>
    </div>
</div>
<script>
	$(document).ready(function() {
		
		// 1. Inisialisasi DataTables
		if ($.fn.DataTable) {
			$('#dataTable').DataTable({
				// Nonaktifkan pengurutan otomatis
				"ordering": false 
			});
		} else {
			console.error("DataTables ($.fn.DataTable) tidak terdefinisi!");
		}
	});
</script>

<?php include './footer.php'; ?>
<script>


    // 2. Logika Modal Hapus/Nonaktifkan (Soft Delete) - Khusus Bootstrap 4
    
    // Kita gunakan delegated event 'show.bs.modal'
    $('#deleteModal').on('show.bs.modal', function (event) {
        
        // Ambil tombol yang memicu modal
        // Note: Bootstrap 4 menggunakan event.relatedTarget
        const button = $(event.relatedTarget);
        
        // Ambil data attributes dari tombol
        // Penting: Pastikan data() method jQuery digunakan
        const menuIdEncrypted = button.data('menu-id');
        const menuName = button.data('menu-name');

        const modal = $(this);
        
        // 1. Set nama menu di body modal
        modal.find('#menuNameToDelete').text(menuName);
        
        // 2. BUAT URL penghapusan
        const deleteUrl = 'menu.php?delete_id=' + menuIdEncrypted;
        
        // 3. SET ATRIBUT HREF pada tombol konfirmasi modal
        modal.find('#confirmDeleteButton').attr('href', deleteUrl);

    });
    
    // Tambahan: Pastikan tombol batal berfungsi dengan benar jika data-dismiss="modal" hilang
    $('.modal-footer .btn-secondary').on('click', function() {
        $('#deleteModal').modal('hide');
    });


</script>
