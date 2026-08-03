<?php
require_once 'config.php';
require_once 'functions.php';

// =========================================================
// AJAX ENDPOINTS
// =========================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // Daftar semua material (dipakai tabel "Lihat Hasil") — bukan utk vendor / gudang2
    if ($_GET['ajax'] === 'materials') {
        if (isVendor() || isGudang2()) {
            http_response_code(403);
            echo json_encode(['error' => 'Menu Material hanya untuk admin gudang PLN.']);
            exit();
        }
        echo json_encode(getMaterials($db));
        exit();
    }

    // Autocomplete material by nama / normalisasi
    if ($_GET['ajax'] === 'material_search') {
        $q = $_GET['q'] ?? '';
        echo json_encode(searchMaterials($db, $q));
        exit();
    }

    // Data default vendor (SPK, jenis pekerjaan, idpel, daya, ULP) utk auto-isi form DPB
    if ($_GET['ajax'] === 'vendor_info') {
        $id = $_GET['id'] ?? 0;
        $v = getVendorById($db, $id);
        if (!$v) {
            echo json_encode(['error' => 'Vendor tidak ditemukan']);
            exit();
        }
        echo json_encode([
            'spk_number'      => $v['default_spk_number'],
            'jenis_pekerjaan' => $v['default_jenis_pekerjaan'],
            'idpel'           => $v['default_idpel'],
            'daya'            => $v['default_daya'],
            'ulp'             => $v['default_ulp'],
        ]);
        exit();
    }

    // Cari DPB berdasarkan nomor TUG -> otomatis tampilkan seluruh data & item
    if ($_GET['ajax'] === 'dpb') {
        $tug = $_GET['tug'] ?? '';
        $dpb = getDpbByTug($db, $tug);

        if (!$dpb) {
            echo json_encode(['error' => 'Nomor TUG tidak ditemukan. Pastikan sudah pernah diajukan.']);
            exit();
        }

        echo json_encode([
            'id'               => $dpb['id'],
            'tug_number'       => $dpb['tug_number'],
            'vendor_id'        => $dpb['vendor_id'],
            'vendor_name'      => $dpb['vendor_name'],
            'spk_number'       => $dpb['spk_number'],
            'jenis_pekerjaan'  => $dpb['jenis_pekerjaan'],
            'idpel'            => $dpb['idpel'],
            'customer_name'    => $dpb['customer_name'],
            'customer_address' => $dpb['customer_address'],
            'daya'             => $dpb['daya'],
            'ulp'              => $dpb['ulp'],
            'status'           => $dpb['status'],
            'status_label'     => dpbStatusLabel($dpb['status']),
            'tanggal_diminta'  => $dpb['tanggal_diminta'],
            'penerima_name'    => $dpb['penerima_name'],
            'security_name'    => $dpb['security_name'],
            'menyerahkan_name' => $dpb['menyerahkan_name'],
            'items'            => $dpb['items'],
        ]);
        exit();
    }

    // Cari K3 (Bon Pengembalian Material) berdasarkan nomor TUG
    if ($_GET['ajax'] === 'k3') {
        $tug = $_GET['tug'] ?? '';
        $k3 = getK3ByTug($db, $tug);

        if (!$k3) {
            echo json_encode(['error' => 'Nomor TUG K3 tidak ditemukan. Pastikan sudah pernah diajukan.']);
            exit();
        }

        echo json_encode([
            'id'                  => $k3['id'],
            'tug_number'          => $k3['tug_number'],
            'vendor_id'           => $k3['vendor_id'],
            'vendor_name'         => $k3['vendor_name'],
            'spk_number'          => $k3['spk_number'],
            'jenis_pekerjaan'     => $k3['jenis_pekerjaan'],
            'idpel'               => $k3['idpel'],
            'customer_name'       => $k3['customer_name'],
            'customer_address'    => $k3['customer_address'],
            'kondisi_material'    => $k3['kondisi_material'],
            'kondisi_label'       => kondisiMaterialLabel($k3['kondisi_material']),
            'gudang_pengembalian' => $k3['gudang_pengembalian'],
            'keterangan'          => $k3['keterangan'],
            'nomor_seri'          => $k3['nomor_seri'] ?? '',
            'no_dpb_bukti'        => $k3['no_dpb_bukti'] ?? '',
            'lokasi_penempatan'   => $k3['lokasi_penempatan'] ?? '',
            'status'              => $k3['status'],
            'status_label'        => dpbStatusLabel($k3['status']),
            'tanggal_diminta'     => $k3['tanggal_diminta'],
            'setuju_name'             => $k3['setuju_name'],
            'kepala_gudang_name'      => $k3['kepala_gudang_name'],
            'pemeriksa_pengawas_name' => $k3['pemeriksa_pengawas_name'],
            'yang_menyerahkan_name'   => $k3['yang_menyerahkan_name'],
            'items'               => $k3['items'],
        ]);
        exit();
    }

    // Cari K7 (Bon Pemakaian Material Bekas) berdasarkan nomor TUG
    if ($_GET['ajax'] === 'k7') {
        $tug = $_GET['tug'] ?? '';
        $k7 = getK7ByTug($db, $tug);

        if (!$k7) {
            echo json_encode(['error' => 'Nomor TUG K7 tidak ditemukan. Pastikan sudah pernah diajukan.']);
            exit();
        }

        echo json_encode([
            'id'               => $k7['id'],
            'tug_number'       => $k7['tug_number'],
            'vendor_id'        => $k7['vendor_id'],
            'vendor_name'      => $k7['vendor_name'],
            'spk_number'       => $k7['spk_number'],
            'jenis_pekerjaan'  => $k7['jenis_pekerjaan'],
            'idpel'            => $k7['idpel'],
            'customer_name'    => $k7['customer_name'],
            'customer_address' => $k7['customer_address'],
            'daya'             => $k7['daya'],
            'ulp'              => $k7['ulp'],
            'merk_material'    => $k7['merk_material'] ?? '',
            'nomor_seri'       => $k7['nomor_seri'] ?? '',
            'keterangan'       => $k7['keterangan'] ?? '',
            'status'           => $k7['status'],
            'status_label'     => dpbStatusLabel($k7['status']),
            'tanggal_diminta'  => $k7['tanggal_diminta'],
            'setuju_name'             => $k7['setuju_name'],
            'kepala_gudang_name'      => $k7['kepala_gudang_name'],
            'pemeriksa_pengawas_name' => $k7['pemeriksa_pengawas_name'],
            'penerima_name'           => $k7['penerima_name'],
            'items'            => $k7['items'],
        ]);
        exit();
    }

    // MDU: cari daftar permintaan material per vendor + sisa yang belum terpenuhi (khusus admin)
    if ($_GET['ajax'] === 'mdu') {
        if (!isAdmin()) {
            echo json_encode(['error' => 'Hanya admin gudang PLN yang dapat mengakses data ini.']);
            exit();
        }
        $q = trim($_GET['q'] ?? '');
        if ($q === '') {
            echo json_encode([]);
            exit();
        }
        $rows = searchMduByMaterial($db, $q);
        echo json_encode(array_map(function ($r) {
            $diminta  = (int) $r['quantity_requested'];
            $diterima = (int) $r['quantity_received'];
            $sisa     = max($diminta - $diterima, 0);
            return [
                'vendor_name'        => $r['vendor_name'],
                'tug_number'         => $r['tug_number'],
                'tanggal_diminta'    => $r['tanggal_diminta'],
                'status'             => $r['status'],
                'status_label'       => dpbStatusLabel($r['status']),
                'material_name'      => $r['material_name'],
                'unit'               => $r['unit'],
                'quantity_requested' => $diminta,
                'quantity_received'  => $diterima,
                'sisa'               => $sisa,
            ];
        }, $rows));
        exit();
    }
}

// =========================================================
// PROSES CRUD (delegasi ke file terpisah)
// =========================================================
if (isset($_POST['add_vendor']) || isset($_POST['edit_vendor']) || (isset($_GET['delete']) && strpos($_SERVER['REQUEST_URI'], 'page=vendor') !== false)) {
    include 'vendor.php';
}
if (isset($_POST['add_material']) || isset($_POST['edit_material']) || (isset($_GET['delete']) && strpos($_SERVER['REQUEST_URI'], 'page=material') !== false)) {
    include 'material.php';
}
if (isset($_POST['login']) || isset($_POST['register'])) {
    include 'auth.php';
}
if (isset($_POST['create_dpb']) || isset($_POST['update_status']) || isset($_POST['update_received']) || isset($_POST['approve_dpb']) || isset($_POST['reject_dpb']) || isset($_POST['update_signers']) || isset($_GET['delete_dpb'])) {
    include 'dpb.php';
}
if (isset($_POST['create_k3']) || isset($_POST['update_k3_status']) || isset($_POST['update_k3_received']) || isset($_POST['update_k3_details']) || isset($_POST['update_k3_signers']) || isset($_GET['delete_k3'])) {
    include 'k3.php';
}
if (isset($_POST['create_k7']) || isset($_POST['update_k7_status']) || isset($_POST['update_k7_received']) || isset($_POST['update_k7_details']) || isset($_POST['update_k7_signers']) || isset($_GET['delete_k7'])) {
    include 'k7.php';
}

$is_logged_in = isLoggedIn();
$is_admin     = isAdmin();
$is_vendor    = isVendor();
$is_gudang2   = isGudang2();

$openModal = $_SESSION['open_modal'] ?? null;
unset($_SESSION['open_modal']);

// WAJIB LOGIN: guest diarahkan ke halaman login mandiri (bukan modal),
// header/nav/hero situs tidak pernah dirender sebelum login.
if (!$is_logged_in) {
    include 'login.php';
    exit();
}

// ADMIN GUDANG 2: satu-satunya sistemnya adalah cari nomor TUG →
// lihat/edit semua info terkait → cetak. Tampilannya SENGAJA dibuat
// beda total dari situs admin gudang 1 / vendor (header/nav/hero biasa
// tidak pernah dirender untuk role ini) — tapi tetap lewat login.php /
// auth.php yang sama persis, jadi bukan file/site terpisah.
if ($is_gudang2) {
    include 'gudang2_view.php';
    exit();
}

$vendors      = getVendors($db);
$materials    = getMaterials($db);
$my_vendor    = $is_vendor ? getVendorById($db, currentVendorId()) : null;
$page         = $_GET['page'] ?? 'home';
$prefillTug   = $_GET['tug'] ?? '';
$riwayatStart = $_GET['start'] ?? '';
$riwayatEnd   = $_GET['end'] ?? '';
if ($page === 'riwayat' && $is_vendor) {
    $riwayat = getVendorHistory($db, currentVendorId(), $riwayatStart ?: null, $riwayatEnd ?: null);
} elseif ($page === 'riwayat' && $is_admin) {
    $riwayat = getAllHistory($db, $riwayatStart ?: null, $riwayatEnd ?: null);
} else {
    $riwayat = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLN Material · Gudang</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="site-header">
    <div class="logo-area">
        <img src="images/logo.png" alt="PLN Logo">
        <span>VOLTA</span>
    </div>

    <div class="nav-links">
        <a href="?page=home" class="<?= $page === 'home' ? 'active' : '' ?>"><i class="fas fa-home"></i> Home</a>
        <?php if (!$is_gudang2): ?>
        <a href="?page=vendor" class="<?= $page === 'vendor' ? 'active' : '' ?>"><i class="fas fa-truck"></i> Vendor</a>
        <?php endif; ?>
        <?php if (!$is_vendor && !$is_gudang2): ?>
        <a href="?page=material" class="<?= $page === 'material' ? 'active' : '' ?>"><i class="fas fa-cubes"></i> Material</a>
        <?php endif; ?>
        <a href="?page=dpb" class="<?= $page === 'dpb' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> DPB</a>
        <a href="?page=k3" class="<?= $page === 'k3' ? 'active' : '' ?>"><i class="fas fa-undo"></i> K3</a>
        <a href="?page=k7" class="<?= $page === 'k7' ? 'active' : '' ?>"><i class="fas fa-recycle"></i> K7</a>
        <?php if ($is_vendor || $is_admin): ?>
        <a href="?page=riwayat" class="<?= $page === 'riwayat' ? 'active' : '' ?>"><i class="fas fa-history"></i> Riwayat</a>
        <?php endif; ?>
        <?php if ($is_admin): ?>
        <a href="?page=mdu" class="<?= $page === 'mdu' ? 'active' : '' ?>"><i class="fas fa-tasks"></i> Dafung</a>
        <?php endif; ?>
        <?php if (!$is_admin): ?>
        <a href="#" onclick="showFaq()"><i class="fas fa-question-circle"></i> FAQ</a>
        <?php endif; ?>
    </div>

    <div class="auth-bar">
        <?php if ($is_logged_in): ?>
            <span class="user-info">
                <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                <span class="role-badge role-<?= $_SESSION['user_role'] ?>"><?= $is_admin ? 'Admin' : ($is_gudang2 ? 'Admin Gudang 2' : 'Vendor') ?></span>
            </span>
            <a href="logout.php" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Keluar</a>
        <?php else: ?>
            <button class="btn btn-outline" onclick="showLogin()"><i class="fas fa-sign-in-alt"></i> Masuk</button>
            <button class="btn btn-primary" onclick="showRegister()">Daftar</button>
        <?php endif; ?>
    </div>
</header>

<section class="hero-banner">
    <img src="images/hero.png" alt="Gudang &amp; Distribusi Material PLN">
    <div class="hero-overlay">
        <span class="hero-tag"><i class="fas fa-bolt"></i> PT PLN (persero) - UP 3 Malang</span>
        <h1>Distribusi Material</h1>
        <p>Kelola stok material, data vendor, dan monitoring DPB dalam satu sistem terintegrasi.</p>
    </div>
</section>

<div class="app-wrapper">
    <?php if (!$openModal && isset($_SESSION['success'])): ?>
        <div class="alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (!$openModal && isset($_SESSION['error'])): ?>
        <div class="alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div id="dynamicContent">
        <?php if ($page === 'home'): ?>
            <div id="homeSection">
                <div class="hero-card">
                    <h1 style="color: #0b2b4a; font-weight: 700; font-size: 2.2rem;"><i class="fas fa-warehouse"></i> VOLTA PLN</h1>
                    <p style="color: #1f4460; max-width: 600px;">
                        <?php if ($is_admin): ?>
                            Kelola material dan vendor, pantau DPB, serta buat surat jalan dengan mudah.
                        <?php elseif ($is_vendor): ?>
                            Ajukan permintaan material, cetak DPB, dan pantau status pengajuan Anda di sini.
                        <?php elseif ($is_gudang2): ?>
                            Cari nomor TUG di menu DPB, K3, atau K7 untuk melihat &amp; mengelola datanya, lalu cetak surat jalan / bon sebelum diserahkan.
                        <?php else: ?>
                            Masuk atau daftar sebagai vendor untuk mulai mengajukan permintaan material.
                        <?php endif; ?>
                    </p>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.8rem; margin-top: 1.2rem;">
                        <?php if ($is_admin || $is_vendor): ?>
                            <a href="?page=material" class="btn-success" style="text-decoration:none; display:inline-block;"><i class="fas fa-plus-circle"></i> Input Material</a>
                            <a href="?page=vendor" class="btn-info" style="text-decoration:none; display:inline-block;"><i class="fas fa-building"></i> Kelola Vendor</a>
                        <?php endif; ?>
                        <?php if ($is_gudang2): ?>
                            <a href="?page=dpb" class="btn-warning" style="text-decoration:none; display:inline-block;"><i class="fas fa-file-pdf"></i> Cetak Surat Jalan (Material)</a>
                            <a href="?page=dpb" class="btn-info" style="text-decoration:none; display:inline-block;"><i class="fas fa-clipboard-list"></i> DPB</a>
                            <a href="?page=k3" class="btn-info" style="text-decoration:none; display:inline-block;"><i class="fas fa-undo"></i> K3</a>
                            <a href="?page=k7" class="btn-info" style="text-decoration:none; display:inline-block;"><i class="fas fa-recycle"></i> K7</a>
                        <?php else: ?>
                        <a href="?page=dpb" class="btn-warning" style="text-decoration:none; display:inline-block;"><i class="fas fa-print"></i> Monitoring DPB</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-grid">
                    <div class="card">
                        <h3><i class="fas fa-boxes"></i> Material</h3>
                        <p>Total <span id="materialCount"><?= count($materials) ?></span> item terdaftar.</p>
                    </div>
                    <div class="card">
                        <h3><i class="fas fa-truck"></i> Vendor</h3>
                        <p>Total <span id="vendorCount"><?= count($vendors) ?></span> vendor.</p>
                    </div>
                    <div class="card">
                        <h3><i class="fas fa-file-pdf"></i> Surat Jalan</h3>
                        <p>Cetak / PDF dari hasil monitoring.</p>
                    </div>
                </div>
            </div>
        <?php elseif ($page === 'vendor'): ?>
            <div id="vendorSection">
                <div class="card">
                    <h3><i class="fas fa-building"></i> Daftar Vendor</h3>

                    <?php if ($is_admin): ?>
                    <form method="POST" action="vendor.php" style="margin-bottom: 1.5rem;">
                        <div class="flex-row">
                            <div class="form-group">
                                <label>Nama PT</label>
                                <input type="text" name="vendor_name" placeholder="PT. ..." required>
                            </div>
                            <div class="form-group">
                                <label>Alamat</label>
                                <input type="text" name="vendor_address" placeholder="Alamat lengkap">
                            </div>
                        </div>
                        <div class="flex-row">
                            <div class="form-group">
                                <label>Telepon</label>
                                <input type="text" name="vendor_phone" placeholder="08123456789">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="vendor_email" placeholder="email@company.com">
                            </div>
                        </div>
                        <p class="text-small">Data default di bawah ini otomatis dipakai untuk mengisi formulir Pengajuan DPB saat vendor ini dipilih.</p>
                        <div class="flex-row">
                            <div class="form-group">
                                <label>No. SPK Default</label>
                                <input type="text" name="vendor_spk" placeholder="0959.PJ/DAN...">
                            </div>
                            <div class="form-group">
                                <label>Jenis Pekerjaan Default</label>
                                <input type="text" name="vendor_jenis" placeholder="GKU / GKS / SUTR">
                            </div>
                        </div>
                        <div class="flex-row">
                            <div class="form-group">
                                <label>IDPEL Default</label>
                                <input type="text" name="vendor_idpel" placeholder="513...">
                            </div>
                            <div class="form-group">
                                <label>Daya Default</label>
                                <input type="text" name="vendor_daya" placeholder="R1 / 1300 VA">
                            </div>
                            <div class="form-group">
                                <label>ULP Default</label>
                                <input type="text" name="vendor_ulp" placeholder="ULP DINOYO">
                            </div>
                        </div>
                        <div style="margin-top:1.2rem;">
                            <button type="submit" name="add_vendor" class="btn-success">Tambah Vendor</button>
                        </div>
                    </form>
                    <?php elseif ($is_vendor): ?>
                        <div class="alert-success" style="padding:1rem; border-radius:10px; margin-bottom:1rem;">
                            <i class="fas fa-check-circle"></i> Anda terdaftar sebagai <strong><?= htmlspecialchars($my_vendor['name'] ?? '-') ?></strong>. Untuk mengubah data vendor, hubungi admin gudang PLN.
                        </div>
                    <?php else: ?>
                        <div class="alert-danger" style="padding:1rem; border-radius:10px; margin-bottom:1rem;">
                            <i class="fas fa-lock"></i> Silakan <a href="#" onclick="showRegister();return false;">daftar sebagai vendor</a> atau login untuk mengajukan permintaan material.
                        </div>
                    <?php endif; ?>

                    <?php if (!$is_admin && !$is_vendor): ?>
                    <p class="text-small" style="margin-top:-0.6rem; margin-bottom:0.8rem;">
                        <i class="fas fa-lock"></i> Kolom Telepon &amp; Email hanya dapat dilihat oleh admin gudang PLN dan Vendor.
                    </p>
                    <?php endif; ?>
                    <div style="display:flex; justify-content:flex-end; margin-bottom:0.8rem;">
                        <div class="form-group" style="min-width:260px; margin-bottom:0;">
                            <input type="text" id="vendorSearchInput" placeholder="Cari Vendor..." oninput="filterVendorTable()">
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table id="vendorTable">
                            <thead>
                                <tr><th>#</th><th>Nama PT</th><th>Alamat</th><th>Telepon</th><th>Email</th><?php if ($is_admin): ?><th>Aksi</th><?php endif; ?></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendors as $i => $v): ?>
                                <tr data-search="<?= htmlspecialchars(mb_strtolower($v['name'] . ' ' . ($v['address'] ?? ''))) ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($v['name']) ?></td>
                                    <td><?= htmlspecialchars($v['address'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($is_admin): ?>
                                            <?= htmlspecialchars($v['phone'] ?? '-') ?>
                                        <?php else: ?>
                                            <span class="locked-cell"><i class="fas fa-lock"></i> Admin saja</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_admin): ?>
                                            <?= htmlspecialchars($v['email'] ?? '-') ?>
                                        <?php else: ?>
                                            <span class="locked-cell"><i class="fas fa-lock"></i> Admin saja</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($is_admin): ?>
                                    <td>
                                        <a href="vendor.php?delete=<?= $v['id'] ?>&page=vendor" onclick="return confirm('Yakin hapus?')" class="btn-danger" style="padding:0.2rem 0.8rem; border-radius:20px; text-decoration:none; font-size:0.7rem;">Hapus</a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif ($page === 'material'): ?>
            <div id="materialSection">
                <?php if ($is_vendor || $is_gudang2): ?>
                <div class="alert-danger" style="padding:1rem; border-radius:10px;">
                    <i class="fas fa-lock"></i> Menu Material hanya dapat diakses oleh admin gudang PLN. Untuk mengajukan permintaan material, gunakan menu <a href="?page=dpb">DPB</a>.
                </div>
                <?php else: ?>
                <div class="card">
                    <h3><i class="fas fa-cube"></i> Input Material</h3>

                    <?php if ($is_admin): ?>
                    <form method="POST" action="material.php" style="margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label>Nama Material</label>
                            <input type="text" id="materialNameInput" name="material_name" list="materialNameList" placeholder="contoh: DUDUKAN TRAFO 2 TIANG" required oninput="updateNorm()">
                        </div>
                        <div class="form-group">
                            <label>Normalisasi</label>
                            <input type="text" id="materialNormInput" name="material_norm" list="materialNormList" placeholder="Input Normalisasi" oninput="updateNormFromCode()">
                        </div>
                        <div class="form-group">
                            <label>Satuan</label>
                            <select name="material_unit">
                                <option value="BH">BH</option>
                                <option value="SET">SET</option>
                                <option value="M">M</option>
                                <option value="PACK">PACK</option>
                                <option value="ROLL">ROLL</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Jumlah Material <span class="text-small">(stok awal)</span></label>
                            <input type="number" name="material_stock" min="0" step="1" placeholder="0" value="0" required>
                        </div>
                        <button type="submit" name="add_material" class="btn-success">Simpan Material</button>
                    </form>
                    <?php else: ?>
                        <div class="alert-danger" style="padding:1rem; border-radius:10px; margin-bottom:1rem;">
                            <i class="fas fa-lock"></i> Silakan login untuk mengakses fitur lebih lanjut.
                        </div>
                    <?php endif; ?>

                    <div style="margin-top:1rem; display:flex; gap:0.8rem; flex-wrap:wrap; align-items:center; justify-content:space-between;">
                        <button class="btn-info" onclick="showMaterialList()"><i class="fas fa-list"></i> Lihat Hasil</button>
                        <div class="form-group" id="materialSearchWrap" style="min-width:260px; margin-bottom:0; display:none;">
                            <input type="text" id="materialSearchInput" placeholder="Cari Material..." oninput="filterMaterialTable()">
                        </div>
                    </div>
                    <div id="materialListContainer" style="margin-top:1rem;"></div>
                </div>
                <?php endif; ?>
            </div>
        <?php elseif ($page === 'dpb'): ?>
            <div id="monitoringSection">

                <div class="monitoring-dpb">
                    <h3 style="color:#0b2b4a;"><i class="fas fa-clipboard-check"></i> Cari / Monitoring DPB</h3>

                    <div class="flex-row">
                        <div class="form-group">
                            <label>Nomor TUG</label>
                            <input id="tugNumberInput" placeholder="TUG 5. MLG26-1624" value="<?= htmlspecialchars($prefillTug) ?>">
                        </div>
                        <button class="btn-success" onclick="loadDPB()">Cari / Muat</button>
                    </div>

                    <div id="dpbResult" style="margin-top:1.5rem; background:white; border-radius:24px; padding:1rem;">
                        <p class="text-small">Masukkan nomor TUG yang sudah pernah diajukan untuk melihat detail &amp; status secara otomatis.</p>
                    </div>

                    <div style="margin-top:1rem; display:flex; gap:0.8rem; flex-wrap:wrap;">
                        <?php if (!$is_vendor): ?>
                        <button class="btn-warning" onclick="printDPB()"><i class="fas fa-print"></i> Cetak Surat Jalan</button>
                        <?php endif; ?>
                        <?php if (!$is_vendor): ?>
                        <button class="btn-warning" onclick="(function(){var t=document.getElementById('tugNumberInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printDPBForm.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-print"></i> Cetak DPB</button>
                        <?php endif; ?>
                        <?php if (!$is_vendor): ?>
                        <button class="btn-info" onclick="saveDPBpdf()"><i class="fas fa-file-pdf"></i> Simpan PDF</button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_admin || $is_vendor): ?>
                <div class="card" style="margin-top:1.5rem;">
                    <h3><i class="fas fa-file-signature"></i> Ajukan Permintaan Material Baru (DPB)</h3>
                    
                    <form method="POST" action="dpb.php" id="dpbCreateForm">
                        <div class="flex-row">
                            <div class="form-group">
                                <label>Nomor TUG</label>
                                <input type="text" name="tug_number" placeholder="TUG 5. MLG26-XXXX" required>
                            </div>
                            <div class="form-group">
                                <label>Tanggal Diminta</label>
                                <input type="date" name="tanggal_diminta" value="<?= date('Y-m-d') ?>" min="2023-01-01" max="2035-12-31">
                            </div>
                        </div>

                        <div class="flex-row">
                            <div class="form-group">
                                <label>Vendor</label>
                                <?php if ($is_vendor): ?>
                                    <input type="text" value="<?= htmlspecialchars($my_vendor['name'] ?? '') ?>" disabled>
                                    <input type="hidden" name="vendor_id" value="<?= $my_vendor['id'] ?? '' ?>">
                                <?php else: ?>
                                    <select name="vendor_id" id="dpbVendorSelect" onchange="autofillVendor()" required>
                                        <option value="">-- pilih PT / vendor --</option>
                                        <?php foreach ($vendors as $v): ?>
                                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>No. SPK</label>
                                <input type="text" name="spk_number" id="dpbSpkInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_spk_number'] ?? '') : '' ?>">
                            </div>
                        </div>

                        <div class="flex-row">
                            <div class="form-group">
                                <label>Jenis Pekerjaan</label>
                                <input type="text" name="jenis_pekerjaan" id="dpbJenisInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_jenis_pekerjaan'] ?? '') : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>IDPEL</label>
                                <input type="text" name="idpel" id="dpbIdpelInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_idpel'] ?? '') : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>Daya</label>
                                <input type="text" name="daya" id="dpbDayaInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_daya'] ?? '') : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>ULP</label>
                                <input type="text" name="ulp" id="dpbUlpInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_ulp'] ?? '') : '' ?>">
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
                        <button type="button" class="btn-info" onclick="addDpbItemRow()" style="margin-top:0.5rem;"><i class="fas fa-plus"></i> Tambah Baris Material</button>

                        <div style="margin-top:1.2rem;">
                            <button type="submit" name="create_dpb" class="btn-success"><i class="fas fa-save"></i> Ajukan</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php elseif ($page === 'k3'): ?>
            <div id="k3Section">
                <div class="monitoring-dpb">
                    <h3 style="color:#0b2b4a;"><i class="fas fa-undo"></i> Cari / Monitoring K3 (Bon Pengembalian Material)</h3>
                    <div class="flex-row">
                        <div class="form-group">
                            <label>Nomor TUG</label>
                            <input id="k3TugInput" placeholder="TUG 10.MLG23-0014" value="<?= $page === 'k3' ? htmlspecialchars($prefillTug) : '' ?>">
                        </div>
                        <button class="btn-success" onclick="loadK3()">Cari / Muat</button>
                    </div>
                    <div id="k3Result" style="margin-top:1.5rem; background:white; border-radius:24px; padding:1rem;">
                        <p class="text-small">Masukkan nomor TUG K3 yang sudah pernah diajukan untuk melihat detail &amp; status secara otomatis.</p>
                    </div>
                    <div style="margin-top:1rem; display:flex; gap:0.8rem; flex-wrap:wrap;">
                        <?php if (!$is_vendor): ?>
                        <button class="btn-warning" onclick="(function(){var t=document.getElementById('k3TugInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printK3.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-print"></i> Cetak Bon (Format Resmi)</button>
                        <?php endif; ?>
                        <button class="btn-info" onclick="(function(){var t=document.getElementById('k3TugInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printK3.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-file-pdf"></i> Simpan PDF</button>
                    </div>
                </div>

                <?php if ($is_logged_in): ?>
                <div class="card" style="margin-top:1.5rem;">
                    <h3><i class="fas fa-file-signature"></i> Ajukan Pengembalian Material Baru (K3)</h3>

                    <form method="POST" action="k3.php" id="k3CreateForm">
                        <div class="flex-row">
                            <div class="form-group">
                                <label>Nomor TUG</label>
                                <input type="text" name="tug_number" placeholder="TUG 10.MLG26-XXXX" required>
                            </div>
                            <div class="form-group">
                                <label>Tanggal Diminta</label>
                                <input type="date" name="tanggal_diminta" value="<?= date('Y-m-d') ?>" min="2023-01-01" max="2035-12-31">
                            </div>
                        </div>

                        <div class="flex-row">
                            <div class="form-group">
                                <label>Vendor</label>
                                <?php if ($is_vendor): ?>
                                    <input type="text" value="<?= htmlspecialchars($my_vendor['name'] ?? '') ?>" disabled>
                                    <input type="hidden" name="vendor_id" value="<?= $my_vendor['id'] ?? '' ?>">
                                <?php else: ?>
                                    <select name="vendor_id" id="k3VendorSelect" onchange="autofillVendorGeneric('k3')" required>
                                        <option value="">-- pilih PT / vendor --</option>
                                        <?php foreach ($vendors as $v): ?>
                                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>No. SPK</label>
                                <input type="text" name="spk_number" id="k3SpkInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_spk_number'] ?? '') : '' ?>">
                            </div>
                        </div>

                        <div class="flex-row">
                            <div class="form-group">
                                <label>Jenis Pekerjaan</label>
                                <input type="text" name="jenis_pekerjaan" id="k3JenisInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_jenis_pekerjaan'] ?? '') : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>IDPEL</label>
                                <input type="text" name="idpel" id="k3IdpelInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_idpel'] ?? '') : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>Kondisi Material</label>
                                <select name="kondisi_material">
                                    <option value="masih_dapat_dipergunakan">Masih Dapat Dipergunakan</option>
                                    <option value="rusak">Rusak</option>
                                    <option value="baru">Baru</option>
                                    <option value="garansi">Garansi</option>
                                </select>
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

                        <div class="flex-row">
                            <div class="form-group">
                                <label>Gudang Pengembalian</label>
                                <input type="text" name="gudang_pengembalian" value="Gudang PLN Aries Munandar">
                            </div>
                            <div class="form-group">
                                <label>Keterangan Detile</label>
                                <input type="text" name="keterangan" placeholder="Opsional">
                            </div>
                        </div>

                        <div class="flex-row">
                            <div class="form-group">
                                <label>Nomor Seri</label>
                                <input type="text" name="nomor_seri" placeholder="Opsional">
                            </div>
                            <div class="form-group">
                                <label>No. DPB / Bukti</label>
                                <input type="text" name="no_dpb_bukti" placeholder="Opsional">
                            </div>
                            <div class="form-group">
                                <label>Lokasi Penempatan Material/Dipakai</label>
                                <input type="text" name="lokasi_penempatan" placeholder="Opsional">
                            </div>
                        </div>

                        <h4 style="color:#0b2b4a; margin-top:1.2rem;">Data Tanda Tangan</h4>
                        <div class="flex-row">
                            <div class="form-group">
                                <label>Setuju</label>
                                <input type="text" name="setuju_name" placeholder="Nama penyetuju">
                            </div>
                            <div class="form-group">
                                <label>Kepala Gudang</label>
                                <input type="text" name="kepala_gudang_name" placeholder="Nama kepala gudang">
                            </div>
                            <div class="form-group">
                                <label>Pemeriksa / Pengawas</label>
                                <input type="text" name="pemeriksa_pengawas_name" placeholder="Nama pemeriksa/pengawas">
                            </div>
                            <div class="form-group">
                                <label>Yang Menyerahkan</label>
                                <input type="text" name="yang_menyerahkan_name" placeholder="Nama yang menyerahkan">
                            </div>
                        </div>

                        <h4 style="color:#0b2b4a; margin-top:1.2rem;">Daftar Material Dikembalikan</h4>
                        <div id="k3ItemsWrap"></div>
                        <button type="button" class="btn-info" onclick="addK3ItemRow()" style="margin-top:0.5rem;"><i class="fas fa-plus"></i> Tambah Baris Material</button>

                        <div style="margin-top:1.2rem;">
                            <button type="submit" name="create_k3" class="btn-success"><i class="fas fa-save"></i> Ajukan</button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="alert-danger" style="padding:1rem; border-radius:10px; margin-top:1.5rem;">
                    <i class="fas fa-lock"></i> Silakan <a href="#" onclick="showLogin();return false;">login</a> atau <a href="#" onclick="showRegister();return false;">daftar sebagai vendor</a> untuk mengajukan pengembalian material.
                </div>
                <?php endif; ?>
            </div>
        <?php elseif ($page === 'k7'): ?>
            <div id="k7Section">
                <div class="monitoring-dpb">
                    <h3 style="color:#0b2b4a;"><i class="fas fa-recycle"></i> Cari / Monitoring K7 (Bon Pemakaian Material Bekas)</h3>
                    <div class="flex-row">
                        <div class="form-group">
                            <label>Nomor TUG</label>
                            <input id="k7TugInput" placeholder="TUG 5 NS.MLG23-0028" value="<?= $page === 'k7' ? htmlspecialchars($prefillTug) : '' ?>">
                        </div>
                        <button class="btn-success" onclick="loadK7()">Cari / Muat</button>
                    </div>
                    <div id="k7Result" style="margin-top:1.5rem; background:white; border-radius:24px; padding:1rem;">
                        <p class="text-small">Masukkan nomor TUG K7 yang sudah pernah diajukan untuk melihat detail &amp; status secara otomatis.</p>
                    </div>
                    <div style="margin-top:1rem; display:flex; gap:0.8rem; flex-wrap:wrap;">
                        <?php if (!$is_vendor): ?>
                        <button class="btn-warning" onclick="(function(){var t=document.getElementById('k7TugInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printK7.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-print"></i> Cetak Bon (Format Resmi)</button>
                        <?php endif; ?>
                        <button class="btn-info" onclick="(function(){var t=document.getElementById('k7TugInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printK7.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-file-pdf"></i> Simpan PDF</button>
                    </div>
                </div>

                <?php if ($is_logged_in): ?>
                <div class="card" style="margin-top:1.5rem;">
                    <h3><i class="fas fa-file-signature"></i> Ajukan Pemakaian Material Bekas Baru (K7)</h3>
                    
                    <form method="POST" action="k7.php" id="k7CreateForm">
                        <div class="flex-row">
                            <div class="form-group">
                                <label>Nomor TUG</label>
                                <input type="text" name="tug_number" placeholder="TUG 5 NS.MLG26-XXXX" required>
                            </div>
                            <div class="form-group">
                                <label>Tanggal Diminta</label>
                                <input type="date" name="tanggal_diminta" value="<?= date('Y-m-d') ?>" min="2023-01-01" max="2035-12-31">
                            </div>
                        </div>

                        <div class="flex-row">
                            <div class="form-group">
                                <label>Vendor</label>
                                <?php if ($is_vendor): ?>
                                    <input type="text" value="<?= htmlspecialchars($my_vendor['name'] ?? '') ?>" disabled>
                                    <input type="hidden" name="vendor_id" value="<?= $my_vendor['id'] ?? '' ?>">
                                <?php else: ?>
                                    <select name="vendor_id" id="k7VendorSelect" onchange="autofillVendorGeneric('k7')" required>
                                        <option value="">-- pilih PT / vendor --</option>
                                        <?php foreach ($vendors as $v): ?>
                                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>No. SPK</label>
                                <input type="text" name="spk_number" id="k7SpkInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_spk_number'] ?? '') : '' ?>">
                            </div>
                        </div>

                        <div class="flex-row">
                            <div class="form-group">
                                <label>Jenis Pekerjaan</label>
                                <input type="text" name="jenis_pekerjaan" id="k7JenisInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_jenis_pekerjaan'] ?? '') : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>IDPEL</label>
                                <input type="text" name="idpel" id="k7IdpelInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_idpel'] ?? '') : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>Daya</label>
                                <input type="text" name="daya" id="k7DayaInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_daya'] ?? '') : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>ULP</label>
                                <input type="text" name="ulp" id="k7UlpInput"
                                       value="<?= $is_vendor ? htmlspecialchars($my_vendor['default_ulp'] ?? '') : '' ?>">
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

                        <div class="flex-row">
                            <div class="form-group">
                                <label>Merk Material</label>
                                <input type="text" name="merk_material" placeholder="Opsional">
                            </div>
                            <div class="form-group">
                                <label>Nomor Seri</label>
                                <input type="text" name="nomor_seri" placeholder="Opsional">
                            </div>
                            <div class="form-group">
                                <label>Keterangan</label>
                                <input type="text" name="keterangan" placeholder="mis. DARI K3 NO. 0040 EX. WAVA HUSADA">
                            </div>
                        </div>

                        <h4 style="color:#0b2b4a; margin-top:1.2rem;">Data Tanda Tangan</h4>
                        <div class="flex-row">
                            <div class="form-group">
                                <label>Setuju</label>
                                <input type="text" name="setuju_name" placeholder="Nama penyetuju">
                            </div>
                            <div class="form-group">
                                <label>Kepala Gudang</label>
                                <input type="text" name="kepala_gudang_name" placeholder="Nama kepala gudang">
                            </div>
                            <div class="form-group">
                                <label>Pemeriksa / Pengawas</label>
                                <input type="text" name="pemeriksa_pengawas_name" placeholder="Nama pemeriksa/pengawas">
                            </div>
                            <div class="form-group">
                                <label>Penerima</label>
                                <input type="text" name="penerima_name" placeholder="Nama penerima">
                            </div>
                        </div>

                        <h4 style="color:#0b2b4a; margin-top:1.2rem;">Daftar Material Bekas Dipakai</h4>
                        <div id="k7ItemsWrap"></div>
                        <button type="button" class="btn-info" onclick="addK7ItemRow()" style="margin-top:0.5rem;"><i class="fas fa-plus"></i> Tambah Baris Material</button>

                        <div style="margin-top:1.2rem;">
                            <button type="submit" name="create_k7" class="btn-success"><i class="fas fa-save"></i> Ajukan</button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="alert-danger" style="padding:1rem; border-radius:10px; margin-top:1.5rem;">
                    <i class="fas fa-lock"></i> Silakan <a href="#" onclick="showLogin();return false;">login</a> atau <a href="#" onclick="showRegister();return false;">daftar sebagai vendor</a> untuk mengajukan pemakaian material bekas.
                </div>
                <?php endif; ?>
            </div>
        <?php elseif ($page === 'riwayat'): ?>
            <div id="riwayatSection">
                <div class="card">
                    <h3><i class="fas fa-history"></i> Riwayat Pengajuan</h3>

                    <?php if (!$is_vendor && !$is_admin): ?>
                        <div class="alert-danger" style="padding:1rem; border-radius:10px;">
                            <i class="fas fa-lock"></i> Menu ini hanya untuk akun vendor / admin gudang PLN.
                        </div>
                    <?php else: ?>
                        <?php if ($is_vendor): ?>
                        <p class="text-small">Menampilkan seluruh pengajuan DPB, K3, dan K7 atas nama <strong><?= htmlspecialchars($my_vendor['name'] ?? '') ?></strong>.</p>
                        <?php else: ?>
                        <p class="text-small">Menampilkan seluruh pengajuan DPB, K3, dan K7 dari semua vendor.</p>
                        <?php endif; ?>

                        <form method="GET" style="margin-bottom:1.2rem;">
                            <input type="hidden" name="page" value="riwayat">
                            <div class="flex-row" style="align-items:flex-end;">
                                <div class="form-group">
                                    <label>Dari Tanggal</label>
                                    <input type="date" name="start" value="<?= htmlspecialchars($riwayatStart) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Sampai Tanggal</label>
                                    <input type="date" name="end" value="<?= htmlspecialchars($riwayatEnd) ?>">
                                </div>
                                <div class="form-group" style="flex:0 0 auto;">
                                    <button type="submit" class="btn-success"><i class="fas fa-filter"></i> Filter</button>
                                    <?php if ($riwayatStart || $riwayatEnd): ?>
                                    <a href="?page=riwayat" class="btn btn-outline" style="text-decoration:none; margin-left:0.5rem;">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <?php if ($riwayatStart && $riwayatEnd): ?>
                        <p class="text-small">Menampilkan pengajuan dari <strong><?= htmlspecialchars($riwayatStart) ?></strong> sampai <strong><?= htmlspecialchars($riwayatEnd) ?></strong>.</p>
                        <?php endif; ?>

                        <?php if (empty($riwayat)): ?>
                        <p class="text-small">Tidak ada pengajuan yang ditemukan<?= ($riwayatStart && $riwayatEnd) ? ' pada rentang tanggal tersebut' : '' ?>.</p>
                        <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th><th>Jenis</th><th>No. TUG</th>
                                        <?php if ($is_admin): ?><th>Vendor</th><?php endif; ?>
                                        <th>Nama Pelanggan</th><th>Tanggal Diminta</th><th>Status</th><th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($riwayat as $i => $r): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><span class="role-badge"><?= htmlspecialchars($r['jenis']) ?></span></td>
                                        <td><?= htmlspecialchars($r['tug_number']) ?></td>
                                        <?php if ($is_admin): ?><td><?= htmlspecialchars($r['vendor_name'] ?? '-') ?></td><?php endif; ?>
                                        <td><?= htmlspecialchars($r['customer_name'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($r['tanggal_diminta'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars(dpbStatusLabel($r['status'])) ?></td>
                                        <td>
                                            <a href="?page=<?= strtolower($r['jenis']) ?>&tug=<?= urlencode($r['tug_number']) ?>" class="btn-info" style="padding:0.3rem 0.9rem; border-radius:20px; text-decoration:none; font-size:0.75rem; white-space:nowrap;">Lihat Detail</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($page === 'mdu'): ?>
            <div id="mduSection">
                <?php if (!$is_admin): ?>
                <div class="alert-danger" style="padding:1rem; border-radius:10px;">
                    <i class="fas fa-lock"></i> Hanya admin gudang PLN yang dapat mengakses menu MDU.
                </div>
                <?php else: ?>
                <div class="monitoring-dpb">
                    <h3 style="color:#0b2b4a;"><i class="fas fa-tasks"></i> Dafung — Monitoring Permintaan &amp; Sisa Pemenuhan Material</h3>
                    <p class="text-small">Cari nama material untuk melihat vendor mana saja yang meminjam/mengajukan material tersebut, tanggal pengajuan (nomor TUG), jumlah yang sudah dipenuhi, dan sisa yang belum terpenuhi.</p>

                    <div class="flex-row">
                        <div class="form-group">
                            <label>Nama Material</label>
                            <input id="mduMaterialInput" list="mduMaterialList" placeholder="Contoh: MCB 10A" oninput="searchMdu()">
                            <datalist id="mduMaterialList">
                                <?php foreach ($materials as $m): ?>
                                    <option value="<?= htmlspecialchars($m['name']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <button class="btn-success" onclick="searchMdu()"><i class="fas fa-search"></i> Cari</button>
                    </div>

                    <div id="mduResult" style="margin-top:1.5rem; background:white; border-radius:24px; padding:1rem;">
                        <p class="text-small">Ketik nama material di atas untuk melihat daftar vendor yang mengajukan &amp; sisa yang belum terpenuhi.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>


    <div class="site-footer-text">
        <span>PLN Identity and Access Management</span>
        <span>Copyright - Aurellia Mezaluna Azwa</span>
    </div>
</div><div id="authModal" class="modal <?= $openModal ? 'show' : '' ?>">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>

        <div class="modal-brand">
            <img src="images/logo.png" alt="PLN Logo">
            <span>VOLTA</span>
        </div>

        <h2 id="modalTitle">Masuk</h2>

        <?php if ($openModal && isset($_SESSION['error'])): ?>
            <div class="alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php elseif ($openModal && isset($_SESSION['success'])): ?>
            <div class="alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form method="POST" action="auth.php" id="authForm">
            <div id="loginFields">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="email@pln.co.id" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="******" maxlength="7" required>
                </div>
                <button type="submit" name="login" class="btn-primary-full">Login</button>
            </div>

            <div id="registerFields" style="display:none;">
                <div class="form-group">
                    <label>Nama Lengkap (Penanggung Jawab)</label>
                    <input type="text" name="reg_name" placeholder="Nama lengkap" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="reg_email" placeholder="email@company.com" required>
                </div>
                <div class="form-group">
                    <label>Password <span class="text-small">(maks. 7 digit)</span></label>
                    <input type="password" name="reg_password" placeholder="******" maxlength="7" required>
                </div>
                <div class="form-group">
                    <label>Nama PT / Vendor</label>
                    <input type="text" name="reg_vendor_name" placeholder="PT. ..." required>
                </div>
                <div class="form-group">
                    <label>Telepon Vendor</label>
                    <input type="text" name="reg_vendor_phone" placeholder="08123456789">
                </div>
                <div class="form-group">
                    <label>Alamat Vendor</label>
                    <input type="text" name="reg_vendor_address" placeholder="Alamat lengkap">
                </div>
                <button type="submit" name="register" class="btn-primary-full">Daftar sebagai Vendor</button>
            </div>
        </form>

        <div id="forgotFields" style="display:none;">
            <div class="forgot-box">
                <p style="margin-top:0;"><strong><i class="fas fa-key"></i> Lupa sandi akun Anda?</strong></p>
                <p>Untuk alasan keamanan, reset sandi hanya dapat dilakukan oleh admin gudang PLN. Silakan hubungi:</p>
                <p><i class="fas fa-phone"></i> Call Center PLN 123 (kode area Malang: 0341-123)</p>
                <p><i class="fas fa-envelope"></i> pln123@pln.co.id</p>
                <p style="margin-bottom:0;"><i class="fas fa-map-marker-alt"></i> PLN UP3 Malang, Jl. Jenderal Basuki Rahmat No. 100, Klojen, Kota Malang</p>
            </div>
        </div>

        <div class="faq-link" onclick="showFaq()">Butuh bantuan lain? Lihat FAQ</div>

        <span id="toggleAuthText">Belum punya akun? <a href="#" onclick="toggleAuth()">Daftar</a></span>
        <span id="backToLoginText" style="display:none;">Sudah ingat sandi? <a href="#" onclick="showLogin()">Kembali ke Login</a></span>
        <div style="text-align:center; margin-top:0.3rem;">
            <a href="#" onclick="showForgotPassword(); return false;" style="font-size:0.82rem; color: var(--blue);">Lupa sandi?</a>
        </div>
    </div>
</div>

<div id="faqModal" class="modal">
    <div class="modal-content" style="max-width:600px;">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>FAQ</h2>
        <p><strong>Bagaimana cara mengajukan permintaan material?</strong> Daftar / login sebagai vendor, lalu buka menu DPB dan isi formulir "Ajukan Permintaan Material Baru".</p>
        <p><strong>Bagaimana cara menambah material atau vendor baru secara langsung?</strong> Hanya admin gudang PLN yang dapat menambah data master Material dan Vendor.</p>
        <p><strong>Kenapa Telepon &amp; Email vendor tidak terlihat?</strong> Data kontak vendor bersifat rahasia dan hanya dapat diakses oleh admin gudang PLN.</p>
        <p><strong>Lupa sandi?</strong> Hubungi admin gudang PLN melalui kontak di bawah untuk reset akun.</p>

        <div class="faq-contact">
            <p style="margin-top:0;"><strong><i class="fas fa-headset"></i> Kontak PLN Kota Malang (UP3 Malang)</strong></p>
            <p style="margin-bottom:0;">
                <i class="fas fa-phone"></i> Call Center PLN 123 — kode area Malang: (0341) 123<br>
                <i class="fas fa-envelope"></i> pln123@pln.co.id<br>
                <i class="fas fa-map-marker-alt"></i> Jl. Jenderal Basuki Rahmat No. 100, Klojen, Kota Malang, Jawa Timur 65119
            </p>
        </div>

        <p style="margin-top:1rem;"><strong>Apa itu WBS?</strong> Sistem Pengaduan Pelanggaran untuk melaporkan fraud.</p>
        <p><strong>Sarana pengaduan:</strong> Website, SMS/WA 08119861901, email wbpdn@pln.co.id, surat ke Kantor Pusat.</p>
        <p><strong>Alur:</strong> buka cos.pln.co.id → buat laporan → isi form identitas, komunikasi, dugaan pelanggaran, bukti.</p>
    </div>
</div>

<?php if ($is_admin): ?>
<div id="materialEditModal" class="modal">
    <div class="modal-content" style="max-width:480px;">
        <span class="close" onclick="closeMaterialEditModal()">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Material</h2>
        <form method="POST" action="material.php" id="materialEditForm">
            <input type="hidden" name="material_id" id="editMaterialId">
            <div class="form-group">
                <label>Nama Material</label>
                <input type="text" name="material_name" id="editMaterialName" list="materialNameList" required oninput="updateNormEdit()">
            </div>
            <div class="form-group">
                <label>Normalisasi</label>
                <input type="text" name="material_norm" id="editMaterialNorm" list="materialNormList">
            </div>
            <div class="form-group">
                <label>Satuan</label>
                <select name="material_unit" id="editMaterialUnit">
                    <option value="BH">BH</option>
                    <option value="SET">SET</option>
                    <option value="M">M</option>
                    <option value="PACK">PACK</option>
                    <option value="ROLL">ROLL</option>
                </select>
            </div>
            <div class="form-group">
                <label>Jumlah Material <span class="text-small">(edit stock)</span></label>
                <input type="number" name="material_stock" id="editMaterialStock" min="0" step="1" required>
            </div>
            <button type="submit" name="edit_material" class="btn-primary-full">Simpan Perubahan</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    window.AUTO_OPEN_MODAL = <?= $openModal ? json_encode($openModal) : 'false' ?>;
    window.IS_ADMIN = <?= ($is_admin || $is_gudang2) ? 'true' : 'false' ?>;
    window.IS_LOGGED_IN = <?= $is_logged_in ? 'true' : 'false' ?>;
    // Seluruh master material (nama + normalisasi) utk autocomplete instan di form DPB
    window.MATERIALS_DATA = <?= json_encode(array_map(function ($m) {
        return ['id' => $m['id'], 'name' => $m['name'], 'norm' => $m['norm'], 'unit' => $m['unit']];
    }, $materials)) ?>;
    // Master kode normalisasi resmi dari data/normalisasi.csv (dipakai di Material, DPB, K3 & K7)
    window.NORMALISASI_DATA = <?= json_encode(getNormalisasiData()) ?>;
    <?php if ($prefillTug): ?>
    window.AUTO_LOAD_TUG = <?= json_encode($prefillTug) ?>;
    <?php endif; ?>
</script>
<div id="printArea"></div>

<script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>"></script>
</body>
</html>