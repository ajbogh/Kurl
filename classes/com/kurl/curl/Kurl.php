<?php
namespace com\kurl\curl;

/**
 * Kurl handles all cURL calls for REST-based systems.
 * 
 * Caching - 
 * It includes basic caching using a cache directory within the same 
 * directory as this file. The 'cache' directory must have write permissions for PHP.
 * The cache directory is cleaned every 25 executions, removing the oldest files based on their cache time.
 * 
 * Username/Password logins - 
 * an $optParams variable is included for things like "username", "password", "datatype", and "cache"
 * The system will know how to deal with the different types.
 * 
 * Supported methods:
 *     GET
 *     POST
 *     DELETE (using custom request)
 *     PUT (using custom request)
 * 
 * $requestParameters can use an array of parameters or a query-string based parameter list.
 */

	 
class Kurl{

	
	/**
	 * doCall
	 * @param array $header - An array of header values to use.
	 * @param array $requestParameters - An array, string, or object (for JSON) of post parameters.
	 * @param string $url
	 * @param string $method - "GET", "POST", "DELETE"
	 * @param associative array $optParams - An array of optional parameters like 
	 * 			["username"=>"john",
	 * 				"password"=>"go",
	 * 				"dataType"=>"json", "text", //optional, assumes JSON and falls back to text/html 
	 * 				"cache"=>86400, //integer, number of seconds
	 * 				"jsonPOST"=>true, //informs the system that the input $requestParameters should be treated as a JSON blob.
	 * 				"cookies"=>array("name=value"),
	 * 				"authAny"=>true - for username and password logins, basic auth is default, this adds authany to the login request. 
	 * 				"secureSSL"=>true - set to false to ignore peer and host verification.
	 * 				"returnRequestParams"=>true - set to false to return an empty array instead of echoing the params
	 * 											This is useful for security purposes if you don't want params to be sent back to the user.
	 * 			]
	 * @return Object
	 */
	public static function doCall($header=array(),$requestParameters=array(),$url,$method="POST",$optParams=null){
		$curl = new Kurl($url, $requestParameters);
		
		//check file cache
		if(isset($optParams["cache"])){
			$curl->setupCache($optParams["cache"]);
		}
		
		$curl->setHeader($header);
		
		//check if it's a JSON POST
		if(isset($optParams["jsonPOST"]) && $optParams["jsonPOST"] == true){
			$curl->setJSONBody($requestParameters);
		}
		
		//special method for dealing with username:password logins
		if(isset($optParams["username"])){
			$curl->setLoginCredentials($optParams["username"],$optParams["password"]);
		}
		
		//special method for dealing with cookies
		if(isset($optParams["cookies"])){
			$curl->setCookies($optParams["cookies"]);
		}
		
		//special method for dealing with SSL
		if(isset($optParams["secureSSL"])){
			$curl->useSecureSSL($optParams["secureSSL"]);
		}
		
		//special method for dealing with SSL
		if(isset($optParams["returnRequestParams"])){
			$curl->returnRequestParams = ($optParams["returnRequestParams"]);
		}
		
		//check method
		$result;
		switch($method){
			case "GET":
				$result = $curl->GET();
				break;
			case "POST":
				$result = $curl->POST();
				break;
			case "DELETE":
				$result = $curl->DELETE();
				break;
			case "PUT":
				$result = $curl->PUT();
				break;
			case "HEAD":
				$result = $curl->HEAD();
				break;
			default:
				$result = $curl->GET();
		}
		
		return $result;
	}


	/* Instance variables */
	private $rep = array(":", "/", ".", "?", "&", "+", "=");
	private $fn = ""; //cache filename
	private $cacheDir; //the cache directory (defaults to "the_directory_of_this_script/cache")
	private $cacheTime = 0; //number of seconds to store cache
	private $cacheResult = false; //whether or not we should use the cache
	private $header = array(); 
	private $requestParameters;
	private $url, $ch, $username, $password;
	private $authenticate = false; //whether or not we should authenticate with the server
	private $authtype; //default auth type of any
	private $secureSSL = true; //If false, do not verify SSL. VERY DANGEROUS!
	private $method = "GET";
	public $returnRequestParams = true; //returns the request parameter in the response.
	
	/**
	 * Instance methods below
	 */
	 
	/**
	 * Constructor for cURL class. 
	 * 
	 * @param $url - The URL to connect to.
	 * @param $requestParameters (optional) - a query string, array, or JSON blob of parameters
	 */  
	public function __construct($url, $requestParameters = array()){
		$this->cacheDir = dirname(__FILE__) . "/cache/";
		$this->requestParameters = $requestParameters;
		$this->url = $url;
		$this->ch = curl_init();
		$this->authtype = CURLAUTH_ANY; //do this after init.
	}
	
	public function setLoginCredentials($username, $password){
		$this->authenticate = true;
		$this->username = $username;
		$this->password = $password;
	}
	
	public function setHeader($header=array()){
		$this->header = array_merge($this->header,$header);
	}
	
	public function useSecureSSL($val){
		$this->secureSSL = $val;
	}
	
	/**
	 * Implodes an array of cookie values into the header of the cURL call.
	 * 
	 * @param $cookiesArr - An array of cookies ["token=12345","userid=bob123"]
	 */
	public function setCookies($cookiesArr){
		//special method for dealing with cookies
		$cookies = $this->implode_with_key($cookiesArr, '=','; ');
		curl_setopt ($this->ch, CURLOPT_COOKIE, $cookies);
	}
	/**
	 * Special implode function used with cookies to preserve keys and values.
	 */
	private function implode_with_key($assoc, $inglue = '>', $outglue = ',') {
	    $return = '';
	    foreach ($assoc as $tk => $tv) {
	        $return .= $outglue . $tk . $inglue . $tv;
	    }
	    return substr($return, strlen($outglue));
	}
	
	public function GET(){
		$this->method = "GET";
		if(is_array($this->requestParameters) && count($this->requestParameters)>0){
			$this->url = $this->url.'?'.http_build_query($this->requestParameters);
		}else if(!is_array($this->requestParameters) && !is_null($this->requestParameters)){
			$this->url = $this->url.'?'.$this->requestParameters;
		}
		return $this->execute();
	}
	public function POST(){
		$this->clearCache();
		$this->method = "POST";
		$this->setupPOSTFields("POST");
		return $this->execute();
	}
	public function PUT(){
		$this->clearCache();
		$this->method = "PUT";
		//handles a true PUT
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "PUT");
		//curl_setopt($this->ch, CURLOPT_PUT, true);
		//$fp = tmpfile(); //fopen('php://temp/maxmemory:256000', 'w');
		
		$body = "";
		if(is_array($this->requestParameters)){
			$body = json_encode($this->requestParameters);
		}else{
			$body = $this->requestParameters;
		}
		curl_setopt($this->ch, CURLOPT_POSTFIELDS,$body);
		//fwrite($fp, $body);
		//rewind($fp);
		//fseek($fp, 0); 
		//curl_setopt($this->ch, CURLOPT_INFILE, $fp);
		//curl_setopt($this->ch, CURLOPT_INFILESIZE, strlen($body));
		// Let curl know that we are sending an entity body
		//curl_setopt($this->ch, CURLOPT_UPLOAD, true);
		// Let curl know that we are using a chunked transfer encoding
		//$this->header[] = 'Transfer-Encoding: chunked';
		//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Transfer-Encoding: chunked'));
		// Use a callback to provide curl with data to transmit from the stream
		//curl_setopt($this->ch, CURLOPT_READFUNCTION, function($ch, $fd, $length) use ($fp) {
		//    return fread($fp, $length);
		//});

		return $this->execute();
	}
	public function DELETE(){
		$this->clearCache();
		$this->method = "DELETE";
		$this->setupPOSTFields("DELETE");
		return $this->execute();
	}
	public function HEAD(){
		$this->method = "HEAD";
		curl_setopt($this->ch, CURLOPT_NOBODY, true);
		return $this->execute();
	}
	
	/**
	 * Sets up the fields to be posted to the server.
	 * Only used for POST and DELETE calls.
	 */
	private function setupPOSTFields($method){
		if(is_array($this->requestParameters)){ 
			curl_setopt($this->ch,CURLOPT_POST,count($this->requestParameters));
		}
		curl_setopt($this->ch,CURLOPT_POSTFIELDS,$this->requestParameters);

		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
	}
	
	
	/**
	 * setJSONBody sets up a JSON post or put to the server
	 */
	public function setJSONBody($requestParameters){
		$this->header[] = 'Content-Type: application/json';

		if(!is_string($requestParameters)){ //convert array to JSON
			$this->requestParameters = json_encode($requestParameters);
		}
		$this->header[] = 'Content-Length: '.strlen($this->requestParameters);
	}
	
	/**
	 * private caching functions.
	 * @param $cacheSeconds - Number of seconds to cache.
	 */
	private function setupCache($cacheSeconds){
		$this->cacheResult = true;
		$this->cacheTime = $cacheSeconds;
	} //end setupCache function
	
	public function setCustomCacheDir($dir){
		$this->cacheDir = $dir;
	}
	/**
	 * getCache either returns the cache result or false if there is no cache.
	 * @return Cache result or false
	 */
	public function getCache(){
		$exists = file_exists($this->fn);
	    if($exists && (time()-@filemtime($this->fn)) < $this->cacheTime){
	    	$string = file_get_contents($this->fn);
			$result = json_decode($string);
			if(is_null($result)){
				$result = $string;
			}
			$info = array(
	            "url"=>$this->url,
	            "http_code"=>200
			);
	        return array('result'=>$result,
	        			'requestParameters'=>($this->returnRequestParams?$this->requestParameters:array()),
	        			'info'=>$info);
	    }else{
	    	return false;
	    }
	}
	
	private function writeCache($result){
		//write the data to the cache
		@unlink($this->fn);
		if($result){
	    	$fh = fopen($this->fn, 'w');
			$string = json_encode($result);
			if(is_null($string)){ //result isn't JSON
				$string = $result;
			}
			fwrite($fh, $string);
			fclose($fh);
		}
	} //end writeCache function
	
	public function clearCacheByTime(){
		if ($handle = opendir($this->cacheDir)) {
			$dir = $this->cacheDir;
		    //Loop over the directory.
		    while (false !== ($file = readdir($handle))) {
		        $filenameArr = explode("-",$file,2); //$filenameArr should be like 604800.txt
		        if(count($filenameArr) == 2){ //make sure it's only 2 array positions
		        	//get seconds
		        	$seconds = substr($filenameArr[1],0,strrpos($filenameArr[1],'.'));

					//delete the file if it's old
					if(filemtime($dir.$file) <= time()-intval($seconds)){
			           unlink($dir.$file);
			        }
		        }
		    }
		
		    closedir($handle);
		}
	} //end clearCacheByTime instance function
	
	/**
	 * clearCache forces the cache to be cleared for the filename.
	 */
	public function clearCache(){
		if($this->fn != ""){
			unlink($this->fn);
		}
	}
	
	/**
	 * Executes the configured cURL call.
	 * 
	 * @return an array of the result, original request parameters, and the header info.
	 */
	public function execute(){
		//cache filename
		$this->fn = $this->cacheDir . sha1(str_replace($this->rep, "", $this->url.json_encode($this->header).json_encode($this->requestParameters))) . "-".$this->cacheTime.".txt";
		if($this->cacheResult){
			$this->clearCacheByTime();
			$cache = $this->getCache();
			if($cache !== false){
				return $cache;
			}
		}
		
		curl_setopt($this->ch, CURLOPT_URL, $this->url);
		
		//special method for dealing with username:password logins
		if($this->authenticate){
			curl_setopt($this->ch, CURLOPT_HTTPAUTH, $this->authtype);				
			curl_setopt($this->ch, CURLOPT_USERPWD, $this->username.":".$this->password); 
		}
		
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->header);
		
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_VERBOSE, true); //include the full header in the response
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->ch, CURLINFO_HEADER_OUT, true); //get full info parameters
		curl_setopt($this->ch, CURLOPT_HEADER, true); //used to get the header information
		
		//check for SSL use
		if(substr($this->url,0,5) == "https" || (substr($this->url,0,2) == "//" && isset($_SERVER['HTTPS']))){
			if($this->secureSSL === false){
				curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 0); //ignore all certificate problems
				curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
			}
			curl_setopt($this->ch, CURLOPT_SSLVERSION, 3);
		}

		$response = curl_exec($this->ch);
		
		//get the header size
		$header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
		//get the header
		$headerOut = substr($response, 0, $header_size);
		$headerOut = array_filter(explode("\n",str_replace("\r\n","\n",$headerOut)));
		$headerOut = array_splice($headerOut, 1);
		$header = array();
		foreach($headerOut as $headerItem){
			$headerItem = explode(":",$headerItem,2);
			if(isset($headerItem[1])){
				$header[$headerItem[0]] = ltrim($headerItem[1]);
			}
		}
		//get the body
		$result = substr($response, $header_size);
		
		$info = curl_getinfo($this->ch);
		$info = array_merge($info,array("error"=>curl_error($this->ch)));

		if($info['http_code']==401){ // Attempt NTLM Auth only, CURLAUTH_ANY does not work with NTML
			if($this->authtype!=CURLAUTH_BASIC && $this->authtype!=CURLAUTH_NTLM){
				$origAuthType = $this->authtype;
				$this->authtype = CURLAUTH_BASIC;
				$res = $this->execute();
				$info = $res["info"];
				$result = $res["result"];
				$this->authtype = $origAuthType;
			}else if($this->authtype!=CURLAUTH_NTLM){
				$origAuthType = $this->authtype;
				$this->authtype = CURLAUTH_NTLM;
				$res = $this->execute();
				$info = $res["info"];
				$result = $res["result"];
				$this->authtype = $origAuthType;
			}
		}
		//try to decode json, fall back to text if it fails
		$origResult = $result;
		$result = @json_decode($result);
		if(is_null($result)){
			$result = $origResult;
		}
		
		//write the data to the cache
		if($this->cacheResult){
			$this->writeCache($result);
		}
		
		$returnArray = array('result'=>$result,
			'requestParameters'=>($this->returnRequestParams?$this->requestParameters:array()),
			'info'=>$info,
			'header'=>$header
		);
		return $returnArray;
	}
}

?>