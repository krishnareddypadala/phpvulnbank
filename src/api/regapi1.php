<?php
class User {
    public $name;
    public $email;
    public $isAdmin = false;
    public $isActive = false;
    
    public function __construct($data) {
        $this->name = isset($data['name']) ? $data['name'] : null;
        $this->email = isset($data['email']) ? $data['email'] : null;
        
        // Only allow 'isAdmin' to be set by authorized users
        if (isset($data['isAdmin']) && $this->isAdminAllowed()) {
            $this->isAdmin = $data['isAdmin'];
        }
    }

$json = file_get_contents('php://input');
/*
$info = json_decode($json);
$name = $info->{'name'};
$tel = $info->{'tel'};
$email = $info->{'email'};
$password = $info->{'pwd'};
*/

//echo "$name - $tel - $email - $password";

if(isset($name))
{
$con=mysqli_connect("127.0.0.1","groot","bose123$","bankdb");
$result=mysqli_query($con,"SELECT * FROM banktable where username='$name'");

$num=mysqli_num_rows($result);

	if($num>0)
		{

			echo "Already registered with username <b> $name </b> or email id <b> $email </b> ..!!";

		}

		else

		{
			mysqli_query($con,"INSERT INTO banktable(username,password,balance,feedback,mobile,email,active,admin)VALUES('$name',md5('$password'),0,'nofeedback','$tel','$email','0','0');");
			echo "Registration completed , Activation is pending";

		}

}
else
{

echo "params missing in api request";

}

