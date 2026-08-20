<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
if (isVendor()) {
    die('Akses ditolak: Vendor tidak diperbolehkan mengakses dokumen Surat Jalan.');
}

$tug = trim($_GET['tug'] ?? '');
$k3 = $tug !== '' ? getK3ByTug($db, $tug) : null;

if (!$k3) {
    die('Data K3 dengan nomor TUG tersebut tidak ditemukan. <a href="index.php?page=k3">Kembali</a>');
}
if (isVendor() && (int)$k3['vendor_id'] !== (int)currentVendorId()) {
    die('Anda tidak memiliki akses untuk mencetak data K3 ini.');
}

if (empty($k3['surat_jalan_number'])) {
    $sjNumber = generateNextK3SuratJalanNumber($db, $k3['tanggal_diminta'] ?: date('Y-m-d'));
    $stmt = $db->prepare("UPDATE k3_transactions SET surat_jalan_number = ? WHERE id = ?");
    $stmt->execute([$sjNumber, $k3['id']]);
    $k3['surat_jalan_number'] = $sjNumber;
}
$noSurat = $k3['surat_jalan_number'];

$items = $k3['items'] ?: [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Angkutan K3 - <?= htmlspecialchars($k3['surat_jalan_number'] ?: $k3['tug_number']) ?></title>
<style>
  * { box-sizing:border-box; }
  @page { size: A4 portrait; margin: 5mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size:11px; color:#000; margin:0; padding:10px; background:#ddd; }
  .toolbar { max-width:900px; margin:0 auto 14px; display:flex; gap:10px; }
  .toolbar button, .toolbar a {
    font-size:14px; padding:8px 18px; border-radius:30px; border:none; cursor:pointer;
    font-weight:700; text-decoration:none; display:inline-flex; align-items:center;
  }
  .btn-print { background:#ffd966; color:#082038; }
  .btn-back { background:#eee; color:#333; }

  .sheet { background:#fff; max-width:900px; margin:0 auto 20px; padding:12px 18px; border:1px solid #999; }
  .page-break { page-break-after: always; break-after: page; }

  .head-row { display:flex; justify-content:space-between; align-items:flex-start; }
  .head-left { font-size:11px; font-weight:700; line-height:1.4; }
  .head-right {
    border:1px solid #000; font-size:9.5px; text-align:center; width:220px;
  }
  .head-right .row1 { border-bottom:1px solid #000; padding:2px; }
  .head-right .row2 { padding:4px 6px; font-weight:700; }

  .title-block { text-align:center; margin:8px 0 6px; }
  .title-block h1 { font-size:16px; margin:0 0 2px; text-decoration:underline; letter-spacing:1px; }
  .title-block .no-surat { font-size:10.5px; }

  table.info-table { width:100%; border-collapse:collapse; margin-bottom:6px; }
  table.info-table td { vertical-align:top; padding:1px 4px; font-size:10.5px; }
  .dotted { border-bottom:1px dotted #000; display:inline-block; min-width:170px; }
  .bold-right { font-weight:700; text-align:right; }
  .underline-val { text-decoration:underline; }

  .info-line { display:flex; align-items:baseline; margin-bottom:1px; }
  .info-line .info-label { flex-shrink:0; width:110px; }
  .info-line .info-colon { flex-shrink:0; width:12px; }
  .info-line .info-value { flex:1; }

  .bottom-line { display:flex; align-items:baseline; margin-bottom:1px; }
  .bottom-line .info-label { flex-shrink:0; width:150px; }
  .bottom-line .info-colon { flex-shrink:0; width:12px; }
  .bottom-line .info-value { flex:1; }

  table.items { width:100%; border-collapse:collapse; margin:6px 0; }
  table.items th, table.items td { border:1px solid #000; padding:3px 5px; font-size:10.5px; text-align:center; }
  table.items th { font-weight:700; }
  table.items td.left { text-align:left; }

  table.bottom-frame { width:100%; border-collapse:collapse; margin-top:-1px; }
  table.bottom-frame td { border:1px solid #000; padding:4px 8px; font-size:10.5px; vertical-align:top; }
  .tug-box { text-align:center; }
  .tug-box .tug-number { color:#c00; font-weight:800; font-size:14px; line-height:1.2; border:2px solid #c00; padding:6px 12px; display:inline-block; }

  table.daya-frame { width:100%; border-collapse:collapse; margin-top:-1px; }
  table.daya-frame td { border:1px solid #000; padding:4px 8px; font-size:10.5px; vertical-align:top; }

  .receive-row { display:flex; justify-content:space-between; margin:8px 0 4px; font-size:10.5px; }
  .mengetahui { text-align:center; font-weight:700; margin:6px 0 16px; font-size:10.5px; }

  .sign-grid { display:flex; justify-content:space-between; gap:16px; }
  .sign-col { flex:1; text-align:left; font-size:10.5px; }
  .sign-role { font-weight:700; margin:0 0 20px; }
  .sign-name { border-top:1px solid #000; padding-top:4px; min-height:14px; }

  .header-section {
    margin-bottom: 8px;
  }

  @media print {
    body { background:#fff; padding:0; margin:0; }
    .toolbar { display:none; }
    .sheet { border:none; max-width:100%; padding: 4px 6px; box-shadow:none; margin:0 auto; page-break-after:always; break-after:page; }
    .sheet:last-of-type { page-break-after: avoid; break-after: avoid; }
    .page-break { page-break-after: always; break-after: page; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <a href="index.php?page=k3&tug=<?= urlencode($k3['tug_number']) ?>" class="btn-back">&larr; Kembali</a>
  <button class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<?php
$chunks = array_chunk($items, 10);
$totalPageCount = count($chunks);
if ($totalPageCount === 0) {
    $chunks = [[null]];
    $totalPageCount = 1;
}
foreach ($chunks as $pageIndex => $pageItems):
    while (count($pageItems) < 10) {
        $pageItems[] = null;
    }
?>
<div class="sheet">

  <div class="header-section">
    <div class="head-row">
      <div class="head-left" style="display: flex; align-items: flex-start; gap: 8px;">
        <img src="images/logoPln.png?v=2" style="width: 42px; height: auto; margin-top: 2px;">
        <div style="font-size: 11px; font-weight: 700; line-height: 1.4;">
          PT PLN (PERSERO)<br>
          UNIT INDUK DISTRIBUSI (UID) JAWA TIMUR<br>
          UNIT PELAKSANA PELAYANAN PELANGGAN (UP3) MALANG
        </div>
      </div>
      <div class="head-right">
        <div class="row1">1. Pengantar &nbsp; 2. Security &nbsp; 3. Pengambil material</div>
        <div class="row2">PERHATIAN :<br>SEMUA RESIKO SETELAH MATERIAL KELUAR DARI LOGISTIK, MENJADI TANGGUNG JAWAB PENGAMBIL MATERIAL</div>
      </div>
    </div>

    <div class="title-block">
      <h1>SURAT ANGKUTAN (K3)</h1>
      <div class="no-surat"><?= htmlspecialchars($noSurat) ?></div>
    </div>

    <table class="info-table">
      <tr>
        <td style="width:55%;">
          <div class="info-line"><span class="info-label">Kendaraan No.</span><span class="info-colon">:</span><span class="info-value"><span class="dotted">&nbsp;</span></span></div>
          <div class="info-line"><span class="info-label">Nama Pengemudi</span><span class="info-colon">:</span><span class="info-value"><span class="dotted">&nbsp;</span></span></div>
          <div class="info-line"><span class="info-label">Dari Logistik</span><span class="info-colon">:</span><span class="info-value">UP3 MALANG</span></div>
          <div class="info-line"><span class="info-label">SPK NO.</span><span class="info-colon">:</span><span class="info-value underline-val"><?= htmlspecialchars($k3['spk_number'] ?: '-') ?></span></div>
        </td>
        <td style="width:45%;" class="bold-right">
          <?= htmlspecialchars($k3['vendor_name'] ?: '-') ?><br><br>
          -<br><br>
          <?= htmlspecialchars($k3['customer_name'] ?: '-') ?>
        </td>
      </tr>
    </table>
  </div>

  <table class="items">
    <thead>
      <tr>
        <th rowspan="2" style="width:5%;">No.<br>Urut</th>
        <th rowspan="2" style="width:27%;">Nama Barang<br>(ditulis selengkap - lengkapnya)</th>
        <th rowspan="2" style="width:11%;">No.<br>Normalisasi</th>
        <th rowspan="2" style="width:8%;">Satuan</th>
        <th colspan="2">Banyaknya</th>
      </tr>
      <tr>
        <th style="width:20%;">yang diberikan<br>dengan angka</th>
        <th style="width:20%;">yang diminta<br>dengan angka</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pageItems as $i => $it): 
          $itemNum = ($pageIndex * 10) + $i + 1;
      ?>
      <tr>
        <td><?= $itemNum ?></td>
        <td class="left"><?= $it ? htmlspecialchars($it['material_name'] ?? '') : '' ?></td>
        <td><?= $it ? htmlspecialchars($it['norm'] ?? '') : '' ?></td>
        <td><?= $it ? htmlspecialchars($it['unit'] ?? '') : '' ?></td>
        <td><?= $it !== null ? (int)($it['quantity_received'] ?? 0) : '' ?></td>
        <td><?= $it !== null ? (int)($it['quantity_returned'] ?? 0) : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="bottom-frame">
    <tr>
      <td style="width:70%;">
        <div class="bottom-line"><span class="info-label">VENDOR</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['vendor_name'] ?: '-') ?></span></div>
        <div class="bottom-line"><span class="info-label">NO. SPK</span><span class="info-colon">:</span><span class="info-value underline-val"><?= htmlspecialchars($k3['spk_number'] ?: '-') ?></span></div>
        <div style="height:8px;"></div>
        <div class="bottom-line"><span class="info-label">JENIS PEKERJAAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['jenis_pekerjaan'] ?: '-') ?></span></div>
        <div class="bottom-line"><span class="info-label">IDPEL</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['idpel'] ?: '-') ?></span></div>
        <div class="bottom-line"><span class="info-label">NAMA PELANGGAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['customer_name'] ?: '-') ?></span></div>
        <div class="bottom-line"><span class="info-label">ALAMAT PELANGGAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['customer_address'] ?: '-') ?></span></div>
      </td>
      <td class="tug-box" style="width:30%;">
        <div class="tug-number"><?= htmlspecialchars($k3['tug_number']) ?></div>
      </td>
    </tr>
  </table>

  <table class="daya-frame">
    <tr>
      <td style="width:70%;">
        <div class="bottom-line"><span class="info-label">DAYA</span><span class="info-colon">:</span><span class="info-value">-</span></div>
        <div class="bottom-line"><span class="info-label">ULP</span><span class="info-colon">:</span><span class="info-value">-</span></div>
      </td>
      <td style="width:30%;">&nbsp;</td>
    </tr>
  </table>

  <div class="receive-row">
    <div>Diterima di: <?= htmlspecialchars($k3['diterima_tgl'] ?: '.......................') ?></div>
    <div>Malang, <?= $k3['malang_tanggal'] ? htmlspecialchars(date('d-m-Y', strtotime($k3['malang_tanggal']))) : '.......................' ?></div>
  </div>

  <div class="mengetahui">Mengetahui,</div>

  <div class="sign-grid">
    <div class="sign-col">
      <p class="sign-role">Penerima :</p>
      <div class="sign-name"><?= htmlspecialchars($k3['kepala_gudang_name'] ?: '') ?>&nbsp;</div>
    </div>
    <div class="sign-col">
      <p class="sign-role">Security :</p>
      <div class="sign-name">&nbsp;</div>
    </div>
    <div class="sign-col">
      <p class="sign-role">Yang Menyerahkan :</p>
      <div class="sign-name"><?= htmlspecialchars($k3['yang_menyerahkan_name'] ?: '') ?>&nbsp;</div>
    </div>
  </div>

  <div style="text-align: right; margin-top: 8px; font-size: 9px; color: #666;">
    Halaman <?= ($pageIndex + 1) ?> dari <?= $totalPageCount ?>
  </div>

</div>
<?php if ($pageIndex < $totalPageCount - 1): ?>
<div class="page-break"></div>
<?php endif; ?>
<?php endforeach; ?>

</body>
</html>
