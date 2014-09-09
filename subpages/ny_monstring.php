<?php
require_once('UKM/inc/password.inc.php');
require_once('ny_monstring_funksjoner.php');

###############################################################################
## HAR VALGT KOMMUNE, WHAT TO DO?
###############################################################################
function UKMA_ny_monstring_valgt($kid) {
	global $wpdb;
	$kommune = new SQL("SELECT  `smartukm_kommune`.`id`, 
								`smartukm_kommune`.`name`,
								`smartukm_fylke`.`name` AS `fylke`,
								`smartukm_fylke`.`id` AS `fylkeid`
						FROM `smartukm_kommune`
						JOIN `smartukm_fylke` ON (`smartukm_fylke`.`id` = `smartukm_kommune`.`idfylke`)
						WHERE `smartukm_kommune`.`id` = '#id'",
					array('id'=>$kid));
	$kommune = $kommune->run('array');

	$fylkesbruker = get_userdatabylogin(UKMA_SEASON_urlsafe($kommune['fylke']));
	$fylkebrukere[strtolower(UKMA_SEASON_urlsafe($kommune['fylke']))] = $fylkesbruker->ID;

	// INIT
	echo '<h2>Oppretter m&oslash;nstring '.utf8_encode($kommune['name']).'</h2>';
	
	// OPPRETT MØNSTRINGEN
	$pl = new SQLins('smartukm_place');
	$pl->add('pl_name', utf8_encode($kommune['name']));
	$pl->add('pl_kommune', time());
	$pl->add('season', get_option('season'));
	$place = $pl->run();
	$pl_id = $pl->insid();
	
	echo 'M&oslash;nstringen har f&aring;tt pl_id ' . $pl_id .'<br />';
	
	// OPPRETT RELASJON MELLOM KOMMUNEN OG MØNSTRINGEN
	## Den gamle må være slettet for at vi skal komme hit.. :) 
	$plk = new SQLins('smartukm_rel_pl_k');
	$plk->add('pl_id', $pl_id);
	$plk->add('k_id', $kid);
	$plk->add('season', get_option('season'));
	$plk = $plk->run();
	if($plk == false)
		echo '<div class="error">Relasjon IKKE opprettet</div>';
	else
		echo 'Opprettet relasjon mellom PL: '.$pl_id.' og kommune: '.$kid.'<br />';
	
	// OPPRETT ELLER SØK OPP BRUKER(E)
	$i = UKMA_MONSTRING_bruker($kommune['name'], $kid, $kommune['fylke']);

	$brukere = $i['brukere'];
	# Array med brukerID'er til lokalmønstringen
	
	$namelist = $i['namelist'];
	# Kommaseparert navneliste over kommuner i mønstringen
	$idlist = $i['idlist'];
	# Kommaseparert ID-liste over kommuner i mønstringen
	
	$rewrites = $i['rewrites'];
	# Array med URL-vennlige kommunenavn for mod rewrite
	
	## OPPRETT SIDEN
	echo '<br />Oppretter blogg<br />';
	$blogg = UKMA_SEASON_opprett_blogg($namelist, $pl_id, 'kommune', $kommune['fylkeid'], $idlist, get_option('season'));

	echo 'Legger til brukere <br />';
	## LEGG TIL BRUKERNE TIL SIDEN
	UKMA_SEASON_brukere($blogg, $brukere, strtolower(UKMA_SEASON_urlsafe($kommune['fylke'])), $fylkebrukere);

	echo 'Legger til re-writes<br />';
	## LEGG TIL RE-WRITES
	UKMA_SEASON_rewrites(strtolower(UKMA_SEASON_urlsafe($kommune['fylke'])), $rewrites, $pl_id);

	echo '<div class="message">M&oslash;nstring opprettet!</div>'
		. '<div class="error">Har ikke flyttet p&aring;meldte!</div>';
		
		
		
	// Finner SS3-bruker
	$ss3 = new SQL("SELECT `id` FROM `smartcore_users` WHERE `username` = '#bruker'",
					array('bruker'=>strtolower(UKMA_SEASON_urlsafe($kommune['name']))));
	$ss3 = $ss3->run('field','id');
	if($ss3 == 0)
		echo '<div class="error">Fant ingen brukere med brukernavn '.UKMA_SEASON_urlsafe($kommune['name']).'</div>';
	else {
		$updss3 = new SQLins('smartukm_user', array('ss3u_id'=>$ss3));
		$updss3->add('pl_id',$pl_id);
		$updres = $updss3->run();
		if($updres == false)
			echo '<div class="error">Kunne ikke oppdatere brukeren</div>';
		else
			echo 'Oppdatert SS3-bruker ('.$ss3.') til riktig PL-id <br />';
	}
}

###############################################################################
## KOMMUNER SOM IKKE ER KNYTTET TIL NOEN MØNSTRING DENNE SESONGEN
###############################################################################
function UKMA_ny_monstring_ureg() {
	$return = '<h2>Opprett en ny m&oslash;nstring</h2>'
			.'F&oslash;lgende kommuner er ikke tilknyttet noen m&oslash;nstringer i '.get_option('season').'-sesongen<br /><br/>';
	
	$qry = new SQL("SELECT * FROM `smartukm_kommune`
					ORDER BY `name` ASC");
	$res = $qry->run();
	while($r = mysql_fetch_assoc($res)) {
		$tom = new SQL("SELECT `pl_id`
						FROM `smartukm_rel_pl_k`
						WHERE `k_id` = '#kommune'
						AND `season` = '#season'",
						array('kommune'=>$r['id'],'season'=>get_option('season')));
		$tom = $tom->run();
		if(mysql_num_rows($tom)==0){
			$return .= '<a href="?page=UKMA_ny_monstring&k='.$r['id'].'">'.utf8_encode($r['name']).'</a><br />';
		}
	}
	return $return;
}



?>