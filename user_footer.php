<?php
// user_footer.php - SEMUA LOGIKA JAVASCRIPT AJAX NOTIFIKASI DISATUKAN

// --- A. PHP BASE URL FALLBACK ---
if (!isset($base_url)) {
    $base_dir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    $base_dir = rtrim($base_dir, '/'); 
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $base_dir . '/';
}
$safe_base_url = htmlspecialchars($base_url);

// Versi unik untuk mencegah caching browser/CDN (diperlukan untuk link JS lain)
$js_version = '20251204'; 
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

<script src="<?php echo $safe_base_url; ?>vendor/jquery/jquery.min.js?v=<?php echo $js_version; ?>"></script> 
<script src="<?php echo $safe_base_url; ?>vendor/bootstrap/js/bootstrap.bundle.min.js?v=<?php echo $js_version; ?>"></script> 
<script src="<?php echo $safe_base_url; ?>vendor/jquery-easing/jquery.easing.min.js?v=<?php echo $js_version; ?>"></script>
<script src="<?php echo $safe_base_url; ?>js/sb-admin.min.js?v=<?php echo $js_version; ?>"></script> 

<script src="<?php echo $safe_base_url; ?>vendor/datatables/jquery.dataTables.js?v=<?php echo $js_version; ?>"></script> 
<script src="<?php echo $safe_base_url; ?>vendor/datatables/dataTables.bootstrap4.js?v=<?php echo $js_version; ?>"></script> 
<script src="https://cdn.jsdelivr.net/npm/chart.js?v=<?php echo $js_version; ?>"></script>

<script>
    
    // --- FUNGSI 1: MENAMPILKAN NOTIFIKASI TASKS/PALU (Dropdown Header) ---
    function renderUserProgressNotifications(apiResponse) {
        
        const progressCount = parseInt(apiResponse.progress_count_user) || 0; 
        const data = apiResponse.data || [];

        const list = $('#userProgressList');
        const badge = $('#userProgressBadge');

        // 1. Update Badge Tasks (Angka di Ikon Palu)
        if (badge.length) {
            if (progressCount > 0) {
                badge.text(progressCount).removeClass('d-none');
            } else {
                badge.addClass('d-none');
            }
        } else {
            console.warn('userProgressBadge ID not found! (Penyebab count 0)');
        }

        // 2. Render List (Dropdown Content)
        let html = `<h6 class="dropdown-header bg-warning text-white">WO Saya (Progress) (${progressCount})</h6>`;

        if (apiResponse.error) {
             html += `<span class="dropdown-item disabled text-danger text-center small">Gagal Memuat Tasks (RBAC Ditolak)</span>`;
        } else if (!data || data.length === 0) {
            html += `<span class="dropdown-item disabled text-center small">Tidak ada Work Order Anda yang sedang dalam Progress.</span>`;
        } else {
            data.forEach(item => {
                const iconClass = 'fas fa-check-circle text-white'; 
                const iconBg = 'bg-warning';
                const statusText = 'PROGRESS';
                // Deskripsi singkat
                const truncatedDesc = item.description ? item.description.substring(0, 30) + '...' : 'Detail Work Order';

                html += `<a class="dropdown-item d-flex align-items-center" href="./user_work_order_view.php?id=${item.id}">
                            <div class="mr-3">
                                <div class="icon-circle ${iconBg}"><i class="${iconClass}"></i></div>
                            </div>
                            <div>
                                <div class="small text-gray-500">${item.date_display} <span class="badge badge-warning">${statusText}</span></div>
                                <div>WO #${item.id_raw}</div>
                                <span class="d-block small text-muted">${truncatedDesc}</span>
                            </div>
                        </a>`;
            });

            html += '<a class="dropdown-item text-center small text-gray-500" href="./work_order_user_view.php">Lihat Semua WO Saya</a>';
        }

        if (list.length) {
            list.html(html);
        } else {
            console.warn('userProgressList ID not found! (Penyebab list kosong)');
        }
    }

    // --- FUNGSI 2: MENAMPILKAN HITUNGAN OPEN & PROGRESS DI DASHBOARD ---
    function renderActiveCounts(apiResponse) {
        
        const open = parseInt(apiResponse.open_count) || 0;
        const progress = parseInt(apiResponse.progress_count) || 0;
        const total = parseInt(apiResponse.total_active) || (open + progress); 

        // Update element di dashboard
        $('#openCount').text(open); 
        $('#progressCount').text(progress); 
        $('#totalActiveCount').text(total); 
        
        // DEBUG VISUAL
        $('#debugDataRead').text(`Role: ${apiResponse.debug_role} | Progress User: ${progress} | Open: ${open} | Total: ${total}`);
    }


    // --- FUNGSI 3: MEMUAT SEMUA NOTIFIKASI/HITUNGAN ---
    function loadNotificationsAll() {
        // Gunakan $safe_base_url dari PHP
        const userProgressUrl = '<?php echo $safe_base_url; ?>ajax/ajax_get_notifications_user_progress.php';

        $.ajax({
            url: userProgressUrl,
            method: 'GET',
            dataType: 'json',
            success: function(apiResponse) {
                console.log("AJAX SUCCESS. Data JSON:", apiResponse); 
                renderUserProgressNotifications(apiResponse); 
                renderActiveCounts(apiResponse); 
            },
            error: function(xhr, status, error) {
                console.error('AJAX ERROR. Gagal mengambil notifikasi:', status, error);
                
                const errorMessage = status + ': ' + error;
                const listUser = $('#userProgressList');
                if(listUser.length) { 
                    listUser.html(`<h6 class="dropdown-header bg-warning text-white">WO Saya (Progress)</h6>
                                    <span class="dropdown-item disabled text-danger small">Gagal Memuat Tasks (${errorMessage})</span>`);
                }
                
                $('#openCount').text('E!');
                $('#progressCount').text('E!');
                $('#totalActiveCount').text('E!'); 
                $('#debugDataRead').text(`Error: ${errorMessage}`);
            }
        });
    }

    // ⭐ EKSEKUSI: Jalankan fungsi setelah DOM siap
    $(document).ready(function() {
        loadNotificationsAll();
    });
</script>
</body>
</html>