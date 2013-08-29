<?php
/*
Plugin Name: Blogger To WordPress Redux
Plugin URI: http://getsource.net
Description: This plugin is useful for setting up 1-to-1 mapping between Blogger.com blog posts and WordPress blog posts. This works nicely for blogs with old subdomain address (e.g. xyz.blogspot.com) which are moved to new custom domain (e.g. xyz.com)
Version: 3.0
Author: DH-Shredder
Author URI: http://getsource.net
Requires at least: 3.6
Tested up to: 3.6
*/

define('GS_B2WR_PLUGIN_URL',  plugins_url( basename( dirname( __FILE__ ) ) ) );

/* Add option to Tools Menu */
function gs_Blogger_to_WordPress_add_option() {
    add_management_page('Blogger Redirection', 'Blogger Redirection', 'administrator', 'gs-blogger-to-wordpress-redirection', 'gs_Blogger_to_WordPress_Administrative_Page');

    wp_enqueue_script('gs-blogger-to-wordpress-redirection-js', (GS_B2WR_PLUGIN_URL . '/js/b2w-redirection-ajax.js'), array('jquery', 'postbox'), '', true);
    wp_enqueue_style('gs-blogger-to-wordpress-redirection-css', (GS_B2WR_PLUGIN_URL . '/css/b2w-redirection.css'));

    if (isset($_GET['page']) && $_GET['page'] == 'rt-blogger-to-wordpress-redirection') {
        wp_enqueue_script('dashboard');
        wp_enqueue_style('dashboard');
    }
}
add_action('admin_menu', 'gs_Blogger_to_WordPress_add_option');

/* Administrative Page - Begin */
function gs_Blogger_to_WordPress_Administrative_Page() {
    ?>
    <div class="wrap">
        <div>
            <img id="btowp_img" alt="B2W-Redirection" src="<?php echo GS_B2WR_PLUGIN_URL; ?>/images/btowp_img.png" />
            <h2 id="btowp_h2"><?php _e('Blogger to WordPress Redirection'); ?></h2>
        </div>
        <div class="clear"></div>
        
        <div id="content_block" class="align_left">
            <p class="description">This plugin is useful for setting up 1-to-1 mapping between Blogger.com blog posts and WordPress blog posts. This works nicely for blogs with old subdomain address <code>(e.g. xyz.blogspot.com)</code> which are moved to new custom domain <code>(e.g. xyz.com)</code></p>
            <div id="message" class="error"><p>Please keep this plugin <strong>activated</strong> for redirection to work.</p></div>
            <h3><u>Start Configuration</u></h3>
            <h4>Press "Start Configuration" button to generate code for Blogger.com blog</h4>
            <p>Plugin will automatically detect Blogger.com blog from where you have imported.</p>
            <input type="submit" class="button-primary" name="start" id ="start_config" value="Start Configuration" onclick="gs_start_config()" />
            <p id="get_config" class="clear"></p>
        </div>

    </div>
    <?php
}

/* Get Configuration, called via AJAX */
function gs_b2wr_get_config(){
	global $wpdb;

	//get all blogger domains, if avaliable
	$sql = "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} where meta_key = 'blogger_blog'";
	$results = $wpdb->get_results($sql);
	
	if(!$results){
		$err_str = '<p id="error_msg">Sorry… No posts found that were imported from a Blogger.com blog</p>';
                $err_str .= '<strong><a href="'. get_bloginfo('url').'/wp-admin/admin.php?import=blogger">Import from Blogger.com</a></strong> first and then "Start Configuration"';

                echo $err_str;
		die();
	}

	$html = '<br/>';
        $html .= '<h3><u>List of Blogs</u></h3>';
	$html .= 'We found posts from following Blogger Blog(s) in your current WordPress installation. Click on <b>Get Code</b> button to generate the redirection code for the chosen Blogger blog<br /><br />';
        $html .= '<table width="350px">';
	$i=1;
	foreach($results as $result){
                $html .= '<tr>';
		$html .= '<td width="15px">'.$i.'</td>';
                $html .= '<td><b>'.$result->meta_value.'</b></td>';
                $html .= '<td align="left" width="75px"><input type="submit" class="button" onclick = "generate_code(\''.$i.'\',\''.$result->meta_value.'\', \''.get_bloginfo('url').'\');" name="start" value="Get Code"/></td>';
                $html .= '</tr>';
                $i++;
	}
        $html .= '</table>';
        $html .= '<div id ="code_here"></div>';
	die($html);
}
add_action('wp_ajax_gs_b2wr_get_config', 'gs_b2wr_get_config');


/* Redirection Function (!important) */
function gs_Blogger_To_WordPress_Redirection() {
	$b2w = (isset($_GET['b2w']))?$_GET['b2w']:false;

	if ($b2w) {
		global $wpdb;
		$sql = "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} where meta_key = 'blogger_blog'";
		$results = $wpdb->get_results($sql);

		foreach ($results as $result){
			$result->meta_value = substr($result->meta_value, 0, strrpos($result->meta_value,'.'));
            if (strstr($b2w, $result->meta_value) !== false) {
				$b2w_temp = explode($result->meta_value, $b2w);
				$b2w = substr($b2w_temp[1], strpos($b2w_temp[1], '/'));
                if(strpos($b2w,'?') > 0){
                    $b2w = strstr($b2w,'?',true);
                }
			}

			$sqlstr = $wpdb->prepare(
                "SELECT wposts.ID, wposts.guid
                    FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta
                    WHERE wposts.ID = wpostmeta.post_id
                        AND wpostmeta.meta_key = 'blogger_permalink'
                        AND wpostmeta.meta_value = '%s'",
                $b2w
            );
			$wpurl = $wpdb->get_results($sqlstr, ARRAY_N);
			if ($wpurl){
				header( 'Location: '.get_permalink($wpurl[0][0]).' ') ;
				die();
			} else {
                header("Status: 301 Moved Permanently");
                header("Location:" . home_url());
            }
		}
	}
}
add_action('init','gs_Blogger_To_WordPress_Redirection');

/* Verify Configuration */
function gs_b2wr_verify_config() {
	global $wpdb;
	$domain_name = $_POST['dname'];
	$sql = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'blogger_blog' AND meta_value = '{$domain_name}' ORDER BY rand() LIMIT 1";

	$rand_col = $wpdb->get_results($sql);
	$rand_post_id = $rand_col[0]->post_id;
	$sql1 = "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = {$rand_post_id} AND meta_key = 'blogger_permalink' ORDER BY rand() LIMIT 1";
	$rand_col2 = $wpdb->get_results($sql1);

	$blogger_url = 'http://'.$domain_name.$rand_col2[0]->meta_value;
	$blogger_link = '<a href = "'.$blogger_url.'" target = "_blank">'.$blogger_url.'</a> ';
	$local_url = get_permalink($rand_post_id);
	$local_link = '<a href = "'.$local_url.'" target = "_blank">'.$local_url.'</a> ';

	die('<h3><u>Test Case</u></h3><pre>Clicking this link &raquo; <b>'.$blogger_link.'</b><br/>Should redirect to &raquo; <b>'.$local_link.'</b></pre>');
}
add_action('wp_ajax_rt_b2wr_verify_config', 'gs_b2wr_verify_config');
