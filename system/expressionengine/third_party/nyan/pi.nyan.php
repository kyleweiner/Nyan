<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Nyan
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			Kyle Weiner (Kylemade)
 * @copyright 		Copyright (c) 2012 Kylemade <http://kylemade.com>
 * @see				http://github.com/kylemade/nyan
 */

$plugin_info = array(
	'pi_name'        => 'Nyan',
	'pi_version'     => '1.0.0',
	'pi_author'      => 'Kyle Weiner (Kylemade)',
	'pi_author_url'  => 'http://kylemade.com/',
	'pi_description' => 'Displays a list of categories in a tag cloud format, where each category is assigned a CSS class based on its popularity.',
	'pi_usage'       => Nyan::usage()
	);

class Nyan {

	// required
	private $cat_id; // comma or pipe delimited string of category group ids

	// optional
	private $css_class; // class for the outermost list container
	private $css_id; // id for the outermost list container
	private $debug; // set to "yes" to enable
	private $limit; // maximum number of categories to show
	private $min_count; // minimum number of entries a category should have to appear in the results
	private $order; // set to "abc" for alphabetical or "pop" (default) for popularity
	private $scale; // comma or pipe delimited string of classes ordered from least to most popular (e.g. "not-popular, popular, most-popular")
	private $sort; // set to "asc" or "desc" (default)

	// class
	protected $site_id;

	// -------------------------------------------------------------------

	public function Nyan() 
	{
		$this->__construct();
	}

	// -------------------------------------------------------------------

	public function __construct()
	{
		$this->EE =& get_instance();

		// set site_id
		$this->site_id = $this->EE->config->item('site_id');

		// set cat_id after basic validation
		$this->cat_id = $this->_set_cat_id();
		if ( ! $this->cat_id) return;

		// set css_class and css_id
		$this->css_class = trim($this->EE->TMPL->fetch_param('class'));
		$this->css_id    = trim($this->EE->TMPL->fetch_param('id'));

		// set limit
		$this->limit = $this->EE->TMPL->fetch_param('limit');

		// set min count
		$min_count = $this->EE->TMPL->fetch_param('min_count');
		$this->min_count = is_numeric($min_count) ? $min_count : 0;

		// set order
		$order = $this->EE->TMPL->fetch_param('order');
		$this->order = ($order == 'abc') ? 'abc' : 'pop';

		// set scale
		$scale       = str_replace('|', ',', $this->EE->TMPL->fetch_param('scale'));
		$this->scale = $scale ? explode(',', $scale) : array('not-popular', 'mildly-popular', 'popular', 'very-popular', 'super-popular');

		// set sort
		$sort = $this->EE->TMPL->fetch_param('sort');
		$this->sort = ($sort == 'asc') ? 'asc' : 'desc';

		// enable debugging?
		$this->debug = $this->EE->TMPL->fetch_param('debug') == 'yes';
		
		// hajime!
		$this->return_data = $this->_meow();
	}

	// -------------------------------------------------------------------

	private function _set_cat_id()
	{
		// remove spaces and replace pipes with commas
		$cat_id = str_replace(array(' ', '|'), array('', ','), $this->EE->TMPL->fetch_param('cat_id'));

		// cat_id is required
		if ( ! $cat_id)
		{
			$this->_log_item('cat_id is required', 'error');
			return;
		}

		// cat id(s) must be numeric
		if ( ! is_numeric(str_replace(array(',', '|', ' '), '', $cat_id)))
		{
			$this->_log_item('cat_id must be a comma or pipe delimited string of category group ids (e.g. "1|2")', 'error');
			return;
		} 

		// passed!
		return $cat_id;
	}

	// -------------------------------------------------------------------

	private function _meow()
	{
		// log params if debugging is enabled
		if ($this->debug)
		{
			$this->_log_item("cat_id = $this->cat_id");
			$this->_log_item("class = $this->css_class");
			$this->_log_item("id = $this->css_id");
			$this->_log_item("limit = $this->limit");
			$this->_log_item("min_count = $this->min_count");
			$this->_log_item("order = $this->order");
			$this->_log_item('scale = '.implode(', ', $this->scale));
			$this->_log_item("sort = $this->sort");
		}

		// get category data
		$this->EE->db->select('c.cat_id, cat_name, cat_url_title, COUNT(cp.entry_id) AS entry_count, parent_id')
			 ->from('categories AS c, channels AS ch')
			 ->join('category_posts AS cp', 'c.cat_id = cp.cat_id', 'left')
			 ->where('c.site_id =', $this->site_id)
			 ->where("group_id IN($this->cat_id)")
			 ->group_by('c.cat_id');

		// order by "abc" or "pop" (default)?
		// sort by "asc" or "desc" (default)?
		if ($this->order == 'abc') $this->EE->db->order_by("cat_name $this->sort, entry_count");
		if ($this->order == 'pop') $this->EE->db->order_by("entry_count $this->sort, cat_name");

		// add limit?
		if (is_numeric($this->limit)) $this->EE->db->limit($this->limit);

		$query = $this->EE->db->get();

		// no results
		if ($query->num_rows() == 0)
		{
			$this->_log_item('unable to retrieve category data', 'error');
			return $this->EE->TMPL->no_results();
		}

		// find the category with the greatest number of entries
		$entry_counts = array();
		foreach($query->result() as $row)
		{
			$entry_counts[] = $row->entry_count;
		}
		$max_entries = max($entry_counts);

		// prepare data
		$vars = array();

		foreach($query->result() as $row)
		{
			// filter out entries that don't meet min_count
			if ( ! $this->min_count || $this->min_count AND $row->entry_count >= $this->min_count)
			{
				$vars[] = array(
					'cat_id'           => $row->cat_id,
					'cat_name'         => $row->cat_name,
					'cat_url_title'    => $row->cat_url_title,
					'cat_entry_count'  => $row->entry_count,
					'cat_weight'       => $this->_weigh_cat($row->entry_count, $max_entries),
					'parent_id'        => $row->parent_id
				);
			}
		}

		// are there still results?
		if (count($vars) == 0)
		{
			$this->_log_item('no results: try adjusting the min_count', 'notice');
			return $this->EE->TMPL->no_results();		
		}

		// format and return output
		$tagdata = $this->EE->TMPL->parse_variables(rtrim($this->EE->TMPL->tagdata), $vars);

		$output  = '<ol';
		$output .= ($this->css_id) ? ' id="'.$this->css_id.'"' : $output; // css_id
		$output .= ($this->css_class) ? ' class="'.$this->css_class.'"' : $output; // css_class
		$output .= '>'.$tagdata."\n</ol>";

		return $output;	
	}

	// -------------------------------------------------------------------

	private function _weigh_cat($cat_entry_count = 0, $max_entries = 0)
	{
		$num_ticks  = count($this->scale);
		$cat_weight = ($cat_entry_count / $max_entries) * 100; // e.g. if largest cat has 10 entries and this cat has 2, weight is 20% 
		$tick = 100 / $num_ticks; // e.g. if it's a 10 scale, each tick is 10% 
		$i = 0;
		while($i <= $num_ticks)
		{
			if ((($i + 1) * $tick) >= $cat_weight) return trim($this->scale[$i]);
			$i++;
		}
	}

	// -------------------------------------------------------------------

	private function _log_item($message = '', $level = 'notice')
	{
		$output = __CLASS__.' '.ucwords($level).': '.$message;
		$this->EE->TMPL->log_item($output);
	}

	// -------------------------------------------------------------------

	public function usage()
	{
		ob_start(); ?>http://github.com/kylemade/nyan<?php 
		$buffer = ob_get_contents(); 
		ob_end_clean(); 
		return $buffer;
	}

}