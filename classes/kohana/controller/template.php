<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Abstract controller class for automatic templating.
 * MODIFIED for use with Kohaml
 *
 * @package    Controller
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */
abstract class Kohana_Controller_Template extends Controller {

	/**
	 * @var  string  page template
	 */
	public $template = 'template';

	/**
	 * @var  boolean  auto render template
	 **/
	public $auto_render = TRUE;

	/**
	 * @var  boolean  used to turn kohaml on and off per controller
	 **/
	public $kohaml = TRUE;

	/**
	 * Loads the template View object.
	 *
	 * @return  void
	 */
	public function before()
	{
		// Load the template
		$this->template = (Kohana::config('kohaml.on') && $this->kohaml)
						? new Haml($this->template)
						: new View($this->template);

		if ($this->auto_render === TRUE)
		{
			// Load the template
			$this->template = View::factory($this->template);
		}
	}

	/**
	 * Assigns the template as the request response.
	 *
	 * @param   string   request method
	 * @return  void
	 */
	public function after()
	{
		if ($this->auto_render === TRUE)
		{
			$this->request->response = $this->template;
		}
	}

} // End Controller_Template