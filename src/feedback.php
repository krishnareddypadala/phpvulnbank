<?php include "ui/header2.php";?>
<?php
require __DIR__ . '/admincheck.php';

if(admincheck() == TRUE)
header('Location:feedback_admin.php');
else
header('Location:feedback_user.php');

?>
<?php include "ui/footer.php"; ?>