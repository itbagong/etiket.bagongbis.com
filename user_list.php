<?php
// user_list.php (FINAL COPY LENGKAP DENGAN AKTIVASI & NONAKTIVASI VIA MODAL)

ob_start();

require_once './src/Database.php';
// require_once './src/Auth.php'; // Uncomment jika Auth digunakan
require_once './src/user.php';
require_once './src/security.php'; // WAJIB: Memuat fungsi enkripsi
require_once './src/role.php';

include './header.php';

$msg = '';
$err = '';

// =========================================================================
// 1. TANGANI REQUEST SOFT DELETE (NONAKTIFKAN)
// =========================================================================

if (isset($_GET['delete_id'])) {
 // $auth->authorize('user_list.php', 'delete'); // Uncomment jika Auth digunakan

 $encrypted_id = $_GET['delete_id'];
 $delete_id = decrypt_id($encrypted_id); // DEKRIPSI ID

 if ($delete_id <= 0) {
  $err = "ID pengguna tidak valid atau gagal didekripsi.";
 } else {
  try {
   // ... (Logika pengecekan user login vs user yang akan dihapus) ...
  
   $user_to_delete = User::find($delete_id);
  
   if ($user_to_delete) {
    $user_name = $user_to_delete->name;
    // Panggil delete() yang memanggil updateStatus(1)
    $user_to_delete->delete();
    $msg = "Pengguna **" . htmlspecialchars($user_name) . "** berhasil **dinonaktifkan**.";
   } else {
    $err = "Pengguna dengan ID {$delete_id} tidak ditemukan.";
   }
  } catch (Exception $e) {
   $err = "Gagal menonaktifkan pengguna: " . $e->getMessage();
  }
 }

 header('Location: user_list.php?msg=' . urlencode($msg) . '&err=' . urlencode($err));
 exit();
}

// =========================================================================
// 2. TANGANI REQUEST AKTIVASI
// =========================================================================
if (isset($_GET['activate_id'])) {
 $encrypted_id = $_GET['activate_id'];
 $activate_id = decrypt_id($encrypted_id); // DEKRIPSI ID

 if ($activate_id <= 0) {
  $err = "ID pengguna tidak valid atau gagal didekripsi.";
 } else {
  try {
   $user_to_activate = User::find($activate_id);
   if ($user_to_activate) {
    $user_name = $user_to_activate->name;
    // Panggil activate() yang memanggil updateStatus(0)
    $user_to_activate->activate();
    $msg = "Pengguna **" . htmlspecialchars($user_name) . "** berhasil **diaktifkan**.";
   } else {
    $err = "Pengguna dengan ID {$activate_id} tidak ditemukan.";
   }
  } catch (Exception $e) {
   $err = "Gagal mengaktifkan pengguna: " . $e->getMessage();
  }
 }

 header('Location: user_list.php?msg=' . urlencode($msg) . '&err=' . urlencode($err));
 exit();
}
// =========================================================================

$display_msg = $_GET['msg'] ?? '';
$display_err = $_GET['err'] ?? '';


// $auth->authorize('user_list.php', 'view'); // Uncomment jika Auth digunakan

$users = User::findAll();
$logged_in_user_id = 0; // Ganti dengan ID user yang sedang login
?>
<div id="content-wrapper">

 <div class="container-fluid">

  <ol class="breadcrumb">
   <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
   <li class="breadcrumb-item active">Daftar Pengguna</li>
  </ol>
 
  <div class="d-flex justify-content-between align-items-center mb-4">
   <h3 class="mb-0">👥 Daftar Pengguna Sistem</h3>
  
   <?php
   // if (isset($auth) && $auth->can('user_form.php', 'add')): // Uncomment jika Auth digunakan
   ?>
   <a href="user_form.php" class="btn btn-primary btn-sm">
    ➕ Buat Pengguna Baru
   </a>
   <?php // endif; ?>
  </div>

  <?php if(strlen($display_err) > 1) :?>
   <div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($display_err);?></div>
  <?php endif?>
  <?php if(strlen($display_msg) > 1) :?>
   <div class="alert alert-success text-center my-3" role="alert"> <strong>Sukses! </strong> <?php echo htmlspecialchars($display_msg);?></div>
  <?php endif?>

  <div class="card mb-3 shadow">
   <div class="card-body">
    <div class="table-responsive">

     <table id="dataTable" class="table table-striped table-bordered table-sm" style="width:100%">
      <thead class="table-light">
       <tr>
        <th>Name</th>
        <th>Role</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Status</th>        <th>Created at</th>
        <th>Action</th>
       </tr>
      </thead>
      <tbody>
       <?php foreach($users as $user_item):
       // ENKRIPSI ID SEBELUM DITAMPILKAN KE URL
       $encrypted_id = encrypt_id($user_item->id);
       ?>
       <tr>
        <td><?php echo htmlspecialchars($user_item->name) ?></td>
        <td><?php echo htmlspecialchars($user_item->role_name) ?></td>
        <td><?php echo htmlspecialchars($user_item->email) ?></td>
        <td><?php echo htmlspecialchars($user_item->phone) ?></td>
       
                <td>
                  <?php if ($user_item->status == 0): ?>
                    <span class="badge bg-success text-white">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-danger text-white">Tidak Aktif</span>
                  <?php endif; ?>
                </td>
                        <?php $date = new DateTime($user_item->created_at) ?>
        <td><?php echo $date->format('d-m-Y H:i:s') ?></td>
       
        <td>
         <?php
         // if(isset($auth) && $auth->can('user_form.php', 'edit')):
         ?>
         <a href="user_form.php?id=<?php echo $encrypted_id; ?>" class="btn btn-warning btn-sm me-1" title="Edit">
          <i class="fas fa-edit"></i>
         </a>
         <?php // endif; ?>
        
         <?php // if(isset($auth) && $auth->can('user_list.php', 'delete')): ?>
                 
                  <?php if ($user_item->status == 0): ?>
                    <button
                      type="button"
                      class="btn btn-danger btn-sm"
                      onclick="showDeleteModal('<?php echo $encrypted_id; ?>', '<?php echo htmlspecialchars(addslashes($user_item->name)); ?>')"
                      title="Nonaktifkan Pengguna"
                      <?php echo ($logged_in_user_id == $user_item->id) ? 'disabled' : ''; ?>
                    >
                      <i class="fas fa-user-times"></i> Nonaktifkan
                    </button>
                  <?php else: ?>
                    <button
                      type="button"
                      class="btn btn-success btn-sm"
                      onclick="showActivateModal('<?php echo $encrypted_id; ?>', '<?php echo htmlspecialchars(addslashes($user_item->name)); ?>')"
                      title="Aktifkan Pengguna"
                    >
                      <i class="fas fa-user-check"></i> Aktifkan
                    </button>
                  <?php endif; ?>

         <?php // endif; ?>
        </td>
       </tr>
       <?php endforeach ?>
      </tbody>
     </table>
    </div>
   
   </div>
  </div>

 </div>
 <?php include './footer.php'; ?>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
 <div class="modal-dialog" role="document">
  <div class="modal-content">
   <div class="modal-header bg-danger text-white">
    <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Menonaktifkan Pengguna</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
     <span aria-hidden="true">&times;</span>
    </button>
   </div>
   <div class="modal-body">
    Apakah Anda yakin ingin **menonaktifkan** pengguna: <strong id="userNameToDelete"></strong>? (Status akan diubah menjadi **Tidak Aktif**)
   </div>
   <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
    <a id="confirmDeleteButton" href="#" class="btn btn-danger">Ya, Nonaktifkan</a>
   </div>
  </div>
 </div>
</div>

<div class="modal fade" id="activateModal" tabindex="-1" role="dialog" aria-labelledby="activateModalLabel" aria-hidden="true">
 <div class="modal-dialog" role="document">
  <div class="modal-content">
   <div class="modal-header bg-success text-white">
    <h5 class="modal-title" id="activateModalLabel"><i class="fas fa-check-circle me-2"></i> Konfirmasi Mengaktifkan Pengguna</h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
     <span aria-hidden="true">&times;</span>
    </button>
   </div>
   <div class="modal-body">
    Apakah Anda yakin ingin **mengaktifkan** pengguna: <strong id="userNameToActivate"></strong>? (Status akan diubah menjadi **Aktif**)
   </div>
   <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
    <a id="confirmActivateButton" href="#" class="btn btn-success">Ya, Aktifkan</a>
   </div>
  </div>
 </div>
</div>


<script>
// Fungsi untuk menampilkan modal Nonaktifkan/Soft Delete
function showDeleteModal(encryptedId, userName) {
 $('#userNameToDelete').text(userName);
 // Action diarahkan ke 'delete_id' (untuk Nonaktifkan)
 $('#confirmDeleteButton').attr('href', 'user_list.php?delete_id=' + encryptedId);
 $('#deleteModal').modal('show');
}

// FUNGSI BARU untuk menampilkan modal Aktivasi
function showActivateModal(encryptedId, userName) {
 $('#userNameToActivate').text(userName);
 // Action diarahkan ke 'activate_id'
 $('#confirmActivateButton').attr('href', 'user_list.php?activate_id=' + encryptedId);
 $('#activateModal').modal('show');
}

$(document).ready(function() {
 $('#dataTable').DataTable();
});
</script>
<?php ob_end_flush(); ?>