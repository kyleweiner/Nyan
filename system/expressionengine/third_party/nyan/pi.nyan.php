<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Nyan
 *
 * @package      ExpressionEngine
 * @category     Plugin
 * @author       Kyle Weiner (Kylemade)
 * @copyright    Copyright (c) 2012 Kylemade <http://kylemade.com>
 * @see          http://github.com/kylemade/nyan
 */

$plugin_info = array(
    'pi_name'        => 'Nyan',
    'pi_version'     => '1.0.2',
    'pi_author'      => 'Kyle Weiner (Kylemade)',
    'pi_author_url'  => 'http://kylemade.com/',
    'pi_description' => 'Displays a list of categories in a tag cloud format, where each category is assigned a CSS class based on its popularity.',
    'pi_usage'       => 'http://github.com/kylemade/nyan'
);

class Nyan {

    protected $site_id;

    // required parameters
    private $cat_id; // comma or pipe delimited string of category group ids

    // optional parameters
    private $css_class; // class for the outermost list container
    private $css_id; // id for the outermost list container
    private $debug; // set to "yes" to enable
    private $expired; // set to "yes" to include expired channel entries
    private $limit; // maximum number of categories to show
    private $min_count; // minimum number of entries a category should have to appear in the results
    private $order; // set to "abc" for alphabetical or "pop" (default) for popularity
    private $parent_only; // set to "yes" to return only parent categories; no sub-categories will be displayed
    private $scale; // comma or pipe delimited string of classes ordered from least to most popular (e.g. "not-popular, popular, most-popular")
    private $sort; // set to "asc" or "desc" (default)
    private $status; // comma or pipe delimited string of channel entry statuses
    private $start_date; // channel entries published prior to this date/time will not be used
    private $end_date; // channel entries published on or after this date/time will not be used

    // -------------------------------------------------------------------

    public function __construct()
    {
        $this->EE =& get_instance();

        // date helper: http://ellislab.com/codeigniter/user-guide/helpers/date_helper.html
        $this->EE->load->helper('date');

        // set the default timezone for accurate date interpretations
        date_default_timezone_set($this->_get_timezone_name());

        // initialize the parameters
        $this->_init_params();

        // hajime!
        $this->return_data = $this->_meow();
    }

    // -------------------------------------------------------------------
    // P R I V A T E
    // -------------------------------------------------------------------

    private function _init_params()
    {
        // site_id
        $this->site_id = $this->EE->config->item('site_id');

        // cat_id
        $this->cat_id = $this->_set_cat_id();
        if ( ! $this->cat_id) return;

        // css_class and css_id
        $this->css_class = trim($this->EE->TMPL->fetch_param('class'));
        $this->css_id    = trim($this->EE->TMPL->fetch_param('id'));

        // expired
        $this->expired = $this->EE->TMPL->fetch_param('expired') == 'yes';

        // limit
        $this->limit = $this->EE->TMPL->fetch_param('limit');

        // min_count
        $min_count = $this->EE->TMPL->fetch_param('min_count');
        $this->min_count = is_numeric($min_count) ? $min_count : 0;

        // order
        $order = $this->EE->TMPL->fetch_param('order');
        $this->order = ($order == 'abc') ? 'abc' : 'pop';

        // parent_only
        $this->parent_only = $this->EE->TMPL->fetch_param('parent_only') == 'yes';

        // scale
        $scale       = str_replace('|', ',', $this->EE->TMPL->fetch_param('scale'));
        $this->scale = $scale ? explode(',', $scale) : array('not-popular', 'mildly-popular', 'popular', 'very-popular', 'super-popular');

        // sort
        $sort = $this->EE->TMPL->fetch_param('sort');
        $this->sort = ($sort == 'asc') ? 'asc' : 'desc';

        // status
        $this->status = str_replace(array(' ', '|'), array('', ','), $this->EE->TMPL->fetch_param('status'));

        // start_date
        $this->start_date = trim($this->EE->TMPL->fetch_param('start_date'));

        // end_date
        $this->end_date = trim($this->EE->TMPL->fetch_param('end_date'));

        // debug
        $this->debug = $this->EE->TMPL->fetch_param('debug') == 'yes';
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

        return $cat_id;
    }

    // -------------------------------------------------------------------

    private function _meow()
    {
        // log parameters if debugging is enabled
        if ($this->debug)
        {
            $this->_log_item("cat_id = $this->cat_id");
            $this->_log_item("class = $this->css_class");
            $this->_log_item("id = $this->css_id");
            $this->_log_item("limit = $this->limit");
            $this->_log_item("min_count = $this->min_count");
            $this->_log_item("order = $this->order");
            $this->_log_item("parent_only = $this->parent_only");
            $this->_log_item('scale = '.implode(', ', $this->scale));
            $this->_log_item("sort = $this->sort");
            $this->_log_item("expired = $this->expired");
            $this->_log_item("status = $this->status");
            $this->_log_item("start_date = $this->start_date");
            $this->_log_item("end_date = $this->end_date");
        }

        // round up 'em cats
        $query = $this->_find_cat_data();

        // stop if no results were returned
        if ($query->num_rows() == 0)
        {
            $this->_log_item('unable to retrieve category data', 'error');
            return $this->EE->TMPL->no_results();
        }

        return $this->_format_output($query->result()); 
    }

    // -------------------------------------------------------------------

    private function _format_output($results)
    {
        // find the entry count for the category with the greatest number of entries
        foreach($results as $row)
        {
            $entry_counts[] = $row->entry_count;
        }
        $max_entry_count = max($entry_counts);

        // prepare data
        foreach($results as $row)
        {
            $vars[] = array(
                'cat_id'          => $row->cat_id,
                'cat_name'        => $row->cat_name,
                'cat_url_title'   => $row->cat_url_title,
                'cat_entry_count' => $row->entry_count,
                'cat_weight'      => $this->_weigh_cat($row->entry_count, $max_entry_count),
                'parent_id'       => $row->parent_id
            );
        }

        $tagdata = $this->EE->TMPL->parse_variables(rtrim($this->EE->TMPL->tagdata), $vars);

        $output  = '<ol';
        $output .= ($this->css_id) ? ' id="'.$this->css_id.'"' : NULL; // css_id
        $output .= ($this->css_class) ? ' class="'.$this->css_class.'"' : NULL; // css_class
        $output .= '>'.$tagdata."\n</ol>";

        return $output;
    }

    // -------------------------------------------------------------------

    private function _find_cat_data()
    {
        // find the disallowed entry IDs based on the start_date, end_date, expired or status parameters
        $disallowed_entries = $this->_find_disallowed_entries();

        // get category data
        $this->EE->db->select('c.cat_id, cat_name, cat_url_title, COUNT(cp.entry_id) AS entry_count, parent_id')
             ->from('categories AS c, channels AS ch')
             ->join('category_posts AS cp', 'c.cat_id = cp.cat_id', 'left')
             ->where('c.site_id', $this->site_id)
             ->where("group_id IN($this->cat_id)")
             ->group_by('c.cat_id')
             ->having("entry_count >= $this->min_count");

        // parents only?
        if ($this->parent_only) $this->EE->db->where('parent_id', 0);

        // exclude disallowed entries?
        if ( ! empty($disallowed_entries)) $this->EE->db->where("cp.entry_id NOT IN($disallowed_entries)");

        // order by "abc" or "pop" (default)?
        // sort by "asc" or "desc" (default)?
        if ($this->order == 'abc') $this->EE->db->order_by("cat_name $this->sort, entry_count");
        if ($this->order == 'pop') $this->EE->db->order_by("entry_count $this->sort, cat_name");

        // add limit?
        if (is_numeric($this->limit)) $this->EE->db->limit($this->limit);

        return $this->EE->db->get();
    }

    // -------------------------------------------------------------------

    private function _find_disallowed_entries()
    {
        $entry_ids = ''; // disallowed entry IDs

        /*
         * start and end dates are converted using PHP's strtotime function.
         * - Reference: http://php.net/manual/en/function.strtotime.php
         * - Supported Formats: http://php.net/manual/en/datetime.formats.php
         * - Possible Issues: dates earlier than 1901 or later than 2038 are NOT supported 
         */
        $start_date = strtotime($this->start_date);
        $end_date   = strtotime($this->end_date);
        $valid_start_date = $this->_valid_strtotime($start_date);
        $valid_end_date   = $this->_valid_strtotime($end_date);

        // stop if no entries should be excluded
        if ($this->expired AND ! $this->status AND ! $valid_start_date AND ! $valid_end_date) return '';

        // find entries to exclude
        $this->EE->db->select('entry_id')
             ->from('channel_titles')
             ->where('site_id', $this->site_id);

         // use 'or_where' (as opposed to 'where') for the next statement
        $use_or_where = FALSE;

        // exclude entries expiring on or before the current date/time
        if ( ! $this->expired)
        {
            $this->EE->db->where('expiration_date BETWEEN 1 AND '.strtotime('now'));
            $use_or_where = TRUE;
        }

        // exclude entries that do not match the status(es)
        if ($this->status)
        {
            $statuses = explode(',', $this->status);
            ($use_or_where) ? $this->EE->db->or_where_not_in('status', $statuses) : $this->EE->db->where_not_in('status', $statuses);
            $use_or_where = TRUE;
        }

        // exclude entries published before the start_date
        if ($valid_start_date)
        {
            if ($this->debug) $this->_log_item('converted start_date = '.date('Y-m-d h:ia T', $start_date));

            $condition = "entry_date < $start_date";
            ($use_or_where) ? $this->EE->db->or_where($condition) : $this->EE->db->where($condition);
            $use_or_where = TRUE;
        }

        // exclude entries published after the end_date
        if ($valid_end_date)
        {
            if ($this->debug) $this->_log_item('converted end_date = '.date('Y-m-d h:ia T', $end_date));

            $condition = "entry_date > $end_date";
            ($use_or_where) ? $this->EE->db->or_where($condition) : $this->EE->db->where($condition);
            $use_or_where = TRUE;
        }

        $query = $this->EE->db->get();

        // create a comma delimited string of disallowed entry IDs
        if ($query->num_rows() > 0)
        {
           foreach ($query->result() as $row)
           {
                $entry_ids .= $row->entry_id . ',';
           }
        }

        return rtrim($entry_ids, ',');
    }

    // -------------------------------------------------------------------

    private function _weigh_cat($cat_entry_count = 0, $max_entries = 0)
    {
        $num_ticks  = count($this->scale);
        $cat_weight = ($cat_entry_count / $max_entries) * 100; // e.g. if largest cat has 10 entries and this cat has 2, weight is 20% 
        $tick = 100 / $num_ticks; // e.g. if it's a 10 scale, each tick is 10% 
        $i = 0;

        while ($i <= $num_ticks)
        {
            if ((($i + 1) * $tick) >= $cat_weight) return trim($this->scale[$i]);
            $i++;
        }
    }

    // -------------------------------------------------------------------

    private function _get_timezone_name()
    {
        $is_DST = ($this->EE->config->item('daylight_savings') == 'y') ? TRUE : FALSE;
        $timezone_offset = timezones($this->EE->config->item('server_timezone'));
        return timezone_name_from_abbr('', $timezone_offset * 3600, $is_DST);
    }

    // -------------------------------------------------------------------

    private function _valid_strtotime($str = '')
    {
        return ( ! in_array($str, array(-1, FALSE))) ? TRUE : FALSE;
    }

    // -------------------------------------------------------------------

    private function _log_item($message = '', $level = 'notice')
    {
        $this->EE->TMPL->log_item(__CLASS__.' '.ucwords($level).': '.$message);
    }

}