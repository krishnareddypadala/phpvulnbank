<?php include "ui/header2.php";?>
<html>

<?php
require __DIR__ . '/admincheck.php';
session_start();
$user=$_SESSION['uname'];
echo "Hello $user";

if(admincheck() == TRUE)
    {
        $con=mysqli_connect("127.0.0.1","groot","bose123$","bankdb");
        $result=mysqli_query($con,"select * from banktable where active='0'");
        $num=mysqli_num_rows($result);
        
        echo "<br><br>Number of activations pending: $num <br><br>";
        if($num >0)
        {
        echo"<form action='activate.php' method='POST'> <label for='user'>Choose a user:</label> <select name='user' id='user'>";

        for($i=0;$i<$num;$i++)
        {
        $row = mysqli_fetch_row($result);
        echo "<option value='$row[1]''>$row[1]</option> ";
        }

        echo " </select> <br><br> <input type='submit' name='submit' value='activate'> </form>";
        }

    }
    else
    {
        echo "<br><br>You are not admin..!! <br><br>";
    }

?>

<a href="profile.php">profile</a>
<br><br>
<a href="logout.php">SignOut</a>


<html>

<?php include "ui/footer.php"; ?>