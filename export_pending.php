<?php
require_once 'config.php';
require_once 'functions.php';

// Check login
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$is_admin = isAdmin();
$is_vendor = isVendor();

if (!$is_admin && !$is_vendor) {
    die("Akses ditolak.");
}

$table = $_GET['table'] ?? '';
if ($table !== 'rincian' && $table !== 'rekap') {
    die("Parameter tabel tidak valid.");
}

// Get filter inputs
$q = trim($_GET['q'] ?? '');
$startDate = trim($_GET['start'] ?? '');
$endDate = trim($_GET['end'] ?? '');

// Build query conditions
$sqlConds = ["d.status IN ('aktif', 'belum_jalan')", "di.quantity_requested > di.quantity_received"];
$sqlParams = [];

if ($is_vendor) {
    $vendorId = currentVendorId();
    $sqlConds[] = "d.vendor_id = ?";
    $sqlParams[] = $vendorId;
}

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

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if ($table === 'rincian') {
    // Query rincian
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
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Title
    $sheet->setCellValue('A1', 'Rincian Material Pending per Vendor');
    $sheet->mergeCells('A1:F1');
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
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setItalic(true);

    // Table Headers
    $headers = ['No', 'Nama Material', 'Jumlah Pending', 'Vendor', 'Nama Pelanggan', 'Nomor TUG'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '4', $h);
        $col++;
    }
    $sheet->getStyle('A4:F4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
    $sheet->getStyle('A4:F4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('0B2B4A');
    $sheet->getStyle('A4:F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Data rows
    $rowIdx = 5;
    $no = 1;
    foreach ($data as $row) {
        $sheet->setCellValue('A' . $rowIdx, $no++);
        $sheet->setCellValue('B' . $rowIdx, $row['material_name']);
        $sheet->setCellValue('C' . $rowIdx, $row['jumlah_pending']);
        $sheet->setCellValue('D' . $rowIdx, $row['vendor_name']);
        $sheet->setCellValue('E' . $rowIdx, $row['customer_name'] ?: '-');
        $sheet->setCellValue('F' . $rowIdx, $row['tug_number'] ?: '-');

        // Alignments
        $sheet->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('F' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowIdx++;
    }

    // Border and styling
    $styleRange = 'A4:F' . ($rowIdx - 1);
    $sheet->getStyle($styleRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    // Auto size columns
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = "Rincian_Material_Pending_" . date('Ymd_His') . ".xlsx";

} else {
    // Query rekap
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
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Title
    $sheet->setCellValue('A1', 'Akumulasi Rekap Material Pending');
    $sheet->mergeCells('A1:C1');
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
    $sheet->mergeCells('A2:C2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A2')->getFont()->setItalic(true);

    // Table Headers
    $headers = ['No', 'Nama Material', 'Total Pending'];
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '4', $h);
        $col++;
    }
    $sheet->getStyle('A4:C4')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
    $sheet->getStyle('A4:C4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('14828A');
    $sheet->getStyle('A4:C4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Data rows
    $rowIdx = 5;
    $no = 1;
    foreach ($data as $row) {
        $sheet->setCellValue('A' . $rowIdx, $no++);
        $sheet->setCellValue('B' . $rowIdx, $row['material_name']);
        $sheet->setCellValue('C' . $rowIdx, $row['total_pending']);

        // Alignments
        $sheet->getStyle('A' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $rowIdx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $rowIdx++;
    }

    // Border and styling
    $styleRange = 'A4:C' . ($rowIdx - 1);
    $sheet->getStyle($styleRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    // Auto size columns
    foreach (range('A', 'C') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $filename = "Rekap_Material_Pending_" . date('Ymd_His') . ".xlsx";
}

// Redirect output to a client’s web browser (Xlsx)
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
// If you're serving to IE 9, then the following may be needed
header('Cache-Control: max-age=1');

// If you're serving to IE over SSL, then the following may be needed
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header('Pragma: public'); // HTTP/1.0

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
