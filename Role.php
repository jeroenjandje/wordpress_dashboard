<?php

namespace App\Dashboard;

/**
 * Class Role
 * @package App\Dashboard
 */
class Role extends DashboardController
{

	/**
	 * Setup actions used by this class
	 */
	public static function setup(): void
	{
		add_action( 'init', [ 'App\Dashboard\Role', 'addRoles' ] );
	}

	/**
	 * Add roles
	 */
	public static function addRoles(): void
	{
	    add_role('owner', 'Eigenaar', [
		    'read' => true,
		    'edit_files' => true,
		    'edit_others_pages' => true,
		    'edit_others_posts' => true,
		    'edit_pages' => true,
		    'edit_posts' => true,
		    'edit_private_pages' => true,
		    'edit_private_posts' => true,
		    'edit_published_pages' => true,
		    'edit_published_posts' => true,
		    'publish_pages' => true,
		    'publish_posts' => true,
		    'read_private_pages' => true,
		    'read_private_posts' => true,
		    'upload_files' => true,
	    ]);
	    add_role('employee', 'Medewerker', [
	    	'read'                  => true,
	    ]);
	}

}
