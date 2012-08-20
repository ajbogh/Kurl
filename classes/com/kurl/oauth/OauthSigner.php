<?php
namespace com\kurl\oauth;

class OauthSigner {
    //private static final Logger LOG = Logger.getLogger(OauthSigner.class);
    
	//Safe characters? Why not just a regex?
    //public static final BitSet SAFE_CHARACTERS = new BitSet();

    //The MAC name (for interfacing with javax.crypto.)
    public $MAC_NAME = "HmacSHA1";

    //the OAuth signature method name 
    private $SIGNATURE_METHOD_NAME = "HMAC-SHA1";

    public $ALPHABET="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
	
	public $disableToken = false;

	//SecureRandom
    private $random = null; //new SecureRandom();
    
    //the length of our nonces
    private $NONCE_LENGTH = 14;
	
	private $debug;
	
	public function __construct($disableSignatureToken=false){
		$this->disableToken = $disableSignatureToken;
	}

    //static
    //{
    //    //The OAuth codec defines different safe characters than the standard URL codec.
    //    SAFE_CHARACTERS.set('a','z'+1);
    //    SAFE_CHARACTERS.set('A','Z'+1);
    //    SAFE_CHARACTERS.set('0','9'+1);
    //    SAFE_CHARACTERS.set('-');
    //    SAFE_CHARACTERS.set('.');
    //    SAFE_CHARACTERS.set('_');
    //    SAFE_CHARACTERS.set('~');
    //}
    
    /**
     * @return the base string to use for signing
	 * @params $httpMethod
	 * @params $providerUrl
	 * @params $oauthParameters - Map<String, String>
	 * @params $rawRequestParameters Map<String, List<String>>
     */
    private function buildSignatureBaseString($httpMethod, $providerUrl, &$oauthParameters, &$rawRequestParameters){
		if($this->debug) echo "<div style=\"border:1px solid #ccc\"><b>".__FUNCTION__." parameters (httpMethod, providerUrl, &oauthParameters, &rawRequestParameters):</b><pre>".print_r(func_get_args(),true)."</pre></div>";
        $base = ""; //StringBuilder

        // example:
        // POST&http%3A%2F%2F127.0.0.1%3A7780%2Fireg%2Fexec&oauth_consumer_key%3Dreference-consumer-key%26oauth_nonce%3D%26oauth_signature_method%3DHMAC-SHA1%26oauth_timestamp%3D1260853952%26oauth_token%3D%257Bb77c359b-1253-4f4b-b780-312714c3791b%257D%26oauth_version%3D1.0

        // start with some basic stuff
		//echo "---".$httpMethod."---";
        $base .= $httpMethod;
        $base .= "&";
        $base .= $this->encode($providerUrl);
        $base .= "&";

        // Perform parameter normalization as described in section 3.4.1.3.2 of
        // the OAuth spec. Combine OAuth parameters and additional request
        // parameters into a single list. Encode the name and value of each
        // pair. Sort by names and then values.

        $allParameters = array();

        // Handle the OAuth parameters.

        foreach($oauthParameters as $name=>$value) {
            $encodedName = $this->encode($name);
            $encodedValue = $this->encode($value);

            // We know the OAuth parameters have only one value at this point.

            $allParameters[$encodedName] = $encodedValue;
        }

		//sort $allParameters by key
		ksort($allParameters);
		
        // the oauth parameters go in as one big string, encoded, so even
        // the separators and equals are encode. I'm just saving a step
        // and doing it all at once.
        $count = 0;
        foreach($allParameters as $key=>$value )
        {
            //for ($e as $value) { //for each e as value?
                if ( $count++ > 0 ) $base .= "%26";
                $base .= $this->encode($key);
                $base .= "%3D";
                $base .= $this->encode($value);
            //}
        }

		if($this->debug) echo "<div><b>Returning signature base string</b> {$base}</div>";
        return $base;
    }

	private function rfc3986_encode($str) 
	{ 
	  $str = rawurlencode($str); 
	  $str = str_replace('%E7', '~', $str); 
	  return $str; 
	} 
	
    /**
     * Encode the specified value.
     *
     * @param value The value to encode.
     * @return The encoded value.
     */
    private function encode($value)
    {
		//return urlencode($value);
		if($this->debug) echo "<div style=\"border:1px solid #ccc\"><b>".__FUNCTION__." parameters (value):</b><pre>".print_r(func_get_args(),true)."</pre></div>";
        if (is_null($value) || empty($value)) return "";
		
		//echo "<div>5.3 rawurlencode ".rawurlencode($value)."</div>";
		$val = $this->rfc3986_encode($value); //5.2
		//$val = urlencode(preg_replace('/[^a-zA-Z0-9-\._\~]/', '', utf8_encode($value)));
		if($this->debug) echo "<div><b>Returning encoded value</b> {$val}</div>";
		return $val;
        //return new String(URLCodec.encodeUrl(SAFE_CHARACTERS, value.getBytes("UTF-8")), "US-ASCII");
    }

    /**
     * build a random string of the given length from the base-64 character set
     * 
     * <p>
     * Note that each character represents 6 bits, not 8, so if you are
     * fulfilling a byte-length requirement, remember to stretch it.
     * 
     * @param length
     * @return a random string of the given length
     */
    private function buildRandomString($length){
		if($this->debug) echo "<div style=\"border:1px solid #ccc\"><b>".__FUNCTION__." parameters (length):</b><pre>".print_r(func_get_args(),true)."</pre></div>";
        $chars = "";
        
        for ( $i = 0; $i < $length ; $i++ )
        {
            $chars .= $this->ALPHABET{mt_rand(0,strlen($this->ALPHABET)-1)};
        }
        
		if($this->debug) echo "<div><b>Returning random chars</b> {$chars}</div>";
        return $chars;
    }
    
    /**
     * @param baseString
     * @return the actual signature
     */
    private function sign($consumerSecret, $baseString)
    {
		if($this->debug) echo "<div style=\"border:1px solid #ccc\"><b>".__FUNCTION__." parameters (consumerSecret, baseString):</b><pre>".print_r(func_get_args(),true)."</pre></div>";
		$keyString = ""; //new StringBuilder();
		$keyString .= $this->encode( $consumerSecret );
		$keyString .= "&";
		$keyString .= $this->encode( "" ); //why encode a blank string?

		$keyBytes =  utf8_decode($keyString); //convert from utf-8 to single-byte ISO-8859-1
		
		
		//final SecretKeySpec key = new SecretKeySpec(keyBytes, MAC_NAME);
		//final Mac mac = Mac.getInstance(MAC_NAME);
		//mac.init(key);
		$text = utf8_decode( $baseString );
		//$signatureBytes = mac.doFinal(text);
		//$key = $this->hmacSha1($keyBytes,$this->
		
		$signatureBytes = hash_hmac('sha1', $text, $keyBytes, true); //outputs raw bytes
		$signatureBytes = base64_encode($signatureBytes);
		$signature = utf8_decode($signatureBytes);
		if($this->debug) echo "<div><b>Returning signature</b> {$signature}</div>";
		return $signature;
    }
	
	public function hmacSha1($key, $data) {
		if($this->debug) echo "<div style=\"border:1px solid #ccc\"><b>".__FUNCTION__." parameters (key, data):</b><pre>".print_r(func_get_args(),true)."</pre></div>";
		$blocksize = 64;
		$hashfunc = 'sha1';
		if (strlen($key) > $blocksize) {
		  $key = pack('H*', $hashfunc($key));
		}
		$key = str_pad($key, $blocksize, chr(0x00));
		$ipad = str_repeat(chr(0x36), $blocksize);
		$opad = str_repeat(chr(0x5c), $blocksize);
		$hmac = pack('H*', $hashfunc(($key ^ $opad) . pack('H*', $hashfunc(($key ^ $ipad) . $data))));
		if($this->debug) echo "<div><b>Returning hmac</b> {$hmac}</div>";
		return $hmac;
	}

    /**
     * 
     * @param consumerSecret
     * @param httpMethod
     * @param providerUrl
     * @param oauthParameters Map<String, String> 
     * @param rawRequestParameters Map<String, List<String>> 
     * @throws RuntimeException
     */
    private function buildOAuthSignatureParameter($consumerSecret, $httpMethod, $providerUrl, &$oauthParameters, &$rawRequestParameters){
		if($this->debug) echo "<div style=\"border:1px solid #ccc\"><b>".__FUNCTION__." parameters (consumerSecret, httpMethod, providerUrl, &oauthParameters, &rawRequestParameters):</b><pre>".print_r(func_get_args(),true)."</pre></div>";
        
		$baseString = $this->buildSignatureBaseString($httpMethod, $providerUrl, $oauthParameters, $rawRequestParameters);
        $signature = $this->sign($consumerSecret, $baseString);
        $oauthParameters["oauth_signature"] = $signature;
		if($this->debug) echo "<div><b>oauth_signature</b> = {$signature}</div>";
    }

    /**
     * assemble the header values into a string
     * @param oauthParameters Map<String,String> 
     * @return the header values as a string
     */
    private function assembleHeaderString($oauthParameters)
    {
		if($this->debug) echo "<div style=\"border:1px solid #ccc\"><b>".__FUNCTION__." parameters (oauthParameters):</b><pre>".print_r(func_get_args(),true)."</pre></div>";
		
        $value = "OAuth "; //StringBuilder
        $startLength = strlen($value);
        foreach($oauthParameters as $key=>$val)// Entry<String,String> e : .entrySet() )
        {
            if ( strlen($value) > $startLength ) $value .= ", ";
            $value .= $key;
            $value .= "=\"";
            $value .= $this->encode($val);
            $value .= "\"";
        }
		if($this->debug) echo "<div><b>Returning headerString</b> {$value}</div>";
        return $value;
    }

    /**
	 * @param $headers final Map<String, String> 
	 * @param $method
	 * @param $url
	 * @param $consumerKey
	 * @param $consumerSecret
	 * @param $requestParameters Map<String, List<String>>
	 */
    public function signWithOAuthHeader(&$headers,
            $method,
            $url,
            $consumerKey,
            $consumerSecret,
            &$requestParameters,
			$debug = false){
        $this->debug = $debug;
		
		if($this->debug) echo "<div style=\"border:1px solid #ccc\"><b>".__FUNCTION__." parameters (&headers,method,url,consumerKey,consumerSecret,&requestParameters,debug):</b><pre>".print_r(func_get_args(),true)."</pre></div>";
		
		//Map<String,String> 
		if($this->debug) echo "<b>Building initial oauthParameters array...</b><ul>";
        $oauthParameters = array();
        $oauthParameters["oauth_consumer_key"] = $consumerKey;
		if(!$this->disableToken){
        	$oauthParameters["oauth_token"] = ""; //there's that blank token again!
		}

        $oauthParameters["oauth_nonce"] = $this->buildRandomString($this->NONCE_LENGTH);
        $oauthParameters["oauth_signature_method"] = $this->SIGNATURE_METHOD_NAME;
        $oauthParameters["oauth_timestamp"] = time();
        $oauthParameters["oauth_version"] = "1.0";
		
		if($this->debug) echo "</ul><b>oauthParameters array value:</b><ul><div style=\"border:1px solid #ccc\"><pre>".print_r($oauthParameters,true)."</pre></div></ul>";

		if($this->debug){ 
			echo "<b>Calling buildOAuthSignatureParameter...</b>";
			echo "<ul>";
		}
		
        $this->buildOAuthSignatureParameter($consumerSecret, $method,str_replace("https://", "http://",$url), $oauthParameters, $requestParameters);
		
		if($this->debug){ 
			echo "</ul>";
			echo "<b>Calling assembleHeaderString...</b>";
			echo "<ul>";
		}
        $header = $this->assembleHeaderString($oauthParameters); //DONE
		
		if($this->debug){ 
			echo "</ul>";
		}
		
        $headers["Authorization"] = $header;
		
		
    }
}
?>