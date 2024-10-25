<?php include "ui/header2.php";?>
<html>

<form name="transfer" method="POST" action="transfer_csrftoken.php">

To Account : <input type="text" name="tacno"> <br><br>
Transfer Amount: <input type="text" name="tamount"> <br><br>
<?php 
session_start(); 
if(!isset($_SESSION['token']))
{
$token=md5(uniqid(rand(),TRUE)); 
$_SESSION['token'] =$token;

}
else
$token=$_SESSION['token'];

?>
<input type="hidden" name="csrftoken" value="<?php echo $token; ?>">
<input type="submit" value="Transfer">
<br><br> <a href='profile.php'>Profile</a>
<br><br> <a href='logout.php'>Signout</a>

</form> 


<?php
#session_start();
if(isset($_SESSION['uname']))
{
 
  if(isset($_POST['tacno']) )
	{
	if($_POST['csrftoken']==$_SESSION['token'])
	{

	$fuser = $_SESSION['uname'];
	$tacno = $_POST['tacno'];
	$tamount = $_POST['tamount'];	

	#echo $fuser;
	#echo $tacno;
	#echo $tamount;

	$con = mysqli_connect("127.0.0.1","groot","bose123$","bankdb");
#---------------------------------------------------------------------------------------#	 
	$fresult = mysqli_query($con,"select * from banktable where username='$fuser'");
                                  
	$frow=mysqli_fetch_row($fresult);

	$fbalance=$frow[3]-$tamount;
	
	#echo $fbalance;
	

	mysqli_query($con,"UPDATE banktable SET balance='$fbalance' where username='$fuser'");

#-----------------------------------------------------------------------------#
	$tresult = mysqli_query($con,"select * from banktable where acno='$tacno'");
                                  
	$trow=mysqli_fetch_row($tresult);

	$tbalance=$trow[3]+$tamount;
	
	#echo $fbalance;
	

	mysqli_query($con,"UPDATE banktable SET balance='$tbalance' where acno='$tacno'");
#-------------------------------------------------------------------------------------#
	
	echo "Transfer Completed...!! your savings account balance : '$fbalance'";
	
	unset($_SESSION['token']);
	
	}
	}
}

	else
	{
		header('Location:login.html'); 		
	}


?>

</html>
<?php include "ui/footer.php"; ?>