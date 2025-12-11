<?php
session_start();
require __DIR__ . '/../../../inc/config_odoo.php';
require __DIR__ . '/../../../inc/config.php';
require __DIR__ . '/../../../inc/csrf.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!CSRF::validateToken($csrf_token)) {
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

$so_id       = intval($_POST['so_id'] ?? 0);
$line_id     = intval($_POST['line_id'] ?? 0);
$product_id  = intval($_POST['product_id'] ?? 0);
$so_name     = $_POST['so_name'] ?? '';
$customer_name     = $_POST['customer_name'] ?? '';
$product_name = $_POST['product_name'] ?? '';
$qty_order   = intval($_POST['qty_order'] ?? 0);
$qty_generate = intval($_POST['qty'] ?? 0);
$no_ttb = $_POST['no_ttb'] ?? '';

$username    = $_SESSION['username'] ?? 'system';

if ($so_id <= 0 || $line_id <= 0 || $product_id <= 0) {
    echo json_encode(["success" => false, "message" => "Data tidak valid!"]);
    exit;
}

if ($no_ttb === '') {
    echo json_encode(["success" => false, "message" => "Nomor TTB wajib diisi"]);
    exit;
}

// Hitung sudah berapa barcode yang digenerate
$stmt = $conn->prepare("SELECT COUNT(*) FROM barcode_item WHERE sale_order_line_id=?");
$stmt->bind_param("i", $line_id);
$stmt->execute();
$stmt->bind_result($qty_existing);
$stmt->fetch();
$stmt->close();

if ($qty_existing + $qty_generate > $qty_order) {
    echo json_encode(["success" => false, "message" => "Jumlah melebihi qty order"]);
    exit;
}

// Get order details for additional data
$order_details = callOdooRead($username, "sale.order", [["id", "=", $so_id]], [
    "date_order",
    "due_date_order",
    "partner_id",
    "client_order_ref"
]);

$order_date = $order_details[0]['date_order'] ?? null;
$order_due_date = $order_details[0]['due_date_order'] ?? null;
$client_order_ref = $order_details[0]['client_order_ref'] ?? null;

// Get country from partner
$partner_country = '';
if (!empty($order_details[0]['partner_id'][0])) {
    $partner_data = callOdooRead($username, "res.partner", [["id", "=", $order_details[0]['partner_id'][0]]], ["country_id"]);
    if (!empty($partner_data[0]['country_id'])) {
        $partner_country = $partner_data[0]['country_id'][1] ?? '';
    }
}

// Get item description from sale order line
$line_details = callOdooRead($username, "sale.order.line", [["id", "=", $line_id]], ["name", "info_to_production", "info_to_buyer", "finish_product"]);

// Get product details for product_ref and image_1920 - DIPINDAHKAN KE ATAS
$product_details = callOdooRead($username, "product.product", [["id", "=", $product_id]], ["default_code", "image_1920", "product_tmpl_id"]);
$product_ref = $product_details[0]['default_code'] ?? '';
$image_1920 = $product_details[0]['image_1920'] ?? null;

// 🔎 Cari semua MO untuk SO ini
$so_data = callOdooRead($username, "sale.order", [["id", "=", $so_id]], ["mrp_production_ids"]);

if (empty($so_data)) {
    echo json_encode(["success" => false, "message" => "SO tidak ditemukan di Odoo"]);
    exit;
}

// Jika MO kosong
$mo_ids = $so_data[0]['mrp_production_ids'] ?? [];
if (empty($mo_ids)) {
    echo json_encode(["success" => false, "message" => "MO belum terbentuk untuk SO ini"]);
    exit;
}

// 🔎 Ambil detail MO untuk filter mana yang masih kosong lot
$mo_list = callOdooRead($username, "mrp.production", [["id", "in", $mo_ids]], ["id", "name", "product_id", "lot_producing_id", "state"]);

// Cek MO yang masih available (Masih belum ada lot serial number) dan sesuai product_id
$mo_available = [];
foreach ($mo_list as $mo) {
    $mo_product_id = is_array($mo['product_id']) ? $mo['product_id'][0] : $mo['product_id'];
    if (
        $mo['lot_producing_id'] == false &&
        !in_array($mo['state'], ['cancel', 'done']) &&
        $mo_product_id == $product_id
    ) {
        $mo_available[] = $mo['id'];
    }
}

if (count($mo_available) < $qty_generate) {
    echo json_encode(["success" => false, "message" => "MO yang masih kosong hanya " . count($mo_available) . " tapi diminta $qty_generate"]);
    exit;
}

// 🚀 Generate barcode dengan sistem prefix
$conn->begin_transaction();
try {
    // cek lot record
    $stmt = $conn->prepare("SELECT * FROM barcode_lot WHERE sale_order_line_id=? LIMIT 1");
    $stmt->bind_param("i", $line_id);
    $stmt->execute();
    $lot = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Set finishing value
    $finishing_raw = $line_details[0]['finish_product'] ?? '';
    $finishing = is_array($finishing_raw) ? ($finishing_raw[1] ?? '') : $finishing_raw;

    // FUNGSI UPDATE GAMBAR DAN FINISHING JIKA MASIH NULL
    $updateImageIfNeeded = function ($lot_id_local, $image_1920, $finishing) use ($conn) {
        if ($image_1920 === null) {
            // Jika tidak ada gambar, tetap cek finishing
        }

        // Cek apakah gambar saat ini NULL
        $stmt_check = $conn->prepare("SELECT image_1920 FROM barcode_lot WHERE id = ?");
        $stmt_check->bind_param("i", $lot_id_local);
        $stmt_check->execute();
        $stmt_check->store_result();
        $stmt_check->bind_result($current_image);
        $stmt_check->fetch();
        $stmt_check->close();

        // Jika gambar saat ini NULL, lakukan update
        if ($current_image === null && $image_1920 !== null) {
            $stmt_update = $conn->prepare("UPDATE barcode_lot SET image_1920 = ? WHERE id = ?");
            if (!$stmt_update) {
                throw new Exception("Prepare update image failed: " . $conn->error);
            }

            $null = NULL;
            $stmt_update->bind_param("bi", $null, $lot_id_local);
            $stmt_update->send_long_data(0, $image_1920);
            $stmt_update->execute();
            $stmt_update->close();
        }

        // Selalu cek dan update finishing jika NULL atau kosong
        $stmt_check_f = $conn->prepare("SELECT finishing FROM barcode_lot WHERE id = ?");
        $stmt_check_f->bind_param("i", $lot_id_local);
        $stmt_check_f->execute();
        $stmt_check_f->bind_result($current_finishing);
        $stmt_check_f->fetch();
        $stmt_check_f->close();

        if ($current_finishing === null || $current_finishing === '') {
            $stmt_update_f = $conn->prepare("UPDATE barcode_lot SET finishing=? WHERE id=?");
            $stmt_update_f->bind_param("si", $finishing, $lot_id_local);
            $stmt_update_f->execute();
            $stmt_update_f->close();
        }
    };

    if ($lot) {
        $lot_id_local = $lot['id'];
        $prefix_code  = $lot['prefix_code'];
        $last_number  = intval($lot['last_number']);

        // UPDATE GAMBAR DAN FINISHING JIKA MASIH NULL
        $updateImageIfNeeded($lot_id_local, $image_1920, $finishing);

        if ($prefix_code === null) {
            // Generate new prefix_code
            $res = $conn->query("SELECT MAX(prefix_code) as max_prefix FROM barcode_lot");
            $row = $res->fetch_assoc();
            $prefix_code = $row['max_prefix'] ? $row['max_prefix'] + 1 : 300002;
            // Update the existing lot record
            $stmt_update = $conn->prepare("UPDATE barcode_lot SET prefix_code=? WHERE id=?");
            $stmt_update->bind_param("ii", $prefix_code, $lot_id_local);
            $stmt_update->execute();
            $stmt_update->close();
        }
    } else {
        // ambil prefix terakhir
        $res = $conn->query("SELECT MAX(prefix_code) as max_prefix FROM barcode_lot");
        $row = $res->fetch_assoc();
        $prefix_code = $row['max_prefix'] ? $row['max_prefix'] + 1 : 300002;
        $last_number = 0;
        $info_to_production = $line_details[0]['info_to_production'] ?? '';
        $info_to_buyer      = $line_details[0]['info_to_buyer'] ?? '';
        $finishing_raw = $line_details[0]['finish_product'] ?? '';

        $finishing = is_array($finishing_raw) ? ($finishing_raw[1] ?? '') : $finishing_raw;


        $stmt = $conn->prepare("INSERT INTO barcode_lot
            (sale_order_id, sale_order_line_id, sale_order_name, sale_order_ref, customer_name, product_id, product_name, product_ref, image_1920, finishing, prefix_code, qty_order, last_number, order_date, order_due_date, country, info_to_production, info_to_buyer)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        if ($image_1920 !== null) {
            // Jika ada gambar, gunakan bind_param dengan send_long_data
            $null = NULL;
            $stmt->bind_param(
                "iisssissbisiiisssss",
                $so_id,
                $line_id,
                $so_name,
                $client_order_ref,
                $customer_name,
                $product_id,
                $product_name,
                $product_ref,
                $null, // image_1920 akan diisi dengan send_long_data
                $finishing,
                $prefix_code,
                $qty_order,
                $last_number,
                $order_date,
                $order_due_date,
                $partner_country,
                $info_to_production,
                $info_to_buyer
            );
            $stmt->send_long_data(8, $image_1920);
        } else {
            // Jika tidak ada gambar, insert tanpa gambar
            $stmt->bind_param(
                "iisssisssiiisssss",
                $so_id,
                $line_id,
                $so_name,
                $client_order_ref,
                $customer_name,
                $product_id,
                $product_name,
                $product_ref,
                $finishing,
                $prefix_code,
                $qty_order,
                $last_number,
                $order_date,
                $order_due_date,
                $partner_country,
                $info_to_production,
                $info_to_buyer
            );
        }

        $stmt->execute();
        $lot_id_local = $stmt->insert_id;
        $stmt->close();
    }

    $createdLots = [];
    for ($i = 1; $i <= $qty_generate; $i++) {
        $mo_id = $mo_available[$i - 1];
        $seq   = $last_number + $i;

        // format: 5digit-5digit-5digit
        $lot_code = sprintf("%06d-%04d-%04d", $prefix_code, $seq, $qty_order);

        // 1. buat lot di Odoo
        $lot_id = callOdooCreate($username, "stock.lot", [
            "product_id" => $product_id,
            "name"       => $lot_code,
             "ref"        => $no_ttb
        ]);
        if (!$lot_id) {
            throw new Exception("Gagal membuat lot di Odoo");
        }

        // 2. assign lot ke MO
        $ok = callOdooWrite($username, "mrp.production", [$mo_id], ["lot_producing_id" => $lot_id]);
        if (!$ok) {
            throw new Exception("Gagal assign lot ke MO $mo_id");
        }

        // 3. simpan item barcode
        $stmt = $conn->prepare("INSERT INTO barcode_item 
                (lot_id, barcode, customer_name, sequence_no, product_id, mrp_id, sale_order_id, sale_order_line_id, no_ttb, created_by, created_at) 
                VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");

        if (!$stmt) {
            die("SQL error: " . $conn->error);
        }

        $stmt->bind_param("issiiiisss", $lot_id_local, $lot_code, $customer_name, $seq, $product_id, $mo_id, $so_id, $line_id, $no_ttb, $username);
        $stmt->execute();
        $stmt->close();

        $createdLots[] = ["mo_id" => $mo_id, "lot_id" => $lot_id, "lot_code" => $lot_code];
    }

    // update last_number
    $new_last = $last_number + $qty_generate;
    $stmt = $conn->prepare("UPDATE barcode_lot SET last_number=? WHERE id=?");
    $stmt->bind_param("ii", $new_last, $lot_id_local);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Berhasil generate $qty_generate barcode", "data" => $createdLots, "prefix_code" => $prefix_code]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit;
}
