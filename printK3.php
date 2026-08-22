<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
if (isGudang2()) {
    die('Akses ditolak: Petugas Gudang tidak diperbolehkan mengakses dokumen TUG.');
}

$tug = trim($_GET['tug'] ?? '');
$k3 = $tug !== '' ? getK3ByTug($db, $tug) : null;

if (!$k3) {
    die('Data K3 dengan nomor TUG tersebut tidak ditemukan. <a href="index.php?page=k3">Kembali</a>');
}
if (isVendor() && (int)$k3['vendor_id'] !== (int)currentVendorId()) {
    die('Anda tidak memiliki akses untuk mencetak data K3 ini.');
}

function terbilangID($n) {
    $n = (int) abs($n);
    $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    $f = function ($num) use (&$f, $satuan) {
        if ($num < 12) return $satuan[$num];
        if ($num < 20) return $f($num - 10) . ' belas';
        if ($num < 100) return $f(intdiv($num, 10)) . ' puluh' . ($num % 10 ? ' ' . $f($num % 10) : '');
        if ($num < 200) return 'seratus' . ($num % 100 ? ' ' . $f($num % 100) : '');
        if ($num < 1000) return $f(intdiv($num, 100)) . ' ratus' . ($num % 100 ? ' ' . $f($num % 100) : '');
        if ($num < 2000) return 'seribu' . ($num % 1000 ? ' ' . $f($num % 1000) : '');
        if ($num < 1000000) return $f(intdiv($num, 1000)) . ' ribu' . ($num % 1000 ? ' ' . $f($num % 1000) : '');
        return $f(intdiv($num, 1000000)) . ' juta' . ($num % 1000000 ? ' ' . $f($num % 1000000) : '');
    };
    return $n === 0 ? 'Nol' : ucfirst($f($n));
}

$d = $k3['tanggal_diminta'] ? strtotime($k3['tanggal_diminta']) : time();
$items = $k3['items'] ?: [];

// "TUG 10" (besar kiri) diambil dari kata pertama nomor TUG
$tugParts = explode('.', $k3['tug_number'], 2);
$tugBig = $tugParts[0] ?? 'TUG';

$kondisiList = [
    'rusak'                    => 'Rusak',
    'masih_dapat_dipergunakan' => 'Masih dapat dipergunakan',
    'baru'                     => 'Baru',
    'garansi'                  => 'Garansi',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak K3 - <?= htmlspecialchars($k3['tug_number']) ?></title>
<style>
  .info-line { display:flex; align-items:baseline; margin-bottom:1px; }
  .info-line .info-label { flex-shrink:0; width:150px; }
  .info-line .info-colon { flex-shrink:0; width:12px; }
  .info-line .info-value { flex:1; }
  * { box-sizing:border-box; }
  @page { size: A4 landscape; margin: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size:11px; color:#000; margin:0; padding:20px; background:#ddd; }
  .toolbar { max-width:1080px; margin:0 auto 12px; display:flex; gap:10px; }
  .toolbar button, .toolbar a {
    font-size:14px; padding:8px 18px; border-radius:30px; border:none; cursor:pointer;
    font-weight:700; text-decoration:none; display:inline-flex; align-items:center;
  }
  .btn-print { background:#ffd966; color:#082038; }
  .btn-back { background:#eee; color:#333; }

  .sheet { background:#fff; max-width:1080px; margin:0 auto 20px; border:1px solid #333; padding: 12px; }
  .page-break { page-break-after: always; break-after: page; }

  table.frame { width:100%; border-collapse:collapse; }
  table.frame td { border:1px solid #333; padding:4px 8px; vertical-align:top; }

  .tug-title { font-size:26px; font-weight:800; text-align:center; }
  .title-cell { text-align:center; }
  .title-cell h1 { font-size:22px; margin:2px 0; letter-spacing:0.5px; }
  .tug-box { text-align:center; }
  .tug-box .tug-number { color:#c00; font-weight:800; font-size:17px; line-height:1.2; }

  .date-grid { width:100%; border-collapse:collapse; font-size:9.5px; text-align:center; }
  .date-grid td { border:1px solid #333; padding:2px 4px; }

  table.items { width:100%; border-collapse:collapse; margin-top:-1px; }
  table.items th, table.items td { border:1px solid #333; padding:3px 5px; font-size:10px; text-align:center; }
  table.items td.left { text-align:left; }

  .sign-row td { vertical-align:top; padding-top:6px; }
  .sign-role { text-align:left; font-weight:700; margin:0 0 25px; }
  .sign-name { font-weight:700; border-top:1px solid #333; margin-top:0; padding-top:4px; text-transform:uppercase; text-align:center; min-height:16px; }

  .kj-box { display:inline-block; border:1px solid #333; width:15px; height:15px; margin-right:2px; }

  .kondisi-list div.active { font-weight:800; }

  .stabilo-masih_dapat_dipergunakan { background-color: #ffff00 !important; color: #000 !important; font-weight: bold; padding: 2px 6px; border-radius: 3px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .stabilo-rusak { background-color: #ff4d4d !important; color: #000 !important; font-weight: bold; padding: 2px 6px; border-radius: 3px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .stabilo-baru { background-color: #00ff00 !important; color: #000 !important; font-weight: bold; padding: 2px 6px; border-radius: 3px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .stabilo-garansi { background-color: #33ccff !important; color: #000 !important; font-weight: bold; padding: 2px 6px; border-radius: 3px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  .header-section {
    background-color: #fffbe6 !important; /* Kuning muda pastel */
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    padding: 8px 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    border: 1px solid #ffe58f;
  }
  .header-section table.frame, 
  .header-section table.frame td {
    background: transparent !important;
  }

  @media print {
    body { background:#fff; padding:0; margin: 10mm; }
    .toolbar { display:none; }
    .sheet { border:none; max-width:100%; box-shadow:none; margin:0 auto; padding:4px !important; page-break-after:always; break-after:page; }
    .sheet:last-of-type { page-break-after: avoid; break-after: avoid; }
    .header-section { border: 1px solid #ffe58f; }
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
    <table class="frame">
      <tr>
        <td class="tug-title" rowspan="2" style="width:13%; vertical-align:middle;"><?= htmlspecialchars($tugBig) ?></td>
        <td class="title-cell" style="width:57%; padding: 4px;">
          <div style="font-size:10px; font-weight:700; margin-bottom: 2px;">PT. PLN (PERSERO) UID JATIM UP3 MALANG</div>
          <h1 style="font-size:16px; margin:2px 0;">BON PENGEMBALIAN MATERIAL</h1>
        </td>
        <td class="tug-box" rowspan="2" style="width:30%; vertical-align:middle;">
          <div style="font-size:9.5px; font-weight:700; margin-bottom:2px;">Untuk TUG (Tata Usaha Gudang)</div>
          <div class="tug-number" style="font-size:15px;"><?= htmlspecialchars($k3['tug_number']) ?></div>
        </td>
      </tr>
      <tr>
        <td style="font-size:9.5px; text-align:center; padding:3px;">
          <strong>Tanggal Diminta:</strong> <?= date('d-m-Y', $d) ?>
        </td>
      </tr>
    </table>

    <table class="frame" style="margin-top:-1px;">
      <tr>
        <td style="width:57%;">
          Kepada &nbsp;: PT PLN (Persero) UP3 Malang<br>
          Gudang &nbsp;: <?= htmlspecialchars($k3['gudang_pengembalian'] ?: 'Gudang PLN Aries Munandar') ?><br>
          Alamat &nbsp;: Jl. Aries Munandar No. 77A Malang
        </td>
        <td style="width:43%;">
          Pengiriman dari :<br>
          <strong><?= htmlspecialchars($k3['vendor_name'] ?: '-') ?></strong><br>
          <?= htmlspecialchars($k3['vendor_address'] ?: '') ?><br>
          <span style="font-size:9.5px;">Kode Jurnal</span><br>
          <?php for ($i = 0; $i < 6; $i++): ?><span class="kj-box"></span><?php endfor; ?>
        </td>
      </tr>
    </table>
  </div>

  <table class="items">
    <thead>
      <tr>
        <th style="width:4%;">No.<br>Urut</th>
        <th style="width:32%;">Nama Barang<br>(ditulis selengkap - lengkapnya)</th>
        <th style="width:10%;">No.<br>Normalisasi</th>
        <th style="width:5%;">Sa-<br>tuan</th>
        <th style="width:10%;">Banyaknya Dikembalikan</th>
        <th style="width:10%;">Banyaknya Diterima</th>
        <th style="width:6%;">Kode</th>
        <th style="width:10%;">Harga<br>Satuan</th>
        <th style="width:13%;">Jumlah Uang<br>Rp.</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pageItems as $i => $it):
          $itemNum = ($pageIndex * 10) + $i + 1;
          $qr = $it ? (int)($it['quantity_returned'] ?? 0) : null;
          $qd = $it ? (int)($it['quantity_received'] ?? 0) : null;
          $harga = $it ? (float)($it['harga_satuan'] ?? 0) : 0;
          $jumlah = $it ? ($qd ?: $qr) * $harga : 0;
      ?>
      <tr>
        <td><?= $itemNum ?></td>
        <td class="left"><?= $it ? htmlspecialchars($it['material_name'] ?? '') : '' ?></td>
        <td><?= $it ? htmlspecialchars($it['norm'] ?? '') : '' ?></td>
        <td><?= $it ? htmlspecialchars($it['unit'] ?? '') : '' ?></td>
        <td><?= $it !== null ? $qr : '' ?></td>
        <td><?= $it !== null ? $qd : '' ?></td>
        <td><?= $it ? htmlspecialchars($it['kode'] ?? '') : '' ?></td>
        <td><?= $it && $harga > 0 ? number_format($harga, 0, ',', '.') : '' ?></td>
        <td><?= $it && $jumlah > 0 ? number_format($jumlah, 0, ',', '.') : '' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="frame">
    <tr>
      <td style="width:70%;">
        <div class="info-line"><span class="info-label">VENDOR</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['vendor_name'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">NO. SPK</span><span class="info-colon">:</span><span class="info-value" style="text-decoration:underline;"><?= htmlspecialchars($k3['spk_number'] ?: '-') ?></span></div>
        <div style="height:8px;"></div>
        <div class="info-line"><span class="info-label">JENIS PEKERJAAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['jenis_pekerjaan'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">IDPEL</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['idpel'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">NAMA PELANGGAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['customer_name'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">ALAMAT PELANGGAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['customer_address'] ?: '-') ?></span></div>
      </td>
      <td style="width:30%;">
        <div class="info-line" style="margin-bottom:6px;"><span class="info-label" style="width:100px;">NOMOR SERI</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['nomor_seri'] ?? '') ?>&nbsp;</span></div>
        <?php 
        $kondisiKey = $k3['kondisi_material'] ?? '';
        $kondisiLabel = $kondisiList[$kondisiKey] ?? '';
        $stabiloClass = in_array($kondisiKey, ['rusak', 'masih_dapat_dipergunakan', 'baru', 'garansi']) ? 'stabilo-' . $kondisiKey : '';
        ?>
        <div class="info-line" style="margin-bottom:6px;">
          <span class="info-label" style="width:100px;">KONDISI MATERIAL</span>
          <span class="info-colon">:</span>
          <span class="info-value">
            <?php if ($stabiloClass): ?>
              <span class="<?= $stabiloClass ?>"><?= htmlspecialchars($kondisiLabel) ?></span>
            <?php else: ?>
              <?= htmlspecialchars($kondisiLabel) ?>
            <?php endif; ?>
          </span>
        </div>
        <div class="info-line" style="margin-bottom:6px;"><span class="info-label" style="width:100px;">KETERANGAN DETILE</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['keterangan'] ?: '') ?>&nbsp;</span></div>
        <div class="info-line" style="margin-bottom:6px;"><span class="info-label" style="width:100px;">NO. DPB / BUKTI</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['no_dpb_bukti'] ?? '') ?>&nbsp;</span></div>
        <div class="info-line"><span class="info-label" style="width:100px;">LOKASI PENEMPATAN<br>MATERIAL/DIPAKAI</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k3['lokasi_penempatan'] ?? '') ?>&nbsp;</span></div>
      </td>
    </tr>
  </table>

  <table class="frame" style="margin-top:-1px;">
    <tr>
      <td style="width:14%;">
        Banyak jenis<br>barang :
      </td>
      <td style="width:26%;">
        Sifat pekerjaan<br>
        <span style="font-size:9.5px;">Pas.Baru/Perluasan/Perbaikan/Pamel/JBST<br><span style="text-decoration:line-through;">Pembongk.</span></span>
      </td>
      <td style="width:14%;">
        No. PK :<br><br>
        No. PDL ..................
      </td>
      <td style="width:24%;">
        No. Urut<br>
        SKI/SKP/PKP/PFK
        <table style="margin-top:2px;"><tr>
          <?php for ($i = 0; $i < 5; $i++): ?><td style="border:1px solid #333; width:14px; height:14px;"></td><?php endfor; ?>
        </tr></table>
        <div style="font-size:9px; margin-top:2px;">No. P. K</div>
        <table><tr>
          <?php for ($i = 0; $i < 5; $i++): ?><td style="border:1px solid #333; width:14px; height:14px;"></td><?php endfor; ?>
        </tr></table>
      </td>
      <td style="width:22%; text-align:center;">
        KODE PERKIRAAN
        <table style="margin-top:4px; margin-left:auto; margin-right:auto;"><tr>
          <?php for ($i = 0; $i < 5; $i++): ?><td style="border:1px solid #333; width:14px; height:14px;"></td><?php endfor; ?>
        </tr></table>
      </td>
    </tr>
  </table>

  <div class="receive-row" style="display:flex; justify-content:space-between; margin:8px 0 4px; font-size:10.5px; padding:0 10px;">
    <div>Diterima di: <?= htmlspecialchars($k3['diterima_tgl'] ?: '.......................') ?></div>
    <div>Malang, <?= $k3['malang_tanggal'] ? htmlspecialchars(date('d-m-Y', strtotime($k3['malang_tanggal']))) : '.......................' ?></div>
  </div>

  <table class="frame" style="margin-top:-1px;">
    <tr class="sign-row">
      <td style="width:25%;">
        <div class="sign-role">Setuju :</div>
        <div style="text-align:center; font-size:9.5px;">Asman Konstruksi</div>
        <div class="sign-name"><?= htmlspecialchars($k3['setuju_name'] ?? '') ?></div>
      </td>
      <td style="width:25%;">
        <div class="sign-role">Kepala Gudang :</div>
        <div class="sign-name"><?= htmlspecialchars($k3['kepala_gudang_name'] ?? '') ?></div>
      </td>
      <td style="width:25%;">
        <div class="sign-role">Pemeriksa &nbsp;&nbsp;Pengawas</div>
        <div class="sign-name"><?= htmlspecialchars($k3['pemeriksa_pengawas_name'] ?? '') ?>&nbsp;</div>
      </td>
      <td style="width:25%;">
        <div class="sign-role">Yang Menyerahkan : <?= htmlspecialchars($k3['vendor_name'] ?: '') ?></div>
        <div class="sign-name"><?= htmlspecialchars($k3['yang_menyerahkan_name'] ?? '') ?>&nbsp;</div>
      </td>
    </tr>
  </table>

  <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px; font-size: 9px; color: #666; padding: 0 10px;">
    <span>* TUG (Tata Usaha Gudang)</span>
    <span>Halaman <?= ($pageIndex + 1) ?> dari <?= $totalPageCount ?></span>
  </div>

</div>
<?php if ($pageIndex < $totalPageCount - 1): ?>
<div class="page-break"></div>
<?php endif; ?>
<?php endforeach; ?>

</body>
</html>