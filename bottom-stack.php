<?php
/*
Plugin Name: Bottom Stack
Plugin URI: https://www.jklir.net/wordpress-bottom-stack-plugin.html
Description: Add a custom HTML and PHP content to the bottom of posts or static pages for signature or banner.
Author: JKLIR
Version: 1.0.2
Author URI: https://www.jklir.net/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: bottom_stack
Domain Path: /languages

Copyright 2011-2018 Jirka Klir
*/

if (!defined('ABSPATH')) { die("Restricted Access!"); }

define("BOTTOM_STACK_CNT", 8);

if (!class_exists('BottomStack'))
{
	Class BottomStack
	{
	
		function __construct() {
			if (function_exists('load_plugin_textdomain')) {
				load_plugin_textdomain('bottom_stack', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			}
			if (function_exists('register_activation_hook')) {
				register_activation_hook(__FILE__, array(&$this, 'activate'));
			}
			if (function_exists('register_uninstall_hook')) {
				register_uninstall_hook(__FILE__, 'uninstall');
			}

			add_filter('the_content', array(&$this, 'btmstack_generate'));
			add_action('admin_menu', array(&$this, 'btmstack_add_option_pages'));
			add_filter('plugin_action_links', array(&$this, 'btmstack_settings_links'), 9999, 2);
		}
		
		static function btmstack_settings_links($links, $file) {
			if ($file == plugin_basename(__FILE__) && function_exists("admin_url")) {
				$settings_link = '<a href="' . admin_url("options-general.php?page=bottom-stack" ). '">' . __('Settings') . '</a>';
				array_push($links, $settings_link);
			}
			return $links;
		}
		
		function btmstack_add_option_pages() {
			if (function_exists('add_options_page')) {
				add_options_page(__("Bottom Stack Options", "bottom_stack"), "Bottom Stack", 8, "bottom-stack", array(&$this, "btmstack_options_page"));
				wp_enqueue_style("bottom-stack", plugins_url('bottom-stack') . '/bottom-stack.css', array());
			}
		}
		
		function btmstack_options_page() {
		
			if (!current_user_can('manage_options')) {
				wp_die(__('Sorry, but you have no permissions to change settings.', 'bottom_stack'), '', array('response' => 403));
			}
		
			if (!empty($_POST["action"]) && $_POST["action"] === "update") {
			
				(isset($_REQUEST['_wpnonce'])) ? $nonce = $_REQUEST['_wpnonce'] : $nonce = '';
				
				if (!wp_verify_nonce($nonce, 'update-options')) {
					wp_die(__('Security-Check failed.', 'bottom_stack'), '', array('response' => 403));
				}
				
				for($i = 1; $i <= constant("BOTTOM_STACK_CNT"); $i++) {
					update_option("btmstack_data".$i, wp_kses( $this->replace_php_code($_POST["btmstack_data".$i]) , $this->expanded_alowed_tags()));
					update_option("btmstack_pages".$i, wp_validate_boolean($_POST["btmstack_pages".$i]));
					update_option("btmstack_posts".$i, wp_validate_boolean($_POST["btmstack_posts".$i]));
				}
				
				echo "<div id=\"message\" class=\"updated fade\"><p><strong>".__("Settings saved.")."</strong></p></div>";

			} ?>

			<div id="bottom-stack-wrapper" class="wrap">
				<div class="icon32" id="icon-options-general" class="icon32"></div>
				<h2>Bottom Stack</h2>

				<p><?php _e("For information, updates and donation, please visit:", "bottom_stack"); ?> <a href="https://www.jklir.net/wordpress-bottom-stack-plugin.html" target="_blank">https://www.jklir.net/wordpress-bottom-stack-plugin.html</a></p>

				<form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
				<input type="hidden" name="info_update" id="info_update" value="1" />

				<h3><?php _e("Data Stacks", "bottom_stack"); ?></h3>

				<?php
					for($i = 1; $i <= constant("BOTTOM_STACK_CNT"); $i++) {
				?>
				<div class="btmstack">
					<div class="inner">
						<p>
							<strong class="stack-bubble"><?php printf(__("Stack %d", "bottom_stack"), $i); ?></strong> - <?php printf(__("Trigger with %s in content or choose an option on the right", "bottom_stack"), "[btmstack".$i."]"); ?><br />
							<textarea name="btmstack_data<?php echo $i; ?>" cols="76" rows="5"><?php echo htmlspecialchars($this->replace_php_code(stripslashes(get_option("btmstack_data".$i)), "reverse")) ?></textarea>
						</p>
					</div>
					<ul class="stack-options">
						<li><?php _e("Display stack automatically at the bottom of each", "bottom_stack"); ?>:</li>
						<li>
							<input type="checkbox" name="btmstack_pages<?php echo $i; ?>" id="btmstack_pages<?php echo $i; ?>" value="checkbox" <?php if (get_option("btmstack_pages".$i) == true) { echo "checked=\"checked\""; } ?>/>&nbsp;
							<label for="btmstack_pages<?php echo $i; ?>"><strong><?php _e("static page", "bottom_stack"); ?></strong></label>
						</li>
						<li>
							<input type="checkbox" name="btmstack_posts<?php echo $i; ?>" id="btmstack_posts<?php echo $i; ?>" value="checkbox" <?php if (get_option("btmstack_posts".$i) == true) { echo "checked=\"checked\""; } ?>/>&nbsp;
							<label for="btmstack_posts<?php echo $i; ?>"><strong><?php _e("blog post", "bottom_stack"); ?></strong></label>
						</li>
					</ul>
				</div>
				<?php
					}
				?>

				<strong><?php _e("Notes"); ?>:</strong>
				<p>
					- <?php _e("<strong>HTML</strong> is allowed", "bottom_stack"); ?><br/>
					- <?php _e("<strong>PHP Code</strong> MUST be enclosed in &lt;?php and ?&gt; tags", "bottom_stack"); ?><br/>
					- <?php _e("<strong>CSS</strong> can be added to customize the look", "bottom_stack"); ?><br/>
				</p>

				<div class="submit">
					<?php wp_nonce_field('update-options') ?>
					<input type="hidden" name="action" value="update" />
					<input type="submit" name="info_update" class="button-primary" value="<?php _e('Save Changes'); ?>" />
				</div>

				</form>
			</div>

		<?php
		}
		
		function btmstack_generate($content) {

			// strip p tags around btmstack shortcode
			$content = preg_replace('/<p>\s*\[(.*)\]\s*<\/p>/i', "[$1]", $content);

			// Load options
			$data_stacks = "";
			
			for($i = 1; $i <= constant("BOTTOM_STACK_CNT"); $i++) {
				$btmstack_data[$i] = get_option("btmstack_data".$i);
				$btmstack_pages[$i] = get_option("btmstack_pages".$i);
				$btmstack_posts[$i] = get_option("btmstack_posts".$i);
				$show_stack = false;
				$in_content_found = strpos($content, "[btmstack".$i."]");
				if ((is_page() && $btmstack_pages[$i]) || (is_single() && $btmstack_posts[$i]) || $in_content_found) {
					$show_stack = true;
				}

				if ($show_stack) {
					// Process stack
					$btmstack_data[$i] = stripslashes($btmstack_data[$i]);
					$btmstack_data[$i] = $this->replace_php_code($btmstack_data[$i], "reverse");
					$btmstack_data[$i] = '<div class="btmstack_wrap">' . $this->btmstack_php_exec_process($btmstack_data[$i]) . '</div>';

					// Look for trigger
					if ($in_content_found) { // If trigger found, process
						$content = str_replace("[btmstack".$i."]", $btmstack_data[$i], $content);
					} else {	
						$data_stacks .= $btmstack_data[$i];
					}
				}
			}
			
			$content .= $data_stacks;
			return $content;

		}

		function btmstack_php_exec_process($phpexec_text) {
			$phpexec_textarr = preg_split("/(<\?php .*\?>)/Us", $phpexec_text, -1, PREG_SPLIT_DELIM_CAPTURE); // capture the tags as well as in between
			$phpexec_stop = count($phpexec_textarr); // loop stuff
			$phpexec_output = "";
			for ($phpexec_i = 0; $phpexec_i < $phpexec_stop; $phpexec_i++) {
				$phpexec_content = $phpexec_textarr[$phpexec_i];
				if (preg_match("/^<\?php (.*)\?>/Us", $phpexec_content, $phpexec_code)) { // If it's a phpcode	
					$phpexec_php = "<?php ".$phpexec_code[1]." ?>";
					ob_start();
					eval("?>". $phpexec_php);
					$phpexec_output .= ob_get_clean();
				} else {
					$phpexec_output .= $phpexec_content;
				}
			}
			return $phpexec_output;
		}
		
		function activate() {
			for($i = 1; $i <= constant("BOTTOM_STACK_CNT"); $i++) {
				if($i == 1) {
					$data = "<strong>".sprintf(__("This is my data stack %s.", "bottom_stack"), $i)."</strong>";
				} else if ($i == 2) {
					$data = sprintf(__("This is my data stack %s.", "bottom_stack"), '[php] $a = 1; $b = 1; echo $a + $b; [/php]');
				} else {
					$data = sprintf(__("This is my data stack %s.", "bottom_stack"), $i);
				}
				add_option("btmstack_data".$i, $data);
				add_option("btmstack_pages".$i, false);	// Show on pages
				add_option("btmstack_posts".$i, false);	// Show on posts
			}
		}

		static function uninstall() {
			for($i = 1; $i <= constant("BOTTOM_STACK_CNT"); $i++) {
				delete_option("btmstack_data".$i);
				delete_option("btmstack_pages".$i);
				delete_option("btmstack_posts".$i);
			}
		}
		
		function replace_php_code($content, $direction) {
			$php = array("<?php", "?>");
			$shortcode = array("[php]", "[/php]");
			if ($direction === "reverse") {
				$content = str_replace($shortcode, $php, $content);
			} else {
				$content = str_replace($php, $shortcode, $content);
			}
			return $content;
		}
		
		function expanded_alowed_tags() {
			$my_allowed = wp_kses_allowed_html('post');
			$my_allowed['form'] = array(
				'action'          => array(),
				'method'          => array(),
				'enctype'         => array()
			);
			$my_allowed['iframe'] = array(
				'src'             => array(),
				'height'          => array(),
				'width'           => array(),
				'frameborder'     => array(),
				'allowfullscreen' => array()
			);
			$my_allowed['script'] = array(
				'type' => array(),
				'src' => array(),
				'async' => array()
			);
			$my_allowed['noscript'] = array();
			$my_allowed['style'] = array();
		 
			return $my_allowed;
		}

	}
	$bottomstack = new BottomStack();
}
?>