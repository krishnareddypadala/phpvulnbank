<?php include "../ui/header2.php";?>
<nav>
<html>

<body>

<h2>Register New User(with JSON Object)</h2>


Name:<input type="text" id="name" name="name" class="register"><br><br>
Password:<input type="password" id="pwd" name="pwd" class="register"><br><br>
email:<input type="text" id="email" name="email" class="register"><br><br>
Phone:<input type="text" id="tel" name="tel" class="register"><br><br>
<button type="button" onclick="createJSON()">Register</button>
<br>
<p id="demo"></p><br>
<a href="../login.php">Click here to Login</a><br>

   
<script>
function createJSON() 
{

	var name=document.getElementById("name").value
	var pwd=document.getElementById("pwd").value
	var email=document.getElementById("email").value
	var tel=document.getElementById("tel").value

     	myObj = {"name":name,"pwd":pwd, "email":email,"tel":tel};
	
	jsonString = JSON.stringify(myObj);
 
	var xhttp = new XMLHttpRequest();
  
	xhttp.onreadystatechange = function() 
		{
    			if (this.readyState == 4 && this.status == 200) 
				{
      					document.getElementById("demo").innerHTML = this.responseText;
    				}
  		};
  xhttp.open("POST", "regapi.php", true);	
  xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  xhttp.send(jsonString);
    
}
</script>



</body>
</html>
</nav>
<?php include "../ui/footer.php";?>