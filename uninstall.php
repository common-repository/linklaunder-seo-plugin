<?php
/*
Uninstall file for LinkLaunder Wordpress Plugin.
http://www.linklaunder.com

*/
	if( !defined( ÔABSPATHÕ) && !defined(ÕWP_UNINSTALL_PLUGINÕ) ) { exit(); }
	
	global $wpdb;
	$table_name = $wpdb->prefix . "linklaunder";
	delete_option('linklaunder_db_version');
	delete_option('ll_username');
	delete_option('ll_password');
    delete_option('linklaunder_divid');
    delete_option('linklaunder_dividname');
	
	
	//TODO: Delete the local database
	//TODO: Tell the core server that we are uninstalling
		
?>