<?php
require __DIR__ . '/../../inc/config_odoo.php';

// Start Session
$username = $_SESSION['username'] ?? '';

// Get partners for filter
$partners = callOdooRead($username, "res.partner", [
    "&", 
    ["category_id", "ilike", "Customer"],
    "&",  
    ["company_type", "=", "company"],
    ["parent_id", "=", false]  
], ["id", "name", "company_type", "parent_id"]);

// Handle filter
$selected_partner_id = $_GET['partner_id'] ?? '';
$late_filter = isset($_GET['late_filter']) && $_GET['late_filter'] === 'on';
$has_filter = !empty($selected_partner_id);
$orders = [];

// Only fetch data if filter is applied
if ($has_filter) {
    // Build filter domain based on selection
    if ($selected_partner_id === 'all') {
        $filter_domain = [["state", "=", "sale"]];
    } else {
         // **PERBAIKAN DI SINI: Ambil semua child contacts dari company yang dipilih**
        $child_partners = callOdooRead($username, "res.partner", [
            ["parent_id", "=", (int)$selected_partner_id]
        ], ["id"]);
        // Kumpulkan semua ID partner (parent + children)
        $partner_ids = [(int)$selected_partner_id];
        foreach ($child_partners as $child) {
            if (isset($child['id'])) {
                $partner_ids[] = (int)$child['id'];
            }
        }
        // Buat domain filter dengan OR condition untuk semua partner IDs
        $filter_domain = [
            ["state", "=", "sale"],
            ["partner_id", "in", $partner_ids]
        ];
    }

    // Get data order dengan filter
    $orders = callOdooRead($username, "sale.order", $filter_domain, [
        "id",
        "name",
        "client_order_ref",
        "partner_id",
        "create_date",
        "user_id",
        "confirmation_date_order",
        "due_date_order",
        "due_date_update_order",
        "note",
        "order_remarks_for_production",
        "order_admin_update",
        "procurement_group_id"
    ]);

    // Get order lines untuk semua order sekaligus untuk efisiensi
    $all_order_lines_data = [];
    if (is_array($orders) && count($orders) > 0) {
        $order_ids = array_column($orders, 'id');
        $all_order_lines_data = callOdooRead($username, "sale.order.line", [
            ["order_id", "in", $order_ids],
            ["product_id", "!=", false]
        ], [
            "id",
            "order_id",
            "product_id",
            "name",
            "supp_order",
            "finish_product",
            "product_uom_qty",
            "product_uom",
            "info_to_production",
            "info_to_buyer",
            "production_opn",
            "production_av",
            "production_raw",
            "production_rpr",
            "production_snd",
            "production_rtr",
            "production_qcin",
            "production_clr",
            "production_qcl",
            "production_finp",
            "production_qfin",
            "production_str",
            "qty_delivered",
            "due_date_item_update"
        ]);
    }

    // Kelompokkan order lines berdasarkan order_id
    $order_lines_by_order = [];
    if (is_array($all_order_lines_data)) {
        foreach ($all_order_lines_data as $line) {
            $order_id = $line['order_id'][0];
            if (!isset($order_lines_by_order[$order_id])) {
                $order_lines_by_order[$order_id] = [];
            }
            $order_lines_by_order[$order_id][] = $line;
        }
    }

    // Tentukan effective due date untuk setiap order berdasarkan prioritas
    $orders_with_effective_dates = [];
    
    foreach ($orders as $order) {
        $order_id = $order['id'];
        $order_lines = $order_lines_by_order[$order_id] ?? [];
        
        // Cari due date berdasarkan prioritas dari semua line items
        $effective_due_date = '';
        $due_date_source = '';
        
        // 1. Prioritas: due_date_item_update dari line manapun
        foreach ($order_lines as $line) {
            if (!empty($line['due_date_item_update']) && $line['due_date_item_update'] !== 'N/A') {
                $effective_due_date = $line['due_date_item_update'];
                $due_date_source = 'Item Update';
                break;
            }
        }
        
        // 2. Prioritas: due_date_update_order dari order
        if (empty($effective_due_date) && !empty($order['due_date_update_order']) && $order['due_date_update_order'] !== 'N/A') {
            $effective_due_date = $order['due_date_update_order'];
            $due_date_source = 'Order Update';
        }
        
        // 3. Prioritas: due_date_order dari order
        if (empty($effective_due_date) && !empty($order['due_date_order']) && $order['due_date_order'] !== 'N/A') {
            $effective_due_date = $order['due_date_order'];
            $due_date_source = 'Original Due Date';
        }
        
        $orders_with_effective_dates[] = [
            'order' => $order,
            'effective_due_date' => $effective_due_date,
            'due_date_source' => $due_date_source,
            'order_lines' => $order_lines
        ];
    }

    // Sort orders by effective due date (ascending - yang paling dekat due date di atas)
    usort($orders_with_effective_dates, function ($a, $b) {
        $dateA = strtotime($a['effective_due_date'] ?? '');
        $dateB = strtotime($b['effective_due_date'] ?? '');
        
        // Handle empty dates - taruh di akhir
        if ($dateA === false) $dateA = PHP_INT_MAX;
        if ($dateB === false) $dateB = PHP_INT_MAX;
        
        return $dateA - $dateB;
    });

    // Replace original orders dengan yang sudah diurutkan
    $orders = array_map(function($item) {
        return $item['order'];
    }, $orders_with_effective_dates);
}

// Function to format date safely
function formatDate($date)
{
    if (empty($date) || $date === 'N/A') {
        return '';
    }
    return date("d M Y", strtotime($date));
}

// Function to calculate week number
function getWeekNumber($date)
{
    if (empty($date) || $date === 'N/A') {
        return '';
    }
    return '(W' . date("W", strtotime($date)) . ')';
}

// Function to calculate days difference (FIXED: + menjadi - dan sebaliknya)
function getDaysDifference($date)
{
    if (empty($date) || $date === 'N/A') {
        return 'N/A';
    }

    $due_date = new DateTime($date);
    $today = new DateTime();

    // Set waktu menjadi 00:00:00 untuk perbandingan yang akurat
    $due_date->setTime(0, 0, 0);
    $today->setTime(0, 0, 0);

    $interval = $today->diff($due_date);

    // Hitung selisih hari dengan memperhitungkan invert
    $days = $interval->days;

    // Jika due date sudah lewat (invert = 1), beri tanda +
    // Jika due date masih akan datang (invert = 0), beri tanda -
    if ($interval->invert) {
        // Sudah lewat due date -> + hari
        return '+' . $days . ' Days';
    } else {
        // Masih sebelum due date -> - hari
        // Jika hari sama (0 days), tetap tampilkan -0
        return '-' . $days . ' Days';
    }
}

// Function to get effective due date based on priority
function getEffectiveDueDate($order, $line = null)
{
    // 1. Prioritas: due_date_item_update (jika ada di line)
    if (!empty($line['due_date_item_update']) && $line['due_date_item_update'] !== 'N/A') {
        return $line['due_date_item_update'];
    }
    // 2. Prioritas: due_date_update_order (jika ada di order)
    else if (!empty($order['due_date_update_order']) && $order['due_date_update_order'] !== 'N/A') {
        return $order['due_date_update_order'];
    }
    // 3. Prioritas: due_date_order (jika yang lain tidak ada)
    else if (!empty($order['due_date_order']) && $order['due_date_order'] !== 'N/A') {
        return $order['due_date_order'];
    }
    
    return '';
}

// Function to check if item should be displayed in late filter - DIPERBAIKI
function shouldDisplayItemInLateFilter($order, $line)
{
    $effective_due_date = getEffectiveDueDate($order, $line);
    
    if (empty($effective_due_date)) {
        return false; // Skip item tanpa due date
    }
    
    $due_date = new DateTime($effective_due_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $due_date->setTime(0, 0, 0);
    
    $interval = $today->diff($due_date);
    $days = $interval->days;
    
    // CEK APAKAH ITEM INI HIJAU (AV = STR) - DIPERBAIKI: tidak perlu munculkan jika AV = STR
    $av = !empty($line['production_av']) ? floatval($line['production_av']) : 0;
    $str = !empty($line['production_str']) ? floatval($line['production_str']) : 0;
    $is_green = ($av > 0 && $str > 0 && $av == $str);
    
    // JIKA HIJAU (AV = STR), SKIP (tidak tampilkan)
    if ($is_green) {
        return false;
    }
    
    // Tampilkan hanya jika sudah lewat atau <= 14 hari
    return ($interval->invert || $days <= 14);
}

// Function to check if order should be displayed in late filter - DIPERBAIKI
function shouldDisplayOrderInLateFilter($order, $order_lines)
{
    // Cek apakah ada minimal satu item yang memenuhi kriteria late filter
    foreach ($order_lines as $line) {
        if (shouldDisplayItemInLateFilter($order, $line)) {
            return true;
        }
    }
    
    return false;
}

// Function to get cell color based on days difference and AV=STR condition dengan prioritas due date
function getCellColor($order, $line = null, $av = 0, $str = 0)
{
    // JIKA AV = STR DAN KEDUANYA > 0, MAKA WARNA HIJAU
    if ($av > 0 && $str > 0 && $av == $str) {
        return '#28a745'; // HIJAU
    }

    $effective_due_date = getEffectiveDueDate($order, $line);
    
    if (empty($effective_due_date) || $effective_due_date === 'N/A') {
        return 'white';
    }

    $due_date = new DateTime($effective_due_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $due_date->setTime(0, 0, 0);

    $interval = $today->diff($due_date);
    $days = $interval->days;

    if ($interval->invert) {
        // Sudah melewati due date (MERAH)
        return '#DC0E0E';
    } elseif ($days <= 14) {
        // Mendekati H-14 (KUNING)
        return '#fff3cd';
    } else {
        // Lebih dari H-14 (PUTIH)
        return 'white';
    }
}

// Function to get due date source info
function getDueDateSource($order, $line = null)
{
    if (!empty($line['due_date_item_update']) && $line['due_date_item_update'] !== 'N/A') {
        return 'Item Update';
    }
    else if (!empty($order['due_date_update_order']) && $order['due_date_update_order'] !== 'N/A') {
        return 'Order Update';
    }
    else if (!empty($order['due_date_order']) && $order['due_date_order'] !== 'N/A') {
        return 'Original Due Date';
    }
    
    return 'No Due Date';
}

// Function to extract text from brackets
function extractFromBrackets($text)
{
    if (empty($text)) {
        return 'N/A';
    }

    preg_match('/\[(.*?)\]/', $text, $matches);
    return $matches[1] ?? $text;
}

// Function to extract value from Odoo field (bisa string atau array)
function getOdooFieldValue($field)
{
    if (empty($field)) {
        return 'N/A';
    }

    if (is_array($field)) {
        // Jika array, biasanya formatnya [id, name] atau [id, value]
        return $field[1] ?? $field[0] ?? 'N/A';
    }

    return $field;
}

// Function to get customer name from partner_id field
function getCustomerName($partnerField)
{
    if (empty($partnerField)) {
        return 'N/A';
    }

    if (is_array($partnerField)) {
        // Format Odoo: [id, name]
        return $partnerField[1] ?? 'N/A';
    }

    return $partnerField;
}

// Di bagian sebelum loop orders, kumpulkan semua data yang diperlukan
$all_order_lines = [];
$all_template_ids = [];
$order_totals = [];
$order_recalculated_opn = [];
$order_production_totals = [];
$orders_to_skip = [];
$all_delivery_orders = [];
$all_batch_data = [];
$orders_with_effective_info = [];
$orders_to_display = []; // Array baru untuk order yang akan ditampilkan
$order_has_displayed_items = []; // Array untuk melacak apakah order memiliki item yang ditampilkan

if ($has_filter && is_array($orders)) {
    // Re-fetch order lines dengan data yang lengkap untuk perhitungan
    foreach ($orders as $order) {
        // VARIABEL UNTUK TOTAL PER ORDER
        $order_total_to = 0;
        $order_total_opn = 0;
        $order_total_av = 0;
        $recalculated_opn = 0;

        // VARIABEL UNTUK TOTAL PRODUCTION
        $order_total_raw = 0;
        $order_total_rpr = 0;
        $order_total_snd = 0;
        $order_total_rtr = 0;
        $order_total_qcin = 0;
        $order_total_clr = 0;
        $order_total_qcl = 0;
        $order_total_finp = 0;
        $order_total_qfin = 0;
        $order_total_str = 0;

        // FLAG UNTUK CEK APAKAH ORDER INI MEMILIKI ITEM DENGAN PRODUCTION_OPN > 0
        $order_has_opn_items = false;

        // AMBIL ORDER LINES
        $order_lines = callOdooRead($username, "sale.order.line", [
            ["order_id", "=", $order['id']],
            ["product_id", "!=", false]
        ], [
            "id",
            "product_id",
            "name",
            "supp_order",
            "finish_product",
            "product_uom_qty",
            "product_uom",
            "info_to_production",
            "info_to_buyer",
            "production_opn",
            "production_av",
            "production_raw",
            "production_rpr",
            "production_snd",
            "production_rtr",
            "production_qcin",
            "production_clr",
            "production_qcl",
            "production_finp",
            "production_qfin",
            "production_str",
            "qty_delivered",
            "due_date_item_update"
        ]);

        // HITUNG TOTAL UNTUK ORDER INI DAN CEK APAKAH ADA ITEM DENGAN PRODUCTION_OPN > 0
        if (is_array($order_lines)) {
            foreach ($order_lines as $line) {
                // HAPUS ANGKA 0 - JIKA 0 MAKA KOSONG
                $to = !empty($line['product_uom_qty']) ? floatval($line['product_uom_qty']) : '';
                $av = !empty($line['production_av']) ? floatval($line['production_av']) : '';
                $delivered = !empty($line['qty_delivered']) ? floatval($line['qty_delivered']) : '';
                $production_opn = !empty($line['production_opn']) ? floatval($line['production_opn']) : '';

                // Hitung opn_delivered
                $opn_delivered = ($to !== '' && $delivered !== '') ? $to - $delivered : '';

                // HITUNG TOTAL UNTUK ORDER - HANYA JIKA ADA NILAI
                $order_total_to += ($to !== '') ? $to : 0;
                $order_total_opn += ($production_opn !== '') ? $production_opn : 0;
                $order_total_av += ($av !== '') ? $av : 0;

                // CEK JIKA PRODUCTION_OPN > 0, MAKA ORDER INI TIDAK BOLEH DISKIP
                if ($production_opn > 0) {
                    $order_has_opn_items = true;
                }

                // Hitung recalculated_opn hanya untuk items dengan opn_delivered > 0
                if ($opn_delivered > 0) {
                    $recalculated_opn += $opn_delivered;
                }

                // HITUNG TOTAL PRODUCTION - HANYA JIKA ADA NILAI
                $order_total_raw += !empty($line['production_raw']) ? floatval($line['production_raw']) : 0;
                $order_total_rpr += !empty($line['production_rpr']) ? floatval($line['production_rpr']) : 0;
                $order_total_snd += !empty($line['production_snd']) ? floatval($line['production_snd']) : 0;
                $order_total_rtr += !empty($line['production_rtr']) ? floatval($line['production_rtr']) : 0;
                $order_total_qcin += !empty($line['production_qcin']) ? floatval($line['production_qcin']) : 0;
                $order_total_clr += !empty($line['production_clr']) ? floatval($line['production_clr']) : 0;
                $order_total_qcl += !empty($line['production_qcl']) ? floatval($line['production_qcl']) : 0;
                $order_total_finp += !empty($line['production_finp']) ? floatval($line['production_finp']) : 0;
                $order_total_qfin += !empty($line['production_qfin']) ? floatval($line['production_qfin']) : 0;
                $order_total_str += !empty($line['production_str']) ? floatval($line['production_str']) : 0;
            }
        }

        // JIKA TIDAK ADA ITEM DENGAN PRODUCTION_OPN > 0, TANDAI ORDER INI UNTUK DISKIP
        if (!$order_has_opn_items) {
            $orders_to_skip[$order['id']] = true;
            continue;
        }

        // FILTER LATE + 2W: CEK APAKAH ORDER INI HARUS DITAMPILKAN - DIPERBAIKI
        $should_display_order = true;
        if ($late_filter) {
            $should_display_order = shouldDisplayOrderInLateFilter($order, $order_lines);
            if (!$should_display_order) {
                $orders_to_skip[$order['id']] = true;
                continue;
            }
        }

        // AMBIL DATA DELIVERY ORDER BERDASARKAN SALES ORDER
        $delivery_orders = callOdooRead($username, "stock.picking", [
            ["origin", "=", $order['name']],
            ["state", "not in", ["cancel"]]
        ], [
            "id",
            "name",
            "origin",
            "state",
            "scheduled_date",
            "date_deadline",
            "batch_id"
        ]);

        $all_delivery_orders[$order['id']] = $delivery_orders;

        // AMBIL DATA BATCH JIKA ADA
        $batch_ids = [];
        if (is_array($delivery_orders)) {
            foreach ($delivery_orders as $do) {
                if (isset($do['batch_id'][0])) {
                    $batch_ids[] = $do['batch_id'][0];
                }
            }
        }

        // Ambil data batch
        $batch_data = [];
        if (!empty($batch_ids)) {
            $batch_ids = array_unique($batch_ids);
            $batch_data = callOdooRead($username, "stock.picking.batch", [
                ["id", "in", $batch_ids]
            ], [
                "id",
                "name",
                "state",
                "date",
                "notes",
                "description",
                "scheduled_date"
            ]);
        }

        $all_batch_data[$order['id']] = $batch_data;

        // SIMPAN TOTAL ORDER
        $order_totals[$order['id']] = [
            'to' => $order_total_to,
            'opn' => $order_total_opn,
            'av' => $order_total_av
        ];

        // SIMPAN RECALCULATED OPN
        $order_recalculated_opn[$order['id']] = $recalculated_opn;

        // SIMPAN TOTAL PRODUCTION
        $order_production_totals[$order['id']] = [
            'raw' => $order_total_raw,
            'rpr' => $order_total_rpr,
            'snd' => $order_total_snd,
            'rtr' => $order_total_rtr,
            'qcin' => $order_total_qcin,
            'clr' => $order_total_clr,
            'qcl' => $order_total_qcl,
            'finp' => $order_total_finp,
            'qfin' => $order_total_qfin,
            'str' => $order_total_str
        ];

        $all_order_lines[$order['id']] = $order_lines;

        // Tentukan effective due date untuk order ini
        $order_effective_due_date = '';
        $order_due_date_source = 'No Due Date';
        
        // 1. Prioritas: due_date_item_update dari line manapun
        foreach ($order_lines as $line) {
            if (!empty($line['due_date_item_update']) && $line['due_date_item_update'] !== 'N/A') {
                $order_effective_due_date = $line['due_date_item_update'];
                $order_due_date_source = 'Item Update';
                break;
            }
        }
        
        // 2. Prioritas: due_date_update_order dari order
        if (empty($order_effective_due_date) && !empty($order['due_date_update_order']) && $order['due_date_update_order'] !== 'N/A') {
            $order_effective_due_date = $order['due_date_update_order'];
            $order_due_date_source = 'Order Update';
        }
        
        // 3. Prioritas: due_date_order dari order
        if (empty($order_effective_due_date) && !empty($order['due_date_order']) && $order['due_date_order'] !== 'N/A') {
            $order_effective_due_date = $order['due_date_order'];
            $order_due_date_source = 'Original Due Date';
        }

        $orders_with_effective_info[$order['id']] = [
            'effective_due_date' => $order_effective_due_date,
            'due_date_source' => $order_due_date_source
        ];

        // CEK APAKAH ORDER INI MEMILIKI ITEM YANG DITAMPILKAN SETELAH FILTER
        $has_displayed_items_in_order = false;
        if (is_array($order_lines)) {
            foreach ($order_lines as $line) {
                $production_opn = !empty($line['production_opn']) ? floatval($line['production_opn']) : '';
                
                // Skip item jika production_opn == 0 atau kosong
                if ($production_opn === '' || $production_opn == 0) {
                    continue;
                }

                // Jika filter Late aktif, cek apakah item memenuhi kriteria
                if ($late_filter) {
                    $av = !empty($line['production_av']) ? floatval($line['production_av']) : 0;
                    $str = !empty($line['production_str']) ? floatval($line['production_str']) : 0;
                    $is_green = ($av > 0 && $str > 0 && $av == $str);
                    
                    // Skip item yang AV = STR ketika filter Late aktif
                    if ($is_green) {
                        continue;
                    }
                    
                    // Cek apakah item termasuk merah atau kuning
                    if (shouldDisplayItemInLateFilter($order, $line)) {
                        $has_displayed_items_in_order = true;
                        break;
                    }
                } else {
                    // Jika tidak ada filter Late, semua item dengan production_opn > 0 ditampilkan
                    $has_displayed_items_in_order = true;
                    break;
                }
            }
        }

        $order_has_displayed_items[$order['id']] = $has_displayed_items_in_order;

        // Tambahkan order ke daftar yang akan ditampilkan hanya jika memiliki item yang ditampilkan
        if ($has_displayed_items_in_order) {
            $orders_to_display[] = $order;
        }

        // Kumpulkan template_ids
        if (is_array($order_lines)) {
            foreach ($order_lines as $line) {
                if (isset($line['product_id'][0])) {
                    $all_template_ids[] = $line['product_id'][0];
                }
            }
        }
    }

    // Remove duplicates
    $all_template_ids = array_unique($all_template_ids);
}

function getLineBatchDetails($order_group, $product_id, $username, $sale_line_id = null)
{
    // Ambil DO berdasarkan procurement group
    $delivery_orders = callOdooRead($username, "stock.picking", [
        ["group_id", "=", $order_group],
        ["state", "!=", "cancel"]
    ], ["id", "name", "batch_id", "scheduled_date", "state"]);

    if (empty($delivery_orders)) return [];

    $results = [];

    foreach ($delivery_orders as $do) {
        if (empty($do['batch_id']) || !is_array($do['batch_id'])) {
            continue;
        }

        $batch_id = $do['batch_id'][0];

        // Filter berdasarkan sale_line_id jika tersedia
        $move_filters = [
            ["picking_id", "=", $do['id']],
            ["product_id", "=", $product_id]
        ];
        
        // Tambahkan filter sale_line_id jika ada
        if ($sale_line_id !== null) {
            $move_filters[] = ["sale_line_id", "=", (int)$sale_line_id];
        }

        // Ambil stock.move
        $moves = callOdooRead($username, "stock.move", $move_filters, [
            "id", 
            "product_id", 
            "quantity", 
            "catatan", 
            "sale_line_id"
        ]);

        if (empty($moves)) {
            continue;
        }

        // Group by sale_line_id
        $grouped_moves = [];
        foreach ($moves as $move) {
            $move_sale_line_id = $move['sale_line_id'][0] ?? 'no_line';
            
            // Jika sale_line_id diberikan, hanya proses yang sesuai
            if ($sale_line_id !== null && $move_sale_line_id != $sale_line_id) {
                continue;
            }
            
            if (!isset($grouped_moves[$move_sale_line_id])) {
                $grouped_moves[$move_sale_line_id] = [
                    'quantity' => 0,
                    'notes' => []
                ];
            }
            
            $grouped_moves[$move_sale_line_id]['quantity'] += $move['quantity'] ?? 0;
            if (!empty($move['catatan'])) {
                $grouped_moves[$move_sale_line_id]['notes'][] = $move['catatan'];
            }
        }

        // Ambil batch details
        $batch = callOdooRead($username, "stock.picking.batch", [
            ["id", "=", $batch_id]
        ], ["name", "description", "scheduled_date", "status_shipping", "container"]);

        if (empty($batch)) continue;

        $batch_sched = substr($batch[0]['scheduled_date'], 0, 10);
        $today = date("Y-m-d");
        if ($batch_sched <= $today) continue;

        // Buat entry untuk setiap sale_line_id
        foreach ($grouped_moves as $move_sale_line_id => $move_data) {
            if ($move_data['quantity'] == 0) continue;
            
            $unique_key = $batch_id . '_' . $move_sale_line_id . '_' . $do['id'];
            
            $results[$unique_key] = [
                "batch"       => $batch[0]['name'] ?? "-",
                "description" => $batch[0]['description'] ?? "-",
                "scheduled"   => $batch_sched,
                "note_shipp"  => $move_data['notes'],
                "status_shipp" => $batch[0]['status_shipping'] ?? "-",
                "status_container" => $batch[0]['container'] ?? "-",
                "qty"         => $move_data['quantity'],
                "do"          => $do['name'],
                "state"       => $do['state'],
                "sale_line_id" => $move_sale_line_id,
                "do_id"       => $do['id']
            ];
        }
    }

    return array_values($results);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Progress Order</title>
    <link rel="stylesheet" type="text/css" href="report-summary-open-order-progress-prod/style.css">
</head>
<body>
    <div class="container">
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <input type="text" name="module" value="report-summary-open-order-progress-prod" hidden>
                <label for="partner_id"><strong>Filter by Customer:</strong></label>
                <select name="partner_id" id="partner_id" required>
                    <option value="">-- Pilih Customer --</option>
                    <option value="all" <?= ($selected_partner_id == 'all') ? 'selected' : '' ?>>
                        -- Semua Customer --
                    </option>
                    <?php if (is_array($partners) && count($partners) > 0): ?>
                        <?php foreach ($partners as $partner): ?>
                            <option value="<?= $partner['id'] ?>"
                                <?= ($selected_partner_id == $partner['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($partner['name'] ?: 'N/A') ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                
                <!-- TAMBAHKAN CHECKBOX FILTER -->
                <input type="checkbox" name="late_filter" id="late_filter" <?= $late_filter ? 'checked' : '' ?>>
                <label for="late_filter">Late + 2W, Not In Storage</label>
                
                <button type="submit">Apply Filter</button>
                <?php if ($has_filter): ?>
                    <button type="button" onclick="window.print()" style="margin-left: 10px; background-color: #28a745; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">🖨️ Print Laporan</button>
                <?php endif; ?>
            </form>

            <!-- Color Legend -->
            <div class="color-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #28a745;"></div>
                    <span>Hijau: AV = STR (Completed) - Tidak ditampilkan jika filter aktif</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #DC0E0E;"></div>
                    <span>Merah: Sudah melewati due date</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: #fff3cd;"></div>
                    <span>Kuning: Mendekati H-14</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: white; border: 1px solid #ccc;"></div>
                    <span>Putih: Lebih dari H-14 (Tidak ditampilkan jika filter aktif)</span>
                </div>
            </div>

            <!-- Due Date Priority Info -->
            <div class="priority-info">
                <strong>Prioritas Due Date (untuk sorting):</strong>
                <ol>
                    <li>Due Date Item Update</li>
                    <li>Due Date Order Update</li>
                    <li>Original Due Date</li>
                </ol>
            </div>
        </div>

        <!-- Tampilkan info filter aktif -->
        <?php if ($late_filter): ?>
            <div class="filter-active-info">
                <strong>Filter Aktif:</strong> Menampilkan hanya order yang <span style="color: #DC0E0E; font-weight: bold;">terlambat</span> atau <span style="color: #856404; font-weight: bold;">mendekati due date (H-14)</span>. 
                <br>Order dengan status <span style="color: #28a745; font-weight: bold;">Hijau (AV = STR Completed)</span> tidak ditampilkan.
            </div>
        <?php endif; ?>

        <!-- Debug Info -->
        <?php if ($has_filter && isset($all_template_ids)): ?>
            <div class="debug-info">
                <strong>Debug Info:</strong>
                Found <?= count($all_template_ids) ?> product templates |
                Orders to skip: <?= count($orders_to_skip) ?> |
                Orders to display: <?= count($orders_to_display) ?>
                <?php if ($late_filter): ?> | Late Filter: Active (Showing only late + 2W, excluding green items where AV = STR) <?php endif; ?>
                | Sorted by: Effective Due Date (Priority: Item Update → Order Update → Original Due Date)
            </div>
        <?php endif; ?>

        <table id="reportTable">
            <tr>
                <td rowspan="5" style="width: 10%; text-align: center; font-size: 45px; font-weight: bold;">PR</td>
                <td align="right" style="width: 50%; font-size: 20px; font-weight: 600; background: #000; color: #fff;">Summary Open Order Progress (PROD) for :</td>
                <td style="width: 1%; border: 0px;"></td>
                <td style="width: 48%; font-size: 20px; font-weight: 600;">
                    <?php if ($has_filter): ?>
                        <?php
                        $selected_partner_name = 'Semua Customer';
                        if ($selected_partner_id !== 'all') {
                            foreach ($partners as $partner) {
                                if ($partner['id'] == $selected_partner_id) {
                                    $selected_partner_name = $partner['name'];
                                    break;
                                }
                            }
                        }
                        echo htmlspecialchars($selected_partner_name);
                        ?>
                    <?php else: ?>
                        Select Customer
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td align="right" style="font-size: 20px; font-weight: 600; background: #000; color: #fff;">Printed On :</td>
                <td style="width: 1%; border: 0px;"></td>
                <td style="font-size: 20px; font-weight: 600;"><?= date("d F Y") ?></td>
            </tr>
            <tr>
                <td align="right" style="font-size: 20px; font-weight: 600; background: #000; color: #fff;">Group by :</td>
                <td style="width: 1%; border: 0px;"></td>
                <td style="font-size: 20px; font-weight: 600;">
                    Due Date
                </td>
            </tr>
        </table>

        <?php if (!$has_filter): ?>
            <!-- Empty State - Belum ada filter -->
            <div class="empty-state">
                <div class="empty-state-icon">📊</div>
                <h3>Belum Ada Data yang Ditampilkan</h3>
                <p>Silakan pilih customer terlebih dahulu dengan menggunakan filter di atas untuk melihat laporan progress order.</p>
            </div>
        <?php elseif (count($orders_to_display) > 0): ?>
            <!-- Tampilkan data jika ada filter dan data ditemukan -->
            <table class="data-table">
                <thead>
                    <tr class="header-row" align="center">
                        <td class="medium-column">Picture</td>
                        <td class="medium-column">Reference</td>
                        <td class="product-name">Product Name</td>
                        <td class="large-column">H W D</td>
                        <td class="medium-column">Color</td>
                        <td class="narrow-column">Dept</td>
                        <td class="narrow-column">To</td>
                        <td class="narrow-column">Opn</td>
                        <td class="narrow-column">Av</td>
                        <td class="narrow-column">Raw</td>
                        <td class="narrow-column">Rpr</td>
                        <td class="narrow-column">Snd</td>
                        <td class="narrow-column">Rtr</td>
                        <td class="narrow-column">QCIn</td>
                        <td class="narrow-column">CLR</td>
                        <td class="narrow-column">QCL</td>
                        <td class="narrow-column">FinP</td>
                        <td class="narrow-column">QFin</td>
                        <td class="narrow-column">STR</td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="19" style="height: 5px; padding: 0;"></td>
                    </tr>

                    <?php
                    // VARIABEL UNTUK TOTAL GLOBAL
                    $global_total_to_recalc = 0;
                    $global_total_opn_recalc = 0;
                    $global_total_av_recalc = 0;

                    // VARIABEL UNTUK TOTAL PRODUCTION GLOBAL
                    $global_total_raw = 0;
                    $global_total_rpr = 0;
                    $global_total_snd = 0;
                    $global_total_rtr = 0;
                    $global_total_qcin = 0;
                    $global_total_clr = 0;
                    $global_total_qcl = 0;
                    $global_total_finp = 0;
                    $global_total_qfin = 0;
                    $global_total_str = 0;

                    $has_any_opn_order = false;
                    ?>

                    <?php foreach ($orders_to_display as $order): ?>
                        <?php
                        $order_lines = $all_order_lines[$order['id']] ?? [];
                        // GUNAKAN ARRAY UNTUK MENDAPATKAN NILAI YANG SUDAH DISIMPAN
                        $order_total = $order_totals[$order['id']] ?? ['to' => 0, 'opn' => 0, 'av' => 0];
                        $recalculated_opn = $order_recalculated_opn[$order['id']] ?? 0;
                        $order_production = $order_production_totals[$order['id']] ?? [
                            'raw' => 0,
                            'rpr' => 0,
                            'snd' => 0,
                            'rtr' => 0,
                            'qcin' => 0,
                            'clr' => 0,
                            'qcl' => 0,
                            'finp' => 0,
                            'qfin' => 0,
                            'str' => 0
                        ];

                        // Dapatkan effective due date info untuk order ini
                        $order_effective_info = $orders_with_effective_info[$order['id']] ?? [
                            'effective_due_date' => '',
                            'due_date_source' => 'No Due Date'
                        ];
                        $order_effective_due_date = $order_effective_info['effective_due_date'];
                        $order_due_date_source = $order_effective_info['due_date_source'];

                        // SET FLAG BAHWA ADA ORDER DENGAN OPN
                        $has_any_opn_order = true;

                        // TAMBAH KE TOTAL GLOBAL
                        $global_total_to_recalc += $order_total['to'];
                        $global_total_opn_recalc += $order_total['opn'];
                        $global_total_av_recalc += $order_total['av'];

                        // TAMBAH KE TOTAL PRODUCTION GLOBAL
                        $global_total_raw += $order_production['raw'];
                        $global_total_rpr += $order_production['rpr'];
                        $global_total_snd += $order_production['snd'];
                        $global_total_rtr += $order_production['rtr'];
                        $global_total_qcin += $order_production['qcin'];
                        $global_total_clr += $order_production['clr'];
                        $global_total_qcl += $order_production['qcl'];
                        $global_total_finp += $order_production['finp'];
                        $global_total_qfin += $order_production['qfin'];
                        $global_total_str += $order_production['str'];

                        $product_images = [];
                        $product_dimensions = [];
                        if (is_array($order_lines) && count($order_lines) > 0) {
                            foreach ($order_lines as $line) {
                                if (isset($line['product_id'][0])) {
                                    $product_id = $line['product_id'][0];

                                    // STEP 1: Ambil product_tmpl_id dari product.product
                                    $product_product_data = callOdooRead($username, "product.product", [
                                        ["id", "=", $product_id]
                                    ], [
                                        "product_tmpl_id"  // get id product.template
                                    ]);

                                    $template_id = null;
                                    if (is_array($product_product_data) && count($product_product_data) > 0) {
                                        $template_id = $product_product_data[0]['product_tmpl_id'][0] ?? null;
                                    }

                                    // Get data dari product.template image dan H W D
                                    if ($template_id) {
                                        $template_data = callOdooRead($username, "product.template", [
                                            ["id", "=", $template_id]
                                        ], [
                                            "image_1920",
                                            "height",
                                            "width",
                                            "depth"
                                        ]);

                                        if (is_array($template_data) && count($template_data) > 0) {
                                            $product_images[$product_id] = $template_data[0]['image_1920'] ?? null;
                                            $product_dimensions[$product_id] = [
                                                'height' => $template_data[0]['height'] ?? 0,
                                                'width' => $template_data[0]['width'] ?? 0,
                                                'depth' => $template_data[0]['depth'] ?? 0
                                            ];
                                        }
                                    } else {
                                        echo "<!-- Debug: Tidak dapat template_id untuk product_id: $product_id -->";
                                    }
                                }
                            }
                        }

                        // Tentukan apakah order ini memiliki item yang ditampilkan
                        $has_displayed_items_in_this_order = $order_has_displayed_items[$order['id']] ?? false;
                        ?>

                        <!-- HEADER ORDER - TAMPILKAN SELALU JIKA ORDER ADA DI orders_to_display -->
                        <?php if ($has_displayed_items_in_this_order): ?>
                            <tr class="no-break">
                                <td colspan="11">
                                    <h2 style="margin: 0; font-size: 20px;">
                                        <?= htmlspecialchars($order['name'] ?: 'N/A') ?> | <?= htmlspecialchars($order['client_order_ref'] ?: 'N/A') ?> | <?= htmlspecialchars(getCustomerName($order['partner_id'] ?? '')) ?>
                                    </h2>
                                </td>
                                <td colspan="8">
                                    DUE DATE :<br />
                                    <h4 align="right" style="margin: 0px; font-size: 25px; font-weight: 700;">
                                        <?= formatDate($order['due_date_order']) ?> <?= getWeekNumber($order['due_date_order']) ?>
                                    </h4>
                                </td>
                            </tr>

                            <!-- INFORMASI DUE DATE UNTUK ORDER (HANYA SEKALI) -->
                            <tr class="no-break">
                                <td colspan="3">
                                    Effective Due Date :
                                    <span class="<?= strpos(getDaysDifference($order_effective_due_date), '+') !== false ? 'negative-days' : 'positive-days' ?>">
                                        <?= getDaysDifference($order_effective_due_date) ?>
                                    </span>
                                    <br><small>(Source: <?= $order_due_date_source ?>)</small>
                                </td>
                                <td colspan="5">
                                    Conf Date : <?= formatDate($order['confirmation_date_order']) ?>
                                </td>
                                <!-- WARNA BERDASARKAN EFFECTIVE DUE DATE DAN KONDISI AV=STR -->
                                <?php
                                $order_av = $order_total['av'] ?? 0;
                                $order_str = $order_production['str'] ?? 0;
                                ?>
                                <td colspan="3" style="background-color: <?= getCellColor($order, null, $order_av, $order_str) ?>;"></td>
                                <td colspan="8">
                                    ORDER DUE DATE UPDATE :<br />
                                    <h4 align="right" style="margin: 0px; font-size: 25px; font-weight: 700;">
                                        <?= formatDate($order['due_date_update_order']) ?> <?= getWeekNumber($order['due_date_update_order']) ?>
                                    </h4>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (is_array($order_lines) && count($order_lines) > 0): ?>
                            <?php
                            $has_displayed_items_in_loop = false;
                            foreach ($order_lines as $line):

                                // HAPUS ANGKA 0 - JIKA 0 MAKA KOSONG
                                $to = !empty($line['product_uom_qty']) ? floatval($line['product_uom_qty']) : '';
                                $av = !empty($line['production_av']) ? floatval($line['production_av']) : '';
                                $delivered = !empty($line['qty_delivered']) ? floatval($line['qty_delivered']) : '';
                                $production_opn = !empty($line['production_opn']) ? floatval($line['production_opn']) : '';

                                $raw = !empty($line['production_raw']) ? floatval($line['production_raw']) : '';
                                $rpr = !empty($line['production_rpr']) ? floatval($line['production_rpr']) : '';
                                $snd = !empty($line['production_snd']) ? floatval($line['production_snd']) : '';
                                $rtr = !empty($line['production_rtr']) ? floatval($line['production_rtr']) : '';
                                $qcin = !empty($line['production_qcin']) ? floatval($line['production_qcin']) : '';
                                $clr = !empty($line['production_clr']) ? floatval($line['production_clr']) : '';
                                $qcl = !empty($line['production_qcl']) ? floatval($line['production_qcl']) : '';
                                $finp = !empty($line['production_finp']) ? floatval($line['production_finp']) : '';
                                $qfin = !empty($line['production_qfin']) ? floatval($line['production_qfin']) : '';
                                $str = !empty($line['production_str']) ? floatval($line['production_str']) : '';

                                $opn_delivered = ($to !== '' && $delivered !== '') ? $to - $delivered : '';

                                // PERBAIKAN: Skip item jika production_opn == 0 atau kosong
                                if ($production_opn === '' || $production_opn == 0) {
                                    continue;
                                }

                                // PERBAIKAN: Skip item jika AV = STR dan filter Late aktif
                                if ($late_filter) {
                                    $av_val = !empty($line['production_av']) ? floatval($line['production_av']) : 0;
                                    $str_val = !empty($line['production_str']) ? floatval($line['production_str']) : 0;
                                    $is_green = ($av_val > 0 && $str_val > 0 && $av_val == $str_val);
                                    if ($is_green) {
                                        continue; // Skip item yang AV = STR ketika filter Late aktif
                                    }
                                    
                                    // Cek apakah item termasuk merah atau kuning
                                    if (!shouldDisplayItemInLateFilter($order, $line)) {
                                        continue; // Skip item yang tidak memenuhi kriteria late filter
                                    }
                                }

                                // Set flag bahwa ada item yang ditampilkan
                                $has_displayed_items_in_loop = true;

                                $str_back = 'style="background: #fff;"';
                                $qfin_back = 'style="background: #fff;"';
                                $finp_back = 'style="background: #fff;"';
                                $qcl_back = 'style="background: #fff;"';
                                $clr_back = 'style="background: #fff;"';
                                $qcin_back = 'style="background: #fff;"';
                                $rtr_back = 'style="background: #fff;"';
                                $snd_back = 'style="background: #fff;"';
                                $rpr_back = 'style="background: #fff;"';
                                $raw_back = 'style="background: #fff;"';

                                if ($str !== '' && $str > 0) {
                                    $str_back = 'style="background: #c9c9c9;"';
                                    $qfin_back = 'style="background: #c9c9c9;"';
                                    $finp_back = 'style="background: #c9c9c9;"';
                                    $qcl_back = 'style="background: #c9c9c9;"';
                                    $clr_back = 'style="background: #c9c9c9;"';
                                    $qcin_back = 'style="background: #c9c9c9;"';
                                    $rtr_back = 'style="background: #c9c9c9;"';
                                    $snd_back = 'style="background: #c9c9c9;"';
                                    $rpr_back = 'style="background: #c9c9c9;"';
                                    $raw_back = 'style="background: #c9c9c9;"';
                                } elseif ($qfin !== '' && $qfin > 0) {
                                    $qfin_back = 'style="background: #c9c9c9;"';
                                    $finp_back = 'style="background: #c9c9c9;"';
                                    $qcl_back = 'style="background: #c9c9c9;"';
                                    $clr_back = 'style="background: #c9c9c9;"';
                                    $qcin_back = 'style="background: #c9c9c9;"';
                                    $rtr_back = 'style="background: #c9c9c9;"';
                                    $snd_back = 'style="background: #c9c9c9;"';
                                    $rpr_back = 'style="background: #c9c9c9;"';
                                    $raw_back = 'style="background: #c9c9c9;"';
                                } elseif ($finp !== '' && $finp > 0) {
                                    $finp_back = 'style="background: #c9c9c9;"';
                                    $qcl_back = 'style="background: #c9c9c9;"';
                                    $clr_back = 'style="background: #c9c9c9;"';
                                    $qcin_back = 'style="background: #c9c9c9;"';
                                    $rtr_back = 'style="background: #c9c9c9;"';
                                    $snd_back = 'style="background: #c9c9c9;"';
                                    $rpr_back = 'style="background: #c9c9c9;"';
                                    $raw_back = 'style="background: #c9c9c9;"';
                                } elseif ($qcl !== '' && $qcl > 0) {
                                    $qcl_back = 'style="background: #c9c9c9;"';
                                    $clr_back = 'style="background: #c9c9c9;"';
                                    $qcin_back = 'style="background: #c9c9c9;"';
                                    $rtr_back = 'style="background: #c9c9c9;"';
                                    $snd_back = 'style="background: #c9c9c9;"';
                                    $rpr_back = 'style="background: #c9c9c9;"';
                                    $raw_back = 'style="background: #c9c9c9;"';
                                } elseif ($clr !== '' && $clr > 0) {
                                    $clr_back = 'style="background: #c9c9c9;"';
                                    $qcin_back = 'style="background: #c9c9c9;"';
                                    $rtr_back = 'style="background: #c9c9c9;"';
                                    $snd_back = 'style="background: #c9c9c9;"';
                                    $rpr_back = 'style="background: #c9c9c9;"';
                                    $raw_back = 'style="background: #c9c9c9;"';
                                } elseif ($qcin !== '' && $qcin > 0) {
                                    $qcin_back = 'style="background: #c9c9c9;"';
                                    $rtr_back = 'style="background: #c9c9c9;"';
                                    $snd_back = 'style="background: #c9c9c9;"';
                                    $rpr_back = 'style="background: #c9c9c9;"';
                                    $raw_back = 'style="background: #c9c9c9;"';
                                } elseif ($rtr !== '' && $rtr > 0) {
                                    $rtr_back = 'style="background: #c9c9c9;"';
                                    $snd_back = 'style="background: #c9c9c9;"';
                                    $rpr_back = 'style="background: #c9c9c9;"';
                                    $raw_back = 'style="background: #c9c9c9;"';
                                } elseif ($snd !== '' && $snd > 0) {
                                    $snd_back = 'style="background: #c9c9c9;"';
                                    $rpr_back = 'style="background: #c9c9c9;"';
                                    $raw_back = 'style="background: #c9c9c9;"';
                                } elseif ($rpr !== '' && $rpr > 0) {
                                    $rpr_back = 'style="background: #c9c9c9;"';
                                    $raw_back = 'style="background: #c9c9c9;"';
                                } elseif ($raw !== '' && $raw > 0) {
                                    $raw_back = 'style="background: #c9c9c9;"';
                                }

                            ?>
                                <?php
                                $product_id = $line['product_id'][0] ?? null;
                                $product_image = $product_images[$product_id] ?? null;
                                ?>
                                
                                <tr class="no-break">
                                    <td rowspan="2" style="text-align: center;">
                                        <?php if (!empty($product_image)): ?>
                                            <img src="data:image/png;base64,<?= $product_image ?>"
                                                class="product-image"
                                                alt="Product Image"
                                                onerror="this.src='../assets/img/img-default.webp'">
                                        <?php else: ?>
                                            <img src="../assets/img/img-default.webp" class="product-image" alt="Product Image">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(extractFromBrackets($line['product_id'][1] ?? 'N/A')) ?>
                                    </td>
                                    <td style="text-align: left;">
                                        <?= htmlspecialchars($line['name'] ?: 'N/A') ?>
                                    </td>
                                    <td class="dimensions-cell">
                                        <?php
                                        // Ambil dimensi dari product_dimensions yang sudah diambil
                                        if ($product_id && isset($product_dimensions[$product_id])) {
                                            $dim = $product_dimensions[$product_id];
                                            $height = $dim['height'] ?? 0;
                                            $width = $dim['width'] ?? 0;
                                            $depth = $dim['depth'] ?? 0;
                                        } else {
                                            $height = $width = $depth = 0;
                                        }

                                        // Format output - tampilkan nilai aktual atau '-' jika 0
                                        $height_display = ($height > 0) ? $height : '-';
                                        $width_display = ($width > 0) ? $width : '-';
                                        $depth_display = ($depth > 0) ? $depth : '-';

                                        echo $height_display . " X ";
                                        echo $width_display . " X ";
                                        echo $depth_display;
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $finish_code = '';

                                        if (!empty($line['finish_product']) && is_array($line['finish_product'])) {
                                            $finish_id = $line['finish_product'][0];

                                            // Baca code dari product.finish
                                            $finish_data = callOdooRead($username, "product.finish", [
                                                ["id", "=", $finish_id]
                                            ], ["code"]); // Ambil field code saja

                                            if (is_array($finish_data) && count($finish_data) > 0 && !empty($finish_data[0]['code'])) {
                                                $finish_code = $finish_data[0]['code'];
                                            } else {
                                                // Fallback jika code tidak ada
                                                $finish_code = $line['finish_product'][1] ?? 'N/A';
                                            }
                                        }

                                        echo htmlspecialchars($finish_code);
                                        ?>
                                    </td>
                                    <td align="center">
                                        <?php
                                        // Gunakan fungsi getOdooFieldValue untuk handle array/string
                                        $supp_order_value = getOdooFieldValue($line['supp_order'] ?? '');
                                        echo htmlspecialchars($supp_order_value ?: '');
                                        ?>
                                    </td>
                                    <td align="center">
                                        <?= $to !== '' ? number_format($to) : '' ?>
                                    </td>
                                    <td align="center">
                                        <?= $production_opn !== '' ? number_format($production_opn) : '' ?>
                                    </td>
                                    <td align="center">
                                        <?= $av !== '' ? number_format($av) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $raw_back; ?>>
                                        <?= $raw !== '' ? number_format($raw) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $rpr_back; ?>>
                                        <?= $rpr !== '' ? number_format($rpr) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $snd_back; ?>>
                                        <?= $snd !== '' ? number_format($snd) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $rtr_back; ?>>
                                        <?= $rtr !== '' ? number_format($rtr) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $qcin_back; ?>>
                                        <?= $qcin !== '' ? number_format($qcin) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $clr_back; ?>>
                                        <?= $clr !== '' ? number_format($clr) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $qcl_back; ?>>
                                        <?= $qcl !== '' ? number_format($qcl) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $finp_back; ?>>
                                        <?= $finp !== '' ? number_format($finp) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $qfin_back; ?>>
                                        <?= $qfin !== '' ? number_format($qfin) : '' ?>
                                    </td>
                                    <td align="center" <?php echo $str_back; ?>>
                                        <?= $str !== '' ? number_format($str) : '' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" valign="top">
                                        <b>PRODUCT EXTRA INFO TO BUYER :</b><br /> <?= htmlspecialchars($line['info_to_buyer'] ?: '-') ?>
                                    </td>
                                    <td colspan="9" valign="top">
                                        <b>PRODUCT EXTRA INFO TO FACTORY PRODUCTION :</b><br /> <?= htmlspecialchars($line['info_to_production'] ?: '-') ?>
                                    </td>
                                    <td colspan="6" valign="top">
                                        <b>PRODUCT SPECIAL/DUE DATE UPDATE :</b><br />
                                        <?= htmlspecialchars($line['due_date_item_update'] ?: '-') ?>
                                    </td>
                                </tr>

                                <!-- PRE SHIPPING ROW -->
                                <?php
                                $line_batches = getLineBatchDetails($order['procurement_group_id'][0], $product_id, $username, $line['id']);
                                ?>
                                <?php if (!empty($line_batches)): ?>
                                    <?php foreach ($line_batches as $b): ?>
                                        <tr style="font-weight: 700;">
                                            <td colspan="4" style="border:0;"></td>
                                            <td colspan="6">
                                                Pre Shp >>
                                                <?= htmlspecialchars($b['description']) ?> |
                                                <?php if (!empty($b['batch']) && $b['batch'] !== '-') : ?>
                                                    <?= htmlspecialchars($b['batch']) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td colspan="2" align="center"><?= ucwords(htmlspecialchars($b['status_shipp'])) ?></td>
                                            <td colspan="2" align="center"><?= ucwords(htmlspecialchars($b['status_container'])) ?></td>
                                            <td colspan="2" align="center">
                                                <?php if (!empty($b['scheduled']) && $b['scheduled'] !== '-'): ?>
                                                    <?= date("d M Y", strtotime($b['scheduled'])) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td align="center"><?= floatval($b['qty']) ?></td>
                                            <td colspan="2" align="center">PR-1*</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <tr>
                                    <td colspan="19" style="height: 10px; padding: 0; border: none;"></td>
                                </tr>

                            <?php endforeach; ?>
                            
                        <?php else: ?>
                            <tr>
                                <td colspan="18" style="text-align: center; padding: 20px;">
                                    Tidak ada item order untuk <?= htmlspecialchars($order['name'] ?: 'order ini') ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <!-- TAMPILKAN FOOTER HANYA JIKA ADA ITEM YANG DITAMPILKAN -->
                        <?php if ($has_displayed_items_in_loop): ?>

                            <!-- Spacer between orders -->
                            <tr>
                                <td colspan="19" style="height: 10px; padding: 0; border: none;"></td>
                            </tr>

                            <!-- TOTAL PER ORDER -->
                            <tr class="total-row">
                                <td colspan="6" align="right">Total Order <?= htmlspecialchars($order['client_order_ref'] ?: 'N/A') ?> :</td>
                                <td align="center"><?= $order_total['to'] > 0 ? number_format($order_total['to']) : '' ?></td>
                                <td align="center"><?= $order_total['opn'] > 0 ? number_format($order_total['opn']) : '' ?></td>
                                <td align="center"><?= $order_total['av'] > 0 ? number_format($order_total['av']) : '' ?></td>
                                <td align="center"><?= $order_production['raw'] > 0 ? number_format($order_production['raw']) : '' ?></td>
                                <td align="center"><?= $order_production['rpr'] > 0 ? number_format($order_production['rpr']) : '' ?></td>
                                <td align="center"><?= $order_production['snd'] > 0 ? number_format($order_production['snd']) : '' ?></td>
                                <td align="center"><?= $order_production['rtr'] > 0 ? number_format($order_production['rtr']) : '' ?></td>
                                <td align="center"><?= $order_production['qcin'] > 0 ? number_format($order_production['qcin']) : '' ?></td>
                                <td align="center"><?= $order_production['clr'] > 0 ? number_format($order_production['clr']) : '' ?></td>
                                <td align="center"><?= $order_production['qcl'] > 0 ? number_format($order_production['qcl']) : '' ?></td>
                                <td align="center"><?= $order_production['finp'] > 0 ? number_format($order_production['finp']) : '' ?></td>
                                <td align="center"><?= $order_production['qfin'] > 0 ? number_format($order_production['qfin']) : '' ?></td>
                                <td align="center"><?= $order_production['str'] > 0 ? number_format($order_production['str']) : '' ?></td>
                            </tr>

                            <!-- Spacer tambahan antara orders -->
                            <tr>
                                <td colspan="19" style="height: 10px; padding: 0; border: none;"></td>
                            </tr>

                            <!-- ORDER INFO TO BUYER -->
                            <tr class="no-break">
                                <td colspan="3" valign="top">
                                    <b>ORDER REMARKS FOR PRODUCTION :</b>
                                    <div style="white-space: pre-line; line-height: 1.2; margin-top: -5px;">
                                        <?= strip_tags(html_entity_decode($order['order_remarks_for_production'] ?? '')) ?>
                                    </div>
                                </td>
                                <td colspan="7" valign="top">
                                    <b>ORDER INFO TO BUYER :</b>
                                    <div style="white-space: pre-wrap; line-height: 1.2; margin-top: -20px;">
                                        <?= html_entity_decode($order['note'] ?? '') ?>
                                    </div>
                                </td>
                                <td colspan="9" valign="top">
                                    <b>ORDER ADMIN UPDATE :</b>
                                    <div style="white-space: pre-line; line-height: 1.2; margin-top: -5px;">
                                        <?= strip_tags(html_entity_decode($order['order_admin_update'] ?? '')) ?>
                                    </div>
                                </td>
                            </tr>

                            <!-- Spacer tambahan antara orders -->
                            <tr>
                                <td colspan="19" style="height: 15px; padding: 0; border: none;"></td>
                            </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>

                    <!-- TOTAL GLOBAL -->
                    <?php if ($has_any_opn_order): ?>
                        <tr class="global-total">
                            <td colspan="6" align="right"><strong>Grand Total</strong></td>
                            <td align="center"><strong><?= $global_total_to_recalc > 0 ? number_format($global_total_to_recalc) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_opn_recalc > 0 ? number_format($global_total_opn_recalc) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_av_recalc > 0 ? number_format($global_total_av_recalc) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_raw > 0 ? number_format($global_total_raw) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_rpr > 0 ? number_format($global_total_rpr) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_snd > 0 ? number_format($global_total_snd) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_rtr > 0 ? number_format($global_total_rtr) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_qcin > 0 ? number_format($global_total_qcin) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_clr > 0 ? number_format($global_total_clr) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_qcl > 0 ? number_format($global_total_qcl) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_finp > 0 ? number_format($global_total_finp) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_qfin > 0 ? number_format($global_total_qfin) : '' ?></strong></td>
                            <td align="center"><strong><?= $global_total_str > 0 ? number_format($global_total_str) : '' ?></strong></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="19" style="text-align: center; padding: 20px;">
                                <?php if ($late_filter): ?>
                                    Tidak ada order dengan Open Order yang terlambat atau mendekati due date (H-14) untuk customer yang dipilih.
                                <?php else: ?>
                                    Tidak ada order dengan Open Order untuk customer yang dipilih.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <!-- Empty State - Filter diterapkan tapi tidak ada data -->
            <div class="empty-state">
                <div class="empty-state-icon">😔</div>
                <h3>Data Tidak Ditemukan</h3>
                <p>
                    <?php if ($late_filter): ?>
                        Tidak ada data order yang terlambat atau mendekati due date (H-14) untuk customer yang dipilih. 
                        Silakan coba dengan customer lainnya atau nonaktifkan filter "Late + 2W".
                    <?php else: ?>
                        Tidak ada data order yang sesuai dengan filter customer yang dipilih. Silakan coba dengan customer lainnya.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>