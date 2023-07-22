<?php include "ui/header2.php";?>
List of KYC Documents uploaded by users<br><br>
<?php
$i=1;
if ($handle = opendir('images')) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            echo "$i: <a href='kycdownload_ssrf.php?file=./images/$entry'>$entry</a><br>";
		$i=$i+1;
		
        }
    }
    closedir($handle);
}
?>

<br><br><a href="profile.php">profile</a>
<?php include "ui/footer.php"; ?>