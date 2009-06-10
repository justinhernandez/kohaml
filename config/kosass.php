<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Debug mode?
 *
 * DEFAULT: FALSE
 */
$config['debug'] = FALSE;

/**
 * Base folder for sass files
 *
 * DEFAULT: views
 */
$config['base_folder'] = 'views';

/**
 * Sub folder for sass files
 *
 * DEFAULT: sass
 */
$config['sub_folder'] = 'sass';

/**
 * Sass cache folder
 *
 * DEFAULT: APPPATH.'cache/sass'
 */
$config['cache_folder'] = APPPATH.'cache/kosass';

/**
 * Web accessible css folder
 *
 * DEFAULT: DOCROOT.'media/css'
 */
$config['output_folder'] = DOCROOT.'media/css';

/**
 * Single or double quotes for wrapping attribute values
 *
 * DEFAULT: 'double'
 */
$config['quotes'] = 'double';

/**
 * Default sass extension to look for
 *
 * DEFAULT: haml
 */
$config['ext'] = 'sass';

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
