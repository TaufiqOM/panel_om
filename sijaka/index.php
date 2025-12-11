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
        SELECT 
            pe.*, 
            e.image_1920,
            CASE 
                WHEN pe.work = 'product' THEN pl.production_code
                ELSE pe.sale_order_ref
            END as display_reference
        FROM production_employee pe
        LEFT JOIN employee e ON pe.employee_nik = e.barcode
        LEFT JOIN production_lots pl ON pe.sale_order_ref = pl.sale_order_ref 
            AND pe.station_code = pl.station_code
        WHERE pe.station_code = ? 
        ORDER BY 
            CASE 
                -- Priority 1: Transfer, HRD, Gudang, Toilet (urutan khusus)
                WHEN pe.description = 'transfer' THEN 1
                WHEN pe.description = 'hrd' THEN 2
                WHEN pe.description = 'gudang' THEN 3
                WHEN pe.description = 'toilet' THEN 4
                
                -- Priority 2: Lainnya kecuali istirahat
                WHEN pe.description = '' THEN 5  -- Kosong
                WHEN pe.description NOT LIKE '%istirahat%' THEN 6  -- Lainnya
                
                -- Priority 3: Istirahat (paling bawah)
                WHEN pe.description LIKE '%istirahat%' THEN 7
                ELSE 8
            END,
            pe.scan_in DESC
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

// ===============================
// 5. Logika info istirahat
// ===============================
$info_istirahat = '';
$hari_ini = date('N'); // 1=Senin, 7=Minggu
$jam_sekarang = date('H:i');

// Jumat: jam 11:00
if ($hari_ini == 5) { // Jumat
    if ($jam_sekarang >= '10:55' && $jam_sekarang <= '11:15') {
        $info_istirahat = 'BERANG-BERANG MAKAN COKLAT, ISTIRAHAT DULU SOBAT';
    }
} 
// Hari lainnya (Sabtu-Kamis): jam 12:00
else {
    if ($jam_sekarang >= '11:55' && $jam_sekarang <= '12:15') {
        $info_istirahat = 'BERANG-BERANG MAKAN COKLAT, ISTIRAHAT DULU SOBAT';
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
     :root {
      --primary-color: #2563eb;
      --primary-dark: #1d4ed8;
      --secondary-color: #64748b;
      --success-color: #10b981;
      --warning-color: #f59e0b;
      --info-color: #3b82f6;
      --danger-color: #ef4444;
      --light-bg: #f8fafc;
      --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
      --card-hover-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }

    body {
      background-color: var(--light-bg);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      padding: 12px;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* HEADER LEBIH KECIL & KOMPAK */
    .sijaka-header {
      background: white;
      border-radius: 10px;
      padding: 12px 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
      border: 1px solid #e2e8f0;
      position: relative;
    }

    .sijaka-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 4px;
      height: 100%;
      background: linear-gradient(to bottom, var(--primary-color), var(--success-color));
    }

    .header-content {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
    }

    .header-left {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo-container {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 16px;
      box-shadow: 0 3px 5px rgba(37, 99, 235, 0.2);
    }

    .station-info {
      display: flex;
      flex-direction: column;
    }

    .station-title {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 2px;
    }

    .station-title h1 {
      font-size: 1.1rem;
      font-weight: 600;
      color: #1e293b;
      margin: 0;
    }

    .station-code {
      background: #f1f5f9;
      color: var(--secondary-color);
      padding: 1px 8px;
      border-radius: 12px;
      font-size: 0.7rem;
      font-weight: 500;
    }

    .station-name {
      color: var(--secondary-color);
      font-size: 0.85rem;
      font-weight: 400;
      margin: 0;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .worker-count {
      background: linear-gradient(135deg, var(--success-color), #0da271);
      color: white;
      padding: 4px 12px;
      border-radius: 16px;
      font-size: 0.75rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 4px;
      box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
    }

    .time-display {
      text-align: right;
    }

    .current-time {
      font-size: 0.95rem;
      font-weight: 600;
      color: #1e293b;
      font-family: 'JetBrains Mono', 'Courier New', monospace;
      letter-spacing: 0.5px;
    }

    .current-date {
      font-size: 0.75rem;
      color: var(--secondary-color);
      margin-top: 1px;
    }

    /* Alert Istirahat */
    .alert-istirahat {
      background: linear-gradient(135deg, var(--warning-color), #d97706);
      color: white;
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.9rem;
      text-align: center;
      animation: pulse 2s infinite;
      box-shadow: 0 3px 8px rgba(245, 158, 11, 0.3);
      border: 2px solid rgba(255, 255, 255, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 8px;
      width: 100%;
    }

    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.01); }
      100% { transform: scale(1); }
    }

    /* Responsive Header */
    @media (max-width: 768px) {
      .sijaka-header {
        padding: 10px 14px;
      }
      
      .header-content {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      
      .header-left, .header-right {
        width: 100%;
      }
      
      .header-right {
        justify-content: space-between;
        border-top: 1px solid #e2e8f0;
        padding-top: 10px;
      }
    }

    /* Card Worker - 7 KOLOM */
    .card-worker {
      border: none;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: var(--card-shadow);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      background: white;
      height: 100%;
      position: relative;
    }

    .card-worker:hover {
      transform: translateY(-3px);
      box-shadow: var(--card-hover-shadow);
    }

    /* Container foto 3x4 - diperkecil */
    .photo-container {
      position: relative;
      width: 100%;
      height: 110px;
      overflow: hidden;
      background: linear-gradient(135deg, #f8fafc, #e2e8f0);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 5px;
    }

    /* Foto 3x4 dengan frame - diperkecil */
    .photo-3x4 {
      width: 70px;
      height: 100px;
      object-fit: cover;
      object-position: top;
      border-radius: 5px;
      box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
      border: 2px solid white;
      transition: transform 0.3s ease;
    }

    .card-worker:hover .photo-3x4 {
      transform: scale(1.02);
    }

    .status-badge {
      position: absolute;
      top: 10px;
      right: 10px;
      font-size: 0.65rem;
      padding: 3px 8px;
      border-radius: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      z-index: 2;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .status-available {
      background: linear-gradient(135deg, var(--success-color), #0da271);
      color: white;
    }

    .card-content {
      padding: 12px;
      background: white;
    }

    .worker-name {
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 0px;
      font-size: 0.85rem;
      line-height: 1.2;
      height: 1.8em;
      overflow: hidden;
      text-overflow: ellipsis;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
    }

    .worker-id {
      color: var(--secondary-color);
      font-size: 0.7rem;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .worker-info {
      background: #f8fafc;
      border-radius: 6px;
      padding: 6px 8px;
      margin: 4px 0;
      font-size: 0.7rem;
      display: flex;
      align-items: center;
      gap: 6px;
      border: 1px solid #e2e8f0;
    }

    .worker-info i {
      color: var(--primary-color);
      width: 14px;
      font-size: 0.7rem;
    }

    .info-label {
      color: var(--secondary-color);
      font-weight: 500;
      min-width: 55px;
      font-size: 0.65rem;
    }

    .info-value {
      color: #1e293b;
      font-weight: 600;
      flex: 1;
      text-align: right;
      font-size: 0.7rem;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    /* Sidebar Minimalis */
    .sidebar {
      background: white;
      border-radius: 10px;
      box-shadow: var(--card-shadow);
      padding: 15px;
      height: calc(100vh - 24px);
      position: sticky;
      top: 12px;
      border-left: 4px solid var(--primary-color);
    }

    .sidebar-title {
      color: #1e293b;
      font-weight: 600;
      font-size: 0.9rem;
      margin-bottom: 12px;
      padding-bottom: 8px;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .production-card {
      background: white;
      border-radius: 6px;
      padding: 8px 10px;
      margin-bottom: 8px;
      border: 1px solid #e2e8f0;
      transition: all 0.2s;
    }

    .production-card:hover {
      border-color: var(--primary-color);
      box-shadow: 0 2px 6px rgba(37, 99, 235, 0.1);
    }

    .prod-code {
      font-weight: 600;
      color: #1e293b;
      font-size: 0.75rem;
      margin-bottom: 2px;
    }

    .prod-so {
      color: var(--secondary-color);
      font-size: 0.65rem;
    }

    /* Footer Simple */
    .footer {
      text-align: center;
      padding: 15px;
      margin-top: 20px;
      color: var(--secondary-color);
      font-size: 0.75rem;
    }

    /* Grid Responsive - 7 KOLOM */
    @media (min-width: 1800px) {
      .col-xl-7col {
        flex: 0 0 14.2857%;
        max-width: 14.2857%;
      }
    }
    
    @media (max-width: 1799px) and (min-width: 1400px) {
      .col-xl-7col {
        flex: 0 0 16.6667%;
        max-width: 16.6667%;
      }
    }
    
    @media (max-width: 1399px) and (min-width: 1200px) {
      .col-xl-7col {
        flex: 0 0 20%;
        max-width: 20%;
      }
    }
    
    @media (max-width: 1199px) and (min-width: 992px) {
      .col-xl-7col {
        flex: 0 0 25%;
        max-width: 25%;
      }
    }
    
    @media (max-width: 991px) and (min-width: 768px) {
      .col-xl-7col {
        flex: 0 0 33.3333%;
        max-width: 33.3333%;
      }
    }
    
    @media (max-width: 767px) and (min-width: 576px) {
      .col-xl-7col {
        flex: 0 0 50%;
        max-width: 50%;
      }
    }
    
    @media (max-width: 575px) {
      .col-xl-7col {
        flex: 0 0 100%;
        max-width: 100%;
      }
    }

    /* Animasi */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .card-worker {
      animation: fadeInUp 0.3s ease-out;
    }

    .sijaka-header {
      animation: fadeInUp 0.2s ease-out;
    }

    /* Scan Input Hidden */
    #scanInput {
      opacity: 0;
      position: absolute;
      left: -9999px;
    }

    /* Break Activities Color */
    .break-transfer { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .break-toilet { background: linear-gradient(135deg, #ec4899, #db2777); }
    .break-pindah_bagian { background: linear-gradient(135deg, #14b8a6, #0d9488); }
    .break-gudang { background: linear-gradient(135deg, #f97316, #ea580c); }
    .break-istirahat { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .break-pulang_dini { background: linear-gradient(135deg, #6366f1, #4f46e5); }
    .break-hrd { background: linear-gradient(135deg, #06b6d4, #0891b2); }
    .break-pulang { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .break-sholat { background: linear-gradient(135deg, #10b981, #059669); }

    /* Activity Modal */
    .activity-card {
      cursor: pointer;
      transition: transform 0.2s;
    }
    
    .activity-card:hover {
      transform: translateY(-2px);
    }

    /* Marquee Text untuk teks panjang */
    .marquee-container {
      width: 100%;
      overflow: hidden;
      position: relative;
      white-space: nowrap;
    }

    .marquee-text {
      display: inline-block;
      padding-left: 100%;
      animation: marquee 10s linear infinite;
      animation-play-state: running;
    }

    .marquee-text:hover {
      animation-play-state: paused;
    }

    @keyframes marquee {
      0% {
        transform: translateX(0);
      }
      100% {
        transform: translateX(-100%);
      }
    }

    /* Untuk teks pendek tidak perlu marquee */
    .no-marquee {
      animation: none !important;
      padding-left: 0 !important;
    }
  </style>

</head>

<body>
  <div class="container-fluid">
    <div class="row">
      <!-- Main Content Area -->
      <div class="col-lg-9 col-xl-10">
        <!-- HEADER LEBIH KECIL -->
        <div class="sijaka-header">
          <div class="header-content">
            <div class="header-left">
              <div class="logo-container">
                <i class="fas fa-users"></i>
              </div>
              <div class="station-info">
                <div class="station-title">
                  <h1>DAFTAR PEKERJA</h1>
                  <?php if($station): ?>
                    <span class="station-code"><?= htmlspecialchars($station) ?></span>
                  <?php endif; ?>
                </div>
                <?php if($station_name): ?>
                  <p class="station-name"><?= htmlspecialchars($station_name) ?></p>
                <?php endif; ?>
              </div>
            </div>
            
            <div class="header-right">
              <div class="worker-count">
                <i class="fas fa-user-check"></i>
                <span><?= count($data) ?> Aktif</span>
              </div>
              <div class="time-display">
                <div class="current-time" id="digital-time">00:00:00</div>
                <div class="current-date" id="digital-date">Hari, 00 Bulan 0000</div>
              </div>
            </div>
          </div>
          
          <?php if ($info_istirahat): ?>
          <div class="alert-istirahat">
            <i class="fas fa-utensils me-2"></i>
            <?= htmlspecialchars($info_istirahat) ?>
          </div>
          <?php endif; ?>
          
          <!-- Scan Input Hidden -->
          <input type="text" id="scanInput" autofocus>
          <input type="hidden" id="stationCode" value="<?= htmlspecialchars($station) ?>">
        </div>

        <!-- Start Alert Login Devisi -->
        <script>
          document.addEventListener("DOMContentLoaded", function() {
            <?php if ($station === ''): ?>
            isModalOpen = true;
              Swal.fire({
                title: "Token Verification",
                html: `
                  <div style="text-align: center;">
                    <div style="width: 56px; height: 56px; margin: 0 auto 12px; border-radius: 10px; background: linear-gradient(135deg, #2563eb, #1d4ed8); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                      <i class="fas fa-lock"></i>
                    </div>
                    <h5 style="margin: 6px 0; color: #1e293b; font-size: 1.1rem;">PT Omega Mas</h5>
                    <p style="margin: 3px 0; color: #64748b; font-size: 0.9rem;">Masukkan Token Akses</p>
                  </div>
                `,
                input: "text",
                inputPlaceholder: "Masukkan Token",
                allowOutsideClick: false,
                allowEscapeKey: false,
                showCancelButton: false,
                confirmButtonText: "Verifikasi Token",
                confirmButtonColor: "#2563eb",
                inputValidator: (value) => {
                  if (!value) {
                    return "Token harus diisi!";
                  }
                }
              }).then((tokenResult) => {
                if (tokenResult.isConfirmed) {
                  let token = tokenResult.value.trim();
                  
                  fetch("get_token.php", {
                      method: "POST",
                      headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                      },
                      body: "token=" + encodeURIComponent(token)
                    })
                    .then(r => r.json())
                    .then(tokenRes => {
                        isModalOpen = true;
                      if (tokenRes.status === "token_ok") {
                        Swal.fire({
                          title: "Panel Factory",
                          html: `
                            <div style="text-align: center;">
                              <div style="width: 56px; height: 56px; margin: 0 auto 12px; border-radius: 10px; background: linear-gradient(135deg, #10b981, #0da271); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                                <i class="fas fa-industry"></i>
                              </div>
                              <h5 style="margin: 6px 0; color: #1e293b; font-size: 1.1rem;">PT Omega Mas</h5>
                              <p style="margin: 3px 0; color: #64748b; font-size: 0.9rem;">Scan Nama Divisi atau Bagian</p>
                              <small style="color: #94a3b8; font-size: 0.8rem;">Token: ${token}</small>
                            </div>
                          `,
                          input: "text",
                          inputPlaceholder: "Masukkan Bagian / Divisi",
                          allowOutsideClick: false,
                          allowEscapeKey: false,
                          showCancelButton: false,
                          confirmButtonText: "Lanjut ke Station",
                          confirmButtonColor: "#10b981",
                          inputValidator: (value) => {
                            if (!value) {
                              return "Bagian / Divisi harus diisi!";
                            }
                          }
                        }).then((stationResult) => {
                            isModalOpen = true;
                          if (stationResult.isConfirmed) {
                            let station = stationResult.value.trim();
                            
                            fetch("index.php", {
                                method: "POST",
                                headers: {
                                  "Content-Type": "application/x-www-form-urlencoded"
                                },
                                body: "station=" + encodeURIComponent(station)
                              })
                              .then(r => r.json())
                              .then(stationRes => {
                                  isModalOpen = false;
                                if (stationRes.status === "ok") {
                                  window.location.href = "index.php?station=" + encodeURIComponent(station);
                                } else {
                                  Swal.fire({
                                    icon: "error",
                                    title: "Station Invalid",
                                    text: stationRes.message,
                                    confirmButtonColor: "#ef4444",
                                    timerProgressBar: true
                                  }).then(() => {
                                    location.reload();
                                  });
                                }
                              })
                              .catch(err => {
                                Swal.fire({
                                  icon: "error",
                                  title: "Error",
                                  text: "Terjadi kesalahan saat validasi station",
                                  confirmButtonColor: "#ef4444"
                                }).then(() => {
                                  location.reload();
                                });
                              });
                          }
                        });
                      } else {
                          isModalOpen = false;
                        Swal.fire({
                          icon: "error",
                          title: "Token Invalid",
                          text: tokenRes.message,
                          confirmButtonColor: "#ef4444",
                          timerProgressBar: true
                        }).then(() => {
                          location.reload();
                        });
                      }
                    })
                    .catch(err => {
                      Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Terjadi kesalahan saat validasi token",
                        confirmButtonColor: "#ef4444"
                      }).then(() => {
                        location.reload();
                      });
                    });
                }
              });
            <?php endif; ?>
          });
        </script>
        <!-- End Alert Login Devisi -->

        <div class="row" style="--bs-gutter-x: 0.8rem !important;">
          <?php foreach ($data as $row): ?>
            <div class="col-xl-7col col-lg-3 col-md-4 col-sm-6 mb-1">
              <div class="card-worker">
                <div class="photo-container">
                  <?php 
                    $photoPath = "../assets/img/employees/" . $row['employee_nik'] . ".jpg";
                    $defaultPhoto = "https://ui-avatars.com/api/?name=" . urlencode($row['employee_fullname']) . "&background=2563eb&color=fff&size=150&bold=true";
                  ?>
                  <img src="<?= $photoPath ?>" 
                       onerror="this.src='<?= $defaultPhoto ?>'; this.onerror=null;" 
                       alt="Foto Karyawan" 
                       class="photo-3x4">
                  
                  <?php if ($row['status'] === 0): ?>
                    <span class="status-badge status-available">
                      <i class="fas fa-check-circle me-1"></i><?= htmlspecialchars($row['description']) ?>
                    </span>
                  <?php endif; ?>
                </div>
                
                <div class="card-content">
                  <h6 class="worker-name"><?= htmlspecialchars($row['employee_fullname']) ?></h6>
                  
                  <div class="worker-id">
                    <i class="fas fa-id-card"></i>
                    <span><?= htmlspecialchars($row['employee_nik']) ?></span>
                  </div>
                  
                  <div class="worker-info">
                    <i class="fas fa-<?= $row['work'] === 'product' ? 'barcode' : 'briefcase' ?>"></i>
                    <span class="info-label">Proyek:</span>
                    <div class="info-value">
                      <?php 
                        $projectText = htmlspecialchars($row['display_reference'] ?? $row['sale_order_ref'] ?? "-");
                        $needsMarquee = strlen($projectText) > 15; // Jika lebih dari 15 karakter, pakai marquee
                      ?>
                      <?php if ($needsMarquee): ?>
                        <div class="marquee-container">
                          <span class="marquee-text"><?= $projectText ?></span>
                        </div>
                      <?php else: ?>
                        <?= $projectText ?>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="worker-info">
                    <i class="fas fa-rocket"></i>
                    <span class="info-label">Customer:</span>
                    <div class="info-value">
                      <?php 
                        $customerText = htmlspecialchars($row['customer_name'] ?? "-");
                        $needsMarqueeCustomer = strlen($customerText) > 15; // Jika lebih dari 15 karakter, pakai marquee
                      ?>
                      <?php if ($needsMarqueeCustomer): ?>
                        <div class="marquee-container">
                          <span class="marquee-text"><?= $customerText ?></span>
                        </div>
                      <?php else: ?>
                        <?= $customerText ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  
                  <?php if (!empty($row['scan_in'])): ?>
                    <div class="worker-info" style="background: #e0f2fe; border-color: #bae6fd;">
                      <i class="fas fa-clock"></i>
                      <span class="info-label">Mulai:</span>
                      <span class="info-value"><?= date('H:i', strtotime($row['scan_in'])) ?></span>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          
          <?php if (empty($data)): ?>
            <div class="col-12 text-center py-4">
              <div class="alert" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; padding: 12px; font-size: 0.9rem;">
                <i class="fas fa-user-clock me-2"></i>
                Tidak ada pekerja yang aktif di station ini
              </div>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="footer">
          <p class="mb-0" style="color: #94a3b8; font-size: 0.8rem;">
            <i class="fas fa-sync-alt me-1"></i> Data diperbarui otomatis setiap 15 detik
          </p>
          <p class="mt-1" style="font-size: 0.7rem; color: #cbd5e1;">
            © 2025 SIJAKA - Sistem Informasi Kinerja Karyawan
          </p>
        </div>
      </div>
      
      <!-- Sidebar Production List -->
      <div class="col-lg-3 col-xl-2">
        <div class="sidebar">
          <h6 class="sidebar-title">
            <i class="fas fa-list-check"></i> Production
          </h6>
          
          <?php if (!empty($production_lots)): ?>
            <div style="max-height: calc(100vh - 120px); overflow-y: auto;">
              <?php foreach ($production_lots as $lot): ?>
                <div class="production-card">
                  <div class="prod-code">
                    <i class="fas fa-hashtag me-1"></i>
                    <?= htmlspecialchars($lot['production_code']) ?>
                  </div>
                  <div class="prod-so">
                    <small><i class="fas fa-tag me-1"></i> <?= htmlspecialchars($lot['sale_order_ref']) ?></small>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="mt-3 text-center">
              <small style="color: #94a3b8; font-size: 0.75rem;">
                <i class="fas fa-cube me-1"></i>
                <?= count($production_lots) ?> aktif
              </small>
            </div>
          <?php else: ?>
            <div class="text-center py-3">
              <i class="fas fa-inbox" style="color: #cbd5e1; margin-bottom: 6px; font-size: 1.2rem;"></i>
              <p style="color: #94a3b8; font-size: 0.8rem;">Tidak ada produksi aktif</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Activity Modal -->
  <div class="modal fade" id="activityModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="activityModalTitle">Pilih Aktivitas</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row" id="activityCards">
            <!-- Cards will be populated here -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS Bundle with Popper -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
<script>
  // ===============================
  // GLOBAL VARIABLES & CONFIGURATION
  // ===============================
  let waitingForActivity = false;
  let waitingForCode = false;
  let currentEmployee = null;
  let isModalOpen = false;
  let autoReloadEnabled = true;
  let debugMode = true; // Set to false in production

  // Debug logging function
  function debugLog(message, data = null) {
    if (debugMode) {
      console.log(`[DEBUG] ${new Date().toISOString()}: ${message}`, data || '');
    }
  }

  // Activity configurations
  const subActivities = {
    'gky': {
      'material_masuk': 'Material Masuk',
      'proses_kd': 'Proses KD',
      'grade_material_out': 'Grade Material Out',
      'grade_material_in': 'Grade Material In',
      'ambil_antar_jasa': 'Ambil & Antar Jasa',
      'forklift': 'Forklift',
      'pemeliharaan_material': 'Pemeliharaan Material',
      'cek_stok': 'Cek Stok',
      'material_keluar': 'Material Keluar'
    },
    'cs3': {
      'bleaching': 'BLEACHING',
      'tanin': 'TANIN',
      'body_stain': 'BODY STAIN',
      'sealer': 'SEALER',
      'sanding_dempul': 'SANDING & DEMPUL',
      'primer_base_coat': 'PRIMER / BASE COAT',
      'glaze': 'GLAZE',
      'hand_pad': 'HAND PAD',
      'setting': 'SETTING',
      'top_coat': 'TOP COAT'
    },
    'cs4': {
      'bleaching': 'BLEACHING',
      'tanin': 'TANIN',
      'body_stain': 'BODY STAIN',
      'sealer': 'SEALER',
      'sanding_dempul': 'SANDING & DEMPUL',
      'primer_base_coat': 'PRIMER / BASE COAT',
      'glaze': 'GLAZE',
      'hand_pad': 'HAND PAD',
      'setting': 'SETTING',
      'top_coat': 'TOP COAT'
    },
    'sp1': {
      'preparation': 'Preparation',
      'proses': 'Proses',
      'assembling': 'Assembling',
      'sanding_repair': 'Sanding & Repair',
      'retro': 'Retro'
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

  // ===============================
  // EVENT LISTENERS
  // ===============================
  const input = document.getElementById('scanInput');

  input.addEventListener('keypress', e => {
    if (e.key === 'Enter') {
      const scanned = input.value.trim();
      if (!scanned) return;
      debugLog('Scan input detected', scanned);
      handleScan(scanned);
      input.value = '';
    }
  });

  // ===============================
  // MAIN SCAN HANDLER
  // ===============================
  async function handleScan(scanned) {
    debugLog('handleScan started', { scanned, waitingForCode, waitingForActivity });
    
    if (waitingForCode) {
      debugLog('Processing as work code', scanned);
      waitingForCode = false;
      return callActivity(currentEmployee.employee_nik, 'bekerja', { code: scanned });
    }

    if (waitingForActivity) {
      debugLog('Processing as activity code', scanned);
      waitingForActivity = false;
      bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
      return callActivity(currentEmployee.employee_nik, scanned);
    }

    // Normal NIK scan
    try {
      const stationCode = document.getElementById('stationCode').value;
      debugLog('Sending scan request', { nik: scanned, station: stationCode });
      
      const body = new URLSearchParams({
        action: 'scan',
        nik: scanned,
        station: stationCode
      });

      const resp = await fetch('scan.php', { method: 'POST', body });
      debugLog('Scan response received', { status: resp.status, ok: resp.ok });

      if (!resp.ok) {
        throw new Error(`HTTP error! status: ${resp.status}`);
      }

      // Enhanced JSON parsing with detailed error handling
      const text = await resp.text();
      debugLog('Raw response text', text);
      
      let data;
      try {
        data = JSON.parse(text);
        debugLog('Parsed JSON response', data);
      } catch (e) {
        debugLog('JSON parse failed', { error: e.message, text: text });
        throw new Error(`Invalid JSON response from server: ${text.substring(0, 100)}`);
      }

      // Process different response statuses
      switch (data.status) {
        case 'not_found':
          debugLog('Employee not found', data);
          return Swal.fire({
            icon: 'error',
            title: 'Error',
            text: data.message,
            timer: 3000,
            showConfirmButton: false,
            timerProgressBar: true,
          });

        case 'choose_break':
          debugLog('Showing break modal', data);
          return showBreakModal(data.employee);

        case 'choose_sub':
          debugLog('Showing sub modal', data);
          return showSubModal(data.employee, stationCode);

        case 'input_code':
          debugLog('Requesting work code input', data);
          return await handleInputCode(data, stationCode);

        case 'resumed':
        case 'success':
          debugLog('Success operation', data);
          return Swal.fire({
            icon: 'success',
            title: 'Sukses',
            text: data.message,
            timer: 2000,
            showConfirmButton: false,
            timerProgressBar: true
          }).then(() => location.reload());

        case 'error':
          debugLog('Error response', data);
          return Swal.fire({
            icon: 'error',
            title: 'Error',
            text: data.message,
            timer: 3000,
            showConfirmButton: false,
            timerProgressBar: true
          });

        default:
          debugLog('Unknown status', data);
          throw new Error(`Unknown response status: ${data.status}`);
      }

    } catch (err) {
      debugLog('Scan error caught', err);
      Swal.fire({
        icon: 'error',
        title: 'Kesalahan',
        text: 'Terjadi kesalahan saat memproses scan: ' + err.message,
        timer: 3000,
        showConfirmButton: false,
        timerProgressBar: true
      });
    }
  }

  // ===============================
  // INPUT CODE HANDLER
  // ===============================
  async function handleInputCode(data, stationCode) {
    currentEmployee = data.employee;
    isModalOpen = true;
    
    debugLog('Showing code input dialog');
    const { value: code } = await Swal.fire({
      title: 'Masukkan Kode Kerja',
      text: 'Silakan scan id product atau id so',
      input: 'text',
      inputPlaceholder: 'Masukkan kode',
      showCancelButton: false,
      allowOutsideClick: false
    });
    
    isModalOpen = false;
    
    if (!code) {
      debugLog('User cancelled code input');
      return;
    }

    debugLog('Processing entered code', code);

    // Product code pattern (barcode)
    if (/^\d{5,6}-\d{4}-\d{4}$/.test(code)) {
      debugLog('Detected product code', code);
      return await handleProductCode(code, data, stationCode);
    } else {
      debugLog('Detected SO code', code);
      return await handleSoCode(code, data, stationCode);
    }
  }

  // ===============================
  // PRODUCT CODE HANDLER
  // ===============================
  async function handleProductCode(code, data, stationCode) {
    try {
      debugLog('Checking product', { code, emp_nik: data.employee.employee_nik, station: stationCode });
      
      const productResp = await fetch('scan.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'check_product',
          code: code,
          emp_nik: data.employee.employee_nik,
          station: stationCode
        })
      });
      
      const productText = await productResp.text();
      debugLog('Product check raw response', productText);
      
      let productRes;
      try {
        productRes = JSON.parse(productText);
        debugLog('Product check parsed response', productRes);
      } catch (e) {
        throw new Error('Invalid server response for product check: ' + productText.substring(0, 100));
      }
      
      if (productRes.success) {
        Swal.fire({
          icon: 'success',
          title: 'Sukses',
          text: productRes.message,
          timer: 2000,
          showConfirmButton: false,
          timerProgressBar: true
        }).then(() => location.reload());
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: productRes.message,
          timer: 3000,
          showConfirmButton: false,
          timerProgressBar: true
        });
      }
    } catch (err) {
      debugLog('Product code error', err);
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Terjadi kesalahan: ' + err.message,
        timerProgressBar: true
      });
    }
  }

  // ===============================
  // SO CODE HANDLER (INSERT WORK)
  // ===============================
  async function handleSoCode(code, data, stationCode) {
    try {
      debugLog('Sending insert_work request', { 
        code, 
        emp_nik: data.employee.employee_nik, 
        station: stationCode 
      });

      const insertResp = await fetch('scan.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'insert_work',
          code: code,
          emp_nik: data.employee.employee_nik,
          station: stationCode
        })
      });
      
      debugLog('Insert work response status', { 
        status: insertResp.status, 
        statusText: insertResp.statusText 
      });
      
      const insertText = await insertResp.text();
      debugLog('Insert work raw response', { 
        text: insertText, 
        length: insertText.length,
        isEmpty: !insertText || insertText.trim() === ''
      });
      
      // Enhanced response handling
      if (!insertText || insertText.trim() === '') {
        debugLog('Empty response detected, using fallback');
        // Fallback: langsung tampilkan sub modal
        showSubModal(data.employee, stationCode, { fallback: true }, code);
        return;
      }
      
      let insertRes;
      try {
        insertRes = JSON.parse(insertText);
        debugLog('Insert work parsed response', insertRes);
      } catch (e) {
        debugLog('JSON parse failed, using fallback', e);
        // Fallback jika JSON invalid
        showSubModal(data.employee, stationCode, { fallback: true, rawResponse: insertText }, code);
        return;
      }
      
      // Process insert_work response
      if (insertRes.status === 'choose_sub') {
        debugLog('Showing sub modal from server response');
        showSubModal(insertRes.employee, stationCode, insertRes.code_info, insertRes.code);
      } else if (insertRes.success) {
        Swal.fire({
          icon: 'success',
          title: 'Sukses',
          text: insertRes.message,
          timer: 2000,
          showConfirmButton: false,
          timerProgressBar: true
        }).then(() => location.reload());
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: insertRes.message,
          timer: 3000,
          showConfirmButton: false,
          timerProgressBar: true
        });
      }
    } catch (err) {
      debugLog('SO code error, using emergency fallback', err);
      // Emergency fallback
      showSubModal(data.employee, stationCode, { emergency: true, error: err.message }, code);
    }
  }

  // ===============================
  // MODAL FUNCTIONS
  // ===============================
  function showSubModal(employee, station, code_info, code) {
    debugLog('Showing sub modal', { employee, station, code_info, code });
    
    currentEmployee = employee;
    waitingForActivity = true;
    isModalOpen = true;
    autoReloadEnabled = false;

    document.getElementById('activityModalTitle').textContent = 
      `Pilih Sub Aktivitas untuk ${employee.employee_name}`;
    document.getElementById('scanInput').style.display = 'none';

    const container = document.getElementById('activityCards');
    container.innerHTML = `
      <div class="col-12 mb-2">
        <input type="text" id="activityScanInput" class="form-control" placeholder="Scan Kode Sub Aktivitas" autofocus>
        ${code_info.fallback ? '<div class="alert alert-warning mt-2">Mode fallback aktif - server tidak merespons</div>' : ''}
        ${code_info.emergency ? '<div class="alert alert-danger mt-2">Mode emergency - terjadi kesalahan sistem</div>' : ''}
      </div>
    `;

    const acts = subActivities[station.toLowerCase()] || {};
    debugLog('Available sub activities', acts);
    
    if (Object.keys(acts).length === 0) {
      container.innerHTML += `
        <div class="col-12">
          <div class="alert alert-warning">Tidak ada sub-aktivitas yang tersedia untuk station ${station}</div>
        </div>
      `;
    }

    Object.keys(acts).forEach(key => {
      const display = acts[key];
      const card = document.createElement('div');
      card.className = 'col-6 col-sm-4 col-md-3 mb-2';
      card.innerHTML = `
        <div class="card activity-card" data-code="${key}">
          <div class="card-body text-center">
            <div style="width:80px;height:80px;margin:0 auto;" id="barcode-${key}" class="d-flex justify-content-center"></div>
            <p class="mt-1 fw-bold" style="font-size:13px;">${display}</p>
          </div>
        </div>
      `;
      
      card.addEventListener('click', () => {
        debugLog('Sub activity selected', { activity: key, code: code });
        bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
        callActivity(employee.employee_nik, key, { code: code });
      });
      
      container.appendChild(card);

      // Generate QR code
      try {
        new QRCode(document.getElementById(`barcode-${key}`), {
          text: key,
          width: 100,
          height: 100,
          colorDark: "#000000",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
        });
      } catch (e) {
        debugLog('QR code generation failed', e);
      }
    });

    const modal = new bootstrap.Modal(document.getElementById('activityModal'));
    modal.show();

    // Activity scan input handler
    const activityInput = document.getElementById('activityScanInput');
    activityInput.addEventListener('keypress', e => {
      if (e.key === 'Enter') {
        const scanned = activityInput.value.trim();
        if (!scanned) return;
        debugLog('Activity scan input', scanned);
        bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
        callActivity(employee.employee_nik, scanned, { code: code });
        activityInput.value = '';
      }
    });

    // Modal event listeners
    document.getElementById('activityModal').addEventListener('shown.bs.modal', function() {
      document.getElementById('activityScanInput').focus();
    });

    document.getElementById('activityModal').addEventListener('hidden.bs.modal', function() {
      document.getElementById('scanInput').style.display = '';
      waitingForActivity = false;
      isModalOpen = false;
      autoReloadEnabled = true;
      debugLog('Sub modal closed');
    });
  }

  function showBreakModal(employee) {
    debugLog('Showing break modal', employee);
    
    currentEmployee = employee;
    waitingForActivity = true;
    isModalOpen = true;
    autoReloadEnabled = false;

    document.getElementById('activityModalTitle').textContent = `Pilih Aktivitas untuk ${employee.employee_name}`;
    document.getElementById('scanInput').style.display = 'none';

    const container = document.getElementById('activityCards');
    container.innerHTML = `
      <div class="col-12 mb-2">
        <input type="text" id="activityScanInput" class="form-control" placeholder="Scan Kode Aktivitas" autofocus>
      </div>
    `;

    Object.keys(breakActivities).forEach(key => {
      const display = breakActivities[key];
      const card = document.createElement('div');
      card.className = 'col-6 col-sm-4 col-md-3 mb-2';
      card.innerHTML = `
        <div class="card activity-card" data-code="${key}">
          <div class="card-body text-center">
            <div style="width:80px;height:80px;margin:0 auto;" id="barcode-${key}" class="d-flex justify-content-center"></div>
            <p class="mt-1 fw-bold" style="font-size:13px;">${display}</p>
          </div>
        </div>
      `;
      
      card.addEventListener('click', () => {
        debugLog('Break activity selected', key);
        bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
        callActivity(employee.employee_nik, key);
      });
      
      container.appendChild(card);

      // Generate QR code
      try {
        new QRCode(document.getElementById(`barcode-${key}`), {
          text: key,
          width: 100,
          height: 100
        });
      } catch (e) {
        debugLog('QR code generation failed', e);
      }
    });

    const modal = new bootstrap.Modal(document.getElementById('activityModal'));
    modal.show();

    const activityInput = document.getElementById('activityScanInput');
    activityInput.addEventListener('keypress', e => {
      if (e.key === 'Enter') {
        const scanned = activityInput.value.trim();
        if (!scanned) return;
        debugLog('Break activity scan input', scanned);
        callActivity(employee.employee_nik, scanned);
        activityInput.value = '';
        bootstrap.Modal.getInstance(document.getElementById('activityModal')).hide();
      }
    });

    document.getElementById('activityModal').addEventListener('shown.bs.modal', function() {
      document.getElementById('activityScanInput').focus();
    });

    document.getElementById('activityModal').addEventListener('hidden.bs.modal', function() {
      document.getElementById('scanInput').style.display = '';
      waitingForActivity = false;
      isModalOpen = false;
      autoReloadEnabled = true;
      debugLog('Break modal closed');
    });
  }

  // ===============================
  // ACTIVITY CALL FUNCTION
  // ===============================
  async function callActivity(nik, activity, extra = {}) {
    try {
      debugLog('Calling activity', { nik, activity, extra });
      autoReloadEnabled = false;
      
      const stationCode = document.getElementById('stationCode').value;
      const body = new URLSearchParams({
        action: 'activity',
        nik,
        activity,
        station: stationCode,
      });
      
      if (extra.code) body.append('code', extra.code);
      if (extra.new_station) body.append('new_station', extra.new_station);

      const resp = await fetch('scan.php', { method: 'POST', body });
      debugLog('Activity response status', { status: resp.status, ok: resp.ok });
      
      const text = await resp.text();
      debugLog('Activity raw response', { text, length: text.length });
      
      let data;
      try {
        data = JSON.parse(text);
        debugLog('Activity parsed response', data);
      } catch (e) {
        throw new Error(`Invalid server response: ${text.substring(0, 100)}`);
      }
      
      if (data.success) {
        Swal.fire({
          icon: 'success',
          title: 'Sukses',
          text: data.message,
          timer: 2000,
          showConfirmButton: false,
          timerProgressBar: true
        }).then(() => {
          autoReloadEnabled = true;
          location.reload();
        });
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: data.message,
          timer: 3000,
          showConfirmButton: false,
          timerProgressBar: true
        }).then(() => {
          autoReloadEnabled = true;
        });
      }
    } catch (err) {
      debugLog('Activity call error', err);
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: err.message,
        timerProgressBar: true
      }).then(() => {
        autoReloadEnabled = true;
      });
    }
  }

  // ===============================
  // UTILITY FUNCTIONS
  // ===============================
  function updateDigitalClock() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const timeString = `${hours}:${minutes}:${seconds}`;

    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    const day = days[now.getDay()];
    const date = now.getDate();
    const month = months[now.getMonth()];
    const year = now.getFullYear();
    const dateString = `${day}, ${date} ${month} ${year}`;

    document.getElementById('digital-time').textContent = timeString;
    document.getElementById('digital-date').textContent = dateString;
  }

  // ===============================
  // INITIALIZATION
  // ===============================
  
  // Update jam setiap detik
  setInterval(updateDigitalClock, 1000);
  updateDigitalClock();

  // Auto reload dengan safety checks
  setInterval(() => {
    if (autoReloadEnabled && !isModalOpen && !waitingForActivity && !waitingForCode) {
      debugLog('Auto-reloading page');
      location.reload();
    }
  }, 15000);

  // Global error handler
  window.addEventListener('error', function(e) {
    debugLog('Global error caught', {
      message: e.message,
      filename: e.filename,
      lineno: e.lineno,
      colno: e.colno
    });
  });

  // Promise rejection handler
  window.addEventListener('unhandledrejection', function(e) {
    debugLog('Unhandled promise rejection', e.reason);
  });

  debugLog('Application initialized successfully');
</script>

</html>