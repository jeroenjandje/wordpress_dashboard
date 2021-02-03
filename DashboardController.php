<?php

namespace App\Dashboard;

/**
 * Class DashboardController
 * @package App\Dashboard
 */
class DashboardController
{

	public static $member_id;


	/**
	 * Setup the actions and filters used by this class
	 */
	public static function setup()
	{
		add_filter('wpseo_breadcrumb_links', [ 'App\Dashboard\DashboardController', 'manipulateBreadcrumbs' ] );
	}

	/**
	 * Set the global $member_id
	 *
	 * @param null $memberID
	 */
	public static function setMemberID($memberID = null): void
	{
		self::$member_id = $memberID;
	}

	/**
	 * Get the global $member_id
	 *
	 * @return string
	 */
	public static function getMemberID(): string
	{
		self::memberIDExists();
		return self::$member_id;
	}

	/**
	 * Get the translated post ID of the global $member_id
	 *
	 * @return string
	 */
	public static function getTranslationMemberID(): string
	{
		self::memberIDExists();
	    $original_member_id = self::$member_id;
	    if(!empty($original_member_id)) {
	    	return apply_filters( 'wpml_object_id', $original_member_id, 'leden', true, 'en');
	    }
	}

	/**
	 * Checks whether the static variable $member_id exists or not
	 * if it doesn't exist yet, it will set it
	 */
	public static function memberIDExists(): void
	{
		if(empty(self::$member_id)) {
			$getMemberID = Member::getUsedMemberId();
			$usedMemberID = false;
			if(key_exists( 0, $getMemberID)) {
				$usedMemberID = $getMemberID[0];
			}
			self::setMemberID($usedMemberID);
		}
	}

	/**
	 * Get the currently logged in user's ID
	 *
	 * @return int
	 */
	public static function getUserId(): int
	{
		return self::getUser()->ID;
	}

	/**
	 * Get the currently logged in user object
	 *
	 * @return \WP_User
	 */
	public static function getUser()
	{
		return wp_get_current_user();
	}

	/**
	 * Check to see if the current user is logged in or not
	 *
	 * @return bool
	 */
	public static function isLoggedIn(): bool
	{
		return is_user_logged_in();
	}

	/**
	 * Refreshes the dashboard content with the most up-to-date data
	 * Is called via a AJAX call
	 *
	 * @param string $title
	 * @param array $messages
	 * @param int $time
	 */
	public static function refreshDashboard($title = '', $messages = [], $time = 5000)
	{
		$credentials = Credential::getCredentials();
		$members = Member::all();
		$locations = Location::all();
		$vacancies = Vacancy::all();
		$users = User::all();

		$include = [
			'credentials'   => $credentials,
			'members'       => $members,
			'locations'     => $locations,
			'vacancies'     => $vacancies,
			'users'         => $users,
		];

		ob_start();
		$template['action'] = 'refresh';
		$template['part'] = 'section';
		$template['element'] = '#dashboard-content';
		$template['modal'] = ['title' => $title, 'values' => $messages, 'type' => 'success', 'time' => $time];
		$template['template'] = \App\template('partials/dashboard/dashboard-content', $include);

		// Send success response
		wp_send_json_success( $template );
		ob_get_clean();
	}

	/**
	 * Responsible for displaying the correct menu items
	 */
	public static function loginLogoutDashboardMenu()
	{
		if(DashboardController::isLoggedIn()) {
			echo '<a href="'. self::getDashboardLink() .'" class="btn btn--primary-onblack w-40 mr-4">'. __('Mijn DDA', 'dda') .'</a>
			<a href="' . wp_logout_url() . '" class="btn btn--tertiary-onblack logoutButton">'. __('Uitloggen', 'dda') .'</a>';
		} else {
			echo '<a href="'. self::getLoginLink() .'" class="btn btn--secondary w-40 mr-4">'. __('Inloggen', 'dda') .'</a>
            <a href="'. self::getRegisterLink() .'" class="btn btn--primary-onblack w-40">'. __('Aanmelden', 'dda') .'</a>';
		}
	}

	/**
	 * Wrapper for global $dashboard_link
	 * used internally
	 *
	 * @return string
	 */
	public static function getDashboardLink(): string
	{
		$dashboard_page_id = get_field('general_dashboard_page', 'option');
		return ($dashboard_page_id) ? get_permalink($dashboard_page_id) : RedirectManager::dashboardSlug() ?? '#';
	}

	/**
	 * Wrapper for global $login_link
	 * used internally
	 *
	 * @return string
	 */
	public static function getLoginLink(): string
	{
		$login_page_id = get_field('general_login_page', 'option');
		return ($login_page_id) ? get_permalink($login_page_id) : RedirectManager::loginSlug() ?? '#';
	}

	/**
	 * Wrapper for global $register_link
	 * used internally
	 *
	 * @return string
	 */
	public static function getRegisterLink(): string
	{
		$register_page_id = get_field('general_register_page', 'option');
		return ($register_page_id) ? get_permalink($register_page_id) : RedirectManager::registerSlug() ?? '#';
	}

	/**
	 * Manipulates the YOAST breadcrumbs on the create & edit page templates
	 *
	 * @param $links
	 *
	 * @return mixed
	 */
	public static function manipulateBreadcrumbs($links)
	{
	    if(is_page_template('views/template-create-my-dda.blade.php') || is_page_template('views/template-edit-my-dda.blade.php')) {
	    	$title = get_the_title();
	    	if(key_exists( 'type', $_GET)) {
				$post_type = $_GET['type'];
				if($post_type === 'vacancy') $post_type = 'vacature';
				if($post_type === 'location') $post_type = 'locatie';
				if($post_type === 'user') $post_type = 'gebruiker';
				if($post_type === 'member') $post_type = 'lid';
				$title = ucfirst($post_type). ' ' .strtolower($title);
		    }
	    	$position = sizeof($links) - 1; // We need to replace the last item in the $links array
	    	$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	    	$links[$position] = [
	    		'url'   => $url,
			    'text'  => $title,
		    ];
	    }
	    return $links;
	}

}
