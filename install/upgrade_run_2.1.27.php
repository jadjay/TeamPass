<?php
/*
** For release 2.1.27
*/
require_once('../sources/sessions.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/include.php';
require_once '../includes/settings.php';
require_once '../sources/main.functions.php';
require_once 'upgrade.functions.php';

$_SESSION['settings']['loaded'] = "";
$return_error = "";
$finish = false;
$next = ($_POST['nb']+$_POST['start']);

if (!isset($_SESSION['settings']['cpassman_dir']) || empty($_SESSION['settings']['cpassman_dir'])) {
	$_SESSION['settings']['cpassman_dir'] = "..";
}

//include librairies
require_once '../includes/libraries/Tree/NestedTree/NestedTree.php';

//Build tree
$tree = new Tree\NestedTree\NestedTree(
	$_SESSION['tbl_prefix'].'nested_tree',
	'id',
	'parent_id',
	'title'
);

// dataBase
$res = "";

mysqli_connect(
	$_SESSION['db_host'],
	$_SESSION['db_login'],
	$_SESSION['db_pw'],
	$_SESSION['db_bdd'],
	$_SESSION['db_port']
);
$dbTmp = mysqli_connect(
	$_SESSION['db_host'],
	$_SESSION['db_login'],
	$_SESSION['db_pw'],
	$_SESSION['db_bdd'],
	$_SESSION['db_port']
);

// change upgrade_needed to 1 for all users
if(!isset($_SESSION['upgrade']['users_field__upgrade_needed']) || $_SESSION['upgrade']['users_field__upgrade_needed'] != 1) {
	$res = mysqli_query($dbTmp,
		"UPDATE `".$_SESSION['tbl_prefix']."users`
		SET `upgrade_needed` = '1'"
	);
	
	if ($res) {
		$_SESSION['upgrade']['users_field__upgrade_needed'] = 1;
	} else {
		echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
		exit();
	}
}

// add field timestamp to cache table
if(!isset($_SESSION['upgrade']['add_field__encryption_protocol__in_items']) || $_SESSION['upgrade']['add_field__encryption_protocol__in_items'] != 1) {
	$res = addColumnIfNotExist(
		$_SESSION['tbl_prefix']."items",
		"encryption_protocol",
		"varchar(20) NOT null DEFAULT 'none'"
	);
	if ($res === false) {
		echo '[{"finish":"1", "msg":"", "error":"An error appears when adding field encryption_protocol to table Items! '.mysqli_error($dbTmp).'!"}]';
		mysqli_close($dbTmp);
		exit();
	} else {
		$_SESSION['upgrade']['add_field__encryption_protocol__in_items'] = 1;
	}
}

// add new table COmments
if(!isset($_SESSION['upgrade']['table_comments']) || $_SESSION['upgrade']['table_comments'] != 1) {
	mysqli_query($dbTmp,
		"CREATE TABLE IF NOT EXISTS `".$_SESSION['tbl_prefix']."comments` (
		`id` tinyint(15) NOT NULL AUTO_INCREMENT,
		`item_id` tinyint(15) NOT NULL,
		`user_id` tinyint(15) NOT NULL,
		`comment_text` text NOT NULL,
		`timestamp` varchar(50) NOT NULL
		PRIMARY KEY (`id`)
		);"
	);
	$_SESSION['upgrade']['table_comments'] = 1;
}


/* ************************ */
// INTERATIVE PART OF THE UPGRADE

// change encryption of public items

// get total items
$rows = mysqli_query($dbTmp,
    "SELECT id, pw, pw_iv FROM ".$_SESSION['tbl_prefix']."items
    WHERE perso = '0'"
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}
$total = mysqli_num_rows($rows);

// loop on items
$rows = mysqli_query($dbTmp,
    "SELECT id, pw, pw_iv FROM ".$_SESSION['tbl_prefix']."items
    WHERE perso = '0' AND encryption_protocol != '".ENCRYPTION_PROTOCOL."'
	LIMIT ".$_POST['start'].", ".$_POST['nb']
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}

while ($data = mysqli_fetch_array($rows)) {
	if (strstr($data['pw_iv'], "def00000") === false) {
		// get previous item pwd
		$pw_old = cryption_phpCrypt($data['pw'], SALT, $data['pw_iv'], "decrypt" );
		
		// encrypt with new protocol
		$encrypt = cryption($pw_old['string'], "", "", "encrypt");
		
		// store encrypt
		$res = mysqli_query($dbTmp,
			"UPDATE ".$_SESSION['tbl_prefix']."items
			SET pw = '".$encrypt['string']."',pw_iv = '".$encrypt['iv']."',encryption_protocol = '".ENCRYPTION_PROTOCOL."'
			WHERE id=".$data['id']
		);
		if (!$res) {
			echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
			exit();
		}
		
		// *** change log
		$resData = mysqli_query($dbTmp,
			"SELECT id_item, raison, raison_iv, date AS mDate, id_user, action
			FROM ".$_SESSION['tbl_prefix']."log_items
			WHERE id_item = ".$data['id']." AND raison LIKE 'at_pw :%'"
		);
		if (!$resData) {
			echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
			exit();
		}
		while ($record = mysqli_fetch_array($resData)) {
			// explode string
			$reason = explode(' :', $record['raison']);
			
			// get previous item pwd
			$pw_old = cryption_phpCrypt(trim($reason['1']), SALT, $record['raison_iv'], "decrypt" );
			
			// encrypt with new protocol
			$encrypt = cryption($pw_old['string'], "", "", "encrypt");
			
			// store change
			$tmp = mysqli_query($dbTmp,
				"UPDATE ".$_SESSION['tbl_prefix']."log_items
				SET raison = 'at_pw : ".$encrypt['string']."', raison_iv = '".$encrypt['iv']."'
				WHERE id_item =".$data['id']." AND date='".$record['mDate']."'
				AND id_user=".$record['id_user']." AND action ='".$record['action']."'"
			);
			if (!$tmp) {
				echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
				exit();
			}
		}
		
		
		// *** change category fields encryption
		$resData = mysqli_query($dbTmp,
			"SELECT data, data_iv
			FROM ".$_SESSION['tbl_prefix']."categories_items
			WHERE item_id = ".$data['id']
		);	
		if (!$resData) {
			echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
			exit();
		}
		while ($record = mysqli_fetch_array($resData)) {
			// get previous item pwd
			$pw_old = cryption_phpCrypt($record['data'], SALT, $record['data_iv'], "decrypt" );
			
			// encrypt with new protocol
			$encrypt = cryption($pw_old['string'], "", "", "encrypt");
			
			// store change
			$tmp = mysqli_query($dbTmp,
				"UPDATE ".$_SESSION['tbl_prefix']."categories_items
				SET data = '".$encrypt['string']."', data_iv = '".$encrypt['iv']."'
				WHERE item_id =".$data['id']
			);
			if (!$tmp) {
				echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
				exit();
			}
		}
	}
}
if ($next >= $total) {
    $finish = 1;
}

echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":"" , "total":"'.$total.'"}]';