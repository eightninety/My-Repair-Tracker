<?php

if ($id=="") $id = $_GET['id'];
if ($type=="") $type = $_GET['type'];
if ($user=="") $user = $_GET['user'];

include_once "includes/bootstrap.php";

if ($type=='s') {
	$sql = "SELECT image_id, file FROM service_order_images WHERE service_id='{$id}' ORDER BY create_ts DESC";
} else {
	$sql = "SELECT image_id, file FROM repair_order_images WHERE repair_id='{$id}' ORDER BY create_ts DESC";
}
$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
while( $images = mysql_fetch_assoc($result) ) {
	echo '<div class="cellImage">';
	if ($user=="a") echo '<div class="deleteImage" name="img'.$images['image_id'].'"></div>';
	echo '<img name="img'.$images['image_id'].'" src="'.$images['file'].'" /></div>';
}

?>