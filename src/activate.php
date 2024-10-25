<?php include "ui/header2.php";?>
<?php
if(isset($_POST["user"]))
{
$usertoactivate=$_POST["user"];
$con=mysqli_connect("127.0.0.1","groot","bose123$","bankdb");
$query="UPDATE banktable SET active='1' WHERE username='$usertoactivate'";
$result=mysqli_query($con,$query);
echo "<br><br><p>User $usertoactivate is Activated .. </p><br><br>";

}
else

{

    echo "User not selected <br><br>";
}
?>
<br><br>
<a href="profile.php">Click here to goto Profile</a>  
<br><br>
<a href="activateform.php">Click here to activate another user</a><br>

<?php include "ui/footer.php"; ?>