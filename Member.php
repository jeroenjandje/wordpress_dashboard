<?php

namespace App\Dashboard;

/**
 * Class Company
 * @package App\Dashboard
 */
class Member extends DashboardController
{

	protected const TABLE_NAME = 'employee_member_pivot';

	/**
	 * Setup the actions and filters used by this class
	 */
	public static function setup(): void
	{
		self::create_employee_member_pivot_table();
		add_action( 'init', [ 'App\Dashboard\Member', 'register_post_type' ] );
		add_action( 'init', [ 'App\Dashboard\Member', 'register_taxonomies' ] );
		add_action( 'wp_ajax_nopriv_switch_member', [ 'App\Dashboard\Member', 'switchMemberID' ] );
		add_action( 'wp_ajax_switch_member', [ 'App\Dashboard\Member', 'switchMemberID' ] );

		add_action( 'wp_ajax_nopriv_update_member', [ 'App\Dashboard\Member', 'updateMember' ] );
		add_action( 'wp_ajax_update_member', [ 'App\Dashboard\Member', 'updateMember' ] );

		add_action( 'wp_ajax_nopriv_remove_employee_from_member', [ 'App\Dashboard\Member', 'ajaxRemoveEmployeeFromMember' ] );
		add_action( 'wp_ajax_remove_employee_from_member', [ 'App\Dashboard\Member', 'ajaxRemoveEmployeeFromMember' ] );

		add_action( 'save_post', [ 'App\Dashboard\Member', 'onSaveMember' ] );
		add_action( 'delete_post', [ 'App\Dashboard\Member', 'onTrashMember' ] );

		if(empty(self::$member_id)) {
			self::memberIDExists();
		}
	}

	/**
	 * Returns all companies linked to the provided userID
	 *
	 * @param $userID
	 */
	public static function all($userID = false): array
	{
		if(empty($userID)) $userID = self::getUserId();
		return self::getUserMembers($userID);
	}

	/**
	 * Switches the used static variable $member_id
	 * and will refresh the dashboard template on success.
	 */
	public static function switchMemberID()
	{
		check_ajax_referer('switch_member', 'nonce');

		if(empty($_POST['member_id'])) {
			error_log('[Dashboard\Members->switchMemberID]: The member_id was not provided');
			wp_send_json_error("We konden niet overschakelen, probeer het opnieuw.");
		}

		$user_id = self::getUserId();

		// Update the pivot table to unset the active member
		self::switch_active_member($user_id, self::getMemberID(), 0);

		// Switch the global member_id
		self::setMemberID($_POST['member_id']);

		// Update the pivot table to set active member
		self::switch_active_member($user_id, $_POST['member_id'], 1);

		// return the "fresh" dashboard
		self::refreshDashboard('Succes', ['Succesvol overgeschakeld'], 1000);

	}

	/**
	 * Get all members related to the currently logged in user
	 *
	 * @param bool $userID
	 *
	 * @return array
	 */
	private static function getUserMembers($userID = false): array
	{
		if(empty($userID)) $userID = self::getUserId();
		global $wpdb;
		$current_lang = !empty(ICL_LANGUAGE_CODE) ? ICL_LANGUAGE_CODE : 'nl';
		$table = self::TABLE_NAME;
		$query = "
			SELECT member_id
			FROM {$table}
			JOIN wp_icl_translations t 
				ON {$table}.member_id = t.element_id AND t.element_type = 'post_leden' 
			JOIN {$wpdb->postmeta} pm
				ON {$table}.member_id = pm.post_id AND pm.meta_key = 'is_active' AND pm.meta_value = '1' 
			WHERE {$table}.employee_id = {$userID} AND t.language_code = '{$current_lang}' 
			ORDER BY {$table}.`active` DESC";
//		$query = sprintf("SELECT member_id FROM %s WHERE employee_id = %s ORDER BY `active` DESC", self::TABLE_NAME, $userID);
		$result = $wpdb->get_col($query);
		$primary_member_id = get_field('member', $userID);
		$result = array_unique( $result, $primary_member_id);
		return $result;
	}

	/**
	 * Gets the member_id that has been set as primary
	 *
	 * @param bool $userID
	 *
	 * @return array
	 */
	public static function getPrimaryMemberId($userID = false): array
	{
		if(empty($userID)) $userID = self::getUserId();
		return [get_user_meta($userID, 'member_object', true)];
	}

	/**
	 * Returns the member_id that is being used
	 * If no used record has been found, return the primary member_id
	 *
	 * @param bool $userID
	 *
	 * @return array
	 */
	public static function getUsedMemberId($userID = false): array
	{
		if(empty($userID)) $userID = self::getUserId();
		global $wpdb;
		$query = sprintf("SELECT member_id FROM %s WHERE employee_id = %s AND active = 1 LIMIT 1", self::TABLE_NAME, $userID);
		$result = $wpdb->get_col($query);
		if(empty($result)) {
			$result = self::getPrimaryMemberId($userID);
		}
		return $result;
	}

	/**
	 * Registers the leden post type
	 */
	public static function register_post_type(): void
	{
		$labels = array (
			'name'               => 'Leden',
			'singular_name'      => 'Lid',
			'add_new'            => 'Toevoegen',
			'add_new_item'       => 'Lid toevoegen',
			'edit_item'          => 'Bewerk lid',
			'new_item'           => 'Nieuw lid',
			'view_item'          => 'Bekijk lid',
			'search_items'       => 'Zoek leden',
			'not_found'          => 'Geen leden gevonden',
			'not_found_in_trash' => 'Geen leden gevonden in prullenbak'
		);

		$args = array (
			'label'               => 'Leden',
			'description'         => 'Leden',
			'labels'              => $labels,
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-groups',
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'rewrite' => [
				'slug' => 'leden',
				'with_front' => true,
			],
		);

		register_post_type( 'leden', $args );
	}

	/**
	 * Registers the taxonomy used by the leden post type
	 */
	public static function register_taxonomies(): void
	{
		// Province taxonomy
		$labels = array(
			'name'                       => 'Provincies',
			'singular_name'              => 'Provincie',
			'menu_name'                  => 'Provincies',
			'all_items'                  => 'Alle provincies',
			'parent_item'                => 'Hoofditem',
			'parent_item_colon'          => 'Hoofditem:',
			'new_item_name'              => 'Nieuwe provincie',
			'add_new_item'               => 'Nieuwe provincie',
			'edit_item'                  => 'Wijzig provincie',
			'update_item'                => 'Update provincie',
			'separate_items_with_commas' => 'Scheiden met een comma',
			'search_items'               => 'Zoek provincies',
			'add_or_remove_items'        => 'Voeg toe of verwijder provincie',
			'choose_from_most_used'      => 'Meest gebruikte',
		);

		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => false,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
		);

		register_taxonomy( 'province', ['leden', 'vacancy'], $args );

		// Size taxonomy
		$labels = array(
			'name'                       => 'Omvang',
			'singular_name'              => 'Omvang',
			'menu_name'                  => 'Omvang',
			'all_items'                  => 'Alle omvang',
			'parent_item'                => 'Hoofditem',
			'parent_item_colon'          => 'Hoofditem:',
			'new_item_name'              => 'Nieuwe omvang',
			'add_new_item'               => 'Nieuwe omvang',
			'edit_item'                  => 'Wijzig omvang',
			'update_item'                => 'Update omvang',
			'separate_items_with_commas' => 'Scheiden met een comma',
			'search_items'               => 'Zoek omvang',
			'add_or_remove_items'        => 'Voeg toe of verwijder omvang',
			'choose_from_most_used'      => 'Meest gebruikte',
		);

		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => true,
			'public'                     => false,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
		);

		register_taxonomy( 'size', 'leden', $args );
	}

	/**
	 * Updates or inserts a record in the employee_member_pivot table
	 *
	 * @param $employee_id
	 * @param $member_id
	 * @param bool $used
	 */
	public static function upsert_employee_member_pivot_data($employee_id, $member_id, $active = 0, $is_primary = 0): bool
	{
		global $wpdb;

		if(empty($employee_id) || empty($member_id)) return false;

		$query = sprintf('INSERT INTO %1$s (employee_id, member_id, active, is_primary)
					VALUES(%2$s, %3$s, %4$b, %5$b)
					ON DUPLICATE KEY UPDATE `active` = %4$b',
			self::TABLE_NAME, $employee_id, $member_id, $active, $is_primary);
		if($wpdb->query($query) === false) {
			error_log("[Dashboard\Member->upsert_employee_member_pivot_data]: Could not upsert the pivot data for member_id: {$member_id} and employee_id: {$employee_id}");
			return false;
		}
		return true;
	}

	/**
	 * Switches the active state of a employee and member in the employee_member_pivot table
	 *
	 * @param $employee_id
	 * @param $member_id
	 * @param int $active
	 * @param int $is_primary
	 */
	public static function switch_active_member($employee_id, $member_id, $active = 0, $is_primary = 0): void
	{
		global $wpdb;

		if(empty($employee_id) || empty($member_id)) return;

		$query = sprintf('UPDATE %s SET `active` = %b WHERE `employee_id` = %d AND `member_id` = %d', self::TABLE_NAME, $active, $employee_id, $member_id);
		if($wpdb->query($query) === false) {
			error_log("[Dashboard\Member->switch_active_member]: Could not switch the active member with member_id: {$member_id} and employee_id: {$employee_id}");
		}
	}

	/**
	 * Creates the employee_member_pivot table if it doesn't exist
	 */
	public static function create_employee_member_pivot_table(): void
	{
		global $wpdb;
		$query = 'CREATE TABLE IF NOT EXISTS `'.self::TABLE_NAME.'` (
			`employee_id` int(11) unsigned NOT NULL,
			`member_id` int(11) unsigned NOT NULL,
			`active` bool NOT NULL,
			`is_primary` bool NOT NULL,
			UNIQUE KEY `employee_id` (`employee_id`, `member_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;';
		if($wpdb->query($query) === false) {
			error_log("[Dashboard\Member->create_employee_member_pivot_table]: Could not create the Employee/Member pivot table");
		}
	}

	/**
	 * Deletes a row from the pivot table based on the provided $employee_id and $member_id
	 *
	 * @param int $employee_id
	 * @param int $member_id
	 *
	 * @return bool
	 */
	private static function delete_employee_member_pivot_row($employee_id = 0, $member_id = 0): bool
	{
	    if(empty($employee_id) || empty($member_id)) return false;
	    global $wpdb;
	    if(!$wpdb->delete(self::TABLE_NAME, ['employee_id' => $employee_id, 'member_id' => $member_id])) {
	    	return false;
	    }
	    return true;
	}

	/**
	 * Deletes a row in the employee_member_pivot table based on $employee_id
	 *
	 * @param $employee_id
	 *
	 * @return bool
	 */
	public static function delete_employee_member_pivot_row_by_employee_id($employee_id): bool
	{
		if(empty($employee_id)) return false;
		$member_id = get_field('member_object', 'user_'.$employee_id);
		return self::delete_employee_member_pivot_row($employee_id, $member_id);
	}

	/**
	 * Returns valid vacancies for the provided $member_id
	 *
	 * @param $member_id
	 *
	 * @return array
	 */
	public static function getMemberVacancies($member_id): array
	{
		if(empty($member_id)) return [];
		global $wpdb;
		$query = sprintf("SELECT vacancy_id, created_at, updated_at FROM %s
				WHERE member_id = %s", 'member_vacancy_pivot', $member_id);

		return $wpdb->get_results($query);
	}

	/**
	 * Returns the vacancy_ids which are attached to the given $member_id
	 *
	 * @param $member_id
	 *
	 * @return array
	 */
	public static function getMemberVacancyIds($member_id): array
	{
	    $member_vacancies = self::getMemberVacancies( $member_id );
	    if(empty($member_vacancies)) return [];
	    return array_map(function($vacancy) { if(property_exists( $vacancy, 'vacancy_id')) { return (int)$vacancy->vacancy_id; } }, $member_vacancies);
	}

	/**
	 * Update a member
	 */
	public static function updateMember()
	{
		check_ajax_referer('update_member', 'nonce');

		// Setting empty errors array
		$errors = [];

		// Setting fields variable for internal usage
		$fields = (array)json_decode(str_replace('\\', '', $_POST['fields']));

		// Check if there are fields sent by the request
		if(empty($fields)) {
			error_log('[App\Dashboard\Member->updateMember]: The fields provided are empty');
			$errors[] = __('De opgegeven velden zijn leeg.', 'dda');
		}

		// Check if the member_id parameter is present
		if(!key_exists( 'member_id', $fields) || empty($fields['member_id'])) {
			error_log('[App\Dashboard\Member->updateMember]: The provided member_id doesn\'t exist or is empty');
			$errors[] = __('Er is iets fout gegaan.', 'dda');
		}

		// Check if the user_id parameter is present
		if(!key_exists( 'user_id', $fields) || empty($fields['user_id'])) {
			error_log('[App\Dashboard\Member->updateMember]: The provided user_id doesn\'t exist or is empty');
			$errors[] = __('Er is iets fout gegaan.', 'dda');
		}

		// Set the user_id variable and get the user object
		$user_id = $fields['user_id'];
		$user = get_user_by('ID', $user_id);

		// If no user has been found by the provided user_id
		if(empty($user)) {
			error_log("[App\Dashboard\Member->updateMember]: User object has not been found for user: {$user_id}.");
			$errors[] = __('Er is iets fout gegaan.', 'dda');
		}

		$lang = '';
		// check if the lang has been set
		if(!key_exists('lang', $fields) || empty($fields['lang'])) {
			error_log("[App\Dashboard\Member->updateMember]: The language is not provided for member: {$member_id}");
			$error[] = __('Er is iets fout gegaan.', 'dda');
		}

		// Setting the variable for internal usage
		$member_id = $fields['member_id'];
		$post_status = $fields['post_status'];
		$content = str_replace('\\', '', $_POST['content']);
		$province_ids = $fields['province_ids'];
		$number_employees = $fields['number_employees'];
		$city = $fields['city'];
		$main_contact_email = $fields['main_contact_email'];
		$slogan = $fields['slogan'];
		$intro_text = $fields['intro'];

		$lang = '';
		if(!key_exists('lang', $fields) || empty($fields['lang'])) {
			error_log("[App\Dashboard\Member->updateMember]: The language is not provided for member: {$member_id}");
			$error[] = __('Er is iets fout gegaan.', 'dda');
		} else {
			$lang = $fields['lang'];
		}

		// Add error messages if condition is met
		if(empty($content)) $errors[] = __('De omschrijving is leeg', 'dda');
		if(empty($province_ids)) $errors[] = __('Er zijn geen provincies geselecteerd', 'dda');
		if(empty($number_employees)) $errors[] = __('Het aantal werknemers veld is leeg', 'dda');
		if(empty($city)) $errors[] = __('Het plaats veld is leeg', 'dda');
		if(empty($lang)) $errors[] = __('Er is iets fout gegaan', 'dda');
		//		if(empty($main_contact_email)) $errors[] = __('Het hoofdcontactemail veld is leeg', 'dda');
		if(!empty($main_contact_email) && !filter_var($main_contact_email, FILTER_VALIDATE_EMAIL)) $errors[] = __('Het opgegeven emailadres is geen geldig emailadres.', 'dda');
		//		if(empty($slogan)) $errors[] = __('Het slogan veld is leeg', 'dda');

		// Send the error messags to the user
		if(!empty($errors)) {

			// loop over the amount of errors and add 750ms to the $modal_timeout_time for each error found
			foreach($errors as $error) {
				$modal_timeout_time += 750;
			}

			$errors[] = __('Probeer het opnieuw.', 'dda');
			$response['modal'] = ['title' => 'Fout!', 'values' => $errors, 'type' => 'error', 'time' => $modal_timeout_time];
			wp_send_json_error($response);
			exit;
		}

		// reset the errors array
		$errors = [];

		// update memberpost
		$update_member = wp_update_post([
			'ID'            => $member_id,
			'post_content'  => $content,
			'post_status'   => $post_status,
		]);

		// Check for errors
		if(is_wp_error($update_member)) {
			error_log("[App\Dashboard\Member->updateMember]: Unable to update post: {$member_id}.");
			error_log(var_export($update_member->get_error_messages()));

			$errors[] = __('Er is iets misgegaan, probeer het opnieuw.', 'dda');
			$modal_timeout_time = 1000;
			$response['modal'] = ['title' => 'Fout!', 'values' => $errors, 'type' => 'error', 'time' => $modal_timeout_time];
			wp_send_json_error($response);
			exit;
		}

		// reset the errors array
		$errors = [];

		if(!function_exists('media_handle_upload')) {
			require_once(ABSPATH . 'wp-admin/includes/media.php');
			require_once(ABSPATH . 'wp-admin/includes/file.php');
			require_once(ABSPATH . 'wp-admin/includes/image.php');
		}

		if(isset($_FILES['logo'])) {
			$logo_id = media_handle_upload('logo', 0);
			if(is_wp_error($logo_id)) {
				error_log('[App\Dashboard\Member->updateMember]: Unable to upload logo');
				error_log(var_export($logo_id->get_error_messages(), true));
				$errors[] = __('Er is iets fout gegaan tijdens het uploaden van je logo.', 'dda');
			} else {
				update_field('logo', $logo_id, $member_id);
			}
		}

		if(isset($_FILES['banner'])) {
			$banner_id = media_handle_upload('banner', 0);
			if(is_wp_error($banner_id)) {
				error_log('[App\Dashboard\Member->updateMember]: Unable to upload banner');
				error_log(var_export($banner_id->get_error_messages(), true));
				$errors[] = __('Er is iets fout gegaan tijdens het uploaden van je banner.', 'dda');
			} else {
				update_field('banner', $banner_id, $member_id);
			}
		}

		$province_ids = $fields['province_ids'];
		$number_employees = $fields['number_employees'];
		$city = $fields['city'];
		$main_contact_email = $fields['main_contact_email'];
		$slogan = $fields['slogan'];


		if(!empty($province_ids)) {
			wp_set_post_terms(
				$member_id,
				$province_ids,
				'province'
			);
		}
		if(!empty($number_employees)) {
			wp_set_post_terms(
				$member_id,
				[(int)$number_employees],
				'size'
			);
		}
		if(!empty($city)) update_field('city', $city, $member_id);
		if(!empty($main_contact_email)) update_field('main_contact_email', $main_contact_email, $member_id);
		if(!empty($slogan)) update_field('slogan', $slogan, $member_id);
		if(!empty($intro_text)) update_field('intro_text', $intro_text, $member_id);

		$messages[] = __('Bedrijfspagina bijgewerkt!', 'dda');
		$messages[] = __('Je wordt binnen enkele seconden doorgestuurd.', 'dda');
		$response['modal'] = ['title' => 'Succes', 'values' => $messages, 'type' => 'success', 'time' => 2500];
		$response['link'] = DashboardController::getDashboardLink();

		// Set member to active
		//		update_field('is_active', true, $member_id);

		// Update post status of member
//		$publish = wp_update_post([
//			'ID'            => $member_id,
//			'post_status'   => 'publish',
//		]);
//
//		// Final check for errors
//		if(is_wp_error($publish)) {
//			error_log("[App\Dashboard\Member->updateMember]: Unable to update post: {$member_id}.");
//			error_log(var_export($publish->get_error_messages()));
//
//			$errors[] = __('Er is iets misgegaan, probeer het opnieuw.', 'dda');
//			$modal_timeout_time = 1000;
//			$response['modal'] = ['title' => 'Fout!', 'values' => $errors, 'type' => 'error', 'time' => $modal_timeout_time];
//			wp_send_json_error($response);
//			exit;
//		}

		// Send success message
		wp_send_json_success($response);
		exit;
	}

	/**
	 * Adds a new member
	 *
	 * @param $data
	 *
	 * @return bool|int|\WP_Error
	 */
	public static function addMember($data)
	{
		if(empty($data['company_name']) || empty($data['user_id'])) return false;

		$member_id = wp_insert_post([
			'post_title'    => $data['company_name'],
			'post_author'   => $data['user_id'],
			'post_type'     => 'leden',
			'post_status'   => 'draft',
		]);

		if(is_wp_error($member_id)) {
			error_log("[App\Dashboard\Member->addMember]: Unable to insert post: {$member_id}.");
			error_log(var_export($member_id->get_error_messages()));
			return false;
		}

		// Set member to inactive
		update_field('is_active', false, $member_id);

		// Add the newly created user to the member users repeater field
		update_field('users', [['user' => $data['user_id'], 'primary_user' => true]], $member_id);

		return $member_id;
	}

	/**
	 * Ajax call to remove an employee from the member
	 */
	public static function ajaxRemoveEmployeeFromMember()
	{
		check_ajax_referer('remove_employee_from_member', 'nonce');

		$employee_id = $_POST['employee_id'];
		User::onDeleteUser($employee_id);
		self::refreshDashboard('Succes', ['Gebruiker verwijderd'], 1000);
	}

	private static function get_member_pivot_user_ids($member_id = 0)
	{
	    if(empty($member_id)) return;
	    global $wpdb;
	    $table = self::TABLE_NAME;
	    $query = "SELECT employee_id FROM {$table} WHERE member_id = {$member_id} GROUP BY employee_id";
	    $result = $wpdb->get_col($query);
	    return $result;
	}

	/**
	 * @param int $member_id
	 */
	private static function remove_user_from_pivot_if_not_in_member_users($member_id = 0): void
	{
	    if(empty($member_id)) return;

	    // Get the acf field users of the $member_id
	    $member_users = get_field('users', $member_id);
	    if(empty($member_users) || !is_array($member_users)) return;
	    // Convert the array to an array of id's only
	    $member_user_ids = array_map(function($el) {
	    	                if(key_exists( 'user', $el)) {
                                return (string)$el['user'];
	    	                }
                        }, $member_users);

	    // Get the pivot table users of the $member_id
	    $member_pivot_user_ids = self::get_member_pivot_user_ids($member_id);
	    if(empty($member_pivot_user_ids) || !is_array( $member_pivot_user_ids)) return;

	    // set an empty array for ids we need to remove
	    $remove = [];

	    // Loop over user_ids found in the pivot table
	    foreach($member_pivot_user_ids as $member_pivot_user_id) {
	    	// If the user_id is not in the acf field users, add the id to the remove array
	    	if(!in_array($member_pivot_user_id, $member_user_ids)) {
			    $remove[] = $member_pivot_user_id;
		    }
	    }

	    // Remove user_id if $remove array is not empty
	    if(!empty($remove)) {
	    	foreach($remove as $remove_id) {
				self::delete_employee_member_pivot_row($remove_id, $member_id);
		    }
	    }
	}

	/**
	 * This function will synchronise the users of a member with it's translation
	 * It will also put the right values in the pivot table
	 *
	 * @param int $original_post_id
	 * @param int $translated_post_id
	 *
	 * @return bool
	 */
	private static function sync_users($original_post_id = 0, $translated_post_id = 0): bool
	{
	    if(empty($original_post_id)) return false;

	    // Get the original post's users
		$employees = get_field('users', $original_post_id);
		if( empty($employees) || !is_array($employees) ) return false;

		if(empty($translated_post_id)) $translated_post_id = self::getEnglishDuplicate($original_post_id);
		if(!empty($translated_post_id)) {
			$translated_post_employees = get_field('users', $translated_post_id);
			if($employees !== $translated_post_employees) update_field('users', $employees, $translated_post_id);
		}

		self::remove_user_from_pivot_if_not_in_member_users($original_post_id);

		// Attach the users to the original post
		foreach($employees as $key => $employee_row) {
			if(key_exists('user', $employee_row) && !empty($employee_row['user'])) {


				if(!self::upsert_employee_member_pivot_data( $employee_row['user'], $original_post_id, '1', $employee_row['primary_user'] )) {
					error_log("[Dashboard\Member->onSaveMember]: unable to add user {$employee_row['user']} to member: {$original_post_id}");
				}
				User::addMemberObject($original_post_id, $employee_row['user']);
			}
		}

		// Attach the users to the translated post
		if(!empty($translated_post_id)) {
			self::remove_user_from_pivot_if_not_in_member_users($translated_post_id);
			foreach($employees as $key => $employee_row) {
				if(key_exists('user', $employee_row) && !empty($employee_row['user'])) {


					if(!self::upsert_employee_member_pivot_data( $employee_row['user'], $translated_post_id, '1', $employee_row['primary_user'] )) {
						error_log("[Dashboard\Member->onSaveMember]: unable to add user {$employee_row['user']} to member: {$translated_post_id}");
					}
					User::addMemberObject($original_post_id, $employee_row['user']);
				}
			}
		}

		return true;
	}

	/**
	 * Sync the active state of the original member with it's translation
	 *
	 * @param int $original_post_id
	 * @param int $translated_post_id
	 */
	private static function sync_active_state($original_post_id = 0, $translated_post_id = 0): void
	{
	    if(empty($original_post_id)) return;
	    $original_active_state = get_field('is_active', $original_post_id);
	    if(empty($translated_post_id)) $translated_post_id = self::getEnglishDuplicate($original_post_id);
	    if(empty($translated_post_id)) return;
	    $translated_active_state = get_field('is_active', $translated_post_id);
	    if($translated_active_state !== $original_active_state) update_field('is_active', $original_active_state, $translated_post_id);
	}

	/**
	 * Triggers when the post "leden" is being saved
	 * Will upsert the pivot table
	 */
	public static function onSaveMember($post_id): void
	{
		if( $parent_id = wp_is_post_revision( $post_id ) ) $post_id = $parent_id;
		if( get_post_type($post_id) !== 'leden' || get_post_status( $post_id ) === 'trash' ) return;


		$is_active = get_post_meta( $post_id, 'is_active', true );
		if( empty( $is_active ) ) {

			// What to do when the member is inactive
			$member_vacancy_ids = self::getMemberVacancyIds( $post_id );
			if( empty( $member_vacancy_ids ) ) return;
			foreach( $member_vacancy_ids as $member_vacancy_id ) {
				$update = wp_update_post([
					'ID'            => $member_vacancy_id,
					'post_status'   => 'draft',
				], true);
				if( is_wp_error( $update ) ) {
					// TODO: DEBUG REMOVE THIS
					error_log(var_export("Unable to update post status to draft for post: {$member_vacancy_id}", true));
				}
			}

		} else {
			// Member is active

			// If the current post is the english duplicate
			if(self::isEnglishDuplicate($post_id)) {
				// Get the original post_id
				$member_id_original = self::getOriginalID($post_id);
				// Sync the original locations with the duplicate
				Location::sync_locations($member_id_original);

				// Sync the active state of the member
				self::sync_active_state($member_id_original, $post_id);
				// Sync the users of the member
				self::sync_users($member_id_original, $post_id);
			} else {
				// Sync the original locations with the duplicate
				Location::sync_locations($post_id);

				// Sync the active state of the member
				self::sync_active_state($post);
				// Sync the users of the member
				self::sync_users($post_id, self::getEnglishDuplicate($post_id));
			}

			// If the current post_id doesn't have a english duplicate and itself isn't the english duplicate, create a english duplicate
			if(!self::hasEnglishDuplicate($post_id) && !self::isEnglishDuplicate($post_id)) self::createEnglishDuplicate($post_id);
		}
	}

	/**
	 * Triggers when a post is being trashed
	 * if this post is post_type leden
	 * the pivot row will be deleted
	 *
	 * @param $post_id
	 */
	public static function onTrashMember($post_id): void
	{
		if( get_post_type($post_id) !== 'leden') return;

		// Set the member on inactive
		update_field('is_active','0', $post_id);

		$employees = get_field('users', $post_id);
		if(empty($employees)) return;

		foreach($employees as $key => $employee_row) {
			User::onDeleteUser( $employee_row['user']);
		}
	}

	/**
	 * Get the original post_id if the provided post_id is a translation
	 *
	 * @param int $member_id
	 *
	 * @return int|mixed|void
	 */
	public static function getOriginalID($member_id = 0)
	{
	    if(empty($member_id)) return 0;
		return apply_filters('wpml_object_id', $member_id, 'leden', true, 'nl');
	}

	/**
	 * Checks whether the given post id has an english translation
	 *
	 * @param int $member_id
	 *
	 * @return bool
	 */
	public static function hasEnglishDuplicate($member_id = 0)
	{
		if(empty($member_id)) return false;
		$has_translation = apply_filters('wpml_element_has_translations', null, $member_id, 'post_leden');
		if(!$has_translation) return false;
	    return true;
	}

	/**
	 * Check whether the given member_id is a translation or not
	 *
	 * @param int $member_id
	 *
	 * @return bool
	 */
	public static function isEnglishDuplicate($member_id = 0): bool
	{
		if(empty($member_id)) return false;
		$member_language_details = apply_filters('wpml_post_language_details', null, $member_id);
		if(empty($member_language_details)) return false;
		if(key_exists( 'language_code', $member_language_details) && $member_language_details['language_code'] === 'en') return true;
		return false;
	}

	/**
	 * Get the english duplicate of the given $member_id
	 *
	 * @param int $member_id
	 *
	 * @return int|mixed|void
	 */
	public static function getEnglishDuplicate($member_id = 0)
	{
		if(empty($member_id)) return 0;
		return apply_filters('wpml_object_id', $member_id, 'leden', true, 'en');
	}

	/**
	 * Create an english duplicate of the member post
	 * returns true on success, false on failure
	 *
	 * @param int $member_id
	 *
	 * @return bool
	 */
	public static function createEnglishDuplicate($member_id = 0)
	{
	    if(empty($member_id)) return false;
	    global $sitepress;
		$title = get_the_title($member_id);
		$logo = get_post_meta($member_id, 'logo', true);
		$content = get_post_field('post_content', $member_id);
		$dda_company_id = get_post_meta($member_id, 'dda_company_id', true);
		$main_contact_email = get_post_meta($member_id, 'main_contact_email', true);
		$dda_imported = get_post_meta($member_id, 'dda_imported', true);
		$intro_text = get_post_meta($member_id, 'intro_text', true);
		$banner = get_post_meta($member_id, 'banner', true);
		$slogan = get_post_meta($member_id, 'slogan', true);
		$city = get_post_meta($member_id, 'city', true);
		$locations = get_field('locations', $member_id);
		$users = get_field('users', $member_id);
		$number_employees = get_post_meta($member_id, 'number_employees', true);
		$member_provinces_taxonomy = get_the_terms($member_id, 'province');
		$member_size_taxonomy = get_the_terms($member_id, 'size');

		$en_args = [
			'post_title'    => $title,
			'post_content'  => $content,
			'post_type'     => 'leden',
			'post_status'   => get_post_status($member_id),
		];
		$trid = $sitepress->get_element_trid($member_id);
		$member_id_en = wp_insert_post($en_args);
		if(is_wp_error( $member_id_en)) return false;
		$sitepress->set_element_language_details($member_id_en, 'post_leden', $trid, 'en');

		if(!empty($logo)) update_post_meta($member_id_en, 'logo', $logo);
		if(!empty($dda_company_id)) update_post_meta($member_id_en, 'dda_company_id', $dda_company_id);
		if(!empty($main_contact_email)) update_post_meta($member_id_en, 'main_contact_email', $main_contact_email);
		if(!empty($dda_imported)) update_post_meta($member_id_en, 'dda_imported', $dda_imported);
		if(!empty($intro_text)) update_post_meta($member_id_en, 'intro_text', $intro_text);
		if(!empty($banner)) update_post_meta($member_id_en, 'banner', $banner);
		if(!empty($slogan)) update_post_meta( $member_id_en, 'slogan', $slogan);
		if(!empty($city)) update_post_meta($member_id_en, 'city', $city);
		if(!empty($locations)) update_field('locations', $locations, $member_id_en);
		if(!empty($users)) update_field('users', $users, $member_id_en);
		if(!empty($number_employees)) update_post_meta($member_id_en, 'number_employees', $number_employees);

		if(!empty($member_provinces_taxonomy) && !is_wp_error( $member_provinces_taxonomy)) {
			wp_set_post_terms(
				$member_id_en,
				array_map(
					function($tax) {
						if(!empty($tax->name)) {
							return $tax->term_id;
						}
					},
					$member_provinces_taxonomy
				),
				'province'
			);
		}

		if(!empty($member_size_taxonomy) && !is_wp_error( $member_size_taxonomy)) {
			wp_set_post_terms(
				$member_id_en,
				array_map(
					function($tax) {
						if(!empty($tax->name)) {
							return $tax->term_id;
						}
					},
					$member_size_taxonomy
				),
				'size'
			);
		}

		return true;
	}


	/**
	 * Returns the term objects for the given $taxonomy_name and $member_id
	 *
	 * @param int $member_id
	 * @param string $taxonomy_name
	 *
	 * @return bool|false|\WP_Error|\WP_Term[]
	 */
	public static function getTaxonomy($member_id = 0, $taxonomy_name = '')
	{
	    if(empty($member_id) || empty($taxonomy_name)) return false;
	    $taxonomy_terms = get_the_terms($member_id, $taxonomy_name);
	    if(!is_wp_error( $taxonomy_terms ) && !empty($taxonomy_terms)) return $taxonomy_terms;
	}

	/**
	 * Returns the term_ids for the given $taxonomy_name and $member_id
	 *
	 * @param int $member_id
	 * @param string $taxonomy_name
	 *
	 * @return array|bool
	 */
	public static function getTaxonomyIds($member_id = 0, $taxonomy_name = ''): array
	{
	    if(empty($member_id) || empty($taxonomy_name)) return [];
	    $taxonomy_terms = self::getTaxonomy($member_id, $taxonomy_name);
	    if(!empty($taxonomy_terms)) {
	    	return array_map(function($term) { if(!empty($term->term_id)) return $term->term_id; }, $taxonomy_terms);
	    }
	    return [];
	}

	/**
	 * Returns the employees repeater for the given $member_id
	 *
	 * @param int $member_id
	 *
	 * @return array|mixed
	 */
	public static function getEmployees($member_id = 0)
	{
	    if(empty($member_id)) return [];
	    $employees = get_field('users', $member_id);
	    if(!empty($employees)) {
	    	$employees = array_map(function($employee) { if(!empty($employee['user'])) { return $employee['user']; } }, $employees);
	    }
	    return !empty($employees) ? $employees : [];
	}

	/**
	 * Sort the sizes taxonomy the correct way
	 *
	 * @return array|int|\WP_Error|\WP_Term[]
	 */
	public static function getSizes()
	{
		$terms_sizes = get_terms( ['taxonomy' => 'size', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);
		if(!empty($terms_sizes) && !is_wp_error($terms_sizes)) {
			usort($terms_sizes, function($a, $b) {
				$ai = (int)filter_var(explode('-', $a->name)[0], FILTER_SANITIZE_NUMBER_INT);
				$bi = (int)filter_var(explode('-', $b->name)[0], FILTER_SANITIZE_NUMBER_INT);
				if($ai == $bi) return 0;
				return ($ai < $bi) ? -1 : 1;
			});
		} else {
			$terms_sizes = [];
		}
		return $terms_sizes;
	}

}
