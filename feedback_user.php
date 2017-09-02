<html>

<form name="feedback" method="POST" action="feedback_user.php">

Update Feedback: <input type="text" name="fb"> <br><br>
<input type="submit" value="Feedback">
<br><br> <a href='logout.php'>Signout</a>

<br><br> <a href='transfer.php'>Transfer</a>

<br><br> <a href='profile.php'>Profile</a>
</form>


<?php
session_start();
if(isset($_POST['fb']) )
{
  if(isset($_SESSION['uname']))
	{

	$user = $_SESSION['uname'];
	$fb = $_POST['fb'];


	$con = mysqli_connect("localhost","root","happy123$","bankdb");

	mysqli_query($con,"UPDATE banktable SET feedback='$fb' where username='$user'");
	
						//UPDATE banktable SET feedback='test' where TRUE
	echo "Update completed..!!!!!";
	
	}

	else
	{
		echo "Out of session"; 		
	}

}
?>

</html>