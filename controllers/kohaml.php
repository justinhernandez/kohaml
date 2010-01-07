<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Kohaml library to parse haml files
 *
 * @package        Kohaml
 * @author         Justin Hernandez <justin@transphorm.com>
 * @license        http://www.opensource.org/licenses/isc-license.txt
 */
class Kohaml_Controller extends Template_Controller
{
	// loads kohaml.haml
	public $template = "kohaml";

	// Do not allow to run in production
	const ALLOW_PRODUCTION = FALSE;

	public function __construct()
	{
		parent::__construct(); // necessary
	}

	public function index()
	{
		$this->template->wow = new Haml('demo2');
		$this->template->hello = ' the hello variable';
	}


	public function sass()
	{		
		header("Content-type: text/css");
		header("Pragma: public");
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");		
		
		# output directly to battle-test this sucka
		echo sass::stylesheet('advanced', 'nested');
		die();
	}
	

}
