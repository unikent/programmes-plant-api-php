<?php
date_default_timezone_set('Europe/London');

use \ProgrammesPlant\API as PP;

use Guzzle\Common\Event;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Guzzle\Http\Utils;

class ProgrammesPlantTest extends \Guzzle\Tests\GuzzleTestCase
{
	public static $cache_directory = '';

	/**
	 * Removes a directory and all its contents.
	 * 
	 * @param  string $directory Directory to remove.
	 * @return bool   True or false on success of the deletion.
	 */
	public static function remove_directory($directory)
	{
		return static::wipe_directory($directory, false);
	}

	/**
	 * Blanks a directory i.e. removes all its contents.
	 * 
	 * @param  string $directory Directory to blank.
	 * @param  bool   $empty     If false then this will remove the directory parameter itself also.
	 * @return bool   True or false depending on the success.
	 */
	public static function wipe_directory($directory, $empty = true)
	{
    	if (substr($directory, -1) == '/')
    	{
        	$directory = substr($directory,0,-1);
    	}

    	if (!file_exists($directory) || ! is_dir($directory))
    	{
        	return false;
   		}
   		elseif (is_readable($directory))
   		{
       		$handle = opendir($directory);

       		while (false !== ($item = readdir($handle)))
	        {
	        	if($item != '.' && $item != '..')
	            {
	                $path = $directory.'/'.$item;

	                if(is_dir($path)) 
	                {
	                	static::remove_directory($path);
	                }
	                else
	                {
	                    unlink($path);
	            	}
	            }
	        }
        	closedir($handle);

	        if ($empty == false)
	        {
	        	if (!rmdir($directory))
	        	{
	            	return false;
	           	}
	        }
    	}

    	return true;
	}

	public static function setUpBeforeClass()
	{
		static::$cache_directory = sys_get_temp_dir() . '/guzzle-cache/';

		if (! is_dir(static::$cache_directory))
		{
			mkdir(static::$cache_directory);
		}
	}

	public static function tearDownAfterClass()
	{
		static::remove_directory(static::$cache_directory);
	}

	public function tearDown()
	{
		$this->wipe_directory(static::$cache_directory);
		$this->getServer()->flush();
	}

	/**
	 * Create a new instance of the Programmes Plant API that connects to Node.js server.
	 */
	private function setup_with_server()
	{
		return new PP($this->getServer()->getUrl());
	}

	public function test__constructSetsUpTarget()
	{
		$pp = new PP('http://example.com/api');
		$this->assertEquals('http://example.com/api', $pp->api_target);
	}

	public function test__constructTrimsTrailingSlashesOffTheURL()
	{
		$pp = new PP("http://example.com/api/");
		$this->assertEquals('http://example.com/api', $pp->api_target, 'Constructor did not strip trailing slash.');

		$pp = new PP("http://example.com/api/2");
		$this->assertEquals('http://example.com/api/2', $pp->api_target, 'Constructor stripped something more than trailing slash.');
	}

	/**
     * @expectedException ProgrammesPlant\ProgrammesPlantException
     * @expectedExceptionMessage No Endpoint for Programmes Plant API specified.
     */
	public function test__constructThrowsExpectionWhenTargetIsNotSpecified()
	{
		$pp = new PP();
	}

	public function LevelTestDataProvider()
	{
		return array(
			array('get_programmes_index'),
			array('get_programme', true),
			array('get_subject_index')
		);
	}

	/**
	 * @dataProvider LevelTestDataProvider
	 */
	public function testExceptionThrownWhenInvalidLevelIsSpecified($method, $id = false)
	{
		$pp = new PP('http://example.com');

		$this->setExpectedException('ProgrammesPlant\InvalidArgument', "Invalid argument specified for $method - level must be undergraduate or postgraduate");

		if ($id)
		{
			$pp->{$method}('2014', 'thing', 1);
		}
		else
		{
			$pp->{$method}('2014', 'thing');
		}			
	}

	/**
	 * @dataProvider LevelTestDataProvider
	 */
	public function testMakesRequestWhenArgumentsAreCorrect($method, $id = false)
	{
		foreach(array('undergraduate', 'postgraduate') as $level)
		{
			// Mock up the make_request method.
			$mock = $this->getMock('ProgrammesPlant\API', array('make_request'),
				array('http://example.com')
			);

	        if ($id)
			{
				$mock->expects($this->once())
	                 ->method('make_request')
	                 ->with($this->equalTo('2014/' . $level . '/programmes/1'));

				$mock->{$method}('2014', $level, 1);
			}
			elseif ($method == 'get_subject_index')
			{
				$mock->expects($this->once())
	                 ->method('make_request')
	                 ->with($this->equalTo('2014/' . $level . '/subjects'));

	            $mock->{$method}('2014', $level);
			}
			else
			{
				$mock->expects($this->once())
	                 ->method('make_request')
	                 ->with($this->equalTo('2014/' . $level . '/programmes'));

				$mock->{$method}('2014', $level);
			}
		}
		
	}

	public function testSetProxyForCURLWithoutPortPortIs3128()
	{
		$pp = new PP('http://example.com');
		$pp->set_proxy('http://proxy.example.com');

		$this->assertEquals('http://proxy.example.com', $pp->proxy_server);
		$this->assertEquals(3128, $pp->proxy_port);
		$this->assertTrue($pp->proxy);
	}

	public function testSetProxyPortForCURL()
	{
		$pp = new PP('http://example.com');
		$pp->set_proxy('http://proxy.example.com', 8000);

		$this->assertEquals('http://proxy.example.com', $pp->proxy_server);
		$this->assertEquals(8000, $pp->proxy_port);
		$this->assertTrue($pp->proxy);
	}

	public function testSetSSLToNotVerify()
	{
		$pp = new PP('http://example.com');
		$pp->no_ssl_verification();

		$this->assertFalse($pp->guzzle_options['ssl.certificate_authority']);
	}

	public function testGuzzleClientSetsUpProxy()
	{
		$pp = new PP('http://example.com');
		$pp->set_proxy('http://proxyserver.com', 3128);

		$this->assertTrue($pp->guzzle_options['curl.options']['CURLOPT_HTTPPROXYTUNNEL']);
		$this->assertEquals('http://proxyserver.com:3128', $pp->guzzle_options['curl.options']['CURLOPT_PROXY']);
	}

	public function testcacheTurnsCacheOn()
	{
		$pp = new PP('http://example.com');
		$pp->with_cache('file');
		$pp->directory('/tmp');

		$this->assertEquals('file', $pp->cache);
		$this->assertEquals('/tmp', $pp->cache_directory);
	}

	/**
	 * @expectedException ProgrammesPlant\ProgrammesPlantException
     * @expectedExceptionMessage Directory does not exist.
	 */
	public function testExceptionThrownWhenCacheDirectoryDoesNotExist()
	{
		$pp = new PP('http://example.com');
		$pp->with_cache('file');
		$pp->directory('/star/wars');
	}

	/**
	 * @expectedException ProgrammesPlant\ProgrammesPlantException
	 * @expectedExceptionMessage hghjghghgh is not a supported cache type.
	 */
	public function testExceptionThrownWhenCacheIsOfAnUnknownType()
	{
		$pp = new PP('http://example.com');
		$pp->with_cache('hghjghghgh');
	}

	/**
	 * @expectedException ProgrammesPlant\ProgrammesPlantException
	 * @expectedExceptionMessage No cache directory set.
	 */
	public function testExceptionThrownWhenCacheDirectoryIsNotSetButWeHaveSetTypeToFile()
	{
		$pp = new PP('http://example.com');
		$pp->with_cache('file');

		$pp->guzzle_request('/users/');
	}

	/**
	 * We have two objects exposed publically:
	 *  - $cache_object - The instance of the cache object itself.
	 *  - $cache_plugin - The instance of the cache as a plugin for Guzzle.
	 * 
	 * We can use the latter to actually access data and ensure it is correctly cached.
	 */

	public function testRequestIsCachedWhenResponseIsCacheableWithMemoryCache()
	{
		$pp = new PP('http://example.com');
		$pp->with_cache('memory');
		$pp->prepare();

		// Simulate the events for the cache object.
		$request = new Request('GET', 'http://foo.com');
		$response = new Response(200, array(), '["Foo"]');

		// Fake the request.
		$pp->cache_plugin->onRequestBeforeSend(
			new Event(array(
            	'request' => $request
        	))
		);

		// Fake the response to that request.
        $pp->cache_plugin->onRequestSent(
        	new Event(array(
        		'request'  => $request,
            	'response' => $response
        	))
        );

        $data = $this->readAttribute($pp->cache_object, 'data');

        $this->assertNotEmpty($data, "Cache did not return any data on a request.");

        // Obtain the last thing put in the cache.
        $data = end($data);

        // Assert we are caching the response as we put it in.
        // [0] => HTTP Code, [1] => HTTP Headers, [2] => Body
        $this->assertEquals(200, $data[0]);
        $this->assertInternalType('array', $data[1]);
        $this->assertEquals('["Foo"]', $data[2]);
	}

	public function testRequestIsCachedWhenResponseIsCacheableWithFileCache()
	{
		$pp = new PP('http://example.com');
		$pp->with_cache('file')->directory(static::$cache_directory); 
		$pp->prepare();

		// Simulate the events for the cache object.
		$request = new Request('GET', 'http://foo.com');
		$response = new Response(200, array(), '["Foo"]');

		// Fake the request.
		$pp->cache_plugin->onRequestBeforeSend(
			new Event(array(
            	'request' => $request
        	))
		);

		// Fake the response to that request.
        $pp->cache_plugin->onRequestSent(
        	new Event(array(
        		'request'  => $request,
            	'response' => $response
        	))
        );

        $key_provider = new \Guzzle\Plugin\Cache\DefaultCacheKeyProvider();
		$data = $pp->cache_object->fetch($key_provider->getCacheKey($request));

        $this->assertNotEmpty($data, "Cache did not return any data on a request.");

        // Assert we are caching the response as we put it in.
        // [0] => HTTP Code, [1] => HTTP Headers, [2] => Body
        $this->assertEquals(200, $data[0]);
        $this->assertInternalType('array', $data[1]);
        $this->assertEquals('["Foo"]', $data[2]);
	}

	public function testEnsureNonJsonIsntCached(){
		$pp = new PP('http://example.com');
		$pp->with_cache('file')->directory(static::$cache_directory); 
		$pp->prepare();

		// Simulate the events for the cache object.
		$request = new Request('GET', 'http://foo.com');
		$response = new Response(200, array(), 'Foo');

		// Fake the request.
		$pp->cache_plugin->onRequestBeforeSend(
			new Event(array(
            	'request' => $request
        	))
		);

		// Fake the response to that request.
        $pp->cache_plugin->onRequestSent(
        	new Event(array(
        		'request'  => $request,
            	'response' => $response
        	))
        );

        $key_provider = new \Guzzle\Plugin\Cache\DefaultCacheKeyProvider();
		$data = $pp->cache_object->fetch($key_provider->getCacheKey($request));

        $this->assertEmpty($data, "Non json responses should not be cached.");
	}

	/**
	 * This uses our Node.js server to run actual tests against.
	 */

	public function testget_subjectMakesAHTTPRequest()
	{
		$server = $this->getServer();

        $pp = new PP($this->getServer()->getUrl());
 		
 		$payload = json_encode(array('test' => 'test'));
 		$size = strlen($payload);

 		// Enqueue the HTTP session.
 		$this->getServer()->enqueue(array(
            "HTTP/1.1 200 OK\r\n" .
            'Date: ' . Utils::getHttpDate('now') . "\r\n" .
            "Last-Modified: Mon, 12 Nov 2012 02:53:38 GMT\r\n" .
            "Content-Length: $size\r\n\r\n$payload"
        ));

 		$this->assertNotEmpty($pp->get_subject_index(2014, 'undergraduate'));
	}

	/**
	 * @expectedException ProgrammesPlant\ProgrammesPlantException
	 * @expectedExceptionMessage Response was not valid JSON and could not be decoded.
	 */
	public function testmake_requestThrowsExceptionWhenResponseIsNotJSON()
	{
		$server = $this->getServer();

		$pp = new PP($server->getUrl());

		$payload = 'This is not JSON';
		$size = strlen($payload);

		// Enqueue the HTTP session.
 		$server->enqueue(array(
            "HTTP/1.1 200 OK\r\n" .
            'Date: ' . Utils::getHttpDate('now') . "\r\n" .
            "Last-Modified: Mon, 12 Nov 2012 02:53:38 GMT\r\n" .
            "Content-Length: $size\r\n\r\n$payload"
        ));

        $pp->make_request('here');
	}

	/**
	 * Feeder of HTTP codes for test.
	 * 
	 * The format is response to send, expected exception, expected exception message.
	 */
	public function HTTPExceptionsTestDataProvider()
	{
		return(array(

			// 404
			array(
				"HTTP/1.1 404 Not Found\r\n=",
				'ProgrammesPlant\ProgrammesPlantNotFoundException',
				'thing/ not found, attempting to get http://127.0.0.1:8124/thing/'
			),

			// Other 40x series

			// 403
			array(
				"HTTP/1.1 403 Forbidden\r\n",
				"ProgrammesPlant\ProgrammesPlantRequestException",
				"Request failed for http://127.0.0.1:8124/thing/, error code 403"
			),

			// Another just for luck - 408
			array(
				"HTTP/1.1 408 Request Time Out\r\n",
				"ProgrammesPlant\ProgrammesPlantRequestException",
				"Request failed for http://127.0.0.1:8124/thing/, error code 408"
			),

			// 5xx series

			// 500
			array(
				"HTTP/1.1 500 Internal Server Error\r\n",
				'ProgrammesPlant\ProgrammesPlantRequestException',
				'Request failed for http://127.0.0.1:8124/thing/ - no cache.'
			),
		));
	}

	/**
	 * @dataProvider HTTPExceptionsTestDataProvider
	 */
	public function testguzzle_requestThrowsExceptionsOnFailureModes($response, $exception, $exception_message)
	{
		$server = $this->getServer();

		$pp = $this->setup_with_server();

		$server->enqueue($response);

		$this->setExpectedException($exception, $exception_message);
		
		$pp->guzzle_request('thing/');
	}

	/**
	 * Test for failure to resolve host.
	 * 
	 * @expectedException ProgrammesPlant\ProgrammesPlantServerNotFound
	 * @expectedExceptionMessage http://this.url.does.not.exist/forsure not found, DNS lookup failed - is this address correct?
	 */
	public function testguzzle_requestThrowsExceptionWhenServerIsDown()
	{
		$pp = new PP('http://this.url.does.not.exist/forsure/');

		$pp->guzzle_request('');
	}

	/**
	 * Cause some other random cURL error - a malformed URL.
	 * 
	 * @expectedException ProgrammesPlant\CurlException
	 * @expecetedException Request failed for this@not$$$URL, problem is with cuRL. cURL error 3
	 */
	public function testguzzle_requestThrowsExceptionOnUnknownCurlProblem()
	{
		$pp = new PP('this@not$$$URL');

		$pp->guzzle_request('');
	}

	/**
	 * Throw an error when we have a rubbish proxy.
	 * 
	 * @expectedException ProgrammesPlant\ProxyNotFound
	 * @expectedExceptionMessage Proxy server http://this.is.not.a.proxy.server.at.all could not be found, DNS lookup failed.
	 */
	public function testguzzle_requestThrowsExceptionOnCurlProxyProblem()
	{
		$pp = new PP('http://example.com');
		$pp->set_proxy('http://this.is.not.a.proxy.server.at.all');

		$pp->guzzle_request('');
	}

	public function testServesFromCacheWhenServerIsDownWithA5xxError() 
	{
		$server = $this->getServer();

		$data = array('thing' => 'thing');
		$payload = json_encode($data);

		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
            "Last-Modified: " . Utils::getHttpDate('-10 days') . "\r\n" .
            "Date: " . Utils::getHttpDate('-1 hour') . "\r\n" .
            "Cache-Control: must-revalidate \r\n" .
            'Content-Length: ' . strlen($payload) . "\r\n\r\n$payload",

            "HTTP/1.1 500 Internal Server Error\r\n" .
            "Date: " . Utils::getHttpDate('now') . "\r\n"
		));

		$pp = $this->setup_with_server();
		$pp->with_cache('file')->directory(static::$cache_directory);

		// This should put a request into the cache.
		$first_response = $pp->make_request('api/');

		$second_response = $pp->make_request('api/');

		$this->assertEquals($first_response, $second_response);

		$this->assertEquals(2, count($server->getReceivedRequests()));

		$this->assertEquals(500, $pp->request->getResponse()->getStatusCode());
	}

	public function testCachePolicyRespondsTo301Correctly()
	{
		$server = $this->getServer();

		$data = array('thing' => 'thing');
		$payload = json_encode($data);

		// Set it up to be last modified in the past.
		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
            "Last-Modified: " . Utils::getHttpDate('-100 hours') . "\r\n" .
            "Cache-Control: public, must-revalidate \r\n" .
            'Content-Length: ' . strlen($payload) . "\r\n\r\n$payload",

            "HTTP/1.1 304 NOT MODIFIED\r\n"
		));

		$pp = $this->setup_with_server();
		$pp->with_cache('file')->directory(static::$cache_directory);

		// This should put a request into the cache.
		$first_response = $pp->make_request('api/');

		// This should be from the cache.
		$second_response = $pp->make_request('api/');

		// This should use the cache, not HTTP so we should expect the server to be hit only twice.
		// First to get the initial payload, then to get a 304.
		$this->assertEquals(2, count($server->getReceivedRequests()));

		// Last request is actually served from the cache.
		$this->assertTrue($pp->request->getResponse()->hasHeader('X-Guzzle-Cache'));
	}

	public function testCacheServesWhenCacheControlPublicIsSet()
	{
		$server = $this->getServer();

		$pp = $this->setup_with_server();
		$pp->with_cache('file')->directory(static::$cache_directory);

		$data = array('thing' => 'thing');
		$payload = json_encode($data);

		// Set it up to be last modified in the past.
		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
            "Last-Modified: " . Utils::getHttpDate('-1 hours') . "\r\n" .
            'Cache-Control: public' . "\r\n" . 
            'Content-Length: ' . strlen($payload) . "\r\n\r\n$payload",

            "HTTP/1.1 200 OK\r\n" .
            'Cache-Control: public' . "\r\n" . 
            'Content-Length: ' . strlen($payload) . "\r\n\r\n$payload"
		));

		// This should put a request into the cache.
		$first_response = $pp->make_request('api/');

		// This should get the request from the cache.
		$second_response = $pp->make_request('api/');

		// This should use the cache, not HTTP so we should expect the server not to be hit.
		$this->assertEquals(1, count($server->getReceivedRequests()));

		// Last request is served from the cache.
		$this->assertTrue($pp->request->getResponse()->hasHeader('X-Guzzle-Cache'));
	}

	public function testCacheRefreshesWhenMaxAgeIsExceeded()
	{
		$server = $this->getServer();

		$pp = $this->setup_with_server();
		$pp->with_cache('file')->directory(static::$cache_directory);

		$data = array('thing' => 'thing');
		$payload = json_encode($data);

		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('-11 minutes') . "\r\n" .
			"Last-Modified: " . Utils::getHttpDate('-10 hours') . "\r\n" .
			"Cache-Control: max-age=600 \r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload",

			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('now') . "\r\n" .
			"Last-Modified: " . Utils::getHttpDate('-10 hours') . "\r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload"
		));

		// Put a request into the cache.
		$first_response = $pp->make_request('api/');

		$second_response = $pp->make_request('api/');

		$this->assertEquals(2, count($server->getReceivedRequests()));

		$this->assertFalse($pp->request->getResponse()->hasHeader('X-Guzzle-Cache'));
	}

	public function testCacheDoesNotRefreshWhenMaxAgeIsNotExceeded()
	{
		$server = $this->getServer();

		$pp = $this->setup_with_server();
		$pp->with_cache('file')->directory(static::$cache_directory);

		$data = array('thing' => 'thing');
		$payload = json_encode($data);

		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('-8 minutes') . "\r\n" .
			"Last-Modified: " . Utils::getHttpDate('-10 hours') . "\r\n" .
			"Cache-Control: max-age=600 \r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload",

			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('now') . "\r\n" .
			"Last-Modified: " . Utils::getHttpDate('-10 hours') . "\r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload"
		));

		// Put a request into the cache
		$first_response = $pp->make_request('api/');

		// This should get served out of the cache.
		$second_response = $pp->make_request('api/');

		$this->assertEquals(1, count($server->getReceivedRequests()));

		$this->assertTrue($pp->request->getResponse()->hasHeader('X-Guzzle-Cache'));
	}

	public function testCacheDoesNotRefreshWhenUsingHeadersWeUseInProgrammesPlant()
	{
		$server = $this->getServer();

		$pp = $this->setup_with_server();
		$pp->with_cache('file')->directory(static::$cache_directory);

		$data = array('thing' => 'thing');
		$payload = json_encode($data);

		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('now') . "\r\n" .
			"Last-Modified: " . Utils::getHttpDate('-10 hours') . "\r\n" .
			"Cache-Control: public, max-age=3600\r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload",

			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('now') . "\r\n" .
			"Last-Modified: " . Utils::getHttpDate('-10 hours') . "\r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload"
		));

		// Put a request into the cache
		$pp->make_request('api/');

		// This should get served out of the cache.
		$pp->make_request('api/');

		$this->assertEquals(1, count($server->getReceivedRequests()));

		$this->assertTrue($pp->request->getResponse()->hasHeader('X-Guzzle-Cache'));
	}

	public function testCacheDoesRefreshWhenUsingHeadersWeUseInProgrammesPlantIfExpired()
	{
		$server = $this->getServer();

		$pp = $this->setup_with_server();
		$pp->with_cache('file')->directory(static::$cache_directory);

		$data = array('thing' => 'thing');
		$payload = json_encode($data);

		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('-3601 seconds') . "\r\n" .
			"Last-Modified: " . Utils::getHttpDate('-10 hours') . "\r\n" .
			"Cache-Control: public, max-age=3600\r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload",

			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('now') . "\r\n" .
			"Last-Modified: " . Utils::getHttpDate('-10 hours') . "\r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload"
		));

		// Put a request into the cache
		$pp->make_request('api/');

		// This should get served out of the cache.
		$pp->make_request('api/');

		$this->assertEquals(2, count($server->getReceivedRequests()));

		$this->assertFalse($pp->request->getResponse()->hasHeader('X-Guzzle-Cache'));
	}

	public function testCacheRefreshesWhenModificationDateIsChangedAndCacheHasExpired()
	{
		$server = $this->getServer();

		$pp = $this->setup_with_server();
		$pp->with_cache('file')->directory(static::$cache_directory);

		$data = array('thing' => 'thing');
		$payload = json_encode($data);

		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('-1 hour') . "\r\n" . 
			"Last-Modified: " . Utils::getHttpDate('January 1 2011') . "\r\n" .
			"Cache-Control: max-age=600 \r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload",

			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('now') . "\r\n" .
			"Last-Modified: " . Utils::getHttpDate('January 14 2011') . "\r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n$payload"
		));

		$pp->make_request('api/');

		$pp->make_request('api/');

		$this->assertEquals(2, count($server->getReceivedRequests()));

		$this->assertFalse($pp->request->getResponse()->hasHeader('X-Guzzle-Cache'));
	}

	public function testserved_from_cache()
	{
		// Setup a response that would be served from cache.
		$server = $this->getServer();

		$data = array('thing' => 'thing');
		$payload = json_encode($data);

		// Set it up to be last modified in the past.
		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
            "Last-Modified: " . Utils::getHttpDate('-100 hours') . "\r\n" .
            "Cache-Control: public \r\n" .
            'Content-Length: ' . strlen($payload) . "\r\n\r\n$payload"
		));

		$pp = $this->setup_with_server();
		$pp->with_cache('file')->directory(static::$cache_directory);

		// This should put a request into the cache.
		$first_response = $pp->make_request('api/');

		// This should be from the cache.
		$second_response = $pp->make_request('api/');

		// Served from cache - one request!
		$this->assertEquals(1, count($server->getReceivedRequests()));

		$this->assertTrue($pp->served_from_cache());
	}

	private function setup_basic_payload()
	{
		$payload = json_encode(array('Thing' => 'Something'));

		$server = $this->getServer();

		$server->enqueue(array(
			"HTTP/1.1 200 OK\r\n" .
			"Date: " . Utils::getHttpDate('now') . "\r\n" . 
			"Last-Modified: " . Utils::getHttpDate('-1 hours') . "\r\n" .
			"Cache-Control: max-age=600 \r\n" .
			"Content-Length: " . strlen($payload) . "\r\n\r\n" . $payload
		));

	}

	public function testmake_requestReturnsObjectByDefault()
	{
		$this->setup_basic_payload();
		$pp = $this->setup_with_server();

		$this->assertTrue(is_object($pp->make_request('')));
	}

	public function testmake_requestReturnsArray()
	{
		$this->setup_basic_payload();
		$pp = $this->setup_with_server();

		$this->assertTrue(is_array($pp->make_request('', 'array')));
	}

	public function testmake_requestReturnsRawJSON()
	{
		$this->setup_basic_payload();
		$pp = $this->setup_with_server();

		$payload = json_decode($pp->make_request('', 'raw'));

		$this->assertNotNull($payload);
	}

	public function ReturnsMethodProvider()
	{
		return array(
			array('get_programme', array('year' => '2014', 'level' => 'undergraduate', 'id' => '123')),
			array('get_programme', array('year' => '2014', 'level' => 'postgraduate', 'id' => '123')),
			array('get_programmes_index', array('year' => '2014', 'level' => 'undergraduate')),
			array('get_programmes_index', array('year' => '2014', 'level' => 'postgraduate')),
			array('get_subject_index', array('year' => '2014', 'level' => 'undergraduate')),
			array('get_subject_index', array('year' => '2014', 'level' => 'postgraduate')),
			array('get_subjectcategories', array('level' => 'undergraduate')),
			array('get_subjectcategories', array('level' => 'postgraduate')),
			array('get_schools', array('single_pass' => true)),
			array('get_faculties', array('single_pass' => true)),
			array('get_campuses', array('single_pass' => true)),
			array('get_subject_leaflets', array('year' => '2014', 'level' => 'undergraduate')),
			array('get_subject_leaflets', array('year' => '2014', 'level' => 'postgraduate')),
			array('get_preview_programme', array('level' => 'undergraduate')),
			array('get_simpleview_programme', array('level' => 'undergraduate')),
			array('get_awards', array('level' => 'undergraduate')),
			array('get_awards', array('level' => 'postgraduate'))
		);
	}

	/**
	 * @dataProvider ReturnsMethodProvider
	 */
	public function testMethodsReturnObjectByDefault($method, $params)
	{
		$this->setup_basic_payload();
		$pp = $this->setup_with_server();

		$defaults = array('level' => false, 'year' => false, 'id' => false, 'single_pass' => false);
		$params = array_merge($defaults, $params);

		if ($method == 'get_preview_programme')
		{
			$payload = $pp->{$method}($params['level'], 111);
		}
		elseif ($method == 'get_simpleview_programme')
		{
			$payload = $pp->{$method}($params['level'], 111);
		}
		elseif ($params['single_pass'])
		{
			$payload = $pp->{$method}();
		}
		elseif ($params['year'] && $params['level'] && $params['id'])
		{
			$payload = $pp->{$method}($params['year'], $params['level'], $params['id']);
		}
		elseif($params['year'] && $params['level'])
		{
			$payload = $pp->{$method}($params['year'], $params['level']);
		}
		elseif($params['year']){
			$payload = $pp->{$method}($params['year']);
		}
		elseif($params['level']) 
		{
			$payload = $pp->{$method}($params['level']);
		}

		$this->assertTrue(is_object($payload));
	}

	/**
	 * @dataProvider ReturnsMethodProvider
	 */
	public function testMethodsReturnArray($method, $params)
	{
		$this->setup_basic_payload();
		$pp = $this->setup_with_server();
		
		$defaults = array('level' => false, 'year' => false, 'id' => false, 'single_pass' => false);
		$params = array_merge($defaults, $params);

		if ($method == 'get_preview_programme')
		{
			$payload = $pp->{$method}($params['level'], 111, 'array');
		}
		elseif ($method == 'get_simpleview_programme')
		{
			$payload = $pp->{$method}($params['level'], 111, 'array');
		}
		elseif ($params['single_pass'])
		{
			$payload = $pp->{$method}('array');
		}
		elseif ($params['year'] && $params['level'] && $params['id'])
		{
			$payload = $pp->{$method}($params['year'], $params['level'], $params['id'], 'array');
		}
		elseif($params['year'] && $params['level'])
		{
			$payload = $pp->{$method}($params['year'], $params['level'], 'array');
		}
		elseif($params['year']){
			$payload = $pp->{$method}($params['year'], 'array');
		}
		elseif($params['level']) 
		{
			$payload = $pp->{$method}($params['level'], 'array');
		}
		
		$this->assertTrue(is_array($payload));
	}

	/**
	 * @dataProvider ReturnsMethodProvider
	 */
	public function testMethodsReturnObject($method, $params)
	{
		$this->setup_basic_payload();
		$pp = $this->setup_with_server();
		
		$defaults = array('level' => false, 'year' => false, 'id' => false, 'single_pass' => false);
		$params = array_merge($defaults, $params);

		if ($method == 'get_preview_programme')
		{
			$payload = $pp->{$method}($params['level'], 111, 'object');
		}
		elseif ($method == 'get_simpleview_programme')
		{
			$payload = $pp->{$method}($params['level'], 111, 'object');
		}
		elseif ($params['single_pass'])
		{
			$payload = $pp->{$method}('object');
		}
		elseif ($params['year'] && $params['level'] && $params['id'])
		{
			$payload = $pp->{$method}($params['year'], $params['level'], $params['id'], 'object');
		}
		elseif($params['year'] && $params['level'])
		{
			$payload = $pp->{$method}($params['year'], $params['level'], 'object');
		}
		elseif($params['year']){
			$payload = $pp->{$method}($params['year'], 'object');
		}
		elseif($params['level']) 
		{
			$payload = $pp->{$method}($params['level'], 'object');
		}
		
		$this->assertTrue(is_object($payload));
	}

	/**
	 * @dataProvider ReturnsMethodProvider
	 */
	public function testMethodsReturnRawJSON($method, $params)
	{
		$this->setup_basic_payload();
		$pp = $this->setup_with_server();
		
		$defaults = array('level' => false, 'year' => false, 'id' => false, 'single_pass' => false);
		$params = array_merge($defaults, $params);

		if ($method == 'get_preview_programme')
		{
			$payload = $pp->{$method}($params['level'], 111, 'raw');
		}
		elseif ($method == 'get_simpleview_programme')
		{
			$payload = $pp->{$method}($params['level'], 111, 'raw');
		}
		elseif ($params['single_pass'])
		{
			$payload = $pp->{$method}('raw');
		}
		elseif ($params['year'] && $params['level'] && $params['id'])
		{
			$payload = $pp->{$method}($params['year'], $params['level'], $params['id'], 'raw');
		}
		elseif($params['year'] && $params['level'])
		{
			$payload = $pp->{$method}($params['year'], $params['level'], 'raw');
		}
		elseif($params['year']){
			$payload = $pp->{$method}($params['year'], 'raw');
		}
		elseif($params['level']) 
		{
			$payload = $pp->{$method}($params['level'], 'raw');
		}
		
		$this->assertNotNull(json_decode($payload));
	}

	public function testjson_decodeReturnsArray()
	{
		$pp = new PP('http://example.com');

		$json = json_encode(array('Test' => 'test'));

		$this->assertTrue(is_array($pp->json_decode($json, true)));
	}

	public function testjson_decodeReturnsObject()
	{
		$pp = new PP('http://example.com');

		$json = json_encode(array('Test' => 'test'));

		$this->assertTrue(is_object($pp->json_decode($json, false)));
	}

	public function testjson_decodeReturnsObjectByDefault()
	{
		$pp = new PP('http://example.com');

		$json = json_encode(array('Test' => 'test'));

		$this->assertTrue(is_object($pp->json_decode($json)));
	}

	/**
     * @expectedException ProgrammesPlant\JSONDecode
     */
	public function testjson_decodeThrowsExceptionOnInvalidJSON()
	{
		$pp = new PP('http://example.com');

		$pp->json_decode('We cannot decode invalid JSON');
	}

	function json_decodeErrorsProvider()
	{	
		return array(
			array(
				 mb_substr(json_encode(array('This' => 'That')), 0, -1),
				'Syntax error, malformed JSON.'
			),
		);
	}

	/**
	 * @dataProvider json_decodeErrorsProvider
	 */
	public function testjson_decodeThrowsExceptionWithCorrectJSONDecodeErrorMessage($string, $expected_message)
	{
		$pp = new PP('http://example.com');

		$this->setExpectedException('ProgrammesPlant\JSONDecode', 'We cannot decode invalid JSON,  json_decode reports: ' . $expected_message . "\nString: " . $string);

		$pp->json_decode($string);
	}

}