<?php defined('SYSPATH') or die('No direct script access.');


return array
(
	/**
	* Turn Kohaml on?
	*
	* DEFAULT: TRUE
	*/
	'on' => TRUE,

	/**
	* Debug mode?
	*
	* DEFAULT: FALSE
	*/
	'debug' => FALSE,

	/**
	* Use controller name as a sub-folder
	*
	* DEFAULT: TRUE
	*/
	'controller_sub_folder' => TRUE,

	/**
	* Single or double quotes for wrapping attribute values
	*
	* DEFAULT: 'double'
	*/
	'quotes' => 'double',

	/**
	* Haml extension. Haml templates must reside within a views folder
	*
	* DEFAULT: haml
	*/
	'ext' => 'haml',

	/**
	* Cache folder for compiled templates.
	*
	* DEFAULT: APPPATH.'/cache/kohaml'
	*/
	'cache_folder' => APPPATH.'cache/kohaml',

	/**
	* Number of seconds between cache and template. If template modified time is
	* more than # seconds different from cache, regenerator cache.
	*
	* DEFAULT: 5
	*/
	'cache_time' => 5,

	/**
	* How long to keep cached templates. Default is one month. Good for removing
	* view templates that are no longer being used.
	*
	* DEFAULT: 2592000
	*/
	'cache_clean_time' => 2592000,

	/**
	* Define gc probability. Default is 30. So 1/30 is a ~3% of gc being run.
	*
	* DEFAULT: 30
	*/
	'cache_gc' => 30,
);