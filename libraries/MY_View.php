<?php defined('SYSPATH') or die('No direct script access.');

class View extends View_Core
{
	// cached file name
	private $cached;

	/**
	 * Same as View_Core::__construct() but checks for template
	 */
	public function __construct($name = NULL, $data = NULL, $type = NULL)
	{
		// check and compile template if needed
		if (Kohana::config('kohaml.on'))
		{
			$kohaml = new Kohaml($name);
			$this->cached = $kohaml->cache_file;
		}
		parent::__construct($name, $data, $type);
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
				$this->kohana_filename = $this->cached;
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
			$this->kohana_filename = Kohana::find_file('views', $name, TRUE, $type);
			$this->kohana_filetype = Kohana::config('mimes.'.$type);

			if ($this->kohana_filetype == NULL)
			{
				// Use the specified type
				$this->kohana_filetype = $type;
			}
		}

		return $this;
	}
}
