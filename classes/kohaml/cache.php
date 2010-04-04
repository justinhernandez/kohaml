<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 * Kohaml Cache Library
 *
 * @package			Kohaml
 * @author			Justin Hernandez <justin@transphorm.com>
 * @copyright		2009
 */
class Kohaml_Cache
{
	// skip caching?
	private $skip;
	// file name
	private $file;
	// cache time
	private $cache_time;
	

	/**
	 * Set type, kohaml or kosass
	 *
	 * @param  string  $type
	 */
	public function __construct($type)
	{
		$this->type = $type;
		$this->cache_time = Kohana::config($this->type.'.cache_time');
	}

	/**
	 * Main function. Handle tasks
	 *
	 * @param  string  $file
	 */
	public function check($file)
	{
		$this->file = $file;
		// check for debug
		$this->check_directory();
		$this->clean_cache();
		
		return $this->check_cache();
	}
	
	/**
	 * Skip need for caching?
	 */
	public function skip()
	{
		return $this->skip;
	}
	
	/**
	 * Cache output from Kohaml.
	 *
	 * @param  string  $output
	 */
	public function cache($output)
	{
		$name = Kohana::config($this->type.'.cache_folder').'/'.md5($this->file).EXT;
		// add offset to cache file if using load() if not nested don't indent
		file_put_contents($name, $output);
		unset($output);
	}

	/**
	 * Check if cache directory exists. If not, create it.
	 */
	private function check_directory()
	{
		if (!is_dir(Kohana::config($this->type.'.cache_folder')))
			mkdir(Kohana::config($this->type.'.cache_folder'));
	}

	
	/**
	 * Check if a cached file exists. If it exists but is old then delete it.
	 */
	private function check_cache()
	{
		$cache_file = Kohana::config($this->type.'.cache_folder').'/'.md5($this->file).EXT;
		$this->skip = FALSE;
		
		if (is_file($cache_file))
		{
			// touch file. helps determine if template was modified
			touch($cache_file);
			// check if template has been mofilemtime($cache_file)dified and is newer than cache
			// allow $cache_time difference
			if ((filemtime($this->file)) > (filemtime($cache_file)+$this->cache_time))
				$this->skip = TRUE;
		}
		
		return $cache_file;
	}

	/**
	 * Delete old cached files based on cache time and cache gc probability set
	 * in the config file.
	 */
	private function clean_cache()
	{
		//gc probability
		$gc = rand(1, Kohana::config($this->type.'.cache_gc'));
		if ($gc != 1) return FALSE;
		$cache = new DirectoryIterator(Kohana::config($this->type.'.cache_folder'));
		while ($cache->valid())
		{
			// if file is past maximum cache settings delete file
			$cached = date('U', $cache->getMTime());
			$max = time() + Kohana::config($this->type.'.cache_clean_time');
			if ($cache->isFile() AND ($cached > $max))
			{
				unlink($cache->getPathname());
			}
			$cache->next();
		}
	}

}
