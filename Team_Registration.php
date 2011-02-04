<?php
/*
Plugin Name: Team_Registration
Plugin URI: 
Description: A brief description of the Plugin.
Version: 1.0
Author: Daniel Godfrey and Jeffrey Arcand
Author URI: 
License: GPL2
*/

register_activation_hook(__FILE__,'db_install');

add_action('admin_menu', 'tr_add_pages');


if ($_GET['del'] != null) {
	$delID = $_GET['del'];
	
	$tournaments_table_name = $wpdb->prefix . "Team_Registration_Tournaments";
	$wpdb->query("DELETE FROM $tournaments_table_name WHERE id = $delID");
}

if (isset($_POST['btnAddTournament'])) 
{ 
		
	$tName = $_POST['txtTRName'];
	$teamSize = $_POST['txtTRTeamSize'];
	$teamMax = $_POST['txtTRTeamMax'];
	
	if (is_numeric($teamSize) && is_numeric($teamMax)){
		$tournaments_table_name = $wpdb->prefix . "Team_Registration_Tournaments";
		$wpdb->insert( $tournaments_table_name, array( 'name' => $tName, 'teamSize' => $teamSize, 'maxTeams' => $teamMax), array( '%s', '%d', '%d' ) );
		echo '<script type="text/javascript">alert("Tournament Added!");</script>';
	}
	else {
		echo '<script type="text/javascript">alert("Invalid Values. Only numeric values excepted for team size and max teams.");</script>';
	}
}

function tr_add_pages() {
	add_menu_page('Team Registration','Team Registration', 'manage_options', 'team-reg-top-level-handle', 'main_page_options' );

	add_submenu_page('team-reg-top-level-handle', 'New Tournament', 'New Tournament', 'manage_options', 'add-tournament', 'new_tournament_options');
}


function main_page_options() {
  global $wpdb;
  if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }

  echo '<div class="wrap">';
  echo '<h1>Tournaments</h1>';
  echo '<a class="button add-new-h2" href="admin.php?page=add-tournament">Add New</a>';
 
  $tournaments_table_name = $wpdb->prefix . "Team_Registration_Tournaments";
   
$tournaments = $wpdb->get_results("SELECT * FROM $tournaments_table_name");

  foreach ($tournaments as $tournament) {
	
	echo '<li>'.$tournament->name.' <a href="admin.php?page=team-reg-top-level-handle&del='.$tournament->id.'">delete</a></li>';
  } 
	
  echo '</ul>';
  echo '</div>';

}


function db_install () {
   	global $wpdb;

   	$teams_table_name = $wpdb->prefix . "Team_Registration_Teams";
   	$tournaments_table_name = $wpdb->prefix . "Team_Registration_Tournaments";
   	$members_table_name = $wpdb->prefix . "Team_Registration_members";
	
   	if($wpdb->get_var("SHOW TABLES LIKE '$tournaments_table_name'") != $tournaments_table_name) {
 		$sql = "CREATE TABLE " . $tournaments_table_name . " (
	 	id mediumint(9) NOT NULL AUTO_INCREMENT,
	  	name tinytext NOT NULL,
	  	teamSize tinyint unsigned NOT NULL,
	  	maxTeams tinyint unsigned NOT NULL,
	  	PRIMARY KEY(id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	if($wpdb->get_var("SHOW TABLES LIKE '$teams_table_name'") != $teams_table_name) {
 		$sql = "CREATE TABLE " . $teams_table_name . " (
	 	id mediumint(9) NOT NULL AUTO_INCREMENT,
	  	name tinytext NOT NULL,
	  	adminID bigint(20) unsigned NOT NULL,
	  	tournamentID mediumint(9) NOT NULL,
	  	PRIMARY KEY(id)		
		
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	if($wpdb->get_var("SHOW TABLES LIKE '$members_table_name'") != $members_table_name) {
 		$sql = "CREATE TABLE " . $members_table_name . " (
	 	teamID mediumint(9) NOT NULL,
		memberID bigint(20) unsigned NOT NULL,
	  	PRIMARY KEY(teamID,memberID)		
		
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

   
}

function new_tournament_options() {
	echo '<form action="admin.php?page=add-tournament" method="post">';
	echo '<h1>New Tournament</h1>';
	echo 'Name:<input type="text" id="txtTRName" name="txtTRName"></input><br/>';
	echo 'Team Size: <input type="text" id="txtTRTeamSize" name="txtTRTeamSize"></input><br/>';
	echo 'Max Number of Teams: <input type="text" id="txtTRTeamMax" name="txtTRTeamMax"></input><br/>';
	echo '<input type="submit" id="btnAddTournament" name="btnAddTournament" value="Add Tournament"></input>';
	echo '</form>';
}




?>