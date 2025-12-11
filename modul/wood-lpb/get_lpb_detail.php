<?php
// wood-lpb/get_lpb_detail.php
include '../../inc/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil ID LPB dari request
    $lpb_id = $_POST['id'] ?? '';
    
    // Validasi data
    if (empty($lpb_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'ID LPB tidak valid'
        ]);
        exit;
    }
    
    try {
        // 1. Ambil data utama LPB dari wood_solid_lpb
        $sql_lpb = "SELECT 
                    wood_solid_lpb_id,
                    wood_solid_lpb_number,
                    wood_solid_lpb_po,
                    wood_solid_lpb_date_invoice,
                    wood_name,
                    supplier_name,
                    created_at,
                    updated_at
                FROM wood_solid_lpb 
                WHERE wood_solid_lpb_id = ?";
        
        $stmt_lpb = mysqli_prepare($conn, $sql_lpb);
        mysqli_stmt_bind_param($stmt_lpb, "i", $lpb_id);
        mysqli_stmt_execute($stmt_lpb);
        $result_lpb = mysqli_stmt_get_result($stmt_lpb);
        
        if (mysqli_num_rows($result_lpb) === 0) {
            throw new Exception('Data LPB tidak ditemukan');
        }
        
        $lpb_data = mysqli_fetch_assoc($result_lpb);
        mysqli_stmt_close($stmt_lpb);
        
        // 2. Ambil detail item LPB dari wood_solid_lpb_detail (jika tabel exists)
        $details = [];
        
        // Cek apakah tabel wood_solid_lpb_detail ada
        $sql_check_table = "SHOW TABLES LIKE 'wood_solid_lpb_detail'";
        $result_check = mysqli_query($conn, $sql_check_table);
        
        if (mysqli_num_rows($result_check) > 0) {
            // Jika tabel detail ada, ambil data detail
            $sql_detail = "SELECT 
                            wood_solid_barcode,
                            wood_name,
                            wood_solid_height,
                            wood_solid_width,
                            wood_solid_length,
                            wood_solid_group
                        FROM wood_solid_lpb_detail 
                        WHERE wood_solid_lpb_id = ? 
                        ORDER BY wood_solid_barcode ASC";
            
            $stmt_detail = mysqli_prepare($conn, $sql_detail);
            mysqli_stmt_bind_param($stmt_detail, "i", $lpb_id);
            mysqli_stmt_execute($stmt_detail);
            $result_detail = mysqli_stmt_get_result($stmt_detail);
            
            while ($row = mysqli_fetch_assoc($result_detail)) {
                $details[] = $row;
            }
            mysqli_stmt_close($stmt_detail);
        } else {
            // Jika tabel detail tidak ada, ambil data dari wood_solid_grade berdasarkan LPB number
            $sql_grade = "SELECT 
                            wood_solid_barcode,
                            wood_name,
                            wood_solid_height,
                            wood_solid_width,
                            wood_solid_length,
                            wood_solid_group
                        FROM wood_solid_grade 
                        WHERE wood_solid_grade_status = '1' 
                        AND EXISTS (
                            SELECT 1 FROM wood_solid_lpb 
                            WHERE wood_solid_lpb.wood_solid_lpb_number = ? 
                            AND wood_solid_grade.wood_name = wood_solid_lpb.wood_name
                        )
                        ORDER BY wood_solid_barcode ASC";
            
            $stmt_grade = mysqli_prepare($conn, $sql_grade);
            mysqli_stmt_bind_param($stmt_grade, "s", $lpb_data['wood_solid_lpb_number']);
            mysqli_stmt_execute($stmt_grade);
            $result_grade = mysqli_stmt_get_result($stmt_grade);
            
            while ($row = mysqli_fetch_assoc($result_grade)) {
                $details[] = $row;
            }
            mysqli_stmt_close($stmt_grade);
        }
        
        // 3. Hitung total volume jika ada data dimensi
        $total_volume = 0;
        $total_items = count($details);
        
        foreach ($details as $detail) {
            if ($detail['wood_solid_height'] && $detail['wood_solid_width'] && $detail['wood_solid_length']) {
                $volume = ($detail['wood_solid_height'] * $detail['wood_solid_width'] * $detail['wood_solid_length']) / 1000000; // Convert to m³
                $total_volume += $volume;
            }
        }
        
        // 4. Format tanggal untuk display
        $lpb_data['created_at_formatted'] = date('d/m/Y H:i:s', strtotime($lpb_data['created_at']));
        $lpb_data['updated_at_formatted'] = date('d/m/Y H:i:s', strtotime($lpb_data['updated_at']));
        $lpb_data['invoice_date_formatted'] = date('d/m/Y', strtotime($lpb_data['wood_solid_lpb_date_invoice']));
        
        // Response sukses
        echo json_encode([
            'success' => true,
            'message' => 'Data LPB berhasil diambil',
            'lpb' => $lpb_data,
            'details' => $details,
            'summary' => [
                'total_items' => $total_items,
                'total_volume' => round($total_volume, 3) // Round to 3 decimal places
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan'
    ]);
}

// Tutup koneksi
mysqli_close($conn);
?>