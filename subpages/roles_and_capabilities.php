<?php
/*




###########################################################
###########################################################

		SE EGEN PLUGIN I NETWORK-ADMIN -> BRUKERE

###########################################################
###########################################################








function UKM_roles_and_capabilities_inner() {
	global $wp_roles;
	echo '<h2>UKM roller og rettigheter</h2>';
	
	$roles = $wp_roles->get_names();
	$ukmroles = array('ukm_videresending'=>'UKM Videresending',
					  'ukm_jurymedlem'=>'UKM Jurymedlem');

	$ukm_capabilities['ukm_videresending'] = array('videresending');
	$ukm_capabilities['ukm_jurymedlem'] = array('juryering');
		
	foreach($ukmroles as $name => $nicename) {

		if(true) {
#		if(isset($_GET['clean'])) {
			$wp_roles->remove_role( $name );
			echo 'Fjernet '.$nicename.'<br />';
		}
		
		if(!in_array($nicename, $roles)) {
			$editor=get_role('subscriber');
			$newrole=add_role($name,$nicename,$editor->capabilities);
			if($newrole && is_array($ukm_capabilities[$name]))
				foreach($ukm_capabilities[$name] as $capability)
					$newrole->add_cap('ukm_cap_'.$capability);
	
			echo 'Opprettet '. $nicename .' <br />';
		} else {
			echo '<strong>Rollen '.$nicename.'</strong> eksisterer med n&oslash;kkel '.$name.'<br />';
			if(is_array($ukm_capabilities[$name])){
				foreach($ukm_capabilities[$name] as $capability) {
					echo ' &nbsp; Capability '.$capability .' lagt til <br />';
					$role=get_role($name);
					$role->add_cap('ukm_cap_'.$capability, true);
				}
			} else {
				echo ' &nbsp; ingen capabilities<br />';
			}
		}
	}

	echo '<hr noshade />'
		.'<h3>Systemet har n&aring; registrert f&oslash;lgende roller</h3>';
    $roles = $wp_roles->roles;
	foreach($roles as $name => $nicename) {
		echo '<h3>'.$roles[$name]['name'].'</strong> ('.$name.')</h3>';
		$cap = $roles[$name]['capabilities'];
		var_dump($cap);
	}
}*/
?>