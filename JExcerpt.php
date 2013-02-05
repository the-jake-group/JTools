<?php
class J_Excerpt {
  private $post;
  private $excerpt_length = 55;
  private $more_link_text = "continue reading &raquo;";
  private $more_link_title = "Continue Reading";
  private $more_link_template = "<a class='%s' href='%s' title='%s'>%s</a>";
  private $more_link_class = "more-link";

  private $limit_explicit_excerpt_length = true;
  
  private $show_more_link = true; // By default.
  private $excerpt_cutoff = false;

  private $excerpt_text;
  private $more_link;


  public function __construct( $post, $more_link_options = null, $excerpt_length = null, $echo = false, $show_more_link = true ) {
    if( is_numeric( $post ) ) $post = get_post( $post );
    $this->post = $post;

    if( ! is_null( $more_link_options ) && ! empty( $more_link_options ) ) {
      foreach( $more_link_options as $ml_option_key => $ml_option_value ) {
        $property = 'more_link_' . $ml_option_key;
        if( property_exists( $this, $property) ){
          $this->{$property} = $ml_option_value;
        }
      }
    }

    if( ! is_null( $excerpt_length ) && is_numeric( $excerpt_length ) ) $this->excerpt_length = $excerpt_length;
  
    $this->show_more_link = $show_more_link;
    $this->excerpt_text = $this->get_excerpt_text( $post->ID );
    $this->more_link = $this->get_more_link( $post->ID );
    
    if( $echo ) {
      echo $this->display();
    }
  }
  public function display() {
    return "<p>" . $this->excerpt_text . ( $this->excerpt_cutoff ? '... ' : ' ' ) .  $this->more_link . "</p>";
  }
  private function get_more_link( $id ) {
    if( ! $this->show_more_link ) return null;

    $permalink = get_permalink( $id );

    return sprintf( $this->more_link_template,
                    $this->more_link_class,
                    $permalink,
                    $this->more_link_title,
                    $this->more_link_text
                    );
  }

  private function get_excerpt_text( $id ) {
            global $post;
            $old_post = $post;
            if ( $id != $post->ID ) {
                $post = $this->post;
            }

            if ( ! $excerpt = trim( $post->post_excerpt ) ) {

              $excerpt = $post->post_content;

              if( $more_tag_location = strpos( $excerpt, "<!--more-->" ) ) {
                // No excerpt present, but more tag IS present
                $excerpt = substr( $excerpt, 0, $more_tag_location );
                $excerpt = $this->filter_excerpt($excerpt);
              } else {
                // No more tag present
                $excerpt = $this->filter_excerpt($excerpt);
                $excerpt = $this->cutoff_text( $excerpt );
              }
            }

            if( $this->limit_explicit_excerpt_length ) {
              $excerpt = $this->cutoff_text( $excerpt );
            }

            $post = $old_post;
            return $excerpt;
        }
  private function cutoff_text( $text ) {
    $words = preg_split("/[\n\r\t ]+/", $text, $this->excerpt_length + 1, PREG_SPLIT_NO_EMPTY);

    if ( count( $words ) > $this->excerpt_length ) {
        array_pop( $words );
        $excerpt = implode( ' ', $words );
        $excerpt = $excerpt;
        $this->excerpt_cutoff = true;
    } else {
        $excerpt = implode( ' ', $words );
        // $this->show_more_link = false;
    }
    return $excerpt;
  }

  private function filter_excerpt( $excerpt ) {
    $excerpt = strip_shortcodes( $excerpt );
    $excerpt = apply_filters( 'the_content', $excerpt );
    $excerpt = str_replace( ']]>', ']]&gt;', $excerpt );
    $excerpt = strip_tags( $excerpt );
    return $excerpt;
  }
}