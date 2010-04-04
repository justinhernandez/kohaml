<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Sass helper.
 *
 * @package			Kohaml
 * @version			1.1
 * @author			Justin Hernandez <justin@transphorm.com>
 * @copyright		2010
 */
class Kohaml_Sass
{

	// files array
	private static $files = array();
	// cache files array
	private static $cache_files = array();
	// compiled ouput
	private static $output = array();
	// cache directory
	private static $cache_folder;

	/**
	 * Render sass files.
	 *
	 * @param   mixed    $files
	 * @param   boolean  $production
	 * @param   boolean  $inline
	 * @return  string
	 */
	public static function stylesheet($files, $style=NULL)
	{
		return self::handler($files, $style);
	}
	
	/**
	 * Handler
	 *
	 * @param   mixed    $files
	 * @param   boolean  $style of the output
	 * @return  string
	 */
	private static function handler($files, $style)
	{
		// set variables
		self::$cache_folder = Kohana::config('kosass.cache_folder');
		
		// if files is not an array convert to one
		if ( ! is_array($files)) $files = array($files);
		
		// set files 
		self::$files = $files;
		
		// add absolute path to file names
		self::get_files_path();
		
		// load cache library
		$cache =  new Kohaml_Cache('kosass');
		// check for debug
		$debug = FALSE; //Kohana::config('kosass.debug');
		// init Kosass
		$kosass = new Kosass($style, $debug);
		
		// loop files
		foreach (self::$files as $file)
		{
			// add cache file name
			self::$cache_files[] = $cache->check($file);
			$name = basename($file, '.'.Kohana::config('kosass.ext'));
			// if cache file does not exists then cache output from Kohaml
			 if ( ! $cache->skip() || $debug)
			{
				// put file contents into an array then pass to render
				$output = $kosass->compile(file($file), $name);
				// cache output
				if ( ! $debug) $cache->cache($output);
			}
		}
		
		// destroy static variables
		self::destruct();
	}
	
	/**
	 * Add absolute paths to sass files
	 */
	private static function get_files_path()
	{
		$base = Kohana::config('kosass.base_folder');
		$sub = Kohana::config('kosass.sub_folder');
		// find each sass file
		foreach (self::$files as &$file)
				$file = Kohana::find_file($base, $sub.'/'.$file, TRUE, 'sass');
	}
	
	/**
	 * Static destroyer - watchout be afraid!!!! LOL man I crack myself up!
	 *
	 * @param   param
	 * @return  return
	 */
	private static function destruct()
	{
		
	}

}
