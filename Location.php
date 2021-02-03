<?php

namespace App\Dashboard;

/**
 * Class Location
 * @package App\Dashboard
 */
class Location extends DashboardController
{

	/**
	 * Setup the actions and filters used by this class
	 */
	public static function setup(): void
	{
		add_action( 'wp_ajax_nopriv_add_location', [ 'App\Dashboard\Location', 'addLocation' ] );
		add_action( 'wp_ajax_add_location', [ 'App\Dashboard\Location', 'addLocation' ] );

		add_action( 'wp_ajax_nopriv_update_location', [ 'App\Dashboard\Location', 'updateLocation' ] );
		add_action( 'wp_ajax_update_location', [ 'App\Dashboard\Location', 'updateLocation' ] );
	}

	/**
	 * Returns all the locations for the $member_id
	 *
	 * @return array
	 */
	public static function all(): array
	{
		if(empty(self::$member_id)) return [];
		$locations = self::getLocations(self::$member_id);
		if(empty($locations)) return [];
		return $locations;
	}

	/**
	 * Adds a new location
	 */
	public static function addLocation()
	{
		check_ajax_referer('add_location', 'nonce');

		// Setting empty errors array
		$errors = [];

		// Setting fields variable for internal usage
		$fields = $_POST['fields'];

		// Check if there are fields sent by the request
		if(empty($fields)) {
			error_log('[App\Dashboard\Location->addLocation]: The fields provided are empty');
			$errors[] = __('De opgegeven velden zijn leeg.', 'dda');
		}

		// Check if the member_id parameter is present
		if(!key_exists( 'member_id', $fields) || empty($fields['member_id'])) {
			error_log('[App\Dashboard\Location->addLocation]: The provided member_id doesn\'t exist or is empty');
			$errors = __('Er is iets fout gegaan bij het doorsturen van de gegevens.', 'dda');
		}

		// Setting the variables for internal usage
		$member_id = $fields['member_id'];
		$address = $fields['address'];
		$postcode = $fields['postcode'];
		$city = $fields['city'];
		$phonenumber = $fields['phonenumber'];
		$emailaddress = $fields['emailaddress'];
		$kvk_number = $fields['kvk_number'];
//		$primary = $fields['primary'];
		$location_label = $fields['location_label'];
		$modal_timeout_time = 0;

		// Add error messages if condition is met
		if(empty($address)) $errors[] = __('Het adres veld is leeg', 'dda');
		if(empty($postcode)) $errors[] = __('Het postcode veld is leeg', 'dda');
		if(empty($city)) $errors[] = __('Het plaats veld is leeg', 'dda');
		if(empty($phonenumber)) $errors[] = __('Het telefoonnummer veld is leeg', 'dda');
		if(empty($emailaddress)) $errors[] = __('Het emailadres veld is leeg.', 'dda');
		if(!empty($emailaddress) && !filter_var($emailaddress, FILTER_VALIDATE_EMAIL)) $errors[] = __('Het opgegeven emailadres is geen geldig emailadres.', 'dda');
//		if(empty($kvk_number)) $errors[] = __('Het KvK nummer veld is leeg', 'dda');
		if(empty($location_label)) $errors[] = __('Je hebt de naam voor de locatie niet opgegeven', 'dda');

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

		$locations = self::getLocations($member_id);
//		$hasPrimaryLocation = false;
//		if(is_array($locations)) {
//			foreach($locations as $location) {
//				if($location['primary']) $hasPrimaryLocation = true;
//			}
//		}
//		$new_location = [];
//		if(!$hasPrimaryLocation) {
//			$new_location['primary'] = $primary;
//		}
		$new_location['primary'] = false;
		$new_location['location_label'] = $location_label;
		$new_location['address'] = $address;
		$new_location['postcode'] = $postcode;
		$new_location['city'] = $city;
		$new_location['phonenumber'] = $phonenumber;
		$new_location['emailaddress'] = $emailaddress;
		$new_location['kvk_number'] = $kvk_number;

		$locations[] = $new_location;

		if(!empty($locations)) {
			self::setLocations($member_id, $locations);
			self::sync_locations($member_id);
		}

		$messages[] = __('Locatie toegevoegd!', 'dda');
		$messages[] = __('Je wordt binnen enkele seconden doorgestuurd.', 'dda');
		$response['modal'] = ['title' => 'Succes', 'values' => $messages, 'type' => 'success', 'time' => 2500];
		$response['link'] = DashboardController::getDashboardLink();

		// Send success message containing the dashboard link where the user will be redirected to
		wp_send_json_success($response);
		exit;
	}

	/**
	 * Edit a location
	 */
	public static function updateLocation()
	{
		check_ajax_referer('update_location', 'nonce');

		// Setting empty errors array
		$errors = [];

		// Setting fields variable for internal usage
		$fields = $_POST['fields'];

		// Check if there are fields sent by the request
		if(empty($fields)) {
			error_log('[App\Dashboard\Location->updateLocation]: The fields provided are empty');
			$errors[] = __('De opgegeven velden zijn leeg.', 'dda');
		}

		// Check if the member_id parameter is present
		if(!key_exists( 'member_id', $fields) || empty($fields['member_id'])) {
			error_log('[App\Dashboard\Location->updateLocation]: The provided member_id doesn\'t exist or is empty');
			$errors = __('Er is iets fout gegaan bij het doorsturen van de gegevens.', 'dda');
		}

		// Setting the variables for internal usage
		$member_id = $fields['member_id'];
		$location_index = $fields['location_index'];
		$address = $fields['address'];
		$postcode = $fields['postcode'];
		$city = $fields['city'];
		$phonenumber = $fields['phonenumber'];
		$emailaddress = $fields['emailaddress'];
		$kvk_number = $fields['kvk_number'];
//		$primary = $fields['primary'];
		$location_label = $fields['location_label'];
		$modal_timeout_time = 0;

		// Add error messages if condition is met
		if(empty($address)) $errors[] = __('Het adres veld is leeg', 'dda');
		if(empty($postcode)) $errors[] = __('Het postcode veld is leeg', 'dda');
		if(empty($city)) $errors[] = __('Het plaats veld is leeg', 'dda');
		if(empty($phonenumber)) $errors[] = __('Het telefoonnummer veld is leeg', 'dda');
		if(empty($emailaddress)) $errors[] = __('Het emailadres veld is leeg.', 'dda');
		if(!empty($emailaddress) && !filter_var($emailaddress, FILTER_VALIDATE_EMAIL)) $errors[] = __('Het opgegeven emailadres is geen geldig emailadres.', 'dda');
//		if(empty($kvk_number)) $errors[] = __('Het KvK nummer veld is leeg', 'dda');
		if(empty($location_label)) $errors[] = __('Je hebt de naam voor de locatie niet opgegeven', 'dda');

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

		$locations = self::getLocations($member_id);
//		$hasPrimaryLocation = false;
//		if(is_array($locations)) {
//			foreach($locations as $location) {
//				if($location['primary']) $hasPrimaryLocation = true;
//			}
//		}
//		$new_location = [];
//		if(!$hasPrimaryLocation) {
//			$new_location['primary'] = $primary;
//		}
		$new_location['primary'] = false;
		$new_location['location_label'] = $location_label;
		$new_location['address'] = $address;
		$new_location['postcode'] = $postcode;
		$new_location['city'] = $city;
		$new_location['phonenumber'] = $phonenumber;
		$new_location['emailaddress'] = $emailaddress;
		$new_location['kvk_number'] = $kvk_number;

		$locations[$location_index] = $new_location;

		if(!empty($locations)) {
			self::setLocations($member_id, $locations);
			self::sync_locations($member_id);
		}

		$messages[] = __('Locatie bijgewerkt!', 'dda');
		$messages[] = __('Je wordt binnen enkele seconden doorgestuurd.', 'dda');
		$response['modal'] = ['title' => 'Succes', 'values' => $messages, 'type' => 'success', 'time' => 2500];
		$response['link'] = DashboardController::getDashboardLink();

		// Send success message containing the dashboard link where the user will be redirected to
		wp_send_json_success($response);
		exit;
	}

	/**
	 * @param int $member_id
	 *
	 * @return array
	 */
	public static function getLocations($member_id = 0): array
	{
		if(empty($member_id)) return [];
		return !empty(get_field('locations', $member_id)) ? get_field('locations', $member_id) : [];
	}

	/**
	 * Syncs the locations of the given $member_id with it's english translation
	 *
	 * @param int $member_id
	 *
	 * @return bool
	 */
	public static function sync_locations($member_id = 0): bool
	{
		if(empty($member_id)) return false;
		if(Member::isEnglishDuplicate($member_id)) return false;
		$member_id_en = Member::getEnglishDuplicate($member_id);
		if(empty($member_id_en)) return false;
		$member_locations = self::getLocations($member_id);
		$member_locations_en = self::getLocations($member_id_en);

		if(	!empty($member_locations) && ( $member_locations_en !== $member_locations ) ) {
			self::setLocations($member_id_en, $member_locations);
			return true;
		}
		return false;
	}

	/**
 * Set the locations for the given $member_id
 *
 * @param int $member_id
 * @param array $locations
 */
	public static function setLocations($member_id = 0, $locations = []): void
	{
		if(empty($member_id) || empty($locations)) return;
		update_field('locations', $locations, $member_id);
	}

}
