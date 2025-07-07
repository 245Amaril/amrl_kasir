<?php

class View {
    public static function login() { ob_start(); ?>
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
                    <p class="mb-0 mt-2"><a href="index.php?page=reset">Lupa password?</a></p>
                </div>
            </div>
        </div>
    <?php return ob_get_clean(); }

    public static function register() { ob_start(); ?>
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
                                <option value="admin">Admin</option>
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

    public static function reset() { ob_start(); ?>
        <div class="form-container">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <h3 class="card-title text-center mb-4">Reset Password</h3>
                    <form action="index.php" method="POST">
                        <input type="hidden" name="action" value="reset_password">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                            <label for="username"><i class="bi bi-person me-2"></i>Username</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Password Baru" required>
                            <label for="new_password"><i class="bi bi-lock me-2"></i>Password Baru</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" placeholder="Konfirmasi Password Baru" required>
                            <label for="confirm_new_password"><i class="bi bi-shield-lock me-2"></i>Konfirmasi Password Baru</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center bg-light border-0 py-3">
                    <p class="mb-0"><a href="index.php?page=login">Kembali ke Login</a></p>
                </div>
            </div>
        </div>
    <?php return ob_get_clean(); }

    public static function home($product_obj) { ob_start(); ?>
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
                        <p class="card-text mb-1"><span class="badge bg-info text-dark"><?php echo htmlspecialchars($kategori ?? '-'); ?></span></p>
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

    public static function produk($product_obj) { ob_start(); ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manajemen Produk</h2>
            <div>
                <button class="btn btn-secondary me-2" data-bs-toggle="modal" data-bs-target="#trashModal"><i class="bi bi-trash3 me-2"></i>Lihat Tempat Sampah</button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="bi bi-plus-circle-fill me-2"></i>Tambah Produk</button>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
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
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($kategori ?? '-'); ?></span></td>
                                <td>Rp <?php echo number_format($price, 0, ',', '.'); ?></td>
                                <td><?php echo $stock; ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editProductModal"
                                        data-bs-id="<?php echo $id; ?>"
                                        data-bs-name="<?php echo htmlspecialchars($name); ?>"
                                        data-bs-price="<?php echo $price; ?>"
                                        data-bs-stock="<?php echo $stock; ?>"
                                        data-bs-image="<?php echo $image; ?>"
                                        data-bs-kategori="<?php echo htmlspecialchars($kategori ?? ''); ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form action="index.php" method="post" class="d-inline move-to-trash-form">
                                        <input type="hidden" name="action" value="move_to_trash">
                                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                                        <button type="button" class="btn btn-sm btn-danger btn-move-to-trash" data-id="<?php echo $id; ?>" data-name="<?php echo htmlspecialchars($name); ?>"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Produk -->
        <div class="modal fade" id="addProductModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="index.php" method="post" enctype="multipart/form-data">
                        <div class="modal-header">
                            <h5 class="modal-title">Tambah Produk</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_product">
                            <div class="mb-3">
                                <label for="add-name" class="form-label">Nama Produk</label>
                                <input type="text" class="form-control" id="add-name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="add-kategori" class="form-label">Kategori</label>
                                <input type="text" class="form-control" id="add-kategori" name="kategori" required placeholder="Contoh: Minuman, Makanan, dll">
                            </div>
                            <div class="mb-3">
                                <label for="add-price" class="form-label">Harga</label>
                                <input type="number" class="form-control" id="add-price" name="price" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="add-stock" class="form-label">Stok</label>
                                <input type="number" class="form-control" id="add-stock" name="stock" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="add-image" class="form-label">Gambar Produk</label>
                                <input type="file" class="form-control" id="add-image" name="image" accept="image/*">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Produk -->
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
                            <div class="mb-3">
                                <label for="edit-name" class="form-label">Nama Produk</label>
                                <input type="text" class="form-control" id="edit-name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-kategori" class="form-label">Kategori</label>
                                <input type="text" class="form-control" id="edit-kategori" name="kategori" required placeholder="Contoh: Minuman, Makanan, dll">
                            </div>
                            <div class="mb-3">
                                <label for="edit-price" class="form-label">Harga</label>
                                <input type="number" class="form-control" id="edit-price" name="price" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-stock" class="form-label">Stok</label>
                                <input type="number" class="form-control" id="edit-stock" name="stock" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-image" class="form-label">Gambar Produk</label>
                                <input type="file" class="form-control" id="edit-image" name="image" accept="image/*">
                                <div class="mt-2">
                                    <img id="edit-image-preview" src="" alt="Preview" style="width:100px; height:100px; object-fit:cover; border-radius:8px; background:#eee;">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Pindah ke Tempat Sampah -->
        <div class="modal fade" id="moveToTrashModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Konfirmasi Pindahkan ke Tempat Sampah</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Anda yakin ingin memindahkan produk <strong id="move-to-trash-name"></strong> ke tempat sampah?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-danger" id="confirm-move-to-trash">Ya, Pindahkan</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tempat Sampah Produk -->
        <div class="modal fade" id="trashModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-trash3"></i> Tempat Sampah Produk</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Gambar</th>
                                        <th>Nama Produk</th>
                                        <th>Harga</th>
                                        <th>Stok</th>
                                        <th>Dihapus Pada</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $trashed = $product_obj->getTrashed();
                                    if ($trashed->rowCount() > 0) {
                                        while ($row = $trashed->fetch(PDO::FETCH_ASSOC)) {
                                            extract($row);
                                            $image_path = !empty($image) && file_exists('uploads/' . $image) ? 'uploads/' . $image : 'https://placehold.co/100x100/E9ECEF/6C757D?text=N/A';
                                    ?>
                                    <tr>
                                        <td><img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($name); ?>" style="width: 60px; height: 60px; object-fit: cover;" class="rounded-3"></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($name); ?></td>
                                        <td>Rp <?php echo number_format($price, 0, ',', '.'); ?></td>
                                        <td><?php echo $stock; ?></td>
                                        <td><span class="badge bg-danger-subtle text-danger"><?php echo date('d-m-Y H:i', strtotime($deleted_at)); ?></span></td>
                                        <td class="text-center">
                                            <form action="index.php" method="post" class="d-inline">
                                                <input type="hidden" name="action" value="restore_product">
                                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-arrow-clockwise"></i> Kembalikan</button>
                                            </form>
                                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePermanentModal" data-id="<?php echo $id; ?>" data-name="<?php echo htmlspecialchars($name); ?>"><i class="bi bi-x-circle"></i> Hapus Permanen</button>
                                        </td>
                                    </tr>
                                    <?php } } else { ?>
                                        <tr><td colspan="6" class="text-center text-muted">Tidak ada produk di tempat sampah.</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Hapus Permanen -->
        <div class="modal fade" id="deletePermanentModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="index.php" method="post">
                        <div class="modal-header">
                            <h5 class="modal-title">Konfirmasi Hapus Permanen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="delete_permanent">
                            <input type="hidden" name="id" id="delete-permanent-id">
                            <p>Anda yakin ingin <span class="text-danger fw-bold">menghapus permanen</span> produk <strong id="delete-permanent-name"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">Ya, Hapus Permanen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var moveToTrashModal = document.getElementById('moveToTrashModal');
            var moveToTrashName = document.getElementById('move-to-trash-name');
            var confirmMoveToTrashBtn = document.getElementById('confirm-move-to-trash');
            var currentTrashForm = null;
            document.querySelectorAll('.btn-move-to-trash').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    var id = btn.getAttribute('data-id');
                    var name = btn.getAttribute('data-name');
                    moveToTrashName.textContent = name;
                    currentTrashForm = btn.closest('form');
                    var modal = bootstrap.Modal.getOrCreateInstance(moveToTrashModal);
                    modal.show();
                });
            });
            if (confirmMoveToTrashBtn) {
                confirmMoveToTrashBtn.addEventListener('click', function() {
                    if (currentTrashForm) {
                        currentTrashForm.submit();
                    }
                    var modal = bootstrap.Modal.getOrCreateInstance(moveToTrashModal);
                    modal.hide();
                });
            }
            var deletePermanentModal = document.getElementById('deletePermanentModal');
            if(deletePermanentModal){
                deletePermanentModal.addEventListener('show.bs.modal', function(event) {
                    var button = event.relatedTarget;
                    var id = button.getAttribute('data-id');
                    var name = button.getAttribute('data-name');
                    deletePermanentModal.querySelector('#delete-permanent-id').value = id;
                    deletePermanentModal.querySelector('#delete-permanent-name').textContent = name;
                });
            }
            // Isi otomatis kategori pada edit modal
            var editProductModal = document.getElementById('editProductModal');
            if(editProductModal){
                editProductModal.addEventListener('show.bs.modal', function(event) {
                    var button = event.relatedTarget;
                    var kategori = button.getAttribute('data-bs-kategori');
                    editProductModal.querySelector('#edit-kategori').value = kategori || '';
                });
            }
        });
        </script>
    <?php return ob_get_clean(); }

    public static function riwayat($order_obj) {
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

    public static function statistik($order_obj) {
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

    public static function receipt_content($order_obj, $order_id) {
        $detail = $order_obj->getOrderDetails($order_id);
        $order = $detail['info'];
        $items = $detail['items'];
        ob_start();
    ?>
    <div class="print-area">
        <div class="text-center mb-3">
            <h4>Kedai Biasane</h4>
            <p class="mb-0 small">Jl. Awan, Jebres, Kec. Jebres, Kota Surakarta</p>
            <hr class="my-2">
        </div>
        <p class="mb-0 small">No: <?php echo htmlspecialchars($order['invoice_number']); ?></p>
        <p class="small">Tgl: <?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></p>
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
                    <th class="text-end">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></th>
                </tr>
            </tfoot>
        </table>
        <div class="text-center mt-3 small">
            <p>Terima kasih telah berbelanja!</p>
        </div>
    </div>
    <?php return ob_get_clean(); }
}
