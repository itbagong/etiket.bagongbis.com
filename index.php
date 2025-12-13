<?php
// index.php (FINAL COPY DENGAN PERBAIKAN LOGIN SESSION & REDIRECT BERBASIS PERAN)

session_start();

// Jika sudah login, alihkan ke dashboard (akan dicek lebih lanjut di bawah)
if (isset($_SESSION['logged-in']) && $_SESSION['logged-in'] === true) {
// Lakukan redirect awal (sebelum form login diproses)
$user_role_on_load = $_SESSION['user']->role ?? '';
$target_dashboard = './dashboard.php';
if ($user_role_on_load !== 'admin' && $user_role_on_load !== 'teknisi') {
 $target_dashboard = './user_dashboard.php';
}
header("Location: {$target_dashboard}");
exit();
}

// Pastikan file database tersedia
require_once './src/Database.php';
$db = Database::getInstance();

$err = '';

if(isset($_POST['submit'])){

// Ambil dan Bersihkan Input
$email = trim($_POST['email']);
$password = $_POST['password'];

if(strlen($email) < 1 ){
 $err = 'Please enter email address';
} else if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
 $err = 'Please enter a valid email adddress';
} else if(strlen($password) < 1){
 $err = "Please enter your password";
} else {

 // Query untuk mengambil ID, NAMA, EMAIL, PASSWORD, dan ROLE_ID
 $sql = "SELECT id, name, email, password, role_id FROM users WHERE email = ?";

 // 1. Persiapan Statement
 $stmt = $db->prepare($sql);

 if ($stmt === false) {
 $err = "Database error: " . $db->error;
 } else {
 // 2. Binding Parameter: "s" untuk string (email)
 $stmt->bind_param("s", $email);

 // 3. Eksekusi
 $stmt->execute();
 $res = $stmt->get_result(); // Ambil hasilnya

 if($res->num_rows < 1){
  $err = "No user found or user is inactive.";
 } else {
  $user = $res->fetch_object();

  if(password_verify($password , $user->password)){
 
  // ⚡ PENCEGAHAN SESSION FIXATION ⚡
  session_regenerate_id(true);

  // Query tambahan untuk mendapatkan role name
  $role_id_safe = $db->real_escape_string($user->role_id);
  $query_role = "SELECT role_name FROM role WHERE role_id = '{$role_id_safe}'";
  $role_result = $db->query($query_role);
 
  $role_name = 'guest'; // Default
  if ($role_result && $role_result->num_rows > 0) {
   $role_data = $role_result->fetch_assoc();
   $role_name = $role_data['role_name'];
  }

  // Buat Objek Sesi
  $session_user_data = (object)[
   'id' => $user->id,
   'name' => $user->name,
   'email' => $user->email,
   'role_id' => $user->role_id,
   'role' => $role_name // Tambahkan nama peran (role name)
  ];
 
  $_SESSION['logged-in'] = true;
  $_SESSION['user'] = $session_user_data;

  // =======================================================
  // 5. REDIRECT BERBASIS PERAN (LOGIKA INI YANG BARU)
  // =======================================================
 
  // Default untuk Admin/Teknisi
  $target_dashboard = './dashboard.php';

  if ($role_name !== 'admin' && $role_name !== 'teknisi') {
   // Jika peran BUKAN admin dan BUKAN teknisi (misal: user, staff, requester, dll.)
   $target_dashboard = './user_dashboard.php';
  }

  // Lakukan Pengalihan
  header("Location: {$target_dashboard}");
  exit();
  } else {
  $err = "Wrong username or password";
  }
 }
 $stmt->close();
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
<meta name="description" content="">
<meta name="author" content="">

<title>Helpdesk - Login</title>

<link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

<link href="css/sb-admin.css" rel="stylesheet">
 <style>
  /* Gaya kustom untuk header dan logo */
    .header-title {
      /* Gaya untuk teks "Login Etiket" */
      font-size: 1.5rem; /* Perbesar ukuran font (sesuai permintaan) */
      font-weight: bold;
      margin-bottom: 10px;
    }

  .logo-container img {
   max-width: 180px; /* Perbesar ukuran maksimum logo */
   height: auto;
  }
    .card-header {
      /* Pastikan card-header menampung logo di tengah */
      text-align: center;
    }
        /* == MODIFIKASI BACKGROUND (AGAR FOKUS KE ATAS) == */
        body {
            background-image: url('img/wallpaper2025.jpg'); /* Sumber gambar background */
            background-size: cover; /* Pastikan gambar menutupi seluruh area */
            background-repeat: no-repeat;
            background-attachment: fixed; /* Agar gambar tetap saat scroll */
            /* BARU: Fokus gambar ke bagian atas dan tengah */
            background-position: center top; 
            /* Warna latar belakang fallback */
            background-color: #343a40 !important; 
        }
 </style>

</head>

<body class="bg-dark">
<div class="container">
 <div class="card card-login mx-auto mt-5">
 <div class="card-header">
  
        <div class="header-title">Login Etiket </div>
    
        <div style="text-align: center;">
          <div class="logo-container">
      <img src="img/LogoPTBagong.png" alt="Logo PT Bagong">
     </div>
        </div>

   </div>
 <div class="card-body">
  <form method="POST" action="<?php echo $_SERVER['PHP_SELF']?>">
  <div class="form-group">
   <label for="inputEmail">Email address</label>
   <input type="text" name="email" class="form-control" placeholder="Email address" autofocus="autofocus">
  </div>
  <div class="form-group">
   <label for="inputPassword">Password</label>
   <input type="password" name="password" class="form-control" placeholder="Password">
  </div>
  <div class="form-group">
   <div class="checkbox">
   <label>
    <input type="checkbox" value="remember-me">
    Remember Password
   </label>
   </div>
  </div>
  <button type="submit" name="submit" class="btn btn-primary btn-block">Login</button>
     <a href="register.php" class="btn btn-secondary btn-block mt-2">Daftar Sekarang</a>
            <div class="text-center small mt-3" style="color: rgba(0, 0, 0, 0.7); font-size: 0.8rem; line-height: 1.2;">
                2025 - Created by <strong>Yosafat Wahyu</strong><br>
                Idea by <strong>Syarif</strong>
            </div>
              </form>

  <?php if(strlen($err) > 1) :?>
  <div class="alert alert-danger text-center mt-3" role="alert"> <strong>Failed! </strong> <?php echo $err;?></div>
  <?php endif?>
 </div>
 </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script src="vendor/jquery-easing/jquery.easing.min.js"></script>

</body>

</html>