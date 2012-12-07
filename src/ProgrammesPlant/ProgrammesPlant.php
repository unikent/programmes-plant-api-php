<?php 

namespace ProgrammesPlant;

class ProgrammesPlant 
{
	public $api_target = '';

	 public function __construct($api_target = false)
	 {
	 	if (! $api_target)
	 	{
			throw new ProgrammesPlantException("No Endpoint for Programmes Plant API specified");

	 	}

	 	$this->api_target = $api_target;
	 }
}

class ProgrammesPlantException extends \Exception {}