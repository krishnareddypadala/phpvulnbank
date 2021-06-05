<html>
<h1>Enter username and password</h1>

<form name="loginform" method="POST" action="login.php">

username:<input type="text" name='uname' value=<?php if(isset($_POST["uname"])) echo $_POST["uname"] ; ?>><br><br> 
<!--		username:<input type="text" name='uname'><br><br>-->
	password:<input type=password name=pwd>
<br><br>
	<input type="submit" value="login">

</form>
<br>
<a href="register.html">Register(xml)</a> | 
<a href="regjson.html">Register(json)</a><br>


<?php if(isset($_POST["uname"])) echo ("Wrong username or Passwords"); ?>

</html>


<?php

if(isset($_POST["uname"]))
{
$username=$_POST["uname"];
$password=$_POST["pwd"];
session_start();
//$_SESSION["uname"]=$username;

$con=mysqli_connect("localhost","groot","bose123$","bankdb");

//$username=mysqli_real_escape_string ($con,$username);
//$password=mysqli_real_escape_string ($con,$password);

//connect to db bankdb



$query="select * from banktable where username='$username' and password='$password'";

//select * from banktable where username='krishna' and password='bose123$'

$result=mysqli_query($con,$query);
$num=mysqli_num_rows($result);


if($num>0)
{

//session_start();
session_regenerate_id();
$_SESSION["uname"]=$username;

header("location:profile.php");

//change is the law of life
}
else
{

//$enusername=htmlspecialchars($username);
//$output=`$username`;

echo "<br><br>you are not $username";

//header("location:login.php");

}
}



?>
