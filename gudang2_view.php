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
if (isset($_POST['update_g2_dpb'])) {
    $dpbId = $_POST['dpb_id'] ?? '';
    $tug   = trim($_POST['tug_number'] ?? '');

    // "Diterima Tanggal" selalu dipaksa ke tanggal hari ini saat disimpan,
    // apapun yang dikirim dari form (kalender di form juga dikunci hanya
    // bisa pilih hari ini, ini jaga-jaga di sisi server).
    $diterimaTgl = date('Y-m-d');

    $stmt = $db->prepare("UPDATE dpb_transactions SET
        spk_number = ?, jenis_pekerjaan = ?, idpel = ?, customer_name = ?, customer_address = ?,
        daya = ?, ulp = ?, tanggal_diminta = ?, diterima_tgl = ?,
        penerima_name = ?, security_name = ?, menyerahkan_name = ?
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
        $dpbId
    ]);

    $ids  = $_POST['item_id'] ?? [];
    $req  = $_POST['item_requested'] ?? [];
    $recv = $_POST['item_received'] ?? [];
    $sns  = $_POST['item_sn'] ?? [];
    foreach ($ids as $i => $itemId) {
        $qr = max(0, (int)($req[$i] ?? 0));
        $qd = max(0, (int)($recv[$i] ?? 0));
        $sn = trim($sns[$i] ?? '');
        $s = $db->prepare("UPDATE dpb_items SET quantity_requested = ?, quantity_received = ?, sn = ? WHERE id = ?");
        $s->execute([$qr, $qd, $sn, $itemId]);
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

    $stmt = $db->prepare("UPDATE k3_transactions SET
        spk_number = ?, jenis_pekerjaan = ?, idpel = ?, customer_name = ?, customer_address = ?,
        kondisi_material = ?, gudang_pengembalian = ?, keterangan = ?, tanggal_diminta = ?,
        setuju_name = ?, kepala_gudang_name = ?, pemeriksa_pengawas_name = ?, yang_menyerahkan_name = ?
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

    $stmt = $db->prepare("UPDATE k7_transactions SET
        spk_number = ?, jenis_pekerjaan = ?, idpel = ?, customer_name = ?, customer_address = ?,
        daya = ?, ulp = ?, tanggal_diminta = ?,
        setuju_name = ?, kepala_gudang_name = ?, pemeriksa_pengawas_name = ?, penerima_name = ?
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
                        <label>No. SPK</label>
                        <input type="text" name="spk_number" id="dpbSpkInput">
                    </div>
                </div>

                <div class="flex-row">
                    <div class="form-group">
                        <label>Jenis Pekerjaan</label>
                        <input type="text" name="jenis_pekerjaan" id="dpbJenisInput">
                    </div>
                    <div class="form-group">
                        <label>IDPEL</label>
                        <input type="text" name="idpel" id="dpbIdpelInput">
                    </div>
                    <div class="form-group">
                        <label>Daya</label>
                        <input type="text" name="daya" id="dpbDayaInput">
                    </div>
                    <div class="form-group">
                        <label>ULP</label>
                        <input type="text" name="ulp" id="dpbUlpInput">
                    </div>
                </div>

                <div class="flex-row">
                    <div class="form-group">
                        <label>Nama Pelanggan</label>
                        <input type="text" name="customer_name" placeholder="Nama pelanggan" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat Pelanggan</label>
                        <input type="text" name="customer_address" placeholder="Alamat lengkap pelanggan">
                    </div>
                </div>

                <h4 style="color:#0b2b4a; margin-top:1.2rem;">Data Tanda Tangan</h4>
                <div class="flex-row">
                    <div class="form-group">
                        <label>Penerima</label>
                        <input type="text" name="penerima_name" placeholder="Nama penerima">
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
            $d = $found['data']; ?>
            <div class="g2-result-card">
                <h2><span class="g2-type-badge dpb">DPB</span> Nomor TUG: <?= htmlspecialchars($d['tug_number']) ?></h2>

                <form method="POST" action="index.php">
                    <input type="hidden" name="dpb_id" value="<?= $d['id'] ?>">
                    <input type="hidden" name="tug_number" value="<?= htmlspecialchars($d['tug_number']) ?>">

                    <div class="g2-grid">
                        <div class="g2-field"><label>Vendor</label><input type="text" value="<?= htmlspecialchars($d['vendor_name'] ?? '-') ?>" disabled></div>
                        <div class="g2-field"><label>No. SPK</label><input type="text" name="spk_number" value="<?= htmlspecialchars($d['spk_number'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Jenis Pekerjaan</label><input type="text" name="jenis_pekerjaan" value="<?= htmlspecialchars($d['jenis_pekerjaan'] ?? '') ?>"></div>
                        <div class="g2-field"><label>IDPEL</label><input type="text" name="idpel" value="<?= htmlspecialchars($d['idpel'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Nama Pelanggan</label><input type="text" name="customer_name" value="<?= htmlspecialchars($d['customer_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Alamat Pelanggan</label><input type="text" name="customer_address" value="<?= htmlspecialchars($d['customer_address'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Daya</label><input type="text" name="daya" value="<?= htmlspecialchars($d['daya'] ?? '') ?>"></div>
                        <div class="g2-field"><label>ULP</label><input type="text" name="ulp" value="<?= htmlspecialchars($d['ulp'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Tanggal Diminta</label><input type="date" name="tanggal_diminta" value="<?= htmlspecialchars($d['tanggal_diminta'] ?? '') ?>"></div>
                        <div class="g2-field">
                            <label>Diterima Tanggal</label>
                            <input type="date" name="diterima_tgl" value="<?= htmlspecialchars(date('Y-m-d')) ?>" min="<?= htmlspecialchars(date('Y-m-d')) ?>" max="<?= htmlspecialchars(date('Y-m-d')) ?>">
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
                                    <input type="number" min="0" name="item_requested[]" value="<?= (int)$it['quantity_requested'] ?>">
                                </td>
                                <td data-label="Diterima"><input type="number" min="0" name="item_received[]" value="<?= (int)$it['quantity_received'] ?>"></td>
                                <td data-label="SN"><input type="text" name="item_sn[]" value="<?= htmlspecialchars($it['sn'] ?? '') ?>" maxlength="30" placeholder=""></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <div class="g2-grid">
                        <div class="g2-field"><label>Penerima</label><input type="text" name="penerima_name" value="<?= htmlspecialchars($d['penerima_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Security</label><input type="text" name="security_name" value="<?= htmlspecialchars($d['security_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Yang Menyerahkan</label><input type="text" name="menyerahkan_name" value="<?= htmlspecialchars($d['menyerahkan_name'] ?? '') ?>"></div>
                    </div>

                    <button type="submit" name="update_g2_dpb" class="g2-save-btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </form>

                <div class="g2-print-row">
                    <a href="printDPB.php?tug=<?= urlencode($d['tug_number']) ?>" target="_blank" class="g2-print-btn"><i class="fas fa-print"></i> Cetak Surat Jalan (Material)</a>
                    <a href="Printdpbform.php?tug=<?= urlencode($d['tug_number']) ?>" target="_blank" class="g2-print-btn secondary"><i class="fas fa-print"></i> Cetak Form DPB Resmi</a>
                </div>
            </div>

        <?php elseif ($found && $found['type'] === 'k3'):
            $k = $found['data']; ?>
            <div class="g2-result-card">
                <h2><span class="g2-type-badge k3">K3</span> Nomor TUG: <?= htmlspecialchars($k['tug_number']) ?></h2>

                <form method="POST" action="index.php">
                    <input type="hidden" name="k3_id" value="<?= $k['id'] ?>">
                    <input type="hidden" name="tug_number" value="<?= htmlspecialchars($k['tug_number']) ?>">

                    <div class="g2-grid">
                        <div class="g2-field"><label>Vendor</label><input type="text" value="<?= htmlspecialchars($k['vendor_name'] ?? '-') ?>" disabled></div>
                        <div class="g2-field"><label>No. SPK</label><input type="text" name="spk_number" value="<?= htmlspecialchars($k['spk_number'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Jenis Pekerjaan</label><input type="text" name="jenis_pekerjaan" value="<?= htmlspecialchars($k['jenis_pekerjaan'] ?? '') ?>"></div>
                        <div class="g2-field"><label>IDPEL</label><input type="text" name="idpel" value="<?= htmlspecialchars($k['idpel'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Nama Pelanggan</label><input type="text" name="customer_name" value="<?= htmlspecialchars($k['customer_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Alamat Pelanggan</label><input type="text" name="customer_address" value="<?= htmlspecialchars($k['customer_address'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Gudang Pengembalian</label><input type="text" name="gudang_pengembalian" value="<?= htmlspecialchars($k['gudang_pengembalian'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Tanggal Diminta</label><input type="date" name="tanggal_diminta" value="<?= htmlspecialchars($k['tanggal_diminta'] ?? '') ?>"></div>
                        <div class="g2-field">
                            <label>Kondisi Material</label>
                            <select name="kondisi_material">
                                <?php foreach ($kondisiList as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $k['kondisi_material'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="g2-field" style="grid-column: 1 / -1;"><label>Keterangan</label><textarea name="keterangan"><?= htmlspecialchars($k['keterangan'] ?? '') ?></textarea></div>
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
                                    <input type="number" min="0" name="item_returned[]" value="<?= (int)$it['quantity_returned'] ?>">
                                </td>
                                <td data-label="Diterima"><input type="number" min="0" name="item_received[]" value="<?= (int)$it['quantity_received'] ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <div class="g2-grid">
                        <div class="g2-field"><label>Setuju</label><input type="text" name="setuju_name" value="<?= htmlspecialchars($k['setuju_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Kepala Gudang</label><input type="text" name="kepala_gudang_name" value="<?= htmlspecialchars($k['kepala_gudang_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Pemeriksa / Pengawas</label><input type="text" name="pemeriksa_pengawas_name" value="<?= htmlspecialchars($k['pemeriksa_pengawas_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Yang Menyerahkan</label><input type="text" name="yang_menyerahkan_name" value="<?= htmlspecialchars($k['yang_menyerahkan_name'] ?? '') ?>"></div>
                    </div>

                    <button type="submit" name="update_g2_k3" class="g2-save-btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </form>

                <div class="g2-print-row">
                    <a href="printK3.php?tug=<?= urlencode($k['tug_number']) ?>" target="_blank" class="g2-print-btn"><i class="fas fa-print"></i> Cetak K3</a>
                </div>
            </div>

        <?php elseif ($found && $found['type'] === 'k7'):
            $k = $found['data']; ?>
            <div class="g2-result-card">
                <h2><span class="g2-type-badge k7">K7</span> Nomor TUG: <?= htmlspecialchars($k['tug_number']) ?></h2>

                <form method="POST" action="index.php">
                    <input type="hidden" name="k7_id" value="<?= $k['id'] ?>">
                    <input type="hidden" name="tug_number" value="<?= htmlspecialchars($k['tug_number']) ?>">

                    <div class="g2-grid">
                        <div class="g2-field"><label>Vendor</label><input type="text" value="<?= htmlspecialchars($k['vendor_name'] ?? '-') ?>" disabled></div>
                        <div class="g2-field"><label>No. SPK</label><input type="text" name="spk_number" value="<?= htmlspecialchars($k['spk_number'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Jenis Pekerjaan</label><input type="text" name="jenis_pekerjaan" value="<?= htmlspecialchars($k['jenis_pekerjaan'] ?? '') ?>"></div>
                        <div class="g2-field"><label>IDPEL</label><input type="text" name="idpel" value="<?= htmlspecialchars($k['idpel'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Nama Pelanggan</label><input type="text" name="customer_name" value="<?= htmlspecialchars($k['customer_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Alamat Pelanggan</label><input type="text" name="customer_address" value="<?= htmlspecialchars($k['customer_address'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Daya</label><input type="text" name="daya" value="<?= htmlspecialchars($k['daya'] ?? '') ?>"></div>
                        <div class="g2-field"><label>ULP</label><input type="text" name="ulp" value="<?= htmlspecialchars($k['ulp'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Tanggal Diminta</label><input type="date" name="tanggal_diminta" value="<?= htmlspecialchars($k['tanggal_diminta'] ?? '') ?>"></div>
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
                                    <input type="number" min="0" name="item_requested[]" value="<?= (int)$it['quantity_requested'] ?>">
                                </td>
                                <td data-label="Diterima"><input type="number" min="0" name="item_received[]" value="<?= (int)$it['quantity_received'] ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                    <div class="g2-grid">
                        <div class="g2-field"><label>Setuju</label><input type="text" name="setuju_name" value="<?= htmlspecialchars($k['setuju_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Kepala Gudang</label><input type="text" name="kepala_gudang_name" value="<?= htmlspecialchars($k['kepala_gudang_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Pemeriksa / Pengawas</label><input type="text" name="pemeriksa_pengawas_name" value="<?= htmlspecialchars($k['pemeriksa_pengawas_name'] ?? '') ?>"></div>
                        <div class="g2-field"><label>Penerima</label><input type="text" name="penerima_name" value="<?= htmlspecialchars($k['penerima_name'] ?? '') ?>"></div>
                    </div>

                    <button type="submit" name="update_g2_k7" class="g2-save-btn"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </form>

                <div class="g2-print-row">
                    <a href="printK7.php?tug=<?= urlencode($k['tug_number']) ?>" target="_blank" class="g2-print-btn"><i class="fas fa-print"></i> Cetak K7</a>
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

</body>
</html>