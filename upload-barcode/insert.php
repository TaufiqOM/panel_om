<?php
session_start();
// require_once '../../inc/config.php';
require __DIR__ . '/../inc/config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (!isset($_SESSION['preview_data']) || empty($_SESSION['preview_data'])) {
    die("Tidak ada data untuk diinsert. Silakan upload file terlebih dahulu.");
}

$data = $_SESSION['preview_data'];
$headers = $data[0]; // First row as headers
$rows = array_slice($data, 1); // Data rows

// Map headers to database columns (assuming standard order)
$column_mapping = [
    'lot_id' => 'lot_id',
    'barcode' => 'barcode',
    'sequence_no' => 'sequence_no',
    'product_id' => 'product_id',
    'mrp_id' => 'mrp_id',
    'sale_order_id' => 'sale_order_id',
    'customer_name' => 'customer_name',
    'sale_order_line_id' => 'sale_order_line_id',
    // Add more mappings if needed
];

$inserted_count = 0;
$errors = [];

foreach ($rows as $row) {
    // Map row data to columns
    $insert_data = [];
    foreach ($headers as $index => $header) {
        $header_lower = strtolower(trim($header));
        if (isset($column_mapping[$header_lower]) && isset($row[$index])) {
            $insert_data[$column_mapping[$header_lower]] = trim($row[$index]);
        }
    }

    // Set default values
    $insert_data['created_at'] = date('Y-m-d H:i:s');
    $insert_data['created_by'] = $_SESSION['username'] ?? 'system'; // Assuming username is in session

    // Prepare insert query
    $columns = implode(', ', array_keys($insert_data));
    $placeholders = str_repeat('?, ', count($insert_data) - 1) . '?';
    $query = "INSERT INTO barcode_item ($columns) VALUES ($placeholders)";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        $values = array_values($insert_data);
        $stmt->bind_param(str_repeat('s', count($values)), ...$values);
        if ($stmt->execute()) {
            $inserted_count++;
            // ---------------------------------------------------
            //  UPDATE PREFIX_CODE + LAST_NUMBER DI barcode_lot
            // ---------------------------------------------------

            $lot_id = intval($insert_data['lot_id']);
            $barcode = $insert_data['barcode'];
            $seq_no = intval($insert_data['sequence_no']);

            // Ambil prefix dari barcode (angka sebelum '-')
            $prefix_code = null;
            if (preg_match('/^(\d+)-/', $barcode, $m)) {
                $prefix_code = $m[1];
            }

            // Ambil MAX sequence_no berdasarkan lot_id
            $sql_last = $conn->prepare("
                SELECT MAX(sequence_no)
                FROM barcode_item
                WHERE lot_id = ?
            ");
            $sql_last->bind_param("i", $lot_id);
            $sql_last->execute();
            $sql_last->bind_result($max_seq);
            $sql_last->fetch();
            $sql_last->close();

            if (!$max_seq) {
                $max_seq = $seq_no; // fallback
            }

            // Update barcode_lot
            $sql_update = $conn->prepare("
                UPDATE barcode_lot
                SET prefix_code = ?, last_number = ?
                WHERE id = ?
            ");
            $sql_update->bind_param("sii", $prefix_code, $max_seq, $lot_id);
            $sql_update->execute();
            $sql_update->close();
        } else {
            $errors[] = "Error inserting row: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errors[] = "Error preparing statement: " . $conn->error;
    }
}

// Clear session data
unset($_SESSION['preview_data']);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insert Result</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">Hasil Insert Data</h2>
        <div class="card">
            <div class="card-body">
                <?php if ($inserted_count > 0): ?>
                    <div class="alert alert-success">
                        <h5>Sukses!</h5>
                        <p><?php echo $inserted_count; ?> baris data berhasil diinsert ke database.</p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5>Error:</h5>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <a href="index.php" class="btn btn-primary">Upload Lagi</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>