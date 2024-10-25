<?php include "ui/header2.php";?>
<html>

<form name="id" method="GET" action="displaydata_safe.php">

Account: <input type="text" name="aid"> <br><br>
<input type="submit" value="getAccount">
</form>

<?php
session_start();
if(isset($_GET['aid']) )
{
  	$aid = $_GET['aid'];


$con = mysqli_connect("127.0.0.1","groot","bose123$","bankdb");

$query= "select username,password FROM banktable where acno=?";

$stmt=mysqli_prepare($con,$query);
If($stmt)
{
	
mysqli_stmt_bind_param($stmt,"s",$aid);
mysqli_stmt_bind_result($stmt,$dbusername,$dbpassword);
mysqli_stmt_execute($stmt);
mysqli_stmt_fetch($stmt);
echo $dbusername."--".$dbpassword;
}
else
{echo "No stmt created";
}
	
 #$result=mysqli_query($con,"select * from banktable where acno='$aid'");
 
 #$row = mysqli_fetch_row($result);
 
 #echo $row[0]." ".$row[1]." ".$row[2]." ".$row[3]." ".$row[4];
 #$balance=$row[3];
	
}
?>

</html>

<?php include "ui/footer.php"; ?>