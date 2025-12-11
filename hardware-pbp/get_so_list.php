<?php
session_start();
require_once '../inc/config_odoo.php';
header('Content-Type: application/json');
$username    = $_SESSION['username'] ?? 'system';

try {
    // Ambil semua SO yang confirmed / ordo sesuai kebutuhan. Ubah domain sesuai requirement.
    $domain = [['state', '!=', 'cancel']];
    $fields = ['id', 'name', 'partner_id', 'date_order', 'client_order_ref'];

    $sos = callOdooRead($username, 'sale.order', $domain, $fields);
        // Ubah hasil supaya menampilkan client_order_ref jika ada
    $data = array_map(function($so) {
        $display_name = $so['client_order_ref'] ?: $so['name']; // fallback ke SO name kalau kosong
        return [
            'id' => $so['id'],
            'display_name' => $display_name,
            'partner_id' => $so['partner_id'],
            'date_order' => $so['date_order'],
            'client_order_ref' => $so['client_order_ref'] ?? ''
        ];
    }, $sos);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
