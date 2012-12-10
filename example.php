<?php
require_once 'src/ProgrammesPlant/ProgrammesPlant.php';

$api_url = 'http://xcri.dev/ws/2012/ug/';

$pp = new \ProgrammesPlant\ProgrammesPlant($api_url);

$programmes = $pp->get_programmes_index('ug', '2012');

foreach ($programmes as $programme)
{
	echo "$programme->title";
}