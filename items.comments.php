<?php
/**
 *
 * @file          items.comments.php
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
require_once('./sources/sessions.php');
session_start();
if (
    (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
        !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
        !isset($_SESSION['key']) || empty($_SESSION['key'])) &&
    $_GET['key'] != $_SESSION['key'] && !isset($_GET['id']))
{
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

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
	
echo '<input id="item_id" type="hidden" value="'.$_GET['id'].'" />';

echo '<div id="cList"></div>';

echo '</div>
	<div id="comments_system_add" style="margin-top:20px;">
		<div style="font-weight:bold; size:16px; margin-bottom:2px;">
			<span class="fa fa-commenting-o"></span>&nbsp;'.$LANG['participate_to_discussion'].'
		</div>
		<textarea id="comments_system_add_text" rowspan="3" style="width:100%;"></textarea>
		<div style="margin-top:2px;">
			<span id="comment_add_button" class="button">'.$LANG['add_comment'].'</span>
			&nbsp;<i id="comment_add_spin" style="display:none;float:right;margin-right:5px;" class="fa fa-cog fa-spin mi-red"></i>
			<span id="comment_add_error"></span>
		</div>
	</div>';
	
?>
<script type="text/javascript">
function trashComment(id) {
	$.post(
		"sources/comments.queries.php",
		{
			type   		: "trash_comment",
			comment_id  : id,
			key    		: "<?php echo $_SESSION['key'];?>"
		},
		function(data) {
			//decrypt data
			try {
				data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key'];?>");
			} catch (e) {
				// error
				$("#comment_add_error").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data);

				return;
			}
			
			//Check errors
			if (data.error == "") {
				$("#canModify_"+id).fadeOut(1000);
			}
		}
	);
}

function refreshList()
{
	// load comments for item
	$("#cList").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['please_wait'];?>...');
	$.post(
		"sources/comments.queries.php",
		{
			type    : "list_comments",
			item_id : "<?php echo $_GET['id'];?>",
			key     : "<?php echo $_SESSION['key'];?>"
		},
		function(data) {			
			//decrypt data
			try {
				data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key'];?>");
			} catch (e) {
				// error
				$("#comment_add_spin").hide();
				$("#comment_add_error").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data);

				return;
			}
			
			if (data.error != "") {
				// error
			} else {
				// display
				$("#cList").html(data.html);
				
				// load css
				$(".comment").hover(function() {
					$(this).css("background","#A0A0A0").css("border-radius","6px");
					var tmp = $(this).attr("id").split("_");
					$("#commentaction_"+tmp[1]).show();
				}, function (){
					$(this).css("background","#D4D5D5").css("border-radius","0px");
					var tmp = $(this).attr("id").split("_");
					$("#commentaction_"+tmp[1]).hide();
				});
				
				//inline editing
				$(".editable_textarea").editable("sources/comments.queries.php", {
					  indicator : "<img src=\'includes/images/ajax-loader.gif\' />",
					  type   : "textarea",
					  select : true,
					  rows: "3",
					  widh: "100%",
					  submit : "<i class=\'fa fa-check mi-green\'></i>&nbsp;",
					  cancel : "<i class=\'fa fa-remove mi-red\'></i>&nbsp;",
					  name : "newValue"
				});
				
				$(".tip").tooltipster({multiple: true});
			}
		}
	);
}

$(function() {
	refreshList();
	
	$(".tip").tooltipster({multiple: true});
	
	$( ".button" ).button();
	
	// add new comment
	$("#comment_add_button").click(function() {
		if ($("#comments_system_add_text").val() == "") {			
			return false;
		}
		$("#comment_add_spin").show();
		
		var data = '{"comment":"'+sanitizeString($("#comments_system_add_text").val()).replace(/\n/g, '<br />').replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;')+'", "item_id":"'+$("#item_id").val()+'"}';
		
		$.post(
			"sources/comments.queries.php",
			{
				type    : "add_comment",
				data     : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key'];?>"),
				key     : "<?php echo $_SESSION['key'];?>"
			},
			function(data) {
				//decrypt data
				try {
					data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key'];?>");
				} catch (e) {
					// error
					$("#comment_add_spin").hide();
					$("#comment_add_error").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />"+data);

					return;
				}

				//Check errors
				if (data.error == "") {
					$("#comment_add_spin").hide();
					$("#comment_add_error").html("").hide();
					$("#cList").html('<i class="fa fa-cog fa-spin"></i>&nbsp;<?php echo $LANG['please_wait'];?>...');
					
					refreshList();
					
					$("#comments_system_add_text").val("");
				}
			}
		);
	});
});
</script>