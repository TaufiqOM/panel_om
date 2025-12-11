<div class="row g-5 g-xxl-10">
    <!--begin::Col-->
    <div class="col-xl-12 mb-5 mb-xxl-10">

        <!--begin::Table Widget 3-->
        <div class="card card-flush h-xl-100">
            <!--begin::Card header-->
            <div class="card-header py-7">
                <!--begin::Tabs-->
                <div class="card-title pt-3 mb-0 gap-4 gap-lg-10 gap-xl-15 nav nav-tabs border-bottom-0"
                    data-kt-table-widget-3="tabs_nav">
                    <!--begin::Tab item-->
                    <div class="fs-4 fw-bold pb-3 border-bottom border-3 border-primary cursor-pointer"
                        data-kt-table-widget-3="tab"
                        data-kt-table-widget-3-value="Show All">
                        All BOM
                    </div>
                </div>
                <!--end::Tabs-->

                <!--begin::Filter button-->
                <div class="card-toolbar">
                    <!--begin::Filter & Search-->
                    <div class="card-toolbar d-flex align-items-center gap-3">

                        <!--begin::Search-->
                        <div class="position-relative">
                            <span class="svg-icon svg-icon-2 svg-icon-gray-500 position-absolute top-50 translate-middle-y ms-3">
                                <!--begin::Icon (search)-->
                                <i class="ki-duotone ki-magnifier fs-2 fs-lg-3 text-gray-800 position-absolute top-50 translate-middle-y me-5"><span class="path1"></span><span class="path2"></span></i>
                                <!--end::Icon-->
                            </span>
                            <input type="text" id="tableSearch" class="form-control form-control-sm form-control-solid ps-9 rounded-pill" placeholder="Search BOM..." />
                        </div>
                        <!--end::Search-->
                    </div>
                    <!--end::Filter & Search-->


                    <!--begin::Filter button-->
                    <a href="#" class="text-hover-primary ps-4"
                        data-kt-menu-trigger="click"
                        data-kt-menu-placement="bottom-end">
                        <i class="ki-duotone ki-filter fs-2 text-gray-500"><span
                                class="path1"></span><span class="path2"></span></i>
                    </a>
                    <!--begin::Menu 1-->
                    <div class="menu menu-sub menu-sub-dropdown w-250px w-md-300px"
                        data-kt-menu="true" id="kt_menu_675dc1e117e49">
                        <!--begin::Header-->
                        <div class="px-7 py-5">
                            <div class="fs-5 text-gray-900 fw-bold">Filter Options
                            </div>
                        </div>
                        <!--end::Header-->

                        <!--begin::Form-->
                        <div class="px-7 py-5">
                            <!--begin::Input group-->
                            <div class="mb-10">
                                <!--begin::Label-->
                                <label
                                    class="form-label fw-semibold">Status:</label>
                                <!--end::Label-->

                                <!--begin::Input-->
                                <div>
                                    <select class="form-select form-select-solid"
                                        multiple data-kt-select2="true"
                                        data-close-on-select="false"
                                        data-placeholder="Select option"
                                        data-dropdown-parent="#kt_menu_675dc1e117e49"
                                        data-allow-clear="true">
                                        <option></option>
                                        <option value="1">Approved</option>
                                        <option value="2">Pending</option>
                                        <option value="2">In Process</option>
                                        <option value="2">Rejected</option>
                                    </select>
                                </div>
                                <!--end::Input-->
                            </div>
                            <!--end::Input group-->

                            <!--begin::Actions-->
                            <div class="d-flex justify-content-end">
                                <button type="reset"
                                    class="btn btn-sm btn-light btn-active-light-primary me-2"
                                    data-kt-menu-dismiss="true">Reset</button>

                                <button type="submit" class="btn btn-sm btn-primary"
                                    data-kt-menu-dismiss="true">Apply</button>
                            </div>
                            <!--end::Actions-->
                        </div>
                        <!--end::Form-->
                    </div>
                    <!--end::Menu 1--> <!--end::Filter button-->
                </div>
                <!--end::Filter button-->
            </div>
            <!--end::Card header-->

            <!--begin::Card body-->
            <div class="card-body pt-1">

                <!--begin::Table-->
                <table id="bomTable"
                    class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all">
                    <thead class="">
                        <tr>
                            <th>Product Template</th>
                            <th>BOM Code</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                    </tbody>
                    <!--end::Table-->
                </table>
                <!--end::Table-->
                <!-- begin:: modal bom detail -->
                <div class="modal fade" id="bomDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    BOM Components <span id="modalBomCode" class="text-primary fw-bold"> </span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center p-5">Loading...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- end:: modal bom detail -->

            </div>
            <!--end::Card body-->
        </div>
        <!--end::Table Widget 3-->
    </div>
    <!--end::Col-->
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const bomTableBody = document.querySelector('#bomTable tbody');
        const tableSearch = document.getElementById('tableSearch');
        const bomDetailModal = new bootstrap.Modal(document.getElementById('bomDetailModal'));
        const modalBomCode = document.getElementById('modalBomCode');
        const modalBody = document.querySelector('#bomDetailModal .modal-body');

        let allBoms = [];

        function loadBoms() {
            fetch('bom/get_bom.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allBoms = data.boms;
                        renderBoms(allBoms);
                    } else {
                        console.error('Failed to load BOMs:', data.message);
                    }
                })
                .catch(error => console.error('Error loading BOMs:', error));
        }

        function renderBoms(boms) {
            bomTableBody.innerHTML = '';
                boms.forEach(bom => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    ${bom.product_img
                        ? `<img src="${bom.product_img}"
                               class="w-40px h-40px rounded me-3"
                               alt="${bom.product_name}">`
                        : `<div class="w-40px h-40px rounded bg-light me-3"></div>`
                    }
                    <div>
                        <div class="fw-bold">${bom.product_name}</div>
                        <div class="text-muted small">${bom.product_reference || '-'}</div>
                    </div>
                </div>
            </td>
            <td>${bom.bom_code ? bom.bom_code : '-'}</td>
            <td>
                <button class="btn btn-sm btn-primary view-detail-btn" 
                        data-bom-id="${bom.bom_id}" 
                        data-bom-code="${bom.bom_code}">
                    View Details
                </button>
                <button class="btn btn-sm btn-secondary change-bom-btn ms-2" 
                        data-bom-id="${bom.bom_id}" 
                        data-product-reference="${bom.product_reference}" 
                        data-product-name="${bom.product_name}" 
                        data-product-img="${bom.product_img}">
                    Save to DB
                </button>
                <a href="?module=bom-detail&bom_id=${bom.bom_id}" class="btn btn-sm btn-info ms-2">
                    BOM Detail
                </a>
            </td>
        `;
                bomTableBody.appendChild(row);
            });

            // Event untuk view detail
            document.querySelectorAll('.view-detail-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const bomId = this.getAttribute('data-bom-id');
                    const bomCode = this.getAttribute('data-bom-code');
                    showBomDetail(bomId, bomCode);
                });
            });

            // Event untuk save ke database
            document.querySelectorAll('.change-bom-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const bomId = this.getAttribute('data-bom-id');
                    const productReference = this.getAttribute('data-product-reference');
                    const productName = this.getAttribute('data-product-name');
                    const productImg = this.getAttribute('data-product-img');
                    saveBom(bomId, productReference, productName, productImg);
                });
            });
        }


        function showBomDetail(bomId, bomCode) {
            modalBomCode.textContent = bomCode;
            modalBody.innerHTML = '<div class="text-center p-5">Loading...</div>';
            bomDetailModal.show();

            fetch(`bom/get_bom_detail.php?bom_id=${bomId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderComponents(data.components);
                    } else {
                        modalBody.innerHTML = '<div class="alert alert-danger">Failed to load components.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading BOM detail:', error);
                    modalBody.innerHTML = '<div class="alert alert-danger">Error loading components.</div>';
                });
        }

        function renderComponents(components) {
            if (components.length === 0) {
                modalBody.innerHTML = '<div class="alert alert-info">No components found.</div>';
                return;
            }

            let html = '<table class="table table-striped"><thead><tr><th>Product</th><th>Quantity</th><th>Unit</th></tr></thead><tbody>';
            components.forEach(comp => {
                html += `<tr><td>${comp.product}</td><td>${comp.qty}</td><td>${comp.uom}</td></tr>`;
            });
            html += '</tbody></table>';
            modalBody.innerHTML = html;
        }

        function saveBom(bomId, productReference, productName, productImg) {
            fetch('bom/save_bom.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        bom_id: bomId,
                        product_reference: productReference,
                        product_name: productName,
                        product_img: productImg
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('BOM saved successfully!');
                    } else {
                        alert('Failed to save BOM: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error saving BOM:', error);
                    alert('Error saving BOM');
                });
        }

        // Search functionality
        tableSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const filteredBoms = allBoms.filter(bom =>
                bom.code.toLowerCase().includes(searchTerm) ||
                bom.product_tmpl.toLowerCase().includes(searchTerm)
            );
            renderBoms(filteredBoms);
        });

        // Load BOMs on page load
        loadBoms();
    });
</script>