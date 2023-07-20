<html>

List Users pending for activation:

<form action="activate.php" method="POST">
<label for="user">Choose a user:</label>
<select name="user" id="user">
<?php

$con=mysqli_connect("localhost","groot","bose123$","bankdb");
$result=mysqli_query($con,"select * from banktable where active='0'");
$num=mysqli_num_rows($result);

 for($i=0;$i<$num;$i++)
 {
 $row = mysqli_fetch_row($result);
 echo "<option value='$row[1]''>$row[1]</option> ";
 }
?>
 </select>
  <br><br>
  <input type="submit" name="submit" value="activate">


</form>

<html>