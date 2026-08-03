<?php
/* =========================================================
   PLN · MATERIAL — FUNGSI BANTU (session, role, query)
   ========================================================= */

// Nama tetap untuk kolom tanda tangan "Setuju" & "Kepala Gudang" di form
// cetak DPB/K3/K7 (biasanya orangnya sama terus). Ubah di sini kalau berganti.
define('DEFAULT_SIGNER_SETUJU', 'GATOT HARIYANTO');
define('DEFAULT_SIGNER_KEPALA_GUDANG', 'MONIKA ROHMATUS S.');

// ---------- SESSION / ROLE ----------
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function isVendor() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'vendor';
}

// Admin Gudang 2: role khusus, hanya bisa cari nomor TUG dan kelola/cetak
// dokumen terkait (DPB/K3/K7). Tidak mendapat akses menu admin gudang 1
// (Vendor, Material, MDU, dsb) — dashboard-nya terpisah di gudang2.php.
function isGudang2() {
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'gudang2';
}

// vendor_id milik user yang sedang login (null jika admin / belum login)
function currentVendorId() {
    return $_SESSION['user_vendor_id'] ?? null;
}

// ---------- VENDOR ----------
function getVendors($db) {
    $stmt = $db->query("SELECT * FROM vendors ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getVendorById($db, $id) {
    $stmt = $db->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ---------- MATERIAL ----------
function getMaterials($db) {
    $stmt = $db->query("SELECT * FROM materials ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Autocomplete: cari material berdasarkan nama ATAU normalisasi (dipakai saat mengetik salah satu)
function searchMaterials($db, $q, $limit = 15) {
    $like = '%' . $q . '%';
    $stmt = $db->prepare("
        SELECT id, name, norm, unit, deskripsi
        FROM materials
        WHERE name ILIKE ? OR norm ILIKE ?
        ORDER BY
            CASE WHEN norm = ? THEN 0 WHEN name ILIKE ? THEN 1 ELSE 2 END,
            name
        LIMIT ?
    ");
    $stmt->execute([$like, $like, $q, $q . '%', $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- NORMALISASI (master kode dari file data/normalisasi.csv) ----------
// File CSV pakai delimiter ";" dan kolom: NAMA MATERIAL;NORMALISASI
// Di-cache di memori (per request) supaya file hanya dibaca sekali.
function loadNormalisasiMap() {
    static $map = null;
    if ($map !== null) return $map;

    $map = [];
    $path = __DIR__ . '/data/normalisasi.csv';
    if (!file_exists($path)) return $map;

    $fh = fopen($path, 'r');
    if (!$fh) return $map;

    $first = true;
    while (($row = fgetcsv($fh, 0, ';', '"')) !== false) {
        if (count($row) < 2) continue;
        $name = trim($row[0]);
        $norm = trim($row[1]);
        if ($first) {
            // buang BOM UTF-8 kalau ada di kolom pertama header
            $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
            $first = false;
            // baris header, bukan data -> skip
            if (strtoupper($name) === 'NAMA MATERIAL') continue;
        }
        if ($name === '') continue;
        // key lowercase utk pencarian case-insensitive, tapi simpan nama asli utk ditampilkan
        $map[mb_strtolower($name)] = ['name' => $name, 'norm' => $norm];
    }
    fclose($fh);
    return $map;
}

// Cari kode normalisasi dari CSV berdasarkan nama (exact match, case-insensitive)
function findNormFromCsv($name) {
    $map = loadNormalisasiMap();
    $key = mb_strtolower(trim($name));
    return isset($map[$key]) ? $map[$key]['norm'] : null;
}

// Data lengkap [{name, norm}] dari CSV, dipakai untuk autocomplete di sisi client (JS)
function getNormalisasiData() {
    $map = loadNormalisasiMap();
    return array_values($map);
}

// dipanggil saat material baru dibuat otomatis (dari pengajuan DPB/K3/K7) tanpa norm manual.
// Prioritas: cocokkan dulu ke data resmi CSV normalisasi, baru fallback ke hash kalau tidak ketemu.
function generateNorm($name) {
    $fromCsv = findNormFromCsv($name);
    if ($fromCsv !== null && $fromCsv !== '') {
        return $fromCsv;
    }
    return strtoupper(substr(md5($name), 0, 7));
}

// ---------- DPB ----------
// Ambil 1 transaksi DPB lengkap berdasarkan nomor TUG (pencarian otomatis)
function getDpbByTug($db, $tug) {
    $stmt = $db->prepare("
        SELECT d.*, v.name AS vendor_name, v.address AS vendor_address
        FROM dpb_transactions d
        LEFT JOIN vendors v ON d.vendor_id = v.id
        WHERE d.tug_number = ?
    ");
    $stmt->execute([$tug]);
    $dpb = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dpb) return null;

    $stmt = $db->prepare("
        SELECT di.*, m.name AS material_name, m.norm, m.unit
        FROM dpb_items di
        LEFT JOIN materials m ON di.material_id = m.id
        WHERE di.dpb_id = ?
        ORDER BY di.id
    ");
    $stmt->execute([$dpb['id']]);
    $dpb['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $dpb;
}

function dpbStatusLabel($status) {
    switch ($status) {
        case 'menunggu_persetujuan': return 'Menunggu Persetujuan';
        case 'ditolak':     return 'Ditolak';
        case 'aktif':       return 'Sudah Jalan / Aktif';
        case 'selesai':     return 'Selesai';
        case 'belum_jalan':
        default:            return 'Belum Jalan';
    }
}

// Daftar DPB yang masih menunggu persetujuan admin (dipakai di panel khusus admin)
function getPendingDpbs($db) {
    $stmt = $db->query("
        SELECT d.id, d.tug_number, d.customer_name, d.tanggal_diminta, v.name AS vendor_name
        FROM dpb_transactions d
        LEFT JOIN vendors v ON d.vendor_id = v.id
        WHERE d.status = 'menunggu_persetujuan'
        ORDER BY d.tanggal_diminta ASC, d.id ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- K3 (BON PENGEMBALIAN MATERIAL) ----------
function getK3ByTug($db, $tug) {
    $stmt = $db->prepare("
        SELECT k.*, v.name AS vendor_name, v.address AS vendor_address
        FROM k3_transactions k
        LEFT JOIN vendors v ON k.vendor_id = v.id
        WHERE k.tug_number = ?
    ");
    $stmt->execute([$tug]);
    $k3 = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$k3) return null;

    $stmt = $db->prepare("
        SELECT ki.*, m.name AS material_name, m.norm, m.unit
        FROM k3_items ki
        LEFT JOIN materials m ON ki.material_id = m.id
        WHERE ki.k3_id = ?
        ORDER BY ki.id
    ");
    $stmt->execute([$k3['id']]);
    $k3['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $k3;
}

function kondisiMaterialLabel($kondisi) {
    switch ($kondisi) {
        case 'rusak':                     return 'Rusak';
        case 'baru':                      return 'Baru';
        case 'garansi':                   return 'Garansi';
        case 'masih_dapat_dipergunakan':
        default:                          return 'Masih Dapat Dipergunakan';
    }
}

// ---------- K7 (BON PEMAKAIAN MATERIAL BEKAS) ----------
function getK7ByTug($db, $tug) {
    $stmt = $db->prepare("
        SELECT k.*, v.name AS vendor_name, v.address AS vendor_address
        FROM k7_transactions k
        LEFT JOIN vendors v ON k.vendor_id = v.id
        WHERE k.tug_number = ?
    ");
    $stmt->execute([$tug]);
    $k7 = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$k7) return null;

    $stmt = $db->prepare("
        SELECT ki.*, m.name AS material_name, m.norm, m.unit
        FROM k7_items ki
        LEFT JOIN materials m ON ki.material_id = m.id
        WHERE ki.k7_id = ?
        ORDER BY ki.id
    ");
    $stmt->execute([$k7['id']]);
    $k7['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $k7;
}

// Dipakai khusus oleh admin gudang PLN.
function searchMduByMaterial($db, $q) {
    $like = '%' . trim($q) . '%';
    $stmt = $db->prepare("
        SELECT
            v.name AS vendor_name,
            d.tug_number,
            d.tanggal_diminta,
            d.status,
            m.name AS material_name,
            m.unit,
            di.quantity_requested,
            di.quantity_received
        FROM dpb_items di
        JOIN dpb_transactions d ON di.dpb_id = d.id
        JOIN vendors v ON d.vendor_id = v.id
        LEFT JOIN materials m ON di.material_id = m.id
        WHERE m.name ILIKE ?
        ORDER BY m.name, d.tanggal_diminta DESC, d.tug_number, di.id
    ");
    $stmt->execute([$like]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- RIWAYAT PENGAJUAN VENDOR ----------
// Gabungan seluruh pengajuan (DPB + K3 + K7) milik satu vendor, diurutkan
// dari yang paling baru. Dipakai di menu "Riwayat" khusus login vendor.
// $startDate / $endDate (format 'YYYY-MM-DD') opsional untuk filter rentang tanggal.
function getVendorHistory($db, $vendorId, $startDate = null, $endDate = null) {
    $params = [$vendorId, $vendorId, $vendorId];
    $dateFilter = '';
    if ($startDate && $endDate) {
        $dateFilter = ' AND tanggal_diminta BETWEEN ? AND ?';
    }

    $sql = "
        SELECT 'DPB' AS jenis, tug_number, customer_name, tanggal_diminta, status
        FROM dpb_transactions WHERE vendor_id = ?$dateFilter
        UNION ALL
        SELECT 'K3' AS jenis, tug_number, customer_name, tanggal_diminta, status
        FROM k3_transactions WHERE vendor_id = ?$dateFilter
        UNION ALL
        SELECT 'K7' AS jenis, tug_number, customer_name, tanggal_diminta, status
        FROM k7_transactions WHERE vendor_id = ?$dateFilter
        ORDER BY tanggal_diminta DESC, tug_number DESC
    ";

    // susun parameter sesuai urutan placeholder tiap blok UNION (vendor_id, lalu tanggal jika ada)
    $finalParams = [];
    foreach ($params as $vId) {
        $finalParams[] = $vId;
        if ($dateFilter) {
            $finalParams[] = $startDate;
            $finalParams[] = $endDate;
        }
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($finalParams);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Sama seperti getVendorHistory, tapi untuk SEMUA vendor sekaligus (khusus admin).
// Menyertakan nama vendor di setiap baris karena tidak difilter per vendor.
function getAllHistory($db, $startDate = null, $endDate = null) {
    $dateFilterDpb = $dateFilterK3 = $dateFilterK7 = '';
    $params = [];
    if ($startDate && $endDate) {
        $dateFilterDpb = ' WHERE d.tanggal_diminta BETWEEN ? AND ?';
        $dateFilterK3  = ' WHERE k.tanggal_diminta BETWEEN ? AND ?';
        $dateFilterK7  = ' WHERE k.tanggal_diminta BETWEEN ? AND ?';
    }

    $sql = "
        SELECT 'DPB' AS jenis, d.tug_number, d.customer_name, d.tanggal_diminta, d.status, v.name AS vendor_name
        FROM dpb_transactions d LEFT JOIN vendors v ON d.vendor_id = v.id$dateFilterDpb
        UNION ALL
        SELECT 'K3' AS jenis, k.tug_number, k.customer_name, k.tanggal_diminta, k.status, v.name AS vendor_name
        FROM k3_transactions k LEFT JOIN vendors v ON k.vendor_id = v.id$dateFilterK3
        UNION ALL
        SELECT 'K7' AS jenis, k.tug_number, k.customer_name, k.tanggal_diminta, k.status, v.name AS vendor_name
        FROM k7_transactions k LEFT JOIN vendors v ON k.vendor_id = v.id$dateFilterK7
        ORDER BY tanggal_diminta DESC, tug_number DESC
    ";

    if ($startDate && $endDate) {
        $params = [$startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>