<?php
require_once 'config.php';

$action = $_REQUEST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;

switch ($action) {
    // ================= AUTENTIKASI =================
    case 'login':
        $email = trim($input['email'] ?? '');
        $pass  = $input['password'] ?? '';
        $stmt  = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password'])) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))")
                ->execute([$token, $user['id']]);
            echo json_encode(['success' => true, 'token' => $token, 'user' => [
                'id' => $user['id'], 
                'name' => $user['name'], 
                'email' => $user['email'], 
                'role' => $user['role'],
                'phone' => $user['phone'],           
                'id_number' => $user['id_number'],   
                'joined_at' => $user['joined_at']    
            ]]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Email atau password salah']);
        }
        break;

    case 'register':
        $name  = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $pass  = $input['password'] ?? '';
        $phone = trim($input['phone'] ?? '');
        $idNum = trim($input['id_number'] ?? '');
        if (empty($name) || empty($email) || empty($pass)) {
            echo json_encode(['success' => false, 'message' => 'Data wajib diisi']); break;
        }
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $id   = 'u_' . bin2hex(random_bytes(8));
        try {
            $pdo->prepare("INSERT INTO users (id, name, email, password, phone, id_number) VALUES (?,?,?,?,?,?)")
                ->execute([$id, $name, $email, $hash, $phone, $idNum]);
            echo json_encode(['success' => true, 'message' => 'Registrasi berhasil']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getCode() == 23000 ? 'Email sudah terdaftar' : 'Registrasi gagal']);
        }
        break;

    case 'me':
        $user = authenticate($pdo);
        echo json_encode(['success' => true, 'user' => [
            'id' => $user['id'], 
            'name' => $user['name'], 
            'email' => $user['email'], 
            'role' => $user['role'],
            'phone' => $user['phone'],           // Tambahan baru
            'id_number' => $user['id_number'],   // Tambahan baru
            'joined_at' => $user['joined_at']    // Tambahan baru
        ]]);
        break;

    // ================= KAMAR & UNIT =================
    case 'get_rooms':
        $stmt = $pdo->query("
            SELECT r.id, r.type, r.price_per_night, r.description, r.emoji, r.is_active, 
                   COUNT(u.id) AS total_units 
            FROM rooms r 
            LEFT JOIN room_units u ON r.id = u.room_id AND u.is_active = 1 
            GROUP BY r.id
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'add_room':
        $user = authenticate($pdo);
        if ($user['role'] !== 'admin') { echo json_encode(['success'=>false, 'message'=>'Akses ditolak']); break; }
        $id = 'rm_' . bin2hex(random_bytes(4));
        $type = $input['type'] ?? '';
        $price = $input['price_per_night'] ?? 0;
        $desc = $input['description'] ?? '';
        $emoji = $input['emoji'] ?? '🛏️';
        $pdo->prepare("INSERT INTO rooms (id, type, price_per_night, description, emoji) VALUES (?,?,?,?,?)")
            ->execute([$id, $type, $price, $desc, $emoji]);
        echo json_encode(['success' => true, 'message' => 'Kamar ditambahkan']);
        break;

    case 'update_room':
        $user = authenticate($pdo);
        if ($user['role'] !== 'admin') { echo json_encode(['success'=>false]); break; }
        $id = $input['id'] ?? '';
        $pdo->prepare("UPDATE rooms SET type=?, price_per_night=?, description=?, emoji=?, is_active=? WHERE id=?")
            ->execute([$input['type'], $input['price_per_night'], $input['description'], $input['emoji'], $input['is_active'], $id]);
        echo json_encode(['success' => true]);
        break;

    case 'delete_room':
        $user = authenticate($pdo);
        if ($user['role'] !== 'admin') { echo json_encode(['success'=>false]); break; }
        try {
            $pdo->prepare("DELETE FROM rooms WHERE id=?")->execute([$input['id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus. Kamar ini masih terkait dengan data booking/unit.']);
        }
        break;

    case 'get_room_units_availability':
        $user = authenticate($pdo);
        $roomId  = $_GET['room_id'] ?? '';
        $checkin = $_GET['checkin'] ?? '';
        $checkout= $_GET['checkout'] ?? '';
        if (!$roomId || !$checkin || !$checkout) {
            echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']); break;
        }
        $units = $pdo->prepare("SELECT id, room_number FROM room_units WHERE room_id = ? AND is_active = 1");
        $units->execute([$roomId]);
        $result = [];
        foreach ($units->fetchAll() as $u) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE room_unit_id = ? AND payment_status IN ('paid', 'pending') AND checkin_date < ? AND checkout_date > ?");
            $stmt->execute([$u['id'], $checkout, $checkin]);
            $result[] = ['unit_id' => $u['id'], 'room_number' => $u['room_number'], 'available' => $stmt->fetch()['cnt'] == 0];
        }
        echo json_encode(['success' => true, 'data' => $result]);
        break;

    // ================= BOOKINGS & TRANSACTIONS =================
    case 'create_booking':
        $user = authenticate($pdo);
        $unitId   = $input['room_unit_id'] ?? '';
        $checkin  = $input['checkin_date'] ?? '';
        $checkout = $input['checkout_date'] ?? '';
        $guests   = (int)($input['guests'] ?? 1);

        // Panggil Stored Procedure
        $stmt = $pdo->prepare("CALL sp_create_secure_booking(?, ?, ?, ?, ?, @b_id, @t_price, @status)");
        $stmt->execute([$user['id'], $unitId, $checkin, $checkout, $guests]);
        $stmt->closeCursor();

        // Ambil Hasilnya
        $res = $pdo->query("SELECT @b_id AS booking_id, @t_price AS total_price, @status AS status")->fetch();

        if ($res['status'] === 'SUCCESS') {
            echo json_encode(['success' => true, 'booking_id' => $res['booking_id'], 'total_price' => $res['total_price']]);
        } else {
            // Jika Andi telat 1 milidetik dari Budi, ia akan menerima pesan ini:
            echo json_encode(['success' => false, 'message' => 'Maaf, kamar ini baru saja di-booking oleh tamu lain.']);
        }
        break;

    case 'get_bookings':
        $user = authenticate($pdo);
        if ($user['role'] === 'admin') {
            // Admin melihat semua booking + nama & email pelanggan
            $stmt = $pdo->query("SELECT b.*, u.room_number, r.type as room_type, c.name as customer_name, c.email as customer_email FROM bookings b JOIN room_units u ON b.room_unit_id = u.id JOIN rooms r ON u.room_id = r.id JOIN users c ON b.customer_id = c.id ORDER BY b.created_at DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } else {
            // Customer melihat miliknya sendiri
            $stmt = $pdo->prepare("SELECT b.*, u.room_number, r.type as room_type FROM bookings b JOIN room_units u ON b.room_unit_id = u.id JOIN rooms r ON u.room_id = r.id WHERE b.customer_id = ? ORDER BY b.created_at DESC");
            $stmt->execute([$user['id']]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        }
        break;

    case 'delete_booking':
        $user = authenticate($pdo);
        $bid = $input['booking_id'] ?? '';
        $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$bid]);
        echo json_encode(['success' => true]);
        break;

    case 'process_payment':
        $user = authenticate($pdo);
        $bid = $input['booking_id'] ?? '';
        $mtd = $input['method'] ?? 'Transfer Bank';
        
        // Pengecekan agar admin bisa proses pembayaran tamu lain
        if ($user['role'] === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND payment_status = 'pending'");
            $stmt->execute([$bid]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND payment_status = 'pending' AND customer_id = ?");
            $stmt->execute([$bid, $user['id']]);
        }
        
        $book = $stmt->fetch();
        if (!$book) { echo json_encode(['success' => false, 'message' => 'Booking tidak ditemukan/sudah lunas']); break; }
        
        $voucher = 'EV-' . strtoupper(substr($bid, -8));
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE bookings SET payment_status='paid', e_voucher=?, metode_pembayaran=? WHERE id=?")->execute([$voucher, $mtd, $bid]);
            $pdo->prepare("INSERT INTO transactions (id, booking_id, customer_id, amount, method, status, e_voucher) VALUES (?,?,?,?,?,'SUCCESS',?)")
                ->execute(['TRX'.time(), $bid, $book['customer_id'], $book['total_price'], $mtd, $voucher]);
            $pdo->commit();
            echo json_encode(['success' => true, 'e_voucher' => $voucher]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Pembayaran gagal']);
        }
        break;

    // ================= DASHBOARD ADMIN =================
    case 'get_stats':
        $user = authenticate($pdo);
        if ($user['role'] !== 'admin') { echo json_encode(['success' => false]); break; }
        $totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
        $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
        $totalUnits = $pdo->query("SELECT COUNT(*) FROM room_units WHERE is_active = 1")->fetchColumn();
        $totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
        echo json_encode(['success' => true, 'data' => [
            'totalBookings' => $totalBookings, 'totalRevenue' => $totalRevenue, 'totalUnits' => $totalUnits, 'totalCustomers' => $totalCustomers
        ]]);
        break;

    case 'get_transactions':
        $user = authenticate($pdo);
        if ($user['role'] !== 'admin') { echo json_encode(['success' => false]); break; }
        $stmt = $pdo->query("SELECT t.*, u.email as customer_email FROM transactions t JOIN users u ON t.customer_id = u.id ORDER BY t.created_at DESC LIMIT 10");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'get_customers':
        $user = authenticate($pdo);
        if ($user['role'] !== 'admin') { echo json_encode(['success' => false]); break; }
        $stmt = $pdo->query("SELECT name, email, phone, id_number, joined_at FROM users WHERE role = 'customer' ORDER BY joined_at DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}