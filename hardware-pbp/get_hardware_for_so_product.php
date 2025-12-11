<?php
session_start();
require_once '../inc/config_odoo.php';
require_once '../inc/config.php';
header('Content-Type: application/json');
$username    = $_SESSION['username'] ?? 'system';

$so = $_GET['so'] ?? '';
$product_code = $_GET['product'] ?? '';

if (!$so || !$product_code) {
    echo json_encode(['success'=>false,'error'=>'SO dan product wajib (parameter so dan product)']);
    exit;
}

try {
    // 1. ambil SO
    if (is_numeric($so)) {
        $domain = [['id','=',(int)$so]];
    } else {
        // Try name first
        $domain = [['name','ilike',$so]];
        $so_rec = callOdooRead($username, 'sale.order', $domain, ['id','name','mrp_production_ids']);
        if (empty($so_rec)) {
            // Try client_order_ref
            $domain = [['client_order_ref','ilike',$so]];
            $so_rec = callOdooRead($username, 'sale.order', $domain, ['id','name','mrp_production_ids']);
            if (empty($so_rec)) {
                // Try po_cust
                $domain = [['po_cust','ilike',$so]];
                $so_rec = callOdooRead($username, 'sale.order', $domain, ['id','name','mrp_production_ids']);
            }
        }
    }

    if (!isset($so_rec)) {
        $so_rec = callOdooRead($username, 'sale.order', $domain, ['id','name','mrp_production_ids']);
    }

    if (empty($so_rec)) {
        echo json_encode(['success'=>false,'error'=>'Order tidak ditemukan']);
        exit;
    }
    $mrp_ids = $so_rec[0]['mrp_production_ids'] ?? [];
    if (empty($mrp_ids)) {
        echo json_encode(['success'=>true,'hardware'=>[]]);
        exit;
    }

    // 2. ambil MO terkait
    $mos = callOdooRead($username, 'mrp.production', [['id','in',$mrp_ids]], ['id','product_id','picking_ids']);

    if (empty($mos)) {
        echo json_encode(['success'=>true,'hardware'=>[]]);
        exit;
    }

    $hardwareList = [];
    $seen_lines = []; // untuk dedup line_id
    
    foreach ($mos as $mo) {
        // skip MO tanpa product
        if (empty($mo['product_id'])) continue;
        
        // ambil product.default_code untuk MO
        $prod = callOdooRead($username, 'product.product', [['id','=', $mo['product_id'][0]]], ['id','default_code','name']);
        

        $mo_product_code = $prod[0]['default_code'] ?? null;

        // hanya lanjut jika product default_code sama dengan product_code parameter
        if ($mo_product_code !== $product_code) continue;

        $mo_id = $mo['id'];
        $picking_ids = $mo['picking_ids'] ?? [];
        if (empty($picking_ids)) continue;

        // ambil pickings -> move_ids
        $pickings = callOdooRead($username, 'stock.picking', [['id','in',$picking_ids], ['state', '!=', 'done']], ['id','move_ids']);
        foreach ($pickings as $picking) {
            $picking_id = $picking['id'];
            $move_ids = $picking['move_ids'] ?? [];
            if (empty($move_ids)) continue;

            // ambil moves
            $moves = callOdooRead($username, 'stock.move', [['id','in',$move_ids]], ['id','product_id','quantity','product_uom']);
            foreach ($moves as $move) {
                $move_id = $move['id'];
                if (isset($seen_lines[$move_id])) continue; // dedup

                if (empty($move['product_id'])) continue;
                $mp = callOdooRead($username, 'product.product', [['id','=', $move['product_id'][0]]], ['id','default_code','name','type']);
                if (empty($mp)) continue;

                // Note: Assuming all products in moves are hardware for this flow
                $hardwareList[] = [
                    'mo_id' => $mo_id,
                    'picking_id' => $picking_id,
                    'line_id' => $move_id, // using move_id as line_id for update
                    'product_id' => $mp[0]['id'],
                    'hardware_code' => $mp[0]['default_code'] ?? null,
                    'hardware_name' => $mp[0]['name'] ?? null,
                    'qty' => floatval($move['quantity'] ?? 0),
                    'hardware_uom' => is_array($move['product_uom']) ? ($move['product_uom'][1] ?? '') : ''
                ];

                $seen_lines[$move_id] = true;
            }
        }
    }

    echo json_encode(['success'=>true,'hardware'=>$hardwareList]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
