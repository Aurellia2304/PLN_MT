<?php
/* =========================================================
   TAMPILAN ADMIN GUDANG 2 — di-include dari index.php SETELAH
   login berhasil (bukan file/site login terpisah). $db, $_SESSION,
   dan semua fungsi dari functions.php sudah tersedia di sini karena
   di-include di tengah proses index.php.
   ========================================================= */

$tug = trim($_GET['tug'] ?? $_POST['tug_number'] ?? '');

// =========================================================
// SIMPAN PERUBAHAN — DPB (semua field + item bisa diedit)
// =========================================================
// =========================================================
// SIMPAN PERUBAHAN — DPB (semua field + item bisa diedit)
// =========================================================
if (isset($_POST['update_g2_dpb'])) {
    $dpbId = $_POST['dpb_id'] ?? '';
    $tug   = trim($_POST['tug_number'] ?? '');

    // Status check block removed to allow editing received quantities even if status is 'selesai'

    // "Diterima Tanggal" selalu dipaksa ke tanggal hari ini saat disimpan
    $diterimaTgl = date('Y-m-d');

    $ids  = $_POST['item_id'] ?? [];
    $req  = $_POST['item_requested'] ?? [];
    $recv = $_POST['item_received'] ?? [];
    
    // Validasi SN wajib dan keunikan sebelum menyimpan
    $currentRequestSns = [];
    foreach ($ids as $i => $itemId) {
        $qd = max(0, (int)($recv[$i] ?? 0));
        
        // Ambil nama material, stok saat ini, dan jumlah diterima lama
        $stmtMat = $db->prepare("SELECT m.name, m.stock, di.quantity_received FROM dpb_items di LEFT JOIN materials m ON di.material_id = m.id WHERE di.id = ?");
        $stmtMat->execute([$itemId]);
        $matInfo = $stmtMat->fetch(PDO::FETCH_ASSOC);
        $materialName = $matInfo['name'] ?? '';
        $currentStock = (int)($matInfo['stock'] ?? 0);
        $oldReceived  = (int)($matInfo['quantity_received'] ?? 0);
        $maxAllowed   = $oldReceived + $currentStock;

        // Validasi stok tidak boleh negatif / tidak mencukupi
        if ($qd > $maxAllowed) {
            $_SESSION['error'] = "Gagal menyimpan: Stok material \"$materialName\" tidak mencukupi (tersisa $currentStock)";
            header("Location: index.php?tug=" . urlencode($tug));
            exit();
        }

        // Dapatkan array input SN untuk item ini
        $snsArr = $_POST['item_sn_' . $itemId] ?? [];
        // Normalisasi
        $snsArr = array_filter(array_map('trim', $snsArr));
        
        if (isMaterialWajibSN($materialName)) {
            if ($qd > 0) {
                if (empty($snsArr)) {
                    $_SESSION['error'] = "Serial Number (SN) wajib diisi untuk material: $materialName";
                    header("Location: index.php?tug=" . urlencode($tug));
                    exit();
                }
                foreach ($snsArr as $snVal) {
                    if (empty($snVal)) {
                        $_SESSION['error'] = "Serial Number (SN) wajib diisi untuk material: $materialName";
                        header("Location: index.php?tug=" . urlencode($tug));
                        exit();
                    }
                    $snLower = strtolower($snVal);
                    // Keunikan lokal dalam request
                    if (in_array($snLower, $currentRequestSns)) {
                        $_SESSION['error'] = "Serial Number (SN) \"$snVal\" tidak boleh diduplikat dalam satu pengajuan.";
                        header("Location: index.php?tug=" . urlencode($tug));
                        exit();
                    }
                    $currentRequestSns[] = $snLower;
                    
                    // Keunikan global di database
                    $stmtUnique = $db->prepare("
                        SELECT COUNT(*) FROM dpb_items 
                        WHERE id <> ? 
                          AND (
                            sn = ? 
                            OR sn LIKE ? 
                            OR sn LIKE ? 
                            OR sn LIKE ?
                          )
                    ");
                    $stmtUnique->execute([
                        $itemId,
                        $snVal,
                        $snVal . ', %',
                        '%, ' . $snVal,
                        '%, ' . $snVal . ', %'
                    ]);
                    if ($stmtUnique->fetchColumn() > 0) {
                        $_SESSION['error'] = "Serial Number (SN) \"$snVal\" sudah terdaftar di database pada transaksi lain.";
                        header("Location: index.php?tug=" . urlencode($tug));
                        exit();
                    }
                }
            }
        }
    }

    $stmt = $db->prepare("UPDATE dpb_transactions SET
        spk_number = ?, jenis_pekerjaan = ?, idpel = ?, customer_name = ?, customer_address = ?,
        daya = ?, ulp = ?, tanggal_diminta = ?, diterima_tgl = ?,
        penerima_name = ?, security_name = ?, menyerahkan_name = ?,
        setuju_name = ?, kepala_gudang_name = ?, pemeriksa_pengawas_name = ?
        WHERE id = ?");
    $stmt->execute([
        trim($_POST['spk_number'] ?? ''),
        trim($_POST['jenis_pekerjaan'] ?? ''),
        trim($_POST['idpel'] ?? ''),
        trim($_POST['customer_name'] ?? ''),
        trim($_POST['customer_address'] ?? ''),
        trim($_POST['daya'] ?? ''),
        trim($_POST['ulp'] ?? ''),
        trim($_POST['tanggal_diminta'] ?? '') ?: null,
        $diterimaTgl,
        trim($_POST['penerima_name'] ?? ''),
        trim($_POST['security_name'] ?? ''),
        trim($_POST['menyerahkan_name'] ?? ''),
        trim($_POST['setuju_name'] ?? ''),
        trim($_POST['kepala_gudang_name'] ?? ''),
        trim($_POST['pemeriksa_pengawas_name'] ?? ''),
        $dpbId
    ]);

    // Generate nomor surat jalan otomatis jika kosong
    $stmtSj = $db->prepare("SELECT surat_jalan_number, tanggal_diminta FROM dpb_transactions WHERE id = ?");
    $stmtSj->execute([$dpbId]);
    $dpbRow = $stmtSj->fetch(PDO::FETCH_ASSOC);
    if ($dpbRow && empty($dpbRow['surat_jalan_number'])) {
        $sjNumber = generateNextSuratJalanNumber($db, $dpbRow['tanggal_diminta'] ?: date('Y-m-d'));
        $stmtUpdateSj = $db->prepare("UPDATE dpb_transactions SET surat_jalan_number = ? WHERE id = ?");
        $stmtUpdateSj->execute([$sjNumber, $dpbId]);
    }

    foreach ($ids as $i => $itemId) {
        $qr = max(0, (int)($req[$i] ?? 0));
        $qd = max(0, (int)($recv[$i] ?? 0));
        
        $snsArr = $_POST['item_sn_' . $itemId] ?? [];
        $snsArr = array_filter(array_map('trim', $snsArr));
        $snString = implode(', ', $snsArr);
        
        $s = $db->prepare("UPDATE dpb_items SET quantity_requested = ?, quantity_received = ?, sn = ? WHERE id = ?");
        $s->execute([$qr, $qd, $snString, $itemId]);
    }

    $_SESSION['success'] = "Data DPB \"$tug\" berhasil diperbarui.";
    header("Location: index.php?tug=" . urlencode($tug));
    exit();
}

// =========================================================
// SIMPAN PERUBAHAN — K3
// =========================================================
if (isset($_POST['update_g2_k3'])) {
    $k3Id = $_POST['k3_id'] ?? '';
    $tug  = trim($_POST['tug_number'] ?? '');

    // Cek status K3 saat ini
    $stmtCheck = $db->prepare("SELECT status FROM k3_transactions WHERE id = ?");
    $stmtCheck->execute([$k3Id]);
    $currentStatus = $stmtCheck->fetchColumn();
    if ($currentStatus === 'selesai') {
        $_SESSION['error'] = "Data surat yang sudah selesai tidak dapat diubah!";
        header("Location: index.php?tug=" . urlencode($tug));
        exit();
    }

    $stmt = $db->prepare("UPDATE k3_transactions SET
        spk_number = ?, jenis_pekerjaan = ?, idpel = ?, customer_name = ?, customer_address = ?,
        kondisi_material = ?, gudang_pengembalian = ?, keterangan = ?, tanggal_diminta = ?,
        setuju_name = ?, kepala_gudang_name = ?, pemeriksa_pengawas_name = ?, yang_menyerahkan_name = ?,
        diterima_tgl = ?, malang_tanggal = ?
        WHERE id = ?");
    $stmt->execute([
        trim($_POST['spk_number'] ?? ''),
        trim($_POST['jenis_pekerjaan'] ?? ''),
        trim($_POST['idpel'] ?? ''),
        trim($_POST['customer_name'] ?? ''),
        trim($_POST['customer_address'] ?? ''),
        trim($_POST['kondisi_material'] ?? 'masih_dapat_dipergunakan'),
        trim($_POST['gudang_pengembalian'] ?? ''),
        trim($_POST['keterangan'] ?? ''),
        trim($_POST['tanggal_diminta'] ?? '') ?: null,
        trim($_POST['setuju_name'] ?? ''),
        trim($_POST['kepala_gudang_name'] ?? ''),
        trim($_POST['pemeriksa_pengawas_name'] ?? ''),
        trim($_POST['yang_menyerahkan_name'] ?? ''),
        trim($_POST['diterima_tgl'] ?? ''),
        trim($_POST['malang_tanggal'] ?? ''),
        $k3Id
    ]);

    $ids  = $_POST['item_id'] ?? [];
    $ret  = $_POST['item_returned'] ?? [];
    $recv = $_POST['item_received'] ?? [];
    foreach ($ids as $i => $itemId) {
        $qr = max(0, (int)($ret[$i] ?? 0));
        $qd = max(0, (int)($recv[$i] ?? 0));
        $s = $db->prepare("UPDATE k3_items SET quantity_returned = ?, quantity_received = ? WHERE id = ?");
        $s->execute([$qr, $qd, $itemId]);
    }

    $_SESSION['success'] = "Data K3 \"$tug\" berhasil diperbarui.";
    header("Location: index.php?tug=" . urlencode($tug));
    exit();
}

// =========================================================
// SIMPAN PERUBAHAN — K7
// =========================================================
if (isset($_POST['update_g2_k7'])) {
    $k7Id = $_POST['k7_id'] ?? '';
    $tug  = trim($_POST['tug_number'] ?? '');

    // Cek status K7 saat ini
    $stmtCheck = $db->prepare("SELECT status FROM k7_transactions WHERE id = ?");
    $stmtCheck->execute([$k7Id]);
    $currentStatus = $stmtCheck->fetchColumn();
    if ($currentStatus === 'selesai') {
        $_SESSION['error'] = "Data surat yang sudah selesai tidak dapat diubah!";
        header("Location: index.php?tug=" . urlencode($tug));
        exit();
    }

    $stmt = $db->prepare("UPDATE k7_transactions SET
        spk_number = ?, jenis_pekerjaan = ?, idpel = ?, customer_name = ?, customer_address = ?,
        daya = ?, ulp = ?, tanggal_diminta = ?,
        setuju_name = ?, kepala_gudang_name = ?, pemeriksa_pengawas_name = ?, penerima_name = ?,
        diterima_tgl = ?, malang_tanggal = ?
        WHERE id = ?");
    $stmt->execute([
        trim($_POST['spk_number'] ?? ''),
        trim($_POST['jenis_pekerjaan'] ?? ''),
        trim($_POST['idpel'] ?? ''),
        trim($_POST['customer_name'] ?? ''),
        trim($_POST['customer_address'] ?? ''),
        trim($_POST['daya'] ?? ''),
        trim($_POST['ulp'] ?? ''),
        trim($_POST['tanggal_diminta'] ?? '') ?: null,
        trim($_POST['setuju_name'] ?? ''),
        trim($_POST['kepala_gudang_name'] ?? ''),
        trim($_POST['pemeriksa_pengawas_name'] ?? ''),
        trim($_POST['penerima_name'] ?? ''),
        trim($_POST['diterima_tgl'] ?? ''),
        trim($_POST['malang_tanggal'] ?? ''),
        $k7Id
    ]);

    $ids  = $_POST['item_id'] ?? [];
    $req  = $_POST['item_requested'] ?? [];
    $recv = $_POST['item_received'] ?? [];
    foreach ($ids as $i => $itemId) {
        $qr = max(0, (int)($req[$i] ?? 0));
        $qd = max(0, (int)($recv[$i] ?? 0));
        $s = $db->prepare("UPDATE k7_items SET quantity_requested = ?, quantity_received = ? WHERE id = ?");
        $s->execute([$qr, $qd, $itemId]);
    }

    $_SESSION['success'] = "Data K7 \"$tug\" berhasil diperbarui.";
    header("Location: index.php?tug=" . urlencode($tug));
    exit();
}

// =========================================================
// PENCARIAN NOMOR TUG — cek di ketiga jenis transaksi
// =========================================================
$found = null;
$notFound = false;

if ($tug !== '') {
    $dpb = getDpbByTug($db, $tug);
    if ($dpb) {
        $found = ['type' => 'dpb', 'data' => $dpb];
    } else {
        $k3 = getK3ByTug($db, $tug);
        if ($k3) {
            $found = ['type' => 'k3', 'data' => $k3];
        } else {
            $k7 = getK7ByTug($db, $tug);
            if ($k7) {
                $found = ['type' => 'k7', 'data' => $k7];
            } else {
                $notFound = true;
            }
        }
    }
}

$kondisiList = [
    'masih_dapat_dipergunakan' => 'Masih Dapat Dipergunakan',
    'rusak'                    => 'Rusak',
    'baru'                     => 'Baru',
    'garansi'                  => 'Garansi',
];

// Data vendor + material, dipakai oleh form "Buat DPB Baru" di bawah
// (form ini meniru form pengajuan DPB yang ada di halaman Admin).
$vendors = getVendors($db);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Gudang 2 — Gudang Material PLN</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Poppins", Arial, sans-serif;
            color: #1f4460;
        }

        /* Background PERSIS seperti halaman login (login.php .login-gate-page) */
        .g2-page {
            min-height: 100vh;
            width: 100%;
            background:
                linear-gradient(rgba(255,255,255,0.75), rgba(255,255,255,0.75)),
                url('images/hero.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
            padding-bottom: 3rem;
        }

        /* Logo PLN pojok kiri atas */
        .g2-logo {
            position: absolute;
            top: 1.5rem;
            left: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            z-index: 5;
        }
        .g2-logo img {
            height: 50px;
            width: auto;
            max-width: 72px;
            object-fit: contain;
        }
        .g2-logo span {
            color: #0b2b4a;
            font-weight: 700;
            font-size: 1.05rem;
        }

        .g2-logout {
            position: absolute;
            top: 1.8rem;
            right: 1.8rem;
            z-index: 5;
        }
        .g2-logout a {
            background: #fff;
            color: #0b2b4a;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.55rem 1.1rem;
            border-radius: 30px;
            box-shadow: 0 6px 18px rgba(11,43,74,0.15);
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .g2-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 6.5rem 1.2rem 1.5rem;
            text-align: center;
        }
        .g2-center .tag {
            display: inline-block;
            background: #ffd966;
            color: #082038;
            font-weight: 700;
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.3rem 0.9rem;
            border-radius: 20px;
            margin-bottom: 0.9rem;
        }
        .g2-center h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #0b2b4a;
            margin: 0 0 0.4rem;
        }
        .g2-center p {
            margin: 0;
            color: #3d5977;
            font-size: 0.95rem;
        }

        .g2-wrap { max-width: 1000px; margin: 0 auto; padding: 0 1.5rem; }

        .g2-search-card {
            background: #fff; border-radius: 22px; padding: 1.6rem 1.8rem;
            box-shadow: 0 20px 50px rgba(11,43,74,0.15); margin: 1.6rem 0;
        }
        .g2-search-card form { display: flex; gap: 0.8rem; flex-wrap: wrap; }
        .g2-search-card input[type="text"] {
            flex: 1; min-width: 220px; padding: 0.75rem 1rem; border-radius: 12px;
            border: 1.5px solid #dbe4ec; font-size: 0.95rem; font-family: inherit;
        }
        .g2-search-card input[type="text"]:focus { outline: none; border-color: #14828a; }
        .g2-search-btn {
            background: #0b2b4a; color: #fff; border: none; padding: 0.75rem 1.6rem;
            border-radius: 12px; font-weight: 700; cursor: pointer; font-size: 0.92rem;
            display: inline-flex; align-items: center; gap: 0.5rem;
        }
        .g2-search-btn:hover { background: #082038; }

        .g2-alert { padding: 0.85rem 1.1rem; border-radius: 12px; margin: 1.5rem 0 0; font-size: 0.9rem; }
        .g2-alert.error { background: #fdeaea; color: #d64545; border: 1px solid #f6c2c2; }
        .g2-alert.success { background: #e3f7ec; color: #1e8e5a; border: 1px solid #b7e6cb; }

        .g2-result-card {
            background: #fff; border-radius: 22px; padding: 1.8rem 2rem;
            box-shadow: 0 20px 50px rgba(11,43,74,0.15); margin: 2rem 0;
        }
        .g2-result-card h2 { color: #0b2b4a; margin-top: 0; display: flex; align-items: center; gap: 0.6rem; }
        .g2-type-badge {
            font-size: 0.7rem; font-weight: 700; padding: 0.25rem 0.7rem; border-radius: 20px;
            text-transform: uppercase; letter-spacing: 0.03em;
        }
        .g2-type-badge.dpb { background: #d7f3f5; color: #14828a; }
        .g2-type-badge.k3 { background: #fdf1e0; color: #a35b00; }
        .g2-type-badge.k7 { background: #f2e7f5; color: #7a3b96; }

        .g2-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin: 1.2rem 0 1.6rem; }
        .g2-field label {
            display: block; font-size: 0.78rem; font-weight: 700; color: #0b2b4a;
            margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 0.02em;
        }
        .g2-field input, .g2-field select {
            width: 100%; padding: 0.6rem 0.8rem; border-radius: 10px;
            border: 1.5px solid #dbe4ec; font-family: inherit; font-size: 0.88rem; background: #fbfdff;
        }
        .g2-field input:focus, .g2-field select:focus { outline: none; border-color: #14828a; }
        .g2-field textarea {
            width: 100%; padding: 0.6rem 0.8rem; border-radius: 10px;
            border: 1.5px solid #dbe4ec; font-family: inherit; font-size: 0.88rem; background: #fbfdff;
            min-height: 60px; resize: vertical;
        }

        table.g2-items { width: 100%; border-collapse: collapse; margin: 0.8rem 0 1.6rem; font-size: 0.86rem; table-layout: fixed; }
        table.g2-items th { background: #d7f3f5; color: #0b2b4a; padding: 0.6rem 0.7rem; text-align: left; font-weight: 700; }
        table.g2-items td { padding: 0.5rem 0.7rem; border-bottom: 1px solid #eef2f6; word-wrap: break-word; overflow-wrap: break-word; }
        table.g2-items input { width: 90px; padding: 0.4rem 0.5rem; border-radius: 8px; border: 1.5px solid #dbe4ec; font-family: inherit; font-size: 0.85rem; }

        .g2-save-btn {
            background: #1e8e5a; color: #fff; border: none; padding: 0.75rem 1.6rem;
            border-radius: 30px; font-weight: 700; cursor: pointer; font-size: 0.9rem;
        }
        .g2-save-btn:hover { filter: brightness(1.08); }

        .g2-print-row {
            margin-top: 1.4rem; padding-top: 1.4rem; border-top: 1px dashed #dbe4ec;
            display: flex; gap: 0.8rem; flex-wrap: wrap;
        }
        .g2-print-btn {
            background: #ffd966; color: #082038; border: none; padding: 0.8rem 1.8rem;
            border-radius: 30px; font-weight: 700; cursor: pointer; font-size: 0.92rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem;
        }
        .g2-print-btn:hover { filter: brightness(1.05); }
        .g2-print-btn.secondary { background: #eef3f7; color: #1f4460; }

        .g2-empty {
            background: #fff; border-radius: 22px; padding: 2.5rem 2rem; text-align: center;
            box-shadow: 0 20px 50px rgba(11,43,74,0.15); color: #6d859b; margin: 2rem 0;
        }
        .g2-empty i { font-size: 2.2rem; color: #dbe4ec; margin-bottom: 0.8rem; display: block; }

        /* =====================================================
           FORM "BUAT DPB BARU" (meniru form pengajuan DPB admin)
           Baris material dibuat dinamis lewat script.js, yang
           menghasilkan elemen ber-class .flex-row / .form-group /
           .btn-danger dsb — style di bawah ini menyamakan
           tampilannya dengan style asli di style.css.
           ===================================================== */
        /* Kartu form "Buat DPB Baru" — nilai PERSIS sama dengan .card di style.css admin
           (border-radius:16px, padding:1.6rem, shadow tipis, border 1px solid #eef2f6),
           supaya tampilannya sama persis dengan form DPB di halaman admin. */
        .g2-dpb-form-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.6rem;
            box-shadow: 0 6px 20px rgba(11, 43, 74, 0.08);
            border: 1px solid #eef2f6;
            margin-top: 1.5rem;
        }
        .g2-dpb-form-card h3 { margin: 0 0 1rem; color: #0b2b4a; display: flex; align-items: center; gap: 0.5rem; }

        /* Baris material dinamis (ditambahkan lewat addDpbItemRow() di script.js) —
           nilai PERSIS sama dengan .dpb-item-row di style.css admin. */
        .dpb-item-row {
            background: #f8fbfd;
            border: 1px solid #eef2f6;
            border-radius: 14px;
            padding: 0.9rem 1rem 0.3rem;
            margin-bottom: 0.8rem;
        }

        .btn-success, .btn-info, .btn-warning, .btn-danger {
            border: none; border-radius: 30px; padding: 0.6rem 1.2rem; font-weight: 600;
            font-size: 0.85rem; cursor: pointer; color: #fff; font-family: inherit;
        }
        .btn-success { background: #1e8e5a; }
        .btn-info { background: #14828a; }
        .btn-warning { background: #ffd966; color: #082038; }
        .btn-danger { background: #d64545; }
        .btn-success:hover, .btn-info:hover, .btn-warning:hover, .btn-danger:hover { filter: brightness(1.07); }

        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block; font-size: 0.85rem; font-weight: 600; color: #0b2b4a; margin-bottom: 0.35rem;
        }
        .form-group input, .form-group select {
            width: 100%; padding: 0.65rem 0.9rem; border-radius: 10px;
            border: 1.5px solid #dbe4ec; font-family: inherit; font-size: 0.9rem; background: #fbfdff;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #14828a; }

        .flex-row { display: flex; gap: 1.2rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 1.1rem; }
        .flex-row:last-of-type { margin-bottom: 0; }
        .flex-row .form-group { flex: 1; min-width: 200px; margin-bottom: 0; }

        /* =====================================================
           TABEL ITEM (DPB/K3/K7) — RESPONSIF
           Di layar sempit, tabel berubah jadi kartu bertumpuk
           supaya kolom SN (dan kolom lain) langsung terlihat
           tanpa perlu menggeser (drag) ke kanan.
           ===================================================== */
        .g2-table-wrap { width: 100%; overflow-x: auto; }
        table.g2-items input[type="text"],
        table.g2-items input[type="number"] { width: 100%; box-sizing: border-box; }

        @media (max-width: 780px) {
            table.g2-items, table.g2-items thead, table.g2-items tbody,
            table.g2-items th, table.g2-items td, table.g2-items tr { display: block; }
            table.g2-items thead { position: absolute; left: -9999px; top: -9999px; }
            table.g2-items tr {
                margin-bottom: 1rem; border: 1.5px solid #eef2f6; border-radius: 14px;
                padding: 0.7rem 1rem; background: #fbfdff;
            }
            table.g2-items td {
                display: flex; justify-content: space-between; align-items: center; gap: 0.8rem;
                border: none; border-bottom: 1px solid #eef2f6; padding: 0.5rem 0; text-align: right;
            }
            table.g2-items td:last-child { border-bottom: none; }
            table.g2-items td::before {
                content: attr(data-label); font-weight: 700; color: #0b2b4a; font-size: 0.75rem;
                text-transform: uppercase; letter-spacing: 0.02em; flex-shrink: 0; text-align: left;
            }
            table.g2-items td input, table.g2-items td select { max-width: 160px; text-align: right; }
        }
    </style>
</head>
<body>

<div class="g2-page">

    <div class="g2-logo">
        <img src="images/logo.png" alt="PLN Logo">
        <span>Gudang Material</span>
    </div>

    <div class="g2-logout">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>

    <div class="g2-center">
        <span class="tag"><i class="fas fa-warehouse"></i> Portal Admin Gudang 2</span>
        <h1>Selamat Datang Admin Gudang</h1>
        <p>Cari nomor TUG, kelola datanya, lalu cetak.</p>
    </div>

    <div class="g2-wrap">

        <div class="g2-search-card">
            <form method="GET" action="index.php">
                <input type="text" name="tug" placeholder="Masukkan Nomor TUG..." value="<?= htmlspecialchars($tug) ?>" autofocus>
                <button type="submit" class="g2-search-btn"><i class="fas fa-search"></i> Cari Nomor TUG</button>
            </form>
        </div>

        <div class="g2-dpb-form-card">
            <h3><i class="fas fa-file-signature"></i> Ajukan Permintaan Material Baru (DPB)</h3>

            <form method="POST" action="Dpb.php" id="dpbCreateForm">
                <div class="flex-row">
                    <div class="form-group">
                        <label>Nomor TUG</label>
                        <input type="text" name="tug_number" placeholder="TUG 5. MLG26-XXXX" required>
                    </div>
                    <div class="form-group">
                        <label>Tanggal Diminta</label>
                        <input type="date" name="tanggal_diminta" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
                    </div>
                </div>

                <div class="flex-row">
                    <div class="form-group">
                        <label>Vendor</label>
                        <select name="vendor_id" id="dpbVendorSelect" onchange="autofillVendor()" required>
                            <option value="">-- pilih PT / vendor --</option>
                            <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>No. SPK <span style="color:red;">*</span></label>
                        <input type="text" name="spk_number" id="dpbSpkInput" required>
                    </div>
                </div>

                <div class="flex-row">
                    <div class="form-group">
                        <label>Jenis Pekerjaan <span style="color:red;">*</span></label>
                        <input type="text" name="jenis_pekerjaan" id="dpbJenisInput" required>
                    </div>
                    <div class="form-group">
                        <label>IPDEL <span style="color:red;">*</span></label>
                        <input type="text" name="idpel" id="dpbIdpelInput" required>
                    </div>
                    <div class="form-group">
                        <label>Daya <span style="color:red;">*</span></label>
                        <input type="text" name="daya" id="dpbDayaInput" required>
                    </div>
                    <div class="form-group">
                        <label>ULP <span style="color:red;">*</span></label>
                        <input type="text" name="ulp" id="dpbUlpInput" required>
                    </div>
                </div>

                <div class="flex-row">
                    <div class="form-group">
                        <label>Nama Pelanggan <span style="color:red;">*</span></label>
                        <input type="text" name="customer_name" placeholder="Nama pelanggan" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat Pelanggan <span style="color:red;">*</span></label>
                        <input type="text" name="customer_address" placeholder="Alamat lengkap pelanggan" required>
                    </div>
                </div>

                <h4 style="color:#0b2b4a; margin-top:1.2rem;">Data Tanda Tangan</h4>
                <div class="flex-row">
                    <div class="form-group">
                        <label>Penerima <span style="color:red;">*</span></label>
                        <input type="text" name="penerima_name" placeholder="Nama penerima" required>
                    </div>
                    <div class="form-group">
                        <label>Security</label>
                        <input type="text" name="security_name" placeholder="Nama security">
                    </div>
                    <div class="form-group">
                        <label>Yang Menyerahkan</label>
                        <input type="text" name="menyerahkan_name" placeholder="Nama yang menyerahkan">
                    </div>
                </div>
                <div class="flex-row" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Setuju (Manager/Asman)</label>
                        <input type="text" name="setuju_name" value="" <?= $disabledAttr ?>>
                    </div>
                    <div class="form-group">
                        <label>Kepala Gudang</label>
                        <input type="text" name="kepala_gudang_name" value="" <?= $disabledAttr ?>>
                    </div>
                    <div class="form-group">
                        <label>Pemeriksa / Petugas</label>
                        <input type="text" name="pemeriksa_pengawas_name" placeholder="Nama pemeriksa/petugas">
                    </div>
                </div>

                <h4 style="color:#0b2b4a; margin-top:1.2rem;">Daftar Material Diminta</h4>
                <div id="dpbItemsWrap"></div>
                <button type="button" class="btn-info" onclick="addDpbItemRow()" style="margin-top:0.5rem;">
                    <i class="fas fa-plus"></i> Tambah Baris Material
                </button>

                <div style="margin-top:1.2rem;">
                    <button type="submit" name="create_dpb" class="btn-success"><i class="fas fa-save"></i> Ajukan</button>
                </div>
            </form>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="g2-alert error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php elseif (isset($_SESSION['success'])): ?>
            <div class="g2-alert success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <?php if ($notFound): ?>
            <div class="g2-empty">
                <i class="fas fa-file-circle-question"></i>
                Nomor TUG "<strong><?= htmlspecialchars($tug) ?></strong>" tidak ditemukan di data DPB, K3, maupun K7.
            </div>

        <?php elseif ($found && $found['type'] === 'dpb'):
            $d = $found['data'];
            $isReadOnly = false;
            $disabledAttr = $isReadOnly ? 'disabled' : ''; ?>
            <div class="g2-result-card">
                <h2><span class="g2-type-badge dpb">DPB</span> Nomor TUG: <?= htmlspecialchars($d['tug_number']) ?></h2>

                <form method="POST" action="index.php" id="g2DpbForm">
                    <input type="hidden" name="dpb_id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="tug_number" value="<?= htmlspecialchars($d['tug_number']) ?>">

                    <div class="g2-grid">
                        <div class="g2-field">
                            <label><?= empty($d['surat_jalan_number']) ? 'Nomor Surat Jalan (Otomatis)' : 'Nomor Surat Jalan' ?></label>
                            <input type="text" value="<?= htmlspecialchars($d['surat_jalan_number'] ?: generateNextSuratJalanNumber($db, $d['tanggal_diminta'] ?: date('Y-m-d'))) ?>" readonly style="background-color: #f1f5f9; color: #64748b; font-weight: bold; border: 1px solid #cbd5e1; cursor: not-allowed;">
                        </div>
                        <div class="g2-field"><label>Vendor</label><input type="text" value="<?= htmlspecialchars($d['vendor_name'] ?? '-') ?>" disabled></div>
                        <div class="g2-field"><label>No. SPK</label><input type="text" name="spk_number" value="<?= htmlspecialchars($d['spk_number'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Jenis Pekerjaan</label><input type="text" name="jenis_pekerjaan" value="<?= htmlspecialchars($d['jenis_pekerjaan'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>IDPEL</label><input type="text" name="idpel" value="<?= htmlspecialchars($d['idpel'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Nama Pelanggan</label><input type="text" name="customer_name" value="<?= htmlspecialchars($d['customer_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Alamat Pelanggan</label><input type="text" name="customer_address" value="<?= htmlspecialchars($d['customer_address'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Daya</label><input type="text" name="daya" value="<?= htmlspecialchars($d['daya'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>ULP</label><input type="text" name="ulp" value="<?= htmlspecialchars($d['ulp'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Tanggal Diminta</label><input type="date" name="tanggal_diminta" value="<?= htmlspecialchars($d['tanggal_diminta'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field">
                            <label>Diterima di</label>
                            <input type="text" name="diterima_tgl" value="<?= htmlspecialchars($d['diterima_tgl'] ?? '') ?>" <?= $disabledAttr ?> placeholder="....................">
                        </div>
                        <div class="g2-field">
                            <label>Malang, Tanggal</label>
                            <input type="date" name="malang_tanggal" value="<?= htmlspecialchars($d['malang_tanggal'] ?? '') ?>" <?= $disabledAttr ?>>
                        </div>
                    </div>

                    <div class="g2-table-wrap">
                    <table class="g2-items">
                        <colgroup>
                            <col style="width:4%"><col style="width:23%"><col style="width:10%"><col style="width:7%">
                            <col style="width:11%"><col style="width:11%"><col style="width:34%">
                        </colgroup>
                        <thead><tr><th>#</th><th>Nama Material</th><th>Norm</th><th>Satuan</th><th>Diminta</th><th>Diterima</th><th>SN</th></tr></thead>
                        <tbody>
                        <?php foreach ($d['items'] as $i => $it): ?>
                            <tr>
                                <td data-label="No."><?= $i + 1 ?></td>
                                <td data-label="Nama Material"><?= htmlspecialchars($it['material_name'] ?? '-') ?></td>
                                <td data-label="Norm"><?= htmlspecialchars($it['norm'] ?? '-') ?></td>
                                <td data-label="Satuan"><?= htmlspecialchars($it['unit'] ?? '-') ?></td>
                                <td data-label="Diminta">
                                    <input type="hidden" name="item_id[]" value="<?= $it['id'] ?>">
                                    <input type="number" min="0" name="item_requested[]" value="<?= (int)$it['quantity_requested'] ?>" <?= $disabledAttr ?>>
                                </td>
                                <td data-label="Diterima">
                                    <input type="number" min="0" name="item_received[]" value="<?= (int)$it['quantity_received'] ?>" class="item-recv-input" data-material-name="<?= htmlspecialchars($it['material_name']) ?>" data-material-stock="<?= (int)($it['material_stock'] ?? 0) ?>" data-old-received="<?= (int)$it['quantity_received'] ?>" <?= $disabledAttr ?>>
                                </td>
                                <td data-label="SN">
                                    <?php if ($isReadOnly): ?>
                                        <?= htmlspecialchars($it['sn'] ?: '-') ?>
                                    <?php else: ?>
                                        <div class="sn-cell-container" data-material-name="<?= htmlspecialchars($it['material_name']) ?>" data-item-id="<?= $it['id'] ?>" data-saved-sn="<?= htmlspecialchars($it['sn'] ?? '') ?>"></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <div class="g2-grid">
                        <div class="g2-field"><label>Penerima</label><input type="text" name="penerima_name" value="<?= htmlspecialchars($d['penerima_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Security</label><input type="text" name="security_name" value="<?= htmlspecialchars($d['security_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Yang Menyerahkan</label><input type="text" name="menyerahkan_name" value="<?= htmlspecialchars($d['menyerahkan_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Setuju</label><input type="text" name="setuju_name" value="<?= htmlspecialchars($d['setuju_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Kepala Gudang</label><input type="text" name="kepala_gudang_name" value="<?= htmlspecialchars($d['kepala_gudang_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Pemeriksa / Petugas</label><input type="text" name="pemeriksa_pengawas_name" value="<?= htmlspecialchars($d['pemeriksa_pengawas_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                    </div>

                    <?php if (!$isReadOnly): ?>
                        <button type="submit" name="update_g2_dpb" class="g2-save-btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    <?php endif; ?>
                </form>

                <div class="g2-print-row" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="printDPB.php?tug=<?= urlencode($d['tug_number']) ?>" target="_blank" class="g2-print-btn"><i class="fas fa-print"></i> Cetak Surat Jalan</a>
                    <a href="printDPBForm.php?tug=<?= urlencode($d['tug_number']) ?>" target="_blank" class="g2-print-btn"><i class="fas fa-print"></i> Cetak DPB</a>
                </div>
            </div>

        <?php elseif ($found && $found['type'] === 'k3'):
            $k = $found['data'];
            $isReadOnly = ($k['status'] === 'selesai');
            $disabledAttr = $isReadOnly ? 'disabled' : ''; ?>
            <div class="g2-result-card">
                <h2><span class="g2-type-badge k3">K3</span> Nomor TUG: <?= htmlspecialchars($k['tug_number']) ?></h2>

                <form method="POST" action="index.php">
                    <input type="hidden" name="k3_id" value="<?= $k['id'] ?>">
                    <input type="hidden" name="tug_number" value="<?= htmlspecialchars($k['tug_number']) ?>">

                    <div class="g2-grid">
                        <div class="g2-field"><label>Vendor</label><input type="text" value="<?= htmlspecialchars($k['vendor_name'] ?? '-') ?>" disabled></div>
                        <div class="g2-field"><label>No. SPK</label><input type="text" name="spk_number" value="<?= htmlspecialchars($k['spk_number'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Jenis Pekerjaan</label><input type="text" name="jenis_pekerjaan" value="<?= htmlspecialchars($k['jenis_pekerjaan'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>IDPEL</label><input type="text" name="idpel" value="<?= htmlspecialchars($k['idpel'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Nama Pelanggan</label><input type="text" name="customer_name" value="<?= htmlspecialchars($k['customer_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Alamat Pelanggan</label><input type="text" name="customer_address" value="<?= htmlspecialchars($k['customer_address'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Gudang Pengembalian</label><input type="text" name="gudang_pengembalian" value="<?= htmlspecialchars($k['gudang_pengembalian'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Tanggal Diminta</label><input type="date" name="tanggal_diminta" value="<?= htmlspecialchars($k['tanggal_diminta'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field">
                            <label>Kondisi Material</label>
                            <select name="kondisi_material" <?= $disabledAttr ?>>
                                <?php foreach ($kondisiList as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $k['kondisi_material'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="g2-field">
                            <label>Diterima di</label>
                            <input type="text" name="diterima_tgl" value="<?= htmlspecialchars($k['diterima_tgl'] ?? '') ?>" <?= $disabledAttr ?> placeholder="....................">
                        </div>
                        <div class="g2-field">
                            <label>Malang, Tanggal</label>
                            <input type="date" name="malang_tanggal" value="<?= htmlspecialchars($k['malang_tanggal'] ?? '') ?>" <?= $disabledAttr ?>>
                        </div>
                        <div class="g2-field" style="grid-column: 1 / -1;"><label>Keterangan</label><textarea name="keterangan" <?= $disabledAttr ?>><?= htmlspecialchars($k['keterangan'] ?? '') ?></textarea></div>
                    </div>

                    <div class="g2-table-wrap">
                    <table class="g2-items">
                        <colgroup>
                            <col style="width:5%"><col style="width:32%"><col style="width:13%"><col style="width:9%">
                            <col style="width:20%"><col style="width:21%">
                        </colgroup>
                        <thead><tr><th>#</th><th>Nama Material</th><th>Norm</th><th>Satuan</th><th>Dikembalikan</th><th>Diterima</th></tr></thead>
                        <tbody>
                        <?php foreach ($k['items'] as $i => $it): ?>
                            <tr>
                                <td data-label="No."><?= $i + 1 ?></td>
                                <td data-label="Nama Material"><?= htmlspecialchars($it['material_name'] ?? '-') ?></td>
                                <td data-label="Norm"><?= htmlspecialchars($it['norm'] ?? '-') ?></td>
                                <td data-label="Satuan"><?= htmlspecialchars($it['unit'] ?? '-') ?></td>
                                <td data-label="Dikembalikan">
                                    <input type="hidden" name="item_id[]" value="<?= $it['id'] ?>">
                                    <input type="number" min="0" name="item_returned[]" value="<?= (int)$it['quantity_returned'] ?>" <?= $disabledAttr ?>>
                                </td>
                                <td data-label="Diterima"><input type="number" min="0" name="item_received[]" value="<?= (int)$it['quantity_received'] ?>" <?= $disabledAttr ?>></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <div class="g2-grid">
                        <div class="g2-field"><label>Setuju</label><input type="text" name="setuju_name" value="<?= htmlspecialchars($k['setuju_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Kepala Gudang</label><input type="text" name="kepala_gudang_name" value="<?= htmlspecialchars($k['kepala_gudang_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Pemeriksa / Pengawas</label><input type="text" name="pemeriksa_pengawas_name" value="<?= htmlspecialchars($k['pemeriksa_pengawas_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Yang Menyerahkan</label><input type="text" name="yang_menyerahkan_name" value="<?= htmlspecialchars($k['yang_menyerahkan_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                    </div>

                    <?php if (!$isReadOnly): ?>
                        <button type="submit" name="update_g2_k3" class="g2-save-btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    <?php endif; ?>
                </form>

                <div class="g2-print-row" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="printK3SJ.php?tug=<?= urlencode($k['tug_number']) ?>" target="_blank" class="g2-print-btn"><i class="fas fa-save"></i> Simpan Surat Jalan</a>
                    <a href="printK3.php?tug=<?= urlencode($k['tug_number']) ?>" target="_blank" class="g2-print-btn" style="background: #14828a; color: #fff;"><i class="fas fa-save"></i> Simpan Surat TUG</a>
                </div>
            </div>

        <?php elseif ($found && $found['type'] === 'k7'):
            $k = $found['data'];
            $isReadOnly = ($k['status'] === 'selesai');
            $disabledAttr = $isReadOnly ? 'disabled' : ''; ?>
            <div class="g2-result-card">
                <h2><span class="g2-type-badge k7">K7</span> Nomor TUG: <?= htmlspecialchars($k['tug_number']) ?></h2>

                <form method="POST" action="index.php">
                    <input type="hidden" name="k7_id" value="<?= $k['id'] ?>">
                    <input type="hidden" name="tug_number" value="<?= htmlspecialchars($k['tug_number']) ?>">

                    <div class="g2-grid">
                        <div class="g2-field"><label>Vendor</label><input type="text" value="<?= htmlspecialchars($k['vendor_name'] ?? '-') ?>" disabled></div>
                        <div class="g2-field"><label>No. SPK</label><input type="text" name="spk_number" value="<?= htmlspecialchars($k['spk_number'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Jenis Pekerjaan</label><input type="text" name="jenis_pekerjaan" value="<?= htmlspecialchars($k['jenis_pekerjaan'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>IDPEL</label><input type="text" name="idpel" value="<?= htmlspecialchars($k['idpel'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Nama Pelanggan</label><input type="text" name="customer_name" value="<?= htmlspecialchars($k['customer_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Alamat Pelanggan</label><input type="text" name="customer_address" value="<?= htmlspecialchars($k['customer_address'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Daya</label><input type="text" name="daya" value="<?= htmlspecialchars($k['daya'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>ULP</label><input type="text" name="ulp" value="<?= htmlspecialchars($k['ulp'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Tanggal Diminta</label><input type="date" name="tanggal_diminta" value="<?= htmlspecialchars($k['tanggal_diminta'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field">
                            <label>Diterima di</label>
                            <input type="text" name="diterima_tgl" value="<?= htmlspecialchars($k['diterima_tgl'] ?? '') ?>" <?= $disabledAttr ?> placeholder="....................">
                        </div>
                        <div class="g2-field">
                            <label>Malang, Tanggal</label>
                            <input type="date" name="malang_tanggal" value="<?= htmlspecialchars($k['malang_tanggal'] ?? '') ?>" <?= $disabledAttr ?>>
                        </div>
                    </div>

                    <div class="g2-table-wrap">
                    <table class="g2-items">
                        <colgroup>
                            <col style="width:5%"><col style="width:32%"><col style="width:13%"><col style="width:9%">
                            <col style="width:20%"><col style="width:21%">
                        </colgroup>
                        <thead><tr><th>#</th><th>Nama Material</th><th>Norm</th><th>Satuan</th><th>Diminta</th><th>Diterima</th></tr></thead>
                        <tbody>
                        <?php foreach ($k['items'] as $i => $it): ?>
                            <tr>
                                <td data-label="No."><?= $i + 1 ?></td>
                                <td data-label="Nama Material"><?= htmlspecialchars($it['material_name'] ?? '-') ?></td>
                                <td data-label="Norm"><?= htmlspecialchars($it['norm'] ?? '-') ?></td>
                                <td data-label="Satuan"><?= htmlspecialchars($it['unit'] ?? '-') ?></td>
                                <td data-label="Diminta">
                                    <input type="hidden" name="item_id[]" value="<?= $it['id'] ?>">
                                    <input type="number" min="0" name="item_requested[]" value="<?= (int)$it['quantity_requested'] ?>" <?= $disabledAttr ?>>
                                </td>
                                <td data-label="Diterima"><input type="number" min="0" name="item_received[]" value="<?= (int)$it['quantity_received'] ?>" <?= $disabledAttr ?>></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <div class="g2-grid">
                        <div class="g2-field"><label>Setuju</label><input type="text" name="setuju_name" value="<?= htmlspecialchars($k['setuju_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Kepala Gudang</label><input type="text" name="kepala_gudang_name" value="<?= htmlspecialchars($k['kepala_gudang_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Pemeriksa / Pengawas</label><input type="text" name="pemeriksa_pengawas_name" value="<?= htmlspecialchars($k['pemeriksa_pengawas_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                        <div class="g2-field"><label>Penerima</label><input type="text" name="penerima_name" value="<?= htmlspecialchars($k['penerima_name'] ?? '') ?>" <?= $disabledAttr ?>></div>
                    </div>

                    <?php if (!$isReadOnly): ?>
                        <button type="submit" name="update_g2_k7" class="g2-save-btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                    <?php endif; ?>
                </form>

                <div class="g2-print-row" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="printK7SJ.php?tug=<?= urlencode($k['tug_number']) ?>" target="_blank" class="g2-print-btn"><i class="fas fa-save"></i> Simpan Surat Jalan</a>
                    <a href="printK7.php?tug=<?= urlencode($k['tug_number']) ?>" target="_blank" class="g2-print-btn" style="background: #14828a; color: #fff;"><i class="fas fa-save"></i> Simpan Surat TUG</a>
                </div>
            </div>

        <?php else: ?>
            <div class="g2-empty">
                <i class="fas fa-magnifying-glass"></i>
                Masukkan nomor TUG di kotak pencarian untuk mulai mengelola dokumennya.
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
    // Data material & normalisasi resmi, dipakai oleh script.js untuk
    // autocomplete dan auto-isi Satuan/Norm di form "Buat DPB Baru" di atas.
    window.MATERIALS_DATA = <?= json_encode(getMaterials($db)) ?>;
    window.NORMALISASI_DATA = <?= json_encode(getNormalisasiData()) ?>;
</script>
<script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>"></script>
<script>
    // Dynamic SN input listeners for static DPB edit page in gudang2_view.php
    document.addEventListener("DOMContentLoaded", function() {
        var form = document.getElementById("g2DpbForm");
        if (!form) return;
        
        var snCells = form.querySelectorAll('.sn-cell-container');
        snCells.forEach(function(cell) {
            var row = cell.closest('tr');
            var recvInput = row.querySelector('.item-recv-input');
            if (recvInput) {
                var updateHandler = function() {
                    var qty = parseFloat(recvInput.value) || 0;
                    var materialName = cell.getAttribute('data-material-name');
                    var itemId = cell.getAttribute('data-item-id');
                    var savedSnAttr = cell.getAttribute('data-saved-sn') || '';

                    if (isMaterialWajibSN(materialName)) {
                        if (qty <= 0) {
                            cell.innerHTML = '<span style="color:#64748b; font-size:11px;">Tidak ada material diterima</span>';
                        } else {
                            var existingInput = cell.querySelector('.sn-input-field');
                            if (!existingInput) {
                                cell.innerHTML = '<input type="text" name="item_sn_' + itemId + '[]" value="' + escapeHtml(savedSnAttr) + '" class="form-control sn-input-field" placeholder="Masukkan Serial Number" style="font-size:12px; padding:4px 8px; width:100%; border-radius:6px; border:1px solid #cbd5e1;" required>';
                            }
                        }
                    } else {
                        var existingInput = cell.querySelector('input');
                        if (!existingInput) {
                            cell.innerHTML = '<input type="text" name="item_sn_' + itemId + '[]" value="' + escapeHtml(savedSnAttr) + '" class="form-control" placeholder="Optional SN" style="font-size:12px; padding:4px 8px; width:100%; border-radius:6px; border:1px solid #cbd5e1;">';
                        }
                    }
                };

                updateHandler();
                recvInput.addEventListener('input', updateHandler);
                recvInput.addEventListener('change', updateHandler);
            }
        });

        form.addEventListener('submit', function(e) {
            var inputs = form.querySelectorAll('.item-recv-input');
            var ok = true;
            var currentRequestSns = [];
            inputs.forEach(function(recvInput) {
                if (!ok) return;
                var qty = parseFloat(recvInput.value) || 0;
                var stock = parseFloat(recvInput.getAttribute('data-material-stock')) || 0;
                var oldRecv = parseFloat(recvInput.getAttribute('data-old-received')) || 0;
                var maxAllowed = oldRecv + stock;
                var row = recvInput.closest('tr');
                var cell = row.querySelector('.sn-cell-container');
                var materialName = cell ? cell.getAttribute('data-material-name') : '';

                // Stock validation
                if (qty > maxAllowed) {
                    alert("Gagal menyimpan: Stok material \"" + materialName + "\" tidak mencukupi (tersisa " + stock + ")");
                    e.preventDefault();
                    recvInput.focus();
                    ok = false;
                    return;
                }

                if (cell && isMaterialWajibSN(materialName) && qty > 0) {
                    var snFields = cell.querySelectorAll('.sn-input-field');
                    if (snFields.length === 0) {
                        alert('Serial Number (SN) wajib diisi untuk material: ' + materialName);
                        e.preventDefault();
                        ok = false;
                        return;
                    }
                    var f = snFields[0];
                    var val = f.value.trim();
                    if (!val) {
                        alert('Serial Number (SN) wajib diisi untuk material: ' + materialName);
                        e.preventDefault();
                        f.focus();
                        ok = false;
                        return;
                    }
                    if (currentRequestSns.indexOf(val.toLowerCase()) !== -1) {
                        alert('Serial Number (SN) "' + val + '" tidak boleh diduplikat dalam satu pengajuan.');
                        e.preventDefault();
                        f.focus();
                        ok = false;
                        return;
                    }
                    currentRequestSns.push(val.toLowerCase());
                }
            });
            return ok;
        });
    });
</script>
</body>
</html>