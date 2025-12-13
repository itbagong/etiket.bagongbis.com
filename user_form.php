<?php
// user_form.php (FINAL COPY)

ob_start(); 
include './header.php'; 
require './src/user.php'; 
require './src/role.php'; 
require './src/security.php'; // WAJIB

$err = '';
$msg = '';

// =========================================================================
// 1. DEKRIPSI ID DARI URL (MODE EDIT)
// =========================================================================
$encrypted_id = $_GET['id'] ?? '';
$user_id = decrypt_id($encrypted_id); // DEKRIPSI ID
$is_edit = $user_id > 0; // Mode edit jika ID berhasil didekripsi
// =========================================================================

$user_to_edit = null;
$page_title = $is_edit ? 'Edit Pengguna' : 'Buat Pengguna Baru';
$submit_btn_text = $is_edit ? 'Simpan Perubahan' : 'Buat Pengguna';
$card_header_class = $is_edit ? 'bg-warning text-dark' : 'bg-primary text-white';
$submit_btn_class = $is_edit ? 'btn-warning text-dark' : 'btn-primary';

$all_roles = Role::findAllActive(); 
$default_role_id = 2; 

// --- MODE EDIT ---
if ($is_edit) {
    // $auth->authorize('user_form.php', 'edit'); // Uncomment jika Auth digunakan
    
    $user_to_edit = User::find($user_id); // Gunakan ID yang sudah didekripsi

    if (!$user_to_edit) {
        header('Location: user_list.php?err=' . urlencode('Pengguna tidak ditemukan atau ID tidak valid.'));
        exit();
    }
    
    // Set Data
    $name = $user_to_edit->name;
    $email = $user_to_edit->email;
    $phone = $user_to_edit->phone;
    $role_id = $user_to_edit->role_id; 
    
    // ACTION URL: Menggunakan ID TERENKRIPSI
    $action_url = htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $encrypted_id; 

} else {
    // --- MODE CREATE ---
    // $auth->authorize('user_form.php', 'add'); // Uncomment jika Auth digunakan
    
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $role_id = $_POST['role_id'] ?? $default_role_id; 
    $password = '';
    $confirm_pass = '';
    $action_url = htmlspecialchars($_SERVER['PHP_SELF']);
}


if(isset($_POST['submit'])) {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role_id = (int)$_POST['role_id']; 

    // Sederhana: Validasi Role
    $valid_role = false;
    foreach ($all_roles as $r) {
        if ((int)$r->role_id === $role_id) {
            $valid_role = true;
            break;
        }
    }
    if (!$valid_role) {
        $err = "Role yang dipilih tidak valid.";
    }

    if (empty($err)) {
        // $action_permission = $is_edit ? 'edit' : 'add'; // Uncomment jika Auth digunakan
        // $auth->authorize('user_form.php', $action_permission);

        try {
            if ($is_edit) {
                // LOGIKA UPDATE USER
                $user_to_edit->name = $name;
                $user_to_edit->email = $email;
                $user_to_edit->phone = $phone;
                $user_to_edit->role_id = $role_id; 

                $user_to_edit->update();
                
                $msg = "Pengguna **" . htmlspecialchars($name) . "** berhasil diperbarui!";
                
                header('Location: user_list.php?msg=' . urlencode($msg));
                exit();

            } else {
                // LOGIKA TAMBAH USER BARU
                $password = $_POST['password'] ?? '';
                $confirm_pass = $_POST['confirm-password'] ?? '';
                
                if (empty($password) || $password !== $confirm_pass || strlen($password) < 8) {
                    throw new Exception("Password minimal 8 karakter dan Konfirmasi password harus cocok.");
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $user = new User([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $hashed_password,
                    'role_id' => $role_id, 
                    'last_password' => $hashed_password 
                ]);

                $user->save();
                $msg = "Pengguna **" . htmlspecialchars($name) . "** berhasil dibuat!";
                
                header('Location: user_list.php?msg=' . urlencode($msg));
                exit();
            }

        } catch (Exception $e) {
           $err = "Gagal memproses pengguna: " . $e->getMessage();
        }
    }
}
?>

<div id="content-wrapper">
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="./user_list.php">Daftar Pengguna</a></li>
            <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
        </ol>

        <div class="card mb-3 shadow">
            <div class="card-header <?php echo $card_header_class; ?>">
                <h5 class="mb-0"><i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus-circle'; ?> me-2"></i> <?php echo $page_title; ?></h5>
            </div>
            <div class="card-body">
                
                <?php if(strlen($err) > 1) :?>
                <div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($err);?></div>
                <?php endif?>

                <form method="POST" action="<?php echo $action_url; ?>">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            
                            <div class="mb-3 row">
                                <label for="name" class="col-sm-4 col-form-label">Nama Lengkap</label>
                                <div class="col-sm-8">
                                    <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 row">
                                <label for="email" class="col-sm-4 col-form-label">Email</label>
                                <div class="col-sm-8">
                                    <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="phone" class="col-sm-4 col-form-label">Telepon</label>
                                <div class="col-sm-8">
                                    <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($phone ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="role_id" class="col-sm-4 col-form-label">Role</label>
                                <div class="col-sm-8">
                                    <select name="role_id" id="role_id" class="form-control" required>
                                        <option value="">-- Pilih Role --</option>
                                        <?php 
                                        foreach ($all_roles as $role_item): ?>
                                            <option 
                                                value="<?php echo $role_item->role_id; ?>" 
                                                <?php echo ((int)($role_id ?? 0) === (int)$role_item->role_id) ? 'selected' : ''; ?>
                                            >
                                                <?php echo htmlspecialchars($role_item->role_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <?php if (!$is_edit): ?>
                            <div class="mb-3 row">
                                <label for="password" class="col-sm-4 col-form-label">Password</label>
                                <div class="col-sm-8">
                                    <input type="password" name="password" id="password" class="form-control" required placeholder="Minimal 8 karakter">
                                    <div class="form-text">Isi password saat membuat pengguna baru.</div>
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="confirm-password" class="col-sm-4 col-form-label">Konfirmasi Password</label>
                                <div class="col-sm-8">
                                    <input type="password" name="confirm-password" id="confirm-password" class="form-control" required>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="text-center mt-4">
                                <button type="submit" name="submit" class="btn btn-lg <?php echo $submit_btn_class; ?>"><?php echo $submit_btn_text; ?></button>
                                <a href="user_list.php" class="btn btn-lg btn-secondary">Batal</a>
                            </div>
                            
                            <?php if ($is_edit): ?>
                            <hr class="my-4">
                            <div class="text-center">
                                <a href="change_password.php?id=<?php echo $encrypted_id; ?>" class="btn btn-outline-danger">Ganti Password</a>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php include './footer.php'; ?>
</div>
<?php ob_end_flush(); ?>