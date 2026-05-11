<?php
require_once 'config.php';

$action = $_REQUEST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;

switch ($action) {
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
                'id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']
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
            'id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']
        ]]);
        break;

    case 'get_rooms':
        $stmt = $pdo->query("SELECT id, type, price_per_night, description, emoji FROM rooms WHERE is_active = 1");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
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
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM bookings WHERE room_unit_id = ? AND payment_status = 'paid' AND checkin_date < ? AND checkout_date > ?");
            $stmt->execute([$u['id'], $checkout, $checkin]);
            $result[] = ['unit_id' => $u['id'], 'room_number' => $u['room_number'], 'available' => $stmt->fetch()['cnt'] == 0];
        }
        echo json_encode(['success' => true, 'data' => $result]);
        break;

    case 'create_booking':
        $user = authenticate($pdo);
        $unitId   = $input['room_unit_id'] ?? '';
        $checkin  = $input['checkin_date'] ?? '';
        $checkout = $input['checkout_date'] ?? '';
        $guests   = (int)($input['guests'] ?? 1);
        if (!$unitId || !$checkin || !$checkout) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']); break;
        }
        $p = $pdo->prepare("SELECT r.price_per_night FROM room_units u JOIN rooms r ON u.room_id = r.id WHERE u.id = ?");
        $p->execute([$unitId]);
        $price = $p->fetch();
        if (!$price) { echo json_encode(['success' => false, 'message' => 'Kamar tidak valid']); break; }
        $nights = (strtotime($checkout) - strtotime($checkin)) / 86400;
        $total  = $nights * $price['price_per_night'];
        $bid    = 'BK' . time();
        $pdo->prepare("INSERT INTO bookings (id, customer_id, room_unit_id, checkin_date, checkout_date, nights, guests, total_price) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$bid, $user['id'], $unitId, $checkin, $checkout, $nights, $guests, $total]);
        echo json_encode(['success' => true, 'booking_id' => $bid, 'total_price' => $total]);
        break;

    case 'get_bookings':
        $user = authenticate($pdo);
        $stmt = $pdo->prepare("SELECT b.*, u.room_number, r.type as room_type FROM bookings b JOIN room_units u ON b.room_unit_id = u.id JOIN rooms r ON u.room_id = r.id WHERE b.customer_id = ? ORDER BY b.created_at DESC");
        $stmt->execute([$user['id']]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'process_payment':
        $user = authenticate($pdo);
        $bid = $input['booking_id'] ?? '';
        $mtd = $input['method'] ?? 'Transfer Bank';
        $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND payment_status = 'pending' AND customer_id = ?");
        $stmt->execute([$bid, $user['id']]);
        $book = $stmt->fetch();
        if (!$book) { echo json_encode(['success' => false, 'message' => 'Booking tidak ditemukan']); break; }
        $voucher = 'EV-' . strtoupper(substr($bid, -8));
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE bookings SET payment_status='paid', e_voucher=?, metode_pembayaran=? WHERE id=?")->execute([$voucher, $mtd, $bid]);
            $pdo->prepare("INSERT INTO transactions (id, booking_id, customer_id, amount, method, status, e_voucher) VALUES (?,?,?,?,?,'SUCCESS',?)")
                ->execute(['TRX'.time(), $bid, $user['id'], $book['total_price'], $mtd, $voucher]);
            $pdo->commit();
            echo json_encode(['success' => true, 'e_voucher' => $voucher]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Pembayaran gagal']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}