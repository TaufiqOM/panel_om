<?php
session_start();
require_once 'session_manager.php';

// Cek jika sudah login, redirect ke dashboard
$sessionCheck = SessionManager::checkEmployeeSession();
if ($sessionCheck['logged_in'] && SessionManager::checkSessionTimeout()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Wood Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary-color: #8B4513;
            --secondary-color: #D2691E;
            --accent-color: #A0522D;
        }
        
        body {
            background: linear-gradient(135deg, #f5f5dc 0%, #deb887 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(139, 69, 19, 0.2);
            border: none;
            overflow: hidden;
            max-width: 450px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(210, 105, 30, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.3);
        }
        
        .wood-pattern {
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%238B4513" opacity="0.1"/></svg>');
        }
    </style>
</head>
<body class="wood-pattern">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-tree-fill login-icon"></i>
                <h2>PT Omega Mas</h2>
                <p class="mb-0">Portal Sistem Karyawan</p>
            </div>
            <div class="login-body">
                <form id="loginForm">
                    <div class="mb-4">
                        <label for="employeeToken" class="form-label fw-bold">
                            <i class="bi bi-key me-2"></i>Token Login
                        </label>
                        <input type="text" class="form-control" id="employeeToken" 
                               placeholder="Masukkan Token Login" autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="employeeBarcode" class="form-label fw-bold">
                            <i class="bi bi-upc-scan me-2"></i>Scan Barcode Karyawan
                        </label>
                        <input type="text" class="form-control" id="employeeBarcode" 
                               placeholder="Gunakan Barcode ID Karyawan" autofocus>
                    </div>

                    <div class="alert alert-danger d-none" id="loginAlert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <span id="loginAlertMessage"></span>
                    </div>
                    
                    <button type="submit" class="btn btn-login w-100" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const employeeToken = document.getElementById('employeeToken'); // TAMBAHKAN INI
            const employeeBarcode = document.getElementById('employeeBarcode');
            const loginAlert = document.getElementById('loginAlert');
            const loginAlertMessage = document.getElementById('loginAlertMessage');
            const loginBtn = document.getElementById('loginBtn');

            function showLoginAlert(message, fieldElement = null) { // Modifikasi
                loginAlertMessage.textContent = message;
                loginAlert.classList.remove('d-none');
                // Beri highlight pada field yang error jika ditentukan
                if (fieldElement) {
                    fieldElement.classList.add('is-invalid');
                } else {
                    // Jika tidak ada, highlight keduanya sebagai default error
                    employeeToken.classList.add('is-invalid');
                    employeeBarcode.classList.add('is-invalid');
                }
            }

            function hideLoginAlert() {
                loginAlert.classList.add('d-none');
                employeeToken.classList.remove('is-invalid'); // Modifikasi
                employeeBarcode.classList.remove('is-invalid');
            }

            async function validateLogin(token, barcode) { // Modifikasi nama fungsi & parameter
                try {
                    const response = await fetch('validate_employee.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        // Kirim kedua data
                        body: 'token=' + encodeURIComponent(token) + '&barcode=' + encodeURIComponent(barcode)
                    });
                    return await response.json();
                } catch (error) {
                    console.error('Error:', error);
                    return null;
                }
            }

            loginForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const token = employeeToken.value.trim(); // Ambil nilai token
                const barcode = employeeBarcode.value.trim();

                if (!token) { // Validasi token
                    showLoginAlert('Token login tidak boleh kosong', employeeToken);
                    return;
                }

                if (!barcode) {
                    showLoginAlert('Scan barcode karyawan terlebih dahulu', employeeBarcode);
                    return;
                }

                hideLoginAlert(); // Sembunyikan alert lama
                loginBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Memvalidasi...';
                loginBtn.disabled = true;

                const result = await validateLogin(token, barcode); // Kirim kedua parameter

                if (result && result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Berhasil!',
                        text: `Selamat datang, ${result.employee.name}!`,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'dashboard.php';
                    });
                } else {
                    // Tampilkan error, tapi jangan highlight field spesifik
                    showLoginAlert(result ? result.message : 'Terjadi kesalahan saat login');
                    loginBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Login';
                    loginBtn.disabled = false;
                }
            });

            // Dengarkan input di kedua field
            employeeToken.addEventListener('input', hideLoginAlert);
            employeeBarcode.addEventListener('input', hideLoginAlert);
        });
    </script>
</body>
</html>

