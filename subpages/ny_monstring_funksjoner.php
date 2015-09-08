<?php
################################################
## LAGER BRUKERNE
################################################
function UKMA_SEASON_brukere($blogg, $brukere, $fylke, $fylkebrukere) {
	if( !is_array( $brukere )) {
		echo ' &nbsp; <span class="alert-danger">Oppretter blogg uten bruker-array. Lokalkontakt får ikke tilgang til siden!</span><br />';
		$brukere = array();
	}
	$brukere[] = $fylkebrukere[$fylke];
	
	for($i=0; $i<sizeof($brukere); $i++) {
		$result = add_user_to_blog($blogg, $brukere[$i], 'editor');
		if(is_bool($result) || $res) {
		    echo ' &nbsp; Legger til &quot;editor&quot; i blogg '.$blogg.' <span class="badge">WP_UID: '. $brukere[$i] .'</span><br />';
		    echo ' &nbsp; &nbsp; Fjerner brukeren fra blogg 1 <br />';
			remove_user_from_blog($brukere[$i], 1);
		} else {
			echo ' &nbsp; <span class="alert-danger">Kunne ikke legge til bruker til blogg '.$blogg.'</span> <span class="badge">'. $brukere[$i] .'</span><br />';
			echo ' <pre>'; var_dump( $result->errors ); echo '</pre><br />';		
	    }
	}
	## Legg til UKM Norge
	add_user_to_blog($blogg, 1, 'administrator');
    echo ' &nbsp; Legger til &quot;administrator&quot; i blogg '.$blogg.' <span class="badge">UKM Norge</span><br />';
}
function UKMA_SEASON_fylkesbrukere( $echo ) {
	global $wpdb;
	$fylker = new SQL("SELECT * FROM `smartukm_fylke`");
	$fylker = $fylker->run();
	## LOOPER ALLE FYLKER OG OPPRETTER BRUKER OM DEN IKKE FINNES
	while($f = mysql_fetch_assoc($fylker)) {
		$twigdata = [];
		
		$name = UKMA_SEASON_urlsafe_non_charset($f['name']);
		$password = UKM_ordpass();
		$bruker = $wpdb->get_row("SELECT * FROM `ukm_brukere`
									  WHERE `b_fylke` = '".$f['id']."'");
		if(is_object($bruker)) {
			$email = $bruker->b_email;
		} else {
			$email = UKMA_SEASON_urlsafe_non_charset($f['name']) .'@fylkefake.'.UKM_HOSTNAME;
		}
		
		$twigdata['name'] = $name;
		$twigdata['password'] = $password;
		$twigdata['email'] = $email;
		$twigdata['existing'] = username_exists( $name );
		
		## Om brukeren finnes, legg til ID i array og gå pent videre
		if(username_exists( $name )) {
			$userIDnow = username_exists($name);
			$users[$f['id']] = $userIDnow;
			remove_user_from_blog($userIDnow, 1);
			$twigdata['id'] = $userIDnow;
			
			wp_set_password( $password, $userIDnow );
			$wpdb->update('ukm_brukere', array('b_password' => $password ), array('wp_bid' => $userIDnow ));
		} else {
			$brukerinfo = array('b_name'=>$name,
								'b_password'=>$password,#wp_generate_password(6,false,false),
								'b_email'=>$email,
								'b_kommune'=>0,
								'b_fylke' => $f['id']);
			$user_id = wp_create_user( $brukerinfo['b_name'], $brukerinfo['b_password'], $brukerinfo['b_email'] );

			if(!is_string($user_id)&&!is_numeric($user_id)) {
				$twigdata['error'] = var_export( $user_id, true );
			} else {
				$twigdata['error'] = false;
			}
			## Oppdater klartekstarray
			$brukerinfo['wp_bid'] = $user_id;
			
			## LAGRE I KLARTEKSTTABELL
			if(is_object($bruker)) {
				$twigdata['insert'] = false;
				$wpdb->update('ukm_brukere',
						  $brukerinfo,
						  array('b_id'=>$bruker->b_id));
			} else {
				$twigdata['insert'] = true;
				$wpdb->insert('ukm_brukere',$brukerinfo);
			}
			## OPPRETTHOLD LISTE OVER FYLKESBRUKERE
			$users[$f['id']] = $user_id;
			remove_user_from_blog($user_id, 1);
			$twigdata['id'] = $user_id;
		}
		if( $echo ) {
			echo TWIG('ny_sesong/fylkesbrukere.twig.html', $twigdata, dirname( dirname( __FILE__ ) ) );
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
			$safestring = preg_replace("/[^A-Za-z0-9-]/","",
								str_replace(array('æ','ø','å','Æ','Ø','Å'),
											array('a','o','a','A','O','A'), 
											$k['name'])
									  );
			$k['url'] = $safestring; #UKMA_SEASON_urlsafe($k['name']);
			$list[] = $k;
		}
	return $list;
}
################################################
## OPPPRETTER BLOGGEN, HVIS DEN IKKE FINNES
################################################
function UKMA_SEASON_opprett_blogg($navn, $pl_id, $type, $fylkeid, $kommuneider='', $season){
	## KALKULER PATH
	if($type == 'kommune')
		$path = '/pl'.$pl_id.'/';
	else
		$path = '/'.strtolower(UKMA_SEASON_urlsafe(utf8_encode($navn))).'/';
	
	echo ' &nbsp; '. $path .'<br />';
	
	## SJEKEKR OM BLOGGEN FINNES, HVIS IKKE - OPPRETT
	if(!domain_exists(UKM_HOSTNAME, $path)){
		echo ' &nbsp; opprettes<br />';
		## OPPRETT BLOGG
		$blog_id = create_empty_blog(UKM_HOSTNAME,$path,$navn);
		
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
	} else  {
		$blog_id = domain_exists(UKM_HOSTNAME, $path);
		echo ' &nbsp; <span class="label label-danger">eksisterer! </span><span class="badge">BLOG_ID: '. $blog_id .'</span> <br />';
	}
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
							'b_email'=>strtolower(UKMA_SEASON_urlsafe($kommunenavn)).'@fake.'.UKM_HOSTNAME,
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
				$email = strtolower($kommune['url']).'@falsk.'.UKM_HOSTNAME;
			
			$brukerinfo_b_name = ucfirst(strtolower($kommune['url']));
			$brukerinfo = array('b_name'=>$brukerinfo_b_name,
								'b_password'=>$password,#wp_generate_password(6,false,false),
								'b_email'=>$email,
								'b_kommune'=>$kommune['id'],
								'b_fylke' => $fylkebrukere);
			
			echo ' &nbsp; '. $brukerinfo_b_name;
			if(username_exists( $brukerinfo['b_name'] )) {
				$userids[] = $brukerinfo['wp_bid'] = username_exists( $brukerinfo['b_name'] );
				echo ' eksisterer, og passordet er oppdatert';
				wp_set_password( $password, $brukerinfo['wp_bid'] );
			} else {
				echo ' opprettes ';
				## OPPRETT BRUKERE
				$userid = wp_create_user($brukerinfo['b_name'], $brukerinfo['b_password'], $brukerinfo['b_email']);
				if(!is_numeric($userid)) {
					echo '<div class="alert alert-danger">'
						.'ERROR: Kunne ikke opprette bruker av følgende årsak:'
						.'<pre>';
					var_dump($userid);
					echo '</pre>ERRORDATA: Følgende array ble gitt til wp_create_user<pre>';
					var_dump($brukerinfo);
					echo '</pre></div>';
				} else {
					## LEGG TIL BRUKERID I FELLESARRAY + KLARTEKSTDATABASE
					$userids[] = $brukerinfo['wp_bid'] = $userid;
				}
			}
			if( is_string( $brukerinfo['wp_bid'] ) ) {
				echo ' <span class="badge badge-success">WP_UID: '. $brukerinfo['wp_bid'] .'</span>';
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
			if(is_string($brukerinfo['wp_bid']) || is_numeric( $brukerinfo['wp_bid']) )
				$idlist .= $brukerinfo['wp_bid'] . ',';
			$rewrites[] = strtolower($kommune['url']);
			echo '<br />';
		}
	}
	
	## Rydd i navneliste og id-liste 
	$namelist = substr($namelist, 0, strlen($namelist)-2);
	$idlist = substr($idlist, 0, strlen($idlist)-1);	
		
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
function UKMA_SEASON_urlsafe_non_charset($text) {
	$text = utf8_encode($text);
	$text = htmlentities($text);

	$ut = array('&Aring;','&aring;','&Aelig;','&aelig;','&Oslash;','&oslash;','&Atilde;','&atilde','Ocedil','ocedil');
	$inn= array('A','a','A','a','O','o','O','o','O','o');
	$text = str_replace($ut, $inn, $text);
	
	$text = preg_replace("/[^A-Za-z0-9-]/","",$text);
	return $text;
}

function UKMA_SEASON_rewrites($fylke, $froms, $pl_id) {
	global $wpdb;
	$fylke = strtolower(UKMA_SEASON_urlsafe_non_charset($fylke));
		
	if(!is_array($froms)) {
		echo ' &nbsp; <span class="alert-danger">M&oslash;nstringen har ingen kommuner</span>';
	} else {
		foreach($froms as $trash => $kommune) {
			$from = '/'.$fylke.'/'.$kommune.'/';
			$to = '/pl'.$pl_id.'/';
			
			echo ' &nbsp; Fra '. $from . ' til ' . $to . '';
			if( empty( $fylke ) ) {
				echo ' &nbsp; <span class="alert-danger">Fylke mangler i URL-rewrite. Denne vil derfor ikke fungere</span>';
			}

		
			if($wpdb->get_var('SELECT path FROM ukm_uri_trans WHERE path = "'.$from.'"'))
				$wpdb->update('ukm_uri_trans',array('realpath' => $to),array('path' => $from));
			else
				$wpdb->insert('ukm_uri_trans',array('path' => $from,'realpath' => $to));
				
			echo '<br />';
		}
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