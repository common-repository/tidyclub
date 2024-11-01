<?php
/*

#################################  Tidy Club Admin Menu Class ###############################

*/

class tcAdminMenu {
	// Holds the values to be used in the fields callbacks
	private $options;

	// Start Up
	public function __construct() {
		add_action('admin_enqueue_scripts', array(&$this, 'load_option_styles'));
		add_action('admin_menu', array( $this, 'add_plugin_page' ));
	}

	// Add option page
	public function add_plugin_page() {
		// This page will be under "Settings"
		add_menu_page(
			'TidyClub Settings', 
			'TidyClub', 
			'administrator', 
			'tc_settings', 
			array($this, 'create_admin_page')
		);
	}

	// Options page callback
	public function create_admin_page() {
		global $wpdb, $nmcron;
		// Set class property
		?>
		<div class="wrap">
			<?php screen_icon(); ?>          
			<form method="post" action="">
				<?php
				if(isset($_POST['submitted']) && $_POST['submitted'] == 'tcSecrets'){
					$tidyclubsettings = get_option('TidyClub_Settings');
					$access_token = $this->set_access_token($_POST);
					$TidyClub_Access_Token = array(
						'tc_access_token' => $access_token,
						'tc_domain_prefix' => $_POST['tc_domain_prefix'],
						'tc_calendar_border_color' => $tidyclubsettings['tc_calendar_border_color'],
						'tc_calendar_font_color' => $tidyclubsettings['tc_calendar_font_color'],
						'tc_calendar_background_color'=> $tidyclubsettings['tc_calendar_background_color'],
						'tc_calendar_has_event_background_color' => $tidyclubsettings['tc_calendar_has_event_background_color'],
						'tc_calendar_current_color' => $tidyclubsettings['tc_calendar_current_color'],

					);
					update_option('TidyClub_Settings', $TidyClub_Access_Token);
				}
				$tidyclubsettings = get_option('TidyClub_Settings');
				if ($tidyclubsettings['tc_access_token'] === NULL){   
					// if there is no access token set, then this is run, requesting information required to generate accesss token.
					$TidyClub_Access_Token = array(
						'tc_calendar_border_color' => '#dddddd',
						'tc_calendar_font_color' => '#000000',
						'tc_calendar_background_color'=> '#ffffff',
						'tc_calendar_has_event_background_color' => '#dddddd',
						'tc_calendar_current_color' => '#ad3d3d',
					);
					update_option('TidyClub_Settings', $TidyClub_Access_Token);
					?>
					<div class="tc-settings">
						<div class="tc-settings-header">
							<h2>TidyClub Settings</h2>
							<p>Thank you for choosing to use the TidyClub plugin for Wordpress. Please fill out the following before continuing. We <strong>will not</strong> store your username and password. </p>
						</div>
						<input type="hidden" name="submitted" value="tcSecrets" />
						<table>
							<tr class="tc-register-settings-option">
								<td><label>TidyClub Username</label></td>
								<td><input name="tc_username" type="text" autocomplete="on" /></td>
							</tr>

							<tr class="tc-register-settings-option">
								<td><label>TidyClub Password</label></td>
								<td><input name="tc_password" type="password" autocomplete="off" /></td>
							</tr>

							<tr class="tc-register-settings-option">
								<td><label>TidyClub Domain Prefix</label></td>
								<td><input name="tc_domain_prefix" type="text" autocomplete="on" /></td>
								<td><h5>(Example: yourdomainprefix.tidyclub.com)</h5></td>
							</tr>

							<tr>
							<td></td>
							<td><input type="submit" value="Submit" formaction='/wp-admin/admin.php?page=tc_settings' class="button" /></td>
						</table>
					</div>

					<?php
				}

				$tidyclubsettings = get_option('TidyClub_Settings');
				if (isset($tidyclubsettings['tc_access_token'])){
					//this is the standard options page for the TidyClub Plugin.
					$this->sqltable_cron = $wpdb->prefix . 'nmcron';
					$run = $wpdb->get_var( "SELECT `run` FROM $nmcron->sqltable_cron WHERE `id`=1" );
					if ($run == 1) {
						$club_info = $this->queryClub();
						if (isset($club_info->name)){
							$update_name = array(
								'tc_access_token'  => $tidyclubsettings['tc_access_token'],
								'tc_domain_prefix' => $tidyclubsettings['tc_domain_prefix'],
								'club_name'        => $club_info->name,
								'tc_calendar_border_color' => $tidyclubsettings['tc_calendar_border_color'],
								'tc_calendar_font_color' => $tidyclubsettings['tc_calendar_font_color'],
								'tc_calendar_background_color'=> $tidyclubsettings['tc_calendar_background_color'],
								'tc_calendar_has_event_background_color' => $tidyclubsettings['tc_calendar_has_event_background_color'],
								'tc_calendar_current_color' => $tidyclubsettings['tc_calendar_current_color'],
								);
							update_option('TidyClub_Settings', $update_name);
						}
					}	
					?>
						
					<div class="tc-options-page">
						<h1>TidyClub</h1>
						<?php NMTidyClubAdminHelper::render_container_open('three-fifths'); 
						NMTidyClubAdminHelper::render_postbox_open('Instructions'); ?>
						<p>Thanks for using the TidyClub Plugin for WordPress! At this point you can place the shortcode <strong>[tidyclub_calendar]</strong> on any page to display the current month and its events.</p>
						<p>If you are interested if displaying multiple month, this is completely possible. In order to display one past month, add "past=1" to your shortcode. Also, If you would like to add one future month, add "future=1" to your shortcode. The current month will always display. Past and future can be combined in your shortcode. Example: <strong>[tidyclub_calendar past=1 future=1]</strong>.</p>
						<?php NMTidyClubAdminHelper::render_postbox_close(); ?>

						<?php NMTidyClubAdminHelper::render_postbox_open('Visual Options'); ?>
						<form method="post" action="">
							<?php
							if (isset($_POST['color_update']) && $_POST['color_update'] == 'update_color') {
								$options = get_option('TidyClub_Settings');
								$info_array = array(
									'tc_access_token' => $options['tc_access_token'],
									'tc_domain_prefix' => $options['tc_domain_prefix'],
									'club_name' => $options['club_name'],
									'tc_calendar_border_color' => $_POST['tc_calendar_border_color'],
									'tc_calendar_font_color' => $_POST['tc_calendar_font_color'],
									'tc_calendar_background_color'=> $_POST['tc_calendar_background_color'],
									'tc_calendar_has_event_background_color' => $_POST['tc_calendar_has_event_background_color'],
									'tc_calendar_current_color' => $_POST['tc_calendar_current_color']
									);
								update_option('TidyClub_Settings', $info_array);
							}
							if (isset($_POST['update_permission']) && $_POST['update_permission'] == 'permission update') {
								if (isset($_POST['credit_permission'])){
									$permission=array(
										'permission_value'=>$_POST['credit_permission']
										);
									update_option('tc_credit_permission', $permission);
									$_POST['update_permission'] = NULL;
								}
								else  {
									$permission=array(
										'permission_value'=> NULL,
										);
									update_option('tc_credit_permission', $permission);
									$_POST['update_permission'] = NULL;
								}
							}
							?>
							<?php $tidyclubsettings = get_option('TidyClub_Settings'); ?>
							<div class="tc_color_options">
								<input type="hidden" name="color_update" value="update_color" />
								<div class="tc-color-picker">
									<label>Border Color</label>
									<input type="color" name="tc_calendar_border_color" value="<?php echo $tidyclubsettings['tc_calendar_border_color']; ?>" />
								</div>
								<div class="tc-color-picker">
									<label>Font Color</label>
									<input type="color" name="tc_calendar_font_color" value="<?php echo $tidyclubsettings['tc_calendar_font_color']; ?>" />
								</div>
								<div class="tc-color-picker">
									<label>Background Color</label>
									<input type="color" name="tc_calendar_background_color" value="<?php echo $tidyclubsettings['tc_calendar_background_color']; ?>" />
								</div>
								<div class="tc-color-picker">
									<label>Background Color</label>
									<input type="color" name="tc_calendar_has_event_background_color" value="<?php echo $tidyclubsettings['tc_calendar_has_event_background_color']; ?>" /><br/>
									<div>(Used when event is present on that day)</div>
								</div>
								<div class="tc-color-picker">
									<label>Today's Color</label>
									<input type="color" name="tc_calendar_current_color" value="<?php echo $tidyclubsettings['tc_calendar_current_color']; ?>" /><br/>
									<div>(Used for the border of today's date)</div>
								</div>

								<div class="tc-color-picker tc-color-picker-options">
									<input type="submit" value="Change Color Settings" formaction="/wp-admin/admin.php?page=tc_settings" class="button" />
									<?php
									if (isset($_POST['color_update']) && $_POST['color_update'] == 'update_color') {
										echo "<h3>Color Update Successful!</h3>";
									}
									?>
								</div>
							</div>
						</form>
						<?php NMTidyClubAdminHelper::render_postbox_close(); ?>

						<?php NMTidyClubAdminHelper::render_postbox_open('Update Events'); ?>
						<h3>Need to update the events right now?</h3>
						<p>The below button will force the TidyClub plugin to update the database of events. This way if you just posted an event on TidyClub and want the event on the calendar right now, all thats needed is one click. </p>
						<div class="demand-update-button">
							<form method="POST" action="">
								<input type="hidden" name="demand_update" value="right now" />
								<input type="submit" value="Update Events Now" class="button" />
								<?php
								if (isset($_POST['demand_update']) && $_POST['demand_update'] == 'right now') {
									echo "<h3>Event Update Successful!</h3>";
								}
								?>
							</form>
						</div>
						<?php NMTidyClubAdminHelper::render_postbox_close(); ?>

						<?php NMTidyClubAdminHelper::render_postbox_open('Support Our Staff'); ?>
						
                        <?php $this->insert_support(); ?>
                        
						<?php NMTidyClubAdminHelper::render_postbox_close(); 
						NMTidyClubAdminHelper::render_container_close();?>

						<?php 
						NMTidyClubAdminHelper::render_container_open('two-fifths');
						$permission = get_option('tc_credit_permission');
						if (!$permission['permission_value'] == 'checked') {
							NMTidyClubAdminHelper::render_postbox_open('Support the Staff');
							$this->insert_support();
							NMTidyClubAdminHelper::render_postbox_close();
						}
						NMTidyClubAdminHelper::render_sidebar();
						NMTidyClubAdminHelper::render_container_close(); 
						?>
					</div>
					<div class="clear"></div>
					<?php
					if (isset($_POST['demand_update']) && $_POST['demand_update'] == 'right now') {
						$this->demand_update_cron();
						$_POST['demand_update'] = NULL;
					}
					if (isset($_POST['color_update']) && $_POST['color_update'] == 'update_color') {
						$_POST['color_update'] = NULL;
					}
				}
				?>
			</form>
		</div>
		<?php
		
	}

	function insert_support() {

        ?>
		<div class="clear credit-permission nm-support-box">
        <?php
        $permission = get_option('tc_credit_permission');
        ?>
        <form method="post" action="" name="credit_permission">
            <input type="hidden" name="update_permission" value="permission update" />
            <?php $permission_val = ''; if ($permission['permission_value'] == 'checked') { $permission_val = 'checked'; } ?>
            <div class="nm-support-staff-checkbox">
                <input type="checkbox" name="credit_permission" value="checked" <?php echo $permission_val; ?> />
            </div>
            <div class="nm-support-staff-label">
                <label for="support">We thank you for choosing to use our plugin! We would also appreciate it if you allowed us to put our name on the plugin we worked so hard to build. If you are okay with us having a credit line on the calendar, then please check this box and change your permission settings.</label>
            </div>
            <br />
            <input type="submit" value="Change Permission Setting" form_id="credit_permission" class="nm-support-staff-submit button" />
        </form>
        </div>
        <?php
	}

	function demand_update_cron(){
		// This forces the nmcron to return a 1, which then allows for a query of events. 
		global $wpdb, $nmcron;
		$this->sqltable_cron = $wpdb->prefix . 'nmcron';
		$data = array(
			'last_date' => date('Y-m-d H:00:00'),
			'run'       => 1,
			);
		$where = array(
			'id' => 1
			);
		$wpdb->update( $this->sqltable_cron, $data, $where);
	}

	function load_option_styles() {
		$pluginDirectory = trailingslashit(plugins_url(basename(dirname(__FILE__))));
		wp_register_style('tidyclub-styles', $pluginDirectory . 'css/tc-admin-styles.css');
		wp_enqueue_style('tidyclub-styles');
		wp_register_script('tc-scripts', $pluginDirectory . 'views/view-helper/js/nm-dashboard-script.js', array('jquery'));
		wp_enqueue_script('tc-scripts');
	}

	/*=====================================================================================================
	======== Gets Queried Items  =================================================================
	=====================================================================================================*/

	function queryClub() {

		global $wpdb;
		$options = get_option('TidyClub_Settings');
		$access_token = $options['tc_access_token'];
		$tc_domain_prefix = $options['tc_domain_prefix'];
		$url = 'https://' . $tc_domain_prefix . '.tidyclub.com/api/v1/club?access_token=' . $access_token;
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
	======== Access Token Aquisition  =================================================================
	=====================================================================================================*/

	function set_access_token($settings_array){

		$fields = array(
			'client_id' => '54ac21a991eb6077b2da5bf4e6c1ebd307bd9391e27813c86034306972c1a505',
			'client_secret' => '5fe25b861c5428c54bb239326f0f20f270606d34f5a6c894d97b6830234c7279',
			'username' => $settings_array['tc_username'],
			'password' => $settings_array['tc_password'],
			'grant_type' => 'password'
			);

		$url = 'https://' . $settings_array['tc_domain_prefix'] . '.tidyclub.com/oauth/token';
		$ch = curl_init();
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_POST =>1,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 5,
			CURLOPT_URL => $url,
			CURLOPT_POSTFIELDS => $fields,
			));
		$access_token = curl_exec($ch);
		curl_close($ch);
		$access_token = substr( $access_token, 17, -74);
		return $access_token;
	}
}

/**
* Register and add settings
*/

if( is_admin() ){
	$my_settings_page = new tcAdminMenu();
}