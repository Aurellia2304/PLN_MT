<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Hanya admin gudang PLN yang boleh menambah / mengubah / menghapus material master
if (!isAdmin()) {
    $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengelola data material.";
    header("Location: index.php?page=material");
    exit();
}

// TAMBAH MATERIAL
if (isset($_POST['add_material'])) {
    $name  = trim($_POST['material_name'] ?? '');
    $unit  = $_POST['material_unit'] ?? 'BH';
    $norm  = trim($_POST['material_norm'] ?? '');
    $stock = (int) ($_POST['material_stock'] ?? 0);
    if ($stock < 0) $stock = 0;
    
    // Perubahan: Cegah normalisasi kosong & matikan generateNorm otomatis
    if ($norm === '') {
        $_SESSION['error'] = "Gagal: Nomor Normalisasi resmi dari PLN wajib diisi!";
        header("Location: index.php?page=material");
        exit();
    }

    // --- PERBAIKAN: Cek apakah norm sudah ada di database ---
    $cek = $db->prepare("SELECT COUNT(*) FROM materials WHERE norm = ?");
    $cek->execute([$norm]);
    if ($cek->fetchColumn() > 0) {
        $_SESSION['error'] = "Gagal: Nomor Normalisasi '$norm' sudah terdaftar!";
        header("Location: index.php?page=material");
        exit();
    }
    // --------------------------------------------------------

    try {
        $stmt = $db->prepare("INSERT INTO materials (name, norm, unit, stock) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $norm, $unit, $stock]);
        $_SESSION['success'] = "Material berhasil ditambahkan!";
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') {
            $_SESSION['error'] = "Gagal: Nomor Normalisasi '$norm' sudah terdaftar!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan material: " . $e->getMessage();
        }
    }
    header("Location: index.php?page=material");
    exit();
}

// EDIT MATERIAL
if (isset($_POST['edit_material'])) {
    $id    = $_POST['material_id'];
    $name  = trim($_POST['material_name'] ?? '');
    $norm  = trim($_POST['material_norm'] ?? '');
    $unit  = $_POST['material_unit'] ?? 'BH';
    $stock = (int) ($_POST['material_stock'] ?? 0);
    if ($stock < 0) $stock = 0;

    // --- PERBAIKAN: Cek apakah norm dipakai oleh material lain ---
    $cek = $db->prepare("SELECT COUNT(*) FROM materials WHERE norm = ? AND id != ?");
    $cek->execute([$norm, $id]);
    if ($cek->fetchColumn() > 0) {
        $_SESSION['error'] = "Gagal: Nomor Normalisasi '$norm' sudah dipakai oleh material lain!";
        header("Location: index.php?page=material");
        exit();
    }
    // -------------------------------------------------------------

    try {
        $stmt = $db->prepare("UPDATE materials SET name = ?, norm = ?, unit = ?, stock = ? WHERE id = ?");
        $stmt->execute([$name, $norm, $unit, $stock, $id]);
        $_SESSION['success'] = "Material berhasil diperbarui!";
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') {
            $_SESSION['error'] = "Gagal: Nomor Normalisasi '$norm' sudah dipakai oleh material lain!";
        } else {
            $_SESSION['error'] = "Gagal memperbarui material: " . $e->getMessage();
        }
    }
    header("Location: index.php?page=material");
    exit();
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    try {
        $db->beginTransaction();

        $db->prepare("UPDATE dpb_items SET material_id = NULL WHERE material_id = ?")->execute([$id]);
        $db->prepare("UPDATE k3_items  SET material_id = NULL WHERE material_id = ?")->execute([$id]);
        $db->prepare("UPDATE k7_items  SET material_id = NULL WHERE material_id = ?")->execute([$id]);

        $stmt = $db->prepare("DELETE FROM materials WHERE id = ?");
        $stmt->execute([$id]);

        $db->commit();
        $_SESSION['success'] = "Material berhasil dihapus!";
    } catch (PDOException $e) {
        $db->rollBack();
        if ($e->getCode() === '23502') {
            // not-null violation -> kolom material_id di salah satu tabel item
            // belum bisa diisi NULL, perlu ALTER TABLE dulu (lihat catatan di bawah)
            $_SESSION['error'] = "Gagal menghapus: kolom material_id di tabel transaksi belum mengizinkan NULL. Jalankan ALTER TABLE ... ALTER COLUMN material_id DROP NOT NULL pada tabel dpb_items/k3_items/k7_items terlebih dahulu.";
        } else {
            $_SESSION['error'] = "Gagal menghapus material: " . $e->getMessage();
        }
    }

    header("Location: index.php?page=material");
    exit();
}
?>