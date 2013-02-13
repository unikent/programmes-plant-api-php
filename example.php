<?php
require_once dirname(__FILE__) . '/vendor/autoload.php';

$api_url = 'http://programmes-plant.dev/api/';

$pp = new ProgrammesPlant\API($api_url);

echo "Programmes Index" . PHP_EOL;
echo '----------------' . PHP_EOL;

// Get the index of programmes
$programmes = $pp->get_programmes_index('2014', 'ug');

echo 'In total there are ' . count((array) $programmes) . ' programmes' . PHP_EOL;

echo PHP_EOL . "Single Programme" . PHP_EOL;
echo '----------------' . PHP_EOL;

// Get a single programme
$programme = $pp->get_programme('2014', 'ug', 1);

echo 'Got ' . $programme->programme_title . PHP_EOL;

unset($programme, $pp);

/**
 * Caching functionality.
 */

echo PHP_EOL . "Disk cache" . PHP_EOL;
echo '------------' . PHP_EOL;

$pp = new ProgrammesPlant\API($api_url);
$pp->with_cache('file')->directory('/tmp/cache');

// Get a single programme.
$programme = $pp->get_programme('2014', 'ug', 1);

echo 'Managed to get ' . $programme->programme_title . PHP_EOL;

// We should now be able to get this direct from the cache without making a request.
use Guzzle\Http\Message\Request;

// Work out where it is stored in the cache.
$request = new Request('GET', $api_url . '2014/ug/programmes/1');
$key_provider = new \Guzzle\Plugin\Cache\DefaultCacheKeyProvider();
$key  = $key_provider->getCacheKey($request);

$data = $pp->cache_object->fetch($key);

if (json_decode($data[2]) == $programme)
{
	echo 'Cache is working! Got ' . $programme->programme_title . ' from disk!' . PHP_EOL;
}

