<?php
################################################
## LAGER FYLKESBRUKERNE
################################################
function UKMA_SEASON_brukere($blogg, $brukere, $fylke, $fylkebrukere) {
	## Legg til fylkesbrukeren
	$brukere[] = $fylkebrukere[$fylke];
	
	for($i=0; $i<sizeof($brukere); $i++) {
		add_user_to_blog($blogg, $brukere[$i], 'editor');
		if(!is_int($brukere[$i])) {
			echo 'La ikke til bruker, grunnet feil: '. $brukere[$i]->errors[0]. '<br />';		
		} else {
		    echo 'La til bruker '.$brukere[$i].' som &quot;editor&quot; i side '.$blogg.' <br />';
			remove_user_from_blog($brukere[$i], 1);
	    }
	}
	## Legg til UKM Norge
	add_user_to_blog($blogg, 1, 'administrator');
	echo 'La til bruker UKM Norge som &quot;administrator&quot; i side '.$blogg.' <br />';

}

################################################
## OPPPRETTER BLOGGEN, HVIS DEN IKKE FINNES
################################################
function UKMA_SEASON_opprett_blogg($navn, $pl_id, $type, $fylkeid, $kommuneider='', $season){
		## KALKULER PATH
	if($type == 'kommune')
		$path = '/pl'.$pl_id.'/';
	else
		$path = '/'.strtolower(UKMA_SEASON_urlsafe($navn)).'/';
	
	## SJEKEKR OM BLOGGEN FINNES, HVIS IKKE - OPPRETT
	if(!domain_exists('ukm.no', $path)){					
		## OPPRETT BLOGG
		$blog_id = create_empty_blog('ukm.no',$path,$navn);
		
		## SETT STANDARDINNHOLD
		UKMA_ny_sesong_standard_posts($blog_id, $type);
		
		# ADDS META OPTIONS TO NEW SITE
		$meta = array('blogname'=>$navn,
					  'blogdescription'=>'UKM i ' . $navn,
					  'fylke'=>$fylkeid,
					  'kommuner'=>$kommuneider,
					  'site_type'=>$type,
					  'pl_id' => $pl_id,
					  'ukm_pl_id' => $pl_id,
					  'season' =>$season,
					  'show_on_front'=>'page',
					  'page_on_front'=>'2',
					  'template'=>'manifesto',
					  'stylesheet'=>'manifesto',
					  'current_theme'=>'UKM Norge - Manifesto'
					 );
		## LEGGER TIL ALLE META-INNSTILLINGER
		foreach($meta as $key => $value) {
			add_blog_option($blog_id, $key, $value);
			update_blog_option($blog_id, $key, $value, true);
		}
	} else 	
		$blog_id = false;
	
	return $blog_id;
}

################################################
## OPPRETTER BRUKERE, BASERT PÅ KOMMUNE/FYLKEINFO
################################################
function UKMA_MONSTRING_bruker($kommunenavn, $kommuneid, $fylkenavn) {
	global $wpdb;
	
	// OPPRETT EN BRUKER FOR SIDEN, HVIS DEN IKKE ALLEREDE FINNES
	$bruker = $wpdb->get_row("SELECT * FROM `ukm_brukere`
								  WHERE `b_kommune` = '".$kommuneid."'");
	#echo '<pre>'; var_dump($bruker); echo '</pre>'; die();
	if(is_object($bruker)) {
		$password = UKM_ordpass();		
		$brukerinfo = array('b_name'=>$bruker->b_name,
							'b_password'=>$password,
							'b_email'=>$bruker->b_email,
							'b_kommune'=>$bruker->b_kommune,
							'b_fylke'=>$bruker->b_fylke);
		echo 'Fant brukerinfo i klarteksttabellen<br />';
	} else {
		$password = UKM_ordpass();
		$brukerinfo = array('b_name'=>ucfirst(UKMA_SEASON_urlsafe($kommunenavn)),
							'b_password'=>$password,#wp_generate_password(6,false,false),
							'b_email'=>strtolower(UKMA_SEASON_urlsafe($kommunenavn)).'@fake.ukm.no',
							'b_kommune'=>$kommuneid,
							'b_fylke' => $fylkenavn);
		echo 'Opprettet en ny bruker<br />';
	}

	if(username_exists( $brukerinfo['b_name'] )) {
		// ADDED WP_SET_PASSWORD 25.09.2013
		$userIDnow = username_exists( $brukerinfo['b_name'] );
		$userids[] = $brukerinfo['wp_bid'] = $userIDnow;
		wp_set_password( $brukerinfo['b_password'], $userIDnow );
	} else {
		## OPPRETT BRUKERE
		$userid = wp_create_user($brukerinfo['b_name'], $brukerinfo['b_password'], $brukerinfo['b_email']);
		if(!is_numeric($userid)) {
			echo '<div class="error">Feilet i brukeropprettelse: '. var_export($userid, true).'</div>';
		}else {
			## LEGG TIL BRUKERID I FELLESARRAY + KLARTEKSTDATABASE
			$userids[] = $brukerinfo['wp_bid'] = $userid;
		}
	}
	## LAGRE I KLARTEKSTTABELL
	if(is_object($bruker)) {
		$wpdb->update('ukm_brukere',
			  $brukerinfo,
			  array('b_id'=>$bruker->b_id));
	} else {
		$wpdb->insert('ukm_brukere',$brukerinfo);
	}
	
	####################################
	## BLOGG-NAVN, URL ++
	####################################
	## Lag kommaseparert navneliste for bloggen
	$namelist .= ucfirst(($kommunenavn)) . ', ';
	$idlist .= $brukerinfo['wp_bid'] . ',';
	$rewrites[] = strtolower(UKMA_SEASON_urlsafe($kommunenavn));

	## Rydd i navneliste og id-liste 
	$namelist = substr($namelist, 0, strlen($namelist)-2);
	$idlist = substr($idlist, 0, strlen($idlist)-1);	
	
	return array('brukere'=>$userids, 'namelist'=>$namelist, 'idlist'=>$idlist, 'rewrites'=>$rewrites);
}

## HVIS KOMMUNEBRUKERE, FYLKEBRUKERE = INT FYLKESNUMMER
## HVIS FYLKESBRUKERE, KOMMUNEBRUKERE = INT 0
function UKMA_SEASON_evaluer_kommuner($kommunebrukere, $fylkebrukere) {
	global $wpdb;
	## OPPRETT KOMMUNEBRUKERE
	if(is_array($kommunebrukere)) {
		## LOOP ALLE KOMMUNER I MØNSTRINGEN
		foreach($kommunebrukere as $trash => $kommune) {
			####################################
			## BRUKERE
			####################################
			$password = UKM_ordpass();
			$bruker = $wpdb->get_row("SELECT * FROM `ukm_brukere`
									  WHERE `b_kommune` = '".$kommune['id']."'");
			if(is_object($bruker))
				$email = $bruker->b_email;
			else
				$email = strtolower($kommune['url']).'@falsk.ukm.no';

			$brukerinfo = array('b_name'=>ucfirst($kommune['url']),
								'b_password'=>$password,#wp_generate_password(6,false,false),
								'b_email'=>$email,
								'b_kommune'=>$kommune['id'],
								'b_fylke' => $fylkebrukere);
			
			if(username_exists( $brukerinfo['b_name'] )) {
				$userids[] = $brukerinfo['wp_bid'] = username_exists( $brukerinfo['b_name'] );
			} else {
				## OPPRETT BRUKERE
				$userid = wp_create_user($brukerinfo['b_name'], $brukerinfo['b_password'], $brukerinfo['b_email']);
				if(!is_numeric($userid))
					var_dump($userid);
				## LEGG TIL BRUKERID I FELLESARRAY + KLARTEKSTDATABASE
				$userids[] = $brukerinfo['wp_bid'] = $userid;
			}
			if(is_object($bruker)) {
				$wpdb->update('ukm_brukere',
							$brukerinfo,
							array('b_id'=>$bruker->b_id));
			} else {
				## LAGRE I KLARTEKSTTABELL
				$wpdb->insert('ukm_brukere',$brukerinfo);
			}
			####################################
			## BLOGG-NAVN, URL ++
			####################################
			## Lag kommaseparert navneliste for bloggen
			$namelist .= ucfirst(($kommune['name'])) . ', ';
			if(is_string($brukerinfo['wp_bid']))
				$idlist .= $brukerinfo['wp_bid'] . ',';
			$rewrites[] = strtolower($kommune['url']);
		}
	}
	
	## Rydd i navneliste og id-liste 
	$namelist = substr($namelist, 0, strlen($namelist)-2);
	$idlist = substr($idlist, 0, strlen($idlist)-1);	
	
	echo 'Kommuner: ' . $namelist;
	
	## 
	return array('brukere'=>$userids, 'namelist'=>$namelist, 'idlist'=>$idlist, 'rewrites'=>$rewrites);
}





################################################
## SIKRER EN STRENG FOR URL-BRUK
################################################
## !!! OBS !!!: KOPIERT TIL brukere_oppdater.php
## !!! OBS !!!: KOPIERT TIL UKM/inc/toolkit
function UKMA_SEASON_urlsafe($text) {
	
	$text = SMAS_encoding($text);

	$text = htmlentities($text);
	
	$ut = array('&Aring;','&aring;','&Aelig;','&aelig;','&Oslash;','&oslash;','&Atilde;','&atilde','Ocedil','ocedil');
	$inn= array('A','a','A','a','O','o','O','o','O','o');
	$text = str_replace($ut, $inn, $text);
	
	$text = preg_replace("/[^A-Za-z0-9-]/","",$text);

	return $text;
}

function UKMA_SEASON_rewrites($fylke, $froms, $pl_id) {
	global $wpdb;
	
	$fylke = strtolower(UKMA_SEASON_urlsafe($fylke));
	
	if(!is_array($froms)) {
		echo ' M&oslash;nstringen har ingen kommuner<br />';
	} else
	foreach($froms as $trash => $kommune) {
		$from = '/'.$fylke.'/'.$kommune.'/';
		$to = '/pl'.$pl_id.'/';
		
		echo 'Fra '. $from . ' til ' . $to . '<br />';
	
		if($wpdb->get_var('SELECT path FROM ukm_uri_trans WHERE path = "'.$from.'"'))
			$wpdb->update('ukm_uri_trans',array('realpath' => $to),array('path' => $from));
		else
			$wpdb->insert('ukm_uri_trans',array('path' => $from,'realpath' => $to));
	
	}

}


############################################################################################################################
## STANDARDINNHOLD


#################################################
## SETTER INN STANDARDINNHOLD I NYOPPRETTET BLOGG
#################################################
function UKMA_ny_sesong_standard_posts($site_id,$type){
	$pages = UKMA_ny_sesong_master_posts($type);
	switch_to_blog($site_id);
	$cat_defaults = array(
					  'cat_name' => 'Nyheter',
					  'category_description' => 'nyheter' ,
					  'category_nicename' => 'Nyheter',
					  'category_parent' => 0,
					  'taxonomy' => 'category');
	wp_insert_category($cat_defaults);
	foreach($pages as $page){
		wp_insert_post($page); # GET POSTS
	}
	## LEGGER TIL VISENG-FUNKSJONALITET PÅ SIDEN SOM ER VALGT SOM FORSIDE
	if($type == 'fylke') {
		add_post_meta(2, 'UKMviseng', 'fylkesside');
		add_post_meta(4, 'UKMviseng', 'program');
		add_post_meta(5, 'UKMviseng', 'pameldte');
	} else {
		add_post_meta(2, 'UKMviseng', 'lokalside');
		add_post_meta(5, 'UKMviseng', 'program');
		add_post_meta(6, 'UKMviseng', 'pameldte');
	}
	## GJØR FORSIDEN OM TIL FULLBREDDE
//		add_post_meta(2, '_wp_page_template','template-full-width.php');
	restore_current_blog();
}

################################################
## HENTER STANDARD-INNHOLD FRA RIKTIG MASTER
################################################
function UKMA_ny_sesong_master_posts($type){
	global $wpdb;
	if($type == 'kommune'){
		switch_to_blog(get_id_from_blogname('masterkommune'));
	}
	else{
		switch_to_blog(get_id_from_blogname('masterfylke'));
	}
	$return = '';
	$pages = $wpdb->get_results('SELECT post_title,post_name,post_content,post_type,post_status FROM '.$wpdb->posts,'ARRAY_A');
	## LEGGER TIL SPESIALFUNKSJONALITET PÅ SIDENE SOM FLYTTES OVER
	restore_current_blog();
	return $pages;
}

?>