<?php
if(!isset($_GET['init'])) 
	die('Sorry mac');
	
function pd($var) {
	return'';
	echo '<pre>'; 
	var_dump($var);
	echo '</pre>';
}
error_reporting(E_ALL);
global $activePlaces_numRows, $activePlaces_numRowCounter, $newDeadline, $newFylkeDeadline;
### UKM SEASON-CREATE ###
# ACTIVE SEASON IS THE ONE YOU'RE LEAVING
# NEW SEASON IS THE ONE YOU'RE ACTIVATING
# NEW SEASON MUST BE CREATED THE YEAR BEFORE THE ACTIVE SEASON, AND IS MENT TO BE DONE WITHIN SEPTEMBER-OCTOBER

###################################################################################################
###################################################################################################
### STARTUP VARIABLES
###################################################################################################
###################################################################################################
#$pathcorrection = '';
#define('MEG', 'core/newSeason.php');
#require_once("../admin/loadSS3v4.php");
error_reporting(E_ALL);

# DATES OF THE SEASON
$activeSeason 			= (int)date("Y");#$ss3->getConf('smartukm_season');			# INT OF THIS SEASON
$activeStart 			= mktime(0,0,0,11,1,($activeSeason-1));					# THE FIRST NOVEMBER THE ACTIVE SEASON
$daysInActiveOctober 	= cal_days_in_month(CAL_GREGORIAN, 10, $activeSeason);	# THE LAST DAY OF THE ACTIVE SEASON
$activeStop 			= mktime(0,0,0,10, $daysInActiveOctober, $activeSeason);# THE DATE OF THE LAST DAY OF THE ACTIVE SEASON

# NEW SEASON
$newSeason 				= $activeSeason + 1;									# INT OF THE NEW SEASON
$newDeadline			= mktime(0,0,0,1,1,$newSeason);							# DEFAULT DEADLINE OF NEW SEASON, THE 1st OF JANUARY
$newFylkeDeadline 		= mktime(0,0,0,3,1,$newSeason);							# DEFAULT DEADLINE FOR FM THE NEW SEASON, THE 1st OF MARCH
$_GET['season'] = $newSeason;


###################################################################################################
###################################################################################################
### STEP 1 
###################################################################################################
###################################################################################################
# SELECT ALL PLACES
/*
$activePlaces = new SQL("SELECT `pl_id` 
				   FROM `smartukm_place`
				   WHERE `pl_start` > '#activeSeasonStart' AND `pl_start` < '#activeSeasonStop'",
				   array('activeSeasonStart'=>$activeStart, 'activeSeasonStop'=>$activeStop));
*/
$activePlaces = new SQL("SELECT `pl_id` 
				   FROM `smartukm_place`
				   WHERE `season` = '#sesong'",
				   array('sesong'=>$activeSeason));
$activePlaces_debug = $activePlaces->debug();
$activePlaces = $activePlaces->run();

$activePlaces_numRows = mysql_num_rows($activePlaces);
$activePlaces_numRowCounter = 0;

$ny_sesong_data = array('activeSeason' => $activeSeason,
						'activeStart' => $activeStart,
						'activeStop' => $activeStop,
						'newSeason' => $newSeason,
						'newDeadline' => $newDeadline,
						'newFylkeDeadline' => $newFylkeDeadline,
						'sql' => $activePlaces_debug,
						'numRows' => $activePlaces_numRows);
echo TWIG('ny_sesong/steg1_top.twig.html', $ny_sesong_data, dirname( dirname( __FILE__ ) ) );

# LOOP ALL PLACES AND CREATE DUPLICATES FOR THE NEW SEASON
#for($i=0; $i<$activePlaces[1]; $i++) {
while($r = mysql_fetch_assoc($activePlaces)){
	$land = false;
	$fylke = false;
	$kommune = false;
	
#	echo 'WHAT: '. var_export($r);
#	$ap = $r['pl_id'];
	
	$apdet = apdet($r['pl_id']);
	
	# IF IT IS LANDSMØNSTRING
	$land = ($apdet['placeAtt']['pl_fylke'] == '123456789' && $apdet['placeAtt']['pl_kommune'] == '123456789') ? true : false;
	# IF NOT LANDSMØNSTRING - FYLKE?
	if(!$land) {
		$fylke = (is_numeric($apdet['placeAtt']['pl_fylke']) && $apdet['placeAtt']['pl_fylke'] > 0)	? true : false;
	}
	# IF NEITHER FYLKE OR LANDSMØNSTRING : KOMMUNE
	if(!$land && !$fylke) {
		$kommune = $apdet['kommune_rel'];
	}
	
	# DO CREATE THE NEW PLACE
	$newID = createPlace($apdet['placeAtt'], $apdet['contactPs'], (($kommune !== false) ? $kommune : false), $apdet['old_pl_id']);
	# INSERT RELATION BETWEEN NEWPLACE AND THISPLACE
	createPlPlRel($newID, $r['pl_id']);
	# UPDATE USERS TO LOGON TO THE NEW PLACE
	updateUsers($newID, $r['pl_id']);
}


		
###################################################################################################
###################################################################################################
### STEP 1 FUNCTIONS
###################################################################################################
###################################################################################################
### CREATE THE NEW "DUPLICATE"
function createPlPlRel($newID, $oldID) {
	global $newSeason;
	
	$qry = new SQLins('smartukm_rel_pl_pl');
	$qry->add('pl_old', $oldID);
	$qry->add('pl_new', $newID);
	$qry->add('season', $_GET['season']);
#	echo $qry->debug();
	$res = $qry->run();
}

function updateUsers($newID, $oldID) {
	$qry = new SQLins('smartukm_user',array('pl_id'=>$oldID));
	$qry->add('pl_id', $newID);
#	echo $qry->debug();
	$res = $qry->run();
}

### CREATE THE NEW "DUPLICATE"
function createPlace($att, $contactPs, $kommunerel,$old_pl_id) {
	global $newDeadline, $newFylkeDeadline;

	# LOOP ALL ATTRIBUTES OF EXISTING PLACE
	foreach($att as $key => $val) {
		# CHECK WHAT ATTRIBUTE IT IS
		switch($key) {
			# UNSET THE PL ID - SHOULD NEVER BE INSERTED (WOULD CAUSE A BUG THEN..)
			case 'pl_id' : 					unset($att[$key]);					break;
			# SET TIME AND DATE, UNREGISTERED PARTICIPANTS, AUDIENCE TO BLANK
			case 'pl_start':
			case 'pl_stop':	
			case 'pl_public':
			case 'pl_missing':
											$att[$key] = 0;						break;
			# SET DEADLINES TO DEFAULT
			case 'pl_deadline':	
			case 'pl_deadline2':
				if($kommunerel === false)	$att[$key] = $newFylkeDeadline;
				else 						$att[$key] = $newDeadline;
				
																				break;
			# DEFAULT - PASS THE ATTRIBUTE FORWARD TO NEW SEASON
			default:						$att[$key] = $val;					break;
		}
	}
	$att['season'] = $_GET['season'];
#	echo "<h2>THE PLACE ". $att['pl_name'] . " (tidl ".$old_pl_id."):</h2>";
	$sql = new SQLins('smartukm_place');
	foreach($att as $key => $val) {
		$sql->add($key, utf8_encode($val));
		#echo "$key => $val<br />";	
	}
#	echo $sql->debug();
	$res = $sql->run();
	
	$id = $sql->insid();
	
	## INSERT ALL CONTACT-P RELATIONS
	for($i=0; $i < sizeof($contactPs); $i++) {
		$sql2 = new SQLins('smartukm_rel_pl_ab');
		$sql2->add('pl_id', $id);
		$sql2->add('ab_id', $contactPs[$i]);
		$sql2->add('order', $i);
#		echo $sql2->debug();
		$sql2->run();
	}
	#echo "<br />Inserted $i contact p relations";
	## INSERT ALL KOMMUNE RELATIONS
	$j = false;#'Dette er en fylkesm&oslash;nstring';
	if(is_array($kommunerel)) {
		for($j=0; $j < sizeof($kommunerel); $j++) {
			$sql3 = new SQLins('smartukm_rel_pl_k');
			$sql3->add('pl_id', $id);
			$sql3->add('k_id', $kommunerel[$j]);
			$sql3->add('season', $_GET['season']);
#			echo $sql3->debug();
			$sql3->run();
		}
	}
#	echo "<br />Inserted $j kommune relations";
	global $activePlaces_numRows, $activePlaces_numRowCounter;
	$activePlaces_numRowCounter++;
	$pldata = array_merge($att, array(	'type' => ($j === false ? 'fylke' : 'kommune'),
										'pl_id_old' => $old_pl_id,
										'num_contact_p_relations' => $i,
										'num_kommune_relations' => $j,
										'numRows' => $activePlaces_numRows,
										'numRowCounter' => $activePlaces_numRowCounter,

										'pl_id' => $id,
										'pl_name' => utf8_encode( $att['pl_name'] ),
										'pl_place' => utf8_encode( $att['pl_place'] ),

									)
						);
	
	echo TWIG('ny_sesong/steg1_monstring.twig.html', $pldata, dirname( dirname( __FILE__ ) ) );
	
	return $id;
} 

### GET ALL PLACE INFOS AS ARRAY
function apdet($ap_id) {
	global $newSeason;
	# GET THE PLACE
	$activePlace = new SQL("SELECT * FROM `smartukm_place` WHERE `pl_id` = '#pl_id'", array('pl_id'=>$ap_id));
	$activePlace = $activePlace->run('array');
	# GET ALL ATTRIBS AS EMPTY
	$placeAttrib = placeAttrib();
	# LOOP ALL DB FIELDS
	foreach($placeAttrib as $key => $trash) {
		if(isset($activePlace[$key])) $placeAttrib[$key] = $activePlace[$key];	
	}
	# GET ALL THE CONTACT PERSON-RELATIONS
	$contactPs = getContactPs($ap_id);
	# GET ALL KOMMUNE-RELATIONS FOR THE PLACE
	if(empty($placeAttrib['pl_fylke']) || $placeAttrib['pl_fylke'] == 0)
		$kommune = getKommuner($ap_id);
	else $kommune = false;
	
	return array('placeAtt'=>$placeAttrib, 'contactPs'=>$contactPs, 'kommune_rel'=>$kommune,'old_pl_id'=>$ap_id);
}

### GET ALL THE CONTACT PERSONS FOR THE PLACE
function getContactPs($ap_id) {
	$activeContacts = new SQL("SELECT `ab_id` FROM `smartukm_rel_pl_ab` WHERE `pl_id` = '#pl_id' ORDER BY `order` ASC", array('pl_id'=>$ap_id));
	$activeContacts = $activeContacts->run();
	
	$contacts = array();
	while($c = mysql_fetch_assoc($activeContacts)) 
		$contacts[] = $c['ab_id'];

	return $contacts;
}

### GET ALL THE KOMMUNE-RELATIONS OF THE PLACE
function getKommuner($ap_id) {
	$kommuner = new SQL("SELECT `k_id` FROM `smartukm_rel_pl_k` WHERE `pl_id` = '#pl_id'",
							array('pl_id'=>$ap_id));
	$kommuner = $kommuner->run();
	$rels = array();
	while($k = mysql_fetch_assoc($kommuner))
		$rels[] = $k['k_id'];
	
	
	return $rels;
}
### GET ALL PLACE ATTRIBS AS ARRAY
function placeAttrib() {
	$array = array();
	$keys = explode(',', 'pl_id,pl_name,pl_fylke,pl_kommune,pl_stop,pl_start,pl_place,'
					.'pl_deadline,pl_contact,pl_deadline2,pl_public,pl_missing,pl_form,'
					.'contactp_arrangor,contactp_konferansier,contactp_nettred,contactp_sceneteknikk');
	
	for($i=0; $i<sizeof($keys); $i++) $array[$keys[$i]] = '';
	return $array;
}