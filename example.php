<?php
require_once dirname(__FILE__) . '/vendor/autoload.php';

$api_url = 'http://programmes-plant.dev/api/';

$pp = new ProgrammesPlant\API($api_url);

// Get the index of programmes
$programmes = $pp->get_programmes_index('2014', 'ug');

$id = false;

foreach ($programmes as $programme)
{
	echo "$programme->name\n";
}

// Get a single programme
$programme = $pp->get_programme('2014', 'ug', 1);

var_dump($programme);

/**
 * Caching functionality.
 */

// Disk cache
$pp = new ProgrammesPlant\API($api_url);
$pp->with_cache('file')->directory('/tmp/cache');

// Get a single programme.
$programme = $pp->get_programme('2014', 'ug', 1);

var_dump($programme);

// This next time it should come from the cache.
$programme = $pp->get_programme('2014', 'ug', 1);



