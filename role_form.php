<?php
// role_form.php - Form Create/Edit Peran

ob_start();
//session_start(); // Biarkan ini dikomentari/dihapus sesuai kesepakatan sebelumnya

// 1. Muat Dependensi
require_once './src/Database.php'; 
require_once './src/Auth.php'; 
require_once './src/security.php'; // Untuk encrypt_id/decrypt_id
require_once './src/role.php'; 

// 2. Inisialisasi Koneksi dan Auth
// Ambil instance Singleton, yang diasumsikan langsung mengembalikan objek mysqli
$db_instance = Database::getInstance(); 
$db = $db_instance; // PERBAIKAN: Gunakan $db_instance secara langsung

// Deklarasikan $db sebagai global agar Auth bisa mengaksesnya, sesuai pola Anda
global $db; 

$user_role_id = $_SESSION['user_data']['role_id'] ?? 0;
$auth = new Auth($user_role_id); 


// 3. Muat Header (Setelah Auth terinisialisasi)
include './header.php'; 

// 4. Proses ID dari URL (Penerapan security.php)
$current_page_resource = 'role.php'; 

$encrypted_id = $_GET['id'] ?? '';
$role_id = decrypt_id($encrypted_id); // Menggunakan fungsi decrypt_id
$is_editing = $role_id > 0;

$err = '';
$msg = '';

// === KONTROL AKSES FORM CREATE/EDIT (VIEW) ===
// Pengecekan ini tetap penting untuk mencegah loading form jika tidak diizinkan.
if ($is_editing) {
    // Jika EDIT, cek izin 'edit' pada resource role.php
    if(!$auth->can($current_page_resource, 'edit')){
        header('Location: ./role.php?err=' . urlencode('Akses Ditolak: Anda tidak memiliki izin untuk mengedit Peran.'));
        exit();
    }
} else {
    // Jika CREATE (Add), cek izin 'add' pada resource role.php
    if(!$auth->can($current_page_resource, 'add')){
        header('Location: ./role.php?err=' . urlencode('Akses Ditolak: Anda tidak memiliki izin untuk menambah Peran baru.'));
        exit();
    }
}
// ========================================


// --- 1. INISIALISASI DATA ---
$role = new Role();
$title = "Tambah Peran Baru";
$card_class = "bg-primary text-white";
$role_name = '';
$role_desc = '';

if ($is_editing) {
    $role = Role::find($role_id);
    if (!$role) {
        header('Location: role.php?err=' . urlencode('Peran tidak ditemukan.'));
        exit();
    }
    $title = "Edit Peran: " . htmlspecialchars($role->role_name ?? '');
    $card_class = "bg-warning text-dark";
    $role_name = $role->role_name;
    $role_desc = $role->role_desc;
    
    // PENTING: Jangan izinkan edit Role ID 1 (Super Admin)
    // Walaupun sudah ada di model, ini untuk UX/Redirect yang lebih baik.
    if ($role_id == 1) {
        header('Location: role.php?err=' . urlencode('Akses Ditolak: Role Super Admin tidak dapat diubah melalui form ini.'));
        exit();
    }
}

// Ambil nilai dari POST (mengganti nilai yang sudah ada)
$role_name = $_POST['role_name'] ?? $role_name;
$role_desc = $_POST['role_desc'] ?? $role_desc;


if(isset($_POST['submit'])) {

    // KONTROL AKSES SERVER-SIDE PADA SUBMIT - DIHAPUS/DISESUAIKAN.
    // Pengecekan dilakukan di dalam $role->save($auth) atau $role->update($auth)
    // dan akan menghasilkan Exception jika otorisasi gagal.
    
    // Lanjutkan pemrosesan, asumsikan pengecekan akses akan ditangani oleh Model/Exception
    if (empty($err)) {
        $role_name_input = trim($_POST['role_name']);
        $role_desc_input = trim($_POST['role_desc']);
        
        // --- VALIDASI ---
        if(empty($role_name_input)){
            $err = "Nama Peran wajib diisi.";
        }
        
        else {
            try {
                $role->role_name = $role_name_input;
                $role->role_desc = $role_desc_input;

                if ($is_editing) {
                    $role->role_id = $role_id; 
                    // MEMANGGIL UPDATE DENGAN OBJEK $AUTH
                    $role->update($auth); 
                    $final_msg = "Peran **" . htmlspecialchars($role_name_input) . "** berhasil diperbarui!";
                } else {
                    // MEMANGGIL SAVE DENGAN OBJEK $AUTH
                    $role->save($auth);
                    $final_msg = "Peran **" . htmlspecialchars($role_name_input) . "** berhasil ditambahkan!";
                }
                
                header('Location: role.php?msg=' . urlencode($final_msg));
                exit();

            } catch (Exception $e) {
               // Menangkap Exception, termasuk pengecekan RBAC yang gagal
               $err = "Gagal menyimpan data: " . $e->getMessage();
            }
        }
    }
}

?>

<div id="content-wrapper">
    <div class="container-fluid">

        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="./role.php">Daftar Peran</a></li>
            <li class="breadcrumb-item active"><?php echo $title; ?></li>
        </ol>

        <div class="card mb-3 shadow">
            <div class="card-header <?php echo $card_class; ?>">
                <h5 class="mb-0"><i class="fas fa-lock me-2"></i> <?php echo $title; ?></h5>
            </div>
            <div class="card-body">
                
                <?php if(strlen($err) > 1) :?>
                <div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($err);?></div>
                <?php endif?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . ($is_editing ? '?id=' . $encrypted_id : ''); ?>">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            
                            <div class="mb-3 row">
                                <label for="role_name" class="col-sm-4 col-form-label">Nama Peran</label>
                                <div class="col-sm-8">
                                    <input type="text" name="role_name" id="role_name" class="form-control" value="<?php echo htmlspecialchars($role_name ?? ''); ?>" required placeholder="Contoh: admin, user, teknisi">
                                </div>
                            </div>
                            
                            <div class="mb-3 row">
                                <label for="role_desc" class="col-sm-4 col-form-label">Deskripsi</label>
                                <div class="col-sm-8">
                                    <textarea name="role_desc" id="role_desc" class="form-control" placeholder="Penjelasan singkat peran"><?php echo htmlspecialchars($role_desc ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" name="submit" class="btn btn-lg btn-<?php echo $is_editing ? 'warning' : 'primary'; ?> <?php echo $is_editing ? 'text-dark' : 'text-white'; ?>">
                                    <i class="fas fa-save me-1"></i> Simpan Peran
                                </button>
                                <a href="role.php" class="btn btn-lg btn-secondary">Batal</a>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include './footer.php'; ?>

<?php
ob_end_flush(); 
?>