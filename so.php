<?php
/**
 * Init variables that will be used in bootstrap, user auth and overall app setup
 */
$fileTrace = "so.php > ";


// Page specific variables needed by bootstrap and authorize_user
include_once "includes/bootstrap.php";
include_once "includes/authorize_user.html";
include_once "includes/functions2.php";

if( !$_SESSION['sess_user_row']['user_id'] ) {
	$location = $BASE_HREF . "home.html";
	header( "Location: $location" );
}

// Handle redirects if they shouldn't be here
if( !$lookup_shop_type == "S" ) {
	$location = ENV_BASE_URL . "/home.html?e=not_service_shop";
	header("Location: $location");
}

if( $go_to_list ) {
	$location = ENV_BASE_URL . "/so_list.php";
	header("Location: $location");
}

if( $go_to_feedback_list ) {
	$location=ENV_BASE_URL."/feedback.html";
	header("Location: $location");
}


/**
 * Initialize page variables
 */
$complaint_count = 8;
$mo_class= "selected";
$reference_url = ( $lookup_company_url ) ? "http://" . $lookup_company_url : ENV_BASE_URL;
$errors = array();
$show_form = TRUE;
$message = "";
$today = date("Ymd");
$from_email = "{$lookup_company_name} (MyRepairTracker.com) <info@myrepairtracker.com>";
$vehicle_recon_steps = array();	// Existing recon workflow step status from prev add/updates on Service Order
$shop_recon_steps = array();		// Recon Workflow steps defined by shop
$firstPending = 0;					// Recon Step ID for 1st pending step in workflow
$post_recon_steps = array();		// Recon steps - status values posted via update/add recon order



/**
 * Sanitize incoming data - initialize is not passed.
 * Note: so_type is stored in $_SESSION
 */
// Array of valid variables organized by datatype
$input_valid = array(
	FILTER_SANITIZE_STRING => array(
		'type', 'delete_order', 'update', 'update_too', 'vehicle_owner_last_name', 'new_reminder_type',
		'new_reminder_date', 'vehicle_owner_first_name', 'complaints'
	),

	FILTER_SANITIZE_NUMBER_INT => array(
		'so_id', 'service_order_number', 'service_provider_advisor_id', 'vehicle_year', 'vehicle_make_id',
		'vehicle_model', 'vehicle_mileage', 'service_status', 'full_edit', 'original_promise_date',
		'revised_promise_date', 'promise_date',
	),

	FILTER_SANITIZE_EMAIL => array(
		'vehicle_owner_primary_email'
	)

);

// These $_POST will be type array
$input_typeArray = array( 'complaints' );

foreach( $input_valid as $input_strings ) {
	foreach( $input_strings as $var ) {
		if( in_array( $var, $input_typeArray ) ) {
			$var_G = filter_input( INPUT_GET , $var, $varType, FILTER_REQUIRE_ARRAY );
			$var_P = filter_input( INPUT_POST, $var, $varType, FILTER_REQUIRE_ARRAY );
		} else {
			$var_G = filter_input( INPUT_GET | INPUT_POST, $var, $varType );
			$var_P = filter_input( INPUT_GET | INPUT_POST, $var, $varType );
		}
		if( $var_G || $var_P ) {
			$$var = $var_P ? $var_P : $var_G;
		}
	}
}

// If reconsteps are in $_POST - process them and store for later
$firstPending = false;
foreach( $_POST as $key=>$value ) {
	if( substr( $key, 0, 10 ) == "reconstep_" ) {
		$keyParts = explode( "_", $key );
		$stepID = (int) $keyParts[1];
		$post_recon_steps[(string)$stepID] = $value;

		if( !$firstPending && $value == 'pending' ) {
			$firstPending = $stepID;
			$reconditionPostCurrStep = (string)$stepID;
		}
	}
}


// Post process certain input variables
$so_type = $so_type ? substr( $so_type, 0, 1 ) : ( $_SESSION['sess_so_type'] ? $_SESSION['sess_so_type'] : "O" );
$so_id = $so_id ? $so_id : 0;

$we_owe = $we_owe ? $we_owe : ( $so_type == "W" ? 'New' : 'N' );
$special_order = $special_order ? $special_order : ( $so_type == "S" ? 'New' : 'N' );
$recondition = $recondition ? $recondition : ( $so_type == "R" ? 'New' : 'N' );


// Initialize variables based on Serive Order type - Recon, We Owe, Special order or plain Service Order
switch( $so_type ) {

	/**
	 * Vanilla Special Orders
	 */
	case "O":
		$_SESSION['sess_so_type'] = "O";
		$so_class = "selected";
		$h3 = "Service Orders > ";
		$SONumLabel = "SRO Number";
		$shortLabel = "SRO";

		break;

	/**
	 * We Owe's
	 */
	case "W":
		$_SESSION['sess_so_type'] = "W";
		$wo_class = "selected";
		$h3 = "We Owe > ";
		$SONumLabel = "Stock Number";
		$shortLabel = "We Owe";

		if( !$new_reminder_date ) {
			$new_reminder_date = date( 'm-d-Y', strtotime("+3 days") );
			$new_reminder_type = 'WOF';
		}

		break;


	/**
	 * Special Orders
	 */
	case "S":
		$_SESSION['sess_so_type'] = "S";
		$sp_class = "selected";
		$h3 = "Special Orders > ";
		$SONumLabel = "SRO Number";
		$shortLabel = "Special Order";

		if( !$new_reminder_date ) {
			$new_reminder_date = date( 'm-d-Y', strtotime("+3 days") );
			$new_reminder_type = 'WOF';
		}

		break;


	/**
	 * Reconditions (Recons)
	 */
	case "R":
		$_SESSION['sess_so_type'] = "R";
		$re_class = "selected";
		$h3 = "Reconditions > ";
		$SONumLabel = "Stock Number";
		$shortLabel = "Recon";
		$pn_open_class = "selected";


		/**
		 * Load Shop's Custom Recondition Workflow
		 */
		$shop_recon_details = LookupShopReconSteps( $cookie_sp_id );
		$shop_recon_steps = $shop_recon_details['steps'];

		// Shop hasn't setup their Recon Workflow steps yet
		if( count( $shop_recon_steps ) == 0 ) {
			$ro_instructions = "Your shop hasn't set up its <em>Vehicle Reconditioning Workflow</em> yet.  Please have a manager log into myRepairTracker, go to the Manager Console and choose <em>Setup Vehicle Reconditioning Workflow</em>.";

		}

		/**
		 * If we are working on a Recon Order, look up existing step status
		 */
		if( $so_id && $so_type == "R" ) {
			$vehicle_recon_details = LookupVehicleReconSteps( $so_id, $shop_recon_steps );
			$vehicle_recon_steps = 	$vehicle_recon_details['steps'];
		}

		break;


	/**
	 *  Default to Special Orders if nothing chosen
	 */
	default:
		$_SESSION['sess_so_type'] = "O";
		$so_class = "selected";
		$h3 = "Service Orders > ";
		$SONumLabel = "SO Number";
		$shortLabel = "SO";

}


// $pagenav is our page level navigation - currently themed like tabs
$pagenav = "<div id='header_Tabs'><ul><li class='{$pn_open_class}'><a href='so_list.php'><span>Open {$shortLabel}'s</span></a></li><li class='{$pn_closed_class}'><a href='so_list.php?status=C'><span>Closed {$shortLabel}'s</span></a></li><li class='{$pn_add_class}'><a href='so.php'><span>+ Add {$shortLabel}</span></a></li></ul></div>";

// What is the current Pending Step (check $_POST and existing reconsteps in DB )
$reconCurrStatus = $reconditionPostCurrStep ? $reconditionPostCurrStep : ( $reconditionCurrStep ? $reconditionCurrStep : 0 );


/**
 * Process request to DELETE a Service Order
 */
if( ( $lookup_user_is_admin=="Y" ) && ( $delete_order ) ) {
	$sql = "DELETE FROM service_order WHERE id={$so_id} LIMIT 1";
	$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

	$sql = "DELETE FROM service_order_history WHERE service_order_id={$so_id}";
	$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

	$sql = "DELETE FROM service_order_part WHERE service_order_id={$so_id}";
	$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

	$sql = "DELETE FROM service_order_recon_status WHERE recon_so_id={$so_id}";
	$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

	$location = ENV_BASE_URL . "/so_list.php";
	header("Location: $location");
}


/**
 * Process request to UPDATE a Service Order
 */
if( ( $update || $update_too ) && ( $full_edit == TRUE ) ) {
	$complaints = $_POST[complaints];

	// Service Order Number is required.
   if( !$service_order_number ) {
		$errors[] = "Please enter a value for the {$SONumLabel}.";
 	}

	// Service Order Number must not already exist for this Service Provider.
	if( $so_id == 0 ) {
		if( $service_order_number ) {
			$sql = "SELECT COUNT(*) AS duplicate FROM service_order WHERE (service_order_number='{$service_order_number}') AND (service_provider_id='{$cookie_sp_id}')";
			$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

			$row = mysql_fetch_array($result);
			$duplicate = $row["duplicate"];

			if( $duplicate > 0 ) {
				$errors[] = "That {$SONumLabel} already exists. Please enter a different {$SONumLabel}.";
			}
		}
	}

	// Service Provider Advisor is required.
	if( $service_provider_advisor_id == 0 ) {
		$errors[]="Please select a value for the Service Advisor.";
	}

	// Vehicle Owner Last Name is required.
	if( !$vehicle_owner_last_name && $so_type != "R" ) {
		$errors[] = "Please enter a value for the Vehicle Owner Last Name.";
	}

	// If entered, Vehicle Owner's Primary Email must be well-formed.
   if( $vehicle_owner_primary_email && $so_type != "R" ) {
		if( !ValidEmail( $vehicle_owner_primary_email ) ) {
			$errors[] = "Vehicle Owner Primary Email is invalid.";
		}
	}

	// Vehicle Year is required.
   if( !$vehicle_year ) {
       $errors[] = "Please select a value for the Vehicle Year.";
	}

	// Vehicle Make is required.
	if( $vehicle_make_id == 0 ) {
		$errors[] = "Please select a value for the Vehicle Make.";
	}

	// Vehicle Model is required.
   if( !$vehicle_model ) {
       $errors[] = "Please enter a value for the Vehicle Model.";
	}

	// Vehicle Mileage is required.
   if( !$vehicle_mileage && $so_type != "R" ) {
		$errors[] = "Please enter a value for the Vehicle Mileage.";
	}

	// Reminder edits.
   if( ( $new_reminder_type ) && ( !$new_reminder_date ) ) {
		$errors[] = "Please select a Reminder Date.";
	}

	// Service Status is required.
   if( $service_status == 0 ) {
       $errors[] = "Please select a value for the Service Status.";
	}


	/*-----------------------------------------------------------*
	 * If errors are found, format the display back to the user. *
	 *-----------------------------------------------------------*/
   if( count( $errors ) > 0 ) {
		$message = FormatErrorDisplay( $errors );

	/*-----------------------------------------------------------------------*
	 * If errors are not found, insert a new row or update the existing row. *
	 *-----------------------------------------------------------------------*/
   } else {

       if( $vehicle_owner_primary_email ) {
       	$to = "{$vehicle_owner_first_name} {$vehicle_owner_last_name} <{$vehicle_owner_primary_email}>";
       }

       if( ( $update || $update_too ) && ( $full_edit == TRUE ) ) {
         	$preliminary_repair_amount = 0;

				// Calculate preliminary amount
				foreach( $complaints as $i => $complaint ) {
					if( $complaint[amount] ) {
						$preliminary_repair_amount = $preliminary_repair_amount + $complaint[amount];
					}
				}

				$preliminary_amount = number_format( $preliminary_amount, 2 );

				// Set/Initialize dates
				if( $original_promise_date == 0 ) {
					if( $promise_date ) {
						$promise_date = formatDateString( $promise_date, 'm-d-Y', 'Ymd');
					} else {
						$promise_date = 0;
					}
				} else {
					$promise_date = 0;
				}

				if( $revised_promise_date ) {
					$revised_promise_date = formatDateString( $revised_promise_date, 'm-d-Y', 'Ymd');
				} else {
					$revised_promise_date = 0;
				}

				// NEW DB ENTERY - BUILD SQL INSERT
				if( $so_id == 0 ) {
					$newServiceOrder = true;
					$promise_date = ($revised_promise_date) ? $revised_promise_date : $today;

               $sql = "INSERT INTO service_order (date_added, last_update_user_id, status, service_status, service_provider_id, service_order_number, job_number, service_provider_advisor_id, vehicle_owner_first_name, vehicle_owner_last_name, vehicle_owner_contact_name, vehicle_owner_primary_phone, vehicle_owner_secondary_phone, vehicle_owner_primary_email, carrier_id, vehicle_year, vehicle_make_id, vehicle_model, vehicle_plate_no, vehicle_mileage, vehicle_vin, internal_job, no_truck, loaner, waiter, recall, we_owe, special_order, recondition, recondition_curr_step, prior_damage_details, preliminary_repair_amount, no_estimate, customer_pu_time";

					     $sql .= ( $promise_date > 0 ) ? ', promise_date' : '';
					     $sql .= ($service_status <> $original_service_status) ? ', service_status_date' : '';
               $sql .= ') ';

               $sql .= "VALUES (NOW(), {$lookup_user_id}, 'O', {$service_status}, '{$cookie_sp_id}', '{$service_order_number}', '{$job_number}', '{$service_provider_advisor_id}', '{$vehicle_owner_first_name}', '{$vehicle_owner_last_name}', '{$vehicle_owner_contact_name}', '{$vehicle_owner_primary_phone}', '{$vehicle_owner_secondary_phone}', '{$vehicle_owner_primary_email}', '{$carrier_id}', '{$vehicle_year}', '{$vehicle_make_id}', '{$vehicle_model}', '{$vehicle_plate_no}', '{$vehicle_mileage}', '{$vehicle_vin}', '{$internal_job}', '{$no_truck}', '{$loaner}', '{$waiter}', '{$recall}', '{$we_owe}', '{$special_order}', '{$recondition}', '{$reconCurrStatus}', '{$prior_damage_details}', '{$preliminary_repair_amount}', '{$no_estimate}', '{$customer_pu_time}'";

               $sql .= ($promise_date > 0) ? ", {$promise_date}" : '';
               $sql .= ($service_status <> $original_service_status) ? ", NOW()" : '';
               $sql .= ')';

               $result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
               $so_id = mysql_insert_id();

					     $sql = "UPDATE service_order SET order_token=MD5( CONCAT( id, date_added, service_provider_id ) ) WHERE id={$so_id}";
               $result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

             // UPDATING EXISTING DB ENTRY - BUILD SQL UPDATE
             } else {
					     $newServiceOrder = false;

               $sql = "UPDATE service_order SET date_updated={$today}, last_update_user_id={$lookup_user_id}, status='{$status}', service_status={$service_status}, job_number='{$job_number}', service_provider_advisor_id='{$service_provider_advisor_id}', vehicle_owner_first_name='{$vehicle_owner_first_name}', vehicle_owner_last_name='{$vehicle_owner_last_name}', vehicle_owner_contact_name='{$vehicle_owner_contact_name}', vehicle_owner_primary_phone='{$vehicle_owner_primary_phone}', vehicle_owner_secondary_phone='{$vehicle_owner_secondary_phone}', vehicle_owner_primary_email='{$vehicle_owner_primary_email}', carrier_id='{$carrier_id}', vehicle_year='{$vehicle_year}', vehicle_make_id='{$vehicle_make_id}', vehicle_model='{$vehicle_model}', vehicle_plate_no='{$vehicle_plate_no}', vehicle_mileage='{$vehicle_mileage}', vehicle_vin='{$vehicle_vin}', internal_job='{$internal_job}', no_truck='{$no_truck}', loaner='{$loaner}', waiter='{$waiter}', recall='{$recall}', we_owe='{$we_owe}', special_order='{$special_order}', recondition='{$recondition}', recondition_curr_step='{$reconCurrStatus}', prior_damage_details='{$prior_damage_details}', preliminary_repair_amount='{$preliminary_repair_amount}', no_estimate='{$no_estimate}', customer_pu_time='{$customer_pu_time}', revised_promise_date={$revised_promise_date}";

							 $sql .= ($promise_date > 0) ? ", promise_date='{$promise_date}'" : '';
		 					 $sql .= ($service_status <> $original_service_status) ? ", service_status_date=NOW(), send_email_status='Y'" : '';
               $sql .= " WHERE id='{$so_id}' LIMIT 1";

               $result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
				}

				/**
				 * Process Complaints.
				 */
  			// DELETE existing complaints (Note: We will readd them to the DB table)
				// $complaint_sql = "DELETE FROM service_order_complaint WHERE service_order_id={$so_id}";
				// $complaint_result = mysql_query($complaint_sql,$db) or die (mysql_error()."<br>SQL= ".$complaint_sql);
				// Complaints are no longer deleted and then added. We have to go through each complaint and determine what to do with it.

				if( is_array( $complaints ) ) {
					$seq = 1;

					foreach( $complaints as $i => $complaint ) {

						$complaint_sql = "SELECT COUNT(*) AS complaint_found FROM service_order_complaint WHERE service_order_id=$so_id AND seq=$seq";
						$complaint_result = mysql_query($complaint_sql,$db) or die (mysql_error()."<br>SQL= ".$complaint_sql);
  					$complaint_row = mysql_fetch_array( $complaint_result );
						$complaint_sql="";
						$so_history_note="";
  					if ( $complaint_row[complaint_found] )
  						{
								if( trim( $complaint[desc] ) ) {
									$complaint_desc = trim( $complaint[desc] );
									// $complaint_amount = number_format($complaint[amount], 2);
									$complaint_amount = $complaint[amount];
									$complaint_sql = "UPDATE service_order_complaint SET complaint='$complaint_desc', amount='$complaint_amount'";

									if ( ($complaint[new_status] == $complaint[prev_status]) OR ($complaint[new_status] == "") )
										{
											$complaint_status = $complaint[prev_status]; // status did not change
										}
									elseif ( $complaint[new_status] == "A" )
										{
											$complaint_status = $complaint[new_status];  // status changed to Approved
											$complaint_sql .= ", approved='Y', date_approved=NOW()";
											$so_history_note = "Complaint status changed to approved by shop: $complaint_desc";
										}
									elseif ( $complaint[new_status] == "C" )
										{
											$complaint_status = $complaint[new_status];  // status changed to Completed
											$complaint_sql .= ", date_completed=NOW()";
											$so_history_note = "Complaint status changed to completed: $complaint_desc";
										}
									elseif ( $complaint[new_status] == "D" )
										{
											$complaint_status = $complaint[new_status];  // status changed to Declined
											$complaint_sql .= ", approved='N', date_declined=NOW()";
											$so_history_note = "Complaint status changed to declined by shop: $complaint_desc";
										}
									elseif ( $complaint[new_status] == "O" )
										{
											$complaint_status = $complaint[new_status]; // status changed to Open
											$complaint_sql .= ", approved='N'";
											$so_history_note = "Complaint status changed to open by shop: $complaint_desc";
										}
									$complaint_sql .= ", status='$complaint_status' WHERE service_order_id=$so_id AND seq=$seq LIMIT 1";
								} else {
									$complaint_sql = "DELETE service_order_complaint WHERE service_order_id=$so_id AND seq=$seq LIMIT 1";
									$so_history_note = "Complaint deleted by shop: $complaint_desc";
								}
  						}
  					else
  						{
								if( trim( $complaint[desc] ) ) {
									$complaint_desc = trim( $complaint[desc] );
									// $complaint_amount = number_format($complaint[amount], 2);
									$complaint_amount = $complaint[amount];
									$complaint_sql = "INSERT INTO service_order_complaint (service_order_id, seq, complaint, amount, status) VALUES ('$so_id', '$seq', '$complaint_desc', '$complaint_amount', 'O')";
									$so_history_note = "Complaint added by shop: $complaint_desc";
								}
  						}

						if ( $complaint_sql ) {
              $complaint_result=mysql_query($complaint_sql,$db) or die (mysql_error()."<br>SQL= ".$complaint_sql);
              if ( $so_history_note ) {
					    	$so_history_sql="INSERT INTO service_order_history (date_added, last_update_user_id, service_order_id, type, note) VALUES (NOW(), {$lookup_user_id}, {$so_id}, 'A', '{$so_history_note}')";
	              $so_history_result=mysql_query($so_history_sql,$db) or die (mysql_error()."<br>SQL= ".$so_history_sql);
							}
						}
						$seq++;
					}
				}

				/**
				 * Process Recon Steps - only if this is a recon vehicle
				 */
 				if( $so_type == 'R' && is_array( $shop_recon_steps ) ) {
 					$firstPending = false;
 					$firstNotify = false;

 					// Lookup old service_order_recon_status (by so_id && $rs_id)
					// note: $vehicle_recon_steps array hold existing DB entries

					// Existing Recon status entries - we need to UPDATE
					if( count( $vehicle_recon_steps ) > 0 ) {
						// loop to see if any p->c from $orig -> $post
						// 	if so, we will need to set all pending steps to new start=NOW()
						$newStart = '';

						foreach( $vehicle_recon_steps as $id=>$det ) {
							if( $det['recon_step_status'] == 'pending' && $post_recon_steps[(string)$id] == 'complete' ) {
								$newStart = 'NOW()';
								$firstNotify = true;
							}
						}

						// loop comparing $orig->$post
						// 	if still pending and new start, then update
						// 	if now closed, update all info
						//		if now pending, start=nowand wipe complete
						foreach( $vehicle_recon_steps as $id=>$det ) {

							// Pending -> Pending, but another step marked complete, so need to reset start datetime step for this one
							if( $det['recon_step_status'] == 'pending' && $post_recon_steps[(string)$id] == 'pending' && $newStart ) {
								$sql = "UPDATE service_order_recon_status SET recon_step_start_date=NOW() WHERE recon_so_id={$so_id} AND recon_sprs_id={$id}";
								if( !$firstPending ) {
									$firstPending = $id;
								}

							// Step changed to complete - update status and done date
							} elseif( $det['recon_step_status'] != 'complete' && $post_recon_steps[(string)$id] == 'complete' ) {
								$sql = "UPDATE service_order_recon_status SET recon_step_status='complete', recon_step_complete_date=NOW(), recon_step_complete_userid={$lookup_user_id} WHERE recon_so_id={$so_id} AND recon_sprs_id={$id}";

							// Step switched back to pending (from skip or complete) - set status and reset start datetime
							} elseif( $det['recon_step_status'] != 'pending' && $post_recon_steps[(string)$id] == 'pending' ) {
								$sql = "UPDATE service_order_recon_status SET recon_step_status='pending', recon_step_start_date=NOW(), recon_step_complete_date='0000-00-00 00:00:00', recon_step_complete_userid={$lookup_user_id} WHERE recon_so_id={$so_id} AND recon_sprs_id={$id}";
								if( !$firstPending ) {
									$firstPending = $id;
								}

							// Step switched to skip
							} elseif( $det['recon_step_status'] != 'skip' && $post_recon_steps[(string)$id] == 'skip' ) {
								$sql = "UPDATE service_order_recon_status SET recon_step_status='skip', recon_step_start_date=NOW(), recon_step_complete_date=NOW(), recon_step_complete_userid={$lookup_user_id} WHERE recon_so_id={$so_id} AND recon_sprs_id={$id}";


							// no change - don't update
							} else {
								$sql = '';
							}

							if( $sql ) {
								// print "\n<!-- sql_sors:\n$sql";
								// print "\n -->\n";
								$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
							}

						}


					// Brand new Recon - need to INSERT
					} else {
						$firstPending = 0;

						// Loop thru the Shop's Custom Steps
	 					foreach( $shop_recon_steps as $rs_id=>$rs_details ) {
	 						$rsid = (string)$rs_id;

							// If the step is marked skip or complete in $post, then set complete date to NOW()
							if( $post_recon_steps[(string)$rsid] == 'complete' || $post_recon_steps[(string)$rsid] == 'skip' ) {
								$completeDate = 'NOW()';

							// Otherwise it must be pending
							} else {
								$completeDate = "'0000-00-00 00:00:00'";

								// We need to track the first pending we come across for notification
								if( !$firstPending ) {
									$firstPending = $rsid;
									$firstNotify = true;
								}
							}

							$sql = "INSERT INTO service_order_recon_status (recon_so_id, recon_sprs_id, recon_step_status, recon_step_init_date, recon_step_start_date, recon_step_complete_date, recon_step_complete_userid ) VALUES ({$so_id}, {$rsid}, '{$post_recon_steps[$rsid]}', NOW(), NOW(), {$completeDate}, {$lookup_user_id} )";
// print "\n<!-- sql:\n$sql";
// print "\n -->\n";
							$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
	 					}
					}

					/**
					 * TODO: If we have a $firstPending $rs_id, then we need to look up that user and send notifications
					 */

					if( $firstPending && $firstNotify ) {
						$sql = "SELECT * FROM service_provider_recon_steps WHERE rs_id={$firstPending}";
						$result = mysql_query( $sql, $db ) or die (mysql_error()."<br>SQL= ".$sql);

						if( $row = mysql_fetch_assoc($result) ) {
							foreach( $row as $key=>$value ) {
								if( substr( $key, 0, 12 ) == "rs_notify_id" ) {
									$whichOne = str_replace( 'rs_notify_id', '', $key );

									if( $value ) {
										// Lookup user to get email, mobile, carrier
										$sql = "SELECT * FROM user WHERE user_id={$value} AND user_status='A'";
										$result2 = mysql_query( $sql, $db ) or die (mysql_error()."<br>SQL= ".$sql);

										if( $row = mysql_fetch_assoc($result2) ) {
											$user_details = @$row;
										}

										$subject = "Recon Notification - vehicle ready for you - #{$so_id}";
										$clean_email = "Recon Notification - vehicle ready for you - #{$so_id}";

										// Send Text Message Notification
										if( $row['rs_notify_text'.$whichOne] && $user_details['user_phone_number'] && $user_details['user_carrier_id'] ) {
											SendTextMessage2( $cookie_sp_id, $so_id, "internal", $user_details['user_phone_number'], $subject, $clean_email, $user_details['user_carrier_id'] );
										}

										// Send Email notification
										if( $row['rs_notify_email'.$whichOne] && $user_details['user_email'] ) {
											$body =  "******************************\n";
											$body .= "A Note from My Repair Tracker\n";
											$body .= "*****************************\n\n";
											$body .= "Service Order Number: $so_id\n\n";
											$body .= $clean_email . "\n\n";
											$body .= "Please log into MyRepairTracker.com for the most current recon status: {$reference_url}.\n\n";
											$body .= "NOTE: This is an automated email from MyRepairTracker.com. Please do not reply directly to this system-generated email.\n\r";
											$to = "{$user_details['user_first_name']} {$user_details['user_last_name']} <{$user_details['user_email']}>";
											$headers = "From: {$from_email}\r\n";

											mail( $to, $subject, $body, $headers );

										}

										// Send message system notification
										if( $row['rs_notify_message'.$whichOne] && $user_details['user_id'] ) {
											$from_user_id = 1;

											$sql="INSERT INTO message (date_added, from_user_id, from_user_type, to_user_id, to_user_type, subject, service_order_number, body) VALUES (NOW(), 0, 'admin', {$user_details['user_id']}, '{$user_details['user_type']}', '{$subject}', '{$so_id}', '{$clean_email}')";
											$result2 = mysql_query( $sql, $db ) or die (mysql_error()."<br>SQL= ".$sql);

										}
									}
								}
							}
						}
					 }

					 //$shop_recon_steps[$rsid]['rs_notify_id']
 				}

 				/**
				 * If new We Owe, then send notification to all 'We Owe' Advisors
				 */
 				if( $so_type == 'W' && $newServiceOrder ) {
 					// Load up list of all
					$sql = "SELECT * FROM user WHERE service_provider_id={$cookie_sp_id} AND user_wo_access='Y' AND user_status='A' ";
					$result = mysql_query( $sql, $db ) or die (mysql_error()."<br>SQL= ".$sql);

					// Setup message content, subject, etc
					$subject = $clean_email = "New We Owe Notification - #{$so_id}";
					$body =  "******************************\n";
					$body .= "A Note from My Repair Tracker\n";
					$body .= "*****************************\n\n";
					$body .= "Service Order Number: $so_id\n\n";
					$body .= $clean_email . "\n\n";
					$body .= "Please log into MyRepairTracker.com for the most current recon status: {$reference_url}.\n\n";
					$body .= "NOTE: This is an automated email from MyRepairTracker.com. Please do not reply directly to this system-generated email.\n\r";
					$to = "{$user_details['user_first_name']} {$user_details['user_last_name']} <{$user_details['user_email']}>";
					$headers = "From: {$from_email}\r\n";

					while( $user_details = mysql_fetch_assoc($result) ) {
						// Send Text Message Notification
						if( $user_details['user_phone_number'] && $user_details['user_carrier_id'] ) {
							SendTextMessage2( $cookie_sp_id, $so_id, "internal", $user_details['user_phone_number'], $subject, $clean_email, $user_details['user_carrier_id'] );
						}

						// Send Email notification
						if( $user_details['user_email'] ) {
							mail( $to, $subject, $body, $headers );
						}

						// Send message system notification
						if( $row['rs_notify_message'.$whichOne] && $user_details['user_id'] ) {
							$from_user_id = 1;

							$sql="INSERT INTO message (date_added, from_user_id, from_user_type, to_user_id, to_user_type, subject, service_order_number, body) VALUES (NOW(), 0, 'admin', {$user_details['user_id']}, '{$user_details['user_type']}', '{$subject}', '{$so_id}', '{$clean_email}')";
							$result2 = mysql_query( $sql, $db ) or die (mysql_error()."<br>SQL= ".$sql);
						}
					}
 				}

/**
 * Process Reminders
 */

 				// Add new Reminder
           if( $new_reminder_type ) {
               $reminder_date = formatDateString($new_reminder_date, 'mdY', 'Y-m-d' );

               $sql = "INSERT INTO reminder (service_order_id, reminder_date, reminder_type) VALUES ('{$so_id}', '{$reminder_date}', '{$new_reminder_type}')";
               $result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
             }

				// Delete reminders if necessary
            for( $i = 0; $i < count( $reminder_del ); $i++ ) {
                if( $reminder_del[$i] == "X" ) {
                    $sql = "DELETE FROM reminder WHERE id='{$reminder_id[$i]}' LIMIT 1";
                    $result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
                 }
				}


/**
 * Process Service Advisor Notes.
 */
				if( $service_advisor_note ) {
               $sql_note = mysql_real_escape_string($service_advisor_note);

               $sql="INSERT INTO service_order_history (date_added, last_update_user_id, service_order_id, type, note) VALUES (NOW(), {$lookup_user_id}, {$so_id}, 'S', '{$sql_note}')";
               $result=mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
				}


/**
 * Process Talk to Customer Note.
 */
           if( $talk_to_customer_note ) {
					$clean_email = mysql_real_escape_string($talk_to_customer_note);
               $sql_note = "MESSAGE TO CUSTOMER: $clean_email";

               $sql = "INSERT INTO service_order_history (date_added, last_update_user_id, service_order_id, type, note) VALUES (NOW(), {$lookup_user_id}, {$so_id}, 'E','{$sql_note}')";
               $result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

					// Send email update to owner
               if( $vehicle_owner_primary_email && $vehicle_owner_email_allowed == "Y" ) {
                   $body =  "******************************\n";
                   $body .= "A Note from My Repair Tracker\n";
                   $body .= "*****************************\n\n";
                   $body .= "Service Order Number: $service_order_number\n\n";
                   $body .= $clean_email . "\n\n";
                   $body .= "Please check MyRepairTracker.com for the most current repair status by pointing your browser to {$reference_url}. Enter your last name, your Service Order number and click the Submit button.\n\n";
                   $body .= "You can also use our 'Talk to Shop' feature located on your personal online repair tracking page to communicate with your Service Advisor.\n\n";
                   $body .= "Thank you for your business!\n\n\r";
                   $body .= "NOTE: This is an automated email from MyRepairTracker.com. Please do not reply directly to this system-generated email.\n\r";
                   $to = "{$vehicle_owner_first_name} {$vehicle_owner_last_name} <{$vehicle_owner_primary_email}>";
                   $subject = "Keeping You Informed";
                   $headers = "From: {$from_email}\r\n";

                   mail( $to, $subject, $body, $headers );
                 }

					// Send text message update to owner
               if( $send_text_message && $lookup_sms_allowed == "Y" && $vehicle_owner_texting_allowed == "Y" && $vehicle_owner_secondary_phone ) {
                   $company_name = str_replace( " Service", "", $lookup_company_name );
                   $subject = "Keeping You Informed";
                   SendTextMessage2( $cookie_sp_id, $so_id, "so", $vehicle_owner_secondary_phone, $subject, $clean_email );
					}
				}


/**
 * Process revised promise date. If it was write a note and send an email. *
 */
				if( $original_revised_promise_date <> $revised_promise_date ) {
               $new_promise_date = formatDateString( $revised_promise_date, 'Ymd', 'm-d-Y'); // substr($revised_promise_date,4,2) . "-" . substr($revised_promise_date,6,2) . "-" . substr($revised_promise_date,0,4);
               $sql_note = "PROMISE DATE OVERRIDE: There has been an extension to the completion date of repairs. New Promise Date is $new_promise_date.";

               // EMAIL ADDR ON FILE AND ALLOWED - SEND EMAIL
               if( $vehicle_owner_primary_email && $vehicle_owner_email_allowed == "Y" ) {
                   $body =  "******************************\n";
                   $body .= "A Note from My Repair Tracker\n";
                   $body .= "*****************************\n\n";
                   $body .= "Service Order Number: $service_order_number\n\n";
                   $body .= "There has been an extension to the completion date of repairs. New Promise Date is $new_promise_date.\n\n";
                   $body .= "Please check MyRepairTracker.com for the most current repair status by pointing your browser to {$reference_url}. Enter your last name, your Service Order number and click the Submit button.\n\n";
                   $body .= "Thank you for your business!\n\n\r";
                   $body .= "NOTE: This is an automated email from MyRepairTracker.com. Please do not reply directly to this system-generated email.\n\r";
                   $to = "{$vehicle_owner_first_name} {$vehicle_owner_last_name} <{$vehicle_owner_primary_email}>";
                   $subject = "Keeping You Informed";
                   $headers = "From: $from_email\r\n";

                   mail( $to, $subject, $body, $headers );

                   $sql_note .= " (Customer has been notified via e-mail.)";
					}

					// ADD ENTRY TO SERVICE HISTORY DB
               $sql = "INSERT INTO service_order_history (date_added, last_update_user_id, service_order_id, type, note) VALUES (NOW(), {$lookup_user_id}, {$so_id}, 'O','{$sql_note}')";
			  		$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

					// Send TEXT message text messaging
               if( $send_text_message && $lookup_sms_allowed == "Y" && $vehicle_owner_texting_allowed == "Y" && $vehicle_owner_secondary_phone ) {
                   $company_name = str_replace(" Service", "", $lookup_company_name);
                   $subject = "Keeping You Informed";
                   $text_message = "New Promise Date is $new_promise_date";
                   SendTextMessage2( $cookie_sp_id, $so_id, "so", $vehicle_owner_secondary_phone, $subject, $text_message );
					}
				}

				include "includes/so_service_status_note.html";
			}	// end if update ~line 275




			/**
			 * Order is being closed. Send CSI email and create RO CLOSED note. *
			 */
			if( ( $status == "C" ) && ( $date_closed == 0 ) ) {
				$report_card_token = keyblock(10,1);

           	// EMAIL ADDR ON FILE AND ALLOWED - SEND EMAIL
				if( $vehicle_owner_primary_email && $vehicle_owner_email_allowed == "Y" ) {
               $body =  "******************************\n";
               $body .= "A Note from My Repair Tracker\n";
               $body .= "*****************************\n\n";
               $body .= "Service Order Number: $service_order_number\n\n";
               $body .= "Your Service Order is now closed.\n\n";
               $body .= "We would appreciate you taking a minute or two to complete a Report Card for us. We value your input and we hope you take the time to let us know how we did! Just click the link below or copy and paste it into your browser to complete our simple Report Card:\n\n";
               $body .= "{$BASE_HREF}report_card/?so_id=$so_id&amp;report_card_token={$report_card_token}\n\n";
               $body .= "Thank you for your business!\n\n\r";
               $body .= "NOTE: This is an automated email from MyRepairTracker.com. Please do not reply directly to this system-generated email.\n\r";

               $subject = "Keeping You Informed";
               $headers = "From: {$from_email}\r\n";

               mail($to, $subject, $body, $headers);

               $sql_note = "SO CLOSED: CSI Report request sent";

            // DONT SEND EMAIL
				} else {
               $sql_note = "SO CLOSED";
				}

				// ADD A SERVICE ENTRY INTO DB
				$sql = "INSERT INTO service_order_history (date_added, last_update_user_id, service_order_id, type, note) VALUES (NOW(), {$lookup_user_id}, {$so_id}, 'A', '{$sql_note}')";
				$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

				$sql="UPDATE service_order SET last_update_user_id=$lookup_user_id, date_closed=NOW(), report_card_token='$report_card_token' WHERE id='{$so_id}' LIMIT 1";
				$result=mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);
			}

			// UPDATE PROCESSED REDIRECT USER
			if( $update || $update_too ) {
           $location = ENV_BASE_URL . "/so_list.php";
           header("Location: $location");
         }
		} // END of else block (no errors so we can update/insert) ~line 179


/*------------------------------------------------------------------------------------------------*
 * If the form was submitted and a full edit is not needed, check to see if the RO was re-opened. *
 *------------------------------------------------------------------------------------------------*/
	} elseif( ( $update || $update_too ) && ( $full_edit==FALSE ) ) {		// NOTE this pairs with ~line 57 if block

		// IF STATUS CHANGED, UPDATE SERVICE ORDER AND POST HISTORY ENTRIES
		if( $original_status != $status ) {
			$sql = "UPDATE service_order SET status='{$status}', date_updated={$today}, last_update_user_id={$lookup_user_id}, date_closed=0, report_card_token='', report_card_completed='N', report_card_a1='', report_card_a2='', report_card_a3='', report_card_a4='', report_card_a5='', report_card_a6='', report_card_comments='' WHERE id='{$so_id}' LIMIT 1";
			$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

			// ADD ENTRIES INTO SERVICE HISTORY DB
			$sql_note = "RO REOPENED: Pick-Up and CSI Report data were reset";
			$sql = "INSERT INTO service_order_history (date_added, last_update_user_id, service_order_id, type, note) VALUES (NOW(), {$lookup_user_id}, {$so_id}, 'A', '{$sql_note}')";
			$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

			$sql_note = "VEHICLE STATUS UPDATED: Service Order was re-opened";
			$sql = "INSERT INTO service_order_history (date_added, last_update_user_id,  service_order_id, type, note) VALUES (NOW(), {$lookup_user_id}, {$so_id}, 'A', '{$sql_note}')";
			$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

			// REDIIRCT USER TO MAIN SERVICE ORDER LIST PAGE
			$location = ENV_BASE_URL . "/so_list.php";
			header( "Location: $BASE_HREF" );
		}


/*-----------------------------------------------*
 * If the form was not submitted, load the form. *
 *-----------------------------------------------*/
	} else {		// NOTE this pairs with ~line 57 'if' block and ~line429 'elseif' block

		// NEW SERVICE ORDER - SET DEFAULTS
		if( !$so_id ) {
			$message = "<p class='noprint'>Complete the form below and click the Add button to add a new Service Order to the database.</p>";
			$status = "O";
			$internal_job = "N";
			$no_truck = "N";
			$loaner = "N";
			$waiter = "N";
			$recall = "N";
			$we_owe = ( $so_type == 'W' ) ? 'New' : 'N';
			$special_condition = ( $so_type == 'S' ) ? 'New' : 'N';
			$recondition = ( $so_type == 'R' ) ? 'New' : 'N';
			$customer_pu_time = "";
			$original_status = "";
			$original_service_status = 0;
			$original_promise_date = date("Y-m-d");
			$original_revised_promise_date = 0;

/*-----------------------------------------------------------------*
 * Default the Service Status to 'Awaiting Diagnostics - Testing'. *
 *-----------------------------------------------------------------*/
			$service_status = 10;

/*----------------------------------------------------------------*
 * If an Advisor is logged on, set the advisor id to the user id. *
 *----------------------------------------------------------------*/
			if( $lookup_user_type == "Advisor") {
				$service_provider_advisor_id = $lookup_user_id;
			}

		// EXISTING SERVICE ORDER - LOOKUP ITS INFO FROM DB
		} else {
			$sql = "SELECT * FROM service_order WHERE (id='{$so_id}') AND (service_provider_id='{$cookie_sp_id}') LIMIT 1";
			$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

         // SERVICE ENTRY FOUND IN DB - LOAD IT UP
			if( $row = mysql_fetch_array( $result ) ) {
				$so_id = $row["id"];
				$service_order_number = $row["service_order_number"];
				$job_number = $row["job_number"];
				$date_added = $row["date_added"];
				$date_updated = ( $row["date_updated"] == 0 ) ? "N/A" : date( "m-d-y", strtotime( $row["date_updated"] ) );
				$last_update_user_id = $row["last_update_user_id"];
				$date_closed = ($row["date_closed"]=="0000-00-00 00:00:00") ? '' : $row["date_closed"];
				$status = $row["status"];
				$original_status = $row["status"];
				$service_status = $row["service_status"];
				$original_service_status = $row["service_status"];
				$service_provider_advisor_id = $row["service_provider_advisor_id"];
				$vehicle_owner_first_name = $row["vehicle_owner_first_name"];
				$vehicle_owner_last_name = $row["vehicle_owner_last_name"];
				$vehicle_owner_contact_name = $row["vehicle_owner_contact_name"];
				$vehicle_owner_primary_phone = $row["vehicle_owner_primary_phone"];
				$vehicle_owner_secondary_phone = $row["vehicle_owner_secondary_phone"];
				$vehicle_owner_primary_email = $row["vehicle_owner_primary_email"];
				$carrier_id = $row["carrier_id"];
				$vehicle_owner_email_allowed = $row["vehicle_owner_email_allowed"];
				$vehicle_owner_texting_allowed = $row["vehicle_owner_texting_allowed"];
				$vehicle_year = $row["vehicle_year"];
				$vehicle_make_id = $row["vehicle_make_id"];
				$vehicle_model = $row["vehicle_model"];
				$vehicle_plate_no = $row["vehicle_plate_no"];
				$vehicle_mileage = $row["vehicle_mileage"];
				$vehicle_vin = $row["vehicle_vin"];
				$internal_job = $row["internal_job"];
				$no_truck = $row["no_truck"];
				$loaner = $row["loaner"];
				$waiter = $row["waiter"];
				$recall = $row["recall"];

				$we_owe = $row["we_owe"] ? $row["we_owe"] : 'N';
				$special_order = $row["special_order"] ? $row["special_order"] : 'N';
				$recondition = $row["recondition"] ? $row["recondition"] : 'N';

				$prior_damage_details = $row["prior_damage_details"];
				$no_estimate = $row["no_estimate"];
				$vehicle_damage_id = $row["vehicle_damage_id"];
				$vehicle_service_id = $row["vehicle_service_id"];
				$promise_date = $row["promise_date"];
				$original_promise_date = $row["promise_date"];

				if( $promise_date ) {
            	$promise_date = formatDateString( $promise_date, 'Ymd', 'm-d-Y' ); // substr($promise_date,4,2) . "-" . substr($promise_date,6,2) . "-".substr($promise_date,0,4);
            } else {
					$promise_date = date("m-d-Y");
					$original_promise_date = date("Y-m-d");
            }

            $revised_promise_date = $row["revised_promise_date"];
            $original_revised_promise_date = $row["revised_promise_date"];

            if ($revised_promise_date) {
            	$revised_promise_date = formatDateString($revised_promise_date, 'Ymd', 'm-d-Y' ); // substr($revised_promise_date,4,2)."-".substr($revised_promise_date,6,2)."-".substr($revised_promise_date,0,4);
				}

            $customer_pu_time=$row["customer_pu_time"];
				$preliminary_repair_amount = 0;
				$complaints = array();

				$sql = "SELECT * FROM service_order_complaint WHERE service_order_id={$so_id} ORDER BY seq";
				$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

				if( $row=mysql_fetch_array( $result ) ) {
					do {
						$complaints[$row[seq]][desc] = $row[complaint];
						$complaints[$row[seq]][amount] = $row[amount];
						$complaints[$row[seq]][prev_status] = $row[status];
						$complaints[$row[seq]][new_status] = $row[status];
						$preliminary_repair_amount = $preliminary_repair_amount + $row[amount];
					} while ($row=mysql_fetch_array($result));
				}

            $message = "<p class='noprint'>Complete the form below and click the Update button to update the {$shortLabel} in the database.</p>";

            $sql = "UPDATE service_order SET unread_customer_msg='', unread_feedback_msg='' WHERE id={$so_id} LIMIT 1";
            $result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

				// If this is a Recon, load recon steps statuses
				if( $so_type == "R" ) {
					$firstPending = 0;

					$sql = "SELECT * FROM service_order_recon_status WHERE recon_so_id={$so_id} ORDER BY recon_sprs_id";
					$result = mysql_query($sql,$db) or die (mysql_error()."<br>SQL= ".$sql);

					while( $row = mysql_fetch_assoc($result) ) {
						$vehicle_recon_steps[(string)$row['recon_sprs_id']] = $row;

						if( !$pendingFirst && $row['recon_step_status'] == 'pending' ) {
							$firstPending = $row['recon_sprs'];
						}
					}
				}

			// SERVICE ORDER NOT FOUND IN DB - TREAT AS NEW
			} else {
            $message = "<p class='noprint'>That Service Order was not found in the database. Complete the form below and click the Add button to add a new Service Order to the database.</p>";
            $so_id = "";
            $date_closed = 0;
            $status = "O";
            $internal_job = "N";
          	$no_truck = "N";
          	$loaner = "N";
            $waiter = "N";
            $recall = "N";
				$we_owe = ( $so_type == 'W' ) ? 'New' : 'N';
				$special_condition = ( $so_type == 'S' ) ? 'New' : 'N';
				$recondition = ( $so_type == 'R' ) ? 'New' : 'N';
	         $customer_pu_time = "";
            $original_status = "";
            $original_service_status = 0;
            $original_promise_date = 0;
            $original_revised_promise_date = 0;
            $total_repair_amount = "0.00";

				// Default the Service Status to 'Awaiting Diagnostics - Testing'.
            $service_status=10;

			}
		}

	}

  if( $so_id > 0 ) {
  		$updateMode = "Update";
      $h3 .= "Update";
      $note_label = "Changes to";
      $ajaxUpdateComplete = 'true';
      if( $original_status == "C" ) {
          $full_edit = FALSE;
          $disabled_attr = " disabled='disabled' ";
		} else {
          $full_edit = TRUE;
          $disabled_attr = "";
		}
	} else {
		$updateMode = "Add";
		$h3 .= "Add";
		$note_label = "Adding this";
		$full_edit = TRUE;
		$disabled_attr = "";
      $ajaxUpdateComplete = 'false';
	}

  $check_for_logo = TRUE;
  $include_cal = TRUE;
  $include_data_changed = TRUE;
  $printable = TRUE;
  $page_heading = $lookup_company_name;
  include "includes/header.php";
  echo "<h3 class='noprint'>{$h3}</h3>\n";
  echo $message;

  if( $show_form == TRUE ) {
	if( $lookup_sms_allowed=="Y" && $vehicle_owner_secondary_phone  && !$carrier_id ) {
		echo "<div class='warning'>&nbsp;<br />* Warning: Vehicle Owner's' Cell Carrier is blank. SMS texts to this customer won't work.<br />&nbsp;</div>\n";
	}
?>
		<script type='text/javascript'>ajaxLookupComplete = <?php echo $ajaxUpdateComplete; ?>;</script>

      <p class="noprint">Items marked with an <span class="red">asterisk (*)</span> are required.</p>
      <form name="so" action="<?php echo $PHP_SELF; ?>" method="post">

		  <input type='hidden' name='full_edit' value='<?php echo $full_edit; ?>' />
		  <input type='hidden' name='so_id' value='<?php echo $so_id; ?>' />
		  <input type='hidden' name='date_added' value='<?php echo $date_added; ?>' />
		  <input type='hidden' name='date_closed' value='<?php echo $date_closed; ?>' />
		  <input type='hidden' name='original_status' value='<?php echo $original_status; ?>' />
		  <input type='hidden' name='original_service_status' value='<?php echo $original_service_status; ?>' />
		  <input type='hidden' name='original_revised_promise_date' value='<?php echo $original_revised_promise_date; ?>' />
		  <input type='hidden' name='beg_date' value='<?php echo $beg_date; ?>' />
		  <input type='hidden' name='end_date' value='<?php echo $end_date; ?>' />
		  <input type='hidden' name='from' value='<?php echo $from; ?>' />
		  <input type='hidden' name='date_updated' value='<?php echo $date_updated; ?>' />
		  <input type='hidden' name='vehicle_owner_texting_allowed' value='<?php echo $vehicle_owner_texting_allowed; ?>' />
		  <input type='hidden' name='vehicle_owner_email_allowed' value='<?php echo $vehicle_owner_email_allowed; ?>' />

        <table align="center" border="0" cellpadding="2" style="border-collapse: collapse" width="100%">
          <tr>
            <td width="50%" valign="top">
              <!-- <fieldset style="height: 210px;"> -->
              <fieldset>
                <legend>Service Order Details</legend>
                <table align="center" border="0" cellpadding="2" cellspacing="4" style="border-collapse: collapse" width="100%">
                  <tr>
                    <td class="form_label" width="25%"><span class="red">*</span><?php echo $SONumLabel; ?></td>
                    <td class="form_value" width="20%" nowrap><input <?php echo $disabled_attr; ?> type="text" id="service_order_number" name="service_order_number" size="20" maxlength="255" value="<?php echo $service_order_number; ?>" onchange="data_changed=true" />
                    <td class="form_label" width="20%">Tag Number:</td>
                    <td class="form_value" width="35%" nowrap><input <?php echo $disabled_attr; ?> type="text" id="job_number" name="job_number" size="10" maxlength="10" value="<?php echo $job_number; ?>" onchange="data_changed=true" /></td>
                  </tr>
                  <tr>
                    <td class="form_label">Order Status:</td>
                    <td class="form_value" colspan="3" width="75%" nowrap>
<?php	if( $so_id > 0 ) {
?>
                    	<label for="status_o"><input id="status_o" type="radio" class="radio" name="status" value="O" <?php echo ($status != 'C') ? ' checked="checked"' : '';?> onclick="data_changed=true"> Opened</label>
                    	<label for="status_c"><input id="status_c" type="radio" class="radio" name="status" value="C" <?php echo ($status == 'C') ? ' checked="checked"' : ''; ?> onclick="data_changed=true"> Closed</label>
<?php	} else { ?>
								OPENED
<?php	} ?>
                    </td>
                  </tr>
                  <tr>
                    <td class="form_label">Date Opened:</td>
                    <td class="form_value" nowrap><?php echo $so_id > 0 ? displayDate( $date_added ) : 'TODAY'; ?></td>
                    <td class="form_label">Date Closed:</td>
                    <td class="form_value" nowrap><?php echo $status == "C" ? displayDate( $date_closed ) : "N/A"; ?></td>
                  </tr>
                  <tr>
                    <td class="form_label"><span class="red">*</span>Advisor Name:</td>
                    <td class="form_value" nowrap><?php echo SelectUser( $service_provider_advisor_id, "service_provider_advisor_id", "LIKE '%Advisor%'", $cookie_sp_id, $disabled_attr ); ?></td>
                    <td class="form_label">
								<?php echo ( $so_type != "R" ) ? 'Recall:' : '&nbsp;'; ?></td>
                    <td class="form_value" nowrap>
<?php if( $so_type != "R" ) { ?>
                    	<label for="recall_n"><input id="recall_n" type="radio" class="radio" name="recall" value="N" <?php echo ($recall == 'N') ? ' checked="checked"' : ''; ?> onclick="data_changed=true"> No</label> <label for="recall_y"><input id="recall_y" type="radio" class="radio" name="recall" value="Y" <?php echo ($recall == 'Y') ? ' checked="checked"' : ''; ?> onclick="data_changed=true"> Yes</label>
<?php	} else { ?>
							&nbsp;
<?php	} ?>
                    	</td>
                  </tr>
                  <tr>
                    <td class="form_label"><?php echo ( $so_type != "R" ) ? 'Waiter:' : '&nbsp;'; ?></td></td>
                    <td class="form_value" nowrap>
<?php if( $so_type != "R" ) { ?>
                    	<label for="waiter_n"><input id="waiter_n" type="radio" class="radio" name="waiter" value="N" <?php echo  ($waiter == 'N') ? ' checked="checked"' : ''; ?> onclick="data_changed=true"> No</label> <label for="waiter_y"><input id="waiter_y" type="radio" class="radio" name="waiter" value="Y" <?php echo ($waiter == 'Y') ? ' checked="checked"' : '';?> onclick="data_changed=true"> Yes</label>
<?php	} else { ?>
							&nbsp;
<?php	} ?>
                    	</td>
<?php if( $so_type == "W" ) { ?>
                    <td class="form_label">We Owe:</td>
                    <td class="form_value" nowrap>
                    		<label for="we_owe_n"><input id="we_owe_n" type="radio" class="radio" name="we_owe" value="N" <?php echo ($we_owe == 'N') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> No</label>
                    		<label for="we_owe_new"><input id="we_owe_new" type="radio" class="radio" name="we_owe" value="New" <?php echo ($we_owe == 'New') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> New</label>
                    		<label for="we_owe_used"><input id="we_owe_used" type="radio" class="radio" name="we_owe" value="Used" <?php echo ($we_owe == 'Used') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> Used</label>
                    	</td>
<?php } elseif( $so_type == "R" ) { ?>

                  	<td class="form_label">Recon:</td>
                  	<td class="form_value" nowrap>
                    		<label for="recondition_n"><input id="recondition_n" type="radio" class="radio" name="recondition" value="N" <?php echo ($recondition == 'N' || !$recondition) ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> No</label>
                    		<label for="recondition_new"><input id="recondition_new" type="radio" class="radio" name="recondition" value="New" <?php echo ($recondition == 'New') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> New</label>
                    		<label for="recondition_used"><input id="recondition_used" type="radio" class="radio" name="recondition" value="Used" <?php echo ($recondition == 'Used') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> Used</label>
                    	</td>

<?php } elseif( $so_type == "S" || $so_type == "O" ) { ?>

                  	<td class="form_label">Special Order:</td>
                  	<td class="form_value" nowrap>
                    		<label for="special_order_n"><input id="special_order_n" type="radio" class="radio" name="special_order" value="N" <?php echo ($special_order == 'N' || !$special_order) ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> No</label>
                    		<label for="special_order_new"><input id="special_order_new" type="radio" class="radio" name="special_order" value="New" <?php echo ($special_order == 'New') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> New</label>
                    		<label for="special_order_used"><input id="special_order_used" type="radio" class="radio" name="special_order" value="Used" <?php echo ($special_order == 'Used') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> Used</label>
                    	</td>

<?php } ?>
                  </tr>
                </table>
              </fieldset>
            </td>
            <td colspan="2" valign="top" width="50%">
              <!-- <fieldset style="height: 210px;"> -->
              <fieldset>
                <legend>Target Date &amp; Service Status</legend>
                <table align="center" border="0" cellpadding="2" cellspacing="4" style="border-collapse: collapse" width="100%">
                  <tr>
                    <td class="form_label" width="20%"><?php echo ($so_id) ? "Current<br />Target Date:" : "Default Target Date:"; ?></td>
                    <td class="form_value" nowrap>
<?php
/*---------------------------------------------------------------------------------------*
 * If we do NOT have a promise date, display the promise date field and calendar pop-up. *
 *---------------------------------------------------------------------------------------*/
	if( $original_promise_date == 0 ) {
?>
                    <input <?php echo $disabled_attr; ?> readonly='readonly' class="ro_datefield" type="text" id='promise_date' name="promise_date" value="<?php echo $promise_date; ?>" size="10" onchange="data_changed=true" />
<?php } else {
							/*-------------------------------------------*
							* If we do have a promise date, display it. *
							*-------------------------------------------*/
							echo date( "m-d-y", strtotime( $original_promise_date ) );
							}
						?>
                    </td>
                  </tr>
                  <tr>
                    <td class="form_label">Revised<br />Target Date:</td>
                    <td class="form_value" nowrap>
<?php
/*------------------------------------------------------------------------------------------------------*
 * If we do NOT have a promise date, do NOT display the revised promise date field and calendar pop-up. *
 *------------------------------------------------------------------------------------------------------*/
	if( $original_promise_date == 0 ) {
?>
                    N/A
<?php } else {
							if( $revised_promise_date == 0 ) {
							$revised_promise_date="";
							}
							/*-------------------------------------------------------------------------------------------*
							* If we do have a promise date, display the revised promise date field and calendar pop-up. *
							*-------------------------------------------------------------------------------------------*/
						?>
                    <input readonly='readonly' class="ro_datefield"  <?php echo $disabled_attr; ?> type="text" id="revised_promise_date" name="revised_promise_date" value="<?php echo $revised_promise_date; ?>" size="10" onchange="data_changed=true" />
<?php } ?>
                    </td>
                  </tr>
                  <tr>
                    <td class="form_label">Preferred P/U:</td>
                    <td class="form_value" nowrap><?php echo PreferredPickUpTime( $customer_pu_time, "customer_pu_time", $disabled_attr ); ?></td>
                  </tr>

                  <tr>
                    <td class="form_label"><span class="red">*</span>Status:</td>
                    <td class="form_value" nowrap><select <?php echo $disabled_attr; ?> size="1" name="service_status" onchange="data_changed=true">
<?php

  echo ( $service_status == 0 ) ? "<option value='' selected>Select...\n" : '';

  $select_sql = "SELECT * FROM service_status WHERE shop_type='S' AND so_type='{$_SESSION['sess_so_type']}' ORDER BY list_order";
  $select_result = mysql_query( $select_sql,$db ) or die ( mysql_error()."<br>SQL= ".$select_sql );

  if( $select_row = mysql_fetch_array( $select_result ) ) {
      do {
          $select_id = $select_row["id"];
          $select_status = $select_row["description"];
          $select_notification = $select_row["email_notification"];
          if( $select_notification == "Y" ) {
          	$select_status .= "*";
          }

			 echo ( $service_status == $select_id ) ? "<option value=\"$select_id\" selected>$select_status\n" : "<option value=\"$select_id\">$select_status\n";
        } while( $select_row = mysql_fetch_array( $select_result ) );
    }
?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td class="form_label smallest" valign="top">&nbsp;</td>
                    <td class="form_value smallest" valign="top">NOTE: * indicates a status notification will be emailed to the Vehicle Owner.</td>
                  </tr>
                  <tr><td colspan="2" ><?php echo ($so_id) ? "<input type='submit' class='button smallest' value='Update' name='update_too' />" : ''; ?></td></tr>
                </table>
              </fieldset>
            </td>
          </tr>

          <tr>
            <td width="50%" valign="top">

<?php if( $so_type != "R" ) { ?>

              <fieldset>
                <legend>Vehicle Owner's Information</legend>
                <table align="center" border="0" cellpadding="2" cellspacing="4" style="border-collapse: collapse" width="100%">
                  <tr>
                  	<td class="form_label">First Name:</td>
                  	<td class="form_value" nowrap><input <?php echo $disabled_attr; ?> type="text" id="vehicle_owner_first_name" name="vehicle_owner_first_name" size="20" maxlength="255" value="<?php echo $vehicle_owner_first_name; ?>" onchange="data_changed=true" /></td>
                  </tr>
                  <tr>
                    <td class="form_label"><span class="red">*</span>Company<br />- OR -<br />Last Name:</td>
                    <td class="form_value" nowrap><input <?php echo $disabled_attr; ?> type="text" id="vehicle_owner_last_name" name="vehicle_owner_last_name" size="20" maxlength="255" value="<?php echo $vehicle_owner_last_name; ?>" onchange="data_changed=true" /></td>
                  </tr>
                  <tr>
                    <td class="form_label" nowrap>Phone /<br />Contact Name:</td>
                    <td class="form_value" nowrap><input <?php echo $disabled_attr; ?> type="text" id="vehicle_owner_primary_phone" class="formatPhone10" name="vehicle_owner_primary_phone" size="20" maxlength="255" value="<?php echo $vehicle_owner_primary_phone; ?>" onchange="data_changed=true" />
                    <input <?php echo $disabled_attr; ?> type="text" id="vehicle_owner_contact_name" name="vehicle_owner_contact_name" size="40" maxlength="255" value="<?php echo $vehicle_owner_contact_name; ?>" onchange="data_changed=true" /></td>
                  </tr>
                  <tr>
                    <td class="form_label" nowrap>Mobile / Carrier:</td>
                    <td class="form_value" nowrap><input <?php echo $disabled_attr; ?> type="text" id="vehicle_owner_secondary_phone" class="formatPhone10" name="vehicle_owner_secondary_phone" size="20" maxlength="255" value="<?php echo $vehicle_owner_secondary_phone; ?>" onchange="data_changed=true" /> <?php echo SelectCarrier( $carrier_id, "carrier_id", $disabled_attr ); ?> <span class="smallest"><a title="Carrier not listed?Click here to contact us. NOTE: New window/tab will open." href="contact_us/" target="_blank">Carrier not listed? Click to contact us.</a></span></td>
                  </tr>
                  <tr>
                    <td class="form_label">Email:</td>
                    <td class="form_value" nowrap><input <?php echo $disabled_attr; ?> type="text" id="vehicle_owner_primary_email" name="vehicle_owner_primary_email" size="20" maxlength="255" value="<?php echo $vehicle_owner_primary_email; ?>" onchange="data_changed=true" /></td>
                  </tr>
                </table>
              </fieldset>

<?php } else { ?>

              <fieldset>
                <legend>Vehicle Recondition Status</legend>
                <table align="center" border="0" cellpadding="2" cellspacing="4" style="border-collapse: collapse" width="100%">
<?php 	if( count( $shop_recon_steps ) ) {
				$count = 1;

				foreach( $shop_recon_steps as $rs_id => $rs_details ) {
					$rsid = (string)$rs_id;

					// Determine if this vehicle has checked off, date, id
					$vehicle_recon_status = @$post_recon_steps[$rsid] ? $post_recon_steps[$rsid] : 'pending';
					$vehicle_recon_id = $rsid;
					$vehicle_recon_complete_date = '';
					$vehicle_recon_complete_userid = 0;
					$vehicle_recon_complete_username = 0;

					if( @array_key_exists( $rsid, $vehicle_recon_steps) ) {
						$vehicle_recon_status = $vehicle_recon_steps[$rsid]['recon_step_status'];
						$vehicle_recon_status = $vehicle_recon_status ? $vehicle_recon_status : 'pending';

						$vehicle_recon_id = $rsid;
						$vehicle_recon_complete_date = $vehicle_recon_steps[$rsid]['recon_step_complete_date'];
						$vehicle_recon_complete_userid = $vehicle_recon_steps[$rsid]['recon_step_complete_userid'];
						$vehicle_recon_complete_username = DecodeUser('user', $vehicle_recon_complete_userid);
					}
?>
					<tr>
                  <td class="form_label" width="25%"><?php echo $rs_details['rs_desc'] . ' (' . $count . ')'; ?></td>
                  <td class="form_value" colspan="3" width="75%" nowrap>
                 		<label for="reconstep_<?php echo $rsid; ?>"><input id="reconstep_<?php echo $rsid; ?>_p" type="radio" class="radio" name="reconstep_<?php echo $rsid; ?>" value="pending" <?php echo ( $vehicle_recon_status == 'pending' ) ? ' checked="checked"' : ''; ?> onclick="javascript:data_changed=true;" /> Pending</label>
                 		<label for="reconstep_<?php echo $rsid; ?>"><input id="reconstep_<?php echo $rsid; ?>_s" type="radio" class="radio" name="reconstep_<?php echo $rsid; ?>" value="skip" <?php echo ( $vehicle_recon_status == 'skip') ? ' checked="checked"' : ''; ?> onclick="javascript:data_changed=true;" /> Skip</label>
                 		<label for="reconstep_<?php echo $rsid; ?>"><input id="reconstep_<?php echo $rsid; ?>_c" type="radio" class="radio" name="reconstep_<?php echo $rsid; ?>" value="complete" <?php echo ( $vehicle_recon_status == 'complete') ? ' checked="checked"' : ''; ?> onclick="javascript:data_changed=true;" /> Complete</label>
                 		<span><?php echo ($vehicle_recon_complete_date != "0000-00-00 00:00:00" ) ? $vehicle_recon_complete_date : ''; ?></span>
                 		<span><?php echo ( $vehicle_recon_complete_username && $vehicle_recon_status != 'pending' ) ? $vehicle_recon_complete_username : ''; ?></span>
                  </td>
					</tr>
<?php
					$count++;
				}
			}
 ?>
                </table>
              </fieldset>

<?php } ?>

            </td>
            <td colspan="2" valign="top" width="50%">
              <!-- <fieldset style="height: 190px;"> -->
              <fieldset>
                <legend>Vehicle Information</legend>
                <table align="center" border="0" cellpadding="2" cellspacing="4" style="border-collapse: collapse" width="100%">
                  <tr>
                    <td class="form_label" width="20%"><span class="red">*</span>Year:</td>
                    <td class="form_value" colspan="3" nowrap><?php echo SelectYear( $vehicle_year, "vehicle_year", $disabled_attr ); ?></td>
                  </tr>
                  <tr>
                    <td class="form_label"><span class="red">*</span>Make:</td>
                    <td class="form_value" colspan="3" nowrap><?php echo SelectVehicleMake( $vehicle_make_id, "vehicle_make_id", $disabled_attr ); ?></td>
                  </tr>
                  <tr>
                    <td class="form_label"><span class="red">*</span>Model:</td>
                    <td class="form_value" nowrap><input <?php echo $disabled_attr; ?> type="text" id="vehicle_model" name="vehicle_model" size="20" maxlength="255" value="<?php echo $vehicle_model; ?>" onchange="data_changed=true" /></td>
                  	<td class="form_label">Plate:</td>
                  	<td class="form_value" nowrap><input <?php echo $disabled_attr; ?> type="text" id="vehicle_plate_no" name="vehicle_plate_no" size="20" maxlength="10" value="<?php echo $vehicle_plate_no; ?>" onchange="data_changed=true" /></td>
                  </tr>
                  <tr>
                    <td class="form_label"><span class="red"><?php echo ($so_type == "R") ? '' : '*'; ?></span>Mileage:</td>
                    <td class="form_value" nowrap><input <?php echo $disabled_attr; ?> type="text" name="vehicle_mileage" size="20" maxlength="10" value="<?php echo $vehicle_mileage; ?>" onkeypress="return checkForInt(event)" onchange="data_changed=true" /></td>
                    <td class="form_label">VIN:</td>
                    <td class="form_value" nowrap><input <?php echo $disabled_attr; ?> type="text" id="vehicle_vin" name="vehicle_vin" size="20" maxlength="17" value="<?php echo $vehicle_vin; ?>" /></td>
						  <td colspan="2"></td>
                  </tr>
                  <tr>
                    <td class="form_value" colspan="6" nowrap>
                    		<span class="form_label">Internal:</span>
								<input id="internal_n" type="radio" class="radio" name="internal_job" value="N" <?php echo ($internal_job == 'N') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> <label for="internal_n">No</label>
                    		<input id="internal_y" type="radio" class="radio" name="internal_job" value="Y" <?php echo ($internal_job == 'Y') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> <label for="internal_y">Yes</label>
                    &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<span class="form_label">No Truck:</span>
                    		<input id="no_truck_n" type="radio" class="radio" name="no_truck" value="N" <?php echo ($no_truck == 'N') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> <label for="no_truck_n">No</label>
                    		<input id="no_truck_y" type="radio" class="radio" name="no_truck" value="Y" <?php echo ($no_truck == 'Y') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> <label for="no_truck_y">Yes</label>
                    &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;<span class="form_label">Loaner:</span>
                    		<input id="loaner_n" type="radio" class="radio" name="loaner" value="N" <?php echo ($loaner == 'N' ) ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> <label for="loaner_n">No</label>
                    		<input id="loaner_y" type="radio" class="radio" name="loaner" value="Y" <?php echo ($loaner == 'Y' ) ? ' checked="checked"': ''; ?> onclick="data_changed=true"> <label for="loaner_y">Yes</label>
                    </td>
                  </tr>
                </table>
              </fieldset>
            </td>
          </tr>
          <tr>
            <td width="50%" rowspan="2">
              <fieldset>
                <legend>Comments &amp; Complaints/Concerns</legend>
                <table align="center" border="0" cellpadding="2" cellspacing="4" style="border-collapse: collapse" width="100%">
                  <tr>
                    <td class="form_label" width="5%">Comments:</td>
                    <td class="form_value" colspan="4" nowrap><textarea <?php echo $disabled_attr; ?> rows="3" name="prior_damage_details" style="width: 99%;"><?php
						if( $prior_damage_details )
							echo $prior_damage_details;
						?></textarea></td>
                  </tr>
                  <tr>
                    <td class="form_label">&nbsp;</td>
                    <td class="form_value" nowrap><span class="bold smallest">NOTE: Comments for internal use only. These are not seen by customer.</span></td>
                    <td class="form_value" nowrap>&nbsp;</td>
                    <td class="form_value" nowrap>&nbsp;</td>
										<!-- <td class="form_value" nowrap>&nbsp;</td> -->
                	</tr>

<?php
for( $i = 1; $i <= $complaint_count; $i++ ) {
	$letter = num_to_letter( $i );
	echo "<tr>\n";
	echo "<td class='form_label'>{$letter}:</td>\n";
	$this_complaint_desc = "";
	$this_complaint_amount = "";

	$this_complaint_desc = $complaints[$i][desc];
	$this_complaint_prev_status = $complaints[$i][prev_status];
	$this_complaint_new_status = $complaints[$i][new_status];
	$this_complaint_amount = $complaints[$i][amount];

	echo "<td class='form_value' width='50%' nowrap><input class='complaint_desc' {$disabled_attr} type='text' name='complaints[{$i}][desc]' size='20' maxlength='255' value='{$this_complaint_desc}' onchange='data_changed=true' style='width: 95%;' /></td>\n";

	echo "<td class='form_value' nowrap>\n";
	echo "<input type=\"hidden\" name=\"complaints[{$i}][prev_status]\" value=\"{$this_complaint_prev_status}\" />";

	if ( $this_complaint_prev_status=="A")
		{
			echo "<input type=\"hidden\" name=\"complaints[{$i}][new_status]\" value=\"A\" />";
			echo "Accepted";
		}
	elseif ( $this_complaint_prev_status=="C")
		{
			echo "<input type=\"hidden\" name=\"complaints[{$i}][new_status]\" value=\"C\" />";
			echo "Completed";
		}
	elseif ( $this_complaint_prev_status=="D")
		{
			echo "<input type=\"hidden\" name=\"complaints[{$i}][new_status]\" value=\"D\" />";
			echo "Declined";
		}
	elseif ( $this_complaint_prev_status )
		{
			$code = array( "A", "C", "D", "O" );
			$value = array( "Accepted", "Completed", "Declined", "Open" );
			$select_box = "<select $disabled_attr name=\"complaints[{$i}][new_status]\" size=\"1\">\n";
			$j = 0;
			while( $j < count( $code ) ) {
				$selected = "";
				if( $this_complaint_new_status == $code[$j] ){
					$selected = " selected";
				}
				$select_box .= "<option value=\"$code[$j]\"$selected>$value[$j]</option>\n";
				$j++;
			}
			$select_box .= "</select>\n";
			echo $select_box;
		}
	else
		{
			echo "<input type=\"hidden\" name=\"complaints[{$i}][new_status]\" value=\"O\" />";
			echo "Open";
		}
	echo "</td>\n";

	echo "<td class='form_value' nowrap>\$<input class='complaint_cost' {$disabled_attr} type='text' name='complaints[{$i}][amount]' size='4' maxlength='10' value='{$this_complaint_amount}' onchange='data_changed=true' /></td>\n";

	// echo "<td class='form_value' nowrap><select name='complaints[{$i}][tech] class='compaint_tech' onchange='data_changed=true'><option value='0'>Select Tech</option></select></td>\n";
	echo "</tr>\n";
}
?>
<!--
SELECT user_id, user_first_name, user_last_name
FROM `user`
WHERE user_type='Service Tech'
and user_status = 'A'
and service_provider_id = 84
order by user_last_name, user_first_name
-->
								</table>
              </fieldset>
            </td>

            <link rel="stylesheet" type="text/css" href="_css/vehiclephoto.css" media="screen">
			<link rel="stylesheet" type="text/css" href="uploadify/uploadify.css" />
			<link rel="stylesheet" type="text/css" href="uploadify5/uploadifive.css">
            <script type="text/javascript" src="uploadify/jquery.uploadify-3.1.min.js"></script>
			<script src="uploadify5/jquery.uploadifive.min.js" type="text/javascript"></script>
			<script src="_js/modernizr-2.0.6.min.js" type="text/javascript"></script>
            <td width="50%" colspan="2">
	            <fieldset>
	              <legend>Vehicle Photos</legend>
	              <table align="left" border="0" cellpadding="2" cellspacing="4" style="border-collapse: collapse">
	                <tr>
	                	<td>
<!-- 							<form> -->
								<div id="queue" style="display:none;"></div>
								<input type="file" name="uploadify" id="uploadify" style="display:none;" />
								<input id="uploadifive" name="uploadifive" type="file" multiple="true">
<!-- 							</form> -->
	                	</td>
	                	<td id="service_images">
	                	<?php
	                		$id = $so_id;
	                		$type = 's';
	                		$user = 'a';
	                		include('getimages.php');
	                	?>
	                	</td>
	                </tr>
	              </table>
	            </fieldset>
            </td>
          </tr>

          <script type="text/javascript" src="/_js/vehiclephoto.js"></script>
          <script type="text/javascript">
          	jQuery(document).ready(function($) {

	          	$(document).on("mouseenter", '.cellImage', function(){ $(this).find('.deleteImage').fadeIn(); })
	          		       .on("mouseleave", '.cellImage', function(){ $(this).find('.deleteImage').stop().fadeOut(); }
	          	);

	          	$(document).on("click", '.deleteImage', function(){
	          		var image_id = $(this).attr('name').substr(3);
	          		var parentTD = $(this).parent();
		          	if (confirm('Are you sure you wish to delete this image?')) {
			          	$.post("deleteimage.php", {'type':'s','image_id':image_id}, function(data) {
			          		if (data=="") {
				          		parentTD.remove();
				          	} else {
					          	alert(data);
				          	}
			          	})
		          	}
	          	});

				<?php $timestamp = time();?>

				$(function() {
					var ro_type = 's';
					var _optionsUploadify = {
						'auto'         : true,
						'formData'     : {
											'timestamp' : '<?php echo $timestamp;?>',
											'token'     : '<?php echo md5('unique_salt' . $timestamp);?>',
											'id'        : '<?php echo $so_id; ?>',
											'type'      : ro_type,
											'prefix'    : ro_type+'_'
						},
						'queueID'  : 'queue',
						'swf'      : 'uploadify/uploadify.swf',
						'uploader' : 'uploadify/uploadify.php',
						'onUploadSuccess' : function(file, data, response) {
							$.get("getimages.php", {'type':ro_type,'user':'a','id':<?php echo $so_id; ?>},function(data) {
								$('#service_images').html(data);
							});
		            	}
					}

					var _optionsUploadifive = {
						'auto'             : true,
						'formData'         : {
											   'timestamp' : '<?php echo $timestamp;?>',
											   'token'     : '<?php echo md5('unique_salt' . $timestamp);?>',
											   'id'        : '<?php echo $so_id; ?>',
											   'type'      : ro_type,
											   'prefix'    : ro_type+'_'
						                     },
						'queueID'          : 'queue',
						'uploadScript'     : 'uploadify5/uploadifive.php',
						'onUploadComplete' : function(file, data) {
							$.get("getimages.php", {'type':ro_type,'user':'a','id':<?php echo $so_id; ?>},function(data) {
								$('#service_images').html(data);
							});
						},
						'onFallback'       : function() {
							$('#uploadifive').hide();
							$('#uploadify').show();
							$("#uploadify").uploadify(_optionsUploadify); // browser has failed the test, lets use Uploadify instead
						}
					};

					$("#uploadifive").uploadifive(_optionsUploadifive); // HTML5 enabled browsers will pass the pretest and fire this instead

				});
          	});
		</script>

          <tr>


						<td width="25%">
              <fieldset>
                <legend>New Task Reminder</legend>
                <table align="center" border="0" cellpadding="2" cellspacing="4" style="border-collapse: collapse" width="100%">
                  <tr>
                    <td class="form_label" width="42%">Reminder Date:</td>
                    <td class="form_value" nowrap>
<?php
  if( $disabled_attr ) {
?>
                      <img alt="New task reminder is not allowed for closed SOs" src="images/icon_calendar.gif" border="0">
<?php } else { ?>
                      <input readonly='readonly' class="ro_datefield" type="text" id="new_reminder_date" name="new_reminder_date" value="<?php echo $new_reminder_date; ?>" size="10" onchange="data_changed=true" />
<?php } ?>
                  </tr>
                  <tr>
                    <td class="form_label">Reminder Type:</td>
                    <td class="form_value" nowrap><?php echo SelectReminderService( $new_reminder_type, "new_reminder_type", $disabled_attr ); ?></td>
                  </tr>
                </table>
              </fieldset>
            </td>
            <td width="25%">
              <fieldset>
                <legend>Open Task Reminders</legend>
<?php
if( $so_id ) {
	$now = date( "Y-m-d" );
	$reminder_sql = "SELECT * FROM reminder WHERE service_order_id='$so_id' ORDER BY reminder_date, id";
	$reminder_result = mysql_query( $reminder_sql, $db ) or die( mysql_error( ) . "<br>SQL= " . $reminder_sql );

	if( $reminder_row = mysql_fetch_array( $reminder_result ) ) {
		$reminder_list = "<div class='black center smallest'>Click the check box beside the Reminder to close it.</div>\n";
		$reminder_list .= "<table border='0' cellpadding='1' cellspacing='1' style='border-collapse: collapse'>\n";
		$reminder_list .= "<tr><td colspan='4'>&nbsp;</td></tr>\n";

		$seq = 0;

		do {
			$checked = ($reminder_del[$seq] == "X") ? " checked" : '';

			$reminder_list .= "<tr>\n";
			$reminder_list .= "<td class='center'><input {$disabled_attr} type='checkbox' class='checkbox' name='reminder_del[{$seq}]' value='X' {$checked} /><input type='hidden' name='reminder_id[{$seq}]' value='{$reminder_row['id']}'></td>\n";
			$reminder_list .= "<td class='center'><img alt='Reminder' border='0' src='images/icon_flag_red.gif' height='16' width='16'></td>\n";
			$reminder_list .= "<td class='center'><span class='bold'>" . ReformatDate( $reminder_row["reminder_date"] ) . "</span></td>\n";
			$reminder_list .= "<td>" . DecodeReminder( $reminder_row["reminder_type"] ) . "</td>\n";
			$reminder_list .= "</tr>\n";
			$seq++;
		} while ( $reminder_row = mysql_fetch_array( $reminder_result ) );

		$reminder_list .= "</table>\n";
	} else {
		$reminder_list = "<div class='black center smallest'>No <em>Open Task Reminders</em> on file for this Order.</div>\n";
	}
} else {
	$reminder_list = "<div class='black center smallest'>No <em>Open Task Reminders</em> on file for this Order.</div>\n";
}

echo $reminder_list;
?>
              </fieldset>
						</td>
          </tr>
          <tr class="noprint">
            <td width="50%">
              <fieldset>
                <legend>Service Advisor Notes</legend>
                  <textarea <?php echo $disabled_attr; ?> rows="5" name="service_advisor_note" style="margin-left: 10px; margin-top: 5px; width: 95%;"><?php echo ($service_advisor_note) ? $service_advisor_note : ''; ?></textarea>
              </fieldset>
            </td>
            <td colspan="2">
              <fieldset>
                <legend>Talk to Customer (Customer Message Center)</legend>
<?php if( $lookup_sms_allowed == "Y" ) { ?>
                  <textarea <?php echo $disabled_attr; ?> rows="3" name="talk_to_customer_note" style="margin-left: 10px; margin-top: 5px; width: 95%;"><?php echo ($talk_to_customer_note) ? $talk_to_customer_note : ''; ?></textarea>
                  <br><label for="send_text_message"><input id="send_text_message" <?php echo $disabled_attr; ?> type="checkbox" class="checkbox" style="margin-left: 10px;" name="send_text_message" value="X" <?php echo ($send_text_message=='X') ? ' checked="checked"' : ''; ?> onclick="data_changed=true" /> <span class="bold smallest">Send as text message.</span></label><div class="bold smallest" style="margin-left: 10px;">NOTE: For best results limit text messages to 1 line.</div>
<?php } else { ?>
                  <textarea <?php echo $disabled_attr; ?> rows="5" name="talk_to_customer_note" style="margin-left: 20px; margin-top: 5px; width: 93%;"><?php echo ($talk_to_customer_note) ? $talk_to_customer_note : ''; ?></textarea>
<?php } ?>
              </fieldset>
            </td>
          </tr>
          <tr class="noprint">
            <td colspan="3" width="100%">
              <fieldset>
                <legend>Actions Available</legend>
                <table align="center" border="0" cellpadding="2" cellspacing="4" style="border-collapse: collapse" width="90%">
                  <tr>
                    <td align="center" valign="top" width="100%">
							 <?php echo ( $so_id > 0 ) ? '<span class="smallest">Scroll down to view all My Repair Notes</span><br>&nbsp;<br />' : ''; ?>
                      <input type="submit" class="button" value="<?php echo $updateMode; ?>" name="update" />
                      <input type="submit" class="button" value="Cancel" name="go_to_list" onclick="if (data_changed) return confirm('Are you sure you want to leave this page? All changes will be lost.');" />

<?php
								if( ($lookup_user_is_admin == "Y") && ($updateMode == "Update") ) {
									$confirm_message = "Are you sure you want to delete this Order? Click OK to delete the Order. Click Cancel to return to the Order page without deleting this Order.";
									echo "<input type='submit' value='Delete' name='delete_order' class='delete_button' onClick='javascript:return confirm(\"{$confirm_message}\");'>\n";
								}

								if( ($lookup_user_is_admin == "Y") && ($from == "feedback") ) {
									echo "<input type='submit' class='button' value='Feedback List' name='go_to_feedback_list' onclick='if( data_changed ) return confirm(\"Are you sure you want to leave this page? All changes will be lost.\");' />\n";
								}
 ?>
                    </td>
                  </tr>
                </table>
              </fieldset>
            </td>
          </tr>
<?php if( $so_id > 0 ) { ?>
          <tr>
            <td colspan="3" valign="top" width="100%">
					<?php
					include "includes/so_display_notes.html";
 ?>
            </td>
          </tr>
<?php } ?>
        </table>
      </form>

<?php
}

include "includes/footer.php";

?>