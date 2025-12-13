<?php
// work_order_view.php - DETAIL WORK ORDER (FINAL)
ob_start(); 

require_once 'src/work_order_functions.php';
require_once 'src/security.php'; 
include 'header.php';

$encrypted_id = $_GET['id'] ?? '';
$id = 0; 

try {
  // 1. Dekripsi ID dari URL
  $id = decrypt_id($encrypted_id); 
} catch (Exception $e) {
  $id = 0;
}

if (!$id) {
  die("WO tidak ditemukan atau ID tidak valid.");
}

// ==============================================================================
// 🎯 PERBAIKAN PENGAMBILAN SESSION TEAM MEMBER ID
// ==============================================================================

$team_member_id = 0;

// Periksa apakah sesi 'user' ada dan merupakan objek/array, dan memiliki properti 'id'
if (isset($_SESSION['user']) && is_object($_SESSION['user'])) {
    // Ambil ID dari objek sesi ->id
    $team_member_id = $_SESSION['user']->id;
} 
// Jika sesi 'user' tidak ada, $team_member_id tetap 0 (default)

// --- VALIDASI KETAT ---
if (!$team_member_id || !is_numeric($team_member_id)) {
    // Jika ID pengguna tidak valid (0 atau tidak numerik), hentikan proses
    die("Akses Ditolak: Sesi pengguna tidak ditemukan atau tidak valid. Silakan login ulang.");
}
$db = getDb();

// --- RBAC Check: Pengecekan Izin untuk Aksi ---
$rbac_target = 'work_order_view.php';
$can_update_status = $auth->can($rbac_target, 'update_status'); 
$can_add_comment = $auth->can($rbac_target, 'add_comment');   

// --- 1. Pemrosesan Aksi Admin (Update Status & Komentar) ---
$err = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['update_status'])) {
    if (!$can_update_status) {
      $err = "Akses Ditolak: Anda tidak memiliki izin untuk mengubah status WO.";
    } else {
      // updateWorkOrderStatus memiliki filter internal status_wo = 0
      $result = updateWorkOrderStatus($id, $_POST['new_status']);
      $msg = $result['success'] ?? '';
      $err = $result['error'] ?? '';
    }
  } elseif (isset($_POST['add_comment'])) {
    if (!$can_add_comment) {
      $err = "Akses Ditolak: Anda tidak memiliki izin untuk menambahkan komentar.";
    } else {
      $comment_data = $_POST;
      $comment_data['wo_id'] = $id;
      // Nilai $team_member_id yang sudah divalidasi dan benar akan digunakan di sini
      $comment_data['team_member_id'] = $team_member_id;
      
      // addWorkOrderComment sudah diperbaiki di work_order_functions.php
      $result = addWorkOrderComment($comment_data);
      $msg = $result['success'] ?? '';
      $err = $result['error'] ?? '';
    }
  }
  if ($msg || $err) {
    // Redireksi untuk mencegah POST ulang
    $params = $msg ? "&msg=" . urlencode($msg) : "&err=" . urlencode($err);
    header("Location: work_order_view.php?id=" . $encrypted_id . $params); 
    exit;
  }
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);
if (isset($_GET['err'])) $err = htmlspecialchars($_GET['err']);


// 2. Ambil data header work order (Prepared Statement)
// ... (Bagian ini tidak berubah)
$stmt = $db->prepare("
  SELECT 
    wo.*,
    u.name AS requester_name, 
    d.departemen_name, 
    l.location_name, 
    wt.type_name, 
    t.name AS team_name
  FROM work_order wo
  LEFT JOIN users u ON wo.requester_id = u.id -- PERBAIKAN: JOIN ke tabel users untuk ambil nama
  LEFT JOIN departemen d ON wo.department = d.departemen_id
  LEFT JOIN location l ON wo.location = l.location_id
  LEFT JOIN work_type wt ON wo.work_type = wt.type_id
  LEFT JOIN team t ON wo.team = t.id
  WHERE wo.wo_id = ? 
"); 
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) die("Work Order tidak ditemukan.");


// 3. Ambil data barang yang diajukan (Prepared Statement)
// ... (Bagian ini tidak berubah)
$stmtItem = $db->prepare("
  SELECT 
    c.name AS cat, 
    s.name AS sub,
    i.qty, 
    i.cost -- *** PERUBAHAN DI SINI: Mengambil i.cost bukan i.reason ***
  FROM work_order_items i
  LEFT JOIN ticket_category c ON i.category = c.id
  LEFT JOIN ticket_subcategory s ON i.subcategory = s.id
  WHERE i.wo_id = ?
");
$stmtItem->bind_param('i', $id);
$stmtItem->execute();
$res = $stmtItem->get_result();
$stmtItem->close();

$items = [];
$mainCategory = 'N/A';
if ($res->num_rows > 0) {
  while ($r = $res->fetch_assoc()) {
    $items[] = $r;
    if ($mainCategory === 'N/A') {
      $mainCategory = $r['cat'];
    }
  }
}

// 4. Ambil data komentar
$comments = getWorkOrderComments($id);

// Helper untuk format mata uang
function formatRupiah($number) {
    if (is_numeric($number)) {
        return 'Rp ' . number_format($number, 0, ',', '.');
    }
    return $number;
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Detail WO #<?= $id ?></title>
<link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4 mb-5">
  <h3>🧾 Detail Work Order #<?= $id ?> <?= getStatusBadge($data['status'] ?? 'N/A') ?></h3>
<?php if ($data['status_wo'] == 1): ?>
    <div class="alert alert-danger text-center fw-bold">
      ⚠️ WORK ORDER INI SUDAH DINONAKTIFKAN (STATUS_WO = 1)
    </div>
  <?php endif; ?>
  
  <?php if ($err): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
  <?php elseif ($msg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <table class="table table-bordered">
    <tr><th>Nama Pemohon</th><td><?= htmlspecialchars($data['requester_name'] ?? 'ID ' . $data['requester_id']) ?></td></tr>
    <tr><th>Departemen</th><td><?= htmlspecialchars($data['departemen_name']) ?></td></tr>
    <tr><th>Lokasi</th><td><?= htmlspecialchars($data['location_name']) ?></td></tr>
    <tr><th>Jenis Pekerjaan</th><td><?= htmlspecialchars($data['type_name']) ?></td></tr>
    <tr><th>Tim</th><td><?= htmlspecialchars($data['team_name']) ?></td></tr>
    <tr><th>Prioritas</th><td><?= getPriorityBadge($data['priority']) ?></td></tr>
    
    <tr><th>SN / No. Inventaris</th><td><span class="fw-bold text-primary"><?= htmlspecialchars($data['SN'] ?? '-') ?></span></td></tr>
    
    <tr><th>Deskripsi Masalah Umum</th><td><?= nl2br(htmlspecialchars($data['description'])) ?></td></tr>
    <tr><th>Tanggal Request</th><td><?= htmlspecialchars($data['date_request']) ?></td></tr>
  </table>

  <h5 class="mt-4">Detail Barang yang Diajukan (Tunggal)</h5>
  
  <?php 
  if (!empty($items)): 
    $item = $items[0]; 
  ?>
  <table class="table table-sm table-bordered">
    <thead>
      <tr class="table-secondary">
        <th width="35%">Kategori & Subkategori</th>
        <th width="15%">Quantity</th>
        <th width="50%">Estimasi Biaya (Cost)</th>       </tr>
    </thead>
    <tbody>
      <tr>
        <td><?= htmlspecialchars($item['cat']) ?> &rarr; <?= htmlspecialchars($item['sub']) ?></td>
        <td><?= htmlspecialchars($item['qty']) ?></td> 
        <td>
                    <span class="fw-bold text-success">
                        <?= formatRupiah($item['cost']) ?> </span>
                </td> 
      </tr>
    </tbody>
  </table>
  <?php else: ?>
    <div class="alert alert-warning text-center">Tidak ada data detail barang untuk Work Order ini.</div>
  <?php endif; ?>
  
  <hr>

  <div class="card mt-4 mb-4">
    <div class="card-header bg-primary text-white">🛠️ Aksi & Respon (Admin/IT)</div>
    <div class="card-body">
      
      <h5 class="card-title mb-3">1. Ubah Status Work Order</h5>
      <?php 
        $disable_form = !$can_update_status || $data['status_wo'] == 1;
      ?>
      <form method="post">
        <div class="row g-2 align-items-center mb-4">
          <div class="col-auto">
            <label for="new_status_select" class="col-form-label">Status Baru:</label>
          </div>
          <div class="col-auto">
            <select name="new_status" id="new_status_select" class="form-select w-auto" required 
              <?= $disable_form ? 'disabled' : '' ?>>
              <option value="open" <?= ($data['status'] == 'open') ? 'selected' : '' ?>>Open</option>
              <option value="progress" <?= ($data['status'] == 'progress') ? 'selected' : '' ?>>Progress</option>
              <option value="closed" <?= ($data['status'] == 'closed') ? 'selected' : '' ?>>Closed</option>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" name="update_status" class="btn btn-warning" 
              <?= $disable_form ? 'disabled' : '' ?>>Update Status</button>
          </div>
          <?php if (!$can_update_status): ?>
            <div class="col-12"><small class="text-danger">Anda tidak memiliki izin untuk mengubah status.</small></div>
          <?php elseif ($data['status_wo'] == 1): ?>
            <div class="col-12"><small class="text-danger">WO sudah dinonaktifkan, status tidak bisa diubah.</small></div>
          <?php endif; ?>
        </div>
      </form>
      <hr>
      
      <h5 class="card-title">2. Tambah Komentar / Notifikasi</h5>
      <?php 
        $disable_form_comment = !$can_add_comment || $data['status_wo'] == 1;
      ?>
      <form method="post">
        <textarea name="comment_body" class="form-control mb-2" rows="3" placeholder="Tulis catatan atau balasan..." required 
          <?= $disable_form_comment ? 'disabled' : '' ?>></textarea>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="private" value="1" id="privateComment"
            <?= $disable_form_comment ? 'disabled' : '' ?>>
          <label class="form-check-label" for="privateComment">
            Catatan Internal (Tidak terlihat oleh pemohon)
          </label>
        </div>
        <button type="submit" name="add_comment" class="btn btn-success" 
          <?= $disable_form_comment ? 'disabled' : '' ?>>Kirim Komentar</button>
        <?php if (!$can_add_comment): ?>
          <div class="col-12 mt-2"><small class="text-danger">Anda tidak memiliki izin untuk menambah komentar.</small></div>
        <?php elseif ($data['status_wo'] == 1): ?>
          <div class="col-12 mt-2"><small class="text-danger">WO sudah dinonaktifkan, komentar tidak bisa ditambahkan.</small></div>
        <?php endif; ?>
      </form>
    </div>
  </div>
  
  <h5 class="mt-4">💬 Riwayat Komentar (<?= count($comments) ?>)</h5>
  <ul class="list-group">
  <?php if (!empty($comments)): ?>
    <?php foreach ($comments as $comment): ?>
      <li class="list-group-item d-flex justify-content-between align-items-start <?= $comment['private'] ? 'list-group-item-light border-warning' : '' ?>">
        <div class="ms-2 me-auto">
          <div class="fw-bold">
            <?= htmlspecialchars($comment['team_member_name'] ?? 'Petugas') ?>
            <?php if ($comment['private']): ?>
              <span class="badge bg-secondary ms-2">INTERNAL</span>
            <?php endif; ?>
          </div>
          <?= nl2br(htmlspecialchars($comment['body'])) ?>
        </div>
        <span class="text-muted small" style="white-space: nowrap;">
          <?= date('d M Y H:i', strtotime($comment['created_at'])) ?>
        </span>
      </li>
    <?php endforeach; ?>
  <?php else: ?>
    <li class="list-group-item text-muted text-center">Belum ada riwayat komentar.</li>
  <?php endif; ?>
  </ul>

  <a href="work_order_list.php" class="btn btn-secondary btn-sm mt-4">← Kembali</a>
</div>
</body>
  <?php include 'footer.php'; ?>
</html>