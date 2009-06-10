<?php
/**
 * Kosass library to parse sass files.
 *
 * @package        Kohaml
 * @author         Justin Hernandez <justin@transphorm.com>
 * @version        1.0.2
 * @license        http://www.opensource.org/licenses/isc-license.txt
 */
class Kosass_Core
{
	// Use Kosass without Kohana
	private $standalone = FALSE;
	// debug?
	private $debug;
	// nested elements
	private $nested = array();
	// current line #
	private $lineno;
	// indent for current row
	private $indent;
	// indent for previous row
	private $next_indent;
	// next line type
	private $next_type;
	// parsed line to return
	private $line;
	// file object
	private $file;
	// compiled template
	private $output;
	// script name for debugging
	private $script;
	// arithmetic operators
	private $operators = array('%', '\*|\/', '\-|\+');
	// arithmetic variable types
	private $arithmetic_types = array('string' => 1, 'integer' => 3, 'hex' => 5, 'percent' => 7);
	// sass constants
	private $constants = array();
	// skip current line
	private $skip = FALSE;
	// value to run arithmetic on
	private $value;
	// nested () value
	private $nested_value;
	

	/**
	 * Load initial settings. Make changes here if you are using Kohaml in
	 * standalone mode. Check out replace_rules() for adding your own custom rules.
	 */
	public function __construct($debug = FALSE)
	{
		// set debug
		$this->debug = $debug;
		// double or single quotes
		$quotes = "double";

		$this->quotes = ($this->standalone)
					  ? $quotes
					  : Kohana::config('kosass.quotes');
	}

	/**
	 * Main function. Parses sass and returns csds
	 * Construct, accepts an array usually from file('style.sass').
	 *
	 * @param  array   $contents
	 * @param  string  $script
	 * @return string
	 */
	public function compile($contents, $script = NULL)
	{
		// if $contents are empty throw error
		if (empty($contents))
			throw new Exception("Kosass template '$script' can not be found.");
		// set script name if in Kohana add the config's default extension
		$this->script = (!$this->standalone)
					  ? $script.'.'.Kohana::config('kohaml.ext')
					  : $script;
		// parse file contents into iterator
		$this->file = new ArrayIterator($contents);
		$this->output = '';
		while($this->file->valid())
		{
			// set current line information
			$this->line = $this->file->current();
			$this->lineno = $this->file->key();
			// add parsed info to template
			$this->parse_line();
			// check for debug
			if ($this->debug)
			{
				$this->debugo();
			}
			else
			{
				// add compiled line to output
				$this->output .= $this->line;
			}
			$this->clear_current();
			$this->file->next();
		}
		if ($this->debug) die();
		
		return trim($this->output);
	}

	/**
	 * Handles the parsing of each individual line
	 */
	private function parse_line()
	{
		// set indent
		$this->set_indent();
		$type = $this->line_type($this->line);
		// call function type
		$this->$type();
		
		// look ahead at next line to determine depth and close tags
		$this->look_ahead();
		// construct line
		$this->construct_line();
	}
	
	/**
	 * Get line type
	 *
	 * @param   param
	 * @return  return
	 */
	private function line_type($line, $debug = FALSE)
	{
		$first = substr(trim($line), 0, 1);
		
		// ignore comments and empty lines return false first to reduce overhead
		if ($first == '/')
		{
			$type = 'comment';
		}
		elseif (($first == '') OR (strlen(trim($line)) == 0))
		{
			$type = 'skip';
		}
		// check for attribute
		elseif (strpos($line, ':'))
		{
			$type = 'attribute';
		}
		// check if first letter is an id, class or element
		elseif ((in_array($first, array('#', '.'))) OR (preg_match('/[a-zA-Z]/', $first)))
		{
			$type = 'element';
		}
		// constant
		elseif ($first == '!')
		{
			$type = 'constant';
		}
		// mixin
		elseif ($first == '=')
		{
			$type = 'mixin';
		}
		// append
		elseif ($first == '&')
		{
			$type = 'append';
		}
		// retrieve mixin
		elseif ($first == '+')
		{
			$type = 'replace_mixin';
		}
		
		return $type;
	}
	
	/**
	 * Skip putting current line in output
	 */
	private function skip()
	{
		$this->skip = TRUE;
	}
	
	/**
	 * Handle comments
	 */
	private function comment()
	{
		$this->skip();
	}
	
	/*
	 * Adds constants to class array. Raise error if constant definition is indented
	 */
	private function constant()
	{
		preg_match('/!(\S+?)\s*=\s*(\S+)$/', $this->line, $m);
		$this->constants[trim($m[1])] = trim($m[2]);
		$this->skip = TRUE;
	}
	
	/**
	 * Handle elements
	 */
	private function element()
	{
		$this->nested[] = trim($this->line);
		$this->line = trim($this->line)." {\n";
	}
	
	
	/**  ATTRIBUTE | VALUE | ARITHMETIC METHODS **/
	
	
	/**
	 * Handle attribute and check for constants
	 */
	private function attribute()
	{
		preg_match('/:?([\w-]+?) *[ |:|=] *(.+)$/', $this->line, $m);
		// check value for constant
		$value = $this->parse_value($m[2]);
		
		$this->line = $this->indent.$m[1].': '.$value.';';
	}
	
	/**
	 * Parse value from 
	 *
	 * @param   param
	 * @return  return
	 */
	private function parse_value($value)
	{
		// if equal sign not present no need to check for constants
		if (($this->skip) OR ( ! strpos($this->line, '=')))
			return $value;
		
		// set class variable value
		$this->value = $value;
		// replace constants
		$this->replace_constants();
		// set for nested after
		$this->nested_value = $this->value;
		// check for nested () and process
		while (strpos($this->value, '(') !== FALSE)
		{
			$this->process_nested();
			// update nested value
			$this->nested_value = $this->value;
		}
		
		// search for arithmetic
		if (preg_match('/%|\*|\/|\+|\-/', $this->value))
		{
			$this->value = $this->arithmetic($this->value);
		}
		
		return $this->value;
	}
	
	/**
	 * Replace !constants
	 *
	 * @param   param
	 * @return  return
	 */
	private function replace_constants()
	{
		// replace all the constants
		preg_match_all('/!(\w+)/', $this->value, $c);
		// compile replacements
		foreach ($c[1] as $constant)
		{
			// add rule for find and replace then do it
			$find[] = '!'.$constant;
			$replace[] = $this->constants[$constant];
			$this->value = str_replace($find, $replace, $this->value);
		}
	}
	
	/**
	 * Find and process nested ()
	 *
	 * @param   param
	 * @return  return
	 */
	private function process_nested()
	{
		while (preg_match('/\((.+)\)/', $this->nested_value, $m))
		{
			if (@$m[1])
			{
				$this->nested_value = $m[1];
			}
			else
			{
				$this->raise_error('matching_parentheses');
			}
		}
		
		// update parentheses arithmetic with computed value
		$this->value = str_replace('('.$this->nested_value.')', $this->arithmetic($this->nested_value), $this->value);
	}
	
	/**
	 * Arithmetic handler
	 *
	 * @param   mixed   $value
	 * @return  mixed
	 */
	private function arithmetic($value)
	{
		// trim value
		$value = trim($value);
		// order of operation %, *, /, +, -
		foreach ($this->operators as $operator)
		{	
			// parse aritmetic string
			while (preg_match("/(#?[\.\w\d]+%?)\s*($operator)\s*(#?[\.\w\d]+%?)/", $value, $m))
			{
				// operator is found then run arithmetic logic and update value string
				if ( ! empty($m))
				{
					// replace arithmetic with computed value and update
					$value = str_replace($m[0], $this->arithmetic_logic($m), $value);
				}
			}
		}
		
		return $value;
	}
	
	/**
	 * Handle arithmetic logic
	 *
	 * @param   param
	 * @return  return
	 */
	private function arithmetic_logic($m)
	{
		$append = '';
		// find variable types and add results
		$type = $this->arithmetic_types[$this->variable_type($m[1])] * $this->arithmetic_types[$this->variable_type($m[3])];
		
		switch ($type)
		{
			// one integer, one percent
			case '21';
			// two percents
			case '49':
				$m[1] = str_replace('%', '', $m[1]);
				$m[3] = str_replace('%', '', $m[3]);
				$append = '%';
			// two integers, evaluate and return value
			case '9':
				$value = eval("return floatval($m[1]) $m[2] floatval($m[3]);");
				$value = round($value, 3);
			break;
			// hex and integer
			case '15':
				// check if first value is hex
				if (substr($m[1], 0, 1) == '#')
				{
					
					$color = $m[1];
					$numeric = $m[3];
				}
				else
				{
					$color = $m[3];
					$numeric = $m[1];
				}
				
				$value = $this->hex_piecewise($color, $numeric, $m[2]);
			break;
			// two hex values
			case '25':
				$value = $this->hex($m[1], $m[3], $m[2]);
			break;
			// either two strings, a string and an integer, or a hex and a string. 
			// Contencate and ignore operator
			default:
				$value = $m[1].$m[3];
		}
		
		return $value.$append;
	}
	
	/**
	 * Thanks groobo. Taken from http://us2.php.net/manual/en/function.hexdec.php#49866.
	 * Adds or subtracts hex colors
	 *
	 * @param   param
	 * @return  return
	 */
	public function hex($orig_color, $mod_color, $mod)
	{
		$orig_color = $this->hex_clean($orig_color);
		$mod_color = $this->hex_clean($mod_color);
	
		preg_match("/([0-9]|[A-F]){6}/i",$orig_color,$orig_arr);
		preg_match("/([0-9]|[A-F]){6}/i",$mod_color,$mod_arr);
		$ret = '';
		if ($orig_arr[0] AND $mod_arr[0]) 
		{
		    for ($i=0; $i<6; $i=$i+2) 
		    {
		        $orig_x = substr($orig_arr[0],$i,2);
		        $mod_x = substr($mod_arr[0],$i,2);
		        if ($mod == '+') 
		        { 
		        	$new_x = hexdec($orig_x) + hexdec($mod_x); 
		        }
		        else 
		        { 
		        	$new_x = hexdec($orig_x) - hexdec($mod_x); 
		        }
		        
		        if ($new_x < 0) 
		        { 
		        	$new_x = 0; 
		        }
		        else if ($new_x > 255) 
		        { 
		        	$new_x = 255; 
		        }
		        $new_x = dechex($new_x);
		        $ret .= $new_x;
		    }
		    
		    if ( ! $ret) $ret = 'ffffff';
		    
		    return '#'.$ret;
		}
	}
	
	/**
	 * Takes a hex color, converts it to rgb, computes piecewise then returns hex
	 *
	 * @param   string   $color
	 * @param   integer  $numeric
	 * @param	string   $modifier
	 * @return  string
	 */
	function hex_piecewise($color, $numeric, $modifier)
	{
		$color = $this->hex_clean($color);

		list($r, $g, $b) = array($color[0].$color[1],
		                         $color[2].$color[3],
		                         $color[4].$color[5]);

		$r = eval("return hexdec($r) $modifier $numeric;");
		$g = eval("return hexdec($g) $modifier $numeric;");
		$b = eval("return hexdec($b) $modifier $numeric;");
		
		
		/* Convert rgb back to hex */	
		
		if (is_array($r) && sizeof($r) == 3)
		{
		    list($r, $g, $b) = $r;
		}

		$r = intval($r);
		$g = intval($g);
		$b = intval($b);

		$r = dechex($r<0?0:($r>255?255:$r));
		$g = dechex($g<0?0:($g>255?255:$g));
		$b = dechex($b<0?0:($b>255?255:$b));

		$color = (strlen($r) < 2?'0':'').$r;
		$color .= (strlen($g) < 2?'0':'').$g;
		$color .= (strlen($b) < 2?'0':'').$b;
		
		return '#'.$color;
	}
	
	/**
	 * Clean # from hex number and lengthen if necessary
	 *
	 * @param   string   $color
	 * @return  string
	 */
	public function hex_clean($color)
	{
		$color = str_replace('#', '', $color);
		$length = strlen($color);
		if ($length == 6)
			return $color;
	
		$append = '';
		switch ($length)
		{
			case '3':
				$prepend = '';
			break;
			case '4':
				$prepend = substr($color, 0, 2);
				$color = substr($color, 2, 2);
			break;
			case '5':
				$prepend = $color.substr($color, 4, 1);
				$color = '';
			break;
			default:
				$this->raise_error('invalid_hex');
		}
		
		// iterate over color and construct append
		$iterate = strlen($color);
		for($a=0; $a<$iterate; $a++)
			$append .= $color[$a].$color[$a];
	
		return $prepend.$append;
	}
	
	/**
	 * Return variable type
	 *
	 * @param   mixed   $variable
	 * @return  string  
	 */
	private function variable_type($i)
	{
		if ($i[0] == '#')
		{
			return 'hex';
		}
		elseif (strpos($i, '%'))
		{
			return 'percent';
		}
		elseif (is_numeric($i))
		{
			return 'integer';
		}
		else
		{
			return 'string';
		}
	}
	
	/**
	 * Comments.
	 *
	 * @param   param
	 * @return  return
	 */
	private function mixin()
	{
		
	}

	/**
	 * Rebuilds line after parsing
	 */
	private function construct_line()
	{
		if ($this->skip)
		{
			$this->line = '';
			return FALSE;
		}
		
		if (in_array($this->next_type, array('skip', 'element', 'append')))
			$this->close();
		
		// add newline if none present
		$this->add_new_line();
	}

	/**
	 * Add a new line to the current line if no new line is present
	 */
	private function add_new_line()
	{
		if (!(substr($this->line, -1) == "\n")) $this->line .= "\n";
	}

	/*
	 * Close tags
	 */
	private function close()
	{
		$this->line = $this->line." }\n";
	}

	/**
	 * Set the indent for each line.
	 */
	private function set_indent()
	{
		preg_match('/^([ \t]+)?/', $this->line, $m);
		// if indent is false set to 0
		$this->indent = @$m[1];
		// check the indent level
		$this->check_indent(strlen($this->indent));
	}

	/*
	 * Look ahead to next line to determine depth and closing tags.
	 * Skips lines of php code (lines that begin with <?)
	 */
	private function look_ahead()
	{
		$next = $this->lineno + 1;
		$count = $this->file->count();
		// get line type for next line if comment keep iterating
		while (($this->next_type == 'comment') OR ($this->next_type == FALSE))
		{
			$this->next_type = $this->line_type(@$this->file->offsetGet($next), TRUE);
			$next++;
		}
		// get next indent
		@preg_match('/^([ \t]+)/', $this->file->offsetGet($next), $m);
		$this->next_indent = @$m[1];

		// check nesting indentation
		$next_indent = strlen($this->next_indent);
		$curr_indent = strlen($this->indent);
		// if next doesn't equal zero
		if (($next_indent != 0) AND ($next_indent > $curr_indent) AND ($next_indent > $curr_indent+2))
		{
			// next line has error
			$line = $this->lineno+2;
			throw new Exception("Incorrect nesting indentation in '$this->script' on line #$line.");
		}

		// check indentation level
		$this->check_indent(strlen($this->next_indent), $next);
	}

	/**
	 * Add closing tag to class array close_tags
	 *
	 * @param  string  $tag
	 */
	private function add_close($tag)
	{
		if (!$this->close_self) $this->close_tags[] = $this->indent.$tag;
	}

	/**
	 * Clean attribrutes for insert into tag.
	 *
	 * @param   string  $input
	 * @return  string
	 */
	private function clean_attr($input)
	{
		$remove = array('\'', '"');
		return str_replace($remove, '', trim($input));
	}

	/**
	 * Check indent for current line. Raises error for invalid indentation.
	 */
	private function check_indent($indent, $line = NULL)
	{
		$line = ($line) ? $line+1 : $this->lineno+1;
		if (($indent % 2) != 0)
		{
			$length = strlen($this->indent);
			throw new Exception("Incorrect indentation in '$this->script' on line #$line.");
		}
	}

	/**
	 * Clears class variables and gets ready for next line.
	 */
	private function clear_current()
	{
		$this->line = '';
		$this->skip = FALSE;
		$this->indent = '';
		$this->next_type = FALSE;
	}
	
	/**
	 * Flattens output for production mode
	 */
	private function flatten()
	{
		
	}
	
	/**
	 * Raise error and return an intelligent error message
	 *
	 * @param   string   $lang
	 */
		private function raise_error($lang)
		{
			$line = $this->lineno+1;
			throw new Kohana_Exception("kosass.$lang", " Error on line #{$line}.");
		}

	/**
	 * Debug output
	 */
	private function debugo()
	{
		$line = $this->lineno+1;
		$replace = array(" ", "\n");
		$fill = array('&nbsp;&nbsp;','<br/>');
		echo(str_replace($replace, $fill, htmlentities($this->line)));
	}
}
