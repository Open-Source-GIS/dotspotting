<?php

	#
	# $Id$
	#

	include("include/init.php");
	loadlib("geo_geocode");
	loadlib("formats");

	$owner = users_ensure_valid_user_from_url();

	$sheet_id = get_int64('sheet_id');

	if (! $sheet_id){
		error_404();
	}

	$more = array(
		'load_extent' => 1,
	);

	$sheet = sheets_get_sheet($sheet_id, $GLOBALS['cfg']['user']['id'], $more);

	if (! $sheet){
		error_404();
	}

	if ($sheet['deleted']){
		$GLOBALS['smarty']->display("page_sheet_deleted.txt");
		exit();		
	}

	if ($sheet['user_id'] != $owner['id']){
		error_404();
	}

	if (! sheets_can_view_sheet($sheet, $GLOBALS['cfg']['user']['id'])){
		error_403();
	}

	#

	$is_own = ($owner['id'] == $GLOBALS['cfg']['user']['id']) ? 1 : 0;
	$smarty->assign("is_own", $is_own);

	$smarty->assign_by_ref("owner", $owner);
	$smarty->assign_by_ref("sheet", $sheet);

	# delete this sheet?

	if ($is_own){

		$crumb_key = 'delete-sheet';
		$smarty->assign("crumb_key", $crumb_key);

		if ((post_str('delete')) && (crumb_check($crumb_key))){

			if (post_str('confirm')){

				$rsp = sheets_delete_sheet($sheet);
				$smarty->assign('deleted', $rsp);
			}

			if ($rsp['ok']){

				$redir = urls_sheets_for_user($GLOBALS['cfg']['user']) . "?deleted=1";
				header("location: $redir");
				exit();
			}

			$smarty->display('page_sheet_delete.txt');
			exit();
		}
	}

	# Hey look! At least to start we are deliberately not doing
	# any pagination on the 'dots-for-a-sheet' page. We'll see
	# how long its actually sustainable but for now it keeps a
	# variety of (display) avenues open.
	# (20101025/straup)

	$more = array(
		'per_page' => $GLOBALS['cfg']['import_max_records'],
	);

	$sheet['dots'] = dots_get_dots_for_sheet($sheet, $GLOBALS['cfg']['user']['id'], $more);

	$to_index = array($sheet['dots'][0]);
	$dots_indexed = dots_indexed_on($to_index);
	
	$GLOBALS['smarty']->assign_by_ref("dots_indexed", $dots_indexed);
	
	
	if ($is_own){
		$smarty->assign("permissions_map", dots_permissions_map());
		$smarty->assign("geocoder_map", geo_geocode_service_map());
	}
	
	// create a simplfied object for js
	$json_fields = array("id","created","details","geohash","is_interactive","latitude","longitude","user_id","perms","sheet_id");
	if($sheet['dots']){
		$ddd = array();
		foreach ($sheet['dots'] as $dot) {
			$bb = array();
			foreach($json_fields as $fi){
				if(isset($dot[$fi])){
					if($fi == "details"){
						$_details = array();
						foreach($dot[$fi] as $de){
							$_details[] = array(
								'label' => $de[0]['label'],
								'value' => $de[0]['value']
							);
						}
						$bb[$fi] = $_details;
					}else{
						$bb[$fi] = $dot[$fi];
					}
				}
				
			}

			$ddd[] =$bb;
			
		}
		//if( isset($owner.username) )$ddd[] = array('owner'=>$owner.username);
		$smarty->assign("dots_simple", $ddd);
	}
	

	$formats = array_values(formats_valid_export_map());
	$GLOBALS['smarty']->assign("export_formats", $formats);

	$formats_pretty_names = formats_pretty_names_map();
	$GLOBALS['smarty']->assign_by_ref("formats_pretty_names", $formats_pretty_names);

	$smarty->display("page_sheet.txt");
	exit;
?>