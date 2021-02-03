<?php

namespace App\Dashboard;

/**
 * Class ApplicationController
 * @package App\Dashboard
 */
class ApplicationController
{

	/**
	 * Setup the actions and filters used by this class
	 */
	public static function setup(): void
	{
		add_action( 'init', [ 'App\Dashboard\ApplicationController', 'register_post_type' ] );
		add_action( 'wp_ajax_nopriv_send_application', [ 'App\Dashboard\ApplicationController', 'handleApplication' ] );
		add_action( 'wp_ajax_send_application', [ 'App\Dashboard\ApplicationController', 'handleApplication' ] );
		add_action( 'save_post', [ 'App\Dashboard\ApplicationController', 'onSaveApplication' ] );
	}

	/**
	 * Handles the incoming application request
	 */
	public static function handleApplication()
	{
		// handles the application sent by an AJAX request
		check_ajax_referer('send_application', 'nonce');

		// Setting empty errors and messages array
		$errors = [];
		$messages = [];

		// Setting fields variable for internal usage
		$fields = $_POST['fields'];

		// Check if there are fields sent by the request
		if(empty($fields)) {
			$errors[] = __('De opgegeven velden zijn leeg.', 'dda');
		}

		// Setting the variables for internal usage
		$first_name = $fields['first_name'];
		$last_name = $fields['last_name'];
		$email = $fields['email'];
		$company_name = $fields['company_name'];
		$message = $fields['message'];

		// Add error messages if condition is met
		if(empty($first_name)) $errors[] = __('Het voornaam veld is leeg.', 'dda');
		if(empty($last_name)) $errors[] = __('Het achternaam veld is leeg.', 'dda');
		if(empty($email)) $errors[] = __('Het emailadres veld is leeg.', 'dda');
		if(!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = __('Het opgegeven emailadres is geen geldig emailadres.', 'dda');
		if(empty($company_name)) $errors[] = __('Het bedrijfsnaam veld is leeg.', 'dda');
		if(empty($message)) $errors[] = __('Er is geen bericht ingevoerd.', 'dda');

		// Send the error messags to the user
		if(!empty($errors)) {
			$errors[] = __('Probeer het opnieuw.', 'dda');
			$response['modal'] = ['title' => 'Er is iets misgegaan.', 'values' => $errors, 'type' => 'error', 'time' => 5000];
			wp_send_json_error($response);
			exit;
		}

		// Create the actual application
		$result = self::createApplication($fields);
		if(!$result) {
			$errors[] = __('Er is iets fout gegaan tijdens het versturen van je aanvraag.', 'dda');
			$errors[] = __('Probeer het opnieuw.', 'dda');
			$response['modal'] = ['title' => 'Er is iets misgegaan.', 'values' => $errors, 'type' => 'error', 'time' => 2000];
			wp_send_json_error($response);
			exit;
		}

		// Send email notification to admin
		$admin_email = get_bloginfo('admin_email');

		$name = $first_name . ' ' . $last_name;
		$subject = sprintf(__('[%s] – Nieuwe aanvraag', 'dda'), get_bloginfo('name'));

		$mail_message = '<p>'.__('Er is zojuist een nieuwe aanvraag binnengekomen.', 'dda'). '</p>';
		$mail_message .= '<p>'.__('Hieronder vind je de informatie betreft deze aanvraag.', 'dda') .'</p>';
		$mail_message .= '<p>'.sprintf(__('Naam: %s', 'dda'), $name). '<br/>';
		$mail_message .= sprintf(__('Emailadres: %s', 'dda'), $email). '<br/>';
		$mail_message .= sprintf(__('Bedrijfsnaam: %s', 'dda'), $company_name). '<br/>';
		$mail_message .= sprintf(__('Bericht: %s', 'dda'), $message). '</p>';
		$mail_message .= '<p>'.sprintf(__('Klik <a href="%s">hier</a> om naar de aanvraag te gaan.', 'dda'), get_edit_post_link($result)). '</p>';
		$mail_message .= '<p>'.__('Er gebeurt niets met de aanvraag tot de status wordt aangepast.', 'dda').'</p>';
		wp_mail($admin_email, $subject, $mail_message, 'Content-type: text/html');

		// Send the success response to the user
		$messages[] = __('Uw aanvraag is succesvol ontvangen, we nemen z.s.m. contact met u op!', 'dda');
		$response['modal'] = ['title' => 'Succes!', 'values' => $messages, 'type' => 'success', 'time' => 1500];

		wp_send_json_success($response);
	}

	/**
	 * Creates the incoming application request
	 *
	 * @param array $fields
	 *
	 * @return bool
	 */
	private static function createApplication($fields = [])
	{
		if(empty($fields)) return false;
	    $postArgs = [
		    'post_title' => 'Aanvraag voor '.sanitize_title($fields['company_name']),
		    'post_content' => '',
		    'post_status' => 'publish',
		    'post_type' => 'application',
	    ];
		$applicationPostId = wp_insert_post($postArgs);

		if(is_wp_error($applicationPostId)) {
			error_log('[App\Dashboard\ApplicationController->createApplication]: Failed to create new application.');
			error_log(var_export($fields, true));
			return false;
		}

		update_field('status', 'new', $applicationPostId);
		update_field('first_name', $fields['first_name'], $applicationPostId);
		update_field('last_name', $fields['last_name'], $applicationPostId);
		update_field('email', $fields['email'], $applicationPostId);
		update_field('company_name', $fields['company_name'], $applicationPostId);
		update_field('message', $fields['message'], $applicationPostId);

		return $applicationPostId;
	}

	/**
	 * Registers the application post type
	 */
	public static function register_post_type(): void
	{
		$labels = array (
			'name'               => 'Aanmeldingen',
			'singular_name'      => 'Aanmelding',
			'add_new'            => 'Toevoegen',
			'add_new_item'       => 'Aanmelding toevoegen',
			'edit_item'          => 'Bewerk aanmelding',
			'new_item'           => 'Nieuwe aanmelding',
			'view_item'          => 'Bekijk aanmelding',
			'search_items'       => 'Zoek aanmeldingen',
			'not_found'          => 'Geen aanmeldingen gevonden',
			'not_found_in_trash' => 'Geen aanmeldingen gevonden in prullenbak'
		);

		$args = array (
			'label'               => 'Aanmeldingen',
			'description'         => 'Aanmeldingen',
			'labels'              => $labels,
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-email-alt2',
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'revisions' ),
		);

		register_post_type( 'application', $args );
	}

	/**
	 * Triggers when saving a application post_type
	 *
	 * @param $post_id
	 */
	public static function onSaveApplication($post_id): void
	{
		if( $parent_id = wp_is_post_revision( $post_id ) ) $post_id = $parent_id;
		if( get_post_type($post_id) !== 'application' || get_post_status($post_id) === 'trash' || get_post_status($post_id) === 'draft' || get_post_status($post_id) === 'inherit' ) return;

		$status = get_field('status', $post_id);
		if($status === 'new') return;

		if($status === 'approved') {
			self::approveApplication($post_id);
		} elseif($status === 'rejected') {
			self::rejectApplication($post_id);
		}
	}

	/**
	 * Approves a application post_type
	 *
	 * @param $post_id
	 */
	public static function approveApplication($post_id): void
	{
	    if(empty($post_id)) return;

	    // Get variables for internal usage
	    $first_name = get_field('first_name', $post_id);
	    $last_name = get_field('last_name', $post_id);
	    $emailaddress = get_field('email', $post_id);
	    $company_name = get_field('company_name', $post_id);

	    // Create data array
	    $data = [
	    	'first_name'    => $first_name,
		    'last_name'     => $last_name,
		    'emailaddress'  => $emailaddress,
		    'company_name'  => $company_name,
		    'user_id'       => null,
	    ];

	    // create user
		$userArray = User::addOwner($data);
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

		// Add the newly added user_id to the data array
		if($user_id) {
			$data['user_id'] = $user_id;
		}

		// create company
		$member_id = Member::addMember($data);

		if(!empty($member_id)) {
			// Send the welcome email to the newly added user
			User::sendWelcomeEmail( $member_id, $emailaddress, $user_name, $password);

			// Add the member_object to the newly adWded user
			User::addMemberObject($member_id, $user_id);

			// Add the newly added user and member to the pivot table
			Member::upsert_employee_member_pivot_data($user_id, $member_id, 1, 1);
		}
	}

	/**
	 * Rejects a application post_type
	 *
	 * @param $post_id
	 */
	public static function rejectApplication($post_id): void
	{
		if(empty($post_id)) return;

		$update = wp_update_post([
			'ID'            => $post_id,
			'post_status'   => 'draft',
		]);

		if(is_wp_error($update)) {
			error_log("[App\Dashboard\ApplicationController->rejectApplication]: Unable to reject application: {$post_id}");
			error_log(var_export($update->get_error_messages(), true));
			return;
		}

		return;
	}

}
