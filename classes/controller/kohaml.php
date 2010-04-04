<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Kohaml library to parse haml files
 *
 * @package        Kohaml
 * @author         Justin Hernandez <justin@transphorm.com>
 * @license        http://www.opensource.org/licenses/isc-license.txt
 */
class Controller_Kohaml extends Controller_Template
{
	// loads kohaml.haml
	public $template = "kohaml";

	public $kohaml = TRUE;

	//public $auto_render = FALSE;

	public function action_index()
	{
		$this->template->wow = new Haml('demo2');
		$this->template->hello = ' the hello variable';
	}


	public function action_sass()
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
