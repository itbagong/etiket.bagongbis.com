<?php
// location_list.php - FINAL FIX UNTUK MODAL DELETE (Menggunakan Native JS Logic)

ob_start();
include './header.php';
require_once './src/location.php';
require_once './src/security.php'; 

// Pengecekan Izin Halaman (VIEW)
$auth->authorize('location_list.php', 'view');

$msg = $_GET['msg'] ?? ''; 
$err = $_GET['err'] ?? ''; 

// TANGANI REQUEST HAPUS (Kini menjadi SOFT DELETE)
if (isset($_GET['delete_id'])) {
    // 1. RBAC: Cek Izin HAPUS
    $auth->authorize('location_list.php', 'delete'); 
    
    // 2. DEKRIPSI ID dari URL
    $encrypted_id = $_GET['delete_id'];
    $delete_id = decrypt_id($encrypted_id); 

    if ($delete_id > 0) {
        try {
            $location_to_delete = Location::find($delete_id);
            if ($location_to_delete) {
                $location_name_safe = htmlspecialchars($location_to_delete->location_name); 
                
                // Panggil fungsi delete(), yang kini adalah softDelete() di src/location.php
                $location_to_delete->delete(); 

                // Redirect untuk membersihkan query string
                header('Location: location_list.php?msg=' . urlencode("Lokasi **{$location_name_safe}** berhasil dinonaktifkan (Soft Delete)."));
                exit();
            } else {
                $err = "Lokasi tidak ditemukan (ID: {$delete_id}).";
            }
        } catch (Exception $e) {
            $err = "Gagal menonaktifkan lokasi: " . $e->getMessage();
        }
    } else {
        $err = "ID Lokasi tidak valid atau terenkripsi dengan salah.";
    }
}

// AMBIL DATA LOKASI
$locations = Location::findAll();
?>

<div id="content-wrapper">

    <div class="container-fluid">

        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Daftar Lokasi</li>
        </ol>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">📍 Daftar Lokasi</h3>
            
            <?php
            // RBAC: Tombol Tambah
            if ($auth->can('location_form.php', 'add')):
            ?>
            <a href="location_form.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Tambah Lokasi Baru
            </a>
            <?php endif; ?>
        </div>

        <?php if(strlen($err) > 1) :?>
            <div class="alert alert-danger my-3" role="alert"><?php echo htmlspecialchars($err);?></div>
        <?php endif?>
        <?php if(strlen($msg) > 1) :?>
            <div class="alert alert-success my-3" role="alert"><?php echo htmlspecialchars($msg);?></div>
        <?php endif?>

        <div class="card mb-3 shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="dataTable" class="table table-striped table-bordered table-sm" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Lokasi</th>
                                <th>Alamat</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($locations as $location_item): 
                                $encrypted_location_id = encrypt_id($location_item->location_id);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($location_item->location_name ?? '') ?></td>
                                <td><?php echo htmlspecialchars($location_item->location_address ?? '') ?></td>
                                <td>
                                    <?php
                                    $status_text = ($location_item->location_status == 0) ? 'Aktif' : 'Tidak Aktif';
                                    $status_class = ($location_item->location_status == 1) ? 'badge bg-success text-white' : 'badge bg-danger text-white';
                                    echo "<span class='{$status_class}'>{$status_text}</span>";
                                    ?>
                                </td>
                                
                                <td>
                                    <?php 
                                    // RBAC: Tombol Edit
                                    if($auth->can('location_form.php', 'edit')): 
                                    ?>
                                    <a href="location_form.php?id=<?php echo $encrypted_location_id; ?>" class="btn btn-warning btn-sm me-1" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // RBAC: Tombol Hapus (Soft Delete)
                                    if($auth->can('location_list.php', 'delete') && $location_item->location_status == 1): 
                                    ?>
                                    <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#deleteModal"
                                        data-location-id="<?php echo $encrypted_location_id; ?>" 
                                        data-location-name="<?php echo htmlspecialchars($location_item->location_name ?? ''); ?>"
                                        title="Nonaktifkan Lokasi">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Nonaktifkan Lokasi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Apakah Anda yakin ingin **menonaktifkan** lokasi <strong id="locationNameToDelete"></strong>? Statusnya akan berubah menjadi **Tidak Aktif**.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <a id="confirmDeleteButton" href="#" class="btn btn-danger">Ya, Nonaktifkan</a> 
            </div>
        </div>
    </div>
</div>

<script>
// Logika ini menggunakan Native JavaScript API dari Bootstrap 4/jQuery
// Ini adalah skrip yang paling mungkin berhasil jika jQuery/Bootstrap API gagal

// Tunggu hingga DOM selesai dimuat
document.addEventListener('DOMContentLoaded', function() {
    
    // Inisialisasi DataTables jika jQuery sudah tersedia
    if (typeof jQuery !== 'undefined' && $.fn.DataTable) {
        $('#dataTable').DataTable();
    }

    const deleteModalEl = document.getElementById('deleteModal');
    
    if (deleteModalEl) {
        // Menggunakan event listener standar dari Bootstrap Modal (untuk v4)
        $(deleteModalEl).on('show.bs.modal', function (event) {
            
            // event.relatedTarget adalah tombol yang memicu modal
            const button = event.relatedTarget; 
            
            // Mengambil data-attribute menggunakan Native dataset API (lebih robust)
            const locationIdEncrypted = button.getAttribute('data-location-id');
            const locationName = button.getAttribute('data-location-name');

            const locationNameToDelete = document.getElementById('locationNameToDelete');
            const confirmDeleteButton = document.getElementById('confirmDeleteButton');
            
            // Set nama lokasi
            if (locationNameToDelete) {
                locationNameToDelete.textContent = locationName;
            }

            // Set URL Hapus/Nonaktifkan
            if (confirmDeleteButton) {
                if (locationIdEncrypted) {
                    const deleteUrl = 'location_list.php?delete_id=' + locationIdEncrypted; 
                    confirmDeleteButton.setAttribute('href', deleteUrl);
                } else {
                    // Jika gagal mendapatkan ID, pastikan linknya tidak aktif
                    confirmDeleteButton.setAttribute('href', '#'); 
                }
            }
        });
    }
});
</script>
<?php include './footer.php'; ?>