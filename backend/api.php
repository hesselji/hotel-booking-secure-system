<?php
require_once 'config.php';

$action = $_REQUEST['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if ($action !== 'midtrans_webhook') {
    validateCsrfToken();
}

switch ($action) {
    // ================= AUTENTIKASI =================
    case 'login':
        $email = trim($input['email'] ?? '');
        $pass  = $input['password'] ?? '';

        // 1. Validasi input login
        if (empty($email) || empty($pass)) {
            echo json_encode(['success' => false, 'message' => 'Email dan password wajib diisi']);
            break;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
            break;
        }

        // 2. Rate limiting login
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $checkAttempts = $pdo->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE email = ? 
            AND ip_address = ? 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $checkAttempts->execute([$email, $ip]);

        if ($checkAttempts->fetchColumn() >= 5) {
            securityLog('WARN', 'RATE_LIMIT_BLOCKED', null, [
                'email' => $email,
                'reason' => 'Too many failed login attempts'
            ]);

            http_response_code(429);
            echo json_encode([
                'success' => false, 
                'message' => 'Terlalu banyak percobaan login. Coba lagi nanti.'
            ]);
            break;
        }

        // 3. Cek user berdasarkan email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // 4. Jika password benar, buat token session
        if ($user && password_verify($pass, $user['password'])) {
            $token = bin2hex(random_bytes(32));

            $pdo->prepare("
                INSERT INTO sessions (token, user_id, expires_at) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ")->execute([$token, $user['id']]);

            $csrfToken = bin2hex(random_bytes(32));

            setAuthCookie($token);
            setCsrfCookie($csrfToken);

            securityLog('INFO', 'LOGIN_SUCCESS', $user['id'], [
                'email' => $email,
                'role'  => $user['role']
            ]);

            echo json_encode([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id'        => $user['id'],
                    'name'      => $user['name'],
                    'email'     => $user['email'],
                    'role'      => $user['role'],
                    'phone'     => $user['phone'],
                    'id_number' => $user['id_number'],
                    'joined_at' => $user['joined_at']
                ]
            ]);
        } else {
            // 5. Catat percobaan login gagal
            $pdo->prepare("
                INSERT INTO login_attempts (email, ip_address) 
                VALUES (?, ?)
            ")->execute([$email, $ip]);

            securityLog('WARN', 'LOGIN_FAILED', null, [
                'email' => $email
            ]);

            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'message' => 'Email atau password salah'
            ]);
        }
        break;

    case 'register':
        $name  = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $pass  = $input['password'] ?? '';
        $phone = trim($input['phone'] ?? '');
        $idNum = trim($input['id_number'] ?? '');

        if (empty($name) || empty($email) || empty($pass)) {
            echo json_encode(['success' => false, 'message' => 'Nama, email, dan password wajib diisi']);
            break;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
            break;
        }

        if (strlen($pass) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password minimal 8 karakter']);
            break;
        }

        if (!empty($phone) && !preg_match('/^[0-9+\-\s]{8,20}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Format nomor telepon tidak valid']);
            break;
        }

        if (!empty($idNum) && !preg_match('/^[0-9]{8,30}$/', $idNum)) {
            echo json_encode(['success' => false, 'message' => 'Nomor identitas tidak valid']);
            break;
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $id   = 'u_' . bin2hex(random_bytes(8));

        try {
            $pdo->prepare("INSERT INTO users (id, name, email, password, phone, id_number) VALUES (?,?,?,?,?,?)")
                ->execute([$id, $name, $email, $hash, $phone, $idNum]);

            echo json_encode(['success' => true, 'message' => 'Registrasi berhasil']);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getCode() == 23000 ? 'Email sudah terdaftar' : 'Registrasi gagal'
            ]);
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
    
    case 'logout':
        $token = $_COOKIE['auth_token'] ?? '';

        if (!empty($token)) {
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE token = ?");
            $stmt->execute([$token]);
        }

        clearAuthCookies();

        securityLog('INFO', 'LOGOUT', null, [
            'message' => 'User logged out'
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
        break;

    // ================= KAMAR & UNIT =================
    case 'get_rooms':
        // Menambahkan GROUP_CONCAT agar nomor kamar (101, 102) ikut ditarik dan dikirim ke Admin
        $stmt = $pdo->query("
            SELECT r.id, r.type, r.price_per_night, r.description, r.emoji, r.is_active, 
                   COUNT(u.id) AS total_units,
                   GROUP_CONCAT(u.room_number SEPARATOR ', ') as room_numbers
            FROM rooms r 
            LEFT JOIN room_units u ON r.id = u.room_id AND u.is_active = 1 
            GROUP BY r.id
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'add_room':
        $user = authenticate($pdo);

        if ($user['role'] !== 'admin') {
            securityLog('WARN', 'ACCESS_DENIED', $user['id'], [
                'action' => $action,
                'required_role' => 'admin',
                'user_role' => $user['role']
            ]);

            echo json_encode([
                'success' => false,
                'message' => 'Akses ditolak'
            ]);
            break;
        }
        
        $id = 'rm_' . bin2hex(random_bytes(4));
        $type = trim($input['type'] ?? '');
        $price = $input['price_per_night'] ?? 0;
        $desc = trim($input['description'] ?? '');
        $emoji = $input['emoji'] ?? '🛏️';
        $roomNumbers = $input['room_numbers'] ?? ''; // Format: "101, 102, 103"

        try {
            $pdo->beginTransaction();
            
            // 1. Buat Induk Kamarnya
            $pdo->prepare("INSERT INTO rooms (id, type, price_per_night, description, emoji) VALUES (?,?,?,?,?)")
                ->execute([$id, $type, $price, $desc, $emoji]);
            
            // 2. Pecah dan masukkan nomor kamarnya satu per satu
            if (!empty($roomNumbers)) {
                $numbers = array_map('trim', explode(',', $roomNumbers));
                foreach ($numbers as $num) {
                    if (!empty($num)) {
                        $unitId = 'ru_' . bin2hex(random_bytes(4));
                        $pdo->prepare("INSERT INTO room_units (id, room_id, room_number) VALUES (?,?,?)")
                            ->execute([$unitId, $id, $num]);
                    }
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Tipe Kamar dan Nomor Unit berhasil ditambahkan!']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            // Error 23000 adalah kode SQL untuk Duplicate Entry (Nomor Kembar)
            if ($e->getCode() == 23000) {
                echo json_encode(['success' => false, 'message' => 'Gagal: Ada nomor kamar yang sudah dipakai di tipe kamar lain!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data kamar.']);
            }
        }
        break;

    case 'update_room':
        $user = authenticate($pdo);

        if ($user['role'] !== 'admin') {
            securityLog('WARN', 'ACCESS_DENIED', $user['id'], [
                'action' => $action,
                'required_role' => 'admin',
                'user_role' => $user['role']
            ]);

            echo json_encode([
                'success' => false,
                'message' => 'Akses ditolak'
            ]);
            break;
        }
        
        $id = $input['id'] ?? '';
        $roomNumbers = $input['room_numbers'] ?? '';
        
        try {
            $pdo->beginTransaction();
            // 1. Update info kamar
            $pdo->prepare("UPDATE rooms SET type=?, price_per_night=?, description=?, emoji=?, is_active=? WHERE id=?")
                ->execute([$input['type'], $input['price_per_night'], $input['description'], $input['emoji'], $input['is_active'], $id]);
            
            // 2. Tambahkan nomor kamar baru jika ada yang diketik Admin
            if (!empty($roomNumbers)) {
                $numbers = array_map('trim', explode(',', $roomNumbers));
                foreach ($numbers as $num) {
                    if (!empty($num)) {
                        // Cek apakah nomor ini sudah ada di database
                        $check = $pdo->prepare("SELECT id FROM room_units WHERE room_number = ?");
                        $check->execute([$num]);
                        if (!$check->fetch()) {
                            // Jika nomor belum ada, tambahkan sebagai kamar baru
                            $unitId = 'ru_' . bin2hex(random_bytes(4));
                            $pdo->prepare("INSERT INTO room_units (id, room_id, room_number) VALUES (?,?,?)")
                                ->execute([$unitId, $id, $num]);
                        }
                    }
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                echo json_encode(['success' => false, 'message' => 'Gagal: Nomor kamar baru yang Anda ketik sudah ada di database!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan sistem.']);
            }
        }
        break;

    case 'delete_room':
        $user = authenticate($pdo);

        if ($user['role'] !== 'admin') {
            securityLog('WARN', 'ACCESS_DENIED', $user['id'], [
                'action' => $action,
                'required_role' => 'admin',
                'user_role' => $user['role']
            ]);

            echo json_encode([
                'success' => false,
                'message' => 'Akses ditolak'
            ]);
            break;
        }
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

        // Validasi input booking
        if (empty($unitId) || empty($checkin) || empty($checkout)) {
            echo json_encode([
                'success' => false,
                'message' => 'Data booking tidak lengkap'
            ]);
            break;
        }

        if (strtotime($checkin) === false || strtotime($checkout) === false) {
            echo json_encode([
                'success' => false,
                'message' => 'Format tanggal tidak valid'
            ]);
            break;
        }

        if (strtotime($checkin) >= strtotime($checkout)) {
            echo json_encode([
                'success' => false,
                'message' => 'Tanggal checkout harus setelah check-in'
            ]);
            break;
        }

        if ($guests < 1 || $guests > 10) {
            echo json_encode([
                'success' => false,
                'message' => 'Jumlah tamu tidak valid'
            ]);
            break;
        }

        // Panggil Stored Procedure
        $stmt = $pdo->prepare("CALL sp_create_secure_booking(?, ?, ?, ?, ?, @b_id, @t_price, @status)");
        $stmt->execute([$user['id'], $unitId, $checkin, $checkout, $guests]);
        $stmt->closeCursor();

        // Ambil Hasilnya
        $res = $pdo->query("SELECT @b_id AS booking_id, @t_price AS total_price, @status AS status")->fetch();

        if ($res['status'] === 'SUCCESS') {
            securityLog('INFO', 'BOOKING_CREATED', $user['id'], [
                'booking_id' => $res['booking_id'],
                'room_unit_id' => $unitId
            ]);
            
            echo json_encode([
                'success' => true,
                'booking_id' => $res['booking_id'],
                'total_price' => $res['total_price']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Maaf, kamar ini baru saja di-booking oleh tamu lain.'
            ]);
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
        
        // 1. Cek dulu statusnya di database
        $stmt = $pdo->prepare("SELECT payment_status FROM bookings WHERE id = ?");
        $stmt->execute([$bid]);
        $status = $stmt->fetchColumn();
        
        // 2. Jika sudah lunas, TOLAK!
        if ($status === 'paid') {
            echo json_encode(['success' => false, 'message' => 'Gagal: Booking yang sudah LUNAS tidak dapat dihapus!']);
            break;
        }
        
        // 3. Jika masih pending, izinkan hapus
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
            // Insert Transaksi (Sekarang memasukkan midtrans_id juga)
            $pdo->prepare("INSERT INTO transactions (id, midtrans_id, booking_id, customer_id, amount, method, status, e_voucher) VALUES (?,?,?,?,?,?,'SUCCESS',?)")
                ->execute(['TRX'.time(), $midtrans_id, $order_id, $customer_id, $gross_amount, $payment_type, $voucher]);
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

        if ($user['role'] !== 'admin') {
            securityLog('WARN', 'ACCESS_DENIED', $user['id'], [
                'action' => $action,
                'required_role' => 'admin',
                'user_role' => $user['role']
            ]);

            echo json_encode([
                'success' => false,
                'message' => 'Akses ditolak'
            ]);
            break;
        }
        $totalBookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
        // Menggunakan UPPER agar aman dari perbedaan huruf besar/kecil
        $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE UPPER(status) = 'SUCCESS'")->fetchColumn();
        $totalUnits = $pdo->query("SELECT COUNT(*) FROM room_units WHERE is_active = 1")->fetchColumn();
        $totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
        echo json_encode(['success' => true, 'data' => [
            'totalBookings' => $totalBookings, 'totalRevenue' => $totalRevenue, 'totalUnits' => $totalUnits, 'totalCustomers' => $totalCustomers
        ]]);
        break;

    case 'get_transactions':
        $user = authenticate($pdo);

        if ($user['role'] !== 'admin') {
            securityLog('WARN', 'ACCESS_DENIED', $user['id'], [
                'action' => $action,
                'required_role' => 'admin',
                'user_role' => $user['role']
            ]);

            echo json_encode([
                'success' => false,
                'message' => 'Akses ditolak'
            ]);
            break;
        }
        
        $stmt = $pdo->query("
            SELECT t.*, u.name as customer_name, u.email as customer_email 
            FROM transactions t 
            JOIN users u ON t.customer_id = u.id 
            WHERE UPPER(t.status) = 'SUCCESS' 
            ORDER BY t.id DESC 
            LIMIT 10
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;

    case 'get_customers':
        $user = authenticate($pdo);

        if ($user['role'] !== 'admin') {
            securityLog('WARN', 'ACCESS_DENIED', $user['id'], [
                'action' => $action,
                'required_role' => 'admin',
                'user_role' => $user['role']
            ]);

            echo json_encode([
                'success' => false,
                'message' => 'Akses ditolak'
            ]);
            break;
        }
        $stmt = $pdo->query("SELECT id, name, email, phone, id_number, joined_at 
                         FROM users 
                         WHERE role = 'customer' AND deleted_at IS NULL 
                         ORDER BY joined_at DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        break;
    
    case 'delete_customer':
        $user = authenticate($pdo);
        if ($user['role'] !== 'admin') { echo json_encode(['success' => false]); break; }
        
        $customerId = $input['customer_id'] ?? '';

        $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND role = 'customer'")
            ->execute([$customerId]);
            
        echo json_encode(['success' => true, 'message' => 'Pelanggan berhasil dihapus (Soft Delete)']);
        break;
    // ================= MIDTRANS INTEGRATION =================
    case 'get_snap_token':
        $user = authenticate($pdo);
        $booking_id = $input['booking_id'] ?? '';
        $total_price = $input['total_price'] ?? 0;
        
        $payload = [
            'transaction_details' => [
                'order_id' => $booking_id,
                'gross_amount' => (int) $total_price,
            ],
            'customer_details' => [
                'first_name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'] ?? ''
            ]
        ];

        $url = MIDTRANS_IS_PRODUCTION ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        $serverKey = MIDTRANS_SERVER_KEY . ':';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($serverKey)
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        if (isset($result['token'])) {
            echo json_encode(['success' => true, 'token' => $result['token']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mendapatkan token dari Midtrans', 'debug' => $result]);
        }
        break;

    case 'midtrans_webhook':
        // Midtrans akan mengirim data otomatis ke sini tanpa token login
        $notif = json_decode(file_get_contents('php://input'), true);
        if (!$notif) exit;

        $order_id = $notif['order_id'];
        $status_code = $notif['status_code'];
        $gross_amount = $notif['gross_amount'];
        $signature_key = $notif['signature_key'];
        $transaction_status = $notif['transaction_status'];
        $payment_type = $notif['payment_type'];
        $midtrans_id = $notif['transaction_id'];

        // 1. Verifikasi Keamanan (Pastikan benar-benar dari Midtrans)
        $my_signature = hash('sha512', $order_id . $status_code . $gross_amount . MIDTRANS_SERVER_KEY);
        if ($my_signature !== $signature_key) {
            http_response_code(403);
            exit('Invalid Signature');
        }

        // 2. Jika Lunas (Settlement/Capture), Eksekusi Stored Procedure
        if ($transaction_status == 'settlement' || $transaction_status == 'capture') {
            try {
                // Catat transaksi via database (Update manual karena Stored Procedure awal dirancang utk parameter beda)
                $pdo->beginTransaction();
                
                $voucher = 'EV-' . strtoupper(substr($order_id, -8));
                
                // Update Booking
                $stmt = $pdo->prepare("UPDATE bookings SET payment_status='paid', e_voucher=?, metode_pembayaran=? WHERE id=? AND payment_status='pending'");
                $stmt->execute([$voucher, $payment_type, $order_id]);
                
                // Cek apakah booking berhasil diupdate (jika baris berubah)
                if ($stmt->rowCount() > 0) {
                    // Ambil customer ID untuk log transaksi
                    $cust = $pdo->prepare("SELECT customer_id FROM bookings WHERE id=?");
                    $cust->execute([$order_id]);
                    $customer_id = $cust->fetchColumn();

                    // Insert Transaksi
                    // Insert Transaksi (Sekarang memasukkan midtrans_id juga)
                    $pdo->prepare("INSERT INTO transactions (id, midtrans_id, booking_id, customer_id, amount, method, status, e_voucher) VALUES (?,?,?,?,?,?,'SUCCESS',?)")
                        ->execute(['TRX'.time(), $midtrans_id, $order_id, $customer_id, $gross_amount, $payment_type, $voucher]);
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
        
        http_response_code(200);
        echo json_encode(['status' => 'OK']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}