<?php
// daily_report_styled.php - Generator Laporan Harian Kategori dengan Styling Rapi

// --- FILE REQUIREMENT INTI (Sesuaikan dengan path sistem Anda) ---
// ASUMSI: getDb() didefinisikan di sini atau di file yang di-require.
require_once 'src/Database.php'; 

// Matikan output buffering jika masih aktif
if (ob_get_level()) {
    ob_end_clean();
}

/**
 * Mendapatkan koneksi database (ASUMSI fungsi ini tersedia)
 * @return mysqli Koneksi database
 */
function getDb() {
    // GANTI INI JIKA getDb() ANDA BERADA DI TEMPAT LAIN
    return Database::getInstance(); 
}

/**
 * Mengambil data Work Order itemized hari ini dan mengelompokkannya per kategori.
 * @param string $date Tanggal laporan (format Y-m-d)
 * @return array Data Work Order yang dikelompokkan
 */
function getDailyReportData(string $date): array {
    $conn = getDb();

    // Mengubah status_filter menjadi format DB (jika diperlukan)
    $status_db_list = ['open', 'in_progress', 'pending', 'closed', 'canceled'];
    $status_in = "'" . implode("','", array_map('strtolower', $status_db_list)) . "'";
    
    // Klausa WHERE disetel untuk tanggal yang diminta
    $where_sql = "WHERE DATE(wo.date_request) = '{$conn->real_escape_string($date)}'";
    // Tambahan filter status: hanya WO yang Closed, In Progress, atau Open hari ini?
    // ASUMSI: Kita ingin semua status yang dibuat hari ini. Jika ingin hanya yang Closed, ubah filternya.

    $sql = "
        SELECT 
            wo.wo_id AS ID_WO, u.name AS Nama_Pemohon, d.departemen_name AS Departemen, 
            l.location_name AS Lokasi, wo.description AS Deskripsi_Masalah, 
            wo.status AS Status_WO, 
            
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
        
        ORDER BY Kategori_Barang ASC, wo.wo_id DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query database gagal: " . $conn->error);
    }
    
    $grouped_data = [];
    while ($row = $result->fetch_assoc()) {
        $category = strtoupper($row['Kategori_Barang'] ?: 'IT - LAINNYA');
        
        // Membentuk deskripsi yang rapi seperti format WhatsApp
        $location_or_dept = htmlspecialchars($row['Lokasi'] ?: $row['Departemen']);
        $wo_status = htmlspecialchars(ucwords(str_replace('_', ' ', $row['Status_WO'])));
        $issue_desc = htmlspecialchars(trim($row['Deskripsi_Masalah']));
        
        // Tentukan deskripsi utama
        $description = $location_or_dept . ' - Issue ';

        if (!empty($row['Subkategori_Barang'])) {
            $description .= htmlspecialchars($row['Subkategori_Barang']) . ': ';
        }
        
        $description .= $issue_desc;
        
        // Jika ada SN/Inventaris, tambahkan
        if ($row['SN_No_Inventaris']) {
             $description .= " (SN/Inv: " . htmlspecialchars($row['SN_No_Inventaris']) . ")";
        }

        // Tambahkan Status/Tindakan di akhir
        $description .= " - Tindakan Sudah dilakukan " . $wo_status;


        // Kelompokkan data
        $grouped_data[$category][] = [
            'ID_WO' => $row['ID_WO'],
            'description' => $description
        ];
    }
    
    return $grouped_data;
}


/**
 * Menghasilkan output laporan harian dalam format HTML dengan styling Bootstrap.
 * @param array $grouped_data Data Work Order yang dikelompokkan
 * @param string $date Tanggal laporan
 * @return string Output HTML laporan
 */
function generateDailyReportOutput(array $grouped_data, string $date): string {
    $formatted_date = date('d F Y', strtotime($date));
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Daily Report IT - <?= $formatted_date ?></title>
        <style>
            body { 
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; 
                background-color: #f8f9fa; /* light gray background */
                padding: 20px;
                color: #212529; /* dark text */
            }
            .chat-container {
                max-width: 650px;
                margin: auto;
                background-color: #e0f2d2; /* Light Green Chat Background */
                border-radius: 10px;
                padding: 15px;
                box-shadow: 0 4px 8px rgba(0,0,0,.1);
            }
            .header-info {
                padding-bottom: 10px;
                margin-bottom: 15px;
                border-bottom: 2px solid #ced4da;
                font-size: 14px;
            }
            .category-title {
                font-size: 16px;
                font-weight: 700; /* Bold */
                color: #38761d; /* Darker Green for Category */
                margin-top: 20px;
                margin-bottom: 5px;
            }
            .issue-list {
                list-style-type: none;
                padding-left: 0;
                margin-bottom: 20px;
            }
            .issue-item {
                font-size: 14px;
                margin-bottom: 8px;
                position: relative;
                padding-left: 20px; /* Space for list number */
            }
            .issue-item::before {
                content: attr(data-counter) ".";
                position: absolute;
                left: 0;
                font-weight: bold;
                color: #1f6b1e;
            }
            .issue-item-text strong {
                font-weight: 600;
            }
            /* Styling for the first line of the report */
            .greeting {
                font-weight: 600;
                color: #000;
            }
            .section-detail {
                color: #6c757d;
                font-size: 13px;
                margin-top: 0;
            }
        </style>
    </head>
    <body>
        <div class="chat-container">
            <div class="header-info">
                <p class="section-detail"><strong>Section : IT </strong></p>
                <strong>Tanggal : <?= $formatted_date ?></strong>
            </div>

            <?php if (empty($grouped_data)): ?>
                <div style="text-align: center; color: #dc3545; margin-top: 30px;">
                    Tidak ada Work Order yang dibuat pada tanggal ini.
                </div>
            <?php endif; ?>

            <?php foreach ($grouped_data as $category => $items): ?>
                <div class="category-title">
                    <?= htmlspecialchars($category) ?>
                </div>

                <ul class="issue-list">
                    <?php 
                    $item_counter = 1;
                    foreach ($items as $item): 
                    ?>
                        <li class="issue-item" data-counter="<?= $item_counter ?>">
                            <span class="issue-item-text">
                                <?= htmlspecialchars($item['description']) ?>
                            </span>
                        </li>
                    <?php 
                    $item_counter++;
                    endforeach; 
                    ?>
                </ul>
            <?php endforeach; ?>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    return $html;
}

// =========================================================
// EKSEKUSI
// =========================================================

// Tentukan tanggal laporan (default: hari ini)
$report_date = date('Y-m-d'); 
// Jika Anda ingin laporan kemarin:
// $report_date = date('Y-m-d', strtotime('-1 day')); 

try {
    $data_report = getDailyReportData($report_date);
    $final_output_html = generateDailyReportOutput($data_report, $report_date);
    
    // Tampilkan hasil
    echo $final_output_html;

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "Terjadi kesalahan saat membuat laporan: " . $e->getMessage();
}

exit;
?>