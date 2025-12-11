<?php
session_start();
require '../../inc/config.php';
require '../../inc/config_odoo.php';

// Start Session
$username = $_SESSION['username'] ?? '';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid']);
    exit;
}

// Validasi session username
if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Session tidak valid atau telah berakhir']);
    exit;
}

// Ambil data employee dari Odoo dengan field yang diperlukan
$employees = callOdooRead($username, "hr.employee", [], [
    "id", 
    "name", 
    "work_email", 
    "work_phone", 
    "barcode", 
    "image_1920", 
    "company_id", 
    "category_ids",
    "department_id",
    "job_id"
]);

if ($employees === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to connect to Odoo or retrieve data']);
    exit;
}

// Validasi jika tidak ada data employee
if (empty($employees)) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada data employee yang ditemukan']);
    exit;
}

// Ambil data company dari Odoo
$companies = callOdooRead($username, "res.company", [], ["id", "name"]);
$company_map = [];
if (is_array($companies)) {
    foreach ($companies as $company) {
        $company_map[$company['id']] = $company['name'] ?? '';
    }
}

// Ambil data employee category dari Odoo
$categories = callOdooRead($username, "hr.employee.category", [], ["id", "name"]);
$category_map = [];
if (is_array($categories)) {
    foreach ($categories as $category) {
        $category_map[$category['id']] = $category['name'] ?? '';
    }
}

// Konfigurasi folder image
$image_dir = '../../assets/img/employees/';
if (!file_exists($image_dir)) {
    if (!mkdir($image_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat folder images']);
        exit;
    }
}

// Hapus semua file image di folder employees terlebih dahulu
$files = glob($image_dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

// TRUNCATE table employee untuk reset semua data
if (!$conn->query("TRUNCATE TABLE employee")) {
    echo json_encode(['success' => false, 'message' => 'Failed to truncate table: ' . $conn->error]);
    exit;
}

$inserted = 0;
$errors = 0;
$error_messages = [];

// Siapkan query INSERT
$stmt = $conn->prepare("
    INSERT INTO employee (id_employee, name, email, phone, barcode, image_1920, company_id, category_ids, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

// CEK ERROR PREPARE
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
    exit;
}

foreach ($employees as $emp) {
    // Validasi data required
    if (empty($emp['id']) || empty($emp['name'])) {
        $errors++;
        continue;
    }
    
    $odoo_id = intval($emp['id']);
    $name    = $emp['name'] ?? '';
    $email   = $emp['work_email'] ?? '';
    $phone   = $emp['work_phone'] ?? '';
    $barcode = $emp['barcode'] ?? '';
    $image_base64 = $emp['image_1920'] ?? '';
    
    // Handle company_id - ambil nama company
    $company_name = '';
    if (!empty($emp['company_id']) && is_array($emp['company_id'])) {
        $company_id = $emp['company_id'][0];
        $company_name = $company_map[$company_id] ?? $emp['company_id'][1] ?? '';
    }
    
    // Handle category_ids - ambil nama categories
    $category_names = [];
    if (!empty($emp['category_ids']) && is_array($emp['category_ids'])) {
        foreach ($emp['category_ids'] as $category_id) {
            if (isset($category_map[$category_id])) {
                $category_names[] = $category_map[$category_id];
            }
        }
    }
    $category_names_str = implode(', ', $category_names);
    
    // Potong string jika terlalu panjang untuk varchar(50)
    if (strlen($company_name) > 50) {
        $company_name = substr($company_name, 0, 50);
    }
    if (strlen($category_names_str) > 50) {
        $category_names_str = substr($category_names_str, 0, 50);
    }
    
    // Process image - simpan sebagai filename (barcode.jpg)
    $image_filename = null;
    if (!empty($barcode)) {
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $barcode) . '.jpg';
        $full_path = $image_dir . $filename;
        
        if (!empty($image_base64)) {
            $image_data = base64_decode($image_base64);
            if ($image_data !== false && file_put_contents($full_path, $image_data)) {
                $image_filename = $filename;
                compressImage($full_path, $full_path, 80);
            }
        } else {
            // Jika tidak ada image dari Odoo, buat file kosong atau skip
            $image_filename = $filename;
        }
    }
    
    // Jika barcode kosong, gunakan ID employee sebagai fallback
    if (empty($barcode) && !empty($image_base64)) {
        $filename = 'emp_' . $odoo_id . '.jpg';
        $full_path = $image_dir . $filename;
        
        $image_data = base64_decode($image_base64);
        if ($image_data !== false && file_put_contents($full_path, $image_data)) {
            $image_filename = $filename;
            compressImage($full_path, $full_path, 80);
        }
    }
    
    // bind parameter - image_1920 sekarang sebagai string (filename)
    $bind_result = $stmt->bind_param("isssssss", 
        $odoo_id, 
        $name, 
        $email, 
        $phone, 
        $barcode, 
        $image_filename, 
        $company_name, 
        $category_names_str
    );
    
    if (!$bind_result) {
        error_log("Failed to bind parameters for employee $odoo_id: " . $stmt->error);
        $errors++;
        $error_messages[] = "Employee $odoo_id: " . $stmt->error;
        continue;
    }

    if ($stmt->execute()) {
        $inserted++;
    } else {
        error_log("Error inserting employee $odoo_id: " . $stmt->error);
        $errors++;
        $error_messages[] = "Employee $odoo_id: " . $stmt->error;
    }
}

$stmt->close();
mysqli_close($conn);

// Tambahkan informasi tentang total data
$total_data = count($employees);

$response = [
    'success' => true, 
    'message' => "Employees synced successfully. Total: $total_data, Inserted: $inserted, Errors: $errors",
    'data' => [
        'total' => $total_data,
        'inserted' => $inserted,
        'errors' => $errors,
        'companies_found' => count($company_map),
        'categories_found' => count($category_map)
    ]
];

// Tambahkan error messages jika ada
if (!empty($error_messages)) {
    $response['error_details'] = array_slice($error_messages, 0, 10);
}

echo json_encode($response);

/**
 * Fungsi untuk kompres image
 */
function compressImage($source, $destination, $quality) {
    if (!file_exists($source)) {
        return false;
    }
    
    $info = @getimagesize($source);
    if ($info === false) {
        return false;
    }
    
    switch ($info['mime']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if ($image === false) {
        return false;
    }
    
    $result = false;
    switch ($info['mime']) {
        case 'image/jpeg':
            $result = imagejpeg($image, $destination, $quality);
            break;
        case 'image/png':
            $png_quality = 9 - round(($quality * 9) / 100);
            $result = imagepng($image, $destination, $png_quality);
            break;
        case 'image/gif':
            $result = imagegif($image, $destination);
            break;
        case 'image/webp':
            $result = imagewebp($image, $destination, $quality);
            break;
    }
    
    imagedestroy($image);
    return $result;
}
?>