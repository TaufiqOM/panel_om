<?php
session_start();
require_once 'session_manager.php';

// Cek session
$sessionCheck = SessionManager::checkEmployeeSession();
if (!$sessionCheck['logged_in'] || !SessionManager::checkSessionTimeout()) {
    header('Location: index.php');
    exit;
}

$employee = $sessionCheck['employee'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Wood Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #8B4513;
            --secondary-color: #D2691E;
            --accent-color: #A0522D;
            --light-wood: #F5DEB3;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            box-shadow: 0 2px 15px rgba(139, 69, 19, 0.4);
            padding: 1rem 0;
        }
        
        .main-content {
            padding: 30px 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--light-wood) 0%, #e6d2b5 100%);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(139, 69, 19, 0.1);
            border-left: 5px solid var(--secondary-color);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: none;
            transition: all 0.3s ease;
            height: 100%;
            overflow: hidden;
            position: relative;
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .dashboard-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .card-body {
            padding: 30px;
            position: relative;
            z-index: 1;
        }
        
        .card-icon {
            font-size: 3.5rem;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        
        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #2c1810;
        }
        
        .card-text {
            color: #6c757d;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .btn-access {
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-access:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .user-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 10px;
            margin-right: 15px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .welcome-section {
                padding: 30px 20px;
                text-align: center;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-tree-fill me-2"></i>
                <strong>PT Omega Mas</strong>
            </a>
            
            <div class="d-flex align-items-center">
                <div class="user-info">
                    <div class="fw-bold"><?php echo htmlspecialchars($employee['name']); ?></div>
                    <small>No Karyawan: <?php echo htmlspecialchars($employee['barcode']); ?></small>
                </div>
                <?php if ($employee['image']): ?>
                    <img src="<?php echo $employee['image']; ?>" class="user-avatar" alt="Avatar">
                <?php else: ?>
                    <div class="user-avatar bg-light d-flex align-items-center justify-content-center">
                        <i class="bi bi-person text-dark"></i>
                    </div>
                <?php endif; ?>
                <button class="btn btn-outline-light btn-sm ms-3" id="logoutBtn">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold text-dark mb-3">Selamat Datang, <?php echo htmlspecialchars($employee['name']); ?>! 👋</h2>
                    <p class="lead text-dark mb-0">Sistem Pengambilan Bahan - Kelola material dengan efisien dan optimal</p>
                </div>
                <div class="col-lg-4 text-lg-end text-center mt-3 mt-lg-0">
                    <i class="bi bi-tree-fill" style="font-size: 5rem; color: var(--primary-color); opacity: 0.8;"></i>
                </div>
            </div>
        </div>

        <!-- Menu Grid -->
        <div class="menu-grid">
            <!-- PBP Hardware -->
            <div class="dashboard-card card-pbp-hardware text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-9">
                            <h3 class="card-title fw-bold">PBP Hardware</h3>
                            <p class="card-text">PBP Material Hardware</p>
                            <a href="../hardware-pbp/" class="btn btn-light btn-access">
                                <i class="bi bi-arrow-right-circle me-2"></i>Akses Menu
                            </a>
                        </div>
                        <div class="col-3 text-end">
                            <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="bi bi-tools" style="font-size: 2.5rem; color: #007bff;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            
            logoutBtn.addEventListener('click', function() {
                Swal.fire({
                    title: 'Konfirmasi Logout',
                    text: 'Apakah Anda yakin ingin logout dari sistem?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Logout',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    backdrop: true,
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'Logging out...',
                            text: 'Sedang memproses logout',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        fetch('logout.php')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Logout Berhasil',
                                        text: 'Anda telah logout dari sistem',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.href = 'index.php';
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                window.location.href = 'index.php';
                            });
                    }
                });
            });
            
            // Add animation to cards on load
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>