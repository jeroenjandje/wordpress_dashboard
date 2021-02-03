<?php

namespace App\Dashboard;

/**
 * Class User
 * @package App\Dashboard
 */
class User extends DashboardController
{

	/**
	 * Setup actions used by this class
	 */
	public static function setup(): void
	{
		add_action( 'wp_ajax_nopriv_logout_user', [ 'App\Dashboard\User', 'logoutUser' ] );
		add_action( 'wp_ajax_logout_user', [ 'App\Dashboard\User', 'logoutUser' ] );

		add_action( 'wp_ajax_nopriv_add_user', [ 'App\Dashboard\User', 'addEmployee' ] );
		add_action( 'wp_ajax_add_user', [ 'App\Dashboard\User', 'addEmployee' ] );

//		add_action( 'profile_update', [ 'App\Dashboard\User', 'onProfileUpdate' ], 10, 2 );

		add_action( 'delete_user', [ 'App\Dashboard\User', 'onDeleteUser' ] );
	}

	/**
	 * Returns all the users for the provided member
	 *
	 * @return array
	 */
	public static function all(): array
	{
		$users = get_field('users', self::$member_id);
		if(empty($users)) return [];
	    return $users;
	}

	/**
	 * Adds a employee
	 */
	public static function addEmployee()
	{
		check_ajax_referer('add_user', 'nonce');

		// Setting empty errors array
		$errors = [];

		// Setting fields variable for internal usage
		$fields = $_POST['fields'];

		// Check if there are fields sent by the request
		if(empty($fields)) {
			error_log('[App\Dashboard\User->addEmployee]: The fields provided are empty');
			$errors[] = __('De opgegeven velden zijn leeg.', 'dda');
		}

		// Check if the member_id parameter is present
		if(!key_exists( 'member_id', $fields) || empty($fields['member_id'])) {
			error_log('[App\Dashboard\User->addEmployee]: The provided member_id doesn\'t exist or is empty');
			$errors = __('Er is iets fout gegaan bij het doorsturen van de gegevens.', 'dda');
		}

		// Setting the variables for internal usage
		$member_id = $fields['member_id'];
		$first_name = $fields['first_name'];
		$last_name = $fields['last_name'];
		$emailaddress = $fields['emailaddress'];

		// Add error messages if condition is met
		if(empty($first_name)) $errors[] = __('Het voornaam veld is leeg', 'dda');
		if(empty($last_name)) $errors[] = __('Het achternaam veld is leeg', 'dda');
		if(empty($emailaddress)) $errors[] = __('Het emailadres veld is leeg.', 'dda');
		if(!empty($emailaddress) && !filter_var($emailaddress, FILTER_VALIDATE_EMAIL)) $errors[] = __('Het opgegeven emailadres is geen geldig emailadres.', 'dda');
		if(email_exists($emailaddress)) $errors[] = __('Het opgegeven emailadres is al in gebruik', 'dda');

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

		$userArray = self::addUser($fields);
		$user_id = false;
		$password = false;
		$user_name = false;
		if(key_exists( 'user_id', $userArray)) {
			$user_id = $userArray['user_id'];
		}
		if(key_exists( 'password', $userArray)) {
			$password = $userArray['password'];
		}
		if(key_exists('user_name', $userArray)) {
			$user_name = $userArray['user_name'];
		}
		if(key_exists( 'emailaddress', $userArray)) {
			$emailaddress = $userArray['emailaddress'];
		}

		$user = get_user_by('id', $user_id);

		$errors = [];

		if(is_wp_error($user_id) || $user === false) {
			error_log("[App\Dashboard\User->addEmployee]: Could not create new user for the user with username: {$user_name} and emailaddress: {$emailaddress}.");
			error_log("[App\Dashboard\User->addEmployee]: {$user_id->get_error_message()}.");

			$modal_timeout_time = 2000;
			$errors[] = __('Er ging iets fout tijdens het toevoegen van een nieuw account.', 'dda');
			$errors[] = __('Probeer het opnieuw of neem contact op.', 'dda');
			$response['modal'] = ['title' => 'Fout!', 'values' => $errors, 'type' => 'error', 'time' => $modal_timeout_time];
			wp_send_json_error($response);
			exit;
		}

		// set the employee role to the newly added user
		$user->set_role('employee');

		// Send the welcome email to the newly added user
		self::sendRegistrationEmail( $member_id, $emailaddress, $user_name, $password);

		// Add the member_object to the newly added user
		self::addMemberObject($member_id, $user_id);

		Member::upsert_employee_member_pivot_data($user_id, $member_id, 1, 1);

		$member_users = get_field('users', $member_id);
		$new_member_user = [
			'user'  => $user_id,
			'primary_user'  => false,
		];
		$member_users[] = $new_member_user;
		update_field('users', $member_users, $member_id);


		$messages[] = __('Gebruiker toegevoegd!', 'dda');
		$messages[] = __('Je wordt binnen enkele seconden doorgestuurd.', 'dda');
		$response['modal'] = ['title' => 'Succes', 'values' => $messages, 'type' => 'success', 'time' => 2500];
		$response['link'] = DashboardController::getDashboardLink();

		// Send success message containing the dashboard link where the user will be redirected to
		wp_send_json_success($response);
		exit;
	}

	/**
	 * Adds a new owner
	 *
	 * @param $data
	 *
	 * @return array|bool
	 */
	public static function addOwner($data)
	{
		$userArray = self::addUser($data);
		$user_id = false;
		$password = false;
		$user_name = false;
		if(key_exists( 'user_id', $userArray)) {
			$user_id = $userArray['user_id'];
		}
		if(key_exists( 'password', $userArray)) {
			$password = $userArray['password'];
		}
		if(key_exists('user_name', $userArray)) {
			$user_name = $userArray['user_name'];
		}
		if(key_exists( 'emailaddress', $userArray)) {
			$emailaddress = $userArray['emailaddress'];
		}

		$user = get_user_by('id', $user_id);

		if(is_wp_error($user_id) || $user === false) {
			error_log("[App\Dashboard\User->addOwner]: Could not create new user for the user with username: {$user_name} and emailaddress: {$emailaddress}.");
			error_log("[App\Dashboard\User->addOwner]: {$user_id->get_error_message()}.");

			return false;
		}

		// set the owner role to the newly added user
		$user->set_role('owner');

		return ['user_id' => $user_id, 'user_name' => $user_name, 'emailaddress' => $emailaddress, 'password' => $password];
	}

	/**
	 * Adds a new user
	 */
	public static function addUser($data)
	{
		$first_name = $data['first_name'];
		$last_name = $data['last_name'];
		$emailaddress = $data['emailaddress'];

		$user_name = sanitize_user(sprintf("%s %s", $first_name, $last_name), true);
		$base_name = $user_name;
		$i = 0;
		while(username_exists( $user_name )) {
			$user_name = $base_name . (++$i);
		}
		$password = wp_generate_password();
		$user_args = [
			'user_login'    => $user_name,
			'first_name'    => sanitize_text_field( $first_name ),
			'last_name'     => sanitize_text_field( $last_name ),
			'nickname'      => sanitize_text_field( $user_name ),
			'user_email'    => sanitize_text_field( $emailaddress ),
			'user_pass'     => $password,
		];
		$user_id = wp_insert_user($user_args);
		return ['user_id' => $user_id, 'user_name' => $user_name, 'emailaddress' => $emailaddress, 'password' => $password];
	}

	/**
	 * Adds the member object to the user
	 *
	 * @param $member_id
	 * @param $user_id
	 */
	public static function addMemberObject($member_id, $user_id): void
	{
		if(empty($member_id) || empty($user_id)) return;
		update_field('member_object', $member_id, 'user_'.$user_id);
	}

	/**
	 * Sends the registration email to the newly added user
	 *
	 * @param $member_id
	 * @param $emailaddress
	 * @param $user_name
	 * @param $password
	 */
	public static function sendRegistrationEmail($member_id, $emailaddress, $user_name, $password): void
	{
		if(empty($member_id) || empty($emailaddress) || empty($user_name) || empty($password)) return;
		$login_link = DashboardController::getLoginLink();
		$message = sprintf(__('Welkom bij %s', 'dda'), get_bloginfo('name')). "\r\n\r\n";
		$message .= sprintf(__('Je bent aan %s toegevoegd.', 'dda'), get_the_title($member_id)). "\r\n\r\n";
		$message .= sprintf(__('Gebruikersnaam: %s', 'dda'), $user_name)."\r\n";
		$message .= sprintf(__('Wachtwoord: %s', 'dda'), $password)."\r\n\r\n";
		$message .= sprintf(__('Bezoek het volgende adres om in te loggen: %s', 'dda'), $login_link). "\r\n\r\n";
		wp_mail($emailaddress, 'Welkom bij '.get_bloginfo('name').'!', $message);
	}

	/**
	 * Sends a welcome email to newly added owners & members
	 *
	 * @param $member_id
	 * @param $emailaddress
	 * @param $user_name
	 * @param $password
	 * @param string $email_message
	 */
	public static function sendWelcomeEmail($member_id, $emailaddress, $user_name, $password, $email_message = ''): void
	{
		if(empty($member_id) || empty($emailaddress) || empty($user_name) || empty($password)) return;
		$login_link = DashboardController::getLoginLink();
		$message = sprintf(__('Welkom bij %s', 'dda'), get_bloginfo('name')). "\r\n\r\n";
		$message .= sprintf(__('Je bent aan %s toegevoegd.', 'dda'), get_the_title($member_id)). "\r\n\r\n";
		$message .= sprintf(__('Gebruikersnaam: %s of %s', 'dda'), $user_name, $emailaddress)."\r\n";
		$message .= sprintf(__('Wachtwoord: %s', 'dda'), $password)."\r\n\r\n";
		$message .= sprintf(__('Bezoek het volgende adres om in te loggen: %s', 'dda'), $login_link). "\r\n\r\n";

		if($email_message) {
			$message .= $email_message;
		} else {
			$message .= __('We hebben je nog niet toegevoegd aan onze lijst met leden, zodra je de bedrijfsgegevens invult via mijn DDA, wordt je bedrijf toegevoegd aan de lijst met leden.', 'dda');
		}

		wp_mail($emailaddress, 'Welkom bij '.get_bloginfo('name').'!', $message);
	}

	/**
	 * Logs out the currently logged in user and returns the home url to redirect to
	 */
	public static function logoutUser()
	{
	    wp_logout();
		wp_send_json_success();
	}

	/**
	 * Returns the currently logged in user's role(s)
	 *
	 * @return array
	 */
	private static function getUserRole(): array
	{
	    return self::getUser()->roles;
	}

	/**
	 * Returns a user's role by user_id
	 *
	 * @param $user_id
	 *
	 * @return array
	 */
	public static function getUserRoleById($user_id): array
	{
		if(empty($user_id)) return [];
	    return get_user_by('ID', $user_id)->roles;
	}

	/**
	 * Is the user an owner or admin
	 *
	 * @return bool
	 */
	public static function isOwner(): bool
	{
		if(in_array( 'owner', self::getUserRole()) || in_array('administrator', self::getUserRole())) return true;
		return false;
	}

	/**
	 * Is the user an employee or admin
	 *
	 * @return bool
	 */
	public static function isEmployee(): bool
	{
	    if(in_array('employee', self::getUserRole()) || in_array('administrator', self::getUserRole())) return true;
	    return false;
	}

	/**
	 * Removes the provided user_id from it's member's employee list
	 *
	 * @param $user_id
	 */
	public static function removeUserFromMember($user_id)
	{
		if(empty($user_id)) return false;
		$member_id = get_field('member_object', 'user_'.$user_id);
		if(empty($member_id)) return false;
		$member_employees_list = get_field('users', $member_id);

		foreach($member_employees_list as $key => $employee_id) {
			if((int)$employee_id['user'] === (int)$user_id) unset($member_employees_list[$key]);
		}
		update_field('users', $member_employees_list, $member_id);
		return true;
	}

	/**
	 * Removes the member_object ACF Field from the user
	 *
	 * @param $user_id
	 *
	 * @return bool
	 */
	public static function removeMemberFromUser($user_id)
	{
	    if(empty($user_id)) return false;
	    update_field('member_object', '', 'user_'.$user_id);
	    return true;
	}

	/**
	 * Triggers when a user is being deleted
	 *
	 * @param $user_id
	 */
	public static function onDeleteUser($user_id)
	{
		if( !Member::delete_employee_member_pivot_row_by_employee_id($user_id) ) {
			error_log("[Dashboard\User->onDeleteUser]: Unable to delete the row for employee_id: {$user_id}");
			return false;
		}
		if(!self::removeUserFromMember( $user_id)) {
			error_log("[Dashboard\User->onDeleteUser]: Unable to remove the user from the member {$user_id}");
			return false;
		}
		if(!self::removeMemberFromUser( $user_id)) {
			error_log("[Dashboard\User->onDeleteUser]: Unable to remove the member from the user {$user_id}");
			return false;
		}
		return true;
	}

	/**
	 * Return the vacancy post_ids for the given $user_id
	 *
	 * @param int $user_id
	 *
	 * @return array
	 */
//	private static function get_user_vacancy_ids($user_id = 0): array
//	{
//	    if(empty($user_id)) return [];
//	    return get_posts(['author' => $user_id, 'post_type' => 'vacancy', 'fields' => 'ids']);
//	}

	/**
	 * Actions to be executed when a user is being saved
	 *
	 * @param $user_id
	 * @param $old_user_data
	 */
//	public static function onProfileUpdate($user_id, $old_user_data)
//	{
//	    $is_active = get_user_meta( $user_id, 'is_active', true );
//	    if( empty( $is_active ) ) {
//
//	    	$user_role = current( self::getUserRoleById( $user_id ) );
//	    	$user_member_id = get_user_meta( $user_id, 'member_object', true );
//
//	    	$user_member = get_post( $user_member_id );
//	    	if( !empty( $user_member ) ) {
//
//	    		$user_member_employees = Member::getEmployees( $user_member_id );
//
//	    		if( !empty( $user_member_employees ) ) {
//	    			// Do something with the employees of the member
//	    			foreach( $user_member_employees as $employee ) {
//	    				if( (int)$employee === (int)$user_id ) continue;
//	    				if( !empty( get_user_meta( $employee, 'is_active', true ) ) ) continue;
//	    				$employee_vacancy_ids = self::get_user_vacancy_ids( $employee );
//				    }
//			    }
//
//
//	    	    $user_member_author_id = $user_member->post_author;
//	    	    if( !empty( $user_member_author_id ) && ( (int)$user_id === (int)$user_member_author_id ) ) {
//	    	    	// Do something with the post author of the member
//		        }
//		    }
//	    	$user_vacancy_ids = self::get_user_vacancy_ids( $user_id );
//	    	if( !empty( $user_vacancy_ids ) ) {
//	    		// Do something with the vacancies of the user
//			    // Attach it to a different user of the member if the member isn't inactive aswell
//			    foreach($user_vacancy_ids as $user_vacancy_id) {
//			    	wp_update_post([
//			    		'ID'            => $user_vacancy_id,
//					    'post_status'   => 'draft',
//				    ], true);
//			    }
//		    }
//	    }
//	}

}
