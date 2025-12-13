<?php
// footer.php (Koreksi ID Mentah Notifikasi & Pemastian Chart.js)

// --- A. PHP BASE URL FALLBACK ---
if (!isset($base_url)) {
    // FALLBACK jika lupa include header.php di halaman lain
    $base_dir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    $base_dir = rtrim($base_dir, '/'); 
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $base_dir . '/';
}
$safe_base_url = htmlspecialchars($base_url);
?>

</div> </div> </div> <a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
</a>

<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <a class="btn btn-primary" href="<?php echo $safe_base_url; ?>logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $safe_base_url; ?>vendor/jquery/jquery.min.js"></script> 
<script src="<?php echo $safe_base_url; ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script> 
<script src="<?php echo $safe_base_url; ?>vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="<?php echo $safe_base_url; ?>js/sb-admin.min.js"></script> 

<script src="<?php echo $safe_base_url; ?>vendor/datatables/jquery.dataTables.js"></script> 
<script src="<?php echo $safe_base_url; ?>vendor/datatables/dataTables.bootstrap4.js"></script> 
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script src="<?php echo $safe_base_url; ?>js/dashboard.js"></script> 

<script>
    // Inisialisasi BASE_URL di JavaScript
    const BASE_URL = '<?php echo $safe_base_url; ?>'; 

    // 1. Skrip DataTables umum (diaktifkan kembali)
    $(document).ready(function() {
        if ($.fn.DataTable && $('#dataTable').length) {
            $('#dataTable').DataTable();
        }
    });

    // 2. Notifikasi AJAX
    function loadNotifications() {
        // Menggunakan BASE_URL untuk path absolut AJAX (Koreksi)
        fetch(BASE_URL + 'ajax/ajax_get_notifications.php')
            .then(res => res.json())
            .then(data => {
                const badge = document.getElementById('notificationBadge');
                const list = document.getElementById('notificationList');

                if (!badge || !list) return; 

                const totalVisibleCount = data.open_count + data.progress_count; 

                if (totalVisibleCount > 0) {
                    badge.textContent = totalVisibleCount;
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }

                let listContent = `<h6 class="dropdown-header">${data.open_count} Open | ${data.progress_count} Progress</h6>`;

                if (data.data && data.data.length > 0) {
                    data.data.forEach(notif => {
                        let statusColor = '';
                        let statusText = '';
                        if (notif.status_wo === 'open') {
                            statusColor = 'text-danger';
                            statusText = 'OPEN';
                        } else if (notif.status_wo === 'progress') {
                            statusColor = 'text-warning';
                            statusText = 'PROGRESS';
                        }
                        
                        // ID mentah/raw untuk TAMPILAN
                        const safe_id_raw = String(notif.id_raw || 'N/A').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                        
                        // ID terenkripsi untuk URL link
                        const safe_id_encrypted = encodeURIComponent(notif.id); 
                        
                        // Sanitize data lainnya
                        const safe_status = statusText;
                        const safe_requester = notif.requester.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                        const safe_department = notif.department.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                        const safe_date = notif.date.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

                        listContent += `
                            <a class="dropdown-item small" href="${BASE_URL}work_order_view.php?id=${safe_id_encrypted}">
                                <strong class="${statusColor}">#${safe_id_raw} (${safe_status})</strong> - ${safe_requester}
                                <br><small class="text-muted">${safe_department} - ${safe_date}</small>
                            </a>
                        `;
                    });

                    if (data.open_count + data.progress_count > data.data.length) {
                        listContent += '<div class="dropdown-divider"></div>';
                        listContent += `<a class="dropdown-item text-center small" href="${BASE_URL}work_order_open.php">Lihat Semua Open & Progress</a>`;
                    }
                } else {
                    listContent += '<a class="dropdown-item disabled text-center small">Tidak ada WO baru/progress.</a>';
                }

                list.innerHTML = listContent;
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }

    // Panggil sekali saat load, lalu set interval
    loadNotifications();
    setInterval(loadNotifications, 300000); 
</script>

</body>
</html>