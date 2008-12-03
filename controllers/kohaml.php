<?php defined('SYSPATH') or die('No direct script access.');

class Kohaml_Controller extends Template_Controller
{
	// loads kohaml.haml
	public $template = "kohaml";

	public function __construct()
	{
		parent::__construct(); // necessary
	}

	public function index()
	{
		$this->template->wow = new View('demo2');
		$this->template->hello = ' the hello variable';
	}
}