<?php 

## GET LIST OF mønstringer FOR A GIVEN SEASON AND RUNS UKMA_make_site_from_pl_id ON THEM
$nr_sites = 0;
## FØRSTE FUNKSJON SOM KJØRER. LOOPER ALLE MØNSTRINGER OG OPPRETTER SIDER, BRUKERE, POSTS OG PAGES
function UKMA_make_site_from_season($season){
	global $nr_sites;
	$pl = new monstringer();

	$monstring_liste = $pl->etter_sesong($season);
	$return = '';
	$n = 0;
	while($r = mysql_fetch_assoc($monstring_liste)) {
		$n++;
		$return .= UKMA_make_site_from_pl_id($r['pl_id']);
	}
	return UKMN_fieldset($lang['sesong'],'<h3>Disse Ble ikke laget</h3><ul>'.$return.'</ul>','740px;padding:5px;');
}

## MAKE USER -> STORE USERNAME PASSWORD FYLKE AND EMAIL IN ukm_brukere

function UKMA_lag_bruker($name,$fylke,$kommune){
	$user_id = username_exists( $name );
	if ( !$user_id ) {
		global $wpdb;
		# GET 
		$query 	= 'SELECT `username`,`email` FROM smartukm_user AS uu
				JOIN smartcore_users AS cu ON uu.ss3u_id = cu.id
				JOIN smartukm_place AS p ON uu.pl_id = p.pl_id
				LEFT JOIN smartukm_rel_pl_k AS plk ON p.pl_id = plk.pl_id';

		if($kommune == 0)
			$query .= ' where pl_fylke = '.$fylke;
		else
			$query .=' WHERE k_id ='.$kommune;
		
		$query = new SQL($query);
		$infos = $query->run('array');
		
		$registrert_epost = $infos['email'];
		
		$random_password = wp_generate_password(6,false,false);
				
		$brukerinfo = array('b_name'=>ucfirst($name),
							'b_password'=>$random_password,
							'b_email'=>$registrert_epost,
							'b_kommune'=>$kommune,
							'b_fylke' => $fylke);
		## LAGRE I KLARTEKSTTABELL
		$wpdb->insert('ukm_brukere',$brukerinfo);
		## OPPRETT BRUKERE
		$user_id = wp_create_user( $name, $random_password, 'kanin'.$fylke.'-'.$kommune.'@lala.no' );
	}
	return $user_id;
}

## MAKE REWRITE -> STORE IN ukm_uri_trans

function UKMA_make_uri($from,$to){
	global $wpdb;
	if($wpdb->get_var('SELECT path FROM ukm_uri_trans WHERE path = "'.$from.'"')){
		$wpdb->update('ukm_uri_trans',array('realpath' => $to),array('path' => $from));
	}
	else $wpdb->insert('ukm_uri_trans',array('path' => $from,'realpath' => $to));
}

## MAKE SITE FROM pl_id IN ukm_place -> ADD USERS AND STANDARD PAGES 
## ANDRE FUNKSJON, OPPRETTER EN GITT SIDE BASERT PÅ PL-ID
function UKMA_make_site_from_pl_id($pl_id){
	global $UKMN;
	global $nr_sites;
	$path = '/pl'.$pl_id.'/';
	
	## OM SIDEN IKKE EKSISTERER, OPPRETT DEN!
	if(!domain_exists($UKM['domain'], $path)) { # PRELIMINARY CHECK FOR EXISTING SITE
		## LASTER INFO OM MØNSTRINGEN
		$pl = new monstring($pl_id);
		$plinfo = $pl->info();
	
		## HVIS DET ER EN LOKALMØNSTRING
		if($plinfo['type'] == 'kommune'){# CHECK TO SEE IF mønstring IS kommune OR fylke, fylke mønstringer DO NOT HAVE kommuner

			## VARIABLER SOM BRUKES TIL Å OPPRETTE BRUKERE OG SIDE
			$k_id_list = ''; # VARIABLE TO STORE kommune ids FOR META INSERTION
			$k_name_list = ''; # VARIABLE TO STORE kommune names FOR META INSERTION
			$k_bruker_navn = array(); # VARIABLE TO STORE USER IDS TO INSERT INTO SITE

			## LOOPER ALLE KOMMUNER PÅ LETING ETTER BRUKERNAVN
			foreach($plinfo['kommuner'] as $trash => $kommune) {
				$navn = UKMN_navn($kommune['name']);
				if(empty($navn)) continue; # kommunen "å" kan risikere å ikke ha navn?
				$k_id_list .= $kommune['id'];
				$k_bruker_navn[] = UKMA_lag_bruker($navn,null,$kommune['id']); #(navn,fylke,kommune)
				$k_name_list .= $kommune['name'];
				$k_name_list_array[] = $kommune['name'];
			}
			## RENSER KOMMUNE-LISTER
			$k_id_list = trim($k_id_list,',');
			$k_name_list = trim($k_name_list,',');
			$k = $k_id_list.':'.$k_name_list.',';
			## SETTER INFO OM FYLKET (DET FYLKET DEN SISTE KOMMUNEN TILHØRER)
			$f_id = $plinfo['fylke_id'];
			$f_name = $plinfo['fylke_name'];
			$fylke_urlsafe_name = UKMN_navn($f_name);	

			##
			## OM SIDEN IKKE FINNES FRA FØR, OPPRETT DEN!
			if(!domain_exists($UKM['domain'], $path)){					
				$blog_id = create_empty_blog($UKM['domain'],$path,$plinfo['pl_name']);
				UKMA_ny_sesong_standard_posts($blog_id,'kommune');

				# ADDS META OPTIONS TO NEW SITE
				$meta = array(
					 'fylke'=>$f_id
					,'kommuner'=>$k_id_list
					,'site_type'=>'kommune'
					,'pl_id' => $pl_id
					,'ukm_pl_id' => $pl_id
					,'season' => $plinfo['season']);
				## LEGGER TIL ALLE META-INNSTILLINGER
				foreach($meta as $key => $value){
					add_blog_option($blog_id, $key, $value);
				}
			
				# ADDS USERS TO NEW SITE
				$n = 0;
				foreach($k_bruker_navn as $brukerid){
					## I TILFELLE KOMMUNENAVNET INNEHOLDER PARENTES "NES (AKERSHUS)"
					## BRUK KUN FØRSTE DEL
					$k_name = explode(' (',$k_name_list_array[$n]);
					$k_name = UKMN_navn($k_name[0]);
					
					echo '/'.$fylke_urlsafe_name.'/'.$k_name.'/';
					
					## LEGGER TIL URI REWRITE FOR ENKEL TILGANG TIL SIDEN
					UKMA_make_uri('/'.$fylke_urlsafe_name.'/'.$k_name.'/','/pl'.$pl_id.'/'); # ADDS URI REWRITE
					## LEGGER TIL BRUKEREN TIL BLOGGEN
					add_user_to_blog($blog_id, $brukerid, 'editor');
					$n++;
				}
				
				## LEGG TIL OVERORDNEDE BRUKERE TIL BLOGGEN (så man kan logge ned)
				# hvis lokalsiden blir lagd før fylkessiden, lag og legg til fylkesbrukeren
				$fylke = UKMA_lag_bruker($navn,$plinfo['f_id'],0);
				add_user_to_blog($blog_id, $fylke, 'editor');
				
				## legger til UKM-Norgebrukeren
				add_user_to_blog($blog_id, 1, 'administrator');
				
				## Teller antall sites
				$nr_sites++;
			} ## SIDEN ER FAKTISK OPPRETTET TIDLIGERE...? 
		}
		
		
		### HVIS DET ER EN FYLKESMØNSTRING
		else if($plinfo['f_id']!=0){# TESTS IF IT IS A fylke
			# ADD EMPTY NEW SITE
			$navn = UKMN_navn($plinfo['pl_name']);		
			$path = '/'.$navn.'/';
			
			## OM SIDEN IKKE EKSISTERER, OPPRETT DEN! (precaution?)
			if(!domain_exists($UKM['domain'], $path)){
				## OPPRETT BLOGG, OG FINN ID
				$blog_id = create_empty_blog($UKM['domain'],$path,$plinfo['pl_name']);
				## LEGG TIL STANDARDINNHOLD
				UKMA_ny_sesong_standard_posts($blog_id,'fylke');
				## LEGG TIL META-DATA
				$meta = array(
					 'fylke'=>$plinfo['fylke_id']
					,'site_type'=>'fylke'
					,'pl_id' => $pl_id
					,'ukm_pl_id' => $pl_id
					,'season' => $plinfo['season']);
				## FAKTISK LEGG TIL
				foreach($meta as $key => $value){
					add_blog_option($blog_id, $key, $value);
				}
				
				# ADDS USERS TO NEW SITE
				$bruker = UKMA_lag_bruker($navn,$plinfo['f_id'],0);
				add_user_to_blog($blog_id, $bruker, 'administrator');
				add_user_to_blog($blog_id, 1, 'administrator');
				$nr_sites++;
			}
		}
		## SIDEN FINNES IKKE, MEN HAR HELLER IKKE BLITT OPPRETTET (LANDSMØNSTRING?)
		else return '<li id="'.$pl_id.'">Mønstring '.$pl_id.' navn: '.$plinfo['pl_name'].'</li>';
	}
	## SIDEN FINNES - INGENTING BLIR GJORT :)
}

## ADDS STANDARD POST AND PAGES FROM PRESENTED ARRAY

function UKMA_ny_sesong_standard_posts($site_id,$type){
	$pages = UKMA_ny_sesong_master_posts($type);
	switch_to_blog($site_id);
	foreach($pages as $page){
		wp_insert_post($page); # GET POSTS
	}
	restore_current_blog();
}

## GET STANDARD POST AND PAGES FROM CORRECT MASTER SITE
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
	restore_current_blog();
	return $pages;
}
?>