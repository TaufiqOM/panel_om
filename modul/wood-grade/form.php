<div class="modal fade" id="dateRangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pilih Rentang Tanggal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="pullDataForm">
                    <div class="mb-3">
                        <label for="dateRangePicker" class="form-label">Tanggal Mulai - Tanggal Selesai</label>
                        <input type="text" class="form-control" id="dateRangePicker" name="date_range" placeholder="Klik untuk memilih tanggal..." />
                    </div>
                </form>
                <div class="alert alert-light-info mt-5">
                    <strong>Info:</strong> Data grade dari sistem lain akan ditarik berdasarkan rentang tanggal yang Anda pilih.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="submitPullDataBtn">
                    <i class="ki-duotone ki-cloud-download fs-5"><span class="path1"></span><span class="path2"></span></i>
                    Tarik Data
                </button>
            </div>
        </div>
    </div>
</div>