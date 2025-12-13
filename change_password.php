<?php
// change_password.php (FINAL COPY)

ob_start(); 
include './header.php';
require './src/user.php';
require './src/security.php'; // WAJIB

$menu_url = 'user_list.php'; 

// =========================================================================
// DEKRIPSI ID PENGGUNA DARI URL
// =========================================================================
$encrypted_id = $_GET['id'] ?? '';
$user_id = decrypt_id($encrypted_id); // DEKRIPSI ID
// =========================================================================

// Pengecekan ID valid setelah dekripsi
if ($user_id <= 0) {
    header('Location: user_list.php?err=' . urlencode('ID pengguna tidak valid atau gagal didekripsi.'));
    exit();
}

// =========================================================================
// RBAC CHECK 1: Cek Izin Edit
// =========================================================================
try {
    if (isset($auth)) {
        $auth->authorize($menu_url, 'edit');
    }
} catch (Exception $e) {
    header('Location: ./dashboard.php?err=' . urlencode('Akses Ditolak: ' . $e->getMessage()));
    exit();
}
// =========================================================================

$err = '';
$msg = '';

// 1. Ambil data pengguna yang akan diganti passwordnya (menggunakan ID yang sudah didekripsi)
$user_to_change = User::find($user_id);

if (!$user_to_change) {
    header('Location: user_list.php?err=' . urlencode('Pengguna tidak ditemukan atau sudah dinonaktifkan.'));
    exit();
}

$name = $user_to_change->name;
// URL action form tetap menggunakan ID terenkripsi yang masuk
$action_url = htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $encrypted_id; 


if(isset($_POST['submit'])) {

    // RBAC CHECK 2
    try {
        if (isset($auth) && !$auth->can($menu_url, 'edit')) {
            throw new Exception("Anda tidak memiliki izin untuk mengubah data pengguna.");
        }
    } catch (Exception $e) {
       $err = "Akses Ditolak: " . $e->getMessage();
    }

    if (!$err) {
        $password = $_POST['password'];
        $confirm_pass = $_POST['confirm-password'];
        
        // --- VALIDASI PASSWORD ---
        if(strlen($password) < 8 ) {
            $err = "Password minimal harus 8 karakter.";
        } else if($password != $confirm_pass) {
            $err = "Konfirmasi password tidak cocok.";
        } 
        // -------------------------
        
        else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $user_to_change->updatePassword($hashed_password); 
                
                $msg = "Password pengguna **" . htmlspecialchars($name) . "** berhasil diperbarui!";
                
                header('Location: user_list.php?msg=' . urlencode($msg));
                exit();

            } catch (Exception $e) {
               $err = "Gagal mengganti password: " . $e->getMessage();
            }
        }
    }
}

?>

<div id="content-wrapper">

    <div class="container-fluid">

        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="./user_list.php">Daftar Pengguna</a></li>
            <li class="breadcrumb-item active">Ganti Password</li>
        </ol>

        <div class="card mb-3 shadow">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i> Ganti Password: <?php echo htmlspecialchars($name); ?></h5>
            </div>
            <div class="card-body">
                
                <?php if(strlen($err) > 1) :?>
                <div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($err);?></div>
                <?php endif?>

                <form method="POST" action="<?php echo $action_url; ?>">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            
                            <div class="mb-3 row">
                                <label for="password" class="col-sm-4 col-form-label">Password Baru</label>
                                <div class="col-sm-8">
                                    <input type="password" name="password" id="password" class="form-control" required placeholder="Masukkan Password Baru (min 8 karakter)">
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="confirm-password" class="col-sm-4 col-form-label">Konfirmasi Password</label>
                                <div class="col-sm-8">
                                    <input type="password" name="confirm-password" id="confirm-password" class="form-control" required placeholder="Ulangi Password Baru">
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" name="submit" class="btn btn-lg btn-danger">Ubah Password</button>
                                <a href="user_list.php" class="btn btn-lg btn-secondary">Batal</a>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php 
include './footer.php';
ob_end_flush(); 
?>