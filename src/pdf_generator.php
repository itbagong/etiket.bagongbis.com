<?php
// src/pdf_generator.php - Generator HTML untuk Print Work Order (FINAL)

// Matikan output buffering jika masih aktif
if (ob_get_level()) {
    ob_end_clean();
}

// Memastikan fungsi helper work order tersedia
require_once __DIR__ . '/work_order_functions.php';

// Helper untuk format mata uang (disarankan diletakkan di work_order_functions.php, tapi untuk kemudahan ditaruh di sini)
function formatRupiah($number) {
    if (is_numeric($number)) {
        return 'Rp ' . number_format($number, 0, ',', '.');
    }
    return $number;
}

/**
 * Menghasilkan dan menampilkan detail Work Order di browser untuk pencetakan manual.
 * @param int $wo_id ID Work Order (sudah didekripsi/divalidasi)
 */
function generateWorkOrderPdf(int $wo_id) {
    $db = getDb();

    // 1. Ambil data Work Order Utama (Termasuk SN)
    $stmt = $db->prepare("
        SELECT
            wo.*,
            u.name AS requester_name,
            d.departemen_name,
            l.location_name,
            wt.type_name,
            t.name AS team_name
        FROM work_order wo
        LEFT JOIN users u ON wo.requester_id = u.id
        LEFT JOIN departemen d ON wo.department = d.departemen_id
        LEFT JOIN location l ON wo.location = l.location_id
        LEFT JOIN work_type wt ON wo.work_type = wt.type_id
        LEFT JOIN team t ON wo.team = t.id
        WHERE wo.wo_id = ?
    ");
    $stmt->bind_param('i', $wo_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$data) {
        header('Content-Type: text/plain');
        http_response_code(404);
        die("Work Order tidak ditemukan.");
    }

    // 2. Ambil data Barang Diajukan (Item Tunggal) - PERUBAHAN DI SINI UNTUK MENGHITUNG TOTAL BIAYA
    $stmtItem = $db->prepare("
        SELECT
            c.name AS cat,
            s.name AS sub,
            i.qty,
            i.cost,
            (i.qty * i.cost) AS total_cost -- ** PERHITUNGAN TOTAL BIAYA **
        FROM work_order_items i
        LEFT JOIN ticket_category c ON i.category = c.id
        LEFT JOIN ticket_subcategory s ON i.subcategory = s.id
        WHERE i.wo_id = ?
    ");
    $stmtItem->bind_param('i', $wo_id);
    $stmtItem->execute();
    $item = $stmtItem->get_result()->fetch_assoc();
    $stmtItem->close();

    // 3. Ambil Komentar Terakhir (Latest Notes) - HANYA KOMENTAR PUBLIK
    $latestComment = null;
    $stmtComment = $db->prepare("
        SELECT
            c.body,
            u.name AS team_member_name
        FROM comments c
        LEFT JOIN users u ON c.team_member = u.id
        WHERE c.ticket = ? AND c.private = 0
        ORDER BY c.created_at DESC
        LIMIT 1
    ");
    $stmtComment->bind_param('i', $wo_id);
    $stmtComment->execute();
    $resComment = $stmtComment->get_result();
    if ($resComment->num_rows > 0) {
        $latestComment = $resComment->fetch_assoc();
    }
    $stmtComment->close();
    
    // =========================================================
    // 4. GENERATE HTML UNTUK TAMPILAN PRINT
    // =========================================================
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>Work Order #<?= $wo_id ?></title>
        <style>
            /* Menggunakan font yang umum di semua browser */
            body { font-family: Arial, sans-serif; font-size: 10pt; padding: 20px; }
            h3, h5 { color: #333; }
            .header-info th, .header-info td, .item-table th, .item-table td, .notes-section td {
                border: 1px solid #ccc;
                padding: 6px;
                text-align: left;
            }
            .header-info { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .item-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            /* PERUBAHAN JUDUL HEADER TABEL ITEM */
            .item-table th { background-color: #f2f2f2; }
            .status { font-weight: bold; color: #0d6efd; }
            .priority { font-weight: bold; color: #dc3545; }
            
            /* --- CSS BARU UNTUK LOGO --- */
            .logo-header {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #333; /* Garis pemisah */
                padding-bottom: 10px;
            }
            .logo-header img {
                max-height: 50px; /* Atur ketinggian logo */
                width: auto;
                margin-right: 20px;
            }
            .logo-header h3 {
                margin: 0;
                font-size: 16pt;
                line-height: 1.2;
            }

            /* CSS Khusus untuk Print: menyembunyikan tombol cetak saat mencetak */
            @media print {
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        
        <button onclick="window.print()" style="padding: 10px 15px; margin-bottom: 20px; background-color: #007bff; color: white; border: none; cursor: pointer;" class="no-print">
            Cetak Work Order Sekarang
        </button>

        <div class="logo-header">
            <img src="https://bagongbis.com/images/logo_bagong.png" alt="Logo Perusahaan">
            <div>
                <h3 style="margin-top: 0;">WORK ORDER PERMINTAAN PERBAIKAN</h3>
                <span style="font-size: 10pt; color: #666;">PT. Bagong Dekaka Makmur</span>
            </div>
        </div>
        <p><strong>No. WO: #<?= $wo_id ?></strong> &nbsp;|&nbsp; Tanggal Request: <strong><?= htmlspecialchars($data['date_request']) ?></strong></p>

        <h5>Detail Permintaan</h5>
        <table class="header-info">
            <tr>
                <th width="25%">Pemohon</th>
                <td><?= htmlspecialchars($data['requester_name'] ?? 'ID ' . $data['requester_id']) ?></td>
                <th width="25%">Status</th>
                <td><span class="status"><?= htmlspecialchars(ucwords($data['status'])) ?></span></td>
            </tr>
            <tr>
                <th>Departemen</th>
                <td><?= htmlspecialchars($data['departemen_name']) ?></td>
                <th>Prioritas</th>
                <td><span class="priority"><?= htmlspecialchars($data['priority']) ?></span></td>
            </tr>
            <tr>
                <th>Lokasi</th>
                <td><?= htmlspecialchars($data['location_name']) ?></td>
                <th>Tim Penanggung Jawab</th>
                <td><?= htmlspecialchars($data['team_name']) ?></td>
            </tr>
            <tr>
                <th>Jenis Pekerjaan</th>
                <td><?= htmlspecialchars($data['type_name']) ?></td>
                <th>SN / Inventaris</th>
                <td><?= htmlspecialchars($data['SN'] ?: '-') ?></td>
            </tr>
        </table>
        
        <p><strong>Deskripsi Masalah Umum:</strong><br><?= nl2br(htmlspecialchars($data['description'])) ?></p>

        <h5>Detail Barang Diajukan</h5>
        <table class="item-table">
            <thead>
                <tr>
                    <th width="35%">Kategori & Subkategori</th>
                    <th width="15%">Qty</th>
                    <th width="50%">Total Estimasi Biaya</th> </tr>
            </thead>
            <tbody>
                <?php if ($item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['cat']) ?> &rarr; <?= htmlspecialchars($item['sub']) ?></td>
                    <td><?= htmlspecialchars($item['qty']) ?></td>
                    <td><span style="font-weight: bold; color: green;"><?= formatRupiah($item['total_cost']) ?></span></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center;">Tidak ada data barang.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h5>Komentar Terakhir (Latest Notes)</h5>
        <table class="notes-section">
            <tr>
                <td style="background-color: #f9f9f9;">
                    <?php if ($latestComment): ?>
                        <p><strong><?= htmlspecialchars($latestComment['team_member_name'] ?? 'Petugas IT') ?>:</strong></p>
                        <p><?= nl2br(htmlspecialchars($latestComment['body'])) ?></p>
                    <?php else: ?>
                        <p>Belum ada komentar publik.</p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <div style="margin-top: 50px; text-align: right; font-size: 8pt;">
            Dicetak pada: <?= date('d M Y H:i:s') ?>
        </div>

    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // 5. TAMPILKAN HTML LANGSUNG DI BROWSER
    echo $html;
}
?>