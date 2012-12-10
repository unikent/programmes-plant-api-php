<?php
require_once 'src/ProgrammesPlant/ProgrammesPlant.php';

$api_url = 'http://xcri.dev/ws/';

$pp = new \ProgrammesPlant\ProgrammesPlant($api_url);

// Get the index of programmes
$programmes = $pp->get_programmes_index('2014', 'ug');

$id = false;

foreach ($programmes as $programme)
{
	echo "$programme->name - https://xcri.dev/ug/programmes/edit/$programme->id\n";

	// Save an ID to use in a moment.
	if (! $id) 
	{
		$id = $programme->id;
	}
}

// Get a single programme
$programme = $pp->get_programme('2014', 'ug', $id);

if (! $pp->last_response)
{
	echo "Could not get programme with ID of $id, error was:";
	$pp->print_errors();
	exit(1);
}

echo "Got $id - its called $programme->name";