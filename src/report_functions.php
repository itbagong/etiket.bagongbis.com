<?php
// src/report_functions.php

/**
 * Mengambil data Work Order untuk laporan kinerja berdasarkan periode waktu.
 * Asumsi: 1 WO = 1 Kategori/Subkategori.
 *
 * @param mysqli $db Objek koneksi database.
 * @param string $start_date Tanggal mulai (Y-m-d).
 * @param string $end_date Tanggal selesai (Y-m-d).
 * @return array Hasil query.
 */
function getWorkOrderPerformanceData($db, $start_date, $end_date) : array
{
    // Sanitize input tanggal untuk keamanan
    $safe_start_date = $db->real_escape_string($start_date);
    $safe_end_date = $db->real_escape_string($end_date);
    
    $date_filter_sql = "";
    if ($start_date && $end_date) {
        $date_filter_sql = "AND wo.date_request BETWEEN '{$safe_start_date}' AND '{$safe_end_date}'";
    }

    $main_query = "
        SELECT
            wo.wo_id, wo.date_request, wo.status, wo.priority,
            d.departemen_name,
            l.location_name, -- Tambah lokasi untuk kelengkapan
            tc.name AS category_name,
            ts.name AS subcategory_name,
            c_latest.created_at AS latest_update_ts,
            c_closed.created_at AS date_closed_comment
        FROM work_order wo
        
        -- Join ke Departemen (Filter Status: departemen_status = 0)
        LEFT JOIN departemen d ON wo.department = d.departemen_id AND d.departemen_status = 0 
        
        -- Join ke Location (Filter Status: location_status = 0)
        LEFT JOIN location l ON wo.location = l.location_id AND l.location_status = 0 

        -- Perbaikan only_full_group_by: Menggunakan MAX() untuk memilih 1 set kategori/subkategori per WO
        LEFT JOIN (
            SELECT 
                wo_id, 
                MAX(category) AS category,    
                MAX(subcategory) AS subcategory    
            FROM work_order_items
            WHERE status = 0 -- Filter Status: work_order_items.status = 0
            GROUP BY wo_id
        ) woi ON wo.wo_id = woi.wo_id
        
        -- Join ke Ticket Category (Filter Status: status = 0)
        LEFT JOIN ticket_category tc ON woi.category = tc.id AND tc.status = 0
        
        -- Join ke Ticket Subcategory (Filter Status: status = 0)
        LEFT JOIN ticket_subcategory ts ON woi.subcategory = ts.id AND ts.status = 0
        
        LEFT JOIN (
            SELECT ticket, MAX(created_at) as created_at
            FROM comments
            WHERE status = 0 -- Filter Status: comments.status = 0
            GROUP BY ticket
        ) c_latest ON wo.wo_id = c_latest.ticket
        
        LEFT JOIN (
            SELECT c.ticket, MAX(c.created_at) as created_at
            FROM comments c
            INNER JOIN work_order wo_c ON c.ticket = wo_c.wo_id
            WHERE wo_c.status = 'closed' AND c.status = 0 -- Filter Status: comments.status = 0
            GROUP BY c.ticket
        ) c_closed ON wo.wo_id = c_closed.ticket AND wo.status = 'closed'
        
        WHERE
            wo.status_wo = 0 -- Kondisi utama: WO harus aktif (status_wo = 0)
            {$date_filter_sql}
        ORDER BY wo.wo_id ASC;
    ";

    $result = $db->query($main_query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

/**
 * Menghitung batas tanggal untuk periode laporan.
 * Fungsi ini dipindahkan dari work_order_functions.php.
 */
function get_report_period(string $type, string $value = null) {
    $start_date = null;
    $end_date = null;
    $label = "Semua Waktu";

    switch (strtolower($type)) {
        case 'day':
            $date = $value ?? date('Y-m-d');
            $start_date = $date . ' 00:00:00';
            $end_date = $date . ' 23:59:59';
            $label = "Harian: " . date('d F Y', strtotime($date));
            break;
            
        case 'week':
            $date = $value ?? date('Y-m-d');
            $start_date = date('Y-m-d', strtotime('monday this week', strtotime($date))) . ' 00:00:00';
            $end_date = date('Y-m-d', strtotime('sunday this week', strtotime($date))) . ' 23:59:59';
            $label = "Mingguan: " . date('d F', strtotime($start_date)) . " - " . date('d F Y', strtotime($end_date));
            break;

        case 'month':
            $month = $value ?? date('Y-m', strtotime('last month'));
            $start_date = date("Y-m-01", strtotime($month)) . ' 00:00:00';
            $end_date = date("Y-m-t", strtotime($month)) . ' 23:59:59';
            $label = "Bulanan: " . date('F Y', strtotime($month));
            break;
            
        case 'year':
            $year = $value ?? date('Y');
            $start_date = $year . "-01-01 00:00:00";
            $end_date = $year . "-12-31 23:59:59";
            $label = "Tahunan: " . $year;
            break;
            
        default:
            // Jika 'all' atau tipe tidak dikenal
            break;
    }
    
    return [
        'start_date' => $start_date,
        'end_date' => $end_date,
        'label' => $label
    ];
}

/**
 * Menghitung perbedaan hari antara dua timestamp.
 * Fungsi ini dipindahkan dari work_order_functions.php.
 */
function calculate_cycle_time($start_date, $end_date) {
    if (!$start_date || !$end_date) return 0;
    
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        
        return (int)$interval->days; 
    } catch (\Exception $e) {
        return 0;
    }
}
?>