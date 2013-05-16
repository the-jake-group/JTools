<?php

/**
 * J_Post is a class that extends the functionality of the WordPress Post object.
 *
 * J_Post provides a number of methods for accessing commonly used data that is
 * associated with, but not immediately accessible to, a WordPress Post object.
 *
 * @package  J_Post
 * @author   Lawson Kurtz
 * @author   Tyler Bruffy
 * @version  Revision: 1.0
 * @access   public
 */

class J_Post {
	public static $tests;
	public static $partials;
	public static $formats;

	// For memoization
	private $post;
	private $permalink;
	private $meta_array;
	private $excerpt;

	private $classes;
	private $conditional_classes;


	/**
	 * Register a test format.
	 *
	 * @param  string   $name   The name of the test.
	 * @param  function $lambda The function that will return the test.
	 * @return boolean          Always returns true.
	 */
	public static function register_test( $name, $lambda ) {
		self::$tests[ $name ] = $lambda;
		return true;
	}


	/**
	 * Register a post partial format.
	 *
	 * @param  string   $name   The name of the partial.
	 * @param  function $lambda The function that will return the HTML partial.
	 * @return boolean          Always returns true.
	 */
	public static function register_partial( $name, $lambda ) {
		self::$partials[ $name ] = $lambda;
		return true;
	}


	/**
	 * Register an HTML post format.
	 *
	 * @param  string   $name    The name of the post format to register.
	 * @param  function $lambda  The function that will return the HTML of the format.
	 * @param  boolean  $default Whether or not this should be the default format for a J_Post object.
	 * @return boolean           Always returns true.
	 */
	public static function register_format( $name, $lambda, $default = false ) {
		self::$formats[ $name ] = $lambda;
		
		if( $default )
			self::$formats[ 'default' ] = $lambda;

		return true;
	}


	/**
	 * Create a J_Post from a WordPress Post object.
	 *
	 * @param object $post    WordPress Post object.
	 * @param mixed  $preload Whether to preload the metadata, permalink and excerpt.
	 */
	public function __construct( $post, $preload = null ) {
		if( ! is_a( $post, 'WP_Post' ) )
			throw new Exception( 'Argument must be of type WP_Post' );

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


	/**
	 * Call a method.
	 *
	 * @param  string $method_name The name of the method to call.
	 * @param  mixed  $arguments   The arguments to pass to the method.
	 * @return mixed               Returns the value of the method, or false if the method was not found.
	 */
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


	/**
	 * Get a property of the current post.
	 *
	 * @param  string $name The property to return.
	 * @return mixed        The value of the requested property.
	 */
	public function __get($name) {
		return $this->post->$name;
	}


	/**
	 * Return a string representation of the post.
	 *
	 * @param  string $format A post format that has been registered with the Post.
	 * @return string         The HTML string representation of the post.
	 */
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


	/**
	 * Set CSS classes and their context for a J_Post instance.
	 *
	 * @param string $class_name The CSS class to set.
	 * @param string $context    The context for that class.
	 */
	public function set_classes( $class_name, $context = null ) {
		if( is_null( $context ) ) {
			return array_push( $this->classes, $class_name );
		} else {
			return $this->classes[ $context ] = $class_name;
		}
	}


	/**
	 * Return the CSS classes associated with a J_Post instance.
	 *
	 * @param  string $context The context set on the classes, so that classes for specific HTML elements can be retrieved.
	 * @return string          The CSS classes for the given context.
	 */
	public function get_classes( $context = 'all' ) {
		if( empty( $this->classes ) )
			$this->classes = array();

		if( $context == 'all' ) {
			$classes = implode(' ', $this->classes );
		} elseif( isset( $this->classes[ $context ] ) ) {
			$classes = $this->classes[ $context ];
		} else {
			return false;
		}

		return $classes;
	}


	/**
	 * Retrieve posts connected to the current post via the Posts 2 Posts plugin.
	 *
	 * @param  string $connection_type                  A registered posts-to-posts connection type.
	 * @param  array $additional_post_list_query_params An array of additional Wordpress query paramaters.
	 * @param  array $post_list_options                 Additional parameters to pass to the J_Postlist class.
	 * @return object                                   A J_Postlist object of connected posts.
	 */
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


	/**
	 * Get an excerpt for the current post.
	 *
	 * @param  array   $more_link_options Options for the more_link of J_Excerpt. (Options include: text, title, template, and class.)
	 * @param  int     $excerpt_length    The length of the excerpt to return.
	 * @param  boolean $show_more_link    Whether the returned HTML should include a more link.
	 * @return string                     Returns the display HTML of the J_Excerpt object.
	 */
	public function excerpt( $more_link_options = null, $excerpt_length = null, $show_more_link = true ) {
		if( ! isset( $this->excerpt ) )
			$this->excerpt = new J_Excerpt( $this->post, $more_link_options, $excerpt_length, false, $show_more_link );

		if( $apply_filters )
			$this->excerpt = apply_filters('the_excerpt', $this->excerpt );

		return $this->excerpt->display();
	}


	/**
	 * Retrieve metadata associated with the current post.
	 *
	 * @param  string  $meta_key        The meta key you wish to return.
	 * @param  boolean $single          Whether to return a string or not.
	 * @param  string  $meta_key_prefix The prefix for the meta_key.
	 * @return mixed                    The meta value.
	 */
	public function meta( $meta_key, $single = false, $meta_key_prefix = "wpcf-" ) {
		$meta_key = $meta_key_prefix . $meta_key;
		if( ! isset( $this->meta_array[$meta_key] ) ) {
			$this->meta_array[$meta_key] = get_post_meta( $this->ID, $meta_key, $single );
		}
		return $this->meta_array[$meta_key];
	}


	/**
	 * Get the permalink of the post.
	 *
	 * @param  boolean $apply_filters Whether or not to apply "the_permalink" filters before returning.
	 * @return string                 The permalink of the post.
	 */
	public function permalink( $apply_filters = true ) {
		if( is_null( $this->permalink ) )
			$this->permalink = get_permalink( $this->post->ID );

		if( $apply_filters )
			$this->permalink = apply_filters('the_permalink', $this->permalink );
		
		return $this->permalink;
	}


	/**
	 * Get the title of the post.
	 *
	 * @param  boolean $apply_filters Whether to apply "the_title" filters before returning.
	 * @return string                 The title of the post.
	 */
	public function title( $apply_filters = true ) {
		$post_title = $this->post->post_title;
		if( $apply_filters )
			$post_title = apply_filters('the_title', $post_title);
		
		return $post_title;
	}


	/**
	 * Get the content of a post.
	 *
	 * @param  boolean $apply_filters Whether to apply "the_content" filters before returning.
	 * @return string                 The content of the post.
	 */
	public function content( $apply_filters = true ) {
		$post_content = $this->post->post_content;
		if( $apply_filters )
			$post_content = apply_filters('the_content', $post_content);
		
		return $post_content;
	}


	/**
	 * Return data about the author of the post.
	 *
	 * @param  string $field The field name to return.
	 *                       Accepted values for $field are: 
	 *                       - user_login
	 *                       - user_pass
	 *                       - user_nicename
	 *                       - user_email
	 *                       - user_url
	 *                       - user_registered
	 *                       - user_activation_key
	 *                       - user_status
	 *                       - display_name
	 *                       - nickname
	 *                       - first_name
	 *                       - last_name
	 *                       - description (biographical info from the user's profile)
	 *                       - jabber
	 *                       - aim
	 *                       - yim
	 *                       - user_level
	 *                       - user_firstname
	 *                       - user_lastname
	 *                       - user_description
	 *                       - rich_editing
	 *                       - comment_shortcuts
	 *                       - admin_color
	 *                       - plugins_per_page
	 *                       - plugins_last_view
	 *                       - ID
	 * @return mixed        The value of the requested field, if it exists.
	 */
	public function author( $field = 'display_name' ) {
		$post_author = get_the_author_meta( $field , $this->author_id() );
		if( $apply_filters )
			$post_author = apply_filters('the_author', $post_author);
		
		return $post_author;
	}


	/**
	 * Get the featured image for a post if one is set.
	 *
	 * If the post has a thumbnail you can retrieve it using this method.  Passing an 
	 * array to the $image-size property will return the smallest image which will cover
	 * the dimensions, and then will apply width and height attributes to match the values
	 * given.
	 * 
	 * @param  mixed  $image_size  The name of a registered WP thumbnail size, or an array containing the width/height.
	 *                             Default: 'post-thumbnail'.
	 * @param  string $image_class The css class(es) to attach to the returned html
	 * @return mixed               Returns an img HTML element as a string if the post has a featured image. Otherwise returns false.
	 */
	public function thumbnail( $image_size = 'post-thumbnail', $image_class = 'thumbnail' ) {
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


	/**
	 * Get the author ID of the current post.
	 *
	 * @return int The numerical author ID associated with the Post object.
	 */
	private function author_id() {
		return $this->post->post_author;
	}
}
