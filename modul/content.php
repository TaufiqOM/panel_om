<?php

    include "../inc/config.php";

    $_php_file = str_replace('_', '/', $_GET['module']);
    if(is_dir($_php_file)) {
        $_php_file .= '/index.php';
    } else {
        $_php_file .= '.php';
    }
    if(file_exists($_php_file)) {
        require $_php_file;
    } else {
        echo "<p>Modul tidak ada.</p>";
    }

?>
