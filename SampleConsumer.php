<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include('includes/autoloader.inc.php');

use com\kurl\curl\Kurl;
//use the static method for a single call and response. 
//This is a GET with no header parameters
$result = Kurl::doCall(array() //no header parameters
						,array("test"=>"yes") //simple GET parameters, could also use string "test=yes&blah=2", GET params can also be included in the URL.
						,(isset($_SERVER["HTTPS"])?'https://':'http://').$_SERVER["SERVER_NAME"].dirname($_SERVER['REQUEST_URI'])."/SampleWebservice.php" //use the full URL, here we shortcut with the current URL
						,"POST" //default method is POST.
						//array(  //We can send optional parameters here,
						//		"username"=>"john",
				 		//		"password"=>"go",
				 		//		"dataType"=>"json", "text", //optional, assumes JSON and falls back to text/html 
				 		//		"cache"=>86400, //integer, number of seconds
				 		//		"jsonPOST"=>true, //informs the system that the input $requestParameters should be treated as a JSON blob.
				 		//		"cookies"=>array("name=value"),
				 		//		"authAny"=>true - for username and password logins, basic auth is default, this adds authany to the login request. 
				 		//)
				 	);
echo "<pre>".print_r($result,true)."</pre><br /><br />";
//you can also json-encode the result
echo "<strong>JSON-encoded:</strong><br />";
echo json_encode($result)."<br /><br />";
							
							 
	
?>