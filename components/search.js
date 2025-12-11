function setupSearch(tableId, rowsPerPage = 20) {
    const searchInput = document.getElementById('tableSearch');
    if (!searchInput) return;

    const table = document.getElementById(tableId);
    if (!table) return;

    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    let allRows = Array.from(tbody.getElementsByTagName('tr'));

    searchInput.addEventListener('input', function() {
        const filter = this.value.toLowerCase().trim();

        // Remove existing no-results message if any
        const existingMessage = tbody.querySelector('.no-results-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        if (filter === '') {
            // Show all rows
            allRows.forEach(row => {
                row.style.display = '';
            });
        } else {
            const filteredRows = allRows.filter(row => {
                const text = row.textContent.toLowerCase();
                return text.includes(filter);
            });

            // Hide all rows
            allRows.forEach(row => {
                row.style.display = 'none';
            });

            // Show filtered rows
            filteredRows.forEach(row => {
                row.style.display = '';
            });

            if (filteredRows.length === 0) {
                // Create no results message
                const messageRow = document.createElement('tr');
                messageRow.className = 'no-results-message';
                messageRow.innerHTML = `<td colspan="${tbody.querySelector('tr').children.length}" class="text-center py-4">Data Tidak Ditemukan</td>`;
                tbody.appendChild(messageRow);
            }
        }

        // Remove old pagination
        const oldPagination = table.nextSibling;
        if (oldPagination && oldPagination.classList && oldPagination.classList.contains('d-flex')) {
            oldPagination.remove();
        }

        // Re-setup pagination on filtered rows
        setupPagination(tableId, rowsPerPage);
    });
}

function initializeSearch() {
    // Detect and setup search for available tables
    const tables = ['suppliersTable', 'hardwareTable', 'plywoodTable', 'woodPalletsTable', 'woodSolidTable'];
    tables.forEach(tableId => {
        const table = document.getElementById(tableId);
        if (table) {
            setupSearch(tableId, 20);
        }
    });
}

// Initialize search when DOM is loaded
document.addEventListener('DOMContentLoaded', initializeSearch);
