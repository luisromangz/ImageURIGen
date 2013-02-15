<?php
// Image URI Generator

// Copyright 2012, Senthil Padmanabhan
// Released under the MIT License
// http://www.opensource.org/licenses/MIT

function curl_get_image( $url, $respObj) {
	// Defining the default CURL options
	$defaults = array( 
	        CURLOPT_URL => $url, 
	        CURLOPT_RETURNTRANSFER => TRUE	  
	 ); 

	// Open the Curl session
	$session = curl_init();		    

	// Setting the options
	curl_setopt_array($session, $defaults);		    

	// Make the call
	$imgResp = curl_exec($session);	
	

	// Handle response
	$srcImage = null;
	if($imgResp) {
	    $srcImage = imagecreatefromstring($imgResp);
	}  else {
		$respObj['error'] = getErrorResp(101, curl_error($session));	
	}


	// Close the connetion
	curl_close($session);

	return $srcImage;
}

function get_uploaded_image($formFieldName, $respObj) {
	if(!array_key_exists($formFieldName, $_FILES)) {
		$respObj['error'] = getErrorResp(101, "uploaded file not found");	
		return null;
	}

	// We try opening the uploaded file we don't trust mime type or extensions.
	$filePath = $_FILES[$formFieldName]["tmp_name"];
	$resultImg = @imagecreatefromjpeg($filePath);

	if($resultImg) {
		return $resultImg;
	}

	$resultImg = @imagecreatefrompng($filePath);
	if($resultImg) {
		return $resultImg;
	}

	$respObj["error"] = getErrorResp(101, "Uploaded file isn't png or jpeg");
	unlink($filePath);

	return null;

}

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
	$params = json_decode(stripslashes($params), true);
	$images = false;
	if(array_key_exists("images", $params)){
		$images = $params["images"];
	}
	$outputFormat = "png";
	if(array_key_exists("outputFormat", $params)){
		$outputFormat = $params["outputFormat"];
	}
	switch($outputFormat) {
		case "png":
		case "jpeg":
			break;
		default:
			$response["error"] = getErrorResp(100, "outputFormat must be png or jpeg");
			header("Content-Type: application/json");  
			echo stripslashes(json_encode($response));
			return;
	}

	$combine = false;
	if(array_key_exists("combine", $params)){
		$combine = $params["combine"];
	}

	$resizeOutput =false;
	if(array_key_exists("resize", $params)) {
		$resizeOutput = $params["resize"];
	}


	$outputWidth = 100;
	$outputHeight = 100;

	if($combine || $resizeOutput) {
		// We need the output width and height.
		if(array_key_exists("outputWidth", $params)) {
			$outputWidth = intval($params["outputWidth"]);
		} else {
			$response["error"] = getErrorResp(100,"outputWidth and outputHeight required when compine or resize are true");
			header("Content-Type: application/json");  
			echo stripslashes(json_encode($response));
			return;
		}

		
		if(array_key_exists("outputHeight", $params)) {
			$outputHeight=intval($params["outputHeight"]);
		} else {
			$response["error"] = getErrorResp(100,"outputWidth and outputHeight required when compine or resize are true");
			header("Content-Type: application/json");  
			echo stripslashes(json_encode($response));
			return;
		}
	}
	
	
}

// Validate input data
if($images && count($images)) {
	$data = array();
	$combinedImage = null;
	if($combine) {
		$combinedImage = imagecreatetruecolor($outputWidth, $outputHeight);
	}

	foreach ($images as $image) {
		$respObj = array();
		
		$url = null;
		if(is_string($image)) {
			$url = $image;
		} else if (array_key_exists("url", $image)) {
			$url = $image["url"];
		} 

		$srcImage = null;
		if($url) {
			// We retrieve the file using curl.
			$respObj["url"] = $url;
			$srcImage = curl_get_image($url, $respObj);
		} else if (array_key_exists("fileFormField", $image)) {
			$respObj["url"] = "uploaded file";
			$srcImage = get_uploaded_image($image["fileFormField"], $respObj);
		} else {
			$respObj["error"] = getErrorResp(100,"Neither url or fileFormField specified for image.");
		}

	    if(!$srcImage) {
	    	break;
	    }


		// In general, we output the same image.			
		$outImage = $srcImage;
		if($combine) {
			// We use the combined image.
			$outImage = $combinedImage;
		} else if($resizeOutput) {
			// We  create a new image with 
			$outImage = imagecreatetruecolor($outputWidth, $outputHeight);
		}		 

		if($combine || $resizeOutput) {
			// We copy the contents of the input image, reescaled.
			imagecopyresampled($outImage, $srcImage,
				0, 0, 0, 0, 
				$outputWidth, $outputHeight, 
				imagesx($srcImage), imagesy($srcImage));
		}
		
		$srcImage = null;
		if(!$combine) {
			$respObj['uri'] = createBase64Content($outImage, $outputFormat);
			$respObj["width"] = imagesx($outImage);
			$respObj["height"] = imagesy($outImage);
			
			$outImage = null;

			// If we are not combining the results in one image,
			// we append the result.
			$data[] = $respObj; 
		}	
	}

	if($combine) {
		$data[] = array(
			"url"=>"combined images",
			"uri" => createBase64Content($combinedImage, $outputFormat),
			"width" => imagesx($combinedImage),
			"height" => imagesy($combinedImage));
	}

	$response['data'] = $data;	   
		
} else {
	// No input - build error response and return
	$response['error'] = getErrorResp(100, 'No input images');
}

// Set HTTP header to JSON
// TODO also add cahce headers if necessary
header("Content-Type: application/json");  

// echo out the JSON
echo stripslashes(json_encode($response));

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

function createBase64Content($image, $format) {

	$tmpfname = tempnam(sys_get_temp_dir(), "image");
	if($format=="jpeg") {
		// We create a jpeg at maximum quality.
		imagejpeg($image, $tmpfname,100);				
	} else {
		imagepng($image,$tmpfname,5);
	}
		

	$base64Content =  base64_encode(file_get_contents($tmpfname));
	 // We delete the temporal file.
 	unlink($tmpfname);
 	return "data:image/$format;base64,".$base64Content;
}

?>

