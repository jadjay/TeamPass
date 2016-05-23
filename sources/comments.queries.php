<?php
/**
 * @file          comments.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2015 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
require_once 'sessions.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "home")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

/**
 * Define Timezone
 */
if (isset($_SESSION['settings']['timezone'])) {
    date_default_timezone_set($_SESSION['settings']['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
require_once 'main.functions.php';

// Connect to mysql server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

// Do asked action
if (isset($_POST['type'])) {
    switch ($_POST['type']) {
        /*
        * CASE
        * building list of COMMENTs
        */
        case "list_comments":
			// Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }
			
			// load comments for this item
			$rows = DB::query(
				"SELECT u.avatar_thumb, u.lastname, u.name, c.user_id, c.comment_text, c.timestamp, c.id
				FROM ".prefix_table("comments")." AS c
				INNER JOIN ".prefix_table("users")." AS u ON (u.id = c.user_id)
				WHERE c.item_id=%i
				ORDER BY timestamp ASC", 
				intval($_POST['item_id'])
			);
			if (DB::count() == 0) {
				// no comments exist
				$html = '<div style="border: 1px #bdbbbb solid; padding: 10px 0px 10px 0px; text-align:center;"><b>'.$LANG['no_comment_exists'].'</b></div>';
			} else {
				$html = '
				<div style="font-weight:bold; size:14px;">'.DB::count().'&nbsp;'.$LANG['comments_on_this_item'].'</div>
				<hr color="#bdbbbb">';
				foreach ($rows as $record) {
					$avatar = isset($record['avatar_thumb']) && !empty($record['avatar_thumb']) ? 'includes/avatars/'.$record['avatar_thumb'] : './includes/images/photo.jpg';
					$cDate = isset($_SESSION['settings']['date_format']) ? date($_SESSION['settings']['date_format'], $record['timestamp']) : date("d/m/Y", $record['timestamp']);
					$cTime = isset($_SESSION['settings']['time_format']) ? date($_SESSION['settings']['time_format'], $record['timestamp']) : date("H:i:s", $record['timestamp']);
					
					// is Author or Manager?
					if ($record['user_id'] == $_SESSION['user_id'] || $_SESSION['user_manager'] == 1) {
						$canModify = "canModify_".$record['id'];
						$commentClass = "editable_textarea";
						$commentStyle = "cursor:pointer;";
					} else {
						$canModify = "";
						$commentClass = "";
						$commentStyle = "";
					}
					
					
					$html .= '
				<div class="comment" id="'.$canModify.'" style="background-color:#D4D5D5;">
					<table width="100%">
						<tr>
							<td width="50px">
								<img src="'.$avatar.'" style="border-radius:3px;" />
							</td>
							<td valign="middle">
								&nbsp;<b>'.$record['name'].' '.$record['lastname'].'</b>
								&nbsp;'.$LANG['has_added_a_comment'].'&nbsp;-&nbsp;
								'.$cDate.' '.$cTime.'
							</td>
							<td>
								<div id="commentaction_'.$record['id'].'" style="float:right;display:none;">
									<span class="fa fa-trash tip" style="cursor:pointer;" onclick="trashComment('.$record['id'].')" title="'.$LANG['delete'].'"></span>&nbsp;
								</div>
							</td>
						</tr>
						<tr>
							<td colspan="3">
								<i class="fa fa-quote-left fa-2x fa-pull-left fa-border" aria-hidden="true"></i>
								<span class="'.$commentClass.'" id="comment_text-'.$record['id'].'" style="'.$commentStyle.'">'.$record['comment_text'].'</span>
							</td>
						</tr>
					</table>
				<hr color="#bdbbbb">
				</div>';
				}
			}
			
			
			// Encrypt data to return
            echo prepareExchangedData(
				array(
					"error" => "", 
					"html" => $html
				), 
				"encode"
			);
		break;
		
        /*
        * CASE
        * creating a new COMMENT
        */
        case "add_comment":
			// Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");
			
			// ADD comment
			DB::insert(
				prefix_table("comments"),
				array(
					'comment_text' => $dataReceived['comment'],
					'item_id' => $dataReceived['item_id'],
					'user_id' => $_SESSION['user_id'],
					'timestamp' => time()
				   )
			);
			$commentId = DB::insertId();
			
			// number of Comments
			DB::query("SELECT * FROM ".prefix_table("comments")." WHERE item_id = %i", $dataReceived['item_id']);
			
			// get info about user
			
			// Encrypt data to return
            echo prepareExchangedData(
				array(
					"error" => "", 
					"number" => DB::count(),
					"comment_id" => $commentId,
					"cDate" => date($_SESSION['settings']['date_format']." ".$_SESSION['settings']['time_format'], time()),
					"userName" => $_SESSION['name']." ".$_SESSION['lastname'],
					"userAvatar" => isset($_SESSION['user_avatar_thumb']) && !empty($_SESSION['user_avatar_thumb']) ? 'includes/avatars/'.$_SESSION['user_avatar_thumb'] : './includes/images/photo_thumb.jpg'
				), 
				"encode"
			);
		break;
		
        /*
        * CASE
        * trash COMMENT
        */
        case "trash_comment":
			// Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }
			
			// delete comment in database
			DB::delete(
				prefix_table("comments"),
				"id = %i",
				$_POST['comment_id']
			);
			
			echo prepareExchangedData(array("error" => ""), "encode");
		break;
		
        /*
        * CASE
        * edit COMMENT
        */
        case "edit_comment":
			// Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }
			
		break;			
	}
}elseif (!empty($_POST['newValue'])) {
    $value = explode('-', $_POST['id']);
    DB::update(
        prefix_table("comments"),
        array(
            $value[0] => $_POST['newValue']
           ),
        "id = %i",
        $value[1]
    );
    // Display info
    echo $_POST['newValue'];
}