<?php

// Image URI Generator

// Copyright 2012, Senthil Padmanabhan
// Released under the MIT License
// http://www.opensource.org/licenses/MIT

$query = array(); // query list
$response = array(); // response object

// Get query params

$params = null;
if(array_key_exists('params',$_GET)) {
	$params = $_GET['params'];
} else if(array_key_exists('params',$_POST)) {	
	$params	= $_POST['params'];
}

// Validate input
if($params) {
	$query = json_decode(stripslashes($params), true);
	$query = $query? $query['images']: 0;
}

// Validate input data
if($query && count($query)) {
	$data = array();
	foreach ($query as $url) {
		$url = "http://".preg_replace('/http:\/\//i', '',$url);
		$respObj = array('url' => $url);
		
		// Defining the default CURL options
		$defaults = array( 
		        CURLOPT_URL => $url, 
		        CURLOPT_RETURNTRANSFER => TRUE	  
		    ); 
		
		$image = imagecreatefromjpeg($url);
		if($image){
			$tmpfname = tempnam(sys_get_temp_dir(), "map");
			
			imagejpeg($image, $tmpfname,100);
			
		
			$respObj['uri'] = base64_encode(file_get_contents($tmpfname));

			// We delete the temporal file.
			unlink($tmpfname);
		} else {
			$respObj['error'] = "Image couldn't be opened";
		}
		$data[] = $respObj; 
	}
	$response['data'] = $data;	
} else {
	// No input - build error response and return
	$response['error'] = getErrorResp(100, 'No input URLs');
}

// Set HTTP header to JSON
// TODO also add cahce headers if necessary
header("Content-Type: application/json");  

// echo out the JSON
echo json_encode($response);

/**
 * Utility Functions 
 */
/**
 * 
 * Function to build the error response  
 * 
 * @function getErrorResp
 * @param $id {String} Error ID
 * @param $msg {String} Error Message
 * 
 */			
function getErrorResp($id, $msg) {
	return array('id' => $id, 'msg' => $msg);
}

?>
