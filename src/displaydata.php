<?php include "ui/header2.php";?>
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

 $con = mysqli_connect("127.0.0.1","groot","bose123$","bankdb");
echo "hello Wordls";
 if($con->connect_errno)
 	{
	echo $con->connect_error;
   die('Unable to connect to database [' . $con->connect_error . ']');
	} 
 
 //$aid= mysqli_real_escape_string($con,$aid);	

 $result=mysqli_query($con,"select * from banktable where acno=$aid");
 
 $row = mysqli_fetch_row($result);

 
 echo $row[1]." ".$row[2]." ".$row[3];
}
?>

</html>

<?php include "ui/footer.php"; ?>