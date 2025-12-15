<?php
require_once __DIR__ . '/config.php';

$encryption_key = 'siomas_odoo_secret_key_2024'; // Ganti dengan key yang aman

function encrypt_password($password) {
    global $encryption_key;
    return openssl_encrypt($password, 'aes-256-cbc', $encryption_key, 0, substr($encryption_key, 0, 16));
}

function decrypt_password($encrypted) {
    global $encryption_key;
    return openssl_decrypt($encrypted, 'aes-256-cbc', $encryption_key, 0, substr($encryption_key, 0, 16));
}

function odooConnectionInfo($username){
    global $conn;
    @session_start();

    // Jika sudah login panel, tetap gunakan user id=1 di Odoo
    if (isset($_SESSION['employee_logged_in']) && $_SESSION['employee_logged_in'] === true) {
        $stmt = $conn->prepare("SELECT uid, password FROM user_accounts WHERE id = 1 LIMIT 1");
    } else {
        // Jika belum login, juga gunakan user id=1
        $stmt = $conn->prepare("SELECT uid, password FROM user_accounts WHERE id = 1 LIMIT 1");
    }

    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result) {
        return [
            'url'      => 'https://om-omegamas.odoo.com/jsonrpc', //url odoo
            'db'       => 'om-omegamas-main-17240508', // db odoo
            'uid'      => (int)$result['uid'],
            'password' => decrypt_password($result['password']),
        ];
    }

    // if ($result) {
    //     return [
    //         'url'      => 'https://om-omegamas-staging-26402715.dev.odoo.com/jsonrpc', //url odoo
    //         'db'       => 'om-omegamas-staging-26402715', // db odoo
    //         'uid'      => (int)$result['uid'],
    //         'password' => decrypt_password($result['password']),
    //     ];
    // }


    return null;
}

function callOdoo($username, $model, $method, $args = [], $kwargs = []) {
    $connInfo = odooConnectionInfo($username);
    if (!$connInfo) {
        error_log("Odoo connection info failed for username: $username");
        return false;
    }

    $data = [
        "jsonrpc" => "2.0",
        "method" => "call",
        "params" => [
            "service" => "object",
            "method"  => "execute_kw",
            "args"    => [
                $connInfo['db'],
                $connInfo['uid'],
                $connInfo['password'],
                $model,
                $method,
                $args,
                $kwargs
            ]
        ],
        "id" => 1
    ];

    $curl = curl_init($connInfo['url']);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false) {
        error_log("Curl error for $username: " . curl_error($curl));
        return false;
    }

    $decoded = json_decode($response, true);
    if (!$decoded || !isset($decoded['result'])) {
        error_log("Odoo call failed for $username: HTTP $httpCode, response: " . substr($response, 0, 500));
        return false;
    }

    return $decoded['result'];
}

/**
 * Fungsi khusus untuk create data
 */
function callOdooCreate($username, $model, $data) {
    return callOdoo($username, $model, 'create', [$data]);
}

/**
 * Fungsi khusus untuk write data
 */
function callOdooWrite($username, $model, $ids = [], $data = []) {
    return callOdoo($username, $model, 'write', [$ids, $data]);
}


/**
 * Fungsi khusus untuk read data
 */
function callOdooRead($username, $model, $domain = [], $fields = []) {
    return callOdoo($username, $model, 'search_read', [$domain], ['fields' => $fields]);
}

/**
 * Fungsi khusus wizard confirm
 */
function callOdooRaw($username, $model, $method, $args = [], $kwargs = []) {
    $connInfo = odooConnectionInfo($username);
    if (!$connInfo) {
        error_log("Odoo connection info failed for username: $username");
        return false;
    }

    $data = [
        "jsonrpc" => "2.0",
        "method" => "call",
        "params" => [
            "service" => "object",
            "method"  => "execute_kw",
            "args"    => [
                $connInfo['db'],
                $connInfo['uid'],
                $connInfo['password'],
                $model,
                $method,
                $args,
                $kwargs
            ]
        ],
        "id" => 1
    ];

    $curl = curl_init($connInfo['url']);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false) {
        error_log("Curl error for $username: " . curl_error($curl));
        return false;
    }

    $decoded = json_decode($response, true);
    if (!$decoded) {
        error_log("Invalid JSON from Odoo for $username: HTTP $httpCode, response: " . substr($response, 0, 500));
        return false;
    }

    // Kembalikan hasil mentah (bisa berisi 'result' atau 'error')
    return $decoded;
}

?>