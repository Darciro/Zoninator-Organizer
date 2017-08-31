<?php
/*
Plugin Name: Zoninator Organizer
Plugin URI: https://github.com/Darciro/Zoninator-Organizer
Depends: Zone Manager (Zoninator)
Description: Allows organization of posts into zones on publication.
Author: Ricardo Carvalho
Version: 0.3
Author URI: http://galdar.com.br
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: zoninator-organizer
Domain Path: /languages/

This plugin is based on the outdated 'Schedule post into Zoninator zone' from Vladimir Smotesko, Boyle Software - https://wordpress.org/plugins/schedule-post-into-zoninator-zone/
*/

if (!class_exists('Zoninator_organizer')):

	require_once( dirname( __FILE__ ) . '/functions.php' );

	class Zoninator_organizer
	{

		private $zoninator; // instance of Zoninator plugin
		private $zone_posts = array(); // local cache variable

		const nonce_field = 'zoninator_organizer_nonce';
		const zone_id_key = 'zoninator_organizer_zone_id';
		const position_key = 'zoninator_organizer_position';
		const optional_title = 'zoninator_organizer_optional_title';
		const optional_link = 'zoninator_organizer_optional_link';
		const optional_link_blank = 'zoninator_organizer_optional_link_blank';

		public function __construct()
		{
			add_action('init', array($this, 'check_zoninator'));
			add_action('plugins_loaded', array($this, 'i18n'));
			add_action('wp_ajax_get_posts_in_zone', array($this, 'get_posts_in_zone'));
			add_action('wp_ajax_reorder_posts_in_zone', array($this, 'reorder_posts_in_zone'));
			add_action('wp_ajax_delete_post_in_zone', array($this, 'delete_post_in_zone'));
			add_action('admin_enqueue_scripts', 'add_scripts_to_post_page');

            add_filter('the_title', array($this, 'show_optional_title'), 10, 2);
            add_filter('the_permalink', array($this, 'show_optional_link'), 10, 2);

			function add_scripts_to_post_page($hook)
			{
				// Check if is post creation/edit page
				if ($hook == 'post.php' || $hook == 'post-new.php'):
					wp_enqueue_style('zone_organizer_styles', plugin_dir_url(__FILE__) . 'assets/css/styles.css');
					wp_register_script('zone_organizer_script', plugin_dir_url(__FILE__) . 'assets/js/zone_organizer_script.js', array('jquery'));
					wp_register_script('jquery-ui', plugin_dir_url(__FILE__) . 'assets/js/jquery-ui.min.js', array('jquery'));
					wp_localize_script('zone_organizer_script', 'zoScripts', array(
						'ajaxUrl' 				=> admin_url('admin-ajax.php'),
						'adminUrl' 				=> get_site_url('', '', 'admin'),
						'postPermalink' 		=> get_post_permalink( get_the_ID() ),
						'thePostID' 			=>  get_the_ID(),
						'string__postTitle' 	=>  get_the_title( get_the_ID() ),
						'string__postOnTheList' => __('This post is already on the list')
					));
					wp_enqueue_script('zone_organizer_script');
					wp_enqueue_script('jquery-ui');
				endif;
			}
		}

		// i18n
		public function i18n() {
			load_plugin_textdomain( 'zoninator-organizer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		// Retrieve the list of zones
		public function get_posts_in_zone()
		{
			$result['objects'] = z_get_posts_in_zone(intval($_REQUEST["zone_id"]));
			$result['type'] = "success";

			if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				$result = json_encode($result);
				echo $result;
			} else {
				header("Location: " . $_SERVER["HTTP_REFERER"]);
			}

			die();

		}

		// Reorder the list of posts into the zone
		public function reorder_posts_in_zone()
		{
			$zone_meta_prefix = '_zoninator_order_';
			$zone_meta_sufix = $_REQUEST["zoneId"];
			$zoneID = $zone_meta_prefix . $zone_meta_sufix;
			$result['type'] = "success";

			foreach ($_REQUEST["postIds"] as $i => $post_id) {
				$result['posts'][$i] = $post_id;
				update_post_meta($post_id, $zoneID, ($i + 1));
			}

			if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				$result = json_encode($result);
				echo $result;
			} else {
				header("Location: " . $_SERVER["HTTP_REFERER"]);
			}

			die();

		}


		// Remove post from zone
		public function delete_post_in_zone()
		{
			$zone_meta_prefix = '_zoninator_order_';
			$zone_meta_sufix = $_REQUEST["zoneId"];
			$zoneID = $zone_meta_prefix . $zone_meta_sufix;
			$result['type'] = "success";
			$post_id = $_REQUEST["postID"];

			delete_post_meta($post_id, $zoneID);

			if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				$result = json_encode($result);
				echo $result;
			} else {
				header("Location: " . $_SERVER["HTTP_REFERER"]);
			}

			die();

		}

        public function show_optional_title($title, $id) {
            $optional_title = get_metadata('post', get_the_ID(), self::optional_title, true);
            if ( !is_admin() && !is_single() && $optional_title !== '' ) {
                $title = $optional_title;
            }
            return $title;
        }

        public function show_optional_link($url) {
        	$optional_link = get_metadata('post', get_the_ID(), self::optional_link, true);
        	if( $optional_link !== '' ){
        		return add_query_arg($_GET, $optional_link);
        	}else{
        		return $url;
        	}

        }

		public function check_zoninator()
		{
			global $zoninator;
			if (
				isset($zoninator) &&
				$zoninator instanceof Zoninator
			) {
				$this->zoninator = $zoninator;
				$this->init_plugin();
			} else {
				add_action('admin_notices', array($this, 'zoninator_not_found_notice'));
			}
		}

		private function init_plugin()
		{
			add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
			add_action('save_post', array($this, 'save_post'));
			add_action('publish_future_post', array($this, 'publish_future_post'));
		}

		public function zoninator_not_found_notice()
		{
			?>
			<div class="error">
				<p>
					<?php _e('Zoninator plugin is not installed or not activated! Zoninator Organizer will not work.', 'zoninator-organizer'); ?>
				</p>
			</div>
		<?php
		}

		public function add_meta_boxes()
		{
			add_meta_box(
				'zoninator_organizer',
				__('Organize posts into zones', 'zoninator-organizer'),
				array($this, 'metaboxes_zone'),
				'post',
				'side',
				'high'
			);
		}

		public function metaboxes_zone($post)
		{
			$available_zones = $this->zoninator->get_zones();
			$selected_zone_id = get_metadata(
				'post',
				$post->ID,
				self::zone_id_key,
				TRUE
			);
			$current_position = get_metadata(
				'post',
				$post->ID,
				self::position_key,
				TRUE
			);
            $optional_title = get_metadata(
                'post',
                $post->ID,
                self::optional_title,
                TRUE
            );
            $optional_link = get_metadata(
                'post',
                $post->ID,
                self::optional_link,
                TRUE
            );
            $optional_link_blank = get_metadata(
                'post',
                $post->ID,
                self::optional_link_blank,
                TRUE
            );
			wp_nonce_field(self::nonce_field, self::nonce_field);

			if( !empty( $available_zones ) ):
			?>
			<p>
				<label class="hidden" for="<?php echo self::zone_id_key; ?>"><?php _e('Zone', 'zoninator-organizer'); ?></label>
				<select name="<?php echo self::zone_id_key; ?>"  id="<?php echo self::zone_id_key; ?>">
					<option value=""><?php _e('Select zone', 'zoninator-organizer'); ?></option>
					<?php foreach ($available_zones as $available_zone): ?>
						<option value="<?php echo $available_zone->term_id; ?>" <?php echo ($selected_zone_id == $available_zone->term_id) ? 'selected="selected"' :  ''; ?>>
							<?php echo $available_zone->name; ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="select-sumthing <?php if ($selected_zone_id != '') { echo 'hidden'; }; ?>"><?php _e('Select one zone to start organizing.', 'zoninator-organizer'); ?></p>
			<div id="zoninator_organizer_zone_box-list" class="<?php if ($selected_zone_id == '') { echo 'hidden'; }; ?>">
				<div class="add-post-box">
                        <label class="screen-reader-text" for="zoninator_organizer_optional_title">Optional title</label>
                        <input type="text" id="zoninator_organizer_optional_title" name="zoninator_organizer_optional_title" class="form-input-tip" placeholder="<?php _e('Post optional title', 'zoninator-organizer'); ?>" value="<?php if( $optional_title ){ echo $optional_title; }; ?>">
                        
                        <label class="screen-reader-text" for="zoninator_organizer_optional_link">Optional link</label>
                        <input type="text" id="zoninator_organizer_optional_link" name="zoninator_organizer_optional_link" class="form-input-tip" placeholder="<?php _e('Post optional link', 'zoninator-organizer'); ?>" value="<?php if( $optional_link ){ echo $optional_link; }; ?>">

                        <div id="zoninator_organizer_optional_link_blank--wrapper">
                        	<input id="zoninator_organizer_optional_link_blank" value="1" type="checkbox" data-type="checkbox" name="zoninator_organizer_optional_link_blank" <?php if($optional_link_blank){ echo 'checked="true"'; }; ?>>
                        	<label for="zoninator_organizer_optional_link_blank"><?php _e('Open in a new window', 'zoninator-organizer'); ?></label><br>
                    	</div>

					<a href="#" id="zone-post-<?php echo get_the_ID(); ?>" class="add-post-to-zone button button-primary" data-post-id="<?php echo get_the_ID(); ?>"><?php _e('Add post to zone', 'zoninator-organizer'); ?></a>

					<p><?php _e('Click to add the post into selected zone. Then, drag and drop to rearrange.', 'zoninator-organizer'); ?></p>
				</div>
				<ul id="zone-posts-area">
					<?php
					if ($selected_zone_id != ''):
						$zone_posts = z_get_zone_query(intval($selected_zone_id));
						if ($zone_posts->have_posts()) :
							while ($zone_posts->have_posts()) : $zone_posts->the_post(); ?>
								<li id="zone-post-<?php echo get_the_ID(); ?>" class="zone-post ui-sortable-handle ui-state-default" data-post-id="<?php echo get_the_ID(); ?>">
									<?php echo get_the_title(); ?>
									<div class="row-actions">
										<a href="<?php echo get_site_url('', '', 'admin'); ?>/wp-admin/post.php?post=<?php echo get_the_ID(); ?>&action=edit" class="edit" target="_blank" title="Opens in new window"><?php _e('Edit', 'zoninator-organizer'); ?></a> |
										<a href="#" class="delete" title="Remove this item from the zone"><?php _e('Remove', 'zoninator-organizer'); ?></a> |
										<a href="<?php echo get_post_permalink(get_the_ID()); ?>" class="view" target="_blank" title="Opens in new window"><?php _e('See post', 'zoninator-organizer'); ?></a>
									</div>
								</li>
							<?php endwhile;
						endif;
						wp_reset_query(); ?>

					<?php endif; ?>
				</ul>
			</div>
			<?php else: ?>
			<!--<p class="select-sumthing"><?php /*_e('No zones found. <a href="'. get_site_url('', '', 'admin') .'/wp-admin/admin.php?page=zoninator" target="_blank">Create one here!</a>'); */?></p>-->
			<p class="select-sumthing"><?php _e('No zones found.', 'zoninator-organizer'); ?> <a href="<?php echo get_site_url('', '', 'admin'); ?>/wp-admin/admin.php?page=zoninator" target="_blank"><?php _e('Create one here!', 'zoninator-organizer'); ?></a></p>
			<?php endif; ?>
		<?php
		}

		public function save_post($post_id)
		{
			if (wp_is_post_revision($post_id))
				return;
			if (
				!isset($_POST[self::nonce_field]) ||
				!wp_verify_nonce($_POST[self::nonce_field], self::nonce_field)
			)
				return;
			if (!current_user_can('edit_post', $post_id))
				return;

			if (isset($_POST[self::zone_id_key]) && $_POST[self::zone_id_key]) {
				$zone_id = absint($_POST[self::zone_id_key]);
				if ($this->zoninator->zone_exists($zone_id))
					update_metadata('post', $post_id, self::zone_id_key, $zone_id);
			} else
				delete_metadata('post', $post_id, self::zone_id_key);

			if (isset($_POST[self::position_key]) && $_POST[self::position_key]) {
				$position = absint($_POST[self::position_key]);
				if ($position > 0)
					update_metadata('post', $post_id, self::position_key, $position);
			} else
				delete_metadata('post', $post_id, self::position_key);

            // Save optional title whether it's empty or not
            $title = $_POST[self::optional_title];
            update_metadata('post', $post_id, self::optional_title, $title);

            // Save optional link
            $link = $_POST[self::optional_link];
            update_metadata('post', $post_id, self::optional_link, $link);

            // Save optional link blank attibute
            $blank = $_POST[self::optional_link_blank];
            update_metadata('post', $post_id, self::optional_link_blank, $blank);
		}

		public function publish_future_post($post_id)
		{
			$zone_id = intval(get_metadata('post', $post_id, self::zone_id_key, TRUE));
			$position = intval(get_metadata('post', $post_id, self::position_key, TRUE));

			delete_metadata('post', $post_id, self::zone_id_key);
			delete_metadata('post', $post_id, self::position_key);

			if (!$zone_id) {
				return;
			}
			if (!$position) {
				return;
			}
			if (!$this->zone_exists($zone_id)) {
				return;
			}

			$posts = $this->get_zone_posts($zone_id);
			if (count($posts) < $position) {
				$posts[] = $post_id;
			} else {
				array_splice($posts, ($position - 1), 0, array($post_id));
			}
			$this->update_zone_posts($zone_id, $posts);
		}

		/*
		 * I had to create the following four functions because the Zoninator's ones
		 * are not working during the publish_future_post hook execution:
		 * zone_exists()
		 * get_zone_posts()
		 * update_zone_posts()
		 * clear_zone_posts()
		 */

		/**
		 * Check whether the Zoninator zone exists
		 * @param int $zone_id
		 * @return boolean
		 */
		private function zone_exists($zone_id)
		{
			global $wpdb;
			return ($wpdb->get_var(
					"SELECT COUNT(term_taxonomy_id)
                FROM {$wpdb->term_taxonomy}
                WHERE term_id = $zone_id
                AND taxonomy = '{$this->zoninator->zone_taxonomy}'"
				) > 0);
		}

		/**
		 * Get the Zoninator zone's posts
		 * @param int $zone_id
		 * @return array posts IDs
		 */
		private function get_zone_posts($zone_id)
		{
			if (
				isset($this->zone_posts[$zone_id]) &&
				is_array($this->zone_posts[$zone_id])
			) {
				return $this->zone_posts[$zone_id];
			}
			global $wpdb;
			$this->zone_posts[$zone_id] = $wpdb->get_col(
				"SELECT {$wpdb->postmeta}.post_id
                FROM {$wpdb->postmeta}
                WHERE {$wpdb->postmeta}.meta_key =
                '{$this->zoninator->zone_meta_prefix}$zone_id'
                ORDER BY {$wpdb->postmeta}.meta_value ASC"
			);
			return $this->zone_posts[$zone_id];
		}

		/**
		 * Overwrite zone posts
		 * @param int $zone_id
		 * @param array $posts
		 * @return boolean
		 */
		private function update_zone_posts($zone_id, $posts)
		{
			$this->clear_zone_posts($zone_id);
			foreach ($posts as $n => $post_id) {
				update_metadata(
					'post',
					$post_id,
					$this->zoninator->zone_meta_prefix . $zone_id,
					$n + 1
				);
			}
			clean_term_cache($zone_id, $this->zoninator->zone_taxonomy);
			return TRUE;
		}

		/**
		 * Clear zone from posts
		 * @param int $zone_id
		 * @return boolean
		 */
		private function clear_zone_posts($zone_id)
		{
			foreach ($this->get_zone_posts($zone_id) as $post_id) {
				delete_metadata(
					'post',
					$post_id,
					$this->zoninator->zone_meta_prefix . $zone_id
				);
			}
			$this->zone_posts[$zone_id] = NULL;
			return TRUE;
		}

	}

	global $schedule_zoninator;
	$schedule_zoninator = new Zoninator_organizer();

endif;