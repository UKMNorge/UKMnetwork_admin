<?php
# STEG 1: Kopier DB ukmno_ss3 
# STEG 2: Kopier DB ukmno_wp2012
# STEG 3: Rydd unna gamle nettsider fra WP
# STEG 4: http://ukm.no/wp-admin/network/admin.php?page=UKMA_ny_sesong&do=true&init=true
# STEG 5: http://ukm.no/wp-admin/network/admin.php?page=UKMA_ny_sesong&start=0&stop=50&do=true

if(!isset($_GET['do']))
	die('"do" mangler som get-parameter. Du husket å kjøre DB-sesong?(init)');

if(isset($_GET['init'])) {
	require_once('ny_ukmdb_sesong.php');
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
$fylkebrukere = UKMA_SEASON_fylkesbrukere();

$teller = 0;
$START = (int)$_GET['start'];
$STOP = (int)$_GET['stop'];

if($STOP - $START > 80)
	die('Beklager, du pr&oslash;ver &aring; opprette for mange m&oslash;nstringer p&aring; en gang!');
	
while($monstring = mysql_fetch_assoc($monstringer)) {
	$teller++;
	if($teller < $START) {
#		echo 'Hopper over ' . $teller . '<br />';
		continue;
	} elseif($teller > $STOP) {
		die('<h1 style="margin: 100px;">N&aring;dd stoppintervall</h1>'
			.'<a href="?page='.$_GET['page']
				.'&do'
				.'&start='.((int)$_GET['stop']+1)
				.'&stop='.(($_GET['stop']+1)+(((int)$_GET['start']-(int)$_GET['stop'])*-1))
				.'">Neste</a>'
			);
	}
	
	## HENT INFO OM MØNSTRING
	$m = UKMA_SEASON_monstringsinfo($monstring['pl_id']);
	$m['pl_name'] = utf8_encode($m['pl_name']);
	$m['fylke_navn'] = utf8_encode($m['fylke_navn']);
	
	# det er en lokalmønstring
	if($m['type'] == 'kommune') {
		echo '<h3>Oppretter lokalmønstring '.$teller.'</h3>'
			.'NAVN: '.$m['pl_name'] . '<br />';
		
		echo 'Mønstringen har følgende kommuner <br />';
		echo '<pre>'; var_dump( $m['kommuner'] ); echo '</pre>';
		## HENTER ALLE KOMMUNER I MØNSTRINGEN
		$k = UKMA_SEASON_monstringsinfo_kommuner($m['kommuner']);
		# Array med k[id], k[name], k[url]

		## GENERER BRUKERLISTE FOR SIDEN
		$i = UKMA_SEASON_evaluer_kommuner($k, $m['fylke_id']);
		
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
		$blogg = UKMA_SEASON_opprett_blogg($namelist, $m['pl_id'], 'kommune', $m['fylke_id'], $idlist, $season);
	
		echo 'Legger til '. (is_array( $brukere ) ? sizeof( $brukere ) : 0 ).' brukere <br />';
		if(is_array( $brukere ) ) {
			foreach( $brukere as $bruker_for_fun_debug ) {
				echo ' &nbsp; BrukerID: ';
				var_dump($bruker_for_fun_debug);
				echo ' <br />';
			}
		}
		## LEGG TIL BRUKERNE TIL SIDEN
		UKMA_SEASON_brukere($blogg, $brukere, $m['fylke_id'], $fylkebrukere);
	
		echo 'Legger til re-writes<br />';
		## LEGG TIL RE-WRITES
		UKMA_SEASON_rewrites($m['fylke_name'], $rewrites, $m['pl_id']);
		
	###################
	## VI SNAKKER FYLKE
	} else {
		echo '<h3>Oppretter fylkesmønstring '.$teller.'</h3>'
			.'NAVN: '.$m['pl_name'] . '<br />';
		## OPPRETT SIDEN
		echo '<br />Oppretter blogg<br />';
		$blogg = UKMA_SEASON_opprett_blogg($m['pl_name'], $m['pl_id'], 'fylke', $m['pl_fylke'], '', $season);
			
		echo 'Legger til brukere <br />';
		## LEGG TIL BRUKERNE TIL SIDEN
		UKMA_SEASON_brukere($blogg, array(), $m['fylke_id'], $fylkebrukere);
		
		echo 'URL-adresse<br />';
		echo UKMA_SEASON_urlsafe($m['pl_name']);

	}
	#die();
}


############################################################################################################################
## FUNKSJONER
############################################################################################################################
################################################
## LAGER FYLKESBRUKERNE
################################################
function UKMA_SEASON_fylkesbrukere() {
	global $wpdb;
	$fylker = new SQL("SELECT * FROM `smartukm_fylke`");
	$fylker = $fylker->run();
	## LOOPER ALLE FYLKER OG OPPRETTER BRUKER OM DEN IKKE FINNES
	while($f = mysql_fetch_assoc($fylker)) {
		$name = UKMA_SEASON_urlsafe($f['name']);

		$password = UKM_ordpass();
		$bruker = $wpdb->get_row("SELECT * FROM `ukm_brukere`
									  WHERE `b_fylke` = '".$f['id']."'");
		if(is_object($bruker)) {
			$email = $bruker->b_email;
		} else {
			$email = UKMA_SEASON_urlsafe($f['name']) .'@fylkefake.ukm.no';
		}
		
		## Om brukeren finnes, legg til ID i array og gå pent videre
		if(username_exists( $name )) {
			$userIDnow = username_exists($name);
			$users[$f['id']] = $userIDnow;
			remove_user_from_blog($userIDnow, 1);
		} else {
			$brukerinfo = array('b_name'=>$name,
								'b_password'=>$password,#wp_generate_password(6,false,false),
								'b_email'=>$email,
								'b_kommune'=>0,
								'b_fylke' => $f['id']);
			## Opprett bruker
			echo $name . ' - ';
			$user_id = wp_create_user( $brukerinfo['b_name'], $brukerinfo['b_password'], $brukerinfo['b_email'] );
			if(!is_string($user_id)&&!is_numeric($user_id))
				var_dump($user_id);
			else
				echo $user_id . '<br />';
			## Oppdater klartekstarray
			$brukerinfo['wp_bid'] = $user_id;
			
			## LAGRE I KLARTEKSTTABELL
			if(is_object($bruker)) {
				$wpdb->update('ukm_brukere',
						  $brukerinfo,
						  array('b_id'=>$bruker->b_id));
			} else {
				$wpdb->insert('ukm_brukere',$brukerinfo);
			}
			## OPPRETTHOLD LISTE OVER FYLKESBRUKERE
			$users[$f['id']] = $user_id;
			remove_user_from_blog($user_id, 1);
		}
	}
	return $users;
}
############################################################################################################################
## MØNSTRINGEN
################################################
## HENTER INFO OM MØNSTRINGEN
################################################
function UKMA_SEASON_monstringsinfo($pl_id) {
	$m = new monstring($pl_id);
	return $m->info();
}
################################################
## HENTER INFO OM HVILKE KOMMUNER SOM ER 
## MED I MØNSTRINGEN
################################################
function UKMA_SEASON_monstringsinfo_kommuner($kommuner) {
	$list = array();
	if(is_array($kommuner))
		foreach($kommuner as $trash => $kommune) {
			$k = $kommune;
			$safestring = str_replace(array('æ','ø','å','Æ','Ø','Å'), array('a','o','a','A','O','A'), $k['name']);
			$k['url'] = preg_replace("/[^A-Za-z0-9-]/","",$safestring); #UKMA_SEASON_urlsafe($k['name']);
			$list[] = $k;
		}
	return $list;
}
?>