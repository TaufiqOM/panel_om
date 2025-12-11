
<!--begin::Sidebar-->
<div id="kt_app_sidebar" class="app-sidebar  flex-column " data-kt-drawer="true" data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="250px" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">
  <!--begin::Logo-->
  <div class="app-sidebar-logo d-none d-lg-flex flex-stack flex-shrink-0 px-8" id="kt_app_sidebar_logo">
    <!--begin::Logo image-->
    <a href="?module=dashboard">
      <img alt="Logo" src="../good/assets/media/logos/logo-black.png" class="theme-light-show h-75px" />
      <img alt="Logo" src="../good/assets/media/logos/logo-white.png" class="theme-dark-show h-40px" />
    </a>

    <!--end::Logo image-->
  </div>
  <!--end::Logo-->
  <div class="separator d-none d-lg-block"></div>
  <div class="separator"></div>
  <!--begin::Sidebar menu-->
  <div class="app-sidebar-menu  hover-scroll-y my-5 my-lg-5 mx-3" id="kt_app_sidebar_menu_wrapper" data-kt-scroll="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_app_sidebar_toolbar, #kt_app_sidebar_footer" data-kt-scroll-offset="0">
    <!--begin::Menu-->
    <div class="
            menu 
            menu-column 
            menu-sub-indention 
            menu-active-bg 
            fw-semibold" id="#kt_sidebar_menu" data-kt-menu="true">
      <!--begin:Menu item-->
      <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link" href="?module=dashboard">
          <span class="menu-icon">
            <i class="ki-duotone ki-chart-pie-3 fs-2">
              <span class="path1"></span>
              <span class="path2"></span>
              <span class="path3"></span>
            </i>
          </span>
          <span class="menu-title">Dashboard</span>
        </a>
        <!--end:Menu link-->
      </div>
      <?php if (isset($_SESSION['uid']) && $_SESSION['uid'] != 1) { ?>
        <div class="menu-item pt-5">
          <div class="menu-content">
            <span class="menu-heading fw-bold text-uppercase fs-7">Master Data</span>
          </div>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=employee">
            <span class="menu-icon">
              <i class="ki-duotone ki-badge fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
              </i>
            </span>
            <span class="menu-title">Karyawan</span>
          </a>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=supplier">
            <span class="menu-icon">
              <i class="ki-duotone ki-shop fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
              </i>
            </span>
            <span class="menu-title">Supplier</span>
          </a>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=hardware">
            <span class="menu-icon">
              <i class="ki-duotone ki-dropbox fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
              </i>
            </span>
            <span class="menu-title">Hardware</span>
          </a>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=so">
            <span class="menu-icon">
              <i class="ki-duotone ki-calendar-8 fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
                <span class="path6"></span>
              </i>
            </span>
            <span class="menu-title">Sales Order</span>
          </a>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=shipping">
            <span class="menu-icon">
              <i class="ki-duotone ki-truck fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
              </i>
            </span>
            <span class="menu-title">Shipping</span>
          </a>
        </div>

        <div class="menu-item pt-5">
          <div class="menu-content">
            <span class="menu-heading fw-bold text-uppercase fs-7">PPIC</span>
          </div>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=token">
            <span class="menu-icon">
              <i class="ki-duotone ki-faceid fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
                <span class="path6"></span>
              </i>
            </span>
            <span class="menu-title">Token Pengambilan</span>
          </a>
        </div>
         <div class="menu-item">
          <a class="menu-link" href="?module=token-sijaka">
            <span class="menu-icon">
              <i class="ki-duotone ki-faceid fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
                <span class="path6"></span>
              </i>
            </span>
            <span class="menu-title">Token Si-Jaka</span>
          </a>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=bom">
            <span class="menu-icon">
              <i class="ki-duotone ki-text-number fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
                <span class="path6"></span>
              </i>
            </span>
            <span class="menu-title">BOM</span>
          </a>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=costing">
            <span class="menu-icon">
              <i class="ki-duotone ki-dropbox fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
              </i>
            </span>
            <span class="menu-title">Costing</span>
          </a>
        </div>

        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
          <span class="menu-link">
            <span class="menu-icon">
              <i class="ki-duotone ki-cube-2 fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
              </i>
            </span>
            <span class="menu-title">Manufacturing</span>
            <span class="menu-arrow"></span>
          </span>
          <div class="menu-sub menu-sub-accordion">
            <div class="menu-item">
              <a class="menu-link" href="?module=manufacturing">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Manufacturing</span>
              </a>
            </div>
            <div class="menu-item">
              <a class="menu-link" href="?module=mo/barcode-product">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Barcode Produk</span>
              </a>
            </div>
            <div class="menu-item">
              <a class="menu-link" href="?module=qc-pass">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">QC Pass</span>
              </a>
            </div>
            <div class="menu-item">
              <a class="menu-link" href="?module=mo-periodik-information">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Periodic Information</span>
              </a>
            </div>
            <div class="menu-item">
              <a class="menu-link" href="?module=mo-produce">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">WIP to Finish Good</span>
              </a>
            </div>
          </div>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=sijaka">
            <span class="menu-icon">
              <i class="ki-duotone ki-fingerprint-scanning fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
              </i>
            </span>
            <span class="menu-title">Si-Jaka</span>
          </a>
        </div>

        <div class="menu-item pt-5">
          <div class="menu-content">
            <span class="menu-heading fw-bold text-uppercase fs-7">Sales</span>
          </div>
        </div>
        <div class="menu-item">
          <a class="menu-link" href="?module=confirm-check-costing">
            <span class="menu-icon">
              <i class="ki-duotone ki-dropbox fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
              </i>
            </span>
            <span class="menu-title">Pre Confirm Check Costing</span>
          </a>
        </div>

        <div class="menu-item pt-5">
          <div class="menu-content">
            <span class="menu-heading fw-bold text-uppercase fs-7">Wood Panel</span>
          </div>
        </div>

        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
          <span class="menu-link">
            <span class="menu-icon">
              <i class="ki-duotone ki-barcode fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
                <span class="path4"></span>
                <span class="path5"></span>
                <span class="path6"></span>
                <span class="path7"></span>
                <span class="path8"></span>
              </i>
            </span>
            <span class="menu-title">Barcode</span>
            <span class="menu-arrow"></span>
          </span>
          <div class="menu-sub menu-sub-accordion">
            <div class="menu-item">
              <a class="menu-link" href="?module=wood-pallet">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Pallet</span>
              </a>
            </div>
            <div class="menu-item">
              <a class="menu-link" href="?module=wood-barcode">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Solid Wood</span>
              </a>
            </div>
          </div>
        </div>

        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
          <span class="menu-link">
            <span class="menu-icon">
              <i class="ki-duotone ki-tree fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
              </i>
            </span>
            <span class="menu-title">Solid Wood</span>
            <span class="menu-arrow"></span>
          </span>
          <div class="menu-sub menu-sub-accordion">
            <div class="menu-item">
              <a class="menu-link" href="?module=wood-grade">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Grade</span>
              </a>
            </div>
            <div class="menu-item">
              <a class="menu-link" href="?module=wood-lpb">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">LPB</span>
              </a>
            </div>
            <div class="menu-item">
              <a class="menu-link" href="?module=wood-dtg">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">DTG</span>
              </a>
            </div>
          </div>
        </div>

        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
          <!--begin:Menu link-->
          <span class="menu-link">
            <span class="menu-icon">
              <i class="ki-duotone ki-abstract-26 fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
              </i>
            </span>
            <span class="menu-title">Engineered Wood</span>
            <span class="menu-arrow"></span>
          </span>
          <div class="menu-sub menu-sub-accordion">
            <div class="menu-item">
              <a class="menu-link" href="?module=plywood">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Plywood</span>
              </a>
            </div>
            <div class="menu-item">
              <a class="menu-link" href="?module=wood-lpb">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">LPB</span>
              </a>
            </div>
          </div>
          <!--end:Menu sub-->
        </div>

        <div class="menu-item pt-5">
          <div class="menu-content">
            <span class="menu-heading fw-bold text-uppercase fs-7">Admin</span>
          </div>
        </div>
        <div data-kt-menu-trigger="click" class="menu-item menu-accordion">
          <span class="menu-link">
            <span class="menu-icon">
              <i class="ki-duotone ki-user fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
              </i>
            </span>
            <span class="menu-title">User Management</span>
            <span class="menu-arrow"></span>
          </span>
          <div class="menu-sub menu-sub-accordion">
            <div class="menu-item">
              <a class="menu-link" href="?module=admin/users">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Manage Users</span>
              </a>
            </div>
            <div class="menu-item">
              <a class="menu-link" href="?module=admin/login_history">
                <span class="menu-bullet">
                  <span class="bullet bullet-dot"></span>
                </span>
                <span class="menu-title">Login History</span>
              </a>
            </div>
          </div>
        </div>
      <?php } ?>

      <div class="menu-item pt-5">
        <div class="menu-content">
          <span class="menu-heading fw-bold text-uppercase fs-7">Reporting</span>
        </div>
      </div>
      <div class="menu-item">
        <a class="menu-link" href="?module=report-summary-open-order-progress-prod">
          <span class="menu-icon">
            <i class="ki-duotone ki-note-2 fs-2">
              <span class="path1"></span>
              <span class="path2"></span>
              <span class="path3"></span>
              <span class="path4"></span>
            </i>
          </span>
          <span class="menu-title">Summary Open Order Progress (Prod)</span>
        </a>
      </div>
      <div class="menu-item">
        <a class="menu-link" href="?module=report-summary-open-order-progress-ext">
          <span class="menu-icon">
            <i class="ki-duotone ki-note-2 fs-2">
              <span class="path1"></span>
              <span class="path2"></span>
              <span class="path3"></span>
              <span class="path4"></span>
            </i>
          </span>
          <span class="menu-title">Summary Open Order Progress (Ext)</span>
        </a>
      </div>
      <div class="menu-item">
        <a class="menu-link" href="?module=report-progress-production">
          <span class="menu-icon">
            <i class="ki-duotone ki-note-2 fs-2">
              <span class="path1"></span>
              <span class="path2"></span>
              <span class="path3"></span>
              <span class="path4"></span>
            </i>
          </span>
          <span class="menu-title">Report Progress Production</span>
        </a>
      </div>

      <div class="menu-item pt-5">
        <div class="menu-content">
          <span class="menu-heading fw-bold text-uppercase fs-7">Help</span>
        </div>
      </div>
      <div class="menu-item">
        <a class="menu-link" href="/good/layout-builder.html">
          <span class="menu-icon">
            <i class="ki-duotone ki-support-24 fs-2">
              <span class="path1"></span>
              <span class="path2"></span>
              <span class="path3"></span>
            </i>
          </span>
          <span class="menu-title">Helpdesk</span>
        </a>
      </div>
      <!--end:Menu item-->
      <!--begin:Menu item-->
      <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link" href="#">
          <span class="menu-icon">
            <i class="ki-duotone ki-abstract-26 fs-2">
              <span class="path1"></span>
              <span class="path2"></span>
            </i>
          </span>
          <span class="menu-title">Documentation</span>
        </a>
        <!--end:Menu link-->
      </div>
      <!--end:Menu item-->
      <!--begin:Menu item-->
      <div class="menu-item">
        <!--begin:Menu link-->
        <a class="menu-link" href="#">
          <span class="menu-icon">
            <i class="ki-duotone ki-code fs-2">
              <span class="path1"></span>
              <span class="path2"></span>
              <span class="path3"></span>
              <span class="path4"></span>
            </i>
          </span>
          <span class="menu-title">Update System</span>
        </a>
        <!--end:Menu link-->
      </div>
      <!--end:Menu item-->
    </div>
    <!--end::Menu-->
  </div>
  <!--end::Sidebar menu-->
  <!--begin::User-->
  <div class="app-sidebar-user d-flex flex-stack py-5 px-8">
    <!--begin::User avatar-->
    <div class="d-flex me-5">
      <!--begin::Menu wrapper-->
      <div class="me-5">
        <!--begin::Symbol-->
        <div class="symbol symbol-40px cursor-pointer" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="bottom-start" data-kt-menu-overflow="true">
          <img src="../good/assets/media/avatars/300-1.jpg" alt="" />
        </div>
        <!--end::Symbol-->
        <!--begin::User account menu-->
        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
          <!--begin::Menu item-->
          <div class="menu-item px-3">
            <div class="menu-content d-flex align-items-center px-3">
              <!--begin::Avatar-->
              <div class="symbol symbol-50px me-5">
                <img alt="Logo" src="../good/assets/media/avatars/300-1.jpg" />
              </div>
              <!--end::Avatar-->
              <!--begin::Username-->
              <div class="d-flex flex-column">
                <div class="fw-bold d-flex align-items-center fs-5"> Max Smith <span class="badge badge-light-success fw-bold fs-8 px-2 py-1 ms-2">Pro</span>
                </div>
                <a href="#" class="fw-semibold text-muted text-hover-primary fs-7"> max@kt.com </a>
              </div>
              <!--end::Username-->
            </div>
          </div>
          <!--end::Menu item-->
          <!--begin::Menu separator-->
          <div class="separator my-2"></div>
          <!--end::Menu separator-->
          <!--begin::Menu item-->
          <div class="menu-item px-5">
            <a href="/good/account/overview.html" class="menu-link px-5"> My Profile </a>
          </div>
          <!--end::Menu item-->
          <!--begin::Menu item-->
          <div class="menu-item px-5">
            <a href="/good/apps/projects/list.html" class="menu-link px-5">
              <span class="menu-text">My Projects</span>
              <span class="menu-badge">
                <span class="badge badge-light-danger badge-circle fw-bold fs-7">3</span>
              </span>
            </a>
          </div>
          <!--end::Menu item-->
          <!--begin::Menu separator-->
          <div class="separator my-2"></div>
          <!--end::Menu separator-->
          <!--begin::Menu item-->
          <div class="menu-item px-5 my-1">
            <a href="/good/account/settings.html" class="menu-link px-5"> Account Settings </a>
          </div>
          <!--end::Menu item-->
          <!--begin::Menu item-->
          <div class="menu-item px-5">
            <a href="/good/authentication/sign-in/basic.html" class="menu-link px-5"> Sign Out </a>
          </div>
          <!--end::Menu item-->
        </div>
        <!--end::User account menu-->
      </div>
      <!--end::Menu wrapper-->
      <!--begin::Info-->
      <div class="me-2">
        <!--begin::Username-->
        <a href="#" class="app-sidebar-username text-gray-800 text-hover-primary fs-6 fw-semibold lh-0">Paul Melone</a>
        <!--end::Username-->
        <!--begin::Description-->
        <span class="app-sidebar-deckription text-gray-500 fw-semibold d-block fs-8">Python Dev</span>
        <!--end::Description-->
      </div>
      <!--end::Info-->
    </div>
    <!--end::User avatar-->
    <!--begin::Action-->
    <button onclick="logout()" class="btn btn-icon btn-active-color-primary btn-icon-custom-color me-n4" data-bs-toggle="tooltip" title="End session and logout">
      <i class="ki-duotone ki-entrance-left fs-2 text-gray-500">
        <span class="path1"></span>
        <span class="path2"></span>
      </i>
    </button>
    <!--end::Action-->
  </div>
  <!--end::User-->
</div>
<!--end::Sidebar-->
<script>
  function logout() {
    fetch('../panel/logout.php', {
        method: 'POST'
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          window.location.href = '/';
        } else {
          alert('Logout failed');
        }
      })
      .catch(err => {
        console.error('Logout error:', err);
        alert('Error during logout');
      });
  }
</script>