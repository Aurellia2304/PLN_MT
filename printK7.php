<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$tug = trim($_GET['tug'] ?? '');
$k7 = $tug !== '' ? getK7ByTug($db, $tug) : null;

if (!$k7) {
    die('Data K7 dengan nomor TUG tersebut tidak ditemukan. <a href="index.php?page=k7">Kembali</a>');
}
if (isVendor() && (int)$k7['vendor_id'] !== (int)currentVendorId()) {
    die('Anda tidak memiliki akses untuk mencetak data K7 ini.');
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

$d = $k7['tanggal_diminta'] ? strtotime($k7['tanggal_diminta']) : time();
$items = $k7['items'];
while (count($items) < 10) { $items[] = null; }

// "TUG 5" (besar kiri) diambil dari kata pertama nomor TUG
$tugParts = explode('.', $k7['tug_number'], 2);
$tugBig = $tugParts[0] ?? 'TUG';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak K7 - <?= htmlspecialchars($k7['tug_number']) ?></title>
<style>
  .info-line { display:flex; align-items:baseline; margin-bottom:1px; }
  .info-line .info-label { flex-shrink:0; width:150px; }
  .info-line .info-colon { flex-shrink:0; width:12px; }
  .info-line .info-value { flex:1; }
  * { box-sizing:border-box; }
  @page { size: A4 landscape; margin: 10mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size:11px; color:#000; margin:0; padding:20px; background:#ddd; }
  .toolbar { max-width:1080px; margin:0 auto 12px; display:flex; gap:10px; }
  .toolbar button, .toolbar a {
    font-size:14px; padding:8px 18px; border-radius:30px; border:none; cursor:pointer;
    font-weight:700; text-decoration:none; display:inline-flex; align-items:center;
  }
  .btn-print { background:#ffd966; color:#082038; }
  .btn-back { background:#eee; color:#333; }

  .sheet { background:#fff; max-width:1080px; margin:0 auto; border:1px solid #333; }
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
  .sign-role { text-align:left; font-weight:700; margin:0 0 40px; }
  .sign-name { font-weight:700; border-top:1px solid #333; margin-top:0; padding-top:4px; text-transform:uppercase; text-align:center; min-height:16px; }

  .kj-box { display:inline-block; border:1px solid #333; width:15px; height:15px; margin-right:2px; }

  .header-section {
    background-color: #fff0f0 !important; /* Merah muda pastel */
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 15px;
    border: 1px solid #ffccc7;
  }
  .header-section table.frame, 
  .header-section table.frame td {
    background: transparent !important;
  }

  @media print {
    body { background:#fff; padding:0; }
    .toolbar { display:none; }
    .sheet { border:none; }
    .header-section { border: 1px solid #ffccc7; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <a href="index.php?page=k7&tug=<?= urlencode($k7['tug_number']) ?>" class="btn-back">&larr; Kembali</a>
  <button class="btn-print" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<div class="sheet">

  <div class="header-section">
    <table class="frame">
      <tr>
        <td class="tug-title" rowspan="3" style="width:13%; vertical-align:middle;">
          <div style="font-size:11px; font-weight:700; text-align:left;">KODE 7</div>
          <?= htmlspecialchars($tugBig) ?>
        </td>
        <td class="title-cell" style="width:57%;">
          <div style="font-size:11px; font-weight:700;">PT. PLN (PERSERO) UID JATIM UP3 MALANG</div>
          <h1>BON PEMAKAIAN</h1>
        </td>
        <td class="tug-box" rowspan="3" style="width:30%; vertical-align:middle;">
          <div class="tug-number"><?= htmlspecialchars($k7['tug_number']) ?></div>
        </td>
      </tr>
      <tr>
        <td style="font-size:9.5px;">
          Tanggal<br>diminta
          <table class="date-grid" style="margin-top:2px;">
            <tr><td>Tgl.</td><td>Bln.</td><td>Thn.</td></tr>
            <tr style="font-weight:700;"><td><?= date('d', $d) ?></td><td><?= date('m', $d) ?></td><td><?= date('Y', $d) ?></td></tr>
          </table>
        </td>
      </tr>
      <tr>
        <td style="font-size:9.5px;">
          Tanggal<br>diberikan
          <table class="date-grid" style="margin-top:2px;">
            <tr><td>Tgl.</td><td>Bln.</td><td>Thn.</td></tr>
            <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
          </table>
        </td>
      </tr>
    </table>

    <table class="frame" style="margin-top:-1px;">
      <tr>
        <td style="width:57%;">
          Kepada &nbsp;: PT PLN (Persero) UP3 Malang<br>
          Gudang &nbsp;: Gudang PLN Aries Munandar<br>
          Alamat &nbsp;: Jl. Aries Munandar No. 77A Malang
        </td>
        <td style="width:43%;">
          Harap dikirim ke :<br>
          <strong><?= htmlspecialchars($k7['vendor_name'] ?: '-') ?></strong><br>
          <?= htmlspecialchars($k7['vendor_address'] ?: '') ?><br>
          <span style="font-size:9.5px;">Kode Jurnal</span><br>
          <?php for ($i = 0; $i < 6; $i++): ?><span class="kj-box"></span><?php endfor; ?>
        </td>
      </tr>
    </table>
  </div>

  <table class="items">
    <tr>
      <th rowspan="2" style="width:4%;">No.<br>Urut</th>
      <th rowspan="2" style="width:26%;">Nama Barang<br>(ditulis selengkap - lengkapnya)</th>
      <th rowspan="2" style="width:9%;">No.<br>Normalisasi</th>
      <th rowspan="2" style="width:5%;">Sa-<br>tuan</th>
      <th colspan="2" style="width:20%;">Banyaknya yang diminta</th>
      <th colspan="2" style="width:20%;">Banyaknya yang diterima</th>
      <th rowspan="2" style="width:16%;">Jumlah Uang<br>Rp.</th>
    </tr>
    <tr>
      <th style="width:8%;">dengan angka</th>
      <th style="width:12%;">dengan huruf</th>
      <th style="width:8%;">dengan angka</th>
      <th style="width:12%;">dengan huruf</th>
    </tr>
    <?php foreach ($items as $i => $it):
        $qr = $it ? (int)($it['quantity_requested'] ?? 0) : null;
        $qd = $it ? (int)($it['quantity_received'] ?? 0) : null;
    ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td class="left"><?= $it ? htmlspecialchars($it['material_name'] ?? '') : '' ?></td>
      <td><?= $it ? htmlspecialchars($it['norm'] ?? '') : '' ?></td>
      <td><?= $it ? htmlspecialchars($it['unit'] ?? '') : '' ?></td>
      <td><?= $it !== null ? $qr : '' ?></td>
      <td class="left"><?= $it !== null ? terbilangID($qr) : '' ?></td>
      <td><?= $it !== null ? $qd : '' ?></td>
      <td class="left"><?= $it !== null ? terbilangID($qd) : '' ?></td>
      <td></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <table class="frame">
    <tr>
      <td style="width:70%;">
        <div class="info-line"><span class="info-label">VENDOR</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['vendor_name'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">NO. SPK</span><span class="info-colon">:</span><span class="info-value" style="text-decoration:underline;"><?= htmlspecialchars($k7['spk_number'] ?: '-') ?></span></div>
        <div style="height:8px;"></div>
        <div class="info-line"><span class="info-label">JENIS PEKERJAAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['jenis_pekerjaan'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">IDPEL</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['idpel'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">NAMA PELANGGAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['customer_name'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">ALAMAT PELANGGAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['customer_address'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">DAYA</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['daya'] ?: '-') ?></span></div>
        <div class="info-line"><span class="info-label">ULP</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['ulp'] ?: '-') ?></span></div>
      </td>
      <td style="width:30%;">
        <div class="info-line" style="margin-bottom:6px;"><span class="info-label" style="width:100px;">MERK MATERIAL</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['merk_material'] ?? '') ?>&nbsp;</span></div>
        <div class="info-line" style="margin-bottom:6px;"><span class="info-label" style="width:100px;">NOMOR SERI</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['nomor_seri'] ?? '') ?>&nbsp;</span></div>
        <div class="info-line"><span class="info-label" style="width:100px;">KETERANGAN</span><span class="info-colon">:</span><span class="info-value"><?= htmlspecialchars($k7['keterangan'] ?? '') ?>&nbsp;</span></div>
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

  <table class="frame" style="margin-top:-1px;">
    <tr class="sign-row">
      <td style="width:25%;">
        <div class="sign-role">Setuju :</div>
        <div style="text-align:center; font-size:9.5px;">Asman Konstruksi</div>
        <div class="sign-name"><?= htmlspecialchars($k7['setuju_name'] ?: DEFAULT_SIGNER_SETUJU) ?></div>
      </td>
      <td style="width:25%;">
        <div class="sign-role">Kepala Gudang :</div>
        <div class="sign-name"><?= htmlspecialchars($k7['kepala_gudang_name'] ?: DEFAULT_SIGNER_KEPALA_GUDANG) ?></div>
      </td>
      <td style="width:25%;">
        <div class="sign-role">Pemeriksa &nbsp;&nbsp;Pengawas</div>
        <div class="sign-name"><?= htmlspecialchars($k7['pemeriksa_pengawas_name'] ?: '') ?>&nbsp;</div>
      </td>
      <td style="width:25%;">
        <div class="sign-role">Penerima <?= htmlspecialchars($k7['ulp'] ?: '') ?> :</div>
        <div class="sign-name"><?= htmlspecialchars($k7['penerima_name'] ?: '') ?>&nbsp;</div>
      </td>
    </tr>
  </table>

</div>

</body>
</html>