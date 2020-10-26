<html>

<br><br> <a href='logout.php'>Signout</a>

<br><br> <a href='transfer.php'>Transfer</a>

<br><br> <a href='profile.php'>Profile</a> <br><br>


<?php

session_start();

 if(isset($_SESSION['uname']))
	{ 
		$user=$_SESSION['uname'];

	if($user=="admin")
		{
		$con = mysqli_connect("localhost","root","bose123$","bankdb");
	 
		$result=mysqli_query($con,"select * from banktable");
		for($i=1;$i<6;$i++)
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

</html>