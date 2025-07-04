<?php
// Mulai session di baris paling atas
session_start();

// ===================================================================
// 1. KONFIGURASI DAN KONEKSI DATABASE
// ===================================================================
class Database {
    private $host = 'localhost';
    private $db_name = 'db_pos_uas';
    private $username = 'root';
    private $password = '';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            die("Kesalahan Koneksi Database: " . $exception->getMessage());
        }
        return $this->conn;
    }
}

// ===================================================================
// 2. CLASS-CLASS MODEL (OOP)
// ===================================================================

/**
 * Class User untuk menangani login, registrasi, dan data pengguna
 */
class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        try {
            $query = "SELECT id, username, password, role FROM " . $this->table_name . " WHERE username = :username";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];
                    return true;
                }
            }
            return false;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function register($username, $password, $role) {
        $check_query = "SELECT id FROM " . $this->table_name . " WHERE username = :username";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':username', $username);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            return false; // Username sudah terdaftar
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $query = "INSERT INTO " . $this->table_name . " SET username=:username, password=:password, role=:role";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':role', $role);

        return $stmt->execute();
    }

    public function logout() {
        session_unset();
        session_destroy();
        header("Location: index.php?page=login");
        exit();
    }
}

/**
 * Class Product untuk menangani operasi CRUD produk
 */
class Product {
    private $conn;
    private $table_name = "products";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT id, name, price, stock, image FROM " . $this->table_name . " ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
    
    public function readOne($id) {
        $query = "SELECT id, name, price, stock, image FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name, $price, $stock, $image) {
        $query = "INSERT INTO " . $this->table_name . " SET name=:name, price=:price, stock=:stock, image=:image";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':image', $image);
        return $stmt->execute();
    }
    
    public function update($id, $name, $price, $stock, $image) {
        $query = "UPDATE " . $this->table_name . " SET name=:name, price=:price, stock=:stock, image=:image WHERE id=:id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':image', $image);
        return $stmt->execute();
    }

    public function delete($id) {
        $product = $this->readOne($id);
        if ($product && !empty($product['image']) && file_exists('uploads/' . $product['image'])) {
            unlink('uploads/' . $product['image']);
        }
        
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        return $stmt->execute();
    }
}

/**
 * Class Order untuk menangani pesanan, riwayat, dan statistik
 */
class Order {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($userId, $totalAmount, $cart) {
        $this->conn->beginTransaction();
        try {
            $invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());
            $order_query = "INSERT INTO orders (invoice_number, user_id, total_amount) VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($order_query);
            $stmt->execute([$invoice_number, $userId, $totalAmount]);
            $orderId = $this->conn->lastInsertId();

            $detail_query = "INSERT INTO order_details (order_id, product_id, quantity, price_per_item) VALUES (?, ?, ?, ?)";
            $update_stock_query = "UPDATE products SET stock = stock - ? WHERE id = ?";
            
            foreach ($cart as $item) {
                $detail_stmt = $this->conn->prepare($detail_query);
                $detail_stmt->execute([$orderId, $item['id'], $item['quantity'], $item['price']]);

                $update_stmt = $this->conn->prepare($update_stock_query);
                $update_stmt->execute([$item['quantity'], $item['id']]);
            }
            
            $this->conn->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Order creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function getHistory($start_date, $end_date) {
        $query = "SELECT o.id, o.invoice_number, o.total_amount, o.order_date, u.username 
                  FROM orders o 
                  JOIN users u ON o.user_id = u.id 
                  WHERE DATE(o.order_date) BETWEEN ? AND ?
                  ORDER BY o.order_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$start_date, $end_date]);
        return $stmt;
    }

    public function getOrderDetails($order_id) {
        $query = "SELECT p.name, p.image, od.quantity, od.price_per_item 
                  FROM order_details od
                  JOIN products p ON od.product_id = p.id
                  WHERE od.order_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$order_id]);
        
        $order_info_query = "SELECT * FROM orders WHERE id = ?";
        $order_stmt = $this->conn->prepare($order_info_query);
        $order_stmt->execute([$order_id]);

        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'info' => $order_stmt->fetch(PDO::FETCH_ASSOC)];
    }

    public function getDailyStats() {
        $today = date('Y-m-d');
        $stats = [];
        $query_revenue = "SELECT SUM(total_amount) as total_revenue FROM orders WHERE DATE(order_date) = ?";
        $stmt_revenue = $this->conn->prepare($query_revenue);
        $stmt_revenue->execute([$today]);
        $stats['revenue'] = $stmt_revenue->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

        $query_trans = "SELECT COUNT(id) as total_transactions FROM orders WHERE DATE(order_date) = ?";
        $stmt_trans = $this->conn->prepare($query_trans);
        $stmt_trans->execute([$today]);
        $stats['transactions'] = $stmt_trans->fetch(PDO::FETCH_ASSOC)['total_transactions'] ?? 0;
        
        return $stats;
    }
}

// ===================================================================
// 3. FUNGSI UNTUK VIEW (TAMPILAN HTML)
// ===================================================================

function view_login() { ob_start(); ?>
<div class="form-container">
    <div class="card shadow-lg border-0">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <i class="bi bi-shop-window text-primary" style="font-size: 3rem;"></i>
                <h3 class="card-title mt-2">Selamat Datang</h3>
                <p class="text-muted">Silakan login untuk melanjutkan</p>
            </div>
            <form action="index.php" method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                    <label for="username"><i class="bi bi-person me-2"></i>Username</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Login</button>
                </div>
            </form>
        </div>
        <div class="card-footer text-center bg-light border-0 py-3">
            <p class="mb-0">Belum punya akun? <a href="index.php?page=register">Daftar di sini</a></p>
        </div>
    </div>
</div>
<?php return ob_get_clean(); }

function view_register() { ob_start(); ?>
<div class="form-container">
    <div class="card shadow-lg border-0">
        <div class="card-body p-5">
            <h3 class="card-title text-center mb-4">Buat Akun Baru</h3>
            <form action="index.php" method="POST">
                <input type="hidden" name="action" value="register">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                    <label for="username"><i class="bi bi-person me-2"></i>Username</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Konfirmasi Password" required>
                    <label for="confirm_password"><i class="bi bi-shield-lock me-2"></i>Konfirmasi Password</label>
                </div>
                <div class="form-floating mb-3">
                    <select class="form-select" id="role" name="role" required>
                        <option value="kasir" selected>Kasir</option>
                        <option value="pemilik">Pemilik</option>
                    </select>
                    <label for="role"><i class="bi bi-person-badge me-2"></i>Daftar sebagai</label>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Register</button>
                </div>
            </form>
        </div>
        <div class="card-footer text-center bg-light border-0 py-3">
            <p class="mb-0">Sudah punya akun? <a href="index.php?page=login">Login di sini</a></p>
        </div>
    </div>
</div>
<?php return ob_get_clean(); }


function view_home($product_obj) { ob_start(); ?>
<h2 class="mb-4">Daftar Produk</h2>
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
    <?php
    $products = $product_obj->readAll();
    while ($row = $products->fetch(PDO::FETCH_ASSOC)) {
        extract($row);
        $image_path = !empty($image) && file_exists('uploads/' . $image) ? 'uploads/' . $image : 'https://placehold.co/400x300/E9ECEF/6C757D?text=Gambar+Produk';
    ?>
    <div class="col">
        <div class="card h-100 product-card shadow-sm border-0">
            <img src="<?php echo $image_path; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($name); ?>">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title flex-grow-1"><?php echo htmlspecialchars($name); ?></h5>
                <p class="card-text text-primary fw-bold fs-5 mb-2">Rp <?php echo number_format($price, 0, ',', '.'); ?></p>
                <p class="card-text"><small class="text-muted">Stok: <span class="fw-bold <?php echo $stock > 0 ? 'text-success' : 'text-danger'; ?>"><?php echo $stock; ?></span></small></p>
            </div>
            <div class="card-footer bg-white border-0 p-3">
                <div class="d-grid">
                    <button class="btn btn-primary" onclick="addToCart(<?php echo $id; ?>, '<?php echo htmlspecialchars(addslashes($name)); ?>', <?php echo $price; ?>, <?php echo $stock; ?>, '<?php echo $image_path; ?>')" <?php echo $stock <= 0 ? 'disabled' : ''; ?>>
                        <i class="bi bi-cart-plus-fill me-2"></i> <?php echo $stock <= 0 ? 'Stok Habis' : 'Tambah'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<?php return ob_get_clean(); }

function view_produk($product_obj) { ob_start(); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manajemen Produk</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Produk</button>
</div>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Gambar</th>
                        <th>Nama Produk</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $products = $product_obj->readAll();
                    while ($row = $products->fetch(PDO::FETCH_ASSOC)) {
                        extract($row);
                        $image_path = !empty($image) && file_exists('uploads/' . $image) ? 'uploads/' . $image : 'https://placehold.co/100x100/E9ECEF/6C757D?text=N/A';
                    ?>
                    <tr>
                        <td><img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($name); ?>" style="width: 60px; height: 60px; object-fit: cover;" class="rounded-3"></td>
                        <td class="fw-bold"><?php echo htmlspecialchars($name); ?></td>
                        <td>Rp <?php echo number_format($price, 0, ',', '.'); ?></td>
                        <td><?php echo $stock; ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editProductModal"
                                data-bs-id="<?php echo $id; ?>"
                                data-bs-name="<?php echo htmlspecialchars($name); ?>"
                                data-bs-price="<?php echo $price; ?>"
                                data-bs-stock="<?php echo $stock; ?>"
                                data-bs-image="<?php echo $image; ?>">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteProductModal"
                                data-bs-id="<?php echo $id; ?>"
                                data-bs-name="<?php echo htmlspecialchars($name); ?>">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals for Produk -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="index.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Produk Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_product">
                    <div class="mb-3"><label class="form-label">Nama Produk</label><input type="text" name="name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Harga</label><input type="number" name="price" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Stok</label><input type="number" name="stock" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Gambar Produk</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="index.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="edit-modal-title">Edit Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="id" id="edit-id">
                    <input type="hidden" name="current_image" id="edit-current-image">
                    <div class="text-center mb-3"><img id="edit-image-preview" src="" class="rounded-3" style="width:100px; height:100px; object-fit:cover;"></div>
                    <div class="mb-3"><label class="form-label">Nama Produk</label><input type="text" name="name" id="edit-name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Harga</label><input type="number" name="price" id="edit-price" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Stok</label><input type="number" name="stock" id="edit-stock" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Ganti Gambar (Opsional)</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="index.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="id" id="delete-id">
                    <p>Anda yakin ingin menghapus produk <strong id="delete-product-name"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php return ob_get_clean(); }

function view_riwayat($order_obj) {
    $start_date = $_GET['start_date'] ?? date('Y-m-d');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    ob_start();
?>
<h2 class="mb-4">Riwayat Pesanan</h2>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="GET">
            <input type="hidden" name="page" value="riwayat">
            <div class="col-md-5"><label class="form-label">Dari Tanggal</label><input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>"></div>
            <div class="col-md-5"><label class="form-label">Sampai Tanggal</label><input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>"></div>
            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill me-2"></i>Filter</button></div>
        </form>
    </div>
</div>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No. Invoice</th>
                        <th>Tanggal</th>
                        <th>Kasir</th>
                        <th>Total</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history = $order_obj->getHistory($start_date, $end_date);
                    if ($history->rowCount() > 0) {
                        while ($row = $history->fetch(PDO::FETCH_ASSOC)) {
                            extract($row);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($invoice_number); ?></td>
                        <td><?php echo date('d M Y, H:i', strtotime($order_date)); ?></td>
                        <td><?php echo htmlspecialchars($username); ?></td>
                        <td>Rp <?php echo number_format($total_amount, 0, ',', '.'); ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info" onclick="showHistoryDetail(<?php echo $id; ?>)"><i class="bi bi-receipt"></i> Detail</button>
                        </td>
                    </tr>
                    <?php } } else { ?>
                        <tr><td colspan="5" class="text-center text-muted">Tidak ada data untuk rentang tanggal ini.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php return ob_get_clean(); }

function view_statistik($order_obj) {
    $stats = $order_obj->getDailyStats();
    ob_start();
?>
<h2 class="mb-4">Statistik Penjualan Hari Ini (<?php echo date('d F Y'); ?>)</h2>
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card text-white bg-success shadow-lg">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">TOTAL PENDAPATAN</h5>
                        <h3 class="display-6 fw-bold">Rp <?php echo number_format($stats['revenue'], 0, ',', '.'); ?></h3>
                    </div>
                    <i class="bi bi-cash-stack" style="font-size: 4rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card text-white bg-info shadow-lg">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">JUMLAH TRANSAKSI</h5>
                        <h3 class="display-6 fw-bold"><?php echo $stats['transactions']; ?></h3>
                    </div>
                    <i class="bi bi-cart-check-fill" style="font-size: 4rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>
<?php return ob_get_clean(); }

function view_receipt_content($order_obj, $order_id) {
    $detail = $order_obj->getOrderDetails($order_id);
    $info = $detail['info'];
    $items = $detail['items'];
    ob_start();
?>
<div class="print-area">
    <div class="text-center mb-3">
        <h4>Toko Sejahtera Makmur</h4>
        <p class="mb-0 small">Jl. Pahlawan No. 123, Semarang</p>
        <hr class="my-2">
    </div>
    <p class="mb-0 small">No: <?php echo htmlspecialchars($info['invoice_number']); ?></p>
    <p class="small">Tgl: <?php echo date('d/m/Y H:i', strtotime($info['order_date'])); ?></p>
    <table class="table table-sm">
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?><br><small><?php echo $item['quantity']; ?> x <?php echo number_format($item['price_per_item']); ?></small></td>
                <td class="text-end align-middle">Rp <?php echo number_format($item['quantity'] * $item['price_per_item'], 0, ',', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="border-top">
                <th class="text-end">Total</th>
                <th class="text-end">Rp <?php echo number_format($info['total_amount'], 0, ',', '.'); ?></th>
            </tr>
        </tfoot>
    </table>
    <div class="text-center mt-3 small">
        <p>Terima kasih telah berbelanja!</p>
    </div>
</div>
<?php
    return ob_get_clean();
}


// ===================================================================
// 4. INISIALISASI OBJEK DAN PENANGANAN REQUEST (CONTROLLER)
// ===================================================================
$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$product = new Product($db);
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
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

        case 'add_product':
            if ($_SESSION['role'] === 'pemilik') {
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
            if ($_SESSION['role'] === 'pemilik') {
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
            if ($_SESSION['role'] === 'pemilik') {
                $product->delete($_POST['id']);
                $_SESSION['success'] = "Produk berhasil dihapus.";
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
    echo view_receipt_content($order, $_GET['detail_id']);
    exit(); // Hentikan eksekusi skrip setelah mengirim data AJAX
}


// --- Routing Halaman (GET Request) ---
$page = $_GET['page'] ?? (isset($_SESSION['user_id']) ? 'home' : 'login');

// Proteksi halaman
if (!isset($_SESSION['user_id']) && !in_array($page, ['login', 'register'])) {
    $page = 'login';
}
if (isset($_SESSION['user_id']) && in_array($page, ['login', 'register'])) {
    $page = 'home';
}
if (($page === 'produk' || $page === 'statistik') && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik')) {
    $page = 'home';
}
if ($page === 'logout') {
    $user->logout();
}

// Menyiapkan konten untuk dirender
$content = '';
switch ($page) {
    case 'login': $content = view_login(); break;
    case 'register': $content = view_register(); break;
    case 'home': $content = view_home($product); break;
    case 'produk': $content = view_produk($product); break;
    case 'riwayat': $content = view_riwayat($order); break;
    case 'statistik': $content = view_statistik($order); break;
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
    <title>Sistem Kasir POS - <?php echo ucfirst($page); ?></title>
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

        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Navbar Utama -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top no-print shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-primary" href="index.php?page=home"><i class="bi bi-shop-window"></i> POS KASIR</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link <?php echo $page == 'home' ? 'active' : ''; ?>" href="index.php?page=home">Home</a></li>
                    <?php if ($_SESSION['role'] === 'pemilik'): ?>
                        <li class="nav-item"><a class="nav-link <?php echo $page == 'produk' ? 'active' : ''; ?>" href="index.php?page=produk">Manajemen Produk</a></li>
                        <li class="nav-item"><a class="nav-link <?php echo $page == 'statistik' ? 'active' : ''; ?>" href="index.php?page=statistik">Statistik</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link <?php echo $page == 'riwayat' ? 'active' : ''; ?>" href="index.php?page=riwayat">Riwayat Pesanan</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <button class="btn btn-primary me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasCart">
                        <i class="bi bi-cart-fill"></i> Keranjang <span class="badge bg-danger ms-1" id="cart-count">0</span>
                    </button>
                    <div class="vr"></div>
                    <span class="navbar-text mx-3">
                        <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <a href="index.php?page=logout" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <main class="container my-4">
        <?php
        // Menampilkan notifikasi
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="bi bi-check-circle-fill me-2"></i>' . $_SESSION['success'] . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="bi bi-exclamation-triangle-fill me-2"></i>' . $_SESSION['error'] . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
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
                        <input type="number" value="${item.quantity}" min="1" max="${item.stock}" class="form-control form-control-sm mx-2" style="width: 60px;" onchange="updateQuantity(${index}, this.value)">
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
            updateCartView();
        };
        
        window.updateQuantity = (index, quantity) => {
            const qty = parseInt(quantity, 10);
            if (qty > 0 && qty <= cart[index].stock) {
                cart[index].quantity = qty;
            } else if (qty > cart[index].stock) {
                alert('Stok tidak mencukupi!');
                cart[index].quantity = cart[index].stock;
            }
            else {
                cart.splice(index, 1);
            }
            updateCartView();
        };
        
        document.getElementById('btn-clear-cart').addEventListener('click', () => {
            if(confirm('Anda yakin ingin mengosongkan keranjang?')) {
                cart = [];
                updateCartView();
            }
        });

        document.getElementById('btn-checkout').addEventListener('click', () => {
            const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
            if(confirm(`Total pembayaran adalah ${formatRupiah(total)}. Lanjutkan?`)){
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
            }
        });
        
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('status') === 'success' && <?php echo isset($_SESSION['last_order_id']) ? 'true' : 'false'; ?>) {
            const orderId = <?php echo $_SESSION['last_order_id'] ?? 0; ?>;
            if(orderId > 0) {
                showHistoryDetail(orderId);
            }
            <?php unset($_SESSION['last_order_id']); ?>
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
