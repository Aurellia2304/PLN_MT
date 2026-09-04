<?php
// Pastikan dipanggil dari index.php
if (!isset($db) || !$is_admin) {
    echo "<div class='alert-danger'>Akses ditolak.</div>";
    return;
}

// Fetch all returns with optional search
$search = $_GET['q'] ?? '';
if ($search) {
    $stmt = $db->prepare("SELECT * FROM return_materials WHERE material_name ILIKE ? OR bon_number ILIKE ? ORDER BY id DESC");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $db->query("SELECT * FROM return_materials ORDER BY id DESC");
}
$returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// URL scan.php dinamis sesuai host
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/scan.php?token=";
?>

<div id="returnSection">
    <div class="card" style="margin-bottom: 24px;">
        <h3 style="color:#0b2b4a; margin: 0 0 16px 0;"><i class="fas fa-qrcode" style="color: #14828a; margin-right: 8px;"></i> Monitoring Return Material</h3>
        
        <form method="POST" action="index.php?page=return" class="flex-row" style="align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action_return" value="add">
            
            <div class="form-group" style="flex:1;">
                <label>Nomor Bon</label>
                <input type="text" name="bon_number" required placeholder="BON-001">
            </div>
            <div class="form-group" style="flex:1;">
                <label>Nama Material</label>
                <input type="text" name="material_name" required placeholder="Trafo 20 kV">
            </div>
            <div class="form-group" style="flex:1;">
                <label>Jumlah</label>
                <input type="number" name="quantity" min="1" required placeholder="10">
            </div>
            <div class="form-group" style="flex:1;">
                <label>Status</label>
                <select name="status" required>
                    <option value="usul hapus">Usul Hapus</option>
                    <option value="perbaikan">Perbaikan</option>
                    <option value="standby">Standby</option>
                    <option value="garansi">Garansi</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn-success" style="padding: 0.65rem 1.5rem;"><i class="fas fa-plus"></i> Tambah</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:12px;">
            <h4 style="color:#0b2b4a; margin:0;"><i class="fas fa-list"></i> Daftar Return Material & QR Code</h4>
            <form method="GET" action="index.php" style="display:flex; gap:8px;">
                <input type="hidden" name="page" value="return">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari material/bon..." style="padding:0.5rem 1rem; border-radius:8px; border:1px solid #ccc;">
                <button type="submit" class="btn-info" style="padding:0.5rem 1rem;"><i class="fas fa-search"></i></button>
                <?php if($search): ?>
                    <a href="?page=return" class="btn-warning" style="padding:0.5rem 1rem; text-decoration:none;"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No Bon</th>
                        <th>Material</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="5" style="text-align:center;">Belum ada data return.</td></tr>
                    <?php else: ?>
                        <?php foreach ($returns as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['bon_number']) ?></strong></td>
                                <td><?= htmlspecialchars($row['material_name']) ?></td>
                                <td><?= (int)$row['quantity'] ?></td>
                                <td>
                                    <?php
                                        $bg = '#eee'; $col = '#333';
                                        if($row['status'] == 'usul hapus') { $bg = '#fff0f0'; $col = '#e11d48'; }
                                        elseif($row['status'] == 'perbaikan') { $bg = '#fff6dd'; $col = '#b78a00'; }
                                        elseif($row['status'] == 'standby') { $bg = '#e3f7ec'; $col = '#1e8e5a'; }
                                        elseif($row['status'] == 'garansi') { $bg = '#e6f7f8'; $col = '#14828a'; }
                                    ?>
                                    <span class="badge" style="background-color: <?= $bg ?>; color: <?= $col ?>;"><?= strtoupper($row['status']) ?></span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:8px;">
                                        <button class="btn-info" onclick='openEditReturnModal(<?= json_encode($row) ?>)' style="padding:4px 8px; font-size:12px;">Edit</button>
                                        <button class="btn-warning" onclick='printQR("<?= $baseUrl . $row['token'] ?>", "<?= htmlspecialchars($row['bon_number']) ?>")' style="padding:4px 8px; font-size:12px;"><i class="fas fa-qrcode"></i> QR</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="returnEditModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <span class="close" onclick="document.getElementById('returnEditModal').style.display='none'">&times;</span>
        <h2>Edit Data Return</h2>
        <form method="POST" action="index.php?page=return">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <input type="hidden" name="action_return" value="edit">
            <input type="hidden" name="id" id="edit_return_id">
            
            <div class="form-group">
                <label>Nomor Bon</label>
                <input type="text" id="edit_return_bon" disabled style="background:#eee;">
            </div>
            <div class="form-group">
                <label>Nama Material</label>
                <input type="text" id="edit_return_mat" disabled style="background:#eee;">
            </div>
            <div class="form-group">
                <label>Jumlah</label>
                <input type="number" name="quantity" id="edit_return_qty" min="1" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_return_status" required>
                    <option value="usul hapus">Usul Hapus</option>
                    <option value="perbaikan">Perbaikan</option>
                    <option value="standby">Standby</option>
                    <option value="garansi">Garansi</option>
                </select>
            </div>
            <button type="submit" class="btn-success" style="width:100%;">Update</button>
        </form>
    </div>
</div>

<!-- Div rahasia untuk render QR -->
<div id="qrPrintArea" style="display:none;"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function openEditReturnModal(data) {
    document.getElementById('edit_return_id').value = data.id;
    document.getElementById('edit_return_bon').value = data.bon_number;
    document.getElementById('edit_return_mat').value = data.material_name;
    document.getElementById('edit_return_qty').value = data.quantity;
    document.getElementById('edit_return_status').value = data.status;
    document.getElementById('returnEditModal').style.display = 'flex';
}

function printQR(url, bon) {
    var printArea = document.getElementById('qrPrintArea');
    printArea.innerHTML = '';
    
    // Generate QR
    new QRCode(printArea, {
        text: url,
        width: 200,
        height: 200,
        colorDark : "#000000",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });

    setTimeout(function() {
        var qrImg = printArea.querySelector('img').src;
        var printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Print QR - ${bon}</title>
                <style>
                    body { text-align: center; font-family: sans-serif; padding: 20px; }
                    .qr-container { display: inline-block; padding: 20px; border: 2px solid #000; border-radius: 10px; }
                    h2 { margin: 10px 0; font-size: 24px; }
                </style>
            </head>
            <body>
                <div class="qr-container">
                    <h2>${bon}</h2>
                    <img src="${qrImg}" style="width: 200px; height: 200px;">
                    <p>Scan untuk info material</p>
                </div>
                <script>
                    window.onload = function() { window.print(); window.close(); }
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }, 500); // Wait for QR generation
}
</script>
