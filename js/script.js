function setRequiredIn(containerId, isRequired) {
  var container = document.getElementById(containerId);
  if (!container) return;
  container.querySelectorAll("input, select, textarea").forEach(function (el) {
    if (isRequired) {
      if (el.dataset.wasRequired === "1") el.required = true;
    } else {
      el.dataset.wasRequired = el.required ? "1" : "0";
      el.required = false;
    }
  });
}

function showLogin() {
  document.getElementById("modalTitle").textContent = "Masuk";
  document.getElementById("loginFields").style.display = "block";
  document.getElementById("registerFields").style.display = "none";
  document.getElementById("forgotFields").style.display = "none";
  document.getElementById("toggleAuthText").style.display = "block";
  document.getElementById("backToLoginText").style.display = "none";
  document.getElementById("authModal").classList.add("show");
  setRequiredIn("loginFields", true);
  setRequiredIn("registerFields", false);
}

function showRegister() {
  document.getElementById("modalTitle").textContent = "Daftar sebagai Vendor";
  document.getElementById("loginFields").style.display = "none";
  document.getElementById("registerFields").style.display = "block";
  document.getElementById("forgotFields").style.display = "none";
  document.getElementById("toggleAuthText").style.display = "block";
  document.getElementById("backToLoginText").style.display = "none";
  document.getElementById("authModal").classList.add("show");
  setRequiredIn("loginFields", false);
  setRequiredIn("registerFields", true);
}

function toggleAuth() {
  var loginVisible =
    document.getElementById("loginFields").style.display !== "none";
  if (loginVisible) {
    showRegister();
  } else {
    showLogin();
  }
}

function showForgotPassword() {
  document.getElementById("modalTitle").textContent = "Lupa Sandi";
  document.getElementById("loginFields").style.display = "none";
  document.getElementById("registerFields").style.display = "none";
  document.getElementById("forgotFields").style.display = "block";
  document.getElementById("toggleAuthText").style.display = "none";
  document.getElementById("backToLoginText").style.display = "block";
  document.getElementById("authModal").classList.add("show");
  setRequiredIn("loginFields", false);
  setRequiredIn("registerFields", false);
}

// ---------- MODAL FAQ ----------
function showFaq() {
  document.getElementById("faqModal").classList.add("show");
}

// ---------- TUTUP SEMUA MODAL ----------
function closeModal() {
  document.querySelectorAll(".modal").forEach(function (m) {
    m.classList.remove("show");
  });
}

// klik di luar modal-content untuk menutup
window.addEventListener("click", function (e) {
  if (e.target.classList && e.target.classList.contains("modal")) {
    closeModal();
  }
});

// tombol ESC untuk menutup modal
window.addEventListener("keydown", function (e) {
  if (e.key === "Escape") closeModal();
});

// ---------- NORMALISASI MATERIAL (data resmi gabungan: materials DB + data/normalisasi.csv) ----------
// Dipakai di halaman Material (tambah/edit) DAN di baris material DPB/K3/K7.
function getCombinedMaterialData() {
  if (window._combinedMaterialData) return window._combinedMaterialData;

  var combined = {};
  (window.MATERIALS_DATA || []).forEach(function (m) {
    if (!m.name) return;
    combined[m.name.toLowerCase()] = {
      name: m.name,
      norm: m.norm || "",
      unit: m.unit || "",
    };
  });
  // Data resmi dari CSV normalisasi jadi acuan utama utk kode norm.
  (window.NORMALISASI_DATA || []).forEach(function (m) {
    if (!m.name) return;
    var key = m.name.toLowerCase();
    if (combined[key]) {
      combined[key].norm = m.norm || combined[key].norm;
    } else {
      combined[key] = { name: m.name, norm: m.norm || "", unit: "" };
    }
  });

  window._combinedMaterialData = Object.keys(combined).map(function (k) {
    return combined[k];
  });
  return window._combinedMaterialData;
}

function findMaterialMatch(typed, field) {
  typed = (typed || "").trim().replace(/\s+/g, " ").toLowerCase();
  if (!typed) return null;
  var data = getCombinedMaterialData();
  return (
    data.find(function (m) {
      return field === "name"
        ? m.name.trim().replace(/\s+/g, " ").toLowerCase() === typed
        : m.norm.trim().toLowerCase() === typed;
    }) ||
    data.find(function (m) {
      return field === "name"
        ? m.name.toLowerCase().indexOf(typed) === 0
        : m.norm.toLowerCase().indexOf(typed) === 0;
    }) ||
    null
  );
}

// saat mengetik NAMA di form tambah material -> isi kode normalisasi otomatis dari data CSV/DB
function updateNorm() {
  var nameInput = document.getElementById("materialNameInput");
  var normInput = document.getElementById("materialNormInput");
  if (!nameInput || !normInput) return;
  if (normInput.value.trim() !== "") return; // jangan timpa jika admin sudah mengetik manual

  var match = findMaterialMatch(nameInput.value, "name");
  normInput.value = match ? match.norm : "";
}

// saat mengetik KODE NORMALISASI duluan di form tambah material -> isi nama otomatis
function updateNormFromCode() {
  var nameInput = document.getElementById("materialNameInput");
  var normInput = document.getElementById("materialNormInput");
  if (!nameInput || !normInput) return;
  if (nameInput.value.trim() !== "") return; // jangan timpa nama yg sudah diisi manual

  var match = findMaterialMatch(normInput.value, "norm");
  if (match) nameInput.value = match.name;
}

// versi untuk modal Edit Material
function updateNormEdit() {
  var nameInput = document.getElementById("editMaterialName");
  var normInput = document.getElementById("editMaterialNorm");
  if (!nameInput || !normInput) return;
  if (normInput.value.trim() !== "") return;

  var match = findMaterialMatch(nameInput.value, "name");
  if (match) normInput.value = match.norm;
}

// ---------- PANEL DPB MENUNGGU PERSETUJUAN (admin) ----------
function loadPendingDpbList() {
  var container = document.getElementById("dpbPendingContainer");
  if (!container) return;

  fetch("index.php?ajax=dpb_pending")
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      if (!Array.isArray(data) || data.length === 0) {
        container.innerHTML =
          '<p class="text-small">Tidak ada pengajuan yang menunggu persetujuan saat ini.</p>';
        return;
      }
      var rows = data
        .map(function (d, i) {
          return (
            "<tr><td>" +
            (i + 1) +
            "</td><td>" +
            escapeHtml(d.tug_number || "-") +
            "</td><td>" +
            escapeHtml(d.vendor_name || "-") +
            "</td><td>" +
            escapeHtml(d.customer_name || "-") +
            "</td><td>" +
            escapeHtml(d.tanggal_diminta || "-") +
            '</td><td style="white-space:nowrap;">' +
            '<form method="POST" action="dpb.php" style="display:inline;">' +
            '<input type="hidden" name="dpb_id" value="' +
            d.id +
            '">' +
            '<button type="submit" name="approve_dpb" class="btn-success" style="padding:0.3rem 0.8rem; border-radius:20px; font-size:0.75rem;">Setujui</button>' +
            "</form> " +
            '<form method="POST" action="dpb.php" style="display:inline;" onsubmit="return confirm(\'Yakin tolak pengajuan ini?\')">' +
            '<input type="hidden" name="dpb_id" value="' +
            d.id +
            '">' +
            '<button type="submit" name="reject_dpb" class="btn-danger" style="padding:0.3rem 0.8rem; border-radius:20px; font-size:0.75rem;">Tolak</button>' +
            "</form>" +
            "</td></tr>"
          );
        })
        .join("");
      container.innerHTML =
        '<div class="table-wrap"><table><thead><tr><th>#</th><th>No. TUG</th><th>Vendor</th><th>Pelanggan</th><th>Tgl Diminta</th><th>Aksi</th></tr></thead>' +
        "<tbody>" +
        rows +
        "</tbody></table></div>";
    })
    .catch(function () {
      container.innerHTML =
        '<p class="text-small">Gagal memuat data pengajuan.</p>';
    });
}

// ---------- LIHAT DAFTAR MATERIAL (AJAX) ----------
var currentMaterialsList = [];
var materialActiveList = []; // list yang sedang ditampilkan (setelah filter pencarian)
var materialCurrentPage = 1;
var MATERIAL_PAGE_SIZE = 10;

function showMaterialList() {
  var container = document.getElementById("materialListContainer");
  if (!container) return;
  container.innerHTML = '<p class="text-small">Memuat data...</p>';

  fetch("index.php?ajax=materials")
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      currentMaterialsList = Array.isArray(data) ? data : [];
      var searchWrap = document.getElementById("materialSearchWrap");
      if (currentMaterialsList.length === 0) {
        container.innerHTML =
          '<p class="text-small">Belum ada material terdaftar.</p>';
        if (searchWrap) searchWrap.style.display = "none";
        return;
      }
      if (searchWrap) searchWrap.style.display = "block";
      renderMaterialTable(currentMaterialsList);
    })
    .catch(function () {
      container.innerHTML =
        '<p class="text-small">Gagal memuat data material.</p>';
    });
}

// Set daftar aktif (hasil pencarian/filter) & mulai lagi dari halaman 1
function renderMaterialTable(list) {
  materialActiveList = list;
  materialCurrentPage = 1;
  renderMaterialPage();
}

// Render 1 halaman (20 baris) dari materialActiveList sesuai materialCurrentPage,
// beserta baris navigasi halaman & tombol ekspor di paling bawah.
function renderMaterialPage() {
  var container = document.getElementById("materialListContainer");
  if (!container) return;
  var isAdmin = window.IS_REAL_ADMIN === true;
  var list = materialActiveList;

  if (list.length === 0) {
    container.innerHTML =
      '<p class="text-small">Tidak ada material yang cocok dengan pencarian.</p>';
    return;
  }

  var totalPages = Math.max(1, Math.ceil(list.length / MATERIAL_PAGE_SIZE));
  if (materialCurrentPage > totalPages) materialCurrentPage = totalPages;
  if (materialCurrentPage < 1) materialCurrentPage = 1;

  var startIdx = (materialCurrentPage - 1) * MATERIAL_PAGE_SIZE;
  var pageItems = list.slice(startIdx, startIdx + MATERIAL_PAGE_SIZE);

  var rows = pageItems
    .map(function (m, i) {
      var no = startIdx + i + 1;
      var actionCell = isAdmin
        ? '<td style="white-space:nowrap;">' +
          '<button type="button" class="btn-info" style="padding:0.3rem 0.7rem; border-radius:20px; font-size:0.75rem;" onclick="openMaterialEditModal(' +
          m.id +
          ')"><i class="fas fa-edit"></i> Edit</button> ' +
          '<a href="material.php?delete=' +
          m.id +
          '" onclick="return confirm(\'Yakin hapus material ini?\')" class="btn-danger" style="padding:0.3rem 0.7rem; border-radius:20px; font-size:0.75rem; text-decoration:none; display:inline-block;"><i class="fas fa-trash"></i> Hapus</a>' +
          "</td>"
        : "";
      return (
        "<tr><td>" +
        no +
        "</td><td>" +
        escapeHtml(m.name) +
        "</td><td>" +
        escapeHtml(m.norm) +
        "</td><td>" +
        escapeHtml(m.unit) +
        "</td><td>" +
        escapeHtml(String(m.stock ?? 0)) +
        "</td>" +
        actionCell +
        "</tr>"
      );
    })
    .join("");

  var tableHtml =
    '<div class="table-wrap"><table><thead><tr><th>No</th><th>Nama</th><th>Normalisasi</th><th>Satuan</th><th>Jumlah</th>' +
    (isAdmin ? "<th>Aksi</th>" : "") +
    "</tr></thead><tbody>" +
    rows +
    "</tbody></table></div>";

  var footerHtml =
    '<div class="material-list-footer">' +
    buildMaterialPaginationHtml(materialCurrentPage, totalPages) +
    '<div class="material-export-row">' +
    '<button type="button" class="btn-warning" onclick="exportMaterialsToExcel()"><i class="fas fa-file-excel"></i> Ekspor ke Excel</button>' +
    "</div>" +
    "</div>";

  container.innerHTML = tableHtml + footerHtml;
}

function goToMaterialPage(p) {
  materialCurrentPage = p;
  renderMaterialPage();
  var container = document.getElementById("materialListContainer");
  if (container)
    container.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

// Bangun tombol navigasi halaman: 1 2 3 4 ... (halaman-sekitar-current) ... n-2 n-1 n, + panah kiri/kanan
function buildMaterialPaginationHtml(current, total) {
  if (total <= 1) return "";

  var pages = {};
  var i;
  for (i = 1; i <= Math.min(4, total); i++) pages[i] = true;
  for (i = Math.max(1, total - 2); i <= total; i++) pages[i] = true;
  for (i = Math.max(1, current - 1); i <= Math.min(total, current + 1); i++)
    pages[i] = true;

  var sorted = Object.keys(pages)
    .map(Number)
    .sort(function (a, b) {
      return a - b;
    });

  var html = '<div class="pagination-wrap">';

  if (current > 1) {
    html +=
      '<button type="button" class="page-btn page-arrow" onclick="goToMaterialPage(' +
      (current - 1) +
      ')">&larr;</button>';
  }

  var prev = 0;
  sorted.forEach(function (p) {
    if (prev && p - prev > 1) {
      html += '<span class="page-ellipsis">...</span>';
    }
    var activeClass = p === current ? " active" : "";
    html +=
      '<button type="button" class="page-btn' +
      activeClass +
      '" onclick="goToMaterialPage(' +
      p +
      ')">' +
      p +
      "</button>";
    prev = p;
  });

  if (current < total) {
    html +=
      '<button type="button" class="page-btn page-arrow" onclick="goToMaterialPage(' +
      (current + 1) +
      ')">&rarr;</button>';
  }

  html += "</div>";
  return html;
}

// Ekspor SELURUH hasil pencarian/filter yang sedang aktif (bukan cuma halaman yang tampil) ke Excel
// Ekspor data material dari database ke berkas CSV
function exportMaterialsToExcel() {
  window.location.href = "material.php?action=export";
}

function openImportModal() {
  resetImportModal();
  document.getElementById("materialImportModal").classList.add("show");
}

function closeImportModal() {
  document.getElementById("materialImportModal").classList.remove("show");
}

function triggerImportFileSelect() {
  document.getElementById("importFileInput").click();
}

function handleImportFileSelect(event) {
  var file = event.target.files[0];
  if (!file) return;

  var uploadArea = document.getElementById("importUploadArea");
  var previewBlock = document.getElementById("importPreviewBlock");
  var statusAlert = document.getElementById("importStatusAlert");
  var errorList = document.getElementById("importErrorList");
  var tableContainer = document.getElementById("importTableContainer");
  var tbody = document.getElementById("importPreviewTbody");
  var btnConfirm = document.getElementById("btnConfirmImport");

  uploadArea.style.display = "none";
  previewBlock.style.display = "block";
  statusAlert.style.display = "none";
  errorList.style.display = "none";
  tableContainer.style.display = "none";
  btnConfirm.disabled = true;

  var formData = new FormData();
  formData.append("file", file);

  statusAlert.className = "alert-info";
  statusAlert.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membaca dan memvalidasi berkas...';
  statusAlert.style.display = "block";

  fetch("material.php?action=import_validate", {
    method: "POST",
    body: formData
  })
  .then(function(res) {
    return res.json();
  })
  .then(function(data) {
    if (data.error) {
      statusAlert.className = "alert-danger";
      statusAlert.innerHTML = '<i class="fas fa-times-circle"></i> Gagal: ' + escapeHtml(data.error);
      btnConfirm.disabled = true;
      return;
    }

    if (data.success === false && data.errors && data.errors.length > 0) {
      statusAlert.className = "alert-danger";
      statusAlert.innerHTML = '<i class="fas fa-times-circle"></i> Validasi gagal. Silakan perbaiki baris data berikut:';
      
      var errorHtml = '<strong>Detail Kesalahan:</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0; text-align: left;">';
      data.errors.forEach(function(err) {
        errorHtml += '<li>' + escapeHtml(err) + '</li>';
      });
      errorHtml += '</ul>';
      
      // Reset custom warning style
      errorList.style.background = "";
      errorList.style.color = "";
      errorList.style.border = "";
      
      errorList.innerHTML = errorHtml;
      errorList.style.display = "block";
      btnConfirm.disabled = true;
      return;
    }

    if (data.success === true) {
      statusAlert.className = "alert-success";
      statusAlert.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.validCount + ' data material siap diimport.';
      
      if (data.warnings && data.warnings.length > 0) {
        var warnHtml = '<strong>Peringatan (Duplikasi Terdeteksi):</strong><ul style="margin: 0.5rem 0 0 1.2rem; padding: 0; text-align: left;">';
        data.warnings.forEach(function(warn) {
          warnHtml += '<li>' + escapeHtml(warn) + '</li>';
        });
        warnHtml += '</ul><p style="margin-top: 0.5rem; font-size: 0.8rem; font-weight: bold; color: #713f12;">* Anda tetap dapat melanjutkan impor dengan menekan tombol Konfirmasi Import di bawah.</p>';
        
        errorList.innerHTML = warnHtml;
        errorList.style.background = "#fef9c3";
        errorList.style.color = "#713f12";
        errorList.style.border = "1px solid #fef08a";
        errorList.style.display = "block";
      } else {
        errorList.style.display = "none";
      }
      
      var tbodyHtml = '';
      data.preview.forEach(function(row) {
        tbodyHtml += '<tr>' +
          '<td>' + escapeHtml(row.name || '-') + '</td>' +
          '<td>' + escapeHtml(row.norm || '-') + '</td>' +
          '<td>' + escapeHtml(row.unit || 'BH') + '</td>' +
          '<td>' + escapeHtml(String(row.stock || 0)) + '</td>' +
          '</tr>';
      });
      tbody.innerHTML = tbodyHtml;
      tableContainer.style.display = "block";
      btnConfirm.disabled = false;
    }
  })
  .catch(function(err) {
    statusAlert.className = "alert-danger";
    statusAlert.innerHTML = '<i class="fas fa-times-circle"></i> Terjadi kesalahan jaringan saat validasi.';
  });
}

function confirmImportData() {
  var statusAlert = document.getElementById("importStatusAlert");
  var btnConfirm = document.getElementById("btnConfirmImport");
  
  btnConfirm.disabled = true;
  statusAlert.className = "alert-info";
  statusAlert.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan data ke database...';

  fetch("material.php?action=import_confirm", {
    method: "POST"
  })
  .then(function(res) {
    return res.json();
  })
  .then(function(data) {
    if (data.error) {
      statusAlert.className = "alert-danger";
      statusAlert.innerHTML = '<i class="fas fa-times-circle"></i> Gagal menyimpan: ' + escapeHtml(data.error);
      btnConfirm.disabled = false;
      return;
    }

    if (data.success === true) {
      statusAlert.className = "alert-success";
      statusAlert.innerHTML = '<i class="fas fa-check-circle"></i> Import berhasil! ' + data.count + ' material baru ditambahkan.';
      
      if (typeof showMaterialList === 'function') {
        showMaterialList();
      }

      setTimeout(function() {
        window.location.reload();
      }, 1500);
    }
  })
  .catch(function(err) {
    statusAlert.className = "alert-danger";
    statusAlert.innerHTML = '<i class="fas fa-times-circle"></i> Terjadi kesalahan jaringan saat konfirmasi.';
    btnConfirm.disabled = false;
  });
}

function resetImportModal() {
  document.getElementById("importFileInput").value = "";
  document.getElementById("importUploadArea").style.display = "block";
  document.getElementById("importPreviewBlock").style.display = "none";
  document.getElementById("importStatusAlert").style.display = "none";
  document.getElementById("importErrorList").style.display = "none";
  document.getElementById("importTableContainer").style.display = "none";
  document.getElementById("importPreviewTbody").innerHTML = "";
  document.getElementById("btnConfirmImport").disabled = true;
}


function filterMaterialTable() {
  var input = document.getElementById("materialSearchInput");
  if (!input) return;
  var q = input.value.trim().toLowerCase();
  if (q === "") {
    renderMaterialTable(currentMaterialsList);
    return;
  }
  var filtered = currentMaterialsList.filter(function (m) {
    var name = (m.name || "").toLowerCase();
    var norm = String(m.norm || "").toLowerCase();
    return name.indexOf(q) !== -1 || norm.indexOf(q) !== -1;
  });
  renderMaterialTable(filtered);
}

// ---------- CARI VENDOR (filter tabel yang sudah di-render server) ----------
function filterVendorTable() {
  var input = document.getElementById("vendorSearchInput");
  var table = document.getElementById("vendorTable");
  if (!input || !table) return;
  var q = input.value.trim().toLowerCase();
  var rows = table.querySelectorAll("tbody tr");
  rows.forEach(function (row) {
    var haystack = row.getAttribute("data-search") || "";
    row.style.display = haystack.indexOf(q) !== -1 ? "" : "none";
  });
}

function openMaterialEditModal(id) {
  var m = currentMaterialsList.find(function (item) {
    return item.id === id;
  });
  if (!m) return;
  document.getElementById("editMaterialId").value = m.id;
  document.getElementById("editMaterialName").value = m.name;
  document.getElementById("editMaterialNorm").value = m.norm;
  document.getElementById("editMaterialUnit").value = m.unit;
  document.getElementById("editMaterialStock").value = m.stock ?? 0;
  document.getElementById("materialEditModal").classList.add("show");
}

function closeMaterialEditModal() {
  document.getElementById("materialEditModal").classList.remove("show");
}

function printMaterialList() {
  window.print();
}

function saveMaterialPDF() {
  window.print();
}

// =========================================================
// FORM PENGAJUAN DPB — BARIS MATERIAL DINAMIS + AUTOCOMPLETE
// =========================================================
var dpbItemRowCount = 0;

// fungsi umum: bisa dipakai utk DPB, K3, maupun K7 (beda wrapId & prefix id baris saja)
function addGenericItemRow(wrapId, rowPrefix, prefill) {
  var wrap = document.getElementById(wrapId);
  if (!wrap) return;
  var idx = dpbItemRowCount++;

  var row = document.createElement("div");
  row.className = "flex-row dpb-item-row";
  row.id = rowPrefix + idx;
  row.innerHTML =
    '<div class="form-group" style="flex:3; min-width:150px;">' +
    "<label>Nama Material</label>" +
    '<input type="text" name="item_material_name[]" autocomplete="off" placeholder="ketik nama...">' +
    "</div>" +
    '<div class="form-group" style="flex:2; min-width:120px;">' +
    "<label>Normalisasi</label>" +
    '<input type="text" name="item_material_norm[]" autocomplete="off" placeholder="atau ketik kode...">' +
    "</div>" +
    '<div class="form-group" style="flex:1; min-width:70px; max-width:90px;">' +
    "<label>Satuan</label>" +
    '<input type="text" name="item_unit_display[]" readonly style="background:#f1f5f9; color:#64748b;">' +
    "</div>" +
    '<div class="form-group" style="flex:1; min-width:80px; max-width:100px;">' +
    "<label>Jumlah</label>" +
    '<input type="number" step="any" min="0" name="item_qty[]" value="' +
    (prefill && prefill.qty ? prefill.qty : "") +
    '" required>' +
    "</div>" +
    '<button type="button" class="btn-danger" style="height:2.6rem; align-self:flex-end; margin-bottom:0; flex:0 0 auto;" onclick="removeDpbItemRow(\'' +
    row.id +
    '\')"><i class="fas fa-trash"></i></button>';

  wrap.appendChild(row);
  ensureMaterialDatalists();

  if (prefill) {
    row.querySelector('input[name="item_material_name[]"]').value =
      prefill.name || "";
    row.querySelector('input[name="item_material_norm[]"]').value =
      prefill.norm || "";
    row.querySelector('input[name="item_unit_display[]"]').value =
      prefill.unit || "";
  }
}

function addDpbItemRow(prefill) {
  addGenericItemRow("dpbItemsWrap", "dpbItemRow", prefill);
}
function addK3ItemRow(prefill) {
  var wrap = document.getElementById("k3ItemsWrap");
  if (!wrap) return;
  var idx = wrap.children.length;
  var row = document.createElement("div");
  row.className = "flex-row item-row";
  row.id = "k3ItemRow_" + idx + "_" + Date.now();

  row.innerHTML =
    '<div class="form-group" style="flex:2;">' +
    "<label>Nama Material</label>" +
    '<input type="text" name="item_material_name[]" autocomplete="off" placeholder="ketik nama...">' +
    "</div>" +
    '<div class="form-group">' +
    "<label>Normalisasi</label>" +
    '<input type="text" name="item_material_norm[]" autocomplete="off" placeholder="atau ketik kode...">' +
    "</div>" +
    '<div class="form-group" style="max-width:100px;">' +
    "<label>Satuan</label>" +
    '<input type="text" name="item_unit_display[]" readonly>' +
    "</div>" +
    '<div class="form-group" style="max-width:110px;">' +
    "<label>Jumlah</label>" +
    '<input type="number" step="any" min="0" name="item_qty[]" value="' +
    (prefill && prefill.qty ? prefill.qty : "") +
    '" required>' +
    "</div>" +
    '<div class="form-group" style="max-width:90px;">' +
    "<label>Kode</label>" +
    '<input type="text" name="item_kode[]" value="' +
    (prefill && prefill.kode ? prefill.kode : "") +
    '">' +
    "</div>" +
    '<div class="form-group" style="max-width:120px;">' +
    "<label>Harga Satuan</label>" +
    '<input type="number" step="any" min="0" name="item_harga_satuan[]" value="' +
    (prefill && prefill.harga_satuan ? prefill.harga_satuan : "") +
    '">' +
    "</div>" +
    '<button type="button" class="btn-danger" style="height:2.6rem; align-self:flex-end;" onclick="removeDpbItemRow(\'' +
    row.id +
    '\')"><i class="fas fa-trash"></i></button>';

  wrap.appendChild(row);
  ensureMaterialDatalists();

  if (prefill) {
    row.querySelector('input[name="item_material_name[]"]').value =
      prefill.name || "";
    row.querySelector('input[name="item_material_norm[]"]').value =
      prefill.norm || "";
    row.querySelector('input[name="item_unit_display[]"]').value =
      prefill.unit || "";
  }
}
function addK7ItemRow(prefill) {
  addGenericItemRow("k7ItemsWrap", "k7ItemRow", prefill);
}

function removeDpbItemRow(rowId) {
  var row = document.getElementById(rowId);
  if (row) row.remove();
}

// bangun <datalist> global sekali saja, dari gabungan materials DB + data/normalisasi.csv
function ensureMaterialDatalists() {
  if (document.getElementById("materialNameList")) return;

  var nameList = document.createElement("datalist");
  nameList.id = "materialNameList";
  var normList = document.createElement("datalist");
  normList.id = "materialNormList";

  document.body.appendChild(nameList);
  document.body.appendChild(normList);
}

// saat user mengetik nama ATAU normalisasi (baris material DPB/K3/K7), cari kecocokan
// di data gabungan (materials DB + data/normalisasi.csv) dan isi field lain otomatis
function onMaterialFieldInput(rowId, field) {
  var row = document.getElementById(rowId);
  if (!row) return;

  var nameInput = row.querySelector('input[name="item_material_name[]"]');
  var normInput = row.querySelector('input[name="item_material_norm[]"]');
  var unitInput = row.querySelector('input[name="item_unit_display[]"]');

  var typed = field === "name" ? nameInput.value : normInput.value;
  var match = findMaterialMatch(typed, field);

  if (match) {
    nameInput.value = match.name;
    normInput.value = match.norm;
    if (match.unit) unitInput.value = match.unit;
  }
}

// ---------- AUTO-ISI DATA VENDOR (dropdown, khusus admin) ----------
function autofillVendor() {
  var select = document.getElementById("dpbVendorSelect");
  if (!select || !select.value) return;

  fetch("index.php?ajax=vendor_info&id=" + encodeURIComponent(select.value))
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      if (data.error) return;
      document.getElementById("dpbSpkInput").value = data.spk_number || "";
      document.getElementById("dpbJenisInput").value =
        data.jenis_pekerjaan || "";
      document.getElementById("dpbIdpelInput").value = data.idpel || "";
      document.getElementById("dpbDayaInput").value = data.daya || "";
      document.getElementById("dpbUlpInput").value = data.ulp || "";
    })
    .catch(function () {});
}

// versi umum utk K3 & K7 (pakai id: {prefix}VendorSelect, {prefix}SpkInput, dst)
function autofillVendorGeneric(prefix) {
  var select = document.getElementById(prefix + "VendorSelect");
  if (!select || !select.value) return;

  fetch("index.php?ajax=vendor_info&id=" + encodeURIComponent(select.value))
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      if (data.error) return;
      var spk = document.getElementById(prefix + "SpkInput");
      var jenis = document.getElementById(prefix + "JenisInput");
      var idpel = document.getElementById(prefix + "IdpelInput");
      var daya = document.getElementById(prefix + "DayaInput");
      var ulp = document.getElementById(prefix + "UlpInput");
      if (spk) spk.value = data.spk_number || "";
      if (jenis) jenis.value = data.jenis_pekerjaan || "";
      if (idpel) idpel.value = data.idpel || "";
      if (daya) daya.value = data.daya || "";
      if (ulp) ulp.value = data.ulp || "";
    })
    .catch(function () {});
}

// =========================================================
// MONITORING / PENCARIAN DPB (AJAX)
// =========================================================
function loadDPB() {
  var tug = document.getElementById("tugNumberInput").value.trim();
  var result = document.getElementById("dpbResult");
  if (!result) return;

  if (!tug) {
    result.innerHTML =
      '<p class="text-small">Masukkan nomor TUG terlebih dahulu.</p>';
    return;
  }

  result.innerHTML = '<p class="text-small">Mencari data...</p>';

  var url = "index.php?ajax=dpb&tug=" + encodeURIComponent(tug);

  fetch(url)
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      if (data.error) {
        result.innerHTML =
          '<p class="text-small">' + escapeHtml(data.error) + "</p>";
        return;
      }
      window.LAST_DPB = data;

      var statusClass =
        data.status === "aktif"
          ? "status-aktif"
          : data.status === "selesai"
            ? "status-selesai"
            : data.status === "menunggu_persetujuan"
              ? "status-pending"
              : data.status === "ditolak"
                ? "status-ditolak"
                : "status-belum";
      var isReadOnly = !window.IS_ADMIN || (data.status === "selesai");

      var items = (data.items || [])
        .map(function (it, i) {
          var receivedCell = "";
          var snCell = "";
          
          if (isReadOnly) {
            receivedCell = String(it.quantity_received ?? "0");
            snCell = escapeHtml(it.sn || "-");
          } else {
            receivedCell = '<input type="number" step="any" min="0" name="item_received[]" value="' +
              (it.quantity_received ?? 0) +
              '" style="width:80px;" class="item-recv-input"><input type="hidden" name="item_id[]" value="' +
              it.id +
              '">';
            snCell = '<div class="sn-cell-container" data-material-name="' + escapeHtml(it.material_name || '') + '" data-item-id="' + it.id + '" data-saved-sn="' + escapeHtml(it.sn || '') + '"></div>';
          }

          return (
            "<tr><td>" +
            (i + 1) +
            "</td><td>" +
            escapeHtml(it.material_name || "-") +
            "</td><td>" +
            escapeHtml(it.norm || "-") +
            "</td><td>" +
            escapeHtml(it.unit || "-") +
            "</td><td>" +
            (it.quantity_requested ?? "-") +
            "</td><td>" +
            receivedCell +
            "</td><td>" +
            snCell +
            "</td></tr>"
          );
        })
        .join("");

      var adminReceivedForm = "";
      if (isReadOnly) {
        adminReceivedForm = '<div class="table-wrap"><table><thead><tr><th>#</th><th>Material</th><th>Norm</th><th>Satuan</th><th>Diminta</th><th>Diterima</th><th>SN</th></tr></thead>' +
          "<tbody>" +
          items +
          "</tbody></table></div>";
      } else {
        adminReceivedForm = '<form method="POST" action="dpb.php" id="dpbUpdateReceivedForm" style="margin-top:0.8rem;">' +
          '<input type="hidden" name="dpb_id" value="' +
          data.id +
          '"><input type="hidden" name="tug_number" value="' +
          escapeHtml(data.tug_number) +
          '">' +
          '<div class="table-wrap"><table><thead><tr><th>#</th><th>Material</th><th>Norm</th><th>Satuan</th><th>Diminta</th><th>Diterima</th><th>SN</th></tr></thead>' +
          "<tbody>" +
          items +
          "</tbody></table></div>" +
          '<button type="submit" name="update_received" class="btn-success" style="margin-top:0.6rem;">Simpan Jumlah Diterima</button>' +
          "</form>";
      }

      var deleteBtn =
        '<a href="dpb.php?delete_dpb=' +
        data.id +
        '" onclick="return confirm(\'Yakin hapus DPB ini?\')" class="btn-danger" style="padding:0.6rem 1.1rem; border-radius:30px; text-decoration:none; font-size:0.85rem;">Hapus DPB</a>';

      var adminStatusForm = !window.IS_ADMIN
        ? ""
        : data.status === "menunggu_persetujuan"
          ? '<div style="margin-top:0.8rem; display:flex; gap:0.6rem; flex-wrap:wrap; align-items:center;">' +
            '<form method="POST" action="dpb.php" style="display:inline;">' +
            '<input type="hidden" name="dpb_id" value="' +
            data.id +
            '">' +
            '<button type="submit" name="approve_dpb" class="btn-success">Setujui Pengajuan</button>' +
            "</form>" +
            '<form method="POST" action="dpb.php" style="display:inline;" onsubmit="return confirm(\'Yakin tolak pengajuan ini?\')">' +
            '<input type="hidden" name="dpb_id" value="' +
            data.id +
            '">' +
            '<button type="submit" name="reject_dpb" class="btn-danger">Tolak Pengajuan</button>' +
            "</form>" +
            deleteBtn +
            "</div>"
          : '<div style="margin-top:0.8rem; display:flex; gap:0.6rem; flex-wrap:wrap; align-items:flex-end;">' +
            deleteBtn +
            "</div>";

      var sjNumberHtml = "";
      if (data.surat_jalan_number) {
        sjNumberHtml = "<p style='margin:0.5rem 0 0 0;'><strong>No. Surat Jalan:</strong> <span style='font-size:1.15rem; color:#0b2b4a; font-weight:700;'>" + escapeHtml(data.surat_jalan_number) + "</span></p>";
      } else if (data.next_surat_jalan_number) {
        sjNumberHtml = "<p style='margin:0.5rem 0 0 0;'><strong>No. Surat Jalan (Otomatis):</strong> <span style='font-size:1.15rem; color:#64748b; font-weight:700;'>" + escapeHtml(data.next_surat_jalan_number) + "</span></p>";
      }

      result.innerHTML =
        '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.6rem;">' +
        "<p style='margin:0;'><strong>No. TUG:</strong> " +
        escapeHtml(data.tug_number || "-") +
        "</p>" +
        '<span class="status-badge ' +
        statusClass +
        '">' +
        escapeHtml(data.status_label || "-") +
        "</span></div>" +
        sjNumberHtml +
        "<p><strong>Vendor:</strong> " +
        escapeHtml(data.vendor_name || "-") +
        "</p>" +
        "<p><strong>Pelanggan:</strong> " +
        escapeHtml(data.customer_name || "-") +
        "</p>" +
        "<p><strong>Alamat:</strong> " +
        escapeHtml(data.customer_address || "-") +
        "</p>" +
        "<p><strong>No. SPK:</strong> " +
        escapeHtml(data.spk_number || "-") +
        "</p>" +
        "<p><strong>Jenis Pekerjaan:</strong> " +
        escapeHtml(data.jenis_pekerjaan || "-") +
        " &nbsp; <strong>ULP:</strong> " +
        escapeHtml(data.ulp || "-") +
        " &nbsp; <strong>Daya:</strong> " +
        escapeHtml(data.daya || "-") +
        "</p>" +
        adminReceivedForm +
        adminStatusForm +
        '<p class="ttd-mengetahui">Mengetahui,</p>' +
        buildTtdBox(
          [
            { field: "setuju_name", label: "Setuju" },
            { field: "kepala_gudang_name", label: "Kepala Gudang" },
            { field: "pemeriksa_pengawas_name", label: "Pemeriksa / Petugas" },
            { field: "penerima_name", label: "Penerima" },
            { field: "security_name", label: "Security" },
            { field: "menyerahkan_name", label: "Yang Menyerahkan" },
          ],
          data,
          "dpb.php",
          "dpb_id",
          "update_signers",
          [
            { field: "diterima_tgl", prefix: "Diterima tgl" },
            { field: "malang_tanggal", prefix: "Malang," },
          ],
        );

      if (!isReadOnly) {
        var snCells = result.querySelectorAll('.sn-cell-container');
        snCells.forEach(function (cell) {
          var row = cell.closest('tr');
          var recvInput = row.querySelector('.item-recv-input');
          if (recvInput) {
            var updateHandler = function () {
              var qty = parseFloat(recvInput.value) || 0;
              var materialName = cell.getAttribute('data-material-name');
              var itemId = cell.getAttribute('data-item-id');
              var savedSnAttr = cell.getAttribute('data-saved-sn') || '';
              var savedSns = savedSnAttr.split(',').map(function (s) { return s.trim(); }).filter(Boolean);

              if (isMaterialWajibSN(materialName)) {
                if (qty <= 0) {
                  cell.innerHTML = '<span style="color:#64748b; font-size:11px;">Tidak ada material diterima</span>';
                } else {
                  var html = '<div style="display:flex; flex-direction:column; gap:6px; margin:4px 0;">';
                  for (var j = 0; j < qty; j++) {
                    var val = savedSns[j] || '';
                    html += '<div style="display:flex; align-items:center; gap:8px;">' +
                            '<span style="font-size:11px; color:#64748b; white-space:nowrap;">SN ' + (j + 1) + ':</span>' +
                            '<input type="text" name="item_sn_' + itemId + '[]" value="' + escapeHtml(val) + '" class="form-control sn-input-field" placeholder="Masukkan SN ' + (j + 1) + '" style="font-size:12px; padding:4px 8px; width:100%; border-radius:6px; border:1px solid #cbd5e1;" required>' +
                            '</div>';
                  }
                  html += '</div>';
                  cell.innerHTML = html;
                }
              } else {
                var val = savedSns.join(', ');
                cell.innerHTML = '<input type="text" name="item_sn_' + itemId + '[]" value="' + escapeHtml(val) + '" class="form-control" placeholder="Optional SN" style="font-size:12px; padding:4px 8px; width:100%; border-radius:6px; border:1px solid #cbd5e1;">';
              }
            };

            updateHandler();
            recvInput.addEventListener('input', updateHandler);
            recvInput.addEventListener('change', updateHandler);
          }
        });

        var form = result.querySelector('#dpbUpdateReceivedForm');
        if (form) {
          form.addEventListener('submit', function (e) {
            var inputs = form.querySelectorAll('.item-recv-input');
            var ok = true;
            var currentRequestSns = [];
            inputs.forEach(function (recvInput) {
              if (!ok) return;
              var row = recvInput.closest('tr');
              var cell = row.querySelector('.sn-cell-container');
              if (cell) {
                var materialName = cell.getAttribute('data-material-name');
                var qty = parseFloat(recvInput.value) || 0;
                if (isMaterialWajibSN(materialName) && qty > 0) {
                  var snFields = cell.querySelectorAll('.sn-input-field');
                  if (snFields.length !== qty) {
                    alert('Jumlah input SN tidak sama dengan jumlah Diterima untuk material: ' + materialName);
                    e.preventDefault();
                    ok = false;
                    return;
                  }
                  snFields.forEach(function (f) {
                    var val = f.value.trim();
                    if (!val) {
                      alert('Serial Number (SN) wajib diisi untuk material: ' + materialName);
                      e.preventDefault();
                      f.focus();
                      ok = false;
                      return;
                    }
                    if (currentRequestSns.indexOf(val.toLowerCase()) !== -1) {
                      alert('Serial Number (SN) "' + val + '" tidak boleh diduplikat dalam satu pengajuan.');
                      e.preventDefault();
                      f.focus();
                      ok = false;
                      return;
                    }
                    currentRequestSns.push(val.toLowerCase());
                  });
                }
              }
            });
            return ok;
          });
        }
      }
    })
    .catch(function () {
      result.innerHTML = '<p class="text-small">Gagal memuat data DPB.</p>';
    });
}

// ---------- ANGKA KE HURUF (untuk kolom "dengan huruf") ----------
function terbilangID(n) {
  n = Math.floor(Math.abs(Number(n) || 0));
  var satuan = [
    "",
    "satu",
    "dua",
    "tiga",
    "empat",
    "lima",
    "enam",
    "tujuh",
    "delapan",
    "sembilan",
    "sepuluh",
    "sebelas",
  ];
  function w(num) {
    if (num < 12) return satuan[num];
    if (num < 20) return w(num - 10) + " belas";
    if (num < 100)
      return (
        w(Math.floor(num / 10)) + " puluh" + (num % 10 ? " " + w(num % 10) : "")
      );
    if (num < 200) return "seratus" + (num % 100 ? " " + w(num % 100) : "");
    if (num < 1000)
      return (
        w(Math.floor(num / 100)) +
        " ratus" +
        (num % 100 ? " " + w(num % 100) : "")
      );
    if (num < 2000) return "seribu" + (num % 1000 ? " " + w(num % 1000) : "");
    if (num < 1000000)
      return (
        w(Math.floor(num / 1000)) +
        " ribu" +
        (num % 1000 ? " " + w(num % 1000) : "")
      );
    return (
      w(Math.floor(num / 1000000)) +
      " juta" +
      (num % 1000000 ? " " + w(num % 1000000) : "")
    );
  }
  if (n === 0) return "nol";
  return w(n).trim();
}

// ---------- UTIL TABEL CETAK (diisi item + dipad sampai minimal N baris, seperti form asli) ----------
// includeHuruf: true -> render 2 sub-kolom (angka + huruf) per qtyField, seperti K3/K7
//               false -> render 1 sub-kolom (angka saja), seperti DPB/Surat Angkutan
function printItemRows(items, qtyFields, includeHuruf, minRows) {
  includeHuruf = includeHuruf !== false;
  minRows = minRows || 8;
  var dotted = "border-bottom:1px dotted #000;";
  var rows = (items || []).map(function (it, i) {
    var qtyCells = qtyFields
      .map(function (f) {
        var v = it[f] || 0;
        var cells =
          '<td style="border-left:1px solid #000; border-right:1px solid #000; ' +
          dotted +
          'padding:3px 6px;text-align:center;">' +
          v +
          "</td>";
        if (includeHuruf) {
          cells +=
            '<td style="border-right:1px solid #000; ' +
            dotted +
            'padding:3px 6px;">' +
            terbilangID(v) +
            "</td>";
        }
        return cells;
      })
      .join("");
    return (
      '<tr style="' +
      dotted +
      '">' +
      '<td style="border-left:1px solid #000; border-right:1px solid #000; ' +
      dotted +
      'padding:3px 6px;text-align:center;">' +
      (i + 1) +
      "</td>" +
      '<td style="border-right:1px solid #000; ' +
      dotted +
      'padding:3px 6px;">' +
      escapeHtml(it.material_name || "") +
      "</td>" +
      '<td style="border-right:1px solid #000; ' +
      dotted +
      'padding:3px 6px;text-align:center;">' +
      escapeHtml(it.norm || "") +
      "</td>" +
      '<td style="border-right:1px solid #000; ' +
      dotted +
      'padding:3px 6px;text-align:center;">' +
      escapeHtml(it.unit || "") +
      "</td>" +
      qtyCells +
      "</tr>"
    );
  });
  while (rows.length < minRows) {
    var blankQty = qtyFields
      .map(function () {
        var cells =
          '<td style="border-left:1px solid #000; border-right:1px solid #000; ' +
          dotted +
          'padding:3px 6px;">&nbsp;</td>';
        if (includeHuruf)
          cells +=
            '<td style="border-right:1px solid #000; ' +
            dotted +
            'padding:3px 6px;">&nbsp;</td>';
        return cells;
      })
      .join("");
    rows.push(
      '<tr style="' +
        dotted +
        '">' +
        '<td style="border-left:1px solid #000; border-right:1px solid #000; ' +
        dotted +
        'padding:3px 6px;text-align:center;">' +
        (rows.length + 1) +
        "</td>" +
        '<td style="border-right:1px solid #000; ' +
        dotted +
        'padding:3px 6px;">&nbsp;</td>' +
        '<td style="border-right:1px solid #000; ' +
        dotted +
        'padding:3px 6px;">&nbsp;</td>' +
        '<td style="border-right:1px solid #000; ' +
        dotted +
        'padding:3px 6px;">&nbsp;</td>' +
        blankQty +
        "</tr>",
    );
  }
  return rows.join("");
}

function romanMonth(m) {
  var romans = [
    "I",
    "II",
    "III",
    "IV",
    "V",
    "VI",
    "VII",
    "VIII",
    "IX",
    "X",
    "XI",
    "XII",
  ];
  return romans[(((m - 1) % 12) + 12) % 12] || "I";
}

function printTtdRow(cols) {
  return (
    '<table style="width:100%; border-collapse:collapse; margin-top:2rem;"><tr>' +
    cols
      .map(function (c) {
        return (
          '<td style="width:' +
          100 / cols.length +
          '%; padding:0 8px; text-align:center; vertical-align:top;">' +
          '<div style="font-weight:600;">' +
          escapeHtml(c.label) +
          " :</div>" +
          '<div style="height:60px;"></div>' +
          '<div style="border-top:1px solid #000; padding-top:4px;">' +
          escapeHtml(c.value || "") +
          "</div>" +
          "</td>"
        );
      })
      .join("") +
    "</tr></table>"
  );
}

function openPrintDoc(html) {
  var area = document.getElementById("printArea");
  if (!area) return;
  area.innerHTML = html;
  window.print();
}

// ---------- CETAK DPB (replika persis SURAT ANGKUTAN) ----------
var ROMAWI_BULAN = [
  "I",
  "II",
  "III",
  "IV",
  "V",
  "VI",
  "VII",
  "VIII",
  "IX",
  "X",
  "XI",
  "XII",
];

function isMaterialWajibSN(name) {
  var whitelist = [
    'MTR;kWH E-PR;;1P;230V;5-60A;1;;2W',
    'MTR;kWH E;;1P;230V;5-60A;1;;2W',
    'MCB;230/400V;1P;2A;50Hz;',
    'MCB;230/400V;1P;4A;50Hz;',
    'MCB;230/400V;1P;6A;50Hz;',
    'MCB;230/400V;1P;10A;50Hz;',
    'MCB;230/400V;1P;16A;50Hz;',
    'MCB;230/400V;1P;20A;50Hz;',
    'MCB;230/400V;1P;25A;50Hz;',
    'MCB;230/400V;1P;35A;50Hz;',
    'MCB;230/400V;1P;50A;50Hz;',
    'MCB;230/400V;3P;20A;50Hz;',
    'MTR;kWH E;;3P;230/400V;5-80A;1;;4W',
    'MCB;230/400V;3P;35A;50Hz;',
    'MTR;kWHE;;3P;57.7/100V-230/400;5A;0.5;4W',
    'MCB;230/400V;3P;16A;50Hz;',
    'MTR;kWH E-PR;;3P;230/400V;5-80A;1;;4W',
    'MCB;230/400V;3P;25A;50Hz;',
    'TRF DIS;D3;20kV/400V;3P;100kVA;YZN5;OD',
    'TRF DIS;D3;20kV/400V;3P;160kVA;YZN5;OD',
    'TRF DIS;D3;20kV/400V;3P;250kVA;DYN5;OD',
    'MCB;230/400V;3P;10A;50Hz;',
    'LVSB;DIST;3P;400V;250A;2LINE;OD',
    'LVSB;DIST;3P;400V;400A;4LINE;OD',
    'BOX 53KVA - BOX;APPMCCB80A+STRIP;AL2MM;1205X420X250',
    'BOX 66KVA - BOX;APPMCCB100A+STRIP;AL2MM;1205X420X250',
    'BOX 82,5 KVA - BOX;APPMCCB125A+STRIP;AL2MM;1205X420X250',
    'BOX 105 KVA - BOX;APPMCCB160A+STRIP;AL2MM;1205X420X250',
    'BOX 131 KVA - BOX;APPMCCB200A+STRIP;AL2MM;1205X420X250',
    'BOX 147 KVA - BOX;APPMCCB225A+STRIP;AL2MM;1205X420X250',
    'BOX 164 KVA - BOX;APPMCCB250A+STRIP;AL2MM;1205X420X250',
    'BOX 197 KVA - BOX;APPMCCB300A+STRIP;AL2MM;1205X420X250',
    'BOX TR - BOX;APP PL CB;AL1.6MM;650X400X220MM'
  ];
  if (!name) return false;
  var normalized = name.trim().toUpperCase();
  for (var i = 0; i < whitelist.length; i++) {
    if (whitelist[i].trim().toUpperCase() === normalized) {
      return true;
    }
  }
  return false;
}

function dottedLine(width) {
  return (
    '<span style="display:inline-block; border-bottom:1px dotted #000; width:' +
    (width || "150px") +
    '; height:14px;"></span>'
  );
}

function printDPB() {
  if (!window.IS_ADMIN) {
    alert("Akses ditolak: Anda tidak diperbolehkan mencetak atau mengunduh Surat Jalan.");
    return;
  }
  var data = window.LAST_DPB;
  if (!data) {
    alert("Cari nomor TUG dulu sebelum mencetak.");
    return;
  }

  // Jika belum ada nomor surat jalan, generate dan simpan secara permanen di database lewat AJAX
  if (!data.surat_jalan_number) {
    var url = "index.php?ajax=generate_sj&dpb_id=" + encodeURIComponent(data.id);
    fetch(url)
      .then(function(res) { return res.json(); })
      .then(function(resData) {
        if (resData.surat_jalan_number) {
          data.surat_jalan_number = resData.surat_jalan_number;
          // Render ulang No. Surat Jalan pada detail panel di DOM agar terupdate tanpa reload page
          var sjEl = document.querySelector("#dpbResult");
          if (sjEl) {
            // Kita bisa trigger reload detail panel untuk mengupdate UI
            loadDPB();
          }
          // Panggil kembali printDPB setelah nomor tersimpan
          printDPB();
        } else {
          alert("Gagal membuat nomor surat jalan otomatis.");
        }
      })
      .catch(function() {
        alert("Terjadi kesalahan jaringan saat membuat nomor surat jalan.");
      });
    return;
  }

  var d = data.tanggal_diminta ? new Date(data.tanggal_diminta) : new Date();
  var noSurat = data.surat_jalan_number;

  function formatDateJS(dateStr) {
    if (!dateStr) return ".......................";
    var parts = dateStr.split('-');
    if (parts.length === 3) {
      if (parts[0].length === 4) {
        return parts[2] + '-' + parts[1] + '-' + parts[0];
      }
    }
    return dateStr;
  }
  var diterimaTglStr = formatDateJS(data.diterima_tgl);
  var malangTanggalStr = formatDateJS(data.malang_tanggal);

  var items = (data.items || []).slice();
  while (items.length < 5) items.push({});
  var itemRows = items
    .map(function (it, i) {
      var isBlank = !it.material_name;
      return (
        '<tr style="height:26px;">' +
        '<td style="border-left:1px solid #000; border-right:1px solid #000; border-bottom:1px dotted #000; text-align:center;">' +
        (i + 1) +
        "</td>" +
        '<td style="border-right:1px solid #000; border-bottom:1px dotted #000; padding-left:4px;">' +
        (isBlank ? "" : escapeHtml(it.material_name || "")) +
        "</td>" +
        '<td style="border-right:1px solid #000; border-bottom:1px dotted #000; text-align:center;">' +
        (isBlank ? "" : escapeHtml(it.norm || "")) +
        "</td>" +
        '<td style="border-right:1px solid #000; border-bottom:1px dotted #000; text-align:center;">' +
        (isBlank ? "" : escapeHtml(it.unit || "")) +
        "</td>" +
        '<td style="border-right:1px solid #000; border-bottom:1px dotted #000; text-align:center;">' +
        (isBlank ? "" : it.quantity_received || 0) +
        "</td>" +
        '<td style="border-right:1px solid #000; border-bottom:1px dotted #000; text-align:center;">' +
        (isBlank ? "" : it.quantity_requested || 0) +
        "</td>" +
        "</tr>"
      );
    })
    .join("");

  var html =
    '<div style="font-family: Arial, sans-serif; font-size:11px; color:#000; padding:24px;">' +
    // ===== KOP SURAT =====
    '<table style="width:100%; border-collapse:collapse; margin-bottom:4px;"><tr>' +
    '<td style="width:65%; vertical-align:top;">' +
    '<table style="border-collapse:collapse;"><tr>' +
    '<td style="vertical-align:top; padding-right:8px;"><img src="images/logoPln.png" style="width:42px; height:auto;"></td>' +
    '<td style="vertical-align:top; line-height:1.5; padding-top:2px;">' +
    '<div style="font-weight:800; font-size:13px;">PT PLN (PERSERO)</div>' +
    "<div>UNIT INDUK DISTRIBUSI (UID) JAWA TIMUR</div>" +
    "<div>UNIT PELAKSANA PELAYANAN PELANGGAN (UP3) MALANG</div>" +
    "</td>" +
    "</tr></table>" +
    "</td>" +
    '<td style="width:35%; vertical-align:top;">' +
    '<table style="width:100%; border-collapse:collapse; font-size:9.5px;">' +
    '<tr><td style="border:1px solid #000; padding:4px 6px; text-align:center; font-weight:600;">1. Pengantar&nbsp;&nbsp;2. Security&nbsp;&nbsp;3. Pengambil material</td></tr>' +
    '<tr><td style="border:1px solid #000; border-top:none; padding:4px 6px; text-align:center;">' +
    '<div style="font-weight:800;">PERHATIAN :</div>' +
    "SEMUA RESIKO SETELAH MATERIAL KELUAR DARI LOGISTIK, MENJADI TANGGUNG JAWAB PENGAMBIL MATERIAL" +
    "</td></tr>" +
    "</table>" +
    "</td>" +
    "</tr></table>" +
    // ===== JUDUL =====
    '<h2 style="text-align:center; text-decoration:underline; margin:16px 0 2px; font-size:16px;">SURAT ANGKUTAN</h2>' +
    '<p style="text-align:center; margin:0 0 10px; font-weight:600;">' +
    escapeHtml(noSurat) +
    "</p>" +
    // ===== INFO ATAS =====
    '<table style="width:100%; border-collapse:collapse; margin-top:6px;">' +
    "<tr>" +
    '<td style="width:14%; padding:2px 0;">Kendaraan No.</td><td style="width:2%;">:</td>' +
    '<td style="width:34%;">' +
    dottedLine("220px") +
    "</td>" +
    '<td style="width:50%; text-align:right; font-weight:800; vertical-align:bottom;">' +
    escapeHtml(data.vendor_name || "") +
    "</td>" +
    "</tr>" +
    "<tr>" +
    '<td style="padding:2px 0;">Nama Pengemudi</td><td>:</td>' +
    "<td>" +
    dottedLine("220px") +
    "</td><td></td>" +
    "</tr>" +
    "<tr>" +
    '<td style="padding:6px 0 2px; vertical-align:top;">Dari Logistik</td><td style="vertical-align:top;">:</td>' +
    '<td style="vertical-align:top;">UP3 MALANG<br><br>SPK NO. :</td>' +
    '<td style="text-align:right; vertical-align:top;">' +
    escapeHtml(data.ulp || "") +
    "<br>" +
    '<span style="text-decoration:underline; font-weight:600;">' +
    escapeHtml(data.spk_number || "") +
    "</span><br>" +
    escapeHtml(data.customer_name || "") +
    "</td>" +
    "</tr>" +
    "</table>" +
    // ===== TABEL MATERIAL =====
    '<table style="width:100%; border-collapse:collapse; margin-top:14px; border:1px solid #000;">' +
    "<thead>" +
    '<tr style="text-align:center; font-weight:600;">' +
    '<td rowspan="2" style="border:1px solid #000; width:5%; padding:4px;">No.<br>Urut</td>' +
    '<td rowspan="2" style="border:1px solid #000; width:38%; padding:4px;">Nama Barang<br>(ditulis selengkap - lengkapnya)</td>' +
    '<td rowspan="2" style="border:1px solid #000; width:15%; padding:4px;">No.<br>Normalisasi</td>' +
    '<td rowspan="2" style="border:1px solid #000; width:8%; padding:4px;">Sa-<br>tuan</td>' +
    '<td style="border:1px solid #000; width:17%; padding:4px;">Banyaknya yang<br>diberikan</td>' +
    '<td style="border:1px solid #000; width:17%; padding:4px;">Banyaknya yang<br>diminta</td>' +
    "</tr>" +
    '<tr style="text-align:center; font-weight:600;">' +
    '<td style="border:1px solid #000; padding:4px;">dengan angka</td>' +
    '<td style="border:1px solid #000; padding:4px;">dengan angka</td>' +
    "</tr>" +
    "</thead>" +
    "<tbody>" +
    itemRows +
    "</tbody>" +
    "</table>" +
    // ===== INFO BAWAH + KOTAK TUG =====
    '<table style="width:100%; border-collapse:collapse; margin-top:2px;"><tr>' +
    '<td style="width:70%; vertical-align:top; border:1px solid #000; border-top:none; padding:6px 8px;">' +
    "VENDOR&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: " +
    escapeHtml(data.vendor_name || "") +
    "<br>" +
    'NO. SPK&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: <span style="text-decoration:underline;">' +
    escapeHtml(data.spk_number || "") +
    "</span><br><br>" +
    "JENIS PEKERJAAN&nbsp;: " +
    escapeHtml(data.jenis_pekerjaan || "") +
    "<br>" +
    "IDPEL&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: " +
    escapeHtml(data.idpel || "") +
    "<br>" +
    "NAMA PELANGGAN&nbsp;: " +
    escapeHtml(data.customer_name || "") +
    "<br>" +
    "ALAMAT PELANGGAN&nbsp;: " +
    escapeHtml(data.customer_address || "") +
    "<br>" +
    "DAYA&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: " +
    escapeHtml(data.daya || "") +
    "<br>" +
    "ULP&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: " +
    escapeHtml(data.ulp || "") +
    "</td>" +
    '<td style="width:30%; vertical-align:middle; border:1px solid #000; border-top:none; border-left:none; text-align:center; padding:10px;">' +
    '<div style="border:2px solid #c00; color:#c00; font-weight:800; font-size:20px; padding:14px 6px; line-height:1.2;">' +
    escapeHtml(data.tug_number || "") +
    "</div>" +
    "</td>" +
    "</tr></table>" +
    // ===== FOOTER TTD =====
    '<table style="width:100%; margin-top:16px;"><tr>' +
    "<td>Diterima tgl " +
    escapeHtml(diterimaTglStr) +
    "</td>" +
    '<td style="text-align:right;">Malang, ' +
    escapeHtml(malangTanggalStr) +
    "</td>" +
    "</tr></table>" +
    '<p style="text-align:center; margin:8px 0;">Mengetahui,</p>' +
    printTtdRow([
      { label: "Penerima", value: data.penerima_name },
      { label: "Security", value: data.security_name },
      { label: "Yang Menyerahkan", value: data.menyerahkan_name },
    ]) +
    "</div>";

  openPrintDoc(html);
}
function saveDPBpdf() {
  printDPB();
}

// =========================================================
// MONITORING / PENCARIAN K3 (Bon Pengembalian Material)
// =========================================================
function loadK3() {
  var tug = document.getElementById("k3TugInput").value.trim();
  var result = document.getElementById("k3Result");
  if (!result) return;
  if (!tug) {
    result.innerHTML =
      '<p class="text-small">Masukkan nomor TUG K3 terlebih dahulu.</p>';
    return;
  }
  result.innerHTML = '<p class="text-small">Mencari data...</p>';

  fetch("index.php?ajax=k3&tug=" + encodeURIComponent(tug))
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      if (data.error) {
        result.innerHTML =
          '<p class="text-small">' + escapeHtml(data.error) + "</p>";
        return;
      }
      window.LAST_K3 = data;
      var statusClass =
        data.status === "aktif"
          ? "status-aktif"
          : data.status === "selesai"
            ? "status-selesai"
            : "status-belum";

      var isReadOnly = !window.IS_ADMIN || (data.status === "selesai");

      var adminReceivedForm = "";
      if (window.IS_ADMIN) {
        var disabledAttr = isReadOnly ? " disabled" : "";
        var items = (data.items || [])
          .map(function (it, i) {
            var receivedCell = isReadOnly
              ? String(it.quantity_received ?? "0")
              : '<input type="number" step="any" min="0" name="item_received[]" value="' + (it.quantity_received ?? 0) + '" style="width:80px;"><input type="hidden" name="item_id[]" value="' + it.id + '">';
            var kodeCell = isReadOnly
              ? escapeHtml(it.kode || "-")
              : '<input type="text" name="item_kode[]" value="' + escapeHtml(it.kode || "") + '" style="width:70px;">';
            var hargaCell = isReadOnly
              ? String(it.harga_satuan ?? "0")
              : '<input type="number" step="any" min="0" name="item_harga_satuan[]" value="' + (it.harga_satuan ?? 0) + '" style="width:100px;">';
            return (
              "<tr><td>" +
              (i + 1) +
              "</td><td>" +
              escapeHtml(it.material_name || "-") +
              "</td><td>" +
              escapeHtml(it.norm || "-") +
              "</td><td>" +
              escapeHtml(it.unit || "-") +
              "</td><td>" +
              (it.quantity_returned ?? "-") +
              "</td><td>" +
              receivedCell +
              "</td><td>" +
              kodeCell +
              "</td><td>" +
              hargaCell +
              "</td></tr>"
            );
          })
          .join("");

        var saveBtn = isReadOnly ? "" : '<button type="submit" name="update_k3_received" class="btn-success" style="margin-top:0.6rem;">Simpan Jumlah Diterima</button>';
        adminReceivedForm = isReadOnly
          ? '<div class="table-wrap"><table><thead><tr><th>#</th><th>Material</th><th>Norm</th><th>Satuan</th><th>Dikembalikan</th><th>Diterima</th><th>Kode</th><th>Harga Satuan</th></tr></thead><tbody>' + items + '</tbody></table></div>'
          : '<form method="POST" action="k3.php" style="margin-top:0.8rem;">' +
            '<input type="hidden" name="k3_id" value="' + data.id + '">' +
            '<input type="hidden" name="tug_number" value="' + escapeHtml(data.tug_number) + '">' +
            '<div class="table-wrap"><table><thead><tr><th>#</th><th>Material</th><th>Norm</th><th>Satuan</th><th>Dikembalikan</th><th>Diterima</th><th>Kode</th><th>Harga Satuan</th></tr></thead><tbody>' + items + '</tbody></table></div>' +
            saveBtn +
            '</form>';
      } else {
        var items = (data.items || [])
          .map(function (it, i) {
            return (
              "<tr><td>" +
              (i + 1) +
              "</td><td>" +
              escapeHtml(it.material_name || "-") +
              "</td><td>" +
              escapeHtml(it.norm || "-") +
              "</td><td>" +
              escapeHtml(it.unit || "-") +
              "</td><td>" +
              (it.quantity_returned ?? "-") +
              "</td><td>" +
              String(it.quantity_received ?? "-") +
              "</td><td>" +
              escapeHtml(it.kode || "-") +
              "</td><td>" +
              String(it.harga_satuan ?? "-") +
              "</td></tr>"
            );
          })
          .join("");
        adminReceivedForm = '<div class="table-wrap"><table><thead><tr><th>#</th><th>Material</th><th>Norm</th><th>Satuan</th><th>Dikembalikan</th><th>Diterima</th><th>Kode</th><th>Harga Satuan</th></tr></thead><tbody>' + items + '</tbody></table></div>';
      }

      var adminDetailsForm = "";
      if (window.IS_ADMIN) {
        var disabledAttr = isReadOnly ? " disabled" : "";
        var saveBtn = isReadOnly ? "" : '<button type="submit" name="update_k3_details" class="btn-success" style="margin-top:0.4rem;">Simpan Detail Bon</button>';
        adminDetailsForm = '<form method="POST" action="k3.php" style="margin-top:0.8rem; background:#f7f9fc; padding:0.8rem; border-radius:14px;">' +
          '<input type="hidden" name="k3_id" value="' +
          data.id +
          '">' +
          '<input type="hidden" name="tug_number" value="' +
          escapeHtml(data.tug_number) +
          '">' +
          '<p style="margin:0 0 0.5rem; font-weight:600; color:#0b2b4a;">Detail Bon</p>' +
          '<div class="flex-row">' +
          '<div class="form-group"><label>Nomor Seri</label><input type="text" name="nomor_seri" value="' +
          escapeHtml(data.nomor_seri || "") +
          '"' + disabledAttr + '></div>' +
          '<div class="form-group"><label>Kondisi Material</label><select name="kondisi_material"' + disabledAttr + '>' +
          ["masih_dapat_dipergunakan", "rusak", "baru", "garansi"]
            .map(function (k) {
              var labels = {
                masih_dapat_dipergunakan: "Masih Dapat Dipergunakan",
                rusak: "Rusak",
                baru: "Baru",
                garansi: "Garansi",
              };
              return (
                '<option value="' +
                k +
                '"' +
                (data.kondisi_material === k ? " selected" : "") +
                ">" +
                labels[k] +
                "</option>"
              );
            })
            .join("") +
          "</select></div>" +
          '<div class="form-group"><label>Keterangan Detile</label><input type="text" name="keterangan" value="' +
          escapeHtml(data.keterangan || "") +
          '"' + disabledAttr + '></div>' +
          "</div>" +
          '<div class="flex-row">' +
          '<div class="form-group"><label>No. DPB / Bukti</label><input type="text" name="no_dpb_bukti" value="' +
          escapeHtml(data.no_dpb_bukti || "") +
          '"' + disabledAttr + '></div>' +
          '<div class="form-group"><label>Lokasi Penempatan Material/Dipakai</label><input type="text" name="lokasi_penempatan" value="' +
          escapeHtml(data.lokasi_penempatan || "") +
          '"' + disabledAttr + '></div>' +
          "</div>" +
          saveBtn +
          "</form>";
      }

      var adminStatusForm = (window.IS_ADMIN && !isReadOnly)
        ? '<div style="margin-top:0.8rem; display:flex; gap:0.6rem; align-items:flex-end; flex-wrap:wrap;">' +
          '<a href="k3.php?delete_k3=' +
          data.id +
          '" onclick="return confirm(\'Yakin hapus?\')" class="btn-danger" style="padding:0.6rem 1.1rem; border-radius:30px; text-decoration:none; font-size:0.85rem;">Hapus</a>' +
          "</div>"
        : "";

      result.innerHTML =
        '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.6rem;">' +
        "<p style='margin:0;'><strong>No. TUG:</strong> " +
        escapeHtml(data.tug_number || "-") +
        "</p>" +
        '<span class="status-badge ' +
        statusClass +
        '">' +
        escapeHtml(data.status_label || "-") +
        "</span></div>" +
        "<p><strong>Vendor:</strong> " +
        escapeHtml(data.vendor_name || "-") +
        "</p>" +
        "<p><strong>Pelanggan:</strong> " +
        escapeHtml(data.customer_name || "-") +
        "</p>" +
        "<p><strong>Alamat:</strong> " +
        escapeHtml(data.customer_address || "-") +
        "</p>" +
        "<p><strong>No. SPK:</strong> " +
        escapeHtml(data.spk_number || "-") +
        "</p>" +
        "<p><strong>Kondisi Material:</strong> " +
        escapeHtml(data.kondisi_label || "-") +
        " &nbsp; <strong>Gudang:</strong> " +
        escapeHtml(data.gudang_pengembalian || "-") +
        "</p>" +
        (data.keterangan
          ? "<p><strong>Keterangan:</strong> " +
            escapeHtml(data.keterangan) +
            "</p>"
          : "") +
        (data.nomor_seri
          ? "<p><strong>Nomor Seri:</strong> " +
            escapeHtml(data.nomor_seri) +
            "</p>"
          : "") +
        adminDetailsForm +
        adminReceivedForm +
        adminStatusForm +
        buildTtdBox(
          [
            { field: "setuju_name", label: "Setuju" },
            { field: "kepala_gudang_name", label: "Kepala Gudang" },
            { field: "pemeriksa_pengawas_name", label: "Pemeriksa / Pengawas" },
            { field: "yang_menyerahkan_name", label: "Yang Menyerahkan" },
          ],
          data,
          "k3.php",
          "k3_id",
          "update_k3_signers",
        );
    })
    .catch(function () {
      result.innerHTML = '<p class="text-small">Gagal memuat data K3.</p>';
    });
}

function printK3() {
  var data = window.LAST_K3;
  if (!data) {
    alert("Cari nomor TUG K3 dulu sebelum mencetak.");
    return;
  }
  var itemRows = printItemRows(data.items, [
    "quantity_returned",
    "quantity_received",
  ]);
  var kondisiOpts = ["rusak", "masih_dapat_dipergunakan", "baru", "garansi"];
  var kondisiLabels = {
    rusak: "Rusak",
    masih_dapat_dipergunakan: "Masih dapat dipergunakan",
    baru: "Baru",
    garansi: "Garansi",
  };
  var kondisiList = kondisiOpts
    .map(function (k) {
      var isSel = k === data.kondisi_material;
      return (
        '<div style="' +
        (isSel ? "background:#ffe98a; font-weight:600;" : "") +
        '">' +
        kondisiLabels[k] +
        "</div>"
      );
    })
    .join("");

  var html =
    '<div style="font-family:Arial, sans-serif; font-size:12px; color:#000; padding:20px;">' +
    '<table style="width:100%; border-collapse:collapse;"><tr>' +
    '<td style="width:20%; font-size:26px; font-weight:800; vertical-align:middle;">TUG</td>' +
    '<td style="width:55%; text-align:center; vertical-align:middle;">' +
    "PT. PLN (PERSERO) UID JATIM UP3 MALANG<br>" +
    '<span style="font-size:18px; font-weight:800;">BON PENGEMBALIAN MATERIAL</span>' +
    "</td>" +
    '<td style="width:25%; text-align:center; vertical-align:middle; color:#c00; font-weight:800; font-size:16px;">' +
    escapeHtml(data.tug_number || "") +
    "</td></tr></table>" +
    '<table style="width:100%; margin-top:10px;"><tr><td style="width:50%; vertical-align:top;">' +
    "Tanggal diminta : " +
    escapeHtml(data.tanggal_diminta || "") +
    "<br>" +
    "Kepada : PT PLN (PERSERO) UP3 MALANG<br>" +
    "Gudang : " +
    escapeHtml(data.gudang_pengembalian || "") +
    '</td><td style="width:50%; vertical-align:top;">' +
    "Pengiriman dari : " +
    escapeHtml(data.vendor_name || "") +
    "<br>" +
    escapeHtml(data.vendor_address || "") +
    "</td></tr></table>" +
    '<table style="width:100%; border-collapse:collapse; margin-top:10px;">' +
    '<thead><tr style="background:#eee;">' +
    '<th style="border:1px solid #000; padding:4px;">No</th>' +
    '<th style="border:1px solid #000; padding:4px;">Nama Barang</th>' +
    '<th style="border:1px solid #000; padding:4px;">No. Normalisasi</th>' +
    '<th style="border:1px solid #000; padding:4px;">Satuan</th>' +
    '<th style="border:1px solid #000; padding:4px;" colspan="2">Banyaknya Dikembalikan</th>' +
    '<th style="border:1px solid #000; padding:4px;" colspan="2">Banyaknya Diterima</th>' +
    "</tr></thead><tbody>" +
    itemRows +
    "</tbody></table>" +
    '<table style="width:100%; margin-top:14px;"><tr><td style="width:60%; vertical-align:top;">' +
    "VENDOR : " +
    escapeHtml(data.vendor_name || "") +
    "<br>" +
    "NO. SPK : " +
    escapeHtml(data.spk_number || "") +
    "<br>" +
    "JENIS PEKERJAAN : " +
    escapeHtml(data.jenis_pekerjaan || "") +
    "<br>" +
    "IDPEL : " +
    escapeHtml(data.idpel || "") +
    "<br>" +
    "NAMA PELANGGAN : " +
    escapeHtml(data.customer_name || "") +
    "<br>" +
    "ALAMAT PELANGGAN : " +
    escapeHtml(data.customer_address || "") +
    '</td><td style="width:40%; vertical-align:top;">' +
    "<strong>Kondisi Material</strong>" +
    kondisiList +
    "<br>" +
    "Keterangan : " +
    escapeHtml(data.keterangan || "") +
    "</td></tr></table>" +
    printTtdRow([
      { label: "Setuju", value: data.setuju_name },
      { label: "Kepala Gudang", value: data.kepala_gudang_name },
      { label: "Pemeriksa / Pengawas", value: data.pemeriksa_pengawas_name },
      { label: "Yang Menyerahkan", value: data.yang_menyerahkan_name },
    ]) +
    "</div>";
  openPrintDoc(html);
}
function saveK3pdf() {
  printK3();
}

// =========================================================
// MONITORING / PENCARIAN K7 (Bon Pemakaian Material Bekas)
// =========================================================
function loadK7() {
  var tug = document.getElementById("k7TugInput").value.trim();
  var result = document.getElementById("k7Result");
  if (!result) return;
  if (!tug) {
    result.innerHTML =
      '<p class="text-small">Masukkan nomor TUG K7 terlebih dahulu.</p>';
    return;
  }
  result.innerHTML = '<p class="text-small">Mencari data...</p>';

  fetch("index.php?ajax=k7&tug=" + encodeURIComponent(tug))
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      if (data.error) {
        result.innerHTML =
          '<p class="text-small">' + escapeHtml(data.error) + "</p>";
        return;
      }
      window.LAST_K7 = data;
      var statusClass =
        data.status === "aktif"
          ? "status-aktif"
          : data.status === "selesai"
            ? "status-selesai"
            : "status-belum";

      var isReadOnly = !window.IS_ADMIN || (data.status === "selesai");

      var adminReceivedForm = "";
      if (window.IS_ADMIN) {
        var disabledAttr = isReadOnly ? " disabled" : "";
        var items = (data.items || [])
          .map(function (it, i) {
            var receivedCell = isReadOnly
              ? String(it.quantity_received ?? "0")
              : '<input type="number" step="any" min="0" name="item_received[]" value="' + (it.quantity_received ?? 0) + '" style="width:80px;"><input type="hidden" name="item_id[]" value="' + it.id + '">';
            return (
              "<tr><td>" +
              (i + 1) +
              "</td><td>" +
              escapeHtml(it.material_name || "-") +
              "</td><td>" +
              escapeHtml(it.norm || "-") +
              "</td><td>" +
              escapeHtml(it.unit || "-") +
              "</td><td>" +
              (it.quantity_requested ?? "-") +
              "</td><td>" +
              receivedCell +
              "</td></tr>"
            );
          })
          .join("");

        var saveBtn = isReadOnly ? "" : '<button type="submit" name="update_k7_received" class="btn-success" style="margin-top:0.6rem;">Simpan Jumlah Diterima</button>';
        adminReceivedForm = isReadOnly
          ? '<div class="table-wrap"><table><thead><tr><th>#</th><th>Material</th><th>Norm</th><th>Satuan</th><th>Diminta</th><th>Diterima</th></tr></thead><tbody>' + items + '</tbody></table></div>'
          : '<form method="POST" action="k7.php" style="margin-top:0.8rem;">' +
            '<input type="hidden" name="k7_id" value="' + data.id + '">' +
            '<input type="hidden" name="tug_number" value="' + escapeHtml(data.tug_number) + '">' +
            '<div class="table-wrap"><table><thead><tr><th>#</th><th>Material</th><th>Norm</th><th>Satuan</th><th>Diminta</th><th>Diterima</th></tr></thead><tbody>' + items + '</tbody></table></div>' +
            saveBtn +
            '</form>';
      } else {
        var items = (data.items || [])
          .map(function (it, i) {
            return (
              "<tr><td>" +
              (i + 1) +
              "</td><td>" +
              escapeHtml(it.material_name || "-") +
              "</td><td>" +
              escapeHtml(it.norm || "-") +
              "</td><td>" +
              escapeHtml(it.unit || "-") +
              "</td><td>" +
              (it.quantity_requested ?? "-") +
              "</td><td>" +
              String(it.quantity_received ?? "-") +
              "</td></tr>"
            );
          })
          .join("");
        adminReceivedForm = '<div class="table-wrap"><table><thead><tr><th>#</th><th>Material</th><th>Norm</th><th>Satuan</th><th>Diminta</th><th>Diterima</th></tr></thead><tbody>' + items + '</tbody></table></div>';
      }

      var adminDetailsFormK7 = "";
      if (window.IS_ADMIN) {
        var disabledAttr = isReadOnly ? " disabled" : "";
        var saveBtn = isReadOnly ? "" : '<button type="submit" name="update_k7_details" class="btn-success" style="margin-top:0.4rem;">Simpan Detail Bon</button>';
        adminDetailsFormK7 = '<form method="POST" action="k7.php" style="margin-top:0.8rem; background:#f7f9fc; padding:0.8rem; border-radius:14px;">' +
          '<input type="hidden" name="k7_id" value="' +
          data.id +
          '">' +
          '<input type="hidden" name="tug_number" value="' +
          escapeHtml(data.tug_number) +
          '">' +
          '<p style="margin:0 0 0.5rem; font-weight:600; color:#0b2b4a;">Detail Bon</p>' +
          '<div class="flex-row">' +
          '<div class="form-group"><label>Merk Material</label><input type="text" name="merk_material" value="' +
          escapeHtml(data.merk_material || "") +
          '"' + disabledAttr + '></div>' +
          '<div class="form-group"><label>Nomor Seri</label><input type="text" name="nomor_seri" value="' +
          escapeHtml(data.nomor_seri || "") +
          '"' + disabledAttr + '></div>' +
          '<div class="form-group"><label>Keterangan</label><input type="text" name="keterangan" value="' +
          escapeHtml(data.keterangan || "") +
          '"' + disabledAttr + '></div>' +
          "</div>" +
          saveBtn +
          "</form>";
      }

      var adminStatusForm = (window.IS_ADMIN && !isReadOnly)
        ? '<div style="margin-top:0.8rem; display:flex; gap:0.6rem; align-items:flex-end; flex-wrap:wrap;">' +
          '<a href="k7.php?delete_k7=' +
          data.id +
          '" onclick="return confirm(\'Yakin hapus?\')" class="btn-danger" style="padding:0.6rem 1.1rem; border-radius:30px; text-decoration:none; font-size:0.85rem;">Hapus</a>' +
          "</div>"
        : "";

      result.innerHTML =
        '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.6rem;">' +
        "<p style='margin:0;'><strong>No. TUG:</strong> " +
        escapeHtml(data.tug_number || "-") +
        "</p>" +
        '<span class="status-badge ' +
        statusClass +
        '">' +
        escapeHtml(data.status_label || "-") +
        "</span></div>" +
        "<p><strong>Vendor:</strong> " +
        escapeHtml(data.vendor_name || "-") +
        "</p>" +
        "<p><strong>Pelanggan:</strong> " +
        escapeHtml(data.customer_name || "-") +
        "</p>" +
        "<p><strong>Alamat:</strong> " +
        escapeHtml(data.customer_address || "-") +
        "</p>" +
        "<p><strong>No. SPK:</strong> " +
        escapeHtml(data.spk_number || "-") +
        "</p>" +
        "<p><strong>Jenis Pekerjaan:</strong> " +
        escapeHtml(data.jenis_pekerjaan || "-") +
        " &nbsp; <strong>ULP:</strong> " +
        escapeHtml(data.ulp || "-") +
        " &nbsp; <strong>Daya:</strong> " +
        escapeHtml(data.daya || "-") +
        "</p>" +
        (data.merk_material || data.nomor_seri || data.keterangan
          ? "<p><strong>Merk Material:</strong> " +
            escapeHtml(data.merk_material || "-") +
            " &nbsp; <strong>Nomor Seri:</strong> " +
            escapeHtml(data.nomor_seri || "-") +
            (data.keterangan
              ? " &nbsp; <strong>Keterangan:</strong> " +
                escapeHtml(data.keterangan)
              : "") +
            "</p>"
          : "") +
        adminDetailsFormK7 +
        adminReceivedForm +
        adminStatusForm +
        buildTtdBox(
          [
            { field: "setuju_name", label: "Setuju" },
            { field: "kepala_gudang_name", label: "Kepala Gudang" },
            { field: "pemeriksa_pengawas_name", label: "Pemeriksa / Pengawas" },
            { field: "penerima_name", label: "Penerima" },
          ],
          data,
          "k7.php",
          "k7_id",
          "update_k7_signers",
        );
    })
    .catch(function () {
      result.innerHTML = '<p class="text-small">Gagal memuat data K7.</p>';
    });
}

function printK7() {
  var data = window.LAST_K7;
  if (!data) {
    alert("Cari nomor TUG K7 dulu sebelum mencetak.");
    return;
  }
  var itemRows = printItemRows(data.items, [
    "quantity_requested",
    "quantity_received",
  ]);
  var html =
    '<div style="font-family:Arial, sans-serif; font-size:12px; color:#000; padding:20px;">' +
    '<table style="width:100%; border-collapse:collapse;"><tr>' +
    '<td style="width:20%; font-size:26px; font-weight:800; vertical-align:middle;">TUG</td>' +
    '<td style="width:55%; text-align:center; vertical-align:middle;">' +
    '<span style="font-size:18px; font-weight:800;">BON PEMAKAIAN</span>' +
    "</td>" +
    '<td style="width:25%; text-align:center; vertical-align:middle; color:#c00; font-weight:800; font-size:16px;">' +
    escapeHtml(data.tug_number || "") +
    "</td></tr></table>" +
    '<table style="width:100%; margin-top:10px;"><tr><td style="width:50%; vertical-align:top;">' +
    "Tanggal diminta : " +
    escapeHtml(data.tanggal_diminta || "") +
    "<br>" +
    "Kepada / Gudang : Gudang PLN Aries Munandar" +
    '</td><td style="width:50%; vertical-align:top;">' +
    "Harap dikirim ke : " +
    escapeHtml(data.ulp || "") +
    "<br>" +
    "Vendor : " +
    escapeHtml(data.vendor_name || "") +
    "</td></tr></table>" +
    '<table style="width:100%; border-collapse:collapse; margin-top:10px;">' +
    '<thead><tr style="background:#eee;">' +
    '<th style="border:1px solid #000; padding:4px;">No</th>' +
    '<th style="border:1px solid #000; padding:4px;">Nama Barang</th>' +
    '<th style="border:1px solid #000; padding:4px;">No. Normalisasi</th>' +
    '<th style="border:1px solid #000; padding:4px;">Satuan</th>' +
    '<th style="border:1px solid #000; padding:4px;" colspan="2">Banyaknya Diminta</th>' +
    '<th style="border:1px solid #000; padding:4px;" colspan="2">Banyaknya Diterima</th>' +
    "</tr></thead><tbody>" +
    itemRows +
    "</tbody></table>" +
    '<table style="width:100%; margin-top:14px;"><tr><td style="width:60%; vertical-align:top;">' +
    "VENDOR : " +
    escapeHtml(data.vendor_name || "") +
    "<br>" +
    "NO. SPK : " +
    escapeHtml(data.spk_number || "") +
    "<br>" +
    "JENIS PEKERJAAN : " +
    escapeHtml(data.jenis_pekerjaan || "") +
    "<br>" +
    "IDPEL : " +
    escapeHtml(data.idpel || "") +
    "<br>" +
    "NAMA PELANGGAN : " +
    escapeHtml(data.customer_name || "") +
    "<br>" +
    "ALAMAT PELANGGAN : " +
    escapeHtml(data.customer_address || "") +
    '</td><td style="width:40%; vertical-align:top;">' +
    "DAYA : " +
    escapeHtml(data.daya || "") +
    "<br>" +
    "ULP : " +
    escapeHtml(data.ulp || "") +
    "</td></tr></table>" +
    printTtdRow([
      { label: "Setuju", value: data.setuju_name },
      { label: "Kepala Gudang", value: data.kepala_gudang_name },
      { label: "Pemeriksa / Pengawas", value: data.pemeriksa_pengawas_name },
      { label: "Penerima " + (data.ulp || ""), value: data.penerima_name },
    ]) +
    "</div>";
  openPrintDoc(html);
}
function saveK7pdf() {
  printK7();
}

// ---------- UTIL ----------
function escapeHtml(str) {
  if (str === null || str === undefined) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function buildTtdBox(
  columns,
  data,
  actionUrl,
  idField,
  submitName,
  prelineFields,
) {
  var isReadOnly = !window.IS_ADMIN || (data.status === "selesai");
  var colsClass = columns.length === 4 ? "cols-4" : "cols-3";
  var cells = columns
    .map(function (c) {
      var val = data[c.field] || "";
      var input = !isReadOnly
        ? '<input type="text" class="ttd-name-input" name="' +
          c.field +
          '" value="' +
          escapeHtml(val) +
          '" placeholder="nama...">'
        : '<div class="ttd-name-static">' + escapeHtml(val || "-") + "</div>";
      return (
        '<div class="ttd-col"><span class="ttd-role">' +
        escapeHtml(c.label) +
        "</span>" +
        input +
        "</div>"
      );
    })
    .join("");

  var prelineHtml = "";
  if (prelineFields && prelineFields.length) {
    var prelineCells = prelineFields
      .map(function (p) {
        var val = data[p.field] || "";
        var input = "";
        if (!isReadOnly && (p.field === "diterima_tgl" || p.field === "malang_tanggal")) {
          if (!val) {
            val = new Date().toLocaleDateString('en-CA'); // 'YYYY-MM-DD'
          }
          input = '<input type="date" class="ttd-preline-input" name="' +
            p.field +
            '" value="' +
            escapeHtml(val) +
            '">';
        } else {
          input = !isReadOnly
            ? '<input type="text" class="ttd-preline-input" name="' +
              p.field +
              '" value="' +
              escapeHtml(val) +
              '" placeholder="....................">'
            : "<span>" + escapeHtml(val ? formatDate(val) : "....................") + "</span>";
        }
        return "<span>" + escapeHtml(p.prefix) + " " + input + "</span>";
      })
      .join("");
    prelineHtml = '<div class="ttd-preline">' + prelineCells + "</div>";
  }

  if (isReadOnly) {
    return (
      prelineHtml + '<div class="ttd-box ' + colsClass + '">' + cells + "</div>"
    );
  }

  return (
    '<form method="POST" action="' +
    actionUrl +
    '">' +
    '<input type="hidden" name="' +
    idField +
    '" value="' +
    data.id +
    '">' +
    '<input type="hidden" name="tug_number" value="' +
    escapeHtml(data.tug_number) +
    '">' +
    prelineHtml +
    '<div class="ttd-box ' +
    colsClass +
    '">' +
    cells +
    "</div>" +
    '<div class="ttd-save-btn"><button type="submit" name="' +
    submitName +
    '" class="btn-info">Simpan Nama Penandatangan</button></div>' +
    "</form>"
  );
}

// =========================================================
// DAFUNG / MDU — MONITORING PERMINTAAN & SISA PEMENUHAN MATERIAL PER MATERIAL
// =========================================================
var mduSearchTimer = null;
var mduLastQuery = "";
var mduLastData = []; // dipakai untuk ekspor Excel (data hasil pencarian terakhir)

function searchMdu() {
  var input = document.getElementById("mduMaterialInput");
  var result = document.getElementById("mduResult");
  if (!input || !result) return;

  var q = input.value.trim();

  // debounce supaya tidak fetch di setiap ketikan huruf
  if (mduSearchTimer) clearTimeout(mduSearchTimer);
  mduSearchTimer = setTimeout(function () {
    runMduSearch(q, result);
  }, 350);
}

function runMduSearch(q, result) {
  mduLastQuery = q;

  if (!q) {
    mduLastData = [];
    result.innerHTML =
      '<p class="text-small">Ketik nama material di atas untuk melihat daftar vendor yang mengajukan &amp; sisa yang belum terpenuhi.</p>';
    return;
  }

  result.innerHTML = '<p class="text-small">Mencari data...</p>';

  fetch("index.php?ajax=mdu&q=" + encodeURIComponent(q))
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      if (data.error) {
        mduLastData = [];
        result.innerHTML =
          '<p class="text-small">' + escapeHtml(data.error) + "</p>";
        return;
      }
      if (!data.length) {
        mduLastData = [];
        result.innerHTML =
          '<p class="text-small">Tidak ada pengajuan ditemukan untuk material "' +
          escapeHtml(q) +
          '".</p>';
        return;
      }

      mduLastData = data;

      var rows = data
        .map(function (r, i) {
          var sisaBadge =
            r.sisa > 0
              ? '<span class="status-badge status-belum">Kurang ' +
                r.sisa +
                "</span>"
              : '<span class="status-badge status-selesai">Terpenuhi</span>';
          return (
            "<tr><td>" +
            (i + 1) +
            "</td><td>" +
            escapeHtml(r.material_name || "-") +
            "</td><td>" +
            escapeHtml(r.vendor_name || "-") +
            "</td><td>" +
            escapeHtml(r.tug_number || "-") +
            "</td><td>" +
            escapeHtml(r.tanggal_diminta || "-") +
            "</td><td>" +
            r.quantity_requested +
            " " +
            escapeHtml(r.unit || "") +
            "</td><td>" +
            r.quantity_received +
            " " +
            escapeHtml(r.unit || "") +
            "</td><td>" +
            sisaBadge +
            "</td><td>" +
            '<a href="index.php?page=dpb&tug=' +
            encodeURIComponent(r.tug_number || "") +
            '" class="btn-info" style="padding:0.3rem 0.9rem; border-radius:20px; text-decoration:none; font-size:0.75rem; white-space:nowrap;">Lihat / Penuhi</a>' +
            "</td></tr>"
          );
        })
        .join("");

      result.innerHTML =
        '<div class="table-wrap"><table><thead><tr>' +
        "<th>#</th><th>Material</th><th>Vendor</th><th>No. TUG</th><th>Tgl Diminta</th>" +
        "<th>Diminta</th><th>Terpenuhi</th><th>Sisa</th><th>Aksi</th>" +
        "</tr></thead><tbody>" +
        rows +
        "</tbody></table></div>" +
        '<div style="margin-top:1rem; text-align:right;">' +
        '<button type="button" class="btn-warning" onclick="exportMduToExcel()"><i class="fas fa-file-excel"></i> Ekspor ke Excel</button>' +
        "</div>";
    })
    .catch(function () {
      mduLastData = [];
      result.innerHTML =
        '<p class="text-small">Terjadi kesalahan saat mengambil data. Coba lagi.</p>';
    });
}

function exportMduToExcel() {
  if (!mduLastData || !mduLastData.length) {
    alert(
      "Belum ada data untuk diekspor. Silakan cari material terlebih dahulu.",
    );
    return;
  }

  var header = [
    "No",
    "Material",
    "Vendor",
    "No. TUG",
    "Tanggal Diminta",
    "Status",
    "Diminta",
    "Terpenuhi",
    "Satuan",
    "Sisa",
  ];

  var rows = mduLastData.map(function (r, i) {
    return [
      i + 1,
      r.material_name || "-",
      r.vendor_name || "-",
      r.tug_number || "-",
      r.tanggal_diminta || "-",
      r.status_label || "-",
      r.quantity_requested,
      r.quantity_received,
      r.unit || "-",
      r.sisa,
    ];
  });

  function csvCell(val) {
    var s = String(val === null || val === undefined ? "" : val);
    s = s.replace(/"/g, '""');
    return '"' + s + '"';
  }

  var csv = header.map(csvCell).join(";") + "\r\n";
  rows.forEach(function (row) {
    csv += row.map(csvCell).join(";") + "\r\n";
  });

  // BOM supaya karakter khusus tetap terbaca benar saat dibuka di Excel
  var blob = new Blob(["\uFEFF" + csv], {
    type: "application/vnd.ms-excel;charset=utf-8;",
  });

  var link = document.createElement("a");
  var fileName =
    "dafung_" +
    (mduLastQuery ? mduLastQuery.replace(/[^a-z0-9]+/gi, "_") : "material") +
    ".csv";
  link.href = URL.createObjectURL(blob);
  link.download = fileName;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// ---------- INISIALISASI HALAMAN ----------
document.addEventListener("DOMContentLoaded", function () {
  ensureMaterialDatalists();

  // pastikan required sesuai section yang aktif sejak awal load
  if (document.getElementById("registerFields")) {
    setRequiredIn("loginFields", true);
    setRequiredIn("registerFields", false);
  }

  // auto-buka modal setelah redirect (misal login gagal)
  if (typeof window.AUTO_OPEN_MODAL !== "undefined" && window.AUTO_OPEN_MODAL) {
    if (window.AUTO_OPEN_MODAL === "register") {
      showRegister();
    } else if (window.AUTO_OPEN_MODAL === "login") {
      showLogin();
    }
  }

  // halaman DPB (admin): muat panel pengajuan menunggu persetujuan
  if (document.getElementById("dpbPendingContainer")) {
    loadPendingDpbList();
  }

  // halaman Stok Material (admin & gudang): muat otomatis saat halaman dibuka
  if (document.getElementById("materialListContainer")) {
    showMaterialList();
    loadIncomingList();
    loadOutgoingList();
  }

  // halaman DPB / K3 / K7: siapkan datalist + minimal 1 baris material kosong
  if (document.getElementById("dpbItemsWrap")) {
    ensureMaterialDatalists();
    addDpbItemRow();
  }
  if (document.getElementById("k3ItemsWrap")) {
    ensureMaterialDatalists();
    addK3ItemRow();
  }
  if (document.getElementById("k7ItemsWrap")) {
    ensureMaterialDatalists();
    addK7ItemRow();
  }

  // jika datang dari redirect setelah simpan pengajuan (?page=dpb/k3/k7&tug=...), langsung tampilkan hasilnya
  if (typeof window.AUTO_LOAD_TUG !== "undefined" && window.AUTO_LOAD_TUG) {
    var urlParams = new URLSearchParams(window.location.search);
    var pageParam = urlParams.get('page') || 'home';
    
    if (pageParam === 'dpb' && document.getElementById("tugNumberInput")) {
      document.getElementById("tugNumberInput").value = window.AUTO_LOAD_TUG;
      loadDPB();
    } else if (pageParam === 'surat_jalan' && typeof openGudangDpbDetail === 'function') {
      openGudangDpbDetail(window.AUTO_LOAD_TUG);
    } else if (pageParam === 'k3' && document.getElementById("k3TugInput")) {
      document.getElementById("k3TugInput").value = window.AUTO_LOAD_TUG;
      loadK3();
    } else if (pageParam === 'k7' && document.getElementById("k7TugInput")) {
      document.getElementById("k7TugInput").value = window.AUTO_LOAD_TUG;
      loadK7();
    }
  }
});

// AUTOCOMPLETE MATERIAL DINAMIS (Event Delegation)
document.addEventListener("input", function (e) {
  var target = e.target;
  if (!target) return;

  var isNameInput = target.name === "item_material_name[]" || target.id === "materialNameInput" || target.id === "editMaterialName";
  var isNormInput = target.name === "item_material_norm[]" || target.id === "materialNormInput" || target.id === "editMaterialNorm";

  if (!isNameInput && !isNormInput) return;

  var type = isNameInput ? "name" : "norm";
  var typed = target.value.trim().toLowerCase();

  // Find or style parent container to relative
  var formGroup = target.closest(".form-group");
  if (!formGroup) return;

  // Clear any existing suggestions dropdown in this form group
  var existingDropdown = formGroup.querySelector(".autocomplete-suggestions-container");
  if (existingDropdown) {
    existingDropdown.remove();
  }

  if (typed.length < 2) return;

  var data = getCombinedMaterialData();
  var matches = data.filter(function (m) {
    var val = type === "name" ? m.name : m.norm;
    return val && val.toLowerCase().includes(typed);
  });

  // Create dropdown container
  var dropdown = document.createElement("div");
  dropdown.className = "autocomplete-suggestions-container";

  if (matches.length === 0) {
    var emptyDiv = document.createElement("div");
    emptyDiv.className = "autocomplete-suggestion-empty";
    emptyDiv.innerText = "Material tidak ditemukan";
    dropdown.appendChild(emptyDiv);
  } else {
    var limit = 15;
    for (var i = 0; i < Math.min(matches.length, limit); i++) {
      var item = matches[i];
      var option = document.createElement("div");
      option.className = "autocomplete-suggestion-item";
      option.innerText = type === "name" ? item.name : item.norm;
      
      // Select click handler
      option.addEventListener("mousedown", (function(selectedItem) {
        return function(evt) {
          evt.preventDefault(); // prevent blur before selection processes
          selectAutocompleteItem(target, selectedItem, type);
          dropdown.remove();
        };
      })(item));
      dropdown.appendChild(option);
    }
  }

  formGroup.style.position = "relative";
  formGroup.appendChild(dropdown);
});

function selectAutocompleteItem(inputEl, item, type) {
  var row = inputEl.closest(".dpb-item-row") || inputEl.closest(".item-row");
  
  if (row) {
    // Dynamic row in DPB, K3, or K7
    var nameInput = row.querySelector('input[name="item_material_name[]"]');
    var normInput = row.querySelector('input[name="item_material_norm[]"]');
    var unitInput = row.querySelector('input[name="item_unit_display[]"]');
    
    if (nameInput) nameInput.value = item.name;
    if (normInput) normInput.value = item.norm;
    if (unitInput && item.unit) unitInput.value = item.unit;
    
    // Trigger change event
    if (nameInput) nameInput.dispatchEvent(new Event("change"));
  } else {
    // Static forms
    if (inputEl.id === "materialNameInput" || inputEl.id === "materialNormInput") {
      var nameInput = document.getElementById("materialNameInput");
      var normInput = document.getElementById("materialNormInput");
      var unitSelect = document.querySelector('select[name="material_unit"]');
      
      if (nameInput) nameInput.value = item.name;
      if (normInput) normInput.value = item.norm;
      if (unitSelect && item.unit) unitSelect.value = item.unit;
    } else if (inputEl.id === "editMaterialName" || inputEl.id === "editMaterialNorm") {
      var nameInput = document.getElementById("editMaterialName");
      var normInput = document.getElementById("editMaterialNorm");
      var unitSelect = document.getElementById("editMaterialUnit");
      
      if (nameInput) nameInput.value = item.name;
      if (normInput) normInput.value = item.norm;
      if (unitSelect && item.unit) unitSelect.value = item.unit;
    }
  }
}

// Close suggestion box when click outside
document.addEventListener("click", function (e) {
  var activeDropdowns = document.querySelectorAll(".autocomplete-suggestions-container");
  activeDropdowns.forEach(function (dropdown) {
    var formGroup = dropdown.closest(".form-group");
    if (formGroup && !formGroup.contains(e.target)) {
      dropdown.remove();
    }
  });
});

// Enforce selection on blur for dynamic inputs
document.addEventListener("focusout", function (e) {
  var target = e.target;
  if (!target) return;

  var isDynamicName = target.name === "item_material_name[]";
  var isDynamicNorm = target.name === "item_material_norm[]";

  if (!isDynamicName && !isDynamicNorm) return;

  // Small delay so mousedown on suggestion item triggers first
  setTimeout(function () {
    var typed = target.value.trim().toLowerCase();
    
    var formGroup = target.closest(".form-group");
    if (formGroup) {
      var dropdown = formGroup.querySelector(".autocomplete-suggestions-container");
      if (dropdown) {
        // Dropdown is still open or being clicked, do not clear
        return;
      }
    }

    if (!typed) {
      clearRowInputs(target);
      return;
    }

    var data = getCombinedMaterialData();
    var match = data.find(function (m) {
      var val = isDynamicName ? m.name : m.norm;
      return val && val.trim().replace(/\s+/g, " ").toLowerCase() === typed;
    });

    if (!match) {
      clearRowInputs(target);
    }
  }, 250);
});

function clearRowInputs(inputEl) {
  var row = inputEl.closest(".dpb-item-row") || inputEl.closest(".item-row");
  if (row) {
    var nameInput = row.querySelector('input[name="item_material_name[]"]');
    var normInput = row.querySelector('input[name="item_material_norm[]"]');
    var unitInput = row.querySelector('input[name="item_unit_display[]"]');
    if (nameInput) nameInput.value = "";
    if (normInput) normInput.value = "";
    if (unitInput) unitInput.value = "";
  }
}

function toggleInputMaterialForm() {
  var wrap = document.getElementById("inputMaterialFormWrap");
  var btn = document.getElementById("btnToggleInputMaterial");
  if (!wrap || !btn) return;
  if (wrap.style.display === "none") {
    wrap.style.display = "block";
    btn.innerHTML = '<i class="fas fa-times"></i> Tutup Form Input';
  } else {
    wrap.style.display = "none";
    btn.innerHTML = '<i class="fas fa-plus"></i> Input Material';
  }
}

// ---------- MATERIAL MASUK (Incoming) ----------
var currentIncomingList = [];
var incomingActiveList = [];
var incomingCurrentPage = 1;
var INCOMING_PAGE_SIZE = 10;

function loadIncomingList() {
  var container = document.getElementById("incomingListContainer");
  if (!container) return;
  container.innerHTML = '<p class="text-small">Memuat data...</p>';
  
  fetch("index.php?ajax=incoming_materials")
    .then(function (res) { return res.json(); })
    .then(function (data) {
      currentIncomingList = Array.isArray(data) ? data : [];
      renderIncomingTable(currentIncomingList);
    })
    .catch(function () {
      container.innerHTML = '<p class="text-small">Gagal memuat data material masuk.</p>';
    });
}

function renderIncomingTable(list) {
  incomingActiveList = list;
  incomingCurrentPage = 1;
  renderIncomingPage();
}

function renderIncomingPage() {
  var container = document.getElementById("incomingListContainer");
  if (!container) return;
  var list = incomingActiveList;
  
  if (list.length === 0) {
    container.innerHTML = '<p class="text-small">Tidak ada catatan material masuk.</p>';
    return;
  }
  
  var totalPages = Math.max(1, Math.ceil(list.length / INCOMING_PAGE_SIZE));
  if (incomingCurrentPage > totalPages) incomingCurrentPage = totalPages;
  if (incomingCurrentPage < 1) incomingCurrentPage = 1;
  
  var startIdx = (incomingCurrentPage - 1) * INCOMING_PAGE_SIZE;
  var pageItems = list.slice(startIdx, startIdx + INCOMING_PAGE_SIZE);
  
  var rows = pageItems.map(function (m, i) {
    var no = startIdx + i + 1;
    return "<tr>" +
      "<td>" + no + "</td>" +
      "<td><strong>" + escapeHtml(m.material_name) + "</strong></td>" +
      "<td>" + escapeHtml(m.material_norm) + "</td>" +
      "<td>" + escapeHtml(m.material_unit) + "</td>" +
      "<td><span class=\"badge\" style=\"background-color:#e3f7ec; color:var(--success); font-weight:700; padding:4px 8px; border-radius:8px;\">" + numberFormat(m.quantity) + "</span></td>" +
      "<td>" + escapeHtml(m.pabrikan || "-") + "</td>" +
      "<td>" + escapeHtml(m.nomor_kontrak || "-") + "</td>" +
      "<td>" + (m.tanggal_datang ? formatDate(m.tanggal_datang) : "-") + "</td>" +
      "</tr>";
  }).join("");
  
  var tableHtml = '<div class="table-wrap"><table><thead><tr>' +
    '<th style="width: 5%;">No</th>' +
    '<th>Nama Material</th>' +
    '<th>No. Normalisasi</th>' +
    '<th>Satuan</th>' +
    '<th>Jumlah</th>' +
    '<th>Nama Pabrikan</th>' +
    '<th>Nomor Kontrak</th>' +
    '<th>Tanggal Datang</th>' +
    '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    
  var paginationHtml = buildIncomingPaginationHtml(incomingCurrentPage, totalPages);
  container.innerHTML = tableHtml + paginationHtml;
}

function filterIncomingTable() {
  var input = document.getElementById("incomingSearchInput");
  var startInput = document.getElementById("incomingStartInput");
  var endInput = document.getElementById("incomingEndInput");
  
  var query = (input ? input.value : "").trim().toLowerCase();
  var startVal = (startInput ? startInput.value : "").trim();
  var endVal = (endInput ? endInput.value : "").trim();
  
  var filtered = currentIncomingList.filter(function (m) {
    // 1. Search Query
    var matchesSearch = true;
    if (query) {
      matchesSearch = (m.material_name && m.material_name.toLowerCase().includes(query)) ||
                      (m.material_norm && m.material_norm.toLowerCase().includes(query)) ||
                      (m.pabrikan && m.pabrikan.toLowerCase().includes(query)) ||
                      (m.nomor_kontrak && m.nomor_kontrak.toLowerCase().includes(query));
    }
    
    // 2. Date Range
    var matchesDate = true;
    if (m.tanggal_datang) {
      var datePart = m.tanggal_datang.substring(0, 10); // 'YYYY-MM-DD'
      if (startVal && datePart < startVal) matchesDate = false;
      if (endVal && datePart > endVal) matchesDate = false;
    } else {
      if (startVal || endVal) matchesDate = false;
    }
    
    return matchesSearch && matchesDate;
  });
  
  renderIncomingTable(filtered);
}

function resetIncomingFilters() {
  var input = document.getElementById("incomingSearchInput");
  var startInput = document.getElementById("incomingStartInput");
  var endInput = document.getElementById("incomingEndInput");
  if (input) input.value = "";
  if (startInput) startInput.value = "";
  if (endInput) endInput.value = "";
  filterIncomingTable();
}

function exportIncoming() {
  var input = document.getElementById("incomingSearchInput");
  var startInput = document.getElementById("incomingStartInput");
  var endInput = document.getElementById("incomingEndInput");
  
  var query = (input ? input.value : "").trim();
  var startVal = (startInput ? startInput.value : "").trim();
  var endVal = (endInput ? endInput.value : "").trim();
  
  window.location.href = "export_incoming.php?q=" + encodeURIComponent(query) + 
                         "&start=" + encodeURIComponent(startVal) + 
                         "&end=" + encodeURIComponent(endVal);
}

function goToIncomingPage(p) {
  incomingCurrentPage = p;
  renderIncomingPage();
}

function buildIncomingPaginationHtml(current, total) {
  if (total <= 1) return "";
  var html = '<div class="pagination-row" style="margin-top:1rem; text-align:center;">';
  for (var i = 1; i <= total; i++) {
    var activeClass = i === current ? "active" : "";
    html += '<button type="button" class="page-btn ' + activeClass + '" onclick="goToIncomingPage(' + i + ')">' + i + '</button>';
  }
  html += '</div>';
  return html;
}

// ---------- MATERIAL KELUAR (Outgoing) ----------
var currentOutgoingList = [];
var outgoingActiveList = [];
var outgoingCurrentPage = 1;
var OUTGOING_PAGE_SIZE = 10;

function loadOutgoingList() {
  var container = document.getElementById("outgoingListContainer");
  if (!container) return;
  container.innerHTML = '<p class="text-small">Memuat data...</p>';
  
  fetch("index.php?ajax=outgoing_materials")
    .then(function (res) { return res.json(); })
    .then(function (data) {
      currentOutgoingList = Array.isArray(data) ? data : [];
      renderOutgoingTable(currentOutgoingList);
    })
    .catch(function () {
      container.innerHTML = '<p class="text-small">Gagal memuat data material keluar.</p>';
    });
}

function renderOutgoingTable(list) {
  outgoingActiveList = list;
  outgoingCurrentPage = 1;
  renderOutgoingPage();
}

function renderOutgoingPage() {
  var container = document.getElementById("outgoingListContainer");
  if (!container) return;
  var list = outgoingActiveList;
  
  if (list.length === 0) {
    container.innerHTML = '<p class="text-small">Tidak ada catatan material keluar.</p>';
    return;
  }
  
  var totalPages = Math.max(1, Math.ceil(list.length / OUTGOING_PAGE_SIZE));
  if (outgoingCurrentPage > totalPages) outgoingCurrentPage = totalPages;
  if (outgoingCurrentPage < 1) outgoingCurrentPage = 1;
  
  var startIdx = (outgoingCurrentPage - 1) * OUTGOING_PAGE_SIZE;
  var pageItems = list.slice(startIdx, startIdx + OUTGOING_PAGE_SIZE);
  
  var rows = pageItems.map(function (m, i) {
    var no = startIdx + i + 1;
    return "<tr>" +
      "<td>" + no + "</td>" +
      "<td><strong>" + escapeHtml(m.material_name) + "</strong></td>" +
      "<td>" + escapeHtml(m.material_norm) + "</td>" +
      "<td>" + escapeHtml(m.material_unit) + "</td>" +
      "<td><span class=\"badge\" style=\"background-color:#ffeef0; color:var(--danger); font-weight:700; padding:4px 8px; border-radius:8px;\">" + numberFormat(m.quantity) + "</span></td>" +
      "<td>" + escapeHtml(m.vendor_name) + "</td>" +
      "<td>" + escapeHtml(m.no_dpb) + "</td>" +
      "<td>" + (m.tanggal_keluar ? formatDate(m.tanggal_keluar) : "-") + "</td>" +
      "</tr>";
  }).join("");
  
  var tableHtml = '<div class="table-wrap"><table><thead><tr>' +
    '<th style="width: 5%;">No</th>' +
    '<th>Nama Material</th>' +
    '<th>No. Normalisasi</th>' +
    '<th>Satuan</th>' +
    '<th>Jumlah</th>' +
    '<th>Nama Vendor</th>' +
    '<th>Nomor DPB / TUG</th>' +
    '<th>Tanggal Keluar</th>' +
    '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    
  var paginationHtml = buildOutgoingPaginationHtml(outgoingCurrentPage, totalPages);
  container.innerHTML = tableHtml + paginationHtml;
}

function filterOutgoingTable() {
  var input = document.getElementById("outgoingSearchInput");
  var startInput = document.getElementById("outgoingStartInput");
  var endInput = document.getElementById("outgoingEndInput");
  
  var query = (input ? input.value : "").trim().toLowerCase();
  var startVal = (startInput ? startInput.value : "").trim();
  var endVal = (endInput ? endInput.value : "").trim();
  
  var filtered = currentOutgoingList.filter(function (m) {
    // 1. Search Query
    var matchesSearch = true;
    if (query) {
      matchesSearch = (m.material_name && m.material_name.toLowerCase().includes(query)) ||
                      (m.material_norm && m.material_norm.toLowerCase().includes(query)) ||
                      (m.vendor_name && m.vendor_name.toLowerCase().includes(query)) ||
                      (m.no_dpb && m.no_dpb.toLowerCase().includes(query));
    }
    
    // 2. Date Range
    var matchesDate = true;
    if (m.tanggal_keluar) {
      var datePart = m.tanggal_keluar.substring(0, 10); // 'YYYY-MM-DD'
      if (startVal && datePart < startVal) matchesDate = false;
      if (endVal && datePart > endVal) matchesDate = false;
    } else {
      if (startVal || endVal) matchesDate = false;
    }
    
    return matchesSearch && matchesDate;
  });
  
  renderOutgoingTable(filtered);
}

function resetOutgoingFilters() {
  var input = document.getElementById("outgoingSearchInput");
  var startInput = document.getElementById("outgoingStartInput");
  var endInput = document.getElementById("outgoingEndInput");
  if (input) input.value = "";
  if (startInput) startInput.value = "";
  if (endInput) endInput.value = "";
  filterOutgoingTable();
}

function exportOutgoing() {
  var input = document.getElementById("outgoingSearchInput");
  var startInput = document.getElementById("outgoingStartInput");
  var endInput = document.getElementById("outgoingEndInput");
  
  var query = (input ? input.value : "").trim();
  var startVal = (startInput ? startInput.value : "").trim();
  var endVal = (endInput ? endInput.value : "").trim();
  
  window.location.href = "export_outgoing.php?q=" + encodeURIComponent(query) + 
                         "&start=" + encodeURIComponent(startVal) + 
                         "&end=" + encodeURIComponent(endVal);
}

function goToOutgoingPage(p) {
  outgoingCurrentPage = p;
  renderOutgoingPage();
}

function buildOutgoingPaginationHtml(current, total) {
  if (total <= 1) return "";
  var html = '<div class="pagination-row" style="margin-top:1rem; text-align:center;">';
  for (var i = 1; i <= total; i++) {
    var activeClass = i === current ? "active" : "";
    html += '<button type="button" class="page-btn ' + activeClass + '" onclick="goToOutgoingPage(' + i + ')">' + i + '</button>';
  }
  html += '</div>';
  return html;
}

// ---------- JS HELPER FUNCTIONS ----------
function numberFormat(val) {
  var n = Number(val) || 0;
  return n.toLocaleString("id-ID");
}

function formatDate(dateStr) {
  if (!dateStr) return "-";
  var d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  var months = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agt", "Sep", "Okt", "Nov", "Des"];
  return String(d.getDate()).padStart(2, '0') + "-" + months[d.getMonth()] + "-" + d.getFullYear();
}
