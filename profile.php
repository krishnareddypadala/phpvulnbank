<?php
session_start();
if(isset($_SESSION['uname']))
{
$user=$_SESSION['uname'];

echo "Hello $user";
#connect database
$con=mysqli_connect("localhost","root","happy123$","bankdb");
#select * from banktable where username='$user'

 $result=mysqli_query($con,"select * from banktable where username='$user'");
 
#extarct data from tablereceived


 $row = mysqli_fetch_row($result);
 $balance=$row[3];
 
 

#extract blance from data and display
echo "<br> <br> Your Savings accoutn balance: $balance";

#logout page

echo "<br><br> <a href='logout.php'>Signout</a>";
#page redirect transfers
echo "<br><br> <a href='transfer.php'>Transfer</a>";
#page redirect feedback
echo "<br><br> <a href='feedback.php'>Feedback</a>";
}

else
	
{
header('Location:login.html');
}

?>