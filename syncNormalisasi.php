<?php
/* =========================================================
   SINKRON ULANG NORMALISASI — jalankan sekali (atau kapan saja)
   untuk MENGHAPUS kode normalisasi lama pada seluruh material lalu
   mengisinya kembali dari data resmi terbaru di data/normalisasi.csv
   (file ini di-generate dari kolom "Material" = kode normalisasi dan
   "Material Description" = nama material, di file Excel scanning).

   Cara pakai: login sebagai admin, lalu buka file ini di browser:
       https://domain-anda/syncNormalisasi.php
   Sebagai pengaman, proses HAPUS + isi ulang baru benar-benar
   dijalankan setelah menekan tombol konfirmasi (?confirm=1), supaya
   tidak tidak sengaja terpicu.
   ========================================================= */
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn() || !isAdmin()) {
    die("Hanya admin gudang PLN yang dapat menjalankan sinkronisasi ini.");
}

$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === '1';

$updated = [];
$notFound = [];
$skippedConflict = [];
$totalMaterials = 0;

if ($confirmed) {
    $normalisasiMap = loadNormalisasiMap(); // ['nama lowercase' => ['name'=>.., 'norm'=>..]]

    try {
        $db->beginTransaction();

        // 1) HAPUS semua kode normalisasi LAMA pada seluruh material
        //    (bukan cuma yang kosong — ini beda dari versi sebelumnya).
        $db->exec("UPDATE materials SET norm = NULL");

        // 2) Ambil ulang semua material, lalu isi norm BARU dari data/normalisasi.csv
        //    (hasil terbaru dari file Excel scanning), dicocokkan lewat nama material
        //    (case-insensitive, exact match).
        $stmt = $db->query("SELECT id, name FROM materials ORDER BY name");
        $allMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalMaterials = count($allMaterials);

        foreach ($allMaterials as $m) {
            $key = mb_strtolower(trim($m['name']));
            if (!isset($normalisasiMap[$key])) {
                $notFound[] = $m['name'];
                continue;
            }
            $newNorm = $normalisasiMap[$key]['norm'];
            if ($newNorm === '') {
                $notFound[] = $m['name'] . ' (kode di CSV juga kosong)';
                continue;
            }

            try {
                $upd = $db->prepare("UPDATE materials SET norm = ? WHERE id = ?");
                $upd->execute([$newNorm, $m['id']]);
                $updated[] = $m['name'] . ' → ' . $newNorm;
            } catch (Exception $e) {
                // kode normalisasi itu sudah dipakai material lain (unique constraint)
                $skippedConflict[] = $m['name'] . ' (kode ' . $newNorm . ' sudah dipakai material lain)';
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        die("Gagal menjalankan sinkronisasi: " . htmlspecialchars($e->getMessage()));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Sinkronisasi Normalisasi</title>
<style>
body{font-family:Arial,sans-serif;max-width:800px;margin:2rem auto;padding:0 1rem;color:#1f4460;}
h2{color:#0b2b4a;}
.ok{color:#1e8e5a;}
.warn{color:#a35b00;}
.err{color:#d64545;}
ul{line-height:1.6;}
a.back{display:inline-block;margin-top:1.5rem;text-decoration:none;color:#14828a;font-weight:600;}
.confirm-box{background:#fdf1e0;border:1px solid #f0d9a6;border-radius:12px;padding:1.2rem 1.4rem;color:#a35b00;}
.confirm-btn{display:inline-block;margin-top:1rem;background:#d64545;color:#fff;text-decoration:none;font-weight:700;padding:0.7rem 1.4rem;border-radius:30px;}
.confirm-btn:hover{filter:brightness(1.07);}
</style>
</head>
<body>

<?php if (!$confirmed): ?>

    <h2>Sinkronisasi Ulang Kode Normalisasi</h2>
    <div class="confirm-box">
        <strong>Perhatian:</strong> proses ini akan <strong>menghapus semua kode normalisasi lama</strong>
        pada seluruh material, lalu mengisinya kembali dari data terbaru di
        <code>data/normalisasi.csv</code> (dicocokkan berdasarkan nama material).
        Material yang namanya tidak ditemukan di data terbaru akan kehilangan kode
        normalisasinya (kosong) sampai diisi manual.
        <br><br>
        Pastikan file <code>data/normalisasi.csv</code> sudah diperbarui dengan data Excel yang benar sebelum melanjutkan.
        <br>
        <a class="confirm-btn" href="?confirm=1">Ya, Hapus &amp; Sinkronkan Ulang Sekarang</a>
    </div>

<?php else: ?>

    <h2>Hasil Sinkronisasi Ulang Kode Normalisasi</h2>
    <p>Total material diproses: <strong><?= $totalMaterials ?></strong></p>

    <p class="ok"><strong><?= count($updated) ?></strong> material berhasil diperbarui kode normalisasinya.</p>
    <?php if ($updated): ?>
    <ul>
        <?php foreach ($updated as $u): ?>
        <li><?= htmlspecialchars($u) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($skippedConflict): ?>
    <p class="err"><strong><?= count($skippedConflict) ?></strong> material dilewati karena kode normalisasi barunya sudah dipakai material lain (perlu dicek manual):</p>
    <ul>
        <?php foreach ($skippedConflict as $s): ?>
        <li><?= htmlspecialchars($s) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($notFound): ?>
    <p class="warn"><strong><?= count($notFound) ?></strong> material tidak ditemukan namanya di data/normalisasi.csv (norm sekarang kosong, perlu diisi manual):</p>
    <ul>
        <?php foreach ($notFound as $n): ?>
        <li><?= htmlspecialchars($n) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

<?php endif; ?>

    <a class="back" href="index.php?page=material">&larr; Kembali ke halaman Material</a>
</body>
</html>