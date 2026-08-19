<?php
require_once 'config.php';
require_once 'functions.php';

// =========================================================
// AJAX ENDPOINTS
// =========================================================
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // Daftar semua material (dipakai tabel Stok Material) — bukan utk vendor
    if ($_GET['ajax'] === 'materials') {
        if (isVendor()) {
            http_response_code(403);
            echo json_encode(['error' => 'Akses ditolak.']);
            exit();
        }
        echo json_encode(getMaterials($db));
        exit();
    }

    // Daftar material masuk
    if ($_GET['ajax'] === 'incoming_materials') {
        if (isVendor()) {
            http_response_code(403);
            echo json_encode(['error' => 'Akses ditolak.']);
            exit();
        }
        $stmt = $db->query("SELECT im.*, m.name AS material_name, m.norm AS material_norm, m.unit AS material_unit 
                            FROM incoming_materials im
                            JOIN materials m ON im.material_id = m.id
                            ORDER BY im.tanggal_datang DESC, im.id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }

    // Daftar material keluar
    if ($_GET['ajax'] === 'outgoing_materials') {
        if (isVendor()) {
            http_response_code(403);
            echo json_encode(['error' => 'Akses ditolak.']);
            exit();
        }
        $stmt = $db->query("SELECT di.id, di.quantity_received AS quantity, di.sn AS sn, m.name AS material_name, m.norm AS material_norm, m.unit AS material_unit,
                                   d.tug_number AS no_dpb, d.surat_jalan_number AS surat_jalan, d.tanggal_diminta AS tanggal_keluar, v.name AS vendor_name
                            FROM dpb_items di
                            JOIN dpb_transactions d ON di.dpb_id = d.id
                            JOIN materials m ON di.material_id = m.id
                            JOIN vendors v ON d.vendor_id = v.id
                            WHERE di.quantity_received > 0
                            ORDER BY d.tanggal_diminta DESC, di.id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
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
            'setuju_name'             => $dpb['setuju_name'],
            'kepala_gudang_name'      => $dpb['kepala_gudang_name'],
            'pemeriksa_pengawas_name' => $dpb['pemeriksa_pengawas_name'],
            'surat_jalan_number'      => $dpb['surat_jalan_number'],
            'next_surat_jalan_number' => $dpb['surat_jalan_number'] ?: generateNextSuratJalanNumber($db, $dpb['tanggal_diminta'] ?: date('Y-m-d')),
            'diterima_tgl'            => $dpb['diterima_tgl'],
            'malang_tanggal'          => $dpb['malang_tanggal'],
            'items'            => $dpb['items'],
        ]);
        exit();
    }

    // Generate dan simpan nomor surat jalan secara permanen lewat AJAX jika masih kosong
    if ($_GET['ajax'] === 'generate_sj') {
        $dpbId = $_GET['dpb_id'] ?? '';
        $stmt = $db->prepare("SELECT surat_jalan_number, tanggal_diminta FROM dpb_transactions WHERE id = ?");
        $stmt->execute([$dpbId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $sjNumber = $row['surat_jalan_number'];
            if (empty($sjNumber)) {
                $sjNumber = generateNextSuratJalanNumber($db, $row['tanggal_diminta'] ?: date('Y-m-d'));
                $stmtUpdate = $db->prepare("UPDATE dpb_transactions SET surat_jalan_number = ? WHERE id = ?");
                $stmtUpdate->execute([$sjNumber, $dpbId]);
            }
            echo json_encode(['surat_jalan_number' => $sjNumber]);
        } else {
            echo json_encode(['error' => 'DPB tidak ditemukan.']);
        }
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


$vendors      = getVendors($db);
$materials    = getMaterials($db);
$my_vendor    = $is_vendor ? getVendorById($db, currentVendorId()) : null;
$page         = $_GET['page'] ?? 'home';
$prefillTug   = $_GET['tug'] ?? '';

$limit = 10;
$apage = isset($_GET['apage']) ? max(1, (int)$_GET['apage']) : 1;
$hpage = isset($_GET['hpage']) ? max(1, (int)$_GET['hpage']) : 1;

$q = trim($_GET['q'] ?? '');
$startDate = trim($_GET['start'] ?? '');
$endDate = trim($_GET['end'] ?? '');
$q_active = trim($_GET['q_active'] ?? '');
$start_active = trim($_GET['start_active'] ?? '');
$end_active = trim($_GET['end_active'] ?? '');
$riwayatStart = $_GET['start'] ?? '';
$riwayatEnd = $_GET['end'] ?? '';

$q1 = trim($_GET['q1'] ?? '');
$start1 = trim($_GET['start1'] ?? '');
$end1 = trim($_GET['end1'] ?? '');

$q2 = trim($_GET['q2'] ?? '');
$start2 = trim($_GET['start2'] ?? '');
$end2 = trim($_GET['end2'] ?? '');

$riwayat = [];

$active_list = [];
$history_list = [];
$totalActivePages = 0;
$totalHistoryPages = 0;
$paged_vendors = [];
$paged_applications = [];
$totalActiveRows = 0;
$totalHistoryRows = 0;

if ($page === 'dpb') {
    if ($is_gudang2) {
        $tab = $_GET['tab'] ?? 'active';
        $sqlConds = [];
        $sqlParams = [];
        
        if ($tab === 'active') {
            $sqlConds[] = "d.status IN ('belum_jalan', 'aktif', 'menunggu_persetujuan')";
        } else {
            $sqlConds[] = "d.status = 'selesai'";
        }
        
        if ($q !== '') {
            $sqlConds[] = "(d.tug_number ILIKE ? OR v.name ILIKE ?)";
            $sqlParams[] = '%' . $q . '%';
            $sqlParams[] = '%' . $q . '%';
        }
        if ($startDate !== '' && $endDate !== '') {
            $sqlConds[] = "d.tanggal_diminta BETWEEN ? AND ?";
            $sqlParams[] = $startDate;
            $sqlParams[] = $endDate;
        }
        
        $condsStr = empty($sqlConds) ? "1=1" : implode(' AND ', $sqlConds);
        
        $countQuery = "SELECT COUNT(*) FROM dpb_transactions d LEFT JOIN vendors v ON d.vendor_id = v.id WHERE $condsStr";
        $stmtCount = $db->prepare($countQuery);
        $stmtCount->execute($sqlParams);
        $totalRows = (int)$stmtCount->fetchColumn();
        $totalPages = ceil($totalRows / $limit);
        
        $offset = ($apage - 1) * $limit;
        $dataQuery = "
            SELECT d.*, v.name AS vendor_name 
            FROM dpb_transactions d 
            LEFT JOIN vendors v ON d.vendor_id = v.id 
            WHERE $condsStr
            ORDER BY d.tanggal_diminta DESC, d.id DESC
            LIMIT ? OFFSET ?
        ";
        $stmtData = $db->prepare($dataQuery);
        
        $sqlParams[] = $limit;
        $sqlParams[] = $offset;
        
        $paramIdx = 1;
        foreach ($sqlParams as $val) {
            $stmtData->bindValue($paramIdx++, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmtData->execute();
        $gudang_list = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    }

    $offsetActive = ($apage - 1) * $limit;
    $sqlCondsActive = ["d.status IN ('belum_jalan', 'aktif', 'menunggu_persetujuan')"];
    $sqlParamsActive = [];

    if ($is_vendor) {
        $sqlCondsActive[] = "d.vendor_id = ?";
        $sqlParamsActive[] = currentVendorId();
    }
    if ($q_active !== '') {
        $sqlCondsActive[] = "(d.tug_number ILIKE ? OR v.name ILIKE ?)";
        $sqlParamsActive[] = '%' . $q_active . '%';
        $sqlParamsActive[] = '%' . $q_active . '%';
    }
    if ($start_active !== '' && $end_active !== '') {
        $sqlCondsActive[] = "d.tanggal_diminta BETWEEN ? AND ?";
        $sqlParamsActive[] = $start_active;
        $sqlParamsActive[] = $end_active;
    }

    $condsStrActive = implode(' AND ', $sqlCondsActive);

    $countActiveQuery = "SELECT COUNT(*) FROM dpb_transactions d LEFT JOIN vendors v ON d.vendor_id = v.id WHERE $condsStrActive";
    $stmtCountActive = $db->prepare($countActiveQuery);
    $stmtCountActive->execute($sqlParamsActive);
    $totalActiveRows = (int)$stmtCountActive->fetchColumn();
    $totalActivePages = ceil($totalActiveRows / $limit);

    $activeQuery = "
        SELECT d.*, v.name AS vendor_name 
        FROM dpb_transactions d 
        LEFT JOIN vendors v ON d.vendor_id = v.id 
        WHERE $condsStrActive
        ORDER BY d.tanggal_diminta DESC, d.id DESC
        LIMIT ? OFFSET ?
    ";
    $activeDpbStmt = $db->prepare($activeQuery);
    $paramIdxActive = 1;
    foreach ($sqlParamsActive as $p) {
        $activeDpbStmt->bindValue($paramIdxActive++, $p);
    }
    $activeDpbStmt->bindValue($paramIdxActive++, $limit, PDO::PARAM_INT);
    $activeDpbStmt->bindValue($paramIdxActive++, $offsetActive, PDO::PARAM_INT);
    $activeDpbStmt->execute();
    $active_list = $activeDpbStmt->fetchAll(PDO::FETCH_ASSOC);

    $offsetHistory = ($hpage - 1) * $limit;
    $sqlConds = ["d.status = 'selesai'"];
    $sqlParams = [];

    if ($is_vendor) {
        $sqlConds[] = "d.vendor_id = ?";
        $sqlParams[] = currentVendorId();
    }
    if ($q !== '') {
        $sqlConds[] = "(d.tug_number ILIKE ? OR v.name ILIKE ?)";
        $sqlParams[] = '%' . $q . '%';
        $sqlParams[] = '%' . $q . '%';
    }
    if ($startDate !== '' && $endDate !== '') {
        $sqlConds[] = "d.tanggal_diminta BETWEEN ? AND ?";
        $sqlParams[] = $startDate;
        $sqlParams[] = $endDate;
    }

    $condsStr = implode(' AND ', $sqlConds);

    $countHistoryQuery = "SELECT COUNT(*) FROM dpb_transactions d LEFT JOIN vendors v ON d.vendor_id = v.id WHERE $condsStr";
    $stmtCountHistory = $db->prepare($countHistoryQuery);
    $stmtCountHistory->execute($sqlParams);
    $totalHistoryRows = (int)$stmtCountHistory->fetchColumn();
    $totalHistoryPages = ceil($totalHistoryRows / $limit);

    $historyQuery = "
        SELECT d.*, v.name AS vendor_name 
        FROM dpb_transactions d 
        LEFT JOIN vendors v ON d.vendor_id = v.id 
        WHERE $condsStr
        ORDER BY d.tanggal_diminta DESC, d.id DESC
        LIMIT ? OFFSET ?
    ";
    $stmtHistory = $db->prepare($historyQuery);
    $paramIdx = 1;
    foreach ($sqlParams as $p) {
        $stmtHistory->bindValue($paramIdx++, $p);
    }
    $stmtHistory->bindValue($paramIdx++, $limit, PDO::PARAM_INT);
    $stmtHistory->bindValue($paramIdx++, $offsetHistory, PDO::PARAM_INT);
    $stmtHistory->execute();
    $history_list = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

} elseif ($page === 'k3') {
    if ($is_gudang2) {
        $tab = $_GET['tab'] ?? 'active';
        $sqlConds = [];
        $sqlParams = [];
        
        if ($tab === 'active') {
            $sqlConds[] = "k.status IN ('belum_jalan', 'aktif')";
        } else {
            $sqlConds[] = "k.status = 'selesai'";
        }
        
        if ($q !== '') {
            $sqlConds[] = "(k.tug_number ILIKE ? OR v.name ILIKE ?)";
            $sqlParams[] = '%' . $q . '%';
            $sqlParams[] = '%' . $q . '%';
        }
        if ($startDate !== '' && $endDate !== '') {
            $sqlConds[] = "k.tanggal_diminta BETWEEN ? AND ?";
            $sqlParams[] = $startDate;
            $sqlParams[] = $endDate;
        }
        
        $condsStr = empty($sqlConds) ? "1=1" : implode(' AND ', $sqlConds);
        
        $countQuery = "SELECT COUNT(*) FROM k3_transactions k LEFT JOIN vendors v ON k.vendor_id = v.id WHERE $condsStr";
        $stmtCount = $db->prepare($countQuery);
        $stmtCount->execute($sqlParams);
        $totalRows = (int)$stmtCount->fetchColumn();
        $totalPages = ceil($totalRows / $limit);
        
        $offset = ($apage - 1) * $limit;
        $dataQuery = "
            SELECT k.*, v.name AS vendor_name 
            FROM k3_transactions k 
            LEFT JOIN vendors v ON k.vendor_id = v.id 
            WHERE $condsStr
            ORDER BY k.tanggal_diminta DESC, k.id DESC
            LIMIT ? OFFSET ?
        ";
        $stmtData = $db->prepare($dataQuery);
        
        $sqlParams[] = $limit;
        $sqlParams[] = $offset;
        
        $paramIdx = 1;
        foreach ($sqlParams as $val) {
            $stmtData->bindValue($paramIdx++, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmtData->execute();
        $gudang_list = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    }

    $offsetActive = ($apage - 1) * $limit;
    $vendorFilter = $is_vendor ? "AND k.vendor_id = " . (int)currentVendorId() : "";

    $countActiveStmt = $db->query("SELECT COUNT(*) FROM k3_transactions k WHERE k.status IN ('belum_jalan', 'aktif') $vendorFilter");
    $totalActiveRows = (int)$countActiveStmt->fetchColumn();
    $totalActivePages = ceil($totalActiveRows / $limit);

    $activeK3Stmt = $db->prepare("
        SELECT k.*, v.name AS vendor_name 
        FROM k3_transactions k 
        LEFT JOIN vendors v ON k.vendor_id = v.id 
        WHERE k.status IN ('belum_jalan', 'aktif') $vendorFilter
        ORDER BY k.tanggal_diminta DESC, k.id DESC
        LIMIT ? OFFSET ?
    ");
    $activeK3Stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $activeK3Stmt->bindValue(2, $offsetActive, PDO::PARAM_INT);
    $activeK3Stmt->execute();
    $active_list = $activeK3Stmt->fetchAll(PDO::FETCH_ASSOC);

    $offsetHistory = ($hpage - 1) * $limit;
    $sqlConds = ["k.status = 'selesai'"];
    $sqlParams = [];

    if ($is_vendor) {
        $sqlConds[] = "k.vendor_id = ?";
        $sqlParams[] = currentVendorId();
    }
    if ($q !== '') {
        $sqlConds[] = "(k.tug_number ILIKE ? OR v.name ILIKE ?)";
        $sqlParams[] = '%' . $q . '%';
        $sqlParams[] = '%' . $q . '%';
    }
    if ($startDate !== '' && $endDate !== '') {
        $sqlConds[] = "k.tanggal_diminta BETWEEN ? AND ?";
        $sqlParams[] = $startDate;
        $sqlParams[] = $endDate;
    }

    $condsStr = implode(' AND ', $sqlConds);

    $countHistoryQuery = "SELECT COUNT(*) FROM k3_transactions k LEFT JOIN vendors v ON k.vendor_id = v.id WHERE $condsStr";
    $stmtCountHistory = $db->prepare($countHistoryQuery);
    $stmtCountHistory->execute($sqlParams);
    $totalHistoryRows = (int)$stmtCountHistory->fetchColumn();
    $totalHistoryPages = ceil($totalHistoryRows / $limit);

    $historyQuery = "
        SELECT k.*, v.name AS vendor_name 
        FROM k3_transactions k 
        LEFT JOIN vendors v ON k.vendor_id = v.id 
        WHERE $condsStr
        ORDER BY k.tanggal_diminta DESC, k.id DESC
        LIMIT ? OFFSET ?
    ";
    $stmtHistory = $db->prepare($historyQuery);
    $paramIdx = 1;
    foreach ($sqlParams as $p) {
        $stmtHistory->bindValue($paramIdx++, $p);
    }
    $stmtHistory->bindValue($paramIdx++, $limit, PDO::PARAM_INT);
    $stmtHistory->bindValue($paramIdx++, $offsetHistory, PDO::PARAM_INT);
    $stmtHistory->execute();
    $history_list = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

} elseif ($page === 'k7') {
    if ($is_gudang2) {
        $tab = $_GET['tab'] ?? 'active';
        $sqlConds = [];
        $sqlParams = [];
        
        if ($tab === 'active') {
            $sqlConds[] = "k.status IN ('belum_jalan', 'aktif')";
        } else {
            $sqlConds[] = "k.status = 'selesai'";
        }
        
        if ($q !== '') {
            $sqlConds[] = "(k.tug_number ILIKE ? OR v.name ILIKE ?)";
            $sqlParams[] = '%' . $q . '%';
            $sqlParams[] = '%' . $q . '%';
        }
        if ($startDate !== '' && $endDate !== '') {
            $sqlConds[] = "k.tanggal_diminta BETWEEN ? AND ?";
            $sqlParams[] = $startDate;
            $sqlParams[] = $endDate;
        }
        
        $condsStr = empty($sqlConds) ? "1=1" : implode(' AND ', $sqlConds);
        
        $countQuery = "SELECT COUNT(*) FROM k7_transactions k LEFT JOIN vendors v ON k.vendor_id = v.id WHERE $condsStr";
        $stmtCount = $db->prepare($countQuery);
        $stmtCount->execute($sqlParams);
        $totalRows = (int)$stmtCount->fetchColumn();
        $totalPages = ceil($totalRows / $limit);
        
        $offset = ($apage - 1) * $limit;
        $dataQuery = "
            SELECT k.*, v.name AS vendor_name 
            FROM k7_transactions k 
            LEFT JOIN vendors v ON k.vendor_id = v.id 
            WHERE $condsStr
            ORDER BY k.tanggal_diminta DESC, k.id DESC
            LIMIT ? OFFSET ?
        ";
        $stmtData = $db->prepare($dataQuery);
        
        $sqlParams[] = $limit;
        $sqlParams[] = $offset;
        
        $paramIdx = 1;
        foreach ($sqlParams as $val) {
            $stmtData->bindValue($paramIdx++, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmtData->execute();
        $gudang_list = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    }

    $offsetActive = ($apage - 1) * $limit;
    $vendorFilter = $is_vendor ? "AND k.vendor_id = " . (int)currentVendorId() : "";

    $countActiveStmt = $db->query("SELECT COUNT(*) FROM k7_transactions k WHERE k.status IN ('belum_jalan', 'aktif') $vendorFilter");
    $totalActiveRows = (int)$countActiveStmt->fetchColumn();
    $totalActivePages = ceil($totalActiveRows / $limit);

    $activeK7Stmt = $db->prepare("
        SELECT k.*, v.name AS vendor_name 
        FROM k7_transactions k 
        LEFT JOIN vendors v ON k.vendor_id = v.id 
        WHERE k.status IN ('belum_jalan', 'aktif') $vendorFilter
        ORDER BY k.tanggal_diminta DESC, k.id DESC
        LIMIT ? OFFSET ?
    ");
    $activeK7Stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $activeK7Stmt->bindValue(2, $offsetActive, PDO::PARAM_INT);
    $activeK7Stmt->execute();
    $active_list = $activeK7Stmt->fetchAll(PDO::FETCH_ASSOC);

    $offsetHistory = ($hpage - 1) * $limit;
    $sqlConds = ["k.status = 'selesai'"];
    $sqlParams = [];

    if ($is_vendor) {
        $sqlConds[] = "k.vendor_id = ?";
        $sqlParams[] = currentVendorId();
    }
    if ($q !== '') {
        $sqlConds[] = "(k.tug_number ILIKE ? OR v.name ILIKE ?)";
        $sqlParams[] = '%' . $q . '%';
        $sqlParams[] = '%' . $q . '%';
    }
    if ($startDate !== '' && $endDate !== '') {
        $sqlConds[] = "k.tanggal_diminta BETWEEN ? AND ?";
        $sqlParams[] = $startDate;
        $sqlParams[] = $endDate;
    }

    $condsStr = implode(' AND ', $sqlConds);

    $countHistoryQuery = "SELECT COUNT(*) FROM k7_transactions k LEFT JOIN vendors v ON k.vendor_id = v.id WHERE $condsStr";
    $stmtCountHistory = $db->prepare($countHistoryQuery);
    $stmtCountHistory->execute($sqlParams);
    $totalHistoryRows = (int)$stmtCountHistory->fetchColumn();
    $totalHistoryPages = ceil($totalHistoryRows / $limit);

    $historyQuery = "
        SELECT k.*, v.name AS vendor_name 
        FROM k7_transactions k 
        LEFT JOIN vendors v ON k.vendor_id = v.id 
        WHERE $condsStr
        ORDER BY k.tanggal_diminta DESC, k.id DESC
        LIMIT ? OFFSET ?
    ";
    $stmtHistory = $db->prepare($historyQuery);
    $paramIdx = 1;
    foreach ($sqlParams as $p) {
        $stmtHistory->bindValue($paramIdx++, $p);
    }
    $stmtHistory->bindValue($paramIdx++, $limit, PDO::PARAM_INT);
    $stmtHistory->bindValue($paramIdx++, $offsetHistory, PDO::PARAM_INT);
    $stmtHistory->execute();
    $history_list = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
}
$stat_total_material = count($materials);
$stat_total_vendor = count($vendors);
$stat_stok_tersedia = 0;
$stat_dpb_aktif = 0;
$stat_k3_aktif = 0;
$stat_k7_aktif = 0;
$stat_material_pending = 0;

$chart_dpb = array_fill(1, 12, 0);
$chart_k3 = array_fill(1, 12, 0);
$chart_k7 = array_fill(1, 12, 0);
$top_pending_materials = [];
$detail_pending = [];
$rekap_pending = [];

if ($is_admin) {
    // Stok Tersedia
    $stmt = $db->query("SELECT SUM(stock) FROM materials");
    $val = $stmt->fetchColumn();
    $stat_stok_tersedia = $val !== null ? (int)$val : 0;

    // DPB Aktif
    $stmt = $db->query("SELECT COUNT(*) FROM dpb_transactions WHERE status = 'aktif' OR status = 'belum_jalan'");
    $stat_dpb_aktif = (int)$stmt->fetchColumn();

    // K3 Aktif
    $stmt = $db->query("SELECT COUNT(*) FROM k3_transactions WHERE status = 'aktif' OR status = 'belum_jalan'");
    $stat_k3_aktif = (int)$stmt->fetchColumn();

    // K7 Aktif
    $stmt = $db->query("SELECT COUNT(*) FROM k7_transactions WHERE status = 'aktif' OR status = 'belum_jalan'");
    $stat_k7_aktif = (int)$stmt->fetchColumn();

    // Material Pending (Jumlah baris permintaan yang pending)
    $stmt = $db->query("
        SELECT COUNT(*) FROM dpb_items di 
        JOIN dpb_transactions d ON di.dpb_id = d.id 
        WHERE d.status IN ('aktif', 'belum_jalan') AND di.quantity_requested > di.quantity_received
    ");
    $stat_material_pending = (int)$stmt->fetchColumn();

    // Data Chart - Tren Bulanan
    $currentYearFull = date('Y');
    
    $stmt = $db->prepare("
        SELECT EXTRACT(MONTH FROM tanggal_diminta) AS bln, COUNT(*) AS count 
        FROM dpb_transactions 
        WHERE EXTRACT(YEAR FROM tanggal_diminta) = ?
        GROUP BY bln ORDER BY bln
    ");
    $stmt->execute([$currentYearFull]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $chart_dpb[(int)$row['bln']] = (int)$row['count'];
    }

    $stmt = $db->prepare("
        SELECT EXTRACT(MONTH FROM tanggal_diminta) AS bln, COUNT(*) AS count 
        FROM k3_transactions 
        WHERE EXTRACT(YEAR FROM tanggal_diminta) = ?
        GROUP BY bln ORDER BY bln
    ");
    $stmt->execute([$currentYearFull]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $chart_k3[(int)$row['bln']] = (int)$row['count'];
    }

    $stmt = $db->prepare("
        SELECT EXTRACT(MONTH FROM tanggal_diminta) AS bln, COUNT(*) AS count 
        FROM k7_transactions 
        WHERE EXTRACT(YEAR FROM tanggal_diminta) = ?
        GROUP BY bln ORDER BY bln
    ");
    $stmt->execute([$currentYearFull]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $chart_k7[(int)$row['bln']] = (int)$row['count'];
    }

    // Data Chart - Top 5 Material Pending
    $stmt = $db->query("
        SELECT 
            m.name AS material_name,
            SUM(di.quantity_requested - di.quantity_received) AS total_pending
        FROM dpb_items di
        JOIN dpb_transactions d ON di.dpb_id = d.id
        LEFT JOIN materials m ON di.material_id = m.id
        WHERE d.status IN ('aktif', 'belum_jalan')
          AND di.quantity_requested > di.quantity_received
        GROUP BY m.name
        ORDER BY total_pending DESC, m.name ASC
        LIMIT 5
    ");
    $top_pending_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Data Halaman Material Pending (Tabel 1 & Tabel 2)
    if ($page === 'material_pending') {
        // Table 1 (Rincian per Vendor)
        $sqlConds1 = ["d.status IN ('aktif', 'belum_jalan')", "di.quantity_requested > di.quantity_received"];
        $sqlParams1 = [];
        if ($start1 !== '' && $end1 !== '') {
            $sqlConds1[] = "d.tanggal_diminta BETWEEN ? AND ?";
            $sqlParams1[] = $start1;
            $sqlParams1[] = $end1;
        }
        if ($q1 !== '') {
            $sqlConds1[] = "(m.name ILIKE ? OR v.name ILIKE ? OR d.customer_name ILIKE ? OR d.tug_number ILIKE ?)";
            $qLike1 = '%' . $q1 . '%';
            $sqlParams1[] = $qLike1;
            $sqlParams1[] = $qLike1;
            $sqlParams1[] = $qLike1;
            $sqlParams1[] = $qLike1;
        }
        $condStr1 = implode(' AND ', $sqlConds1);

        $stmt = $db->prepare("
            SELECT 
                m.name AS material_name,
                (di.quantity_requested - di.quantity_received) AS jumlah_pending,
                v.name AS vendor_name,
                d.customer_name AS customer_name,
                d.tug_number AS tug_number
            FROM dpb_items di
            JOIN dpb_transactions d ON di.dpb_id = d.id
            JOIN vendors v ON d.vendor_id = v.id
            LEFT JOIN materials m ON di.material_id = m.id
            WHERE $condStr1
            ORDER BY v.name ASC, m.name ASC
        ");
        $stmt->execute($sqlParams1);
        $detail_pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Table 2 (Akumulasi Rekap per Material)
        $sqlConds2 = ["d.status IN ('aktif', 'belum_jalan')", "di.quantity_requested > di.quantity_received"];
        $sqlParams2 = [];
        if ($start2 !== '' && $end2 !== '') {
            $sqlConds2[] = "d.tanggal_diminta BETWEEN ? AND ?";
            $sqlParams2[] = $start2;
            $sqlParams2[] = $end2;
        }
        if ($q2 !== '') {
            $sqlConds2[] = "(m.name ILIKE ? OR v.name ILIKE ? OR d.customer_name ILIKE ? OR d.tug_number ILIKE ?)";
            $qLike2 = '%' . $q2 . '%';
            $sqlParams2[] = $qLike2;
            $sqlParams2[] = $qLike2;
            $sqlParams2[] = $qLike2;
            $sqlParams2[] = $qLike2;
        }
        $condStr2 = implode(' AND ', $sqlConds2);

        $stmt = $db->prepare("
            SELECT 
                m.name AS material_name,
                SUM(di.quantity_requested - di.quantity_received) AS total_pending
            FROM dpb_items di
            JOIN dpb_transactions d ON di.dpb_id = d.id
            LEFT JOIN materials m ON di.material_id = m.id
            WHERE $condStr2
            GROUP BY m.name
            ORDER BY total_pending DESC, m.name ASC
        ");
        $stmt->execute($sqlParams2);
        $rekap_pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif ($is_gudang2) {
    // DPB Aktif Gudang (Global)
    $stmt = $db->query("SELECT COUNT(*) FROM dpb_transactions WHERE status = 'aktif' OR status = 'belum_jalan'");
    $stat_dpb_aktif = (int)$stmt->fetchColumn();

    // K3 Aktif Gudang (Global)
    $stmt = $db->query("SELECT COUNT(*) FROM k3_transactions WHERE status = 'aktif' OR status = 'belum_jalan'");
    $stat_k3_aktif = (int)$stmt->fetchColumn();

    // K7 Aktif Gudang (Global)
    $stmt = $db->query("SELECT COUNT(*) FROM k7_transactions WHERE status = 'aktif' OR status = 'belum_jalan'");
    $stat_k7_aktif = (int)$stmt->fetchColumn();
} elseif ($is_vendor) {
    $vendorId = currentVendorId();

    // 1. DPB Aktif Vendor
    $stmt = $db->prepare("SELECT COUNT(*) FROM dpb_transactions WHERE vendor_id = ? AND status IN ('belum_jalan', 'aktif', 'menunggu_persetujuan')");
    $stmt->execute([$vendorId]);
    $stat_dpb_aktif = (int)$stmt->fetchColumn();

    // 2. K3 Aktif Vendor
    $stmt = $db->prepare("SELECT COUNT(*) FROM k3_transactions WHERE vendor_id = ? AND status IN ('belum_jalan', 'aktif')");
    $stmt->execute([$vendorId]);
    $stat_k3_aktif = (int)$stmt->fetchColumn();

    // 3. K7 Aktif Vendor
    $stmt = $db->prepare("SELECT COUNT(*) FROM k7_transactions WHERE vendor_id = ? AND status IN ('belum_jalan', 'aktif')");
    $stmt->execute([$vendorId]);
    $stat_k7_aktif = (int)$stmt->fetchColumn();

    // 4. Material Pending Vendor (jumlah baris material yang pending)
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM dpb_items di 
        JOIN dpb_transactions d ON di.dpb_id = d.id 
        WHERE d.vendor_id = ? 
          AND d.status IN ('aktif', 'belum_jalan') 
          AND di.quantity_requested > di.quantity_received
    ");
    $stmt->execute([$vendorId]);
    $stat_material_pending = (int)$stmt->fetchColumn();

    // 5. Data Chart - Tren Bulanan Vendor
    $currentYearFull = date('Y');
    
    $stmt = $db->prepare("
        SELECT EXTRACT(MONTH FROM tanggal_diminta) AS bln, COUNT(*) AS count 
        FROM dpb_transactions 
        WHERE vendor_id = ? AND EXTRACT(YEAR FROM tanggal_diminta) = ?
        GROUP BY bln ORDER BY bln
    ");
    $stmt->execute([$vendorId, $currentYearFull]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $chart_dpb[(int)$row['bln']] = (int)$row['count'];
    }

    $stmt = $db->prepare("
        SELECT EXTRACT(MONTH FROM tanggal_diminta) AS bln, COUNT(*) AS count 
        FROM k3_transactions 
        WHERE vendor_id = ? AND EXTRACT(YEAR FROM tanggal_diminta) = ?
        GROUP BY bln ORDER BY bln
    ");
    $stmt->execute([$vendorId, $currentYearFull]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $chart_k3[(int)$row['bln']] = (int)$row['count'];
    }

    $stmt = $db->prepare("
        SELECT EXTRACT(MONTH FROM tanggal_diminta) AS bln, COUNT(*) AS count 
        FROM k7_transactions 
        WHERE vendor_id = ? AND EXTRACT(YEAR FROM tanggal_diminta) = ?
        GROUP BY bln ORDER BY bln
    ");
    $stmt->execute([$vendorId, $currentYearFull]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $chart_k7[(int)$row['bln']] = (int)$row['count'];
    }

    // 6. Data Chart - Top 5 Material Terbanyak Diajukan (kuantitas)
    $stmt = $db->prepare("
        SELECT 
            m.name AS material_name,
            SUM(di.quantity_requested) AS total_requested
        FROM dpb_items di
        JOIN dpb_transactions d ON di.dpb_id = d.id
        LEFT JOIN materials m ON di.material_id = m.id
        WHERE d.vendor_id = ?
        GROUP BY m.name
        ORDER BY total_requested DESC, m.name ASC
        LIMIT 5
    ");
    $stmt->execute([$vendorId]);
    $top_requested_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Data Halaman Material Pending (Tabel 1 & Tabel 2)
    if ($page === 'material_pending') {
        $sqlConds = ["d.status IN ('aktif', 'belum_jalan')", "di.quantity_requested > di.quantity_received", "d.vendor_id = ?"];
        $sqlParams = [$vendorId];
        if ($startDate !== '' && $endDate !== '') {
            $sqlConds[] = "d.tanggal_diminta BETWEEN ? AND ?";
            $sqlParams[] = $startDate;
            $sqlParams[] = $endDate;
        }
        if ($q !== '') {
            $sqlConds[] = "(m.name ILIKE ? OR v.name ILIKE ? OR d.customer_name ILIKE ? OR d.tug_number ILIKE ?)";
            $qLike = '%' . $q . '%';
            $sqlParams[] = $qLike;
            $sqlParams[] = $qLike;
            $sqlParams[] = $qLike;
            $sqlParams[] = $qLike;
        }
        $condStr = implode(' AND ', $sqlConds);

        $stmt = $db->prepare("
            SELECT 
                m.name AS material_name,
                (di.quantity_requested - di.quantity_received) AS jumlah_pending,
                v.name AS vendor_name,
                d.customer_name AS customer_name,
                d.tug_number AS tug_number
            FROM dpb_items di
            JOIN dpb_transactions d ON di.dpb_id = d.id
            JOIN vendors v ON d.vendor_id = v.id
            LEFT JOIN materials m ON di.material_id = m.id
            WHERE $condStr
            ORDER BY v.name ASC, m.name ASC
        ");
        $stmt->execute($sqlParams);
        $detail_pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT 
                m.name AS material_name,
                SUM(di.quantity_requested - di.quantity_received) AS total_pending
            FROM dpb_items di
            JOIN dpb_transactions d ON di.dpb_id = d.id
            LEFT JOIN materials m ON di.material_id = m.id
            WHERE $condStr
            GROUP BY m.name
            ORDER BY total_pending DESC, m.name ASC
        ");
        $stmt->execute($sqlParams);
        $rekap_pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if ($page === 'riwayat') {
    if ($is_vendor) {
        $riwayat = getVendorHistory($db, currentVendorId(), $riwayatStart ?: null, $riwayatEnd ?: null);
    } elseif ($is_admin) {
        $riwayat = getAllHistory($db, $riwayatStart ?: null, $riwayatEnd ?: null);
    }
}

    // Data untuk Menu Vendor Admin
    if ($page === 'vendor') {
        // Summary KPI
        $stmt = $db->query("SELECT COUNT(*) FROM vendors");
        $stat_v_total = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM vendors WHERE status = 'aktif'");
        $stat_v_aktif = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM vendors WHERE status = 'nonaktif'");
        $stat_v_nonaktif = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM vendor_applications WHERE status = 'Menunggu Persetujuan'");
        $stat_v_pending = (int)$stmt->fetchColumn();

        // 1. Pengajuan Vendor List
        $stmt = $db->query("
            SELECT * FROM vendor_applications 
            ORDER BY (CASE WHEN status = 'Menunggu Persetujuan' THEN 0 ELSE 1 END), created_at DESC
        ");
        $all_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pagination Pengajuan Vendor
        $appPage = isset($_GET['apage']) ? max(1, (int)$_GET['apage']) : 1;
        $limit = 10;
        $totalAppRows = count($all_applications);
        $totalAppPages = ceil($totalAppRows / $limit);
        $paged_applications = array_slice($all_applications, ($appPage - 1) * $limit, $limit);

        // 2. Daftar Vendor List
        $stmt = $db->query("SELECT * FROM vendors ORDER BY name ASC");
        $all_vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pagination Vendor terdaftar
        $vendorPage = isset($_GET['vpage']) ? max(1, (int)$_GET['vpage']) : 1;
        $totalVendorRows = count($all_vendors);
        $totalVendorPages = ceil($totalVendorRows / $limit);
        $paged_vendors = array_slice($all_vendors, ($vendorPage - 1) * $limit, $limit);
    }
    

    
    // Data untuk Menu Material (Admin & Gudang 2)
    if ($page === 'material') {
        $stat_m_total_jenis = count($materials);
        
        $stmt = $db->query("SELECT SUM(stock) FROM materials");
        $val = $stmt->fetchColumn();
        $stat_m_total_stok = $val !== null ? (int)$val : 0;
        
        $stmt = $db->query("SELECT COUNT(*) FROM materials WHERE stock < 10");
        $stat_m_stok_rendah = (int)$stmt->fetchColumn();
        
        $stmt = $db->query("
            SELECT COUNT(*) FROM dpb_items di 
            JOIN dpb_transactions d ON di.dpb_id = d.id 
            WHERE d.status IN ('aktif', 'belum_jalan') AND di.quantity_requested > di.quantity_received
        ");
        $stat_m_pending = (int)$stmt->fetchColumn();
        
        $stmt = $db->query("
            SELECT 
                m.name AS material_name,
                m.norm AS material_norm,
                (di.quantity_requested - di.quantity_received) AS jumlah_pending,
                v.name AS vendor_name
            FROM dpb_items di
            JOIN dpb_transactions d ON di.dpb_id = d.id
            JOIN vendors v ON d.vendor_id = v.id
            LEFT JOIN materials m ON di.material_id = m.id
            WHERE d.status IN ('aktif', 'belum_jalan')
              AND di.quantity_requested > di.quantity_received
            ORDER BY v.name ASC, m.name ASC
        ");
        $material_pending_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Data untuk Menu Surat Jalan Mandiri (Gudang 2)
    if ($page === 'surat_jalan') {
        if (!$is_admin && !$is_gudang2) {
            $_SESSION['error'] = "Akses ditolak.";
            header("Location: index.php?page=home");
            exit();
        }

        $startDate = $_GET['start'] ?? '';
        $endDate = $_GET['end'] ?? '';
        $q = trim($_GET['q'] ?? '');
        
        $params = [];
        $sql = "SELECT d.*, v.name AS vendor_name 
                FROM dpb_transactions d
                LEFT JOIN vendors v ON d.vendor_id = v.id
                WHERE d.is_manual_sj = TRUE";
                
        if ($startDate !== '') {
            $sql .= " AND d.tanggal_diminta >= ?";
            $params[] = $startDate;
        }
        if ($endDate !== '') {
            $sql .= " AND d.tanggal_diminta <= ?";
            $params[] = $endDate;
        }
        if ($q !== '') {
            $sql .= " AND (LOWER(d.tug_number) LIKE ? OR LOWER(v.name) LIKE ? OR LOWER(d.surat_jalan_number) LIKE ? OR LOWER(d.customer_name) LIKE ?)";
            $qTerm = '%' . strtolower($q) . '%';
            $params[] = $qTerm;
            $params[] = $qTerm;
            $params[] = $qTerm;
            $params[] = $qTerm;
        }
        
        $sql .= " ORDER BY d.id DESC";
        
        // Count total rows for pagination
        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") AS count_table";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $totalRows = (int)$stmt->fetchColumn();
        
        $limit = 10;
        $apage = isset($_GET['apage']) ? max(1, (int)$_GET['apage']) : 1;
        $totalPages = ceil($totalRows / $limit);
        $offset = ($apage - 1) * $limit;
        
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($sql);
        $paramIdx = 1;
        foreach ($params as $paramVal) {
            if (is_int($paramVal)) {
                $stmt->bindValue($paramIdx++, $paramVal, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($paramIdx++, $paramVal, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        $manual_sj_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<body<?= ($is_admin || $is_vendor || $is_gudang2) ? ' class="adm-body"' : '' ?>>

<?php if ($is_admin || $is_vendor || $is_gudang2): ?>
<!-- =====================================================
     VOLTA SYSTEM LAYOUT — Sidebar + Topbar
     ===================================================== -->
<div class="adm-layout" id="admLayout">

    <!-- ---- SIDEBAR ---- -->
    <aside class="adm-sidebar" id="admSidebar">

        <!-- Brand -->
        <a href="?page=home" class="adm-brand">
            <img src="images/logo.png" alt="PLN Logo">
            <span class="adm-brand-text">VOLTA</span>
        </a>

        <!-- Unit Info (non-clickable) -->
        <div class="adm-unit-info">
            <p class="adm-unit-name" style="white-space: normal; font-size: 13px; line-height: 1.3;">Verifikasi & Operasional Logistik TerpAdu</p>
            <p class="adm-unit-sub" style="font-size: 11px;">PLN UP3 Malang</p>
        </div>

<!-- MODAL IMPORT MATERIAL -->
<div id="materialImportModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeImportModal()">&times;</span>
        <h2 style="color:#0b2b4a;"><i class="fas fa-file-upload"></i> Import Data Material (CSV)</h2>
        
        <div id="importUploadArea" style="border: 2px dashed #cbd5e1; border-radius: 16px; padding: 2.5rem 1.5rem; text-align: center; background: #f8fafc; cursor: pointer; margin-top: 1.5rem; transition: border-color 0.2s;" onclick="triggerImportFileSelect()">
            <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: #64748b; margin-bottom: 1rem; display: block;"></i>
            <span style="font-size: 1rem; font-weight: 600; color: #334155; display: block; margin-bottom: 0.25rem;">Pilih berkas Excel / CSV untuk diunggah</span>
            <span style="font-size: 0.85rem; color: #64748b;">Maksimal ukuran file 5MB. Format .xlsx, .xls, atau .csv</span>
            <input type="file" id="importFileInput" accept=".csv, .xlsx, .xls" style="display: none;" onchange="handleImportFileSelect(event)">
        </div>

        <!-- Preview & Status Block -->
        <div id="importPreviewBlock" style="display: none; margin-top: 1.5rem;">
            <div id="importStatusAlert" class="alert-success" style="margin-bottom: 1rem; display: none;"></div>
            <div id="importErrorList" class="alert-danger" style="margin-bottom: 1rem; max-height: 150px; overflow-y: auto; font-size: 0.85rem; display: none; text-align: left; padding: 1rem; border-radius: 12px;"></div>
            
            <div id="importTableContainer" style="display: none;">
                <h4 style="color:#0b2b4a; margin-top: 0; margin-bottom: 0.5rem;"><i class="fas fa-eye"></i> Preview 5 Data Pertama</h4>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama Material</th>
                                <th>Normalisasi</th>
                                <th>Satuan</th>
                                <th>Jumlah</th>
                            </tr>
                        </thead>
                        <tbody id="importPreviewTbody"></tbody>
                    </table>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" id="btnConfirmImport" class="btn-success" onclick="confirmImportData()" disabled>Konfirmasi Import</button>
                <button type="button" class="btn-info" onclick="resetImportModal()">Pilih File Lain</button>
            </div>
        </div>
    </div>
</div>

        <!-- Navigation -->
        <nav class="adm-nav">
            <a href="?page=home"  class="adm-nav-item <?= $page === 'home'    ? 'active' : '' ?>">
                <i class="fas fa-home"></i> <span>Dashboard</span>
            </a>
            <?php if (!$is_gudang2): ?>
            <a href="?page=vendor" class="adm-nav-item <?= $page === 'vendor'  ? 'active' : '' ?>">
                <i class="fas fa-truck"></i> <span>Vendor</span>
            </a>
            <?php endif; ?>
            <?php if ($is_admin || $is_gudang2): ?>
            <a href="?page=material" class="adm-nav-item <?= $page === 'material' ? 'active' : '' ?>">
                <i class="fas fa-boxes"></i> <span>Material</span>
            </a>
            <?php endif; ?>
            <a href="?page=dpb" class="adm-nav-item <?= $page === 'dpb'     ? 'active' : '' ?>">
                <i class="fas fa-clipboard-list"></i> <span>DPB</span>
            </a>
            <a href="?page=k3" class="adm-nav-item <?= $page === 'k3'      ? 'active' : '' ?>">
                <i class="fas fa-undo"></i> <span>K3</span>
            </a>
            <a href="?page=k7" class="adm-nav-item <?= $page === 'k7'      ? 'active' : '' ?>">
                <i class="fas fa-recycle"></i> <span>K7</span>
            </a>
            <?php if ($is_admin || $is_gudang2): ?>
            <a href="?page=surat_jalan" class="adm-nav-item <?= $page === 'surat_jalan' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice"></i> <span>Surat Jalan</span>
            </a>
            <?php endif; ?>
            <?php if ($is_vendor): ?>
            <a href="#" onclick="showFaq()" class="adm-nav-item">
                <i class="fas fa-question-circle"></i> <span>FAQ</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- Footer (Logout Only) -->
        <div class="adm-sidebar-footer" style="margin-top: auto;">
            <!-- Keluar -->
            <div class="adm-logout-wrap">
                <a href="logout.php" class="adm-logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Keluar</span>
                </a>
            </div>
        </div>

    </aside>
    <!-- ---- END SIDEBAR ---- -->

    <!-- ---- MAIN AREA ---- -->
    <div class="adm-main" id="admMain">

        <!-- Top Header Bar -->
        <header class="adm-topbar" id="admTopbar">
            <div class="adm-topbar-left">
                <button class="adm-sidebar-toggle" id="admSidebarToggle" onclick="admToggleSidebar()" title="Toggle Sidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div class="adm-topbar-right">
                <button class="adm-notif-btn" title="Notifikasi">
                    <i class="fas fa-bell"></i>
                    <span class="adm-notif-badge">!</span>
                </button>
                <div class="adm-topbar-user">
                    <span class="adm-topbar-username"><?= htmlspecialchars($_SESSION['user_name'] ?? 'VOLTA User') ?></span>
                    <span class="role-badge role-<?= $_SESSION['user_role'] ?? 'vendor' ?>"><?= $is_admin ? 'Admin' : ($is_gudang2 ? 'Gudang' : 'Vendor') ?></span>
                    <div class="adm-topbar-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="adm-content">
<?php else: ?>
<!-- =====================================================
     VENDOR / GUEST LAYOUT — Header horizontal lama
     ===================================================== -->
<header class="site-header">
    <div class="logo-area">
        <img src="images/logo.png" alt="PLN Logo">
        <span>VOLTA</span>
    </div>

    <div class="nav-links">
        <a href="?page=home" class="<?= $page === 'home' ? 'active' : '' ?>"><i class="fas fa-home"></i> Home</a>
        <a href="?page=vendor" class="<?= $page === 'vendor' ? 'active' : '' ?>"><i class="fas fa-truck"></i> Vendor</a>
        <a href="?page=dpb" class="<?= $page === 'dpb' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> DPB</a>
        <a href="?page=k3" class="<?= $page === 'k3' ? 'active' : '' ?>"><i class="fas fa-undo"></i> K3</a>
        <a href="?page=k7" class="<?= $page === 'k7' ? 'active' : '' ?>"><i class="fas fa-recycle"></i> K7</a>
        <?php if ($is_vendor || $is_logged_in): ?>
        <a href="?page=riwayat" class="<?= $page === 'riwayat' ? 'active' : '' ?>"><i class="fas fa-history"></i> Riwayat</a>
        <?php endif; ?>
        <a href="#" onclick="showFaq()"><i class="fas fa-question-circle"></i> FAQ</a>
    </div>

    <div class="auth-bar">
        <?php if ($is_logged_in): ?>
            <span class="user-info">
                <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                <span class="role-badge role-<?= $_SESSION['user_role'] ?>">Vendor</span>
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
        <span class="hero-tag"><i class="fas fa-bolt"></i> PT PLN (persero) - UP3 Malang</span>
        <h1>Distribusi Material</h1>
        <p>Kelola stok material, data vendor, dan monitoring DPB dalam satu sistem terintegrasi.</p>
    </div>
</section>
<?php endif; ?>

<div class="app-wrapper">
    <?php if ($is_admin || $is_vendor || $is_gudang2): ?>
    <section class="hero-banner">
        <img src="images/hero.png" alt="Gudang &amp; Distribusi Material PLN">
        <div class="hero-overlay">
            <span class="hero-tag"><i class="fas fa-bolt"></i> PT PLN (persero) - UP3 Malang</span>
            <h1>Distribusi Material</h1>
            <?php if ($is_admin): ?>
            <p>Kelola stok material, data vendor, dan monitoring DPB dalam satu sistem terintegrasi.</p>
            <?php elseif ($is_gudang2): ?>
            <p>Cari nomor TUG, lihat status, dan cetak dokumen DPB / K3 / K7 untuk operasional gudang.</p>
            <?php else: ?>
            <p>Ajukan permintaan material, cetak DPB, dan pantau status pengajuan Anda di sini.</p>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php if (!$openModal && isset($_SESSION['success'])): ?>
        <div class="alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (!$openModal && isset($_SESSION['error'])): ?>
        <div class="alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div id="dynamicContent">
        <?php if ($page === 'home'): ?>
            <div id="homeSection">
                <?php if ($is_admin || $is_vendor || $is_gudang2): ?>
                <div class="adm-kpi-grid">
                    <!-- DPB Aktif -->
                    <div class="adm-kpi-card clickable-kpi-card" onclick="location.href='?page=dpb'" style="cursor: pointer;">
                        <div class="adm-kpi-content">
                            <span class="adm-kpi-number"><?= number_format($stat_dpb_aktif, 0, ',', '.') ?></span>
                            <span class="adm-kpi-label">DPB Aktif</span>
                        </div>
                        <div class="adm-kpi-icon-wrap" style="background-color: #e3f7ec; color: #1e8e5a;">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                    <!-- K3 Aktif -->
                    <div class="adm-kpi-card clickable-kpi-card" onclick="location.href='?page=k3'" style="cursor: pointer;">
                        <div class="adm-kpi-content">
                            <span class="adm-kpi-number"><?= number_format($stat_k3_aktif, 0, ',', '.') ?></span>
                            <span class="adm-kpi-label">K3 Aktif</span>
                        </div>
                        <div class="adm-kpi-icon-wrap" style="background-color: #fff6dd; color: #b78a00;">
                            <i class="fas fa-undo"></i>
                        </div>
                    </div>
                    <!-- K7 Aktif -->
                    <div class="adm-kpi-card clickable-kpi-card" onclick="location.href='?page=k7'" style="cursor: pointer;">
                        <div class="adm-kpi-content">
                            <span class="adm-kpi-number"><?= number_format($stat_k7_aktif, 0, ',', '.') ?></span>
                            <span class="adm-kpi-label">K7 Aktif</span>
                        </div>
                        <div class="adm-kpi-icon-wrap" style="background-color: #f7e6ff; color: #8e1eff;">
                            <i class="fas fa-recycle"></i>
                        </div>
                    </div>
                    <!-- Material Pending (Admin/Vendor Only) -->
                    <?php if (!$is_gudang2): ?>
                    <div class="adm-kpi-card clickable-kpi-card" onclick="location.href='?page=material_pending'" style="cursor: pointer;">
                        <div class="adm-kpi-content">
                            <span class="adm-kpi-number"><?= number_format($stat_material_pending, 0, ',', '.') ?></span>
                            <span class="adm-kpi-label">Material Pending</span>
                        </div>
                        <div class="adm-kpi-icon-wrap" style="background-color: #e6f7f8; color: #14828a;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$is_gudang2): ?>
                <!-- Grafik Dashboard -->
                <div class="adm-chart-row" style="display: flex; gap: 24px; margin-top: 32px; flex-wrap: wrap;">
                    <!-- Grafik 1: Tren Aktivitas Bulanan -->
                    <div class="adm-chart-card" style="flex: 1.5; min-width: 320px; background: white; padding: 24px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05);">
                        <h4 style="margin: 0 0 16px 0; color: #0b2b4a; font-size: 16px; font-weight: 600;"><i class="fas fa-chart-line" style="color: #14828a; margin-right: 8px;"></i> Tren Aktivitas Transaksi Bulanan (<?= date('Y') ?>)</h4>
                        <div style="position: relative; height: 320px;">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>
                    <!-- Grafik 2: Top 5 Material Pending (Admin) atau Terbanyak Diajukan (Vendor) -->
                    <div class="adm-chart-card" style="flex: 1; min-width: 320px; background: white; padding: 24px; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.05);">
                        <?php if ($is_admin): ?>
                            <h4 style="margin: 0 0 16px 0; color: #0b2b4a; font-size: 16px; font-weight: 600;"><i class="fas fa-chart-bar" style="color: #b78a00; margin-right: 8px;"></i> Top 5 Material Pending Terbanyak</h4>
                            <div style="position: relative; height: 320px;">
                                <canvas id="topPendingChart"></canvas>
                            </div>
                        <?php else: ?>
                            <h4 style="margin: 0 0 16px 0; color: #0b2b4a; font-size: 16px; font-weight: 600;"><i class="fas fa-chart-bar" style="color: #1e8e5a; margin-right: 8px;"></i> Top 5 Material Terbanyak Diajukan (Kuantitas)</h4>
                            <div style="position: relative; height: 320px;">
                                <canvas id="requestedMaterialsChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php elseif ($page === 'vendor'): ?>
            <div id="vendorSection">
                <?php if ($is_admin): ?>
                    <!-- Summary / KPI Cards -->
                    <div class="adm-kpi-grid" style="margin-bottom: 32px;">
                        <div class="adm-kpi-card">
                            <div class="adm-kpi-content">
                                <span class="adm-kpi-number"><?= number_format($stat_v_total) ?></span>
                                <span class="adm-kpi-label">Total Vendor</span>
                            </div>
                            <div class="adm-kpi-icon-wrap" style="background-color: #e3f7ec; color: #1e8e5a;">
                                <i class="fas fa-building"></i>
                            </div>
                        </div>
                        <div class="adm-kpi-card">
                            <div class="adm-kpi-content">
                                <span class="adm-kpi-number"><?= number_format($stat_v_aktif) ?></span>
                                <span class="adm-kpi-label">Vendor Aktif</span>
                            </div>
                            <div class="adm-kpi-icon-wrap" style="background-color: #e6f7f8; color: #14828a;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="adm-kpi-card">
                            <div class="adm-kpi-content">
                                <span class="adm-kpi-number"><?= number_format($stat_v_nonaktif) ?></span>
                                <span class="adm-kpi-label">Vendor Nonaktif</span>
                            </div>
                            <div class="adm-kpi-icon-wrap" style="background-color: #fff0f0; color: #e11d48;">
                                <i class="fas fa-ban"></i>
                            </div>
                        </div>
                        <div class="adm-kpi-card">
                            <div class="adm-kpi-content">
                                <span class="adm-kpi-number"><?= number_format($stat_v_pending) ?></span>
                                <span class="adm-kpi-label">Pengajuan Pending</span>
                            </div>
                            <div class="adm-kpi-icon-wrap" style="background-color: #fff6dd; color: #b78a00;">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 1: PENGAJUAN VENDOR -->
                    <div class="card" style="margin-bottom: 32px;">
                        <h3 style="color:#0b2b4a; margin-top:0; margin-bottom:1.5rem;"><i class="fas fa-clipboard-list" style="color: #b78a00; margin-right: 8px;"></i> Pengajuan Registrasi Vendor</h3>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nama PT</th>
                                        <th>Email</th>
                                        <th>Telepon</th>
                                        <th>Tanggal Pengajuan</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($paged_applications)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center; color:#777; padding:2rem;">Tidak ada pengajuan vendor saat ini.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($paged_applications as $idx => $app): ?>
                                            <tr>
                                                <td><?= (($appPage - 1) * 10) + $idx + 1 ?></td>
                                                <td><strong><?= htmlspecialchars($app['name']) ?></strong></td>
                                                <td><?= htmlspecialchars($app['email']) ?></td>
                                                <td><?= htmlspecialchars($app['phone'] ?: '-') ?></td>
                                                <td><?= date('d M Y H:i', strtotime($app['created_at'])) ?></td>
                                                <td>
                                                    <?php if ($app['status'] === 'Menunggu Persetujuan'): ?>
                                                        <span class="badge" style="background-color: #fff6dd; color: #b78a00; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-clock"></i> Menunggu Persetujuan</span>
                                                    <?php elseif ($app['status'] === 'Disetujui'): ?>
                                                        <span class="badge" style="background-color: #e3f7ec; color: #1e8e5a; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-check-circle"></i> Disetujui</span>
                                                    <?php else: ?>
                                                        <span class="badge" style="background-color: #fff0f0; color: #e11d48; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-times-circle"></i> Ditolak</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 8px; align-items: center;">
                                                        <button type="button" class="btn-info" onclick="showAppDetail(<?= htmlspecialchars(json_encode($app)) ?>)" style="padding: 0.3rem 0.8rem; font-size: 0.75rem; border-radius: 20px;">Detail</button>
                                                        <?php if ($app['status'] === 'Menunggu Persetujuan'): ?>
                                                            <a href="vendor.php?approve_app=<?= $app['id'] ?>" class="btn-success" onclick="return confirm('Setujui pengajuan vendor PT <?= htmlspecialchars(addslashes($app['name'])) ?>?')" style="padding: 0.3rem 0.8rem; font-size: 0.75rem; border-radius: 20px; text-decoration: none;">Setujui</a>
                                                            <a href="vendor.php?reject_app=<?= $app['id'] ?>" class="btn-danger" onclick="return confirm('Tolak pengajuan vendor PT <?= htmlspecialchars(addslashes($app['name'])) ?>?')" style="padding: 0.3rem 0.8rem; font-size: 0.75rem; border-radius: 20px; text-decoration: none;">Tolak</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($totalAppPages > 1): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1rem; flex-wrap: wrap; gap: 12px;">
                                <span style="font-size: 0.85rem; color: #555;">Menampilkan <?= (($appPage - 1) * 10) + 1 ?>–<?= min($appPage * 10, $totalAppRows) ?> dari <?= $totalAppRows ?> Pengajuan</span>
                                <?= renderPhpPagination($appPage, $totalAppPages, 'apage') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- SECTION 2: DAFTAR VENDOR -->
                <div class="card" style="margin-bottom: 32px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 12px;">
                        <h3 style="color:#0b2b4a; margin: 0;"><i class="fas fa-building" style="color: #14828a; margin-right: 8px;"></i> Daftar Vendor Terdaftar</h3>
                        <?php if ($is_admin): ?>
                            <div style="display: flex; gap: 8px;">
                                <a href="vendor.php?action=export" class="btn-warning" style="text-decoration:none; display:inline-block;"><i class="fas fa-file-csv"></i> Ekspor Vendor</a>
                                <button type="button" class="btn-success" onclick="toggleAddVendorForm()"><i class="fas fa-plus"></i> Tambah Vendor Baru</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_admin): ?>
                    <!-- Form Tambah Vendor (Hidden by default, toggled via JS) -->
                    <div id="addVendorFormBlock" class="card" style="display: none; border: 1px solid #dbe4ec; margin-bottom: 24px; background: #f8fafc; padding: 20px; border-radius: 16px;">
                        <h4 style="color:#0b2b4a; margin-top:0; margin-bottom:1rem;"><i class="fas fa-plus-circle"></i> Form Tambah Vendor Baru</h4>
                        <form method="POST" action="vendor.php">
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
                                    <label>Email (digunakan untuk login)</label>
                                    <input type="email" name="vendor_email" placeholder="email@company.com" required>
                                </div>
                                <div class="form-group">
                                    <label>Password (maks. 7 digit)</label>
                                    <input type="password" name="vendor_password" placeholder="******" maxlength="7" required>
                                </div>
                            </div>
                            <div style="margin-top:1.2rem; display: flex; gap: 8px;">
                                <button type="submit" name="add_vendor" class="btn-success">Simpan &amp; Aktifkan</button>
                                <button type="button" class="btn-info" onclick="toggleAddVendorForm()">Batal</button>
                            </div>
                        </form>
                    </div>
                    <?php elseif ($is_vendor): ?>
                        <div class="alert-success" style="padding:1rem; border-radius:10px; margin-bottom:1rem;">
                            <i class="fas fa-check-circle"></i> Anda terdaftar sebagai <strong><?= htmlspecialchars($my_vendor['name'] ?? '-') ?></strong>. Untuk mengubah data vendor, hubungi admin gudang PLN.
                        </div>
                    <?php endif; ?>

                    <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
                        <div class="form-group" style="min-width:260px; margin-bottom:0;">
                            <input type="text" id="vendorSearchInput" placeholder="Cari Vendor..." oninput="filterVendorTableLocal()" style="padding: 0.5rem 1rem; border-radius: 20px; border: 1px solid #ccc; font-size: 13px; width: 100%;">
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table id="vendorTable">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">No</th>
                                    <th>Nama PT</th>
                                    <th>Alamat</th>
                                    <th>Telepon</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <?php if ($is_admin): ?><th>Aksi</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $vendors_list = $is_admin ? $paged_vendors : $vendors;
                                ?>
                                <?php if (empty($vendors_list)): ?>
                                    <tr>
                                        <td colspan="7" style="text-align:center; color:#777; padding:2rem;">Tidak ada vendor terdaftar saat ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($vendors_list as $i => $v): ?>
                                    <tr data-search="<?= htmlspecialchars(mb_strtolower($v['name'] . ' ' . ($v['address'] ?? ''))) ?>">
                                        <td><?= $is_admin ? (($vendorPage - 1) * 10) + $i + 1 : $i + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
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
                                        <td>
                                            <?php if (($v['status'] ?? 'aktif') === 'aktif'): ?>
                                                <span class="badge" style="background-color: #e3f7ec; color: #1e8e5a; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-check-circle"></i> Aktif</span>
                                            <?php else: ?>
                                                <span class="badge" style="background-color: #fff0f0; color: #e11d48; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-ban"></i> Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($is_admin): ?>
                                        <td>
                                            <div style="display:flex; gap:6px; align-items:center;">
                                                <button type="button" class="btn-info" onclick="showVendorDetail(<?= htmlspecialchars(json_encode($v)) ?>)" style="padding:0.25rem 0.75rem; border-radius:20px; font-size:0.75rem;">Detail</button>
                                                <button type="button" class="btn-warning" onclick="showVendorEdit(<?= htmlspecialchars(json_encode($v)) ?>)" style="padding:0.25rem 0.75rem; border-radius:20px; font-size:0.75rem; color:#fff;">Edit</button>
                                                <?php if (($v['status'] ?? 'aktif') === 'aktif'): ?>
                                                    <a href="vendor.php?deactivate=<?= $v['id'] ?>" class="btn-danger" onclick="return confirm('Nonaktifkan vendor <?= htmlspecialchars(addslashes($v['name'])) ?>?')" style="padding:0.25rem 0.75rem; border-radius:20px; text-decoration:none; font-size:0.75rem;">Nonaktifkan</a>
                                                <?php else: ?>
                                                    <a href="vendor.php?activate=<?= $v['id'] ?>" class="btn-success" onclick="return confirm('Aktifkan kembali vendor <?= htmlspecialchars(addslashes($v['name'])) ?>?')" style="padding:0.25rem 0.75rem; border-radius:20px; text-decoration:none; font-size:0.75rem;">Aktifkan</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($is_admin && $totalVendorPages > 1): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1rem; flex-wrap: wrap; gap: 12px;">
                            <span style="font-size: 0.85rem; color: #555;">Menampilkan <?= (($vendorPage - 1) * 10) + 1 ?>–<?= min($vendorPage * 10, $totalVendorRows) ?> dari <?= $totalVendorRows ?> Vendor</span>
                            <?= renderPhpPagination($vendorPage, $totalVendorPages, 'vpage') ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            function toggleAddVendorForm() {
                var block = document.getElementById('addVendorFormBlock');
                if (block) {
                    block.style.display = (block.style.display === 'none') ? 'block' : 'none';
                }
            }

            function showAppDetail(app) {
                document.getElementById('appDetName').textContent = app.name;
                document.getElementById('appDetAddress').textContent = app.address || '-';
                document.getElementById('appDetPhone').textContent = app.phone || '-';
                document.getElementById('appDetEmail').textContent = app.email;
                document.getElementById('appDetDate').textContent = new Date(app.created_at).toLocaleString('id-ID', {day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'});
                
                var statusSpan = '';
                if (app.status === 'Menunggu Persetujuan') {
                    statusSpan = '<span class="badge" style="background-color: #fff6dd; color: #b78a00; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-clock"></i> Menunggu Persetujuan</span>';
                } else if (app.status === 'Disetujui') {
                    statusSpan = '<span class="badge" style="background-color: #e3f7ec; color: #1e8e5a; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-check-circle"></i> Disetujui</span>';
                } else {
                    statusSpan = '<span class="badge" style="background-color: #fff0f0; color: #e11d48; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-times-circle"></i> Ditolak</span>';
                }
                document.getElementById('appDetStatus').innerHTML = statusSpan;
                
                var actionBox = '';
                if (app.status === 'Menunggu Persetujuan') {
                    actionBox = '<a href="vendor.php?approve_app=' + app.id + '" class="btn-success" style="padding: 0.4rem 1rem; border-radius: 20px; text-decoration: none; font-size: 0.85rem;" onclick="return confirm(\'Setujui pengajuan vendor PT ' + app.name.replace(/'/g, "\\'") + '?\')">Setujui</a>' +
                                '<a href="vendor.php?reject_app=' + app.id + '" class="btn-danger" style="padding: 0.4rem 1rem; border-radius: 20px; text-decoration: none; font-size: 0.85rem;" onclick="return confirm(\'Tolak pengajuan vendor PT ' + app.name.replace(/'/g, "\\'") + '?\')">Tolak</a>';
                }
                actionBox += '<button type="button" class="btn-info" onclick="closeAppDetailModal()" style="padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.85rem;">Tutup</button>';
                document.getElementById('appDetActions').innerHTML = actionBox;
                
                document.getElementById('appDetailModal').classList.add('show');
            }
            function closeAppDetailModal() {
                document.getElementById('appDetailModal').classList.remove('show');
            }

            function showVendorDetail(vendor) {
                document.getElementById('vDetName').textContent = vendor.name;
                document.getElementById('vDetAddress').textContent = vendor.address || '-';
                document.getElementById('vDetPhone').textContent = vendor.phone || '-';
                document.getElementById('vDetEmail').textContent = vendor.email || '-';
                
                var statusSpan = '';
                if ((vendor.status || 'aktif') === 'aktif') {
                    statusSpan = '<span class="badge" style="background-color: #e3f7ec; color: #1e8e5a; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-check-circle"></i> Aktif</span>';
                } else {
                    statusSpan = '<span class="badge" style="background-color: #fff0f0; color: #e11d48; font-weight: 600; padding: 4px 8px; border-radius: 8px; font-size: 12px;"><i class="fas fa-ban"></i> Nonaktif</span>';
                }
                document.getElementById('vDetStatus').innerHTML = statusSpan;
                
                document.getElementById('vendorDetailModal').classList.add('show');
            }
            function closeVendorDetailModal() {
                document.getElementById('vendorDetailModal').classList.remove('show');
            }

            function showVendorEdit(vendor) {
                document.getElementById('editVendorId').value = vendor.id;
                document.getElementById('editVendorName').value = vendor.name;
                document.getElementById('editVendorAddress').value = vendor.address || '';
                document.getElementById('editVendorPhone').value = vendor.phone || '';
                document.getElementById('editVendorEmail').value = vendor.email || '';
                
                document.getElementById('vendorEditModal').classList.add('show');
            }
            function closeVendorEditModal() {
                document.getElementById('vendorEditModal').classList.remove('show');
            }

            function filterVendorTableLocal() {
                var input = document.getElementById("vendorSearchInput").value.toLowerCase();
                var rows = document.querySelectorAll("#vendorTable tbody tr");
                rows.forEach(function(row) {
                    var searchData = row.getAttribute("data-search");
                    if (searchData) {
                        if (searchData.indexOf(input) > -1) {
                            row.style.display = "";
                        } else {
                            row.style.display = "none";
                        }
                    }
                });
            }
            </script>
            </div>
        <?php elseif ($page === 'material'): ?>
            <div id="materialSection">
                <?php if ($is_vendor): ?>
                <div class="alert-danger" style="padding:1rem; border-radius:10px;">
                    <i class="fas fa-lock"></i> Menu Material hanya dapat diakses oleh admin / petugas gudang PLN. Untuk mengajukan permintaan material, gunakan menu <a href="?page=dpb">DPB</a>.
                </div>
                <?php else: ?>
                
                <!-- KPI Grid -->
                <div class="adm-kpi-grid" style="margin-bottom: 24px;">
                    <!-- Total Jenis -->
                    <div class="adm-kpi-card">
                        <div class="adm-kpi-content">
                            <span class="adm-kpi-number"><?= number_format($stat_m_total_jenis, 0, ',', '.') ?></span>
                            <span class="adm-kpi-label">Total Jenis Material</span>
                        </div>
                        <div class="adm-kpi-icon-wrap" style="background-color: #e0f2fe; color: #0284c7;">
                            <i class="fas fa-cubes"></i>
                        </div>
                    </div>
                    <!-- Total Stok -->
                    <div class="adm-kpi-card">
                        <div class="adm-kpi-content">
                            <span class="adm-kpi-number"><?= number_format($stat_m_total_stok, 0, ',', '.') ?></span>
                            <span class="adm-kpi-label">Total Stok Fisik</span>
                        </div>
                        <div class="adm-kpi-icon-wrap" style="background-color: #e2fbf0; color: #10b981;">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                    <!-- Stok Rendah -->
                    <div class="adm-kpi-card">
                        <div class="adm-kpi-content">
                            <span class="adm-kpi-number"><?= number_format($stat_m_stok_rendah, 0, ',', '.') ?></span>
                            <span class="adm-kpi-label">Stok Rendah (< 10)</span>
                        </div>
                        <div class="adm-kpi-icon-wrap" style="background-color: #ffebee; color: #ef4444;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <!-- Material Pending -->
                    <div class="adm-kpi-card">
                        <div class="adm-kpi-content">
                            <span class="adm-kpi-number"><?= number_format($stat_m_pending, 0, ',', '.') ?></span>
                            <span class="adm-kpi-label">Material Pending (DPB)</span>
                        </div>
                        <div class="adm-kpi-icon-wrap" style="background-color: #fffdeb; color: #f59e0b;">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <!-- 1. FORM INPUT MATERIAL (Hanya Admin) -->
                <?php if ($is_admin): ?>
                <div style="margin-bottom: 1.5rem;">
                    <button type="button" class="btn-success" id="btnToggleInputMaterial" onclick="toggleInputMaterialForm()"><i class="fas fa-plus"></i> Input Material</button>
                </div>

                <div class="card" id="inputMaterialFormWrap" style="display: none; margin-bottom: 24px;">
                    <h3><i class="fas fa-cube"></i> Input Material Baru</h3>
                    <form method="POST" action="material.php" style="margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label>Nama Material</label>
                            <input type="text" id="materialNameInput" name="material_name" placeholder="contoh: DUDUKAN TRAFO 2 TIANG" required>
                        </div>
                        <div class="form-group">
                            <label>Normalisasi</label>
                            <input type="text" id="materialNormInput" name="material_norm" placeholder="Input Normalisasi">
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
                            <label>Nama Pabrikan</label>
                            <input type="text" name="pabrikan" placeholder="contoh: PT. Tembaga Jaya">
                        </div>
                        <div class="form-group">
                            <label>Nomor Kontrak</label>
                            <input type="text" name="nomor_kontrak" placeholder="contoh: 001/SPK/PLN/2026">
                        </div>
                        <div class="form-group">
                            <label>Tanggal Datang</label>
                            <input type="date" name="tanggal_datang" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>Jumlah Material</label>
                            <input type="number" name="material_stock" min="0" step="1" placeholder="0" value="0" required>
                        </div>
                        <button type="submit" name="add_material" class="btn-success">Simpan Material</button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- 2. STOK MATERIAL SECTION -->
                <div class="card" style="margin-bottom: 24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:1rem; border-bottom:1px solid #f4f7f9; padding-bottom:12px;">
                        <h3 style="margin:0; border:none; padding:0;"><i class="fas fa-boxes" style="color: var(--blue);"></i> Stok Material</h3>
                        <div style="display:flex; gap:0.8rem; align-items:center; flex-wrap:wrap;">
                            <?php if ($is_admin): ?>
                            <button type="button" class="btn-success" onclick="openImportModal()"><i class="fas fa-file-upload"></i> Import Material</button>
                            <a href="material.php?action=template" class="btn-warning" style="text-decoration:none; display:inline-block;"><i class="fas fa-download"></i> Download Template</a>
                            <a href="material.php?action=export" class="btn-warning" style="text-decoration:none; display:inline-block;"><i class="fas fa-file-csv"></i> Export Material</a>
                            <?php endif; ?>
                            <div class="form-group" id="materialSearchWrap" style="min-width:240px; margin-bottom:0;">
                                <input type="text" id="materialSearchInput" placeholder="Cari Stok Material..." oninput="filterMaterialTable()" style="padding: 0.5rem 1rem; border-radius: 20px; border: 1px solid #ccc; font-size: 13px; width: 100%;">
                            </div>
                        </div>
                    </div>
                    <div id="materialListContainer">
                        <p class="text-small">Memuat data...</p>
                    </div>
                </div>

                <!-- 3. MATERIAL MASUK SECTION -->
                <div class="card" style="margin-bottom: 24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:1rem; border-bottom:1px solid #f4f7f9; padding-bottom:12px;">
                        <h3 style="margin:0; border:none; padding:0;"><i class="fas fa-download" style="color: var(--blue);"></i> Material Masuk</h3>
                        <button type="button" onclick="exportIncoming()" class="btn-success" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 13px; display:inline-flex; align-items:center; gap:8px; border:none; cursor:pointer;"><i class="fas fa-file-excel"></i> Export Excel</button>
                    </div>
                    <!-- Filter Row -->
                    <div class="g2-search-filter-card">
                        <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                            <div class="form-group" style="flex: 2; min-width: 240px; margin-bottom: 0;">
                                <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Cari Material Masuk</label>
                                <input type="text" id="incomingSearchInput" oninput="filterIncomingTable()" placeholder="Cari material masuk..." style="padding: 0.65rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Dari Tanggal</label>
                                <input type="date" id="incomingStartInput" onchange="filterIncomingTable()" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Sampai Tanggal</label>
                                <input type="date" id="incomingEndInput" onchange="filterIncomingTable()" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" onclick="filterIncomingTable()" class="btn-success" style="padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;"><i class="fas fa-search"></i> Cari</button>
                            </div>
                        </div>
                    </div>
                    <div id="incomingListContainer">
                        <p class="text-small">Memuat data...</p>
                    </div>
                </div>

                <!-- 4. MATERIAL KELUAR SECTION -->
                <div class="card" style="margin-bottom: 24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:1rem; border-bottom:1px solid #f4f7f9; padding-bottom:12px;">
                        <h3 style="margin:0; border:none; padding:0;"><i class="fas fa-upload" style="color: var(--blue);"></i> Material Keluar</h3>
                        <button type="button" onclick="exportOutgoing()" class="btn-success" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 13px; display:inline-flex; align-items:center; gap:8px; border:none; cursor:pointer;"><i class="fas fa-file-excel"></i> Export Excel</button>
                    </div>
                    <!-- Filter Row -->
                    <div class="g2-search-filter-card">
                        <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                            <div class="form-group" style="flex: 2; min-width: 240px; margin-bottom: 0;">
                                <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Cari Material Keluar</label>
                                <input type="text" id="outgoingSearchInput" oninput="filterOutgoingTable()" placeholder="Cari material keluar..." style="padding: 0.65rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Dari Tanggal</label>
                                <input type="date" id="outgoingStartInput" onchange="filterOutgoingTable()" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Sampai Tanggal</label>
                                <input type="date" id="outgoingEndInput" onchange="filterOutgoingTable()" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" onclick="filterOutgoingTable()" class="btn-success" style="padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;"><i class="fas fa-search"></i> Cari</button>
                            </div>
                        </div>
                    </div>
                    <div id="outgoingListContainer">
                        <p class="text-small">Memuat data...</p>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        <?php elseif ($page === 'surat_jalan'): ?>
            <div id="suratJalanSection">
                <?php if (($_GET['action'] ?? '') === 'create'): ?>
                    <!-- FORM BUAT SURAT JALAN MANDIRI BARU -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 1.5rem; border-bottom: 1px solid #eee; padding-bottom: 1rem;">
                            <div>
                                <h3 style="color:#0b2b4a; margin: 0 0 4px 0;"><i class="fas fa-file-invoice" style="color: var(--blue); margin-right: 8px;"></i> Buat Surat Jalan Mandiri Baru</h3>
                                <p class="text-small" style="margin:0;">Lengkapi form di bawah untuk membuat Surat Jalan mandiri yang akan langsung memotong stok.</p>
                            </div>
                            <a href="?page=surat_jalan" class="btn-outline" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #ccc; color: #555; background: #fff; padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600;">
                                <i class="fas fa-arrow-left"></i> Batal &amp; Kembali
                            </a>
                        </div>

                        <form method="POST" action="Dpb.php">
                            <!-- Hidden indicator to trigger the correct post handler -->
                            <input type="hidden" name="create_manual_sj" value="1">
                            
                            <div class="flex-row">
                                <div class="form-group" style="flex: 1; min-width: 220px;">
                                    <label>Nomor Surat Jalan (Otomatis)</label>
                                    <input type="text" value="<?= generateNextSuratJalanNumber($db, date('Y-m-d')) ?>" readonly style="background-color: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; cursor: not-allowed; font-weight: bold;">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 200px;">
                                    <label>Nomor TUG <span style="color:red;">*</span></label>
                                    <input type="text" name="tug_number" placeholder="contoh: TUG5NSMLG26-0100" required>
                                </div>
                                <div class="form-group">
                                    <label>Pilih Vendor <span style="color:red;">*</span></label>
                                    <select name="vendor_id" required>
                                        <option value="">-- Pilih Vendor --</option>
                                        <?php 
                                        $all_v = $db->query("SELECT id, name FROM vendors ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($all_v as $v):
                                        ?>
                                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Nomor SPK</label>
                                    <input type="text" name="spk_number" placeholder="Nomor SPK">
                                </div>
                            </div>

                            <div class="flex-row">
                                <div class="form-group">
                                    <label>Jenis Pekerjaan</label>
                                    <input type="text" name="jenis_pekerjaan" placeholder="Jenis Pekerjaan">
                                </div>
                                <div class="form-group">
                                    <label>IDPEL</label>
                                    <input type="text" name="idpel" placeholder="ID Pelanggan">
                                </div>
                                <div class="form-group">
                                    <label>Nama Pelanggan <span style="color:red;">*</span></label>
                                    <input type="text" name="customer_name" placeholder="Nama Pelanggan" required>
                                </div>
                            </div>

                            <div class="flex-row">
                                <div class="form-group">
                                    <label>Alamat Pelanggan</label>
                                    <input type="text" name="customer_address" placeholder="Alamat Pelanggan">
                                </div>
                                <div class="form-group">
                                    <label>Daya</label>
                                    <input type="text" name="daya" placeholder="contoh: 1300 VA">
                                </div>
                                <div class="form-group">
                                    <label>ULP</label>
                                    <input type="text" name="ulp" placeholder="contoh: ULP Malang Kota">
                                </div>
                            </div>

                            <div class="flex-row">
                                <div class="form-group">
                                    <label>Tanggal Surat</label>
                                    <input type="date" name="tanggal_diminta" value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Penerima (Yang Mengambil)</label>
                                    <input type="text" name="penerima_name" placeholder="Nama Penerima">
                                </div>
                                <div class="form-group">
                                    <label>Security</label>
                                    <input type="text" name="security_name" placeholder="Nama Security">
                                </div>
                                <div class="form-group">
                                    <label>Yang Menyerahkan (Petugas)</label>
                                    <input type="text" name="menyerahkan_name" placeholder="Nama Petugas">
                                </div>
                            </div>

                            <div class="flex-row" style="margin-top: 1rem;">
                                <div class="form-group">
                                    <label>Setuju (Manager/Asman)</label>
                                    <input type="text" name="setuju_name" value="">
                                </div>
                                <div class="form-group">
                                    <label>Kepala Gudang</label>
                                    <input type="text" name="kepala_gudang_name" value="">
                                </div>
                                <div class="form-group">
                                    <label>Pemeriksa / Petugas</label>
                                    <input type="text" name="pemeriksa_pengawas_name" placeholder="Nama pemeriksa/petugas">
                                </div>
                            </div>

                            <!-- DYNAMIC ITEMS LIST FOR MATERIALS -->
                            <div style="margin-top: 1.5rem; margin-bottom: 1.5rem; border-top: 1px solid #eee; padding-top: 1.5rem;">
                                <h4 style="color:#0b2b4a; margin-bottom:1rem;"><i class="fas fa-boxes"></i> Daftar Material yang Dikeluarkan</h4>
                                <div id="dpbItemsWrap"></div>
                                <button type="button" class="btn-info" onclick="addDpbItemRow()" style="margin-top: 0.8rem;"><i class="fas fa-plus"></i> Tambah Baris Material</button>
                            </div>

                            <button type="submit" class="btn-success" style="padding: 0.8rem 2rem; border-radius: 10px; font-weight: 600; font-size: 14px;"><i class="fas fa-save"></i> Simpan Surat Jalan</button>
                        </form>
                    </div>

                    <!-- Script helper untuk input dinamis form ini -->
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            ensureMaterialDatalists();
                            addDpbItemRow(); // Tambahkan baris pertama saat form dibuka
                        });
                    </script>
                <?php else: ?>
                    
                    <!-- TABEL RIWAYAT / PENELUSURAN SURAT JALAN MANDIRI -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                            <div>
                                <h3 style="color:#0b2b4a; margin: 0 0 4px 0;"><i class="fas fa-file-invoice" style="color: var(--blue); margin-right: 8px;"></i> Daftar Surat Jalan Mandiri</h3>
                                <p class="text-small" style="margin:0;">Menampilkan seluruh catatan surat jalan mandiri yang dibuat oleh petugas gudang.</p>
                            </div>
                            <?php if ($is_gudang2): ?>
                            <a href="?page=surat_jalan&action=create" class="btn-success" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                                <i class="fas fa-plus"></i> Buat Surat Jalan
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="g2-search-filter-card" style="margin-bottom: 24px;">
                        <form method="GET" action="index.php" style="margin: 0;">
                            <input type="hidden" name="page" value="surat_jalan">
                            <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                                <div class="form-group" style="flex: 2; min-width: 280px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Cari Nomor TUG / Nomor Surat / Pelanggan / Vendor</label>
                                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ketik pencarian..." style="padding: 0.65rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Dari Tanggal</label>
                                    <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Sampai Tanggal</label>
                                    <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button type="submit" class="btn-success" style="padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;"><i class="fas fa-search"></i> Cari</button>
                                    <?php if ($q !== '' || $startDate !== '' || $endDate !== ''): ?>
                                        <a href="?page=surat_jalan" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; background:#64748b; color:#fff; font-weight: 600;">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor Surat Jalan</th>
                                        <th>Nomor TUG</th>
                                        <th>Pelanggan</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Keluar</th>
                                        <th style="width: 15%; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($manual_sj_list)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center; padding:3rem; color:#64748b;">Tidak ada data surat jalan mandiri ditemukan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($apage - 1) * $limit + 1;
                                        foreach ($manual_sj_list as $i => $row): 
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td><strong><?= htmlspecialchars($row['surat_jalan_number'] ?: '-') ?></strong></td>
                                                <td><?= htmlspecialchars($row['tug_number']) ?></td>
                                                <td><?= htmlspecialchars($row['customer_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td style="text-align: center;">
                                                    <button type="button" class="btn-info" onclick="openGudangDpbDetail('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.5rem 1.2rem; border-radius:30px; font-size:0.85rem; font-weight:600; display: inline-flex; align-items: center; gap: 6px;"><i class="fas <?= $row['status'] === 'selesai' ? 'fa-eye' : 'fa-folder-open' ?>"></i> <?= $row['status'] === 'selesai' ? 'Lihat Detail' : 'Kelola Surat' ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div style="margin-top: 1.5rem; text-align: center;">
                                <?= renderPhpPagination($apage, $totalPages, 'apage') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($page === 'dpb'): ?>
            <div id="monitoringSection">
                <?php if ($is_gudang2): ?>

                    <!-- Bilah Pencarian & Filter Terpadu -->
                    <div class="g2-search-filter-card">
                        <form method="GET" action="index.php" style="margin: 0;">
                            <input type="hidden" name="page" value="dpb">
                            <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'active') ?>">
                            <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                                <div class="form-group" style="flex: 2; min-width: 280px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Cari Nomor TUG / Nama Vendor</label>
                                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ketik nomor TUG atau nama vendor..." style="padding: 0.65rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Dari Tanggal</label>
                                    <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Sampai Tanggal</label>
                                    <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button type="submit" class="btn-success" style="padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; font-weight: 600;"><i class="fas fa-search"></i> Cari</button>
                                    <?php if ($q !== '' || $startDate !== '' || $endDate !== ''): ?>
                                        <a href="?page=dpb&tab=<?= htmlspecialchars($_GET['tab'] ?? 'active') ?>" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; background:#64748b; color:#fff; font-weight: 600;">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Sistem Tab Terpadu -->
                    <?php $activeTab = $_GET['tab'] ?? 'active'; ?>
                    <div class="g2-tabs-container">
                        <a href="?page=dpb&tab=active<?= $q ? '&q='.urlencode($q) : '' ?><?= $startDate ? '&start='.urlencode($startDate) : '' ?><?= $endDate ? '&end='.urlencode($endDate) : '' ?>" class="g2-tab-btn <?= $activeTab === 'active' ? 'active' : '' ?>">
                            <i class="fas fa-hourglass-half"></i> Surat Aktif
                        </a>
                        <a href="?page=dpb&tab=completed<?= $q ? '&q='.urlencode($q) : '' ?><?= $startDate ? '&start='.urlencode($startDate) : '' ?><?= $endDate ? '&end='.urlencode($endDate) : '' ?>" class="g2-tab-btn <?= $activeTab === 'completed' ? 'active' : '' ?>">
                            <i class="fas fa-check-circle"></i> Riwayat Selesai
                        </a>
                    </div>

                    <!-- Tabel Paginasi -->
                    <div class="card" style="margin-top: 0;">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor TUG</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Diminta</th>
                                        <th>Status</th>
                                        <th style="width: 15%; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($gudang_list)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:3rem; color:#64748b;">Tidak ada data surat ditemukan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($apage - 1) * $limit + 1;
                                        foreach ($gudang_list as $i => $row): 
                                            $statusClass = $row['status'] === 'selesai' ? 'status-selesai' : ($row['status'] === 'aktif' ? 'status-aktif' : 'status-belum');
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td><strong><?= htmlspecialchars($row['tug_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(dpbStatusLabel($row['status'])) ?></span></td>
                                                <td style="text-align: center;">
                                                    <button type="button" class="btn-info" onclick="openGudangDpbDetail('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.5rem 1.2rem; border-radius:30px; font-size:0.85rem; font-weight:600; display: inline-flex; align-items: center; gap: 6px;"><i class="fas <?= $row['status'] === 'selesai' ? 'fa-eye' : 'fa-folder-open' ?>"></i> <?= $row['status'] === 'selesai' ? 'Lihat Detail' : 'Kelola Surat' ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div style="margin-top: 1.5rem; text-align: center;">
                                <?= renderPhpPagination($apage, $totalPages, 'apage') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- ORIGINAL ADMIN/VENDOR MONITORING DPB -->
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
                            <?php if ($is_admin || $is_gudang2): ?>
                            <button class="btn-warning" onclick="printDPB()"><i class="fas fa-print"></i> Cetak Surat Jalan</button>
                            <?php endif; ?>
                            <?php if ($is_admin || $is_vendor): ?>
                            <button class="btn-warning" onclick="(function(){var t=document.getElementById('tugNumberInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printDPBForm.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-print"></i> Cetak DPB</button>
                            <?php endif; ?>
                            <?php if ($is_admin || $is_gudang2): ?>
                            <button class="btn-info" onclick="saveDPBpdf()"><i class="fas fa-file-pdf"></i> Simpan PDF</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TABEL DPB AKTIF -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <h3 style="color:#0b2b4a;"><i class="fas fa-hourglass-half" style="color: #14828a;"></i> Daftar DPB Aktif</h3>
                        <p class="text-small" style="margin-bottom:1rem;">Menampilkan daftar DPB yang sedang berjalan atau menunggu diproses/diserahkan.</p>
                        
                        <!-- Filter & Search Form for Active DPB -->
                        <form method="GET" action="index.php" style="margin-bottom: 1.2rem; background: #f8fafc; padding: 1.2rem; border-radius: 14px; border: 1px solid #eef2f6;">
                            <input type="hidden" name="page" value="dpb">
                            <div class="flex-row" style="align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                                <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Cari Nomor TUG / Vendor</label>
                                    <input type="text" name="q_active" value="<?= htmlspecialchars($q_active) ?>" placeholder="TUG / Nama PT Vendor..." style="padding: 0.55rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Dari Tanggal</label>
                                    <input type="date" name="start_active" value="<?= htmlspecialchars($start_active) ?>" style="padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Sampai Tanggal</label>
                                    <input type="date" name="end_active" value="<?= htmlspecialchars($end_active) ?>" style="padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div style="display: flex; gap: 8px; margin-bottom: 0;">
                                    <button type="submit" class="btn-success" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 13px;"><i class="fas fa-search"></i> Cari</button>
                                    <?php if ($q_active !== '' || $start_active !== '' || $end_active !== ''): ?>
                                        <a href="?page=dpb" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 13px; background:#64748b; color:#fff;">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor TUG</th>
                                        <th>Pelanggan</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Diminta</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($active_list)): ?>
                                        <tr>
                                            <td colspan="7" style="text-align:center; padding:2rem; color:#777;">Tidak ada DPB aktif saat ini.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($apage - 1) * $limit + 1;
                                        foreach ($active_list as $i => $row): 
                                            $statusClass = $row['status'] === 'selesai' ? 'status-selesai' : ($row['status'] === 'aktif' ? 'status-aktif' : 'status-belum');
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="cursor: pointer; color: var(--blue);"><strong><?= htmlspecialchars($row['tug_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['customer_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(dpbStatusLabel($row['status'])) ?></span></td>
                                                <td>
                                                    <button type="button" class="btn-info" onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.35rem 0.8rem; border-radius:20px; font-size:0.75rem;">Pilih &amp; Muat</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalActivePages > 1): ?>
                            <div style="margin-top: 1.2rem; text-align: center;">
                                <?= renderPhpPagination($apage, $totalActivePages, 'apage') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- TABEL RIWAYAT DPB -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <h3 style="color:#0b2b4a;"><i class="fas fa-history" style="color: #14828a;"></i> Riwayat DPB (Selesai)</h3>
                        <p class="text-small" style="margin-bottom:1rem;">Daftar pengajuan DPB yang sudah selesai sepenuhnya.</p>

                        <!-- Filter & Search Form -->
                        <form method="GET" action="index.php" style="margin-bottom: 1.2rem; background: #f8fafc; padding: 1.2rem; border-radius: 14px; border: 1px solid #eef2f6;">
                            <input type="hidden" name="page" value="dpb">
                            <div class="flex-row" style="align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                                <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Cari Nomor TUG / Vendor</label>
                                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="TUG / Nama PT Vendor..." style="padding: 0.55rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Dari Tanggal</label>
                                    <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" style="padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Sampai Tanggal</label>
                                    <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" style="padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div style="display: flex; gap: 8px; margin-bottom: 0;">
                                    <button type="submit" class="btn-success" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 13px;"><i class="fas fa-search"></i> Cari</button>
                                    <?php if ($q !== '' || $startDate !== '' || $endDate !== ''): ?>
                                        <a href="?page=dpb" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 13px; background:#64748b; color:#fff;">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor TUG</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Diminta</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($history_list)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:2rem; color:#777;">Tidak ada riwayat DPB ditemukan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($hpage - 1) * $limit + 1;
                                        foreach ($history_list as $i => $row): 
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="cursor: pointer; color: var(--blue);"><strong><?= htmlspecialchars($row['tug_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td><span class="status-badge status-selesai">Selesai</span></td>
                                                <td>
                                                    <button type="button" class="btn-info" onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.35rem 0.8rem; border-radius:20px; font-size:0.75rem;">Pilih &amp; Muat</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalHistoryPages > 1): ?>
                            <div style="margin-top: 1.2rem; text-align: center;">
                                <?= renderPhpPagination($hpage, $totalHistoryPages, 'hpage') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_admin || $is_vendor): ?>
                        <?php if ($is_vendor && (($my_vendor['status'] ?? 'aktif') !== 'aktif')): ?>
                            <div class="alert-danger" style="padding:1.2rem; border-radius:16px; margin-top:1.5rem; display:flex; align-items:center; gap:12px; height:auto;">
                                <i class="fas fa-exclamation-circle" style="font-size:1.5rem;"></i>
                                <div>
                                    <strong style="display:block; margin-bottom:2px;">Akun Vendor Nonaktif</strong>
                                    Vendor Anda saat ini berstatus Nonaktif. Pengajuan surat baru tidak dapat dilakukan. Anda masih dapat melihat riwayat transaksi.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card" style="margin-top:1.5rem;">
                                <h3><i class="fas fa-file-signature"></i> Ajukan Permintaan Material Baru (DPB)</h3>
                        
                                <form method="POST" action="Dpb.php" id="dpbCreateForm">
                                    <div class="flex-row">
                                        <div class="form-group">
                                            <label>Nomor TUG</label>
                                            <input type="text" name="tug_number" value="<?= getNextTugNumber($db, 'dpb') ?>" readonly required>
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
                                                <input type="hidden" name="vendor_id" value="<?= $my_vendor['id'] ?>">
                                                <input type="text" value="<?= htmlspecialchars($my_vendor['name']) ?>" readonly style="background:#f1f5f9; color:#64748b;">
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
                                             <input type="text" name="setuju_name" value="">
                                         </div>
                                         <div class="form-group">
                                             <label>Kepala Gudang</label>
                                             <input type="text" name="kepala_gudang_name" value="">
                                         </div>
                                         <div class="form-group">
                                             <label>Pemeriksa / Petugas</label>
                                             <input type="text" name="pemeriksa_pengawas_name" placeholder="Nama pemeriksa/petugas">
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
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php elseif ($page === 'k3'): ?>
            <div id="k3Section">
                <?php if ($is_gudang2): ?>
                    <!-- GUDANG 2 SIMPLIFIED K3 VIEW -->
                    <div style="margin-bottom: 1.5rem;">
                        <h2 style="color: #0b2b4a; font-weight: 700; font-size: 1.8rem; margin: 0 0 4px 0;"><i class="fas fa-undo" style="color: var(--blue);"></i> K3</h2>
                        <p style="color: #64748b; font-size: 0.95rem; margin: 0;">Kelola dan cetak surat K3 (Bon Pengembalian Material).</p>
                    </div>

                    <!-- Bilah Pencarian & Filter Terpadu -->
                    <div class="g2-search-filter-card">
                        <form method="GET" action="index.php" style="margin: 0;">
                            <input type="hidden" name="page" value="k3">
                            <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'active') ?>">
                            <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                                <div class="form-group" style="flex: 2; min-width: 280px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Cari Nomor TUG / Nama Vendor</label>
                                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ketik nomor TUG atau nama vendor..." style="padding: 0.65rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Dari Tanggal</label>
                                    <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Sampai Tanggal</label>
                                    <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button type="submit" class="btn-success" style="padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; font-weight: 600;"><i class="fas fa-search"></i> Cari</button>
                                    <?php if ($q !== '' || $startDate !== '' || $endDate !== ''): ?>
                                        <a href="?page=k3&tab=<?= htmlspecialchars($_GET['tab'] ?? 'active') ?>" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; background:#64748b; color:#fff; font-weight: 600;">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Sistem Tab Terpadu -->
                    <?php $activeTab = $_GET['tab'] ?? 'active'; ?>
                    <div class="g2-tabs-container">
                        <a href="?page=k3&tab=active<?= $q ? '&q='.urlencode($q) : '' ?><?= $startDate ? '&start='.urlencode($startDate) : '' ?><?= $endDate ? '&end='.urlencode($endDate) : '' ?>" class="g2-tab-btn <?= $activeTab === 'active' ? 'active' : '' ?>">
                            <i class="fas fa-hourglass-half"></i> Surat Aktif
                        </a>
                        <a href="?page=k3&tab=completed<?= $q ? '&q='.urlencode($q) : '' ?><?= $startDate ? '&start='.urlencode($startDate) : '' ?><?= $endDate ? '&end='.urlencode($endDate) : '' ?>" class="g2-tab-btn <?= $activeTab === 'completed' ? 'active' : '' ?>">
                            <i class="fas fa-check-circle"></i> Riwayat Selesai
                        </a>
                    </div>

                    <!-- Tabel Paginasi -->
                    <div class="card" style="margin-top: 0;">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor TUG</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Diminta</th>
                                        <th>Status</th>
                                        <th style="width: 15%; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($gudang_list)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:3rem; color:#64748b;">Tidak ada data surat ditemukan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($apage - 1) * $limit + 1;
                                        foreach ($gudang_list as $i => $row): 
                                            $statusClass = $row['status'] === 'selesai' ? 'status-selesai' : ($row['status'] === 'aktif' ? 'status-aktif' : 'status-belum');
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td><strong><?= htmlspecialchars($row['tug_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(dpbStatusLabel($row['status'])) ?></span></td>
                                                <td style="text-align: center;">
                                                    <button type="button" class="btn-info" onclick="openGudangK3Detail('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.5rem 1.2rem; border-radius:30px; font-size:0.85rem; font-weight:600; display: inline-flex; align-items: center; gap: 6px;"><i class="fas <?= $row['status'] === 'selesai' ? 'fa-eye' : 'fa-folder-open' ?>"></i> <?= $row['status'] === 'selesai' ? 'Lihat Detail' : 'Kelola Surat' ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div style="margin-top: 1.5rem; text-align: center;">
                                <?= renderPhpPagination($apage, $totalPages, 'apage') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- ORIGINAL ADMIN/VENDOR K3 VIEW -->
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
                            <?php if ($is_admin || $is_vendor): ?>
                            <button class="btn-warning" onclick="(function(){var t=document.getElementById('k3TugInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printK3.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-print"></i> Cetak Bon (Format Resmi)</button>
                            <button class="btn-info" onclick="(function(){var t=document.getElementById('k3TugInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printK3.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-file-pdf"></i> Simpan PDF</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TABEL K3 AKTIF -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <h3 style="color:#0b2b4a;"><i class="fas fa-hourglass-half" style="color: #14828a;"></i> Daftar K3 Aktif</h3>
                        <p class="text-small" style="margin-bottom:1rem;">Menampilkan daftar K3 yang sedang berjalan atau menunggu diproses/diserahkan.</p>
                        
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor TUG</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Diminta</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($active_list)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:2rem; color:#777;">Tidak ada K3 aktif saat ini.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($apage - 1) * $limit + 1;
                                        foreach ($active_list as $i => $row): 
                                            $statusClass = $row['status'] === 'selesai' ? 'status-selesai' : ($row['status'] === 'aktif' ? 'status-aktif' : 'status-belum');
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="cursor: pointer; color: var(--blue);"><strong><?= htmlspecialchars($row['tug_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(dpbStatusLabel($row['status'])) ?></span></td>
                                                <td>
                                                    <button type="button" class="btn-info" onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.35rem 0.8rem; border-radius:20px; font-size:0.75rem;">Pilih &amp; Muat</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalActivePages > 1): ?>
                            <div style="margin-top: 1.2rem; text-align: center;">
                                <?= renderPhpPagination($apage, $totalActivePages, 'apage') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TABEL RIWAYAT K3 -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <h3 style="color:#0b2b4a;"><i class="fas fa-history" style="color: #14828a;"></i> Riwayat K3 (Selesai)</h3>
                        <p class="text-small" style="margin-bottom:1rem;">Daftar pengajuan K3 yang sudah selesai sepenuhnya.</p>

                        <!-- Filter & Search Form -->
                        <form method="GET" action="index.php" style="margin-bottom: 1.2rem; background: #f8fafc; padding: 1.2rem; border-radius: 14px; border: 1px solid #eef2f6;">
                            <input type="hidden" name="page" value="k3">
                            <div class="flex-row" style="align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                                <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Cari Nomor TUG / Vendor</label>
                                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="TUG / Nama PT Vendor..." style="padding: 0.55rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Dari Tanggal</label>
                                    <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" style="padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Sampai Tanggal</label>
                                    <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" style="padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div style="display: flex; gap: 8px; margin-bottom: 0;">
                                    <button type="submit" class="btn-success" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 13px;"><i class="fas fa-search"></i> Cari</button>
                                    <?php if ($q !== '' || $startDate !== '' || $endDate !== ''): ?>
                                        <a href="?page=k3" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 13px; background:#64748b; color:#fff;">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor TUG</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Diminta</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($history_list)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:2rem; color:#777;">Tidak ada riwayat K3 ditemukan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($hpage - 1) * $limit + 1;
                                        foreach ($history_list as $i => $row): 
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="cursor: pointer; color: var(--blue);"><strong><?= htmlspecialchars($row['tug_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td><span class="status-badge status-selesai">Selesai</span></td>
                                                <td>
                                                    <button type="button" class="btn-info" onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.35rem 0.8rem; border-radius:20px; font-size:0.75rem;">Pilih &amp; Muat</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalHistoryPages > 1): ?>
                            <div style="margin-top: 1.2rem; text-align: center;">
                                <?= renderPhpPagination($hpage, $totalHistoryPages, 'hpage') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_admin || $is_vendor): ?>
                        <?php if ($is_vendor && (($my_vendor['status'] ?? 'aktif') !== 'aktif')): ?>
                            <div class="alert-danger" style="padding:1.2rem; border-radius:16px; margin-top:1.5rem; display:flex; align-items:center; gap:12px; height:auto;">
                                <i class="fas fa-exclamation-circle" style="font-size:1.5rem;"></i>
                                <div>
                                    <strong style="display:block; margin-bottom:2px;">Akun Vendor Nonaktif</strong>
                                    Vendor Anda saat ini berstatus Nonaktif. Pengajuan surat baru tidak dapat dilakukan. Anda masih dapat melihat riwayat transaksi.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card" style="margin-top:1.5rem;">
                                <h3><i class="fas fa-file-signature"></i> Ajukan Pengembalian Material (K3)</h3>
                        
                                <form method="POST" action="k3.php" id="k3CreateForm">
                                    <div class="flex-row">
                                        <div class="form-group">
                                            <label>Nomor TUG K3</label>
                                            <input type="text" name="tug_number" value="<?= getNextTugNumber($db, 'k3') ?>" readonly required>
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
                                                <input type="hidden" name="vendor_id" value="<?= $my_vendor['id'] ?>">
                                                <input type="text" value="<?= htmlspecialchars($my_vendor['name']) ?>" readonly style="background:#f1f5f9; color:#64748b;">
                                            <?php else: ?>
                                                <select name="vendor_id" id="k3VendorSelect" onchange="autofillVendorK3()" required>
                                                    <option value="">-- pilih PT / vendor --</option>
                                                    <?php foreach ($vendors as $v): ?>
                                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group">
                                            <label>No. SPK</label>
                                            <input type="text" name="spk_number" id="k3SpkInput">
                                        </div>
                                    </div>

                                    <div class="flex-row">
                                        <div class="form-group">
                                            <label>Jenis Pekerjaan</label>
                                            <input type="text" name="jenis_pekerjaan" id="k3JenisInput">
                                        </div>
                                        <div class="form-group">
                                            <label>IDPEL</label>
                                            <input type="text" name="idpel" id="k3IdpelInput">
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
                                            <label>Nomor Seri Alat</label>
                                            <input type="text" name="nomor_seri" placeholder="Contoh: SN-12345">
                                        </div>
                                        <div class="form-group">
                                            <label>No. DPB / Bukti Penyerahan</label>
                                            <input type="text" name="no_dpb_bukti" placeholder="Nomor DPB asal barang">
                                        </div>
                                        <div class="form-group">
                                            <label>Lokasi Penempatan Material / Dipakai</label>
                                            <input type="text" name="lokasi_penempatan" placeholder="Lokasi pemasangan">
                                        </div>
                                    </div>

                                    <div class="flex-row">
                                        <div class="form-group">
                                            <label>Gudang Pengembalian</label>
                                            <input type="text" name="gudang_pengembalian" value="Gudang UP3 Malang" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Keterangan Detile</label>
                                            <input type="text" name="keterangan" placeholder="Keterangan kondisi / alasan pengembalian">
                                        </div>
                                    </div>

                                    <div class="flex-row">
                                        <div class="form-group">
                                            <label>Kondisi Material</label>
                                            <select name="kondisi_material" required>
                                                <option value="masih_dapat_dipergunakan">Masih Dapat Dipergunakan (MDB)</option>
                                                <option value="rusak">Rusak / Tidak Dapat Digunakan (TUA)</option>
                                                <option value="baru">Baru (Sisa Proyek)</option>
                                                <option value="garansi">Klaim Garansi</option>
                                            </select>
                                        </div>
                                    </div>

                                    <h4 style="color:#0b2b4a; margin-top:1.2rem;">Data Tanda Tangan</h4>
                                    <div class="flex-row">
                                        <div class="form-group">
                                            <label>Setuju (Manager/Asman)</label>
                                            <input type="text" name="setuju_name" value="">
                                        </div>
                                        <div class="form-group">
                                            <label>Kepala Gudang</label>
                                            <input type="text" name="kepala_gudang_name" value="">
                                        </div>
                                        <div class="form-group">
                                            <label>Pemeriksa / Pengawas</label>
                                            <input type="text" name="pemeriksa_pengawas_name" placeholder="Nama pemeriksa/pengawas">
                                        </div>
                                        <div class="form-group">
                                            <label>Yang Menyerahkan (Vendor)</label>
                                            <input type="text" name="yang_menyerahkan_name" placeholder="Nama petugas vendor">
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
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php elseif ($page === 'k7'): ?>
            <div id="k7Section">
                <?php if ($is_gudang2): ?>
                    <!-- GUDANG 2 SIMPLIFIED K7 VIEW -->
                    <div style="margin-bottom: 1.5rem;">
                        <h2 style="color: #0b2b4a; font-weight: 700; font-size: 1.8rem; margin: 0 0 4px 0;"><i class="fas fa-recycle" style="color: var(--blue);"></i> K7</h2>
                        <p style="color: #64748b; font-size: 0.95rem; margin: 0;">Kelola dan cetak surat K7 (Bon Pemakaian Material Bekas).</p>
                    </div>

                    <!-- Bilah Pencarian & Filter Terpadu -->
                    <div class="g2-search-filter-card">
                        <form method="GET" action="index.php" style="margin: 0;">
                            <input type="hidden" name="page" value="k7">
                            <input type="hidden" name="tab" value="<?= htmlspecialchars($_GET['tab'] ?? 'active') ?>">
                            <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                                <div class="form-group" style="flex: 2; min-width: 280px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Cari Nomor TUG / Nama Vendor</label>
                                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Ketik nomor TUG atau nama vendor..." style="padding: 0.65rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Dari Tanggal</label>
                                    <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block;">Sampai Tanggal</label>
                                    <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button type="submit" class="btn-success" style="padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; font-weight: 600;"><i class="fas fa-search"></i> Cari</button>
                                    <?php if ($q !== '' || $startDate !== '' || $endDate !== ''): ?>
                                        <a href="?page=k7&tab=<?= htmlspecialchars($_GET['tab'] ?? 'active') ?>" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; background:#64748b; color:#fff; font-weight: 600;">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Sistem Tab Terpadu -->
                    <?php $activeTab = $_GET['tab'] ?? 'active'; ?>
                    <div class="g2-tabs-container">
                        <a href="?page=k7&tab=active<?= $q ? '&q='.urlencode($q) : '' ?><?= $startDate ? '&start='.urlencode($startDate) : '' ?><?= $endDate ? '&end='.urlencode($endDate) : '' ?>" class="g2-tab-btn <?= $activeTab === 'active' ? 'active' : '' ?>">
                            <i class="fas fa-hourglass-half"></i> Surat Aktif
                        </a>
                        <a href="?page=k7&tab=completed<?= $q ? '&q='.urlencode($q) : '' ?><?= $startDate ? '&start='.urlencode($startDate) : '' ?><?= $endDate ? '&end='.urlencode($endDate) : '' ?>" class="g2-tab-btn <?= $activeTab === 'completed' ? 'active' : '' ?>">
                            <i class="fas fa-check-circle"></i> Riwayat Selesai
                        </a>
                    </div>

                    <!-- Tabel Paginasi -->
                    <div class="card" style="margin-top: 0;">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor TUG</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Diminta</th>
                                        <th>Status</th>
                                        <th style="width: 15%; text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($gudang_list)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:3rem; color:#64748b;">Tidak ada data surat ditemukan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($apage - 1) * $limit + 1;
                                        foreach ($gudang_list as $i => $row): 
                                            $statusClass = $row['status'] === 'selesai' ? 'status-selesai' : ($row['status'] === 'aktif' ? 'status-aktif' : 'status-belum');
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td><strong><?= htmlspecialchars($row['tug_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(dpbStatusLabel($row['status'])) ?></span></td>
                                                <td style="text-align: center;">
                                                    <button type="button" class="btn-info" onclick="openGudangK7Detail('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.5rem 1.2rem; border-radius:30px; font-size:0.85rem; font-weight:600; display: inline-flex; align-items: center; gap: 6px;"><i class="fas <?= $row['status'] === 'selesai' ? 'fa-eye' : 'fa-folder-open' ?>"></i> <?= $row['status'] === 'selesai' ? 'Lihat Detail' : 'Kelola Surat' ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div style="margin-top: 1.5rem; text-align: center;">
                                <?= renderPhpPagination($apage, $totalPages, 'apage') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- ORIGINAL ADMIN/VENDOR K7 VIEW -->
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
                            <?php if ($is_admin || $is_vendor): ?>
                            <button class="btn-warning" onclick="(function(){var t=document.getElementById('k7TugInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printK7.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-print"></i> Cetak Bon (Format Resmi)</button>
                            <button class="btn-info" onclick="(function(){var t=document.getElementById('k7TugInput').value.trim(); if(!t){alert('Cari nomor TUG dulu sebelum mencetak.');return;} window.open('printK7.php?tug='+encodeURIComponent(t), '_blank');})()"><i class="fas fa-file-pdf"></i> Simpan PDF</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TABEL K7 AKTIF -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <h3 style="color:#0b2b4a;"><i class="fas fa-hourglass-half" style="color: #14828a;"></i> Daftar K7 Aktif</h3>
                        <p class="text-small" style="margin-bottom:1rem;">Menampilkan daftar K7 yang sedang berjalan atau menunggu diproses/diserahkan.</p>
                        
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor TUG</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Diminta</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($active_list)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:2rem; color:#777;">Tidak ada K7 aktif saat ini.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($apage - 1) * $limit + 1;
                                        foreach ($active_list as $i => $row): 
                                            $statusClass = $row['status'] === 'selesai' ? 'status-selesai' : ($row['status'] === 'aktif' ? 'status-aktif' : 'status-belum');
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="cursor: pointer; color: var(--blue);"><strong><?= htmlspecialchars($row['tug_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td><span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(dpbStatusLabel($row['status'])) ?></span></td>
                                                <td>
                                                    <button type="button" class="btn-info" onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.35rem 0.8rem; border-radius:20px; font-size:0.75rem;">Pilih &amp; Muat</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalActivePages > 1): ?>
                            <div style="margin-top: 1.2rem; text-align: center;">
                                <?= renderPhpPagination($apage, $totalActivePages, 'apage') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TABEL RIWAYAT K7 -->
                    <div class="card" style="margin-top: 1.5rem;">
                        <h3 style="color:#0b2b4a;"><i class="fas fa-history" style="color: #14828a;"></i> Riwayat K7 (Selesai)</h3>
                        <p class="text-small" style="margin-bottom:1rem;">Daftar pengajuan K7 yang sudah selesai sepenuhnya.</p>

                        <!-- Filter & Search Form -->
                        <form method="GET" action="index.php" style="margin-bottom: 1.2rem; background: #f8fafc; padding: 1.2rem; border-radius: 14px; border: 1px solid #eef2f6;">
                            <input type="hidden" name="page" value="k7">
                            <div class="flex-row" style="align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                                <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Cari Nomor TUG / Vendor</label>
                                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="TUG / Nama PT Vendor..." style="padding: 0.55rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Dari Tanggal</label>
                                    <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" style="padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label style="font-size: 11px; text-transform: uppercase; font-weight:700;">Sampai Tanggal</label>
                                    <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" style="padding: 0.5rem 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%;">
                                </div>
                                <div style="display: flex; gap: 8px; margin-bottom: 0;">
                                    <button type="submit" class="btn-success" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 13px;"><i class="fas fa-search"></i> Cari</button>
                                    <?php if ($q !== '' || $startDate !== '' || $endDate !== ''): ?>
                                        <a href="?page=k7" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 13px; background:#64748b; color:#fff;">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nomor TUG</th>
                                        <th>Vendor</th>
                                        <th>Tanggal Diminta</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($history_list)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align:center; padding:2rem; color:#777;">Tidak ada riwayat K7 ditemukan.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $startNum = ($hpage - 1) * $limit + 1;
                                        foreach ($history_list as $i => $row): 
                                        ?>
                                            <tr>
                                                <td><?= $startNum + $i ?></td>
                                                <td onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="cursor: pointer; color: var(--blue);"><strong><?= htmlspecialchars($row['tug_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($row['vendor_name'] ?? '-') ?></td>
                                                <td><?= date('d-M-Y', strtotime($row['tanggal_diminta'])) ?></td>
                                                <td><span class="status-badge status-selesai">Selesai</span></td>
                                                <td>
                                                    <button type="button" class="btn-info" onclick="autofillSearchTug('<?= htmlspecialchars($row['tug_number']) ?>')" style="padding:0.35rem 0.8rem; border-radius:20px; font-size:0.75rem;">Pilih &amp; Muat</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalHistoryPages > 1): ?>
                            <div style="margin-top: 1.2rem; text-align: center;">
                                <?= renderPhpPagination($hpage, $totalHistoryPages, 'hpage') ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_admin || $is_vendor): ?>
                        <?php if ($is_vendor && (($my_vendor['status'] ?? 'aktif') !== 'aktif')): ?>
                            <div class="alert-danger" style="padding:1.2rem; border-radius:16px; margin-top:1.5rem; display:flex; align-items:center; gap:12px; height:auto;">
                                <i class="fas fa-exclamation-circle" style="font-size:1.5rem;"></i>
                                <div>
                                    <strong style="display:block; margin-bottom:2px;">Akun Vendor Nonaktif</strong>
                                    Vendor Anda saat ini berstatus Nonaktif. Pengajuan surat baru tidak dapat dilakukan. Anda masih dapat melihat riwayat transaksi.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card" style="margin-top:1.5rem;">
                                <h3><i class="fas fa-file-signature"></i> Ajukan Pemakaian Material Bekas (K7)</h3>
                        
                                <form method="POST" action="k7.php" id="k7CreateForm">
                                    <div class="flex-row">
                                        <div class="form-group">
                                            <label>Nomor TUG K7</label>
                                            <input type="text" name="tug_number" value="<?= getNextTugNumber($db, 'k7') ?>" readonly required>
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
                                                <input type="hidden" name="vendor_id" value="<?= $my_vendor['id'] ?>">
                                                <input type="text" value="<?= htmlspecialchars($my_vendor['name']) ?>" readonly style="background:#f1f5f9; color:#64748b;">
                                            <?php else: ?>
                                                <select name="vendor_id" id="k7VendorSelect" onchange="autofillVendorK7()" required>
                                                    <option value="">-- pilih PT / vendor --</option>
                                                    <?php foreach ($vendors as $v): ?>
                                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group">
                                            <label>No. SPK</label>
                                            <input type="text" name="spk_number" id="k7SpkInput">
                                        </div>
                                    </div>

                                    <div class="flex-row">
                                        <div class="form-group">
                                            <label>Jenis Pekerjaan</label>
                                            <input type="text" name="jenis_pekerjaan" id="k7JenisInput">
                                        </div>
                                        <div class="form-group">
                                            <label>IDPEL</label>
                                            <input type="text" name="idpel" id="k7IdpelInput">
                                        </div>
                                        <div class="form-group">
                                            <label>Daya</label>
                                            <input type="text" name="daya" id="k7DayaInput">
                                        </div>
                                        <div class="form-group">
                                            <label>ULP</label>
                                            <input type="text" name="ulp" id="k7UlpInput">
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
                        <?php endif; ?>
                    <?php endif; ?>
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
        <?php elseif ($page === 'material_pending'): ?>
            <div id="materialPendingSection">
                <div class="card" style="margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <h3 style="color:#0b2b4a; margin: 0 0 4px 0;"><i class="fas fa-exclamation-triangle" style="color: #14828a; margin-right: 8px;"></i> Daftar Material Pending</h3>
                            <p class="text-small" style="margin:0;">Menampilkan material yang diminta oleh vendor tetapi belum seluruhnya terkirim/diterima.</p>
                        </div>
                        <a href="?page=home" class="btn-info" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <!-- TABLE 1 — DETAIL MATERIAL PENDING -->
                    <div class="card">
                        <form method="GET" style="margin: 0;">
                            <input type="hidden" name="page" value="material_pending">
                            <!-- Hidden inputs for Table 2 state to prevent resetting it -->
                            <input type="hidden" name="q2" value="<?= htmlspecialchars($q2) ?>">
                            <input type="hidden" name="start2" value="<?= htmlspecialchars($start2) ?>">
                            <input type="hidden" name="end2" value="<?= htmlspecialchars($end2) ?>">

                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 12px; border-bottom:1px solid #eee; padding-bottom:1rem;">
                                <h4 style="color:#0b2b4a; margin:0;"><i class="fas fa-list-ul" style="color:#14828a; margin-right:8px;"></i> Table 1 — Rincian Pending per Vendor</h4>
                                <a href="export_pending.php?table=rincian&q=<?= urlencode($q1) ?>&start=<?= urlencode($start1) ?>&end=<?= urlencode($end1) ?>" class="btn-success" style="text-decoration:none; padding: 0.5rem 1rem; border-radius: 20px; font-size: 13px; display: inline-flex; align-items: center; gap: 8px;"><i class="fas fa-file-excel"></i> Export Excel</a>
                            </div>

                            <div class="g2-search-filter-card">
                                <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                                    <div class="form-group" style="flex: 2; min-width: 240px; margin-bottom: 0;">
                                        <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Cari Rincian</label>
                                        <input type="text" name="q1" value="<?= htmlspecialchars($q1) ?>" placeholder="Cari material, vendor, TUG, pelanggan..." style="padding: 0.65rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                    </div>
                                    <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                        <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Dari Tanggal</label>
                                        <input type="date" name="start1" value="<?= htmlspecialchars($start1) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                    </div>
                                    <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                        <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Sampai Tanggal</label>
                                        <input type="date" name="end1" value="<?= htmlspecialchars($end1) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <button type="submit" class="btn-success" style="padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;"><i class="fas fa-search"></i> Cari</button>
                                        <?php if ($q1 !== '' || $start1 !== '' || $end1 !== ''): ?>
                                            <a href="?page=material_pending&q2=<?= urlencode($q2) ?>&start2=<?= urlencode($start2) ?>&end2=<?= urlencode($end2) ?>" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; background:#64748b; color:#fff; font-weight: 600;">Reset</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-wrap">
                            <table id="detailPendingTable">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">No</th>
                                        <th>Nama Material</th>
                                        <th>Jumlah Pending</th>
                                        <th>Vendor</th>
                                        <th>Nama Pelanggan</th>
                                        <th>Nomor TUG</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($detail_pending)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: #777; padding: 2rem;">Tidak ada rincian material pending saat ini.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($detail_pending as $i => $row): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><strong><?= htmlspecialchars($row['material_name']) ?></strong></td>
                                                <td><span class="badge" style="background-color: #ffeef0; color: #e11d48; font-weight: 700; padding: 4px 8px; border-radius: 8px; font-size: 13px;"><?= number_format($row['jumlah_pending']) ?></span></td>
                                                <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                                                <td><?= htmlspecialchars($row['customer_name'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($row['tug_number'] ?: '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TABLE 2 — REKAP MATERIAL PENDING -->
                    <div class="card">
                        <form method="GET" style="margin: 0;">
                            <input type="hidden" name="page" value="material_pending">
                            <!-- Hidden inputs for Table 1 state to prevent resetting it -->
                            <input type="hidden" name="q1" value="<?= htmlspecialchars($q1) ?>">
                            <input type="hidden" name="start1" value="<?= htmlspecialchars($start1) ?>">
                            <input type="hidden" name="end1" value="<?= htmlspecialchars($end1) ?>">

                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 12px; border-bottom:1px solid #eee; padding-bottom:1rem;">
                                <h4 style="color:#0b2b4a; margin:0;"><i class="fas fa-boxes" style="color:#14828a; margin-right:8px;"></i> Table 2 — Akumulasi Rekap per Material</h4>
                                <a href="export_pending.php?table=rekap&q=<?= urlencode($q2) ?>&start=<?= urlencode($start2) ?>&end=<?= urlencode($end2) ?>" class="btn-success" style="text-decoration:none; padding: 0.5rem 1rem; border-radius: 20px; font-size: 13px; display: inline-flex; align-items: center; gap: 8px;"><i class="fas fa-file-excel"></i> Export Excel</a>
                            </div>

                            <div class="g2-search-filter-card">
                                <div style="display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end;">
                                    <div class="form-group" style="flex: 2; min-width: 240px; margin-bottom: 0;">
                                        <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Cari Rekap</label>
                                        <input type="text" name="q2" value="<?= htmlspecialchars($q2) ?>" placeholder="Cari material..." style="padding: 0.65rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                    </div>
                                    <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                        <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Dari Tanggal</label>
                                        <input type="date" name="start2" value="<?= htmlspecialchars($start2) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                    </div>
                                    <div class="form-group" style="flex: 1; min-width: 150px; margin-bottom: 0;">
                                        <label style="font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 6px; display: block; text-transform: uppercase;">Sampai Tanggal</label>
                                        <input type="date" name="end2" value="<?= htmlspecialchars($end2) ?>" style="padding: 0.6rem 1rem; border-radius: 10px; border: 1px solid #cbd5e1; font-size: 14px; width: 100%;">
                                    </div>
                                    <div style="display: flex; gap: 8px;">
                                        <button type="submit" class="btn-success" style="padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; font-weight: 600; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;"><i class="fas fa-search"></i> Cari</button>
                                        <?php if ($q2 !== '' || $start2 !== '' || $end2 !== ''): ?>
                                            <a href="?page=material_pending&q1=<?= urlencode($q1) ?>&start1=<?= urlencode($start1) ?>&end1=<?= urlencode($end1) ?>" class="btn btn-info" style="text-decoration:none; display: inline-flex; align-items:center; justify-content:center; padding: 0.65rem 1.5rem; border-radius: 10px; font-size: 14px; background:#64748b; color:#fff; font-weight: 600;">Reset</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-wrap">
                            <table id="rekapPendingTable">
                                <thead>
                                    <tr>
                                        <th style="width: 8%;">No</th>
                                        <th>Nama Material</th>
                                        <th>Total Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rekap_pending)): ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; color: #777; padding: 2rem;">Tidak ada rekap material pending saat ini.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rekap_pending as $i => $row): ?>
                                            <tr>
                                                <td><?= $i + 1 ?></td>
                                                <td><strong><?= htmlspecialchars($row['material_name']) ?></strong></td>
                                                <td><span class="badge" style="background-color: #fffae6; color: #d97706; font-weight: 700; padding: 4px 8px; border-radius: 8px; font-size: 13px;"><?= number_format($row['total_pending']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- PANEL DETAIL & KELOLA SURAT GUDANG (GUDANG 2) - FULL PAGE VIEW -->
        <div id="g2DetailModal" style="display: none; margin-bottom: 24px;">
            <!-- Detail Header (Card) -->
            <div class="card" style="margin-bottom: 24px; padding: 1.5rem 2rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h3 style="color:#0b2b4a; margin: 0 0 4px 0; font-size: 1.5rem; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-file-invoice" id="g2ModalIcon" style="color: var(--blue);"></i> Detail Surat <span id="g2ModalTugTitle" style="color: var(--blue);"></span>
                        </h3>
                        <p class="text-small" style="margin:0; color: #64748b;">Kelola status penerimaan material, cetak dokumen, dan tanda tangan petugas.</p>
                    </div>
                    <button type="button" onclick="closeG2DetailModal()" class="btn-outline" style="display: inline-flex; align-items: center; gap: 8px; border: 1px solid #cbd5e1; color: #334155; background: #fff; padding: 0.65rem 1.5rem; border-radius: 10px; font-weight: 500; cursor: pointer; font-size: 14px; transition: all 0.2s;">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                    </button>
                </div>
            </div>
            
            <!-- Detail Body (Dynamic Ajax Container) -->
            <div class="card" style="padding: 2rem; background: #ffffff;">
                <!-- Hidden inputs & containers needed to satisfy original AJAX code -->
                <div style="display: none;">
                    <!-- DPB inputs/outputs -->
                    <input type="text" id="tugNumberInput">
                    <div id="dpbResult"></div>
                    <!-- K3 inputs/outputs -->
                    <input type="text" id="k3TugInput">
                    <div id="k3Result"></div>
                    <!-- K7 inputs/outputs -->
                    <input type="text" id="k7TugInput">
                    <div id="k7Result"></div>
                </div>

                <!-- Beautiful Content Wrapper shown to the user -->
                <div id="g2DetailContentContainer">
                    <div style="text-align: center; padding: 3rem 0; color: #64748b;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 2.5rem; color: var(--blue); margin-bottom: 1rem; display: block;"></i>
                        Membuka detail surat...
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /#dynamicContent -->

    <div class="site-footer-text" style="display:flex; justify-content:space-between; align-items:center; width:100%; font-size:11px; opacity:0.7;">
        <span>PLN Identity and Access Management</span>
        <span>Copyright &copy; Aurellia Mezaluna Azwa | Fatma Azzahra Alif Hidayah</span>
    </div>
</div><?php if ($is_admin || $is_vendor): ?>
        </div><!-- /.adm-content -->
    </div><!-- /.adm-main -->
</div><!-- /.adm-layout -->
<?php endif; ?><div id="authModal" class="modal <?= $openModal ? 'show' : '' ?>">
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

<?php if ($is_admin || $is_gudang2): ?>
<div id="materialEditModal" class="modal">
    <div class="modal-content" style="max-width:480px;">
        <span class="close" onclick="closeMaterialEditModal()">&times;</span>
        <h2><i class="fas fa-edit"></i> Edit Material</h2>
        <form method="POST" action="material.php" id="materialEditForm">
            <input type="hidden" name="material_id" id="editMaterialId">
            <div class="form-group">
                <label>Nama Material</label>
                <input type="text" name="material_name" id="editMaterialName" required>
            </div>
            <div class="form-group">
                <label>Normalisasi</label>
                <input type="text" name="material_norm" id="editMaterialNorm">
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

<!-- MODAL DETAIL PENGAJUAN VENDOR -->
<div id="appDetailModal" class="modal">
    <div class="modal-content" style="max-width: 540px;">
        <span class="close" onclick="closeAppDetailModal()">&times;</span>
        <h2 style="color:#0b2b4a;"><i class="fas fa-clipboard-list"></i> Detail Pengajuan Vendor</h2>
        <table class="detail-table" style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; width: 40%; text-align: left;">Nama PT</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="appDetName"></td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; text-align: left;">Alamat</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="appDetAddress"></td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; text-align: left;">Telepon</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="appDetPhone"></td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; text-align: left;">Email</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="appDetEmail"></td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; text-align: left;">Tanggal Pengajuan</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="appDetDate"></td>
            </tr>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; text-align: left;">Status</td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="appDetStatus"></td>
            </tr>
        </table>
        <div style="margin-top: 1.5rem; display: flex; gap: 8px; justify-content: flex-end;" id="appDetActions">
            <!-- Disetujui/Tolak loaded dynamically -->
        </div>
    </div>
</div>

<!-- MODAL DETAIL VENDOR -->
<div id="vendorDetailModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeVendorDetailModal()">&times;</span>
        <h2 style="color:#0b2b4a;"><i class="fas fa-building"></i> Profil &amp; Data Default Vendor</h2>
        <div style="max-height: 400px; overflow-y: auto; padding-right: 8px; margin-top: 1rem;">
            <table class="detail-table" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; width: 40%; text-align: left;">Nama PT</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="vDetName"></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; text-align: left;">Alamat</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="vDetAddress"></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; text-align: left;">Telepon</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="vDetPhone"></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; text-align: left;">Email</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="vDetEmail"></td>
                </tr>
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: 600; text-align: left;">Status</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: left;" id="vDetStatus"></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- MODAL EDIT VENDOR -->
<div id="vendorEditModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeVendorEditModal()">&times;</span>
        <h2 style="color:#0b2b4a;"><i class="fas fa-edit"></i> Edit Data &amp; Defaults Vendor</h2>
        <form method="POST" action="vendor.php" style="margin-top: 1rem;">
            <input type="hidden" name="vendor_id" id="editVendorId">
            <div class="flex-row">
                <div class="form-group">
                    <label>Nama PT</label>
                    <input type="text" name="vendor_name" id="editVendorName" required>
                </div>
                <div class="form-group">
                    <label>Alamat</label>
                    <input type="text" name="vendor_address" id="editVendorAddress">
                </div>
            </div>
            <div class="flex-row">
                <div class="form-group">
                    <label>Telepon</label>
                    <input type="text" name="vendor_phone" id="editVendorPhone">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="vendor_email" id="editVendorEmail" required>
                </div>
            </div>
            <div style="margin-top:1.5rem; display: flex; gap: 8px; justify-content: flex-end;">
                <button type="submit" name="edit_vendor" class="btn-success">Simpan Perubahan</button>
                <button type="button" class="btn-info" onclick="closeVendorEditModal()">Batal</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    window.AUTO_OPEN_MODAL = <?= $openModal ? json_encode($openModal) : 'false' ?>;
    window.IS_ADMIN = <?= ($is_admin || $is_gudang2) ? 'true' : 'false' ?>;
    window.IS_REAL_ADMIN = <?= $is_admin ? 'true' : 'false' ?>;
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

    function autofillSearchTug(tug) {
        <?php if ($page === 'dpb'): ?>
        var input = document.getElementById("tugNumberInput");
        if (input) {
            input.value = tug;
            loadDPB();
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        <?php elseif ($page === 'k3'): ?>
        var input = document.getElementById("k3TugInput");
        if (input) {
            input.value = tug;
            loadK3();
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        <?php elseif ($page === 'k7'): ?>
        var input = document.getElementById("k7TugInput");
        if (input) {
            input.value = tug;
            loadK7();
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        <?php endif; ?>
    }
</script>
<div id="printArea"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="js/script.js?v=<?= filemtime(__DIR__ . '/js/script.js') ?>"></script>

<?php if ($page === 'home' && ($is_admin || $is_vendor)): ?>
<script>
(function() {
    // 1. Chart Tren Bulanan
    var ctxTrend = document.getElementById('monthlyTrendChart');
    if (ctxTrend) {
        new Chart(ctxTrend.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                datasets: [
                    {
                        label: 'DPB',
                        data: <?= json_encode(array_values($chart_dpb)) ?>,
                        borderColor: '#1e8e5a',
                        backgroundColor: 'rgba(30, 142, 90, 0.05)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'K3',
                        data: <?= json_encode(array_values($chart_k3)) ?>,
                        borderColor: '#b78a00',
                        backgroundColor: 'rgba(183, 138, 0, 0.05)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'K7',
                        data: <?= json_encode(array_values($chart_k7)) ?>,
                        borderColor: '#8e1eff',
                        backgroundColor: 'rgba(142, 30, 255, 0.05)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: 'Poppins', size: 12 }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    <?php if ($is_admin): ?>
    // 2. Chart Top 5 Pending Materials (Admin Only)
    var ctxPending = document.getElementById('topPendingChart');
    if (ctxPending) {
        var topMaterials = <?= json_encode($top_pending_materials) ?>;
        var labels = topMaterials.map(function(item) { return item.material_name; });
        var data = topMaterials.map(function(item) { return parseInt(item.total_pending); });

        new Chart(ctxPending.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels.length > 0 ? labels : ['Tidak ada pending'],
                datasets: [{
                    label: 'Total Pending',
                    data: data.length > 0 ? data : [0],
                    backgroundColor: 'rgba(20, 130, 138, 0.7)',
                    borderColor: '#14828a',
                    borderWidth: 1.5,
                    borderRadius: 8
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
    <?php else: ?>
    // 3. Chart Top 5 Material Terbanyak Diajukan (Vendor Only)
    var ctxRequested = document.getElementById('requestedMaterialsChart');
    if (ctxRequested) {
        var topRequested = <?= json_encode($top_requested_materials ?? []) ?>;
        var labels = topRequested.map(function(item) { return item.material_name; });
        var data = topRequested.map(function(item) { return parseInt(item.total_requested); });

        new Chart(ctxRequested.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels.length > 0 ? labels : ['Belum ada pengajuan'],
                datasets: [{
                    label: 'Total Kuantitas Diajukan',
                    data: data.length > 0 ? data : [0],
                    backgroundColor: 'rgba(30, 142, 90, 0.7)',
                    borderColor: '#1e8e5a',
                    borderWidth: 1.5,
                    borderRadius: 8
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }
    <?php endif; ?>
})();
</script>
<?php endif; ?>

<?php if ($is_admin || $is_vendor): ?>
<!-- Mobile overlay & sidebar toggle logic -->
<div class="adm-drawer-overlay" id="admOverlay" onclick="admCloseSidebar()"></div>

<script>
(function () {
    var layout   = document.getElementById('admLayout');
    var sidebar  = document.getElementById('admSidebar');
    var topbar   = document.getElementById('admTopbar');
    var main     = document.getElementById('admMain');
    var isMobile = window.innerWidth <= 768;
    var isTablet = window.innerWidth <= 1024;

    var userCollapsed = false;

    /* ---- init state ---- */
    function initSidebar() {
        var prevMobile = isMobile;
        isMobile = window.innerWidth <= 768;
        isTablet = window.innerWidth <= 1024;

        if (prevMobile !== isMobile) {
            layout.classList.remove('drawer-open', 'sidebar-collapsed');
        }

        if (isMobile) {
            // Mobile: drawer tersembunyi
            sidebar.style.transform = '';
        } else {
            // Desktop & Tablet
            if (userCollapsed || isTablet) {
                layout.classList.add('sidebar-collapsed');
            } else {
                layout.classList.remove('sidebar-collapsed');
            }
        }
    }

    /* ---- toggle (tombol hamburger) ---- */
    window.admToggleSidebar = function () {
        isMobile = window.innerWidth <= 768;
        if (isMobile) {
            if (layout.classList.contains('drawer-open')) {
                layout.classList.remove('drawer-open');
            } else {
                layout.classList.add('drawer-open');
            }
        } else {
            // Desktop / Tablet
            if (layout.classList.contains('sidebar-collapsed')) {
                layout.classList.remove('sidebar-collapsed');
                userCollapsed = false;
            } else {
                layout.classList.add('sidebar-collapsed');
                userCollapsed = true;
            }
        }
    };

    /* ---- tutup drawer (klik overlay) ---- */
    window.admCloseSidebar = function () {
        layout.classList.remove('drawer-open');
    };

    /* ---- resize handler ---- */
    window.addEventListener('resize', function () {
        initSidebar();
    });

    initSidebar();
})();
</script>
<?php endif; ?>

<script>
    function openG2DetailModal() {
        var sections = document.querySelectorAll("#dynamicContent > div");
        sections.forEach(function(sec) {
            if (sec.id !== "g2DetailModal") {
                sec.style.display = "none";
            }
        });
        var modal = document.getElementById("g2DetailModal");
        if (modal) {
            modal.style.display = "block";
        }
    }

    function closeG2DetailModal() {
        var modal = document.getElementById("g2DetailModal");
        if (modal) {
            modal.style.display = "none";
        }
        // Determine which section to show based on URL 'page' parameter
        var urlParams = new URLSearchParams(window.location.search);
        var page = urlParams.get('page') || 'home';
        
        var targetId = "homeSection";
        if (page === 'dpb') targetId = "monitoringSection";
        else if (page === 'k3') targetId = "k3Section";
        else if (page === 'k7') targetId = "k7Section";
        else if (page === 'surat_jalan') targetId = "suratJalanSection";
        else if (page === 'material') targetId = "materialSection";
        else if (page === 'vendor') targetId = "vendorSection";
        else if (page === 'riwayat') targetId = "riwayatSection";
        else if (page === 'mdu') targetId = "mduSection";
        else if (page === 'material_pending') targetId = "materialPendingSection";
        
        var sec = document.getElementById(targetId);
        if (sec) {
            sec.style.display = "block";
        }
    }

    // Helper to monitor AJAX changes in hidden elements and render them beautifully
    function observeAndRenderGudangDetail(resultElementId, type) {
        var target = document.getElementById(resultElementId);
        if (!target) return;

        // Open modal first with loading indicator
        document.getElementById("g2DetailContentContainer").innerHTML = `
            <div style="text-align: center; padding: 3rem 0; color: #64748b;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2.5rem; color: var(--blue); margin-bottom: 1rem; display: block;"></i>
                Memuat data dari database...
            </div>
        `;
        openG2DetailModal();

        // Use MutationObserver or fallback to a fast poll to check when the Ajax finishes loading
        var checkInterval = setInterval(function() {
            // Original code injects HTML content into results container
            // When it changes from empty/default instructions to actual result cards, we extract and style it
            var html = target.innerHTML.trim();
            if (html && !html.includes("Masukkan nomor TUG") && !html.includes("secara otomatis") && !html.includes("Mencari data")) {
                clearInterval(checkInterval);
                
                // Set modal icon based on type
                var iconEl = document.getElementById("g2ModalIcon");
                if (type === 'dpb') {
                    iconEl.className = "fas fa-file-invoice";
                } else if (type === 'k3') {
                    iconEl.className = "fas fa-undo";
                } else {
                    iconEl.className = "fas fa-recycle";
                }

                // Render into our clean modal container
                var container = document.getElementById("g2DetailContentContainer");
                container.innerHTML = "";
                while (target.firstChild) {
                    container.appendChild(target.firstChild);
                }

                // Adjust any inner styling to fit inside the modal beautifully
                // 1. Remove unnecessary card styling or borders inside modal
                var innerCards = container.querySelectorAll('.card');
                innerCards.forEach(function(card) {
                    card.style.boxShadow = 'none';
                    card.style.border = '1px solid #e2e8f0';
                    card.style.margin = '0 0 1rem 0';
                });

                // 2. Style status badge
                var badges = container.querySelectorAll('.status-badge');
                badges.forEach(function(b) {
                    b.style.fontSize = '0.9rem';
                    b.style.padding = '0.5rem 1rem';
                });

                // 3. Make sure table wraps look good
                var tables = container.querySelectorAll('table');
                tables.forEach(function(t) {
                    t.style.width = '100%';
                    t.style.marginTop = '1rem';
                });

                // 4. Style buttons/actions inside detail
                var actionButtons = container.querySelectorAll('button, .btn, .btn-success, .btn-danger, .btn-warning, .btn-info');
                actionButtons.forEach(function(btn) {
                    // Make them larger and touch friendly
                    if (btn.style) {
                        btn.style.padding = '0.6rem 1.2rem';
                        btn.style.borderRadius = '10px';
                        btn.style.fontWeight = '600';
                    }
                });

                // Add print buttons bar inside the modal if print script exists
                if (type === 'dpb' || type === 'k3' || type === 'k7') {
                    var printPdfSection = document.createElement('div');
                    printPdfSection.style.marginTop = '2rem';
                    printPdfSection.style.paddingTop = '1.5rem';
                    printPdfSection.style.borderTop = '1px solid #e2e8f0';
                    printPdfSection.style.display = 'flex';
                    printPdfSection.style.gap = '12px';
                    printPdfSection.style.justifyContent = 'flex-end';
                    printPdfSection.style.flexWrap = 'wrap';

                    if (type === 'dpb') {
                        var printOfficialBtn = document.createElement('button');
                        printOfficialBtn.className = 'btn-warning';
                        printOfficialBtn.style.padding = '0.7rem 1.5rem';
                        printOfficialBtn.style.borderRadius = '10px';
                        printOfficialBtn.style.fontWeight = '600';
                        printOfficialBtn.innerHTML = '<i class="fas fa-print"></i> Cetak Surat Jalan';
                        printOfficialBtn.onclick = function() {
                            printDPB();
                        };
                        printPdfSection.appendChild(printOfficialBtn);

                        var printDpbBtn = document.createElement('button');
                        printDpbBtn.className = 'btn-warning';
                        printDpbBtn.style.padding = '0.7rem 1.5rem';
                        printDpbBtn.style.borderRadius = '10px';
                        printDpbBtn.style.fontWeight = '600';
                        printDpbBtn.innerHTML = '<i class="fas fa-print"></i> Cetak DPB';
                        printDpbBtn.onclick = function() {
                            window.open('printDPBForm.php?tug=' + encodeURIComponent(document.getElementById("g2ModalTugTitle").innerText), '_blank');
                        };
                        printPdfSection.appendChild(printDpbBtn);
                    } else if (type === 'k3') {
                        var printK3Btn = document.createElement('button');
                        printK3Btn.className = 'btn-warning';
                        printK3Btn.style.padding = '0.7rem 1.5rem';
                        printK3Btn.style.borderRadius = '10px';
                        printK3Btn.style.fontWeight = '600';
                        printK3Btn.innerHTML = '<i class="fas fa-print"></i> Cetak K3';
                        printK3Btn.onclick = function() {
                            window.open('printK3.php?tug=' + encodeURIComponent(document.getElementById("g2ModalTugTitle").innerText), '_blank');
                        };
                        printPdfSection.appendChild(printK3Btn);
                    } else if (type === 'k7') {
                        var printK7Btn = document.createElement('button');
                        printK7Btn.className = 'btn-warning';
                        printK7Btn.style.padding = '0.7rem 1.5rem';
                        printK7Btn.style.borderRadius = '10px';
                        printK7Btn.style.fontWeight = '600';
                        printK7Btn.innerHTML = '<i class="fas fa-print"></i> Cetak K7';
                        printK7Btn.onclick = function() {
                            window.open('printK7.php?tug=' + encodeURIComponent(document.getElementById("g2ModalTugTitle").innerText), '_blank');
                        };
                        printPdfSection.appendChild(printK7Btn);
                    }

                    container.appendChild(printPdfSection);
                }
            }
        }, 100);

        // Clear poll if modal is closed or after 10 seconds to avoid memory leaks
        setTimeout(function() {
            clearInterval(checkInterval);
        }, 10000);
    }

    function openGudangDpbDetail(tug) {
        document.getElementById("g2ModalTugTitle").innerText = tug;
        var input = document.getElementById("tugNumberInput");
        if (input) {
            input.value = tug;
            // Clear current output to trigger observer
            document.getElementById("dpbResult").innerHTML = "";
            // Load DPB via original AJAX
            loadDPB();
            // Start observer
            observeAndRenderGudangDetail("dpbResult", "dpb");
        }
    }

    function openGudangK3Detail(tug) {
        document.getElementById("g2ModalTugTitle").innerText = tug;
        var input = document.getElementById("k3TugInput");
        if (input) {
            input.value = tug;
            // Clear current output
            document.getElementById("k3Result").innerHTML = "";
            // Load K3 via original AJAX
            loadK3();
            // Start observer
            observeAndRenderGudangDetail("k3Result", "k3");
        }
    }

    function openGudangK7Detail(tug) {
        document.getElementById("g2ModalTugTitle").innerText = tug;
        var input = document.getElementById("k7TugInput");
        if (input) {
            input.value = tug;
            // Clear current output
            document.getElementById("k7Result").innerHTML = "";
            // Load K7 via original AJAX
            loadK7();
            // Start observer
            observeAndRenderGudangDetail("k7Result", "k7");
        }
    }
</script>

</body>
</html>
