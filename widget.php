<?php
include_once('settings.php');

/**
 * Adds Foo_Widget widget.
 */
class UpCast_Widget extends WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
			'upcast_widget', // Base ID
			__( 'UpCast', 'text_domain' ), // Name
			array( 'description' => __( 'Display a list of episodes from a podcast feed', 'text_domain' ), ) // Args
		);
		wp_register_style('upcastStylesheet', plugins_url('style.css', __FILE__) );
	    wp_enqueue_style('upcastStylesheet');
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		//error_log('args='.print_r($args, true));
		//error_log('instance='.print_r($instance, true));
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
		}

		$options = get_option('upcast_options');
		$system_date_format = 'M j, Y'; 
		$columns = '';
		foreach (explode(',', UPCAST_WIDGET_FIELD_NAMES) as $field) {
			if (isset($instance['column_'.$field]) && $instance['column_'.$field] == 'on') {
				if ($columns != '') {
					$columns .= ',';
				}
				$columns .= $field;
			}
		}
		if ($columns == '') {
			$columns = UPCAST_DEFAULT_WIDGET_COLUMNS;
		}
		$columns = explode(',',$columns);
		
		if (!isset($instance['dates']) || !$instance['dates']) {
			$instance['dates'] = (isset($options['rss_date_format']) && $options['rss_date_format']) ? 
									$options['rss_date_format'] : $system_date_format;
		}
		
		$instance['zone'] = (isset($options['rss_time_zone']) && $options['rss_time_zone']) ? 
								$options['rss_time_zone'] : (get_option('timezone_string', false) ? 
														 	 	get_option('timezone_string') :  
														 		date_default_timezone_get());
	
		$old_tz = date_default_timezone_get();
		date_default_timezone_set($instance['zone']);
		
		if (!isset($instance['sort'])) {
			$instance['sort'] = ((isset($instance['future']) && $instance['future'] == 'on') ? '+' : '-') . 'pubdate';
		}
		
		# If no feed, return with nothing
		if (!$instance['feed'])
			return;
			
		//error_log('feed1='.$instance['feed']);
		$feed = modify_upcast_url($instance['feed'], $instance['max'], $instance['future'], $instance['files'], $instance['source']);
		//error_log('feed2='.$feed);
		# Create loop content
		$rss = new SimplePie_upcast();
		$rss->set_feed_url($feed);
		$rss->set_cache_class('WP_Feed_Cache');
		$rss->set_file_class('WP_SimplePie_File');
		if (isset($_GET['sp']) && $_GET['sp']=='flush')
			$rss->set_cache_duration(apply_filters('wp_feed_cache_transient_lifetime', 0));
		else
			$rss->set_cache_duration(apply_filters('wp_feed_cache_transient_lifetime', 43200));
		$rss->init();
		$rss->handle_content_type();
		$rss->set_sortorder($instance['sort']);
		
		if (($rss_author = $rss->get_author()) != FALSE)
			$rss_author_name = $rss_author->get_name();
		else
			$rss_author_name = '';
		
		$maxitems = $rss->get_item_quantity();
		
		$output = '<div class="upcast-widget-list">';
	
		$rss_items = $rss->get_items(0, $maxitems);
		
		$rsscount = 0;

		$content = '';
					
		if ($content == '') {
			if (isset($instance['template']) && $instance['template']) {
				$content = $instance['template'];
			} else {
				$content = '<div class="upcast-widget-cell">['.implode(']</div><div class="upcast-widget-cell">[',$columns).']</div>';
			}
		}

		// Allow for a surrounding table to be consistent with shortcode template rows
		$matches = array();
		$thead = '';
		if (preg_match('/\s*<table>\s*(<thead>.*<\/thead>)?\s*(<tbody>)?\s*(<tr>)?\s*(.*?)\s*(<\/tr>)?\s*(<\/tbody>)?\s*<\/table>\s*/mis', $content, $matches)) {
			//error_log('matches='.print_r($matches, true));
			if (isset($matches[1])) {
				$thead = $matches[1];
			}
			$content = $matches[4];
		}
		
		$content = '<div class="upcast-widget-row">'.$content.'</div>';
				
		// Pre-find all the substitutions and locations in the content to be repeated for each row
		$matches = array();
		preg_match_all('/\[('.str_replace(',','|',UPCAST_WIDGET_FIELD_NAMES).')\]/i', $content, $matches, PREG_OFFSET_CAPTURE);
		
		foreach ($rss_items as $item)
		{
			//error_log('content:"'.$content.'"');
			$selected = true;
			
			$item_enclosure = $item->get_enclosure();
			$item_author = $item->get_author();
			$item_category = $item->get_category();
			$item_subject = $item->get_item_tags(SIMPLEPIE_NAMESPACE_DC_11,'subject');
			$item_image = $item->get_item_tags(SIMPLEPIE_NAMESPACE_ITUNES,'image');
			
			if ($selected && $instance['filter_category'] && $item_category) $selected = preg_match_simple($instance['filter_category'],$item_category->get_label());
			if ($selected && $instance['filter_description']) $selected = preg_match_simple($instance['filter_description'],$item->get_description());
			if ($selected && $instance['filter_title']) $selected = preg_match_simple($instance['filter_title'],$item->get_title());
			if ($selected && $instance['filter_subject'] && is_array($item_subject) && count($item_subject)) $selected = preg_match_simple($instance['filter_subject'],$item_subject[0]['data']);		
			if ($dates) {
				if ($selected && $instance['filter_pubdate']) $selected = preg_match_simple($instance['filter_pubdate'],$item->get_gmdate($dates));
				if ($selected && $instance['filter_date']) $selected = preg_match_simple($instance['filter_date'],$item->get_local_date($dates));
			}
			else {
				if ($selected && $instance['filter_pubdate']) $selected = preg_match_simple($instance['filter_pubdate'],$item->get_gmdate());
				if ($selected && $instance['filter_date']) $selected = preg_match_simple($instance['filter_date'],$item->get_local_date());
			}
			if ($selected && $instance['filter_author'] && $item_author) $selected = preg_match_simple($instance['filter_author'],$item_author->get_name());
									
			if ($selected)
			{
				$rsscount++;
				if (isset($instance['max']) && $instance['max'] && $rsscount > $instance['max'])
					break;
					
				if (isset($matches[1])) {
					$row = $content;
					// work through in reverse so substitutions do not affect earlier offsets
					for ($f = count($matches[1]) - 1; $f >= 0; $f--) {
						$text = '&nbsp;';
						switch ($matches[1][$f][0]) {
							case 'count':
								$text = $rsscount;
								break;
							case 'category':
								if ($item_category) $text = $item_category->get_label();
								break;
							case 'subject':
								if (is_array($item_subject) && count($item_subject)) $text = $item_subject[0]['data'];
								break;
							case 'description':
								$text = $item->get_description();
								break;
							case 'title':
								$text = '<strong>'.($item->get_title() ? $item->get_title() : 'Untitled').'</strong>';
								break;
							case 'date':
								try {
									$t = date_create_from_format('Y-m-d G:i:s', $item->get_local_date('%G-%m-%d %T'));							
									if ($t !== FALSE)
										$text = $t->format($instance['dates']);
								} catch (Exception $e) {
									error_log($e->getMessage());
									// Do nothing, text just defaults to a space
								}
								break;
							case 'pubdate':
								try {
									$t = date_create_from_format('Y-m-d G:i:s', $item->get_date('Y-m-d G:i:s'));							
									if ($t !== FALSE)
										$text = $t->format($instance['dates']);
								} catch (Exception $e) {
									error_log($e->getMessage());
									// Do nothing, text just defaults to a space
								}
								break;
							case 'author':
								if ($item_author && $item_author->get_name() != $rss_author_name) $text = $item_author->get_name();
								break;
							case 'url':
								$link = $item->get_link();
								if ($link)
									$text = $item->get_link();
								break;								
							case 'link':
								$link = $item->get_link();
								if ($link)
									$text = '<strong><a href="'.$item->get_link().'">'.($item->get_title() ? $item->get_title() : 'Untitled').'</a></strong>';
								break;
							case 'content':
								$text = $item->get_content();
								break;
							case 'guid':
								$text = $item->get_id();
								break;
							case 'thumbnail':
								if ($item_image) $text = '<img src="'.$item_image[0]['attribs']['']['href'].'">';
								break;
							case 'embed':
								if ($item_enclosure) $text = $item_enclosure->embed();
								break;
							case 'bitrate':
								if ($item_enclosure) $text = $item_enclosure->get_bitrate();
								break;
							case 'duration':
								if ($item_enclosure) $text = $item_enclosure->get_duration(true);
								break;
							case 'sampling_rate':
								if ($item_enclosure) $text = $item_enclosure->get_sampling_rate();
								break;
							case 'native':
								if ($item_enclosure) $text = $item_enclosure->native_embed();
								break;
							case 'type':
								if ($item_enclosure) $text = $item_enclosure->get_type();
								break;
							case 'size':
								if ($item_enclosure) $text = number_format($item_enclosure->get_size(),1).' MB';
								break;
							case 'real_type':
								if ($item_enclosure) $text = $item_enclosure->get_real_type();
								break;
							case 'embed':
								if ($item_enclosure) $text = $item_enclosure->embed();
								break;		
						}
						$row = substr_replace($row, $text, $matches[1][$f][1] - 1, strlen($matches[1][$f][0]) + 2);
					}
					$output .= $row;
				} else {
					$output .= $content;
				}
			}
		}
		
		$output .= '</div>';

		echo do_shortcode($output);
				
		date_default_timezone_set($old_tz);
		
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		//error_log('instance='.print_r($instance, true));
		$options = get_option('upcast_options');	
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Feed Title', 'text_domain' );
		$feed = ! empty( $instance['feed'] ) ? $instance['feed'] : __( (isset($options['rss_link']) ? $options['rss_link'] : ''), 'text_domain' ) ;
		$max = ! empty( $instance['max'] ) ? $instance['max'] : __( (isset($options['rss_max']) ? $options['rss_max'] : '6'), 'text_domain' );
		$future = ! empty( $instance['future'] ) ? $instance['future'] : __( '', 'text_domain' );
		$files = ! empty( $instance['files'] ) ? $instance['files'] : __( '', 'text_domain' );
		$dates = ! empty( $instance['dates'] ) ? $instance['dates'] : __( '', 'text_domain' );
		$source = ! empty( $instance['source'] ) ? $instance['source'] : __( (isset($options['analytics_source']) ? $options['analytics_source'] : ''), 'text_domain' );		
		$template = ! empty( $instance['template'] ) ? $instance['template'] : __( '', 'text_domain' ) ;
		?>
		<div class="upcast-widget-header-logo">
          	<a href="https://upcast.me"><img src="<?=plugins_url('UpCastPPHeader.png', __FILE__)?>"></a>
        </div>
        <br>
		<p class="upcast-widget">
		<label class="upcast-widget-label" for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p class="upcast-widget">
		<label class="upcast-widget-label" for="<?php echo $this->get_field_id( 'feed' ); ?>"><?php _e( 'Feed:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'feed' ); ?>" name="<?php echo $this->get_field_name( 'feed' ); ?>" type="text" value="<?php echo esc_attr( $feed ); ?>">
		</p>
        <table class="upcast-widget"><tbody>
        <tr>
        	<td><label class="upcast-widget-label" for="<?php echo $this->get_field_id( 'max' ); ?>"><?php _e( 'Maximum items:' ); ?></label></td>
			<td><input id="<?php echo $this->get_field_id( 'max' ); ?>" name="<?php echo $this->get_field_name( 'max' ); ?>" type="number" value="<?php echo esc_attr( $max ); ?>"></td>
		</tr>
        <tr>
			<td><label class="upcast-widget-label" for="<?php echo $this->get_field_id( 'future' ); ?>"><?php _e( 'Future items' ); ?></label></td>
			<td><input class="widefat" id="<?php echo $this->get_field_id( 'future' ); ?>" name="<?php echo $this->get_field_name( 'future' ); ?>" type="checkbox" <?php echo $future == 'on' ? 'CHECKED' : ''; ?>></td>
        </tr>
        <tr>
			<td><label class="upcast-widget-label" for="<?php echo $this->get_field_id( 'files' ); ?>"><?php _e( 'Only items with files' ); ?></label></td>
			<td><input class="widefat" id="<?php echo $this->get_field_id( 'files' ); ?>" name="<?php echo $this->get_field_name( 'files' ); ?>" type="checkbox" <?php echo $files == 'on' ? 'CHECKED' : ''; ?>></td>
		</tr>
        <tr>
        	<td><label class="upcast-widget-label" for="<?php echo $this->get_field_id( 'dates' ); ?>"><a href="http://php.net/manual/en/function.date.php">Date format</a></label></td>
			<td><input class="upcast-widget-input-narrow" id="<?php echo $this->get_field_id( 'dates' ); ?>" name="<?php echo $this->get_field_name( 'dates' ); ?>" type="text" value="<?php echo esc_attr( $dates ); ?>" placeholder="e.g. M j, Y">&nbsp;&nbsp;</td>
		</tr>        
        <tr>
        	<td><label class="upcast-widget-label" for="<?php echo $this->get_field_id( 'source' ); ?>"><?php _e( 'Analytics source' ); ?></label></td>
			<td><input class="upcast-widget-input-narrow" id="<?php echo $this->get_field_id( 'source' ); ?>" name="<?php echo $this->get_field_name( 'source' ); ?>" type="text" value="<?php echo esc_attr( $source ); ?>" placeholder="e.g. widget"></td>
		</tr>        
        </tbody></table>
        <div class="upcast-widget">
        <div class="upcast-widget-section-header">
            <label class="upcast-widget-label"><?php _e( 'Filters:' ); ?></label>
            <a id="upcast-widget-filter-syntax" href="http://php.net/manual/en/reference.pcre.pattern.syntax.php">filter syntax</a>
        </div>
        <table class="upcast-widget"><tbody>
		<?php foreach (explode(',',UPCAST_FILTER_FIELD_NAMES) as $field) { $column = 'filter_' . $field; ?>
           <tr>
           	<td><label for="<?php echo $this->get_field_id($column); ?>"><?php _e(ucwords($field)); ?></label></td>
            <td><input type="text" id="<?php echo $this->get_field_id($column);?>" name="<?php echo $this->get_field_name($column); ?>" placeholder="e.g. foo.*" value="<?php echo esc_attr(isset($instance[$column]) ? $instance[$column] : ''); ?>"></td>
           </tr>
        <?php } ?>
        </tbody></table>
        </div>        
        <div class="upcast-widget">
        <label class="upcast-widget-label"><?php _e( 'Columns:' ); ?></label>
            <div class="upcast-widget-settings-list">
            <?php foreach (explode(',',UPCAST_WIDGET_FIELD_NAMES) as $field) { $column = 'column_' . $field; ?>
                <label for="<?php echo $this->get_field_id($column); ?>"><input type="checkbox" id="<?php echo $this->get_field_id($column);?>" name="<?php echo $this->get_field_name($column);?>" <?=(isset($instance[$column]) && $instance[$column] == 'on') ? 'CHECKED' : ''?>>&nbsp;<?php _e(ucwords($field)); ?></label><br>
            <?php } ?>
            </div>
        </div>
		<p class="upcast">
		<label for="<?php echo $this->get_field_id( 'template' ); ?>"><?php _e( 'Template:' ); ?></label><br>
		<textarea class="upcast-widget widefat" rows="4" class="widefat" id="<?php echo $this->get_field_id( 'template' ); ?>" name="<?php echo $this->get_field_name( 'template' ); ?>" type="text" placeholder="e.g. [date]:&nbsp;<a href=&quot;[link]&quot;>[title]</a>"><?php echo htmlentities( $template ); ?></textarea>
		</p>
		<?php 
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		//error_log('new_instance='.print_r($new_instance, true));
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['feed'] = ( ! empty( $new_instance['feed'] ) ) ? $new_instance['feed'] : '';
		$instance['max'] = ( ! empty( $new_instance['max'] ) ) ? abs($new_instance['max']) : 6;
		$instance['future'] = ( ! empty( $new_instance['future'] ) ) ? $new_instance['future'] : '';
		$instance['files'] = ( ! empty( $new_instance['files'] ) ) ? $new_instance['files'] : '';
		$instance['dates'] = ( ! empty( $new_instance['dates'] ) ) ? $new_instance['dates'] : '';
		$instance['source'] = ( ! empty( $new_instance['source'] ) ) ? $new_instance['source'] : '';
		foreach (explode(',',UPCAST_FILTER_FIELD_NAMES) as $field) {
			$column = 'filter_' . $field;
			$instance[$column] = ( ! empty( $new_instance[$column] ) ) ? $new_instance[$column] : '';
		}
		foreach (explode(',',UPCAST_WIDGET_FIELD_NAMES) as $field) {
			$column = 'column_' . $field;
			$instance[$column] = ( ! empty( $new_instance[$column] ) ) ? $new_instance[$column] : '';
		}
		$instance['template'] = ( ! empty( $new_instance['template'] ) ) ? $new_instance['template'] : '';
		return $instance;
	}

} // class UpCast_Widget
?>