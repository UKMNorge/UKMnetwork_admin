<?php
	
# STEG 1: Kopier DB ukmno_ss3 
# STEG 2: Kopier DB ukmno_wp2012
# STEG 3: Rydd unna gamle nettsider fra WP
# STEG 4: http://ukm.no/wp-admin/network/admin.php?page=UKMA_ny_sesong&do=true&init=true
# STEG 5: http://ukm.no/wp-admin/network/admin.php?page=UKMA_ny_sesong&start=0&stop=10&do=true

if(!isset($_GET['do']) && !isset($_GET['init'] )) {
	echo TWIG('dash.twig.html', array() , dirname(dirname(__FILE__)) );
	die();
}

if( isset($_GET['do']) && $_GET['do'] == 'wordpress' ) {
	require_once('ny_sesong_wordpress.php');
	die('FULLFØRT');
}
/*
	if(!isset($_GET['do']))
	die('"do" mangler som get-parameter. Du husket å kjøre DB-sesong?(init)');
*/
if(isset($_GET['init'])) {
	require_once('ny_ukmdb_sesong.php');
	die('NY SESONG ER NÅ KLAR! HUSK Å OPPDATERE INNSTILLINGER');
}
	
if(!isset($_GET['stop']))	
	die('Mangler intervall!');
	
if(!isset($_GET['start']))
	die('Mangler startpunkt');

## lagt til 09.11.2011 - flyttet mange funksjoner til denne filen pga ny_monstring
require_once('UKM/inc/password.inc.php');
require_once('ny_monstring_funksjoner.php');

$season = (int)date("Y")+1;

## LOOP ALLE MØNSTRINGER
$monstringer = new monstringer();
$monstringer = $monstringer->etter_sesong($season);

## OPPRETT FYLKESBRUKERE
echo '<h2>Oppretter fylkesbrukere</h2>';
$fylkebrukere = UKMA_SEASON_fylkesbrukere( $_GET['start'] == 0 );

$teller = 0;
$START = (int)$_GET['start'];
$STOP = (int)$_GET['stop'];

echo '<h2>Oppretter mønstringssider for '. $season .'-sesongen</h2>';
if($STOP - $START > 80)
	die('Beklager, du pr&oslash;ver &aring; opprette for mange m&oslash;nstringer p&aring; en gang!');
	
if( $START > 0 ) {
	echo '<p>Hopper over ';
}

if( mysql_num_rows( $monstringer ) == 0 ) {
	die('<div class="alert alert-danger">Beklager, fant ingen mønstringer!</div>');
}
while($monstring = mysql_fetch_assoc($monstringer)) {
	$teller++;
	if($teller <= $START) {
		echo $teller . ', ';
		continue;
	} elseif($teller > $STOP) {
		break;
	}
	echo '</p>';
	
	
	## HENT INFO OM MØNSTRING
	$m = UKMA_SEASON_monstringsinfo($monstring['pl_id']);
	$m['pl_name'] = utf8_encode($m['pl_name']);
	$m['fylke_navn'] = utf8_encode($m['fylke_navn']);
	
	echo '<fieldset><legend>Mønstring '. ($teller-$START) .' av '. ($STOP-$START) .' (nr '. $START .' til '. $STOP .') </legend>';
	# det er en lokalmønstring
	if($m['type'] == 'kommune') {
		echo ' '. (empty($m['pl_name']) ? '<span class="alert-danger">Mønstring uten navn</span>' : $m['pl_name'] ) .' <span class="badge">Lokalmønstring</span> <br />';
		
		echo ' <label>KOMMUNER:</label><br />';
		## HENTER ALLE KOMMUNER I MØNSTRINGEN
		$k = UKMA_SEASON_monstringsinfo_kommuner($m['kommuner']);
		# Array med k[id], k[name], k[url]
		foreach( $k as $kommune ) {
			echo ' &nbsp; <label>'. $kommune['name']. ': </label> '. $kommune['url'] .' <span class="badge">KommuneID:'. $kommune['id'] .'</span><br />';
		}

		echo ' <label>BRUKERE:</label><br />';
		## GENERER BRUKERLISTE FOR SIDEN
		$i = UKMA_SEASON_evaluer_kommuner($k, $m['fylke_id']);
		
		$brukere = $i['brukere'];
		# Array med brukerID'er til lokalmønstringen
		
		# Kommaseparert navneliste over kommuner i mønstringen
		$namelist = $i['namelist'];
		echo ' <label>KOMMUNE-LISTE:</label> '. $namelist .'<br />';

		# Kommaseparert ID-liste over kommuner i mønstringen
		$idlist = $i['idlist'];
		echo ' <label>KOMMUNEID-LISTE:</label> '. $idlist .'<br />';		

		# Array med URL-vennlige kommunenavn for mod rewrite
		$rewrites = $i['rewrites'];
		echo ' <label>URL REWRITES:</label><br />';
		if( is_array($rewrites) ) {
			foreach( $rewrites as $rewrite ) {
				echo ' &nbsp; '. $rewrite .'<br />';
			}
		}
		
				
		## OPPRETT SIDEN
		echo '<br /><label>OPPRETTER BLOGG</label><br />';
		$blogg = UKMA_SEASON_opprett_blogg($namelist, $m['pl_id'], 'kommune', $m['fylke_id'], $idlist, $season);
	
		echo '<label>LEGGER TIL BRUKERE</label><br />';
		## LEGG TIL BRUKERNE TIL SIDEN
		UKMA_SEASON_brukere($blogg, $brukere, $m['fylke_id'], $fylkebrukere);
	
		echo '<label>LEGGER TIL REWRITES</label><br />';
		## LEGG TIL RE-WRITES
		UKMA_SEASON_rewrites($m['fylke_name'], $rewrites, $m['pl_id']);
		
	###################
	## VI SNAKKER FYLKE
	} else {
		echo $m['pl_name'] .' <span class="label-warning">FYLKESMØNSTRING</span> <br />';
		## OPPRETT SIDEN
		echo '<br /><label>OPPRETTER BLOGG</label><br />';
		$blogg = UKMA_SEASON_opprett_blogg($m['pl_name'], $m['pl_id'], 'fylke', $m['pl_fylke'], '', $season);
			
		echo '<label>LEGGER TIL BRUKERE</label><br />';
		## LEGG TIL BRUKERNE TIL SIDEN
		UKMA_SEASON_brukere($blogg, array(), $m['fylke_id'], $fylkebrukere);

		echo '<label>SETTER STANDARDSIDE FOR BRUKERE</label><br />';
		$fylkesbruker = $fylkebrukere[ $m['pl_fylke'] ];
		echo ' &nbsp; Sett primary site ID = '. $blogg .' for bruker <span class="badge">'. $fylkesbruker .'</span><br />';
		update_user_meta( $fylkesbruker, 'primary_blog', $blogg);
		
		echo ' <label>URL REWRITES:</label><br />';
		echo UKMA_SEASON_urlsafe($m['pl_name']);

	}
	echo '</fieldset>';
}

echo '<h2>Opprett neste</h2>';
#$intervall = $_GET['stop'] - $_GET['start'];
$intervall = 10;
echo '<a href="?page='. $_GET['page'] .'&start='. $_GET['stop'] .'&stop='. ($_GET['stop']+$intervall).'&do=true">Opprett de neste '. $intervall .'</a>';
?>