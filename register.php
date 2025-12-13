<?php
// register.php
// Halaman Pendaftaran Pengguna Baru (Registration Page)

ob_start();

// HILANGKAN: header, footer, security.
require './src/Database.php';
require './src/user.php';

// =========================================================================
// KONFIGURASI FIXED ROLE & STATUS UNTUK PENDAFTARAN
// =========================================================================
// ASUMSI: ID Role 2 adalah role untuk User/Requester biasa. Ganti nilai ini jika berbeda.
$FIXED_ROLE_ID = 2;
// Status = 1 (Fixed: Non-aktif / Perlu Persetujuan)
// Nilai ini hanya digunakan untuk pesan, status aktual diatur di User::save()
$FIXED_STATUS_MESSAGE = 1;
// =========================================================================

$db = Database::getInstance(); // Inisialisasi Database

$err = '';
$msg = '';

// Inisialisasi variabel untuk tampilan form
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$password = '';
$confirm_pass = '';

$action_url = htmlspecialchars($_SERVER['PHP_SELF']);

if(isset($_POST['submit'])) {

 $name = trim($_POST['name']);
 $email = trim($_POST['email']);
 $phone = trim($_POST['phone']);
 $password = $_POST['password'] ?? '';
 $confirm_pass = $_POST['confirm-password'] ?? '';

 // --- 1. Validasi Input ---
 if (empty($name) || empty($email) || empty($phone)) {
  $err = "Semua kolom wajib diisi.";
 } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $err = "Format email tidak valid.";
 } else if (empty($password) || $password !== $confirm_pass || strlen($password) < 8) {
  $err = "Password minimal 8 karakter dan Konfirmasi password harus cocok.";
 }

 // --- 2. Validasi Ketersediaan Email (Penting untuk pendaftaran) ---
 if (empty($err)) {
  try {
   $check_sql = "SELECT id FROM users WHERE email = ?";
   $stmt = $db->prepare($check_sql);
   $stmt->bind_param("s", $email);
   $stmt->execute();
   $result = $stmt->get_result();

   if ($result->num_rows > 0) {
    $err = "Email ini sudah terdaftar. Silakan gunakan email lain atau Login.";
   }
   $stmt->close();
  } catch (Exception $e) {
   $err = "Gagal memeriksa ketersediaan email: " . $e->getMessage();
  }
 }


 // --- 3. Proses Penyimpanan ---
 if (empty($err)) {
  try {
   $hashed_password = password_hash($password, PASSWORD_DEFAULT);
  
   // Buat objek User dengan nilai fixed role_id
   $user = new User([
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'password' => $hashed_password,
    'role_id' => $FIXED_ROLE_ID,// Fixed: User Role
    'last_password' => $hashed_password
   ]);

   // >>> TAMBAHKAN PROPERTI KHUSUS UNTUK MENANDAI REGISTRASI <<<
   $user->is_from_register = true;
   // >>> INI AKAN MEMBUAT STATUS = 1 DI USER::SAVE() <<<

   $user->save();
   $msg = "Pendaftaran berhasil! Akun Anda telah dibuat dan berstatus **Non-aktif (Menunggu Persetujuan Admin)**. Silakan hubungi Admin untuk aktivasi.";
  
      // HAPUS REDIRECT (TETAP DI HALAMAN INI)
      // Kita perlu mereset variabel form agar form kosong setelah sukses
      $name = '';
      $email = '';
      $phone = '';
      $password = '';
      $confirm_pass = '';
      // TIDAK ADA header('Location: ...');
   

  } catch (Exception $e) {
  $err = "Gagal memproses pendaftaran: " . $e->getMessage();
  }
 }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
 <meta charset="utf-8">
 <meta http-equiv="X-UA-Compatible" content="IE=edge">
 <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
 <title>Helpdesk - Pendaftaran</title>
 <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
 <link href="css/sb-admin.css" rel="stylesheet">
</head>

<body class="bg-dark">
 <div class="container">
  <div class="card card-login mx-auto mt-5">
   <div class="card-header bg-primary text-white">
    <h5 class="mb-0 text-center"><i class="fas fa-user-plus me-2"></i> Pendaftaran Akun Baru</h5>
   </div>
   <div class="card-body">
   
        <?php if(strlen($err) > 1) :?>
    <div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($err);?></div>
    <?php endif?>
        
        <?php if(strlen($msg) > 1) :?>
    <div class="alert alert-success text-center my-3" role="alert"> <strong>Sukses! </strong> <?php echo htmlspecialchars($msg);?></div>
    <?php endif?>

    <form method="POST" action="<?php echo $action_url; ?>">
     <div class="form-group mb-3">
      <label for="name">Nama Lengkap</label>
      <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
     </div>
    
     <div class="form-group mb-3">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
     </div>

     <div class="form-group mb-3">
      <label for="phone">Telepon</label>
      <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>" required>
     </div>

     <div class="form-group mb-3">
      <label for="password">Password</label>
      <input type="password" name="password" id="password" class="form-control" required placeholder="Minimal 8 karakter">
     </div>

     <div class="form-group mb-4">
      <label for="confirm-password">Konfirmasi Password</label>
      <input type="password" name="confirm-password" id="confirm-password" class="form-control" required>
     </div>
         
          <input type="hidden" name="role_id" value="<?php echo $FIXED_ROLE_ID; ?>">
               <button type="submit" name="submit" class="btn btn-primary btn-block">Daftar</button>
    </form>
        <div class="text-center mt-3">
          <a class="d-block small" href="index.php">Sudah punya akun? Login!</a>
        </div>
   </div>
  </div>
 </div>

 <script src="vendor/jquery/jquery.min.js"></script>
 <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
 <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

</body>
</html>
<?php ob_end_flush(); ?>