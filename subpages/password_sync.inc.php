<?php
if( !isset($_GET['sync'] ) ) {
	die('Denne modulen vil oppdatere alle wordpressbrukere med passord satt i klarteksttabellen. Bruk get-parameter ?sync=true for å starte synkronisering');
}

global $wpdb;
$brukere = $wpdb->get_results("SELECT * FROM `ukm_brukere`", OBJECT);

echo '<h2>Synkroniserer brukere</h2>';
foreach( $brukere as $bruker ) {
	echo '<strong>ID: '. $bruker->wp_bid .'</strong><br />';
	echo ' &nbsp; Name: '. $bruker->b_name .' <br />';
	echo ' &nbsp; Password: '. $bruker->b_password .' <br />';
}