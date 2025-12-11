<?php
require __DIR__ . '/../inc/config.php';

// Ambil keyword search
$search = $_GET['search'] ?? '';
$search_param = "%$search%";

// Query dengan JOIN ke barcode_lot
$query = "
    SELECT 
        bi.lot_id,
        bi.mrp_id,
        bi.barcode,
        bi.sequence_no,
        bi.product_id,
        bi.sale_order_id,
        bi.customer_name,
        bi.sale_order_line_id,
        bl.prefix_code,
        bl.last_number,
        bl.qty_order
    FROM barcode_item AS bi
    LEFT JOIN barcode_lot AS bl ON bi.lot_id = bl.id
    WHERE 
        bi.lot_id LIKE ? OR
        bi.mrp_id LIKE ? OR
        bi.barcode LIKE ? OR
        bi.sequence_no LIKE ? OR
        bi.product_id LIKE ? OR
        bi.sale_order_id LIKE ? OR
        bi.customer_name LIKE ? OR
        bi.sale_order_line_id LIKE ? OR
        bl.prefix_code LIKE ? OR
        bl.last_number LIKE ? OR
        bl.qty_order LIKE ?
    ORDER BY bi.id DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param(
    "sssssssssss",
    $search_param, $search_param, $search_param, $search_param,
    $search_param, $search_param, $search_param, $search_param,
    $search_param, $search_param, $search_param
);

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>List Barcode Item</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">
    <h2 class="mb-4">List Barcode Item</h2>

    <!-- Search Box -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input 
                type="text" 
                name="search" 
                value="<?= htmlspecialchars($search) ?>" 
                class="form-control" 
                placeholder="Cari barcode, lot_id, mrp_id, customer..."
            >
            <button class="btn btn-primary">Search</button>
            <a href="list.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <!-- Table -->
    <div class="card shadow">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-bordered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Lot ID</th>
                            <th>MRP ID</th>
                            <th>Barcode</th>
                            <th>Sequence No</th>
                            <th>Product ID</th>
                            <th>SO ID</th>
                            <th>Customer</th>
                            <th>SO Line ID</th>
                            
                            <!-- Tambahan relasi barcode_lot -->
                            <th>Prefix Code</th>
                            <th>Last Number</th>
                            <th>Qty Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['lot_id']) ?></td>
                                    <td><?= htmlspecialchars($row['mrp_id']) ?></td>
                                    <td><?= htmlspecialchars($row['barcode']) ?></td>
                                    <td><?= htmlspecialchars($row['sequence_no']) ?></td>
                                    <td><?= htmlspecialchars($row['product_id']) ?></td>
                                    <td><?= htmlspecialchars($row['sale_order_id']) ?></td>
                                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($row['sale_order_line_id']) ?></td>

                                    <!-- Dari tabel barcode_lot -->
                                    <td><?= htmlspecialchars($row['prefix_code']) ?></td>
                                    <td><?= htmlspecialchars($row['last_number']) ?></td>
                                    <td><?= htmlspecialchars($row['qty_order']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center py-3">
                                    Tidak ada data ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

</body>
</html>
