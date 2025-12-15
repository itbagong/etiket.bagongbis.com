

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

  <?php if (!empty($controller->error)) : ?>
<div class="alert alert-danger text-center mt-3">
    <strong>Failed!</strong> <?= htmlspecialchars($controller->error) ?>
</div>
<?php endif ?>
 </div>
 </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<script src="vendor/jquery-easing/jquery.easing.min.js"></script>

</body>

</html>