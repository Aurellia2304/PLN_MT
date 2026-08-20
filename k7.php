<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// =========================================================
// SIMPAN PENGAJUAN K7 BARU (admin ATAU vendor)
// =========================================================
if (isset($_POST['create_k7'])) {
    $tug = trim($_POST['tug_number'] ?? '');
    $tanggal = $_POST['tanggal_diminta'] ?? date('Y-m-d');
    $vendorId = isVendor() ? currentVendorId() : ($_POST['vendor_id'] ?? null);

    // Cek status vendor (aktif/nonaktif)
    $v = getVendorById($db, $vendorId);
    if (!$v || ($v['status'] ?? 'aktif') !== 'aktif') {
        $_SESSION['error'] = "Vendor Anda saat ini berstatus Nonaktif. Pengajuan surat baru tidak dapat dilakukan. Anda masih dapat melihat riwayat transaksi.";
        header("Location: index.php?page=k7");
        exit();
    }

    $spk = trim($_POST['spk_number'] ?? '');
    $jenis = trim($_POST['jenis_pekerjaan'] ?? '');
    $idpel = trim($_POST['idpel'] ?? '');
    $daya = trim($_POST['daya'] ?? '');
    $ulp = trim($_POST['ulp'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $setuju = trim($_POST['setuju_name'] ?? '');
    $kepalaGudang = trim($_POST['kepala_gudang_name'] ?? '');
    $pemeriksaPengawas = trim($_POST['pemeriksa_pengawas_name'] ?? '');
    $penerima = trim($_POST['penerima_name'] ?? '');
    $merkMaterial = trim($_POST['merk_material'] ?? '');
    $nomorSeri = trim($_POST['nomor_seri'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($tug === '' || !$vendorId || $customerName === '') {
        $_SESSION['error'] = "Nomor TUG, Vendor, dan Nama Pelanggan wajib diisi!";
        header("Location: index.php?page=k7");
        exit();
    }

    $stmt = $db->prepare("SELECT id FROM k7_transactions WHERE tug_number = ?");
    $stmt->execute([$tug]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Nomor TUG \"$tug\" sudah pernah diajukan. Gunakan kotak pencarian K7 untuk melihatnya.";
        header("Location: index.php?page=k7");
        exit();
    }

    $names = $_POST['item_material_name'] ?? [];
    $norms = $_POST['item_material_norm'] ?? [];
    $units = $_POST['item_unit_display'] ?? [];
    $qtys  = $_POST['item_qty'] ?? [];

    try {
        $db->beginTransaction();

        // Status langsung 'belum_jalan' (auto setuju)
        $stmt = $db->prepare("INSERT INTO k7_transactions
            (tug_number, vendor_id, spk_number, jenis_pekerjaan, idpel, customer_name, customer_address, daya, ulp, status, tanggal_diminta, requested_by, setuju_name, kepala_gudang_name, pemeriksa_pengawas_name, penerima_name, merk_material, nomor_seri, keterangan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'belum_jalan', ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute([$tug, $vendorId, $spk, $jenis, $idpel, $customerName, $customerAddress, $daya, $ulp, $tanggal, $_SESSION['user_id'], $setuju, $kepalaGudang, $pemeriksaPengawas, $penerima, $merkMaterial, $nomorSeri, $keterangan]);
        $k7Id = $stmt->fetchColumn();

        for ($i = 0; $i < count($names); $i++) {
            $name = trim($names[$i] ?? '');
            $norm = trim($norms[$i] ?? '');
            $unit = trim($units[$i] ?? '') ?: 'BH';
            $qty  = (int)($qtys[$i] ?? 0);
            if (($name === '' && $norm === '') || $qty <= 0) continue;

            $materialId = null;
            if ($norm !== '') {
                $s = $db->prepare("SELECT id FROM materials WHERE norm = ?");
                $s->execute([$norm]);
                $materialId = $s->fetchColumn();
            }
            if (!$materialId && $name !== '') {
                $s = $db->prepare("SELECT id FROM materials WHERE LOWER(name) = LOWER(?)");
                $s->execute([$name]);
                $materialId = $s->fetchColumn();
            }
            if (!$materialId) {
                $finalNorm = $norm !== '' ? $norm : generateNorm($name);
                $s = $db->prepare("INSERT INTO materials (name, norm, unit) VALUES (?, ?, ?)
                                    ON CONFLICT (norm) DO UPDATE SET name = EXCLUDED.name RETURNING id");
                $s->execute([$name ?: $finalNorm, $finalNorm, $unit]);
                $materialId = $s->fetchColumn();
            }

            $s = $db->prepare("INSERT INTO k7_items (k7_id, material_id, quantity_requested, quantity_received) VALUES (?, ?, ?, 0)");
            $s->execute([$k7Id, $materialId, $qty]);
        }

        $db->commit();
        $_SESSION['success'] = "Pengajuan pemakaian material bekas \"$tug\" berhasil disimpan.";
        header("Location: index.php?page=k7&tug=" . urlencode($tug));
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal menyimpan: " . $e->getMessage();
        header("Location: index.php?page=k7");
        exit();
    }
}

// =========================================================
// UPDATE JUMLAH DITERIMA (khusus admin)
// =========================================================
if (isset($_POST['update_k7_received'])) {
    if (!isAdmin() && !isGudang2()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengubah jumlah diterima.";
        header("Location: index.php?page=k7");
        exit();
    }
    $tug = $_POST['tug_number'] ?? '';
    $ids = $_POST['item_id'] ?? [];
    $received = $_POST['item_received'] ?? [];

    $k7Id = null;
    if (!empty($ids)) {
        $stmt = $db->prepare("SELECT k7_id FROM k7_items WHERE id = ?");
        $stmt->execute([$ids[0]]);
        $k7Id = $stmt->fetchColumn();
    }

    if ($k7Id) {
        $stmtCheck = $db->prepare("SELECT status FROM k7_transactions WHERE id = ?");
        $stmtCheck->execute([$k7Id]);
        $currentStatus = $stmtCheck->fetchColumn();
        if ($currentStatus === 'selesai') {
            $_SESSION['error'] = "Data surat yang sudah selesai tidak dapat diubah!";
            header("Location: index.php?page=k7&tug=" . urlencode($tug));
            exit();
        }
    }

    try {
        $db->beginTransaction();

        foreach ($ids as $i => $itemId) {
            $val = (int)($received[$i] ?? 0);
            $stmt = $db->prepare("UPDATE k7_items SET quantity_received = ? WHERE id = ?");
            $stmt->execute([$val, $itemId]);
        }

        if ($k7Id) {
            $stmt = $db->prepare("SELECT quantity_requested, quantity_received FROM k7_items WHERE k7_id = ?");
            $stmt->execute([$k7Id]);
            $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $allCompleted = true;
            $anyReceived = false;

            foreach ($allItems as $item) {
                $req = (int)($item['quantity_requested'] ?? 0);
                $recv = (int)($item['quantity_received'] ?? 0);

                if ($recv < $req) {
                    $allCompleted = false;
                }
                if ($recv > 0) {
                    $anyReceived = true;
                }
            }

            $newStatus = 'belum_jalan';
            if ($allCompleted) {
                $newStatus = 'selesai';
            } elseif ($anyReceived) {
                $newStatus = 'aktif';
            }

            $stmtUpdateStatus = $db->prepare("UPDATE k7_transactions SET status = ? WHERE id = ?");
            $stmtUpdateStatus->execute([$newStatus, $k7Id]);
        }

        $db->commit();
        $_SESSION['success'] = "Jumlah diterima K7 berhasil disimpan.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Terjadi kesalahan saat menyimpan data K7: " . $e->getMessage();
    }

    header("Location: index.php?page=k7&tug=" . urlencode($tug));
    exit();
}

// =========================================================
// SIMPAN / UBAH DETAIL BON (Merk Material, Nomor Seri, Keterangan)
// — admin ATAU admin gudang 2
// =========================================================
if (isset($_POST['update_k7_details'])) {
    if (!isAdmin() && !isGudang2()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengubah detail bon.";
        header("Location: index.php?page=k7");
        exit();
    }
    $id = $_POST['k7_id'] ?? '';
    $tug = $_POST['tug_number'] ?? '';
    $merkMaterial = trim($_POST['merk_material'] ?? '');
    $nomorSeri = trim($_POST['nomor_seri'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Cek status K7 saat ini
    $stmtCheck = $db->prepare("SELECT status FROM k7_transactions WHERE id = ?");
    $stmtCheck->execute([$id]);
    $currentStatus = $stmtCheck->fetchColumn();
    if ($currentStatus === 'selesai') {
        $_SESSION['error'] = "Data surat yang sudah selesai tidak dapat diubah!";
        header("Location: index.php?page=k7&tug=" . urlencode($tug));
        exit();
    }

    $stmt = $db->prepare("UPDATE k7_transactions SET merk_material = ?, nomor_seri = ?, keterangan = ? WHERE id = ?");
    $stmt->execute([$merkMaterial, $nomorSeri, $keterangan, $id]);

    $_SESSION['success'] = "Detail bon K7 berhasil disimpan.";
    header("Location: index.php?page=k7&tug=" . urlencode($tug));
    exit();
}

// =========================================================
// SIMPAN / UBAH NAMA PENANDATANGAN (box TTD) — khusus admin
// =========================================================
if (isset($_POST['update_k7_signers'])) {
    if (!isAdmin() && !isGudang2()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengubah data tanda tangan.";
        header("Location: index.php?page=k7");
        exit();
    }
    $id = $_POST['k7_id'] ?? '';
    $tug = $_POST['tug_number'] ?? '';
    $setuju = trim($_POST['setuju_name'] ?? '');
    $kepalaGudang = trim($_POST['kepala_gudang_name'] ?? '');
    $pemeriksaPengawas = trim($_POST['pemeriksa_pengawas_name'] ?? '');
    $penerima = trim($_POST['penerima_name'] ?? '');

    // Cek status K7 saat ini
    $stmtCheck = $db->prepare("SELECT status FROM k7_transactions WHERE id = ?");
    $stmtCheck->execute([$id]);
    $currentStatus = $stmtCheck->fetchColumn();
    if ($currentStatus === 'selesai') {
        $_SESSION['error'] = "Data surat yang sudah selesai tidak dapat diubah!";
        header("Location: index.php?page=k7&tug=" . urlencode($tug));
        exit();
    }

    $diterimaTgl   = trim($_POST['diterima_tgl'] ?? '');
    $malangTanggal = trim($_POST['malang_tanggal'] ?? '');

    $stmt = $db->prepare("UPDATE k7_transactions SET setuju_name = ?, kepala_gudang_name = ?, pemeriksa_pengawas_name = ?, penerima_name = ?, diterima_tgl = ?, malang_tanggal = ? WHERE id = ?");
    $stmt->execute([$setuju, $kepalaGudang, $pemeriksaPengawas, $penerima, $diterimaTgl, $malangTanggal, $id]);

    $_SESSION['success'] = "Data tanda tangan berhasil disimpan.";
    header("Location: index.php?page=k7&tug=" . urlencode($tug));
    exit();
}

// =========================================================
// HAPUS K7 (khusus admin)
// =========================================================
if (isset($_GET['delete_k7'])) {
    if (!isAdmin()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat menghapus data K7.";
        header("Location: index.php?page=k7");
        exit();
    }
    $id = $_GET['delete_k7'];

    // Cek status K7 saat ini
    $stmtCheck = $db->prepare("SELECT status FROM k7_transactions WHERE id = ?");
    $stmtCheck->execute([$id]);
    $currentStatus = $stmtCheck->fetchColumn();
    if ($currentStatus === 'selesai') {
        $_SESSION['error'] = "Data surat yang sudah selesai tidak dapat dihapus!";
        header("Location: index.php?page=k7");
        exit();
    }

    $stmt = $db->prepare("DELETE FROM k7_transactions WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "Data K7 berhasil dihapus.";
    header("Location: index.php?page=k7");
    exit();
}
?>