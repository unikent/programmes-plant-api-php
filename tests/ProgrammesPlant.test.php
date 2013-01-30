<?php

use \Mockery as m;
use \ProgrammesPlant\API as PP;

class ProgrammesPlantTest extends PHPUnit_Framework_TestCase
{
	public function tearDown()
	{
		m::close();
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

}

