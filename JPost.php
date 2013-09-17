<?php
class J_Post {
	public static $tests;
	public static $partials;
	public static $formats;
	public static $meta_prefix = "";

	// For memoization
	private $post;
	private $permalink;
	private $meta_array;
	private $excerpt;

	private $options;
	private $classes;
	private $conditional_classes;


	public static function register_test( $name, $lambda ) {
		self::$tests[ $name ] = $lambda;
	}

	public static function register_partial( $name, $lambda ) {
		self::$partials[ $name ] = $lambda;
	}

	public static function register_format( $name, $lambda, $default = false ) {
		self::$formats[ $name ] = $lambda;
		
		if( $default )
			self::$formats[ 'default' ] = $lambda;
	}

	public static function set_meta_prefix( $prefix ) {
		self::$meta_prefix = $prefix;
	}	

	public function __construct( $post, $preload = null ) {
		if( ! is_a( $post, 'WP_Post' ) )
			throw new Exception( 'Argument must be of type Post' );

		if( is_null( self::$tests ) )
			self::$tests = array();

		if( is_null( self::$formats ) )
			self::$formats = array();

		$this->post = $post;
		$this->meta_array = array();
		$this->classes = array();

		if( ! is_null( $preload ) ) {
			$preloaders = array( 'meta_array', 'permalink', 'excerpt' );
			foreach( $preloaders as $preloader ) {
				if( isset( $preload[ $preloader ] ) && !is_null( $preload[ $preloader ] ) ) $this->$preloader = $preload[ $preloader ];
			}
		}
	}

	public function __call( $method_name, $arguments ) {
		if( isset(self::$tests[ $method_name ] ) ) {
			// If the called method is the name of a registered test
			array_unshift( $arguments, $this );
			return call_user_func_array( self::$tests[ $method_name ], $arguments );
		} elseif( isset(self::$partials[ $method_name ] ) ) {
			// If the called method is the name of a registered partial
			array_unshift( $arguments, $this );
			return call_user_func_array( self::$partials[ $method_name ], $arguments );
		} elseif( method_exists($this->post, $method_name ) ) {
			// If the called method is the name of a native WP_Post method
			return $this->post->$method_name( implode( ', ', $arguments ) );
		} else {
			return false;
		}
	}

	public function __get($name) {
		return $this->post->$name;
	}

	public function to_html( $format ) {
		if( is_string( $format ) && isset( self::$formats[ $format ] ) ) {
			return call_user_func_array( self::$formats[ $format ], array($this) );
		} elseif( is_null( $format ) || ( is_string( $format ) && isset( self::$formats[ 'default' ] ) ) ) {
			return call_user_func_array( self::$formats[ 'default' ], array($this) );
		} elseif( isset( self::$formats[ $format ] ) && is_callable( self::$formats[ $format ] ) ) {
			return $format( $this );
		} else {
			return false;
		}
	}

	public function add_class( $class_name, $index = null ) {
		if( is_null( $index ) ) {
			return array_push( $this->classes, $class_name );
		} else {
			return $this->classes[ $index ] = $class_name;
		}
	}

	public function add_option( $index, $option ) {
		return $this->options[$index] = $option;
	}

	// public function add_conditional_class( $lambda, $index = null ) {
	//   // if( is_null( $index ) ) {
	//   //   return array_push( $this->classes, $class_name );
	//   // } else {
	//   //   return $this->classes[ $index ] = $class_name;
	//   // }
	// }

	public function classes( $which = 'all' ) {
		if( empty( $this->classes ) )
			$this->classes = array();

		if( $which == 'all' ) {
			$classes = implode(' ', $this->classes );
		} elseif( isset( $this->classes[ $which ] ) ) {
			$classes = $this->classes[ $which ];
		} else {
			return false;
		}

		return $classes;
	}

	public function options( $index ) {
		if( empty($this->options) || !isset($this->options[$index]) ) {
			return false;
		}

		return $this->options[$index];
	}

	public function connected_posts( $connection_type = null, $additional_post_list_query_params = null, $post_list_options = null ) {
		if( is_null( $additional_post_list_query_params ) )
			$additional_post_list_query_params = array();

		if( is_null( $connection_type ) )
			$connection_type = 'posts_to_' . $this->post->post_type . 's';
		
		$query_parameters = array(
			'connected_type' => $connection_type,
			'connected_items' => $this->post, 
			'connected_direction' => 'to'
		);

		$query_parameters = array_merge( $query_parameters, $additional_post_list_query_params );
		return new J_PostList( $query_parameters, $post_list_options );
	}

	public function excerpt( $more_link_options = null, $excerpt_length = null, $show_more_link = true ) {
		if( ! isset( $this->excerpt ) )
			$this->excerpt = new J_Excerpt( $this->post, $more_link_options, $excerpt_length, false, $show_more_link );

		return $this->excerpt->display();
	}

	public function meta( $meta_key, $single = false, $meta_key_prefix = false ) {
		$meta_key_prefix = ($meta_key_prefix !== false) ? $meta_key_prefix : self::$meta_prefix;
		
		$meta_key = $meta_key_prefix . $meta_key;

		if( !isset( $this->meta_array[$meta_key] ) ) {
			$this->meta_array[$meta_key] = get_post_meta( $this->post->ID, $meta_key, $single );
		}
		return $this->meta_array[$meta_key];
	}


	public function permalink( $apply_filters = true ) {
		if( is_null( $this->permalink ) )
			$this->permalink = get_permalink( $this->post->ID );

		if( $apply_filters )
			$this->permalink = apply_filters('the_permalink', $this->permalink );
		
		return $this->permalink;
	}

	public function title( $apply_filters = true ) {
		$post_title = $this->post->post_title;
		if( $apply_filters )
			$post_title = apply_filters('the_title', $post_title);
		
		return $post_title;
	}

	public function content( $apply_filters = true ) {
		$post_content = $this->post->post_content;
		if( $apply_filters )
			$post_content = apply_filters('the_content', $post_content);
		
		return $post_content;
	}

	public function author( $field = 'display_name' ) {
		/*
		Field Options:
		--------------
		user_login
		user_pass
		user_nicename
		user_email
		user_url
		user_registered
		user_activation_key
		user_status
		display_name
		nickname
		first_name
		last_name
		description (Biographical Info from the user's profile)
		jabber
		aim
		yim
		user_level
		user_firstname
		user_lastname
		user_description
		rich_editing
		comment_shortcuts
		admin_color
		plugins_per_page
		plugins_last_view
		ID
		*/
		
		$post_author = get_the_author_meta( $field , $this->author_id() );
		if( $apply_filters )
			$post_author = apply_filters('the_author', $post_author);
		
		return $post_author;
	}

	public function get_terms($taxonomy, $return = 'all') {
		$terms = wp_get_post_terms( $this->ID, $taxonomy );

		if ($return != "all") {
			$items = array();

			foreach( $terms as $term ) {
				array_push($items, $term->$return);
			} 
			return $items;
		} else {
			return $terms;
		}
	}

	public function get_ancestor_id( $level = 1 ) {
		$parents = get_post_ancestors( $this->ID );
		return ($parents) ? $parents[count($parents) -$level]: $this->ID;
	}

	public function get_parent_id() {
		return ($this->post_parent) ? $this->post_parent : $this->ID;
	}

	public function thumbnail( $image_size = 'post-thumbnail', $image_class = 'thumbnail' ) {
		/*
		Image Size Options
		------------------
		thumbnail
		medium
		large
		full

		-- OR --
		2-item array representing width and height in pixels, e.g. array(32,32)
		*/
		if ( has_post_thumbnail( $this->ID ) ) {
			$attr = array(
				'class' => $image_class,
				'alt' => $this->post_title,
				'title' => $this->post_title
				);

			$photo = get_the_post_thumbnail( $this->ID, $image_size, $attr );
			return $photo;
		}
		return false;
	}

	private function author_id() {
		return $this->post->post_author;
	}

	public function date( $format = "n.j.y" ) {
		return date($format, strtotime($this->post_date));
	}
}