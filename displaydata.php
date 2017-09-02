<html>

<form name="id" method="GET" action="displaydata.php">

Account: <input type="text" name="aid"> <br><br>
<input type="submit" value="getAccount">
</form>


<?php
session_start();
if(isset($_GET['aid']) )
{
  	$aid = $_GET['aid'];

	
$con = mysqli_connect("localhost","root","happy123$","bankdb");
	
 $result=mysqli_query($con,"select * from banktable where acno='$aid'");
 
 $row = mysqli_fetch_row($result);

 
 echo $row[1]." ".$row[2]." ".$row[3];
}
?>

</html>