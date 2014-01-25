<?php

error_reporting(E_ALL & ~E_NOTICE);

// Pre-cache templates and data.
$phrasegroups = array('maintenance');
$specialtemplates = array();

// Backend.
require_once('./global.php');
require_once(DIR . '/includes/functions_misc.php');

// Make sure admins are viewing this page.
if (!can_administer('canadminusers')) {
	print_cp_no_permission();
}

log_admin_action();
print_cp_header($vbphrase['masspwreset_page_header']);

// No action specified? Lets set a default one.
if (empty($_REQUEST['do'])) {
	$_REQUEST['do'] = 'panel';
}

// Checkable periods of time.
$periods = array(
	'0' => $vbphrase['over_any_period'],
	'259200' => construct_phrase($vbphrase['over_x_days_ago'], 3),
	'604800' => $vbphrase['over_1_week_ago'],
	'1209600' => construct_phrase($vbphrase['over_x_weeks_ago'], 2),
	'1814400' => construct_phrase($vbphrase['over_x_weeks_ago'], 3),
	'2592000' => $vbphrase['over_1_month_ago'],
	'5270400' => construct_phrase($vbphrase['over_x_months_ago'], 2),
	'7862400' => construct_phrase($vbphrase['over_x_months_ago'], 3),
	'15724800' => construct_phrase($vbphrase['over_x_months_ago'], 6)
);
// Selected period.
$period = $vbulletin->GPC['period'];

// Get language data.
$languages = array_merge(array('All'), fetch_language_titles_array('', 0));
// Used for language selection.
$languageId = 0;
// Total number of users.
$processedTotal = 0;
// Total number of passwords reset.
$reset = 0;
// Any errors?
$resetErrors = false;
$emailErrors = false;
$vbulletin->input->clean_array_gpc('r', array(
	'lastUser' => TYPE_UINT,
	'resetOnError' => TYPE_BOOL
));
// Helps keep track of the last member during error messages.
$lastUser = $vbulletin->GPC['lastUser'];
$resetOnError = $vbulletin->GPC['resetOnError'];

function checkResetErrors() {
	
	global $resetOnError, $resetErrors, $emailErrors, $lastUser, $vbphrase;

	// Email errors only? Let's not interrupt then.
	if ($resetOnError && $emailErrors)
		return true;
	else if (!$resetErrors) // No password reset errors too? Everything must be fine then.
		return true;
	
	// Prompt the user there was an error.
	print_cp_message($vbphrase['masspwreset_reset_error'], 'passwordreset.php?do=reset;lastUser=' . $lastUser, 0, 'passwordreset.php', true);
	return false;
}

if ($_REQUEST['do'] == 'reset') {
	// Cleanse some POST input vars.
	$vbulletin->input->clean_array_gpc('p', array(
		'quantity' => TYPE_UINT,
		'reset' => TYPE_UINT,
		'processed' => TYPE_UINT,
		'doBanned' => TYPE_UINT,
		'message' => TYPE_NOHTML,
		'emailSubject' => TYPE_NOHTML,
		'emailFrom' => TYPE_NOHTML,
		'languageid' => TYPE_UINT,
		'membergroupIds' => TYPE_ARRAY_INT,
		'useMailQueue' => TYPE_UINT
	));
	$quantity = $vbulletin->GPC['quantity'];
	$processed = $vbulletin->GPC['processed'];
	$doBanned = $vbulletin->GPC['doBanned'];
	$message = $vbulletin->GPC['message'];
	$emailSubject = $vbulletin->GPC['emailSubject'];
	$emailFrom = $vbulletin->GPC['emailFrom'];
	$languageId = $vbulletin->GPC['languageId'];
	$membergroupIds = $vbulletin->GPC['membergroupIds'];
	$useMailQueue = $vbulletin->GPC['useMailQueue'];
	
	if (empty($emailSubject) || empty($message) || empty($emailFrom) || empty($membergroupIds))
		print_stop_message('please_complete_required_fields');

	if (strpos($message, '{password}') === false)
		print_stop_message('you_must_enter_the_password_token_into_the_message');

	while (checkResetErrors()) {
		$request = $vbulletin->db->query(
			'SELECT user.userid, userban.liftdate
			FROM ' . TABLE_PREFIX . 'user AS user
			LEFT JOIN ' . TABLE_PREFIX . 'userban AS userban
				ON user.userid = userban.userid
			WHERE user.userid > ' . $lastUser . '
			AND user.usergroupid IN (' . implode(',', $membergroupIds) . ') ' .
			($period ? 'AND user.lastvisit < ' . (TIMENOW - $period) : '') .
			($languageId ? 'AND user.languageid = ' . $languageId : '') . '
			LIMIT 0, ' . $quantity
		);
		
		$total = $vbulletin->db->num_rows($request);
		// All finished?
		if (!$total)
			break;
		
		while ($user = $vbulletin->db->fetch_array($request)) {
			// Set last user processed.
			$lastUser = $user['userid'];
			// Copy the ban liftdate since fetch_userinfo doesn't do this.
			$liftDate = $user['liftdate'];
			$user = fetch_userinfo($user['userid']);
			$randomPassword = fetch_random_password();

			// Send mail to user.
			if ($doBanned || $liftDate !== '0') {
				$message = str_replace('{username}', $user['username'], $message);
				$message = str_replace('{password}', $randomPassword, $message);
				// Did the message fail?
				if (!vbmail($user['email'], $emailSubject, $message, $useMailQueue, $emailFrom)) {
					$emailErrors = true;
					if (!$resetOnError)
						continue;
				}
			}

			// Reset the password.
			$userData = datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userData->set_existing($user);
			$userData->set('password', $randomPassword);
			$userData->save();

			// Check reset for errors.
			if (count($userData->errors) > 0) {
				$resetErrors = true;
				continue;
			}

			$reset++;
		}
		
		$vbulletin->db->free_result($request);
		unset($userData);
		$processedTotal += $total;
	}
	
	// Display results.
	print_table_start();
	print_table_header($vbphrase['passwords_reset']);
	print_description_row(construct_phrase($vbphrase['x_of_y_passwords_were_reset'], $reset, $processedTotal), false, 2, '', 'center');

	if ($resetErrors)
		print_description_row($vbphrase['some_errors_occured_while_resetting_passwords']);
	if ($emailErrors)
		print_description_row($vbphrase['some_errors_occured_while_sending_emails']);
	if ($languageId)
		print_description_row(construct_phrase($vbphrase['only_accounts_using_language_x_were_processed'], $languages[$languageId]));

	print_table_footer();
	// Display panel for re-submit.
	$_REQUEST['do'] = 'panel';
}

if ($_REQUEST['do'] == 'panel') {
	// Email message data.
	$emailSubject = construct_phrase($vbphrase['setting_masspwreset_subject'] . $vbulletin->options['bbtitle']);
	$message = 'Username: {username}' . PHP_EOL . 'Password: {password}';
	
	// Display the form.
	print_form_header('passwordreset', 'reset');
	print_table_header($vbphrase['masspwreset_page_header']);
	print_column_style_code(array('width: 40%','width: 60%'));
	print_select_row($vbphrase['reset_accounts_with_last_activity'], 'period', $periods, $period);
	print_input_row($vbphrase['email_to_send_at_once'], 'quantity', 100);
	print_input_row($vbphrase['email_subject'], 'emailSubject', $emailSubject, false, 70);
	print_input_row($vbphrase['email_from'], 'emailFrom', $vbulletin->options['webmasteremail'], false, 70);
	print_textarea_row($vbphrase['password_vulnerability_email_message_label'], 'message', $message, 30, 70);
	print_select_row($vbphrase['reset_passwords_for_users_with_language'], 'languageId', $languages, $languageId);
	print_yes_no_row($vbphrase['email_permanently_banned_users'], 'doBanned', 0);
	print_yes_no_row($vbphrase['reset_password_if_email_failed'], 'resetOnError', 0);
	print_membergroup_row($vbphrase['reset_primary_usergroups'], 'membergroupIds');
	print_yes_no_row($vbphrase['reset_use_mail_queue'], 'useMailQueue', 0);
	print_submit_row($vbphrase['reset_vulnerable_passwords'], false);
	print_table_footer();
}

print_cp_footer();

?>