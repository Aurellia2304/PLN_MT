<?php
require_once 'config.php';
require_once 'functions.php';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

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

// TEMPLATE DOWNLOAD
if (isset($_GET['action']) && $_GET['action'] === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_material.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Nama Material', 'Kode Normalisasi', 'Satuan', 'Jumlah']);
    fputcsv($output, ['BAUT 16X75', '4190244', 'BH', '100']);
    fclose($output);
    exit();
}

// EXPORT TO CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=export_material.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No', 'Nama Material', 'Kode Normalisasi', 'Satuan', 'Jumlah']);
    
    $stmt = $db->query("SELECT norm, name, unit, stock FROM materials ORDER BY name ASC");
    $i = 1;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $i++,
            $row['name'],
            $row['norm'],
            $row['unit'],
            $row['stock']
        ]);
    }
    fclose($output);
    exit();
}

// IMPORT VALIDATE
if (isset($_GET['action']) && $_GET['action'] === 'import_validate') {
    header('Content-Type: application/json');
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'File tidak terunggah atau terjadi kesalahan saat mengunggah.']);
        exit();
    }

    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($fileExtension, ['csv', 'xlsx', 'xls'])) {
        echo json_encode(['error' => 'Format file tidak didukung. Silakan unggah berkas .xlsx, .xls, atau .csv.']);
        exit();
    }

    $rows = [];
    try {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            echo json_encode(['error' => 'Library PhpSpreadsheet tidak terpasang di sistem.']);
            exit();
        }
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmpPath);
        $worksheet = $spreadsheet->getActiveSheet();
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(FALSE);
            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }
            $rows[] = $rowData;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Gagal membaca berkas Excel/CSV: ' . $e->getMessage()]);
        exit();
    }

    if (empty($rows)) {
        echo json_encode(['error' => 'File kosong atau tidak valid.']);
        exit();
    }

    // Read header row
    $headers = array_shift($rows);
    if (!$headers) {
        echo json_encode(['error' => 'File kosong atau tidak memiliki baris header.']);
        exit();
    }

    $errors = [];
    $warnings = [];
    $validData = [];
    $seenNorms = [];
    $lineNum = 1;

    $stmt = $db->query("SELECT norm FROM materials WHERE norm IS NOT NULL AND norm != ''");
    $dbNorms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $dbNormsSet = array_flip($dbNorms);

    foreach ($rows as $rowData) {
        $lineNum++;
        
        // Skip empty row
        $isEmpty = true;
        foreach ($rowData as $val) {
            if ($val !== null && trim((string)$val) !== '') {
                $isEmpty = false;
                break;
            }
        }
        if ($isEmpty) {
            continue;
        }

        while (count($rowData) < 4) {
            $rowData[] = '';
        }

        $name = trim((string)($rowData[0] ?? ''));
        $norm = trim((string)($rowData[1] ?? ''));
        $unit = trim((string)($rowData[2] ?? 'BH'));
        $stockStr = trim((string)($rowData[3] ?? '0'));

        if ($name === '') {
            $errors[] = "Baris $lineNum: Nama Material wajib diisi.";
            continue;
        }

        if ($norm === '') {
            $errors[] = "Baris $lineNum: Kode Normalisasi wajib diisi.";
            continue;
        }

        if (isset($dbNormsSet[$norm])) {
            $warnings[] = "Baris $lineNum: Kode Normalisasi '$norm' sudah terdaftar di database (akan di-input ganda).";
        }

        if (isset($seenNorms[$norm])) {
            $warnings[] = "Baris $lineNum: Duplikasi Kode Normalisasi '$norm' di dalam file.";
        }

        if ($stockStr !== '' && !is_numeric($stockStr)) {
            $errors[] = "Baris $lineNum: Jumlah harus berupa angka.";
            continue;
        }

        $stock = (int)$stockStr;
        if ($stock < 0) {
            $errors[] = "Baris $lineNum: Jumlah tidak boleh kurang dari 0.";
            continue;
        }

        $seenNorms[$norm] = true;
        $validData[] = [
            'norm' => $norm,
            'name' => $name,
            'unit' => $unit !== '' ? strtoupper($unit) : 'BH',
            'stock' => $stock,
            'deskripsi' => null
        ];
    }

    if (empty($errors) && empty($validData)) {
        echo json_encode(['error' => 'Tidak ada data material yang ditemukan dalam file.']);
        exit();
    }

    if (empty($errors)) {
        $_SESSION['pending_import_materials'] = $validData;
        echo json_encode([
            'success' => true,
            'errors' => [],
            'warnings' => $warnings,
            'validCount' => count($validData),
            'preview' => array_slice($validData, 0, 5)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'errors' => $errors,
            'warnings' => $warnings
        ]);
    }
    exit();
}

// IMPORT CONFIRM
if (isset($_GET['action']) && $_GET['action'] === 'import_confirm') {
    header('Content-Type: application/json');
    $data = $_SESSION['pending_import_materials'] ?? null;
    if (!$data || !is_array($data)) {
        echo json_encode(['error' => 'Tidak ada data yang siap diimport. Silakan upload file kembali.']);
        exit();
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO materials (norm, name, unit, stock, deskripsi) VALUES (?, ?, ?, ?, ?)");
        foreach ($data as $item) {
            $stmt->execute([
                $item['norm'],
                $item['name'],
                $item['unit'],
                $item['stock'],
                $item['deskripsi']
            ]);
        }
        $db->commit();
        unset($_SESSION['pending_import_materials']);
        echo json_encode(['success' => true, 'count' => count($data)]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => 'Gagal menyimpan data ke database: ' . $e->getMessage()]);
    }
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