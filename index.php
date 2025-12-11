<?php
session_start();
require_once __DIR__ . '/inc/csrf.php';
$csrf_token = CSRF::generateToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIOMAS - ODOO</title>
    <link rel="shortcut icon" href="/siomas-odoo/good/assets/media/logos/favicon.ico" />

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow: hidden;
            background: #000;
            position: relative;
        }

        /* Canvas untuk background bintang */
        #stars-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        /* Container utama */
        .login-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 1100px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        .row {
            margin: 0;
        }

        /* Kolom Kiri - Animasi */
        .left-section {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .left-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: grid-move 20s linear infinite;
        }

        @keyframes grid-move {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .logo-container {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .logo-title {
            color: #fff;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 0 20px rgba(255,255,255,0.3);
        }

        .logo-subtitle {
            color: rgba(255,255,255,0.7);
            font-size: 0.95rem;
            margin-bottom: 40px;
        }

        /* Animasi Karakter */
        .character-animation {
            position: relative;
            width: 250px;
            height: 250px;
            margin: 30px 0;
        }

        .character-body {
            position: absolute;
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #fff 0%, #e0e0e0 100%);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            border: 3px solid rgba(255,255,255,0.2);
        }

        .character-face {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
        }

        .eye-container {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
        }

        .eye {
            position: absolute;
            width: 30px;
            height: 30px;
            background: #000;
            border-radius: 50%;
            top: 45%;
            transition: all 0.1s ease;
        }

        .eye.left {
            left: 35%;
        }

        .eye.right {
            right: 35%;
        }

        .pupil {
            position: absolute;
            width: 12px;
            height: 12px;
            background: #fff;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.1s ease;
        }

        .smile {
            position: absolute;
            width: 60px;
            height: 30px;
            border: 3px solid #000;
            border-top: none;
            border-radius: 0 0 60px 60px;
            bottom: 30%;
            left: 50%;
            transform: translateX(-50%);
        }

        /* Kolom Kanan - Form Login */
        .right-section {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            padding: 60px 50px;
        }

        .form-header {
            margin-bottom: 40px;
        }

        .form-header h3 {
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-header p {
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
        }

        .form-label {
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .input-group {
            margin-bottom: 25px;
        }

        .input-group-text {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
            border-radius: 10px 0 0 10px;
            padding: 12px 15px;
        }

        .form-control {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-left: none;
            border-radius: 0 10px 10px 0;
            padding: 12px 20px;
            color: #fff;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.3);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.05);
            color: #fff;
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.3);
        }

        .invalid-feedback {
            color: #ff6b6b;
            font-size: 0.85rem;
        }

        .btn-login {
            background: linear-gradient(135deg, #fff 0%, #e0e0e0 100%);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-weight: 600;
            color: #000;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
            box-shadow: 0 5px 20px rgba(255,255,255,0.2);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255,255,255,0.3);
            background: linear-gradient(135deg, #f0f0f0 0%, #d0d0d0 100%);
        }

        .additional-links {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.6);
        }

        .additional-links a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .additional-links a:hover {
            text-decoration: underline;
        }

        .copyright {
            position: fixed;
            bottom: 20px;
            width: 100%;
            text-align: center;
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.85rem;
            z-index: 100;
        }

        /* Alert styling */
        .alert-danger {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
            border-radius: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .left-section {
                display: none;
            }
            
            .right-section {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Canvas untuk bintang -->
    <canvas id="stars-canvas"></canvas>

    <!-- Login Wrapper -->
    <div class="login-wrapper">
        <div class="login-container animate__animated animate__fadeIn">
            <div class="row g-0">
                <!-- Kolom Kiri - Animasi -->
                <div class="col-lg-5 left-section">
                    <div class="logo-container">
                        <h1 class="logo-title"><i class="fas fa-shield-alt"></i> SIOMAS V2</h1>
                        <p class="logo-subtitle">Sistem Informasi Manajemen Omega Mas</p>
                    </div>
                    
                    <div class="character-animation">
                        <div class="character-body">
                            <div class="character-face">
                                <div class="eye-container">
                                    <div class="eye left">
                                        <div class="pupil"></div>
                                    </div>
                                    <div class="eye right">
                                        <div class="pupil"></div>
                                    </div>
                                </div>
                                <div class="smile"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan - Form Login -->
                <div class="col-lg-7 right-section">
                    <div class="form-header">
                        <h3>Welcome Back</h3>
                        <p>Please login to your account</p>
                    </div>

                    <form id="loginForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>" />
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Enter your email" required />
                            </div>
                            <div class="invalid-feedback">Please enter your email.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required />
                            </div>
                            <div class="invalid-feedback">Please enter your password.</div>
                        </div>

                        <div id="loginError" class="alert alert-danger d-none"></div>

                        <button type="submit" class="btn btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
                        </button>
                    </form>

                    <div class="additional-links">
                        Don't have an account? <a href="#">Contact Administrator</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="copyright">
        &copy; 2025 SIOMAS - ODOO. All rights reserved.
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ===== BACKGROUND BINTANG =====
        const canvas = document.getElementById('stars-canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const stars = [];
        const numStars = 200;

        class Star {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.size = Math.random() * 2;
                this.speed = Math.random() * 0.5 + 0.1;
                this.opacity = Math.random();
                this.fadeDirection = Math.random() > 0.5 ? 1 : -1;
            }

            draw() {
                ctx.fillStyle = `rgba(255, 255, 255, ${this.opacity})`;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
            }

            update() {
                this.opacity += this.fadeDirection * 0.01;
                if (this.opacity <= 0 || this.opacity >= 1) {
                    this.fadeDirection *= -1;
                }
                this.y += this.speed;
                if (this.y > canvas.height) {
                    this.y = 0;
                    this.x = Math.random() * canvas.width;
                }
            }
        }

        for (let i = 0; i < numStars; i++) {
            stars.push(new Star());
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            stars.forEach(star => {
                star.update();
                star.draw();
            });
            requestAnimationFrame(animate);
        }

        animate();

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });

        // ===== ANIMASI MATA MENGIKUTI KURSOR =====
        const leftEye = document.querySelector('.eye.left .pupil');
        const rightEye = document.querySelector('.eye.right .pupil');
        const characterBody = document.querySelector('.character-body');

        document.addEventListener('mousemove', (e) => {
            if (window.innerWidth > 768) {
                const rect = characterBody.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;

                const angle = Math.atan2(e.clientY - centerY, e.clientX - centerX);
                const distance = Math.min(8, Math.sqrt(Math.pow(e.clientX - centerX, 2) + Math.pow(e.clientY - centerY, 2)) / 30);

                const pupilX = Math.cos(angle) * distance;
                const pupilY = Math.sin(angle) * distance;

                leftEye.style.transform = `translate(calc(-50% + ${pupilX}px), calc(-50% + ${pupilY}px))`;
                rightEye.style.transform = `translate(calc(-50% + ${pupilX}px), calc(-50% + ${pupilY}px))`;
            }
        });

        // ===== FORM LOGIN LOGIC (tidak diubah) =====
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('loginForm');

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return;
                }

                const formData = new FormData(form);

                fetch('save_login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => {
                    if (!res.ok) throw new Error("HTTP status " + res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        document.querySelector('.login-container').classList.add('animate__zoomOut');
                        setTimeout(() => {
                            window.location.href = "/panel_om/modul/?module=dashboard";
                        }, 500);
                    } else {
                        let errBox = document.getElementById('loginError');
                        errBox.innerHTML = "Login gagal!<br>Email atau password salah.";
                        errBox.classList.remove("d-none");

                        form.classList.remove('was-validated');
                        form.classList.add('animate__animated', 'animate__headShake');
                        setTimeout(() => {
                            form.classList.remove('animate__animated', 'animate__headShake');
                        }, 1000);

                        form.password.value = "";
                    }
                })
                .catch(err => {
                    console.error("Fetch error:", err);
                    alert("Terjadi error koneksi ke server.");
                });
            }, false);
        });
    </script>
</body>
</html>