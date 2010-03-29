<?php
/**
 * Kosass library to parse sass files.
 *
 * @package        Kohaml
 * @author         Justin Hernandez <justin@transphorm.com>
 * @version        1.1
 * @license        http://www.opensource.org/licenses/isc-license.txt
 */
class Kohaml_Kosass
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
	// sass mixins
	private $mixins = array();
	// skip current line
	private $skip = FALSE;
	// value to run arithmetic on
	private $value;
	// nested () value
	private $nested_value;
	// lets us know if we are working with lines in a mixin.
	private $in_mixin = FALSE;
	
	/**
	 * Load initial settings. Make changes here if you are using Kohaml in
	 * standalone mode. Check out replace_rules() for adding your own custom rules.
	 */
	public function __construct($style='nested', $debug = FALSE)
	{
		// set debug
		$this->debug = $debug;
		# set output style
		$this->style = $style;
		
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
		
		$this->file = $contents;
		
		# First parse original content to record constants and mixin definitions.
		$parsed_contents = $this->record_vars($contents);
	
		# Next inject any mixin references as arrays.
		$this->inject_mixins($parsed_contents);
		
		# expand the updated contents to a flat array
			# thanks : http://stackoverflow.com/questions/526556
		$objTmp = (object) array('aFlat' => array());
		array_walk_recursive($this->file, create_function('&$v, $k, &$t', '$t->aFlat[] = $v;'), $objTmp);
		$this->file = $objTmp->aFlat;
		
		# echo kohana::debug($this->file); 
		# echo kohana::debug($this->mixins);
		# echo kohana::debug($this->constants);	die();
		
		
		# Lastly we can parse the contents for element/attribute stuff.
		$this->output = '';
		foreach($this->file as $key => $line)
		{
			// set current line information
			$this->line = $line;
			$this->lineno = $key;	
			// add parsed info to template. updates "$this->line" for inclusion into the output.
			$this->parse_line();
			
			// check for debug , else add compiled line to final output.
			if ($this->debug)
				$this->debugo();
			else
				$this->output .= $this->line;

			$this->clear_current();
		}
		if($this->debug) die();
		echo trim($this->output);
	}

	
	
	/**
	 * Handles the recording of the variable definitions.
	 * constants and mixins
	 */
	private function record_vars($contents)
	{
		foreach($contents as $key => $line)
		{
			$type = $this->line_type($line);	
			if('mixin' == $type OR 'mixin_data' == $type OR 'constant' == $type)
			{
				$this->line = $line;
				$this->lineno = $key;
				$this->set_indent();
				
				# function type which sets "$this->line" to the formatted data.
				$this->$type();				
				# look ahead at next line to determine depth and close tags
				$this->look_ahead();	
				
				unset($this->file[$key]);
			}
			$this->clear_current();
		}	
		$this->file = array_merge($this->file);
		$this->in_mixin = FALSE;	
		return $this->file;
	}

	
/* inject the mixins into the file array, replacing the mixin var
 * with an array of the referenced mixin.
 */
	private function inject_mixins($contents)
	{
		# loop through all the lines looking for mixins.
		foreach($contents as $key => $line)
			if('replace_mixin' == $this->line_type($line))
			{
				$this->line = $line;
				$this->set_indent();
				$var = trim(str_replace('+', '', $line));
				
				# update the mixin with this line's indent value.
				$mixin = $this->mixins[$var];
				foreach($this->mixins[$var] as $mixin_key => $mixin_line)
					$mixin[$mixin_key] = $this->indent . $mixin_line;

				# inject the mixin into the contents as an array.
				$this->file[$key] = $mixin;
			}
	}
	
	
	
	/**
	 * Handles the parsing of each individual line
	 */
	private function parse_line()
	{
		// set indent
		$this->set_indent();
		
		// call function type
		$type = $this->line_type($this->line);
		
		# this sets $this->line to the formatted data.
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
		# get the first character on this line.
		$first = substr(trim($line), 0, 1);
		
		# this means we are parsing data within a mixin definitin.
		if ($this->in_mixin)
		{
			$type = 'mixin_data';
		}
		// ignore comments and empty lines return false first to reduce overhead
		else if ($first == '/')
		{
			$type = 'comment';
		}
		elseif (($first == '') OR (strlen(trim($line)) == 0))
		{
			$type = 'skip';
		}
		// constant definition
		elseif ($first == '!')
		{
			$type = 'constant';
		}
		// mixin definition
		elseif ($first == '=')
		{
			$type = 'mixin';
		}
		# check for attribute. Anly attrs. can retrieve constants (=)
		elseif ((FALSE !== strpos($line, ':') OR strpos($line, '=')) AND FALSE === strpos($line, '&'))
		{
			$type = 'attribute';
		}
		# check if first letter is an id, class or element
		# or contains parent reference (&)
		elseif ((in_array($first, array('#', '.'))) OR (preg_match('/[a-zA-Z&]/', $first)))
		{
			$type = 'element'; 
		}
		// retrieve mixin
		elseif (trim($first) == '+')
		{
			$type = 'replace_mixin';
		}
		return $type;
	}



//- line types -------------------------------------------------------
	
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
	 * add mixins to mixin class array.
	 */
	private function mixin()
	{
		$var = trim(str_replace('=','', $this->line));
		$this->mixins[$var] = array();
		$this->in_mixin = $var;
	}
	
	/**
	 * add mixins to mixin class array.
	 */
	private function mixin_data()
	{
		# does this line have another mixin reference?
		$ref = str_replace('+','', $this->line, $matches);
	
		# preserve indenting.
		# all data is indented at least 2 spaces so we remove these.
		$indent = str_repeat(' ', strlen($this->indent)-2);
		
		if(0 < $matches)
			foreach($this->mixins[trim($ref)] as $line)
				$this->mixins[$this->in_mixin][] = $indent . $line;
		else
			$this->mixins[$this->in_mixin][] =  $indent . trim($this->line);	
	

		# is this the last line in this mixin def. ?
		# where next indent would equal zero
		if(0 == strlen($this->next_indent))
			$this->in_mixin = FALSE;
	}
	

	
	/**
	 * Handle elements
	 */
	private function element()
	{			
		$curr_indent = strlen($this->indent);

		# update the nested array.
		$this->nested[$curr_indent] = trim($this->line);
		
		# is this element a child ?
		# element is not root, the next line is indented  as attributes must be 
		if(($curr_indent != 0) AND (strlen($this->next_indent) > $curr_indent))
		{
			# TODO optimize this.
			$this->nested[$curr_indent] = str_replace('&', $this->nested[$curr_indent-2], trim($this->line));
		
			# create the parent string to prepend to the element names on this line.
			# can have more then one ancentor so we loop.
			$parent_string = '';
			$i = 0;		
			while($i <= $curr_indent-2) 
			{
				$parent_string .= $this->nested[$i] . ' ';
				++$i; ++$i; # decrease by 2 (should always be even.)
			}

			# split up multiple element names
			$elements = explode(',', $this->line);
			
			# create the new line.
			$formatted_line = '';
			foreach($elements as $element)
			{
				# is there an ampersand parent reference?
				$element = str_replace('&', $this->nested[$curr_indent-2], $element, $matches);
				if(0 < $matches)
					$formatted_line .= str_replace($this->nested[$curr_indent-2], '', $parent_string) . trim($element) . ', ';
				else
					$formatted_line .= $parent_string . trim($element) . ', ';
			}
			# trim whitespace and trailing comma
			$formatted_line = trim(trim($formatted_line), ',');
		}
		else
		{
			# this is a root element. create the new line.
			$formatted_line = trim($this->line);
		}
		
		# output line based on style.
		if('nested' == $this->style)
			$this->line = $this->indent . $formatted_line . " {\n";
		elseif('expanded' == $this->style)
			$this->line = $formatted_line . " {\n";
		else
			$this->line = $formatted_line . '{';
	}
	

	
	/**  ATTRIBUTE | VALUE | ARITHMETIC METHODS **/
	
	
	/**
	 * Handle attribute and check for constants
	 */
	private function attribute()
	{
		// check value for constant
		preg_match('/:?([\w-]+?) *[ |:|=] *(.+)$/', $this->line, $m);
		$value = $this->parse_value($m[2]);
		
		# output line based on style.
		if('nested' == $this->style)
			$this->line = $this->indent . $m[1] . ': ' . $value . ';';
		elseif('expanded' == $this->style)
			$this->line = '  ' . $m[1] . ': ' . $value . ';';
		else
			$this->line = $m[1] . ':' . $value . '; ';		

	}



// ---- end line types -------------------------------------


	
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

	/*
	 * Replace a mixin reference
	 *
	 * @param   param
	 * @return  return
	 */
	private function replace_mixin()
	{
		$var = trim(str_replace('+', '', $this->line));
		#$this->line = $this->indent . $this->mixins[$var];
		$this->line = "[$var]";
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
		if('nested' == $this->style OR 'expanded' == $this->style)
			$this->add_new_line();
	}

	/**
	 * Add a new line to the current line if no new line is present
	 * only for nested and expanded style outputs
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
		if('nested' == $this->style OR 'compact' == $this->style)
			$this->line = $this->line." }\n";
		elseif('expanded' == $this->style)
			$this->line = $this->line."\n}\n";
		else
			$this->line = $this->line."}";
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
	}

	/*
	 * Look ahead to next line to determine depth and closing tags.
	 * Skips lines of php code (lines that begin with <?)
	 */
	private function look_ahead()
	{
		$next = $this->lineno + 1;
		$count = count($this->file);
		// get line type for next line if comment keep iterating
		while (($this->next_type == 'comment') OR ($this->next_type == FALSE))
		{
			$next_line = (empty($this->file[$next])) ? '' : $this->file[$next]; 
			$this->next_type = $this->line_type($next_line, TRUE);
			$next++;
		}
		// get next indent
		$next_line = (empty($this->file[$next])) ? '' : $this->file[$next]; 
		@preg_match('/^([ \t]+)/', $next_line, $m);
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
