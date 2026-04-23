<?php
/**
 * Platinum Horizon Hotel
 * Backend API - api.php
 * Letakkan file ini di folder yang sama dengan index.html
 * Jalankan dengan: php -S localhost:8000
 */

// ============================================================
// KONFIGURASI DATABASE
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'platinum_horizon');
define('DB_USER', 'root');       // Ganti sesuai username MySQL Anda
define('DB_PASS', '');           // Ganti sesuai password MySQL Anda
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// CORS & HEADERS
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================
// KONEKSI DATABASE
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            sendError(500, 'Koneksi database gagal: ' . $e->getMessage());
            exit;
        }
    }
    return $pdo;
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function sendJSON($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function getInput(): array {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return $json ?? array_merge($_GET, $_POST);
}

function generateBookingCode(): string {
    $db = getDB();
    do {
        $code = 'PHR-' . strtoupper(substr(uniqid(), -6)) . rand(10, 99);
        $stmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE booking_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn() > 0);
    return $code;
}

// ============================================================
// ROUTER
// ============================================================
$method = $_SERVER['REQUEST_METHOD'];
$path   = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts  = explode('/', $path);

// Hapus prefix jika ada (misal: api/login -> login)
$action = array_pop($parts);

switch ($action) {

    // ----------------------------------------------------------
    // POST /login
    // ----------------------------------------------------------
    case 'login':
        if ($method !== 'POST') sendError(405, 'Method tidak diizinkan');
        $input = getInput();
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');
        $role     = trim($input['role'] ?? '');

        if (!$username || !$password || !$role) {
            sendError(400, 'Username, password, dan role wajib diisi');
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND password = MD5(?) AND role = ?");
        $stmt->execute([$username, $password, $role]);
        $user = $stmt->fetch();

        if (!$user) {
            sendError(401, 'Username, password, atau role tidak sesuai');
        }

        unset($user['password']);
        sendJSON(['user' => $user, 'token' => base64_encode($user['username'] . ':' . time())]);
        break;

    // ----------------------------------------------------------
    // GET /rooms
    // ----------------------------------------------------------
    case 'rooms':
        if ($method !== 'GET') sendError(405, 'Method tidak diizinkan');
        $db   = getDB();
        $stmt = $db->query("SELECT * FROM rooms ORDER BY price_per_night ASC");
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['facilities'] = json_decode($r['facilities'], true) ?? [];
        }
        sendJSON($rows);
        break;

    // ----------------------------------------------------------
    // GET /bookings  (admin: semua | guest: filter by email)
    // GET /bookings?email=xxx
    // ----------------------------------------------------------
    case 'bookings':
        if ($method === 'GET') {
            $db = getDB();
            $email = $_GET['email'] ?? null;
            if ($email) {
                $stmt = $db->prepare("SELECT b.*, r.icon, r.gradient_class FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.guest_email = ? ORDER BY b.booking_date DESC");
                $stmt->execute([$email]);
            } else {
                $stmt = $db->query("SELECT b.*, r.icon, r.gradient_class FROM bookings b JOIN rooms r ON b.room_id = r.id ORDER BY b.booking_date DESC");
            }
            sendJSON($stmt->fetchAll());
        }

        // POST /bookings - buat reservasi baru
        elseif ($method === 'POST') {
            $input = getInput();
            $required = ['guest_name','guest_email','guest_phone','guest_id_number','room_id','check_in_date','check_out_date','guest_count'];
            foreach ($required as $f) {
                if (empty($input[$f])) sendError(400, "Field '$f' wajib diisi");
            }

            $db = getDB();

            // Ambil data kamar
            $stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
            $stmt->execute([$input['room_id']]);
            $room = $stmt->fetch();
            if (!$room) sendError(404, 'Kamar tidak ditemukan');
            if ($room['available_rooms'] < 1) sendError(409, 'Kamar tidak tersedia saat ini');

            // Hitung malam
            $checkin  = new DateTime($input['check_in_date']);
            $checkout = new DateTime($input['check_out_date']);
            $nights   = (int)$checkin->diff($checkout)->days;
            if ($nights < 1) sendError(400, 'Tanggal check-out harus setelah check-in');

            $total = $room['price_per_night'] * $nights;
            $code  = generateBookingCode();

            // Simpan booking
            $stmt = $db->prepare("
                INSERT INTO bookings 
                (booking_code, guest_name, guest_email, guest_phone, guest_id_number,
                 room_id, room_type, check_in_date, check_out_date, nights, guest_count,
                 price_per_night, total_amount, special_request, status, booked_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $code,
                $input['guest_name'],
                $input['guest_email'],
                $input['guest_phone'],
                $input['guest_id_number'],
                $input['room_id'],
                $room['type_name'],
                $input['check_in_date'],
                $input['check_out_date'],
                $nights,
                (int)$input['guest_count'],
                $room['price_per_night'],
                $total,
                $input['special_request'] ?? null,
                'confirmed',
                $input['booked_by'] ?? 'guest'
            ]);

            $bookingId = $db->lastInsertId();

            // Kurangi kamar tersedia
            $db->prepare("UPDATE rooms SET available_rooms = available_rooms - 1 WHERE id = ?")->execute([$input['room_id']]);

            sendJSON(['booking_id' => $bookingId, 'booking_code' => $code, 'total' => $total], 201);
        }
        else {
            sendError(405, 'Method tidak diizinkan');
        }
        break;

    // ----------------------------------------------------------
    // GET /booking/{id}
    // ----------------------------------------------------------
    case 'booking':
        $id = $_GET['id'] ?? null;
        if (!$id) sendError(400, 'ID booking diperlukan');
        $db   = getDB();
        $stmt = $db->prepare("SELECT b.*, r.facilities, r.icon, r.gradient_class FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) sendError(404, 'Booking tidak ditemukan');
        $row['facilities'] = json_decode($row['facilities'], true) ?? [];
        sendJSON($row);
        break;

    // ----------------------------------------------------------
    // PUT /checkin  { booking_id }
    // ----------------------------------------------------------
    case 'checkin':
        if ($method !== 'PUT' && $method !== 'POST') sendError(405, 'Method tidak diizinkan');
        $input = getInput();
        $id = $input['booking_id'] ?? null;
        if (!$id) sendError(400, 'booking_id diperlukan');

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        $booking = $stmt->fetch();
        if (!$booking) sendError(404, 'Booking tidak ditemukan');
        if ($booking['status'] !== 'confirmed') sendError(409, 'Status booking tidak memungkinkan check-in');

        $db->prepare("UPDATE bookings SET status='checkedin', check_in_time=NOW() WHERE id=?")->execute([$id]);
        sendJSON(['message' => 'Check-in berhasil', 'check_in_time' => date('Y-m-d H:i:s')]);
        break;

    // ----------------------------------------------------------
    // PUT /checkout  { booking_id }
    // ----------------------------------------------------------
    case 'checkout':
        if ($method !== 'PUT' && $method !== 'POST') sendError(405, 'Method tidak diizinkan');
        $input = getInput();
        $id = $input['booking_id'] ?? null;
        if (!$id) sendError(400, 'booking_id diperlukan');

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        $booking = $stmt->fetch();
        if (!$booking) sendError(404, 'Booking tidak ditemukan');
        if ($booking['status'] !== 'checkedin') sendError(409, 'Tamu belum check-in');

        $db->prepare("UPDATE bookings SET status='checkedout', check_out_time=NOW() WHERE id=?")->execute([$id]);
        $db->prepare("UPDATE rooms SET available_rooms = available_rooms + 1 WHERE id=?")->execute([$booking['room_id']]);

        sendJSON(['message' => 'Check-out berhasil']);
        break;

    // ----------------------------------------------------------
    // DELETE /cancel  { booking_id }
    // ----------------------------------------------------------
    case 'cancel':
        if ($method !== 'DELETE' && $method !== 'POST') sendError(405, 'Method tidak diizinkan');
        $input = getInput();
        $id = $input['booking_id'] ?? null;
        if (!$id) sendError(400, 'booking_id diperlukan');

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$id]);
        $booking = $stmt->fetch();
        if (!$booking) sendError(404, 'Booking tidak ditemukan');
        if (in_array($booking['status'], ['checkedin','checkedout'])) sendError(409, 'Tidak dapat membatalkan booking yang sudah check-in/out');

        $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=?")->execute([$id]);
        if ($booking['status'] === 'confirmed') {
            $db->prepare("UPDATE rooms SET available_rooms = available_rooms + 1 WHERE id=?")->execute([$booking['room_id']]);
        }

        sendJSON(['message' => 'Reservasi berhasil dibatalkan']);
        break;

    // ----------------------------------------------------------
    // GET /dashboard
    // ----------------------------------------------------------
    case 'dashboard':
        if ($method !== 'GET') sendError(405, 'Method tidak diizinkan');
        $db = getDB();

        $totalRooms     = $db->query("SELECT SUM(total_rooms) FROM rooms")->fetchColumn();
        $availableRooms = $db->query("SELECT SUM(available_rooms) FROM rooms")->fetchColumn();
        $totalBookings  = $db->query("SELECT COUNT(*) FROM bookings WHERE status != 'cancelled'")->fetchColumn();
        $checkedIn      = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'checkedin'")->fetchColumn();
        $totalRevenue   = $db->query("SELECT SUM(total_amount) FROM bookings WHERE status IN ('confirmed','checkedin','checkedout')")->fetchColumn();
        $todayBookings  = $db->query("SELECT COUNT(*) FROM bookings WHERE DATE(booking_date) = CURDATE()")->fetchColumn();

        sendJSON([
            'total_rooms'     => (int)$totalRooms,
            'available_rooms' => (int)$availableRooms,
            'occupied_rooms'  => (int)$totalRooms - (int)$availableRooms,
            'total_bookings'  => (int)$totalBookings,
            'checked_in'      => (int)$checkedIn,
            'total_revenue'   => (float)$totalRevenue,
            'today_bookings'  => (int)$todayBookings,
        ]);
        break;

    default:
        sendError(404, 'Endpoint tidak ditemukan');
}
?>