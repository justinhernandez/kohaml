<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Kohaml frontend. Interfaces with View and KohamlLib
 *
 * @package        Kohaml
 * @author         Justin Hernandez <justin@transphorm.com>
 * @license        http://www.opensource.org/licenses/isc-license.txt
 */
class Haml extends View
{
	// cached template file
	private $cache_file;
	// name of file to be parsed
	private $file;
	// skip if cache exists and is current
	private $skip = FALSE;
	// debug mode
	private $debug;


	/**
	 * Set debug from config file and handle passed file name
	 *
	 * @param  string  $name
	 */
	public function __construct($name = NULL, $data = NULL, $type = NULL)
	{
		// Attempt to autoload template if name is empty
		if (!$name) $name = $this->haml_autoload();

		$this->debug = Kohana::config('kohaml.debug');
		$this->handler($name);
		if (!$type) $type =  Kohana::config('kohaml.ext');

		// taken from view construct
		$this->set_filename($name, $type);

		if (is_array($data) AND ! empty($data))
		{
			// Preload data using array_merge, to allow user extensions
			$this->kohana_local_data = array_merge($this->kohana_local_data, $data);
		}

	}

	/**
	 * Main function. Handle tasks
	 *
	 * @param  string  $name
	 */
	private function handler($name)
	{
		$this->check_directory();
		$this->clean_cache();
		$this->load_file_path($name);
		$this->check_cache();
		$kohaml = new Kohaml($this->debug);
		// if skip cache is true
		if (!$this->skip || $this->debug)
		{
			// put file contents into an array then pass to render
			$output = $kohaml->compile(file($this->file), $name);
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
	 * Cache output from Kohaml.
	 *
	 * @param  string  $output
	 */
	private function cache($output)
	{
		// touch file. helps determine if template was modified
		touch($this->file);
		$name = Kohana::config('kohaml.cache_folder').'/'.md5($this->file).EXT;
		// add offset to cache file if using load() if not nested don't indent
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

	/**
	 * Sets the view filename.
	 *
	 * @chainable
	 * @param   string  view filename
	 * @param   string  view file type
	 * @return  object
	 */
	public function set_filename($name, $type = NULL)
	{
		if ($type == NULL)
		{
			// Load the filename and set the content type
			// Loads cached template
			if (Kohana::config('kohaml.on'))
			{
				$this->kohana_filename = $this->cache_file;
			}
			else
			{
				$this->kohana_filename = Kohana::find_file('views', $name, TRUE);
			}
			$this->kohana_filetype = EXT;
		}
		else
		{
			// Check if the filetype is allowed by the configuration
			if ( ! in_array($type, Kohana::config('view.allowed_filetypes')))
				throw new Kohana_Exception('core.invalid_filetype', $type);

			// Load the filename and set the content type
			if (Kohana::config('kohaml.on') && $type == Kohana::config('kohaml.ext'))
			{
				$this->kohana_filename = $this->cache_file;
			}
			else
			{
				$this->kohana_filename = Kohana::find_file('views', $name, TRUE, $type);
			}
			$this->kohana_filetype = Kohana::config('mimes.'.$type);

			if ($this->kohana_filetype == NULL)
			{
				// Use the specified type
				$this->kohana_filetype = $type;
			}
		}

		return $this;
	}

	/**
	 * Set autoload variables. Folder is the name of the controller class.
	 * File is the name of the calling function within the class.
	 */
	private function haml_autoload()
	{
		// find folder depth
		$path = preg_match('/.+\/controllers\/(.+)?\/.+$/', Router::$controller_path, $m);
		$folder = @$m[1];
		// add class if set in config
		if (Kohana::config('kohaml.controller_sub_folder'))
			$folder = '/'.str_replace('_controller', '', strtolower(Router::$controller));
		// set auto file
		$file = strtolower(Router::$method);

		return $folder.'/'.$file;
	}

}