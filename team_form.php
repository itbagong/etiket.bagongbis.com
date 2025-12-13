<?php
// team_form.php - Gabungan Create/Update Team + Member Management
ob_start();

require_once './src/team.php';
require_once './src/Database.php';
require_once './src/user.php'; 
require_once './src/team-member.php'; 
require_once './src/security.php'; // Digunakan untuk enkripsi/dekripsi ID

$err = '';
$msg = '';

// =========================================================================
// DEKRIPSI ID & PENGATURAN MODE
// =========================================================================
$encrypted_id = $_GET['id'] ?? '';
$team_id = decrypt_id($encrypted_id);

$is_editing = $team_id !== false && $team_id > 0;

// =========================================================================
// RBAC CHECK
// =========================================================================
$current_page = 'team_list.php'; 
$required_permission = $is_editing ? 'edit' : 'add';

include './header.php'; 

// Pengecekan otorisasi untuk mengakses form ini
try {
    $auth->authorize($current_page, $required_permission);
    $can_save = true; 
} catch (Exception $e) {
    $can_save = false;
}


// --- 1. INISIALISASI DATA TEAM ---
$team = new Team();
$title = "Tambah Tim Baru";
$card_class = "bg-primary text-white";
$team_name = '';

if ($is_editing) {
    // Memuat tim, hanya yang berstatus aktif (status=0)
    $team = Team::find($team_id);
    if (!$team) {
        ob_clean();
        header('Location: team_list.php?err=' . urlencode('Tim tidak ditemukan atau sudah diarsipkan.'));
        exit();
    }
    $title = "Edit Tim: " . htmlspecialchars($team->name ?? '');
    $card_class = "bg-warning text-dark";
    $team_name = $team->name;
}

$team_name = $_POST['name'] ?? $team_name;
$selected_members_post = $_POST['members'] ?? [];

// --- 2. AMBIL DATA USER & KEANGGOTAAN SAAT INI ---
// Asumsi User::findAll() hanya mengambil user aktif jika ada kolom status/active di user
$all_users = User::findAll();
$current_member_ids = [];

if ($is_editing) {
    $members = TeamMember::findMembersByTeamId($team_id);
    foreach($members as $member) {
        $current_member_ids[] = $member->user; 
    }
}

// =========================================================================
// 3. TANGANI SUBMIT (SIMPAN TIM + ANGGOTA)
// =========================================================================
if(isset($_POST['submit'])) {
    
    if (!$can_save) {
        $err = "Akses Ditolak: Anda tidak memiliki izin untuk menyimpan perubahan ini.";
    }

    $team_name_input = trim($_POST['name']);
    $members_to_save = $_POST['members'] ?? [];
    
    if(strlen($team_name_input) < 3 ){
        $err = "Nama tim minimal harus 3 karakter.";
    } 
    
    if (empty($err)) {
        global $db;
        $db->begin_transaction();
        
        try {
            // A. Simpan/Update Data Tim
            $team->name = $team_name_input;
            // PENTING: Set status=0 (Aktif) sesuai logika 0=Aktif
            $team->status = 0; 
            $final_team_id = 0;

            if ($is_editing) {
                $team->id = $team_id; 
                $team->update(); 
                $final_team_id = $team_id;
                $final_msg = "Tim **" . htmlspecialchars($team_name_input) . "** berhasil diperbarui!";
            } else {
                $team->save(); 
                $final_team_id = $db->insert_id; 
                $final_msg = "Tim **" . htmlspecialchars($team_name_input) . "** berhasil ditambahkan!";
            }
            
            if ($final_team_id <= 0) {
                throw new Exception("ID Tim tidak terdeteksi (0) setelah penyimpanan. Rollback dilakukan.");
            }
            
            // B. Simpan/Update Data Anggota Tim
            
            // 1. Hapus semua anggota lama dari tim ini
            TeamMember::deleteByTeamId($final_team_id); 
            
            // 2. Tambahkan anggota yang dipilih
            foreach ($members_to_save as $user_id_to_add) {
                $member = new TeamMember([
                    'team' => $final_team_id, 
                    'user' => (int)$user_id_to_add 
                ]);
                $member->save(); 
            }
            
            $db->commit();
            
            ob_clean();
            header('Location: team_list.php?msg=' . urlencode($final_msg));
            exit();

        } catch (Exception $e) {
           $db->rollback();
           $err = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

// Mempertahankan input form jika terjadi error
$current_member_ids = isset($_POST['submit']) && !empty($err) ? $selected_members_post : $current_member_ids;
?>

<div id="content-wrapper">
    <div class="container-fluid">

        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="./team_list.php">Daftar Tim</a></li>
            <li class="breadcrumb-item active"><?php echo $title; ?></li>
        </ol>

        <div class="card mb-3 shadow">
            <div class="card-header <?php echo $card_class; ?>">
                <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i> <?php echo $title; ?></h5>
            </div>
            <div class="card-body">
                
                <?php if(strlen($err) > 1) :?>
                <div class="alert alert-danger text-center my-3" role="alert"> <strong>Gagal! </strong> <?php echo htmlspecialchars($err);?></div>
                <?php endif?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . ($is_editing ? '?id=' . encrypt_id($team_id) : ''); ?>">
                    <div class="row justify-content-center">
                        <div class="col-lg-8 col-md-10">
                            
                            <div class="mb-3 row">
                                <label for="name" class="col-sm-4 col-form-label">Nama Tim</label>
                                <div class="col-sm-8">
                                    <input type="text" 
                                            name="name" 
                                            id="name" 
                                            class="form-control" 
                                            value="<?php echo htmlspecialchars($team_name ?? ''); ?>" 
                                            required 
                                            placeholder="Contoh: Tim Jaringan" 
                                            <?php echo $can_save ? '' : 'disabled'; ?>>
                                </div>
                            </div>
                            
                            <div class="mb-3 row pt-3 border-top">
                                <label class="col-sm-4 col-form-label pt-0">Pilih Anggota Tim</label>
                                <div class="col-sm-8">
                                    <div class="list-group" style="max-height: 250px; overflow-y: auto;">
                                        <?php if (empty($all_users)): ?>
                                            <div class="list-group-item text-muted">Tidak ada pengguna yang terdaftar.</div>
                                        <?php else: ?>
                                            <?php foreach ($all_users as $user): ?>
                                                <?php 
                                                    $is_checked = in_array($user->id, $current_member_ids);
                                                ?>
                                                <label class="list-group-item d-flex align-items-center">
                                                    <input class="form-check-input me-3" 
                                                            type="checkbox" 
                                                            name="members[]" 
                                                            value="<?php echo htmlspecialchars($user->id); ?>" 
                                                            <?php echo $is_checked ? 'checked' : ''; ?>
                                                            <?php echo $can_save ? '' : 'disabled'; ?>>
                                                    <?php echo htmlspecialchars($user->name); ?> 
                                                    <small class="text-muted ms-auto">(ID: <?php echo htmlspecialchars($user->id); ?>)</small>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted d-block mt-2">Checklist pengguna yang akan menjadi anggota tim ini.</small>
                                </div>
                            </div>

                            <?php if (!$can_save): ?>
                                <div class="alert alert-warning text-center mt-3" role="alert">
                                    Anda hanya memiliki izin **Lihat**. Form tidak dapat diubah.
                                </div>
                            <?php endif; ?>

                            <div class="text-center mt-4 pt-3 border-top">
                                <?php if ($can_save): ?>
                                <button type="submit" name="submit" class="btn btn-lg btn-<?php echo $is_editing ? 'warning' : 'primary'; ?> text-dark">
                                    <i class="fas fa-save me-1"></i> Simpan Tim & Anggota
                                </button>
                                <?php endif; ?>
                                <a href="team_list.php" class="btn btn-lg btn-secondary ms-2">Batal</a>
                            </div>

                        </div>
                    </div>
                </form>
            </div>
        </div>
        </div>
    <?php include './footer.php'; ?>

</div>