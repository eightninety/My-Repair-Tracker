<?php
/*
UploadiFive
Copyright (c) 2012 Reactive Apps, Ronnie Garcia
*/

include_once "../includes/bootstrap.php";

// Set the uplaod directory
$uploadDir = 'vehiclephotos/';

// Set the allowed file extensions
$fileTypes = array('jpg', 'jpeg', 'gif', 'png'); // Allowed file extensions

$verifyToken = md5('unique_salt' . $_POST['timestamp']);

if (!empty($_FILES) && $_POST['token'] == $verifyToken) {

	// Validate the filetype
	$fileParts = pathinfo($_FILES['Filedata']['name']);
	if (in_array(strtolower($fileParts['extension']), $fileTypes)) {
	
		if ($_POST['type']=='s') {
			$sql = "INSERT INTO service_order_images (service_id) VALUES (".$_POST['id'].")";
		} else {
			$sql = "INSERT INTO repair_order_images (repair_id) VALUES (".$_POST['id'].")";
		}
		$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
		$image_id = mysql_insert_id();
	
		$tempFile   = $_FILES['Filedata']['tmp_name'];
		$saveFile   = $uploadDir . $_POST['prefix'] . $image_id . '.' . $fileParts['extension'];
		$targetFile = $_SERVER['DOCUMENT_ROOT'] . '/' . $saveFile;
		
		if ($_POST['type']=='s') {
			$sql = "UPDATE service_order_images SET file = '".$saveFile."' WHERE image_id = ".$image_id;
		} else {
			$sql = "UPDATE repair_order_images SET file = '".$saveFile."' WHERE image_id = ".$image_id;
		}
		$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

		// Save the file
		move_uploaded_file($tempFile, $targetFile);
		//include('SimpleImage.php');
		//$image = new SimpleImage();
		//$image->load($targetFile);
		//$image->resizeToHeight(75);
		//$image->save('uploads/picture2.jpg');
		//echo $image;
		echo 1;

	} else {

		// The file type wasn't allowed
		echo 'Invalid file type.';

	}
}
?>