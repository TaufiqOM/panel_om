<?php 
session_start();
if (!isset($_SESSION['uid']) || !isset($_SESSION['username'])) {
    header("Location: /siomas-odoo/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<!--begin::Head-->

<head>
    <title>Odoo Sub Program</title>
    <meta charset="utf-8" />
    <meta name="description"
        content="Good admin dashboard live demo. Check out all the features of the admin panel. A large number of settings, additional services and widgets." />
    <meta name="keywords"
        content="Good, bootstrap, bootstrap 5, admin themes, Asp.Net Core & Django starter kits, admin themes, bootstrap admin, bootstrap dashboard, bootstrap dark mode" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="article" />
    <meta property="og:title"
        content="Good – Bootstrap 5 HTML Asp.Net Core, Blazor, Django & Flask Admin Dashboard Template by Jassa" />
    <meta property="og:url"
        content="https://themes.getbootstrap.com/product/good-bootstrap-5-admin-dashboard-template" />
    <meta property="og:site_name" content="Good by jassa" />
    
    <link rel="shortcut icon" href="../good/assets/media/logos/favicon.ico" />

    <!--begin::Fonts(mandatory for all pages)-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700" /> <!--end::Fonts-->

    <!--begin::Vendor Stylesheets(used for this page only)-->
    <link href="../good/assets/css/datatables.bundle.css" rel="stylesheet" type="text/css" />
    <link href="../good/assets/css/vis-timeline.bundle.css" rel="stylesheet" type="text/css" />
    <!--end::Vendor Stylesheets-->

    <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
    <link href="../good/assets/css/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="../good/assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->

    <style>
        /* Preloader Styles */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #ffffff;
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .preloader-content {
            text-align: center;
        }

        .preloader-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #009ef7;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        .preloader-text {
            font-family: 'Inter', sans-serif;
            color: #5e6278;
            font-size: 16px;
            font-weight: 500;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .preloader-hidden {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
        }
    </style>

</head>
<!--end::Head-->

<!--begin::Body-->

<body id="kt_app_body" data-kt-app-layout="light-sidebar" data-kt-app-sidebar-enabled="true"
    data-kt-app-sidebar-fixed="true" data-kt-app-sidebar-push-header="true" data-kt-app-sidebar-push-toolbar="true"
    data-kt-app-sidebar-push-footer="true" class="app-default">
    
    <!--begin::Preloader-->
    <div id="preloader">
        <div class="preloader-content">
            <div class="preloader-spinner"></div>
            <div class="preloader-text">Loading...</div>
        </div>
    </div>
    <!--end::Preloader-->

    <!--begin::Theme mode setup on page load-->
    <script>
        var defaultThemeMode = "light";
        var themeMode;

        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
            } else {
                if (localStorage.getItem("data-bs-theme") !== null) {
                    themeMode = localStorage.getItem("data-bs-theme");
                } else {
                    themeMode = defaultThemeMode;
                }
            }

            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }

            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }

        // Preloader Functionality
        window.addEventListener('load', function() {
            const preloader = document.getElementById('preloader');
            
            // Add small delay for better UX
            setTimeout(function() {
                preloader.classList.add('preloader-hidden');
                
                // Remove preloader from DOM after transition
                setTimeout(function() {
                    preloader.style.display = 'none';
                }, 500);
            }, 500);
        });

        // Fallback - hide preloader if page takes too long to load
        setTimeout(function() {
            const preloader = document.getElementById('preloader');
            if (preloader && preloader.style.display !== 'none') {
                preloader.classList.add('preloader-hidden');
                setTimeout(function() {
                    preloader.style.display = 'none';
                }, 500);
            }
        }, 10000); // 10 seconds timeout
    </script>
    <!--end::Theme mode setup on page load-->

    <!--begin::App-->
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <!--begin::Page-->
        <div class="app-page  flex-column flex-column-fluid " id="kt_app_page">
            <?php include "../layouts/header.php"; ?>
            <!--begin::Wrapper-->
            <div class="app-wrapper  flex-column flex-row-fluid " id="kt_app_wrapper">
                <?php include "../layouts/sidebar.php"; ?>
                <!--begin::Main-->
                <div class="app-main flex-column flex-row-fluid " id="kt_app_main">
                    <!--begin::Content wrapper-->
                    <div class="d-flex flex-column flex-column-fluid">
                        <?php include "content.php"; ?>
                    </div>
                    <!--end::Content wrapper-->
                <?php include "../layouts/footer.php"; ?>
                </div>
                <!--end:::Main-->
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::Page-->
    </div>
    <!--end::App-->

    <div id="kt_scrolltop" class="scrolltop" data-kt-scrolltop="true">
        <i class="ki-duotone ki-arrow-up"><span class="path1"></span><span class="path2"></span></i>
    </div>
    <!--end::Scrolltop-->

    <!--begin::Javascript-->
    <script>
        var hostUrl = "../good/assets/";
    </script>
    <script src="../good/assets/js/navigations.js"></script>

    <!--begin::Global Javascript Bundle(mandatory for all pages)-->
    <script src="../good/assets/js/plugins.bundle.js"></script>
    <script src="../good/assets/js/scripts.bundle.js"></script>
    <!--end::Global Javascript Bundle-->

    <!--begin::Vendors Javascript(used for this page only)-->
    <script src="../good/assets/js/datatables.bundle.js"></script>
    <script src="../good/assets/js/vis-timeline.bundle.js"></script>
    <!--end::Vendors Javascript-->

    <!--begin::Custom Javascript(used for this page only)-->
    <script src="../good/assets/js/widgets.bundle.js"></script>
    <script src="../good/assets/js/widgets.js"></script>
    <script src="../good/assets/js/chat.js"></script>
    <script src="../good/assets/js/upgrade-plan.js"></script>
    <script src="../good/assets/js/type.js"></script>
    <script src="../good/assets/js/budget.js"></script>
    <script src="../good/assets/js/settings.js"></script>
    <script src="../good/assets/js/team.js"></script>
    <script src="../good/assets/js/targets.js"></script>
    <script src="../good/assets/js/files.js"></script>
    <script src="../good/assets/js/complete.js"></script>
    <script src="../good/assets/js/main.js"></script>
    <script src="../good/assets/js/create-campaign.js"></script>
    <script src="../good/assets/js/users-search.js"></script>
    <!--end::Custom Javascript-->
    <!--end::Javascript-->
</body>
<!--end::Body-->

</html>