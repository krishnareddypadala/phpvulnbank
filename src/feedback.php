<?php
session_start();
  if(isset($_SESSION['uname']))
	{

	$user = $_SESSION['uname'];
	echo $user;
	
	If($user == "admin")
	{
	header('Location:feedback_admin.php');
	}
	else
	{
	header('Location:feedback_user.php');
	}
		
	}

	else
	{
		echo "Out of session"; 		
	}

?>
