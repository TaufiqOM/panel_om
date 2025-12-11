<?php
// error_reporting(0);
// ini_set('display_errors', 0);

require __DIR__ . '/../inc/config.php';
header('Content-Type: application/json');
date_default_timezone_set("Asia/Jakarta");

// Set default time zone jakarta
date_default_timezone_set('Asia/Jakarta');

$action = $_POST['action'] ?? null;
$nik_post = $_POST['nik'] ?? null;         // untuk action=scan
$station_post = $_POST['station'] ?? null; // station dari front
// untuk action=insert_work
$code_post = $_POST['code'] ?? null;
$emp_nik_post = $_POST['emp_nik'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

/**
 * Utility: ambil station saat ini
 */
function get_current_station_from_post()
{
    if (!empty($_POST['station'])) {
        return strtoupper(trim($_POST['station']));
    }
    return '';
}

/**
 * Validate work code (barcode / SO code)
 */
function validate_work_code($conn, $code)
{
    $code = trim($code);

    // Jika mengandung angka 6 digit lalu "-", contoh: 123456-
    if (preg_match('/^\d{6}-/', $code)) {
        // Cek di tabel barcode_item dengan join ke barcode_lot untuk sale_order_id dan sale_order_ref
        $stmt = $conn->prepare("SELECT bi.*, bl.sale_order_id, bl.sale_order_ref FROM barcode_item bi LEFT JOIN barcode_lot bl ON bi.lot_id = bl.id WHERE bi.barcode = ? LIMIT 1");
        if (!$stmt) {
            return ['valid' => false, 'message' => 'System error: gagal prepare query barcode_item'];
        }
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            return [
                'valid' => true,
                'type' => 'product',
                'data' => $row
            ];
        }

        // Jika tidak ditemukan di barcode_item, coba cek di product
        $stmt2 = $conn->prepare("SELECT * FROM product WHERE barcode = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param("s", $code);
            $stmt2->execute();
            $r2 = $stmt2->get_result();
            if ($row2 = $r2->fetch_assoc()) {
                return [
                    'valid' => true,
                    'type' => 'product',
                    'data' => $row2
                ];
            }
        }

        // Tidak ditemukan
        return ['valid' => false, 'message' => 'Kode produk tidak ditemukan'];
    }

    // Selain itu, cek pada barcode_lot jika ada maka bekerja pada project atau SO tersebut
    $stmt = $conn->prepare("SELECT * FROM barcode_lot WHERE sale_order_ref = ? LIMIT 1");
    if (!$stmt) {
        return ['valid' => false, 'message' => 'System error: gagal prepare query barcode_lot'];
    }
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        return [
            'valid' => true,
            'type' => 'so',
            'data' => $row
        ];
    }
    return ['valid' => false, 'message' => 'Kode SO tidak ditemukan'];
}


/**
 * Move current activity of an employee into history.
 * Menggunakan employee_nik sebagai foreign key (barcode).
 * $overrides: array to override process, customer_name, production_code in history insert
 */
function move_to_history($conn, $employee_nik, $overrides = [])
{
    // ambil record aktif production_employee
    $stmt = $conn->prepare("SELECT * FROM production_employee WHERE employee_nik = ? LIMIT 1");
    if (!$stmt) {
        error_log("prepare failed move_to_history: " . $conn->error);
        return false;
    }
    $stmt->bind_param("s", $employee_nik);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$row = $res->fetch_assoc()) {
        // tidak ada record aktif
        return true;
    }

    // safety: tetap insert history jika ada scan_in
    $now = date("Y-m-d H:i:s");

    if (empty($row['scan_in'])) {
        error_log("move_to_history: scan_in kosong untuk " . $employee_nik);
        return false;
    }

    $scan_in = $row['scan_in'];
    $scan_out = $now;

    $time_in = strtotime($scan_in);
    $time_out = strtotime($scan_out);
    if ($time_in === false || $time_out === false) {
        error_log("move_to_history: waktu gagal parse");
        return false;
    }

    $duration = $time_out - $time_in;
    if ($duration <= 0) $duration = 1;

    // insert ke history (tanpa id_employee)
    $stmt_hist = $conn->prepare("
        INSERT INTO production_employee_history
        (employee_nik, employee_fullname, work, process,
         customer_name, production_code, station_code, scan_in, scan_out, duration_seconds, description, sale_order_id, sale_order_ref)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    if (!$stmt_hist) {
        error_log("prepare hist failed: " . $conn->error);
        return false;
    }

    $employee_nik_val = $row['employee_nik'] ?? $employee_nik;
    $employee_fullname = $row['employee_fullname'] ?? null;
    $activity = $row['work'] ?? null;
    $process = $overrides['process'] ?? $row['process'] ?? null;
    $customer_name = $overrides['customer_name'] ?? $row['customer_name'] ?? null;
    $production_code = $overrides['production_code'] ?? $row['production_code'] ?? null;
    $station_code = $row['station_code'] ?? null;
    $description = $row['description'] ?? null;
    $sale_order_id = $row['sale_order_id'] ?? null;
    $sale_order_ref = $row['sale_order_ref'] ?? null;

    // types: 9 strings + int + string + int + string => "sssssssssisis"
    $stmt_hist->bind_param(
        "sssssssssisis",
        $employee_nik_val,
        $employee_fullname,
        $activity,
        $process,
        $customer_name,
        $production_code,
        $station_code,
        $scan_in,
        $scan_out,
        $duration,
        $description,
        $sale_order_id,
        $sale_order_ref
    );

    if (!$stmt_hist->execute()) {
        error_log("gagal insert history: " . $stmt_hist->error);
        // lanjutkan, kita tetap update duration di production_employee
    }

    // update duration_seconds in production_employee (opsional)
    $stmt_update = $conn->prepare("UPDATE production_employee SET duration_seconds = ? WHERE employee_nik = ?");
    if ($stmt_update) {
        $stmt_update->bind_param("is", $duration, $employee_nik);
        $stmt_update->execute();
    }

    return true;
}

/**
 * ACTION: scan
 */
if ($action === 'scan') {
    $nik = trim($nik_post ?? '');
    $station = get_current_station_from_post();

    if (!$nik) {
        echo json_encode(['status' => 'not_found', 'message' => 'NIK tidak dikirim']);
        exit;
    }

    // Check if product barcode pattern
    if (preg_match('/^\d{6}-/', $nik)) {
        // (product scanning code tetap sama seperti sebelumnya)
        $production_code = $nik;

        // Cek apakah barcode sudah masuk storage
        $stmt_check_strg = $conn->prepare("SELECT * FROM production_lots_strg WHERE production_code = ? LIMIT 1");
        $stmt_check_strg->bind_param("s", $production_code);
        $stmt_check_strg->execute();
        $res_check_strg = $stmt_check_strg->get_result();
        if ($res_check_strg->fetch_assoc()) {
            echo json_encode(['status' => 'error', 'message' => 'Barcode sudah masuk storage']);
            exit;
        }

        $stmt_check = $conn->prepare("SELECT pl.*, s.station_name FROM production_lots pl LEFT JOIN stations s ON pl.station_code = s.station_code WHERE pl.production_code = ? LIMIT 1");
        $stmt_check->bind_param("s", $production_code);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();

        if ($row = $res_check->fetch_assoc()) {
            // PRODUK SUDAH ADA DI DATABASE PRODUCTION_LOTS

            // ============================================================
            // LOGIKA BARU: Jika produk sudah ada di station yang sama
            // ============================================================
            if ($row['station_code'] == $station) {
                // 1. Cari semua karyawan yang sedang mengerjakan barcode ini
                $stmt_emp = $conn->prepare("
                    SELECT * FROM production_employee 
                    WHERE production_code = ? 
                    AND station_code = ? 
                    AND status = 1
                ");
                $stmt_emp->bind_param("ss", $production_code, $station);
                $stmt_emp->execute();
                $res_emp = $stmt_emp->get_result();

                // 2. Pindahkan semua karyawan tersebut ke history dan hapus
                while ($emp_row = $res_emp->fetch_assoc()) {
                    move_to_history($conn, $emp_row['employee_nik']);

                    $stmt_del_emp = $conn->prepare("DELETE FROM production_employee WHERE employee_nik = ?");
                    $stmt_del_emp->bind_param("s", $emp_row['employee_nik']);
                    $stmt_del_emp->execute();
                }

                // 3. Pindahkan produk ke history
                $stmt_hist = $conn->prepare("
                    INSERT INTO production_lots_history 
                    (customer_name, so_name, product_code, production_code, station_code, scan_in, scan_out, sale_order_id, sale_order_ref) 
                    VALUES (?,?,?,?,?,?, NOW(), ?, ?)
                ");
                $stmt_hist->bind_param(
                    "ssssssis",
                    $row['customer_name'],
                    $row['so_name'],
                    $row['product_code'],
                    $row['production_code'],
                    $row['station_code'],
                    $row['scan_in'],
                    $row['sale_order_id'],
                    $row['sale_order_ref']
                );

                if ($stmt_hist->execute()) {
                    // Jika station adalah PA1, insert juga ke production_lots_strg
                    if ($row['station_code'] == 'PA1') {
                        // Ambil data lengkap dari barcode_item dan barcode_lot untuk relasi
                        // $stmt_fetch_bi = $conn->prepare("SELECT bi.*, bl.sale_order_name, bl.customer_name, bl.sale_order_id, bl.sale_order_ref, bl.mrp_id, bl.sale_order_line_id, bi.lot_id FROM barcode_item bi LEFT JOIN barcode_lot bl ON bi.lot_id = bl.id WHERE bi.barcode = ? LIMIT 1");
                        $stmt_fetch_bi = $conn->prepare("SELECT bi.*, bl.sale_order_name, bl.customer_name, bl.sale_order_id, bl.sale_order_ref, bl.sale_order_line_id, bi.lot_id FROM barcode_item bi LEFT JOIN barcode_lot bl ON bi.lot_id = bl.id WHERE bi.barcode = ? LIMIT 1");

                        if (!$stmt_fetch_bi) {
                            die("Prepare failed: " . $conn->error);
                        }

                        $stmt_fetch_bi->bind_param("s", $production_code);
                        $stmt_fetch_bi->execute();
                        $res_fetch_bi = $stmt_fetch_bi->get_result();
                        $row_bi = $res_fetch_bi->fetch_assoc();

                        if ($row_bi) {
                            $stmt_strg = $conn->prepare("
                                INSERT INTO production_lots_strg
                                (customer_name, so_name, sale_order_id, sale_order_ref, product_code, production_code)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $stmt_strg->bind_param(
                                "ssisss",
                                $row['customer_name'],
                                $row['so_name'],
                                $row['sale_order_id'],
                                $row['sale_order_ref'],
                                $row['product_code'],
                                $row['production_code']
                            );
                            if ($stmt_strg->execute()) {
                                // Berhasil insert, barcode sudah masuk storage
                            } else {
                                error_log("Gagal insert production_lots_strg: " . $stmt_strg->error);
                            }
                        } else {
                            error_log("Barcode item tidak ditemukan untuk production_code: " . $production_code);
                        }
                    }

                    // 4. Hapus produk dari production_lots
                    $stmt_del = $conn->prepare("DELETE FROM production_lots WHERE id = ?");
                    $stmt_del->bind_param("i", $row['id']);

                    if ($stmt_del->execute()) {
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Produk berhasil dikeluarkan dari station.'
                        ]);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Gagal delete Product']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Gagal insert history produk']);
                }
                exit;
            } else {
                // Produk ada di station lain
                $station_name = $row['station_name'] ?? $row['station_code'];
                echo json_encode(['status' => 'error', 'message' => 'Product sedang berada pada station ' . $station_name]);
                exit;
            }
        } else {
            // PRODUK BELUM ADA DI DATABASE PRODUCTION_LOTS (insert baru)
            $stmt_fetch = $conn->prepare("SELECT bi.*, bl.sale_order_name, bl.customer_name, bl.sale_order_id, bl.sale_order_ref FROM barcode_item bi LEFT JOIN barcode_lot bl ON bi.lot_id = bl.id WHERE bi.barcode = ? LIMIT 1");
            $stmt_fetch->bind_param("s", $production_code);
            $stmt_fetch->execute();
            $res_fetch = $stmt_fetch->get_result();

            if ($row_fetch = $res_fetch->fetch_assoc()) {
                $stmt_st = $conn->prepare("SELECT station_code FROM stations WHERE station_code = ? LIMIT 1");
                $stmt_st->bind_param("s", $station);
                $stmt_st->execute();
                $res_st = $stmt_st->get_result();

                if (!$res_st->fetch_assoc()) {
                    echo json_encode(['status' => 'error', 'message' => 'Station tidak ditemukan']);
                    exit;
                }

                $stmt_ins = $conn->prepare("
                    INSERT INTO production_lots 
                    (customer_name, so_name, product_code, production_code, station_code, scan_in, sale_order_id, sale_order_ref) 
                    VALUES (?,?,?,?,?,NOW(),?,?)
                ");

                $customer_name = (string)($row_fetch['customer_name'] ?? '');
                $so_name = (string)($row_fetch['sale_order_name'] ?? '');
                $product_code = (int)$row_fetch['product_id'];
                $production_code = (string)$row_fetch['barcode'];
                $sale_order_id_val = $row_fetch['sale_order_id'] ? (int)$row_fetch['sale_order_id'] : null;
                $sale_order_ref_val = $row_fetch['sale_order_ref'] ?? null;

                $stmt_ins->bind_param("ssissis", $customer_name, $so_name, $product_code, $production_code, $station, $sale_order_id_val, $sale_order_ref_val);

                if ($stmt_ins->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Berhasil mengaktifkan product']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Gagal insert production_lots: ' . $conn->error]);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Product tidak terdeteksi']);
            }
        }
        exit;
    }

    // Not product pattern, treat as employee
    $stmt = $conn->prepare("SELECT * FROM employee WHERE barcode = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'System error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $nik);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$emp = $res->fetch_assoc()) {
        echo json_encode(['status' => 'not_found', 'message' => 'Employee tidak terdaftar']);
        exit;
    }

    // cek apakah sudah ada record aktif di production_employee
    $stmt2 = $conn->prepare("SELECT * FROM production_employee WHERE employee_nik = ? LIMIT 1");
    $stmt2->bind_param("s", $nik);
    $stmt2->execute();
    $res2 = $stmt2->get_result();

    $auto_resume_activities = ['Transfer', 'Toilet', 'Gudang H/W', 'Istirahat', 'Kepentingan Divisi/HRD', 'Sholat Sore'];

    if ($row = $res2->fetch_assoc()) {
        if ($row['status'] == 0 && in_array($row['description'], $auto_resume_activities)) {
            $overrides = ['process' => null, 'customer_name' => null, 'production_code' => null, 'work' => null];
            $moved = move_to_history($conn, $nik, $overrides);
            if (!$moved) {
                echo json_encode(['status' => 'error', 'message' => 'Gagal memindahkan break ke history']);
                exit;
            }

            $stmt_up = $conn->prepare("UPDATE production_employee SET status = 1, description = '', scan_in = NOW() WHERE employee_nik = ?");
            $stmt_up->bind_param("s", $nik);
            if ($stmt_up->execute()) {
                echo json_encode(['status' => 'resumed', 'message' => 'Pekerjaan dilanjutkan otomatis']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal resume: ' . $conn->error]);
            }
            exit;
        }

        // Sudah ada record aktif -> tampilkan pilihan break/transfer/dll (frontend menampilkan modal)
        echo json_encode([
            'status' => 'choose_break',
            'employee' => [
                'employee_nik' => $row['employee_nik'],
                'employee_name' => $row['employee_fullname'] ?? $emp['name']
            ]
        ]);
        exit;
    }

    // Belum ada record aktif => langsung minta scan id product / id so
    echo json_encode([
        'status' => 'input_code',
        'employee' => [
            'employee_nik' => $emp['barcode'],
            'employee_name' => $emp['name'] ?? ($emp['employee_fullname'] ?? '')
        ],
        'station' => $station
    ]);
    exit;
}

/**
 * ACTION: activity
 */
if ($action === 'activity') {
    $activity = $_POST['activity'] ?? null; // mis: 'bekerja' atau 'toilet' atau 'pulang'
    $code = $_POST['code'] ?? null; // dipakai bila activity == 'bekerja'
    $nik = $_POST['nik'] ?? null;   // employee NIK
    $station = get_current_station_from_post();

    if (!$activity || !$nik) {
        echo json_encode(['success' => false, 'message' => 'Aktivitas / NIK tidak valid']);
        exit;
    }

    $end_activities = ['pulang', 'pulang_dini', 'pindah_bagian'];

    $activity_map = [
        'transfer'      => 'Transfer',
        'toilet'        => 'Toilet',
        'pindah_bagian' => 'Pindah Bagian',
        'gudang'        => 'Gudang H/W',
        'istirahat'     => 'Istirahat',
        'pulang_dini'   => 'Pulang Dini',
        'hrd'           => 'Kepentingan Divisi/HRD',
        'pulang'        => 'Pulang',
        'sholat'        => 'Sholat Sore',
    ];

    $break_activities = array_keys($activity_map);

    if (!in_array($activity, $end_activities) && !in_array($activity, $break_activities)) {
        if (!$code) {
            echo json_encode(['success' => false, 'message' => 'Kode SO atau Barcode wajib diisi']);
            exit;
        }

        $validation = validate_work_code($conn, $code);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'message' => $validation['message']]);
            exit;
        }

        if ($validation['type'] === 'so') {
            $production_code = $validation['data']['sale_order_name'];
            $customer_name = $validation['data']['customer_name'] ?? null;
            $process = 'order';
            $description = "";
            $sale_order_id = $validation['data']['sale_order_id'] ?? null;
            $sale_order_ref = $validation['data']['sale_order_ref'] ?? null;
        } else {
            $production_code = $validation['data']['barcode'] ?? $code;
            $customer_name = $validation['data']['customer_name'] ?? null;
            $process = 'product';
            $description = "";
            $sale_order_id = $validation['data']['sale_order_id'] ?? null;
            $sale_order_ref = $validation['data']['sale_order_ref'] ?? null;
        }

        // Cek apakah ada record
        $stmt_check = $conn->prepare("SELECT * FROM production_employee WHERE employee_nik = ? LIMIT 1");
        $stmt_check->bind_param("s", $nik);
        $stmt_check->execute();
        $rcheck = $stmt_check->get_result();

        if ($rcheck->fetch_assoc()) {
            $overrides = ['process' => null, 'customer_name' => null, 'production_code' => null];
            $moved_break = move_to_history($conn, $nik, $overrides);
            if (!$moved_break) {
                echo json_encode(['success' => false, 'message' => 'Gagal memindahkan break ke history']);
                exit;
            }

            $stmt_up = $conn->prepare("UPDATE production_employee SET description = '', scan_in = NOW(), scan_out = NULL, status = 1 WHERE employee_nik = ?");
            $stmt_up->bind_param("s", $nik);
            if ($stmt_up->execute()) {
                echo json_encode(['success' => true, 'message' => "Aktivitas dilanjutkan"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui: ' . $conn->error]);
            }
            exit;
        } else {
            // insert baru (tanpa id_employee)
            $stmt_emp = $conn->prepare("SELECT * FROM employee WHERE barcode = ? LIMIT 1");
            $stmt_emp->bind_param("s", $nik);
            $stmt_emp->execute();
            $remp = $stmt_emp->get_result()->fetch_assoc();
            if (!$remp) {
                echo json_encode(['success' => false, 'message' => 'Employee data not found']);
                exit;
            }

            $employee_fullname = $remp['name'] ?? ($remp['employee_fullname'] ?? null);
            $status_val = 1;
            $scan_in = date("Y-m-d H:i:s");

            $stmt_ins = $conn->prepare("
                INSERT INTO production_employee
                (employee_nik, employee_fullname, work, process, customer_name, production_code, station_code, scan_in, status, description, sale_order_id, sale_order_ref)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            if ($stmt_ins) {
                // types: 10 strings + int + 2 strings
                $stmt_ins->bind_param(
                    "ssssssssisss",
                    $nik,
                    $employee_fullname,
                    $activity,
                    $process,
                    $customer_name,
                    $production_code,
                    $station,
                    $scan_in,
                    $status_val,
                    $description,
                    $sale_order_id,
                    $sale_order_ref
                );
                if ($stmt_ins->execute()) {
                    echo json_encode(['success' => true, 'message' => "Aktivitas dimulai"]);
                } else {
                    // fallback simpler insert
                    $stmt_simple = $conn->prepare("INSERT INTO production_employee (employee_nik, employee_fullname, work, process, customer_name, production_code, station_code, scan_in, status, description, sale_order_id, sale_order_ref) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                    if ($stmt_simple) {
                        $stmt_simple->bind_param("ssssssssisss", $nik, $employee_fullname, $activity, $process, $customer_name, $production_code, $station, date("Y-m-d H:i:s"), $status_val, $description, $sale_order_id, $sale_order_ref);
                        $stmt_simple->execute();
                        echo json_encode(['success' => true, 'message' => "Aktivitas dimulai"]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Gagal insert production_employee: ' . $conn->error]);
                    }
                }
            } else {
                $stmt_simple = $conn->prepare("INSERT INTO production_employee (employee_nik, employee_fullname, work, process, customer_name, production_code, station_code, scan_in, status, description, sale_order_id, sale_order_ref) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                if ($stmt_simple) {
                    $stmt_simple->bind_param("ssssssssisss", $nik, $employee_fullname, $activity, $process, $customer_name, $production_code, $station, date("Y-m-d H:i:s"), $status_val, $description, $sale_order_id, $sale_order_ref);
                    $stmt_simple->execute();
                    echo json_encode(['success' => true, 'message' => "Aktivitas dimulai"]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Gagal insert production_employee: ' . $conn->error]);
                }
            }
            exit;
        }
    }

    // jika activity termasuk end_activities -> move history dan hapus record production_employee
    if (in_array($activity, $end_activities)) {
        $moved = move_to_history($conn, $nik);
        if (!$moved) {
            echo json_encode(['success' => false, 'message' => 'Gagal memindahkan history sebelum hapus']);
            exit;
        }

        $stmt_del = $conn->prepare("DELETE FROM production_employee WHERE employee_nik = ?");
        $stmt_del->bind_param("s", $nik);
        if ($stmt_del->execute()) {
            echo json_encode(['success' => true, 'message' => 'Aktivitas selesai. Terimakasih']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus record: ' . $conn->error]);
        }
        exit;
    }

    // jika activity termasuk break_activities -> insert current work to history, then update record to break status
    if (in_array($activity, $break_activities)) {
        $moved = move_to_history($conn, $nik);
        if (!$moved) {
            echo json_encode(['success' => false, 'message' => 'Gagal memindahkan history sebelum break']);
            exit;
        }

        $desc = $activity_map[$activity] ?? ucfirst(str_replace('_', ' ', $activity));

        $stmt_up_break = $conn->prepare("UPDATE production_employee SET status = 0, scan_in = NOW(), description = ? WHERE employee_nik = ?");
        $stmt_up_break->bind_param("ss", $desc, $nik);
        if ($stmt_up_break->execute()) {
            echo json_encode(['success' => true, 'message' => "Berhasil memulai '$desc'"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal update break: ' . $conn->error]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aktivitas tidak dikenali']);
    exit;
}

/**
 * ACTION: insert_work
 */
if ($action === 'insert_work') {
    $code = trim($code_post ?? '');
    $emp_nik = trim($emp_nik_post ?? '');
    $station = get_current_station_from_post();

    if (!$code || !$emp_nik) {
        echo json_encode(['success' => false, 'message' => 'Kode dan NIK wajib diisi']);
        exit;
    }

    // cek employee master
    $stmt = $conn->prepare("SELECT * FROM employee WHERE barcode = ? LIMIT 1");
    $stmt->bind_param("s", $emp_nik);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$emp = $res->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Employee tidak terdaftar']);
        exit;
    }

    // validasi code
    $validation = validate_work_code($conn, $code);
    if (!$validation['valid']) {
        echo json_encode(['success' => false, 'message' => $validation['message']]);
        exit;
    }

    $special_stations = ['GKY', 'CS3', 'SP1'];
    if (in_array($station, $special_stations)) {
        echo json_encode([
            'status' => 'choose_sub',
            'employee' => [
                'employee_nik' => $emp['barcode'],
                'employee_name' => $emp['name'] ?? ($emp['employee_fullname'] ?? '')
            ],
            'code_info' => $validation['data'],
            'code' => $code,
            'message' => 'Pilih sub-aktivitas untuk station ' . $station
        ]);
        exit;
    }

    $work = 'project';
    if ($validation['type'] === 'so') {
        $production_code = $validation['data']['sale_order_name'];
        $customer_name = $validation['data']['customer_name'] ?? null;
        $process = 'order';
        $description = "";
        $sale_order_id = $validation['data']['sale_order_id'] ?? null;
        $sale_order_ref = $validation['data']['sale_order_ref'] ?? null;
    } else {
        echo json_encode(['success' => false, 'message' => 'Product harus discan langsung']);
        exit;
    }

    // apabila sudah ada record -> move_to_history lalu delete
    $stmt_check = $conn->prepare("SELECT * FROM production_employee WHERE employee_nik = ? LIMIT 1");
    $stmt_check->bind_param("s", $emp_nik);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($exist = $res_check->fetch_assoc()) {
        move_to_history($conn, $emp_nik);
        $stmt_del = $conn->prepare("DELETE FROM production_employee WHERE employee_nik = ?");
        $stmt_del->bind_param("s", $emp_nik);
        $stmt_del->execute();
    }

    // insert (tanpa id_employee)
    $stmt_insert = $conn->prepare("INSERT INTO production_employee (employee_nik, employee_fullname, work, description, production_code, customer_name, process, station_code, sale_order_id, sale_order_ref, scan_in, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)");
    if (!$stmt_insert) {
        echo json_encode(['success' => false, 'message' => 'Prepare insert failed: ' . $conn->error]);
        exit;
    }

    $employee_fullname = $emp['name'] ?? ($emp['employee_fullname'] ?? '');
    $customer_name = (string)($validation['data']['customer_name'] ?? '');
    $scan_in = date("Y-m-d H:i:s");
    $sale_order_id_val = $sale_order_id ? intval($sale_order_id) : null;
    $sale_order_ref_val = $sale_order_ref ?? null;

    $stmt_insert->bind_param("ssssssssiss", $emp_nik, $employee_fullname, $work, $description, $production_code, $customer_name, $process, $station, $sale_order_id_val, $sale_order_ref_val, $scan_in);

    if ($stmt_insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Work inserted dan aktivitas dimulai']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal insert work: ' . $stmt_insert->error]);
    }
    exit;
}

/**
 * ACTION: check_product
 */
if ($action === 'check_product') {
    $code = trim($_POST['code'] ?? '');
    $emp_nik = trim($_POST['emp_nik'] ?? '');
    $station = get_current_station_from_post();

    if (!$code || !$emp_nik) {
        echo json_encode(['success' => false, 'message' => 'Kode dan NIK wajib diisi']);
        exit;
    }

    $stmt_check = $conn->prepare("SELECT * FROM production_lots WHERE production_code = ? LIMIT 1");
    $stmt_check->bind_param("s", $code);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if (!$row = $res_check->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'Product tidak ada']);
        exit;
    }

    if ($row['station_code'] != $station) {
        echo json_encode(['success' => false, 'message' => 'Product sudah aktif pada station ' . $row['station_code']]);
        exit;
    }

    $stmt_emp = $conn->prepare("SELECT * FROM employee WHERE barcode = ? LIMIT 1");
    $stmt_emp->bind_param("s", $emp_nik);
    $stmt_emp->execute();
    $remp = $stmt_emp->get_result()->fetch_assoc();
    if (!$remp) {
        echo json_encode(['success' => false, 'message' => 'Employee data not found']);
        exit;
    }

    $stmt_ins = $conn->prepare("
        INSERT INTO production_employee
        (employee_nik, employee_fullname, work, process, customer_name, production_code, station_code, scan_in, status, description, sale_order_id, sale_order_ref)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $employee_fullname = $remp['name'] ?? ($remp['employee_fullname'] ?? null);
    $work = 'product';
    $process = 'product';
    $customer_name = $row['customer_name'];
    $production_code = $code;
    $scan_in = date("Y-m-d H:i:s");
    $status = 1;
    $description = "";
    $sale_order_id = $row['sale_order_id'] ?? null;
    $sale_order_ref = $row['sale_order_ref'] ?? null;

    $stmt_ins->bind_param(
        "ssssssssisss",
        $emp_nik,
        $employee_fullname,
        $work,
        $process,
        $customer_name,
        $production_code,
        $station,
        $scan_in,
        $status,
        $description,
        $sale_order_id,
        $sale_order_ref
    );

    if ($stmt_ins->execute()) {
        echo json_encode(['success' => true, 'message' => 'Karyawan bekerja pada product']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal assign employee: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak dikenali']);
exit;
