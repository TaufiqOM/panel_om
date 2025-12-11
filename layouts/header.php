<!--begin::Header-->
<div id="kt_app_header" class="app-header ">
  <!--begin::Header container-->
  <div class="app-container  container-fluid d-flex align-items-stretch justify-content-between " id="kt_app_header_container">
    <!--begin::Mobile menu toggle-->
    <div class="d-flex align-items-center d-lg-none ms-n2 me-2" title="Show sidebar menu">
      <div class="btn btn-icon btn-active-color-primary w-35px h-35px" id="kt_app_sidebar_mobile_toggle">
        <i class="ki-duotone ki-abstract-14 fs-1">
          <span class="path1"></span>
          <span class="path2"></span>
        </i>
      </div>
    </div>
    <!--end::Mobile menu toggle-->
    <!--begin::Mobile logo-->
    <div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
      <a href="/good/index.html" class="d-lg-none">
        <img alt="Logo" src="../good/assets/media/logos/default.svg" class="h-25px" />
      </a>
    </div>
    <!--end::Mobile logo-->
    <!--begin::Header wrapper-->
    <div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1" id="kt_app_header_wrapper">
      <!--begin::Page title-->
      <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}" data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}" class="page-title d-flex flex-column justify-content-center flex-wrap me-3 mb-5 mb-lg-0">
        <?php
          // 1. Buat "kamus" untuk judul kustom Anda
          $module_titles = [
              'employee'      => 'Karyawan',
              'token'         => 'Token Pengambilan',
              'token-sijaka'  => 'Token Si-Jaka',
              'blanket-order' => 'Blanket Order',
              'so'            => 'Sales Order',
              'bom'           => 'BoM',
              'dashboard'     => 'Dashboard Utama',
              'po'            => 'Purchase Order',
              'stock'         => 'Manajemen Stok',
              'wood-pallet'   => 'Pallet',
              'wood-barcode'  => 'Barcode Solid Wood',
              'wood-grade'    => 'Grade Solid Wood',
              'wood-lpb'      => 'LPB Solid Wood',
              'wood-dtg'      => 'DTG Solid Wood',
              'plywood'       => 'Plywood',
              'plywood-lpb'   => 'LPB Plywood',

              'confirm-check-costing'                     => 'Pre Confirm Check Costing',
              'report-summary-open-order-progress-prod'   => 'Summary Open Order Progress (Prod)',
              'report-summary-open-order-progress-ext'   => 'Summary Open Order Progress (Ext)',
              'report-progress-production'                => 'Progress Produksi',
          ];

          // 2. Ambil nilai dari URL. Default-nya 'dashboard' jika tidak ada parameter
          $current_module = $_GET['module'] ?? 'dashboard';

          // 3. Tentukan judul berdasarkan kamus
          // Jika $current_module ada di dalam kamus, gunakan nilainya. Jika tidak, tampilkan "Halaman Tidak Ditemukan"
          $page_title = $module_titles[$current_module] ?? 'Halaman Tidak Ditemukan';
          $page_subtitle = "Sub Program " . $page_title;
        ?>

        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 flex-column justify-content-center my-0">
          <?php echo htmlspecialchars($page_title); ?>
          <span class="page-desc text-gray-500 fs-7 fw-semibold pt-1">
            <?php echo htmlspecialchars($page_subtitle); ?>
          </span>
          </h1>
      </div>
      <!--end::Page title-->
      <!--begin::Navbar-->
      <div class="app-navbar align-items-center flex-shrink-0">        
        <!--begin::Theme mode-->
        <div class="app-navbar-item ms-2 ms-lg-4">
          <!--begin::Menu toggle-->
          <a href="#" class="btn btn-custom btn-outline btn-icon btn-icon-gray-700 btn-active-icon-primary" data-kt-menu-trigger="{default:'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
            <i class="ki-duotone ki-night-day theme-light-show fs-1">
              <span class="path1"></span>
              <span class="path2"></span>
              <span class="path3"></span>
              <span class="path4"></span>
              <span class="path5"></span>
              <span class="path6"></span>
              <span class="path7"></span>
              <span class="path8"></span>
              <span class="path9"></span>
              <span class="path10"></span>
            </i>
            <i class="ki-duotone ki-moon theme-dark-show fs-1">
              <span class="path1"></span>
              <span class="path2"></span>
            </i>
          </a>
          <!--begin::Menu toggle-->
          <!--begin::Menu-->
          <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px" data-kt-menu="true" data-kt-element="theme-mode-menu">
            <!--begin::Menu item-->
            <div class="menu-item px-3 my-0">
              <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="light">
                <span class="menu-icon" data-kt-element="icon">
                  <i class="ki-duotone ki-night-day fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                    <span class="path5"></span>
                    <span class="path6"></span>
                    <span class="path7"></span>
                    <span class="path8"></span>
                    <span class="path9"></span>
                    <span class="path10"></span>
                  </i>
                </span>
                <span class="menu-title"> Light </span>
              </a>
            </div>
            <!--end::Menu item-->
            <!--begin::Menu item-->
            <div class="menu-item px-3 my-0">
              <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="dark">
                <span class="menu-icon" data-kt-element="icon">
                  <i class="ki-duotone ki-moon fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                  </i>
                </span>
                <span class="menu-title"> Dark </span>
              </a>
            </div>
            <!--end::Menu item-->
            <!--begin::Menu item-->
            <div class="menu-item px-3 my-0">
              <a href="#" class="menu-link px-3 py-2" data-kt-element="mode" data-kt-value="system">
                <span class="menu-icon" data-kt-element="icon">
                  <i class="ki-duotone ki-screen fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                  </i>
                </span>
                <span class="menu-title"> System </span>
              </a>
            </div>
            <!--end::Menu item-->
          </div>
          <!--end::Menu-->
        </div>
        <!--end::Theme mode-->
        <!--begin::Quick links-->
        <div class="app-navbar-item ms-2 ms-lg-4">
          <!--begin::Menu wrapper-->
          <a href="#" class="btn btn-icon btn-primary fw-bold" data-kt-menu-trigger="click" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end" data-kt-menu-flip="bottom">
            <span class="fs-5">3</span>
          </a>
          <!--begin::Menu-->
          <div class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-375px" data-kt-menu="true" id="kt_menu_notifications">
            <!--begin::Heading-->
            <div class="d-flex flex-column bgi-no-repeat rounded-top" style="background-image:url('../good/assets/media/misc/menu-header-bg.jpg')">
              <!--begin::Title-->
              <h3 class="text-white fw-semibold px-9 mt-10 mb-6"> Notifications <span class="fs-8 opacity-75 ps-3">24 reports</span>
              </h3>
              <!--end::Title-->
              <!--begin::Tabs-->
              <ul class="nav nav-line-tabs nav-line-tabs-2x nav-stretch fw-semibold px-9">
                <li class="nav-item">
                  <a class="nav-link text-white opacity-75 opacity-state-100 pb-4" data-bs-toggle="tab" href="#kt_topbar_notifications_1">Alerts</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link text-white opacity-75 opacity-state-100 pb-4 active" data-bs-toggle="tab" href="#kt_topbar_notifications_2">Updates</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link text-white opacity-75 opacity-state-100 pb-4" data-bs-toggle="tab" href="#kt_topbar_notifications_3">Logs</a>
                </li>
              </ul>
              <!--end::Tabs-->
            </div>
            <!--end::Heading-->
            <!--begin::Tab content-->
            <div class="tab-content">
              <!--begin::Tab panel-->
              <div class="tab-pane fade" id="kt_topbar_notifications_1" role="tabpanel">
                <!--begin::Items-->
                <div class="scroll-y mh-325px my-5 px-8">
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center">
                      <!--begin::Symbol-->
                      <div class="symbol symbol-35px me-4">
                        <span class="symbol-label bg-light-primary">
                          <i class="ki-duotone ki-abstract-28 fs-2 text-primary">
                            <span class="path1"></span>
                            <span class="path2"></span>
                          </i>
                        </span>
                      </div>
                      <!--end::Symbol-->
                      <!--begin::Title-->
                      <div class="mb-0 me-2">
                        <a href="#" class="fs-6 text-gray-800 text-hover-primary fw-bold">Project Alice</a>
                        <div class="text-gray-500 fs-7">Phase 1 development</div>
                      </div>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">1 hr</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center">
                      <!--begin::Symbol-->
                      <div class="symbol symbol-35px me-4">
                        <span class="symbol-label bg-light-danger">
                          <i class="ki-duotone ki-information fs-2 text-danger">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                          </i>
                        </span>
                      </div>
                      <!--end::Symbol-->
                      <!--begin::Title-->
                      <div class="mb-0 me-2">
                        <a href="#" class="fs-6 text-gray-800 text-hover-primary fw-bold">HR Confidential</a>
                        <div class="text-gray-500 fs-7">Confidential staff documents </div>
                      </div>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">2 hrs</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center">
                      <!--begin::Symbol-->
                      <div class="symbol symbol-35px me-4">
                        <span class="symbol-label bg-light-warning">
                          <i class="ki-duotone ki-briefcase fs-2 text-warning">
                            <span class="path1"></span>
                            <span class="path2"></span>
                          </i>
                        </span>
                      </div>
                      <!--end::Symbol-->
                      <!--begin::Title-->
                      <div class="mb-0 me-2">
                        <a href="#" class="fs-6 text-gray-800 text-hover-primary fw-bold">Company HR</a>
                        <div class="text-gray-500 fs-7">Corporeate staff profiles </div>
                      </div>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">5 hrs</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center">
                      <!--begin::Symbol-->
                      <div class="symbol symbol-35px me-4">
                        <span class="symbol-label bg-light-success">
                          <i class="ki-duotone ki-abstract-12 fs-2 text-success">
                            <span class="path1"></span>
                            <span class="path2"></span>
                          </i>
                        </span>
                      </div>
                      <!--end::Symbol-->
                      <!--begin::Title-->
                      <div class="mb-0 me-2">
                        <a href="#" class="fs-6 text-gray-800 text-hover-primary fw-bold">Project Redux</a>
                        <div class="text-gray-500 fs-7">New frontend admin theme </div>
                      </div>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">2 days</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center">
                      <!--begin::Symbol-->
                      <div class="symbol symbol-35px me-4">
                        <span class="symbol-label bg-light-primary">
                          <i class="ki-duotone ki-colors-square fs-2 text-primary">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                          </i>
                        </span>
                      </div>
                      <!--end::Symbol-->
                      <!--begin::Title-->
                      <div class="mb-0 me-2">
                        <a href="#" class="fs-6 text-gray-800 text-hover-primary fw-bold">Project Breafing</a>
                        <div class="text-gray-500 fs-7">Product launch status update </div>
                      </div>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">21 Jan</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center">
                      <!--begin::Symbol-->
                      <div class="symbol symbol-35px me-4">
                        <span class="symbol-label bg-light-info">
                          <i class="ki-duotone ki-picture
 fs-2 text-info"></i>
                        </span>
                      </div>
                      <!--end::Symbol-->
                      <!--begin::Title-->
                      <div class="mb-0 me-2">
                        <a href="#" class="fs-6 text-gray-800 text-hover-primary fw-bold">Banner Assets</a>
                        <div class="text-gray-500 fs-7">Collection of banner images </div>
                      </div>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">21 Jan</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center">
                      <!--begin::Symbol-->
                      <div class="symbol symbol-35px me-4">
                        <span class="symbol-label bg-light-warning">
                          <i class="ki-duotone ki-color-swatch fs-2 text-warning">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                            <span class="path6"></span>
                            <span class="path7"></span>
                            <span class="path8"></span>
                            <span class="path9"></span>
                            <span class="path10"></span>
                            <span class="path11"></span>
                            <span class="path12"></span>
                            <span class="path13"></span>
                            <span class="path14"></span>
                            <span class="path15"></span>
                            <span class="path16"></span>
                            <span class="path17"></span>
                            <span class="path18"></span>
                            <span class="path19"></span>
                            <span class="path20"></span>
                            <span class="path21"></span>
                          </i>
                        </span>
                      </div>
                      <!--end::Symbol-->
                      <!--begin::Title-->
                      <div class="mb-0 me-2">
                        <a href="#" class="fs-6 text-gray-800 text-hover-primary fw-bold">Icon Assets</a>
                        <div class="text-gray-500 fs-7">Collection of SVG icons </div>
                      </div>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">20 March</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                </div>
                <!--end::Items-->
                <!--begin::View more-->
                <div class="py-3 text-center border-top">
                  <a href="/good/pages/user-profile/activity.html" class="btn btn-color-gray-600 btn-active-color-primary"> View All <i class="ki-duotone ki-arrow-right fs-5">
                      <span class="path1"></span>
                      <span class="path2"></span>
                    </i>
                  </a>
                </div>
                <!--end::View more-->
              </div>
              <!--end::Tab panel-->
              <!--begin::Tab panel-->
              <div class="tab-pane fade show active" id="kt_topbar_notifications_2" role="tabpanel">
                <!--begin::Wrapper-->
                <div class="d-flex flex-column px-9">
                  <!--begin::Section-->
                  <div class="pt-10 pb-0">
                    <!--begin::Title-->
                    <h3 class="text-gray-900 text-center fw-bold"> Get Pro Access </h3>
                    <!--end::Title-->
                    <!--begin::Text-->
                    <div class="text-center text-gray-600 fw-semibold pt-1"> Outlines keep you honest. They stoping you from amazing poorly about drive </div>
                    <!--end::Text-->
                    <!--begin::Action-->
                    <div class="text-center mt-5 mb-9">
                      <a href="#" class="btn btn-sm btn-primary px-6" data-bs-toggle="modal" data-bs-target="#kt_modal_upgrade_plan">Upgrade</a>
                    </div>
                    <!--end::Action-->
                  </div>
                  <!--end::Section-->
                  <!--begin::Illustration-->
                  <div class="text-center px-4">
                    <img class="mw-100 mh-200px" alt="image" src="../good/assets/media/illustrations/sketchy-1/1.png" />
                  </div>
                  <!--end::Illustration-->
                </div>
                <!--end::Wrapper-->
              </div>
              <!--end::Tab panel-->
              <!--begin::Tab panel-->
              <div class="tab-pane fade" id="kt_topbar_notifications_3" role="tabpanel">
                <!--begin::Items-->
                <div class="scroll-y mh-325px my-5 px-8">
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-success me-4">200 OK</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">New order</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">Just now</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-danger me-4">500 ERR</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">New customer</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">2 hrs</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-success me-4">200 OK</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">Payment process</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">5 hrs</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-warning me-4">300 WRN</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">Search query</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">2 days</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-success me-4">200 OK</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">API connection</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">1 week</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-success me-4">200 OK</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">Database restore</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">Mar 5</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-warning me-4">300 WRN</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">System update</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">May 15</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-warning me-4">300 WRN</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">Server OS update</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">Apr 3</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-warning me-4">300 WRN</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">API rollback</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">Jun 30</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-danger me-4">500 ERR</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">Refund process</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">Jul 10</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-danger me-4">500 ERR</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">Withdrawal process</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">Sep 10</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                  <!--begin::Item-->
                  <div class="d-flex flex-stack py-4">
                    <!--begin::Section-->
                    <div class="d-flex align-items-center me-2">
                      <!--begin::Code-->
                      <span class="w-70px badge badge-light-danger me-4">500 ERR</span>
                      <!--end::Code-->
                      <!--begin::Title-->
                      <a href="#" class="text-gray-800 text-hover-primary fw-semibold">Mail tasks</a>
                      <!--end::Title-->
                    </div>
                    <!--end::Section-->
                    <!--begin::Label-->
                    <span class="badge badge-light fs-8">Dec 10</span>
                    <!--end::Label-->
                  </div>
                  <!--end::Item-->
                </div>
                <!--end::Items-->
                <!--begin::View more-->
                <div class="py-3 text-center border-top">
                  <a href="/good/pages/user-profile/activity.html" class="btn btn-color-gray-600 btn-active-color-primary"> View All <i class="ki-duotone ki-arrow-right fs-5">
                      <span class="path1"></span>
                      <span class="path2"></span>
                    </i>
                  </a>
                </div>
                <!--end::View more-->
              </div>
              <!--end::Tab panel-->
            </div>
            <!--end::Tab content-->
          </div>
          <!--end::Menu-->
          <!--end::Menu wrapper-->
        </div>
        <!--end::Quick links-->
      </div>
      <!--end::Navbar-->
    </div>
    <!--end::Header wrapper-->
  </div>
  <!--end::Header container-->
</div>
<!--end::Header-->