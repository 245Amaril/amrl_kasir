<?php
// Mulai session di baris paling atas
session_start();

require_once 'config.php';
require_once 'models.php';
require_once 'views.php';

// Inisialisasi objek database dan model
$database = new Database();
$db = $database->getConnection();

$user = new User($db);
// Inisialisasi product sesuai role
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $product = new AdminProduct($db);
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'kasir') {
    $product = new KasirProduct($db);
} else {
    $product = new Product($db);
}
$order = new Order($db);

// --- Fungsi untuk menangani upload gambar ---
function handle_image_upload($file_input_name, $current_image = '') {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        // Hapus gambar lama jika ada gambar baru yang diupload
        if (!empty($current_image) && file_exists($target_dir . $current_image)) {
            unlink($target_dir . $current_image);
        }

        $image_name = uniqid() . '-' . basename($_FILES[$file_input_name]["name"]);
        $target_file = $target_dir . $image_name;

        if (move_uploaded_file($_FILES[$file_input_name]["tmp_name"], $target_file)) {
            return $image_name; // Sukses upload, kembalikan nama file baru
        } else {
            $_SESSION['error'] = "Gagal memindahkan file yang diupload. Pastikan folder 'uploads' dapat ditulisi (writable).";
            return $current_image; // Gagal upload, kembalikan nama file lama
        }
    }
    return $current_image; // Tidak ada file baru, kembalikan nama file lama
}

// --- Penanganan Aksi dari Form (POST Request) ---
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    switch ($action) {
        case 'login':
            if ($user->login($_POST['username'], $_POST['password'])) {
                header("Location: index.php?page=home");
            } else {
                $_SESSION['error'] = "Username atau password salah!";
                header("Location: index.php?page=login");
            }
            exit();

        case 'register':
            if ($_POST['password'] !== $_POST['confirm_password']) {
                $_SESSION['error'] = "Konfirmasi password tidak cocok!";
                header("Location: index.php?page=register");
            } elseif ($user->register($_POST['username'], $_POST['password'], $_POST['role'])) {
                $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
                header("Location: index.php?page=login");
            } else {
                $_SESSION['error'] = "Username sudah digunakan!";
                header("Location: index.php?page=register");
            }
            exit();
            
        case 'reset_password':
            $username = $_POST['username'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_new_password = $_POST['confirm_new_password'] ?? '';
            if ($new_password !== $confirm_new_password) {
                $_SESSION['error'] = "Konfirmasi password baru tidak cocok!";
                header("Location: index.php?page=reset");
            } elseif ($user->resetPassword($username, $new_password)) {
                $_SESSION['success'] = "Password berhasil direset. Silakan login.";
                header("Location: index.php?page=login");
            } else {
                $_SESSION['error'] = "Username tidak ditemukan atau gagal reset password.";
                header("Location: index.php?page=reset");
            }
            exit();

        case 'add_product':
            if ($_SESSION['role'] === 'admin') {
                $image_name = handle_image_upload('image');
                if ($product->create($_POST['name'], $_POST['price'], $_POST['stock'], $image_name)) {
                    $_SESSION['success'] = "Produk berhasil ditambahkan.";
                } else {
                     $_SESSION['error'] = "Gagal menambahkan produk.";
                }
            }
            header("Location: index.php?page=produk");
            exit();
            
        case 'edit_product':
            if ($_SESSION['role'] === 'admin') {
                $image_name = handle_image_upload('image', $_POST['current_image']);
                if ($product->update($_POST['id'], $_POST['name'], $_POST['price'], $_POST['stock'], $image_name)) {
                    $_SESSION['success'] = "Produk berhasil diperbarui.";
                } else {
                    $_SESSION['error'] = "Gagal memperbarui produk.";
                }
            }
            header("Location: index.php?page=produk");
            exit();
            
        case 'delete_product':
            if ($_SESSION['role'] === 'admin') {
                $product->delete($_POST['id']);
                $_SESSION['success'] = "Produk berhasil dihapus.";
            }
            header("Location: index.php?page=produk");
            exit();

        case 'move_to_trash':
            if ($_SESSION['role'] === 'admin') {
                $product->moveToTrash($_POST['id']);
                $_SESSION['success'] = "Produk dipindahkan ke tempat sampah.";
            }
            header("Location: index.php?page=produk");
            exit();
        case 'restore_product':
            if ($_SESSION['role'] === 'admin') {
                $product->restore($_POST['id']);
                $_SESSION['success'] = "Produk berhasil dikembalikan.";
            }
            header("Location: index.php?page=produk");
            exit();
        case 'delete_permanent':
            if ($_SESSION['role'] === 'admin') {
                $product->deletePermanent($_POST['id']);
                $_SESSION['success'] = "Produk dihapus permanen.";
            }
            header("Location: index.php?page=produk");
            exit();

        case 'process_order':
            $cart_data = json_decode($_POST['cart_data'], true);
            $total_amount = $_POST['total_amount'];
            $orderId = $order->create($_SESSION['user_id'], $total_amount, $cart_data);
            if ($orderId) {
                $_SESSION['last_order_id'] = $orderId;
                $_SESSION['success'] = "Pesanan berhasil dibuat!";
                header("Location: index.php?page=home&status=success");
            } else {
                $_SESSION['error'] = "Gagal memproses pesanan.";
                header("Location: index.php?page=home&status=failed");
            }
            exit();
    }
}

// --- AJAX handler untuk detail/struk ---
if (isset($_GET['ajax']) && $_GET['page'] === 'riwayat' && isset($_GET['detail_id'])) {
    echo View::receipt_content($order, $_GET['detail_id']);
    exit(); // Hentikan eksekusi skrip setelah mengirim data AJAX
}

// --- Routing Halaman (GET Request) ---
$page = $_GET['page'] ?? (isset($_SESSION['user_id']) ? 'home' : 'login');

// Proteksi halaman
if (!isset($_SESSION['user_id']) && !in_array($page, ['login', 'register', 'reset'])) {
    $page = 'login';
}
if (isset($_SESSION['user_id']) && in_array($page, ['login', 'register'])) {
    $page = 'home';
}
// Hanya admin yang bisa akses produk, kasir & admin bisa akses statistik
if ($page === 'produk' && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
    $page = 'home';
}
if ($page === 'statistik' && (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','kasir']))) {
    $page = 'home';
}
if ($page === 'logout') {
    $user->logout();
}

// Menyiapkan konten untuk dirender
$content = '';
switch ($page) {
    case 'login': $content = View::login(); break;
    case 'register': $content = View::register(); break;
    case 'reset': $content = View::reset(); break;
    case 'home': $content = View::home($product); break;
    case 'produk': $content = View::produk($product); break;
    case 'riwayat': $content = View::riwayat($order); break;
    case 'statistik': $content = View::statistik($order); break;
    default: $content = "<div class='alert alert-danger'>Halaman tidak ditemukan.</div>"; break;
}

?>
<!-- =================================================================== -->
<!-- 5. TEMPLATE HTML UTAMA                                            -->
<!-- =================================================================== -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir - <?php echo ucfirst($page); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        .navbar { box-shadow: 0 2px 4px rgba(0,0,0,.05); }
        .product-card { transition: all .2s ease-in-out; border-radius: 1rem; }
        .product-card:hover { transform: translateY(-5px); box-shadow: 0 4px 20px rgba(0,0,0,.1); }
        .product-card img { border-radius: 1rem 1rem 0 0; height: 180px; object-fit: cover; }
        .form-container { max-width: 450px; margin: 5rem auto; }
        .offcanvas-body { display: flex; flex-direction: column; }
        .cart-items { flex-grow: 1; }

        /* Sidebar styles */
        .sidebar {
            min-height: 100vh;
            width: 240px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            display: flex;
            flex-direction: column;
        }
        .sidebar .sidebar-header {
            border-bottom: 1px solid #eee;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: #f0f4ff;
            color: #0d6efd !important;
        }
        .sidebar .nav-link {
            border-radius: 0.5rem;
            margin-bottom: 0.2rem;
            transition: background 0.15s, color 0.15s;
        }
        .sidebar .nav-link {
            color: #222;
        }
        .sidebar .nav-link i {
            min-width: 1.2em;
        }
        .sidebar .mt-auto {
            margin-top: auto !important;
        }
        @media (max-width: 991.98px) {
            .sidebar { position: static; width: 100%; min-height: auto; }
            .main-content { margin-left: 0 !important; }
        }

        .floating-alert {
            position: fixed;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3000;
            min-width: 320px;
            max-width: 90vw;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            animation: fadeInDown .5s;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px) translateX(-50%); }
            to { opacity: 1; transform: translateY(0) translateX(-50%); }
        }
        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }

        /* Mobile dropdown menu hover/active color fix */
        @media (max-width: 767.98px) {
            .dropdown-menu .dropdown-item:active,
            .dropdown-menu .dropdown-item.active,
            .dropdown-menu .dropdown-item:hover {
                background-color: #e9f2ff !important;
                color: #0d6efd !important;
            }
            .dropdown-menu .dropdown-item {
                transition: background 0.15s, color 0.15s;
            }
        }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Navbar Utama di Atas -->
    <nav class="navbar navbar-light bg-white shadow-sm px-3 py-2 d-flex align-items-center justify-content-between flex-wrap no-print" style="position:sticky;top:0;z-index:2102;min-height:56px;">
        <a class="navbar-brand fw-bold text-primary d-flex align-items-center m-0 me-4" href="index.php?page=home" style="font-size: 1.3rem;"><i class="bi bi-shop-window me-2"></i> KEDAI BIASANE</a>
        <!-- Desktop menu -->
        <div class="d-none d-md-flex align-items-center flex-grow-1 flex-wrap" style="min-width:0;">
            <ul class="navbar-nav flex-row flex-wrap ms-2">
                <li class="nav-item mx-1"><a class="nav-link px-2 <?php echo $page == 'home' ? 'active fw-semibold text-primary' : 'text-dark'; ?>" href="index.php?page=home"><i class="bi bi-house-door me-2"></i> Home</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li class="nav-item mx-1"><a class="nav-link px-2 <?php echo $page == 'produk' ? 'active fw-semibold text-primary' : 'text-dark'; ?>" href="index.php?page=produk"><i class="bi bi-box-seam me-2"></i> Manajemen Produk</a></li>
                    <li class="nav-item mx-1"><a class="nav-link px-2 <?php echo $page == 'statistik' ? 'active fw-semibold text-primary' : 'text-dark'; ?>" href="index.php?page=statistik"><i class="bi bi-bar-chart-line me-2"></i> Statistik</a></li>
                <?php elseif ($_SESSION['role'] === 'kasir'): ?>
                    <li class="nav-item mx-1"><a class="nav-link px-2 <?php echo $page == 'statistik' ? 'active fw-semibold text-primary' : 'text-dark'; ?>" href="index.php?page=statistik"><i class="bi bi-bar-chart-line me-2"></i> Statistik</a></li>
                <?php endif; ?>
                <li class="nav-item mx-1"><a class="nav-link px-2 <?php echo $page == 'riwayat' ? 'active fw-semibold text-primary' : 'text-dark'; ?>" href="index.php?page=riwayat"><i class="bi bi-clock-history me-2"></i> Riwayat Pesanan</a></li>
            </ul>
            <div class="d-flex align-items-center ms-auto mt-2 mt-md-0 gap-2">
                <button class="btn btn-primary position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart">
                    <i class="bi bi-cart-fill"></i>
                    <span class="badge bg-danger ms-1 position-absolute top-0 start-100 translate-middle" id="cart-count">0</span>
                </button>
                <span class="navbar-text d-none d-md-inline-block">
                    <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a href="index.php?page=logout" class="btn btn-outline-danger btn-sm ms-1"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
        <!-- Mobile menu: dropdown -->
        <div class="d-md-none ms-auto">
            <button class="btn btn-outline-secondary" id="mobileMenuDropdownBtn" type="button" aria-label="Menu" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end mt-2 shadow-sm" id="mobileMenuDropdown">
                <li><a class="dropdown-item <?php echo $page == 'home' ? 'active fw-semibold text-primary' : ''; ?>" href="index.php?page=home"><i class="bi bi-house-door me-2"></i> Home</a></li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a class="dropdown-item <?php echo $page == 'produk' ? 'active fw-semibold text-primary' : ''; ?>" href="index.php?page=produk"><i class="bi bi-box-seam me-2"></i> Manajemen Produk</a></li>
                    <li><a class="dropdown-item <?php echo $page == 'statistik' ? 'active fw-semibold text-primary' : ''; ?>" href="index.php?page=statistik"><i class="bi bi-bar-chart-line me-2"></i> Statistik</a></li>
                <?php elseif ($_SESSION['role'] === 'kasir'): ?>
                    <li><a class="dropdown-item <?php echo $page == 'statistik' ? 'active fw-semibold text-primary' : ''; ?>" href="index.php?page=statistik"><i class="bi bi-bar-chart-line me-2"></i> Statistik</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item <?php echo $page == 'riwayat' ? 'active fw-semibold text-primary' : ''; ?>" href="index.php?page=riwayat"><i class="bi bi-clock-history me-2"></i> Riwayat Pesanan</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item d-flex align-items-center" href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart"><i class="bi bi-cart-fill me-2"></i> Keranjang <span class="badge bg-danger ms-1" id="cart-count-mobile">0</span></a></li>
                <li><div class="dropdown-item-text d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="index.php?page=logout" class="btn btn-outline-danger btn-sm ms-2"><i class="bi bi-box-arrow-right"></i></a>
                </div></li>
            </ul>
        </div>
    </nav>
    <div class="container my-4">
    <?php endif; ?>

    <main class="container my-4" style="max-width: 100%;">
        <?php
        // Menampilkan notifikasi floating
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show floating-alert" role="alert"><i class="bi bi-check-circle-fill me-2"></i>' . $_SESSION['success'] . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show floating-alert" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i>' . $_SESSION['error'] . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['error']);
        }
        // Menampilkan konten halaman yang sudah disiapkan
        echo $content;
        ?>
    </main>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Offcanvas untuk Keranjang Belanja -->
    <div class="offcanvas offcanvas-end no-print" tabindex="-1" id="offcanvasCart">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><i class="bi bi-cart3"></i> Keranjang Belanja</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div id="cart-items" class="cart-items mb-3">
                <p class="text-center text-muted mt-5">Keranjang Anda kosong.</p>
            </div>
            <div class="mt-auto">
                <hr>
                <h4>Total: <span id="cart-total" class="float-end">Rp 0</span></h4>
                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-primary btn-lg" id="btn-checkout" disabled><i class="bi bi-cash-coin"></i> Lanjut ke Pembayaran</button>
                    <button class="btn btn-outline-danger" id="btn-clear-cart"><i class="bi bi-trash"></i> Kosongkan Keranjang</button>
                </div>
            </div>
        </div>
    </div>
    <?php if (isset($_SESSION['user_id'])): ?>
        </div> <!-- close main-content for sidebar layout -->
    <?php endif; ?>

    <!-- Modal untuk Struk & Detail Riwayat -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header no-print">
                    <h5 class="modal-title">Struk Pesanan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receipt-content"></div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="printReceipt()"><i class="bi bi-printer"></i> Cetak</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Jangan jalankan script keranjang jika tidak login
        if (!document.getElementById('offcanvasCart')) return;

        let cart = JSON.parse(localStorage.getItem('posCart')) || [];
        
        const formatRupiah = (number) => {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
        };

        const updateCartView = () => {
            const cartItemsContainer = document.getElementById('cart-items');
            const cartCount = document.getElementById('cart-count');
            const cartTotal = document.getElementById('cart-total');
            const btnCheckout = document.getElementById('btn-checkout');

            cartItemsContainer.innerHTML = '';
            if (cart.length === 0) {
                cartItemsContainer.innerHTML = '<p class="text-center text-muted mt-5">Keranjang Anda kosong.</p>';
                cartTotal.textContent = formatRupiah(0); // Reset total ke 0
                btnCheckout.disabled = true;
            } else {
                let total = 0;
                cart.forEach((item, index) => {
                    const itemElement = document.createElement('div');
                    itemElement.className = 'd-flex align-items-center mb-3';
                    itemElement.innerHTML = `
                        <img src="${item.image}" class="rounded-3 me-3" style="width:60px; height:60px; object-fit:cover;">
                        <div class="flex-grow-1">
                            <h6 class="mb-0 small">${item.name}</h6>
                            <small class="text-muted">${formatRupiah(item.price)}</small>
                        </div>
                        <div class="input-group input-group-sm mx-2" style="width: 90px;">
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${item.id}, -1)">-</button>
                            <span class="mx-2">${item.quantity}</span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="updateQuantity(${item.id}, 1)">+</button>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})"><i class="bi bi-x"></i></button>
                    `;
                    cartItemsContainer.appendChild(itemElement);
                    total += item.price * item.quantity;
                });
                cartTotal.textContent = formatRupiah(total);
                btnCheckout.disabled = false;
            }
            cartCount.textContent = cart.reduce((sum, item) => sum + parseInt(item.quantity, 10), 0);
            localStorage.setItem('posCart', JSON.stringify(cart));
        };
        
        window.addToCart = (id, name, price, stock, image) => {
            const existingItemIndex = cart.findIndex(item => item.id === id);
            if (existingItemIndex > -1) {
                if(cart[existingItemIndex].quantity < stock) {
                   cart[existingItemIndex].quantity++;
                } else {
                    alert('Stok tidak mencukupi!');
                }
            } else {
                if (stock > 0) {
                    cart.push({ id, name, price: parseFloat(price), stock: parseInt(stock), image, quantity: 1 });
                } else {
                    alert('Stok produk habis!');
                }
            }
            updateCartView();
            // Tampilkan offcanvas
            const cartOffcanvas = new bootstrap.Offcanvas(document.getElementById('offcanvasCart'));
            cartOffcanvas.show();
        };

        window.removeFromCart = (index) => {
            cart.splice(index, 1);
            // Jika keranjang kosong setelah penghapusan, reset total ke 0
            if (cart.length === 0) {
                document.getElementById('cart-total').textContent = formatRupiah(0);
            }
            updateCartView();
        };
        
        window.updateQuantity = (id, delta) => {
            const idx = cart.findIndex(item => item.id === id);
            if (idx === -1) return;
            let newQty = cart[idx].quantity + delta;
            if (newQty > cart[idx].stock) {
                alert('Stok tidak mencukupi!');
                newQty = cart[idx].stock;
            }
            if (newQty < 1) {
                cart.splice(idx, 1);
            } else {
                cart[idx].quantity = newQty;
            }
            updateCartView();
        };
        
        document.getElementById('btn-clear-cart').addEventListener('click', () => {

            // Floating confirmation for clear cart
            showFloatingConfirm({
                message: 'Anda yakin ingin mengosongkan keranjang?',
                confirmText: 'Ya, Kosongkan',
                cancelText: 'Batal',
                onConfirm: () => {
                    cart = [];
                    updateCartView();
                }
            });
        });

        document.getElementById('btn-checkout').addEventListener('click', () => {
            const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
            showFloatingConfirm({
                message: `Total pembayaran adalah ${formatRupiah(total)}. Lanjutkan?`,
                confirmText: 'Lanjut Pembayaran',
                cancelText: 'Batal',
                onConfirm: () => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'index.php';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="process_order">
                        <input type="hidden" name="cart_data" value='${JSON.stringify(cart)}'>
                        <input type="hidden" name="total_amount" value="${total}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                    cart = [];
                    updateCartView();
                }
            });
        });

        // Floating confirm dialog
        function showFloatingConfirm({ message, confirmText = 'OK', cancelText = 'Batal', onConfirm }) {
            let dialog = document.getElementById('floating-confirm-dialog');
            if (!dialog) {
                dialog = document.createElement('div');
                dialog.id = 'floating-confirm-dialog';
                dialog.innerHTML = `
                    <div class="floating-confirm-backdrop"></div>
                    <div class="floating-confirm-box">
                        <div class="floating-confirm-message"></div>
                        <div class="d-flex gap-2 justify-content-end mt-3">
                            <button class="btn btn-secondary btn-cancel"></button>
                            <button class="btn btn-primary btn-confirm"></button>
                        </div>
                    </div>
                `;
                document.body.appendChild(dialog);
            }
            dialog.querySelector('.floating-confirm-message').innerHTML = message;
            dialog.querySelector('.btn-confirm').textContent = confirmText;
            dialog.querySelector('.btn-cancel').textContent = cancelText;
            dialog.style.display = 'flex';
            dialog.classList.add('show');
            // Remove previous listeners
            const newConfirm = dialog.querySelector('.btn-confirm').cloneNode(true);
            const newCancel = dialog.querySelector('.btn-cancel').cloneNode(true);
            dialog.querySelector('.btn-confirm').replaceWith(newConfirm);
            dialog.querySelector('.btn-cancel').replaceWith(newCancel);
            newConfirm.addEventListener('click', () => {
                dialog.style.display = 'none';
                dialog.classList.remove('show');
                if (onConfirm) onConfirm();
            });
            newCancel.addEventListener('click', () => {
                dialog.style.display = 'none';
                dialog.classList.remove('show');
            });
        }

        // Floating confirm dialog styles
        if (!document.getElementById('floating-confirm-style')) {
            const style = document.createElement('style');
            style.id = 'floating-confirm-style';
            style.innerHTML = `
                #floating-confirm-dialog {
                    position: fixed; z-index: 2000; left: 0; top: 0; width: 100vw; height: 100vh; display: none; align-items: center; justify-content: center;
                }
                #floating-confirm-dialog.show { display: flex; }
                .floating-confirm-backdrop {
                    position: absolute; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.3); z-index: 1;
                }
                .floating-confirm-box {
                    position: relative; z-index: 2; background: #fff; border-radius: 1rem; box-shadow: 0 4px 32px rgba(0,0,0,0.15); padding: 2rem; min-width: 320px; max-width: 90vw;
                    animation: popin .2s cubic-bezier(.4,2,.6,1) 1;
                }
                @keyframes popin { from { transform: scale(.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
            `;
            document.head.appendChild(style);
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('status') === 'success' && <?php echo isset($_SESSION['last_order_id']) ? 'true' : 'false'; ?>) {
            const orderId = <?php echo $_SESSION['last_order_id'] ?? 0; ?>;
            if(orderId > 0) {
                // Show custom success dialog with struk
                fetch(`index.php?page=riwayat&ajax=1&detail_id=${orderId}`)
                    .then(response => response.text())
                    .then(data => {
                        showOrderSuccessDialog(data);
                    });
            }
            <?php unset($_SESSION['last_order_id']); ?>
        }

        function showOrderSuccessDialog(receiptHtml) {
            let dialog = document.getElementById('order-success-dialog');
            if (!dialog) {
                dialog = document.createElement('div');
                dialog.id = 'order-success-dialog';
                dialog.innerHTML = `
                    <div class="floating-confirm-backdrop"></div>
                    <div class="floating-confirm-box order-success-box text-center">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem;"></i>
                        </div>
                        <h5 class="mb-2">Pembayaran Berhasil!</h5>
                        <div class="mb-3">Berikut adalah struk pesanan Anda:</div>
                        <div class="mb-3 border rounded p-2 bg-light order-success-struk-wrap">
                            <div id="order-success-struk"></div>
                        </div>
                        <div class="d-flex gap-2 justify-content-center mt-3">
                            <button class="btn btn-secondary btn-close-success">Tutup</button>
                            <button class="btn btn-primary btn-print-success"><i class="bi bi-printer"></i> Cetak Struk</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(dialog);
            }
            dialog.querySelector('#order-success-struk').innerHTML = receiptHtml;
            dialog.style.display = 'flex';
            dialog.classList.add('show');
            // Remove previous listeners
            const newClose = dialog.querySelector('.btn-close-success').cloneNode(true);
            const newPrint = dialog.querySelector('.btn-print-success').cloneNode(true);
            dialog.querySelector('.btn-close-success').replaceWith(newClose);
            dialog.querySelector('.btn-print-success').replaceWith(newPrint);
            newClose.addEventListener('click', () => {
                dialog.style.display = 'none';
                dialog.classList.remove('show');
            });
            newPrint.addEventListener('click', () => {
                const printContent = dialog.querySelector('#order-success-struk').innerHTML;
                const printWindow = window.open('', '', 'height=600,width=400');
                printWindow.document.write('<html><head><title>Cetak Struk</title>');
                printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">');
                printWindow.document.write('<style> body { font-family: monospace; width: 300px; margin: auto; } .table { font-size: 12px; } </style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write(printContent);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                setTimeout(() => { 
                    printWindow.print();
                    printWindow.close();
                }, 250);
            });
            // Add/Update style for better centering and responsive
            if (!document.getElementById('order-success-style')) {
                const style = document.createElement('style');
                style.id = 'order-success-style';
                style.innerHTML = `
                    #order-success-dialog {
                        position: fixed; z-index: 2100; left: 0; top: 0; width: 100vw; height: 100vh; display: flex; align-items: center; justify-content: center;
                    }
                    #order-success-dialog .floating-confirm-backdrop {
                        position: absolute; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.35); z-index: 1;
                    }
                    #order-success-dialog .order-success-box {
                        position: relative; z-index: 2; background: #fff; border-radius: 1.25rem; box-shadow: 0 8px 40px rgba(0,0,0,0.18); padding: 2.5rem 2rem 2rem 2rem; min-width: 340px; max-width: 95vw; max-height: 95vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
                        animation: popin .22s cubic-bezier(.4,2,.6,1) 1;
                    }
                    #order-success-dialog .order-success-struk-wrap {
                        width: 100%; max-width: 320px; margin-left: auto; margin-right: auto; background: #f8f9fa;
                    }
                    @media (max-width: 480px) {
                        #order-success-dialog .order-success-box { min-width: 90vw; padding: 1.2rem 0.5rem; }
                        #order-success-dialog .order-success-struk-wrap { max-width: 95vw; }
                    }
                `;
                document.head.appendChild(style);
            }
        }

        window.showHistoryDetail = (orderId) => {
            fetch(`index.php?page=riwayat&ajax=1&detail_id=${orderId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('receipt-content').innerHTML = data;
                    const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
                    receiptModal.show();
                });
        };
        
        window.printReceipt = () => {
            const printContent = document.getElementById('receipt-content').innerHTML;
            const printWindow = window.open('', '', 'height=600,width=400');
            printWindow.document.write('<html><head><title>Cetak Struk</title>');
            printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">');
            printWindow.document.write('<style> body { font-family: monospace; width: 300px; margin: auto; } .table { font-size: 12px; } </style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write(printContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            setTimeout(() => { 
                printWindow.print();
                printWindow.close();
            }, 250);
        };

        const editProductModal = document.getElementById('editProductModal');
        if(editProductModal){
            editProductModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-bs-id');
                const name = button.getAttribute('data-bs-name');
                const price = button.getAttribute('data-bs-price');
                const stock = button.getAttribute('data-bs-stock');
                const image = button.getAttribute('data-bs-image');

                editProductModal.querySelector('#edit-modal-title').textContent = `Edit Produk: ${name}`;
                editProductModal.querySelector('#edit-id').value = id;
                editProductModal.querySelector('#edit-name').value = name;
                editProductModal.querySelector('#edit-price').value = price;
                editProductModal.querySelector('#edit-stock').value = stock;
                editProductModal.querySelector('#edit-current-image').value = image;
                editProductModal.querySelector('#edit-image-preview').src = image ? `uploads/${image}` : 'https://placehold.co/100x100/E9ECEF/6C757D?text=N/A';
            });
        }
        
        const deleteProductModal = document.getElementById('deleteProductModal');
        if(deleteProductModal){
            deleteProductModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-bs-id');
                const name = button.getAttribute('data-bs-name');
                deleteProductModal.querySelector('#delete-product-name').textContent = name;
                deleteProductModal.querySelector('#delete-id').value = id;
            });
        }
        
        updateCartView();
    });
    </script>
</body>
</html>
