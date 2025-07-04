<?php

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
                    if ($row['role'] === 'admin') {
                        $_SESSION['user_obj'] = serialize(new Admin($row['id'], $row['username']));
                    } elseif ($row['role'] === 'kasir') {
                        $_SESSION['user_obj'] = serialize(new Kasir($row['id'], $row['username']));
                    }
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

    public function resetPassword($username, $new_password) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            return false; // Username tidak ditemukan
        }
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        $update = $this->conn->prepare("UPDATE " . $this->table_name . " SET password = :password WHERE username = :username");
        $update->bindParam(':password', $hashed_password);
        $update->bindParam(':username', $username);
        return $update->execute();
    }

    public function logout() {
        session_unset();
        session_destroy();
        header("Location: index.php?page=login");
        exit();
    }
}

class Admin extends User {
    public $id;
    public $username;
    public $role = 'admin';
    public function __construct($id, $username) {
        $this->id = $id;
        $this->username = $username;
    }
}

class Kasir extends User {
    public $id;
    public $username;
    public $role = 'kasir';
    public function __construct($id, $username) {
        $this->id = $id;
        $this->username = $username;
    }
}

// Base Product: hanya read
class Product {
    protected $conn;
    protected $table_name = "products";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getTrashed() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readAll() {
        $query = "SELECT id, name, price, stock, image FROM " . $this->table_name . " WHERE deleted_at IS NULL ORDER BY name ASC";
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
}

// AdminProduct: bisa CRUD, restore, trash, deletePermanent
class AdminProduct extends Product {
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

    public function moveToTrash($id) {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        return $stmt->execute();
    }

    public function restore($id) {
        $query = "UPDATE " . $this->table_name . " SET deleted_at = NULL WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        return $stmt->execute();
    }

    public function deletePermanent($id) {
        $product = $this->readOne($id);
        if ($product && !empty($product['image']) && file_exists('uploads/' . $product['image'])) {
            unlink('uploads/' . $product['image']);
        }
        // Hapus riwayat transaksi terkait produk ini
        $deleteOrderDetails = $this->conn->prepare("DELETE FROM order_details WHERE product_id = ?");
        $deleteOrderDetails->bindParam(1, $id);
        $deleteOrderDetails->execute();

        // Hapus order yang tidak punya detail lagi (order yatim)
        $deleteOrphanOrders = $this->conn->prepare("DELETE o FROM orders o LEFT JOIN order_details od ON o.id = od.order_id WHERE od.order_id IS NULL");
        $deleteOrphanOrders->execute();

        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        return $stmt->execute();
    }
}

// KasirProduct: hanya read, tidak bisa CRUD

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
