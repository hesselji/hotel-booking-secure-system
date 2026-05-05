<?php
require_once 'config.php';

// Ambil action dari parameter GET atau POST
$action = $_REQUEST['action'] ?? '';

// Data request (JSON atau form-data)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

// Routing berdasarkan action
switch ($action) {
    case 'login':
        handleLogin($pdo, $input);
        break;
    case 'logout':
        handleLogout();
        break;
    case 'get_stats':
        requireLogin();
        handleGetStats($pdo);
        break;
    case 'get_bookings':
        requireLogin();
        handleGetBookings($pdo);
        break;
    case 'create_booking':
        requireLogin();
        handleCreateBooking($pdo, $input);
        break;
    case 'update_booking':
        requireLogin();
        handleUpdateBooking($pdo, $input);
        break;
    case 'delete_booking':
        requireLogin();
        handleDeleteBooking($pdo, $input);
        break;
    case 'get_rooms':
        requireLogin();
        handleGetRooms($pdo);
        break;
    case 'add_room':
        requireLogin();
        handleAddRoom($pdo, $input);
        break;
    case 'update_room':
        requireLogin();
        handleUpdateRoom($pdo, $input);
        break;
    case 'delete_room':
        requireLogin();
        handleDeleteRoom($pdo, $input);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
exit;

// ========== FUNGSI HANDLER ==========
function handleLogin($pdo, $input) {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        return;
    }
    $stmt = $pdo->prepare("SELECT id, password_hash FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id'];
        echo json_encode(['success' => true, 'message' => 'Login successful']);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

function handleLogout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out']);
}

function handleGetStats($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status != 'cancelled'");
    $totalBookings = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM rooms");
    $totalRooms = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE booking_status IN ('confirmed','checked_in','checked_out')");
    $revenue = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'");
    $availableRooms = $stmt->fetchColumn();
    echo json_encode([
        'success' => true,
        'data' => [
            'totalBookings' => (int)$totalBookings,
            'totalRooms' => (int)$totalRooms,
            'revenue' => (float)$revenue,
            'availableRooms' => (int)$availableRooms
        ]
    ]);
}

function handleGetBookings($pdo) {
    $stmt = $pdo->query("
        SELECT b.*, r.room_number, r.room_type 
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        ORDER BY b.check_in_date DESC
    ");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $bookings]);
}

function handleCreateBooking($pdo, $input) {
    $customer_name = trim($input['customer_name'] ?? '');
    $customer_email = trim($input['customer_email'] ?? '');
    $customer_phone = trim($input['customer_phone'] ?? '');
    $room_id = (int)($input['room_id'] ?? 0);
    $check_in = $input['check_in_date'] ?? '';
    $check_out = $input['check_out_date'] ?? '';
    if (empty($customer_name) || empty($customer_email) || empty($customer_phone) || !$room_id || !$check_in || !$check_out) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        return;
    }
    $stmt = $pdo->prepare("SELECT price_per_night FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room = $stmt->fetch();
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Invalid room']);
        return;
    }
    $nights = (strtotime($check_out) - strtotime($check_in)) / (60*60*24);
    if ($nights <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid dates']);
        return;
    }
    $total_price = $nights * $room['price_per_night'];
    $stmt = $pdo->prepare("
        INSERT INTO bookings (customer_name, customer_email, customer_phone, room_id, check_in_date, check_out_date, total_price, booking_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed')
    ");
    $success = $stmt->execute([$customer_name, $customer_email, $customer_phone, $room_id, $check_in, $check_out, $total_price]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Booking created' : 'Creation failed']);
}

function handleUpdateBooking($pdo, $input) {
    $booking_id = (int)($input['booking_id'] ?? 0);
    $new_status = $input['status'] ?? '';
    if (!$booking_id || !in_array($new_status, ['confirmed','checked_in','checked_out','cancelled'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }
    $stmt = $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE id = ?");
    $success = $stmt->execute([$new_status, $booking_id]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Status updated' : 'Update failed']);
}

function handleDeleteBooking($pdo, $input) {
    $booking_id = (int)($input['booking_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $success = $stmt->execute([$booking_id]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Booking deleted' : 'Delete failed']);
}

function handleGetRooms($pdo) {
    $stmt = $pdo->query("SELECT * FROM rooms ORDER BY room_number");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $rooms]);
}

function handleAddRoom($pdo, $input) {
    $room_number = trim($input['room_number'] ?? '');
    $room_type = $input['room_type'] ?? '';
    $price = (float)($input['price_per_night'] ?? 0);
    $status = $input['status'] ?? 'available';
    $description = trim($input['description'] ?? '');
    if (!$room_number || !$room_type || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Room number, type and valid price required']);
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO rooms (room_number, room_type, price_per_night, status, description) VALUES (?, ?, ?, ?, ?)");
    $success = $stmt->execute([$room_number, $room_type, $price, $status, $description]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Room added' : 'Add failed']);
}

function handleUpdateRoom($pdo, $input) {
    $room_id = (int)($input['id'] ?? 0);
    $room_number = trim($input['room_number'] ?? '');
    $room_type = $input['room_type'] ?? '';
    $price = (float)($input['price_per_night'] ?? 0);
    $status = $input['status'] ?? 'available';
    $description = trim($input['description'] ?? '');
    if (!$room_id || !$room_number || !$room_type || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid room data']);
        return;
    }
    $stmt = $pdo->prepare("UPDATE rooms SET room_number = ?, room_type = ?, price_per_night = ?, status = ?, description = ? WHERE id = ?");
    $success = $stmt->execute([$room_number, $room_type, $price, $status, $description, $room_id]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Room updated' : 'Update failed']);
}

function handleDeleteRoom($pdo, $input) {
    $room_id = (int)($input['room_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
    $success = $stmt->execute([$room_id]);
    echo json_encode(['success' => $success, 'message' => $success ? 'Room deleted' : 'Delete failed']);
}
?>