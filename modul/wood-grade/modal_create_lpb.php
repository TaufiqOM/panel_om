<div class="modal fade" id="createLpbModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat LPB Wood Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createLpbForm">
                    <input type="hidden" id="lpbDate" name="lpb_date">
                    <input type="hidden" id="lpbLocation" name="lpb_location">
                    <input type="hidden" id="lpbWood" name="lpb_wood">
                    
                    <div class="mb-3">
                        <label for="lpbNumber" class="form-label">Nomor LPB <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="lpbNumber" name="lpb_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="lpbPo" class="form-label">Nomor PO <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="lpbPo" name="lpb_po" required>
                    </div>

                    <div class="mb-3">
                        <label for="supplier" class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select class="form-select" id="supplierName" name="supplier_name" required>
                            <option value="">Pilih Supplier</option>
                            <?php
                                if(!$conn) {
                                    echo '<option value="">Error: Koneksi database gagal</option>';
                                } else {
                                    $sql_supplier = "SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name ASC";
                                    $result_supplier = $conn->query($sql_supplier); 
                                    
                                    if ($result_supplier) {
                                        while($row_supplier = $result_supplier->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($row_supplier['supplier_name']) . '">' . htmlspecialchars($row_supplier['supplier_name']) . '</option>';
                                        }
                                        $result_supplier->free();
                                    } else {
                                        echo '<option value="">Error: Gagal memuat supplier.</option>';
                                        echo '<option value="">Debug: ' . htmlspecialchars($conn->error) . '</option>';
                                    }
                                }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="lpbDateInvoice" class="form-label">Tanggal Invoice <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="lpbDateInvoice" name="lpb_date_invoice" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Informasi:</strong><br>
                        <span id="lpbInfo"></span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="submitCreateLpb">Simpan LPB</button>
            </div>
        </div>
    </div>
</div>