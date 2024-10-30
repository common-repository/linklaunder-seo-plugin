<?php
/*
Plugin Name: LinkLaunder SEO
Plugin Script: linklaunder.php
Plugin URI: http://www.linklaunder.com
Description: Automated link-building system that works for you 24/7, totally free!
Version: 0.92.1
License: GPL
Author: LinkLaunder.com

Author URI: http://www.linklaunder.com 
cf http://mattread.com/projects/wp-plugins/installer-the-plugin/
Min WP Version: 2.7
Max WP Version: 2.7.1
Update Server: http://www.linklaunder.com/download


=== RELEASE NOTES ===


2009-02-16 - v0.92.1 	F: The plugin gracefully keeps quiet if there is a problem with the core server
2009-01-31 - v0.91 		F: Quick bugfix that showed the div all the time
2009-01-27 - v0.90 		F: Improved cache and speed
						+: CSS footprint removed
						+: A "Settings"-link directly from the Plugins page				
2009-01-18 - v0.89 		F: Backward compability bug fix
2009-01-16 - v0.88 		+: Link to sign up directly from the plugin
						+: Caching implemented to reduce network communication
						+: Updates to the database structure
						+: No access to the plugin for Wordpress Blogs running version 2.3 or less
2008-11-30 - v0.87 		+: No direct access outside Wordpress to the plugin 
2008-11-30 - v0.86 		F: Stupid beta bug fixed!
2008-11-23 - v0.85 		F: Bugfixing the error code communication.
2008-10-25 - v0.81	 	+: Communicating error code as well as status text.
2008-07-25 - v0.8 		+: Not storing error messages in local database.
2008-07-25 - v0.7		F: Fixed bug with local data storage
						+: Optimized plugin to store data locally, reducing communication needs
2008-07-25 - v0.6		F: Added additional security to the plugin
2008-07-25 - v0.5		+: Server now returns several suggestions, and uses the most valid
2008-07-21 - v0.4		F: Communication functions updated
2008-07-21 - v0.3		+: Added webservice communication with the central server
2008-07-20 - v0.2		+: Added tags and category reporting and assigning in the client and server
2008-07-10 - v0.1		+: Initial Wordpress version, based on original Proof Of Concept-script

Legend:
- : Removal of feature
+ : Adding of a feature
F : Bugfix

*/





/*  Copyright 2008-2009  Linklaunder.com  (email : contact@linklaunder.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
    
    
    This plugin uses the NuSOAP - Web Services Toolkit for PHP, more details can be found here:
    
	http://sourceforge.net/projects/nusoap/

*/

$llversion = "0.92.1";
$wsdl="http://linklaunder.com/webservice/server.php?wsdl"; //The link to the webservice

// ### No direct access to the plugin outside Wordpress
if (preg_match('#'.basename(__FILE__) .'#', $_SERVER['PHP_SELF'])) { 
	die('Direct access to this file is not allowed!'); 
}



// ### Generates a random 6-character string
function linklaunder_random_string()  {  
	$length= 6;
	$characters = "ABCDEFGHIJKLMNOPRQSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
	$num_characters = strlen($characters) - 1;  
	while (strlen($return) < $length)  
	{  
		$return.= $characters[mt_rand(0,$num_characters)];  
	}  
	return $return;  
}  

// ### Returns the difference in time between the current time and submitted parameter ..
function linklaunder_datedifference($instamp) {
	$instamp = strtotime($instamp);
	$now = strtotime(date('Y-m-d H:i:s'));
	$seconds = $now - $instamp; 
	return $seconds;
} 

// ### Base installation function for LinkLaunder
function linklaunder_install () { // Database setup. Runs first time plugin is activated
	$username	= get_option( 'll_username' ); 		// Getting the linklaunder.com username from the options table	
	$password	= get_option( 'll_password' ); 		// Getting the linklaunder.com password from the options table	

	if ( ($username<>'') AND ($password<>'')) { //We have a user that is reactivating!!
		$blog_url = get_bloginfo('url');
		$parse = parse_url ($blog_url);
		$domain = $parse[host];

		include_once(dirname(__FILE__).'/nusoap.php'); // Loading the NuSOAP library			
		$wsdl="http://linklaunder.com/webservice/server.php?wsdl"; //The link to the webservice
		$client=new nusoap_client($wsdl, 'wdsl');  // Setting up a new client
		$param=array( //The values to be submitted to the webservice
		'username'=>$username,
		'password'=>$password,
		'blog'=>$domain,
		); 
	
		$wdslreturn = $client->call('ReActivate', $param); //The call to the webservice
	}
	else { // New user, so we're gonna set up the plugin for first time use....

		global $wpdb;
		$table_name = $wpdb->prefix . "linklaunder";
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
			time timestamp,
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			postrefid mediumint(9) NOT NULL,
			link text NOT NULL,
			UNIQUE KEY id (id)
			);";
		} 
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		update_option("linklaunder_db_version", $llversion); // setting the db-version
		update_option('linklaunder_divid',"	background: #f3f3f3;padding-top:5px;padding-bottom:5px;font-size: 90%;padding: .5em 10px;border: 1px solid #ddd; "); // Default CCS-styling
		update_option('linklaunder_dividname',linklaunder_random_string()); // Random #div-name
	}

}

// ### Function is called for returning CSS style...
function linklaunder_css() {
	if (is_feed()) return '';

    	$dividname	= get_option( 'linklaunder_dividname' ); 	// The title of the div id, randomized for less footprint
    	$divid		= get_option( 'linklaunder_divid' );		// The actual div style
    	
    	if (($dividname<>'')&&($divid<>'')) {
			echo '<style type="text/css">';
			echo "#$dividname {";
			echo $divid;
			echo '}</style>';
	}
}

// ### Core LinkLaunder filter
function linklaunder_filter($content) {
	if (is_single()) {//only display or assign if it is viewed in single post view - not for pages or anything else...
	    $username	= get_option( 'll_username' ); 		// Getting the linklaunder.com username from the options table	
    	$password	= get_option( 'll_password' ); 		// Getting the linklaunder.com password from the options table	
    	$dividname	= get_option( 'linklaunder_dividname' ); 	// The title of the div id, randomized for less footprint
    	$divid		= get_option( 'linklaunder_divid' );			// The actual div style
  
 		if ( ($dividname=='') && ($divid=='') )  { // If the settings has not been set yet
			update_option('linklaunder_divid',"	background: #f3f3f3;padding-top:5px;padding-bottom:5px;font-size: 90%;padding: .5em 10px;border: 1px solid #ddd; ");
			update_option('linklaunder_dividname',linklaunder_random_string()); 
	    	$dividname	= get_option( 'linklaunder_dividname' ); 
    		$divid		= get_option( 'linklaunder_divid' );			
		}
		
		
		
		
    	 		    
		if (($username<>'') && ($password<>'')) { // No communication with the core server, unless a username and password has been set
			global $wpdb;
			$postid=get_the_id(); //the id of the current post we are viewing
			$table=$wpdb->prefix.'linklaunder';
			$linkinfo = $wpdb->get_row("SELECT * FROM $table where postrefid = '$postid'", ARRAY_A);
			if ($linkinfo){ $datediff = linklaunder_datedifference($linkinfo['time']); } //If something is returned from the server, check the values.
			//	604800 = 7 days between checks to the core server
			//  86400  = 1 day between checks to the core server


			if (($linkinfo) && ($datediff<604800)){

			
				if ($linkinfo['link']<>''){
					$content = $content .'<div id="'.$dividname.'">'.$linkinfo['link']."</div>";
			
				}
		
			}
	
			else
				{					
				// GATHERING INFORMATION ABOUT THE POST
				$anchor=the_title("", "", false);
				$url=get_permalink();
				foreach((get_the_category()) as $category) { 
			 	   $categories .= $category->cat_name . ','; 
				} 
				$posttags = get_the_tags();
				if ($posttags) {
					foreach($posttags as $tag) {
						$tags .= $tag->name . ','; 
					}
				}
				include_once(dirname(__FILE__).'/nusoap.php'); // Loading the NuSOAP library			
				
				$client=new nusoap_client($wsdl, 'wdsl');  // Setting up a new client
				$param=array( //The values to be submitted to the webservice
				'username'=>$username,
				'password'=>$password,
				'anchor'=>$anchor,
				'url'=>$url,
				'id'=>$postid,
				'categories'=>$categories,
				'tags'=>$tags,
				); 
				$wsdl="http://linklaunder.com/webservice/server.php?wsdl"; //The link to the webservice
				$wdslreturn = $client->call('LinkLaunder', $param); //The call to the webservice
				
				if ($client->getError()) { //If there is a communication error, we will ignore it, and just return the post content
					return $content;
   					
   					exit;
				} 		
									
				$now = date('Y-m-d H:i:s');
				$wpdb->query( "DELETE FROM $table WHERE $table.`postrefid`='$postid' "); 	
				$wpdb->query( $wpdb->prepare( "INSERT INTO $table( time, postrefid, link ) VALUES ( %s, %d, %s )", $now, $postid, $wdslreturn )); 
					
				if ($wdslreturn<>''){ // Only add the div and the data if not empty
					$content = $content .'<div id="'.$dividname.'">'.$wdslreturn."</div>";
			
				}						
	
			}
		}
	}
	return $content; 
}



// ### Adding the admin menu to the WP-Admin interface
function linklaunder_admin_menu() {
  if (function_exists('add_submenu_page')) {
    add_options_page('Linklaunder SEO', 'LinkLaunder.com SEO', 8, basename(__FILE__), 'linklaunder_admin_options');


  }
}



// ### The Admin options/settings screen
function linklaunder_admin_options(){

    $hidden_field_name = 'll_submit_hidden';


    
    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'
    if( $_POST[ $hidden_field_name ] == 'Y' ) {
        // Read their posted value
        $llusername 	= $_POST[ 'll_username' ];
        $llpassword 	= $_POST[ 'll_password' ];
        $lldividname 	= $_POST[ 'll_dividname' ];
        $lldivid 		= $_POST[ 'll_divid' ];
        
        // Save the posted value in the database
        update_option( 'll_username', $llusername );
        update_option( 'll_password', $llpassword );
        update_option( 'linklaunder_divid', $lldivid );
        update_option( 'linklaunder_dividname', $lldividname );
        
        // Put an options updated message on the screen

		?>
		<div class="updated"><p><strong><?php _e('Options saved.', 'll_options_saved' ); ?></strong></p></div>
		<?php

    }
    // Read in existing option value from database
    $llusername 	= get_option( 'll_username' );
    $llpassword 	= get_option( 'll_password' );
    $lldivid		= get_option('linklaunder_divid');
    $lldividname	= get_option('linklaunder_dividname');
    echo '<div class="wrap">';
	echo "<h2>" . __( 'LinkLaunder options', 'll_options_header' ) . "</h2>";
 	echo "<h3>" . __( 'Username and password', 'll_options_useroptions' ) . "</h3>";

?>
    
    	<form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
		<table>
			<tr>
		<td><?php _e("Username:", 'll_username' ); ?> </td>
		<td><input type="text" name="ll_username" value="<?php echo $llusername; ?>" size="20">
		</td>
			</tr>
			<tr>
		<td><?php _e("Password:", 'll_password' ); ?> </td>
		<td><input type="text" name="ll_password" value="<?php echo $llpassword; ?>" size="20">
		</td>
			</tr>
		</table>

		<p>Not yet a user? <a href='http://linklaunder.com/wp-login.php?action=register' target='_blank'>Sign up on LinkLaunder.com</a> to use this plugin.</p>


<?php

 echo "<h3>" . __( 'CSS Styling options', 'll_options_useroptions' ) . "</h3>";
?>
<p>For removing recognizable footprints, it is now possible to style the link-box. Values have been populated with default values.</p>
<p>If you do not know what is going on here, do not worry, let it be, it will work just fine.</p>
		<table>
			<tr>
		<td><?php _e("Div #name:", 'linklaunder_dividname' ); ?> </td>
		<td><input type="text" name="ll_dividname" value="<?php echo $lldividname; ?>" size="20">
		</td>
			</tr>
			<tr>
		<td><?php _e("Div Style:", 'linklaunder_divid' ); ?> </td>
		<td>
		<textarea name="ll_divid" cols=40 rows=8><?php echo $lldivid; ?></textarea>
		</td>
			</tr>
		</table>
		<p class="submit">
		<input type="submit" name="Submit" value="<?php _e('Update Options', 'll_update_options' ) ?>" />
		</p>
	</form>

</div>

<?php
 
}

// ### 
function linklaunder_plugin_actions($links, $file){
//Code modified with love and appreciation from http://wpengineer.com
	static $this_plugin;
 
	if( !$this_plugin ) $this_plugin = plugin_basename(__FILE__);
 
	if( $file == $this_plugin ){
		$settings_link = '<a href="options-general.php?page=linklaunder.php">' . __('Settings') . '</a>';
		$links = array_merge( array($settings_link), $links); // before other links
	}
	return $links;
}




// ### 
function linklaunder_deactivate(){ //Telling the core server that we are deactivating the plugin.
	$username	= get_option( 'll_username' ); 		// Getting the linklaunder.com username from the options table	
	$password	= get_option( 'll_password' ); 		// Getting the linklaunder.com password from the options table	
	
	$blog_url = get_bloginfo('url');
	$parse = parse_url ($blog_url);
	$domain = $parse[host];

	include_once(dirname(__FILE__).'/nusoap.php'); // Loading the NuSOAP library			
	$wsdl="http://linklaunder.com/webservice/server.php?wsdl"; //The link to the webservice
	$client=new nusoap_client($wsdl, 'wdsl');  // Setting up a new client
	$param=array( //The values to be submitted to the webservice
	'username'=>$username,
	'password'=>$password,
	'blog'=>$domain,
	); 

	$wdslreturn = $client->call('DeActivate', $param); //The call to the webservice


}










$username	= get_option( 'll_username' ); 		// Getting the linklaunder.com username from the options table	
$password	= get_option( 'll_password' ); 		// Getting the linklaunder.com password from the options table	

if ( ($username=='') OR ($password=='')) {
		add_action('admin_notices', 'linklaunder_settings_warning');
} 



function linklaunder_settings_warning() {
	echo '<div class="updated fade"><p><strong>'.__('Remember to sign up as a <a href="http://linklaunder.com" target="_blank">LinkLaunder.com user</a> and enter your user details in the <a href="options-general.php?page=linklaunder.php">' . __('Settings') . '</a>', 'LinkLaunder').'</strong></p></div>';



}



register_activation_hook(__FILE__,'linklaunder_install');
register_deactivation_hook( __FILE__, 'linklaunder_deactivate' );
add_action('admin_menu', 'linklaunder_admin_menu');
add_filter('wp_head', 'linklaunder_css');
add_filter('the_content', 'linklaunder_filter');
add_filter( 'plugin_action_links', 'linklaunder_plugin_actions', 10, 2 );


?>