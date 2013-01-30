<?php

namespace ProgrammesPlant;

use Guzzle\Http\Client;

/**
 * ProgrammesPlant
 * 
 * Provides a simple API for the Programmes Plant REST API.
 */
class API
{
	/**
	 * Persists the cURL object.
	 */
	public $guzzle_client = false;

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
	 * Holds the success of the last response.
	 */
	public $last_response = false;

	/**
	 * Stores errors.
	 */
	public $errors = array();

	/**
	 * Client options used by Guzzle.
	 */
	public $guzzle_options = array();

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

		// Remove trailing slash as this sometimes causes a 404 with cURL
		$this->api_target = rtrim($api_target, '/');
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
	 * Turn SSL verification for our API request off.
	 * 
	 * @return void
	 */
	public function no_ssl_verification()
	{
		$this->guzzle_options['ssl.certificate_authority'] = false;
	}

	/**
	* Runs the request against the API.
	*
	* @param string $url The API endpoint to send a request to.
	* @return string $response The response object.
	*/
	public function guzzle_request($url)
	{
		if (! $this->guzzle_client)
		{
			$this->guzzle_client = new Client($this->api_target, $this->guzzle_options);
		}

		try 
		{
			$response = $this->guzzle_client->get($url)->send();
		}
		catch (Guzzle\Http\Exception\ClientErrorResponseException $e)
		{
			echo 'Bad response from Guzzle' . $e->getMessage();
		}

		return $response;	
	}

	/**
	* Runs a request against the Programmes Plant API.
	* 
	* @param $api_method The API method.
	* @return class $response The de-JSONified response from the API.
	*/
	public function make_request($api_method)
	{
		$url = $api_method;
		$response = $this->guzzle_request($url);

		$payload = json_decode($response->getBody());

		if (! $response)
		{
			throw new ProgrammesPlantException("Response from $url was not valid JSON");
		}

	 	return $payload;
	 }

	 /**
	  * Print errors to screen.
	  * 
	  * @return void
	  */
	 public function print_errors()
	 {
	 	echo "\n";
	 	
	 	foreach($this->errors as $error)
	 	{
	 		echo $error . "\n";
	 	}
	 }

	 /**
	  * Get a programme by ID from the API.
	  * 
	  * @param int $year The year of the programme to get.
	  * @param string $level Either undergraduate or post-graduate.
	  * @param int $id The ID of the programme to get.
	  * @return object $response The programme as an object.
	  */ 
	 public function get_programme($year, $level, $id)
	 {
	 	return $this->make_request("$year/$level/programmes/$id");
	 }

	 /**
	  * Get the complete index of programmes from the API.
	  * 
	  * @param int $year The year of the programme index to get.
	  * @param string $level Either undergraduate for post-graduate.
	  * @return object $response The programmes index as an object.
	  */
	 public function get_programmes_index($year, $level)
	 {
	 	return $this->make_request("$year/$level/programmes");
	 }
	 
	 /**
	  * Get an index of subjects from the programmes plant API
	  * 
	  * @param int $year The year of programmes to get.
	  * @param string $level Either undergraduate or post-graduate.
	  * @return array $response The subject index as an array.
	  */ 
	 public function get_subject_index($year, $level)
	 {
	 	return $this->make_request("$year/$level/subjects");
	 }
	 
	 /**
	  * Get an "unpublished" preiview programme from the Programmes Plant API
	  * 
	  * @param string $hash Unique identifier for preview snapshot.
	  * @return object $response The programme as an object.
	  */ 
	 public function get_preview_programme($hash)
	 {
	 	return $this->make_request("preview/$hash");
	 }
}

class ProgrammesPlantException extends \Exception {}