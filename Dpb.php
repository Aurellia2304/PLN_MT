<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// =========================================================
// SIMPAN PENGAJUAN DPB BARU (admin ATAU vendor)
// =========================================================
if (isset($_POST['create_dpb'])) {
    $tug = trim($_POST['tug_number'] ?? '');
    $tanggal = $_POST['tanggal_diminta'] ?? date('Y-m-d');

    // vendor hanya boleh mengajukan atas nama vendor dirinya sendiri
    if (isVendor()) {
        $vendorId = currentVendorId();
    } else {
        $vendorId = $_POST['vendor_id'] ?? null;
    }

    // Cek status vendor (aktif/nonaktif)
    $v = getVendorById($db, $vendorId);
    if (!$v || ($v['status'] ?? 'aktif') !== 'aktif') {
        $_SESSION['error'] = "Vendor Anda saat ini berstatus Nonaktif. Pengajuan surat baru tidak dapat dilakukan. Anda masih dapat melihat riwayat transaksi.";
        header("Location: index.php?page=dpb");
        exit();
    }

    $spk = trim($_POST['spk_number'] ?? '');
    $jenis = trim($_POST['jenis_pekerjaan'] ?? '');
    $idpel = trim($_POST['idpel'] ?? '');
    $daya = trim($_POST['daya'] ?? '');
    $ulp = trim($_POST['ulp'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $penerima = trim($_POST['penerima_name'] ?? '');
    $security = trim($_POST['security_name'] ?? '');
    $menyerahkan = trim($_POST['menyerahkan_name'] ?? '');

    if ($tug === '' || !$vendorId || $customerName === '') {
        $_SESSION['error'] = "Nomor TUG, Vendor, dan Nama Pelanggan wajib diisi!";
        header("Location: index.php?page=dpb");
        exit();
    }

    // cek nomor TUG belum pernah dipakai
    $stmt = $db->prepare("SELECT id FROM dpb_transactions WHERE tug_number = ?");
    $stmt->execute([$tug]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Nomor TUG \"$tug\" sudah pernah diajukan sebelumnya. Gunakan menu Cari DPB untuk melihatnya.";
        header("Location: index.php?page=dpb");
        exit();
    }

    $names = $_POST['item_material_name'] ?? [];
    $norms = $_POST['item_material_norm'] ?? [];
    $units = $_POST['item_unit_display'] ?? [];
    $qtys  = $_POST['item_qty'] ?? [];

    try {
        $db->beginTransaction();

        // Status langsung 'belum_jalan' (auto setuju, tidak perlu persetujuan)
        $stmt = $db->prepare("INSERT INTO dpb_transactions
            (tug_number, vendor_id, spk_number, jenis_pekerjaan, idpel, customer_name, customer_address, daya, ulp, status, tanggal_diminta, requested_by, penerima_name, security_name, menyerahkan_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'belum_jalan', ?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute([$tug, $vendorId, $spk, $jenis, $idpel, $customerName, $customerAddress, $daya, $ulp, $tanggal, $_SESSION['user_id'], $penerima, $security, $menyerahkan]);
        $dpbId = $stmt->fetchColumn();

        for ($i = 0; $i < count($names); $i++) {
            $name = trim($names[$i] ?? '');
            $norm = trim($norms[$i] ?? '');
            $unit = trim($units[$i] ?? '') ?: 'BH';
            $qty  = (int)($qtys[$i] ?? 0);

            if ($name === '' && $norm === '') continue;
            if ($qty <= 0) continue;

            // cari material master (by norm dulu, lalu by nama)
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
            // belum ada di master -> buat baru otomatis
            if (!$materialId) {
                $finalNorm = $norm !== '' ? $norm : generateNorm($name);
                $s = $db->prepare("INSERT INTO materials (name, norm, unit) VALUES (?, ?, ?)
                                    ON CONFLICT (norm) DO UPDATE SET name = EXCLUDED.name RETURNING id");
                $s->execute([$name ?: $finalNorm, $finalNorm, $unit]);
                $materialId = $s->fetchColumn();
            }

            $s = $db->prepare("INSERT INTO dpb_items (dpb_id, material_id, quantity_requested, quantity_received) VALUES (?, ?, ?, 0)");
            $s->execute([$dpbId, $materialId, $qty]);
        }

        $db->commit();
        $_SESSION['success'] = "Pengajuan DPB \"$tug\" berhasil disimpan.";
        header("Location: index.php?page=dpb&tug=" . urlencode($tug));
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal menyimpan pengajuan: " . $e->getMessage();
        header("Location: index.php?page=dpb");
        exit();
    }
}

// =========================================================
// UPDATE JUMLAH DITERIMA (admin ATAU admin gudang 2)
// =========================================================
if (isset($_POST['update_received'])) {
    if (!isAdmin() && !isGudang2()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengubah jumlah diterima.";
        header("Location: index.php?page=dpb");
        exit();
    }
    $tug = $_POST['tug_number'] ?? '';
    $ids = $_POST['item_id'] ?? [];
    $received = $_POST['item_received'] ?? [];

    try {
        $db->beginTransaction();

        $dpbId = null;
        if (!empty($ids)) {
            $stmt = $db->prepare("SELECT dpb_id FROM dpb_items WHERE id = ?");
            $stmt->execute([$ids[0]]);
            $dpbId = $stmt->fetchColumn();
        }

        foreach ($ids as $i => $itemId) {
            $newVal = max(0, (int)($received[$i] ?? 0));

            $stmt = $db->prepare("SELECT quantity_received, material_id FROM dpb_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $oldRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($oldRow) {
                $oldVal = (int)($oldRow['quantity_received'] ?? 0);
                $materialId = $oldRow['material_id'];

                $difference = $newVal - $oldVal;

                if ($difference !== 0) {
                    $stmtUpdateStock = $db->prepare("UPDATE materials SET stock = stock - ? WHERE id = ?");
                    $stmtUpdateStock->execute([$difference, $materialId]);
                }

                $stmtUpdateItem = $db->prepare("UPDATE dpb_items SET quantity_received = ? WHERE id = ?");
                $stmtUpdateItem->execute([$newVal, $itemId]);
            }
        }

        if ($dpbId) {
            $stmt = $db->prepare("SELECT quantity_requested, quantity_received FROM dpb_items WHERE dpb_id = ?");
            $stmt->execute([$dpbId]);
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

            $stmtUpdateStatus = $db->prepare("UPDATE dpb_transactions SET status = ? WHERE id = ?");
            $stmtUpdateStatus->execute([$newStatus, $dpbId]);
        }

        $db->commit();
        $_SESSION['success'] = "Jumlah diterima dan penyesuaian stok berhasil disimpan.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Terjadi kesalahan saat menyimpan data: " . $e->getMessage();
    }

    header("Location: index.php?page=dpb&tug=" . urlencode($tug));
    exit();
}

// =========================================================
// SIMPAN / UBAH NAMA PENANDATANGAN (box TTD) — admin ATAU admin gudang 2
// =========================================================
if (isset($_POST['update_signers'])) {
    if (!isAdmin() && !isGudang2()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengubah data tanda tangan.";
        header("Location: index.php?page=dpb");
        exit();
    }
    $id = $_POST['dpb_id'] ?? '';
    $tug = $_POST['tug_number'] ?? '';
    $penerima = trim($_POST['penerima_name'] ?? '');
    $security = trim($_POST['security_name'] ?? '');
    $menyerahkan = trim($_POST['menyerahkan_name'] ?? '');
    $diterimaTgl = trim($_POST['diterima_tgl'] ?? '');
    $malangTanggal = trim($_POST['malang_tanggal'] ?? '');

    $stmt = $db->prepare("UPDATE dpb_transactions SET penerima_name = ?, security_name = ?, menyerahkan_name = ?, diterima_tgl = ?, malang_tanggal = ? WHERE id = ?");
    $stmt->execute([$penerima, $security, $menyerahkan, $diterimaTgl, $malangTanggal, $id]);

    $_SESSION['success'] = "Data tanda tangan berhasil disimpan.";
    header("Location: index.php?page=dpb&tug=" . urlencode($tug));
    exit();
}

// =========================================================
// HAPUS DPB (khusus admin)
// =========================================================
if (isset($_GET['delete_dpb'])) {
    if (!isAdmin()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat menghapus DPB.";
        header("Location: index.php?page=dpb");
        exit();
    }
    $id = $_GET['delete_dpb'];
    $stmt = $db->prepare("DELETE FROM dpb_transactions WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "DPB berhasil dihapus.";
    header("Location: index.php?page=dpb");
    exit();
}
?>