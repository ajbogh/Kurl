<?php
	spl_autoload_register(function($class){
		$dir = str_replace('\\', '/', __DIR__);
		$dir = substr($dir,0,strrpos($dir,'/'));
		// convert namespace to full file path  
	    $class = $dir.'/classes/'.str_replace('\\', '/', $class).'.php'; 
	    //if(!class_exists($class)){
	    	include_once($class);
		//}
	});
?>