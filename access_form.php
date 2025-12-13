<?php
// access_form.php - Form Pengaturan Izin (Checklist RBAC)
ob_start();
include './header.php'; // Asumsi $db dan $auth dibuat di header.php

require_once './src/role.php'; 
require_once './src/menu.php';
require_once './src/AccessService.php'; // <--- BARU: Include AccessService

// Inisialisasi Service
// Asumsi $db sudah tersedia secara global setelah include header.php
global $db;
$accessService = new AccessService($db); 

require_once './src/security.php'; // Pastikan encrypt_id/decrypt_id tersedia

$current_page = basename(__FILE__); // access_form.php
$err = '';
$msg = $_GET['msg'] ?? ''; // Ambil pesan dari redirect

// =========================================================================
// DEKRIPSI ID & PENGAMBILAN DATA ROLE
// =========================================================================

// Mengambil ID yang terenkripsi dari URL (sekarang menggunakan ?id=)
$encrypted_id = $_GET['id'] ?? '';
$role_id = decrypt_id($encrypted_id);

if ($role_id === false || $role_id <= 0) {
    header('Location: role.php?err=' . urlencode('ID Role tidak valid atau gagal didekripsi.'));
    exit();
}

$role = Role::find($role_id);
if (!$role) {
    header('Location: role.php?err=' . urlencode('Role tidak ditemukan.'));
    exit();
}

// =========================================================================
// RBAC CHECK 1: AKSES HALAMAN & KONTROL TOMBOL
// =========================================================================

// Cek hak Edit dan Delete (Hak yang dibutuhkan untuk Update)
$can_edit = $auth->can($current_page, 'edit');
$can_delete = $auth->can($current_page, 'delete');

// Variabel Kontrol Utama: TRUE jika user punya hak EDIT ATAU DELETE
$can_update_form = $can_edit || $can_delete; 

// String untuk menonaktifkan input (checkbox) jika tidak bisa update
$disabled_attr = $can_update_form ? '' : 'disabled'; 

// Pengecekan Akses Halaman (Minimal harus punya hak view atau salah satu hak update)
if (!$auth->can($current_page, 'view') && !$can_update_form) {
    // Jika tidak punya hak view DAN tidak punya hak edit/delete, tolak akses.
    $message = 'Akses Ditolak: Anda tidak memiliki izin untuk melihat atau mengelola Akses Peran.';
    ob_clean();
    header('Location: ./role.php?err=' . urlencode($message));
    exit();
}
// =========================================================================

// Ambil semua menu dan izin akses saat ini
$menus = Menu::findAll();
$current_access = $accessService->getCurrentAccess($role_id); // Menggunakan AccessService

// --- 2. HANDLE SUBMIT (UPSERT AKSES) ---
if(isset($_POST['submit']) && empty($err)) {
    // Safety check POST request: hanya izinkan jika punya hak UPDATE
    if (!$can_update_form) {
        $err = "Akses Ditolak: Anda tidak memiliki izin (Edit/Delete) untuk menyimpan Izin Akses.";
    }
    
    if (empty($err)) {
        try {
            // Memanggil fungsi saveAccess dari AccessService
            $accessService->saveAccess($role_id, $menus, $_POST);

            $msg = "Izin Akses untuk Role **" . htmlspecialchars($role->role_name) . "** berhasil diperbarui!";
            
            // Redirect setelah sukses (ID ENKRIPSI)
            ob_clean();
            header('Location: access_form.php?id=' . encrypt_id($role_id) . '&msg=' . urlencode($msg));
            exit();

        } catch (Exception $e) {
            $err = "Gagal menyimpan Izin Akses: " . $e->getMessage();
            // Ambil ulang data akses terbaru jika terjadi error (tidak perlu, karena data yang ditampilkan adalah data POST)
            // Namun, jika ingin konsisten, bisa di-load ulang:
            $current_access = $accessService->getCurrentAccess($role_id); 
        }
    }
}

?>

<div id="content-wrapper">
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="./role.php">Daftar Peran</a></li>
            <li class="breadcrumb-item active">Atur Akses</li>
        </ol>

        <div class="card mb-3 shadow">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i> Pengaturan Akses untuk Role: <strong><?php echo htmlspecialchars($role->role_name); ?></strong></h5>
            </div>
            <div class="card-body">
                
                <?php if(strlen($err) > 1) :?><div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($err);?></div><?php endif?>
                <?php if(strlen($msg) > 1) :?><div class="alert alert-success text-center my-3" role="alert"> <strong>Sukses! </strong> <?php echo htmlspecialchars($msg);?></div><?php endif?>

                <form method="POST" action="access_form.php?id=<?php echo encrypt_id($role_id); ?>">
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>Menu / Modul</th>
                                    <th class="text-center">View (Lihat)</th>
                                    <th class="text-center">Add (Tambah)</th>
                                    <th class="text-center">Edit (Ubah)</th>
                                    <th class="text-center">Delete (Hapus)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($menus as $menu): ?>
                                <?php $mid = $menu->menu_id; ?>
                                <?php $access = $current_access[$mid] ?? ['can_view' => 0, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0]; ?>
                                <tr>
                                    <td>
                                        <i class="<?php echo htmlspecialchars($menu->menu_icon ?? 'fas fa-cog'); ?>"></i> 
                                        <?php echo htmlspecialchars($menu->menu_name); ?> 
                                        <small class="text-muted">(<?php echo htmlspecialchars($menu->menu_url); ?>)</small>
                                    </td>
                                    
                                    <td class="text-center">
                                        <input type="checkbox" name="view[<?php echo $mid; ?>]" value="1" <?php echo $access['can_view'] ? 'checked' : ''; ?> <?php echo $disabled_attr; ?>>
                                    </td>
                                    
                                    <td class="text-center">
                                        <input type="checkbox" name="add[<?php echo $mid; ?>]" value="1" <?php echo $access['can_add'] ? 'checked' : ''; ?> <?php echo $disabled_attr; ?>>
                                    </td>
                                    
                                    <td class="text-center">
                                        <input type="checkbox" name="edit[<?php echo $mid; ?>]" value="1" <?php echo $access['can_edit'] ? 'checked' : ''; ?> <?php echo $disabled_attr; ?>>
                                    </td>
                                    
                                    <td class="text-center">
                                        <input type="checkbox" name="delete[<?php echo $mid; ?>]" value="1" <?php echo $access['can_delete'] ? 'checked' : ''; ?> <?php echo $disabled_attr; ?>>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-center mt-4">
                        <?php if ($can_update_form): // HANYA TAMPILKAN TOMBOL JIKA ADA HAK EDIT ATAU DELETE ?>
                        <button type="submit" name="submit" class="btn btn-lg btn-success">
                            <i class="fas fa-sync-alt me-1"></i> Update Izin Akses
                        </button>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            Anda hanya memiliki izin **Lihat** untuk halaman ini. Pengaturan tidak dapat diubah.
                        </div>
                        <?php endif; ?>

                        <a href="role.php" class="btn btn-lg btn-secondary">Kembali ke Daftar Role</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include './footer.php'; ?> 
	
</div>