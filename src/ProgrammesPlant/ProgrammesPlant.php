<?php 

namespace ProgrammesPlant;

/**
 * ProgrammesPlant
 * 
 * Provides a simple API for the Programmes Plant REST API.
 */
class ProgrammesPlant 
{
	/**
	 * Persists the cURL object.
	 */
	public $curl = false;

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

	/**
	 * Construct The Class.
	 * 
	 * Adding in the API target for the programmes plant, a URL.
	 * 
	 * @param string $api_target An 
	 */
	public function __construct($api_target = false)
	{
		if (! $api_target)
		{
		throw new ProgrammesPlantException("No Endpoint for Programmes Plant API specified");
		}

		$this->api_target = $api_target;
	}

	/**
	* Set a HTTP proxy for the request.
	* 
	* @param string $proxy_server The URL of the proxy server.
	* @param int $port The port of the proxy server.
	*/
	public function set_proxy($proxy_server, $proxy_port = 3128) 
	{
		$this->proxy = true;
		$this->proxy_server = $proxy_server;
		$this->proxy_port = $proxy_port;
	}

	/**
	* Runs a cURL request.
	* 
	* The library here automatically sets CURLOPT_RETURNTRANSFER and CURLOPT_FOLLOWLOCATION.
	*
	* @param string $url The URL to make the request to.
	* @return string $response The response object.
	*/
	public function curl_request($url)
	{
		$this->curl = new Curl($url);
		$this->curl->http_method = 'get';

		if ($this->proxy)
		{
			$this->curl->proxy($this->proxy_server, $this->proxy_port);
		}
		
		return $this->curl->execute;
	}

	/**
	* Runs a request against the Programmes Plant API.
	* 
	* @param $api_method The API method.
	* @return class $response The de-JSONified response from the API.
	*/
	public function make_request($api_method)
	{
		$url = "$this->api_target/$api_method";
		$curl_response = $this->curl_request($url);

		if (! $curl_response)
		{
			throw new ProgrammesPlantException("Could not cURL $url, $this->curl->error_code, $this->curl->error_string");
		}

		$response = json_decode($curl_response);
		if (! $response)
		{
			throw new ProgrammesPlantException("Response from $url was not valid JSON");
		}

	 	return $response;
	 }

	 /**
	  * Get a programme by ID from the API.
	  * 
	  * @param int $id The ID of the programme to get.
	  * @param string $level Either undergraduate or post-graduate.
	  * @param int $year The year of the programme to get.
	  * @return object $response The programme as an object.
	  */ 
	 public function get_programme($id, $level, $year)
	 {
	 	return $this->make_request("$level/$year/$id");
	 }

	 /**
	  * Get the complete index of programmes from the API.
	  * 
	  * @param string $level Either undergraduate for post-graduate.
	  * @param int $year The year of the programme index to get.
	  * @return object $response The programmes index as an object.
	  */
	 public function get_index($level, $year)
	 {
	 	return $this->make_request("$level/$year");
	 }
}

class ProgrammesPlantException extends \Exception {}