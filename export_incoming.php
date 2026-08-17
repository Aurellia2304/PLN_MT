<?php
require_once 'config.php';
require_once 'functions.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
if (isVendor()) {
    die("Akses ditolak.");
}

$q = trim($_GET['q'] ?? '');
$startDate = trim($_GET['start'] ?? '');
$endDate = trim($_GET['end'] ?? '');

$sqlConds = [];
$sqlParams = [];

if ($startDate !== '' && $endDate !== '') {
    $sqlConds[] = "im.tanggal_datang BETWEEN ? AND ?";
    $sqlParams[] = $startDate . ' 00:00:00';
    $sqlParams[] = $endDate . ' 23:59:59';
}

if ($q !== '') {
    $sqlConds[] = "(m.name ILIKE ? OR m.norm ILIKE ? OR im.pabrikan ILIKE ? OR im.nomor_kontrak ILIKE ?)";
    $qLike = '%' . $q . '%';
    $sqlParams[] = $qLike;
    $sqlParams[] = $qLike;
    $sqlParams[] = $qLike;
    $sqlParams[] = $qLike;
}

$whereClause = !empty($sqlConds) ? 'WHERE ' . implode(' AND ', $sqlConds) : '';

$stmt = $db->prepare("
    SELECT im.*, m.name AS material_name, m.norm AS material_norm, m.unit AS material_unit 
    FROM incoming_materials im
    JOIN materials m ON im.material_id = m.id
    $whereClause
    ORDER BY im.tanggal_datang DESC, im.id DESC
");
$stmt->execute($sqlParams);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Title
$sheet->setCellValue('A1', 'Daftar Material Masuk');
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Subtitle filter
$sub = "Semua Data";
if ($startDate !== '' && $endDate !== '') {
    $sub = "Periode: " . date('d-M-Y', strtotime($startDate)) . " s/d " . date('d-M-Y', strtotime($endDate));
}
if ($q !== '') {
    $sub .= " | Pencarian: \"" . $q . "\"";
}
$sheet->setCellValue('A2', $sub);
$sheet->mergeCells('A2:H2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A2')->getFont()->setItalic(true);

// Table Headers
$headers = ['No', 'Nama Material', 'No. Normalisasi', 'Satuan', 'Jumlah', 'Nama Pabrikan', 'Nomor Kontrak', 'Tanggal Datang'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '4', $h);
    $col++;
}
$sheet->getStyle('A4:H4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
$sheet->getStyle('A4:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('0B2B4A');
$sheet->getStyle('A4:H4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Data rows
$rowIdx = 5;
$no = 1;
foreach ($data as $row) {
    $sheet->setCellValue('A' . $rowIdx, $no++);
    $sheet->setCellValue('B' . $rowIdx, $row['material_name']);
    $sheet->setCellValue('C' . $rowIdx, $row['material_norm']);
    $sheet->setCellValue('D' . $rowIdx, $row['material_unit']);
    $sheet->setCellValue('E' . $rowIdx, $row['quantity']);
    $sheet->setCellValue('F' . $rowIdx, $row['pabrikan'] ?: '-');
    $sheet->setCellValue('G' . $rowIdx, $row['nomor_kontrak'] ?: '-');
    $sheet->setCellValue('H' . $rowIdx, $row['tanggal_datang'] ? date('d-m-Y', strtotime($row['tanggal_datang'])) : '-');

    // Alignments
    $sheet->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('H' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowIdx++;
}

// Border and styling
$styleRange = 'A4:H' . ($rowIdx - 1);
$sheet->getStyle($styleRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Auto size columns
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = "Material_Masuk_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
