<?php
if(isset($_POST["user"]))
{
$usertoactivate=$_POST["user"];
$con=mysqli_connect("localhost","groot","bose123$","bankdb");
$query="UPDATE banktable SET active='1' WHERE username='$usertoactivate'";
$result=mysqli_query($con,$query);
echo "User $usertoactivate is Activated ..";

}
else

{

    echo "User not selected";
}
?>

<a href="profile.php">Click here to goto Profile</a> | 
<a href="activateform.php">Click here to activate another user</a><br>