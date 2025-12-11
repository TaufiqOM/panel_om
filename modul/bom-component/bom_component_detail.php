<?php
// Bagian ini memuat data awal saat halaman dibuka
include "../../inc/config.php";
$bom_id = $_GET['bom_id'] ?? 0;
$initial_data = [];

if (!empty($bom_id)) {
    $sql = "SELECT * FROM bom_component WHERE bom_id = ? ORDER BY bom_component_number ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $bom_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $initial_data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $conn->close();
}
?>

<style>
    .button-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .action-group { display: flex; gap: 1rem; }
    .btn { display: inline-flex; align-items: center; padding: 5px; font-size: 11px; font-weight: 600; text-align: center; text-decoration: none; vertical-align: middle; cursor: pointer; border: 1px solid transparent; border-radius: 0.375rem; transition: all 0.2s ease-in-out; }
    .btn-light { color: #3F4254; background-color: #F5F8FA; border-color: #F5F8FA; }
    .btn-light:hover { background-color: #E4E6EF; }
    .btn-light-primary { color: #009EF7; background-color: #F1FAFF; border-color: #F1FAFF; }
    .btn-light-primary:hover { background-color: #D6F0FF; }
    .btn-primary { color: #FFFFFF; background-color: #009EF7; border-color: #009EF7; }
    .btn-primary:hover { background-color: #0089d1; }
    .delete-row-btn { background-color: red; }
    .compact-table { font-family: Arial, Helvetica, sans-serif; font-size: 10px; width: 100%; border-collapse: collapse; margin-top: 10px; }
    .compact-table th, .compact-table td { border: 1px solid #ccc; padding: 1px 3px; text-align: center; vertical-align: middle; }
    .compact-table td { text-align: left; }
    .compact-table th { background-color: #f2f2f2; }
    .compact-table input[type="text"],
    .compact-table input[type="number"],
    .compact-table select {
        width: 100%;
        border: none;
        background-color: transparent;
        padding: 1px;
        margin: 0;
        font-size: 10px;
        box-sizing: border-box;
    }
    .compact-table input[readonly] { background-color: #f5f5f5; font-weight: bold; }
    .compact-table input.num-input { text-align: right; }
    .compact-table .text-center { text-align: center !important; }
    .compact-table tfoot tr { font-weight: bold; background-color: #e9e9e9; }
    .compact-table tfoot td { text-align: right; }
    .compact-table .action-buttons { width: 60px; text-align: center; }
    .compact-table input[type="number"]::-webkit-outer-spin-button,
    .compact-table input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .compact-table input[type="number"] {
        -moz-appearance: textfield;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div class="d-flex gap-3">
        <a href="../?module=bom-detail&bom_id=<?= htmlspecialchars($bom_id) ?>" class="btn btn-sm btn-light">
            <i class="ki-duotone ki-arrow-left fs-3"><span class="path1"></span><span class="path2"></span></i>
            Kembali
        </a>
        <button type="button" class="btn btn-sm btn-light-primary" id="addRowBtn">
            <i class="ki-duotone ki-plus-square fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
            Tambah Baris
        </button>
    </div>

    <button type="button" class="btn btn-sm btn-primary" id="saveChangesBtn">
        <i class="ki-duotone ki-save-2 fs-3"><span class="path1"></span><span class="path2"></span></i>
        Simpan Perubahan
    </button>

</div>

<table class="compact-table" id="bomEditableTable">
    <thead>
        <tr>
            <th rowspan="2" style="width: 4%;">ID</th>
            <th rowspan="2" style="width: 20%;">Nama Komponen</th>
            <th rowspan="2" style="width: 7%;">Bahan</th>
            <th rowspan="2" style="width: 7%;">Kayu</th>
            <th rowspan="2" style="width: 8%;">Kayu Detail</th>
            <th rowspan="2" style="width: 3%;">Jns</th>
            <th rowspan="2" style="width: 4%;">Jml</th>
            <th colspan="3">BERSIH (mm)</th>
            <th rowspan="2" style="width: 5%;">M3 Bersih</th>
            <th colspan="3">KOTOR (mm)</th>
            <th rowspan="2" style="width: 5%;">M3 Kotor</th>
            <th rowspan="2" style="width: 15%;">Keterangan</th>
            <th rowspan="2" style="width: 6%;">Grup</th>
            <th rowspan="2">Aksi</th>
        </tr>
        <tr>
            <th style="width: 3%;">Pjg</th>
            <th style="width: 3%;">Lbr</th>
            <th style="width: 3%;">Tbl</th>
            <th style="width: 3%;">Pjg</th>
            <th style="width: 3%;">Lbr</th>
            <th style="width: 3%;">Tbl</th>
        </tr>
    </thead>
    <tbody>
        </tbody>
    <tfoot>
        <tr>
            <td colspan="6" style="text-align: right;">TOTAL</td>
            <td id="total_qty" class="text-center">0</td>
            <td colspan="3"></td>
            <td id="total_m3_bersih">0.000000</td>
            <td colspan="3"></td>
            <td id="total_m3_kotor">0.000000</td>
            <td colspan="3"></td>
        </tr>
    </tfoot>
</table>

<input type="hidden" id="bomId" value="<?= htmlspecialchars($bom_id) ?>">

<script>
document.addEventListener('DOMContentLoaded', function() {
    let bomData = <?= json_encode($initial_data) ?>;
    const bomId = document.getElementById('bomId').value;
    
    const tableBody = document.querySelector("#bomEditableTable tbody");
    const addRowBtn = document.getElementById('addRowBtn');
    const saveChangesBtn = document.getElementById('saveChangesBtn');

    const bahanOptions = ["Kayu", "MDF", "Plywood", "Teak Block", "Block Board", "Grup", "Sub Grup"];

    const recalculateRow = (item) => {
        const p_b = parseFloat(item.bom_component_panjang) || 0;
        const l_b = parseFloat(item.bom_component_lebar) || 0;
        const t_b = parseFloat(item.bom_component_tebal) || 0;
        const qty = parseInt(item.bom_component_qty) || 0;
        item.m3_bersih = (p_b * l_b * t_b * qty) / 1000000000;
        item.p_kotor = p_b + 20;
        item.l_kotor = l_b + 10;
        item.t_kotor = t_b + 5;
        item.m3_kotor = (item.p_kotor * item.l_kotor * item.t_kotor * qty) / 1000000000;
        return item;
    };

    const renderTable = () => {
        tableBody.innerHTML = '';
        let total_qty = 0;
        let total_m3_bersih = 0;
        let total_m3_kotor = 0;

        bomData.forEach((item, index) => {
            item = recalculateRow(item);
            
            const formattedCounter = String(index + 1).padStart(4, '0');
            const componentNumber = `${bomId}-${formattedCounter}`;
            item.bom_component_number = componentNumber;

            total_qty += parseInt(item.bom_component_qty) || 0;
            total_m3_bersih += item.m3_bersih;
            total_m3_kotor += item.m3_kotor;

            const row = document.createElement('tr');
            row.setAttribute('data-index', index);
            row.innerHTML = `
                <td><input type="text" name="bom_component_number" value="${componentNumber}" readonly></td>
                <td><input type="text" name="bom_component_name" value="${item.bom_component_name || ''}"></td>
                <td>
                    <select name="bom_component_bahan">
                        <option value="">-- Pilih --</option>
                        ${bahanOptions.map(option => 
                            `<option value="${option}" ${item.bom_component_bahan === option ? 'selected' : ''}>${option}</option>`
                        ).join('')}
                    </select>
                </td>
                <td><input type="text" name="bom_component_kayu" value="${item.bom_component_kayu || ''}"></td>
                <td><input type="text" name="bom_component_kayu_detail" value="${item.bom_component_kayu_detail || ''}"></td>
                <td class="text-center"><input type="text" class="num-input" name="bom_component_jenis" value="${item.bom_component_jenis || ''}"></td>
                <td class="text-center"><input type="number" class="num-input" name="bom_component_qty" value="${item.bom_component_qty || 0}"></td>
                <td><input type="number" class="num-input" name="bom_component_panjang" value="${item.bom_component_panjang || 0}"></td>
                <td><input type="number" class="num-input" name="bom_component_lebar" value="${item.bom_component_lebar || 0}"></td>
                <td><input type="number" class="num-input" name="bom_component_tebal" value="${item.bom_component_tebal || 0}"></td>
                <td><input type="text" class="num-input" value="${item.m3_bersih.toFixed(6)}" readonly></td>
                <td><input type="number" class="num-input" value="${item.p_kotor}" readonly></td>
                <td><input type="number" class="num-input" value="${item.l_kotor}" readonly></td>
                <td><input type="number" class="num-input" value="${item.t_kotor}" readonly></td>
                <td><input type="text" class="num-input" value="${item.m3_kotor.toFixed(6)}" readonly></td>
                <td><input type="text" name="bom_component_description" value="${item.bom_component_description || ''}"></td>
                <td><input type="text" name="bom_component_group" value="${item.bom_component_group || ''}"></td>
                <td class="action-buttons">
                    <button type="button" class="btn btn-sm btn-icon btn-light-danger delete-row-btn"></button>
                </td>
            `;
            tableBody.appendChild(row);
        });

        document.getElementById('total_qty').textContent = total_qty;
        document.getElementById('total_m3_bersih').textContent = total_m3_bersih.toFixed(6);
        document.getElementById('total_m3_kotor').textContent = total_m3_kotor.toFixed(6);
    };

    tableBody.addEventListener('change', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') {
            const index = e.target.closest('tr').getAttribute('data-index');
            const name = e.target.name;
            if (index && bomData[index]) {
                bomData[index][name] = e.target.value;
                renderTable();
            }
        }
    });

    tableBody.addEventListener('click', function(e) {
        if (e.target.closest('.delete-row-btn')) {
            const index = e.target.closest('tr').getAttribute('data-index');
            bomData.splice(index, 1);
            renderTable();
        }
    });

    addRowBtn.addEventListener('click', function() {
        bomData.push({
            bom_component_qty: 1, bom_component_panjang: 0,
            bom_component_lebar: 0, bom_component_tebal: 0
        }); 
        renderTable(); 
    });

    saveChangesBtn.addEventListener('click', function() {
        Swal.fire({
            title: 'Simpan Perubahan?',
            text: "Semua data komponen untuk BOM ini akan diperbarui.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Ya, simpan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                
                let isValid = true;
                let firstErrorIndex = -1;
                
                if (bomData.length === 0) {
                     Swal.fire('Validasi Gagal', 'Tidak ada data komponen untuk disimpan.', 'warning');
                     return;
                }

                bomData.forEach((item, index) => {
                    if (!item.bom_component_name || item.bom_component_name.trim() === '') {
                        isValid = false;
                        if (firstErrorIndex === -1) {
                            firstErrorIndex = index;
                        }
                    }
                });

                if (!isValid) {
                    Swal.fire('Validasi Gagal', 'Pastikan semua isian "Nama Komponen" tidak kosong.', 'error');
                    
                    const errorRow = tableBody.querySelector(`tr[data-index="${firstErrorIndex}"]`);
                    if (errorRow) {
                        errorRow.classList.add('table-danger');
                        errorRow.querySelector('input[name="bom_component_name"]').focus();
                        setTimeout(() => {
                            errorRow.classList.remove('table-danger');
                        }, 3000);
                    }
                    return; 
                }

                saveChangesBtn.setAttribute('data-kt-indicator', 'on');
                saveChangesBtn.disabled = true;

                // Ganti dengan path yang benar ke file save_changes.php Anda
                fetch('path/to/save_changes.php', { 
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        bom_id: bomId,
                        components: bomData
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Berhasil!', data.message, 'success');
                    } else {
                        Swal.fire('Gagal!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
                })
                .finally(() => {
                    saveChangesBtn.removeAttribute('data-kt-indicator');
                    saveChangesBtn.disabled = false;
                });
            }
        });
    });

    renderTable();
});
</script>