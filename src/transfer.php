
<?php include "ui/header2.php";?>
<html>


<form name="transfer" action="transfer.php" onsubmit="return checkInp()" method="POST">

To Account : <input type="text" name="tacno"> <br><br>
Transfer Amount: <input type="text" name="tamount"> <br><br>
<input type="submit" value="Transfer">
<br><br> <a href='profile.php'>Profile</a>
<br><br> <a href='logout.php'>Signout</a>
</form>

<script>
function checkInp()
{
	
	var x=document.forms["transfer"]["tacno"].value;

	if(isNaN(x))
	{
		document.write(x+" is not a number");
		return false;
	}
}
</script>

<?php
session_start();
If (isset($_SESSION['uname']))
{
if(isset($_POST['tacno']) )
{
  if(isset($_SESSION['uname']))
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
	
	}

	else
	{
		echo "Out of session"; 		
	}

}
}
else
	
	{
		header('Location:login.php');
	}
?>

</html>

<?php include "ui/footer.php"; ?>
