// ========================================================
// PASTIKAN TIDAK ADA DUPLIKASI DEKLARASI INI DI FILE LAIN!
// ========================================================

// Variabel konteks chart diinisialisasi
const ctxStatus = document.getElementById('chartStatus');
const ctxTrend = document.getElementById('chartTrend');
const ctxLoc = document.getElementById('chartLocation');
const ctxCat = document.getElementById('chartCategory');
const ctxSubcat = document.getElementById('chartSubcategory');
const ctxSLA = document.getElementById('chartSLA');

let chartStatus, chartTrend, chartLoc, chartCat, chartSubcat, chartSLA;

// Helper untuk mendapatkan warna acak
function getRandomColor(count) {
    const colors = [];
    for(let i=0; i<count; i++) {
        const hue = (360 / count) * i;
        colors.push(`hsl(${hue}, 70%, 50%)`);
    }
    return colors;
}

// --- FUNGSI UTAMA UNTUK MEMUAT DATA ---
function loadDashboard() {
    const filter = document.getElementById('filter').value;
    const location_id = document.getElementById('filterLocation').value;
    const category_id = document.getElementById('filterCategory').value;

    fetch(`src/dashboard_api.php?filter=${filter}&location_id=${location_id}&category_id=${category_id}`)
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            // Mencegah SyntaxError jika PHP crash (mengembalikan HTML error)
            const contentType = res.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new Error("API tidak mengembalikan JSON. (API PHP mungkin crash)");
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
            updateLocationDropdown(data.locations);
            updateCategoryDropdown(data.all_categories);
        })
        .catch(error => {
            console.error('Error loading dashboard data:', error);
            // Tampilkan pesan error di dashboard
            const container = document.getElementById('statCards');
            container.innerHTML = `<div class="col-12"><div class="alert alert-danger">❌ Error memuat data dashboard: ${error.message}</div></div>`;
            document.getElementById('recentList').innerHTML = `<tr><td colspan='7' class='text-center'>Gagal memuat data Work Order.</td></tr>`;
        });
}

// --- FUNGSI RENDER CHART & DATA ---

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

function renderStatus(stats) {
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

function renderTrend(trend) {
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

function renderLocations(locations) {
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

function renderCategories(categories) {
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

function renderSubcategories(subcategories) {
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

function renderSLA(slaStats) {
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

function renderRecent(recent) {
    const tbody = document.getElementById('recentList');
    
    if (!recent || recent.length === 0) {
        tbody.innerHTML = `<tr><td colspan='7' class='text-center'>Tidak ada Work Order terbaru.</td></tr>`;
        return;
    }

    tbody.innerHTML = recent.map(r => {
        // PERBAIKAN: Menggunakan requester_name dari API
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

function updateLocationDropdown(locations) {
    const select = document.getElementById('filterLocation');
    if (select.options.length <= 1) {
        const validLocations = locations.filter(l => l.id && l.name);
        validLocations.forEach(l => {
            const opt = document.createElement('option');
            opt.value = l.id;
            opt.textContent = `${l.name} (${l.total})`; 
            select.appendChild(opt);
        });
    }
}

function updateCategoryDropdown(allCategories) {
    const select = document.getElementById('filterCategory');
    if (select.options.length <= 1) {
        allCategories.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            select.appendChild(opt);
        });
    }
}

// --- EVENT LISTENERS ---
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('filter').addEventListener('change', loadDashboard);
    document.getElementById('filterLocation').addEventListener('change', loadDashboard);
    document.getElementById('filterCategory').addEventListener('change', loadDashboard);

    loadDashboard();
});