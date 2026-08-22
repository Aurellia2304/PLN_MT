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
    $stmt = $db->query("
        SELECT m.*, 
               COALESCE((
                   SELECT SUM(CASE WHEN di.quantity_requested > di.quantity_received THEN di.quantity_requested - di.quantity_received ELSE 0 END)
                   FROM dpb_items di
                   JOIN dpb_transactions d ON di.dpb_id = d.id
                   WHERE di.material_id = m.id
                     AND d.status != 'selesai'
               ), 0) AS daftung
        FROM materials m 
        ORDER BY m.name
    ");
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

function generateNextSuratJalanNumber($db, $tanggal) {
    $d = strtotime($tanggal);
    $ROMAWI_BULAN = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    $bulanRomawi = $ROMAWI_BULAN[(int)date('n', $d) - 1];
    $tahun = date('Y', $d);
    
    // Ambil seluruh nomor surat jalan yang sesuai untuk mencari nilai seq tertinggi secara akurat
    $stmt = $db->prepare("
        SELECT surat_jalan_number 
        FROM dpb_transactions 
        WHERE surat_jalan_number LIKE '%/LOG.08.03/GD. ARIES/%'
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $maxSeq = -1;
    foreach ($rows as $row) {
        $num = $row['surat_jalan_number'];
        $parts = explode('/', $num);
        if (count($parts) > 0) {
            $seq = (int)$parts[0];
            if ($seq > $maxSeq) {
                $maxSeq = $seq;
            }
        }
    }
    
    $nextSeq = $maxSeq + 1;
    
    return sprintf('%04d', $nextSeq) . "/LOG.08.03/GD. ARIES/" . $bulanRomawi . "/" . $tahun;
}

function generateNextK3SuratJalanNumber($db, $tanggal) {
    $d = strtotime($tanggal);
    $ROMAWI_BULAN = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    $bulanRomawi = $ROMAWI_BULAN[(int)date('n', $d) - 1];
    $tahun = date('Y', $d);
    
    $stmt = $db->prepare("
        SELECT surat_jalan_number 
        FROM k3_transactions 
        WHERE surat_jalan_number LIKE '%/K3/LOG.08.03/GD. ARIES/%'
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $maxSeq = -1;
    foreach ($rows as $row) {
        $num = $row['surat_jalan_number'];
        if ($num) {
            $parts = explode('/', $num);
            if (count($parts) > 0) {
                $seq = (int)$parts[0];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }
    }
    
    $nextSeq = $maxSeq + 1;
    return sprintf('%04d', $nextSeq) . "/K3/LOG.08.03/GD. ARIES/" . $bulanRomawi . "/" . $tahun;
}

function generateNextK7SuratJalanNumber($db, $tanggal) {
    $d = strtotime($tanggal);
    $ROMAWI_BULAN = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    $bulanRomawi = $ROMAWI_BULAN[(int)date('n', $d) - 1];
    $tahun = date('Y', $d);
    
    $stmt = $db->prepare("
        SELECT surat_jalan_number 
        FROM k7_transactions 
        WHERE surat_jalan_number LIKE '%/K7/LOG.08.03/GD. ARIES/%'
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $maxSeq = -1;
    foreach ($rows as $row) {
        $num = $row['surat_jalan_number'];
        if ($num) {
            $parts = explode('/', $num);
            if (count($parts) > 0) {
                $seq = (int)$parts[0];
                if ($seq > $maxSeq) {
                    $maxSeq = $seq;
                }
            }
        }
    }
    
    $nextSeq = $maxSeq + 1;
    return sprintf('%04d', $nextSeq) . "/K7/LOG.08.03/GD. ARIES/" . $bulanRomawi . "/" . $tahun;
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
        SELECT di.*, m.name AS material_name, m.norm, m.unit, m.stock AS material_stock
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
        case 'selesai':     return 'Selesai';
        case 'aktif':       return 'Belum Selesai';
        case 'cancel':      return 'Cancel';
        case 'belum_jalan':
        default:            return 'Aktif';
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

// ---------- AUTO-GENERASI NOMOR TUG ----------
function getNextTugNumber($db, $type) {
    $currentYear = date('y');
    if ($type === 'dpb') {
        $prefix = 'TUG5MLG' . $currentYear . '-';
        $table = 'dpb_transactions';
    } elseif ($type === 'k3') {
        $prefix = 'TUG10MLG' . $currentYear . '-';
        $table = 'k3_transactions';
    } elseif ($type === 'k7') {
        $prefix = 'TUG5NSMLG' . $currentYear . '-';
        $table = 'k7_transactions';
    } else {
        return '';
    }

    // Query untuk mengambil nomor TUG terakhir dengan prefix tahun ini
    $query = "SELECT tug_number FROM {$table} WHERE tug_number LIKE :pattern ORDER BY tug_number DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':pattern' => $prefix . '%']);
    $lastTug = $stmt->fetchColumn();

    if (!$lastTug) {
        return $prefix . '0001';
    }

    // Mengambil 4 digit angka terakhir dari nomor TUG
    $parts = explode('-', $lastTug);
    $lastNum = (int) end($parts);
    $nextNum = $lastNum + 1;
    
    return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

// ---------- RENDER PHP PAGINATION ----------
function renderPhpPagination($currentPage, $totalPages, $urlParamName) {
    if ($totalPages <= 1) return '';
    
    $html = '<div class="pagination-wrap" style="display:inline-flex; border:1px solid #dbe4ec; border-radius:12px; overflow:hidden; box-shadow:0 4px 12px rgba(11,43,74,0.03);">';
    
    // Previous Arrow
    if ($currentPage > 1) {
        $html .= '<a href="?' . http_build_query(array_merge($_GET, [$urlParamName => $currentPage - 1])) . '" class="page-btn page-arrow" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">&larr;</a>';
    }
    
    // Page Numbers
    $pages = [];
    for ($i = 1; $i <= min(4, $totalPages); $i++) $pages[$i] = true;
    for ($i = max(1, $totalPages - 2); $i <= $totalPages; $i++) $pages[$i] = true;
    for ($i = max(1, $currentPage - 1); $i <= min($totalPages, $currentPage + 1); $i++) $pages[$i] = true;
    
    $sorted_pages = array_keys($pages);
    sort($sorted_pages);
    
    $last_val = 0;
    foreach ($sorted_pages as $p) {
        if ($last_val > 0 && $p - $last_val > 1) {
            $html .= '<span class="page-ellipsis" style="min-width:44px; height:44px; display:inline-flex; align-items:center; justify-content:center; background:#fff; border-right:1px solid #dbe4ec; color:#718096; font-size:0.9rem;">...</span>';
        }
        $activeClass = ($p === $currentPage) ? ' active' : '';
        $html .= '<a href="?' . http_build_query(array_merge($_GET, [$urlParamName => $p])) . '" class="page-btn' . $activeClass . '" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">' . $p . '</a>';
        $last_val = $p;
    }
    
    // Next Arrow
    if ($currentPage < $totalPages) {
        $html .= '<a href="?' . http_build_query(array_merge($_GET, [$urlParamName => $currentPage + 1])) . '" class="page-btn page-arrow" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">&rarr;</a>';
    }
    
    $html .= '</div>';
    return $html;
}

function generateNextManualSuratJalanNumber($db, $tanggal) {
    return generateNextSuratJalanNumber($db, $tanggal);
}

function isMaterialWajibSN($name) {
    $whitelist = [
        'MTR;kWH E-PR;;1P;230V;5-60A;1;;2W',
        'MTR;kWH E;;1P;230V;5-60A;1;;2W',
        'MCB;230/400V;1P;2A;50Hz;',
        'MCB;230/400V;1P;4A;50Hz;',
        'MCB;230/400V;1P;6A;50Hz;',
        'MCB;230/400V;1P;10A;50Hz;',
        'MCB;230/400V;1P;16A;50Hz;',
        'MCB;230/400V;1P;20A;50Hz;',
        'MCB;230/400V;1P;25A;50Hz;',
        'MCB;230/400V;1P;35A;50Hz;',
        'MCB;230/400V;1P;50A;50Hz;',
        'MCB;230/400V;3P;20A;50Hz;',
        'MTR;kWH E;;3P;230/400V;5-80A;1;;4W',
        'MCB;230/400V;3P;35A;50Hz;',
        'MTR;kWHE;;3P;57.7/100V-230/400;5A;0.5;4W',
        'MCB;230/400V;3P;16A;50Hz;',
        'MTR;kWH E-PR;;3P;230/400V;5-80A;1;;4W',
        'MCB;230/400V;3P;25A;50Hz;',
        'TRF DIS;D3;20kV/400V;3P;100kVA;YZN5;OD',
        'TRF DIS;D3;20kV/400V;3P;160kVA;YZN5;OD',
        'TRF DIS;D3;20kV/400V;3P;250kVA;DYN5;OD',
        'MCB;230/400V;3P;10A;50Hz;',
        'LVSB;DIST;3P;400V;250A;2LINE;OD',
        'LVSB;DIST;3P;400V;400A;4LINE;OD',
        'BOX 53KVA - BOX;APPMCCB80A+STRIP;AL2MM;1205X420X250',
        'BOX 66KVA - BOX;APPMCCB100A+STRIP;AL2MM;1205X420X250',
        'BOX 82,5 KVA - BOX;APPMCCB125A+STRIP;AL2MM;1205X420X250',
        'BOX 105 KVA - BOX;APPMCCB160A+STRIP;AL2MM;1205X420X250',
        'BOX 131 KVA - BOX;APPMCCB200A+STRIP;AL2MM;1205X420X250',
        'BOX 147 KVA - BOX;APPMCCB225A+STRIP;AL2MM;1205X420X250',
        'BOX 164 KVA - BOX;APPMCCB250A+STRIP;AL2MM;1205X420X250',
        'BOX 197 KVA - BOX;APPMCCB300A+STRIP;AL2MM;1205X420X250',
        'BOX TR - BOX;APP PL CB;AL1.6MM;650X400X220MM'
    ];
    $normalizedName = trim(strtoupper($name));
    foreach ($whitelist as $item) {
        if (trim(strtoupper($item)) === $normalizedName) {
            return true;
        }
    }
    return false;
}

function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken) || empty($token)) {
        return false;
    }
    return hash_equals($sessionToken, $token);
}
?>