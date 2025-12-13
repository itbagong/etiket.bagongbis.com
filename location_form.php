<?php
// location_form.php - REVISI DENGAN RBAC DAN URL DEKRIPSI

ob_start();
include './header.php';
require_once './src/location.php';
require_once './src/security.php'; // WAJIB: Memuat fungsi enkripsi/dekripsi

$err = '';
$msg = '';

$encrypted_id = $_GET['id'] ?? '';
$location_id = 0;

if (!empty($encrypted_id)) {
    // 1. DEKRIPSI ID dari URL
    $location_id = decrypt_id($encrypted_id);
}

$is_editing = $location_id > 0;

// =========================================================================
// 🎯 INTEGRASI RBAC & DEKRIPSI ID
// =========================================================================
if ($is_editing) {
    // Mode EDIT: Cek Izin 'edit'
    $auth->authorize('location_form.php', 'edit'); 
    
    // Cari Lokasi
    if ($location_id > 0) {
        $location = Location::find($location_id);
    } else {
        $location = false;
    }

    if (!$location) {
        // ID ditemukan di URL tapi dekripsi gagal atau data tidak ada
        header('Location: location_list.php?err=' . urlencode('Lokasi tidak ditemukan atau ID tidak valid.'));
        exit();
    }
    $title = "Edit Lokasi: " . htmlspecialchars($location->location_name);
    // Link action form harus menyertakan ID terenkripsi agar tombol Save bekerja
    $form_action_url = htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . urlencode($encrypted_id); 
    
} else {
    // Mode TAMBAH BARU: Cek Izin 'add'
    $auth->authorize('location_form.php', 'add');
    
    $location = new Location();
    $title = "Tambah Lokasi Baru";
    $form_action_url = htmlspecialchars($_SERVER['PHP_SELF']);
}
// =========================================================================

// Ambil nilai dari objek/form
$location_name = $_POST['location_name'] ?? $location->location_name;
$location_address = $_POST['location_address'] ?? $location->location_address;
$location_status = $_POST['location_status'] ?? $location->location_status;


if(isset($_POST['submit'])) {

    $location_name = trim($_POST['location_name']);
    $location_address = trim($_POST['location_address']);
    $location_status = (int)$_POST['location_status'];
    
    // --- VALIDASI ---
    if(strlen($location_name) < 1 ){
        $err = "Nama lokasi tidak boleh kosong.";
    } else if(strlen($location_address) < 1 ){
        $err = "Alamat lokasi tidak boleh kosong.";
    } else if(!in_array($location_status, [0, 1])){
        $err = "Status lokasi tidak valid.";
    } 
    // ----------------
    
    else {
        try {
            // Isi properti objek dengan data dari form
            // Jika dalam mode EDIT, $location sudah memiliki ID asli setelah find()
            $location->location_name = $location_name;
            $location->location_address = $location_address;
            $location->location_status = $location_status;

            if ($is_editing) {
                // UPDATE (ID sudah terisi di $location->location_id)
                $location->update();
                $final_msg = "Lokasi **" . htmlspecialchars($location_name) . "** berhasil diperbarui!";
            } else {
                // CREATE
                $location->save();
                $final_msg = "Lokasi **" . htmlspecialchars($location_name) . "** berhasil ditambahkan!";
            }
            
            // Redirect ke daftar lokasi dengan pesan sukses
            header('Location: location_list.php?msg=' . urlencode($final_msg));
            exit();

        } catch (Exception $e) {
           $err = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

?>

<div id="content-wrapper">

    <div class="container-fluid">

        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="./location_list.php">Lokasi</a></li>
            <li class="breadcrumb-item active"><?php echo $title; ?></li>
        </ol>

        <div class="card mb-3 shadow">
            <div class="card-header bg-<?php echo $is_editing ? 'warning' : 'primary'; ?> text-<?php echo $is_editing ? 'dark' : 'white'; ?>">
                <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i> <?php echo $title; ?></h5>
            </div>
            <div class="card-body">
                
                <?php if(strlen($err) > 1) :?>
                <div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($err);?></div>
                <?php endif?>

                <form method="POST" action="<?php echo $form_action_url; ?>">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            
                            <div class="mb-3 row">
                                <label for="location_name" class="col-sm-4 col-form-label">Nama Lokasi</label>
                                <div class="col-sm-8">
                                    <input type="text" name="location_name" class="form-control" value="<?php echo htmlspecialchars($location_name); ?>" required placeholder="Contoh: Gedung Utama, Kantor Pusat">
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="location_address" class="col-sm-4 col-form-label">Alamat</label>
                                <div class="col-sm-8">
                                    <textarea name="location_address" class="form-control" rows="3" required placeholder="Alamat detail lokasi"><?php echo htmlspecialchars($location_address); ?></textarea>
                                </div>
                            </div>

                            <div class="mb-3 row">
                                <label for="location_status" class="col-sm-4 col-form-label">Status</label>
                                <div class="col-sm-8">
                                    <select name="location_status" id="location_status" class="form-control" required>
                                        <option value="0" <?php echo ($location_status == 0) ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="1" <?php echo ($location_status == 1) ? 'selected' : ''; ?>>Tidak Aktif</option>
                                    </select>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" name="submit" class="btn btn-lg btn-<?php echo $is_editing ? 'warning' : 'primary'; ?> text-dark">
                                    <i class="fas fa-save me-1"></i> Simpan Data
                                </button>
                                <a href="location_list.php" class="btn btn-lg btn-secondary">Batal</a>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </div>

<?php include './footer.php'; ?>