<html>

<form name="id" method="GET" action="displaydata.php">

Account: <input type="text" name="aid"> <br><br>
<input type="submit" value="getAccount">
</form>

 <a href='profile.php'>profile</a>


<?php
session_start();
if(isset($_GET['aid']) )
{
  	$aid = $_GET['aid'];

 $con = mysqli_connect("localhost","root","bose123$","bankdb");

 
 //$aid= mysqli_real_escape_string($con,$aid);	

 $result=mysqli_query($con,"select * from banktable where acno=$aid");
 
 $row = mysqli_fetch_row($result);

 
 echo $row[1]." ".$row[2]." ".$row[3];
}
?>

</html>