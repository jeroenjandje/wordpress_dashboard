<?php

namespace App\Dashboard;

class Loader
{

	/**
	 * Setup all the actions and filters used by the Dashboard
	 */
	public static function setup()
	{
		DashboardController::setup();
		RedirectManager::setup();
		Member::setup();
		Credential::setup();
		Vacancy::setup();
		User::setup();
		Location::setup();
		Role::setup();
		ApplicationController::setup();
	}

}
