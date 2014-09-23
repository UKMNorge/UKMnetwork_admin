<?php
/* 
Plugin Name: UKM Admin
Plugin URI: http://www.ukm-norge.no
Description: UKM Network admin
Author: UKM Norge / O E Betten 
Version: 2.1 
Author URI: http://www.ukm-norge.no
*/

$UKMN['me'] = '/wp-content/plugins/UKMAdmin/';
$UKMN['domain'] = 'ukm.no';
require_once('UKM/monstringer.class.php');
#UKM_loader('form|toolkit|api/ukmAPI');

function UKMA_sesong() {
	require_once('subpages/ny_sesong_marius.php');
}

function UKMA_brukere() {
	UKM_loader('inc/toolkit');
	global $wpdb;
	require_once('subpages/brukere_oppdater.php');
}


##  Adds UKMA Admin panel in Network Admin
function UKMA_add_site_admin() {
	global $lang;
	global $menu; # henter admin menyen
	global $filliste;
	$menu[30] = array('', 8, 'separator', '', 'wp-menu-separator');
	
	# functions
	# add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position )
	# add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function )
	## ADD SUBMENU TO SITE-OPTIONS
	add_submenu_page( 'sites.php', 'Oppdater/sett blog option', 'Set option', 'superadministrator', 'UKMA_setOpt', 'UKMA_setOpt' );	

	add_menu_page('UKM Site-admin', 'UKM Site-admin', 'administrator', 'UKMA_site_admin', 'UKMA_site_admin_gui', 'http://ico.ukm.no/hus-menu.png', 39);

	add_submenu_page( 'UKMA_site_admin', 'Opprett sesong', 'Opprett sesong', 'superadministrator', 'UKMA_ny_sesong', 'UKMA_sesong' );
	add_submenu_page( 'UKMA_site_admin', 'Oppdater kortadresser', 'Oppdater kortadresser', 'superadministrator', 'UKMA_rewrite', 'UKMA_rewrite' );
	add_submenu_page( 'UKMA_site_admin', 'Synkroniser passord', 'Synkroniser passord', 'superadministrator', 'UKMA_password_sync', 'UKMA_password_sync' );
	add_submenu_page( 'UKMA_site_admin', 'Oppdater brukere', 'Oppdater brukere', 'superadministrator', 'UKMA_brukere', 'UKMA_brukere' );
	add_submenu_page( 'UKMA_site_admin', 'Opprett m&oslash;nstring', 'Opprett m&oslash;nstring', 'superadministrator', 'UKMA_ny_monstring', 'UKMA_ny_monstring' );
	add_submenu_page( 'UKMA_site_admin', 'Trekk ut kommune', 'Trekk ut kommune', 'superadministrator', 'UKMA_trekkut', 'UKMA_trekkut' );
	add_submenu_page( 'UKMA_site_admin', 'Roller og rettigheter', 'Roller og rettigheter', 'superadministrator', 'UKMA_roles_and_capabilities', 'UKMA_roles_and_capabilities' );
	
	
	add_menu_page('UKM Toppfaner', 'UKM Toppfane', 'administrator', 'UKM_toppfaner', 'UKM_toppfaner', 'http://ico.ukm.no/hus-menu.png', 40);

}

function UKMA_roles_and_capabilities() {
	require_once('subpages/roles_and_capabilities.php');
	UKM_roles_and_capabilities_inner();
}

function UKMA_trekkut() {
	require_once('subpages/trekkut.php');
	if(isset($_GET['kid']) && isset($_GET['pl_id']))
		echo UKMA_trekkut_steg2($_GET['pl_id'],$_GET['kid']);
	else
		echo UKMA_trekkut_steg1();
}

function UKMA_ny_monstring() {
	require_once('UKM/sql.class.php');
	require_once('subpages/ny_monstring.php');
	
	if(isset($_GET['k']))
		echo UKMA_ny_monstring_valgt($_GET['k']);
	else
		echo UKMA_ny_monstring_ureg();	
}


add_action( 'admin_head', 'UKMA_favicon' );
function UKMA_favicon() {
	echo '<link rel="shortcut icon" href="http://ico.ukm.no/wp-admin_favicon.png" />';
}

// BRUKERES PASSORDHÅNDTERING
add_action('profile_update', 'UKMA_passordsak');
function UKMA_passordsak() {
	global $wpdb;

	if($_POST['pass1']==$_POST['pass2']&&!empty($_POST['pass1'])) {
		$wpdb->update('ukm_brukere',
					array('b_password'=>$_POST['pass1']),
					array('wp_bid'=>$_POST['user_id']));
	}
}

function UKMA_password_sync() {
	require_once('subpages/password_sync.inc.php');
}


function UKMA_setOpt(){
	require_once('subpages/setopt.php');
}

add_action('network_admin_menu', 'UKMA_add_site_admin');

## GUI oppsett ny sesong
function UKMA_rewrite() {
	echo '<h1>Re-genererer site rewrites</h1>';
	UKMA_rewrite_update_siteurl();
}

function UKMA_rewrite_update_siteurl() {
	global $wpdb, $wp_rewrite;
	$sites = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->blogs"));
	$start = (isset($_GET['start'])?$_GET['start']:150);
	$i = 0;
	foreach ( $sites as $site ) {
		$i++;
		if($i==$start+150)
			die('<a href="?page='.$_GET['page'].'&start='.($start+150).'">Neste 150</a>');
		if($i < $start){
			echo 'hopper over '. $i .'<br />';
			continue;
		}
		$url = get_site_url($site->blog_id);
		echo 'Side '. get_blog_option($site->blog_id, 'blogname') . '<br />';
		switch_to_blog($site->blog_id);
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();
		restore_current_blog();
	}	

	echo '<strong>Totalt oppdatert '. $i .' sites</strong>';
}


function UKMA_ny_sesong_gui(){
	global $lang;
	
	# overskrift til siden
	$nav = new nav($lang['UKMA_ny_sesong_opprett'], '', 120, 'h3'); # headline
	print $nav->run();
	
	
	require_once(ABSPATH.'wp-content/plugins/UKMAdmin/subpages/ny_sesong.php');
	if(isset($_POST['admin_ny_sesong_input'])){ # oppretter siter i henhold til sesong gitt
		print UKMA_make_site_from_season($_POST['admin_ny_sesong_input']);
	}
	# skjema for hvalg av sesong
	$admin_ny_seson_form = new form('admin_ny_sesong');
	$admin_ny_seson_form->input($lang['sesong'],'admin_ny_sesong_input',date('Y'),'dette er hvilket år man tar utgangspunkt i når man bygger årets mønstrings sidene');
	
	print '<form id="form_admin_ny_sesong" method="post" action="'.$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'].'" class="validate">'
	.UKMN_fieldset($lang['sesong'], $admin_ny_seson_form->run(), '750px;')
	.'<p class="submit"><input type="submit" class="button" name="admin_ny_sesong_submit" id="admin_ny_sesong_submit" value="'.$lang['save'].'" /></p>'
	.  '</form>';
}

## GUI Opprett ny site FRA KOMMUNER SOM ER UTEN MØNSTRING

function UKMA_ny_site_gui(){
	global $lang;
	global $wpdb;
	require_once(ABSPATH.'wp-content/plugins/UKMAdmin/subpages/ny_sesong.php');
	
	
	# overskrift til siden
	$nav = new nav($lang['UKMA_site_opprett'], '', 120, 'h3'); # headline
	print $nav->run();
	
	
	if(isset($_POST['admin_ny_site_submit'])){ # oppretter siter i henhold til sesong gitt
		# NY PL
		$wpdb->insert('ukm_place',array(
			'pl_name' => UKMN_kommune($_POST['k_id']),
			'season' => date('Y')
		));
		# KOMMUNE REL PL K
		$pl_id = $wpdb->insert_id;
		$wpdb->insert('ukm_rel_pl_k',array(
			'pl_id' => $pl_id,
			'k_id' => $_POST['k_id']
		));
		# NY SITE
		if($pl_id!=0)  print UKMA_make_site_from_pl_id($pl_id);
	}
	# skjema for hvalg av sesong
	$admin_ny_site_form = new form('admin_ny_site');
	$usedKommuner = $wpdb->get_col('SELECT distinct k_id FROM ukm_rel_pl_k');
	$allKommuner = $wpdb->get_results('SELECT * FROM ukm_kommune','ARRAY_A');
	$rest = array();
	foreach($allKommuner as $kommune){
		if(!in_array($kommune['k_id'],$usedKommuner)) $rest[$kommune['k_id']] = $kommune['k_name'];
	}
	$admin_ny_site_form->select('Kommune', 'k_id', $rest);
	print '<form id="form_admin_ny_site" method="post" class="validate">'
	.UKMN_fieldset($lang['ny_site'], $admin_ny_site_form->run(), '750px;')
	.'<p class="submit"><input type="submit" class="button" name="admin_ny_site_submit" id="admin_ny_site_submit" value="'.$lang['save'].'" /></p>'
	.  '</form>';
}


## GUI test underside 

function UKMA_site_admin_test_gui(){# skal fjernes
	require_once(ABSPATH.'wp-content/plugins/UKMAdmin/subpages/ny_sesong.php');
}

## GUI site admin side

function UKMA_site_admin_gui(){
	global $lang;
	$nav = new nav($lang['UKMA_admin_meny'], 'her stiller man på netverk instillinger til UKM multisite', 120, 'h3');

	$box = new navCell($lang['UKMA_ny_sesong_opprett'],'city');
	$box->link('?page=UKMA_ny_sesong',$lang['UKMA_ny_sesong_opprett']);
	
	$nav->add($box);
	
	$gui = $nav->run();
	print $gui;
}
?>