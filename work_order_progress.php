<?php
// work_order_progress.php - DAFTAR WORK ORDER STATUS PROGRESS

// Pastikan error reporting dimatikan/diatur sesuai standar produksi Anda
ini_set('display_errors', 0);
error_reporting(0);

// --- ASUMSI FILE INI MENGURUS SESSION, AUTH, DAN KONEKSI DB ---
require_once 'src/work_order_functions.php';
// ASUMSI: File ini mengandung inisialisasi $auth dan $db
include 'header.php'; 

$rbac_target = 'work_order_list.php'; 

// Pengecekan RBAC untuk halaman view utama
if (!isset($auth) || !$auth->can($rbac_target, 'view')) { 
    header('Location: ./dashboard.php?err=' . urlencode('Akses Ditolak: Anda tidak memiliki izin untuk melihat Daftar Work Order.'));
    exit();
}

$can_add_wo = $auth->can($rbac_target, 'add');
$can_edit_wo = $auth->can($rbac_target, 'edit');
$can_delete_wo = $auth->can($rbac_target, 'delete');

// --- LOGIKA DELETE WO DI SINI ---
$msg = '';
$err = '';
if (isset($_GET['delete_id'])) {
    if (!$can_delete_wo) {
        header('Location: work_order_list.php?err=' . urlencode("Akses Ditolak: Anda tidak memiliki hak untuk menghapus Work Order."));
        exit();
    } else {
        // Lakukan proses deleteWorkOrder(intval($_GET['delete_id'])); di sini
        $msg = "Work Order ID " . intval($_GET['delete_id']) . " berhasil dihapus.";
        // Ganti dengan proses delete yang sebenarnya jika sudah siap
        header('Location: work_order_list.php?msg=' . urlencode($msg));
        exit();
    }
}
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// 🟢 FILTER STATUS KHUSUS UNTUK HALAMAN INI
$status_filter = 'Progress'; 
$badge_color = 'bg-warning text-dark';
?>
<div id="content-wrapper">
    <div class="container-fluid">
        
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Daftar Work Order (Status: PROGRESS)</li>
        </ol>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">📋 Daftar Work Order <span class="badge <?php echo $badge_color; ?>">PROGRESS</span></h3>
            
            <div class="d-flex gap-2">
                <?php if ($can_add_wo): ?>
                    <a href="work_order.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Buat Work Order Baru
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if(strlen($err) > 1) :?><div class="alert alert-danger my-3" role="alert"><strong>Gagal!</strong> <?php echo htmlspecialchars($err);?></div><?php endif?>
        <?php if(strlen($msg) > 1) :?><div class="alert alert-success my-3" role="alert"><strong>Berhasil!</strong> <?php echo htmlspecialchars($msg);?></div><?php endif?>

        <div class="card mb-3 shadow">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-clipboard-list"></i> Data Work Order Status PROGRESS
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="workOrderTable" class="table table-bordered table-striped" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>No.</th> 
                                <th>Updated</th> 
                                <th>Request Type</th> 
                                <th>Request Detail</th> 
                                <th>Latest Notes</th> 
                                <th>Status</th> 
                                <th>Priority</th> 
                                <th>Action</th> 
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</div>


<script>
// =========================================================
// KONFIGURASI DATATABLES DENGAN FILTER STATUS
// =========================================================
$(document).ready(function() {
    // Ambil filter status dari variabel PHP
    const statusFilter = '<?php echo $status_filter; ?>'; 
    
    const table = $('#workOrderTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'ajax/ajax_work_order_list.php',
            type: 'GET',
            // 🟢 MENGIRIM PARAMETER STATUS KE AJAX
            data: function (d) {
                d.status = statusFilter; 
            }
        },
        order: [[0, 'desc']],
        columns: [
            {  data: 0, title: 'No.', width: '5%', orderable: true,
                render: function (data, type, row, meta) {
                    // Render nomor urut otomatis
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            { data: 1, title: 'Updated', width: '10%' },
            { data: 2, title: 'Request Type', width: '15%' },
            { data: 3, title: 'Request Detail', width: '20%', orderable: false },
            { data: 4, title: 'Latest Notes', width: '20%', orderable: false },
            { data: 5, title: 'Status', width: '10%' },
            { data: 6, title: 'Priority', width: '10%' },
            { data: 7, title: 'Action', orderable: false, searchable: false, width: '10%' } 
        ]
    });
});
</script>