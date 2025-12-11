<?php
// Set header untuk response JSON
header('Content-Type: application/json');

// =================================================================
// KONFIGURASI DATABASE (WAJIB DISESUAIKAN)
// =================================================================

// Koneksi ke Database ONLINE (Sumber Data)
$online_db_host = 'omegamas.co.id'; // Ganti dengan host server online Anda
$online_db_user = 'u1579629_root';      // Ganti dengan username db online
$online_db_pass = '0Megamas123#@!';          // Ganti dengan password db online
$online_db_name = 'u1579629_warehouse'; // Ganti dengan nama database online
$online_table_name = 'detailkayu'; // GANTI dengan nama tabel online Anda

// Koneksi ke Database OFFLINE (Tujuan Data)
$offline_db_host = 'localhost';
$offline_db_user = 'siomas';
$offline_db_pass = 'siomas123#@!';
$offline_db_name = 'siomas'; // Nama database offline/lokal Anda
// =================================================================

// Fungsi untuk mengembalikan response error dan menghentikan script
function send_error($message) {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// 1. Validasi Input Tanggal
$startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$endDate = isset($_POST['end_date']) ? $_POST['end_date'] : null;

if (!$startDate || !$endDate) {
    send_error('Error: Rentang tanggal harus dipilih.');
}

// 2. Buat Koneksi ke Kedua Database
$conn_online = new mysqli($online_db_host, $online_db_user, $online_db_pass, $online_db_name);
if ($conn_online->connect_error) {
    send_error('Gagal terhubung ke database online: ' . $conn_online->connect_error);
}

$conn_offline = new mysqli($offline_db_host, $offline_db_user, $offline_db_pass, $offline_db_name);
if ($conn_offline->connect_error) {
    send_error('Gagal terhubung ke database offline: ' . $conn_offline->connect_error);
}

$conn_online->set_charset('utf8');
$conn_offline->set_charset('utf8');

$inserted_count = 0;
$skipped_count = 0;

// Mulai transaksi di database offline untuk integritas data
$conn_offline->begin_transaction();

try {
    // 3. Query untuk mengambil data dari database ONLINE
    // Sesuaikan nama kolom jika berbeda
    $sql_online = "SELECT Grup, tgl, jam, jns_kayu, no_kayu, T, L, P, operator, lokasi, Keterangan 
                   FROM {$online_table_name} 
                   WHERE tgl BETWEEN ? AND ?";
                   
    $stmt_online = $conn_online->prepare($sql_online);
    $stmt_online->bind_param("ss", $startDate, $endDate);
    $stmt_online->execute();
    $result_online = $stmt_online->get_result();

    if ($result_online->num_rows === 0) {
        $conn_offline->rollback(); // Batalkan transaksi jika tidak ada data
        echo json_encode(['success' => true, 'message' => 'Tidak ada data baru untuk ditarik pada rentang tanggal yang dipilih.']);
        exit;
    }

    // Siapkan statement untuk memeriksa duplikat dan menyisipkan data di OFFLINE
    $check_sql = "SELECT wood_solid_grade_id FROM wood_solid_grade WHERE wood_solid_barcode = ?";
    $stmt_check = $conn_offline->prepare($check_sql);

    $insert_sql = "INSERT INTO wood_solid_grade 
                    (wood_solid_group, wood_solid_grade_date, wood_solid_grade_time, wood_name, wood_solid_barcode, 
                     wood_solid_height, wood_solid_width, wood_solid_length, operator_fullname, location_name, 
                     wood_solid_grade_description, wood_solid_grade_status) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"; // status default 0
    $stmt_insert = $conn_offline->prepare($insert_sql);

    // 4. Loop setiap baris data dari ONLINE dan masukkan ke OFFLINE
    while ($row = $result_online->fetch_assoc()) {
        // Cek apakah barcode sudah ada di tabel offline
        $stmt_check->bind_param("s", $row['no_kayu']);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows === 0) {
            // Barcode belum ada, lanjutkan proses insert
            $stmt_insert->bind_param(
                "sssssssssss",
                $row['Grup'],
                $row['tgl'],
                $row['jam'],
                $row['jns_kayu'],
                $row['no_kayu'],
                $row['T'],
                $row['L'],
                $row['P'],
                $row['operator'],
                $row['lokasi'],
                $row['Keterangan']
            );
            
            if (!$stmt_insert->execute()) {
                // Jika satu saja insert gagal, batalkan semua
                throw new Exception("Gagal memasukkan data barcode: " . $row['no_kayu'] . ". Error: " . $stmt_insert->error);
            }
            $inserted_count++;
        } else {
            // Barcode sudah ada, lewati
            $skipped_count++;
        }
    }

    // 5. Jika semua berhasil, commit transaksi
    $conn_offline->commit();

    // Buat pesan ringkasan
    $message = "Proses selesai. \n";
    $message .= "Data baru berhasil ditambahkan: " . $inserted_count . " baris.\n";
    $message .= "Data duplikat dilewati: " . $skipped_count . " baris.";

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    // Jika terjadi error, rollback transaksi
    $conn_offline->rollback();
    send_error('Terjadi kesalahan selama proses: ' . $e->getMessage());
} finally {
    // 6. Tutup semua statement dan koneksi
    if (isset($stmt_online)) $stmt_online->close();
    if (isset($stmt_check)) $stmt_check->close();
    if (isset($stmt_insert)) $stmt_insert->close();
    $conn_online->close();
    $conn_offline->close();
}
?>