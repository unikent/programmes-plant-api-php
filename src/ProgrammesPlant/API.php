<?php

namespace ProgrammesPlant;

use Guzzle\Http\Client;
use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Plugin\Cache\CachePlugin;

use Doctrine\Common\Cache\FileCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Cache\ArrayCache;

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
	 * Stores errors.
	 */
	public $errors = array();

	/**
	 * Client options used by Guzzle.
	 */
	public $guzzle_options = array();

	/**
	 * Use a cache or not.
	 * 
	 * False or set to a type of cache to use.
	 */
	public $cache = false;

	/**
	 * The cache object itself.
	 */
	public $cache_object = false;

	/**
	 * The Guzzle cache plugin.
	 */
	public $cache_plugin = false;

	/**
	 * The current request object for Guzzle.
	 */
	public $request = false;

	/**
	 * The last response.
	 */
	public $last_response = false;

	/**
	 * The cache adapter object.
	 */
	public $adapter = false;

	/**
	 * Directory of the cache.
	 */
	public $cache_directory;

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
	 * Turn the cache on with a particular type.
	 * 
	 * Defaults to memory without first argument.
	 * 
	 * @param string The type of cache - currently support are array and file.
	 */
	public function with_cache($type = 'memory')
	{
		if (strpos($type, 'memory') === false && strpos($type, 'file') === false)
		{
			throw new ProgrammesPlantException("$this->cache is not a supported cache type");
		}

		$this->cache = $type;

		return $this;
	}

	/**
	 * Set the directory for the file cache.
	 *
	 * @param $dir The directory for the cache.
	 */
	public function directory($directory)
	{
		if ($this->cache != 'file')
		{
			throw new ProgrammesPlantException("Cannot set directory, not using file cache.");
		}

		if (! is_dir($directory))
		{
			throw new ProgrammesPlantException("Directory does not exist");
		}

		$this->cache_directory = $directory;

		return $this;
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

		$this->guzzle_options['curl.options']['CURLOPT_HTTPPROXYTUNNEL'] = true;
		$this->guzzle_options['curl.options']['CURLOPT_PROXY'] = $this->proxy_server . ':' . $this->proxy_port;
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

			if ($this->cache)
			{
				if ($this->cache == 'file')
				{
					if (! $this->cache_directory)
					{
						throw new ProgrammesPlantException("No cache directory set");
					}

					$this->cache_object = new FilesystemCache($this->cache_directory);
				}
				elseif ($this->cache == 'memory')
				{
					$this->cache_object = new ArrayCache();
				}

				$this->adapter = new DoctrineCacheAdapter($this->cache_object);

				$this->cache_plugin = new CachePlugin(
					array(
	    				'adapter' => $this->adapter
					)
				);

				$this->guzzle_client->addSubscriber($this->cache_plugin);
			}			
		}

		try 
		{
			$this->request = $this->guzzle_client->get($url);
			$this->last_response = $this->request->send();
		}

		/**
		 * Handle each of the possible exception states separately.
		 */

		// 4xx Codes
		// Throw exception.
		catch (\Guzzle\Http\Exception\ClientErrorResponseException $e)
		{
			switch ($e->getResponse()->getStatusCode()) 
			{
				case 404:
					throw new ProgrammesPlantNotFoundException("$url not found, attempting to get " . $this->api_target . '/' . $url);
				default:
					throw new ProgrammesPlantRequestException('Request failed for ' . $this->api_target . '/' . $url . ', error code ' . $e->getResponse()->getStatusCode());
				break;
			}
			return;
		}

		// 5xx Codes
		// Attempt to respond with cache or throw an error.
		catch (\Guzzle\Http\Exception\ServerErrorResponseException $e)
		{
			throw new ProgrammesPlantRequestException('Request failed for ' . $this->api_target . '/' . $url . ', Guzzle reports ' . $e->getMessage());
		}

		// cURL Related Exception
		// Attempt a cache load or throw an error.
		catch (\Guzzle\Http\Exception\CurlException $e)
		{
			// Server Not Found
			// Likely the API is down.
			if ($e->getErrorNo() == 6)
			{
				throw new ProgrammesPlantServerNotFound($this->api_target . ' not found - is the Programmes Plant API down?');
			}
			else
			{
				throw new ProgrammesPlantRequestException('Request failed for ' . $this->api_target . '/' . $url . ', problem is with cuRL. Guzzle reports ' . $e->getMessage());
			}
		}

		return $this->last_response;	
	}

	/**
	* Runs a request against the Programmes Plant API.
	* 
	* @param $url              The API method.
	* @return class $response  The de-JSONified response from the API.
	*/
	public function make_request($api_method)
	{
		$response = $this->guzzle_request($api_method);

		if ($this->errors)
		{
			if ($errors[0] == 404)
			{

			}
			else
			{
				throw new ProgrammesPlantRequestException("Error occurred: " . $this->errors[0]);
			}
		}
		
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
	  * Get an "unpublished" preview programme from the Programmes Plant API
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

class ProgrammesPlantRequestException extends \Exception {}

class ProgrammesPlantServerNotFound extends \Exception {}

class ProgrammesPlantNotFoundException extends \Exception {}