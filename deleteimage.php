<?php

if ($_POST['type']=='s') $table = 'service_order_images'; else $table = 'repair_order_images';

include_once "includes/bootstrap.php";

$sql = "SELECT file FROM ".$table." WHERE image_id='{$_POST['image_id']}'";
$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
$image = mysql_fetch_assoc($result);
$filename = $image['file'];
if (file_exists($filename)) {
	unlink($filename);
} else echo $filename;

$sql = "DELETE FROM ".$table." WHERE image_id='{$_POST['image_id']}'";
$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

?>