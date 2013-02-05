<?php
class J_SubNav {
    protected $current_post;
    protected $before;
    protected $after;
    protected $appendments;
    protected $class_additions;
    protected $ul_format = '<ul class="nav sidebar-nav">%s</ul>';

    public function __construct( $current_post, $options_array = null, $appendments = null, $class_additions = null ) {
        $this->current_post = $current_post;

        $this->before = array();
        $this->after = array();

        if( ! is_null( $appendments ) ) $this->appendments = $appendments;
        if( ! is_null( $class_additions ) ) $this->class_additions = $class_additions;

        if( isset( $options_array['before'] ) ) {
            if( is_array( $options_array['before'] ) ) {
                $this->before = $options_array['before'];
            } else {
                array_push( $this->before, $options_array['before'] );
            }
        }

        if( isset( $options_array['after'] ) ) {
            if( is_array( $options_array['after'] ) ) {
                $this->after = $options_array['after'];
            } else {
                array_push( $this->after, $options_array['after'] );
            }
        }
    }

    public function __toString() {
        return $this->to_html();
    }


    protected function get_nav_items() {
        // must be defined by the subclass
    }

    protected function item_filter( $item ) {
        if( is_a($item, 'WP_Post') ) {
            return $item;
        } else if( is_numeric($item) ) {
            return get_post( intval($item) );
        } else {
            return $item;
        }
    }

    protected function li_html( $jpost ) {
        $class_additions = isset( $this->class_additions[$jpost->ID] ) ? $this->class_additions[$jpost->ID] : '';
        
        $external_link = false;

        if( preg_match( '/^http[s]?:\/\/.*/', $jpost->permalink() ) ) $external_link = true;

        $link_target = $external_link ? 'target="_blank"' : '';

        $li_format = '<li class="%s %s"><a href="%s" title="%s" %s>%s</a>%s</li>';
        $active_indicator = (($jpost->ID == $this->current_post->ID) ? 'active' : '');
        $appendment = isset( $this->appendments[$jpost->ID] ) ? $this->appendments[$jpost->ID] : '';
        return sprintf($li_format, esc_attr($active_indicator), esc_attr($class_additions), $jpost->permalink(), esc_attr($jpost->title()), $link_target, esc_html($jpost->title()), $appendment );
    }

    public function to_html() {
        $sub_nav_li_html = null;
        $sub_nav_items = $this->get_nav_items();

        if( ! empty( $sub_nav_items ) ) {
            foreach( $sub_nav_items as $sub_nav_item ) {
                $sub_nav_li_html .= $this->li_html( new J_Post( $sub_nav_item ), $this->current_post );
            }
        }

        if( ! empty( $this->before ) ) {
            $before_lis = null;
            foreach( $this->before as $before_item ) {
                $before_item = $this->item_filter( $before_item );
                $before_lis .= $this->li_html( new J_Post( $before_item ) );
            }
            $sub_nav_li_html = $before_lis . $sub_nav_li_html;
        }

        if( ! empty( $this->after ) ) {
            $after_lis = null;
            foreach( $this->after as $after_item ) {
                $after_item = $this->item_filter( $after_item );
                $after_lis .= $this->li_html( new J_Post( $after_item ) );
            }
            $sub_nav_li_html .= $after_lis;
        }


        return sprintf( $this->ul_format, $sub_nav_li_html );
    }

}

class J_SubNav_ChildPages extends J_SubNav {
    private $top_parent;

    public function __construct( $current_post, $options_array = null, $appendments = null, $class_additions = null ) {
        parent::__construct( $current_post, $options_array, $appendments, $class_additions );
        $this->top_parent = $this->get_top_parent();
    }

    protected function get_nav_items() {
        return $this->get_top_parent_children();
    }

    private function get_top_parent() {
        $post_obj = $this->current_post;

        while( $post_obj->post_parent != 0 ) {
            $post_obj = get_post( $post_obj->post_parent );
        }
        return $post_obj;
    }

    private function get_top_parent_children() {
        return get_children( array( 'post_parent' => $this->top_parent_id(), 'post_type' => 'page', 'post_status' => 'publish', 'orderby' => 'menu_order', 'order' => 'ASC') );
    }

    private function top_parent_id() {
        return $this->top_parent->ID;
    }

    protected function item_filter( $item ) {
        if( $item == 'top_parent' ) {
            return $this->top_parent;
        } else {
            return parent::item_filter();
        }
    }
}

class J_SubNav_PostList extends J_SubNav {
    private $postlist;
    protected $ul_format = '<ul class="nav subnav">%s</ul>';

    public function __construct( $current_post, $postlist, $options_array = null, $appendments = null, $class_additions = null ) {
        parent::__construct($current_post, $options_array, $appendments, $class_additions );
        $this->postlist = $postlist;
    }

    protected function get_nav_items() {
        return $this->postlist->to_array();
    }
}