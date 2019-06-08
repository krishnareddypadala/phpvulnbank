<html>
<h1>Enter username and password</h1>

<form name="loginform" method="POST" action="login.php">

	username:<input type="text" name='uname' value='<?php if(isset($_POST["uname"])) echo $_POST["uname"] ; ?>'><br><br>
	password:<input type=password name=pwd>
<br><br>
	<input type="submit" value="login">

</form>
<a href="register.html">Register</a><br>
<?php if(isset($_POST["uname"])) echo ("Wrong username or Passwords"); ?>

</html>


<?php

if(isset($_POST["uname"]))
{
$username=$_POST["uname"];
$password=$_POST["pwd"];
session_start();
//$_SESSION["uname"]=$username;
//session_regenerate_id();

$con=mysqli_connect("localhost","root","happy123$","marwebdb");



//connect to db marwebdb



$query="select * from marwebtb where username='$username' and password='$password'";

//select * from marwebtb where username='krishna' and password='happy123$'

$result=@mysqli_query($con,$query);

$num=@mysqli_num_rows($result);


if($num>0)
{

//session_start();

$_SESSION["uname"]=$username;

header("location:profile.php");



}
else
{

//$username=htmlspecialchars($username);
//echo "you are not $username";
//header("location:login.html");

}
}



?>
