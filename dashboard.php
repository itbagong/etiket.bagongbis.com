<?php
// dashboard.php - FINAL GABUNGAN WORK ORDER (VERSI 2.0 - DIBERSIHKAN DARI COST REPORT)
require_once 'header.php'; 

// Pastikan session sudah dimulai sebelumnya
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =======================================================
// 1. KONTROL AKSES BERBASIS PERAN (RBAC)
// =======================================================
if (!isset($_SESSION['logged-in']) || $_SESSION['logged-in'] !== true) {
    header('Location: ./index.php');
    exit();
}

$user_role = $_SESSION['user']->role ?? 'guest';
if ($user_role !== 'admin' && $user_role !== 'teknisi') {
    header('Location: ./user_dashboard.php');
    exit();
}

// =======================================================
// 2. FILE DEPENDENSI DAN KONEKSI
// =======================================================
require_once 'function.php'; 

// HAPUS HELPER formatRupiah PHP dan parameter GET Cost Report.
// (Tidak ada kode PHP lain yang perlu diubah selain inisialisasi awal)

?>

<div class="container py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Dashboard Work Order</h4>
        <div class="d-flex gap-2">
            <select id="filter" class="form-control m-3">
                <option value="all">Semua Waktu</option>
                <option value="month">Bulan Ini</option>
                <option value="week">Minggu Ini</option>
            </select>
            <select id="filterLocation" class="form-control m-3">
                <option value="all">Semua Lokasi</option>
            </select>
            <select id="filterCategory" class="form-control m-3" style="min-width: 150px;">
                <option value="all">Semua Kategori</option>
            </select>
        </div>
    </div>

    <div class="row mb-4" id="statCards">
        <div class="col-12"><div class="alert alert-info">Memuat data statistik...</div></div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100"><div class="card-header fw-bold">Status Work Order</div>
                <div class="card-body"><canvas id="chartStatus" height="200"></canvas></div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100"><div class="card-header fw-bold">Tren Work Order Harian</div>
                <div class="card-body"><canvas id="chartTrend" height="200"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100"><div class="card-header fw-bold">WO Berdasarkan Lokasi</div>
                <div class="card-body"><canvas id="chartLocation" height="250"></canvas></div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100"><div class="card-header fw-bold">WO Berdasarkan Kategori</div>
                <div class="card-body"><canvas id="chartCategory" height="250"></canvas></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100"><div class="card-header fw-bold">Rata-rata Waktu Pengerjaan (SLA)</div>
                <div class="card-body"><canvas id="chartSLA" height="250"></canvas></div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card shadow h-100"><div class="card-header fw-bold">WO Berdasarkan Subkategori</div>
                <div class="card-body"><canvas id="chartSubcategory" height="250"></canvas></div>
            </div>
        </div>
    </div>

    <h4 class="fw-bold mb-3 mt-4">Work Order Terbaru</h4>
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID WO</th>
                            <th>Requester</th>
                            <th>Departemen</th>
                            <th>Lokasi</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                            <th>Tanggal Request</th>
                        </tr>
                    </thead>
                    <tbody id="recentList">
                        <tr><td colspan='7' class='text-center'>Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


</div> <?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // =========================================================
    // VARIABEL GLOBAL & HELPER
    // =========================================================
    let chartStatus, chartTrend, chartLoc, chartCat, chartSubcat, chartSLA;
    // HAPUS: let comparisonChartInstance = null;
    // HAPUS: let trendCostChartInstance = null;
    
    const API_WO_URL = 'src/dashboard_api.php';
    // HAPUS: const API_COST_URL = 'src/work_order_cost.php'; 

    // HAPUS: formatRupiahJs dan formatRupiahTick (karena hanya digunakan untuk Cost Report)
    
    // Helper: Mendapatkan warna acak
    function getRandomColor(count) {
        const colors = [];
        for(let i=0; i<count; i++) {
            const hue = (360 / count) * i;
            colors.push(`hsl(${hue}, 70%, 50%)`);
        }
        return colors;
    }
    
    // =========================================================
    // FUNGSI UTAMA LOAD DATA WORK ORDER
    // =========================================================

    function loadDashboard() {
        const filter = document.getElementById('filter').value;
        const location_id = document.getElementById('filterLocation').value;
        const category_id = document.getElementById('filterCategory').value;

        fetch(`${API_WO_URL}?filter=${filter}&location_id=${location_id}&category_id=${category_id}`)
            .then(res => {
                if (!res.ok) { throw new Error(`HTTP error! status: ${res.status}`); }
                const contentType = res.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    // Hanya mengambil 100 karakter pertama jika terjadi error non-JSON
                    res.text().then(text => console.error("API Work Order Error Response:", text.substring(0, 100))); 
                    throw new Error("API Work Order tidak mengembalikan JSON. (API PHP mungkin crash)");
                }
                return res.json();
            })
            .then(data => {
                if (!data || Object.keys(data).length === 0 || data.error) {
                    throw new Error('API mengembalikan data kosong atau pesan error: ' + (data.error || ''));
                }

                renderStats(data.stats);
                renderStatus(data.stats);
                renderTrend(data.trend ?? []);
                renderLocations(data.locations);
                renderCategories(data.category_stats);
                renderSubcategories(data.subcategory_stats);
                renderSLA(data.sla_stats);
                renderRecent(data.recent); 
                updateLocationDropdown(data.locations, 'filterLocation');
                updateCategoryDropdown(data.all_categories, 'filterCategory');
            })
            .catch(error => {
                console.error('Error loading dashboard data:', error);
                const container = document.getElementById('statCards');
                container.innerHTML = `<div class="col-12"><div class="alert alert-danger">❌ Error memuat data Work Order: ${error.message}</div></div>`;
                document.getElementById('recentList').innerHTML = `<tr><td colspan='7' class='text-center'>Gagal memuat data Work Order.</td></tr>`;
            });
    }


    // =========================================================
    // FUNGSI RENDER WORK ORDER (DASHBOARD)
    // =========================================================
    
    // ... (Fungsi renderStats sama) ...
    function renderStats(stats) {
        const container = document.getElementById('statCards');
        const totalWO = stats.total; 
        
        const closed = stats.closed ?? 0;
        const progress = stats.progress ?? 0;
        const open = stats.open ?? 0;
        
        const cards = [
            {label: 'Total WO Aktif', value: totalWO, color: 'info'}, 
            {label: 'WO Open', value: open, color: 'danger'},
            {label: 'WO Progress', value: progress, color: 'warning'},
            {label: 'WO Closed', value: closed, color: 'success'}
        ];
        
        container.innerHTML = cards.map(c => `
            <div class="col-md-3">
                <div class="card text-white bg-${c.color} h-100 shadow-sm card-stat-custom">
                    <div class="card-body">
                        <h5 class="card-title">${c.value}</h5>
                        <p class="card-text">${c.label}</p>
                    </div>
                    <div class="card-footer">${totalWO > 0 ? ((c.value / totalWO) * 100).toFixed(1) : 0}%</div>
                </div>
            </div>`).join('');
    }

    // ... (Fungsi renderStatus sama) ...
    function renderStatus(stats) {
        const ctxStatus = document.getElementById('chartStatus').getContext('2d'); 
        if (chartStatus) chartStatus.destroy();
        
        const totalClosedDone = (stats.closed || 0) + (stats.done || 0); 
        const totalWO = stats.total;

        const dataValues = [stats.open, stats.progress, totalClosedDone];
        const dataLabels = ['Open', 'Progress', 'Closed/Done'];
        const dataPercents = dataValues.map(val => totalWO > 0 ? ((val / totalWO) * 100).toFixed(1) : 0);
        const backgroundColors = ['#dc3545', '#ffc107', '#198754']; 

        chartStatus = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: dataLabels,
                datasets: [{data: dataValues, backgroundColor: backgroundColors}]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {position: 'bottom'},
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {label += ': ';}
                                const value = context.parsed;
                                const percent = dataPercents[context.dataIndex]; 
                                return `${label} ${value} (${percent}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // ... (Fungsi renderTrend sama) ...
    function renderTrend(trend) {
        const ctxTrend = document.getElementById('chartTrend').getContext('2d'); 
        if (chartTrend) chartTrend.destroy();
        const labels = trend.map(t => t.date);
        const totals = trend.map(t => t.total);
        chartTrend = new Chart(ctxTrend, {
            type: 'line',
            data: {labels, datasets: [{
                label: 'Total Work Order', 
                data: totals, 
                borderWidth: 2, 
                tension: 0.3, 
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.2)'
            }]},
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {legend: {display: false}},
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Jumlah WO' } },
                    x: { title: { display: true, text: 'Tanggal' } }
                }
            }
        });
    }

    // ... (Fungsi renderLocations sama) ...
    function renderLocations(locations) {
        const ctxLoc = document.getElementById('chartLocation').getContext('2d'); 
        if (chartLoc) chartLoc.destroy();
        const filteredLocations = locations.filter(l => l.name); 

        chartLoc = new Chart(ctxLoc, {
            type: 'bar',
            data: {
                labels: filteredLocations.map(l => l.name), 
                datasets: [{
                    label: 'Total WO Aktif', 
                    data: filteredLocations.map(l => l.total),
                    backgroundColor: getRandomColor(filteredLocations.length)
                }]},
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {legend: {display: false}},
                indexAxis: 'y', 
                scales: {x: { beginAtZero: true }}
            }
        });
    }

    // ... (Fungsi renderCategories sama) ...
    function renderCategories(categories) {
        const ctxCat = document.getElementById('chartCategory').getContext('2d'); 
        if (chartCat) chartCat.destroy();
        const labels = categories.map(c => c.name);
        const totals = categories.map(c => c.total);
        const grandTotal = totals.reduce((sum, current) => sum + current, 0);

        chartCat = new Chart(ctxCat, {
            type: 'doughnut',
            data: {labels, datasets: [{data: totals, backgroundColor: getRandomColor(labels.length)}]},
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {position: 'bottom'},
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {label += ': ';}
                                const value = context.parsed;
                                const percent = grandTotal > 0 ? ((value / grandTotal) * 100).toFixed(1) : 0;
                                return `${label} ${value} (${percent}%)`;
                            }
                        }
                    }
                },
                layout: {padding: 10}
            }
        });
    }

    // ... (Fungsi renderSubcategories sama) ...
    function renderSubcategories(subcategories) {
        const ctxSubcat = document.getElementById('chartSubcategory').getContext('2d'); 
        if (chartSubcat) chartSubcat.destroy();

        const labels = subcategories.map(s => s.name);
        const totals = subcategories.map(s => s.total);
        const grandTotal = totals.reduce((sum, current) => sum + current, 0);

        chartSubcat = new Chart(ctxSubcat, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Total WO Subkategori',
                    data: totals,
                    backgroundColor: '#0dcaf0', 
                    borderColor: '#0dcaf0',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {display: false},
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed.x; 
                                const percent = grandTotal > 0 ? ((value / grandTotal) * 100).toFixed(1) : 0;
                                return `Total: ${value} (${percent}%)`;
                            }
                        }
                    }
                },
                indexAxis: 'y', 
                scales: {x: { beginAtZero: true }}
            }
        });
    }

    // ... (Fungsi renderSLA sama) ...
    function renderSLA(slaStats) {
        const ctxSLA = document.getElementById('chartSLA').getContext('2d'); 
        if (chartSLA) chartSLA.destroy();

        const labels = slaStats.map(s => s.name);
        const avgHours = slaStats.map(s => s.avg_hours);

        chartSLA = new Chart(ctxSLA, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Rata-rata Waktu (Jam)',
                    data: avgHours,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)', 
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {display: false},
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed.x; 
                                return `Rata-rata: ${value} Jam`;
                            }
                        }
                    }
                },
                indexAxis: 'y', 
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {display: true, text: 'Rata-rata Waktu Pengerjaan (Jam)'}
                    }
                }
            }
        });
    }

    // ... (Fungsi renderRecent, updateLocationDropdown, updateCategoryDropdown sama) ...
    function renderRecent(recent) {
        const tbody = document.getElementById('recentList');
        
        if (!recent || recent.length === 0) {
            tbody.innerHTML = `<tr><td colspan='7' class='text-center'>Tidak ada Work Order terbaru.</td></tr>`;
            return;
        }

        tbody.innerHTML = recent.map(r => {
            const requesterName = r.requester_name || 'N/A';
            let statusClass = '';
            switch (r.status.toLowerCase()) {
                case 'closed': statusClass = 'badge bg-success'; break;
                case 'progress': statusClass = 'badge bg-warning text-dark'; break;
                case 'open': statusClass = 'badge bg-danger'; break;
                default: statusClass = 'badge bg-secondary'; break;
            }

            return `
                <tr>
                    <td>${r.wo_id}</td>
                    <td>${requesterName}</td> 
                    <td>${r.departemen_name}</td>
                    <td>${r.location_name}</td>
                    <td><span class="badge bg-secondary">${r.priority}</span></td>
                    <td><span class="${statusClass}">${r.status}</span></td>
                    <td>${r.date_request}</td>
                </tr>
            `;
        }).join('');
    }

    function updateLocationDropdown(locations, elementId) {
        const select = document.getElementById(elementId);
        const currentSelectedValue = select.value;
        select.innerHTML = '<option value="all">All Area</option>'; 
        
        const validLocations = Array.isArray(locations) ? 
            locations.filter(l => l.id && l.name) : 
            Object.keys(locations).map(key => ({id: key, name: locations[key]}));

        validLocations.forEach(l => {
            if (l.id !== 'all') {
                const opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = elementId === 'filterLocation' ? `${l.name} (${l.total || '0'})` : l.name; 
                if (l.id == currentSelectedValue) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            }
        });
    }

    function updateCategoryDropdown(allCategories, elementId) {
        const select = document.getElementById(elementId);
        select.innerHTML = '<option value="all">Semua Kategori</option>';
        allCategories.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            if (c.id == select.value) { opt.selected = true; }
            select.appendChild(opt);
        });
    }


    // =========================================================
    // HAPUS FUNGSI RENDER COST REPORT (Biaya)
    // =========================================================
    // Fungsi-fungsi yang dihapus: renderCostSummary, renderComparisonChart, renderMonthlyTrendChart

    // =========================================================
    // EVENT LISTENERS
    // =========================================================
    document.addEventListener('DOMContentLoaded', () => {
        // Event Listeners Work Order Dashboard
        document.getElementById('filter').addEventListener('change', loadDashboard);
        document.getElementById('filterLocation').addEventListener('change', loadDashboard);
        document.getElementById('filterCategory').addEventListener('change', loadDashboard);

        // HAPUS Event Listeners Cost Report

        // Load data saat halaman pertama kali dimuat
        loadDashboard();
        // HAPUS: loadCostData();
    });

</script>