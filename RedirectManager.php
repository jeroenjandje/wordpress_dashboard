<?php

namespace App\Dashboard;

/**
 * Class RedirectManager
 * @package App\Dashboard
 */
class RedirectManager
{
	/**
	 * Setup actions used by this class
	 */
	public static function setup(): void
	{
	    add_action('template_redirect', [ 'App\Dashboard\RedirectManager', 'isLoggedInOrRedirect' ] );
		add_action('login_headerurl', [ 'App\Dashboard\RedirectManager', 'lostPasswordRedirect' ] );
	}

	/**
	 * Returns the dashboard slug
	 * or a default set slug
	 *
	 * @return string
	 */
	public static function dashboardSlug(): string
	{
	    return get_post_field('post_name', get_field('general_dashboard_page', 'option'));
	}

	/**
	 * Returns the login slug
	 * or a default set slug
	 *
	 * @return string
	 */
	public static function loginSlug(): string
	{
		return get_post_field('post_name', get_field('general_login_page', 'option'));
	}

	/**
	 * Returns the register slug
	 * or a default set slug
	 *
	 * @return string
	 */
	public static function registerSlug(): string
	{
	    return get_post_field('post_name', get_field('general_register_page', 'option'));
	}

	/**
	 * Returns the create page ID that has been set in the options table
	 *
	 * @return mixed
	 */
	public static function getCreatePageID()
	{
	    return get_field('general_create_page', 'option');
	}

	/**
	 * Returns the create slug
	 * or a default set slug
	 *
	 * @return string
	 */
	public static function createSlug(): string
	{
	    return get_post_field('post_name', get_field('general_create_page', 'option'));
	}

	/**
	 * Returns the permalink of the create page
	 *
	 * @return string
	 */
	public static function getCreatePermalink($array = []): string
	{
	    return self::getPermalinkByID(self::getCreatePageID(), self::buildQuery($array));
	}

	/**
	 * Returns the edit page ID that has been set in the options table
	 *
	 * @return mixed
	 */
	public static function getEditPageID()
	{
	    return get_field('general_edit_page', 'option');
	}

	/**
	 * Returns the edit slug
	 * or a default set slug
	 *
	 * @return string
	 */
	public static function editSlug(): string
	{
	    return get_post_field('post_name', self::getEditPageID());
	}

	/**
	 * Returns the permalink of the edit page
	 *
	 * @return string
	 */
	public static function getEditPermalink($array = []): string
	{
	    return self::getPermalinkByID(self::getEditPageID(), self::buildQuery($array));
	}

	/**
	 * Generates a URL-encoded query string
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	private static function buildQuery($array = []): string
	{
	    if(empty($array)) return '';
	    return '?'.http_build_query($array);
	}

	/**
	 * Safe redirect function
	 *
	 * @param $url
	 */
	private static function redirect($url): void
	{
	    wp_safe_redirect( $url );
	    exit;
	}

	/**
	 * Returns the permalink for a slug
	 *
	 * @param string $slug
	 * @param string $param
	 *
	 * @return string
	 */
	public static function getPermalinkByPath($slug = '', $param = ''): string
	{
		if(empty($slug)) return '';
	    return get_permalink(get_page_by_path($slug)) . $param;
	}

	/**
	 * Returns the permalink for a page ID
	 *
	 * @param $id
	 * @param string $param
	 *
	 * @return string
	 */
	public static function getPermalinkByID($id, $param = ''): string
	{
	    if(empty($id)) return '';
	    return get_permalink($id).$param;
	}

	/**
	 * Redirect to login using the safe redirect function
	 */
	public static function redirectToLogin($param = ''): void
	{
		self::redirect(self::getPermalinkByPath(self::loginSlug(), $param));
    }

    /**
     * Redirect to dashboard using the safe redirect function
     */
    public static function redirectToDashboard(): void
    {
        self::redirect(self::getPermalinkByPath(self::dashboardSlug()));
	}

	/**
	 * Check on which page the currently logged in user is.
	 * If the user is on one of these pages and not logged in,
	 * this function will redirect the logged in user to the login page.
	 *
	 * If the user is on the login page while being logged in
	 * it will redirect the user to the dashboard
	 */
	public static function isLoggedInOrRedirect(): void
	{
		$dashboard = self::dashboardSlug();
		$edit = self::editSlug();
		$create = self::createSlug();
		$login = self::loginSlug();
		$isLoggedIn = DashboardController::isLoggedIn();

		if(!empty($dashboard) || !empty($edit) || !empty($create)) {
			if( ( is_page($dashboard) && !$isLoggedIn ) || ( is_page($create) && !$isLoggedIn) || ( is_page($edit) && !$isLoggedIn) ) self::redirectToLogin();
		} elseif(!empty($login)) {
			if( is_page($login) && $isLoggedIn ) self::redirectToDashboard();
		}
	}

	/**
	 * After resetting the password, redirect the user to the login page with a set parameter to display a success message
	 */
	public static function lostPasswordRedirect(): void
	{
		// Check if action is present
		$action = ( isset($_GET['action'] ) ? $_GET['action'] : '' );

		if( $action == 'resetpass' ) {
			self::redirectToLogin('?action=resetpass');
		}
	}
}
