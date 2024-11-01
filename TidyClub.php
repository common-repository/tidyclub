<?php

/*
Plugin Name: TidyClub
Plugin URI: http://nuancedmedia.com/tidyclub-wordpress-plugin/
Description: TidyClub plugin communicates with your club on tidyclub.com, pulls in all of your scheduled events, and allowed you to display them either in a full calendar or as blog posts.
Version: 1.0.2
Author: Nuanced Media
Author URI: http://www.nuancedmedia.com
License: GPLv2 or later
*/

/*  Copyright 2013  Nuanced Media (email : austinadamson@nuancedmedia.com)

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





/*
###############################   Includes ###################################
*/ 

include 'tcAdminMenu.php';
include 'nmCron.php';
if (!class_exists('NMTidyClubAdminHelper')) {
    require_once('views/view-helper/admin-view-helper-functions.php');
}

/*

#################################  Tidy Club Class ###############################

*/

class tidyClub {

	var $sqltable = 'tidyclub_events';
	var $tc_admin_options = 'tc_version';
	// var $updated_today = false;
	// public $result_array;

	function __construct(){

		global $wpdb;
		$version = array(
			'version' => '1.0.1'
		);
		update_option($this->tc_admin_options, $version);
		add_action('init', array(&$this, 'init'));
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'tc_add_plugin_settings_link') );
	}

	function add_event_post($event) {
		/*  This function creates events for all new posts and adds them to the TidyClub sql database. Also it updates all previously existing posts  */
		global $wpdb;
		$tc_event_id_count = $wpdb->get_var("SELECT COUNT(*) FROM $this->sqltable WHERE `tc_event_id`=$event->id");
		/* If the event does not already exist, add the new post -- else update existing post and existing database entries.*/
		if ($tc_event_id_count != 1){	
			$post_id = $this->create_event_post($event);
			$newdata = array(
				'tc_event_id'     => $event->id,
				'wp_post_id'      => $post_id,
				'start_time'    => $event->start_at,
			);
			$wpdb->insert($this->sqltable, $newdata);
		}
		else {
			$tc_database_id = $wpdb->get_var("SELECT `id` FROM $this->sqltable WHERE `tc_event_id`=$event->id");
			$replace_data = array(
				'tc_event_id'     => $event->id,
				'start_time'    => $event->start_at,
			);
			$wpdb->update($this->sqltable, $replace_data, array(
				'id' => $tc_database_id
			));
			$this->update_existing_post($event);
		};
	}

	/**
	 * Creates new event posts and returns WordPress Post ID
	 * @param  object $event
	 * @return int $post_id
	 */
	function create_event_post($event){
		/* Creates new event posts and returns the WordPress post ID */
		global $wpdb;
		$post = array( 
			"post_type" => 'tc_event',
			'post_status'=> 'publish',
			'post_content' => wp_strip_all_tags($event->body),
			"post_title" => $event->name,
			'event_location' => $event->location,
			'start_time' => $event->start_at,
			'end_time' => $event->end_at,
		);
		$post_id = wp_insert_post($post, true);
		return $post_id;

	}
	function tc_add_plugin_settings_link($links) {
		$settings_link = '<a href="admin.php?page=tc_settings">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}

	function update_existing_post($event){
		/*  Updates all existing posts */
		global $wpdb;
		$wp_post_id = $wpdb->get_var( "SELECT `wp_post_id` FROM $this->sqltable WHERE `tc_event_id`=$event->id" );
		$post_update = array(
			'ID' =>$wp_post_id,
			'post_type' => 'tc_event',
			'post_content' => wp_strip_all_tags($event->body),
			'post_title' => $event->name,
			'event_location' => $event->location,
			'start_time' => $event->start_at,
			'end_time' => $event->end_at
		);
		wp_update_post($post_update);
	}

	function init(){
		/*  
		Firstly, the wp_tidyclub_events database is created. Then, if a access token does not exist, it prompts the first settings page and adds the no_token() to the calendar shortcode. Once an access token exists then post type is created, the nmcron is ran and the shortcode_calendar() is added to the shortcode. 
		 */
		global $wpdb, $nmcron;

		$this->sqltable = $wpdb->prefix . $this->sqltable;
		$tableSearch = $wpdb->get_var("SHOW TABLES LIKE '$this->sqltable'");
		if ($tableSearch != $this->sqltable) {
			$this->fp_update_database();
		}

		if (!$this->is_registered()){
			/*
			This is executed if the access token isn't already set. 
			*/
			add_shortcode('tidyclub_calendar', array(&$this, 'no_token'));
			add_filter('widget_text', 'do_shortcode');
		}

		if ($this->is_registered()){
			/*
			Executed if the access token is set. 
			*/
			add_action('wp_enqueue_scripts', array(&$this, 'tc_load_styles'), 100);
			$this->create_event_post_type();
			add_shortcode('tidyclub_calendar', array(&$this, 'tc_shortcode_evaluated'));
			add_filter('widget_text', 'do_shortcode');
			/* This is nmcron.php which will return a 1 if it is time to query or a 0 if it is not time to query.  */
			$run = $wpdb->get_var( "SELECT `run` FROM $nmcron->sqltable_cron WHERE `id`=1" );
			if ($run == 1) {
				$result_array = $this->getQuery();
				foreach($result_array as $event){
					$this->add_event_post($event);
				}
			}
		}
	}

	function tc_shortcode_evaluated($atts){
		global $wpdb;
		global $post;

		$output = '';
		extract(shortcode_atts(
			array(
				'past' => '0',
				'future' => '0',
			)
		,$atts));
		$today = getdate();
		if ($past >= '0'){
			$past = intval($past);
			while ($past > 0){
				if ($today['mon']-$past < 1){
					$display_month = array(
						'mday' => NULL,
						'mon' => $today['mon'] - $past + 12,
						'year' => $today['year']-1,
						'month' => date('F', mktime(0, 0, 0, $today['mon']-$past +13, 0, $today['year']-1)),
						);
				}
				else{
					$display_month = array(
						'mday' => NULL,
						'mon' => $today['mon'] - $past,
						'year' => $today['year'],
						'month' => date('F', mktime(0, 0, 0, $today['mon']-$past+1, 0, $today['year'])),
						);
				}
				$past = $past - 1;
				$output .= $this->shortcode_calendar($display_month);
			}
		}
		$output .= $this->shortcode_calendar($today);
		if ($future >= '0'){
			$future = intval($future);
			$mon = 1;
			while ($mon <= $future){
				if ($today['mon']+$mon > 12){
					$display_month = array(
					'mday' => NULL,
					'mon' => $today['mon'] + $mon - 12,
					'year' => $today['year']+1,
					'month' => date('F', mktime(0, 0, 0, $today['mon']+$mon-11, 0, $today['year']+1)),
					);
				}
				else{
					$display_month = array(
						'mday' => NULL,
						'mon' => $today['mon'] + $mon,
						'year' => $today['year'],
						'month' => date('F', mktime(0, 0, 0, $today['mon']+$mon+1, 0, $today['year'])),
						);
				}
				$mon = $mon + 1;
				$output .= $this->shortcode_calendar($display_month);
			}
		}
		$permission = get_option('tc_credit_permission');
		if ($permission['permission_value']==='checked'){
			$output.='<div class="credit-line"><p>Brought to you by <a href="http://www.nuancedmedia.com">Nuanced Media</a>.<p></div>';
		}
		return $output;
	}

	function no_token(){
		/*
		Echo message id acces token does not exist. Called by init if is_registered returns false. 
		*/
		echo '<div class="no-token-message">Please register your settings on the TidyClub Settings page.</div>';
	}

	function is_registered(){
		/*
		Checks to see is an access token exists in the options database. 
		*/
		$options = get_option('TidyClub_Settings');
		if (isset($options['tc_access_token'])){
			return TRUE;
		}
		else{
			return FALSE;
		}
	}

	function fp_update_database() {
		/*  Build the TidyCLub database if and only if it does not already exist */
		global $wpdb;
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		$sql = "CREATE TABLE $this->sqltable (
				 id int(11) NOT NULL AUTO_INCREMENT,
				 wp_post_id INT(11) NOT NULL,
				 tc_event_id INT(11) NOT NULL,
				 start_time datetime NOT NULL,
				PRIMARY KEY (id)
				)
				CHARACTER SET utf8
				COLLATE utf8_general_ci;";
		dbDelta($sql);
	}

	function create_event_post_type() {
		/*  registers the event type post with WordPress  */
		register_post_type('tc_event',
			array(
			'labels' => array(
				'name' => __( 'Tidyclub Events' ),
				'singular_name' => __( 'Tidyclub Event' )
			),
			'public' => true,
			'has_archive' => true,
			'show_ui'   => false,
			)
		);
	}

	function tc_load_styles() {
		$pluginDirectory = trailingslashit(plugins_url(basename(dirname(__FILE__))));
		wp_register_style('tidyclub-styles', $pluginDirectory . 'css/tc_calendar.css');
		wp_enqueue_style('tidyclub-styles');
	}


	

	/*=====================================================================================================
	======== Gets Queried Items  =================================================================
	=====================================================================================================*/

	function getQuery() {
		global $wpdb;
		$options = get_option('TidyClub_Settings');
		$access_token = $options['tc_access_token'];
		$url = 'https://'.$options['tc_domain_prefix'].'.tidyclub.com/api/v1/events?access_token=' . $options['tc_access_token'];
        $ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
		echo curl_error($ch);
        curl_close($ch);
        $result_array = json_decode($result);
		return $result_array;
	}

	/*=====================================================================================================
	======== Calendar Shortcode Execution =================================================================
	=====================================================================================================*/


	function shortcode_calendar($today) {
		/*  Builds Calendar --- Links events to dates */ 
		$output = '<div class="tidyclub-calendar">';
		// Why do you build me up (buttercup) baby just to let me down (let me down)?
		$month = array();
		$week = array();
		$firstWeekdayOfMonth = date('w', mktime(0, 0, 0, $today['mon'], 0, $today['year']));
		$daysInMonth = date('d', mktime(0, 0, 0, $today['mon']+1, 0, $today['year']));
		if ($firstWeekdayOfMonth != 6){
			for ($i=0; $i<$firstWeekdayOfMonth+1; $i++){
				// empty days have no string as their day of month
				$day = array(
				'content' => '<div class="no-date"></div>',
				'has_event' => FALSE,
				'today'=> FALSE
				);
				$week[] = $day;
			}
		}
		for ($i=1; $i<=$daysInMonth; $i++){
			// Put day of month as 0'th element of day array
			$day = $i;
			$day = $this->dateMatching($day, $today);

			// check and see if there's an event on this day.
			// add it to the day's array.
			// add day to week
			$week[] = $day;

			// If we have added 7 days to the week . . .
			if (count($week)>=7)
			{
			// . . . then add the week to the month, and reset.
				$month[]=$week;
				$week = array();
			}
		}
		// If we aren't quite spent, then add the week
		// to the month. Also "we" (i.e., you.) should
		// probably add the extra days to it as well.
		if ($week != array()) 
		{
			$month[] = $week;
		}

		// Now that we've built the month, let's print it out
		$output .= '<h4 class="tc-current-date-display">' . /*$today['mday'] . ' ' .*/ $today['month'] . ' ' . $today['year'] . '</h4>';
		$output .= '<table class="table calendar-month heading-date">';
		$output .= '<thead>';
		$output .= '<tr>';
		$output .= '<th class="calendar-widget-headings tc-header-label">Sun</th>';
		$output .= '<th class="calendar-widget-headings tc-header-label">Mon</th>';
		$output .= '<th class="calendar-widget-headings tc-header-label">Tue</th>';
		$output .= '<th class="calendar-widget-headings tc-header-label">Wed</th>';
		$output .= '<th class="calendar-widget-headings tc-header-label">Thu</th>';
		$output .= '<th class="calendar-widget-headings tc-header-label">Fri</th>';
		$output .= '<th class="calendar-widget-headings tc-header-label">Sat</th>';
		$output .= '</tr>';
		$output .= '</thead>';
		foreach ($month as $week) {
			$output .= '<tr class="calendar-week">';
			foreach ($week as $day) {
				if (isset($day['has_event']) and isset($day['content']) and isset($day['today'])){
					if (($day['has_event'])==TRUE){
						if ($day['today'] === TRUE){
							$output .= '<td class="tc-table-data tc-day tc-has-event tc-current-day-date ">';
							$output .= '<div class="widget-calendar-entry tc-date">' . ($day['content']) . '</div>';
							$output .= '</td>';
						}
						else{
							$output .= '<td class="tc-table-data tc-day tc-has-event">';
							$output .= '<div class="widget-calendar-entry tc-date">' . ($day['content']) . '</div>';
							$output .= '</td>';
						}
					}
					else {
						if ($day['today'] === TRUE){
							$output .= '<td class="tc-table-data tc-day tc-no-event tc-current-day-date ">';
							$output .= '<div class="widget-calendar-entry">' . ($day['content']) . '</div>';
							$output .= '</td>';
						}
						else{
							$output .= '<td class="tc-table-data tc-day tc-no-event">';
							$output .= '<div class="widget-calendar-entry">' . ($day['content']) . '</div>';
							$output .= '</td>';
						}
					}
				}
			}

			$output .= '</tr>';
		}
		$output .= '</table>';
		
		$style = get_option('TidyClub_settings');
		$output .= '<style>';
		$output .= '.tidyclub-calendar .tc-current-date-display { color: ' . $style['tc_calendar_font_color'] . '; }' . PHP_EOL;
		$output .= '.tidyclub-calendar .tc-day { color: ' . $style['tc_calendar_font_color'] . ';' . 'background-color: ' . $style['tc_calendar_background_color'] . '; border-bottom: solid 1px ' . $style['tc_calendar_border_color'] . '; }' . PHP_EOL;
		$output .= '.tidyclub-calendar th { color: ' . $style['tc_calendar_font_color'] . '; background-color: ' . $style['tc_calendar_background_color'] . '; border-bottom: solid 1px ' . $style['tc_calendar_border_color'] . '; }' . PHP_EOL;
		$output .= '.tidyclub-calendar .calendar-month .calendar-week .tc-has-event { background-color: ' . $style['tc_calendar_has_event_background_color'] . ';}' . PHP_EOL;
		$output .= '.credit-line p{ font-size:12px; }' . PHP_EOL;
		$output .= '.tc-header-label{ color:' . $style['tc_calendar_font_color'] . ';}' . PHP_EOL;
		$output .= '.tidyclub-calendar .tc-current-day-date { border: 2px solid ' . $style['tc_calendar_current_color'] . '; margin: 5px; text-align:center; }' . PHP_EOL;
		$output .= '</style>' . PHP_EOL;

		return $output;
	}

	function dateMatching($day, $today){
		/*
		Matches the dates on the calendar with the links to the event pages for that date. This is done by checks if event start time is within the timeframe of today. 
		*/ 
		global $wpdb;
		if ($today['mon'] < 10){
			$thisday= $today['year'] . '-0' . $today['mon'] . '-' . $day . ' 00:00:00';
			$tomorrow= $today['year'] . '-0' . $today['mon'] . '-' . $day . ' 23:59:59';
		}

		else {
			$thisday= $today['year'] . '-' . $today['mon'] . '-' . $day . ' 00:00:00';
			$tomorrow= $today['year'] . '-' . $today['mon'] . '-' . $day . ' 23:59:59';
		}
		$date = $day;
		$tomorrow= $today['year'] . '-' . $today['mon'] . '-' . $day . ' 23:59:59';
		$wp_post_id = $wpdb->get_var("SELECT `wp_post_id` FROM $this->sqltable WHERE `start_time`>'$thisday' AND `start_time`<'$tomorrow'");
		if ($wp_post_id != NULL){
			$link = post_permalink($wp_post_id);
			if ($today['mday'] == $date){
				$day = array(
					'content' => '<a href="' . $link . '/">' . $day . '</a>',
					'has_event' => TRUE,
					'today'=> TRUE
					);
			}
			else{
				$day = array(
					'content' => '<a href="' . $link . '/">' . $day . '</a>',
					'has_event' => TRUE,
					'today'=> FALSE
					);
			}
		}
		else {
			if ($today['mday'] == $date){
				$day = array( 
					'content' => $day,
					'has_event' => FALSE,
					'today' => TRUE,
					);
			}
			else{
				$day = array( 
					'content' => $day,
					'has_event' => FALSE,
					'today' => FALSE,
					);
			}

		}
		return $day;
	}
}
global $file;
$file = ABSPATH . 'wp-content/plugins/tidyclub/tidyclub.php';

$tidyclub = new tidyClub();


/*===================================================
========  dump() function for debug ===========================
===================================================*/

if (!function_exists('dump')) {function dump ($var, $label = 'Dump', $echo = TRUE){ob_start();var_dump($var);$output = ob_get_clean();$output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);$output = '<pre style="background: #FFFEEF; color: #000; border: 1px dotted #000; padding: 10px; margin: 10px 0; text-align: left;">' . $label . ' => ' . $output . '</pre>';if ($echo == TRUE) {echo $output;}else {return $output;}}}if (!function_exists('dump_exit')) {function dump_exit($var, $label = 'Dump', $echo = TRUE) {dump ($var, $label, $echo);exit;}}

