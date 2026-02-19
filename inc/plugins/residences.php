<?php

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// ADMIN-CP 
$plugins->add_hook('admin_config_settings_change', 'residences_settings_change');
$plugins->add_hook('admin_settings_print_peekers', 'residences_settings_peek');
$plugins->add_hook("admin_config_menu", "residences_admin_config_menu");
$plugins->add_hook("admin_config_permissions", "residences_admin_config_permissions");
$plugins->add_hook("admin_config_action_handler", "residences_admin_config_action_handler");
$plugins->add_hook("admin_load", "admin_load_residences");
$plugins->add_hook("admin_formcontainer_end", "residences_usergroup_permission");
$plugins->add_hook("admin_user_groups_edit_commit", "residences_usergroup_permission_commit");

// global
$plugins->add_hook("global_intermediate", "residences_global");

// Misc
$plugins->add_hook("misc_start", "residences_misc");

// Alert
if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
    $plugins->add_hook("global_start", "residences_alerts");
}
//wer ist wo
$plugins->add_hook('fetch_wol_activity_end', 'residences_user_activity');
$plugins->add_hook('build_friendly_wol_location_end', 'residences_location_activity');

// Modcp
$plugins->add_hook("modcp_nav", "residences_modcp_nav");
$plugins->add_hook("modcp_start", "residences_modcp");

// Profile
$plugins->add_hook("member_profile_start", "residences_profile");

function residences_info()
{
    return array(
        "name" => "Wer wohnt wo?",
        "description" => "Dieser Plugin ermöglicht es, dass Charaktere in verschiedene Residenzen einziehen können. Egal ob Hochhaus, Wohnung oder doch auf der Straße.",
        "website" => "",
        "author" => "Ales",
        "authorsite" => "",
        "version" => "2.0",
        "guid" => "",
        "codename" => "",
        "compatibility" => "*"
    );
}

function residences_install()
{
    global $db, $cache;
    $db->query("CREATE TABLE `" . TABLE_PREFIX . "places` (
        `pid` int(10) NOT NULL auto_increment,
        `country` varchar(500) CHARACTER SET utf8 NOT NULL,
        `place` varchar(500) CHARACTER SET utf8 NOT NULL,
        `accepted` int(10) NOT NULL,
        `uid` int(10) NOT NULL,
        PRIMARY KEY (`pid`)
      ) ENGINE=MyISAM" . $db->build_create_table_collation());

    $db->query("CREATE TABLE `" . TABLE_PREFIX . "residences` (
        `rid` int(10) NOT NULL auto_increment,
        `pid` int(10) NOT NULL,
        `street` varchar(500) CHARACTER SET utf8 NOT NULL,
        `housenumber` varchar(500) CHARACTER SET utf8 NOT NULL,
        `description` text CHARACTER SET utf8 NOT NULL,
        `status` varchar(500) NOT NULL,
        `max_res` int(11) NOT NULL,
        `accepted` int(10) NOT NULL default '0',
        `uid` int(10) NOT NULL,
        PRIMARY KEY (`rid`)
      ) ENGINE=MyISAM" . $db->build_create_table_collation());

    $db->query("ALTER TABLE `" . TABLE_PREFIX . "users` ADD `rid` int(10) NOT NULL;");
    $db->query("ALTER TABLE `" . TABLE_PREFIX . "users` ADD `r_level` varchar(500) CHARACTER SET utf8 NOT NULL;");

    $db->add_column("usergroups", "canaddplace", "tinyint NOT NULL default '0'");

    $cache->update_usergroups();

    /*
     * nun kommen die Einstellungen
     */
    $setting_group = array(
        'name' => 'residences',
        'title' => 'Einstellungen für Wer wohnt wo?',
        'description' => 'Hier kannst du alle Einstellungen für das Wer wohnt wo? vornehmen.',
        'disporder' => 5, // The order your setting group will display
        'isdefault' => 0
    );

    $gid = $db->insert_query("settinggroups", $setting_group);

    $setting_array = array(
        // A text setting
        'r_places' => array(
            'title' => 'Örtlichkeiten',
            'description' => 'Wo können alles eure Orte liegen? Egal ob ganze Länder, Städte oder Stadtbereiche:',
            'optionscode' => 'textarea',
            'value' => 'Place1, Place2, Place3', // Default
            'disporder' => 1
        ),
        // A select box
        'r_status' => array(
            'title' => 'Angabe von Einkommensschichten',
            'description' => 'Sollen die User angeben können, zu welcher Schicht der Wohnort gehört?',
            'optionscode' => 'yesno',
            'value' => 1,
            'disporder' => 2
        ),
        // A yes/no boolean box
        'r_status_kind' => array(
            'title' => 'Arten von Einkommensschichten ',
            'description' => 'Welche Einkommensschichten soll man auswählen können?',
            'optionscode' => 'textarea',
            'value' => 'Oberschicht, obere Mittelschicht, Mittelschicht, Unterschicht',
            'disporder' => 3
        ),
    );

    foreach ($setting_array as $name => $setting) {
        $setting['name'] = $name;
        $setting['gid'] = $gid;

        $db->insert_query('settings', $setting);
    }

    // Don't forget this!
    rebuild_settings();

// templates
$insert_array = array(
    'title' => 'residences',
    'template' => $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->residences}</title>
{$headerinclude}
</head>
<body>
{$header}
<table class="tborder" border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}">
	<tr><td class="thead"><strong>{$lang->residences}</strong></td></tr>
<tr>
		<td class="trow1" valign="top" width="10%">
			<div id="residences">
			<div class="tab">
			<button class="tablinks" onclick="openResidences(event, \'main\')" id="defaultOpen">{$lang->residences_main}</button>
{$places_menu}
			</div>
<div id="main" class="tabcontent">
	{$open_resi}
{$add_place}
	{$add_residence}
			</div>
			{$residences_tabcontent}
			</div></td>
</tr>
</table>
{$footer}
</body>
</html>
<script>
function openResidences(evt, Residences) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(Residences).style.display = "block";
  evt.currentTarget.className += " active";
}

// Get the element with id="defaultOpen" and click on it
document.getElementById("defaultOpen").click();
</script>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);


$insert_array = array(
    'title' => 'residences_add_openalert',
    'template' => $db->escape_string('<div class="red_alert">
	{$open_alert}
</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_add_place',
    'template' => $db->escape_string('{$open_places}
<form action="misc.php?action=residences" method="post" id="add_place">
	<table width="80%" style="margin: auto; text-align: center;" align="center" cellpadding="5">
		<tr><td class="thead" colspan="2"><strong>{$lang->residences_add_place}</strong></td></tr>
		<tr>
			<td class="tcat"><strong>{$lang->residences_add_newplace}</strong>
				<div class="smalltext">{$lang->residences_add_newplace_desc}</div></td>
			<td class="tcat"><strong>{$lang->residences_add_newcountry}</strong>
				<div class="smalltext">{$lang->residences_add_newcountry_desc}</div></td>
		</tr>
	<tr>
		<td class="trow1"><input class="textbox" style="width: 80%;" id="place" name="place" placeholder="Straßenname, Stadtteil etc."></td>
		<td class="trow2"><select name="country">
			<option value="%">{$lang->residences_add_place_select}</option>
			{$form_places}
		</tr>
					<td class="trow1" colspan="2">
				<input type="submit"  name="add_place" id="add_place" class="buttom" value="{$lang->residences_add_place}">
			</td>
	</table>
</form>
<br />'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_add_residence',
    'template' => $db->escape_string('<form action="misc.php?action=residences" method="post" id="add_residence">
	<table width="80%" style="margin: auto; text-align: center;" align="center" cellpadding="5">
		<tr><td class="thead" colspan="2"><strong>{$lang->residences_add_residence}</strong></td></tr>
		<tr>
			<td class="tcat"><strong>{$lang->residences_add_residence_street}</strong>
				<div class="smalltext">{$lang->residences_add_residence_street_desc}</div></td>
			<td class="tcat"><strong>{$lang->residences_add_residence_housenumber}</strong>
	</td>
		</tr>
	<tr>
		<td class="trow1"><input type="text" class="textbox" style="width: 80%;" id="street" name="street" placeholder="Straßenname, Stadtteil etc."></td>
	<td class="trow1"><input type="number" class="textbox" style="width: 80%;" id="housenumber" name="housenumber" placeholder="00"></td>
			
		</tr>
					<tr>
			<td class="tcat"><strong>{$lang->residences_add_residence_place}</strong>
				<div class="smalltext">{$lang->residences_add_residence_place_desc}</div></td>
	
			<td class="tcat"><strong>{$lang->residences_add_maxres}</strong>
				<div class="smalltext">{$lang->residences_add_maxres_desc}</div></td>
		</tr>
	<tr>
		<tr>				
					<td class="trow2"><select name="place">
			<option value="%">{$lang->residence_add_residence_select}</option>
			{$select_place}
			</select></td>
				<td class="trow1"><input type="number" class="textbox" style="width: 80%;" id="max_res" name="max_res" placeholder="00"></td>
			</tr>
				<td class="tcat" colspan="2">
<strong>{$lang->residences_add_desc}</strong>
				<div class="smalltext">{$lang->residences_add_desc_desc}</div>
			</td>	</tr>
		<tr>
				<td class="trow1" colspan="2">
	<textarea class="textarea"  name="description" id="description" rows="5" cols="15" style="width: 100%; margin: auto;"></textarea> 
			</td></tr>
		<tr>
					<td class="trow1" colspan="2">
				<input type="submit"  name="add_residence" id="add_residence" class="buttom" value="{$lang->residences_residence_send}">
			</td></tr>
	</table>
</form>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_add_residence_status',
    'template' => $db->escape_string('<form action="misc.php?action=residences" method="post" id="add_residence">
	<table width="80%" style="margin: auto; text-align: center;" align="center" cellpadding="5">
		<tr><td class="thead" colspan="3"><strong>{$lang->residences_add_residence}</strong></td></tr>
		<tr>
			<td class="tcat" colspan="2"><strong>{$lang->residences_add_residence_street}</strong>
				<div class="smalltext">{$lang->residences_add_residence_street_desc}</div></td>	
			<td class="tcat" width="33%"><strong>{$lang->residences_add_residence_housenumber}</strong></td>
		</tr>
	<tr>
		<td class="trow1" colspan="2"><input type="text" class="textbox" style="width: 80%;" id="street" name="street" placeholder="Straßenname, Stadtteil etc."></td>
	<td class="trow1"><input type="number" class="textbox" style="width: 80%;" id="housenumber" name="housenumber" placeholder="00"></td>
		</tr>
					<tr>
					<td class="tcat"><strong>{$lang->residences_add_residence_place}</strong>
				<div class="smalltext">{$lang->residences_add_residence_place_desc}</div></td>
		
			<td class="tcat" width="33%"><strong>{$lang->residences_add_maxres}</strong>
				<div class="smalltext">{$lang->residences_add_maxres_desc}</div></td>
									<td class="tcat" width="33%"><strong>{$lang->residences_add_status}</strong>
				<div class="smalltext">{$lang->residences_add_status_desc}</div></td>
		</tr>
	<tr>
		<tr>
					<td class="trow2"><select name="place">
			<option value="%">{$lang->residence_add_residence_select}</option>
			{$select_place}
			</select>
		</td>	
				<td class="trow1"><input type="number" class="textbox" style="width: 80%;" id="max_res" name="max_res" placeholder="00"></td>
				<td class="trow2"><select name="status">
			<option value="%">{$lang->residence_add_residence_status_select}</option>
			{$select_status}
			</select>
		</td>
			</tr>
				<td class="tcat" colspan="3">
<strong>{$lang->residences_add_desc}</strong>
				<div class="smalltext">{$lang->residences_add_desc_desc}</div>
			</td>	
		</tr>
		<tr>
				<td class="trow1" colspan="3">
	<textarea class="textarea"  name="description" id="description" rows="5" cols="15" style="width: 100%; margin: auto;"></textarea> 
			</td></tr>
		<tr>
					<td class="trow1" colspan="3">
				<input type="submit" class="textbox" name="add_residence" id="add_residence" class="buttom" value="{$lang->residences_residence_send}">
			</td>
	</table>
</form>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_alert',
    'template' => $db->escape_string('<div class="red_alert">
	<strong>	{$alert}</strong>
</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_header',
    'template' => $db->escape_string('<li><a href="{$mybb->settings[\'bburl\']}/misc.php?action=residences" class="search">{$lang->residences_menu}</a></li>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_modcp',
    'template' => $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->residences_modcp}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
	<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" align="center" colspan="2"><strong>{$lang->residences_modcp}</strong></td>
</tr>
		<tr>
			<td>
				<table width="100%">
<tr>
<td class="tcat" colspan="2">
	<strong>{$lang->residences_modcp_place}</strong>
	</td>
</tr>
		{$places_bit}
		<tr>
				</table>
				<br />
				<table width="100%">
<td class="tcat" colspan="2">
	<strong>{$lang->residences_modcp_residence}</strong>
	</td>
</tr>
		{$residences_bit}
		</table>
	</td>
	</tr>
</table>
	</td>
	</tr>
	</table>
{$footer}
</body>
</html>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_modcp_nav',
    'template' => $db->escape_string('<tr><td class="trow1 smalltext"><a href="modcp.php?action=residences" class="modcp_nav_item modcp_nav_ipsearch">{$lang->residences_mcp_nav_residence}</a></td></tr>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_modcp_places',
    'template' => $db->escape_string('<tr>
	<td class="trow1" align="center" width="50%">
		<strong>{$place}</strong>
		<div class="smalltext">in {$country}</div>
			<div class="smalltext">{$creator}</div>
	</td>
	<td class="trow2" align="center" width="50%">
		{$acceptplace} // {$denyplace}
</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_modcp_residences',
    'template' => $db->escape_string('<tr><td class="trow1" colspan="2" align="center">		<strong>{$street} {$housenumber}</strong>
	<div class="smalltext">{$creator}</div>
	</td></tr>
<tr>
	<td class="trow1" align="center" width="50%">
		<div class="smalltext">{$max_res} {$status} {$res_place}</div>
		<div class="residence_desc">
			{$description}
		</div>
	</td>
	<td class="trow2" align="center" width="50%">
		{$accepthome} 
		<form action="modcp.php?action=residences" method="post">
			<input type="hidden" value="{$rid}" name="rid">
			<textarea name="denyreason" placeholder="Hier kannst du den Grund eintragen, wieso die Residenz nicht akzeptiert wurde." style="width: 80%; height: 50px;"></textarea>
			<input type="submit" name="denyhome" value="Residenz ablehnen" class="buttom">
		</form>
</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_movein',
    'template' => $db->escape_string('<a onclick="$(\'#movein_{$rid}\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">{$lang->residences_movein}</a>	<div class="modal" id="movein_{$rid}" style="display: none;">
	<form action="misc.php?action=residences" method="post" id="movein">
<table style="margin: 10px;" >
	<tr>
		<td class="thead">
			{$moveinstreet}
		</td>
	</tr>
	<tr>
		<td class="trow1" align="center">
		<input type="hidden" value="{$rid}" name="rid">
			<div class="smalltext" style="margin: 10px auto;">	{$lang->residences_moveinlevel}		</div>
			<input type="text" placeholder="1. Stock, Erdgeschoss etc." name="r_level"  class="textbox" id="r_level" style="width: 80%;">
				<div><input type="submit" name="movein" id="movein" class="buttom" value="{$lang->residences_movein}"></div>
		</td>
	</tr>
		</table>
	</form>
</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_options',
    'template' => $db->escape_string('<a href="misc.php?action=residences&deletehome={$rid}">{$lang->residences_deletehome}</a> // <a onclick="$(\'#edithome_{$rid}\').modal({ fadeDuration: 250, keepelement: true, zIndex: (typeof modal_zindex !== \'undefined\' ? modal_zindex : 9999) }); return false;" style="cursor: pointer;">{$lang->residences_edithome}</a>	<div class="modal" id="edithome_{$rid}" style="display: none;">
	<form action="misc.php?action=residences" method="post" id="edithome"><input type="hidden" value="{$rid}" name="rid">
<table style="margin: 10px;" >
<tr><td class="thead" colspan="2"><strong>{$lang->residences_edithome}</strong></td></tr>
		<tr>
			<td class="tcat"><strong>{$lang->residences_add_residence_street}</strong>
				<div class="smalltext">{$lang->residences_add_residence_street_desc}</div></td>
				<td class="trow1"><input type="text" class="textbox" style="width: 80%;" id="street" name="street" value="{$street}"></td>
	</tr>
	<tr>
						<td class="tcat"><strong>{$lang->residences_add_residence_housenumber}</strong></td>
								<td class="trow1"><input type="number" class="textbox" style="width: 80%;" id="housenumber" name="housenumber" value="{$housenumber}"></td>
					</tr>
	<tr>
				<td class="tcat"><strong>{$lang->residences_add_residence_place}</strong>
				<div class="smalltext">{$lang->residences_add_residence_place_desc}</div></td>
				<td class="trow2"><select name="place">
			<option value="%">{$lang->residence_add_residence_select}</option>
			{$select_place}
		</tr>
				
				<tr>
						<td class="tcat"><strong>{$lang->residences_add_maxres}</strong>
				<div class="smalltext">{$lang->residences_add_maxres_desc}</div></td>
								<td class="trow1"><input type="number" class="textbox" style="width: 80%;" id="max_res" name="max_res" value="{$max_res}"></td>
					</tr>
					{$residence_options_status}
					<tr>
							<td class="tcat" colspan="2">
<strong>{$lang->residences_add_desc}</strong>
				<div class="smalltext">{$lang->residences_add_desc_desc}</div>
			</td>	
					</tr>
					
					<tr>
				<td class="trow1" colspan="2">
	<textarea class="textarea"  name="description" id="description" rows="5" cols="15" style="width: 100%; margin: auto;">{$row[\'description\']}</textarea> 
			</td></tr>
		<tr>
					<td class="trow1" colspan="2">
				<input type="submit" name="edithome" id="edithome" class="buttom" value="{$lang->residences_edithome}">
			</td>
			</tr>	
		</table>
	</form>
</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_options_status',
    'template' => $db->escape_string('<tr>
			<td class="tcat" width="33%"><strong>{$lang->residences_add_status}</strong>
				<div class="smalltext">{$lang->residences_add_status_desc}</div></td>
					<td class="trow2"><select name="status">
			<option value="%">{$lang->residence_add_residence_status_select}</option>
			{$select_status}
			</select>
		</td>
</tr>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_places',
    'template' => $db->escape_string('<div class="tcat"><strong>{$place}</strong></div>
<div class="residence_flex">
	{$residences_residence}
</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_residence',
    'template' => $db->escape_string('<div class="residence">
	<div class="thead">
		<strong>{$street} {$housenumber}</strong>
		{$status}
	</div>
	<div class="residence_desc">
		{$desc}
	</div>
	<div class="tcat">{$count_resi}</div>

	<div class="residence_resident">
		{$residences_resident}
	</div>
	<div class="residence_options">
		{$movein} 
	</div>
		<div class="residence_options">
 {$residence_options}
	</div>
</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_resident',
    'template' => $db->escape_string('<div>&raquo; {$resident} {$moveout}</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);

$insert_array = array(
    'title' => 'residences_tabcontent',
    'template' => $db->escape_string('<div id="{$country}" class="tabcontent">
	{$residences_places}
</div>'),
    'sid' => '-1',
    'version' => '',
    'dateline' => TIME_NOW
);
$db->insert_query("templates", $insert_array);
//CSS einfügen
$css = array(
    'name' => 'residences.css',
    'tid' => 1,
    'attachedto' => '',
    "stylesheet" => '#residences {
	display: flex;
	justify-content: space-between;
}

/* Style the tab */
#residences .tab {
  overflow: hidden;
	display: flex;
	 flex-flow: column wrap;
}

/* Style the buttons inside the tab */
#residences .tab button {
  background-color: inherit;
  border: none;
  outline: none;
  cursor: pointer;
  padding: 14px 16px;
  transition: 0.3s;
  font-size: 12px;
}

/* Change background color of buttons on hover */
#residences .tab button:hover {
  background-color: #ddd;
}

/* Create an active/current tablink class */
#residences .tab button.active {
  background-color: #ccc;
}

/* Style the tab content */
#residences .tabcontent {
  display: none;
  padding: 6px 12px;
	box-sizing: border-box;
	width: 100%;
  animation: fadeEffect 1s; /* Fading effect takes 1 second */
}

/* Go from zero to full opacity */
@keyframes fadeEffect {
  from {opacity: 0;}
  to {opacity: 1;}
}

.residence_flex{
	display: flex;
	flex-wrap: wrap;
}

.residence{
width: 45%;
	margin: 10px;
}

.residence_desc{
	height: 100px;
	overflow: auto;
	padding: 5px;
	box-sizing: border-box;
	text-align: left;
}

.residence_options{
	padding: 2px;
	text-align: center;
}',
    'cachefile' => $db->escape_string(str_replace('/', '', 'residences.css')),
    'lastmodified' => time()
);

require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

$sid = $db->insert_query("themestylesheets", $css);
$db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);

$tids = $db->simple_select("themes", "tid");
while ($theme = $db->fetch_array($tids)) {
    update_theme_stylesheet_list($theme['tid']);
}

// Don't forget this!
rebuild_settings();
}

function residences_is_installed()
{
    global $db;
    if ($db->table_exists("places")) {
        return true;
    }
    return false;
}

function residences_uninstall()
{
    global $db, $cache;
    if ($db->table_exists("residences")) {
        $db->drop_table("residences");
    }

    if ($db->table_exists("places")) {
        $db->drop_table("places");
    }

    if ($db->field_exists("rid", "users")) {
        $db->drop_column("users", "rid");
    }
    if ($db->field_exists("r_level", "users")) {
        $db->drop_column("users", "r_level");
    }

    if ($db->field_exists("canaddplace", "usergroups")) {
        $db->drop_column("usergroups", "canaddplace");
    }

    $cache->update_usergroups();

    $db->delete_query('settings', "name IN ('r_places','r_status','r_status_kind')");
    $db->delete_query('settinggroups', "name = 'residences'");

    // Don't forget this
    rebuild_settings();
    $db->delete_query("templates", "title LIKE '%residences%'");
	// Don't forget this
	rebuild_settings();

	require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
	$db->delete_query("themestylesheets", "name = 'residences.css'");
	$query = $db->simple_select("themes", "tid");
	while ($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
		rebuild_settings();
	}

	// Don't forget this
	rebuild_settings();
}

function residences_activate()
{
    global $db, $cache;
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertTypeManager) {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }

        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertType->setCode('residences_accepthome'); // The codename for your alert type. Can be any unique string.
        $alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);

        $alertTypeManager->add($alertType);

        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertType->setCode('residences_acceptplace'); // The codename for your alert type. Can be any unique string.
        $alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);

        $alertTypeManager->add($alertType);

        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertType->setCode('residences_denyplace'); // The codename for your alert type. Can be any unique string.
        $alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);

        $alertTypeManager->add($alertType);
    }
	require MYBB_ROOT . "/inc/adminfunctions_templates.php";
	find_replace_templatesets("header", "#" . preg_quote('<navigation>') . "#i", '{$place_alert} {$residence_alert} <navigation>');
	find_replace_templatesets("header", "#" . preg_quote('{$menu_portal}') . "#i", '{$menu_residences}{$menu_portal}');
    find_replace_templatesets("member_profile", "#" . preg_quote('{$userstars}') . "#i", '{$residence}{$userstars}');
    find_replace_templatesets("modcp_nav_users", "#" . preg_quote('{$nav_ipsearch}') . "#i", '{$nav_ipsearch}{$nav_residences}');
    


}

function residences_deactivate()
{
    global $db, $cache;
    //Alertseinstellungen
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertTypeManager) {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }

        $alertTypeManager->deleteByCode('residences_accepthome');
        $alertTypeManager->deleteByCode('residences_acceptplace');
        $alertTypeManager->deleteByCode('residences_denyplace');
    }
	require MYBB_ROOT . "/inc/adminfunctions_templates.php";
	find_replace_templatesets("header", "#" . preg_quote('{$place_alert} {$residence_alert}') . "#i", '', 0);
	find_replace_templatesets("header", "#" . preg_quote('{$menu_residences}') . "#i", '', 0);
	find_replace_templatesets("member_profile", "#" . preg_quote('{$residence}') . "#i", '', 0);
	find_replace_templatesets("modcp_nav_users", "#" . preg_quote('{$nav_residences}') . "#i", '', 0);

}

function residences_settings_change()
{
    global $db, $mybb, $residences_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='residences'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $residences_settings_peeker = ($mybb->input['gid'] == $group['gid']) && ($mybb->request_method != 'post');
}
function residences_settings_peek(&$peekers)
{
    global $mybb, $residences_settings_peeker;

    if ($residences_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_r_status"), $("#row_setting_r_status_kind"),/1/,true)';

    }
}


function residences_usergroup_permission()
{
    global $mybb, $lang, $form, $form_container, $run_module;

    if ($run_module == 'user' && !empty($form_container->_title) & !empty($lang->misc) & $form_container->_title == $lang->misc) {
        $residences_options = array(
            $form->generate_check_box('canaddplace', 1, "Kann eine Örtlichkeit hinzufügen?", array("checked" => $mybb->input['canaddplace'])),
        );
        $form_container->output_row("Einstellung für Wer wohnt wo?", "", "<div class=\"group_settings_bit\">" . implode("</div><div class=\"group_settings_bit\">", $residences_options) . "</div>");
    }
}

function residences_usergroup_permission_commit()
{
    global $db, $mybb, $updated_group;
    $updated_group['canaddplace'] = $mybb->get_input('canaddplace', MyBB::INPUT_INT);
}


// Admin CP konfigurieren - 
//Action Handler erstellen


function residences_admin_config_action_handler(&$actions)
{
    $actions['residences'] = array('active' => 'residences', 'file' => 'residences');
}

//ACP Permissions - Berechtigungen für die Admins (über ACP einstellbar)

function residences_admin_config_permissions(&$admin_permissions)
{
    global $lang;
    $lang->load('residences');
    $admin_permissions['residences'] = $lang->residences_canadmin;
    return $admin_permissions;
}

function residences_admin_config_menu(&$sub_menu)
{
    global $lang;
    $lang->load('residences');

    $sub_menu[] = [
        "id" => "residences",
        "title" => $lang->residences_nav,
        "link" => "index.php?module=config-residences"
    ];
}

function admin_load_residences()
{
    global $mybb, $db, $lang, $page, $run_module, $action_file;
    $lang->load('residences');

    if ($page->active_action != 'residences') {
        return false;
    }

    if ($run_module == 'config' && $action_file == "residences") {
        if ($mybb->input['action'] == "" || !isset($mybb->input['action'])) {
            // Add a breadcrumb - Navigation Seite 
            $page->add_breadcrumb_item($lang->residences);

            //Header Auswahl Felder im Aufnahmestop verwalten Menü hinzufügen
            $page->output_header($lang->residences . " - " . $lang->residences_overview);

            //Übersichtsseite über alle Stops
            $sub_tabs['residences'] = [
                "title" => $lang->residences_overview,
                "link" => "index.php?module=config-residences",
                "description" => $lang->residences_overview_desc
            ];
            $sub_tabs['residences_add_place'] = [
                "title" => $lang->residences_add_place,
                "link" => "index.php?module=config-residences&amp;action=add_place",
                "description" => $lang->residences_add_place_desc
            ];
            $sub_tabs['residences_add_residence'] = [
                "title" => $lang->residences_add_residence,
                "link" => "index.php?module=config-residences&amp;action=add_residence",
                "description" => $lang->residences_add_residence_desc
            ];

            $page->output_nav_tabs($sub_tabs, 'residences');
            //Übersichtsseite erstellen 
            $form = new Form("index.php?module=config-residences", "post");
            $form_container = new FormContainer("<div style=\"text-align: center;\">$lang->residences_overview</div>");
            // alle unteren Hauptbereiche
            $form_container->output_row_header("<div style=\"text-align: center;\">$lang->residences_street</div>");
            $form_container->output_row_header("<div style=\"text-align: center;\">$lang->residences_desc</div>");

            if ($mybb->settings['r_status'] == 1) {
                $form_container->output_row_header("<div style=\"text-align: center;\">$lang->residences_status</div>");
            }
            $form_container->output_row_header("<div style=\"text-align: center;\">$lang->residences_residents</div>");
            $form_container->output_row_header("<div style=\"text-align: center;\">$lang->residences_accepted</div>");
            $form_container->output_row_header($lang->residences_options, array('style' => 'text-align: center; width: 10%;'));

            // geht all places
            $get_all_places = $db->simple_select("places", "*", "accepted = '1'", array(
                "order_by" => 'country',
                "order_dir" => 'ASC'
            ));

            while ($places = $db->fetch_array($get_all_places)) {

                if ($mybb->settings['r_status'] == 1) {
                    $form_container->output_cell('<div style="text-align: center; width: 100%;"><STRONG>' . htmlspecialchars_uni($places['place']) . '</strong> in <STRONG>' . htmlspecialchars_uni($places['country']) . '</strong></div>', array("colspan" => "5", "style" => "padding:10px;"));
                } else {
                    $form_container->output_cell('<div style="text-align: center; width: 100%;"><STRONG>' . htmlspecialchars_uni($places['place']) . '</strong> in <STRONG>' . htmlspecialchars_uni($places['country']) . '</strong></div>', array("colspan" => "4", "style" => "padding:10px;"));

                }

                $popup = new PopupMenu("residences_place_{$places['pid']}", $lang->residences_options);
                $popup->add_item(
                    $lang->residences_place_edit,
                    "index.php?module=config-residences&amp;action=edit_place&amp;pid={$places['pid']}"
                );
                $popup->add_item(
                    $lang->residences_place_delete,
                    "index.php?module=config-residences&amp;action=delete_place&amp;pid={$places['pid']}"
                    . "&amp;my_post_key={$mybb->post_code}"
                );
                $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                $form_container->construct_row();
                $pid = $places['pid'];
                $get_all_resi = $db->simple_select("residences", "*", "accepted = '1' and pid = '{$pid}'", array(
                    "order_by" => 'street',
                    "order_dir" => 'ASC'
                ));

                while ($home = $db->fetch_array($get_all_resi)) {
                    $form_container->output_cell('<strong>' . htmlspecialchars_uni($home['street']) . '</strong>', array("class" => "align_center", 'style' => 'width: 20%;'));
                    $form_container->output_cell("<div style='max-height: 100px; overflow: auto; text-align: justify; padding: 0 5px;'>" . htmlspecialchars_uni($home['description']) . "</div>", array('style' => 'width:35%; '));

                    if ($mybb->settings['r_status'] == 1) {
                        if (!empty($home['status'])) {
                            $form_container->output_cell(htmlspecialchars_uni($home['status']), array("class" => "align_center", 'style' => 'width: 20%;'));
                        } else {
                            $form_container->output_cell($lang->residences_no_status, array("class" => "align_center", 'style' => 'width: 20%;'));
                        }
                    }

                    $rid = $home['rid'];


                    $get_resi = $db->simple_select("users", "*", "rid = '{$rid}'", array(
                        "order_by" => 'username',
                        "order_dir" => 'ASC'
                    ));

                    $all_residents = "";
                    $residents = "";
                    $count = 0;

                    while ($row = $db->fetch_array($get_resi)) {
                        $count++;
                        $residents .= "<li>" . build_profile_link($row['username'], $row['uid']) . "</li>";
                    }

                    if ($count > 0) {
                        $count_res = "";
                        $count_res = $lang->sprintf($lang->residence_residents, $count, $home['max_res']);
                        $all_residents = "<div style='text-align: center;'><b>{$count_res}</b></div><ul>" . $residents . "</ul>";

                    } else {

                        $all_residents = "<div style='text-align: center;'>" . $lang->residence_no_resident . "</div>";
                    }

                    $form_container->output_cell($all_residents, array('style' => 'width: 20%;'));
                    if ($home['accepted'] == "0") {
                        $residence_status = "<img src='styles/default/images/icons/no_change.png' title='{$lang->residences_noaccept}'>";
                    } else {
                        $residence_status = "<img src='styles/default/images/icons/success.png' title='{$lang->residence_accepted}'>";
                    }

                    $form_container->output_cell('<div style="text-align: center;">' . $residence_status . '</div>', array("class" => "align_center", 'style' => 'width: 5%;'));

                    $popup = new PopupMenu("residences_residence_{$home['rid']}", $lang->residences_options);
                    $popup->add_item(
                        $lang->residences_residence_edit,
                        "index.php?module=config-residences&amp;action=edit_residence&amp;rid={$home['rid']}"
                    );
                    $popup->add_item(
                        $lang->residences_residence_delete,
                        "index.php?module=config-residences&amp;action=delete_residence&amp;rid={$home['rid']}"
                        . "&amp;my_post_key={$mybb->post_code}"
                    );
                    $form_container->output_cell($popup->fetch(), array("class" => "align_center"));

                    $form_container->construct_row();
                }



            }

            $form_container->end();
            $form->end();
            $page->output_footer();
            exit;
        }


        if ($mybb->input['action'] == "add_place") {
            // Eintragen
            if ($mybb->request_method == "post") {
                // Prüfe, ob alle erforderlichen Felder ausgefüllt wurden
                if (empty($mybb->input['country'])) {
                    $error[] = $lang->residences_error_country;
                }
                if (empty($mybb->input['place'])) {
                    $error[] = $lang->residences_error_place;
                }

                if (empty($error)) {
                    $country = $db->escape_string($mybb->input['country']);
                    $place = $db->escape_string($mybb->input['place']);

                    $new_place = array(
                        "country" => $country,
                        "place" => $place,
                        "accepted" => 1,
                        "uid" => $mybb->user['uid']
                    );

                    $db->insert_query("places", $new_place);

                    $mybb->input['module'] = "residences";
                    $mybb->input['action'] = $lang->residences_added_place;
                    log_admin_action(htmlspecialchars_uni($mybb->input['country']));

                    flash_message($lang->residences_added_place, 'success');
                    admin_redirect("index.php?module=config-residences");

                }
            }

            $page->add_breadcrumb_item($lang->residences_add_place);

            // Build options header
            $page->output_header($lang->residences . " - " . $lang->residences_overview);


            $sub_tabs['residences'] = [
                "title" => $lang->residences_overview,
                "link" => "index.php?module=config-residences",
                "description" => $lang->residences_overview_desc
            ];
            $sub_tabs['residences_add_place'] = [
                "title" => $lang->residences_add_place,
                "link" => "index.php?module=config-residences&amp;action=add_place",
                "description" => $lang->residences_add_place_desc
            ];
            $sub_tabs['residences_add_residence'] = [
                "title" => $lang->residences_add_residence,
                "link" => "index.php?module=config-residences&amp;action=add_residence",
                "description" => $lang->residences_add_residence_desc
            ];


            $page->output_nav_tabs($sub_tabs, 'residences_add_place');


            // Erstellen der "Formulareinträge"
            $form = new Form("index.php?module=config-residences&amp;action=add_place", "post", "", 1);
            $form_container = new FormContainer($lang->residences_add_place_title);

            $form_container->output_row(
                $lang->residences_add_newplace . " <em>*</em>",
                $lang->residences_add_newplace_desc,
                $form->generate_text_box('place', isset($mybb->input['place']))
            );

            $options = array();
            //Wenn es welche gibt: 
            $countries = str_replace(", ", ",", $mybb->settings['r_places']);
            $countries = explode(",", $countries);
            asort($countries);
            foreach ($countries as $country) {
                $options[$country] = $country;
            }
            $form_container->output_row(
                $lang->residences_add_newcountry . " <em>*</em>",
                $lang->residences_add_newcountry_desc,
                $form->generate_select_box(
                    'country',
                    $options,
                    array($mybb->get_input('country', MyBB::INPUT_INT)),
                    array('id' => 'country')
                ),
                'country'
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->residences_place_send);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();

            exit;
        }

        // Örtlichkeit bearbeiten
        if ($mybb->input['action'] == "edit_place") {
            // Eintragen
            if ($mybb->request_method == "post") {
                // Prüfe, ob alle erforderlichen Felder ausgefüllt wurden
                if (empty($mybb->input['country'])) {
                    $error[] = $lang->residences_error_country;
                }
                if (empty($mybb->input['place'])) {
                    $error[] = $lang->residences_error_place;
                }

                if (empty($error)) {
                    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
                    $country = $db->escape_string($mybb->input['country']);
                    $place = $db->escape_string($mybb->input['place']);

                    $edit_place = array(
                        "country" => $country,
                        "place" => $place
                    );

                    $db->update_query("places", $edit_place, "pid = '{$pid}'");

                    $mybb->input['module'] = "residences";
                    $mybb->input['action'] = $lang->residences_edit_place_success;
                    log_admin_action(htmlspecialchars_uni($mybb->input['country']));

                    flash_message($lang->residences_edit_place_success, 'success');
                    admin_redirect("index.php?module=config-residences");

                }
            }

            $page->add_breadcrumb_item($lang->residences_edit_place);

            // Build options header
            $page->output_header($lang->residences . " - " . $lang->residences_overview);

            $sub_tabs['residences'] = [
                "title" => $lang->residences_overview,
                "link" => "index.php?module=config-residences",
                "description" => $lang->residences_overview_desc
            ];
            $sub_tabs['residences_add_place'] = [
                "title" => $lang->residences_add_place,
                "link" => "index.php?module=config-residences&amp;action=add_place",
                "description" => $lang->residences_add_place_desc
            ];
            $sub_tabs['residences_add_residence'] = [
                "title" => $lang->residences_add_residence,
                "link" => "index.php?module=config-residences&amp;action=add_residence",
                "description" => $lang->residences_add_residence_desc
            ];

            $page->output_nav_tabs($sub_tabs, 'residences_edit_place');

            $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
            $query = $db->simple_select("places", "*", "pid={$pid}");
            $edit_place = $db->fetch_array($query);


            // Erstellen der "Formulareinträge"
            $form = new Form("index.php?module=config-residences&amp;action=edit_place", "post", "", 1);
            $form_container = new FormContainer($lang->residences_edit_place);
            echo $form->generate_hidden_field('pid', $pid);

            $form_container->output_row(
                $lang->residences_add_newplace . " <em>*</em>",
                $lang->residences_add_newplace_desc,
                $form->generate_text_box('place', $edit_place['place'])
            );

            $options = array();
            //Wenn es welche gibt: 
            $countries = str_replace(", ", ",", $mybb->settings['r_places']);
            $countries = explode(",", $countries);
            asort($countries);
            foreach ($countries as $country) {
                $options[$country] = $country;
            }
            $form_container->output_row(
                $lang->residences_add_newcountry . " <em>*</em>",
                $lang->residences_add_newcountry_desc,
                $form->generate_select_box(
                    'country',
                    $options,
                    array($edit_place['country']),
                    array('id' => 'country')
                ),
                'country'
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->residences_edit_place_send);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();

            exit;
        }


        // Örtlichkeit löschen
        if ($mybb->input['action'] == "delete_place") {
            $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
            $query = $db->simple_select("places", "*", "pid={$pid}");
            $delete_place = $db->fetch_array($query);

            if (empty($pid)) {
                flash_message($lang->residences_error_option, 'error');
                admin_redirect("index.php?module=config-residences");

            }
            // Cancel button pressed?
            if (isset($mybb->input['no']) && $mybb->input['no']) {
                admin_redirect("index.php?module=config-residences");
            }

            if (!verify_post_check($mybb->input['my_post_key'])) {
                flash_message($lang->invalid_post_verify_key2, 'error');
                admin_redirect("index.php?module=config-residences");
            } else {
                if ($mybb->request_method == "post") {

                    $db->delete_query("places", "pid='{$pid}'");

                    $mybb->input['module'] = "residences";
                    $mybb->input['action'] = $lang->residences_delete_place_solved;
                    log_admin_action(htmlspecialchars_uni($delete_place['pid']));

                    flash_message($lang->residences_delete_place_solved, 'success');
                    admin_redirect("index.php?module=config-residences");
                } else {

                    $page->output_confirm_action(
                        "index.php?module=config-residences&amp;action=delete_place&amp;pid={$pid}",
                        $lang->residences_delete_place_question
                    );
                }
            }
        }


        if ($mybb->input['action'] == "add_residence") {
            // Eintragen
            if ($mybb->request_method == "post") {
                // Prüfe, ob alle erforderlichen Felder ausgefüllt wurden
                if (empty($mybb->input['street'])) {
                    $error[] = $lang->residences_error_street;
                }
                if (empty($mybb->input['pid'])) {
                    $error[] = $lang->residences_error_place;
                }
                if (empty($mybb->input['description'])) {
                    $error[] = $lang->residences_error_desc;
                }

                if (empty($error)) {
                    $pid = (int) $mybb->input['pid'];
                    $street = $db->escape_string($mybb->input['street']);
           
                    $desc = $db->escape_string($mybb->input['description']);
                    $status = $db->escape_string($mybb->input['status']);
                    $max_res = (int) $mybb->input['max_res'];

                    $new_residence = array(
                        "pid" => $pid,
                        "street" => $street,
                        "description" => $desc,
                        "status" => $status,
                        "max_res" => $max_res,
                        "accepted" => 1,
                        "uid" => $mybb->user['uid']
                    );

                    $db->insert_query("residences", $new_residence);

                    $mybb->input['module'] = "residences";
                    $mybb->input['action'] = $lang->residences_add_residence_solved;
                    log_admin_action(htmlspecialchars_uni($mybb->input['street']));

                    flash_message($lang->residences_add_residence_solved, 'success');
                    admin_redirect("index.php?module=config-residences");
                }
            }
            $page->add_breadcrumb_item($lang->residences_add_residence);

            // Build options header
            $page->output_header($lang->residences . " - " . $lang->residences_overview);


            $sub_tabs['residences'] = [
                "title" => $lang->residences_overview,
                "link" => "index.php?module=config-residences",
                "description" => $lang->residences_overview_desc
            ];
            $sub_tabs['residences_add_place'] = [
                "title" => $lang->residences_add_place,
                "link" => "index.php?module=config-residences&amp;action=add_place",
                "description" => $lang->residences_add_place_desc
            ];
            $sub_tabs['residences_add_residence'] = [
                "title" => $lang->residences_add_residence,
                "link" => "index.php?module=config-residences&amp;action=add_residence",
                "description" => $lang->residences_add_residence_desc
            ];

            $page->output_nav_tabs($sub_tabs, 'residences_add_residence');


            // Erstellen der "Formulareinträge"
            $form = new Form("index.php?module=config-residences&amp;action=add_residence", "post", "", 1);
            $form_container = new FormContainer($lang->residences_add_residence_title);
            $query = $db->query("SELECT * 
            FROM " . TABLE_PREFIX . "places 
            ORDER BY place ASC");

            $options = array();
            //Wenn es welche gibt: 
            if (mysqli_num_rows($query) > 0) {
                while ($output = $db->fetch_array($query)) {
                    $options[$output['pid']] = $output['place'];
                }
                $form_container->output_row(
                    $lang->residences_add_residence_place . " <em>*</em>",
                    $lang->residences_add_residence_place_desc,
                    $form->generate_select_box('pid', $options, array($mybb->get_input('pid', MyBB::INPUT_INT)), array('id' => 'pid')),
                    'pid'
                );
            }


            $form_container->output_row(
                $lang->residences_add_residence_street . " <em>*</em>",
                $lang->residences_add_residence_street_desc,
                $form->generate_text_box('street', isset($mybb->input['street']))
            );

            $form_container->output_row(
                $lang->residences_add_maxres . " <em>*</em>",
                $lang->residences_add_maxres_desc,
                $form->generate_numeric_field('max_res', isset($mybb->input['max_res']))
            );



            if ($mybb->settings['r_status'] == 1) {
                $options = array();
                //Wenn es welche gibt: 
                $stati = str_replace(", ", ",", $mybb->settings['r_status_kind']);
                $stati = explode(",", $stati);
                foreach ($stati as $status) {
                    $options[$status] = $status;
                }
                $form_container->output_row(
                    $lang->residences_add_status . " <em>*</em>",
                    $lang->residences_add_status_desc,
                    $form->generate_select_box(
                        'status',
                        $options,
                        array($mybb->get_input('status', MyBB::INPUT_INT)),
                        array('id' => 'status')
                    ),
                    'status'
                );
            }


            $form_container->output_row(
                $lang->residences_add_desc . " <em>*</em>",
                $lang->residences_add_desc_desc,
                $form->generate_text_area('description', isset($mybb->input['description']))
            );


            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->residences_residence_send);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();

            exit;
        }


        if ($mybb->input['action'] == "edit_residence") {
            // Eintragen
            if ($mybb->request_method == "post") {
                // Prüfe, ob alle erforderlichen Felder ausgefüllt wurden
                if (empty($mybb->input['street'])) {
                    $error[] = $lang->residences_error_street;
                }
                if (empty($mybb->input['pid'])) {
                    $error[] = $lang->residences_error_place;
                }
                if (empty($mybb->input['description'])) {
                    $error[] = $lang->residences_error_desc;
                }

                if (empty($error)) {
                    $rid = $mybb->get_input('rid', MyBB::INPUT_INT);
                    $pid = (int) $mybb->input['pid'];
                    $street = $db->escape_string($mybb->input['street']);
             
                    $desc = $db->escape_string($mybb->input['description']);
                    $status = $db->escape_string($mybb->input['status']);
                    $max_res = (int) $mybb->input['max_res'];
                    $uid = (int) $mybb->input['uid'];

                    $edit_residence = array(
                        "pid" => $pid,
                        "street" => $street,
                   
                        "description" => $desc,
                        "status" => $status,
                        "max_res" => $max_res,
                        "accepted" => 1,
                        "uid" => $uid
                    );

                    $db->update_query("residences", $edit_residence, "rid = '{$rid}'");

                    $mybb->input['module'] = "residences";
                    $mybb->input['action'] = $lang->residences_edit_residence_solved;
                    log_admin_action(htmlspecialchars_uni($mybb->input['street']));

                    flash_message($lang->residences_edit_residence_solved, 'success');
                    admin_redirect("index.php?module=config-residences");
                }
            }

            $page->add_breadcrumb_item($lang->residences_edit_residence);

            // Build options header
            $page->output_header($lang->residences . " - " . $lang->residences_overview);


            //Übersichtsseite über alle Stops

            $sub_tabs['residences'] = [
                "title" => $lang->residences_overview,
                "link" => "index.php?module=config-residences",
                "description" => $lang->residences_overview_desc
            ];
            $sub_tabs['residences_add_place'] = [
                "title" => $lang->residences_add_place,
                "link" => "index.php?module=config-residences&amp;action=add_place",
                "description" => $lang->residences_add_place_desc
            ];
            $sub_tabs['residences_add_residence'] = [
                "title" => $lang->residences_add_residence,
                "link" => "index.php?module=config-residences&amp;action=add_residence",
                "description" => $lang->residences_add_residence_desc
            ];
            $sub_tabs['residences_edit_residence'] = [
                "title" => $lang->residences_edit_residence,
                "link" => "index.php?module=config-residences&amp;action=edit_residence",
                "description" => $lang->residences_edit_residence_desc
            ];
            $page->output_nav_tabs($sub_tabs, 'residences_edit_residence');


            // Erstellen der "Formulareinträge"
            $form = new Form("index.php?module=config-residences&amp;action=edit_residence", "post", "", 1);
            $form_container = new FormContainer($lang->residences_edit_residence);
            $rid = $mybb->get_input('rid', MyBB::INPUT_INT);
            $query = $db->simple_select("residences", "*", "rid={$rid}");
            $edit_residences = $db->fetch_array($query);
            echo $form->generate_hidden_field('rid', $rid);
            $query = $db->query("SELECT * 
            FROM " . TABLE_PREFIX . "places 
            ORDER BY place ASC");

            $options = array();
            //Wenn es welche gibt: 
            if (mysqli_num_rows($query) > 0) {
                while ($output = $db->fetch_array($query)) {
                    $options[$output['pid']] = $output['place'];
                }
            }
            $form_container->output_row(
                $lang->residences_add_residence_place . " <em>*</em>",
                $lang->residences_add_residence_place_desc,
                $form->generate_select_box('pid', $options, $edit_residences['pid'], array($mybb->get_input('pid', MyBB::INPUT_INT)), array('id' => 'pid')),
                'pid'
            );

            $form_container->output_row(
                $lang->residences_add_residence_street . " <em>*</em>",
                $lang->residences_add_residence_street_desc,
                $form->generate_text_box('street', $edit_residences['street'])
            );
            $form_container->output_row(
                $lang->residences_add_maxres . " <em>*</em>",
                $lang->residences_add_maxres_desc,
                $form->generate_numeric_field('max_res', $edit_residences['max_res'])
            );


            if ($mybb->settings['r_status'] == 1) {
                $options = array();
                //Wenn es welche gibt: 
                $stati = str_replace(", ", ",", $mybb->settings['r_status_kind']);
                $stati = explode(",", $stati);
                foreach ($stati as $status) {
                    $options[$status] = $status;
                }
                $form_container->output_row(
                    $lang->residences_add_status . " <em>*</em>",
                    $lang->residences_add_status_desc,
                    $form->generate_select_box(
                        'status',
                        $options,
                        $edit_residences['status'],
                        array($mybb->get_input('status', MyBB::INPUT_INT)),
                        array('id' => 'status')
                    ),
                    'status'
                );
            }


            $form_container->output_row(
                $lang->residences_add_desc . " <em>*</em>",
                $lang->residences_add_desc_desc,
                $form->generate_text_area('description', $edit_residences['description'])
            );

            $form_container->output_row(
                $lang->residences_owner,
                $lang->residences_owner_desc,
                $form->generate_numeric_field('uid', $edit_residences['uid'])
            );


            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->residences_edit_residence_send);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();

            exit;
        }

        // Örtlichkeit löschen
        if ($mybb->input['action'] == "delete_residence") {
            $rid = $mybb->get_input('rid', MyBB::INPUT_INT);
            $query = $db->simple_select("residences", "*", "rid={$rid}");
            $delete_residence = $db->fetch_array($query);

            if (empty($rid)) {
                flash_message($lang->residences_error_option, 'error');
                admin_redirect("index.php?module=config-residences");

            }
            // Cancel button pressed?
            if (isset($mybb->input['no']) && $mybb->input['no']) {
                admin_redirect("index.php?module=config-residences");
            }

            if (!verify_post_check($mybb->input['my_post_key'])) {
                flash_message($lang->invalid_post_verify_key2, 'error');
                admin_redirect("index.php?module=config-residences");
            } else {
                if ($mybb->request_method == "post") {

                    $db->delete_query("residences", "rid='{$rid}'");

                    $mybb->input['module'] = "residences";
                    $mybb->input['action'] = $lang->residences_delete_residence_solved;
                    log_admin_action(htmlspecialchars_uni($delete_residence['rid']));

                    flash_message($lang->residences_delete_residence_solved, 'success');
                    admin_redirect("index.php?module=config-residences");
                } else {

                    $page->output_confirm_action(
                        "index.php?module=config-residences&amp;action=delete_residence&amp;rid={$rid}",
                        $lang->residences_delete_residence_question
                    );
                }
            }
        }
    }

}

// globale Informationen wie Alert, Navigation etc.
function residences_global()
{
    global $db, $mybb, $templates, $lang, $menu_residences, $place_alert, $residence_alert;
    $lang->load("residences");
    eval ("\$menu_residences = \"" . $templates->get("residences_header") . "\";");

    // Alertanzeige, wenn es neue Wohnort gibt, die noch freigeschaltet werden müssen

    if ($mybb->usergroup['canmodcp'] == 1) {
        $count_openhome = 0;
        $alert = "";
        $residence_alert = "";
        $place_alert = "";
        $front = "";
        $count_openplace = $db->fetch_field($db->simple_select("places", "COUNT(*) as countplace", "accepted = 0"), 'countplace');

        if ($count_openplace > 0) {
            $place = "";
            if ($count_openplace == 1) {
                $front = "ist";
                $place = "Ort";
            } else {
                $front = "sind";
                $place = "Orte";
            }
            $alert = $lang->sprintf($lang->residences_alert, $front, $count_openplace, $place);
            eval ("\$place_alert = \"" . $templates->get("residences_alert") . "\";");
        }

        $count_openhome = $db->fetch_field($db->simple_select("residences", "COUNT(*) as counthome", "accepted = 0"), 'counthome');

        if ($count_openhome > 0) {
            $residence = "";
            if ($count_openhome == 1) {
                $front = "ist";
                $residence = "Residenz";
            } else {
                $front = "sind";
                $residence = "Residenzen";
            }
            $alert = $lang->sprintf($lang->residences_alert, $front, $count_openhome, $residence);
            eval ("\$residence_alert = \"" . $templates->get("residences_alert") . "\";");
        }


    }

}

function residences_misc()
{
    global $mybb, $templates, $lang, $header, $headerinclude, $footer, $select_status, $parser, $places_menu, $add_place, $moveout, $movein, $add_residence, $db, $open_alert, $select_place, $residences_places, $residences_resident, $count_resi;
    $lang->load("residences");
    require_once MYBB_ROOT . "inc/class_parser.php";

    $parser = new postParser;
    // Do something, for example I'll create a page using the hello_world_template
    $options = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );

    // Einstellungen
    $all_places = $mybb->settings['r_places'];
    $withstatus = $mybb->settings['r_status'];
    $status_kind = $mybb->settings['r_status_kind'];

    if ($mybb->get_input('action') == 'residences') {
        // Do something, for example I'll create a page using the hello_world_template

        // Add a breadcrumb
        add_breadcrumb($lang->residences_nav, "misc.php?action=residences");

        // bau navigation auf
        $all_countries = str_replace(", ", ",", $all_places);
        $all_countries = explode(",", $all_countries);
        asort($all_countries);

        $residences_tabcontent = "";
        foreach ($all_countries as $country) {
            $places_menu .= "  <button class=\"tablinks\" onclick=\"openResidences(event, '{$country}')\">{$country}</button>";

            $get_all_places = $db->simple_select("places", "*", "accepted = 1 and country = '{$country}'");
            $residences_places = "";
            while ($places = $db->fetch_array($get_all_places)) {
                $place = "";
                $pid = 0;

                $place = $places['place'];
                $pid = $places['pid'];

                $get_all_resi = $db->query("SELECT *
                    from " . TABLE_PREFIX . "residences
                    where pid = '{$pid}'
                    and accepted = 1
                    ORDER BY street ASC
                ");

                $residences_residence = "";
                while ($row = $db->fetch_array($get_all_resi)) {
                    $street = "";
                
                    $max_res = 0;
                    $desc = "";
                    $rid = 0;
                    $cont_resi = "";
                    $movein = "";
                    $moveinstatus = "";
                    $moveinstreet = "";
                    $residence_options = "";
                    $rid = $row['rid'];
                    if ($withstatus == 1) {
                        $status = "";
                        $status = $lang->sprintf($lang->residences_status, $row['status']);
                    }

                    $street = $row['street'];
                   
                    $max_res = $row['max_res'];
                    $desc = $parser->parse_message($row['description'], $options);
                    $moveinstatus = "false";
                    // jetzt müssen wir natürlich noch unsere Bewohner auslesen, um sie auch anzeigen zu lassen
                    $get_resi = $db->simple_select("users", "*", "rid = {$rid}");
                    $count = 0;
                    $residences_resident = "";
                    while ($resi = $db->fetch_array($get_resi)) {
                        $resident = "";
                        $level = "";
                        $count++;
                        $moveout = "";

                        if (!empty($resi['r_level'])) {
                            $level = $lang->sprintf($lang->residences_level, $resi['r_level']);
                        }

                        if ($resi['uid'] == $mybb->user['uid']) {
                            $moveinstatus = "true";
                            $moveout = "<a href='misc.php?action=residences&moveout={$resi['uid']}' title='Ausziehen'>{$lang->residences_moveout}</a>";
                        }

                        if ($mybb->usergroup['canmodcp'] == 1) {
                            $moveout = "<a href='misc.php?action=residences&moveout={$resi['uid']}' title='Ausziehen'>{$lang->residences_moveout}</a>";
                        }

                        $username = format_name($resi['username'], $resi['usergroup'], $resi['displaygroup']);
                        $resident = build_profile_link($username, $resi['uid']);
                        $resident = $resident . $level;
                        eval ("\$residences_resident.= \"" . $templates->get("residences_resident") . "\";");
                    }
                    $count_resi = $lang->sprintf($lang->residences_count_resi, $count, $max_res);
                    if ($moveinstatus == "false" and $count < $max_res) {
                        $moveinstreet = $lang->sprintf($lang->residences_moveinstreet, $street);
                        eval ("\$movein = \"" . $templates->get("residences_movein") . "\";");
                    }

                    $resi_pid = $row['pid'];
                    // Bearbeiten der Residenz
                    if ($mybb->user['uid'] == $row['uid']) {
                        $edit_places= $db->simple_select("places", "*", "accepted = 1");

                        while ($get_place = $db->fetch_array($edit_places)) {
                            $allplace = "";
                            $pid = 0;
                            $selected = "";

                            $allplace = $get_place['place'] . " (" . $get_place['country'] . ")";
                            $pid = $get_place['pid'];
                            if ($pid == $resi_pid) {
                                $selected = "selected";
                            }
                            $select_place .= "<option value='{$pid}' {$selected}>{$allplace}</option>";
                        }

                        if ($withstatus == 1) {
                            $stati = str_replace(", ", ",", $mybb->settings['r_status_kind']);
                            $stati = explode(",", $stati);
                            foreach ($stati as $getstatus) {
                                $selected = "";

                                if ($row['status'] == $getstatus) {
                                    $selected = "selected";
                                }
                                $select_status .= "<option value='{$getstatus}'>{$getstatus}</option>";
                            }
                            eval ("\$residence_options_status = \"" . $templates->get("residences_options_status") . "\";");
                        }

                        eval ("\$residence_options = \"" . $templates->get("residences_options") . "\";");
                    }

                    eval ("\$residences_residence .= \"" . $templates->get("residences_residence") . "\";");
                }


                eval ("\$residences_places .= \"" . $templates->get("residences_places") . "\";");
            }

            eval ("\$residences_tabcontent .= \"" . $templates->get("residences_tabcontent") . "\";");

        }

        $uid = 0;
        $uid = $mybb->user['uid'];
        // Formulare
        if ($mybb->user['uid'] != 0) {
            $open_places = "";
            $open_resi = "";

            $open_alert = "";

            if ($mybb->usergroup['canaddplace'] == 1) {
                $get_openplaces = $db->fetch_field($db->simple_select("places", "COUNT(*) as openplaces", "accepted = '0' and uid = {$uid}"), 'openplaces');
                if ($get_openplaces > 0) {
                    $open_alert = $lang->residence_open_place;
                    eval ("\$open_places .= \"" . $templates->get("residences_add_openalert") . "\";");
                }
                
                foreach ($all_countries as $country) {
                    $form_places .= "<option value='{$country}'>{$country}</option>";
                }

                eval ("\$add_place = \"" . $templates->get("residences_add_place") . "\";");
            }
            $get_all_places = $db->simple_select("places", "*", "accepted = 1");
            $select_place = "";
            while ($get_place = $db->fetch_array($get_all_places)) {
                $place = "";
                $pid = 0;

                $place = $get_place['place'] . " (" . $get_place['country'] . ")";
                $pid = $get_place['pid'];

                $select_place .= "<option value='{$pid}'>{$place}</option>";
            }

            $get_openresidences = $db->fetch_field($db->simple_select("residences", "COUNT(*) as openresidences", "accepted = '0' and uid = {$uid}"), 'openresidences');
            if ($get_openresidences > 0) {
                $open_alert = $lang->residence_open_residence;
                eval ("\$open_resi .= \"" . $templates->get("residences_add_openalert") . "\";");
            }

            if ($withstatus == 1) {
                $select_status = "";
                $stati = str_replace(", ", ",", $mybb->settings['r_status_kind']);
                $stati = explode(",", $stati);
                foreach ($stati as $status) {
                    $select_status .= "<option value='{$status}'>{$status}</option>";
                }
                eval ("\$add_residence = \"" . $templates->get("residences_add_residence_status") . "\";");
            } else {
                eval ("\$add_residence = \"" . $templates->get("residences_add_residence") . "\";");
            }
        }


        // Örtlichkeit hinzufügen
        if (isset($_POST['add_place'])) {
            $add_place = array(
                'place' => $db->escape_string($_POST['place']),
                'country' => $db->escape_string($_POST['country']),
                'uid' => $uid
            );

            $db->insert_query("places", $add_place);
            redirect("misc.php?action=residences");
        }


        // Residenz hinzufügen
        if (isset($_POST['add_residence'])) {
            $add_residence = array(
                'pid' => (int) $_POST['place'],
                'street' => $db->escape_string($_POST['street']),
   
                'max_res' => (int) $_POST['max_res'],
                'status' => $db->escape_string($_POST['status']),
                'description' => $db->escape_string($_POST['description']),
                'uid' => (int) $uid
            );
            $db->insert_query("residences", $add_residence);
            redirect("misc.php?action=residences");
        }

        // Residenz ändern
        if (isset($mybb->input['edithome'])) {
            $rid = $mybb->input['rid'];

            $edit_residence = array(
                'pid' => (int) $mybb->input['place'],
                'street' => $db->escape_string($mybb->input['street']),
         
                'max_res' => (int) $mybb->input['max_res'],
                'status' => $db->escape_string($mybb->input['status']),
                'description' => $db->escape_string($mybb->input['description'])
            );

            $db->update_query("residences", $edit_residence, "rid = {$rid}");
            redirect("misc.php?action=residences");
        }

        if (isset($mybb->input['deletehome'])) {

            $rid = $mybb->input['deletehome'];

            $get_moveout = array(
                "rid" => 0,
                "r_level" => ""
            );

            $db->update_query("users", $get_moveout, "rid = {$rid}");
            $db->delete_query("residences", "rid= {$rid}");
            redirect("misc.php?action=residences");
        }
        // Einziehen

        if (isset($_POST['movein'])) {
            $rid = $_POST['rid'];
            $uid = $mybb->user['uid'];
            $get_movein = array(
                "rid" => $rid,
                "r_level" => $db->escape_string($_POST['r_level'])
            );

            $db->update_query("users", $get_movein, "uid = {$uid}");
            redirect("misc.php?action=residences");
        }

        // Ausziehen
        if (isset($mybb->input['moveout'])) {
            $uid = $mybb->input['moveout'];

            $get_moveout = array(
                "rid" => 0,
                "r_level" => ""
            );

            $db->update_query("users", $get_moveout, "uid = {$uid}");
            redirect("misc.php?action=residences");

        }

        // Using the misc_help template for the page wrapper
        eval ("\$page = \"" . $templates->get("residences") . "\";");
        output_page($page);
    }
}


// Modcp
function residences_modcp_nav()
{
    global $nav_residences, $templates, $lang;
    $lang->load('residences');
    eval ("\$nav_residences = \"" . $templates->get("residences_modcp_nav") . "\";");
}

function residences_modcp()
{
    global $mybb, $templates, $lang, $header, $headerinclude, $footer, $modcp_nav, $db, $theme, $owner, $residences_modcp_bit, $pmhandler, $session;
    $lang->load('residences');

    $lang->load('residences');
    require_once MYBB_ROOT . "inc/class_parser.php";
    $parser = new postParser;
    require_once MYBB_ROOT . "inc/datahandlers/pm.php";
    $pmhandler = new PMDataHandler();

    // Do something, for example I'll create a page using the hello_world_template
    $options = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );

    if ($mybb->get_input('action') == 'residences') {
        add_breadcrumb($lang->residences_modcp, "modcp.php?action=residences");

        $query = $db->query("SELECT *
            FROM " . TABLE_PREFIX . "places 
            where accepted = 0
            order by place ASC
        ");

        while ($row = $db->fetch_array($query)) {
            $place = "";
            $country = "";
            $denyplace = "";
            $acceptplace = "";
            $pid = 0;
            $creator = "";
            $place = $row['place'];
            $country = $row['country'];
            $pid = $row['pid'];
            $get_user = $db->fetch_array($db->simple_select("users", "*", "uid = '{$row['uid']}'"));
            $user = build_profile_link($get_user['username'], $get_user['uid']);
            $creator = $lang->sprintf($lang->residence_modcp_creator, $user);
            $denyplace = "<a href='modcp.php?action=residences&denyplace={$pid}'>{$lang->residences_modcp_deny_place}</a>";
            $acceptplace = "<a href='modcp.php?action=residences&acceptplace={$pid}'>{$lang->residences_modcp_accept_place}</a>";
            eval ("\$places_bit .= \"" . $templates->get("residences_modcp_places") . "\";");
        }

        $query = $db->query("SELECT *
        FROM " . TABLE_PREFIX . "residences 
        where accepted = 0
        order by street ASC
    ");

        while ($row = $db->fetch_array($query)) {
            $street = "";
   
            $description = "";
            $status = "";
            $max_res = 0;
            $rid = 0;
            $pid = 0;
            $accepthome = "";
            $creator = "";
            $rid = $row['rid'];
            $pid = $row['pid'];
            $res_place = "";

            $place = $db->fetch_field($db->simple_select("places", "place", "pid = {$pid}"), "place");
            $res_place = $lang->sprintf($lang->residence_modcp_resplace, $place);
            $street = $row['street'];
        
            if ($mybb->settings['r_status'] == 1 && !empty($row['status'])) {
                $status = $lang->sprintf($lang->residence_modcp_status, $row['status']);
            }
            $max_res = $lang->sprintf($lang->residence_modcp_maxres, $row['max_res']);
            $description = $parser->parse_message($row['description'], $options);
            $get_user = $db->fetch_array($db->simple_select("users", "*", "uid = '{$row['uid']}'"));
            $user = build_profile_link($get_user['username'], $get_user['uid']);
            $creator = $lang->sprintf($lang->residence_modcp_creator, $user);
            $accepthome = "<a href='modcp.php?action=residences&accepthome={$rid}'>{$lang->residences_modcp_accept_residence}</a>";
            eval ("\$residences_bit .= \"" . $templates->get("residences_modcp_residences") . "\";");
        }

        // Wohnort akzeptieren
        if (isset($mybb->input['acceptplace'])) {

            $pid = $mybb->input['acceptplace'];
            $row = $db->fetch_array($db->simple_select("places", "*", "pid = {$pid}"));

            $place = $row['place'];
            $owner = $row['uid'];

            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('residences_acceptplace');
                if ($alertType != NULL && $alertType->getEnabled()) {
                    $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $owner, $alertType);
                    $alert->setExtraDetails([
                        'place' => $place,
                    ]);
                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }

            $accept_place = array(
                "accepted" => 1
            );

            $db->update_query('places', $accept_place, "pid = {$pid}");
            redirect("modcp.php?action=residences");

        }

        // Wohnort ablehnen
        if (isset($mybb->input['denyplace'])) {

            $pid = $mybb->input['denyplace'];
            $row = $db->fetch_array($db->simple_select("places", "*", "pid = {$pid}"));

            $place = $row['place'];
            $owner = $row['uid'];

            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('residences_denyplace');
                if ($alertType != NULL && $alertType->getEnabled()) {
                    $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $owner, $alertType);
                    $alert->setExtraDetails([
                        'place' => $place,
                    ]);
                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }

            $db->delete_query('places', "pid = {$pid}");
            redirect("modcp.php?action=residences");

        }

        // Residenz akzeptieren
        if (isset($mybb->input['accepthome'])) {

            $rid = $mybb->input['accepthome'];
            $row = $db->fetch_array($db->simple_select("residences", "*", "rid = {$rid}"));

            $residence = $row['street'];
            $owner = $row['uid'];

            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('residences_accepthome');
                if ($alertType != NULL && $alertType->getEnabled()) {
                    $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $owner, $alertType);
                    $alert->setExtraDetails([
                        'residence' => $residence,
                    ]);
                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }

            $accept_home = array(
                "accepted" => 1
            );

            $db->update_query('residences', $accept_home, "rid = {$rid}");
            redirect("modcp.php?action=residences");

        }

        // Residenz ablehnen

        if (isset($mybb->input['denyhome'])) {
            $rid = 0;
            $reason = "";
            $fromuid = 0;
            $owner = 0;

            $rid = $mybb->input['rid'];
            $reason = $db->escape_string($mybb->input['denyreason']);
            $fromuid = $mybb->user['uid'];
            $row = $db->fetch_array($db->simple_select("residences", "*", "rid = {$rid}"));

            $owner = $row['uid'];
            $message = $lang->sprintf($lang->residence_modcp_denyhome, $row['street'], $reason, $row['description']);

            $can_pm = $db->fetch_field($db->simple_select("users", "receivepms", "uid = '{$owner}'"), "receivepms");


            if ($can_pm == 1) {

                $pm = array(
                    "subject" => "{$lang->residence_modcp_subject_deny}",
                    "message" => $message,
                    //to: wer muss die anfrage bestätigen
                    "fromid" => $fromuid,
                    //from: wer hat die anfrage gestellt
                    "toid" => $owner
                );

                $pm['options'] = array(
                    'signature' => '0',
                    'savecopy' => '0',
                    'disablesmilies' => '0',
                    'readreceipt' => '0',
                );
                if (isset($session)) {
                    $pm['ipaddress'] = $session->packedip;
                }
                // $pmhandler->admin_override = true;
                $pmhandler->set_data($pm);
                if (!$pmhandler->validate_pm())
                    return false;
                else {
                    $pmhandler->insert_pm();
                }
            }

            $db->delete_query("residences", "rid = '{$rid}'");
            redirect("modcp.php?action=residences");
        }

        eval ("\$page = \"" . $templates->get("residences_modcp") . "\";");
        output_page($page);
    }
}

function residences_alerts()
{
    global $mybb, $lang;
    $lang->load('residences');


    /**
     * Alert, wenn der Wohnort angenommen wurde
     */
    class MybbStuff_MyAlerts_Formatter_AcceptHomeFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         *
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->residences_accepthome,
                $outputAlert['from_user'],
                $alertContent['residence'],
                $outputAlert['dateline']
            );
        }


        /**
         * Init function called before running formatAlert(). Used to load language files and initialize other required
         * resources.
         *
         * @return void
         */
        public function init()
        {
        }

        /**
         * Build a link to an alert's content so that the system can redirect to it.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
         *
         * @return string The built alert, preferably an absolute link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/misc.php?action=residences';
        }
    }

    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }

        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_AcceptHomeFormatter($mybb, $lang, 'residences_accepthome')
        );
    }

    class MybbStuff_MyAlerts_Formatter_AcceptPlaceFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         *
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->residences_acceptplace,
                $outputAlert['from_user'],
                $alertContent['place'],
                $outputAlert['dateline']
            );
        }


        /**
         * Init function called before running formatAlert(). Used to load language files and initialize other required
         * resources.
         *
         * @return void
         */
        public function init()
        {
        }

        /**
         * Build a link to an alert's content so that the system can redirect to it.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
         *
         * @return string The built alert, preferably an absolute link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/misc.php?action=residences';
        }
    }

    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }

        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_AcceptPlaceFormatter($mybb, $lang, 'residences_acceptplace')
        );
    }


    class MybbStuff_MyAlerts_Formatter_DenyPlaceFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         *
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->residences_denyplace,
                $outputAlert['from_user'],
                $alertContent['place'],
                $outputAlert['dateline']
            );
        }


        /**
         * Init function called before running formatAlert(). Used to load language files and initialize other required
         * resources.
         *
         * @return void
         */
        public function init()
        {
        }

        /**
         * Build a link to an alert's content so that the system can redirect to it.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
         *
         * @return string The built alert, preferably an absolute link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/misc.php?action=residences';
        }
    }

    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }

        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_DenyPlaceFormatter($mybb, $lang, 'residences_denyplace')
        );
    }

}

function residences_user_activity($user_activity)
{
    global $user;
    if (my_strpos($user['location'], "misc.php?action=residences") !== false) {
        $user_activity['activity'] = "residences";
    }

    return $user_activity;
}

function residences_location_activity($plugin_array)
{
    global $db, $mybb, $lang;
    $lang->load('residences');
    if ($plugin_array['user_activity']['activity'] == "residences") {
        $plugin_array['location_name'] = $lang->residences_wiw;
    }
    return $plugin_array;
}

function residences_profile(){
    global $db, $memprofile, $template, $mybb, $residence, $lang;
    $lang->load('residences');
    $residence = "";
 
    if(!empty($memprofile['rid'])){
        $get_home = $db->fetch_array($db->query("SELECT *
        FROM ".TABLE_PREFIX."residences r
        LEFT JOIN ".TABLE_PREFIX."places p
        on (r.pid = p.pid)
        where rid = '{$memprofile['rid']}'
        "));
        $residence = $lang->sprintf($lang->residences_profile_home, $get_home['street'],  $get_home['place']);
    }

}