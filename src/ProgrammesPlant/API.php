<?php

namespace ProgrammesPlant;

use Guzzle\Http\Client;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Message\Request;
use Guzzle\Cache\DoctrineCacheAdapter;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Plugin\Cache\CallbackCanCacheStrategy;

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
	protected $guzzle_client = false;

	/**
	 * The location the API is at.
	 */
	public $api_target = '';

	/**
	 * The location of a HTTP proxy if required.
	 */
	public $proxy_server = '';

	/**
	 * The port of a HTTP proxy if required.
	 */
	public $proxy_port = '';

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
	 * The cache plugin object.
	 */
	public $cache_plugin = false;

	/**
	 * The cache object itself.
	 */
	public $cache_object = false;

	/**
	 * The current request object for Guzzle.
	 */
	public $request = false;

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
			throw new ProgrammesPlantException("No Endpoint for Programmes Plant API specified.");
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
			throw new ProgrammesPlantException("$type is not a supported cache type.");
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
			throw new ProgrammesPlantException("Directory does not exist.");
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
	 * Serve the response from cache.
	 * 
	 * @return bool|array  False if we haven't got this in the cache, the cache array if not.
	 */
	public function serve_from_cache()
	{
		// Work out how this would be cached.
		$key_provider = new \Guzzle\Plugin\Cache\DefaultCacheKeyProvider();
		$cache_key = $key_provider->getCacheKey($this->request);

		// Attempt to get cache object.
		$cached = $this->cache_object->fetch($cache_key);

		return $cached;
	}

	/**
	 * Tell us if the last request was served from the cache.
	 * 
	 * @param  Request $request The Request object to check this for - defaults to the last request.
	 * @return bool If the last request was served from the cache.
	 */
	public function served_from_cache($request = false)
	{
		if (! $request)
		{
			$request = $this->request;
		}

		return $request->getResponse()->hasHeader('X-Guzzle-Cache');
	}

	/**
	 * Prepare the Guzzle object for a request.
	 * 
	 * @return $this Self object for chaining.
	 */
	public function prepare()
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
						throw new ProgrammesPlantException("No cache directory set.");
					}

					$this->cache_object = new FilesystemCache($this->cache_directory);
				}
				elseif ($this->cache == 'memory')
				{
					$this->cache_object = new ArrayCache();
				}

				$adapter = new DoctrineCacheAdapter($this->cache_object);

				$this->cache_plugin = new CachePlugin(
					array(
	    				'adapter' => $adapter,
	    				'can_cache' => new CallbackCanCacheStrategy(function(Request $request){
	    					
	    					return $request->canCache();

	    				}, function(Response $response){
	    					$can = $response->isSuccessful() && $response->canCache();

	    					if($can){
	    						try {
	    							API::json_decode($response->getBody(true));
	    						} catch (JSONDecode $e) {
	    							$can = false;
	    						}
	    						
	    					}

	    					return $can;
	    				})
					)
				);

				$this->guzzle_client->addSubscriber($this->cache_plugin);
			}			
		}

		return $this;
	}

	/**
	* Runs the request against the API.
	*
	* @param string $url The API endpoint to send a request to.
	* @return string $response The response object.
	*/
	public function guzzle_request($url)
	{
		$this->prepare();

		$this->request = $this->guzzle_client->get($url);

		try 
		{
			$this->request->send();
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
				break;

				default:
					throw new ProgrammesPlantRequestException('Request failed for ' . $this->api_target . '/' . $url . ', error code ' . $e->getResponse()->getStatusCode());
				break;
			}

			return;
		}

		// 5xx Codes
		// Attempt to respond with cache or throws an error.
		catch (\Guzzle\Http\Exception\ServerErrorResponseException $e)
		{
			// Obtain from cache.
			if ($this->cache)
			{
				$cached = $this->serve_from_cache();

				if (! $cached)
				{
					throw new ProgrammesPlantRequestException('Request failed for ' . $this->api_target . '/' . $url . '  - attempted to serve from cache but not found.');
				}

				return new \Guzzle\Http\Message\Response($cached[0], $cached[1], $cached[2]);
			}
			else
			{
				throw new ProgrammesPlantRequestException('Request failed for ' . $this->api_target . '/' . $url . '  - no cache.');
			}
		}

		// cURL Related Exception
		catch (\Guzzle\Http\Exception\CurlException $e)
		{
			// Work out which exception to throw based on cURL error code.
			switch ($e->getErrorNo()) 
			{
				// Could Not Resolve Proxy
				// Provided proxy server is not functioning.
				case 5:
					throw new ProxyNotFound('Proxy server ' . $this->proxy_server . ' could not be found, DNS lookup failed.');
				break;

				// Could Not Resolve Host
				// Likely the API is down due to some misconfiguration.
				case 6:
					throw new ProgrammesPlantServerNotFound($this->api_target . ' not found, DNS lookup failed - is this address correct?');
				break;
				
				default:
					throw new CurlException('Request failed for ' . $this->api_target . '/' . $url . ', problem is with cuRL. cURL error ' . $e->getErrorNo());
				break;
			}
		}

		return $this->request->getResponse();
	}

	/**
	 * JSON decode, but throws exceptions on false JSON.
	 *	
	 * @param string $json A JSON string.
	 * @param bool $as_array Return as array if true.
	 * @return array|object $json The JSON converted.
	 */
	public static function json_decode($json, $as_array = false)
	{
		$decoded = json_decode($json, $as_array);

		if (is_null($decoded))
		{
			$error = '';

			switch (json_last_error()) 
			{
        		case JSON_ERROR_NONE:
            		$error = 'No errors.';
        		break;

		        case JSON_ERROR_DEPTH:
		            $error = 'Maximum stack depth exceeded.';
		        break;

		        case JSON_ERROR_STATE_MISMATCH:
		            $error = 'Underflow or the modes mismatch.';
		        break;

		        case JSON_ERROR_CTRL_CHAR:
		            $error = 'Unexpected control character found.';
		        break;

		        case JSON_ERROR_SYNTAX:
		            $error = 'Syntax error, malformed JSON.';
		        break;

		        case JSON_ERROR_UTF8:
		            $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
		        break;

		        default:
		            $error = 'Unknown error.';
		        break;
    		}

			throw new JSONDecode('We cannot decode invalid JSON,  json_decode reports: ' . $error . "\nString: " . $json);
		}

		return $decoded;
	}

	/**
	* Runs a request against the Programmes Plant API.
	* 
	* @param  string $url       The API method.
	* @param  string $as        Variable type to return - object, array or raw.
	* @return class  $response  The response in the format specified.
	*/
	public function make_request($api_method, $as = 'object')
	{
		try
		{
			$payload = $this->guzzle_request($api_method)->getBody();

			if ($as == 'array')
			{
				return static::json_decode($payload, true);
			}
			else if ($as == 'raw')
			{
				return $payload;
			}
			// By default return an object.
			else
			{
				return static::json_decode($payload);
			}
		}
		
		// Catch exception in case where JSON in invalid.
	 	catch (JSONDecode $e)
	 	{
	 		throw new ProgrammesPlantException("Response was not valid JSON and could not be decoded.");
	 	}

	}

	/**
	 * Get a programme by ID from the API.
	 * 
	 * @param int $year The year of the programme to get.
	 * @param string $level Either undergraduate or post-graduate.
	 * @param int $id The ID of the programme to get.
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response The programme as an object.
	 */ 
	public function get_programme($year, $level, $id, $as = 'object')
	{
		if ($level != 'undergraduate' && $level != 'postgraduate')
		{
			throw new InvalidArgument("Invalid argument specified for get_programme - level must be undergraduate or postgraduate");
		}

	 	return $this->make_request("$year/$level/programmes/$id", $as);
	}

	/**
	 * Get the complete index of programmes from the API.
	 * 
	 * @param int $year The year of the programme index to get.
	 * @param string $level Either undergraduate for post-graduate.
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response The programmes index as an object.
	 */
	public function get_programmes_index($year, $level, $as = 'object')
	{
		if ($level != 'undergraduate' && $level != 'postgraduate')
		{
			throw new InvalidArgument("Invalid argument specified for get_programmes_index - level must be undergraduate or postgraduate");
		}
	 	return $this->make_request("$year/$level/programmes", $as);
	}
	
	/**
	 * Get an index of subjects from the programmes plant API
	 * 
	 * @param int $year The year of programmes to get.
	 * @param string $level Either undergraduate or post-graduate.
	 * @param string $as Variable type to return - object, array or raw.
	 * @return array $response The subject index as an array.
	 */ 
	public function get_subject_index($year, $level, $as = 'object')
	{
		if ($level != 'undergraduate' && $level != 'postgraduate')
		{
			throw new InvalidArgument("Invalid argument specified for get_subject_index - level must be undergraduate or postgraduate");
		}
	 	return $this->make_request("$year/$level/subjects", $as);
	}
	
	/**
	 * Get an "unpublished" preview programme from the Programmes Plant API
	 * 
	 * @param string $hash Unique identifier for preview snapshot.
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response The programme as an object.
	 */ 
	public function get_preview_programme($level, $hash, $as = 'object')
	{
		return $this->make_request("preview/$level/$hash", $as);
	}

	/**
	 * Get a simpleview programme from the Programmes Plant API
	 * 
	 * @param string $hash Unique identifier for simpleview snapshot.
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response The programme as an object.
	 */ 
	public function get_simpleview_programme($level, $hash, $as = 'object')
	{
		return $this->make_request("preview/$level/$hash", $as);
	}
	
	/**
	 * Get subject categories list from the API.
	 *
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response
	 */ 
	public function get_subjectcategories($level = 'undergraduate', $as = 'object')
	{
		if ($level != 'undergraduate' && $level != 'postgraduate')
		{
			throw new InvalidArgument("Invalid argument specified for get_subjectcategories - level must be undergraduate or postgraduate");
		}

		return $this->make_request("{$level}/subjectcategories", $as);
	}
	
	/**
	 * Get schools list from the API.
	 *
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response
	 */ 
	public function get_schools($as = 'object')
	{
		return $this->make_request("schools", $as);
	}
	
	/**
	 * Get faculties list from the API.
	 * 
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response
	 */ 
	public function get_faculties($as = 'object')
	{
		return $this->make_request("faculties", $as);
	}
	
	/**
	 * Get campuses list from the API.
	 * 
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response
	 */ 
	public function get_campuses($as = 'object')
	{
		return $this->make_request("campuses", $as);
	}
	
	/**
	 * Get subject leaflets from the API.
	 *
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response
	 */ 
	public function get_subject_leaflets($year, $level, $as = 'object')
	{
		return $this->make_request("$level/$year/leaflets", $as);
	}

	/**
	 * Get awards from the API.
	 *
	 * @param string $level 
	 * @param string $as Variable type to return - object, array or raw.
	 * @return object $response
	 */ 
	public function get_awards($level = 'undergraduate', $as = 'object')
	{
		if ($level != 'undergraduate' && $level != 'postgraduate')
		{
			throw new InvalidArgument("Invalid argument specified for get_awards - level must be undergraduate or postgraduate");
		}
		return $this->make_request("{$level}/awards", $as);
	}
	
	/**
	 * 
	 * 
	 * @param int $year The year.
	 * @param string $level Either undergraduate or post-graduate.
	 * @param string $as Variable type to return - object, array or raw.
	 * @return array $response The subject index as an array.
	 */ 
	public function get_xcri_cap($year, $level, $as = 'raw')
	{
		if ($level != 'undergraduate' && $level != 'postgraduate')
		{
			throw new InvalidArgument("Invalid argument specified for get_xcri_cap - level must be undergraduate or postgraduate");
		}
		
		//we want a gzipped feed
		//$this->set_encoding();
	 	
	 	return $this->make_request("xcri-cap", $as);
	}

	/**
	 * Set the encoding of the response.
	 * Note: calling this caused curl to fail with error 61 on my localhost, so not currently in use
	 * 
	 * @param $encoding This defaults to gzip. also, if an empty string is passed, CURL automatically defaults to all supported encoding types
	 */
	public function set_encoding($encoding = "gzip")
	{
		$this->guzzle_options['curl.options']['CURLOPT_ENCODING'] = $encoding;
	}
}

class ProgrammesPlantException extends \Exception {}

class ProgrammesPlantRequestException extends \Exception {}

class ProgrammesPlantServerNotFound extends \Exception {}

class InvalidArgument extends \Exception {}

class CurlException extends \Exception {}

class ProxyNotFound extends \Exception {}

class JSONDecode extends \Exception {}

class ProgrammesPlantNotFoundException extends \Exception {}