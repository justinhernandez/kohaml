<?php
/**
 * Kohaml library to parse haml files.
 *
 * @package        Kohaml
 * @author         Justin Hernandez <justin@transphorm.com>
 * @license        http://www.opensource.org/licenses/isc-license.txt
 */
abstract class KohamlLib
{
	// Use KohamlLib without Kohana
	private $standalone = FALSE;
	// array of closing tags
	private $close_tags = array();
	// current line #
	private $lineno;
	// indent for current row
	private $indent;
	// indent for previous row
	private $next_indent;
	// element tag to construct for current row
	private $matched_tag;
	// attributes for current line
	private $matched_attr;
	// php stripped from current line
	private $php = array();
	// is line php?
	private $php_line = FALSE;
	// is next line escaped php?
	private $escaped_next = FALSE;
	// elemenet tag after parsing
	private $tag;
	// parsed attributes for current
	private $attr = array();
	// parsed line to return
	private $line;
	// text for current line
	private $text;
	// is current tag a self closing tag
	private $close_self;
	// file object
	private $file;
	// compiled template
	private $output;
	// skip nested php
	private $nested_php;
	// script name for debugging
	private $script;

	/**
	 * Load initial settings. Make changes here if you are using KohamlLib in
	 * standalone mode. Check out replace_rules() for adding your own custom rules.
	 * No __construct because it is an abstract class.
	 */
	private function init()
	{
		// double or single quotes
		$quotes = "double";

		$this->quotes = ($this->standalone)
					  ? $quotes
					  : Kohana::config('kohaml.quotes');
	}

	/**
	 * Main function. Parses haml and returns html
	 * Construct, accepts an array usually from file('template.haml').
	 *
	 * @param  array    $contents
	 * @return string
	 */
	public function compile($contents, $script = NULL)
	{
		// load initial settings
		$this->init();
		// set script name
		$this->script = $script;
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
		// close all open tags
		$this->close_nested(count($this->close_tags), TRUE);
		if ($this->debug) die();
		return trim($this->output);
	}

	/**
	 * Handles the parsing of each individual line
	 */
	private function parse_line()
	{
		// check for nested php
		if ($this->nested_php()) return FALSE;

		$first = substr(trim($this->line), 0, 1);
		// set indent
		$this->set_indent();
		// run replacement rules
		$this->replace_rules();
		// check if it's a tag element then handle appropriately
		if (in_array($first, array('%', '#', '.')))
		{
			// strip php tags to re-input later
			$this->strip_php();
			// break up line into chunks
			preg_match('/^([ \t]+)? ?([^ \{]+)(\{(.+)\})?(.+)?/', $this->line, $m);
			// parse element tag
			$this->matched_tag = trim(@$m[2]);
			// matched text
			$this->text = trim(@$m[5]);
			// parse element tag
			$this->element();
			// parse attributes into a string
			$this->matched_attr = trim(@$m[4]);
			if ($this->matched_attr) $this->parse_attributes();
		}
		// check for comment element
		else if ($first == '/')
		{
			preg_match('/^([ \t]+)?\/(.+)/', $this->line, $m);
			$this->tag = '<!-- ';
			$this->text = trim(@$m[2]);
			// add nbsp because close tags are trimmed will be converted back later
			$this->add_close('&nbsp;-->');
		}
		// check for DOCTYPE shortcut
		else if ($first == '!')
		{
			preg_match('/!{3}( [^ ]+)?( .+)?/', $this->line, $m);
			$type = (@$m[1])
				  ? trim(@$m[1])
				  : '1.0';
			$enc = (@$m[2])
				 ? trim(@$m[2])
				 : 'utf-8';
			$types =
				array(
					'1.0' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
					'1.1' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">',
					'Strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">',
					'Transitional' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
					'Frameset' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">',
					'XML' => '<?xml version="1.0" encoding="'.$enc.'"?>'
				);
			$this->line = $types[$type]."\n";
			return FALSE;
		}
		// escape php and take into account depth
		else if ($first == '|')
		{
			preg_match('/^([ \t]+)?/', $this->line, $m);
			$this->tag = '[[KOHAML::ESCAPE]]';
			$this->line = $this->indent.trim(str_replace('|', '', $this->line));
			$this->add_close('');
			// if closing tag is not on this line than php is nested
			if (!strpos($this->line, '?>')) $this->nested_php = TRUE;
		}
		// is current line php?
		else if (preg_match('/^([ \t]+)?(\<\?).+(\?\>)?/', $this->line, $m))
		{
			// if closing php tag is not on current line look for it
			if (@trim($m[3]))
			{
				// step over lines and add to output until closing tag is found
				// or end of file
				while ((!preg_match('/\?\>/', $this->file->current())) AND $this->file->valid())
				{
					$this->output .= $this->file->current();
					$this->file->next();
				}
				$this->line = $this->file->current();
				$this->add_new_line();
			}
			else
			{
				$this->add_new_line();
			}

			return FALSE;
		}
		// must be text or something else pass thru
		else
		{
			preg_match('/^([ \t]+)?/', $this->line, $m);
			$this->tag = '[[KOHAML::ESCAPE]]';
			$this->add_close('');
		}
		// look ahead at next line to determine depth and close tags
		$this->look_ahead();
		// construct line
		$this->construct_line();
	}

	/**
	 * Is php nested?
	 *
     * @return boolean
     */
	private function nested_php()
	{
		// check for nested php and return false while nested
		if ($this->nested_php)
		{
			// if end tag on this line the set nested to FALSE but still skip
			// this line.
			if (strpos($this->line, '?>')) $this->nested_php = FALSE;
			// still nested
			return TRUE;
		}
		// no nested php
		return FALSE;
	}

	/**
	 * Rebuilds line after parsing
	 */
	private function construct_line()
	{
		$curr = strlen($this->indent)/2;
		$next = strlen($this->next_indent)/2;
		if($curr == $next)
		{
			$this->close();
		}
		// not indented
		else if ($next < $curr)
		{
			$this->close();
			$this->close_nested($curr-$next);
		}
		// indented
		else if ($next > $curr)
		{
			if ($this->tag != '[[KOHAML::ESCAPE]]')
			{
				// if text is present then indent add 2 spaces and put it one it's own line
				if ($this->text) $this->text = "\n".$this->indent.'  '.$this->text;
				// add new line and indent text
				$this->line = $this->indent.$this->tag.$this->text."\n";
			}
		}
		$this->refill_line();
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
		if ($this->tag != '[[KOHAML::ESCAPE]]')
		{
			$this->line = $this->indent.$this->tag.$this->text;
			$this->line .= trim(array_pop($this->close_tags));
			$this->add_new_line();
		}
		else
		{
			$this->line .= trim(array_pop($this->close_tags));
		}
	}

	/**
	 * Close $count number of tags
	 *
	 * @param  integer  $count
	 */
	private function close_nested($count, $use_output = FALSE)
	{
		$this->add_new_line();
		// close nested
		for ($a=0; $a!=$count; $a++)
		{
			// check for empty close tags and don't add them
			$close = array_pop($this->close_tags);
			if (trim($close))
			{
				if ($use_output)
				{
					$this->output .= $close."\n";
				}
				else
				{
					$this->line .= $close."\n";
				}
			}
		}
	}

	/**
	 * Set the indent for each line.
	 */
	private function set_indent()
	{
		preg_match('/^([ \t]+)?/', $this->line, $m);
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
		while (@preg_match('/^([ \t]+)?(\<\?)/', $this->file->offsetGet($next)) AND ($next < $count))
		{
			$next++;
		}
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
	 * Replace php and attributes into current line
	 */
	private function refill_line()
	{
		$attrs = '';
		// compile attributes
		foreach($this->attr as $type => $val)
		{
			$val = addslashes(trim($val));
			$attrs .= ($this->quotes == "double")
					? " $type=\"$val\""
					: " $type='$val'";
		}
		$replace = array('[[KOHAML::ATTR]]', '&nbsp;');
		$fill = array($attrs, ' ');
		$this->line = str_replace($replace, $fill, $this->line);
		// replace php
		if ($this->php)
		{
			foreach($this->php as $php)
			{
				$this->line = preg_replace('/\[\[KOHAML::PHP\]\]/', $php, $this->line, 1);
			}
		}
	}

	/**
	 * Function where all replace rules are handled and parsed
	 */
	private function replace_rules()
	{
		$rule = array();
		$replace = array();
		$m = array();
		// RULE #1 element= $var --> element <?= $var ? >
		$rule[] = '/([^\<\?]+)=[ ]+(\$[^ $\n{]+)/';
		$replace[] = '$1 <?= $2 ?>&nbsp;';

		// Kohana specific replace rules
		if (!$this->standalone)
		{
			// RULE #2 load for loading sub-views
			$rule[] = '/^([ \t]+)?load\((.+)\)/';
			$replace[] = "$1<?php print new View('$2') ?>";

			// RULE #3 load for loading sub-views
			$rule[] = '/([^\<\?]+)=[ ]+load\((.+)\)/';
			$replace[] = "$1<?php print new View('$2') ?>";
		}

		// apply rules
		$this->line = preg_replace($rule, $replace, $this->line);
	}

	/**
	 * Handle matched element text e.g. %div#content.text
	 */
	private function element()
	{
		// get first
		$first = $this->matched_tag[0];
		// get rest of element
		preg_match("/$first([^\{\.#  =\/]+)/", $this->matched_tag, $m);
		$element = trim(@$m[1]);
		// check for self closing tag
		if (substr(trim($this->line), -2, 2) == ' /')
		{
			$this->close_self = '/';
			$this->text = "";
			$this->close_tags[] = '';
		}
		// find class and id attributes
		$this->parse_id_class();

		switch ($first)
		{
			case '%':
				$this->tag = "<$element"."[[KOHAML::ATTR]]$this->close_self>";
				$this->add_close("</$element>");
				break;
			case '#':
				$this->tag = "<div[[KOHAML::ATTR]]$this->close_self>";
				$this->add_close('</div>');
				break;
			case '.':
				$this->tag = "<div[[KOHAML::ATTR]]$this->close_self>";
				$this->add_close('</div>');
				break;
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
	 * Parse matched attributes text, e.g. { 'id' => 'something' }
	 */
	private function parse_attributes()
	{
		if (strpos($this->matched_attr, ','))
		{
			$attr = split(',', $this->matched_attr);
		}
		else
		{
			$attr[0] = $this->matched_attr;
		}

		foreach($attr as $a)
		{
			$val = split('=>', $a);
			@$this->add_attr($this->clean_attr($val[0]), array($this->clean_attr($val[1])));
		}
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
	 * Find ids and class within matched element text.
	 */
	private function parse_id_class()
	{
		preg_match_all('/\.([^#  \{\.]+)/', $this->matched_tag, $m);
		$this->add_attr('class', @$m[1]);
		preg_match_all('/#([^\.  \{\#]+)/', $this->matched_tag, $m);
		$this->add_attr('id', @$m[1]);
	}

	/**
	 * Add values to class attributes array for current line.
	 *
	 * @param  string  $type
	 * @param  array   $vals
	 */
	private function add_attr($type, $vals)
	{
		for($i=0; $i<count($vals); $i++)
		{
			@$this->attr[$type] .= ' '.$vals[$i];
		}
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
	 * Strips php tags from current line for re-insertion later.
	 */
	private function strip_php()
	{
		preg_match_all('/(\<\?.+?\?\>)/', $this->line, $m);
		if (@$m[0])
		{
			foreach(@$m[0] as $php)
			{
				$this->php[] = $php;
			}
		}
		// strip out php
		$this->line = preg_replace('/(\<\?.+?\?\>)/', '[[KOHAML::PHP]]', $this->line);
	}

	/**
	 * Clears class variables and gets ready for next line.
	 */
	private function clear_current()
	{
		$this->line = '';
		$this->php = '';
		$this->php_line = FALSE;
		$this->escaped_next = FALSE;
		$this->tag = '';
		$this->matched_tag = '';
		$this->matched_attr = '';
		$this->indent = '';
		$this->attr = array();
		$this->close_self = '';
		$this->text = '';
	}

	/**
	 * Debug output
	 */
	private function debugo()
	{
		$line = $this->lineno+1;
		$replace = array(' ', "\n");
		$fill = array('&nbsp;&nbsp;', '<br/>');
		echo(str_replace($replace, $fill, htmlentities($this->line)));
	}
}