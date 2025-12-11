<?php
session_start();
require_once __DIR__ . '/excel/SimpleXLSX.php';
require_once __DIR__ . '/excel/SimpleXLS.php';

$file = $_FILES['excel_file']['tmp_name'];
$name = $_FILES['excel_file']['name'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

$data = [];

if ($ext == 'xlsx') {
    if ($xlsx = Shuchkin\SimpleXLSX::parse($file)) {
        $data = $xlsx->rows();
    }
} elseif ($ext == 'xls') {
    if ($xls = Shuchkin\SimpleXLS::parse($file)) {
        $data = $xls->rows();
    }
} elseif ($ext == 'csv') {
    $data = array_map('str_getcsv', file($file));
}

$_SESSION['preview_data'] = $data;
?>

<h2>Preview Data</h2>
<form action="insert.php" method="POST">
    <table border="1" cellpadding="4">
        <?php
        if (!empty($data)) {
            // First row as headers
            $headers = $data[0];
            echo "<thead><tr>";
            foreach ($headers as $header) {
                echo "<th>" . htmlspecialchars($header) . "</th>";
            }
            echo "</tr></thead><tbody>";

            // Data rows
            for ($i = 1; $i < count($data); $i++) {
                $row = $data[$i];
                echo "<tr>";
                foreach ($row as $col) {
                    echo "<td>" . htmlspecialchars($col) . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody>";
        }
        ?>
    </table>

    <br>
    <button type="submit">Insert ke Database</button>
</form>
