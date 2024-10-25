<?php

libxml_disable_entity_loader (true);
$xmlfile = file_get_contents('php://input');
$dom = new DOMDocument();
$dom->loadXML($xmlfile, LIBXML_NOENT | LIBXML_DTDLOAD);
$info = simplexml_import_dom($dom);
$name = $info->name;
$tel = $info->tel;
$email = $info->email;
$password = $info->password;

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

