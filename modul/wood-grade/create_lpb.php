<?php
// wood-grade/create_lpb.php
include '../../inc/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $lpb_number = $_POST['lpb_number'] ?? '';
    $lpb_po = $_POST['lpb_po'] ?? '';
    $lpb_date_invoice = $_POST['lpb_date_invoice'] ?? '';
    $lpb_date = $_POST['lpb_date'] ?? '';
    $lpb_location = $_POST['lpb_location'] ?? '';
    $lpb_wood = $_POST['lpb_wood'] ?? '';
    $supplier_name = $_POST['supplier_name'] ?? '';
    
    // Validasi data
    if (empty($lpb_number) || empty($lpb_po) || empty($lpb_date_invoice) || empty($lpb_date) || empty($lpb_location) || empty($lpb_wood) || empty($supplier_name)) {
        echo json_encode([
            'success' => false,
            'message' => 'Semua field harus diisi'
        ]);
        exit;
    }
    
    // Mulai transaction
    mysqli_begin_transaction($conn);
    
    try {
        // **VALIDASI: Cek apakah nomor LPB sudah ada**
        $sql_check = "SELECT wood_solid_lpb_id FROM wood_solid_lpb WHERE wood_solid_lpb_number = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "s", $lpb_number);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            throw new Exception('Nomor LPB "' . $lpb_number . '" sudah digunakan. Silakan gunakan nomor yang berbeda.');
        }
        mysqli_stmt_close($stmt_check);

        // **VALIDASI: Cek apakah supplier ada di tabel supplier**
        $sql_check_supplier = "SELECT supplier_name FROM supplier WHERE supplier_name = ?";
        $stmt_check_supplier = mysqli_prepare($conn, $sql_check_supplier);
        mysqli_stmt_bind_param($stmt_check_supplier, "s", $supplier_name);
        mysqli_stmt_execute($stmt_check_supplier);
        mysqli_stmt_store_result($stmt_check_supplier);
        
        if (mysqli_stmt_num_rows($stmt_check_supplier) == 0) {
            throw new Exception('Supplier "' . $supplier_name . '" tidak ditemukan dalam database.');
        }
        mysqli_stmt_close($stmt_check_supplier);
        
        // 1. Insert ke tabel wood_solid_lpb dengan supplier_name
        $sql_lpb = "INSERT INTO wood_solid_lpb (wood_solid_lpb_number, wood_solid_lpb_po, wood_solid_lpb_date_invoice, wood_name, supplier_name) 
                   VALUES (?, ?, ?, ?, ?)";
        $stmt_lpb = mysqli_prepare($conn, $sql_lpb);
        mysqli_stmt_bind_param($stmt_lpb, "sssss", $lpb_number, $lpb_po, $lpb_date_invoice, $lpb_wood, $supplier_name);
        
        if (!mysqli_stmt_execute($stmt_lpb)) {
            // Cek jika error disebabkan oleh duplicate entry
            if (mysqli_errno($conn) == 1062) { // Error code untuk duplicate entry
                throw new Exception('Nomor LPB "' . $lpb_number . '" sudah digunakan. Silakan gunakan nomor yang berbeda.');
            } else {
                throw new Exception('Gagal menyimpan data LPB: ' . mysqli_error($conn));
            }
        }
        
        $lpb_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_lpb);
        
        // 2. Ambil data dari wood_solid_grade berdasarkan tanggal, lokasi, dan wood_name
        $sql_grade = "SELECT wood_solid_group, wood_name, wood_solid_barcode, wood_solid_height, wood_solid_width, wood_solid_length 
                     FROM wood_solid_grade 
                     WHERE wood_solid_grade_date = ? AND location_name = ? AND wood_name = ? AND wood_solid_grade_status IS NULL";
        $stmt_grade = mysqli_prepare($conn, $sql_grade);
        mysqli_stmt_bind_param($stmt_grade, "sss", $lpb_date, $lpb_location, $lpb_wood);
        mysqli_stmt_execute($stmt_grade);
        $result_grade = mysqli_stmt_get_result($stmt_grade);
        
        // 3. Insert ke tabel wood_solid_lpb_detail (jika ada tabel detail)
        $inserted_count = 0;
        $wood_solid_groups = []; // Untuk menyimpan wood_solid_group yang diproses
        
        // Cek apakah tabel detail ada, jika ada maka insert data detail
        $sql_check_detail_table = "SHOW TABLES LIKE 'wood_solid_lpb_detail'";
        $result_check = mysqli_query($conn, $sql_check_detail_table);
        
        if (mysqli_num_rows($result_check) > 0) {
            $sql_detail = "INSERT INTO wood_solid_lpb_detail (wood_solid_lpb_id, wood_solid_group, wood_name, wood_solid_barcode, wood_solid_height, wood_solid_width, wood_solid_length) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_detail = mysqli_prepare($conn, $sql_detail);
            
            while ($row = mysqli_fetch_assoc($result_grade)) {
                mysqli_stmt_bind_param($stmt_detail, "issssss", 
                    $lpb_id, 
                    $row['wood_solid_group'], 
                    $row['wood_name'], 
                    $row['wood_solid_barcode'], 
                    $row['wood_solid_height'], 
                    $row['wood_solid_width'], 
                    $row['wood_solid_length']
                );
                
                if (!mysqli_stmt_execute($stmt_detail)) {
                    throw new Exception('Gagal menyimpan detail LPB: ' . mysqli_error($conn));
                }
                $inserted_count++;
                
                // Simpan wood_solid_group untuk update status nanti
                $wood_solid_groups[] = $row['wood_solid_group'];
            }
            
            if (isset($stmt_detail)) {
                mysqli_stmt_close($stmt_detail);
            }
        } else {
            // Jika tidak ada tabel detail, tetap hitung records yang akan diupdate statusnya
            while ($row = mysqli_fetch_assoc($result_grade)) {
                $inserted_count++;
                $wood_solid_groups[] = $row['wood_solid_group'];
            }
        }
        
        mysqli_stmt_close($stmt_grade);
        
        // 4. UPDATE STATUS: Update wood_solid_grade_status ke 1 untuk wood_solid_group yang diproses
        if (!empty($wood_solid_groups)) {
            // Buat placeholder untuk prepared statement
            $placeholders = str_repeat('?,', count($wood_solid_groups) - 1) . '?';
            
            $sql_update_status = "UPDATE wood_solid_grade 
                                 SET wood_solid_grade_status = '1' 
                                 WHERE wood_solid_group IN ($placeholders)";
            
            $stmt_update = mysqli_prepare($conn, $sql_update_status);
            
            // Bind parameters dynamically
            $types = str_repeat('s', count($wood_solid_groups));
            mysqli_stmt_bind_param($stmt_update, $types, ...$wood_solid_groups);
            
            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception('Gagal update status wood grade: ' . mysqli_error($conn));
            }
            
            $updated_count = mysqli_affected_rows($conn);
            mysqli_stmt_close($stmt_update);
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        echo json_encode([
            'success' => true,
            'message' => "LPB berhasil dibuat dengan $inserted_count item. Status wood grade diperbarui.",
            'lpb_id' => $lpb_id,
            'items_inserted' => $inserted_count,
            'status_updated' => $updated_count ?? 0
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction jika ada error
        mysqli_rollback($conn);
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
    mysqli_close($conn);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan'
    ]);
}
?>