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

$sqlConds = ["di.quantity_received > 0"];
$sqlParams = [];

if ($startDate !== '' && $endDate !== '') {
    $sqlConds[] = "d.tanggal_diminta BETWEEN ? AND ?";
    $sqlParams[] = $startDate;
    $sqlParams[] = $endDate;
}

if ($q !== '') {
    $sqlConds[] = "(m.name ILIKE ? OR m.norm ILIKE ? OR v.name ILIKE ? OR d.tug_number ILIKE ?)";
    $qLike = '%' . $q . '%';
    $sqlParams[] = $qLike;
    $sqlParams[] = $qLike;
    $sqlParams[] = $qLike;
    $sqlParams[] = $qLike;
}

$whereClause = 'WHERE ' . implode(' AND ', $sqlConds);

$stmt = $db->prepare("
    SELECT di.id, di.quantity_received AS quantity, di.sn AS sn, m.name AS material_name, m.norm AS material_norm, m.unit AS material_unit,
           d.tug_number AS no_dpb, d.surat_jalan_number AS surat_jalan, d.tanggal_diminta AS tanggal_keluar, v.name AS vendor_name
    FROM dpb_items di
    JOIN dpb_transactions d ON di.dpb_id = d.id
    JOIN materials m ON di.material_id = m.id
    JOIN vendors v ON d.vendor_id = v.id
    $whereClause
    ORDER BY d.tanggal_diminta DESC, di.id DESC
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
$sheet->setCellValue('A1', 'Daftar Material Keluar');
$sheet->mergeCells('A1:J1');
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
$sheet->mergeCells('A2:J2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A2')->getFont()->setItalic(true);

// Table Headers
$headers = ['No', 'Nama Material', 'No. Normalisasi', 'Satuan', 'Jumlah', 'Nama Vendor', 'Nomor DPB / TUG', 'Nomor Surat Jalan', 'Serial Number (SN)', 'Tanggal Keluar'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . '4', $h);
    $col++;
}
$sheet->getStyle('A4:J4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
$sheet->getStyle('A4:J4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('0B2B4A');
$sheet->getStyle('A4:J4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Data rows
$rowIdx = 5;
$no = 1;
foreach ($data as $row) {
    $sheet->setCellValue('A' . $rowIdx, $no++);
    $sheet->setCellValue('B' . $rowIdx, $row['material_name']);
    $sheet->setCellValue('C' . $rowIdx, $row['material_norm']);
    $sheet->setCellValue('D' . $rowIdx, $row['material_unit']);
    $sheet->setCellValue('E' . $rowIdx, $row['quantity']);
    $sheet->setCellValue('F' . $rowIdx, $row['vendor_name'] ?: '-');
    $sheet->setCellValue('G' . $rowIdx, $row['no_dpb'] ?: '-');
    $sheet->setCellValue('H' . $rowIdx, $row['surat_jalan'] ?: '-');
    $sheet->setCellValue('I' . $rowIdx, $row['sn'] ?: '-');
    $sheet->setCellValue('J' . $rowIdx, $row['tanggal_keluar'] ? date('d-m-Y', strtotime($row['tanggal_keluar'])) : '-');

    // Alignments
    $sheet->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('G' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('H' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('I' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('J' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowIdx++;
}

// Border and styling
$styleRange = 'A4:J' . ($rowIdx - 1);
$sheet->getStyle($styleRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Auto size columns
foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = "Material_Keluar_" . date('Ymd_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
