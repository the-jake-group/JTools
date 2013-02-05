<?php
class J_PostFormats {
  public static function linked_title( $post ) {
    return $post->linked_title();
  }

  public static function linked_title_and_date( $post ) {
    return self::linked_title( $post ) . sprintf( "<span>%s</span>", $post->post_date );
  }

  public static function linked_title_and_content( $post ) {
    return self::linked_title( $post ) . sprintf( "<p>%s</p>", $post->post_content );
  }
}