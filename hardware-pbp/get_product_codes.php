<?php
session_start();
require_once '../inc/config_odoo.php';
header('Content-Type: application/json');
$username    = $_SESSION['username'] ?? 'system';

$so = $_GET['so'] ?? ''; // bisa berisi "SO 25/09/00105" atau id
if (!$so) {
    echo json_encode(['success'=>false,'error'=>'Parameter so wajib']);
    exit;
}

try {
    // dapatkan SO record (mencari by name, client_order_ref, po_cust, atau id)
    if (is_numeric($so)) {
        $domain = [['id','=',(int)$so]];
    } else {
        // Try name first
        $domain = [['name','ilike',$so]];
        $so_record = callOdooRead($username, 'sale.order', $domain, ['id','name','order_line']);
        if (empty($so_record)) {
            // Try client_order_ref
            $domain = [['client_order_ref','ilike',$so]];
            $so_record = callOdooRead($username, 'sale.order', $domain, ['id','name','order_line']);
            if (empty($so_record)) {
                // Try po_cust
                $domain = [['po_cust','ilike',$so]];
                $so_record = callOdooRead($username, 'sale.order', $domain, ['id','name','order_line']);
            }
        }
    }

    if (!isset($so_record)) {
        $so_record = callOdooRead($username, 'sale.order', $domain, ['id','name','order_line']);
    }
    if (empty($so_record)) {
        echo json_encode(['success'=>false,'error'=>'SO tidak ditemukan']);
        exit;
    }
    $order_line_ids = $so_record[0]['order_line'] ?? [];
    if (empty($order_line_ids)) {
        echo json_encode(['success'=>true,'data'=>[]]);
        exit;
    }

    // ambil order lines beserta product details
    $lines = callOdooRead($username, 'sale.order.line', [['id','in',$order_line_ids]], ['id','product_id','product_uom_qty','product_uom']);
    $result = [];
    foreach ($lines as $l) {
        if (!empty($l['product_id'])) {
            $prod = callOdooRead($username, 'product.product', [['id','=', $l['product_id'][0]]], ['id','default_code','name']);
            $result[] = [
                'order_line_id' => $l['id'],
                'product_id' => $l['product_id'][0],
                'default_code' => $prod[0]['default_code'] ?? null,
                'name' => $prod[0]['name'] ?? null,
                'qty_order' => $l['product_uom_qty'],
            ];
        }
    }

    echo json_encode(['success'=>true,'data'=>$result]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
