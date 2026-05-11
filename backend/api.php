<?php
require_once 'config.php';

$action = $_REQUEST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;

switch ($action) {
    // === AUTENTIKASI ===
    case 'login':
        handleLogin($pdo, $input);
        break;
    case 'register':
        handleRegister($pdo, $input);
        break;
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out']);
        break;
    case 'me':
        requireAuth();
        echo json_encode(['success' => true, 'user' => $_SESSION]);
        break;

    // === ROOMS (KAMAR) ===
    case 'get_rooms':
        handleGetRooms($pdo);
        break;
    case 'add_room':
        requireAuth(['admin']);
        handleAddRoom($pdo, $input);
        break;
    case 'update_room':
        requireAuth(['admin']);
        handleUpdateRoom($pdo, $input);
        break;
    case 'delete_room':
        requireAuth(['admin']);
        handleDeleteRoom($pdo, $input);
        break;

    // === BOOKINGS (RESERVASI) ===
    case 'get_bookings':
        requireAuth();
        handleGetBookings($pdo);
        break;
    case 'create_booking':
        requireAuth(['customer']);
        handleCreateBooking($pdo, $input);
        break;
    case 'delete_booking':
        requireAuth(['admin']);
        handleDeleteBooking($pdo, $input);
        break;

    // === TRANSAKSI & PEMBAYARAN ===
    case 'process_payment':
        requireAuth(['customer', 'admin']);
        handleProcessPayment($pdo, $input);
        break;
    case 'get_transactions':
        requireAuth(['admin']);
        handleGetTransactions($pdo);
        break;

    // === DASHBOARD & STATS ===
    case 'get_stats':
        requireAuth(['admin']);
        handleGetStats($pdo);
        break;
    case 'get_customers':
        requireAuth(['admin']);
        handleGetCustomers($pdo);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
exit;


// ==========================================
// FUNGSI HANDLER
// ==========================================

function handleLogin($pdo, $input) {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        echo json_encode(['success' => true, 'role' => $user['role'], 'message' => 'Login berhasil']);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Email atau kata sandi salah']);
    }
}

function handleRegister($pdo, $input) {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $phone = trim($input['phone'] ?? null);
    $id_number = trim($input['id_number'] ?? null);

    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Nama, Email, dan Password wajib diisi']);
        return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $id = 'u_' . bin2hex(random_bytes(8));

    try {
        $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password, phone, id_number, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
        $stmt->execute([$id, $name, $email, $hash, $phone, $id_number]);
        
        // Auto-login setelah register
        $_SESSION['user_id'] = $id;
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'customer';

        echo json_encode(['success' => true, 'message' => 'Registrasi berhasil']);
    } catch(PDOException $e) {
        if($e->getCode() == 23000) { // Integrity constraint violation (Duplicate email)
            echo json_encode(['success' => false, 'message' => 'Email sudah terdaftar']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registrasi gagal']);
        }
    }
}

function handleGetRooms($pdo) {
    $stmt = $pdo->query("SELECT * FROM rooms ORDER BY created_at DESC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function handleAddRoom($pdo, $input) {
    $id = 'rm_' . bin2hex(random_bytes(6));
    $type = trim($input['type'] ?? '');
    $price = (float)($input['price_per_night'] ?? 0);
    $stock = (int)($input['stock'] ?? 1);
    $desc = trim($input['description'] ?? '');
    $emoji = trim($input['emoji'] ?? '🛏️');
    $active = (int)($input['is_active'] ?? 1);

    $stmt = $pdo->prepare("INSERT INTO rooms (id, type, price_per_night, stock, description, emoji, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $success = $stmt->execute([$id, $type, $price, $stock, $desc, $emoji, $active]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Kamar ditambahkan' : 'Gagal menambahkan']);
}

function handleUpdateRoom($pdo, $input) {
    $id = $input['id'] ?? '';
    $type = trim($input['type'] ?? '');
    $price = (float)($input['price_per_night'] ?? 0);
    $stock = (int)($input['stock'] ?? 1);
    $desc = trim($input['description'] ?? '');
    $emoji = trim($input['emoji'] ?? '🛏️');
    $active = (int)($input['is_active'] ?? 1);

    $stmt = $pdo->prepare("UPDATE rooms SET type=?, price_per_night=?, stock=?, description=?, emoji=?, is_active=? WHERE id=?");
    $success = $stmt->execute([$type, $price, $stock, $desc, $emoji, $active, $id]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Kamar diperbarui' : 'Gagal memperbarui']);
}

function handleDeleteRoom($pdo, $input) {
    $id = $input['room_id'] ?? '';
    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
    try {
        $success = $stmt->execute([$id]);
        echo json_encode(['success' => $success, 'message' => 'Kamar dihapus']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Kamar tidak bisa dihapus karena sedang direlasikan (ada booking).']);
    }
}

function handleCreateBooking($pdo, $input) {
    $room_id = $input['room_id'] ?? '';
    $check_in = $input['checkin_date'] ?? '';
    $check_out = $input['checkout_date'] ?? '';
    $guests = (int)($input['guests'] ?? 1);
    $notes = $input['notes'] ?? null;
    $customer_id = $_SESSION['user_id'];

    // 1. Ambil data kamar
    $stmt = $pdo->prepare("SELECT price_per_night FROM rooms WHERE id = ? AND is_active = 1");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Kamar tidak valid atau tidak aktif']);
        return;
    }

    // 2. Cek ketersediaan via Stored Procedure
    $stmtCheck = $pdo->prepare("CALL sp_check_room_availability(?, ?, ?, @is_available)");
    $stmtCheck->execute([$room_id, $check_in, $check_out]);
    $stmtCheck->closeCursor(); // Bersihkan cursor
    
    $res = $pdo->query("SELECT @is_available AS available")->fetch();
    if ($res['available'] == 0) {
        echo json_encode(['success' => false, 'message' => 'Kamar sudah di-booking pada tanggal tersebut']);
        return;
    }

    // 3. Kalkulasi dan Insert
    $nights = (strtotime($check_out) - strtotime($check_in)) / 86400000;
    $total_price = $nights * $room['price_per_night'];
    $booking_id = 'BK' . time();

    $stmtInsert = $pdo->prepare("
        INSERT INTO bookings (id, customer_id, room_id, checkin_date, checkout_date, nights, guests, total_price, payment_status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
    ");
    
    $success = $stmtInsert->execute([$booking_id, $customer_id, $room_id, $check_in, $check_out, $nights, $guests, $total_price, $notes]);
    
    echo json_encode([
        'success' => $success, 
        'message' => 'Booking berhasil dibuat',
        'data' => ['booking_id' => $booking_id, 'total_price' => $total_price]
    ]);
}

function handleGetBookings($pdo) {
    if ($_SESSION['role'] === 'admin') {
        // View khusus admin
        $stmt = $pdo->query("SELECT * FROM v_booking_detail ORDER BY booked_at DESC");
    } else {
        // Customer hanya melihat miliknya
        $stmt = $pdo->prepare("
            SELECT b.*, r.type AS room_type, r.emoji 
            FROM bookings b 
            JOIN rooms r ON b.room_id = r.id 
            WHERE b.customer_id = ? 
            ORDER BY b.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
    }
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function handleDeleteBooking($pdo, $input) {
    $booking_id = $input['booking_id'] ?? '';
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $success = $stmt->execute([$booking_id]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Booking dihapus' : 'Gagal menghapus']);
}

function handleProcessPayment($pdo, $input) {
    $booking_id = $input['booking_id'] ?? '';
    $method = $input['method'] ?? 'Transfer Bank';
    $gateway_ref = 'GW-'.strtoupper(bin2hex(random_bytes(4)));

    // Memanggil SP untuk memproses pembayaran
    $stmt = $pdo->prepare("CALL sp_process_payment(?, ?, ?, @voucher, @result)");
    $stmt->execute([$booking_id, $method, $gateway_ref]);
    $stmt->closeCursor();

    $res = $pdo->query("SELECT @voucher AS voucher, @result AS status")->fetch();

    if ($res['status'] === 'SUCCESS') {
        // Update metode pembayaran di tabel booking (opsional karena sudah ada di transaksi, tapi ada di struktur DB)
        $pdo->prepare("UPDATE bookings SET metode_pembayaran = ? WHERE id = ?")->execute([$method, $booking_id]);
        
        echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil', 'e_voucher' => $res['voucher']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Pembayaran gagal atau booking sudah lunas']);
    }
}

function handleGetTransactions($pdo) {
    $stmt = $pdo->query("
        SELECT t.*, u.name, u.email 
        FROM transactions t 
        JOIN users u ON t.customer_id = u.id 
        ORDER BY t.created_at DESC
    ");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function handleGetCustomers($pdo) {
    $stmt = $pdo->query("SELECT id, name, email, phone, id_number, joined_at FROM users WHERE role = 'customer' ORDER BY joined_at DESC");
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
}

function handleGetStats($pdo) {
    $data = [];
    $data['total_revenue'] = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE status = 'SUCCESS'")->fetchColumn();
    $data['total_bookings'] = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $data['total_customers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $data['total_rooms'] = $pdo->query("SELECT SUM(stock) FROM rooms WHERE is_active = 1")->fetchColumn();
    
    echo json_encode(['success' => true, 'data' => $data]);
}
?>