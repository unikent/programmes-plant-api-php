# Programmes Plant API
A PHP library for interacting with the [Programmes Plant](http://github.com/unikent/programmes-plant) REST API.

[![Build Status - master](https://travis-ci.org/unikent/programmes-plant-api-php.png?branch=master)](https://travis-ci.org/unikent/programmes-plant-api-php)

## Usage
Please refer to the current [brand guidelines](https://www.kent.ac.uk/brand) for use of the existing brand.

## Installing For Development

Dependencies are installed using [Composer](http://getcomposer.org/). Once installed run `composer.phar install --dev` (or `composer` dependending on where Composer is installed).

You will need Node.js to run the tests which boot a simple HTTP server for simulation of various HTTP responses.

## Installing For Production

Run `composer install` before usage.

## Tests

Providing the development installation is complete, run `bin/phpunit`.
