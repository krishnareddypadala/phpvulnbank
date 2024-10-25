<?php include "ui/header2.php";?>
<html>
<?php
require __DIR__ . '/admincheck.php';

session_start();

 if(isset($_SESSION['uname']))
	{ 
		$user=$_SESSION['uname'];

	if(admincheck() == TRUE)
		{
		$con = mysqli_connect("127.0.0.1","groot","bose123$","bankdb");
	 
		$result=mysqli_query($con,"select * from banktable");
		$num=mysqli_num_rows($result);

		for($i=1;$i<$num;$i++)
			{ 
			$row=mysqli_fetch_row($result);
			$user=$row[1];
			$row[6]=$row[6];
			$fback=$row[6];
			echo "$user : $fback <br><br>";
			}
		}
		else
			
			{
				echo "You are Not Admin..!!!";
			}
	
	}
else
	{
		header('Location:login.html');
		
	}


?>
<br><br> <a href='logout.php'>Signout</a> | <a href='transfer.php'>Transfer</a> | <a href='profile.php'>Profile</a> <br><br>

</html>

<?php include "ui/footer.php"; ?>