<?php include "ui/header2.php";?>
<?php

$file=trim($_GET['file']);

set_error_handler(
    function ($severity, $message, $file, $line) {
        throw new ErrorException($message, $severity, $severity, $file, $line);
    }
);

try {
    echo (file_get_contents($file));
}
catch (Exception $e) {
    echo $e->getMessage();
}

restore_error_handler();


?>

<?php include "ui/footer.php"; ?>