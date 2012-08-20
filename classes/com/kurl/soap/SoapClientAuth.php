<?php
namespace com\kurl\soap;

use com\kurl\soap\streamWrapperHttpAuth;
use com\kurl\curl\Kurl;

/**
 *    SoapClientAuth for accessing Web Services protected by HTTP authentication
 *    Author: tc
 *    Last Modified: 07/23/2012
 *    Update: 23/07/2012 - Now uses cURL class to handle curl. PHP 5.3 compatible namespaces.
 *    Download from: http://tcsoftware.net/blog/
 *    Modified by: Allan Bogh (ajbogh@allanbogh.com)
 *
 *    Copyright (C) 2011  tc software (http://tcsoftware.net)
 *
 *    This program is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


    /**
     * SoapClientAuth
     * The interface and operation of this class is identical to the PHP SoapClient class (http://php.net/manual/en/class.soapclient.php)
     * except this class will perform HTTP authentication for both SOAP messages and while downloading WSDL over HTTP and HTTPS.
     * Provide the options login and password in the options array of the constructor.
     * 
     * @author tc
     * @copyright Copyright (C) 2011 tc software
     * @license http://opensource.org/licenses/gpl-license.php GNU Public License
     * @link http://php.net/manual/en/class.soapclient.php
     * @link http://tcsoftware.net/
     */
    class SoapClientAuth extends \SoapClient{
		public $Username = NULL;
		public $Password = NULL;
		
		/**
		 * 
		 * @param string $wsdl
		 * @param array $options 
		 */
		function __construct($wsdl, $options = NULL){
		    stream_wrapper_unregister('https');
		    stream_wrapper_unregister('http');
		    stream_wrapper_register('https', 'com\kerls\soap\streamWrapperHttpAuth');
		    stream_wrapper_register('http', 'com\kerls\soap\streamWrapperHttpAuth');
		    
		    if($options){
				$this->Username = $options['login'];
				streamWrapperHttpAuth::$Username = $this->Username;
				$this->Password = $options['password'];
				streamWrapperHttpAuth::$Password = $this->Password;
		    }
		    
		    parent::SoapClient($wsdl, ($options?$options:array()));
		    
		    stream_wrapper_restore('https');
		    stream_wrapper_restore('http');
		}
		
		function __doRequest($request, $location, $action, $version){
	
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
			if(($info = $response["info"]) && ($info['http_code']==200 || $info['http_code']==302))
			    return $response["result"];
			else if($info['http_code']==401)
			    throw new \Exception ('Access Denied', 401);	
			else if(curl_errno($ch)!=0)
			{
				throw new \Exception($response);
			}else
			    throw new \Exception($response);
		}
    }

    
?>
