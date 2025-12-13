<?php
// report_performance.php - Laporan Kinerja Work Order (Final Version)

require_once 'src/Database.php';
require_once 'src/work_order_functions.php'; 
require_once 'src/Auth.php';
require_once 'src/report_functions.php'; // WAJIB ADA: Memuat semua fungsi report
include 'header.php'; 

// Pengecekan koneksi dan RBAC
if (!isset($db) || !$db) {
    exit("Koneksi database gagal.");
}

$rbac_target = 'report_performance.php';
if (!isset($auth) || !$auth->can($rbac_target, 'view')) {
    header('Location: ./dashboard.php?err=' . urlencode('Akses Ditolak: Anda tidak memiliki izin untuk melihat Laporan Kinerja.'));
    exit();
}

// --------------------------------------------------
// A. PENENTUAN PERIODE LAPORAN DINAMIS (Memanggil dari report_functions.php)
// --------------------------------------------------
$period_type = $_GET['type'] ?? 'month';
$period_value = $_GET['value'] ?? date('Y-m', strtotime('last month'));

$period = get_report_period($period_type, $period_value);
$start_date = $period['start_date'];
$end_date = $period['end_date'];
$period_label = $period['label'];

// --------------------------------------------------
// B. PENGAMBILAN DATA (DARI SRC/REPORT_FUNCTIONS.PHP)
// --------------------------------------------------
$data = getWorkOrderPerformanceData($db, $start_date, $end_date); // Panggil fungsi helper

// --------------------------------------------------
// C. PENGOLAHAN DATA UNTUK REPORT
// --------------------------------------------------
$report_data = [
    'total_created' => 0,
    'total_closed' => 0,
    'status_open' => 0,
    'status_progress' => 0,
    'status_closed' => 0,
    'cycle_times' => [],
    'wo_by_dept' => [],
    'wo_by_category' => [],
    'wo_by_subcategory' => [],
    'wo_by_priority' => ['High' => 0, 'Medium' => 0, 'Low' => 0],
];
$processed_wo_ids = [];

foreach ($data as $wo) {
    $status = strtolower($wo['status']);
    $priority = $wo['priority'] ?? 'Low';
    $dept = $wo['departemen_name'] ?? 'N/A';
    $cat = $wo['category_name'] ?? 'N/A';
    $subcat = $wo['subcategory_name'] ?? 'N/A';
    $wo_id = $wo['wo_id'];

    // SEMUA METRIK DIHITUNG SEKALI PER WO ID
    if (!isset($processed_wo_ids[$wo_id])) {
        $report_data['total_created']++;
        $processed_wo_ids[$wo_id] = true;

        // Hitung Status, Cycle Time, Priority
        if ($status === 'closed' || $status === 'done' || $status === 'resolved') {
            $report_data['total_closed']++;
            $report_data['status_closed']++;
            
            $close_date = $wo['date_closed_comment'] ?? $wo['latest_update_ts'];
            $cycle_time = calculate_cycle_time($wo['date_request'], $close_date); 
            if ($cycle_time >= 0) {
                $report_data['cycle_times'][] = $cycle_time;
            }

        } elseif ($status === 'open' || $status === 'pending') {
            $report_data['status_open']++;
        } elseif ($status === 'progress' || $status === 'assigned') {
            $report_data['status_progress']++;
        }
        
        // Agregasi Sumber Permintaan
        $report_data['wo_by_dept'][$dept] = ($report_data['wo_by_dept'][$dept] ?? 0) + 1;
        $report_data['wo_by_category'][$cat] = ($report_data['wo_by_category'][$cat] ?? 0) + 1;
        $report_data['wo_by_subcategory'][$subcat] = ($report_data['wo_by_subcategory'][$subcat] ?? 0) + 1;
        $report_data['wo_by_priority'][$priority] = ($report_data['wo_by_priority'][$priority] ?? 0) + 1;
    }
}

// Hitung Rata-rata Cycle Time
$avg_cycle_time = count($report_data['cycle_times']) > 0
    ? round(array_sum($report_data['cycle_times']) / count($report_data['cycle_times']), 1)
    : 0;

// Urutkan data berdasarkan jumlah (DESC)
arsort($report_data['wo_by_dept']);
arsort($report_data['wo_by_category']); 
arsort($report_data['wo_by_subcategory']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kinerja Work Order</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .text-xs { font-size: 0.75rem; }
        .table-dark-header thead th {
            background-color: #212529; /* Warna hitam gelap */
            color: #fff;
            vertical-align: middle;
            border-bottom: 2px solid #dee2e6;
        }
        .table-status-priority td {
            vertical-align: middle;
            font-weight: 500;
        }
    </style>
</head>
<body>

<div id="content-wrapper" class="container mt-4">
    <div class="container-fluid">
        
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Laporan Kinerja Work Order</li>
        </ol>

        <h3 class="mb-4">📈 Laporan Kinerja Work Order: <?php echo $period_label; ?></h3>
        
        <div class="card mb-4 p-3 shadow-sm bg-light">
            <form method="GET" action="report_performance.php" class="row g-3 align-items-end">
                <div class="col-auto">
                    <label for="period_type" class="form-label">Jenis Periode:</label>
                    <select id="period_type" name="type" class="form-control" onchange="updatePeriodInput(this.value)">
                        <option value="day" <?php echo $period_type == 'day' ? 'selected' : ''; ?>>Harian</option>
                        <option value="week" <?php echo $period_type == 'week' ? 'selected' : ''; ?>>Mingguan</option>
                        <option value="month" <?php echo $period_type == 'month' ? 'selected' : ''; ?>>Bulanan</option>
                        <option value="year" <?php echo $period_type == 'year' ? 'selected' : ''; ?>>Tahunan</option>
                <!--        <option value="all" <?php //echo $period_type == 'all' ? 'selected' : ''; ?>>Semua Waktu</option>-->
                    </select>
                </div>
                <div class="col-auto" id="period_input_container">
                    </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Tampilkan Laporan</button>
                </div>
            </form>
        </div>

        <hr>

        <h3>1. Ringkasan Kinerja Umum</h3>
        
        <div class="row">
            <div class="col-md-3">
                <div class="card text-white bg-primary mb-3 shadow">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Total WO Dibuat</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $report_data['total_created']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success mb-3 shadow">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">WO Selesai (Closed)</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $report_data['total_closed']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info mb-3 shadow">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Waktu Siklus Rata-rata</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $avg_cycle_time; ?> Hari</div>
                        <small>*(Waktu dari dibuat hingga ditutup)</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning mb-3 shadow">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Tingkat Penyelesaian</div>
                        <?php
                            $completion_rate = $report_data['total_created'] > 0
                                ? round(($report_data['total_closed'] / $report_data['total_created']) * 100, 1)
                                : 0;
                        ?>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $completion_rate; ?>%</div>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <h3>2. Detail Status & Prioritas</h3>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <table class="table table-bordered table-status-priority table-dark-header mb-0">
                        <thead class="table-dark-header">
                            <tr>
                                <th>Status</th>
                                <th class="text-start">Jumlah WO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <span class="badge bg-danger text-white">Open</span>
                                </td>
                                <td class="text-start"><?php echo $report_data['status_open']; ?></td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="badge bg-warning text-dark">Progress</span>
                                </td>
                                <td class="text-start"><?php echo $report_data['status_progress']; ?></td>
                            </tr>
                            <tr>
                                <td>
                                    <span class="badge bg-success text-white">Closed</span>
                                </td>
                                <td class="text-start"><?php echo $report_data['status_closed']; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <table class="table table-bordered table-status-priority table-dark-header mb-0">
                        <thead class="table-dark-header">
                            <tr>
                                <th>Prioritas</th>
                                <th class="text-start">Jumlah WO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $priority_order = ['High', 'Medium', 'Low'];
                            foreach ($priority_order as $prio):
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo ($prio == 'High' ? 'danger' : ($prio == 'Medium' ? 'warning' : 'info')); ?> text-white"><?php echo $prio; ?></span>
                                </td>
                                <td class="text-start"><?php echo $report_data['wo_by_priority'][$prio] ?? 0; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <hr>

        <h3>3. Analisis Sumber Permintaan</h3>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">Permintaan WO Berdasarkan <strong>Departemen</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr class="table-secondary">
                                    <th style="width: 5%;">#</th>
                                    <th>Departemen</th>
                                    <th class="text-end" style="width: 25%;">Jumlah WO</th>
                                    <th class="text-end" style="width: 15%;">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 0;
                                $total_dept_count = array_sum($report_data['wo_by_dept']);
                                foreach ($report_data['wo_by_dept'] as $dept => $count):
                                    $i++;
                                    $percent = $total_dept_count > 0 ? round(($count / $total_dept_count) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td><?php echo htmlspecialchars($dept); ?></td>
                                    <td class="text-end fw-bold"><?php echo $count; ?></td>
                                    <td class="text-end"><?php echo $percent; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">Permintaan WO Berdasarkan <strong>Kategori</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr class="table-secondary">
                                    <th style="width: 5%;">#</th>
                                    <th>Kategori</th>
                                    <th class="text-end" style="width: 25%;">Jumlah WO</th>
                                    <th class="text-end" style="width: 15%;">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 0;
                                $total_cat_count = array_sum($report_data['wo_by_category']);
                                foreach ($report_data['wo_by_category'] as $cat => $count):
                                    $i++;
                                    $percent = $total_cat_count > 0 ? round(($count / $total_cat_count) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td><?php echo htmlspecialchars($cat); ?></td>
                                    <td class="text-end fw-bold"><?php echo $count; ?></td>
                                    <td class="text-end"><?php echo $percent; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">Permintaan WO Berdasarkan <strong>Subkategori</strong></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr class="table-secondary">
                                    <th style="width: 5%;">#</th>
                                    <th>Subkategori</th>
                                    <th class="text-end" style="width: 25%;">Jumlah WO</th>
                                    <th class="text-end" style="width: 15%;">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 0;
                                $total_subcat_count = array_sum($report_data['wo_by_subcategory']);
                                foreach ($report_data['wo_by_subcategory'] as $subcat => $count):
                                    $i++;
                                    $percent = $total_subcat_count > 0 ? round(($count / $total_subcat_count) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $i; ?></td>
                                    <td><?php echo htmlspecialchars($subcat); ?></td>
                                    <td class="text-end fw-bold"><?php echo $count; ?></td>
                                    <td class="text-end"><?php echo $percent; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
    
    <?php include 'footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Fungsi JavaScript untuk input periode dinamis
    $(document).ready(function() {
        const initialType = '<?php echo $period_type; ?>';
        const initialValue = '<?php echo $period_value; ?>';
        updatePeriodInput(initialType, initialValue);
    });

    function updatePeriodInput(type, initialValue = '') {
        const container = $('#period_input_container');
        container.empty();

        if (type === 'all') {
            return;
        }

        let inputType = 'text';
        let label = 'Pilih Tanggal:';
        let defaultValue = initialValue;

        if (type === 'day') {
            inputType = 'date';
            if (!defaultValue || !defaultValue.match(/^\d{4}-\d{2}-\d{2}$/)) defaultValue = '<?php echo date('Y-m-d'); ?>';
            label = 'Pilih Tanggal:';
        } else if (type === 'week') {
            inputType = 'date';
            if (!defaultValue || !defaultValue.match(/^\d{4}-\d{2}-\d{2}$/)) defaultValue = '<?php echo date('Y-m-d'); ?>';
            label = 'Pilih Tanggal di Minggu Tersebut:';
        } else if (type === 'month') {
            inputType = 'month';
            if (!defaultValue || !defaultValue.match(/^\d{4}-\d{2}$/)) defaultValue = '<?php echo date('Y-m'); ?>';
            label = 'Pilih Bulan:';
        } else if (type === 'year') {
            inputType = 'number';
            if (!defaultValue || !defaultValue.match(/^\d{4}$/)) defaultValue = '<?php echo date('Y'); ?>';
            label = 'Masukkan Tahun:';
        }

        const inputHtml = `
            <label for="period_value" class="form-label">${label}</label>
            <input type="${inputType}"
                   id="period_value"
                   name="value"
                   class="form-control"
                   value="${defaultValue}"
                   required>
        `;
        
        container.html(inputHtml);
    }
</script>
</body>
</html>