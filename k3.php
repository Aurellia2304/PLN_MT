<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// =========================================================
// SIMPAN PENGAJUAN K3 BARU (admin ATAU vendor)
// =========================================================
if (isset($_POST['create_k3'])) {
    $tug = trim($_POST['tug_number'] ?? '');
    $tanggal = $_POST['tanggal_diminta'] ?? date('Y-m-d');
    $vendorId = isVendor() ? currentVendorId() : ($_POST['vendor_id'] ?? null);

    // Cek status vendor (aktif/nonaktif)
    $v = getVendorById($db, $vendorId);
    if (!$v || ($v['status'] ?? 'aktif') !== 'aktif') {
        $_SESSION['error'] = "Vendor Anda saat ini berstatus Nonaktif. Pengajuan surat baru tidak dapat dilakukan. Anda masih dapat melihat riwayat transaksi.";
        header("Location: index.php?page=k3");
        exit();
    }

    $spk = trim($_POST['spk_number'] ?? '');
    $jenis = trim($_POST['jenis_pekerjaan'] ?? '');
    $idpel = trim($_POST['idpel'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $customerAddress = trim($_POST['customer_address'] ?? '');
    $kondisi = $_POST['kondisi_material'] ?? 'masih_dapat_dipergunakan';
    $gudang = trim($_POST['gudang_pengembalian'] ?? 'Gudang PLN Aries Munandar');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $nomorSeri = trim($_POST['nomor_seri'] ?? '');
    $noDpbBukti = trim($_POST['no_dpb_bukti'] ?? '');
    $lokasiPenempatan = trim($_POST['lokasi_penempatan'] ?? '');
    $setuju = trim($_POST['setuju_name'] ?? '');
    $kepalaGudang = trim($_POST['kepala_gudang_name'] ?? '');
    $pemeriksaPengawas = trim($_POST['pemeriksa_pengawas_name'] ?? '');
    $yangMenyerahkan = trim($_POST['yang_menyerahkan_name'] ?? '');

    if ($tug === '' || !$vendorId || $customerName === '') {
        $_SESSION['error'] = "Nomor TUG, Vendor, dan Nama Pelanggan wajib diisi!";
        header("Location: index.php?page=k3");
        exit();
    }

    $stmt = $db->prepare("SELECT id FROM k3_transactions WHERE tug_number = ?");
    $stmt->execute([$tug]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "Nomor TUG \"$tug\" sudah pernah diajukan. Gunakan kotak pencarian K3 untuk melihatnya.";
        header("Location: index.php?page=k3");
        exit();
    }

    $names = $_POST['item_material_name'] ?? [];
    $norms = $_POST['item_material_norm'] ?? [];
    $units = $_POST['item_unit_display'] ?? [];
    $qtys  = $_POST['item_qty'] ?? [];
    $kodes = $_POST['item_kode'] ?? [];
    $harga = $_POST['item_harga_satuan'] ?? [];

    try {
        $db->beginTransaction();

        // Status langsung 'belum_jalan' (auto setuju)
        $stmt = $db->prepare("INSERT INTO k3_transactions
            (tug_number, vendor_id, spk_number, jenis_pekerjaan, idpel, customer_name, customer_address, kondisi_material, gudang_pengembalian, keterangan, nomor_seri, no_dpb_bukti, lokasi_penempatan, status, tanggal_diminta, requested_by, setuju_name, kepala_gudang_name, pemeriksa_pengawas_name, yang_menyerahkan_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'belum_jalan', ?, ?, ?, ?, ?, ?) RETURNING id");
        $stmt->execute([$tug, $vendorId, $spk, $jenis, $idpel, $customerName, $customerAddress, $kondisi, $gudang, $keterangan, $nomorSeri, $noDpbBukti, $lokasiPenempatan, $tanggal, $_SESSION['user_id'], $setuju, $kepalaGudang, $pemeriksaPengawas, $yangMenyerahkan]);
        $k3Id = $stmt->fetchColumn();

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

            $itemKode = trim($kodes[$i] ?? '');
            $itemHarga = (float) ($harga[$i] ?? 0);

            $s = $db->prepare("INSERT INTO k3_items (k3_id, material_id, quantity_returned, quantity_received, kode, harga_satuan) VALUES (?, ?, ?, 0, ?, ?)");
            $s->execute([$k3Id, $materialId, $qty, $itemKode, $itemHarga]);
        }

        $db->commit();
        $_SESSION['success'] = "Pengajuan pengembalian material \"$tug\" berhasil disimpan.";
        header("Location: index.php?page=k3&tug=" . urlencode($tug));
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Gagal menyimpan: " . $e->getMessage();
        header("Location: index.php?page=k3");
        exit();
    }
}

// =========================================================
// UPDATE JUMLAH DITERIMA (khusus admin)
// =========================================================
if (isset($_POST['update_k3_received'])) {
    if (!isAdmin() && !isGudang2()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengubah jumlah diterima.";
        header("Location: index.php?page=k3");
        exit();
    }
    $tug = $_POST['tug_number'] ?? '';
    $ids = $_POST['item_id'] ?? [];
    $received = $_POST['item_received'] ?? [];
    $kodes = $_POST['item_kode'] ?? [];
    $harga = $_POST['item_harga_satuan'] ?? [];

    try {
        $db->beginTransaction();

        $k3Id = null;
        if (!empty($ids)) {
            $stmt = $db->prepare("SELECT k3_id FROM k3_items WHERE id = ?");
            $stmt->execute([$ids[0]]);
            $k3Id = $stmt->fetchColumn();
        }

        foreach ($ids as $i => $itemId) {
            $val = (int)($received[$i] ?? 0);
            $itemKode = trim($kodes[$i] ?? '');
            $itemHarga = (float) ($harga[$i] ?? 0);
            $stmt = $db->prepare("UPDATE k3_items SET quantity_received = ?, kode = ?, harga_satuan = ? WHERE id = ?");
            $stmt->execute([$val, $itemKode, $itemHarga, $itemId]);
        }

        if ($k3Id) {
            $stmt = $db->prepare("SELECT quantity_returned, quantity_received FROM k3_items WHERE k3_id = ?");
            $stmt->execute([$k3Id]);
            $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $allCompleted = true;
            $anyReceived = false;

            foreach ($allItems as $item) {
                $req = (int)($item['quantity_returned'] ?? 0);
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

            $stmtUpdateStatus = $db->prepare("UPDATE k3_transactions SET status = ? WHERE id = ?");
            $stmtUpdateStatus->execute([$newStatus, $k3Id]);
        }

        $db->commit();
        $_SESSION['success'] = "Jumlah diterima K3 berhasil disimpan.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Terjadi kesalahan saat menyimpan data K3: " . $e->getMessage();
    }

    header("Location: index.php?page=k3&tug=" . urlencode($tug));
    exit();
}

// =========================================================
// SIMPAN / UBAH DETAIL BON (Nomor Seri, No. DPB/Bukti, Lokasi
// Penempatan, Kondisi Material, Keterangan Detile) — admin ATAU gudang2
// =========================================================
if (isset($_POST['update_k3_details'])) {
    if (!isAdmin() && !isGudang2()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengubah detail bon.";
        header("Location: index.php?page=k3");
        exit();
    }
    $id = $_POST['k3_id'] ?? '';
    $tug = $_POST['tug_number'] ?? '';
    $kondisi = $_POST['kondisi_material'] ?? 'masih_dapat_dipergunakan';
    $keterangan = trim($_POST['keterangan'] ?? '');
    $nomorSeri = trim($_POST['nomor_seri'] ?? '');
    $noDpbBukti = trim($_POST['no_dpb_bukti'] ?? '');
    $lokasiPenempatan = trim($_POST['lokasi_penempatan'] ?? '');

    $stmt = $db->prepare("UPDATE k3_transactions SET kondisi_material = ?, keterangan = ?, nomor_seri = ?, no_dpb_bukti = ?, lokasi_penempatan = ? WHERE id = ?");
    $stmt->execute([$kondisi, $keterangan, $nomorSeri, $noDpbBukti, $lokasiPenempatan, $id]);

    $_SESSION['success'] = "Detail bon K3 berhasil disimpan.";
    header("Location: index.php?page=k3&tug=" . urlencode($tug));
    exit();
}

// =========================================================
// SIMPAN / UBAH NAMA PENANDATANGAN (box TTD) — khusus admin
// =========================================================
if (isset($_POST['update_k3_signers'])) {
    if (!isAdmin() && !isGudang2()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat mengubah data tanda tangan.";
        header("Location: index.php?page=k3");
        exit();
    }
    $id = $_POST['k3_id'] ?? '';
    $tug = $_POST['tug_number'] ?? '';
    $setuju = trim($_POST['setuju_name'] ?? '');
    $kepalaGudang = trim($_POST['kepala_gudang_name'] ?? '');
    $pemeriksaPengawas = trim($_POST['pemeriksa_pengawas_name'] ?? '');
    $yangMenyerahkan = trim($_POST['yang_menyerahkan_name'] ?? '');

    $stmt = $db->prepare("UPDATE k3_transactions SET setuju_name = ?, kepala_gudang_name = ?, pemeriksa_pengawas_name = ?, yang_menyerahkan_name = ? WHERE id = ?");
    $stmt->execute([$setuju, $kepalaGudang, $pemeriksaPengawas, $yangMenyerahkan, $id]);

    $_SESSION['success'] = "Data tanda tangan berhasil disimpan.";
    header("Location: index.php?page=k3&tug=" . urlencode($tug));
    exit();
}

// =========================================================
// HAPUS K3 (khusus admin)
// =========================================================
if (isset($_GET['delete_k3'])) {
    if (!isAdmin()) {
        $_SESSION['error'] = "Hanya admin gudang PLN yang dapat menghapus data K3.";
        header("Location: index.php?page=k3");
        exit();
    }
    $id = $_GET['delete_k3'];
    $stmt = $db->prepare("DELETE FROM k3_transactions WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success'] = "Data K3 berhasil dihapus.";
    header("Location: index.php?page=k3");
    exit();
}
?>