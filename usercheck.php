<html>
<?php
$user=$_REQUEST['uname'];
$pass=$_REQUEST['pwd'];
session_start();
//session_regenerate_id();

$con=mysqli_connect("localhost","root","happy123$","bankdb");



$user=mysqli_real_escape_string($con,$user);
$pass=mysqli_real_escape_string($con,$pass);

$result=mysqli_query($con,"SELECT * FROM banktable where username='$user' and password='$pass'");




$num_rows=mysqli_num_rows($result);




if($num_rows!=0)

{
echo "you are logged in as $user";

$_SESSION['uname']=$user;

header('Location:profile.php');

}

else
{

echo "you are not loggedin $user";
//header('Location:Login.html');

}
?>

</html>