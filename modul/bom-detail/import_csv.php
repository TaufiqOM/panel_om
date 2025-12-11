<?php
require_once '../../inc/config.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['bom_file']) || $_FILES['bom_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file. Pastikan Anda telah memilih file.']);
    exit;
}

$bom_id = isset($_POST['bom_id']) ? (int)$_POST['bom_id'] : 0;
if (empty($bom_id)) {
    echo json_encode(['success' => false, 'message' => 'ID BOM tidak valid atau tidak diterima oleh server.']);
    exit;
}

$file_tmp_path = $_FILES['bom_file']['tmp_name'];
$file_extension = strtolower(pathinfo($_FILES['bom_file']['name'], PATHINFO_EXTENSION));
if ($file_extension !== 'csv') {
    echo json_encode(['success' => false, 'message' => 'Format file tidak valid. Hanya file .csv yang diizinkan.']);
    exit;
}

$conn->begin_transaction();

try {
    // FASE 1: MEMBACA DAN MEMPROSES SELURUH FILE CSV KE MEMORI
    $file = fopen($file_tmp_path, 'r');
    if ($file === false) throw new Exception("Gagal membuka file CSV.");

    $first_line = fgets($file);
    if ($first_line === false) {
        throw new Exception("File CSV kosong atau tidak bisa dibaca.");
    }
    
    $comma_count = substr_count($first_line, ',');
    $semicolon_count = substr_count($first_line, ';');
    $delimiter = ($semicolon_count > $comma_count) ? ';' : ',';
    
    rewind($file);

    $parsed_data = [];
    $is_header = true;
    while (($row_data = fgetcsv($file, 0, $delimiter)) !== false) {
        if ($is_header) {
            $is_header = false;
            continue;
        }
        
        $full_string_col2 = $row_data[1] ?? '';
        $string_no_cab = preg_replace('/^CAB\s+/i', '', $full_string_col2);
        
        $component_name = $string_no_cab;
        $component_group = '';
        $component_description = '';
        if (preg_match('/\((.*?)\)/', $string_no_cab, $group_matches)) {
            $component_group = trim($group_matches[1]);
            $parts = explode($group_matches[0], $string_no_cab, 2);
            $component_name = trim($parts[0]);
            $component_description = trim($parts[1]);
        }

        if (empty(trim($row_data[0] ?? '')) && empty(trim($component_name))) continue;
        
        $full_string_col10 = $row_data[9] ?? '';
        $component_bahan = '';
        $component_kayu = '';
        $component_kayu_detail = '';
        $component_jenis = '';
        if (strpos($full_string_col10, '-') !== false) {
            $parts = explode('-', $full_string_col10, 2);
            $bahan = trim($parts[0]);
            $kayu_part = trim($parts[1]);
            if (preg_match('/(.*?)\s*\((.*?)\)$/', $kayu_part, $kayu_matches)) {
                $detail = trim($kayu_matches[1]);
                $jenis = trim($kayu_matches[2]);
            } else {
                $detail = $kayu_part;
                $jenis = '';
            }
            $component_bahan = $bahan;
            $component_kayu = $bahan;
            $component_kayu_detail = $detail;
            $component_jenis = $jenis;
        } elseif (preg_match('/^(Kayu|Plywood)\s(.*?)\s\((.*?)\)$/i', $full_string_col10, $kayu_matches)) {
            $bahan = trim($kayu_matches[1]);
            $kayu = trim($kayu_matches[2]);
            $jenis = trim($kayu_matches[3]);
            $component_bahan = $bahan;
            $component_kayu = $bahan;
            $component_kayu_detail = $kayu;
            $component_jenis = $jenis;
        } else {
            $component_bahan = $full_string_col10;
            $component_kayu = $full_string_col10;
            $component_kayu_detail = $full_string_col10;
        }
        
        $parsed_data[] = [
            'bom_component_name' => $component_name,
            'bom_component_bahan' => $component_bahan,
            'bom_component_kayu' => $component_kayu,
            'bom_component_kayu_detail' => $component_kayu_detail,
            'bom_component_jenis' => $component_jenis,
            'bom_component_qty' => (int)($row_data[2] ?? 0),
            'bom_component_panjang' => (int)($row_data[6] ?? 0),
            'bom_component_lebar' => (int)($row_data[7] ?? 0),
            'bom_component_tebal' => (int)($row_data[8] ?? 0),
            'bom_component_description' => $component_description,
            'bom_component_group' => $component_group
        ];
    }
    fclose($file);

    // FASE 2: MENGURUTKAN DAN MERESTRUKTURISASI DATA
    usort($parsed_data, function($a, $b) {
        $group_comparison = strcmp($a['bom_component_group'], $b['bom_component_group']);
        if ($group_comparison !== 0) {
            return $group_comparison;
        }
        return strcmp($a['bom_component_name'], $b['bom_component_name']);
    });

    $data_to_insert = [];
    $current_group = null;
    foreach ($parsed_data as $item) {
        if ($item['bom_component_group'] !== $current_group) {
            $current_group = $item['bom_component_group'];
            
            // --- PERUBAHAN DI SINI ---
            $data_to_insert[] = [
                'bom_component_name' => 'CAB ' . $current_group,
                'bom_component_bahan' => 'Grup', // Diubah dari $current_group
                'bom_component_kayu' => 'Grup', // Diubah dari $current_group
                'bom_component_kayu_detail' => 'Grup', // Diubah dari $current_group
                'bom_component_jenis' => '',
                'bom_component_qty' => 0,
                'bom_component_panjang' => 0,
                'bom_component_lebar' => 0,
                'bom_component_tebal' => 0,
                'bom_component_description' => '',
                'bom_component_group' => $current_group
            ];
        }
        $data_to_insert[] = $item;
    }

    // FASE 3: MENYIMPAN DATA YANG SUDAH DIURUTKAN KE DATABASE
    $counter_sql = "SELECT MAX(CAST(SUBSTRING_INDEX(bom_component_number, '-', -1) AS UNSIGNED)) as last_num FROM bom_component WHERE bom_id = ?";
    $stmt_counter = $conn->prepare($counter_sql);
    if ($stmt_counter === false) throw new Exception("Gagal mempersiapkan statement untuk counter: " . $conn->error);
    
    $stmt_counter->bind_param("i", $bom_id);
    $stmt_counter->execute();
    $result = $stmt_counter->get_result()->fetch_assoc();
    $last_num = $result['last_num'] ?? 0;
    $stmt_counter->close();

    $row_counter = (int)$last_num + 1;

    $sql = "INSERT INTO bom_component (
                bom_id, bom_component_number, bom_component_name, bom_component_bahan, bom_component_kayu,
                bom_component_kayu_detail, bom_component_jenis, bom_component_qty, bom_component_panjang,
                bom_component_lebar, bom_component_tebal, bom_component_description, bom_component_group
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) throw new Exception("Gagal mempersiapkan statement SQL: " . $conn->error);

    $inserted_rows = 0;

    foreach ($data_to_insert as $item) {
        $formatted_counter = str_pad($row_counter, 4, '0', STR_PAD_LEFT);
        $component_number = $bom_id . '-' . $formatted_counter;
        $jenis_for_bind = substr($item['bom_component_jenis'], 0, 1);

        $stmt->bind_param("issssssiiiiss", 
            $bom_id,
            $component_number,
            $item['bom_component_name'],
            $item['bom_component_bahan'],
            $item['bom_component_kayu'],
            $item['bom_component_kayu_detail'],
            $jenis_for_bind,
            $item['bom_component_qty'],
            $item['bom_component_panjang'],
            $item['bom_component_lebar'],
            $item['bom_component_tebal'],
            $item['bom_component_description'],
            $item['bom_component_group']
        );

        if (!$stmt->execute()) throw new Exception("Gagal menyimpan data ke database: " . $stmt->error);
        
        $inserted_rows++;
        $row_counter++;
    }

    $stmt->close();
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "$inserted_rows total baris (termasuk header grup) berhasil diimpor untuk BOM ID: $bom_id."
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    if ($conn) $conn->close();
}
?>