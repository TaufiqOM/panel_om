<?php
session_start();
require_once '../../inc/config_odoo.php';

function detectMimeFromBase64($base64)
{
    $base64 = ltrim($base64);
    if (strpos($base64, 'iVBORw0KGgo') === 0) {
        return 'image/png';
    } elseif (strpos($base64, '/9j/') === 0) {
        return 'image/jpeg';
    } elseif (strpos($base64, 'PD94') === 0) {
        return 'image/svg+xml';
    } else {
        return 'application/octet-stream'; // fallback
    }
}

header('Content-Type: application/json; charset=utf-8');

$username = $_SESSION['username'] ?? '';

if (!$username) {
    http_response_code(401);
    echo json_encode(['error' => 'Session expired']);
    exit;
}

// Ambil BOM dari Odoo
$boms = callOdooRead($username, "mrp.bom", [], ["id", "code", "product_tmpl_id"]);

if ($boms === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to connect to Odoo or retrieve data']);
    exit;
}

// Kumpulkan product template IDs dari BOM
$product_ids = [];
foreach ($boms as $bom) {
    if (is_array($bom['product_tmpl_id'])) {
        $product_ids[] = $bom['product_tmpl_id'][0]; // ID product template
    } elseif (!empty($bom['product_tmpl_id'])) {
        $product_ids[] = $bom['product_tmpl_id'];
    }
}
$product_ids = array_unique($product_ids);

// Ambil detail product template dari Odoo
$products = [];
$variants = [];
if (!empty($product_ids)) {
    $products_data = callOdooRead(
        $username,
        "product.template",
        [['id', 'in', $product_ids]],
        ['id', 'name', 'image_1920', 'product_variant_id']
    );

    if ($products_data !== false) {
        $products = array_column($products_data, null, 'id');

        // Fetch images separately
        $image_ids = array_keys($products);
        if (!empty($image_ids)) {
            $images_data = callOdooRead(
                $username,
                "product.template",
                [['id', 'in', $image_ids]],
                ['id', 'image_1920']
            );
            if ($images_data !== false) {
                $images = array_column($images_data, 'image_1920', 'id');
                foreach ($images as $id => $img) {
                    if (isset($products[$id])) {
                        $products[$id]['image_1920'] = $img;
                    }
                }
            }
        }

        // Ambil semua variant id
        $variant_ids = [];
        foreach ($products_data as $prod) {
            if (!empty($prod['product_variant_id'][0])) {
                $variant_ids[] = $prod['product_variant_id'][0];
            }
        }
        $variant_ids = array_unique($variant_ids);

        // Ambil detail product variant untuk default_code
        if (!empty($variant_ids)) {
            $variants_data = callOdooRead(
                $username,
                "product.product",
                [['id', 'in', $variant_ids]],
                ['id', 'default_code']
            );
            if ($variants_data !== false) {
                $variants = array_column($variants_data, null, 'id');
            }
        }
    }
}

// Normalisasi hasil untuk output
$bom_list = [];
foreach ($boms as $bom) {
    $bom_id = $bom['id'];
    $code = $bom['code'] ?? '';

    // product_tmpl_id bisa berbentuk array [id, name] atau langsung id
    $product_id = is_array($bom['product_tmpl_id'])
        ? $bom['product_tmpl_id'][0]
        : $bom['product_tmpl_id'];

    // Extract product name from BOM data as fallback
    $product_name_from_bom = is_array($bom['product_tmpl_id'])
        ? $bom['product_tmpl_id'][1]
        : '';

    $product = $products[$product_id] ?? [];
    $variant_id = $product['product_variant_id'][0] ?? null;
    $variant = $variant_id && isset($variants[$variant_id]) ? $variants[$variant_id] : [];

    // Determine product name
    $product_name = $product['name'] ?? $product_name_from_bom;

    // Extract product reference from product name if in brackets
    $product_reference = '';
    if (preg_match('/^\[([^\]]+)\]/', $product_name, $matches)) {
        $product_reference = $matches[1];
    }

    // Handle product image with MIME detection
    $product_img_base64 = $product['image_1920'] ?? '';
    $product_img = '';
    if (!empty($product_img_base64)) {
        $mime = detectMimeFromBase64($product_img_base64);
        $product_img = "data:{$mime};base64,{$product_img_base64}";
    }

    $bom_list[] = [
        'bom_id'            => $bom_id,
        'bom_code'          => $code,
        'product_tmpl_id'   => $product_id,
        'product_name_odoo' => $product_name,
        'product_reference' => $variant['default_code'] ?? $product_reference,
        'product_name'      => $product_name,
        'product_img'       => $product_img
    ];
}

// Output JSON
echo json_encode([
    'success' => true,
    'boms' => $bom_list
], JSON_UNESCAPED_UNICODE);
