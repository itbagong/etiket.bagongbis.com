<?php
// work_order.php - FORM WORK ORDER BARU

require_once 'src/work_order_functions.php';

// --- Memuat Header & RBAC Check ---
// ASUMSI: header.php sudah memuat session_start(), memuat $auth, dan mengecek sesi login.
include 'user_header.php';

// Data User dari Sesi
// Data ini aman diakses karena header.php sudah memastikan sesi ada dan valid
$current_user = $_SESSION['user'];

// RBAC Check untuk Tambah WO
$rbac_target = 'user_work_order.php';
if (!isset($auth) || !$auth->can($rbac_target, 'add')) {
  header('Location: ./dashboard.php?err=' . urlencode('Akses Ditolak: Anda tidak memiliki izin untuk membuat Work Order baru.'));
  exit();
}

$err = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ticket'])) {
  // saveWorkOrder akan menerima 'user_id' dan 'requester' dari POST
  $result = saveWorkOrder($_POST);
  if (isset($result['error'])) $err = $result['error'];
  if (isset($result['success'])) {
    $msg = $result['success'];
    $_POST = []; // Bersihkan data post setelah sukses
  }
}

// Load data master
$categoriesList = getCategories();
$departemenList = getDepartemen();
$locationList = getLocations();
$TypeList    = getWorkTypes();
$TeamList    = getTeams();
?>
<div class="container mt-4 mb-5">
  <h3 class="mb-3">🛠️ Buat Work Order Baru</h3>

  <?php if ($err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
  <?php elseif ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="user_id" value="<?= htmlspecialchars($current_user->id) ?>">

    <div class="row">
      <div class="col-md-3 mb-3">
        <label>Nama Pemohon <span class="text-danger">*</span></label>
        <input type="text" name="requester" class="form-control"
          value="<?= htmlspecialchars($current_user->name) ?>" readonly required>
      </div>
      <div class="col-md-3 mb-3">
        <label>Departemen <span class="text-danger">*</span></label>
        <select name="department" class="form-control" required>
          <option value="">Pilih Departemen</option>
          <?php
          $selected_dept = htmlspecialchars($_POST['department'] ?? '');
          foreach ($departemenList as $d):
            $is_selected = ($selected_dept == $d['departemen_id']) ? 'selected' : '';
          ?>
            <option value="<?= $d['departemen_id'] ?>" <?= $is_selected ?>><?= htmlspecialchars($d['departemen_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 mb-3">
        <label>Lokasi <span class="text-danger">*</span></label>
        <select name="location" class="form-control" required>
          <option value="">Pilih Lokasi</option>
          <?php
          $selected_loc = htmlspecialchars($_POST['location'] ?? '');
          foreach ($locationList as $d):
            $is_selected = ($selected_loc == $d['location_id']) ? 'selected' : '';
          ?>
            <option value="<?= $d['location_id'] ?>" <?= $is_selected ?>><?= htmlspecialchars($d['location_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 mb-3">
        <label>Jenis Pekerjaan <span class="text-danger">*</span></label>
        <select name="work_type" class="form-control" required>
          <option value="">Pilih Jenis Pekerjaan</option>
          <?php
          $selected_type = htmlspecialchars($_POST['work_type'] ?? '');
          foreach ($TypeList as $d):
            $is_selected = ($selected_type == $d['type_id']) ? 'selected' : '';
          ?>
            <option value="<?= $d['type_id'] ?>" <?= $is_selected ?>><?= htmlspecialchars($d['type_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col-md-3 mb-3">
        <label>Tim <span class="text-danger">*</span></label>
        <select name="team" class="form-control" required>
          <option value="">Pilih Tim</option>
          <?php
          $selected_team = htmlspecialchars($_POST['team'] ?? '');
          foreach ($TeamList as $d):
            $is_selected = ($selected_team == $d['id']) ? 'selected' : '';
          ?>
            <option value="<?= $d['id'] ?>" <?= $is_selected ?>><?= htmlspecialchars($d['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 mb-3">
        <label>Prioritas <span class="text-danger">*</span></label>
        <select name="priority" class="form-control" required>
          <?php
          $selected_priority = htmlspecialchars($_POST['priority'] ?? 'Medium');
          $priorities = ['Low', 'Medium', 'High'];
          foreach ($priorities as $p):
            $is_selected = ($selected_priority == $p) ? 'selected' : '';
          ?>
            <option value="<?= $p ?>" <?= $is_selected ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 mb-3">
        <label>SN / No. Inventaris</label>
        <input type="text" name="SN" class="form-control" maxlength="30" placeholder="Opsional"
          value="<?= htmlspecialchars($_POST['SN'] ?? '') ?>">
        <small class="form-text text-muted">SN akan disimpan langsung di Work Order.</small>
      </div>
    </div>

    <div class="mb-3">
      <label>Deskripsi Masalah Umum <span class="text-danger">*</span></label>
      <textarea name="description" class="form-control" rows="3" placeholder="Opsional: Jelaskan masalah secara umum..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

    <hr>
    <h5>Detail Barang yang Diajukan (Tunggal)</h5>
    
    <div class="row mb-3">
      <div class="col-md-3">
        <label>Kategori <span class="text-danger">*</span></label>
        <select name="main_category" id="mainCategorySelect" class="form-control" required>
          <option value="">-- Pilih Kategori --</option>
          <?php
          $selected_cat = htmlspecialchars($_POST['main_category'] ?? '');
          foreach($categoriesList as $c):
            $is_selected = ($selected_cat == $c['id']) ? 'selected' : '';
          ?>
            <option value="<?= $c['id'] ?>" <?= $is_selected ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label>Subkategori <span class="text-danger">*</span></label>
        <select name="subcategory" id="subcategorySelect" class="form-control" required>
          <option value="">-- Pilih Subkategori --</option>
          </select>
      </div>
      <div class="col-md-3">
        <label>Quantity <span class="text-danger">*</span></label>
        <input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($_POST['quantity'] ?? 1) ?>" min="1" required>
      </div>
      <div class="col-md-3">
      <label>Estimasi Harga per Item</label> 
      <input type="number" name="cost" class="form-control" placeholder="Masukkan biaya dalam angka (Cth: 150000)" 
        value="<?= htmlspecialchars($_POST['cost'] ?? '') ?>" min="0" step="1">       
        <small class="form-text text-muted">Isi dengan nilai perkiraan biaya barang (Opsional, gunakan 0 jika gratis).</small>
    </div>

    </div>
    
    
    <button type="submit" name="save_ticket" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Work Order</button>
  </form>
</div>
  <?php include './user_footer.php'; ?>
<script>
// Pastikan anda memiliki file JS dari Bootstrap atau Font Awesome untuk ikon 'fas fa-save'
// dan Anda telah memuat jQuery.
$(function(){
  const $mainCategorySelect = $('#mainCategorySelect');
  const $subcategorySelect = $('#subcategorySelect');
  
  // Nilai awal yang mungkin dikirim kembali dari POST (jika ada error)
  const initialCategory = "<?= htmlspecialchars($_POST['main_category'] ?? '') ?>";
  const initialSubcategory = "<?= htmlspecialchars($_POST['subcategory'] ?? '') ?>";
  
  // Fungsi untuk memuat subkategori
  function loadSubcategories(categoryId, selectedSubcategoryId = null) {
    if (!categoryId) {
      $subcategorySelect.html('<option value="">-- Pilih Subkategori --</option>');
      return;
    }

    $subcategorySelect.html('<option value="">Loading...</option>');

    // Mengambil subkategori dari server (asumsi Anda memiliki file ini: ajax/ajax_get_subcategories.php)
    $.get('ajax/ajax_get_subcategories.php', {category_id: categoryId}, function(data){
      $subcategorySelect.empty().append('<option value="">-- Pilih Subkategori --</option>');
      data.forEach(function(item){
        const selected = (selectedSubcategoryId && item.id == selectedSubcategoryId) ? 'selected' : '';
        $subcategorySelect.append(`<option value="${item.id}" ${selected}>${item.name}</option>`);
      });
    }, 'json').fail(function(){
      $subcategorySelect.html('<option value="">-- Error Memuat --</option>');
    });
  }

  $mainCategorySelect.on('change', function(){
    const categoryId = $(this).val();
    loadSubcategories(categoryId);
  });
  
  // Saat halaman dimuat, jika ada nilai POST lama (karena error), muat subkategori yang dipilih
  if (initialCategory) {
    // PENTING: Set nilai kategori terlebih dahulu
    $mainCategorySelect.val(initialCategory);
    loadSubcategories(initialCategory, initialSubcategory);
  }
});
</script>