<?php
/**
 * Return the target for the specified post
* @return string Target attribute
 */
function the_target_link( $post_id = 0 ) {
	if( ! $post_id ) {
		global $post;
		if( $post && isset( $post->ID ) ) $post_id = $post->ID;
		$target_blank = get_metadata('post', $post_id, 'zoninator_organizer_optional_link_blank', true);

		if( $target_blank ){
			echo 'target="_blank"';
		}

	}
}