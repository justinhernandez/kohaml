<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Kohaml frontend. Interfaces with View and KohamlLib
 *
 * @package        Kohaml
 * @author         Justin Hernandez <justin@transphorm.com>
 * @version        1.0.2
 * @license        http://www.opensource.org/licenses/isc-license.txt
 */
class Kohaml_Haml extends View
{
	// cached template file
	public $cache_file;
	// skip if cache exists and is current
	private $skip = FALSE;
	// name of file to be parsed
	protected $_file;
	

	/**
	 * Set debug from config file and handle passed file name
	 *
	 * @param  string  $name
	 */
	public function __construct($name = NULL, $data = NULL, $type = NULL)
	{
		// Attempt to autoload template if name is empty
		if ( ! $name) $name = $this->haml_autoload();
		
		// load file
		$this->_file = $this->load_file_path($name);

		// load cache library
		$cache =  new Kohaml_Cache('kohaml');
		$this->cache_file = $cache->check($this->_file);
		$debug = Kohana::config('kohaml.debug');
		
		// if cache file does not exists then cache output from Kohaml
		 if ( ! $cache->skip() || $debug)
		{
			$kohaml = new Kohaml($debug);
			// put file contents into an array then pass to render
			$output = $kohaml->compile(file($this->_file), $name);
			// cache output
			if ( ! $debug) $cache->cache($output);
		}
		
		if ( ! $type) $type =  Kohana::config('kohaml.ext');

		// taken from view construct
		$this->set_filename($name, $type);

		if (is_array($data) AND ! empty($data))
			// Preload data using array_merge, to allow user extensions
			$this->kohana_local_data = array_merge($this->kohana_local_data, $data);
	}
		
	/**
	 * Find file path for given file name. E.g. demo.haml
	 *
	 * @param  string  $name
	 */
	private function load_file_path($name)
	{
		$type = Kohana::config('kohaml.ext');

		return Kohana::find_file('views', $name, $type);
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

			if (empty($this->kohana_filetype))
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
