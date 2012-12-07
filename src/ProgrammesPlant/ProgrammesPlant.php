<?php 

namespace ProgrammesPlant;

class ProgrammesPlant 
{
	/**
	 * The location the API is at.
	 */
	public $api_target = '';

	/**
	 * Boolean that sets if we want to use a proxy in CURL
	 */
	public $proxy = false;

	/**
	 * The location of a HTTP proxy if required.
	 */
	public $proxy_server = '';

	/**
	 * The port of a HTTP proxy if required.
	 */
	public $proxy_port = '';

	 public function __construct($api_target = false)
	 {
	 	if (! $api_target)
	 	{
			throw new ProgrammesPlantException("No Endpoint for Programmes Plant API specified");

	 	}

	 	$this->api_target = $api_target;
	 }

	 public function set_proxy($proxy_server, $proxy_port = 3128) 
	 {
	 	$this->proxy = true;
	 	$this->proxy_server = $proxy_server;
	 	$this->proxy_port = $proxy_port;
	 }
}

class ProgrammesPlantException extends \Exception {}