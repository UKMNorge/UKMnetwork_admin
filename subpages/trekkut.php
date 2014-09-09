<?php

function UKMA_trekkut_steg2($plid, $kid) {
	$kommune = new SQL("SELECT `name` FROM `smartukm_kommune` WHERE `id` = '#id'",
					  array('id'=>$kid));
	$kommune = utf8_encode($kommune->run('field','name'));
	
	if($plid == 0 || $kid == 0)
		die('Beklager, feil info!');
	
	echo '<h2>Trekk kommune '. $kommune . ' ('.$kid .') ut av en fellesm&oslash;nstring</h2>';
	// Finn blog ID
	$blogg = get_blog_details(array('domain'=>'ukm.no','path'=>'/pl'.$plid.'/'));
	
	if(!is_object($blogg) || $blogg->blog_id == 0)
		die('Beklager, fant ikke blogg!');
	
	// Korriger site-navn, beskrivelse og kommune-ID
	$options['blogdescription'] = get_blog_option($blogg->blog_id, 'blogdescription');
	$options['blogdescription'] = str_replace(array($kommune.' og', $kommune.', ', $kommune),'', $options['blogdescription']);

	$options['blogname']		 = get_blog_option($blogg->blog_id, 'blogname');
	$options['blogname']		 = str_replace(array($kommune.', ', $kommune), '', $options['blogname']);
	
	$options['kommuner']		 = get_blog_option($blogg->blog_id, 'kommuner');
	$options['kommuner']		 = str_replace(array($kid.',',$kid), '', $options['kommuner']);
	
	foreach($options as $option => $value) {
		echo 'update_blog_option('. $blogg->blog_id . ', '. $option . ', '. $value . ')<br />';
		update_blog_option($blogg->blog_id, $option, $value);
	}
	
	$qry = new SQL("SELECT * FROM `smartukm_rel_pl_k`
					WHERE `pl_id` = '#plid'
					AND `k_id` = '#kid'
					AND `season` = '#season'",
					array('plid'=>$plid, 'kid'=>$kid, 'season'=>get_option('season')));
	$res = $qry->run('array');
	if(is_array($res) && isset($res['pl_k_id']) && $res['pl_k_id'] > 0) {
		$delRel = new SQLdel('smartukm_rel_pl_k', $res);
		$delRel->run();
		echo 'Slettet relasjon mellom kommune og m&oslash;nstring <br />';
	} else {
		echo '<div class="error">Fant ingen relasjon &aring; slette mellom m&oslash;nstringen og kommunen!</div>';
	}
	
	
	// Oppdater pl_name for mønstringen
	$plname = new SQL("SELECT `pl_name` FROM `smartukm_place` WHERE `pl_id` = '#plid'",
					array('plid'=>$plid));
	$plname = $plname->run('field', 'pl_name');
	
	$updName = new SQLins('smartukm_place', array('pl_id'=>$plid));
	$updName->add('pl_name', str_replace(array(' og '.$kommune, $kommune.' og', $kommune.', ', $kommune),'', utf8_encode($plname)));
	#echo $updName->debug();
	if($plid > 0) {
		$updNameRes = $updName->run();
		echo 'Oppdaterte pl_name i databasen<br />';
	} else {
		echo '<div class="error">Kunne ikke oppdatere pl_name i databasen</div>';
	}
	// Finn og fjern kontaktpersoner for denne kommunen
	$kontakter = new SQL("SELECT `smartukm_rel_pl_ab`.`pl_ab_id`,
								 `smartukm_contacts`.`name`,
								 `smartukm_contacts`.`kommune`
						  FROM `smartukm_rel_pl_ab`
						  JOIN `smartukm_contacts` ON (`smartukm_contacts`.`id` = `smartukm_rel_pl_ab`.`ab_id`)
						  WHERE `smartukm_rel_pl_ab`.`pl_id` = '#plid'
						  AND `smartukm_contacts`.`kommune` = '#kid'",
						  array('plid'=>$plid, 'kid'=>$kid));
	$kontakter = $kontakter->run();
	if(mysql_num_rows($kontakter)==0)
		echo 'Fant ingen kontaktpersoner knyttet spesielt til kommunen';
	while($k = mysql_fetch_assoc($kontakter)) {
		echo 'Fant kontaktperson '. utf8_encode($k['name']). ' ('.$k['kommune'].') med pl_ab_id '. $k['pl_ab_id'].' <br />';
		if(isset($k['pl_ab_id']) && $k['pl_ab_id'] > 0) {
			$delC = new SQLdel('smartukm_rel_pl_ab', array('pl_ab_id'=>$k['pl_ab_id']));
			$delRes = $delC->run();	
			echo 'Slettet relasjon ('.$k['pl_ab_id'].') mellom kontaktperson og m&oslash;nstring<br />';
		}
	}
	
	echo '<div class="error">BRUKERE ER IKKE FJERNET FRA BLOGGEN!</div>';
	
	echo '<br /><div class="success">Kommunen er fjernet fra m&oslash;nstringen!</div>';
}


function UKMA_trekkut_steg1(){
	UKM_loader('sql');
	
	$qry = new SQL("SELECT `rel`.`pl_id`,
						   `smartukm_place`.`pl_name`,
						   `smartukm_kommune`. `name`,
						   `smartukm_kommune`.`id`
					FROM `smartukm_rel_pl_k` AS `rel`
					JOIN `smartukm_place` ON (`smartukm_place`.`pl_id` =  `rel`.`pl_id`)
					JOIN `smartukm_kommune` ON (`smartukm_kommune`.`id` = `rel`.`k_id`)
					WHERE `smartukm_place`.`season` = '#season'
					ORDER BY `smartukm_kommune`.`name` ASC",
					array('season'=>get_option('season')));
	$res = $qry->run();
	
	echo '<h2>Trekk en kommune ut av en m&oslash;nstring</h2>';
	
	while($r = mysql_fetch_assoc($res)) {
		$test = new SQL("SELECT `k_id` FROM `smartukm_rel_pl_k`
						 WHERE `pl_id` = '#plid'
						 AND `k_id` != '#kid'",
						 array('plid'=>$r['pl_id'], 'kid'=>$r['id']));
		$test = $test->run();
		if(mysql_num_rows($test)>0)
			echo '<a href="?page='.$_GET['page'].'&kid='.$r['id'].'&pl_id='.$r['pl_id'].'">'
				. utf8_encode($r['name'])
				.'</a>'
				. ' <em>fra</em> ' . utf8_encode($r['pl_name']) . ' ('.$r['pl_id'].')' . '<br />';
	}
}

?>