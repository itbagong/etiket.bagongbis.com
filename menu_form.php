<?php
// menu_form.php - Form Tambah/Edit Menu (FINAL FIX: Mengatasi Headers Already Sent)

require_once './src/menu.php';
require_once './src/Database.php';
require_once './src/Auth.php';
require_once './src/security.php';

// --- LANGKAH 1: MEMULAI SESI, DATABASE, DAN OTENTIKASI ---
// CATATAN: session_start() kini ditangani di header.php untuk mencegah duplicate calls,
// tetapi Anda harus memastikan header.php menggunakan pengecekan "if (session_status() == PHP_SESSION_NONE)".

if (session_status() == PHP_SESSION_NONE) {
    // Sebagai fallback keamanan, inisialisasi sesi di sini jika belum ada
    session_start();
}


$db = Database::getInstance();
$user = $_SESSION['user'] ?? null;
// Pastikan role_id aman dengan default 0 jika $user tidak ada
$auth = new Auth($user->role_id ?? 0, $db); 
$rbac_target = 'menu.php';

// --- INISIALISASI VARIABEL DAN RBAC ---

$err = '';
$menu_name = '';
$menu_url = '';
$menu_icon = '';
$menu_order = 0;
$status = 0;
$parent_id = 0;
$form_title = 'Tambah Menu Baru'; // Default title

// Load data menu yang ada untuk dropdown Parent (aktif dan nonaktif)
$all_menus = Menu::findAll(true); 

$encrypted_id = $_GET['id'] ?? '';
$menu_id = 0;
if (!empty($encrypted_id)) {
    // Diasumsikan decrypt_id() ada di Security.php
    $menu_id = decrypt_id($encrypted_id); 
}

$is_editing = $menu_id > 0;
$required_permission = $is_editing ? 'edit' : 'add';

// --- 2. TANGANI SUBMIT (POST ke diri sendiri) ---
if (isset($_POST['submit_menu'])) {
    
    // Fallback untuk mengisi form jika gagal (diambil SEBELUM validasi)
    $menu_name = htmlspecialchars($_POST['menu_name'] ?? '');
    $menu_url = htmlspecialchars($_POST['menu_url'] ?? '');
    $menu_icon = htmlspecialchars($_POST['menu_icon'] ?? '');
    $menu_order = htmlspecialchars($_POST['menu_order'] ?? 0);
    $status = htmlspecialchars($_POST['status'] ?? 0);
    $parent_id = htmlspecialchars($_POST['parent_id'] ?? 0);
    
    try {
        
        // Cek otorisasi di awal untuk menghindari pemrosesan data yang tidak perlu
        if (!$auth->can($rbac_target, $required_permission)) {
            throw new Exception("Anda tidak memiliki hak akses untuk operasi ini.");
        }
        
        // Logika POST di sini...
        if ($is_editing) {
            $menu = Menu::find($menu_id, true);
            if (!$menu) throw new Exception("Menu ID tidak ditemukan.");
        } else {
            $menu = new Menu();
        }

        $menu->menu_id = $menu_id;
        $menu->menu_name = trim($_POST['menu_name']);
        $menu->menu_url = trim($_POST['menu_url']);
        $menu->menu_icon = trim($_POST['menu_icon']);
        $menu->menu_order = (int)$_POST['menu_order'];
        $menu->status = (int)$_POST['status'];
        $parent_id_post = (int)$_POST['parent_id'];
        $menu->parent_id = ($parent_id_post > 0) ? $parent_id_post : null;
        
        // --- Validasi Data ---
        if (strlen($menu->menu_name) < 3) {
            throw new Exception("Nama Menu harus memiliki minimal 3 karakter.");
        }
        
        // Validasi URL, mengizinkan '#' atau URL yang panjangnya < 3 jika itu adalah menu induk
        $url_len = strlen($menu->menu_url);
        if ($url_len > 0 && $url_len < 3 && $menu->menu_url !== '#') {
             throw new Exception("URL File harus memiliki minimal 3 karakter atau menggunakan '#'.");
        }
        
        if ($is_editing && $menu->parent_id === $menu_id) {
            throw new Exception("Menu tidak bisa menjadi induk dari dirinya sendiri.");
        }

        // --- Penyimpanan Data ---
        if ($is_editing) {
            $menu->update();
            $message = "Menu '{$menu->menu_name}' berhasil diperbarui.";
        } else {
            $menu->save();
            $message = "Menu '{$menu->menu_name}' berhasil ditambahkan. Jangan lupa atur Aksesnya!";
        }
        
        // !!! KODE REDIRECT HARUS DI SINI (Sebelum Output/Include Header) !!!
        header("Location: ./menu.php?msg=" . urlencode($message));
        exit();

    } catch (Exception $e) {
        $err = "Gagal memproses menu: " . $e->getMessage();
        // Fallback pengisian form sudah dilakukan di awal blok POST
    }
}
// ----------------------------------------------------

// --- 3. KONTROL AKSES TAMPILAN (GET) ---
// Jika request BUKAN POST (atau POST gagal), kita cek akses dan tampilkan header.
$auth->authorize($rbac_target, $required_permission);


// --- 4. LOAD DATA UNTUK TAMPILAN (GET) ---
if ($is_editing && !isset($_POST['submit_menu'])) {
    $menu = Menu::find($menu_id, true);
    if ($menu) {
        $form_title = 'Edit Menu: ' . htmlspecialchars($menu->menu_name);
        $menu_name = htmlspecialchars($menu->menu_name);
        $menu_url = htmlspecialchars($menu->menu_url);
        $menu_icon = htmlspecialchars($menu->menu_icon);
        $menu_order = htmlspecialchars($menu->menu_order);
        $status = htmlspecialchars($menu->status);
        $parent_id = $menu->parent_id ?? 0;
    } else {
        // Redirect jika ID tidak ditemukan, dan ini HARUS di atas include header
        header("Location: ./menu.php?err=" . urlencode('Menu ID tidak ditemukan.'));
        exit();
    }
}


// --- 5. INCLUDE HEADER (Output dimulai di sini) ---
// !!! PENTING: Pindahkan include ini ke sini untuk FIX Warning "Cannot modify header information" !!!
include './header.php';
?>

<div id="content-wrapper">
    <div class="container-fluid">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="./menu.php">Menu Management</a></li>
            <li class="breadcrumb-item active"><?php echo $form_title; ?></li>
        </ol>
        
        <?php if(strlen($err) > 1) :?><div class="alert alert-danger my-3" role="alert"><strong>Gagal!</strong> <?php echo htmlspecialchars($err);?></div><?php endif?>

        <div class="card mb-3 shadow">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-fw fa-list"></i> <?php echo $form_title; ?>
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . ($is_editing ? '?id=' . $encrypted_id : ''); ?>" method="POST">

                    <div class="mb-3">
                        <label for="menu_name" class="form-label">Nama Menu</label>
                        <input type="text" class="form-control" id="menu_name" name="menu_name" value="<?php echo $menu_name; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Menu Induk (Parent)</label>
                        <select name="parent_id" id="parent_id" class="form-control">
                            <option value="0" <?php echo ((int)$parent_id === 0) ? 'selected' : ''; ?>>-- Tidak Ada (Menu Utama) --</option>
                            <?php foreach ($all_menus as $menu_item):
                                // Jangan tampilkan menu ini sendiri sebagai parent saat editing
                                if ($is_editing && $menu_item->menu_id == $menu_id) continue;
                                
                                // Indikator jika menu tersebut adalah sub-menu
                                $indicator = $menu_item->parent_id ? ' -- ' : '';
                            ?>
                            <option value="<?php echo $menu_item->menu_id; ?>"
                                <?php echo ((int)$parent_id === $menu_item->menu_id) ? 'selected' : ''; ?>>
                                <?php echo $indicator . htmlspecialchars($menu_item->menu_name); ?> (<?php echo $menu_item->menu_order; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Pilih menu lain jika menu ini adalah sub-menu.</small>
                    </div>

                    <div class="mb-3">
                        <label for="menu_url" class="form-label">URL File</label>
                        <input type="text" class="form-control" id="menu_url" name="menu_url" value="<?php echo $menu_url; ?>" placeholder="Contoh: users.php atau #" required>
                        <small class="form-text text-muted">Untuk menu grup (parent), gunakan **#** atau URL unik (misalnya 'master\_data\_group').</small>
                    </div>

                    <div class="mb-3">
                        <label for="menu_icon" class="form-label">Ikon (Font Awesome Class)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="<?php echo $menu_icon; ?>"></i></span>
                            <input type="text" class="form-control" id="menu_icon" name="menu_icon" value="<?php echo $menu_icon; ?>" placeholder="Contoh: fas fa-users">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="menu_order" class="form-label">Urutan Menu</label>
                        <input type="number" class="form-control" id="menu_order" name="menu_order" value="<?php echo $menu_order; ?>" required>
                        <small class="form-text text-muted">Digunakan untuk mengurutkan menu dalam satu level.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="0" <?php echo ((int)$status === 0) ? 'selected' : ''; ?>>Aktif</option>
                            <option value="1" <?php echo ((int)$status === 1) ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>

                    <button type="submit" name="submit_menu" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Menu</button>
                    <a href="./menu.php" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
include './footer.php';
?>