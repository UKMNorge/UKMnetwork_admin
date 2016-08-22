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


##  Adds UKMA Admin panel in Network Admin
function UKMA_add_site_admin() {
	global $lang;
	global $menu; # henter admin menyen
	global $filliste;
	$menu[30] = array('', 8, 'separator', '', 'wp-menu-separator');

	add_menu_page('UKM Site-admin', 'UKM Site-admin', 'administrator', 'UKMA_site_admin', 'UKMA_site_admin_gui', 'http://ico.ukm.no/hus-menu.png', 39);

	$page_season = add_submenu_page( 'UKMA_site_admin', 'Opprett sesong', 'Opprett sesong', 'superadministrator', 'UKMA_ny_sesong', 'UKMA_sesong' );
	add_action( 'admin_print_styles-' . $page_season, 'UKMA_scripts_and_styles' );

}



add_action('network_admin_menu', 'UKMA_add_site_admin');

## GUI oppsett ny sesong


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

function UKMA_scripts_and_styles() {
	wp_enqueue_script('WPbootstrap3_js');
	wp_enqueue_style('WPbootstrap3_css');
}
?>