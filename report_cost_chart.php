<?php
// report_cost_chart.php - Dashboard Pengeluaran (Client Fetch JS)

// Asumsi 'src/work_order_functions.php' dan 'header.php' menginisialisasi $auth
require_once 'src/work_order_functions.php';
require_once 'header.php'; // Ini seharusnya sudah memulai sesi dan menginisialisasi $auth

// =======================================================
// 1. KONTROL AKSES BERBASIS PERAN (RBAC) BARU
// =======================================================

$rbac_target = 'cost_report'; 

// Pengecekan RBAC 0: Akses Halaman VIEW utama
if (!isset($auth) || !$auth->can($rbac_target, 'view')) { 
    $errorMessage = urlencode('Akses Ditolak: Anda tidak memiliki izin untuk melihat Laporan Biaya.');
    
    if (!isset($_SESSION['logged-in']) || $_SESSION['logged-in'] !== true) {
        header('Location: ./index.php');
    } else {
        header('Location: ./dashboard.php?err=' . $errorMessage);
    }
    exit();
}

// Fungsi Helper PHP tetap dibutuhkan untuk tampilan awal
function formatRupiah($number) {
    if ($number === null || $number === 0) return 'Rp 0';
    return 'Rp ' . number_format($number, 0, ',', '.');
}

// Set nilai default filter PHP untuk mengisi form HTML
$default_end_A = date('Y-m-t', strtotime('today'));
$default_start_A = date('Y-m-01', strtotime('today'));
$default_end_B = date('Y-m-t', strtotime('last month'));
$default_start_B = date('Y-m-01', strtotime('last month'));

$start_A = $_GET['start_A'] ?? $default_start_A;
$end_A = $_GET['end_A'] ?? $default_end_A;
$start_B = $_GET['start_B'] ?? $default_start_B;
$end_B = $_GET['end_B'] ?? $default_end_B;
$location_filter_id = $_GET['location_id'] ?? 'all';

// Inisialisasi pesan dari URL (jika ada)
$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cost Report</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Gaya Opsional */
        .card-stat-custom { transition: transform 0.2s; }
        .card-stat-custom:hover { transform: translateY(-3px); }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <h3>💰 Laporan Pengeluaran Work Order (Biaya Bahan/Suku Cadang)</h3>
    
    <?php if ($err): ?>
        <div class="alert alert-danger">❌ <?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>
    <?php if ($msg): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div id="errorAlert"></div>

    <div class="card shadow mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Pilih Periode Perbandingan</h5>
        </div>
        <div class="card-body">
            <form method="GET" id="costFilterForm" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="location_select" class="form-label fw-bold">Lokasi:</label>
                    <select name="location_id" id="location_select" class="form-select">
                        <option value="all">All Area</option>
                        </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label fw-bold">Periode A (Utama):</label>
                    <div class="input-group">
                        <input type="date" name="start_A" id="start_A" class="form-control" value="<?= htmlspecialchars($start_A) ?>" required>
                        <span class="input-group-text">s/d</span>
                        <input type="date" name="end_A" id="end_A" class="form-control" value="<?= htmlspecialchars($end_A) ?>" required>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Periode B (Pembanding):</label>
                    <div class="input-group">
                        <input type="date" name="start_B" id="start_B" class="form-control" value="<?= htmlspecialchars($start_B) ?>" required>
                        <span class="input-group-text">s/d</span>
                        <input type="date" name="end_B" id="end_B" class="form-control" value="<?= htmlspecialchars($end_B) ?>" required>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <button type="button" id="loadChartBtn" class="btn btn-primary">Bandingkan</button>
                    <span id="loadingSpinner" class="spinner-border spinner-border-sm text-primary" role="status" style="display:none;"></span>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-4" id="summaryCard">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 border-end">
                    <h5 class="card-title text-primary">Total Pengeluaran Periode A:</h5>
                    <h4 class="text-primary mb-3" id="costAValue">Rp 0</h4>
                    <small class="text-muted" id="labelAValue">Tanggal Start - Tanggal End</small>
                </div>
                <div class="col-md-6">
                    <h5 class="card-title text-secondary">Perbandingan vs Periode B:</h5>
                    <p class="mt-3">
                        <span class="fw-bold fs-4 text-muted" id="comparisonPercent">
                            0% <i class="fas fa-arrows-h"></i>
                        </span>
                        vs Periode B
                        <small class="text-muted" id="costBValue">
                            (Rp 0 | Tanggal Start - Tanggal End)
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header">
                    <h5 class="mb-0">📈 Grafik Biaya Harian Perbandingan</h5>
                </div>
                <div class="card-body">
                    <div id="comparisonChartContainer">
                        <canvas id="comparisonChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header">
                    <h5 class="mb-0">📈 Tren Pengeluaran Bulanan</h5>
                </div>
                <div class="card-body">
                    <div id="trendChartContainer">
                        <canvas id="trendChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // =========================================================
    // VARIABEL GLOBAL & HELPER
    // =========================================================
    let comparisonChartInstance = null;
    let trendChartInstance = null;
    const API_URL = 'src/work_order_cost.php'; // Menggunakan path relatif

    // Helper: Format angka menjadi Rupiah (diambil dari fungsi PHP)
    function formatRupiahJs(number) {
        if (number === 0 || number === null) return 'Rp 0';
        return 'Rp ' + number.toLocaleString('id-ID');
    }

    function formatRupiahTick(value) {
        if (value >= 1000000000) return 'Rp ' + (value / 1000000000).toFixed(1) + ' M';
        if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
        return 'Rp ' + value.toLocaleString('id-ID');
    }

    function showError(message) {
        document.getElementById('errorAlert').innerHTML =
            `<div class="alert alert-danger">❌ ${message}</div>`;
        document.getElementById('summaryCard').style.display = 'none';
    }

    function clearError() {
        document.getElementById('errorAlert').innerHTML = '';
        document.getElementById('summaryCard').style.display = '';
    }

    // =========================================================
    // FUNGSI UTAMA LOAD DATA
    // =========================================================
    async function loadCostData() {
        clearError();
        document.getElementById('loadingSpinner').style.display = 'inline-block';
        document.getElementById('loadChartBtn').disabled = true;

        const location_id = document.getElementById('location_select').value;
        const start_A = document.getElementById('start_A').value;
        const end_A = document.getElementById('end_A').value;
        const start_B = document.getElementById('start_B').value;
        const end_B = document.getElementById('end_B').value;

        const queryParams = new URLSearchParams({
            location_id, start_A, end_A, start_B, end_B
        }).toString();
        
        const fullUrl = `${API_URL}?${queryParams}`;

        try {
            const res = await fetch(fullUrl);
            
            // 1. Cek Respons HTTP (404/500)
            if (!res.ok) {
                throw new Error(`Gagal memuat API cost: HTTP status ${res.status}`);
            }

            // 2. Cek Content Type (Pastikan JSON)
            const contentType = res.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                const text = await res.text();
                console.error("API Error Response:", text);
                throw new Error("API tidak mengembalikan JSON. (API PHP mungkin crash/error fatal)");
            }

            const data = await res.json();
            
            // 3. Cek Data Error Logika
            if (!data || data.error || !data.totals || !data.params) {
                throw new Error('API mengembalikan data error: ' + (data.message || 'Data tidak lengkap.'));
            }

            // Data berhasil dimuat dan valid
            renderSummary(data);
            updateLocationDropdown(data.locations, location_id); // Update dropdown jika ada lokasi baru
            
            // PENTING: Panggil fungsi render HANYA jika Chart.js sudah termuat (setelah DOMContentLoaded)
            // Chart.js harus sudah didefinisikan secara global.
            if (typeof Chart !== 'undefined') {
                renderComparisonChart(data);
                renderMonthlyTrendChart(data);
            } else {
                 console.warn("Chart.js is not loaded yet. Skipping chart render.");
            }

        } catch (error) {
            console.error('Error loading cost data:', error);
            showError(`Gagal memuat data biaya: ${error.message} (URL: ${fullUrl})`);
        } finally {
            document.getElementById('loadingSpinner').style.display = 'none';
            document.getElementById('loadChartBtn').disabled = false;
        }
    }

    // =========================================================
    // FUNGSI RENDER
    // =========================================================

    function renderSummary(data) {
        const costA = data.totals.cost_A;
        const costB = data.totals.cost_B;
        const labelA = data.params.label_A;
        const labelB = data.params.label_B;

        document.getElementById('costAValue').textContent = formatRupiahJs(costA);
        document.getElementById('labelAValue').textContent = `(${labelA})`;

        let comparison_diff = costA - costB;
        let comparison_percent;

        if (costB > 0) {
            comparison_percent = (comparison_diff / costB) * 100;
        } else if (costA > 0) {
            comparison_percent = 100; // Jika B nol tapi A ada, berarti 100% kenaikan
        } else {
            comparison_percent = 0;
        }
        
        const percentDisplay = Math.abs(comparison_percent).toFixed(1);
        let statusClass = 'text-muted';
        let statusIcon = 'fa-arrows-h'; // Sama
        
        if (comparison_percent > 0) {
            statusClass = 'text-danger';
            statusIcon = 'fa-arrow-up'; // Kenaikan
        } else if (comparison_percent < 0) {
            statusClass = 'text-success';
            statusIcon = 'fa-arrow-down'; // Penurunan
        }

        document.getElementById('comparisonPercent').innerHTML =
            `${percentDisplay}% <i class="fas ${statusIcon}"></i>`;
        document.getElementById('comparisonPercent').className = `fw-bold fs-4 ${statusClass}`;

        document.getElementById('costBValue').innerHTML =
            `(${formatRupiahJs(costB)} | ${labelB})`;
    }

    function updateLocationDropdown(locations, selectedId) {
        const select = document.getElementById('location_select');
        // Kosongkan kecuali opsi 'All Area' yang sudah ada di PHP
        select.innerHTML = '<option value="all">All Area</option>';
        
        // Loop melalui data lokasi dari API
        for (const id in locations) {
            if (id !== 'all') {
                const name = locations[id];
                const opt = document.createElement('option');
                opt.value = id;
                opt.textContent = name;
                if (id == selectedId) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            }
        }
    }
    
    // =========================================================
    // PERBAIKAN UTAMA DI FUNGSI INI
    // =========================================================
    function renderComparisonChart(data) {
        // PERBAIKAN 1: Pastikan elemen container ada
        const container = document.getElementById('comparisonChartContainer');
        if (!container) return; // Keluar jika container tidak ada

        if (comparisonChartInstance) comparisonChartInstance.destroy();

        const labels = data.daily_comparison_data.map(d => d.date);
        const dataA = data.daily_comparison_data.map(d => d.cost_A);
        const dataB = data.daily_comparison_data.map(d => d.cost_B);
        
        if (labels.length === 0) {
            container.innerHTML =
                `<div class="alert alert-warning text-center">Tidak ada biaya tercatat pada periode ini.</div>`;
            return;
        } else {
            // PERBAIKAN 2: Regenerasi Canvas
            container.innerHTML = `<canvas id="comparisonChart" height="200"></canvas>`;
        }


        const ctx = document.getElementById('comparisonChart');
        if (!ctx) return; // Keluar jika canvas gagal diregenerasi

        comparisonChartInstance = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Periode A (' + data.params.label_A + ')',
                        data: dataA,
                        borderColor: 'rgba(54, 162, 235, 1)', 
                        backgroundColor: 'rgba(54, 162, 235, 0.4)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0
                    },
                    {
                        label: 'Periode B (' + data.params.label_B + ')',
                        data: dataB,
                        borderColor: 'rgba(255, 99, 132, 1)', 
                        backgroundColor: 'rgba(255, 99, 132, 0.4)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + formatRupiahJs(c.parsed.y) } },
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: formatRupiahTick } },
                    x: { ticks: { maxRotation: 45, minRotation: 45 } }
                }
            }
        });
    }

    function renderMonthlyTrendChart(data) {
        // PERBAIKAN 1: Pastikan elemen container ada
        const container = document.getElementById('trendChartContainer');
        if (!container) return; // Keluar jika container tidak ada
        
        if (trendChartInstance) trendChartInstance.destroy();

        const labels = data.monthly_trend_data.map(d => d.month_year);
        const totals = data.monthly_trend_data.map(d => d.total_cost);

        if (labels.length === 0) {
            container.innerHTML =
                `<div class="alert alert-info text-center">Tidak ada data tren bulanan untuk ditampilkan.</div>`;
            return;
        } else {
            // PERBAIKAN 2: Regenerasi Canvas
            container.innerHTML = `<canvas id="trendChart" height="200"></canvas>`;
        }

        const ctx = document.getElementById('trendChart');
        if (!ctx) return; // Keluar jika canvas gagal diregenerasi
        
        trendChartInstance = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels.map(l => {
                    const [year, month] = l.split('-');
                    return `${month}/${year.slice(2)}`;
                }),
                datasets: [{
                    label: 'Total Pengeluaran (Rp)',
                    data: totals,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => 'Total: ' + formatRupiahJs(c.parsed.y) } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: formatRupiahTick } }
                }
            }
        });
    }


    // =========================================================
    // EVENT LISTENERS
    // =========================================================
    document.addEventListener('DOMContentLoaded', () => {
        // Panggil loadCostData() setelah semua elemen DOM, termasuk Chart.js (yang dimuat dari footer), siap.
        
        // PENTING: Gunakan timeout kecil untuk memberi waktu Chart.js dimuat jika diletakkan di footer
        setTimeout(() => {
            loadCostData();
        }, 100); 

        // Load data saat tombol "Bandingkan" diklik
        document.getElementById('loadChartBtn').addEventListener('click', loadCostData);
        
        // Simpan filter saat form disubmit (tekan enter)
        document.getElementById('costFilterForm').addEventListener('submit', (e) => {
            e.preventDefault();
            loadCostData();
        });
    });

</script>

</body>
<?php include 'user_footer.php'; // Ganti user_footer.php menjadi footer.php jika itu yang dipakai secara global ?>
</html>