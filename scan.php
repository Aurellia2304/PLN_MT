<?php
require_once 'config.php';

if (!isset($_GET['token'])) {
    die("Token tidak valid.");
}

$token = $_GET['token'];

try {
    $stmt = $db->prepare("SELECT * FROM return_materials WHERE token = ?");
    $stmt->execute([$token]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Terjadi kesalahan sistem.");
}

if (!$item) {
    die("Data material tidak ditemukan.");
}

// Menentukan warna badge berdasarkan status
$status = strtolower($item['status']);
$statusColor = '#64748b'; // default gray
$statusBg = '#f1f5f9';

if ($status == 'usul hapus') {
    $statusColor = '#e11d48'; // red
    $statusBg = '#ffeef0';
} elseif ($status == 'perbaikan') {
    $statusColor = '#b78a00'; // yellow/orange
    $statusBg = '#fff6dd';
} elseif ($status == 'standby') {
    $statusColor = '#1e8e5a'; // green
    $statusBg = '#e3f7ec';
} elseif ($status == 'garansi') {
    $statusColor = '#14828a'; // teal
    $statusBg = '#e6f7f8';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Info Material - VOLTA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .mobile-container {
            width: 100%;
            max-width: 480px;
            background: #ffffff;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            line-height: 1.4;
            word-wrap: break-word;
        }



        .content {
            padding: 24px;
            flex: 1;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-label {
            font-size: 15px;
            color: #475569;
            font-weight: 500;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .image-container {
            margin-top: 32px;
            background: #e0f2fe;
            border-radius: 16px;
            padding: 32px;
            text-align: center;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .image-container img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            mix-blend-mode: multiply;
        }
        
        /* Ilustrasi ikon kotak box sebagai placeholder */
        .box-icon {
            width: 120px;
            height: 120px;
            background-color: #bae6fd;
            border-radius: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .box-icon svg {
            width: 64px;
            height: 64px;
            color: #0284c7;
        }

    </style>
</head>
<body>

    <div class="mobile-container">
        <div class="header">
            <h1 class="title"><?= htmlspecialchars($item['material_name']) ?></h1>
        </div>

        <div class="content">
            <div class="info-row">
                <span class="info-label">Jumlah (Units)</span>
                <span class="info-value"><?= number_format($item['quantity']) ?></span>
            </div>
            
            <div class="info-row">
                <span class="info-label">Nomor Bon</span>
                <span class="info-value" style="color: #64748b; font-family: monospace; font-size: 14px;"><?= htmlspecialchars($item['bon_number']) ?></span>
            </div>

            <div class="info-row" style="border-bottom: none;">
                <span class="info-label">Status Terkini</span>
                <span class="status-badge" style="background-color: <?= $statusBg ?>; color: <?= $statusColor ?>;">
                    <?= htmlspecialchars($item['status']) ?>
                </span>
            </div>

            <!-- Placeholder Image (Mendekati referensi foto yang dikirim) -->
            <div class="image-container">
                <div class="box-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9"></path>
                    </svg>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 24px; color: #94a3b8; font-size: 12px;">
                Terakhir diupdate: <?= date('d M Y, H:i', strtotime($item['updated_at'])) ?><br>
                Sistem VOLTA PLN UP3 Malang
            </div>
        </div>
    </div>

</body>
</html>
