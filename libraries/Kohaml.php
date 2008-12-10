<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Kohaml frontend. Interfaces with View and KohamlLib
 *
 * @package        Kohaml
 * @author         Justin Hernandez <justin@transphorm.com>
 * @license        http://www.opensource.org/licenses/isc-license.txt
 */
class Kohaml extends KohamlLib
{
	// cached template file
	public $cache_file;
	// name of file to be parsed
	private $file;
	// skip if cache exists and is current
	private $skip = FALSE;
	// is nested
	private $nested;
	// debug mode
	protected $debug;

	/**
	 * Set debug from config file and handle passed file name
	 *
	 * @param  string  $name
	 */
	public function __construct($name = NULL, $offset = 0, $nested = FALSE)
	{
		$this->debug = Kohana::config('kohaml.debug');
		$this->handler($name, $offset, $nested);
	}

	/**
	 * Helper for loading sub-views
	 *
	 * @param  string   $name
	 * @param  int      $offset
	 * @param  boolean  $nested
	 */
	public static function load($name, $offset, $nested)
	{
		// if nested the increase offset
		if ($nested) $offset += 2;
		$load = new Kohaml($name, $offset, $nested);
		print(file_get_contents($load->cache_file));
	}

	/**
	 * Main function. Handle tasks
	 *
	 * @param  string  $name
	 */
	private function handler($name, $offset, $nested)
	{
		$this->nested = $nested;
		$this->check_directory();
		$this->clean_cache();
		$this->load_file_path($name);
		$this->check_cache();
		// if skip cache is true
		if (!$this->skip || $this->debug)
		{
			// put file contents into an array then pass to render
			$output = $this->compile(file($this->file), $name, $offset, $nested);
			// cache output
			if (!$this->debug) $this->cache($output);
		}
		$base = basename($this->cache_file, EXT);
	}

	/**
	 * Check if cache directory exists. If not, create it.
	 */
	private function check_directory()
	{
		if (!is_dir(Kohana::config('kohaml.cache_folder')))
		{
			mkdir(Kohana::config('kohaml.cache_folder'));
		}
	}

	/**
	 * Find file path for given file name. E.g. demo.haml
	 *
	 * @param  string  $name
	 */
	private function load_file_path($name)
	{
		$type = Kohana::config('kohaml.ext');
		$raise = Kohana::config('kohaml.find_raise_error');
		$this->file = Kohana::find_file('views', $name, $raise, $type);
	}

	/**
	 * Check if a cached file exists. If it exists but is old then delete it.
	 */
	private function check_cache()
	{
		$this->cache_file = Kohana::config('kohaml.cache_folder').'/'.md5($this->file).EXT;
		if (is_file($this->cache_file))
		{
			$cache_time = Kohana::config('kohaml.cache_time');
			// check if template has been modified and is newer than cache
			// allow $cache_time difference
			if ((filemtime($this->file)) > (filemtime($this->cache_file)+$cache_time))
			{
				unlink($this->cache_file);
			}
			else
			{
				$this->skip = TRUE;
			}
		}
	}

	/**
	 * Cache output from KohamlLib.
	 *
	 * @param  string  $output
	 */
	private function cache($output)
	{
		// touch file. helps determine if template was modified
		touch($this->file);
		$name = Kohana::config('kohaml.cache_folder').'/'.md5($this->file).EXT;
		// add offset to cache file if using load() if not nested don't indent
		if ($this->offset & $this->nested)
		{
			$outdent = substr($this->offset, 2, strlen($this->offset));
			$output = "\n".$this->offset.$output."\n".$outdent;
		}
		file_put_contents($name, $output);
		unset($output);
	}

	/**
	 * Delete old cached files based on cache time and cache gc probability set
	 * in the config file.
	 */
	private function clean_cache()
	{
		//gc probability
		$gc = rand(1, Kohana::config('kohaml.cache_gc'));
		if ($gc != 1) return FALSE;
		$cache = new DirectoryIterator(Kohana::config('kohaml.cache_folder'));
		while ($cache->valid())
		{
			// if file is past maximum cache settings delete file
			$cached = date('U', $cache->getMTime());
			$max = time() + Kohana::config('kohaml.cache_clean_time');
			if ($cache->isFile() AND ($cached > $max))
			{
				unlink($cache->getPathname());
			}
			$cache->next();
		}
	}
}