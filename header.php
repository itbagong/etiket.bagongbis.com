<?php
// header.php - VERSI FINAL DENGAN SEMUA MENU DIAMBIL DARI DATABASE/RBAC

// --- 1. LOGIKA PHP/AUTENTIKASI ---
session_start();

if(!isset($_SESSION['logged-in']) || $_SESSION['logged-in'] == false){
  header('Location: ./index.php');
  exit();
}

// Pastikan path ini benar (relatif terhadap header.php di root)
$user = $_SESSION['user'];
require_once './src/Database.php';
require_once './src/Auth.php';

$db = Database::getInstance();

// Modifikasi RBAC
$user_role_id = 0;
if (isset($user) && property_exists($user, 'role')) {
  $role_name_safe = $db->real_escape_string($user->role);
  $query_role_id = "SELECT role_id FROM role WHERE role_name = '{$role_name_safe}'";
  $result = $db->query($query_role_id);
  if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $user_role_id = (int)$data['role_id'];
  }
}
$user->role_id = $user_role_id;
$auth = new Auth($user->role_id);
// Mengambil semua menu yang diizinkan dari database
$allowed_admin_menus = $auth->getAllowedMenus();

// =========================================================
// === IMPLEMENTASI PENGURUTAN BERDASARKAN ID (menu_id) ===
// =========================================================

if (!empty($allowed_admin_menus)) {
    // Mengurutkan array menu utama berdasarkan 'menu_id' secara ascending.
    usort($allowed_admin_menus, function($a, $b) {
        return $a['menu_id'] <=> $b['menu_id'];
    });
}

// TENTUKAN BASE URL OTOMATIS
$base_dir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $base_dir;
$base_url = rtrim($base_url, '/') . '/';

$safe_user_name = htmlspecialchars($user->name);
$safe_base_url = htmlspecialchars($base_url);

// =========================================================
// === 2. BAGIAN HEADER HTML (Dicetak Langsung) ===
// =========================================================
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="description" content="">
<meta name="author" content="">

<title>Etiket - Dashboard</title>

<link href="<?php echo $safe_base_url; ?>vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
<link href="<?php echo $safe_base_url; ?>vendor/datatables/dataTables.bootstrap4.css" rel="stylesheet">
<link href="<?php echo $safe_base_url; ?>css/sb-admin.css" rel="stylesheet">

<style>
  /* MENGEMBALIKAN CSS KE VERSI ASLI (HANYA UNTUK CHART/CARD) */
  .chart-card .card-body {
    position: relative;
    height: 350px;
  }
  .chart-card canvas {
    height: 100% !important;
    width: 100% !important;
  }
  .pie-chart-container {
    max-width: 70%;
    margin: 0 auto;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  #chartSubcategory, #chartSLA {
    height: 350px;
  }
  .card-body-row3 {
    height: 380px;
  }
</style>

</head>

<body id="page-top">

<nav class="navbar navbar-expand navbar-dark bg-dark static-top">
<a class="navbar-brand mr-1" href="dashboard.php">Etiket</a>
<button class="btn btn-link btn-sm text-white order-1 order-sm-0" id="sidebarToggle" href="#">
<i class="fas fa-bars"></i>
</button>
<form class="d-none d-md-inline-block form-inline ml-auto mr-0 mr-md-3 my-2 my-md-0">
<div class="input-group"><input type="hidden"></div>
</form>

<ul class="navbar-nav ml-auto ml-md-0">
<li class="nav-item dropdown no-arrow mx-1">
<a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
  <i class="fas fa-bell fa-fw"></i>
  <span id="notificationBadge" class="badge badge-danger d-none">0</span>
</a>
<div id="notificationList" class="dropdown-menu dropdown-menu-right" aria-labelledby="alertsDropdown" style="width: 300px;">
  <h6 class="dropdown-header">Work Order Baru (Open)</h6>
  <span class="dropdown-item disabled text-center small">Memuat...</span>
</div>
</li>
<li class="nav-item dropdown no-arrow">
<a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
<i class="fas fa-user-circle fa-fw"></i> <?php echo $safe_user_name; ?>
</a>
<div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
<a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">Logout</a>
</div>
</li>
</ul>
</nav>
<div id="wrapper">
<ul class="sidebar navbar-nav">

<?php
// SEMUA MENU DIAMBIL DARI DATABASE OLEH $auth->getAllowedMenus()
// CATATAN: Array $allowed_admin_menus sudah diurutkan berdasarkan menu_id di atas
if(!empty($allowed_admin_menus)):
  foreach ($allowed_admin_menus as $menu_group):
   
    $collapse_id = 'collapse-group-' . $menu_group['menu_id'];

    // Cek apakah menu ini adalah grup (memiliki children)
    if (!empty($menu_group['children'])):
?>
<li class="nav-item">
  <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#<?php echo $collapse_id; ?>" aria-expanded="false" aria-controls="<?php echo $collapse_id; ?>">
    <i class="fas fa-fw <?php echo htmlspecialchars($menu_group['menu_icon']); ?>"></i>
    <span><?php echo htmlspecialchars($menu_group['menu_name']); ?></span>
  </a>
  <div id="<?php echo $collapse_id; ?>" class="collapse" aria-labelledby="heading<?php echo $menu_group['menu_id']; ?>" data-parent="#wrapper">
    <div class="bg-dark text-white py-2 pl-4">
      <?php 
              // Catatan: Jika children menu perlu diurutkan, pengurutan dilakukan di Auth.php 
              // atau menggunakan kode tambahan seperti yang dikomentari di atas.
              foreach ($menu_group['children'] as $child_menu): 
            ?>
      <a class="nav-link text-white" href="./<?php echo htmlspecialchars($child_menu->menu_url); ?>">
        <i class="<?php echo htmlspecialchars($child_menu->menu_icon); ?> mr-2"></i>
        <?php echo htmlspecialchars($child_menu->menu_name); ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</li>
<?php
    // Jika menu ini adalah menu tunggal (bukan grup) dan URL bukan '#'
    elseif (empty($menu_group['children']) && $menu_group['menu_url'] !== '#'):
?>
<li class="nav-item">
  <a class="nav-link" href="./<?php echo htmlspecialchars($menu_group['menu_url']); ?>">
    <i class="fas fa-fw <?php echo htmlspecialchars($menu_group['menu_icon']); ?>"></i>
    <span><?php echo htmlspecialchars($menu_group['menu_name']); ?></span>
  </a>
</li>
<?php
    endif;
  endforeach;
endif;
?>
</ul>
<div id="content-wrapper">