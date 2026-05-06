// ==================== KONFIGURASI ====================
const API_URL = '../../backend/api.php';

// Fungsi umum untuk memanggil API
async function apiCall(action, method = 'POST', body = null) {
    const url = `${API_URL}?action=${action}`;
    const options = {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include'
    };
    if (body && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(body);
    }
    try {
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('API Call Error:', error);
        return { success: false, message: 'Network error' };
    }
}

// ==================== HALAMAN LOGIN (index.html) ====================
if (window.location.pathname.includes('index.html') || window.location.pathname.endsWith('/frontend/')) {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('loginError');
            const loginBtn = loginForm.querySelector('.btn-login');

            errorDiv.style.display = 'none';
            const originalText = loginBtn.innerHTML;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Authenticating...';
            loginBtn.disabled = true;

            const result = await apiCall('login', 'POST', { username, password });

            if (result.success) {
                window.location.href = 'dashboard.html';
            } else {
                errorDiv.style.display = 'block';
                errorDiv.innerText = result.message || 'Login failed';
                loginBtn.innerHTML = originalText;
                loginBtn.disabled = false;
            }
        });
    }

    // Toggle password visibility
    const toggleBtn = document.getElementById('togglePassBtn');
    const passwordInput = document.getElementById('password');
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', () => {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            toggleBtn.querySelector('i').classList.toggle('fa-eye');
            toggleBtn.querySelector('i').classList.toggle('fa-eye-slash');
        });
    }
}

// ==================== HALAMAN DASHBOARD (dashboard.html) ====================
if (window.location.pathname.includes('dashboard.html')) {
    let roomsCache = [];

    // --- Cek autentikasi (redirect jika belum login)
    (async function checkAuth() {
        const stats = await apiCall('get_stats', 'GET');
        if (!stats.success && stats.message === 'Unauthorized') {
            window.location.href = 'index.html';
        }
    })();

    // --- Load Statistik
    async function loadStats() {
        const stats = await apiCall('get_stats', 'GET');
        if (stats.success && stats.data) {
            const grid = document.getElementById('statsGrid');
            grid.innerHTML = `
                <div class="stat-card"><i class="fas fa-calendar-check"></i><h3>${stats.data.totalBookings}</h3><p>Total Bookings</p></div>
                <div class="stat-card"><i class="fas fa-door-open"></i><h3>${stats.data.totalRooms}</h3><p>Rooms</p></div>
                <div class="stat-card"><i class="fas fa-dollar-sign"></i><h3>$${stats.data.revenue}</h3><p>Revenue</p></div>
                <div class="stat-card"><i class="fas fa-bed"></i><h3>${stats.data.availableRooms}</h3><p>Available Rooms</p></div>
            `;
        } else {
            document.getElementById('statsGrid').innerHTML = '<div class="stat-card">Failed to load stats</div>';
        }
    }

    // --- Load Bookings
    async function loadBookings() {
        const res = await apiCall('get_bookings', 'GET');
        if (res.success && res.data) {
            const tbody = document.querySelector('#bookingsTable tbody');
            if (!tbody) return;
            tbody.innerHTML = res.data.map(b => `
                <tr>
                    <td>${b.id}</td>
                    <td>${escapeHtml(b.customer_name)}<br><small>${escapeHtml(b.customer_email)}</small></td>
                    <td>${escapeHtml(b.room_number)} (${escapeHtml(b.room_type)})</td>
                    <td>${b.check_in_date}</td>
                    <td>${b.check_out_date}</td>
                    <td>$${parseFloat(b.total_price).toFixed(2)}</td>
                    <td>
                        <select class="status-select" data-id="${b.id}">
                            <option value="confirmed" ${b.booking_status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                            <option value="checked_in" ${b.booking_status === 'checked_in' ? 'selected' : ''}>Checked In</option>
                            <option value="checked_out" ${b.booking_status === 'checked_out' ? 'selected' : ''}>Checked Out</option>
                            <option value="cancelled" ${b.booking_status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                    </td>
                    <td><button class="btn-danger btn-sm delete-booking" data-id="${b.id}">Delete</button></td>
                </tr>
            `).join('');
            // Event listeners
            document.querySelectorAll('.status-select').forEach(sel => {
                sel.removeEventListener('change', handleStatusChange);
                sel.addEventListener('change', handleStatusChange);
            });
            document.querySelectorAll('.delete-booking').forEach(btn => {
                btn.removeEventListener('click', handleDeleteBooking);
                btn.addEventListener('click', handleDeleteBooking);
            });
        } else {
            console.error('Failed to load bookings', res);
        }
    }

    async function handleStatusChange(e) {
        const bookingId = e.target.dataset.id;
        const newStatus = e.target.value;
        const res = await apiCall('update_booking', 'POST', { booking_id: bookingId, status: newStatus });
        if (!res.success) {
            alert(res.message || 'Failed to update status');
            loadBookings(); // reload to revert
        }
    }

    async function handleDeleteBooking(e) {
        if (!confirm('Delete this booking permanently?')) return;
        const bookingId = e.target.dataset.id;
        const res = await apiCall('delete_booking', 'POST', { booking_id: bookingId });
        if (res.success) {
            loadBookings();
            loadStats(); // refresh stats
        } else {
            alert(res.message || 'Failed to delete');
        }
    }

    // --- Load Rooms
    async function loadRooms() {
        const res = await apiCall('get_rooms', 'GET');
        if (res.success && res.data) {
            roomsCache = res.data;
            const tbody = document.querySelector('#roomsTable tbody');
            if (!tbody) return;
            tbody.innerHTML = roomsCache.map(r => `
                <tr>
                    <td>${r.id}</td>
                    <td>${escapeHtml(r.room_number)}</td>
                    <td>${escapeHtml(r.room_type)}</td>
                    <td>$${parseFloat(r.price_per_night).toFixed(2)}</td>
                    <td><span class="room-status ${r.status}">${r.status}</span></td>
                    <td>${escapeHtml(r.description || '-')}</td>
                    <td>
                        <button class="btn-sm edit-room" data-id="${r.id}">Edit</button>
                        <button class="btn-danger btn-sm delete-room" data-id="${r.id}">Delete</button>
                    </td>
                </tr>
            `).join('');
            document.querySelectorAll('.edit-room').forEach(btn => {
                btn.removeEventListener('click', handleEditRoom);
                btn.addEventListener('click', handleEditRoom);
            });
            document.querySelectorAll('.delete-room').forEach(btn => {
                btn.removeEventListener('click', handleDeleteRoom);
                btn.addEventListener('click', handleDeleteRoom);
            });
        }
    }

    async function handleEditRoom(e) {
        const roomId = e.target.dataset.id;
        const room = roomsCache.find(r => r.id == roomId);
        if (room) openRoomModal(room);
    }

    async function handleDeleteRoom(e) {
        if (!confirm('Delete this room permanently? All related bookings will be deleted due to foreign key cascade.')) return;
        const roomId = e.target.dataset.id;
        const res = await apiCall('delete_room', 'POST', { room_id: roomId });
        if (res.success) {
            loadRooms();
            loadStats();
        } else {
            alert(res.message || 'Failed to delete room');
        }
    }

    // --- Modal untuk Booking
    async function openAddBookingModal() {
        // Fetch rooms untuk dropdown
        const roomsRes = await apiCall('get_rooms', 'GET');
        if (!roomsRes.success) {
            alert('Cannot load rooms');
            return;
        }
        const rooms = roomsRes.data;
        const form = document.getElementById('bookingForm');
        if (!form) return;
        form.innerHTML = `
            <div class="form-group"><label>Customer Name</label><input type="text" id="cust_name" required></div>
            <div class="form-group"><label>Email</label><input type="email" id="cust_email" required></div>
            <div class="form-group"><label>Phone</label><input type="text" id="cust_phone" required></div>
            <div class="form-group"><label>Room</label><select id="room_id">${rooms.map(r => `<option value="${r.id}">${escapeHtml(r.room_number)} - ${escapeHtml(r.room_type)} ($${r.price_per_night}/night)</option>`).join('')}</select></div>
            <div class="form-group"><label>Check In</label><input type="date" id="check_in" required></div>
            <div class="form-group"><label>Check Out</label><input type="date" id="check_out" required></div>
            <button type="submit" class="btn-primary">Create Booking</button>
        `;
        const modal = document.getElementById('bookingModal');
        modal.style.display = 'flex';
        form.onsubmit = async (e) => {
            e.preventDefault();
            const payload = {
                customer_name: document.getElementById('cust_name').value,
                customer_email: document.getElementById('cust_email').value,
                customer_phone: document.getElementById('cust_phone').value,
                room_id: document.getElementById('room_id').value,
                check_in_date: document.getElementById('check_in').value,
                check_out_date: document.getElementById('check_out').value
            };
            const res = await apiCall('create_booking', 'POST', payload);
            if (res.success) {
                modal.style.display = 'none';
                loadBookings();
                loadStats();
            } else {
                alert(res.message || 'Failed to create booking');
            }
        };
    }

    // --- Modal untuk Room
    function openRoomModal(room = null) {
        const form = document.getElementById('roomForm');
        if (!form) return;
        form.innerHTML = `
            <input type="hidden" id="room_id" value="${room ? room.id : ''}">
            <div class="form-group"><label>Room Number</label><input type="text" id="room_number" value="${room ? escapeHtml(room.room_number) : ''}" required></div>
            <div class="form-group"><label>Room Type</label><select id="room_type"><option ${room && room.room_type === 'Single' ? 'selected' : ''}>Single</option><option ${room && room.room_type === 'Double' ? 'selected' : ''}>Double</option><option ${room && room.room_type === 'Suite' ? 'selected' : ''}>Suite</option></select></div>
            <div class="form-group"><label>Price per Night ($)</label><input type="number" step="0.01" id="price_per_night" value="${room ? room.price_per_night : ''}" required></div>
            <div class="form-group"><label>Status</label><select id="status"><option value="available" ${room && room.status === 'available' ? 'selected' : ''}>Available</option><option value="booked" ${room && room.status === 'booked' ? 'selected' : ''}>Booked</option><option value="maintenance" ${room && room.status === 'maintenance' ? 'selected' : ''}>Maintenance</option></select></div>
            <div class="form-group"><label>Description</label><textarea id="description">${room ? escapeHtml(room.description || '') : ''}</textarea></div>
            <button type="submit" class="btn-primary">${room ? 'Update Room' : 'Add Room'}</button>
        `;
        const modal = document.getElementById('roomModal');
        modal.style.display = 'flex';
        form.onsubmit = async (e) => {
            e.preventDefault();
            const roomId = document.getElementById('room_id').value;
            const payload = {
                room_number: document.getElementById('room_number').value,
                room_type: document.getElementById('room_type').value,
                price_per_night: parseFloat(document.getElementById('price_per_night').value),
                status: document.getElementById('status').value,
                description: document.getElementById('description').value
            };
            if (roomId) payload.id = parseInt(roomId);
            const action = roomId ? 'update_room' : 'add_room';
            const res = await apiCall(action, 'POST', payload);
            if (res.success) {
                modal.style.display = 'none';
                loadRooms();
                loadStats();
            } else {
                alert(res.message || 'Operation failed');
            }
        };
    }

    // --- Logout
    async function logout() {
        await apiCall('logout', 'POST');
        window.location.href = 'index.html';
    }

    // --- Tab Switching
    function initTabs() {
        const tabs = document.querySelectorAll('.nav-btn');
        tabs.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                if (tab === 'logout') {
                    logout();
                    return;
                }
                document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                document.getElementById(`${tab}Tab`).classList.add('active');
                document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                if (tab === 'dashboard') loadStats();
                else if (tab === 'bookings') loadBookings();
                else if (tab === 'rooms') loadRooms();
            });
        });
    }

    // --- Helper: escape HTML
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
            return c;
        });
    }

    // --- Event listeners for add buttons & modals close
    document.getElementById('addBookingBtn')?.addEventListener('click', openAddBookingModal);
    document.getElementById('addRoomBtn')?.addEventListener('click', () => openRoomModal(null));
    document.querySelectorAll('.close, .modal').forEach(el => {
        el.addEventListener('click', function(e) {
            if (e.target.classList.contains('close') || e.target.classList.contains('modal')) {
                document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
            }
        });
    });

    // Initial load
    initTabs();
    loadStats(); // default tab dashboard
}