<?php

/*
	Guest Registration Notification
	by: Anthony`
	Copyright 2012 - Anthony`

	############################################
	License Information:

	Keep this comment unmodified.
	#############################################
*/

if (!defined('IN_MYBB')) {
	die('This file cannot be accessed directly.');
}

$plugins->add_hook('pre_output_page', 'guestregistrationnotification_show');

function guestregistrationnotification_info() {
	return array (
		'name' => 'Guest Registration Notification',
		'description' => 'Adds a notification to guests viewing the page.',
		'website' => '',
		'author' => 'Anthony`',
		'authorsite' => '',
		'version' => '1.1',
		'guid' => 'a41a5963cf146325a792a7c20551386a',
		'compatibility'	=> '16*'
	);
}


function guestregistrationnotification_install() {

	global $db, $lang;

    $lang->load('guestregistrationnotification');
    
	$db->insert_query('settings', array(
        'sid' => 0,
        'name' => 'guestregistrationnotification_content',
        'title' => $lang->GRN_content_title,
        'description' => $lang->GRN_content_desc,
        'optionscode' => 'text',
        'value' => '',
        'disporder' => 0,
        'gid' => 6
    ));
	$db->insert_query('settings', array(
        'sid' => 0,
        'name' => 'guestregistrationnotification_title',
        'title' => $lang->GRN_title_title,
        'description' => $lang->GRN_title_desc,
        'optionscode' => 'text',
        'value' => '',
        'disporder' => 0,
        'gid' => 6
    ));
	$db->insert_query('settings', array(
        'sid' => 0,
        'name' => 'guestregistrationnotification_style',
        'title' => $lang->GRN_style_title,
        'description' => $lang->GRN_style_desc,
        'optionscode' => 'text',
        'value' => 'padding: 1em;border: 1px solid #cc3344;color: #000;background-color: #ffe4e9;margin-bottom: 1em;',
        'disporder' => 0,
        'gid' => 6
    ));
	$db->insert_query('settings', array(
        'sid' => 0,
        'name' => 'guestregistrationnotification_image',
        'title' => $lang->GRN_image_title,
        'description' => $lang->GRN_image_desc,
        'optionscode' => 'text',
        'value' => '',
        'disporder' => 0,
        'gid' => 6
    ));

	rebuild_settings();
}

function guestregistrationnotification_is_installed() {

	global $db;
	
	$query = $db->simple_select('settings', 'sid', 'name = \'guestregistrationnotification_content\'');
	$query2 = $db->simple_select('settings', 'sid', 'name = \'guestregistrationnotification_title\'');
	$query3 = $db->simple_select('settings', 'sid', 'name = \'guestregistrationnotification_style\'');
	$query4 = $db->simple_select('settings', 'sid', 'name = \'guestregistrationnotification_image\'');

	if ($db->num_rows($query) > 0 && $db->num_rows($query2) > 0
        && $db->num_rows($query3) > 0 && $db->num_rows($query4) > 0)
		return true;
	else
		return false;
}

function guestregistrationnotification_uninstall() {

	global $db;

	$db->write_query('DELETE FROM ' . TABLE_PREFIX . 'settings WHERE name = \'guestregistrationnotification_content\'');
	$db->write_query('DELETE FROM ' . TABLE_PREFIX . 'settings WHERE name = \'guestregistrationnotification_title\'');
	$db->write_query('DELETE FROM ' . TABLE_PREFIX . 'settings WHERE name = \'guestregistrationnotification_style\'');
	$db->write_query('DELETE FROM ' . TABLE_PREFIX . 'settings WHERE name = \'guestregistrationnotification_image\'');

	rebuild_settings();
}


function guestregistrationnotification_activate() { }

function guestregistrationnotification_deactivate() { }

function guestregistrationnotification_show($return) {

	global $mybb, $lang;

	$lang->load('guestregistrationnotification');
	$grn_content = '';
	$lang->GRN_default_title = $lang->sprintf($lang->GRN_default_title, $mybb->settings['bbname']);
	
	if ($mybb->user['usergroup'] == 1) {
    		$grn_content .= !empty($mybb->settings['guestregistrationnotification_style']) ? '<div style="padding: 1em; margin-bottom: 1em;' . $mybb->settings['guestregistrationnotification_style'] . '">' : '<div style="padding: 1em;border: 1px solid #cc3344;color: #000;background-color: #ffe4e9;margin-bottom: 1em;">';
    		$grn_content .= !empty($mybb->settings['guestregistrationnotification_image']) ? '<img style="float: left; width: 2ex; padding-right: 5px;" src="' . $mybb->settings["guestregistrationnotification_image"] . '" title="" />' : '<p style="padding: 0;margin: 0;float: left;width: 1em;font-size: 1.5em;color:red;">!!</p>';
    		$grn_content .= !empty($mybb->settings['guestregistrationnotification_title']) ? '<h3 style="padding: 0;margin: 0;">' . $mybb->settings['guestregistrationnotification_title'] . '</h3>' : '<h3 style="padding: 0;margin: 0;">' . $lang->GRN_default_title . '</h3>';
    		$grn_content .= '<p style="margin: 1em 0 0 0;">' . $mybb->settings['guestregistrationnotification_content'] . '</p>';
    		$grn_content .= '</div>';
	}
	
    require_once(MYBB_ROOT . 'inc/class_parser.php');
    $parser = new postParser();
    $grn_content = $parser->parse_mycode($grn_content);
	$return = str_replace('<div id="content">', '<div id="content">' . $grn_content, $return);
	return $return;
}

?>