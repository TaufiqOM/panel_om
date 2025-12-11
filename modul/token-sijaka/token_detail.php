<div class="row g-5 g-xxl-10">
    <div class="col-xl-12 mb-5 mb-xxl-10">
        <div class="card card-flush h-xl-100">
            <div class="card-header py-7">
                <div class="card-title pt-3 mb-0 gap-4 gap-lg-10 gap-xl-15 nav nav-tabs border-bottom-0">
                    <div class="fs-4 fw-bold pb-3 border-bottom border-3 border-primary cursor-pointer"
                        data-kt-table-widget-3="tab" data-kt-table-widget-3-value="Show All">
                        List Token
                    </div>
                </div>
            </div>

            <div class="card-body pt-1">
                <div id="skeletonLoader">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><span class="placeholder col-6"></span></th>
                                <th><span class="placeholder col-6"></span></th>
                                <th><span class="placeholder col-4"></span></th>
                                <th><span class="placeholder col-4"></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="placeholder col-8"></span></td>
                                <td><span class="placeholder col-6"></span></td>
                                <td><span class="placeholder col-5"></span></td>
                                <td><span class="placeholder col-5"></span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <table id="plywoodTable" class="table table-row-dashed align-middle fs-6 gy-4 my-0 pb-3"
                    data-kt-table-widget-3="all" style="display:none;">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Token</th>
                            <th>Status</th>
                            <th class="text-center">QR-Code</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT * FROM token ORDER BY token_id DESC";
                        $result = mysqli_query($conn, $sql);

                        if ($result && mysqli_num_rows($result) > 0):
                            $no = 1;
                        ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <?php
                                    // --- INI LOGIKA UNTUK STATUS ---
                                    $status_text = '';
                                    $status_badge_class = '';
                                    
                                    if ($row['token_status'] == 1) {
                                        $status_text = 'Active';
                                        $status_badge_class = 'badge-light-success'; // Badge hijau
                                    } else {
                                        $status_text = 'Inactive';
                                        $status_badge_class = 'badge-light-danger'; // Badge merah
                                    }
                                    // --- AKHIR DARI LOGIKA ---
                                ?>
                                
                                <tr>
                                    <td class="min-w-50px"><?= $no++ ?></td>
                                    
                                    <td class="min-w-150px">
                                        <span class="badge badge-light-primary fs-7"><?= htmlspecialchars($row['token_code']) ?></span>
                                    </td>
                                    
                                    <td class="min-w-100px">
                                        <span class="badge <?= $status_badge_class ?> fs-7"><?= $status_text ?></span>
                                    </td>
                                    <td class="min-w-100px text-center">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?= urlencode($row['token_code']) ?>" alt="QR Code" width="50" height="50">
                                        <br>
                                        <button class="btn btn-xs btn-primary p-1 px-2 mt-2" onclick="downloadQR('<?= htmlspecialchars($row['token_code']) ?>')">
    <span class="fs-8">Download</span>
</button>
                                    </td>

                                    <td class="min-w-150px">
                                        <?= htmlspecialchars($row['created_at']) ?>
                                    </td>

                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Tidak ada data token ditemukan</td>
                            </tr>
                        <?php endif;

                        if ($result) {
                            mysqli_free_result($result);
                        }
                        mysqli_close($conn);
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="../components/pagination.js"></script>
<script>
function downloadQR(token) {
    // URL QR code API
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=${encodeURIComponent(token)}`;
    
    // Nama file untuk download
    const fileName = `QR_${token}.png`;
    
    // Tampilkan loading indicator
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Downloading...';
    button.disabled = true;
    
    // Menggunakan fetch untuk mendapatkan gambar sebagai blob
    fetch(qrUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.blob();
        })
        .then(blob => {
            // Membuat URL object dari blob
            const blobUrl = window.URL.createObjectURL(blob);
            
            // Membuat elemen anchor untuk download
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = fileName;
            
            // Menambahkan ke DOM dan klik otomatis
            document.body.appendChild(link);
            link.click();
            
            // Membersihkan URL object setelah download
            window.URL.revokeObjectURL(blobUrl);
            document.body.removeChild(link);
            
            // Reset button state
            button.innerHTML = originalText;
            button.disabled = false;
            
            // Optional: Tampilkan notifikasi sukses
            showToast('success', 'QR Code berhasil didownload!');
        })
        .catch(error => {
            console.error('Error downloading QR code:', error);
            
            // Reset button state
            button.innerHTML = originalText;
            button.disabled = false;
            
            // Tampilkan error
            showToast('error', 'Gagal mendownload QR Code');
            
            // Fallback ke metode lama jika fetch gagal
            const fallbackLink = document.createElement('a');
            fallbackLink.href = qrUrl;
            fallbackLink.download = fileName;
            document.body.appendChild(fallbackLink);
            fallbackLink.click();
            document.body.removeChild(fallbackLink);
        });
}

// Fungsi untuk menampilkan toast notification (opsional)
function showToast(type, message) {
    // Jika menggunakan Bootstrap Toast
    if (typeof bootstrap !== 'undefined') {
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        const toastContainer = document.querySelector('.toast-container') || createToastContainer();
        toastContainer.appendChild(toastEl);
        
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        
        // Hapus toast setelah ditampilkan
        toastEl.addEventListener('hidden.bs.toast', function () {
            toastEl.remove();
        });
    } else {
        // Fallback ke alert biasa
        alert(message);
    }
}

// Fungsi untuk membuat toast container jika belum ada
function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}
</script>