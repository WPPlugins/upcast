<?php
define('UPCAST_FIELD_NAMES','thumbnail,date,title,author,subject,category,description,link,url,image_link,image_url,thumbnail_url,content,duration,size,search');
define('UPCAST_HIDE_FIELD_NAMES','url,image_link,image_url,thumbnail_url');
define('UPCAST_FILTER_FIELD_NAMES','author,subject,category');
define('UPCAST_DEFAULT_COLUMNS','date,title,author,subject,link');
define('UPCAST_WIDGET_FIELD_NAMES','thumbnail,link,url,title,date,author,subject,category,description,duration,size');
define('UPCAST_DEFAULT_WIDGET_COLUMNS','date,link');
define('UPCAST_RSS_FIELD_NAMES','title,author,category,description,link,copyright');
define('UPCAST_IMAGE_FIELD_NAMES','image_title,image_link,image_url,image_width,image_height');

define('UPCAST_URL_SCHEME', 'scheme');
define('UPCAST_URL_HOST', 'host');
define('UPCAST_URL_CAST', 'c');
define('UPCAST_URL_SOURCE', 'o');
define('UPCAST_URL_MAX', 'm');
define('UPCAST_URL_TEXT', 't');

define('UPCAST_URL_FLAG_TRUE', '1');
define('UPCAST_URL_FLAG_FALSE', '0');

define('UPCAST_URL_MAX_MAX', 128);

// Image URLs
define('UPCAST_URL_TYPE', 's');

include_once(ABSPATH . WPINC . '/feed.php');
require_once (ABSPATH . WPINC . '/class-feed.php');

class SimplePie_upcast extends SimplePie {

	var $sortorder = 'default';
	var $terms; // = explode(',','default');
	var $maxterm;
	var $delimiter = ',';
	
	function set_terms()
	{
		$this->terms = explode($this->delimiter, $this->sortorder);
		$this->maxterm = sizeof($this->terms) - 1;
	}
	
	//function SimplePie_upcast()
	//{
	//	//$this->SimplePie();
	//	$this->set_terms();
	//}
	
	function get_sortorder() {
		return $this->sortorder;
	}
	
	function get_maxterm() {
		return $this->maxterm;
	}
	
	function set_sortorder($new_order) {
		$this->sortorder = $new_order;
		$this->set_terms();
	}

	public static function sort_nested($a, $b, $depth)
	{
		$src = $a->get_feed();
		$term = $src->terms[$depth];
		
		switch ($term[0]) {
			case '-':
				$ascend = false;
				$term = substr($term,1);
				break;
			case '+':
				$ascend = true;
				$term = substr($term,1);
				break;
			default:
				$ascend = true;
				break;
		}
		
		switch($term) {
			case 'title':
				$a_value = $a->get_title();
				$b_value = $b->get_title();
				break;
			case 'category':
				$a_value = $a->get_category()->get_label();
				$b_value = $b->get_category()->get_label();
				break;
			case 'description':
				$a_value = $a->get_description();
				$b_value = $b->get_description();
				break;
			case 'author':
				$a_value = $a->get_author()->get_name();
				$b_value = $b->get_author()->get_name();
				break;
			case 'pubdate':
				$a_value = $a->get_date('YmdHis');
				$b_value = $b->get_date('YmdHis');
				break;
			case 'default':
			default:
				return parent::sort_items($a, $b);
		}
		
		if ($ascend) {
			$comp = strcmp($a_value, $b_value);
		} else {
			$comp = strcmp($b_value, $a_value);
		}
		
		if ($comp != 0)
		{
			return $comp;
		}		
		else
		{
			if ($depth < $src->maxterm) {
				return SimplePie_upcast::sort_nested($a, $b, $depth + 1);
			} 
			else {
				return 0;
			}
		}
	}
	
	public static function sort_items($a, $b)
	{
		//return 0;
		//return strcmp($a->get_date('YmdHis'), $b->get_date('YmdHis'));
		return SimplePie_upcast::sort_nested($a, $b, 0);
	}
}
 
function parse_upcast_url($feed) {
	$url = parse_url($feed);
	//error_log('url='.print_r($url, true));
	// If this is an upcast feed, apply and modifiers
	//error_log('host='.$url['host']);
	if (preg_match('/(.*\.)?upcast\.me/', $url['host'])) {
		$upcast_url = array();
		if (isset($url['query']))
			parse_str($url['query'], $upcast_url);
		//error_log('upcast_url='.print_r($upcast_url, true));
		$matches = array();
		if (preg_match('/\/'.UPCAST_URL_SOURCE.'\/([^\/]+)\/.*/', $url['path'], $matches)) {
			$upcast_url[UPCAST_URL_SOURCE] = $matches[1];
		}
		$matches = array();
		if (preg_match('/.*\/([^\/]+)\.rss/', $url['path'], $matches)) {
			$upcast_url[UPCAST_URL_CAST] = $matches[1];
		} else {
			return FALSE;
		}
		$upcast_url[UPCAST_URL_SCHEME] = $url['scheme'];
		$upcast_url[UPCAST_URL_HOST] = $url['host'];
		return $upcast_url;
	} else {
		return FALSE;
	}
}

function make_upcast_url($parsed) {
	//error_log('parsed='.print_r($parsed, true));
	$url = 	$parsed[UPCAST_URL_SCHEME] . '://' .
		   	$parsed[UPCAST_URL_HOST] . '/' .
			((isset($parsed[UPCAST_URL_SOURCE]) && $parsed[UPCAST_URL_SOURCE]) ? 
				UPCAST_URL_SOURCE.'/'.$parsed[UPCAST_URL_SOURCE].'/' : '') .
			(isset($parsed[UPCAST_URL_TYPE]) ? $parsed[UPCAST_URL_TYPE].'/' : '') .
			$parsed[UPCAST_URL_CAST] . '.' . (isset($parsed[UPCAST_URL_TYPE]) ? 'php' : 'rss');
	//error_log('url='.$url);
	$parms = array();
	if (isset($parsed[UPCAST_URL_MAX])) $parms[UPCAST_URL_MAX] = $parsed[UPCAST_URL_MAX];
	if (isset($parsed[UPCAST_URL_TEXT])) $parms[UPCAST_URL_TEXT] = $parsed[UPCAST_URL_TEXT];
	if (count($parms))
		$url .= '?' . http_build_query($parms);
	return $url;
}

function modify_upcast_url($feed, $max = NULL, $future = NULL, $files = NULL, $source = NULL, $type = NULL) {
	$url = parse_upcast_url($feed);
	//error_log('url='.print_r($url, true));
	if ($max !== NULL && $max !== FALSE) {
		$url[UPCAST_URL_MAX] = $max;
		if ($future)
			$url[UPCAST_URL_MAX] = -$url[UPCAST_URL_MAX];
	} elseif ($future) {
		$url[UPCAST_URL_MAX] = -UPCAST_URL_MAX_MAX;
	}
	// Note the sense is intentionally reversed below because the plugin flag means the opposite to the upcast flag
	if ($files !== NULL)
		$url[UPCAST_URL_TEXT] = $files ? UPCAST_URL_FLAG_FALSE : UPCAST_URL_FLAG_TRUE;
	if ($source !== NULL && $source !== FALSE) {
		$url[UPCAST_URL_SOURCE] = $source;
	}
	if ($type !== NULL)
		$url[UPCAST_URL_TYPE] = $type;
	return make_upcast_url($url);
}

function preg_match_simple($pattern, $subject) {
	return preg_match('/'.$pattern.'/i', $subject);
}


class UpcastSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    private	$field_names;
	private $hide_field_names;
	private $filter_field_names;
	
	public function GetFields() { 
		return $field_names;
	}

	public function GetHideFields() { 
		return $hide_field_names;
	}

	public function GetFilterFields() { 
		return $filter_field_names;
	}
	
    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
		$this->field_names = explode(',',UPCAST_FIELD_NAMES);
		$this->hide_field_names = explode(',',UPCAST_HIDE_FIELD_NAMES);
		$this->filter_field_names = explode(',',UPCAST_FILTER_FIELD_NAMES);
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        $page = add_options_page(
            'Settings Admin', 
            'UpCast', 
            'manage_options', 
            'upcast-setting-admin', 
            array( $this, 'create_admin_page' )
        );
		add_action( 'admin_print_styles-' . $page, array( $this,'upcast_admin_styles' ));		
    }

	public function upcast_admin_styles() {
       /*
        * It will be called only on your plugin admin page, enqueue our stylesheet here
        */
       wp_enqueue_style('upcastStylesheet');
   }
    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'upcast_options' );
		//error_log(print_r($this->options, true));
        ?>
        <script type="application/javascript">
		function toggleSection(e) {
			var toggle = jQuery(e);
			if (toggle.text() == "+") {
				toggle.parent().next().next().slideUp("fast", function() { jQuery(this).prev().slideUp(); });
				toggle.text("-");
			} else {
				toggle.parent().next().slideDown("fast", function() { jQuery(this).next().slideDown(); });
				toggle.text("+");
			}
		}
		jQuery(function(){
			jQuery('div.wrap div.upcast-expand:not(:first)').text('-');
			jQuery('div.wrap div.upcast-section-header:not(:first)').hide();			
			jQuery('div.wrap table.form-table:not(:first)').hide();			
		});
		</script>
        <div class="wrap">
        	<div class="upcast-settings-header">
            	<div class="upcast-settings-header-logo">
                	<a href="https://upcast.me"><img src="<?=plugins_url('UpCastPPHeader.png', __FILE__)?>"></a>
                </div>
                <div class="upcast-settings-header-text">
                    <h2>Shortcode Settings</h2>
                    <p>Add a podcast feed to any post or page by including the text <code>[upcast]</code></p>
                    <p>Include a search bar with the search attribute: <code>[upcast search="true"]</code></p>
                    <p>Display the feed's main image or thumbnail by including <code>[upcast_image]</code></p>
                </div>
            </div>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'upcast_option_group' );   
                do_settings_sections( 'upcast-setting-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

	public function section_title($text) {
		return '<div class="upcast-expand" onClick="toggleSection(this);">+</div>' . $text;
	}
	
    /**
     * Register and add settings
     */
    public function page_init()
    {        
 		 wp_register_style('upcastStylesheet', plugins_url('style.css', __FILE__) );
         register_setting(
            'upcast_option_group', // Option group
            'upcast_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_defaults', // ID
            $this->section_title('Defaults'), // Title
            array( $this, 'print_section_defaults' ), // Callback
            'upcast-setting-admin' // Page
        );  

        add_settings_section(
            'setting_section_analytics', // ID
             $this->section_title('Analytics'), // Title
            array( $this, 'print_section_analytics' ), // Callback
            'upcast-setting-admin' // Page
        );  

        add_settings_section(
            'setting_section_filters', // ID
             $this->section_title('Filters'), // Title
            array( $this, 'print_section_filters' ), // Callback
            'upcast-setting-admin' // Page
        );  

        add_settings_section(
            'setting_section_columns', // ID
             $this->section_title('Columns'), // Title
            array( $this, 'print_section_columns' ), // Callback
            'upcast-setting-admin' // Page
        );  

        add_settings_section(
            'setting_section_headings', // ID
             $this->section_title('Headings'), // Title
            array( $this, 'print_section_headings' ), // Callback
            'upcast-setting-admin' // Page
        );  

        add_settings_section(
            'setting_section_templates', // ID
             $this->section_title('Templates'), // Title
            array( $this, 'print_section_templates' ), // Callback
            'upcast-setting-admin' // Page
        );  

        add_settings_field(
            'rss_link', // ID
            'Podcast link', // Title 
            array( $this, 'rss_link_callback' ), // Callback
            'upcast-setting-admin', // Page
            'setting_section_defaults' // Section           
        );      

        add_settings_field(
            'rss_max', // ID
            'Maximum items', // Title 
            array( $this, 'rss_max_callback' ), // Callback
            'upcast-setting-admin', // Page
            'setting_section_defaults' // Section           
        );      

        add_settings_field(
            'rss_future', // ID
            'Future items', // Title 
            array( $this, 'rss_future_callback' ), // Callback
            'upcast-setting-admin', // Page
            'setting_section_defaults' // Section           
        );      

        add_settings_field(
            'rss_files_only', // ID
            'Only items with files', // Title 
            array( $this, 'rss_files_only_callback' ), // Callback
            'upcast-setting-admin', // Page
            'setting_section_defaults' // Section           
        );      

        add_settings_field(
            'rss_date_format', // ID
            'Date format', // Title 
            array( $this, 'rss_date_format_callback' ), // Callback
            'upcast-setting-admin', // Page
            'setting_section_defaults' // Section           
        );      

        add_settings_field(
            'rss_time_zone', // ID
            'Timezone', // Title 
            array( $this, 'rss_time_zone_callback' ), // Callback
            'upcast-setting-admin', // Page
            'setting_section_defaults' // Section           
        );      

        add_settings_field(
            'rss_header', // ID
            'Headings', // Title 
            array( $this, 'rss_header_callback' ), // Callback
            'upcast-setting-admin', // Page
            'setting_section_defaults' // Section           
        );      

		foreach ($this->filter_field_names as $field) {
			add_settings_field(
				'filter_'.$field, 
				ucwords($field), 
				array( $this, 'filter_callback' ), 
				'upcast-setting-admin', 
				'setting_section_filters',
				array('field'=>$field)
			);      
		}

		foreach (array_diff($this->field_names, $this->hide_field_names) as $field) {
			add_settings_field(
				'column_'.$field, 
				ucwords($field), 
				array( $this, 'column_callback' ), 
				'upcast-setting-admin', 
				'setting_section_columns',
				array('field'=>$field)
			);      
			add_settings_field(
				'header_'.$field, 
				ucwords($field), 
				array( $this, 'header_callback' ), 
				'upcast-setting-admin', 
				'setting_section_headings',
				array('field'=>$field)
			);      
			add_settings_field(
				'template_'.$field, 
				ucwords($field), 
				array( $this, 'template_callback' ), 
				'upcast-setting-admin', 
				'setting_section_templates',
				array('field'=>$field)
			);      						
		}

        add_settings_field(
            'template_row',
            'Row template',
            array( $this, 'template_row_callback' ), 
            'upcast-setting-admin', 
            'setting_section_templates'
		);

        add_settings_field(
            'template_rss',
            'RSS template',
            array( $this, 'template_rss_callback' ), 
            'upcast-setting-admin', 
            'setting_section_templates'
		);
		
        add_settings_field(
            'analytics_source',
            'Source',
            array( $this, 'analytics_source_callback' ), 
            'upcast-setting-admin', 
            'setting_section_analytics'
		);
				
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
		//error_log('unsanitized='.print_r($input, true));		
        $new_input = array();
		
        if( isset( $input['rss_max'] ) )
            $new_input['rss_max'] = absint( $input['rss_max'] );

		$t = new DateTime();
        if(isset($input['rss_date_format']) && $t->format($input['rss_date_format']) !== FALSE)
            $new_input['rss_date_format'] = $input['rss_date_format'];


		foreach (array('rss_link','rss_time_zone','analytics_source') as $field) {
			if( isset( $input[$field] ) )
				$new_input[$field] = sanitize_text_field( $input[$field] );
		}

		foreach ($this->filter_field_names as $name) {
			$field = 'filter_'.$name;
			if( isset( $input[$field] ) )
				$new_input[$field] = sanitize_text_field( $input[$field] );
		}

		foreach (array('rss_future','rss_files_only','rss_header') as $field) {
			if(isset($input[$field]))
				$new_input[$field] = strtolower($input[$field]) == 'on' ? 'on' : '';
		}

		foreach ($this->field_names as $name) {
			$field = 'column_'.$name;
			if(isset($input[$field]))
				$new_input[$field] = strtolower($input[$field]) == 'on' ? 'on' : '';
			$field = 'header_'.$name;
			if(isset($input[$field]))
				$new_input[$field] = sanitize_text_field($input[$field]);
			$field = 'template_'.$name;
			if(isset($input[$field]))
				$new_input[$field] = $input[$field];
		}
		
		//error_log('sanitized='.print_r($new_input, true));
        return $new_input;
    }

    /** 
     * Print the Section text
     */
	 public function print_section_header($desc) {
		 print '<div class="upcast-section-header"><div class="upcast-section-header-desc">'.$desc.'</div><div class="upcast-section-header-save">';
		 submit_button();
		 print '</div></div>';
	 }
	 
    public function print_section_defaults()
    {
        $this->print_section_header('Default settings when using the [upcast] shortcode on a page or post.<br><br>You can override these by using the shortcode attribute listed beside each setting e.g. <code>[upcast attribute="value"]</code>');
    }

    public function print_section_filters()
    { 
        $this->print_section_header('Restrict the displayed items to those matching the following values.<br><br>You can use <a href="http://php.net/manual/en/reference.pcre.pattern.syntax.php">regular expression syntax</a> for pattern matching e.g. <code>Jon.*</code><br><br>You can override these by using the name of the filter as a shortcode attribute e.g. <code>[upcast author="Jon G"]</code>');
    }

    public function print_section_columns()
    {
        $this->print_section_header('Select the columns to display by default.<br><br>You can override these by specifying the columns as a shortcode attribute e.g. <code>[upcast columns="date,author,size,link"]</code>');
    }

    public function print_section_headings()
    {
        $this->print_section_header('Enter an alternate heading to use for individual columns.<br><br>You can override these by specifying headings as a shortcode attribute on a page or post, in which case they must match the number of columns checked above e.g. <code>[upcast headers="Date,Author,Size,Link"]</code>');
    }

    public function print_section_templates()
    {
        $this->print_section_header('Enter a template value to use for individual columns or the whole row, including HTML.<br><br>Put [column] where you would like the value of any column substituted e.g. <code>'.htmlentities('<a href="[link]">[title]</a>').'</code>');
    }

    public function print_section_analytics()
    {
        $this->print_section_header('Set how podcast links clicked on from this website will be categorised in analytics on <a href="https://upcast.me">upcast.me</a>.<br><br>You can override this on a page or post by specifying the source as a shortcode attribute e.g. <code>[upcast source="website"]</code>');
    }

    /** 
     * Get the settings option array and print one of its values
     */
	public function print_input_text($type, $id, $placeholder = '', $class='upcast') {
        printf('<input class="%s" type="%s" id="%s" name="upcast_options[%s]" placeholder="%s" value="%s" />',
			$class, $type, $id, $id, $placeholder, isset( $this->options[$id] ) ? esc_attr( $this->options[$id]) : '');
	}	 

	public function print_checkbox($id, $class='upcast') {
        printf('<input class="%s" type="checkbox" id="%s" name="upcast_options[%s]" %s />',
			$class, $id, $id, isset( $this->options[$id] ) ? ($this->options[$id] == 'on' ? 'CHECKED' : '') : '');
	}

	public function print_textarea($id, $placeholder = '', $class='upcast') {
        printf('<textarea class="%s" id="%s" name="upcast_options[%s]" rows="3" placeholder="%s">%s</textarea>',
			$class, $id, $id, esc_attr($placeholder), isset( $this->options[$id] ) ? esc_attr($this->options[$id]) : '');
	}

	public function print_select($id, $values, $delimiter = '', $class='upcast') {
		$default = isset($this->options[$id]) ? $this->options[$id] : '';
        printf('<select class="%s" id="%s" name="upcast_options[%s]"><option value=""></option>', $class, $id, $id);
		$cur_group = '';
		foreach ($values as $value) {
			$att = ($value && $value == $default) ? 'SELECTED' : '';
			if ($delimiter != '') {
				$parsed = explode($delimiter, $value);
				if (count($parsed) > 1) {
					if ($parsed[0] != $cur_group) {
						if ($cur_group != '') {
							printf('</optgroup>');
						}
						$cur_group = $parsed[0];
						printf('<optgroup label="%s">', $cur_group);
					}
					$label = implode($delimiter, array_slice($parsed,1));
				} else {
					$label = $value;
				}
			} else {
				$label = $value;
			}
		    printf('<option value="%s" %s>%s</option>', $value, $att, str_replace('_',' ',$label));
		}
		if ($delimiter != '' && $cur_group != '')
			printf('</optgroup>');
		printf('</select>');
	}
	
    public function rss_link_callback() { $this->print_input_text('text', 'rss_link'); echo '&nbsp;&nbsp;(feed)'; } 
    public function rss_max_callback() { $this->print_input_text('number', 'rss_max'); echo '&nbsp;&nbsp;(max)'; } 
	public function rss_future_callback() { $this->print_checkbox('rss_future'); echo '&nbsp;&nbsp;(future)'; } 
	public function rss_files_only_callback() { $this->print_checkbox('rss_files_only'); echo '&nbsp;&nbsp;(files)'; } 
    public function rss_date_format_callback() { $this->print_input_text('text', 'rss_date_format', 'e.g. M j, Y'); echo '&nbsp;&nbsp;(dates)&nbsp;&nbsp;<a href="http://php.net/manual/en/function.date.php">valid formats</a>'; } 
    public function rss_time_zone_callback() { $this->print_select('rss_time_zone', timezone_identifiers_list(), '/'); echo '&nbsp;&nbsp;(zone)'; } 
	public function rss_header_callback() { $this->print_checkbox('rss_header'); echo '&nbsp;&nbsp;(header)'; } 

    public function filter_callback($args) { $this->print_input_text('text', 'filter_' . $args['field']); } 
    public function column_callback($args) { $this->print_checkbox('column_' . $args['field']); } 
    public function header_callback($args) { $this->print_input_text('text', 'header_' . $args['field']); } 
    public function template_callback($args) { $this->print_input_text('text', 'template_' . $args['field'], 'e.g. <p>['.$args['field'].']</p>', 'upcast-template'); } 
    public function template_row_callback() { 
		print('This template overrides all the individual templates above to specify a complete row to be repeated for each podcast item.<br><br>You can override this on a page or post by enclosing shortcode content e.g. <code>[upcast]...[/upcast]</code><br><br>Additional fields you can use here are ['.implode('], [', $this->hide_field_names).']<br><br>');
		$this->print_textarea('template_row', htmlentities('e.g. <td>[title]</td><td><a href="[link]">[date]</a></td>'), 'upcast-template'); 
	} 
    public function template_rss_callback() { 
		print('Set how the [upcast_rss] shortcode in a page or post will display by default.<br><br>You can override this by enclosing shortcode content e.g. <code>[upcast_rss]...[/upcast_rss]</code><br><br>Valid shortcodes for RSS feeds are ['.str_replace(',','], [',UPCAST_RSS_FIELD_NAMES).'] and if the feed has an enclosed image ['.str_replace(',','], [',UPCAST_IMAGE_FIELD_NAMES).']<br><br>');
		$this->print_textarea('template_rss', htmlentities('e.g. <h2>[title]</h2><img src="[image_link]">'), 'upcast-template'); 
	}
    public function analytics_source_callback() { $this->print_input_text('text', 'analytics_source'); }

}
?>