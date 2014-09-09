<?php
/* 
Part of: UKMAdmin
Description: Gjør det mulig å legge til / oppdatere en option på en av systemets sites
Author: UKM Norge / M Mandal
Version: 1.0 
*/

if(isset($_POST) && sizeof($_POST) > 0){
	update_blog_option($_POST['site'], $_POST['option'], $_POST['value']);
	echo '<h2>Site oppdatert!</h2>'
		. 'Oppdaterte '. get_blog_option($_POST['site'], 'blogname') 
		. ', og la til innstillingen <strong>'. $_POST['option'] .'</strong>'
		. ' med verdien &quot;<strong><em>'.$_POST['value'].'</em></strong>&quot;';
} else {
	echo '<h2>Sett / oppdater en blog option</h2>';

	## FINN ALLE BLOGGER
	$blog_list = get_blog_list( 0, 'all' );
	foreach ($blog_list AS $blog)
		$options .= '<option value="'.$blog['blog_id'].'">'.get_blog_option($blog['blog_id'],'blogname').'</option>';


	echo '<form action="'.$_SERVER['PHP_SELF'].'?page=UKMA_setOpt" method="POST">'
		.'<label>Velg side</label>'
		.'<select name="site">'
		. $options
		.'</select>'
		
		.'<br />'
		.'<label>Option-navn</label>'
		.'<input type="text" name="option" />'
		.'<br />'	
		.'<label>Option-verdi</label>'
		.'<input type="text" name="value" />'
	
		.'<br /><br />'
		.'<input type="submit" value="Lagre" />';
}
?>