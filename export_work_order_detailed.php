<?php
// 🚨 LANGKAH PENTING 1: MEMULAI OUTPUT BUFFERING
// Ini menangkap semua output HTML/whitespace awal sehingga header unduhan bisa dikirim.
ob_start(); 

// --- FILE REQUIREMENT INTI (Database, Auth, Security) ---
require_once 'src/Database.php'; 
require_once 'src/Auth.php'; 
require_once 'src/security.php'; 

// Panggil header.php untuk menjalankan session_start() dan menginisialisasi objek $auth.
include './header.php'; 

// --- INISIASI VARIABEL & RBAC CHECK ---
$rbac_target = 'export_work_order_detailed.php'; 

// Cek inisialisasi $auth (Jika ini masih gagal, masalahnya ada pada header.php/Auth.php)
if (!isset($auth)) {
    die("Error Fatal: Objek Auth tidak berhasil dimuat.");
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? 'All';
$format = $_GET['format'] ?? null;
$error_message = '';
$available_statuses = ['All', 'Open', 'In Progress', 'Pending', 'Closed', 'Canceled'];


// =========================================================================
// 1. LOGIKA EKSPOR (Hanya dijalankan jika tombol Ekspor ditekan)
// =========================================================================

if ($format) {
    
    // RBAC Check
    if (!$auth->can($rbac_target, 'view')) { 
        die("Akses Ditolak: Anda tidak memiliki izin untuk mengekspor data.");
    }
    
    // Mendapatkan koneksi
    $conn = Database::getInstance();
    
    if ($conn->connect_error) {
        $error_message = "Koneksi database gagal: " . $conn->connect_error;
    } else {
        
        // Membangun Klausa WHERE
        $where_clauses = ["wo.date_request >= '{$conn->real_escape_string($start_date)}'", 
                          "wo.date_request <= '{$conn->real_escape_string($end_date)}'"];

        if ($status_filter != 'All') {
            $where_clauses[] = "wo.status = '{$conn->real_escape_string($status_filter)}'";
        }

        $where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

        $sql = "
            SELECT 
                wo.wo_id AS ID_WO, u.name AS Nama_Pemohon, d.departemen_name AS Departemen, 
                l.location_name AS Lokasi, wt.type_name AS Jenis_Pekerjaan, 
                t.name AS Tim_Penanggung_Jawab, wo.priority AS Prioritas, 
                wo.SN AS SN_No_Inventaris, wo.description AS Deskripsi_Masalah, 
                wo.date_request AS Tanggal_Permintaan, wo.status AS Status_WO, 
                wo.created_at AS Waktu_Dibuat, wo.updated_at AS Waktu_Diperbarui,
                
                tc.name AS Kategori_Barang, tsc.name AS Subkategori_Barang,
                woi.qty AS Quantity, woi.cost AS Estimasi_Biaya_Satuan,
                (woi.qty * woi.cost) AS Total_Biaya_Item
                
            FROM work_order wo
            LEFT JOIN users u ON wo.requester_id = u.id
            LEFT JOIN departemen d ON wo.department = d.departemen_id
            LEFT JOIN location l ON wo.location = l.location_id
            LEFT JOIN work_type wt ON wo.work_type = wt.type_id
            LEFT JOIN team t ON wo.team = t.id
            LEFT JOIN work_order_items woi ON wo.wo_id = woi.wo_id
            LEFT JOIN ticket_category tc ON woi.category = tc.id
            LEFT JOIN ticket_subcategory tsc ON woi.subcategory = tsc.id
            
            {$where_sql}
            
            ORDER BY wo.wo_id DESC, woi.id ASC 
        ";

        $result = $conn->query($sql);

        if (!$result) {
            $error_message = "Query gagal: " . $conn->error;
        } else {
            $headers = [
                'ID WO', 'Nama Pemohon', 'Departemen', 'Lokasi', 'Jenis Pekerjaan', 
                'Tim Penanggung Jawab', 'Prioritas', 'SN / No. Inventaris', 'Deskripsi Masalah', 
                'Tanggal Permintaan', 'Status WO', 'Waktu Dibuat', 'Waktu Diperbarui',
                'Kategori Barang', 'Subkategori Barang', 'Quantity', 'Estimasi Biaya Satuan (Rp)',
                'Total Biaya Item (Rp)'
            ];

            $filename = "Work_Order_Itemized_Report_" . date('Ymd_His');

            // --- EKSEKUSI EKSPOR SESUNGGUHNYA ---
            
            // 🚨 LANGKAH PENTING 2: Membersihkan buffer output yang mungkin terkumpul
            ob_clean(); 
            
            if ($format == 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '.csv";');
                
                $output = fopen('php://output', 'w');
                // Gunakan fputcsv(..., ';') karena CSV Indonesia sering menggunakan semicolon
                fputcsv($output, $headers, ';'); 
                
                while ($row = $result->fetch_assoc()) {
                    fputcsv($output, array_values($row), ';');
                }
                
                fclose($output);
                exit(); 
                
            } elseif ($format == 'xls') {
                // XLS Header (menggunakan format HTML table)
                header("Content-type: application/vnd.ms-excel");
                header("Content-Disposition: attachment; filename={$filename}.xls");
                header("Pragma: no-cache"); 
                header("Expires: 0");

                echo '<table><thead><tr>';
                foreach ($headers as $header) {
                    echo '<th>' . $header . '</th>';
                }
                echo '</tr></thead><tbody>';

                while ($row = $result->fetch_assoc()) {
                    echo '<tr>';
                    foreach ($row as $key => $data) {
                        $output_data = $data;
                        if (in_array($key, ['Quantity', 'Estimasi_Biaya_Satuan', 'Total_Biaya_Item'])) {
                            $output_data = (is_numeric($data) ? $data : 0);
                        } else {
                            $output_data = str_replace("\n", " ", $data);
                            $output_data = htmlspecialchars($output_data);
                        }
                        echo '<td>' . $output_data . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
                exit(); 
            }
        }
    }
}
// =========================================================================
// 2. TAMPILAN UI/UX FILTER (Jika $format TIDAK diset)
// =========================================================================

// 🚨 LANGKAH PENTING 3: Menghapus/membersihkan buffer yang tidak diperlukan untuk UI
ob_end_flush(); 
?>

<div id="content-wrapper" class="pt-4">
    <div class="container-fluid">

        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Ekspor Work Order Itemized</li>
        </ol>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">📊 Laporan Detail Item Work Order</h3>
        </div>
        
        <?php if (isset($error_message) && $error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"> 
                <i class="fas fa-times-circle me-2"></i> **Gagal Ekspor!** <?php echo htmlspecialchars($error_message);?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="card mb-4 shadow border-left-success">
            <div class="card-header bg-success text-white py-3">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-filter me-2"></i> Filter dan Pilih Format Ekspor</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="export_work_order_detailed.php" class="p-0">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal Selesai</label>
                            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Work Order</label>
                            <select name="status" class="form-control">
                                <?php foreach ($available_statuses as $status): ?>
                                    <option value="<?= htmlspecialchars($status) ?>" 
                                            <?= ($status == $status_filter) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" name="format" value="csv" class="btn btn-info me-2">
                            <i class="fas fa-file-csv me-1"></i> Ekspor ke CSV
                        </button>
                        <button type="submit" name="format" value="xls" class="btn btn-success">
                            <i class="fas fa-file-excel me-1"></i> Ekspor ke XLS
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4 shadow">
            <div class="card-body">
                <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i> Laporan ini akan menghasilkan data Work Order, di mana **setiap baris** mewakili **satu item barang** yang diajukan atau digunakan. Semua detail Work Order utama (Pemohon, Lokasi, dll.) akan diulang untuk setiap item.</p>
                <small class="text-danger">Pastikan rentang tanggal yang dipilih tidak terlalu besar untuk mencegah *timeout* saat mengunduh.</small>
            </div>
        </div>

    </div> 
</div>

<?php 
// Di sini kita tidak perlu ob_clean() lagi, hanya footer.
include './footer.php'; 
?>