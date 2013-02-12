<?php

use \ProgrammesPlant\API as PP;

class ProgrammesPlantTest extends PHPUnit_Framework_TestCase
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
    	if (substr($directory,-1) == '/')
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
     */
	public function test__constructThrowsExpectionWhenTargetIsNotSpecified()
	{
		$pp = new PP();
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
	 */
	public function testExceptionThrownWhenCacheDirectoryDoesNotExist()
	{
		$pp = new PP('http://example.com');
		$pp->with_cache('file');
		$pp->directory('/star/wars');
	}

	/**
	 * @expectedException ProgrammesPlant\ProgrammesPlantException
	 */
	public function testExceptionThrownWhenCacheIsOfAnUnknownType()
	{
		$pp = new PP('http://example.com');
		$pp->with_cache('hghjghghgh');
	}

	/**
	 * @expectedException ProgrammesPlant\ProgrammesPlantException
	 */
	public function testExceptionThrownWhenCacheDirectoryIsNotSetButWeHaveSetTypeToFile()
	{
		$pp = new PP('http://example.com');
		$pp->with_cache('file');

		$pp->guzzle_request('/users/');
	}

}

