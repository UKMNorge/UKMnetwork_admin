<?php

echo '<h2>Synkroniserer brukere mellom WP og SS3</h2>';

## LAST INN ALLE BRUKERE FRA KLARTEKSTTABELLEN
$brukere = $wpdb->get_results( "SELECT `b_id`, `b_name`
								FROM `ukm_brukere`
								ORDER BY `b_id` DESC", "ARRAY_A");
## LOOP KLARTEKSTTABELLEN BAKFRA (ORDER) FOR Å KUNNE FJERNE ELDRE BRUKERE
echo '<h3>Slett duplikater</h3>';
foreach($brukere as $i => $bruker) {
	$sjekk = $wpdb->get_results( "SELECT `b_id`
								  FROM `ukm_brukere`
								  WHERE `b_name` = '". $bruker['b_name']."'" );
	$antall = sizeof($sjekk);
	
	## HVIS FLERE ENN 1, RYDD
	if($antall > 1) {
		$wpdb->query("DELETE FROM `ukm_brukere` 
					  WHERE `b_name` = '". $bruker['b_name']. "'
					  AND `b_id` < '". $bruker['b_id']. "'");
		echo 'Slettet ' . ($antall-1) . ' duplikat' . (($antall-1)>1 ? 'er':'') .' for bruker &quot;'. $bruker['b_name'] .'&quot;<br />';
	}
}

## LOOP SS3-TABELLEN
echo '<h3>Oppdater SS3-brukere</h3>';
$qry = new SQL("SELECT `id`, `username` FROM `smartcore_users`");
$res = $qry->run();
while($b = mysql_fetch_assoc($res)) {
	# KORRIGER NAVN FOR WP
	$bruker = UKMA_SEASON_urlsafe($b['username']);
	echo $b['username'] .' => '. $bruker . ' => ';

	# FINN TILSVARENDE I WP
	$wpbruker = $wpdb->get_results("SELECT `b_id`, `b_name`, `b_password`
								  FROM `ukm_brukere`
								  WHERE `b_name` = '". $bruker . "'", "ARRAY_A");
	if(sizeof($wpbruker) == 0) {
		echo ' <strong> ga ingen treff! </strong> <br />';
		$fantikke[$bruker] = $b['username'];
	}
	else {
		# GENERER MD5 FOR SS3
		$md = md5($wpbruker[0]['b_password']);

		# SETT NYTT PASSORD
		$upd = new SQLins('smartcore_users', array('id'=>$b['id']));
		$upd->add('username', strtolower($wpbruker[0]['b_name']));
		$upd->add('password', $md);
		$updres = $upd->run();			
		# ECHO
		echo $wpbruker[0]['b_name'] . ' ('.$wpbruker[0]['b_id'].') <br />';
		echo ' &nbsp; ' . $wpbruker[0]['b_password'] . ' => ' . $md . '<br />';
	}
}
## SKRIV UT ERROR-RAPPORT
echo '<h3 style="color: #ff0000;">Disse SS3-brukerne ble ikke funnet i WP</h3>';
foreach($fantikke as $u_wp => $u_ss3)
	echo $u_wp . ' ('.$u_ss3.')<br />';
	
	
echo '<h1>FERDIG!</h1>'
	.'Brukerbasene er n&aring; synkronisert med klartekstbasen';
# FERDIG




################################################
## SIKRER EN STRENG FOR URL-BRUK
################################################
## !!! OBS !!!: KOPIERT FRA ny_sesong_marius.php
## !!! OBS !!!: KOPIRT TIL UKM/inc/toolkit
function UKMA_SEASON_urlsafe($text) {
	
	$text = SMAS_encoding($text);

	$text = htmlentities($text);
	
	$ut = array('&Aring;','&aring;','&Aelig;','&aelig;','&Oslash;','&oslash;','&Atilde;','&atilde','Ocedil','ocedil');
	$inn= array('A','a','A','a','O','o','O','o','O','o');
	$text = str_replace($ut, $inn, $text);
	
	$text = preg_replace("/[^A-Za-z0-9-]/","",$text);

	return $text;
}
?>