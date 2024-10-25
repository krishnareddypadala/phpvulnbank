
<?php
function adminCheck()
{
	session_start();

	if(isset($_SESSION['uname']))
	{

	$user = $_SESSION['uname'];
	$con=mysqli_connect("127.0.0.1","groot","bose123$","bankdb");
	$result=mysqli_query($con,"select * from banktable where username='$user'");
 	$row = mysqli_fetch_row($result);
 	$admin=$row[8];

	If($admin > 0 )
	{
		
		return TRUE;
	}
	else
	{	
		return FALSE;
	}
		
	}

	else
	{
		echo "<script>alert('Out of session.. Please login')</script>"; 
		header("location:logout.php");
		
	}

}


?>
