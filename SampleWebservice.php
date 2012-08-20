<?php

$request = new Request();

$request->exec();


class Request {
    public $url_elements;
    public $verb;
    public $parameters;
	private $post;
 
    public function __construct() {
        $this->verb = $_SERVER['REQUEST_METHOD'];
        $this->url_elements = $_GET; //always set just in case
        $this->post = $_POST;
		$this->get = $_GET;
	}
	
	private function GET(){
		$this->parameters = $this->get;
		$this->url_elements = "";
	}
	private function POST(){
		$this->parameters = $this->post;
	}
	private function PUT(){
		parse_str(file_get_contents("php://input"),$this->parameters);
	}
	private function DELETE(){
		parse_str(file_get_contents("php://input"),$this->parameters);
	}
	
	public function exec(){
		$verb = $this->verb;
		$this->$verb();
		echo $this->verb."<br />";
		echo "URL Elements: ".print_r($this->url_elements,true)."<br />";
		echo "Parameters: ".print_r($this->parameters,true);
	}
}

?>