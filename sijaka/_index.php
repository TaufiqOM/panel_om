<?php
require __DIR__ . '/../inc/config.php';

// ===============================
// 1. Handle request AJAX validasi station
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['station'])) {
  header('Content-Type: application/json');

  $station = trim($_POST['station'] ?? '');

  if ($station === '') {
    echo json_encode(["status" => "error", "message" => "Bagian tidak boleh kosong"]);
    exit;
  }

  $stmt = $conn->prepare("SELECT station_code FROM stations WHERE station_code = ?");
  $stmt->bind_param("s", $station);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    echo json_encode(["status" => "ok"]);
  } else {
    echo json_encode(["status" => "error", "message" => "Bagian / Devisi tidak ada ditemukan"]);
  }

  $stmt->close();
  exit;
}

// ===============================
// 2. Ambil station dari URL
// ===============================
$station = $_GET['station'] ?? '';
$station_name = '';
$data = [];

if ($station !== '') {
  // Get station name
  $stmt = $conn->prepare("SELECT station_name FROM stations WHERE station_code = ?");
  $stmt->bind_param("s", $station);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($row = $result->fetch_assoc()) {
    $station_name = $row['station_name'];
  }
  $stmt->close();

  // Get active employees with image from employee table
  $stmt = $conn->prepare("
        SELECT pe.*, e.image_1920 FROM production_employee pe
        LEFT JOIN employee e ON pe.employee_nik = e.barcode
        WHERE pe.station_code = ? ORDER BY pe.scan_in DESC
    ");
  $stmt->bind_param("s", $station);

  if ($stmt->execute()) {
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
  }

  $stmt->close();

  // Get active production lots for the station - product_code now stores product_id
  $production_lots = [];
  $stmt = $conn->prepare("SELECT so_name, product_code, production_code, sale_order_ref FROM production_lots WHERE station_code = ? ORDER BY scan_in DESC");
  $stmt->bind_param("s", $station);
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    $production_lots = $result->fetch_all(MYSQLI_ASSOC);
  }
  $stmt->close();
}

// ===============================
// 3. Fungsi format durasi
// ===============================
function formatDuration($seconds)
{
  if (!$seconds || $seconds <= 0) {
    return "-";
  }
  $hours   = floor($seconds / 3600);
  $minutes = floor(($seconds % 3600) / 60);
  $secs    = $seconds % 60;
  return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
}

// ===============================
// 4. Fungsi format image
// ===============================
function detectMimeFromBase64($base64)
{
  $base64 = ltrim($base64);
  if (strpos($base64, 'iVBORw0KGgo') === 0) {
    return 'image/png';
  } elseif (strpos($base64, '/9j/') === 0) {
    return 'image/jpeg';
  } elseif (strpos($base64, 'PD94') === 0) {
    return 'image/svg+xml';
  } else {
    return 'application/octet-stream'; // fallback
  }
}

?>


<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>S I J A K A</title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome untuk ikon -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- QRCode for generating QR codes -->
  <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
  <style>
    body {
      background-color: #f8f9fa;
      padding: 20px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .col-xl-2 {
      margin-bottom: 20px;
    }

    .header {
      background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
      color: white;
      padding: 20px 0;
      border-radius: 10px;
      margin-bottom: 30px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      position: relative;
    }

    .card-worker {
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s, box-shadow 0.3s;
      border: none;
      height: 100%;
      background-color: #fff;
    }

    .card-worker:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
    }

    .worker-img {
      width: 100%;
      height: 220px;
      object-fit: cover;
      object-position: center;
      border-top-left-radius: 8px;
      border-top-right-radius: 8px;
      border-bottom: 1px solid #f0f0f0;
    }

    .status-badge {
      position: absolute;
      top: 10px;
      right: 10px;
      font-size: 0.75rem;
      padding: 5px 10px;
      border-radius: 30px;
      font-weight: 600;
    }

    .card-content {
      padding: 15px 15px 10px 15px;
    }

    .worker-name {
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 8px;
      font-size: 1rem;
    }

    .worker-id {
      color: #6c757d;
      font-size: 0.85rem;
      margin-bottom: 5px;
    }

    .worker-job {
      color: #495057;
      font-size: 0.9rem;
      font-weight: 500;
      padding: 4px 0;
      margin: 0px;
      border-top: 1px solid #f0f0f0;
    }

    .worker-cust {
      color: #495057;
      font-size: 0.9rem;
      font-weight: 500;
      padding: 4px 0;
      margin: 0px;
      border-top: 1px solid #f0f0f0;
    }

    .status-toilet {
      background-color: #dc3545;
      color: white;
    }

    .status-break {
      background-color: #fd7e14;
      color: white;
    }

    .status-available {
      background-color: #198754;
      color: white;
    }

    .status-training {
      background-color: #6f42c1;
      color: white;
    }

    .footer {
      text-align: center;
      padding: 20px;
      margin-top: 30px;
      color: #6c757d;
      font-size: 0.9rem;
    }

    @media (max-width: 1200px) {
      .col-xl-2 {
        flex: 0 0 25%;
        max-width: 25%;
      }
    }

    @media (max-width: 992px) {
      .col-xl-2 {
        flex: 0 0 33.333%;
        max-width: 33.333%;
      }
    }

    @media (max-width: 768px) {
      .col-xl-2 {
        flex: 0 0 50%;
        max-width: 50%;
      }
    }

    @media (max-width: 576px) {
      .col-xl-2 {
        flex: 0 0 100%;
        max-width: 100%;
      }
    }

    .digital-clock {
      position: absolute;
      top: 10px;
      right: 10px;
      text-align: right;
      background: rgba(59, 49, 49, 0.1);
      padding: 10px 20px;
      border-radius: 8px;
      backdrop-filter: blur(5px);
    }

    .time {
      font-size: 2.2rem;
      font-weight: 700;
      letter-spacing: 2px;
      margin: 0;
      line-height: 1;
    }

    .date {
      font-size: 1.1rem;
      margin: 5px 0 0 0;
      opacity: 0.9;
    }

    .activity-card {
      cursor: pointer;
      transition: box-shadow 0.3s;
    }

    .activity-card:hover {
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .barcode-canvas {
      max-width: 100%;
      height: auto;
    }

    .sidebar {
      min-height: 100vh;
      border-left: 1px solid #dee2e6;
    }

    .product-card .card {
      transition: transform 0.2s, box-shadow 0.2s;
      border-radius: 8px;
      background-color: #f8f1e9;
    }

    .product-card .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .product-card h6 {
      font-size: 0.9rem;
      font-weight: 600;
    }

    .product-card p {
      font-size: 0.8rem;
    }
  </style>

</head>

<body>
  <div class="d-flex">
    <div class="container">
      <div class="header text-center">
        <h2><i class="fas fa-users me-2"></i>DAFTAR PEKERJA - <?= htmlspecialchars($station_name ?: $station) ?></h2>
        <p class="mb-0">Sistem Informasi Kinerja Karyawan</p>
        <input type="text" id="scanInput" autofocus style="opacity:0;position:absolute;left:-9999px;">
        <input type="text" hidden id="stationCode" value="<?= htmlspecialchars($station) ?>">
        <div class="digital-clock">
          <div class="time" id="digital-time">00:00:00</div>
          <div class="date" id="digital-date">Hari, 00 Bulan 0000</div>
        </div>
      </div>
      <!-- Start Alert Login Devisi -->
      <script>
        document.addEventListener("DOMContentLoaded", function() {
          <?php if ($station === ''): ?>
            Swal.fire({
              title: "Panel Factory",
              html: `
                <img src="../good/assets/media/logos/logo-black.png" alt="Logo" style="max-width:200px; margin:0px auto; display:block;" />
                <h4 style="margin:5px 0;">PT Omega Mas</h4>
                <p style="margin:5px 0;">Scan Nama Divisi atau Bagian</p>
              `,
              input: "text",
              inputPlaceholder: "Masukkan Bagian / Divisi",
              allowOutsideClick: false,
              allowEscapeKey: false,
              showCancelButton: false,
              confirmButtonText: "Masuk",
              inputValidator: (value) => {
                if (!value) {
                  return "Bagian / Divisi harus diisi!";
                }
              }
            }).then((result) => {
              if (result.isConfirmed) {
                let station = result.value.trim();
                fetch("index.php", {
                    method: "POST",
                    headers: {
                      "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: "station=" + encodeURIComponent(station)
                  })
                  .then(r => r.json())
                  .then(res => {
                    if (res.status === "ok") {
                      window.location.href = "index.php?station=" + encodeURIComponent(station);
                    } else {
                      Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: res.message
                      }).then(() => {
                        location.reload(); // reload supaya bisa input ulang
                      });
                    }
                  });
              }
            });
          <?php endif; ?>
        });
      </script>
      <!-- End Alert Login Devisi -->

      <div class="row">
        <?php foreach ($data as $row): ?>

          <!-- Pekerja 1 -->
          <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card card-worker">
              <?php if (empty($row['image_1920'])): ?>
                <img src="https://st4.depositphotos.com/15648834/23779/v/450/depositphotos_237795804-stock-illustration-unknown-person-silhouette-profile-picture.jpg" alt="Foto Karyawan" class="worker-img">
              <?php else:
                $base64Img = base64_encode($row['image_1920']);
                $mimetype = detectMimeFromBase64($base64Img);
              ?>
                <img src="data:<?= $mimetype ?>;base64,<?= $base64Img ?>" alt="Foto Karyawan" class="worker-img">
              <?php endif; ?>
              <?php if ($row['status'] === 0): ?>
                <span class="status-badge status-available"><?= htmlspecialchars($row['description']) ?></span>
              <?php endif; ?>
              <div class="card-content">
                <h6 class="worker-name"><?= htmlspecialchars($row['employee_fullname']) ?></h6>
                <p class="worker-id"><i class="fas fa-id-card me-2"></i><?= htmlspecialchars($row['employee_nik']) ?></p>
                <p class="worker-job"><i class="fas fa-briefcase me-2"></i><?= htmlspecialchars($row['sale_order_ref'] ?? "-") ?></p>
                <p class="worker-cust"><i class="fas fa-rocket me-2"></i><?= htmlspecialchars($row['customer_name'] ?? "-") ?></p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="modal fade" id="activityModal" tabindex="-1">
          <div class="modal-dialog modal-xl">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="activityModalTitle">Pilih Aktivitas</h5>
              </div>
              <div class="modal-body">
                <div class="row" id="activityCards">
                  <!-- Cards will be populated here -->
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="footer">
          <p>© 2025 Sistem Informasi Kinerja Karyawan. Data diperbarui secara real-time.</p>
        </div>
      </div>
    </div>
    <!-- Sidebar with Product and SO Cards -->
    <div class="sidebar p-3" style="width: 200px; background-color: #f8f9fa;">
      <h5 class="text-center mb-3">Production List</h5>
      <?php if (!empty($production_lots)): ?>
        <?php foreach ($production_lots as $lot): ?>
          <div class="product-card mb-2">
            <div class="card shadow-sm border-0">
              <div class="card-body p-2 text-center">
                <h6 class="mb-1 text-primary"><?= htmlspecialchars($lot['production_code']) ?></h6>
                <p class="mb-0 text-muted"><?= htmlspecialchars($lot['sale_order_ref']) ?></p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-muted">No active production lots for this station.</p>
      <?php endif; ?>
    </div>

  </div>

  <!-- Bootstrap 5 JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
<script>
  let waitingForActivity = false;
  let waitingForCode = false;
  let currentEmployee = null;

  const subActivities = {
    'gky': {
      'material_masuk': '📥 Material Masuk',
      'proses_kd': '🔧 Proses KD',
      'grade_material_out': '📤 Grade Material Out',
      'grade_material_in': '📥 Grade Material In',
      'ambil_antar_jasa': '🚚 Ambil & Antar Jasa',
      'forklift': '🚛 Forklift',
      'pemeliharaan_material': '🔧 Pemeliharaan Material',
      'cek_stok': '📊 Cek Stok',
      'material_keluar': '📤 Material Keluar'
    },
    'cs3': {
      'bleaching': '🧴 BLEACHING',
      'tanin': '🌿 TANIN',
      'body_stain': '🎨 BODY STAIN',
      'sealer': '🛡️ SEALER',
      'sanding_dempul': '🪚 SANDING & DEMPUL',
      'primer_base_coat': '🖌️ PRIMER / BASE COAT',
      'glaze': '✨ GLAZE',
      'hand_pad': '🖐️ HAND PAD',
      'setting': '⚙️ SETTING',
      'top_coat': '🎨 TOP COAT'
    },
    'sp1': {
      'preparation': '🔧 Preparation',
      'proses': '⚙️ Proses',
      'assembling': '🛠️ Assembling',
      'sanding_repair': '🪚 Sanding & Repair',
      'retro': '🔄 Retro'
    }
  };

  const breakActivities = {
    'transfer': '🔄 Transfer',
    'toilet': '🚻 Toilet',
    'pindah_bagian': '🏢 Pindah Bagian',
    'gudang': '📦 Gudang H/W',
    'istirahat': '☕ Istirahat',
    'pulang_dini': '⏰ Pulang Dini',
    'hrd': '📋 Kepentingan Divisi/HRD',
    'pulang': '🏁 Pulang',
    'sholat': '🙏 Sholat Sore'
  };

  const input = document.getElementById('scanInput');

  input.addEventListener('keypress', e => {
    if (e.key === 'Enter') {
      const scanned = input.value.trim();
      if (!scanned) return;
      handleScan(scanned);
      input.value = '';
    }
  });

  async function handleActivityScan(scanned) {
    // Check if it's an activity code
    const upperScanned = scanned.toUpperCase();
    const act = activityCodes[upperScanned];
    if (act) {
      waitingForActivity = false;
      bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
      if (act.needsCode) {
        const {
          value: code
        } = await Swal.fire({
          title: 'Masukkan Kode SO / Barcode',
          input: 'text',
          showCancelButton: true
        });
        if (!code) return;
        return callActivity(currentEmployee.employee_nik, act.name, {
          code
        });
      } else {
        return callActivity(currentEmployee.employee_nik, act.name);
      }
    } else {
      // Invalid activity code
      Swal.fire({
        icon: 'error',
        title: 'Kode Aktivitas Tidak Valid',
        text: 'Silakan scan barcode aktivitas yang benar.',
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: false
      });
      return;
    }
  }

  async function handleScan(scanned) {
    if (waitingForCode) {
      waitingForCode = false;
      return callActivity(currentEmployee.employee_nik, 'bekerja', {
        code: scanned
      });
    }

    if (waitingForActivity) {
      // For sub or break modals
      const upperScanned = scanned.toUpperCase();
      // Assume scanned is the activity name, or map if needed
      // For simplicity, assume scanned is the key
      waitingForActivity = false;
      bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
      return callActivity(currentEmployee.employee_nik, scanned);
    }

    // Normal NIK scan
    try {
      const stationCode = document.getElementById('stationCode').value;
      const body = new URLSearchParams({
        action: 'scan',
        nik: scanned,
        station: stationCode
      });

      const resp = await fetch('scan.php', {
        method: 'POST',
        body
      });

      if (!resp.ok) {
        throw new Error(`HTTP error! status: ${resp.status}`);
      }

      const data = await resp.json();

      if (data.status === 'not_found') {
        return Swal.fire({
          icon: 'error',
          title: 'Error',
          text: data.message,
          timer: 3000,
          timerProgressBar: true,
          showConfirmButton: false
        }).then(() => {
          location.reload();
        });
      }

      if (data.status === 'choose_break') {
        return showBreakModal(data.employee);
      }

      if (data.status === 'choose_sub') {
        return showSubModal(data.employee, stationCode);
      }

      if (data.status === 'input_code') {
        currentEmployee = data.employee;
        return Swal.fire({
          title: 'Masukkan Kode Kerja',
          text: 'Silakan scan id product atau id so',
          input: 'text',
          inputPlaceholder: 'Masukkan kode',
          showCancelButton: false,
          allowOutsideClick: false
        }).then((result) => {
          if (result.isConfirmed && result.value) {
            const code = result.value.trim();
            if (/^\d{5,6}-\d{4}-\d{4}$/.test(code)) {
              // It's a product code, check production_lots
              fetch('scan.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'check_product',
                  code: code,
                  emp_nik: data.employee.employee_nik,
                  station: stationCode
                })
              }).then(r => r.json()).then(res => {
                if (res.success) {
                  Swal.fire({
                    icon: 'success',
                    title: 'Sukses',
                    text: res.message,
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false
                  }).then(() => location.reload());
                } else {
                  Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: res.message,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false
                  }).then(() => location.reload());
                }
              });
            } else {
              // Call insert_work for SO
              fetch('scan.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'insert_work',
                  code: code,
                  emp_nik: data.employee.employee_nik,
                  station: stationCode
                })
              }).then(r => r.json()).then(res => {
                if (res.status === 'choose_sub') {
                  // Tampilkan modal sub-activity
                  showSubModal(res.employee, stationCode, res.code_info, res.code);
                } else if (res.success) {
                  Swal.fire({
                    icon: 'success',
                    title: 'Sukses',
                    text: res.message,
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false
                  }).then(() => location.reload());
                } else {
                  Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: res.message,
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false
                  }).then(() => location.reload());
                }
                console.log("JSON OK:", res);
              });
            }
          }
        });
      }

      if (data.status === 'resumed') {
        return Swal.fire({
          icon: 'success',
          title: 'Sukses',
          text: data.message,
          timer: 2000,
          timerProgressBar: true,
          showConfirmButton: false
        }).then(() => location.reload());
      }

      if (data.status === 'success') {
        return Swal.fire({
          icon: 'success',
          title: 'Sukses',
          text: data.message,
          timer: 2000,
          timerProgressBar: true,
          showConfirmButton: false
        }).then(() => location.reload());
      }

      if (data.status === 'error') {
        return Swal.fire({
          icon: 'error',
          title: 'Error',
          text: data.message,
          timer: 3000,
          timerProgressBar: true,
          showConfirmButton: false
        }).then(() => location.reload());
      }

      if (data.success === false) {
        return Swal.fire({
          icon: 'error',
          title: 'Error',
          text: data.message,
          timer: 3000,
          timerProgressBar: true,
          showConfirmButton: false
        }).then(() => {
          location.reload();
        });
      }
    } catch (err) {
      console.error('Scan error:', err);
      Swal.fire('Kesalahan', 'Terjadi kesalahan saat memproses scan: ' + err.message, 'error');
    }
  }

  function showSubModal(employee, station, code_info, code) {
    currentEmployee = employee;
    waitingForActivity = true;
    isModalOpen = true;
    document.getElementById('activityModalTitle').textContent = `Pilih Sub Aktivitas untuk ${employee.employee_name}`;

    // Hide global scan input
    document.getElementById('scanInput').style.display = 'none';

    const container = document.getElementById('activityCards');
    container.innerHTML = `
      <div class="col-12 mb-4">
        <input type="text" id="activityScanInput" class="form-control" placeholder="Scan Kode Sub Aktivitas" autofocus>
      </div>
    `;

    const acts = subActivities[station] || {};
    console.log("Sub activities for", station, acts);
    Object.keys(acts).forEach(key => {
      const display = acts[key];
      const card = document.createElement('div');
      card.className = 'col-md-3 mb-5';
      card.innerHTML = `
        <div class="card activity-card" data-code="${key}">
          <div class="card-body text-center">
            <div id="barcode-${key}" class="d-flex justify-content-center"></div>
            <p class="mt-3 fs-3 fw-bold">${display}</p>
          </div>
        </div>
      `;
      card.addEventListener('click', () => {
        bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
        callActivity(employee.employee_nik, key, {
          code: code
        });
      });
      container.appendChild(card);

      // Generate QR code
      new QRCode(document.getElementById(`barcode-${key}`), {
        text: key,
        width: 140,
        height: 140
      });
    });

    const modal = new bootstrap.Modal(document.getElementById('activityModal'));
    modal.show();

    // Add event listener for activity scan input
    const activityInput = document.getElementById('activityScanInput');
    activityInput.addEventListener('keypress', e => {
      if (e.key === 'Enter') {
        const scanned = activityInput.value.trim();
        if (!scanned) return;
        bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
        callActivity(employee.employee_nik, scanned, {
          code: code
        });
        activityInput.value = '';
      }
    });

    // Focus on activity input when modal is shown
    document.getElementById('activityModal').addEventListener('shown.bs.modal', function() {
      document.getElementById('activityScanInput').focus();
    });

    // Show global scan input when modal closes
    document.getElementById('activityModal').addEventListener('hidden.bs.modal', function() {
      document.getElementById('scanInput').style.display = '';
      waitingForActivity = false;
      isModalOpen = false;
    });
  }

  function showBreakModal(employee) {
    currentEmployee = employee;
    waitingForActivity = true;
    isModalOpen = true;
    document.getElementById('activityModalTitle').textContent = `Pilih Aktivitas untuk ${employee.employee_name}`;

    // Hide global scan input
    document.getElementById('scanInput').style.display = 'none';

    const container = document.getElementById('activityCards');
    container.innerHTML = `
      <div class="col-12 mb-4">
        <input type="text" id="activityScanInput" class="form-control" placeholder="Scan Kode Aktivitas" autofocus>
      </div>
    `;

    Object.keys(breakActivities).forEach(key => {
      const display = breakActivities[key];
      const card = document.createElement('div');
      card.className = 'col-md-3 mb-5';
      card.innerHTML = `
        <div class="card activity-card" data-code="${key}">
          <div class="card-body text-center">
            <div id="barcode-${key}" class="d-flex justify-content-center"></div>
            <p class="mt-3 fs-3 fw-bold">${display}</p>
          </div>
        </div>
      `;
      card.addEventListener('click', () => {
        bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
        callActivity(employee.employee_nik, key);
      });
      container.appendChild(card);

      // Generate QR code
      new QRCode(document.getElementById(`barcode-${key}`), {
        text: key,
        width: 140,
        height: 140
      });
    });

    const modal = new bootstrap.Modal(document.getElementById('activityModal'));
    modal.show();

    // Add event listener for activity scan input
    const activityInput = document.getElementById('activityScanInput');
    activityInput.addEventListener('keypress', e => {
      if (e.key === 'Enter') {
        const scanned = activityInput.value.trim();
        if (!scanned) return;
        callActivity(employee.employee_nik, scanned);
        activityInput.value = '';
        bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
      }
    });

    // Focus on activity input when modal is shown
    document.getElementById('activityModal').addEventListener('shown.bs.modal', function() {
      document.getElementById('activityScanInput').focus();
    });

    // Show global scan input when modal closes
    document.getElementById('activityModal').addEventListener('hidden.bs.modal', function() {
      document.getElementById('scanInput').style.display = '';
      waitingForActivity = false;
      isModalOpen = false;
    });
  }

  function selectActivity(code) {
    const act = activityCodes[code];
    if (act.needsCode) {
      // Ask for code
      Swal.fire({
        title: 'Masukkan Kode SO / Barcode',
        input: 'text',
        showCancelButton: true
      }).then(result => {
        if (result.isConfirmed && result.value) {
          callActivity(currentEmployee.employee_nik, act.name, {
            code: result.value
          });
        }
      });
    } else {
      callActivity(currentEmployee.employee_nik, act.name);
    }
    bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
  }

  async function callActivity(nik, activity, extra = {}) {
    try {
      const stationCode = document.getElementById('stationCode').value;
      const body = new URLSearchParams({
        action: 'activity',
        nik,
        activity,
        station: stationCode,
      });
      if (extra.code) body.append('code', extra.code);
      if (extra.new_station) body.append('new_station', extra.new_station);

      const resp = await fetch('scan.php', {
        method: 'POST',
        body
      });
      const data = await resp.json();
      console.log(data);
      if (data.success) {
        Swal.fire({
          icon: 'success',
          title: 'Sukses',
          text: data.message,
          timer: 2000,
          timerProgressBar: true,
          showConfirmButton: false
        }).then(() => {
          location.reload(); // Refresh to show updated data
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: data.message,
          timer: 3000,
          timerProgressBar: true,
          showConfirmButton: false
        }).then(() => {
          location.reload();
        });
      }
    } catch (err) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: err.message,
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: false
      }).then(() => {
        location.reload();
      });
    }
  }

  function updateDigitalClock() {
    const now = new Date();

    // Format waktu
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const timeString = `${hours}:${minutes}:${seconds}`;

    // Format tanggal
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    const day = days[now.getDay()];
    const date = now.getDate();
    const month = months[now.getMonth()];
    const year = now.getFullYear();

    const dateString = `${day}, ${date} ${month} ${year}`;

    // Update elemen HTML
    document.getElementById('digital-time').textContent = timeString;
    document.getElementById('digital-date').textContent = dateString;
  }

  // Update jam setiap detik
  setInterval(updateDigitalClock, 1000);

  // Panggil pertama kali untuk menghindari penundaan tampilan
  updateDigitalClock();

  // Auto reload every 15 seconds, but only if no modal is open
  let isModalOpen = false;

  setInterval(() => {
    if (!isModalOpen) {
      location.reload();
    }
  }, 15000);
</script>

</html>