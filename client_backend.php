<?php
    $ini = parse_ini_file('/home/dries/appointment_tool.ini');
    $error_prefix = "client_backend.php --";
	if (isset($_POST['action'])) {
		switch ($_POST['action']) {
			case 'createNewUser':				               createNewUser($_POST['firstname'],$_POST['lastname'],$_POST['companyname'],$_POST['email'],$_POST['phone'],$_POST['plan_type'],$_POST['duration']);
				break;
            }
	}

	function createNewUser($firstname,$lastname,$companyname,$email,$phone,$plan_type,$duration){
            global $ini,$error_prefix; //to make sure the global variables are used
			$complete = true;
			$bericht = "";
			if($firstname == ""){
				$complete = false;
				$bericht = "Please fill in your first name.\n";
			}
			if($lastname == ""){
				$complete = false;
				$bericht = "Please fill in your last name.\n";
			}
			if($email == ""){
				$complete = false;
				$bericht = $bericht . "Please fill in your email.\n";
			}
			else{
				if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
					$bericht = $bericht . "Please fill in a valid email.\n";
					$complete = false;
				}
			}
			if($phone == ""){
				$complete = false;
				$bericht = $bericht . "Please fill in your phone number.\n";
			}
			//if all info is filled in then complete == true
			if($complete){
                //create a new user                    
                //Create database connection
                $pw = $ini['db_name']; //database info is stored in the appointment_tool.ini file
                $link = mysqli_connect("localhost", $ini['db_user'], $ini['db_password'], $ini['db_name']);
                if (!$link) {
                    error_log(date('d.m.Y h:i:s')."--".$error_prefix." Unable to connect to MySQL." . PHP_EOL ."\n", 3, $ini['db_logs_path']);
                    error_log(date('d.m.Y h:i:s')."--".$error_prefix." mysqli_connect_errno() --" . mysqli_connect_errno() . PHP_EOL ."\n", 3, $ini['db_logs_path']);
                    error_log(date('d.m.Y h:i:s')."--".$error_prefix." mysqli_connect_error() --" . mysqli_connect_error() . PHP_EOL ."\n", 3, $ini['db_logs_path']);
                    exit;
                }
                else{
                    $sql = "INSERT INTO general_users (user_first_name, user_last_name, user_company_name, user_email, user_phone, user_license_id) VALUES ('".$firstname."','".$lastname."','".$companyname."','".$email."','".$phone."', NULL)";
                    if (mysqli_query($link, $sql)) {
                        error_log(date('d.m.Y h:i:s')."--".$error_prefix."_OK_" . PHP_EOL ."\n", 3, $ini['db_logs_path']);
                        echo date('d.m.Y h:i:s')."--".$error_prefix."_OK_" . PHP_EOL ."\n";
                        //get the generated ID to link to the license
                        $user_id = "NO_ID";
                        $sql = "SELECT user_id FROM general_users WHERE user_email = \"".$email."\" && user_company_name =\"".$companyname."\"";
                        $result = mysqli_query($link, $sql);
                        if ($result) {
                            error_log(date('d.m.Y h:i:s')."--".$error_prefix."_OK_" . PHP_EOL ."\n", 3, $ini['db_logs_path']);
                            $row = mysqli_fetch_assoc($result);
                            $user_id = $row["user_id"];
                        } else {
                            error_log(date('d.m.Y h:i:s')."--".$error_prefix."Error:" . $sql . "--" . mysqli_error($link) ."\n", 3, $ini['db_logs_path']);
                            $user_id = "NO_ID";
                        }
                    } else {
                        error_log(date('d.m.Y h:i:s')."--".$error_prefix."Error:" . $sql . "--" . mysqli_error($link) ."\n", 3, $ini['db_logs_path']);
                        echo date('d.m.Y h:i:s')."--".$error_prefix."Error:" . $sql . "--" . mysqli_error($link) ."\n";
                    }	    	
                    if($user_id != "NO_ID"){
                        //license in database - starting with todays date as it is a new account
                        $today = new DateTime(); 
                        $today_string = date_format($today, 'Y-m-d');
                        if($plan_type == "trail"){ //if it's a trail account, it only counts for 3 months
                            $license_paid = '0001-01-01';
                            $today->modify('+3 months');
                            $end = date_format($today, 'Y-m-d');
                        }
                        else{
                            $license_paid = $today;
                            $today->modify('+'.$duration.' months');
                            $end = date_format($today, 'Y-m-d');
                        }
                        $sql = "INSERT INTO general_licenses (license_account_type, license_paid, license_start_date, license_end_date,license_user_id)	VALUES ('".$plan_type."','".$license_paid."','".$today_string."','".$end."','".$user_id."')";
                        if (mysqli_query($link, $sql)) {
                            error_log(date('d.m.Y h:i:s')."--".$error_prefix."_OK_" . PHP_EOL ."\n", 3, $ini['db_logs_path']);
                        } else {
                            error_log(date('d.m.Y h:i:s')."--".$error_prefix."Error:" . $sql . "--" . mysqli_error($link) ."\n", 3, $ini['db_logs_path']);
                        }	

                        $sql = "SELECT license_id FROM general_licenses WHERE license_user_id = \"".$user_id."\" && license_start_date =\"".$today_string."\" && license_account_type = \"".$plan_type."\"";
                        $result = mysqli_query($link, $sql);
                        if ($result) {
                            error_log(date('d.m.Y h:i:s')."--".$error_prefix."_OK_" . PHP_EOL ."\n", 3, $ini['db_logs_path']);
                            $row = mysqli_fetch_assoc($result);
                            $license_id = $row["license_id"];
                            //license id found so update the user and add the ID to the record of the user
                            $sql = "UPDATE general_users SET user_license_id=\"".$license_id."\" WHERE user_id=\"".$user_id."\"";
                            if (mysqli_query($link, $sql)) {
                                error_log(date('d.m.Y h:i:s')."--".$error_prefix."_OK_" . PHP_EOL ."\n", 3, $ini['db_logs_path']);
                            } else {
                                error_log(date('d.m.Y h:i:s')."--".$error_prefix."Error:" . $sql . "--" . mysqli_error($link) ."\n", 3, $ini['db_logs_path']);
                            }	
                        } else {
                            error_log(date('d.m.Y h:i:s')."--".$error_prefix."Error:" . $sql . "--" . mysqli_error($link) ."\n", 3, $ini['db_logs_path']);
                        }	
 
                    }                    
                }

                
                /*
                    //TODO: change this to a thank you for registering... message
					$bericht = 
					"<div class=\"col-md-12 top-buffer brown_text\">"
						."<h1 class=\"font_Khula\" brown_text\" align=\"left\">Bedankt voor het maken van een afspraak op <a class=\"orange_text bold_text\">".date("d-m-Y",strtotime($date))."</a> om <a class=\"orange_text bold_text\">".$time."</a>.</h1>"
					."</div>"
					."<div>"
					.'<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2510.7758153433583!2d4.330278215220721!3d51.001814755364286!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47c3e9c37b0e00f1%3A0x3a07463f3d43ce8b!2sDi%C3%ABtiste+%26+Diabeteseducator+Borah+Van+Doorslaer!5e0!3m2!1snl!2sbe!4v1530373189648" width="600" height="450" frameborder="0" style="border:0" allowfullscreen></iframe>'
					."</div>";
                */
			}
			print $bericht;
	}

	function send_email($date,$time,$name,$email,$phone,$remark,$type){
		$client = new Google_Client();
		$client->setApplicationName('Gmail API PHP Quickstart');
		// All Permissions
		$client->addScope("https://mail.google.com/");
		$client->setAuthConfig('/home/borahv1q/borah-secrets/client_secret_gmail.json');
		$client->setAccessType('offline');

		// Load previously authorized credentials from a file.
		$credentialsPath = '/home/borahv1q/borah-secrets/credentials_gmail.json';
		if (file_exists($credentialsPath)) {
			$accessToken = json_decode(file_get_contents($credentialsPath), true);
		} else {
			printf("Er is een probleem met de mailing functionaliteit. \n Gelieve een mail te sturen naar dietiste.borah@gmail.com");
			error_log(date('d.m.Y h:i:s')."--"."mail-issue in send_email.\n", 3, "/home/borahv1q/logs/php-afspraken-backend.log");
		}
		$client->setAccessToken($accessToken);

		// Refresh the token if it's expired.
		if ($client->isAccessTokenExpired()) {
			$client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
			file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
		}

		$service = new Google_Service_Gmail($client);

		if($type=="opvolg"){
			$strMailContent = 'Beste '. $name .',<br/><br/>hierbij bevestig ik jouw opvolgconsultatie op '.date("d-m-Y",strtotime($date)). ' om '.$time. '.<br/><br/>Volgende opmerkingen werden toegevoegd:<br/>'.$remark.'<br/><br/>Gelieve een seintje te geven indien je niet aanwezig kan zijn op deze afspraak.<br/><br/><br/>Met vriendelijke groeten,<br/><br/>Borah Van Doorslaer<br/>+32 485 36 04 09<br/>Stuiverstraat 17/1, 1840 Londerzeel';
		}
		else{
			$strMailContent = 'Beste '. $name .',<br/><br/>hierbij bevestig ik jouw startconsultatie op '.date("d-m-Y",strtotime($date)). ' om '.$time. '.<br/><br/>Volgende opmerkingen werden toegevoegd:<br/>'.$remark.'<br/><br/>Gelieve een seintje te geven indien je niet aanwezig kan zijn op deze afspraak.<br/><br/><br/>Met vriendelijke groeten,<br/><br/>Borah Van Doorslaer<br/>+32 485 36 04 09<br/>Stuiverstraat 17/1, 1840 Londerzeel';
		}
		$strMailTextVersion = strip_tags($strMailContent, '');

		$strRawMessage = "";
		$boundary = uniqid(rand(), true);
		$subjectCharset = $charset = 'utf-8';
		$strToMailName = $name;
		$strToMail = $email;
		$strToMailNameBcc = 'Diëtiste Borah';
		$strToMailBcc = 'dietiste.borah@gmail.com';
		$strSesFromName = 'Diëtiste Borah';
		$strSesFromEmail = 'dietiste.borah@gmail.com';
		$strSubject = 'Afspraak Dïetiste Borah op '. date("d-m-Y",strtotime($date)) .' om '. $time;

		$strRawMessage .= 'To: ' . encodeRecipients($strToMailName . " <" . $strToMail . ">") . "\r\n";
		$strRawMessage .= 'Bcc: '. encodeRecipients($strToMailNameBcc . " <" . $strToMailBcc . ">") . "\r\n";
		$strRawMessage .= 'From: '. encodeRecipients($strSesFromName . " <" . $strSesFromEmail . ">") . "\r\n";

		$strRawMessage .= 'Subject: =?' . $subjectCharset . '?B?' . base64_encode($strSubject) . "?=\r\n";
		$strRawMessage .= 'MIME-Version: 1.0' . "\r\n";
		$strRawMessage .= 'Content-type: Multipart/Alternative; boundary="' . $boundary . '"' . "\r\n";


		$strRawMessage .= "\r\n--{$boundary}\r\n";
		$strRawMessage .= 'Content-Type: text/plain; charset=' . $charset . "\r\n";
		$strRawMessage .= 'Content-Transfer-Encoding: 7bit' . "\r\n\r\n";
		$strRawMessage .= $strMailTextVersion . "\r\n";

		$strRawMessage .= "--{$boundary}\r\n";
		$strRawMessage .= 'Content-Type: text/html; charset=' . $charset . "\r\n";
		$strRawMessage .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
		$strRawMessage .= $strMailContent . "\r\n";

		//Send Mails
		//Prepare the message in message/rfc822
		try {
			// The message needs to be encoded in Base64URL
			$mime = rtrim(strtr(base64_encode($strRawMessage), '+/', '-_'), '=');
			$msg = new Google_Service_Gmail_Message();
			$msg->setRaw($mime);
			$objSentMsg = $service->users_messages->send("me", $msg);

			//print('Hartelijk dank voor het maken van een afspraak op '.$date.' om '.$time);

		} catch (Exception $e) {
			error_log(date('d.m.Y h:i:s')."--"."mail-issue:".$e->getMessage()."\n", 3, "/home/borahv1q/logs/php-afspraken-backend.log");
		}

	}

	function getMonthNumber($monthstr){
		$month="";
		//get number of month
		switch ($monthstr){
			case "Januari":
				$month="01";
				break;
			case "Februari":
				$month="02";
				break;
			case "Maart":
				$month="03";
				break;
			case "April":
				$month="04";
				break;
			case "Mei":
				$month="05";
				break;
			case "Juni":
				$month="06";
				break;
			case "Juli":
				$month="07";
				break;
			case "Augustus":
				$month="08";
				break;
			case "September":
				$month="09";
				break;
			case "Oktober":
				$month="10";
				break;
			case "November":
				$month="11";
				break;
			case "December":
				$month="12";
				break;
			default:
				break;		
		}
		return $month;
	}
?>