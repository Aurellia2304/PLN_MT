<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$tug = trim($_GET['tug'] ?? '');
$dpb = $tug !== '' ? getDpbByTug($db, $tug) : null;

if (!$dpb) {
    die('Data DPB dengan nomor TUG tersebut tidak ditemukan. <a href="index.php?page=dpb">Kembali</a>');
}
if (isVendor() && (int)$dpb['vendor_id'] !== (int)currentVendorId()) {
    die('Anda tidak memiliki akses untuk mencetak DPB ini.');
}

$ROMAWI_BULAN = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
$d = $dpb['tanggal_diminta'] ? strtotime($dpb['tanggal_diminta']) : time();
$noSurat = '............. /LOG.00.02/GD. ARIES/' . $ROMAWI_BULAN[(int)date('n', $d) - 1] . '/' . date('Y', $d);

$items = $dpb['items'];
while (count($items) < 10) { $items[] = null; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Angkutan - <?= htmlspecialchars($dpb['tug_number']) ?></title>
<style>
  * { box-sizing:border-box; }
  @page { size: A4; margin: 10mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size:12px; color:#000; margin:0; padding:20px; background:#ddd; }
  .toolbar { max-width:900px; margin:0 auto 14px; display:flex; gap:10px; }
  .toolbar button, .toolbar a {
    font-size:14px; padding:8px 18px; border-radius:30px; border:none; cursor:pointer;
    font-weight:700; text-decoration:none; display:inline-flex; align-items:center;
  }
  .btn-print { background:#ffd966; color:#082038; }
  .btn-back { background:#eee; color:#333; }

  .sheet { background:#fff; max-width:900px; margin:0 auto; padding:20px 26px; border:1px solid #999; }

  .head-row { display:flex; justify-content:space-between; align-items:flex-start; }
  .head-left { font-size:12px; font-weight:700; line-height:1.5; }
  .head-right {
    border:1px solid #000; font-size:9.5px; text-align:center; width:220px;
  }
  .head-right .row1 { border-bottom:1px solid #000; padding:3px; }
  .head-right .row2 { padding:5px 8px; font-weight:700; }

  .title-block { text-align:center; margin:14px 0 10px; }
  .title-block h1 { font-size:17px; margin:0 0 2px; text-decoration:underline; letter-spacing:1px; }
  .title-block .no-surat { font-size:11px; }

  table.info-table { width:100%; border-collapse:collapse; margin-bottom:10px; }
  table.info-table td { vertical-align:top; padding:2px 4px; font-size:11.5px; }
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

  table.items { width:100%; border-collapse:collapse; margin:10px 0; }
  table.items th, table.items td { border:1px solid #000; padding:4px 6px; font-size:11px; text-align:center; }
  table.items th { font-weight:700; }
  table.items td.left { text-align:left; }

  table.bottom-frame { width:100%; border-collapse:collapse; margin-top:-1px; }
  table.bottom-frame td { border:1px solid #000; padding:6px 10px; font-size:11.5px; vertical-align:top; }
  .tug-box { text-align:center; }
  .tug-box .tug-number { color:#c00; font-weight:800; font-size:16px; line-height:1.3; border:2px solid #c00; padding:8px 14px; display:inline-block; }

  table.daya-frame { width:100%; border-collapse:collapse; margin-top:-1px; }
  table.daya-frame td { border:1px solid #000; padding:6px 10px; font-size:11.5px; vertical-align:top; }

  .receive-row { display:flex; justify-content:space-between; margin:16px 0 6px; font-size:11.5px; }
  .mengetahui { text-align:center; font-weight:700; margin:10px 0 30px; font-size:11.5px; }

  .sign-grid { display:flex; justify-content:space-between; gap:16px; }
  .sign-col { flex:1; text-align:left; font-size:11.5px; }
  .sign-role { font-weight:700; margin:0 0 34px; }
  .sign-name { border-top:1px solid #000; padding-top:4px; min-height:14px; }

  @media print {
    body { background:#fff; padding:0; }
    .toolbar { display:none; }
    .sheet { border:none; max-width:100%; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <a href="index.php?page=dpb&tug=<?= urlencode($dpb['tug_number']) ?>" class="btn-back">&larr; Kembali</a>
  <button class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<div class="sheet">

  <div class="head-row">
    <div class="head-left">
      PT PLN (PERSERO)<br>
      UNIT INDUK DISTRIBUSI (UID) JAWA TIMUR<br>
      UNIT PELAKSANA PELAYANAN PELANGGAN (UP3) MALANG
    </div>
    <div class="head-right">
      <div class="row1">1. Pengantar &nbsp; 2. Security &nbsp; 3. Pengambil material</div>
      <div class="row2">PERHATIAN :<br>SEMUA RESIKO SETELAH MATERIAL KELUAR DARI LOGISTIK, MENJADI TANGGUNG JAWAB PENGAMBIL MATERIAL</div>
    </div>
  </div>

  <div class="title-block">
    <h1>SURAT ANGKUTAN</h1>
    <div class="no-surat"><?= htmlspecialchars($noSurat) ?></div>
  </div>

  <table class="info-table">
    <tr>
      <td style="width:55%;">
        <div class="info-line"><span class="info-label">Kendaraan No.</span><span class="info-colon">:</span><span class="info-value"><span class="dotted">&nbsp;</span></span></div>
        <div class="info-line"><span class="info-label">Nama Pengemudi</span><span class="info-colon">:</span><span class="info-value"><span class="dotted">&nbsp;</span></span></div>
        <div class="info-line"><span class="info-label">Dari Logistik</span><span class="info-colon">:</span><span class="info-value">UP3 MALANG</span></div>
        <div class="info-line"><span class="info-label">SPK NO.</span><span class="info-colon">:</span><span class="info-value underline-val"><?= htmlspecialchars($dpb['spk_number'] ?: '-') ?></span></div>
      </td>
      <td style="width:45%;" class="bold-right">
        <?= htmlspecialchars($dpb['vendor_name'] ?: '-') ?><br><br>
        <?= htmlspecialchars($dpb['ulp'] ?: '-') ?><br><br>
        <?= htmlspecialchars($dpb['customer_name'] ?: '-') ?>
      </td>
    </tr>
  </table>

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
      <?php foreach ($items as $i => $it): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td class="left"><?= $it ? htmlspecialchars($it['material_name'] ?? '') : '' ?></td>
        <td><?= $it ? htmlspecialchars($it['norm'] ?? '') : '' ?></td>
        <td><?= $it ? htmlspecialchars($it['unit'] ?? '') : '' ?></td>
        <td><?= $it !== null ? (int)($it['quantity_received'] ?? 0) : '' ?></td>
        <td><?= $it !== null ? (int)($it['quantity_requested'] ?? 0) : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="bottom-frame">
    <tr>
      <td style="width:70%;">
        <div class="bottom-line"><span class="info-label">VENDOR</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($dpb['vendor_name'] ?: '-') ?></span></div>
        <div class="bottom-line"><span class="info-label">NO. SPK</span><span class="info-colon">:</span><span class="info-value underline-val"><?= htmlspecialchars($dpb['spk_number'] ?: '-') ?></span></div>
        <div style="height:8px;"></div>
        <div class="bottom-line"><span class="info-label">JENIS PEKERJAAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($dpb['jenis_pekerjaan'] ?: '-') ?></span></div>
        <div class="bottom-line"><span class="info-label">IDPEL</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($dpb['idpel'] ?: '-') ?></span></div>
        <div class="bottom-line"><span class="info-label">NAMA PELANGGAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($dpb['customer_name'] ?: '-') ?></span></div>
        <div class="bottom-line"><span class="info-label">ALAMAT PELANGGAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($dpb['customer_address'] ?: '-') ?></span></div>
      </td>
      <td class="tug-box" style="width:30%;">
        <div class="tug-number"><?= htmlspecialchars($dpb['tug_number']) ?></div>
      </td>
    </tr>
  </table>

  <table class="daya-frame">
    <tr>
      <td style="width:70%;">
        <div class="bottom-line"><span class="info-label">DAYA</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($dpb['daya'] ?: '-') ?></span></div>
        <div class="bottom-line"><span class="info-label">ULP</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($dpb['ulp'] ?: '-') ?></span></div>
      </td>
      <td style="width:30%;">&nbsp;</td>
    </tr>
  </table>

  <div class="receive-row">
    <div>Diterima tgl <?= $dpb['diterima_tgl'] ? htmlspecialchars(date('d-m-Y', strtotime($dpb['diterima_tgl']))) : '.......................' ?></div>
    <div>Malang, .......................</div>
  </div>

  <div class="mengetahui">Mengetahui,</div>

  <div class="sign-grid">
    <div class="sign-col">
      <p class="sign-role">Penerima :</p>
      <div class="sign-name"><?= htmlspecialchars($dpb['penerima_name'] ?: '') ?>&nbsp;</div>
    </div>
    <div class="sign-col">
      <p class="sign-role">Security :</p>
      <div class="sign-name"><?= htmlspecialchars($dpb['security_name'] ?: '') ?>&nbsp;</div>
    </div>
    <div class="sign-col">
      <p class="sign-role">Yang Menyerahkan :</p>
      <div class="sign-name"><?= htmlspecialchars($dpb['menyerahkan_name'] ?: '') ?>&nbsp;</div>
    </div>
  </div>

</div>

</body>
</html>