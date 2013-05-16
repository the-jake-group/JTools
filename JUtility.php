<?php
class J_Utility {
	public static $class_directory = __DIR__;

	public static function autoload_classes() {
		if ($handle = opendir(self::$class_directory)) {
				while (false !== ($entry = readdir($handle))) {
						if ($entry != "." && $entry != "..") {
								require_once($entry);
						}
				}
				closedir($handle);
		}
	}

	public static function load_classes_in_order() {
		$class_filenames = array(
			'JExcerpt',
			'JPost',
			'JPostFormats',
			'JPostList',
			'JSubNav'
		);

		foreach( $class_filenames as $filename ) {
			require_once( self::$class_directory . '/' . $filename . '.php' );
		}
	}
}