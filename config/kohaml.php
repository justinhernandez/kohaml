<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Turn Kohaml on?
 *
 * DEFAULT: TRUE
 */
$config['on'] = TRUE;

/**
 * Debug mode?
 *
 * DEFAULT: FALSE
 */
$config['debug'] = FALSE;

/**
 * Haml extension. Haml templates must reside within a views folder
 *
 * DEFAULT: haml
 */
$config['ext'] = 'haml';

/**
 * Cache folder for compiled templates.
 *
 * DEFAULT: APPPATH.'/cache/kohaml'
 */
$config['cache_folder'] = APPPATH.'cache/kohaml';

/**
 * Number of seconds between cache and template. If template modified time is
 * more than # seconds different from cache, regenerator cache.
 *
 * DEFAULT: 5
 */
$config['cache_time'] = 5;

/**
 * How long to keep cached templates. Default is one month. Good for removing
 * view templates that are no longer being used.
 *
 * DEFAULT: 2592000
 */
$config['cache_clean_time'] = 2592000;

/**
 * Define gc probability. Default is 30. So 1/30 is a ~3% of gc being run.
 *
 * DEFAULT: 30
 */
$config['cache_gc'] = 30;