<?php
session_start();
// Pastikan path ini sesuai dengan struktur folder kamu
require_once 'config/database.php';
require_once 'config/functions.php';

// CEK LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// AMBIL DATA USER
$stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$currentUser = $stmt_user->fetch();
$_SESSION['name'] = $currentUser['full_name'];

// CEK STATUS KULI
$is_laborer = false;
$laborer_data = null;
$stmt_check_kuli = $conn->prepare("SELECT * FROM laborers WHERE user_id = ?");
$stmt_check_kuli->execute([$user_id]);
if ($stmt_check_kuli->rowCount() > 0) {
    $is_laborer = true;
    $laborer_data = $stmt_check_kuli->fetch();
}

// --- LOGIKA GLOBAL: CEK BOOKING ---
$stmt_global_booked = $conn->prepare("SELECT land_id FROM bookings WHERE status IN ('pending', 'verified')");
$stmt_global_booked->execute();
$booked_lands_raw = $stmt_global_booked->fetchAll(PDO::FETCH_COLUMN);

// --- CEK BOOKING SAYA ---
$stmt_my_bookings = $conn->prepare("SELECT land_id, status FROM bookings WHERE buyer_id = ?");
$stmt_my_bookings->execute([$user_id]);
$my_bookings_raw = $stmt_my_bookings->fetchAll(PDO::FETCH_ASSOC);
$my_access = [];
foreach($my_bookings_raw as $row) { $my_access[$row['land_id']] = $row['status']; }

// ==========================================
// LOGIC ACTION HANDLERS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    $action = $_POST['action_type'];
    
    // 1. JUAL TANAH (UPDATED FIX HARGA)
    if ($action == 'jual_tanah') {
        $title = cleanInput($_POST['title']); 
        $desc = cleanInput($_POST['description']);
        
        // GABUNGKAN ANGKA LUAS + SATUAN
        $area_val = cleanInput($_POST['area_input']);
        $area_unit = cleanInput($_POST['area_unit']);
        $area = $area_val . ' ' . $area_unit;

        // --- BAGIAN PENTING: PEMBERSIHAN HARGA ---
        // Input: "Rp 1.500.000" -> Menjadi: "1500000" (Hanya ambil angka)
        $price_raw = $_POST['price_display'];
        $price = preg_replace('/[^0-9]/', '', $price_raw); 

        // AMBIL LOKASI DARI HIDDEN INPUT
        $location = cleanInput($_POST['location']); 
        
        $phone = cleanInput($_POST['phone_number']);
        $uploadDir = 'assets/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $img1 = uploadImage($_FILES['image1'], $uploadDir);
        $img2 = uploadImage($_FILES['image2'], $uploadDir);
        
        if ($img1 && $img2) {
            $stmt = $conn->prepare("INSERT INTO lands (user_id, title, description, area_size, price, location, phone_number, image1, image2) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $desc, $area, $price, $location, $phone, $img1, $img2]);
            echo "<script>alert('Tanah berhasil diiklankan!'); window.location.href='dashboard.php';</script>"; exit;
        }
    }
    // 2. BOOKING TANAH
    elseif ($action == 'booking_tanah') {
        $land_id = $_POST['land_id']; $amount = 5000000;
        $cek_status = $conn->prepare("SELECT id FROM bookings WHERE land_id = ? AND status IN ('pending', 'verified')");
        $cek_status->execute([$land_id]);
        if ($cek_status->rowCount() > 0) {
            echo "<script>alert('MAAF! Tanah ini baru saja di-booking oleh orang lain.'); window.location.href='dashboard.php';</script>"; exit;
        }
        $proof = uploadImage($_FILES['payment_proof'], 'assets/uploads/');
        if ($proof) {
            $stmt = $conn->prepare("INSERT INTO bookings (land_id, buyer_id, amount, proof_image, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$land_id, $user_id, $amount, $proof]);
            echo "<script>alert('Booking Berhasil Dikirim!'); window.location.href='dashboard.php';</script>"; exit;
        }
    }
    // 3. DAFTAR KULI (DENGAN API LOKASI)
    elseif ($action == 'daftar_kuli') {
        $age = cleanInput($_POST['age']); 
        // AMBIL DOMISILI DARI HIDDEN INPUT
        $domicile = cleanInput($_POST['domicile_location']); 
        $skills = cleanInput($_POST['skills']); 
        $out = cleanInput($_POST['ready_out_of_town']);
        $exp = cleanInput($_POST['experience_year']);
        
        if ($age < 18) { $message = "Umur minimal 18 tahun."; $message_type = "error"; }
        else {
            $foto = uploadImage($_FILES['profile_image'], 'assets/uploads/');
            if ($foto) {
                $stmt = $conn->prepare("INSERT INTO laborers (user_id, age, domicile, skills, ready_out_of_town, experience_year, profile_image, payment_status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 'unpaid', 0)");
                $stmt->execute([$user_id, $age, $domicile, $skills, $out, $exp, $foto]);
                echo "<script>alert('Pendaftaran Berhasil! Bayar membership agar aktif.'); window.location.href='dashboard.php';</script>"; exit;
            }
        }
    }
    // 4. BATAL KULI
    elseif ($action == 'batalkan_kuli') {
        $conn->prepare("DELETE FROM laborers WHERE user_id = ?")->execute([$user_id]);
        echo "<script>alert('Berhenti jadi kuli berhasil.'); window.location.href='dashboard.php';</script>"; exit;
    }
    // 5. BAYAR MEMBERSHIP
    elseif ($action == 'bayar_membership') {
        $proof = uploadImage($_FILES['payment_proof'], 'assets/uploads/');
        if ($proof) {
            $conn->prepare("UPDATE laborers SET payment_proof = ?, payment_status = 'pending' WHERE user_id = ?")->execute([$proof, $user_id]);
            echo "<script>alert('Bukti bayar dikirim.'); window.location.href='dashboard.php';</script>"; exit;
        }
    }
    // 6. EDIT PROFIL
    elseif ($action == 'edit_profil') {
        $fullName = cleanInput($_POST['full_name']); $phone = cleanInput($_POST['phone']); $pass = $_POST['password'];
        $sql = "UPDATE users SET full_name = ?, phone = ? WHERE id = ?"; $params = [$fullName, $phone, $user_id];
        if (!empty($pass)) { $hash = password_hash($pass, PASSWORD_DEFAULT); $sql = "UPDATE users SET full_name = ?, phone = ?, password = ? WHERE id = ?"; $params = [$fullName, $phone, $hash, $user_id]; }
        $stmt = $conn->prepare($sql); $stmt->execute($params);
        if (!empty($_FILES['profile_image']['name'])) {
            $foto = uploadImage($_FILES['profile_image'], 'assets/uploads/');
            if($foto) $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$foto, $user_id]);
        }
        echo "<script>alert('Profil diupdate.'); window.location.href='dashboard.php';</script>"; exit;
    }
}

// QUERY DATA UTAMA
$stmt_lands = $conn->prepare("SELECT * FROM lands ORDER BY created_at DESC");
$stmt_lands->execute();
$lands = $stmt_lands->fetchAll();

$stmt_laborers = $conn->prepare("SELECT laborers.*, users.full_name, users.phone FROM laborers JOIN users ON laborers.user_id = users.id WHERE laborers.is_active = 1 ORDER BY laborers.created_at DESC");
$stmt_laborers->execute();
$laborers = $stmt_laborers->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Make a LARGE</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .slider-wrapper { position: relative; width: 100%; height: 100%; }
        .slide { position: absolute; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.5s; top:0; left:0; }
        .slide.active { opacity: 1; z-index: 1; }
        .slider-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.5); color: white; border: none; padding: 5px 10px; cursor: pointer; z-index: 10; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; }
        .prev { left: 10px; } .next { right: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: bold; }
        .status-active { background: #e8f5e9; color: #2e7d32; }
        .status-pending { background: #fff8e1; color: #ff8f00; }
        .status-unpaid { background: #ffebee; color: #c62828; }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
        .btn-locked { background: #999; cursor: not-allowed; opacity: 0.8; display: flex; align-items: center; justify-content: center; gap: 5px; color: white; }
        .btn-pending { background: #ff9800 !important; color: white; cursor: default; }
        .date-info { font-size: 0.75rem; color: #666; margin-top: 5px; display: block; }
        
        /* CSS Khusus untuk Input Group */
        .input-group { display: flex; gap: 10px; }
        .input-group input { flex: 2; }
        .input-group select { flex: 1; }
    </style>
</head>
<body style="background-color: #FAF9F6;">

    <nav class="dashboard-nav">
        <div class="container dashboard-header">
            <div class="logo" style="color: white;">
                <i class="fas fa-mountain"></i> Make a <span style="color: var(--light-brown);">LARGE</span>
            </div>
            <div class="user-info">
                <div style="text-align: right;">
                    <span style="display: block; font-weight: 600; font-size: 0.9rem;">Halo, <?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                </div>
                <div class="user-avatar">
                    <?php if(!empty($currentUser['profile_image'])): ?>
                        <img src="assets/uploads/<?php echo htmlspecialchars($currentUser['profile_image']); ?>" alt="Profil">
                    <?php else: ?>
                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <a href="logout.php" class="btn-logout" onclick="return confirm('Yakin ingin keluar?');">
                    <i class="fas fa-sign-out-alt"></i> Keluar
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if($message): ?>
            <div class="alert-box <?php echo ($message_type == 'success') ? 'alert-success' : 'alert-error'; ?>">
                <i class="fas fa-info-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if($is_laborer): ?>
            <div class="alert-box <?php echo ($laborer_data['is_active']) ? 'alert-success' : 'alert-warning'; ?>" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong>Status Kuli:</strong>
                    <?php if($laborer_data['is_active']): ?> <span class="status-badge status-active">AKTIF</span>
                    <?php elseif($laborer_data['payment_status'] == 'pending'): ?> <span class="status-badge status-pending">Menunggu Verifikasi</span>
                    <?php else: ?> <span class="status-badge status-unpaid">BELUM BAYAR</span> <?php endif; ?>
                </div>
                <div style="display: flex; gap: 10px;">
                    <?php if(!$laborer_data['is_active'] && $laborer_data['payment_status'] != 'pending'): ?>
                        <button onclick="openMembershipModal()" class="btn-action" style="background: #FFD700; color: #333;"><i class="fas fa-crown"></i> Bayar</button>
                    <?php endif; ?>
                    <form action="" method="POST" onsubmit="return confirm('Yakin berhenti jadi Kuli?');">
                        <input type="hidden" name="action_type" value="batalkan_kuli">
                        <button type="submit" class="btn-action" style="background: #c62828; color: white; border: none; cursor: pointer;"><i class="fas fa-user-times"></i> Berhenti</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="action-bar">
            <div>
                <h2 style="color: var(--dark-brown);">Panel Kontrol</h2>
                <p style="color: #666; font-size: 0.9rem;">Kelola properti dan profil Anda.</p>
            </div>
            <div class="action-buttons">
                <button onclick="openLandModal()" class="btn-action btn-sell"><i class="fas fa-plus-circle"></i> Jual Tanah</button>
                <?php if(!$is_laborer): ?>
                    <button onclick="openLaborerRegModal()" class="btn-action btn-worker"><i class="fas fa-hard-hat"></i> Daftar Jadi Kuli</button>
                <?php endif; ?>
                <button onclick="openProfileModal()" class="btn-action btn-edit"><i class="fas fa-camera"></i> Edit Profil</button>
            </div>
        </div>

        <div class="section-header" style="text-align: left; margin-bottom: 30px;">
            <h2 style="font-size: 1.8rem;">Katalog <span class="brown-text">Tanah</span></h2>
        </div>
        <div class="grid-cards">
            <?php if(count($lands) > 0): ?>
                <?php foreach($lands as $land): ?>
                <?php 
                    $lid = $land['id'];
                    $my_status = isset($my_access[$lid]) ? $my_access[$lid] : null; 
                    $is_globally_booked = in_array($lid, $booked_lands_raw);
                    $is_sold_final = ($land['status'] == 'sold'); 
                    $is_booked_by_me = ($my_status == 'pending' || $my_status == 'verified');
                    $tgl_upload = date('d F Y', strtotime($land['created_at']));
                ?>
                <div class="card">
                    <div class="card-img" id="slider-<?php echo $lid; ?>">
                        <div class="slider-wrapper">
                            <img src="assets/uploads/<?php echo htmlspecialchars($land['image1']); ?>" class="slide active" onerror="this.src='https://via.placeholder.com/500x300?text=No+Image'">
                            <img src="assets/uploads/<?php echo htmlspecialchars($land['image2']); ?>" class="slide" onerror="this.src='https://via.placeholder.com/500x300?text=No+Image'">
                        </div>
                        <button class="slider-btn prev" onclick="moveSlide(<?php echo $lid; ?>, -1)">&#10094;</button>
                        <button class="slider-btn next" onclick="moveSlide(<?php echo $lid; ?>, 1)">&#10095;</button>

                        <?php if($is_sold_final && !$is_booked_by_me): ?>
                            <span class="badge" style="background: red;">TERJUAL</span>
                        <?php elseif($is_booked_by_me && $my_status == 'verified'): ?>
                            <span class="badge" style="background: green;">MILIK ANDA</span>
                        <?php elseif($is_globally_booked && !$is_booked_by_me): ?>
                            <span class="badge" style="background: orange;">SEMENTARA DI-BOOKING</span>
                        <?php else: ?>
                            <span class="badge">Dijual</span>
                        <?php endif; ?>
                    </div>

                    <div class="card-body">
                        <h3><?php echo htmlspecialchars($land['title']); ?></h3>
                        
                        <p class="price">Rp <?php echo number_format($land['price'], 0, ',', '.'); ?></p>
                        
                        <ul class="specs">
                            <li><i class="fas fa-ruler-combined"></i> <?php echo htmlspecialchars($land['area_size']); ?></li>
                            <li><i class="fas fa-map-marker-alt"></i> <span class="location-real"><?php echo htmlspecialchars($land['location']); ?></span></li>
                        </ul>
                        <span class="date-info"><i class="far fa-calendar-alt"></i> Diiklankan: <?php echo $tgl_upload; ?></span>
                        
                        <div style="display: flex; gap: 10px; flex-direction: column; margin-top: 15px;">
                            <?php if ($is_sold_final && !$is_booked_by_me): ?>
                                <button class="btn-card btn-locked" disabled><i class="fas fa-times-circle"></i> Tidak Tersedia</button>
                            <?php elseif ($is_globally_booked && !$is_booked_by_me): ?>
                                <button class="btn-card btn-locked" disabled style="background: #FF9800; color: white;"><i class="fas fa-clock"></i> Sedang Di-booking...</button>
                            <?php elseif ($my_status == 'verified'): ?>
                                <?php $hp_jual = isset($land['phone_number']) ? $land['phone_number'] : ''; if(substr($hp_jual, 0, 1) == '0') $hp_jual = '62'.substr($hp_jual, 1); ?>
                                <div style="background: #e8f5e9; padding: 10px; border-radius: 5px; text-align: center; color: #2e7d32; font-weight: bold;"><i class="fas fa-check"></i> Terverifikasi</div>
                                <a href="https://wa.me/<?php echo $hp_jual; ?>" target="_blank" class="btn-card" style="text-align:center; text-decoration:none; background: #25D366;"><i class="fab fa-whatsapp"></i> Hubungi Penjual</a>
                            <?php elseif ($my_status == 'pending'): ?>
                                <button class="btn-card btn-pending" disabled><i class="fas fa-spinner fa-spin"></i> Verifikasi Pembayaran...</button>
                            <?php else: ?>
                                <button onclick="openBookingModal(<?php echo $lid; ?>, '<?php echo addslashes($land['title']); ?>')" class="btn-card" style="background: #8B4513;">
                                    <i class="fas fa-shopping-cart"></i> Booking Fee (Rp 5 Juta)
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?> <p style="color: #666; padding: 20px;">Belum ada tanah.</p> <?php endif; ?>
        </div>

        <hr style="margin: 50px 0; border: 0; border-top: 1px solid #ddd;">

        <div class="section-header" style="text-align: left; margin-bottom: 30px;">
            <h2 style="font-size: 1.8rem;">Mitra <span class="brown-text">Kuli</span></h2>
        </div>
        <div class="grid-cards">
            <?php if(count($laborers) > 0): ?>
                <?php foreach($laborers as $kuli): ?>
                <div class="card profile-card">
                    <div class="card-img profile-img">
                        <img src="assets/uploads/<?php echo htmlspecialchars($kuli['profile_image']); ?>" onerror="this.src='https://via.placeholder.com/200x200?text=No+Image'">
                    </div>
                    <div class="card-body text-center">
                        <h3><?php echo htmlspecialchars($kuli['full_name']); ?></h3>
                        <p class="job-title"><?php echo htmlspecialchars($kuli['skills']); ?></p>
                        
                        <div style="font-size: 0.85rem; color: #555; margin: 5px 0;">
                            <p><i class="fas fa-user-clock"></i> Umur: <?php echo htmlspecialchars($kuli['age']); ?> Tahun</p>
                            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($kuli['domicile']); ?></p>
                            <p><i class="far fa-calendar-check"></i> Gabung: <?php echo date('d F Y', strtotime($kuli['created_at'])); ?></p>
                        </div>

                        <?php if($kuli['ready_out_of_town']): ?>
                            <span style="font-size:0.7rem; background:#e3f2fd; color:#1565c0; padding:2px 6px; border-radius:4px;">Siap Luar Kota</span>
                        <?php endif; ?>
                        
                        <div style="margin-top: 10px;">
                            <?php $hp = $kuli['phone']; if(substr($hp, 0, 1) == '0') $hp = '62'.substr($hp, 1); ?>
                            <a href="https://wa.me/<?php echo $hp; ?>" target="_blank" class="btn-card" style="background: #25D366; text-decoration:none; display:block;"><i class="fab fa-whatsapp"></i> Hubungi</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?> <p style="color: #666; padding: 20px;">Belum ada kuli.</p> <?php endif; ?>
        </div>
    </div>

    <div class="modal-overlay" id="bookingModal">
        <div class="modal-content large-modal">
            <span class="close-modal" onclick="document.getElementById('bookingModal').style.display='none'">&times;</span>
            <h3>Booking Tanah</h3>
            <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 5px solid #2196f3;">
                <p><strong>Booking Fee: Rp 5.000.000</strong></p>
                <p>Bank BRI: 775401008498534 (JAMES KHRISTIAN ALBERTSON)</p>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="booking_tanah">
                <input type="hidden" name="land_id" id="booking_land_id">
                <input type="text" id="booking_land_title" disabled style="background:#eee; width:100%; padding:10px; margin-bottom:10px;">
                <input type="file" name="payment_proof" required>
                <button type="submit" class="btn-submit">Kirim Bukti</button>
            </form>
        </div>
    </div>
    
    <div class="modal-overlay" id="membershipModal">
        <div class="modal-content large-modal">
            <span class="close-modal" onclick="document.getElementById('membershipModal').style.display='none'">&times;</span>
            <h3>Aktivasi Membership</h3>
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 5px solid #ffc107;">
                <p><strong>Biaya: Rp 50.000 / Bulan</strong></p>
                <p>Bank BRI: 775401008498534 (JAMES KHRISTIAN ALBERTSON)</p>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="bayar_membership">
                <input type="file" name="payment_proof" required>
                <button type="submit" class="btn-submit">Konfirmasi</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="landModal">
        <div class="modal-content large-modal">
            <span class="close-modal" onclick="document.getElementById('landModal').style.display='none'">&times;</span>
            <h3>Iklankan Tanah</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="jual_tanah">
                
                <input type="text" name="title" required placeholder="Judul Iklan" class="form-group" style="width:100%; padding:10px; margin-bottom:10px;">
                
                <div style="margin-bottom: 10px;">
                    <label style="font-weight:bold; font-size:0.9rem;">Lokasi Tanah</label>
                    <select id="land_prov" class="form-group" style="width:100%; padding:10px;">
                        <option value="">Pilih Provinsi...</option>
                    </select>
                </div>
                <div style="margin-bottom: 10px;">
                    <select id="land_city" class="form-group" style="width:100%; padding:10px;" disabled>
                        <option value="">Pilih Kabupaten/Kota...</option>
                    </select>
                </div>
                <input type="hidden" name="location" id="land_final_loc">

                <input type="number" name="phone_number" required placeholder="No WA" class="form-group" style="width:100%; padding:10px; margin-bottom:10px;" value="<?php echo htmlspecialchars($currentUser['phone']); ?>">
                <input type="text" name="description" required placeholder="Deskripsi Lengkap" class="form-group" style="width:100%; padding:10px; margin-bottom:10px;">
                
                <label style="font-weight:bold; font-size:0.9rem;">Luas Tanah</label>
                <div class="input-group" style="margin-bottom: 10px;">
                    <input type="number" name="area_input" required placeholder="Contoh: 500" class="form-group" style="padding:10px;">
                    <select name="area_unit" class="form-group" style="padding:10px;">
                        <option value="m²">Persegi (m²)</option>
                        <option value="Hektar">Hektar</option>
                    </select>
                </div>

                <label style="font-weight:bold; font-size:0.9rem;">Harga Tanah</label>
                <input type="text" name="price_display" id="input_harga" required placeholder="Rp 0" class="form-group" style="width:100%; padding:10px; margin-bottom:10px;">

                <label>Foto Utama</label><input type="file" name="image1" required class="form-group">
                <label>Foto Lainnya</label><input type="file" name="image2" required class="form-group">
                <button type="submit" class="btn-submit">Posting Iklan</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="laborerRegModal">
        <div class="modal-content large-modal">
            <span class="close-modal" onclick="document.getElementById('laborerRegModal').style.display='none'">&times;</span>
            <h3>Daftar Jadi Kuli</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="daftar_kuli">
                <input type="text" value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" disabled style="background:#eee; width:100%; padding:10px; margin-bottom:10px;">
                <input type="number" name="age" required min="18" placeholder="Umur (Min 18)" class="form-group" style="width:100%; padding:10px; margin-bottom:10px;">
                
                <div style="margin-bottom: 10px;">
                    <label style="font-weight:bold; font-size:0.9rem;">Domisili</label>
                    <select id="kuli_prov" class="form-group" style="width:100%; padding:10px;">
                        <option value="">Pilih Provinsi...</option>
                    </select>
                </div>
                <div style="margin-bottom: 10px;">
                    <select id="kuli_city" class="form-group" style="width:100%; padding:10px;" disabled>
                        <option value="">Pilih Kabupaten/Kota...</option>
                    </select>
                </div>
                <input type="hidden" name="domicile_location" id="kuli_final_loc">

                <select name="ready_out_of_town" class="form-group" style="width:100%; padding:10px; margin-bottom:10px;">
                    <option value="0">Hanya Dalam Kota</option>
                    <option value="1">Siap Luar Kota</option>
                </select>
                <input type="number" name="experience_year" required placeholder="Pengalaman (Tahun)" class="form-group" style="width:100%; padding:10px; margin-bottom:10px;">
                <textarea name="skills" required placeholder="Keahlian (Contoh: Ngaduk semen, pasang bata)" class="form-group" style="width:100%; padding:10px; margin-bottom:10px;"></textarea>
                <label>Foto Profil</label><input type="file" name="profile_image" required accept="image/*">
                <button type="submit" class="btn-submit" style="background: #2E7D32;">Daftar Sekarang</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="profileModal">
        <div class="modal-content large-modal">
            <span class="close-modal" onclick="document.getElementById('profileModal').style.display='none'">&times;</span>
            <h3>Edit Profil</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="edit_profil">
                <label>Ganti Foto</label><input type="file" name="profile_image" class="form-group">
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required class="form-group" style="width:100%; padding:10px; margin-bottom:10px;">
                <input type="text" name="phone" value="<?php echo htmlspecialchars($currentUser['phone']); ?>" required class="form-group" style="width:100%; padding:10px; margin-bottom:10px;">
                <input type="password" name="password" placeholder="Password Baru (Kosongkan jika tidak diganti)" class="form-group" style="width:100%; padding:10px; margin-bottom:10px;">
                <button type="submit" class="btn-submit">Simpan</button>
            </form>
        </div>
    </div>

    <script>
        // --- 1. SCRIPT FORMAT RUPIAH (AUTO TITIK) ---
        const inputHarga = document.getElementById('input_harga');
        if(inputHarga){
            inputHarga.addEventListener('keyup', function(e){
                inputHarga.value = formatRupiah(this.value, 'Rp. ');
            });
        }

        function formatRupiah(angka, prefix){
            var number_string = angka.replace(/[^,\d]/g, '').toString(),
            split   		= number_string.split(','),
            sisa     		= split[0].length % 3,
            rupiah     		= split[0].substr(0, sisa),
            ribuan     		= split[0].substr(sisa).match(/\d{3}/gi);

            if(ribuan){
                separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix == undefined ? rupiah : (rupiah ? 'Rp ' + rupiah : '');
        }

        // --- 2. SCRIPT API WILAYAH (UNTUK TANAH & KULI) ---
        const apiBaseUrl = 'https://www.emsifa.com/api-wilayah-indonesia/api';

        function setupLocationDropdown(provId, cityId, hiddenInputId) {
            const selectProv = document.getElementById(provId);
            const selectCity = document.getElementById(cityId);
            const hiddenInput = document.getElementById(hiddenInputId);
            let selectedProvName = "";

            fetch(`${apiBaseUrl}/provinces.json`)
            .then(res => res.json())
            .then(data => {
                data.forEach(prov => {
                    let opt = document.createElement('option');
                    opt.value = prov.id; opt.text = prov.name;
                    selectProv.add(opt);
                });
            });

            selectProv.addEventListener('change', function(){
                selectedProvName = this.options[this.selectedIndex].text;
                selectCity.innerHTML = '<option value="">Pilih Kabupaten/Kota...</option>';
                selectCity.disabled = true;

                if(this.value) {
                    fetch(`${apiBaseUrl}/regencies/${this.value}.json`)
                    .then(res => res.json())
                    .then(data => {
                        selectCity.disabled = false;
                        data.forEach(city => {
                            let opt = document.createElement('option');
                            opt.value = city.name; opt.text = city.name;
                            selectCity.add(opt);
                        });
                    });
                }
            });

            selectCity.addEventListener('change', function(){
                hiddenInput.value = `${this.value}, ${selectedProvName}`;
            });
        }

        // Jalankan API untuk Tanah & Kuli
        setupLocationDropdown('land_prov', 'land_city', 'land_final_loc');
        setupLocationDropdown('kuli_prov', 'kuli_city', 'kuli_final_loc');


        // --- FUNGSI MODAL & SLIDER ---
        function openLandModal() { document.getElementById('landModal').style.display = 'flex'; }
        function openProfileModal() { document.getElementById('profileModal').style.display = 'flex'; }
        function openMembershipModal() { document.getElementById('membershipModal').style.display = 'flex'; }
        function openLaborerRegModal() { document.getElementById('laborerRegModal').style.display = 'flex'; }
        function openBookingModal(id, title) {
            document.getElementById('booking_land_id').value = id;
            document.getElementById('booking_land_title').value = title;
            document.getElementById('bookingModal').style.display = 'flex';
        }
        window.onclick = function(e) { if(e.target.className === 'modal-overlay') { e.target.style.display = 'none'; } }
        
        function moveSlide(landId, direction) {
            const container = document.getElementById('slider-' + landId);
            const slides = container.querySelectorAll('.slide');
            let activeIndex = 0;
            slides.forEach((slide, index) => { if (slide.classList.contains('active')) { activeIndex = index; slide.classList.remove('active'); } });
            let newIndex = activeIndex + direction;
            if (newIndex >= slides.length) newIndex = 0;
            if (newIndex < 0) newIndex = slides.length - 1;
            slides[newIndex].classList.add('active');
        }
    </script>
</body>
</html>