<?php
// work_order_user_view.php - TAMPILAN KHUSUS UNTUK USER AKHIR (List View)

// Pastikan error reporting dimatikan/diatur sesuai standar produksi Anda
ini_set('display_errors', 0);
error_reporting(0);

// --- ASUMSI FILE INI MENGURUS SESSION, AUTH, DAN KONEKSI DB ---
// Asumsi: work_order_functions.php berisi softDeleteWorkOrder, encrypt/decrypt
require_once 'src/work_order_functions.php'; 
// Asumsi: work_order_view_helpers.php berisi fungsi getWorkOrdersByUserId, getWorkOrderById, dll.
require_once 'src/work_order_view_helpers.php'; 
require_once 'src/security.php'; 

// 1. INCLUDE HEADER (ASUMSI: Ini memuat SESSION, $db, dan $auth)
include 'user_header.php'; 

// --- INISIALISASI VARIABEL USER DARI SESSION ---
// ASUMSI: $auth->getUser() atau $_SESSION['user'] memberikan data user
$loggedInUserData = $_SESSION['user'] ?? null;
$loggedInUserId = $loggedInUserData->id ?? 0; 
// Ambil NAMA user untuk tampilan (tidak lagi untuk filter DB)
$loggedInUserName = $loggedInUserData->name ?? 'User'; 

$rbac_target = 'work_order_user_view.php'; 

// 2. CHECK AUTENTIKASI DAN RBAC
// a) Pengecekan ID User (KRITIKAL: Hanya user terautentikasi yang boleh melihat)
// Pastikan user terautentikasi dan ID-nya tersedia
if ($loggedInUserId === 0) { // Cukup cek ID, karena kita akan menggunakan ID sebagai filter
	header('Location: ./login.php?err=' . urlencode('Sesi Habis atau User data tidak ditemukan. Harap login kembali.'));
	exit();
}

// b) Pengecekan RBAC 0: Akses Halaman VIEW utama
if (!isset($auth) || !$auth->can($rbac_target, 'view')) { 
	header('Location: ./dashboard.php?err=' . urlencode('Akses Ditolak: Anda tidak memiliki izin untuk melihat daftar Work Order Anda.'));
	exit();
}

// Cek hak akses CRUD (untuk tombol)
$can_add_wo = $auth->can($rbac_target, 'add');
// Menghilangkan fungsi tombol delete, namun logika delete tetap dipertahankan
$can_delete_wo = false; // <<< DIUBAH: Nonaktifkan tampilan tombol delete/nonaktifkan

// Inisialisasi pesan dari URL
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// --- LOGIKA SOFT DELETE WO ---
// Perhatian: Karena tombol delete dihilangkan, blok ini mungkin tidak akan dieksekusi, 
// tetapi dipertahankan jika ada link delete dari tempat lain yang mengarah ke file ini.
if (isset($_GET['delete_id'])) {
	$encrypted_id = $_GET['delete_id'];
	$current_msg = '';
	$current_err = '';
	
	// Gunakan pengecekan otorisasi asli jika diperlukan
	if (!$auth->can($rbac_target, 'delete')) { // Menggunakan pengecekan otorisasi yang sebenarnya
		$current_err = "Akses Ditolak: Anda tidak memiliki hak untuk menghapus Work Order.";
	} else {
		$wo_id = 0;
		try {
			$wo_id = (int) decrypt_id($encrypted_id); 
		} catch (Exception $e) {
			$current_err = "ID Work Order tidak valid atau gagal didekripsi.";
			$wo_id = 0;
		}

		if ($wo_id > 0) {
			// Mengambil detail WO (sekarang menyertakan requester_id)
			$wo_details = getWorkOrderById($wo_id); 
			
			// KRITIKAL PERBAIKAN: Cek apakah requester_id sama dengan loggedInUserId
			if ($wo_details && $wo_details['requester_id'] == $loggedInUserId) { 
				$result = softDeleteWorkOrder($wo_id); 
				$current_msg = $result['success'] ?? '';
				$current_err = $result['error'] ?? '';
			} else {
				$current_err = "Anda tidak memiliki hak untuk menghapus Work Order ini atau WO tidak ditemukan.";
			}
		} else {
			$current_err = $current_err ?: "ID Work Order yang dikirim tidak valid.";
		}
	}
	
	// Redirect setelah operasi delete untuk membersihkan URL
	header('Location: ' . $rbac_target . '?msg=' . urlencode($current_msg) . '&err=' . urlencode($current_err));
	exit();
}

// --- AMBIL DATA WORK ORDER KHUSUS UNTUK USER YANG LOGIN ---
// KRITIKAL PERBAIKAN: Gunakan $loggedInUserId (integer) sebagai filter
$userWorkOrders = getWorkOrdersByUserId($loggedInUserId, true); 

// --- PERHITUNGAN TOTAL UNTUK DASHBOARD CARD ---
$activeTicketCount = 0;
$totalTicketCount = count($userWorkOrders);
foreach ($userWorkOrders as $wo) {
	if ($wo['status_wo'] == 0 && strtolower($wo['status_text']) != 'closed') { 
		$activeTicketCount++;
	}
}
?>

<div id="content-wrapper">
	<div class="container-fluid">
		<ol class="breadcrumb">
			<li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
			<li class="breadcrumb-item active">Work Order Saya</li>
		</ol>

		<div class="d-flex justify-content-between align-items-center mb-4">
			<h3 class="mb-0">📋 Work Order Saya (<?php echo htmlspecialchars($loggedInUserName); ?>)</h3>
			
			<div class="d-flex gap-2">
				<?php if ($can_add_wo): ?>
					<a href="user_work_order.php" class="btn btn-info btn-sm">
						<i class="fas fa-plus me-1"></i> Buat Work Order Baru
					</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if(strlen($err) > 1) :?><div class="alert alert-danger my-3" role="alert"><strong>Gagal!</strong> <?php echo htmlspecialchars($err);?></div><?php endif?>
		<?php if(strlen($msg) > 1) :?><div class="alert alert-success my-3" role="alert"><strong>Berhasil!</strong> <?php echo htmlspecialchars($msg);?></div><?php endif?>

		<div class="row mb-3">
			<div class="col-xl-4 col-md-6 mb-3">
				<div class="card text-white bg-primary o-hidden h-100 shadow">
					<div class="card-body">
						<div class="card-body-icon">
							<i class="fas fa-fw fa-comments"></i>
						</div>
						<div class="mr-5">
							<span class="h1"><?php echo $activeTicketCount; ?></span><br>
							TIKET BANTUAN AKTIF (OPEN/PROGRESS)
						</div>
					</div>
					<a class="card-footer text-white clearfix small z-1" href="#workOrdersList">
						<span class="float-left">Total <?php echo $totalTicketCount; ?> Work Order Tercatat</span>
						<span class="float-right">
							<i class="fas fa-angle-right"></i>
						</span>
					</a>
				</div>
			</div>
		</div>

		<div class="card mb-3 shadow" id="workOrdersList">
			<div class="card-header bg-info text-white">
				<i class="fas fa-ticket-alt"></i> Daftar Work Order Saya
			</div>
			<div class="card-body">
				<?php if (empty($userWorkOrders)): ?>
					<div class="alert alert-warning">
						Anda belum memiliki Work Order yang tercatat. Silakan buat yang baru.
					</div>
				<?php else: ?>
					<div class="list-group">
						<?php 
						$no = 1;
						foreach ($userWorkOrders as $wo): 
							$encrypted_wo_id = encrypt_id($wo['id']);
							
							$wo_status_text = getWorkOrderStatusText($wo['status_text']); 
							$wo_status_class = getWorkOrderStatusClass($wo['status_text']); 
							$is_deleted = ($wo['status_wo'] == 1); 
 			              $is_closed = (strtolower($wo['status_text']) === 'closed');

							$row_class = $is_deleted ? 'list-group-item-secondary text-muted' : '';
						?>
							<div class="list-group-item list-group-item-action flex-column align-items-start <?php echo $row_class; ?>">
								<div class="d-flex w-100 justify-content-between">
									<h5 class="mb-1">#<?php echo htmlspecialchars($wo['id']); ?> - <?php echo htmlspecialchars($wo['request_type']); ?></h5>
									<small class="text-muted">Update: <?php echo date('d/m/Y (H:i)', strtotime($wo['updated_at'])); ?></small>
								</div>
								<p class="mb-1 text-truncate">
									<small class="<?php echo $is_deleted ? 'text-decoration-line-through' : ''; ?>">
										Detail: <strong><?php echo htmlspecialchars($wo['request_detail']); ?></strong>
									</small><br>
									<small>
										Catatan Terbaru: <?php echo htmlspecialchars($wo['latest_notes'] ?: 'Belum ada catatan publik.'); ?>
									</small>
								</p>
								<div class="d-flex justify-content-between align-items-center mt-2">
									<small>
										<span class="badge bg-<?php echo $wo_status_class; ?>"><?php echo $wo_status_text; ?></span>
										<?php 
										$priority_class = ($wo['priority'] === 'High') ? 'danger' : (($wo['priority'] === 'Medium') ? 'warning text-dark' : 'success');
										?>
										<span class="badge bg-<?php echo $priority_class; ?> ml-1"><?php echo htmlspecialchars($wo['priority']); ?> Priority</span>
									</small>
									<div>
										<?php 
										$print_url = "export_pdf.php?id=" . urlencode($encrypted_wo_id);
										?>
										
			                    <?php if ($is_closed && !$is_deleted): ?>
			                       <a href="<?php echo $print_url; ?>" target="_blank" class="btn btn-outline-secondary btn-sm ml-1" title="Cetak Work Order">
			                         <i class="fas fa-print"></i> Cetak
			                       </a>
			                    <?php else: ?>
			                       <a href="#" class="btn btn-outline-secondary btn-sm ml-1 disabled" title="WO belum ditutup atau sudah nonaktif" style="pointer-events: none;">
			                         <i class="fas fa-print"></i> Cetak
			                       </a>
			                    <?php endif; ?>

										<?php if ($is_deleted): ?>
											<span class="badge bg-dark ml-1">Nonaktif</span>
										<?php endif; ?>
									</div>
								</div>
							</div>
						<?php $no++; endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
	
    </div>

<?php include './user_footer.php'; ?>
<script>
$(document).ready(function() {
	// Logika Modal Hapus/Nonaktifkan dihilangkan karena tombolnya sudah dihapus
	// Jika Anda ingin mengaktifkan kembali fungsi modal/delete,
	// Anda harus menambahkan tombol delete/nonaktifkan kembali pada list-group item di atas.
});
</script>