<?php

namespace App\Dashboard;

/**
 * Class Vacancy
 * @package App\Dashboard
 */
class Vacancy extends DashboardController
{

	protected const TABLE_NAME = 'member_vacancy_pivot';

	/**
	 * Setup the actions and filters used by this class
	 */
	public static function setup(): void
	{
		self::create_member_vacancy_pivot_table();
		add_action( 'init', [ 'App\Dashboard\Vacancy', 'register_post_type' ] );
		add_action( 'init', [ 'App\Dashboard\Vacancy', 'register_taxonomies' ] );

		add_action( 'wp_ajax_nopriv_add_vacancy', [ 'App\Dashboard\Vacancy', 'addVacancy' ] );
		add_action( 'wp_ajax_add_vacancy', [ 'App\Dashboard\Vacancy', 'addVacancy' ] );

		add_action( 'wp_ajax_nopriv_update_vacancy', [ 'App\Dashboard\Vacancy', 'updateVacancy' ] );
		add_action( 'wp_ajax_update_vacancy', [ 'App\Dashboard\Vacancy', 'updateVacancy' ] );

		add_action( 'wp_ajax_nopriv_delete_vacancy', [ 'App\Dashboard\Vacancy', 'deleteVacancy' ] );
		add_action( 'wp_ajax_delete_vacancy', [ 'App\Dashboard\Vacancy', 'deleteVacancy' ] );

		add_action( 'save_post', [ 'App\Dashboard\Vacancy', 'onSaveVacancy' ] );
		add_action( 'delete_post', [ 'App\Dashboard\Vacancy', 'onTrashVacancy' ] );
		add_action( 'wp_trash_post', [ 'App\Dashboard\Vacancy', 'onTrashVacancy' ] );
		add_action( 'untrash_post', [ 'App\Dashboard\Vacancy', 'onRestoreVacancy' ] );
	}

	public static function getAllActiveVacancies(): array
	{
	    return self::get_all_active_vacancy_ids() ?? [];
	}

	private static function get_all_active_vacancy_ids()
	{
	    global $wpdb;
	    $table = self::TABLE_NAME;
	    $query = "SELECT mvp.vacancy_id AS vacancy_id
					FROM {$table} AS mvp 
					INNER JOIN {$wpdb->postmeta} AS postmeta 
						ON mvp.member_id = postmeta.post_id
					WHERE postmeta.meta_key = 'is_active' 
					AND postmeta.meta_value = '1'
					ORDER BY rand()";
	    return $wpdb->get_col($query);
	}

	/**
	 * Wrapper function that returns all the vacancies for the $member_id
	 *
	 * @return array
	 */
	public static function allVacancies(): array
	{
	    return self::getVacancies() ?? [];
	}

	/**
	 * Wrapper function that returns all the internships for the $member_id
	 *
	 * @return array
	 */
	public static function allInternships(): array
	{
	    return self::getInternships() ?? [];
	}

	/**
	 * Returns all the vacancies for the $member_id
	 *
	 * @return array
	 */
	private static function getVacancies(): array
	{
		if(empty(self::$member_id)) self::$member_id = self::getMemberID();
		if(empty(self::$member_id)) return [];
		global $wpdb;
		$current_lang = !empty(ICL_LANGUAGE_CODE) ? ICL_LANGUAGE_CODE : 'nl';
		$table = self::TABLE_NAME;
		$member_id = self::$member_id;

		$term = get_term_by('slug', 'baan', 'employment');
		if(empty($term) || is_wp_error($term)) return [];
		$term_id = $term->term_id;

		$query = "SELECT pivot.vacancy_id AS vacancy_id, pivot.created_at AS created_at, pivot.updated_at AS updated_at FROM {$table} AS pivot
					INNER JOIN {$wpdb->posts} AS posts ON posts.ID = pivot.vacancy_id
					INNER JOIN wp_icl_translations AS t ON pivot.vacancy_id = t.element_id
					INNER JOIN wp_term_relationships AS relations ON relations.object_id = pivot.vacancy_id
					INNER JOIN wp_term_taxonomy AS term_taxonomy ON relations.term_taxonomy_id = term_taxonomy.term_taxonomy_id
				WHERE pivot.member_id = {$member_id} 
				AND term_taxonomy.term_id = {$term_id} 
				AND t.language_code = '{$current_lang}' 
				AND t.element_type = 'post_vacancy'
				AND posts.post_type = 'vacancy'
				AND posts.post_status = 'publish'
				GROUP BY vacancy_id";

//		$query = sprintf("SELECT vacancy_id, created_at, updated_at FROM %s WHERE member_id = %s", self::TABLE_NAME, self::$member_id);
//		$query = sprintf("SELECT vacancy_id, created_at, updated_at FROM %s WHERE member_id = %s AND end_date > %s", self::TABLE_NAME, self::$member_id, $today);

		return $wpdb->get_results($query);
	}

	/**
	 * Returns all the internships for the $member_id
	 *
	 * @return array
	 */
	private static function getInternships(): array
	{
	    if(empty(self::$member_id)) self::$member_id = self::getMemberID();
	    if(empty(self::$member_id)) return [];
	    global $wpdb;
	    $current_lang = !empty(ICL_LANGUAGE_CODE) ? ICL_LANGUAGE_CODE : 'nl';
	    $table = self::TABLE_NAME;
	    $member_id = self::$member_id;

	    $term = get_term_by('slug', 'stage', 'employment');
	    if(empty($term) || is_wp_error($term)) return [];
	    $term_id = $term->term_id;

	    $query = "SELECT pivot.vacancy_id AS vacancy_id, pivot.created_at AS created_at, pivot.updated_at AS updated_at FROM {$table} AS pivot
					INNER JOIN {$wpdb->posts} AS posts ON posts.ID = pivot.vacancy_id
					INNER JOIN wp_icl_translations AS t ON pivot.vacancy_id = t.element_id
					INNER JOIN wp_term_relationships AS relations ON relations.object_id = pivot.vacancy_id
					INNER JOIN wp_term_taxonomy AS term_taxonomy ON relations.term_taxonomy_id = term_taxonomy.term_taxonomy_id
				WHERE pivot.member_id = {$member_id} 
				AND term_taxonomy.term_id = {$term_id} 
				AND t.language_code = '{$current_lang}' 
				AND t.element_type = 'post_vacancy'
				AND posts.post_type = 'vacancy'
				AND posts.post_status IN ('publish', 'draft') GROUP BY vacancy_id";

	    return $wpdb->get_results($query);
	}

	/**
	 * Get the post author first_name for the provided $vacancyID
	 *
	 * @param null $vacancyID
	 *
	 * @return bool|mixed|void
	 */
	public static function getPostAuthor($vacancyID = null)
	{
	    if(empty($vacancyID)) return false;
		$last_id = get_post_meta( $vacancyID, '_edit_last', true );

		if ( $last_id ) {
			$last_user = get_userdata( $last_id );

			/**
			 * Filters the display name of the author who last edited the current post.
			 *
			 * @since 2.8.0
			 *
			 * @param string $display_name The author's display name.
			 */
			return apply_filters( 'the_modified_author', $last_user->first_name );
		} else {
		    $author_id = get_post_meta($vacancyID, 'post_author', true);
		    return the_author_meta('first_name', $author_id);
		}
	}

	/**
	 * Registers the vacancy post_type
	 */
	public static function register_post_type(): void
	{
		$labels = array (
			'name'               => 'Vacatures',
			'singular_name'      => 'Vacature',
			'add_new'            => 'Toevoegen',
			'add_new_item'       => 'Vacature toevoegen',
			'edit_item'          => 'Bewerk Vacature',
			'new_item'           => 'Nieuw Vacature',
			'view_item'          => 'Bekijk Vacature',
			'search_items'       => 'Zoek vacatures',
			'not_found'          => 'Geen vacatures gevonden',
			'not_found_in_trash' => 'Geen vacatures gevonden in prullenbak'
		);

		$args = array (
			'label'                 => 'Vacatures',
			'description'           => 'Vacatures',
			'labels'                => $labels,
			'public'                => true,
			'has_archive'           => false,
			'exclude_from_search'   => false,
			'show_ui'               => true,
			'capability_type'       => 'page',
			'hierarchical'          => false,
			'rewrite' => array(
				'slug'              => 'vacatures',
				'with_front'        => true,
			),
			'query_var'             => true,
			'menu_icon'             => 'dashicons-welcome-learn-more',
			'show_in_rest'	        =>	true,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'revisions' ),
		);

		register_post_type( 'vacancy', $args );
	}

	/**
	 * Registers the taxonomies used by the vacancy post_type
	 */
	public static function register_taxonomies(): void
	{
		// Dienstverband taxonomy
		$labels = array(
			'name'                       => 'Dienstverbanden',
			'singular_name'              => 'Dienstverband',
			'menu_name'                  => 'Dienstverbanden',
			'all_items'                  => 'Alle Dienstverbanden',
			'parent_item'                => 'Hoofditem',
			'parent_item_colon'          => 'Hoofditem:',
			'new_item_name'              => 'Nieuwe dienstverband',
			'add_new_item'               => 'Nieuwe dienstverband',
			'edit_item'                  => 'Wijzig dienstverband',
			'update_item'                => 'Update dienstverband',
			'separate_items_with_commas' => 'Scheiden met een comma',
			'search_items'               => 'Zoek dienstverbanden',
			'add_or_remove_items'        => 'Voeg toe of verwijder dienstverband',
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

		register_taxonomy( 'employment', 'vacancy', $args );

		// Soort taxonomy
		$labels = array(
			'name'                       => 'Soorten',
			'singular_name'              => 'Soort',
			'menu_name'                  => 'Soorten',
			'all_items'                  => 'Alle soorten',
			'parent_item'                => 'Hoofditem',
			'parent_item_colon'          => 'Hoofditem:',
			'new_item_name'              => 'Nieuwe soort',
			'add_new_item'               => 'Nieuwe soort',
			'edit_item'                  => 'Wijzig soort',
			'update_item'                => 'Update soort',
			'separate_items_with_commas' => 'Scheiden met een comma',
			'search_items'               => 'Zoek soorten',
			'add_or_remove_items'        => 'Voeg toe of verwijder soort',
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

		register_taxonomy( 'vacancy-kind', 'vacancy', $args );

		// Function taxonomy
//		$labels = array(
//			'name'                       => 'Functies',
//			'singular_name'              => 'Functie',
//			'menu_name'                  => 'Functies',
//			'all_items'                  => 'Alle functies',
//			'parent_item'                => 'Hoofditem',
//			'parent_item_colon'          => 'Hoofditem:',
//			'new_item_name'              => 'Nieuwe functie',
//			'add_new_item'               => 'Nieuwe functie',
//			'edit_item'                  => 'Wijzig functie',
//			'update_item'                => 'Update functie',
//			'separate_items_with_commas' => 'Scheiden met een comma',
//			'search_items'               => 'Zoek functies',
//			'add_or_remove_items'        => 'Voeg toe of verwijder functie',
//			'choose_from_most_used'      => 'Meest gebruikte',
//		);
//
//		$args = array(
//			'labels'                     => $labels,
//			'hierarchical'               => true,
//			'public'                     => false,
//			'show_ui'                    => true,
//			'show_admin_column'          => true,
//			'show_in_nav_menus'          => true,
//		);
//
//		register_taxonomy( 'vacancy-function', 'vacancy', $args );
	}

	/**
	 * Will upsert the pivot table with the provided data
	 * If the record already exists it will update the updated_at field
	 *
	 * @param $member_id
	 * @param $vacancy_id
	 */
	public static function upsert_member_vacancy_pivot_data($member_id, $vacancy_id, $end_date = null): bool
	{
		global $wpdb;
		if(empty($member_id) || empty($vacancy_id)) return false;
		if(!empty($end_date)) {
			$query = sprintf('INSERT INTO %s (member_id, vacancy_id, end_date)
					VALUES(%d, %d, %s)
					ON DUPLICATE KEY UPDATE `updated_at` = NOW()',
				self::TABLE_NAME, $member_id, $vacancy_id, $end_date);
		} else {
			$query = sprintf('INSERT INTO %s (member_id, vacancy_id)
						VALUES(%d, %d)
						ON DUPLICATE KEY UPDATE `updated_at` = NOW()',
				self::TABLE_NAME, $member_id, $vacancy_id);
		}
		if($wpdb->query($query) === false) {
			error_log("[Dashboard\Vacancy->upsert_member_vacancy_pivot_data]: Could not upsert the pivot data for member_id: {$member_id} and vacancy_id: {$vacancy_id}");
			return false;
		}
		return true;
	}

	/**
	 * Create the member_vacancy_pivot table if it doesn't exist yet
	 */
	public static function create_member_vacancy_pivot_table(): void
	{
		global $wpdb;
		$query = 'CREATE TABLE IF NOT EXISTS `'.self::TABLE_NAME.'` (
			`member_id` int(11) unsigned NOT NULL,
			`vacancy_id` int(11) unsigned NOT NULL,
			`created_at` datetime NOT NULL DEFAULT current_timestamp(),
			`updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
			`end_date` date DEFAULT NULL,
			UNIQUE KEY `vacancy_id` (`vacancy_id`, `member_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;';
		if($wpdb->query($query) === false) {
			error_log("[Dashboard\Vacancy->create_member_vacancy_pivot_table]: Could not create the Member/Vacancy pivot table");
		}
	}

	/**
	 * Deletes a row in the member_vacancy_pivot table based on $vacancy_id
	 *
	 * @param $vacancy_id
	 *
	 * @return bool
	 */
	public static function delete_member_vacancy_pivot_row($vacancy_id): bool
	{
		if(empty($vacancy_id)) return false;
	    global $wpdb;
	    $member_id = get_field('organizer', $vacancy_id);
	    if(empty($member_id)) return false;

	    $check = $wpdb->get_results("SELECT * FROM ".self::TABLE_NAME." WHERE vacancy_id = {$vacancy_id} AND member_id = {$member_id}");

	    if(empty($check) || !$wpdb->delete(self::TABLE_NAME, ['vacancy_id' => $vacancy_id, 'member_id' => $member_id])) {
	    	return false;
	    }
	    return true;
	}

	/**
	 * Deletes a row in the member_vacancy_pivot table based on $vacancy_id
	 *
	 * @param $vacancy_id
	 * @param $member_id
	 *
	 * @return bool
	 */
	public static function delete_member_vacancy_pivot_row_with_member_id($vacancy_id, $member_id): bool
	{
		if(empty($vacancy_id) || empty($member_id)) return false;
	    global $wpdb;
	    if(!$wpdb->delete(self::TABLE_NAME, ['vacancy_id' => $vacancy_id, 'member_id' => $member_id])) {
	    	return false;
	    }
	    return true;
	}

	/**
	 * Adds a new vacancy
	 */
	public static function addVacancy()
	{
		check_ajax_referer('add_vacancy', 'nonce');

		// Setting empty errors array
		$errors = [];

		// Setting fields variable for internal usage
		$fields = $_POST['fields'];

		// Check if there are fields sent by the request
		if(empty($fields)) {
			error_log('[App\Dashboard\Vacancy->addVacancy]: The fields provided are empty');
			$errors[] = __('De opgegeven velden zijn leeg.', 'dda');
		}

		// Check if the member_id parameter is present
		if(!key_exists( 'member_id', $fields) || empty($fields['member_id'])) {
			error_log('[App\Dashboard\Vacancy->addVacancy]: The provided member_id doesn\'t exist or is empty');
			$errors = __('Er is iets fout gegaan bij het doorsturen van de gegevens.', 'dda');
		}

		// Check if the user_id parameter is present
		if(!key_exists( 'user_id', $fields) || empty($fields['user_id'])) {
			error_log('[App\Dashboard\Vacancy->addVacancy]: The provided user_id doesn\'t exist or is empty');
			$errors[] = __('Er is iets fout gegaan.', 'dda');
		}

		// Set the user_id variable and get the user object
		$user_id = $fields['user_id'];
		$user = get_user_by('ID', $user_id);

		// If no user has been found by the provided user_id
		if(empty($user)) {
			error_log("[App\Dashboard\Vacancy->addVacancy]: User object has not been found for user: {$user_id}.");
			$errors[] = __('Gebruiker niet gevonden a.h.v. het opgegeven emailadres.', 'dda');
		}

		// Setting the variables for internal usage
		$member_id = $fields['member_id'];
		$title = $fields['title'];
		$content = $fields['content'];
		$province_id = $fields['province_id'];
		$function = $fields['function'];
		$contract_id = $fields['contract_id'];
		$vacancy_type_id = $fields['vacancy_type_id'];
		$hours = $fields['hours'];
		$salary_min = $fields['salary_range_min'];
		$salary_max = $fields['salary_range_max'];
		$location = $fields['location'];
//		$end_date = $fields['end_date'];
		$vacancy_link = $fields['vacancy_link'];
		$post_status = $fields['post_status'];
		$modal_timeout_time = 0;

		// Add error messages if condition is met
		if(empty($title)) $errors[] = __('De titel is leeg', 'dda');
		if(empty($content)) $errors[] = __('De omschrijving is leeg', 'dda');
		if(empty($province_id)) $errors[] = __('Er is geen provincie geselecteerd', 'dda');
		if(empty($function)) $errors[] = __('Er is geen functie ingevoerd', 'dda');
		if(empty($contract_id)) $errors[] = __('Er is geen dienstverband geselecteerd', 'dda');
		if(empty($vacancy_type_id)) $errors[] = __('Er is geen soort vacature geselecteerd', 'dda');
		if(empty($hours)) $errors[] = __('Het aantal uren (per maand) veld is leeg', 'dda');
		if(empty($location)) $errors[] = __('Er is geen locatie geselecteerd', 'dda');

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

		// Create the new vacancy post
		$vacancy_id = wp_insert_post([
			'post_title'    => esc_html($title),
			'post_content'  => $content,
			'post_status'   => $post_status,
			'post_type'     => 'vacancy',
			'post_author'   => $user_id,
		]);

		// Something went wrong while updating the user
		if(is_wp_error($vacancy_id)) {
			error_log("[App\Dashboard\Vacancy->addVacancy]: We were unable to create the vacancy for member: {$member_id}, created by user: {$user_id}");
			$errors[] = __('Er is iets misgegaan, probeer het opnieuw.', 'dda');
			$modal_timeout_time = 1000;
			$response['modal'] = ['title' => 'Fout!', 'values' => $errors, 'type' => 'error', 'time' => $modal_timeout_time];
			wp_send_json_error($response);
			exit;
		}

		// Update separate post fields
		if(!empty($member_id)) update_field('organizer', $member_id, $vacancy_id);
		if(!empty($province_id)) update_field('province', $province_id, $vacancy_id);
		if(!empty($location)) update_field('city', $location, $vacancy_id);
		if(!empty($function)) update_field('function', $function, $vacancy_id);
		if(!empty($contract_id)) update_field('contract', $contract_id, $vacancy_id);
		if(!empty($vacancy_type_id)) update_field('vacancy_type', $vacancy_type_id, $vacancy_id);
		if(!empty($hours)) update_field('hours', $hours, $vacancy_id);
		if(!empty($salary_min) && !empty($salary_max)) {
			$salary_range = $salary_min. ' - ' .$salary_max;
			update_field('salary_range', $salary_range, $vacancy_id);
		}
//		if(!empty($end_date)) update_field('end_date', $end_date, $vacancy_id);
		if(!empty($vacancy_link)) update_field('vacancy_link', $vacancy_link, $vacancy_id);

		$end_date = get_field('end_date', $vacancy_id);

		// sync the pivot table
		if( !self::upsert_member_vacancy_pivot_data($member_id, $vacancy_id, $end_date) ) {
		    error_log("[Dashboard\Vacancy->addVacancy]: Could not upsert the pivot data for member_id: {$member_id} and vacancy_id: {$vacancy_id}");
	    }

		$employment = get_term_by('id', $contract_id, 'employment');
		$title = __('Vacature is aangemaakt!', 'dda');
		if(!empty($employment) && !is_wp_error( $employment)) {
			if($employment->slug === 'stage') $title = __('Stage is aangemaakt!', 'dda');
		}

		// Set the edit_last post meta field
		update_post_meta($vacancy_id, '_edit_last', DashboardController::getUserId());

		$messages[] = $title;
		$messages[] = __('Je wordt binnen enkele seconden doorgestuurd.', 'dda');
		$response['modal'] = ['title' => 'Succes', 'values' => $messages, 'type' => 'success', 'time' => 2500];
		$response['link'] = DashboardController::getDashboardLink();

		// Send success message containing the dashboard link where the user will be redirected to
		wp_send_json_success($response);
		exit;
	}

	/**
	 * Updates a vacancy
	 */
	public static function updateVacancy()
	{
		check_ajax_referer('update_vacancy', 'nonce');

		// Setting empty errors array
		$errors = [];

		// Setting fields variable for internal usage
		$fields = $_POST['fields'];

		// Check if there are fields sent by the request
		if(empty($fields)) {
			error_log('[App\Dashboard\Vacancy->updateVacancy]: The fields provided are empty');
			$errors[] = __('De opgegeven velden zijn leeg.', 'dda');
		}

		// Check if the member_id parameter is present
		if(!key_exists( 'member_id', $fields) || empty($fields['member_id'])) {
			error_log('[App\Dashboard\Vacancy->updateVacancy]: The provided member_id doesn\'t exist or is empty');
			$errors = __('Er is iets fout gegaan bij het doorsturen van de gegevens.', 'dda');
		}

		// Check if the user_id parameter is present
		if(!key_exists( 'user_id', $fields) || empty($fields['user_id'])) {
			error_log('[App\Dashboard\Vacancy->updateVacancy]: The provided user_id doesn\'t exist or is empty');
			$errors[] = __('Er is iets fout gegaan bij het doorsturen van de gegevens.', 'dda');
		}

		// Set the user_id variable and get the user object
		$user_id = $fields['user_id'];
		$user = get_user_by('ID', $user_id);

		// If no user has been found by the provided user_id
		if(empty($user)) {
			error_log("[App\Dashboard\Vacancy->updateVacancy]: User object has not been found for user: {$user_id}.");
			$errors[] = __('Er is iets fout gegaan bij het doorsturen van de gegevens.', 'dda');
		}

		// Setting the variables for internal usage
		$member_id = $fields['member_id'];
		$vacancy_id = $fields['vacancy_id'];
		$title = $fields['title'];
		$content = $fields['content'];
		$province_id = $fields['province_id'];
		$function = $fields['function'];
		$contract_id = $fields['contract_id'];
		$vacancy_type_id = $fields['vacancy_type_id'];
		$hours = $fields['hours'];
		$salary_min = $fields['salary_range_min'];
		$salary_max = $fields['salary_range_max'];
		$location = $fields['location'];
//		$end_date = $fields['end_date'];
		$vacancy_link = $fields['vacancy_link'];
		$post_status = $fields['post_status'];
		$modal_timeout_time = 0;

		// Add error messages if condition is met
		if(empty($vacancy_id)) $errors[] = __('Er is iets fout gegaan bij het bewerken van de vacature', 'dda');
		if(empty($title)) $errors[] = __('De titel is leeg', 'dda');
		if(empty($content)) $errors[] = __('De omschrijving is leeg', 'dda');
		if(empty($province_id)) $errors[] = __('Er is geen provincie geselecteerd', 'dda');
		if(empty($function)) $errors[] = __('Er is geen functie ingevoerd', 'dda');
		if(empty($contract_id)) $errors[] = __('Er is geen dienstverband geselecteerd', 'dda');
		if(empty($vacancy_type_id)) $errors[] = __('Er is geen soort vacature geselecteerd', 'dda');
		if(empty($hours)) $errors[] = __('Het aantal uren (per maand) veld is leeg', 'dda');
		if(empty($location)) $errors[] = __('Er is geen locatie geselecteerd', 'dda');

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

		// Update the vacancy post
		$vacancy_id = wp_update_post([
			'ID'            => $vacancy_id,
			'post_title'    => esc_html($title),
			'post_content'  => $content,
			'post_status'   => $post_status,
			'post_author'   => $user_id,
		]);

		// Something went wrong while updating the user
		if(is_wp_error($vacancy_id)) {
			error_log("[App\Dashboard\Vacancy->updateVacancy]: We were unable to update the vacancy for member: {$member_id}, created by user: {$user_id}");
			$errors[] = __('Er is iets misgegaan, probeer het opnieuw.', 'dda');
			$modal_timeout_time = 1000;
			$response['modal'] = ['title' => 'Fout!', 'values' => $errors, 'type' => 'error', 'time' => $modal_timeout_time];
			wp_send_json_error($response);
			exit;
		}

		// Update separate post fields
		if(!empty($member_id)) update_field('organizer', $member_id, $vacancy_id);
		if(!empty($province_id)) update_field('province', $province_id, $vacancy_id);
		if(!empty($location)) update_field('city', $location, $vacancy_id);
		if(!empty($function)) update_field('function', $function, $vacancy_id);
		if(!empty($contract_id)) update_field('contract', $contract_id, $vacancy_id);
		if(!empty($vacancy_type_id)) update_field('vacancy_type', $vacancy_type_id, $vacancy_id);
		if(!empty($hours)) update_field('hours', $hours, $vacancy_id);
		if(!empty($salary_min) && !empty($salary_max)) {
			$salary_range = $salary_min. ' - ' .$salary_max;
			update_field('salary_range', $salary_range, $vacancy_id);
		}
//		if(!empty($end_date)) update_field('end_date', $end_date, $vacancy_id);
		if(!empty($vacancy_link)) update_field('vacancy_link', $vacancy_link, $vacancy_id);

		$end_date = get_field('end_date', $vacancy_id);

		// sync the pivot table
		if( !self::upsert_member_vacancy_pivot_data($member_id, $vacancy_id, $end_date) ) {
			error_log("[Dashboard\Vacancy->updateVacancy]: Could not upsert the pivot data for member_id: {$member_id} and vacancy_id: {$vacancy_id}");
		}

		// Set the edit_last post meta field
		update_post_meta($vacancy_id, '_edit_last', DashboardController::getUserId());

		$employment = get_term_by('id', $contract_id, 'employment');
		$title = __('Vacature bijgewerkt!', 'dda');
		if(!empty($employment) && !is_wp_error( $employment)) {
			if($employment->slug === 'stage') $title = __('Stage bijgewerkt!', 'dda');
		}

		$messages[] = $title;
		$messages[] = __('Je wordt binnen enkele seconden doorgestuurd.', 'dda');
		$response['modal'] = ['title' => 'Succes', 'values' => $messages, 'type' => 'success', 'time' => 2500];
		$response['link'] = DashboardController::getDashboardLink();

		// Send success message containing the dashboard link where the user will be redirected to
		wp_send_json_success($response);
		exit;
	}

	/**
	 * Delete a vacancy through ajax call
	 */
	public static function deleteVacancy()
	{
		check_ajax_referer('delete_vacancy', 'nonce');

		// Setting empty errors array
		$errors = [];

		// Setting fields variable for internal usage
		$vacancy_id = key_exists('vacancy_id', $_POST) ? $_POST['vacancy_id'] : '';
		$user_id = key_exists('user_id', $_POST) ? $_POST['user_id'] : '';

		// Check if there are fields sent by the request
		if(empty($vacancy_id)) {
			error_log('[App\Dashboard\Vacancy->deleteVacancy]: The $vacancy_id is empty');
			$errors[] = __('Er is iets fout gegaan, probeer het opnieuw.', 'dda');
		}

		// Check if the user_id parameter is present
		if(empty($user_id)) {
			error_log('[App\Dashboard\Vacancy->deleteVacancy]: The provided user_id is empty');
			$errors[] = __('Er is iets fout gegaan, probeer het opnieuw.', 'dda');
		}

		// Setting the variables for internal usage
		$vacancy = get_post($vacancy_id);
		if(empty($vacancy) || is_wp_error($vacancy) || get_post_type($vacancy) !== 'vacancy' || !is_object($vacancy) || !property_exists( $vacancy, 'post_author')) {
			error_log("[App\Dashboard\Vacancy->deleteVacancy]: Found vacancy is empty, wp_error or not of post type 'vacancy'");
			$errors[] = __('Er is iets fout gegaan, probeer het opnieuw.', 'dda');
		}

		// Get the member id
		$member_id = get_field('organizer', $vacancy_id);
		$member_vacancies = Member::getMemberVacancyIds( $member_id );
		$member_employees = [];
		if(!empty($member_vacancies) && in_array($vacancy_id, $member_vacancies)) {
			$member_employees = Member::getEmployees($member_id);
		}

		// If the provided user_id is not the post_author, add to the errors array
		if(!is_object($vacancy) && !in_array($user_id, $member_employees)) {
			error_log("[App\Dashboard\Vacancy->deleteVacancy]: Provided user_id: {$user_id} is not the post author.");
			$errors[] = __('Je hebt geen rechten om deze vacature te verwijderen.', 'dda');
		}

		// Send the error messages to the user
		if(!empty($errors)) {

			$modal_timeout_time = 0;

			// loop over the amount of errors and add 750ms to the $modal_timeout_time for each error found
			foreach($errors as $error) {
				$modal_timeout_time += 750;
			}

			$errors = array_unique($errors);
			$response['modal'] = ['title' => 'Fout!', 'values' => $errors, 'type' => 'error', 'time' => $modal_timeout_time];
			wp_send_json_error($response);
			exit;
		}

		// Reset the $errors array to an empty array
		$errors = [];

		$member_id = get_field('organizer', $vacancy_id);
		$pivot_row_delete = self::delete_member_vacancy_pivot_row_with_member_id( $vacancy_id, $member_id);
		if(empty($pivot_row_delete)) {
			error_log("[App\Dashboard\Vacancy->deleteVacancy]: Unable to delete vacancy from pivot row. Vacancy: {$vacancy_id}, Member: {$member_id}");
			$errors[] = __('Er is iets fout gegaan tijdens het verwijderen van deze vacature.', 'dda');
		}

		// Delete the vacancy (add it to trash)
		$vacancy_delete = wp_delete_post($vacancy_id);

		if(empty($vacancy_delete)) {
			error_log("[App\Dashboard\Vacancy->deleteVacancy]: Unable to delete vacancy {$vacancy_id}");
			$errors[] = __('Er is iets fout gegaan tijdens het verwijderen van deze vacature.', 'dda');
		}

		// Check if we have errors, if so exit
		if(!empty($errors)) {
			// loop over the amount of errors and add 750ms to the $modal_timeout_time for each error found
			foreach($errors as $error) {
				$modal_timeout_time += 750;
			}

			$errors[] = array_unique($errors);
			$response['modal'] = ['title' => 'Fout!', 'values' => $errors, 'type' => 'error', 'time' => $modal_timeout_time];
			wp_send_json_error($response);
			exit;
		}

		// return the "fresh" dashboard
		self::refreshDashboard('Succes!', [__('Vacature verwijderd', 'dda')], 2500);
	}

	/**
	 * Triggers when a post is being saved,
	 * if this post is post_type vacancy
	 * the pivot data will be upserted
	 *
	 * @param $post_id
	 */
	public static function onSaveVacancy($post_id): void
	{
	    if( $parent_id = wp_is_post_revision( $post_id ) ) $post_id = $parent_id;
		if( get_post_type($post_id) !== 'vacancy' || get_post_status($post_id) === 'trash' ) return;
	    $member_id = get_field('organizer', $post_id);
	    if( empty($member_id) ) return;
	    $end_date = get_field('end_date', $post_id);

	    if( !self::upsert_member_vacancy_pivot_data($member_id, $post_id, $end_date) ) {
		    error_log("[Dashboard\Vacancy->onSaveVacancy]: Could not upsert the pivot data for member_id: {$member_id} and vacancy_id: {$post_id}");
	    }

	    if(!self::hasEnglishDuplicate($post_id) && !self::isEnglishDuplicate($post_id)) self::createEnglishDuplicate($post_id);
	}

	/**
	 * Triggers when a post is being trashed
	 * if this post is post_type vacancy
	 * the pivot row will be deleted
	 *
	 * @param $post_id
	 */
	public static function onTrashVacancy($post_id): void
	{
	    if( get_post_type($post_id) !== 'vacancy') return;

	    if( !self::delete_member_vacancy_pivot_row($post_id) ) {
	    	error_log("[Dashboard\Vacancy->onTrashVacancy]: Unable to delete the row for vacancy_id: {$post_id}");
	    }
	}

	/**
	 * Triggers when a post is being restored
	 * if this post is post_type vacancy
	 * the pivot row will be upserted
	 *
	 * @param $post_id
	 */
	public static function onRestoreVacancy($post_id): void
	{
	    if( get_post_type($post_id) !== 'vacancy') return;

	    $member_id = get_field('organizer', $post_id);
	    if( empty($member_id) ) {
	    	error_log("[Dashboard\Vacancy->onRestoreVacancy]: Couldn't find the member_id for the provided vacancy post: {$post_id}");
	    	return;
	    }
	    if(!self::upsert_member_vacancy_pivot_data($member_id, $post_id)) {
	    	error_log("[Dashboard\Vacancy->onRestoreVacancy]: Unable to re-sync the member_vacancy_pivot with member_id: {$member_id} and vacancy_id: {$post_id}");
	    }
	}

	public static function get_terms_by_post_type( $taxonomies, $post_types ) {

		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT t.*, COUNT(*) from $wpdb->terms AS t
        INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
        INNER JOIN $wpdb->term_relationships AS r ON r.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN $wpdb->posts AS p ON p.ID = r.object_id
        WHERE p.post_type IN('%s') AND tt.taxonomy IN('%s')
        GROUP BY t.term_id",
			join( "', '", $post_types ),
			join( "', '", $taxonomies )
		);

		$results = $wpdb->get_results( $query );

		return $results;

	}

	/**
	 * Check whether the given vacancy_id is a translation or not
	 *
	 * @param int $vacancy_id
	 *
	 * @return bool
	 */
	public static function isEnglishDuplicate($vacancy_id = 0): bool
	{
	    if(empty($vacancy_id)) return false;
	    $vacancy_language_details = apply_filters('wpml_post_language_details', null, $vacancy_id);
	    if(empty($vacancy_language_details)) return false;
	    if(key_exists( 'language_code', $vacancy_language_details) && $vacancy_language_details['language_code'] === 'en') return true;
	    return false;
	}

	/**
	 * Checks whether the given post id has an english translation
	 *
	 * @param int $member_id
	 *
	 * @return bool
	 */
	public static function hasEnglishDuplicate($vacancy_id = 0)
	{
		if(empty($vacancy_id)) return false;
		$has_translation = apply_filters('wpml_element_has_translations', null, $vacancy_id, 'post_vacancy');
		if(!$has_translation) return false;
		return true;
	}

	/**
	 * Get the english duplicate of the given $vacancy_id
	 *
	 * @param int $vacancy_id
	 *
	 * @return int|mixed|void
	 */
	public static function getEnglishDuplicate($vacancy_id = 0)
	{
	    if(empty($vacancy_id)) return 0;
		return apply_filters('wpml_object_id', $vacancy_id, 'vacancy', true, 'en');
	}

	/**
	 * Create an english duplicate of the member post
	 * returns true on success, false on failure
	 *
	 * @param int $member_id
	 *
	 * @return bool
	 */
	public static function createEnglishDuplicate($vacancy_id = 0)
	{
		if(empty($vacancy_id)) return false;
		global $sitepress;
		$title = get_the_title($vacancy_id);
		$content = get_post_field('post_content', $vacancy_id);
		$organizer = get_field('organizer', $vacancy_id);
		$function = get_field('function', $vacancy_id);
		$province = get_field('province', $vacancy_id);
		$hours = get_post_meta($vacancy_id, 'hours', true);
		$city = get_post_meta($vacancy_id, 'city', true);
		$contract = get_field('contract', $vacancy_id);
		$vacancy_type = get_field('vacancy_type', $vacancy_id);
		$salary_range = get_post_meta($vacancy_id, 'salary_range', true);
		$end_date = get_post_meta($vacancy_id, 'end_date', true);
		$vacancy_url = get_post_meta($vacancy_id, 'vacancy_link', true);
		$vacancy_provinces_taxonomy = get_the_terms($vacancy_id, 'province');
		$vacancy_employment_taxonomy = get_the_terms($vacancy_id, 'employment');
		$vacancy_kind_taxonomy = get_the_terms($vacancy_id, 'vacancy-kind');

		$en_args = [
			'post_title'    => $title,
			'post_content'  => $content,
			'post_type'     => 'vacancy',
			'post_status'   => get_post_status($vacancy_id),
		];
		$trid = $sitepress->get_element_trid($vacancy_id);
		$vacancy_id_en = wp_insert_post($en_args);
		if(is_wp_error( $vacancy_id_en)) return false;
		$sitepress->set_element_language_details($vacancy_id_en, 'post_vacancy', $trid, 'en');

		if(!empty($organizer)) {
			$organizer_en = Member::getEnglishDuplicate($organizer);
			if(!empty($organizer_en)) $organizer = $organizer_en;
			update_post_meta($vacancy_id_en, 'organizer', $organizer);
		}
		if(!empty($function)) update_post_meta($vacancy_id_en, 'function', $function);
		if(!empty($province)) update_post_meta($vacancy_id_en, 'province', $province);
		if(!empty($hours)) update_post_meta($vacancy_id_en, 'hours', $hours);
		if(!empty($contract)) update_post_meta($vacancy_id_en, 'contract', $contract);
		if(!empty($vacancy_type)) update_post_meta($vacancy_id_en, 'vacancy_type', $vacancy_type);
		if(!empty($salary_range)) update_post_meta($vacancy_id_en, 'salary_range', $salary_range);
		if(!empty($end_date)) update_post_meta($vacancy_id_en, 'end_date', $end_date);
		if(!empty($vacancy_url)) update_post_meta($vacancy_id_en, 'vacancy_link', $vacancy_url);

		if(!empty($vacancy_provinces_taxonomy) && !is_wp_error( $vacancy_provinces_taxonomy)) {
			wp_set_post_terms(
				$vacancy_id_en,
				array_map(
					function($tax) {
						if(!empty($tax->name)) {
							return $tax->term_id;
						}
					},
					$vacancy_provinces_taxonomy
				),
				'province'
			);
		}

		if(!empty($vacancy_employment_taxonomy) && !is_wp_error( $vacancy_employment_taxonomy)) {
			wp_set_post_terms(
				$vacancy_id_en,
				array_map(
					function($tax) {
						if(!empty($tax->name)) {
							return $tax->term_id;
						}
					},
					$vacancy_employment_taxonomy
				),
				'employment'
			);
		}

		if(!empty($vacancy_kind_taxonomy) && !is_wp_error( $vacancy_kind_taxonomy)) {
			wp_set_post_terms(
				$vacancy_id_en,
				array_map(
					function($tax) {
						if(!empty($tax->name)) {
							return $tax->term_id;
						}
					},
					$vacancy_kind_taxonomy
				),
				'vacancy-kind'
			);
		}

		return true;
	}
}


