<?php
require_once dirname(dirname(__FILE__)) . '/src/ProgrammesPlant/ProgrammesPlant.php';

use \Mockery as m;
use \ProgrammesPlant\ProgrammesPlant as PP;

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

	/**
     * @expectedException ProgrammesPlant\ProgrammesPlantException
     */
	public function test__constructThrowsExpectionWhenTargetIsNotSpecified()
	{
		$pp = new PP();
	}
}

