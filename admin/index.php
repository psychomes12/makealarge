<?php
// C:\xampp\htdocs\makealarge\admin\index.php
session_start();

// Perbaiki path koneksi karena file ini ada di dalam folder 'admin'
require_once '../config/database.php';
require_once '../config/functions.php';

// 1. KEAMANAN: Cek apakah user adalah ADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('Akses Ditolak! Anda bukan Admin.'); window.location.href='../index.html';</script>";
    exit;
}

$message = "";

// ==========================================
// LOGIC: VERIFIKASI PEMBAYARAN KULI
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'approve_kuli') {
    $laborer_id = $_POST['id'];
    try {
        $stmt = $conn->prepare("UPDATE laborers SET is_active = 1, payment_status = 'paid' WHERE id = ?");
        $stmt->execute([$laborer_id]);
        $message = "Mitra Kuli Berhasil Diaktifkan!";
    } catch (PDOException $e) { $message = "Error: " . $e->getMessage(); }
}

if (isset($_POST['action']) && $_POST['action'] == 'reject_kuli') {
    $laborer_id = $_POST['id'];
    // Opsional: Hapus data atau kembalikan ke unpaid
    $stmt = $conn->prepare("UPDATE laborers SET payment_status = 'unpaid', is_active = 0 WHERE id = ?");
    $stmt->execute([$laborer_id]);
    $message = "Permintaan Kuli Ditolak.";
}

// ==========================================
// LOGIC: VERIFIKASI BOOKING TANAH
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'approve_booking') {
    $booking_id = $_POST['id'];
    $land_id = $_POST['land_id'];
    
    try {
        // 1. Update status booking jadi verified
        $stmt = $conn->prepare("UPDATE bookings SET status = 'verified' WHERE id = ?");
        $stmt->execute([$booking_id]);
        
        // 2. Update status tanah jadi 'sold' (Terjual) agar tidak bisa dibeli orang lain
        $stmt2 = $conn->prepare("UPDATE lands SET status = 'sold' WHERE id = ?");
        $stmt2->execute([$land_id]);
        
        $message = "Booking Dikonfirmasi! Tanah ditandai terjual.";
    } catch (PDOException $e) { $message = "Error: " . $e->getMessage(); }
}

// ==========================================
// LOGIC: HAPUS TANAH (MODERASI)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_land') {
    $land_id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM lands WHERE id = ?");
    $stmt->execute([$land_id]);
    $message = "Iklan tanah berhasil dihapus.";
}


// --- QUERY DATA ---
// Hitung Statistik
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_lands = $conn->query("SELECT COUNT(*) FROM lands")->fetchColumn();
$total_laborers = $conn->query("SELECT COUNT(*) FROM laborers WHERE is_active = 1")->fetchColumn();

// Ambil Data Pending Kuli
$pending_kulis = $conn->query("
    SELECT laborers.*, users.full_name, users.phone 
    FROM laborers JOIN users ON laborers.user_id = users.id 
    WHERE laborers.payment_status = 'pending'
")->fetchAll();

// Ambil Data Booking Pending
$pending_bookings = $conn->query("
    SELECT bookings.*, lands.title as land_title, users.full_name as buyer_name 
    FROM bookings 
    JOIN lands ON bookings.land_id = lands.id 
    JOIN users ON bookings.buyer_id = users.id
    WHERE bookings.status = 'pending'
")->fetchAll();

// Ambil Semua Tanah (Untuk Moderasi)
$all_lands = $conn->query("SELECT * FROM lands ORDER BY created_at DESC")->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Make a LARGE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Simple Admin */
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { margin: 0; display: flex; background: #f4f6f9; }
        
        /* Sidebar */
        .sidebar { width: 250px; background: #343a40; color: white; height: 100vh; position: fixed; padding: 20px; }
        .sidebar h2 { color: #D7CCC8; text-align: center; margin-bottom: 30px; }
        .sidebar a { display: block; color: #c2c7d0; text-decoration: none; padding: 12px; margin-bottom: 5px; border-radius: 4px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: #8B4513; color: white; }
        .sidebar i { width: 25px; }

        /* Content */
        .content { margin-left: 250px; padding: 30px; width: 100%; }
        
        /* Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 5px solid #8B4513; }
        .stat-card h3 { margin: 0; font-size: 2rem; color: #333; }
        .stat-card p { margin: 5px 0 0; color: #666; }

        /* Tables */
        .table-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #333; }
        
        /* Buttons & Images */
        .btn { padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer; color: white; font-size: 0.8rem; }
        .btn-approve { background: #28a745; }
        .btn-reject { background: #dc3545; }
        .proof-img { width: 50px; height: 50px; object-fit: cover; cursor: pointer; border: 1px solid #ddd; }
        .proof-img:hover { transform: scale(3); transition: 0.3s; position: relative; z-index: 10; }

        .alert { background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>Admin Panel</h2>
        <a href="#dashboard" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="#verif-kuli"><i class="fas fa-hard-hat"></i> Verifikasi Kuli</a>
        <a href="#verif-booking"><i class="fas fa-money-bill-wave"></i> Booking Tanah</a>
        <a href="#manage-land"><i class="fas fa-map"></i> Kelola Tanah</a>
        <a href="../dashboard.php" style="margin-top: 50px; background: #555;"><i class="fas fa-arrow-left"></i> Kembali ke Web</a>
        <a href="../logout.php" style="background: #c62828;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="content">
        <h1>Halo, Admin <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
        
        <?php if($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="stats-grid" id="dashboard">
            <div class="stat-card">
                <h3><?php echo $total_users; ?></h3>
                <p>Total Pengguna</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_lands; ?></h3>
                <p>Iklan Tanah</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_laborers; ?></h3>
                <p>Mitra Kuli Aktif</p>
            </div>
            <div class="stat-card" style="border-left-color: #ffc107;">
                <h3><?php echo count($pending_kulis) + count($pending_bookings); ?></h3>
                <p>Perlu Verifikasi</p>
            </div>
        </div>

        <div class="table-container" id="verif-kuli">
            <div class="table-header">
                <h3><i class="fas fa-user-check"></i> Pending Membership Kuli (<?php echo count($pending_kulis); ?>)</h3>
            </div>
            <?php if(count($pending_kulis) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Keahlian</th>
                        <th>Bukti Transfer</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_kulis as $kuli): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($kuli['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($kuli['skills']); ?></td>
                        <td>
                            <a href="../assets/uploads/<?php echo $kuli['payment_proof']; ?>" target="_blank">
                                <img src="../assets/uploads/<?php echo $kuli['payment_proof']; ?>" class="proof-img" alt="Bukti">
                            </a>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $kuli['id']; ?>">
                                <input type="hidden" name="action" value="approve_kuli">
                                <button type="submit" class="btn btn-approve" onclick="return confirm('Terima pembayaran ini?')">Terima</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $kuli['id']; ?>">
                                <input type="hidden" name="action" value="reject_kuli">
                                <button type="submit" class="btn btn-reject" onclick="return confirm('Tolak?')">Tolak</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>Tidak ada pembayaran kuli yang menunggu.</p>
            <?php endif; ?>
        </div>

        <div class="table-container" id="verif-booking">
            <div class="table-header">
                <h3><i class="fas fa-file-invoice-dollar"></i> Pending Booking Tanah (<?php echo count($pending_bookings); ?>)</h3>
            </div>
            <?php if(count($pending_bookings) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Pembeli</th>
                        <th>Tanah</th>
                        <th>Jumlah</th>
                        <th>Bukti Transfer</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_bookings as $book): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($book['buyer_name']); ?></td>
                        <td><?php echo htmlspecialchars($book['land_title']); ?></td>
                        <td>Rp <?php echo number_format($book['amount'], 0, ',', '.'); ?></td>
                        <td>
                            <a href="../assets/uploads/<?php echo $book['proof_image']; ?>" target="_blank">
                                <img src="../assets/uploads/<?php echo $book['proof_image']; ?>" class="proof-img" alt="Bukti">
                            </a>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $book['id']; ?>">
                                <input type="hidden" name="land_id" value="<?php echo $book['land_id']; ?>">
                                <input type="hidden" name="action" value="approve_booking">
                                <button type="submit" class="btn btn-approve" onclick="return confirm('Verifikasi pembayaran ini? Status tanah akan berubah jadi SOLD.')">Verifikasi</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>Tidak ada booking baru.</p>
            <?php endif; ?>
        </div>

        <div class="table-container" id="manage-land">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Semua Iklan Tanah</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_lands as $land): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($land['title']); ?></td>
                        <td>Rp <?php echo number_format($land['price'], 0, ',', '.'); ?></td>
                        <td>
                            <?php if($land['status'] == 'sold'): ?>
                                <span style="background:red; color:white; padding:2px 5px; border-radius:4px;">TERJUAL</span>
                            <?php else: ?>
                                <span style="background:green; color:white; padding:2px 5px; border-radius:4px;">TERSEDIA</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $land['id']; ?>">
                                <input type="hidden" name="action" value="delete_land">
                                <button type="submit" class="btn btn-reject" onclick="return confirm('Hapus iklan ini secara permanen?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>