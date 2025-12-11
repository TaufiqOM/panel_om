function setupPagination(tableId, rowsPerPage) {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.getElementsByTagName('tr')).filter(row => row.style.display !== 'none');
    const totalRows = rows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    let currentPage = 1;

    // Create pagination container
    const paginationContainer = document.createElement('div');
    paginationContainer.className = 'd-flex justify-content-between align-items-center mt-4';
    paginationContainer.innerHTML = `
        <div>
            Showing <span id="${tableId}-showing">1</span> to <span id="${tableId}-to">${Math.min(rowsPerPage, totalRows)}</span> of ${totalRows} entries
        </div>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item" id="${tableId}-prev">
                    <a class="page-link" href="#" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <li class="page-item active" id="${tableId}-page-1"><a class="page-link" href="#">1</a></li>
            </ul>
        </nav>
    `;

    // Insert pagination after table
    table.parentNode.insertBefore(paginationContainer, table.nextSibling);

    // Function to update table display
    function displayRows(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        // Hide all rows
        for (let i = 0; i < totalRows; i++) {
            rows[i].style.display = 'none';
        }

        // Show rows for current page
        for (let i = start; i < end && i < totalRows; i++) {
            rows[i].style.display = '';
        }

        // Update showing text
        document.getElementById(`${tableId}-showing`).textContent = start + 1;
        document.getElementById(`${tableId}-to`).textContent = Math.min(end, totalRows);

        // Update pagination state
        updatePagination(page);
        currentPage = page;
    }

    // Function to create pagination buttons (called once)
    function createPaginationButtons() {
        const ul = paginationContainer.querySelector('.pagination');
        ul.innerHTML = `
            <li class="page-item" id="${tableId}-first">
                <a class="page-link" href="#" aria-label="First"><<</a>
            </li>
            <li class="page-item disabled" id="${tableId}-prev">
                <a class="page-link" href="#" aria-label="Previous"><</a>
            </li>
            <li class="page-item" id="${tableId}-page-1">
                <a class="page-link" href="#">1</a>
            </li>
            <li class="page-item disabled" id="${tableId}-ellipsis-1">
                <span class="page-link">...</span>
            </li>
            <li class="page-item" id="${tableId}-page-mid-1">
                <a class="page-link" href="#">2</a>
            </li>
            <li class="page-item" id="${tableId}-page-mid-2">
                <a class="page-link" href="#">3</a>
            </li>
            <li class="page-item" id="${tableId}-page-mid-3">
                <a class="page-link" href="#">4</a>
            </li>
            <li class="page-item disabled" id="${tableId}-ellipsis-2">
                <span class="page-link">...</span>
            </li>
            <li class="page-item" id="${tableId}-page-last">
                <a class="page-link" href="#">5</a>
            </li>
            <li class="page-item disabled" id="${tableId}-next">
                <a class="page-link" href="#" aria-label="Next">></a>
            </li>
            <li class="page-item" id="${tableId}-last">
                <a class="page-link" href="#" aria-label="Last">>></a>
            </li>
        `;
    }

    // Function to update pagination state without rebuilding
    function updatePagination(page) {
        const page1 = document.getElementById(`${tableId}-page-1`);
        const ellipsis1 = document.getElementById(`${tableId}-ellipsis-1`);
        const mid1 = document.getElementById(`${tableId}-page-mid-1`);
        const mid2 = document.getElementById(`${tableId}-page-mid-2`);
        const mid3 = document.getElementById(`${tableId}-page-mid-3`);
        const ellipsis2 = document.getElementById(`${tableId}-ellipsis-2`);
        const pageLast = document.getElementById(`${tableId}-page-last`);

        // Set texts
        page1.querySelector('a').textContent = '1';
        page1.style.display = totalPages > 1 ? '' : 'none';

        ellipsis1.style.display = (totalPages > 5 && page > 4) ? '' : 'none';

        const mid1Val = page - 1;
        mid1.querySelector('a').textContent = mid1Val;
        mid1.style.display = (mid1Val > 1 && mid1Val < page) ? '' : 'none';

        mid2.querySelector('a').textContent = page;
        mid2.style.display = (page > 1 && page < totalPages) ? '' : 'none';

        const mid3Val = page + 1;
        mid3.querySelector('a').textContent = mid3Val;
        mid3.style.display = (mid3Val < totalPages && mid3Val > page) ? '' : 'none';

        ellipsis2.style.display = (totalPages > 5 && page < totalPages - 3) ? '' : 'none';

        pageLast.querySelector('a').textContent = totalPages;
        pageLast.style.display = totalPages > 1 ? '' : 'none';

        // Update active
        document.querySelectorAll(`#${tableId} .page-item`).forEach(item => {
            item.classList.remove('active');
        });
        if (page === 1) {
            page1.classList.add('active');
        } else if (page === totalPages) {
            pageLast.classList.add('active');
        } else if (mid1Val === page) {
            mid1.classList.add('active');
        } else if (mid3Val === page) {
            mid3.classList.add('active');
        } else {
            mid2.classList.add('active');
        }

        // Update disabled states
        const prevItem = document.getElementById(`${tableId}-prev`);
        const nextItem = document.getElementById(`${tableId}-next`);
        const firstItem = document.getElementById(`${tableId}-first`);
        const lastItem = document.getElementById(`${tableId}-last`);

        if (prevItem) prevItem.classList.toggle('disabled', page === 1);
        if (nextItem) nextItem.classList.toggle('disabled', page === totalPages);
        if (firstItem) firstItem.classList.toggle('disabled', page === 1);
        if (lastItem) lastItem.classList.toggle('disabled', page === totalPages);
    }

    // Initialize pagination buttons
    createPaginationButtons();

    // Event listeners for pagination
    paginationContainer.addEventListener('click', function(e) {
        e.preventDefault();
        const target = e.target.closest('.page-link');
        if (!target) return;

        const li = target.closest('.page-item');
        if (li && li.classList.contains('disabled')) return;

        const first = target.closest(`#${tableId}-first`);
        const last = target.closest(`#${tableId}-last`);
        const prev = target.closest(`#${tableId}-prev`);
        const next = target.closest(`#${tableId}-next`);
        const pageItem = target.closest('.page-item');

        if (first) {
            displayRows(1);
        } else if (last) {
            displayRows(totalPages);
        } else if (prev) {
            displayRows(currentPage - 1);
        } else if (next) {
            displayRows(currentPage + 1);
        } else if (pageItem && (pageItem.id.includes('page-1') || pageItem.id.includes('page-last') || pageItem.id.includes('page-mid-'))) {
            const pageNum = parseInt(pageItem.querySelector('a').textContent);
            displayRows(pageNum);
        }
    });

    // Initial display
    displayRows(1);
    updatePagination(1);
}

function initializePagination() {
    // Show tables after loader if they exist
    setTimeout(function() {
        const skeletonLoader = document.getElementById('skeletonLoader');
        if (skeletonLoader) {
            skeletonLoader.style.display = 'none';
        }

        const suppliersTable = document.getElementById('suppliersTable');
        if (suppliersTable) {
            suppliersTable.style.display = 'table';
            setupPagination('suppliersTable', 20);
        }

        const hardwareTable = document.getElementById('hardwareTable');
        if (hardwareTable) {
            hardwareTable.style.display = 'table';
            setupPagination('hardwareTable', 20);
        }

        const plywoodTable = document.getElementById('plywoodTable');
        if (plywoodTable) {
            plywoodTable.style.display = 'table';
            setupPagination('plywoodTable', 20);
        }

        const woodPalletsTable = document.getElementById('woodPalletsTable');
        if (woodPalletsTable) {
            woodPalletsTable.style.display = 'table';
            setupPagination('woodPalletsTable', 20);
        }

        const woodSolidTable = document.getElementById('woodSolidTable');
        if (woodSolidTable) {
            woodSolidTable.style.display = 'table';
            setupPagination('woodSolidTable', 20);
        }

        const shippingTable = document.getElementById('shippingTable');
        if (shippingTable) {
            shippingTable.style.display = 'table';
            setupPagination('shippingTable', 20);
        }
    }, 500);
}

// Initialize pagination when DOM is loaded
document.addEventListener('DOMContentLoaded', initializePagination);
