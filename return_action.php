<?php
// Pastikan dipanggil dari index.php
if (!isset($db) || !isAdmin()) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_return'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Sesi tidak valid.";
        header("Location: index.php?page=return");
        exit();
    }

    if ($_POST['action_return'] === 'add') {
        $bon = trim($_POST['bon_number']);
        $mat = trim($_POST['material_name']);
        $qty = (int)$_POST['quantity'];
        $status = $_POST['status'];
        $token = bin2hex(random_bytes(16));

        try {
            $stmt = $db->prepare("INSERT INTO return_materials (bon_number, material_name, quantity, status, token) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$bon, $mat, $qty, $status, $token]);
            $_SESSION['success'] = "Data return berhasil ditambahkan!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error: Nomor bon mungkin sudah ada atau terjadi kesalahan sistem.";
        }
        header("Location: index.php?page=return");
        exit();
    }

    if ($_POST['action_return'] === 'edit') {
        $id = (int)$_POST['id'];
        $qty = (int)$_POST['quantity'];
        $status = $_POST['status'];

        try {
            $stmt = $db->prepare("UPDATE return_materials SET quantity = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$qty, $status, $id]);
            $_SESSION['success'] = "Data return berhasil diubah!";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error saat mengubah data.";
        }
        header("Location: index.php?page=return");
        exit();
    }
}
