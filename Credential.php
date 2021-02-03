<?php

namespace App\Dashboard;

/**
 * Class Credential
 * @package App\Dashboard
 */
class Credential extends DashboardController
{

	/**
	 * Setup actions used by this class
	 */
	public static function setup()
	{
		add_action('wp_ajax_nopriv_update_credentials', ['App\Dashboard\Credential', 'updateCredentials']);
		add_action('wp_ajax_update_credentials', ['App\Dashboard\Credential', 'updateCredentials']);
	}

	/**
	 * Gets the necessary credentials for the currently logged in user
	 *
	 * @return array
	 */
	public static function getCredentials(): array
	{
		$credentials = [];
		if(self::isLoggedIn()) {
			$credentials['user_id'] = self::getUserId();
			$credentials['first_name'] = get_user_meta(self::getUserId(), 'first_name', true) ?? '';
			$credentials['last_name'] = get_user_meta(self::getUserId(),'last_name', true) ?? '';
			$credentials['function'] = get_user_meta(self::getUserId(), 'function', true) ?? '';
			$credentials['emailaddress'] = self::getUser()->user_email ?? '';
			$credentials['bureau'] = get_user_meta(self::getUserId(), 'bureau', true) ?? ''; // Need to find out what this is used for
		}

		return $credentials;
	}

	/**
	 * Updates the necessary credentials for the logged in user
	 * and will refresh the dashboard template on success.
	 */
	public static function updateCredentials()
	{
		check_ajax_referer('update_credentials', 'nonce');

		// Setting empty errors array
		$errors = [];

		// Setting fields variable for internal usage
		$fields = $_POST['fields'];

		// Check if there are fields sent by the request
		if(empty($fields)) {
			error_log('[App\Dashboard\Credentials->updateCredentials]: The fields provided are empty');
			$errors[] = __('De opgegeven velden zijn leeg.', 'dda');
		}

		// Check if the user_id parameter is present
		if(!key_exists( 'user_id', $fields) || empty($fields['user_id'])) {
			error_log('[App\Dashboard\Credentials->updateCredentials]: The provided user_id doesn\'t exist or is empty');
			$errors[] = __('Er is iets fout gegaan.', 'dda');
		}

		// Set the user_id variable and get the user object
	    $user_id = $fields['user_id'];
	    $user = get_user_by('ID', $user_id);

	    // If no user has been found by the provided user_id
	    if(empty($user)) {
	    	error_log("[App\Dashboard\Credential->updateCredentials]: User object has not been found for user: {$user_id}.");
	    	$errors[] = __('Gebruiker niet gevonden a.h.v. het opgegeven emailadres.', 'dda');
	    }

	    // Setting the variables for internal usage
	    $first_name = $fields['first_name'];
	    $last_name = $fields['last_name'];
	    $function = $fields['function'];
	    $emailaddress = $fields['emailaddress'];
	    $bureau = $fields['bureau'];

	    // Add error messages if condition is met
	    if(empty($first_name)) $errors[] = __('Het voornaam veld is leeg.', 'dda');
	    if(empty($last_name)) $errors[] = __('Het achternaam veld is leeg.', 'dda');
	    if(empty($emailaddress)) $errors[] = __('Het emailadres veld is leeg.', 'dda');
	    if(!empty($emailaddress) && !filter_var($emailaddress, FILTER_VALIDATE_EMAIL)) $errors[] = __('Het opgegeven emailadres is geen geldig emailadres.', 'dda');

	    // Send the error messags to the user
	    if(!empty($errors)) {
	    	$response['action'] = 'refresh';
	    	$response['part'] = 'error';
	    	$errors[] = __('Probeer het opnieuw.', 'dda');
	    	$response['modal'] = ['title' => 'Fout!', 'values' => $errors, 'type' => 'error', 'time' => 5000];
	    	wp_send_json_error($response);
	    	exit;
	    }

	    // Update the user and store the result in a variable
	    $update = wp_update_user([
	    	'ID'            => $user_id,
		    'first_name'    => esc_attr($first_name),
		    'last_name'     => esc_attr($last_name),
		    'user_email'    => esc_attr($emailaddress),
	    ]);

	    // Something went wrong while updating the user
	    if(is_wp_error($update)) {
	    	error_log("[App\Dashboard\Credential->updateCredentials]: We were unable to update the user: {$user_id}");
	    	$errors['error'] = __('Er is iets misgegaan, probeer het opnieuw.', 'dda');
	    	wp_send_json_error($errors);
	    	exit;
	    }

	    // Update separate user meta fields
		// These fields are not required or used for the User object so if it's present, update the fields
        if(!empty($function)) update_user_meta($user_id, 'function', $function);
        if(!empty($bureau)) update_user_meta($user_id, 'bureau', $bureau);

		// return the "fresh" dashboard
		self::refreshDashboard('Succes!', ['De wijzigingen zijn succesvol doorgevoerd'], 2500);
	}

}
