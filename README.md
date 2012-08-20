## Requirements

1. PHP 5.3
2. PHP cURL extension installed.
3. Apache or other webserver (nginx, IIS)

## Instructions

* Add proper permission for the Apache user to write to classes/com/ics/disney/id/curl/cache directory
* Include the autoloader.inc.php file in your main script.

    require_once('includes/autoloader.inc.php');

* Include the Kurl.php class by using the PHP 5.3 "use" keyword.

    use com\ics\disney\id\curl\Kurl;
	
Additional files are included for Oauth 1.0 signing (com\kurl\oauth\OauthSigner.php)
and SOAP communication (com\kurl\soap\SoapClientAuth.php and com\kurl\soap\streamWrapperHttpAuth.php)

## Parameters:

    /**
     * doCall
     * @param array $header - An array of header values to use.
     * @param array $requestParameters - An array, string, or object (for JSON) of post parameters.
     * @param string $url
     * @param string $method - "GET", "POST", "PUT", "DELETE"
     * @param associative array $optParams - An array of optional parameters like 
     * 			["username"=>"john",
     * 				"password"=>"go",
     * 				"dataType"=>"json", //optional, assumes JSON and falls back to text/html 
     * 				"cache"=>86400, //integer, number of seconds
     * 				"jsonPOST"=>true, //informs the system that the input $requestParameters should be treated as a JSON blob.
     * 				"cookies"=>array("name=value"),
     * 				"authAny"=>true - for username and password logins, basic auth is default, this adds authany to the login request. 
     * 			]
     * @return Object
     */

## Examples:

    //This example performs a POST with a username and password in the request parameters
	//A special parameter called "jsonPOST" tells the system to send the data in an application/json stream
	//    {
	//    	"credentials":{
	//    	    "username":"blah",
	//    	    "password":"whatsit"
	//		}
	//    }
    $requestParameters = array("credentials"=>array(
    			"username"=>$credentials['username'], 
    			"password"=>$credentials['password']));
		
    		$method = 'POST';
    		$url = self::$AUTH_URL.'/authenticate';
    		$header = array();
    		$result = Kurl::doCall($header, 
    				$requestParameters,
    				$url, 
    				$method,
    				array("jsonPOST"=>true)
    			);
			//$result['result'] is an object in this case
    		$vc = $result['result']->verificationCode->verificationCode;
    
    //----------------------------------------------------------------------------------------
	
    //This example performs a SOAP POST request with a username, password, caching, and authAny enabled
	//If authAny were not set then basic authentication would be used.
    $headers = array(
    			'User-Agent: PHP-SOAP',
    			'Content-Type: text/xml; charset=utf-8',
    			'SOAPAction: "' . $action . '"',
    			'Content-Length: ' . strlen($request),
    			'Expect: 100-continue',
    			'Connection: Keep-Alive'
    		);
			
    		$this->__last_request_headers = $headers;
			
    		$response = Kurl::doCall($headers,$request,$location,"POST",array(
    				"username"=>$this->Username,
    				"password"=>$this->Password,
    				"cache"=>86400, //1 day cache
    				"authAny"=>true
    			)
    		);
			print_r($response["result"]);
	
    //----------------------------------------------------------------------------------------
	
    //This example performs a GET request with a string-based parameter list (arrays are also supported and automatically converted).
    //Some additional username and password parameters are included for basic authentication.
	//A datatype of "text" is used to prevent the system from performing a JSON-decode. Anything other than "json" or null 
	//  will be considered text/html.
    $method = "GET";
	$requestParameters = "group=TEST".
		"&summary=Client Request".
		"&status!=completed".
		"&status!=customer informed".
		"&_show=status".
		"&_show=summary".
		"&_show=description";
	$header=array(); 
	$url = ServiceTicket::$serviceURL.'/request.json';

	$result = Kurl::doCall($header, 
			$requestParameters,
			$url, 
			$method, 
			array("username"=>ServiceTicket::$serviceUser,
				"password"=>ServiceTicket::$servicePassword,
				"dataType"=>"text")
		);
	print_r($result);
	
    //----------------------------------------------------------------------------------------
	
    //This example uses the OAuth 1.0 signing mechanism
	//note, you must "use com\kurl\oauth\OauthSigner;"
    $method = "GET";
    $debug = FALSE; //setting to true produces a very verbose debug message
    $requestParameters = array(); //put your parameters in here.
			
    $url = 'https://somedomain.tld/somesystem/v1/listAllActive';
	
	//Some OAuth systems require an "auth_token" parameter but want its value to be blank
	//others don't want the parameter at all. Set this to TRUE or FALSE if yours is complaining.
    $disableSignatureToken = TRUE;
    $signer = new OauthSigner($disableSignatureToken);
    $headers = array(); //a hash map
    $signer->signWithOAuthHeader($headers, $method,
    		$url,
    		RegistrationResource::$bu_id, RegistrationResource::$bu_secret,
    		$requestParameters, $debug);

    $header[] = 'Authorization: '.$headers['Authorization'];
    $result = Kurl::doCall($header, $requestParameters,$url, $method);

    print_r($result);