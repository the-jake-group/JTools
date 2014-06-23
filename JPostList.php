<?php
class J_PostList {
	public static $chunks = array();

	public $query_object;
	public $array;

	private $html;

	private $html_list_tag = "ul";
	private $html_list_id;
	private $html_list_class;
	private $html_post_format;
	private $chunk_filters = array();

	private $no_posts_message = "No items could be found.";
	private $show_no_posts_message = false;

	private $post_classes = array();
	private $post_options = array();
	private $variable_post_classes;
	private $variable_post_options;
	private $post_index = 0;

	private $has_chunks = false;

	public function __construct( $query = '', $options = null, $post_array = false ) {
		if( is_a( $query, 'WP_Query') ) {
			$this->array = $query->posts;
		} elseif ($post_array) {
			$this->array = $query;
		} else {
			$this->query_object = new J_WP_Query( $query );
			$this->array        = $this->query_object->posts;
		}

		if( ! is_null( $options ) && is_array( $options ) ) {
			
			if( isset( $options['post_format'] ) ) {
				$this->html_post_format = $options['post_format'];
			}

			if(isset( $options['list_class'] ) ) {
				$this->html_list_class = $options['list_class'];
			}

			if(isset( $options['list_id'] ) ) {
				$this->html_list_id = $options['list_id'];
			}

			if(isset( $options['list_tag'] ) ) {
				$this->html_list_tag = $options['list_tag'];
			}

			if(isset( $options['show_no_posts_message'] ) ) {
				$this->show_no_posts_message = $options['show_no_posts_message'];
			}

			if(isset( $options['no_posts_message'] ) ) {
				$this->no_posts_message = $options['no_posts_message'];
			}
		}

		$this->post_classes = array(); 
		$this->variable_post_classes = array(); 
	}

	public function __destruct() {
		wp_reset_postdata();
	}

	public function __call( $method_name, $arguments ) {
		if( isset(self::$chunks[ $method_name ] ) ) {
			array_unshift( $arguments, $this );
			return call_user_func_array( self::$chunks[ $method_name ], $arguments );
		} else {
			return false;
		}
	}

	public static function register_chunk( $name, $lambda ) {
		self::$chunks[ $name ] = $lambda;
	}

	public function set_chunk_filters($size, $chunk) {
		$this->chunk_filters[$size] = $chunk;
	}

	public function set_chunk($name, $size) {
		$this->chunk["name"] = $name;
		$this->chunk["size"] = $size;
		$this->has_chunks   = true;
	}

	public function set_post_classes( $class_array ) {
		$this->post_classes = array_merge( $this->post_classes, $class_array );
	}

	public function set_variable_post_class( $function, $index = null ) {
		if( is_null( $index ) ) {
			return array_push( $this->variable_post_classes, $function ); 
		} else {
			return $this->variable_post_classes[ $index ] = $function;
		}
	}

	public function set_post_options( $options_array ) {
		$this->post_options = array_merge( $this->post_options, $options_array );
	}	

	public function set_variable_post_option( $function, $index = null ) {
		if( is_null( $index ) ) {
			return array_push( $this->variable_post_options, $function ); 
		} else {
			return $this->variable_post_options[ $index ] = $function;
		}
	}

	public function add_list_class($classes, $override = false) {
		if (is_array($classes)) {
			$class = implode(" ", $classes);
		}

		$this->html_list_class = !$override ? $this->html_list_class." ".$classes : $classes;
	}

	public function max_num_pages() {
		return $this->query_object->max_num_pages;
	}

	private function add_to_html_output( $string ) {
		$this->html .= $string;
	}
	
	public function to_html( $wrapper = true ) {
		if( $this->have_posts() || !empty($this->array) ) {
			$this->add_to_html_output( $this->maybe_chunk( $wrapper ) );
		} elseif( $this->show_no_posts_message ) {
			$this->add_to_html_output( $this->no_posts_message() );
		}
		wp_reset_postdata();
		return $this->html;
	}

	public function __toString() {
		return $this->to_html();
	}

	public function pagination_to_html( $paged = null ) {
		//TODO: refactor the crap out of this, obviously... maybe accept another lambda for formatting pagination?
		if( is_null( $paged ) ) $paged = $this->query_object->query_vars['paged'];
		if ($this->max_num_pages() > 1) : ?>
			<nav id="post-nav">
				<ul class="pager">
					<?php if ($prev_link = $this->get_previous_posts_link( $paged, __('&lsaquo; PREV', 'roots'))) : ?>
						 <li class="previous"><?php echo $prev_link; ?></li>
					<?php else: ?>
						<li class="previous disabled"><a><?php _e('&lsaquo; PREV', 'roots'); ?></a></li>
					<?php endif; ?>
					<?php if ($next_link = $this->get_next_posts_link( $paged, __('NEXT &rsaquo;', 'roots'))) : ?>
						<li class="next"><?php echo $next_link; ?></li>
					<?php else: ?>
						<li class="next disabled"><a><?php _e('NEXT &rsaquo;', 'roots'); ?></a></li>
					<?php endif; ?>
				</ul>
			</nav>
		<?php endif;
	}

	public function get_next_posts_link( $paged, $label = null, $max_page = 0 ) {
		if ( !$max_page )
			$max_page = $this->max_num_pages();

		if ( !$paged )
			$paged = 1;

		$nextpage = intval($paged) + 1;

		if ( null === $label )
			$label = __( 'Next Page &raquo;' );

		if ( ( $nextpage <= $max_page ) ) {
			$attr = apply_filters( 'next_posts_link_attributes', '' );
			return '<a href="' . next_posts( $max_page, false ) . "\" $attr>" . preg_replace('/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $label) . '</a>';
		}
	}

	public function get_previous_posts_link( $paged, $label = null ) {
		if ( null === $label )
			$label = __( '&laquo; Previous Page' );

		if ( !is_single() && $paged > 1 ) {
			$attr = apply_filters( 'previous_posts_link_attributes', '' );
			return '<a href="' . previous_posts( false ) . "\" $attr>". preg_replace( '/&([^#])(?![a-z]{1,8};)/i', '&#038;$1', $label ) .'</a>';
		}
	}

	private function add_post_classes( &$jpost ) {
		if( ! empty( $this->post_classes ) ) {
			foreach( $this->post_classes as $class_index => $class_name ) {
				if( is_int( $class_index ) ) {
					$jpost->add_class( $class_name );
				} elseif( is_numeric( str_replace('post', '', $class_index ) ) ) {
					if( str_replace('post', '', $class_index ) == $this->post_index ) {
						$jpost->add_class( $class_name );
					}
				} else {
					$jpost->add_class( $class_name, $class_index );
				}
			}
		}

		if( ! empty( $this->variable_post_classes ) ) {
			foreach( $this->variable_post_classes as $class_index => $function ) {
				if( is_numeric( $class_index ) ) {
					$jpost->add_class( call_user_func_array( $function, array( $jpost ) ) );
				} else {
					$jpost->add_class( call_user_func_array( $function, array( $jpost ) ), $class_index );
				}
			}
		}

	}

	private function add_post_options( &$jpost ) {
		if( ! empty( $this->post_options ) ) {
			foreach( $this->post_options as $index => $option ) {
				if( is_numeric(str_replace('post', '', $index)) 
					&& str_replace('post', '', $index ) == $this->post_index ) {
						$jpost->add_option( $option );
				} else {
					$jpost->add_option( $index, $option );
				}
			}
		}
		if( ! empty( $this->variable_post_options ) ) {
			foreach( $this->variable_post_options as $index => $function ) {
				if( is_numeric( $index ) ) {
					$jpost->add_option( call_user_func_array( $function, array( $jpost ) ) );
				} else {
					$jpost->add_option($index, call_user_func_array( $function, array($jpost) ));
				}
			}
		}

	}

	private function maybe_chunk( $wrapper ) {
		$output = null;
		if ($this->has_chunks) {
			$chunks = array_chunk($this->array, $this->chunk["size"]);
			foreach ($chunks as $index => $chunk) {
				$output .= call_user_func_array( 
					self::$chunks[ $this->chunk["name"] ], 
					array(
						$this->list_to_html( $chunk, $wrapper ),
						$index
					) 
				);
			}
		} else {
			$output = $this->list_to_html( $this->array, $wrapper );
		}
		return $output;
	}

	private function list_to_html( $array, $wrapper ) {
		if( $wrapper ) {
			return $this->html_open_list_tag() . $this->list_items_to_html($array) . $this->html_close_list_tag(); 
		} else {
			return $this->list_items_to_html($array);
		}
	}

	private function list_items_to_html( $posts ) {
		$return = null;
		// if (!empty($this->array)) {
		if ( true ) {
			foreach($posts as $index => $post_obj) {
				global $post;
				$new_post = new J_Post( $post_obj );
				$this->add_post_classes( $new_post ); 
				$this->add_post_options( $new_post ); 
				$return .= $this->list_item_to_html( $new_post );
				$this->post_index++;
			}
		} else {
			while ( $this->have_posts() ):
				$return .= $this->the_post(); global $post; $new_post = new J_Post( $post );
				$this->add_post_classes( $new_post ); 
				$this->add_post_options( $new_post ); 
				$return .= $this->list_item_to_html( $new_post );
				$this->post_index++;
			endwhile;
		}
		return $return;
	}

	private function list_item_to_html( $list_item ) {
		return $list_item->to_html( $this->html_post_format );
	}

	private function html_open_list_tag() {
		$html_attributes = array();
		if( ! is_null( $this->html_list_id ) ) $html_attributes['id'] = $this->html_list_id;
		if( ! is_null( $this->html_list_class ) ) $html_attributes['class'] = $this->html_list_class;
		return $this->html_open_tag( $this->html_list_tag, $html_attributes );
	}

	private function html_close_list_tag() {
		return $this->html_close_tag( $this->html_list_tag );
	}

	private function html_open_list_item_tag( $html_attributes = null ) {
		return $this->html_open_tag( 'li', $html_attributes );
	}

	private function html_close_list_item_tag() {
		return $this->html_close_tag( 'li' );
	}

	private function html_open_tag( $tag, $html_attributes ) {
		return "<$tag "
			. ((isset($html_attributes['class'])) ? "class=\"{$html_attributes['class']}\" " : "")
			. ((isset($html_attributes['id'])) ? "id=\"{$html_attributes['id']}\" " : "")
			. ">";
	}

	private function html_close_tag( $tag ) {
		return "</$tag>";
	}

	private function no_posts_message() {
		return $this->no_posts_message ? "<div class=\"alert\">{$this->no_posts_message}</div>" : "<div class=\"alert\">No items could be found.</div>";
	}


	// Just for Decoupling

	public function have_posts() {
		return $this->query_object ? $this->query_object->have_posts() : false;
	}

	private function the_post() {
		return $this->query_object->the_post();
	}
}
