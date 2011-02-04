<?php
/*
Plugin Name: Team_Registration2
Plugin URI: 
Description: 
Version: 
Author: Daniel Godfrey and Jeffrey Arcand
Author URI:
License: GPL2
*/

/*  Copyright 2010  XXXXXXXXXXXXXXXXXXXXXXXX

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!function_exists('add_action')) {
	require_once("../../../wp-config.php");
}

if (!class_exists("Team_Registration")) {
	class Team_Registration {
		
		var $shortcode = "[Team_Registration]";
		var $is_root = false;
		
		function addContent($content = '')  {
			global $user_ID, $current_user;
			if (strPos($content, $this->shortcode) !== FALSE) {
				get_currentuserinfo();
				$this->is_root = current_user_can('level_10');
				if (strToUpper($_SERVER['REQUEST_METHOD']) == 'POST') {
					switch ($_GET['a']) {
						case 'c':
							$output = $this->createTeamResults();
							break;
						default:
							$output = '';
					}
				} else {
					switch ($_GET['a']) {
						case 't':
							$output = $this->genTeamListScreen();
							break;
						case 'e':
							$output = $this->genTeamScreen();
							break;
						case 'k':
							$this->kickMember();
							$output = $this->genTeamScreen();
							break;
						case 'd':
							$this->dropTeam();
							$output = $this->genTeamListScreen();
							break;
						case 'j':
							$this->joinTeam();
							$output = $this->genTeamScreen();
							break;
						case 'c':
							$output = $this->genTeamCreateScreen();
							break;
						default:
							$output = $this->genTournamentListScreen();
					}
				}
#				if (!$this->is_root)
#					$output = '<p><strong>Note:</strong> This feature is under development, please be patient.</p>';
				$content = str_replace($this->shortcode, '</p>' . $output . '<p>', $content);
			}
			return $content;
		}
		
		function genTeamCreateScreen() {
			global $wpdb;
			$tournament_id = $this->getID('t');
			$table = $wpdb->prefix . 'Team_Registration_Tournaments';
			$tournament = $wpdb->get_row("SELECT * FROM $table WHERE id=$tournament_id", OBJECT);
			
			$src = sPrintF('<h2>Create a new team for %1$s</h2>', $tournament->name);
			$src .= sPrintF('<form action="%1$s&amp;a=c&amp;t=%2$s" method="post">', $this->getBaseUrl(), $tournament_id);
			$src .= '<table><tr><td>Team name:</td><td><input name="team_name" type="text" /></td></tr>
				<tr><td></td><td><input type="submit" value="Create new team" /></td></tr></table>';
			$src .= '</form>';
			return $src;
		}
		
		function genTeamScreen() {
			global $wpdb, $user_ID;
			$team_id = $this->getID('e');
			$team = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}Team_Registration_Teams WHERE id=" . $team_id, OBJECT);
			$tournament = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}Team_Registration_Tournaments WHERE id=" . $team->tournamentID, OBJECT);
			$members = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}Team_Registration_members WHERE teamID=$team_id", OBJECT);
			
			$src = sPrintF('<h2>Manage team &ldquo;%1$s&rdquo; for %2$s</h2>', $team->name, $tournament->name);
			if ($this->isRegisteredTournament()) {
				$src_join = '';
			} else if (count($members) >= $tournament->teamSize) {
				$src_join = 'This team is full.';
			} else {
				$src_join = sPrintF('<a href="%1$s&amp;a=j&amp;e=%2$s">Join this team</a>',
				 $this->getBaseUrl(), $team_id);
			}
			if (count($members) > 0) {
				$src .= '<ul>';
				foreach ($members as $member) {
					$user = get_userdata($member->memberID);
					if ($member->memberID == $user_ID || $team->adminID == $user_ID) {
						$src_kick = sPrintF(' &ndash; <a href="%1$s&amp;a=k&amp;e=%2$s&amp;m=%3$s">%4$s</a>',
						  $this->getBaseUrl(), $team_id, $member->memberID,
						  $member->memberID == $user_ID || $this->is_root ? 'leave team' : 'kick from team');
					} else {
						$src_kick = '';
					}
					$src .= sPrintF('<li>%1$s %2$s</li>', $user->display_name, $src_kick);
				}
				$src .= '</ul>';
				$src .= sPrintF('<p>%1$s</p>', $src_join);
			} else {
				$src .= sPrintF('<p>There are no players on this team.  %1$s</p>', $src_join);
			}
			$src .= sPrintF('<p><a href="%1$s&amp;a=t&amp;t=%2$s">&laquo; Return to Teams screen</a></p>', $this->getBaseUrl(), $team->tournamentID);
			return $src;
		}
		
		function genTeamListScreen() {
			global $wpdb;
			$tournament_id = $this->getID('t');
			$table = $wpdb->prefix . 'Team_Registration_Tournaments';
			$tournament = $wpdb->get_row("SELECT * FROM $table WHERE id=$tournament_id", OBJECT);
			$table = $wpdb->prefix . 'Team_Registration_Teams';
			$teams = $wpdb->get_results("SELECT * FROM $table WHERE tournamentID=$tournament_id ORDER BY name", OBJECT);
			
			$src = sPrintF('<h2>Teams for %1$s</h2>', $tournament->name);
			$tournament_team = $this->getTeamForTournament();
			if ($this->is_root) {
				$fstr_drop = ' &ndash; <a href="%1$s&amp;a=d&amp;e=%2$s&amp;t=%4$s">Delete this team</a>';
			} else {
				$fstr_drop = '';
			}
			if ($tournament_team != null) {
				$src_create = '';
			} else if (count($teams) >= $tournament->maxTeams && !$this->is_root) {
				$src_create = 'This tournament is full.';
			} else {
				$src_create = sPrintF('<a href="%1$s&amp;a=c&amp;t=%2$s">Create a new team</a>',
				 $this->getBaseUrl(), $tournament_id);
			}
			if (count($teams) > 0) {
				$src .= '<ul>';
				foreach ($teams as $team) {
					$src .= sPrintF('<li><a href="%1$s&amp;a=e&amp;e=%2$s">%3$s</a>' . $fstr_drop . '%5$s</li>',
					  $this->getBaseUrl(), $team->id, $team->name, $tournament_id,
					  $team->id == $tournament_team->id ? ' &ndash; this is your team' : '');
				}
				$src .= '</ul>';
				$src .= sPrintF('<p>%1$s</p>', $src_create);
			} else {
				$src .= sPrintF('<p>There are not yet any teams.  %1$s</p>', $src_create);
			}
			$src .= sPrintF('<p><a href="%1$s">&laquo; Return to Tournaments screen</a></p>', $this->getBaseUrl());
			return $src;
		}
		
		function genTournamentListScreen() {
			global $wpdb;
			$table = $wpdb->prefix . 'Team_Registration_Tournaments';
			$tournaments = $wpdb->get_results("SELECT * FROM $table ORDER BY name", OBJECT);
			
			$src = '<h2>Tournaments with Teams</h2><ul>';
			foreach ($tournaments as $tournament) {
				$team = $this->getTeamForTournament($tournament->id);
				$src .= sPrintF('<li><a href="%1$s&amp;a=t&amp;t=%2$u">%3$s</a>%4$s</li>',
				  $this->getBaseUrl(), $tournament->id, $tournament->name, $team != null ? ' &ndash; You are in team &ldquo;' . $team->name  . '&rdquo;' : '');
			}
			$src .= '</ul>';
			return $src;
		}
		
		function getBaseUrl() {
			return '/?page_id=8';
		}
		
		function isRegisteredTournament() {
			return $this->getTeamForTournament() != null;
		}
		
		function getTeamForTournament($tournament_id = null) {
			global $wpdb, $user_ID;
			$team_id = $this->getID('e');
			if (!isSet($tournament_id))
				$tournament_id = $this->getID('t');
			if ($tournament_id == null && isSet($team_id)) {
				$team = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}Team_Registration_Teams WHERE id=$team_id");
				$tournament_id = $team->tournamentID;
			}
			$team = $wpdb->get_row($sql = "SELECT * FROM {$wpdb->prefix}Team_Registration_members m 
			  INNER JOIN {$wpdb->prefix}Team_Registration_Teams t ON m.teamID=t.id
			  WHERE m.memberID=$user_ID AND t.tournamentID=$tournament_id", OBJECT);
			return $team;
		}
		
		function getID($type) {
			$ret = $_GET[$type];
			return preg_match('/^[0-9]+$/', $ret) ? $ret : null;
		}
		
		function createTeamResults() {
			global $wpdb, $user_ID;
			$tournament_id = $this->getID('t');
			$team_name = subStr($_POST['team_name'], 0, 50);
			$teams = $wpdb->get_results("SELECT * FROM $table WHERE tournamentID=$tournament_id", OBJECT);
			$tournament = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}Team_Registration_Tournaments WHERE id=$tournament_id", OBJECT);
		
			if ($this->is_root || ($this->getTeamForTournament($tournament_id) == null && count($teams) < $tournament->maxTeams)) {
				$wpdb->insert($wpdb->prefix . 'Team_Registration_Teams', array('id' => null, 'name' => $team_name, 'adminID' => $user_ID, 'tournamentID' => $tournament_id));
				$team_id = $wpdb->insert_id;
				$wpdb->insert($wpdb->prefix . 'Team_Registration_members', array('teamID' => $team_id, 'memberID' => $user_ID));
			
				$src = sPrintF('<h2>Team created</h2><p><a href="%1$s&amp;a=e&amp;e=%2$u">Manage your team</a></p>',
					 $this->getBaseUrl(), $team_id);
				return $src;
			}
		}
		
		function dropTeam() {
			global $wpdb;
			$team_id = $this->getID('e');
			
			if ($this->is_root) {
				$wpdb->query("DELETE FROM {$wpdb->prefix}Team_Registration_members WHERE teamID=$team_id");
				$wpdb->query("DELETE FROM {$wpdb->prefix}Team_Registration_Teams WHERE id=$team_id");
			}
		}
		
		function joinTeam() {
			global $wpdb, $user_ID;
			$team_id = $this->getID('e');
			$team = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}Team_Registration_Teams WHERE id=" . $team_id, OBJECT);
			$tournament = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}Team_Registration_Tournaments WHERE id={$team->tournamentID}", OBJECT);
			$members = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}Team_Registration_members WHERE teamID=$team_id", OBJECT);
#			echo '--';
#			var_export($this->getTeamForTournament($team->tournamentID));
#			echo '--';
#			var_export(count($members));
#			echo '--';
#			var_export($tournament);
#			echo '--';
			if ($this->getTeamForTournament($team->tournamentID) == null && count($members) < $tournament->teamSize) {
				$wpdb->insert($wpdb->prefix . 'Team_Registration_members', array('teamID' => $team_id, 'memberID' => $user_ID));
			}
		}
		
		function kickMember() {
			global $wpdb, $user_ID;
			$team_id = $this->getID('e');
			$member_id = $this->getID('m');
			$team = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}Team_Registration_Teams WHERE id=" . $team_id, OBJECT);
			
			if ($member_id == $user_ID || $team->adminID == $user_ID || $this->is_root) {
				$wpdb->query("DELETE FROM {$wpdb->prefix}Team_Registration_members WHERE teamID=$team_id AND memberID=$member_id");
			}
		}
	}
}

$wptr = new Team_Registration();
add_filter('the_content', array(&$wptr, 'addContent')); 

?>
