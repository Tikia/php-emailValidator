<?php
date_default_timezone_set("UTC");
function aff($data,$title=false) {
	if($title) {
		echo "<fieldset><legend>".$title."</legend>";
	}
	echo "<pre>";
	print_r($data);
	echo "</pre>";
	if($title) {
		echo "</fieldset>";
	}
}
function displayMicrotime($mtime) {
	$sec=intval($mtime);
	$usec=round($mtime-$sec,4);
	$usec=str_replace("0.",".",$usec);
	return date('H:i:s',$sec).$usec;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Email Validator</title>
    </head>
    <style type="text/css">
		/* redefine html tag style */ 
		html, body {
			font-family: "Trebuchet MS", Helvetica, Arial, sans-serif;
			font-size: 12px;
		}
		h1,h2,h3 {
			color: #036;
		}
		h1 {
			font-size: 24px;
		}
		h2 {
			font-size: 20px;
		}
		h3 {
			font-size: 16px;
		}
		td {
			padding: 5px;	
		}
		/* define custom style */
		.smallText {
			font-size: 10px;
		}
	</style>
    <body>
    <h1>Email validator</h1>
    <h2>Input</h2>
    <form action="./index.php" method="post" name="emails">
    <table border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td align="right" valign="top">Emails to test :<span class="smallText"><br/>(One email by line or<br />use separator = ',' or ';')</span></td>
        <td width="10">&nbsp;</td>
        <td><textarea name="emails" cols="80" rows="10"><?php if(isset($_POST['emails'])) { echo $_POST['emails']; } ?></textarea></td>
      </tr>
      <tr>
        <td align="right" valign="top">Adresse "From" :</td>
        <td width="10">&nbsp;</td>
        <td><input type="mail" name="from" value="<?php if(isset($_POST['from'])) { echo $_POST['from']; } else { echo "user@domain.com"; } ?>" /></td>
      </tr>
      <tr>
        <td align="right" valign="top">Activer les logs :</td>
        <td width="10">&nbsp;</td>
        <td><input type="checkbox" name="log" value="1"<?php if(isset($_POST['log']) && $_POST['log']==1) { echo ' checked="checked"'; } ?> /></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td><input name="sendTest" type="submit" value="Test" /></td>
      </tr>
    </table>
    </form>
    <?php
	if(isset($_POST['emails'])) {
	?>
    <h2>Results</h2>
    <table border="1" cellspacing="0" cellpadding="0">
      <tr>
        <th scope="col">Tested email address</th>
        <th scope="col">Status</th>
        <th scope="col">Délai</th>
        <th scope="col">Log MX</th>
        <th scope="col">Log Smtp</th>
      </tr>
        <?php
        // require VerifyEmail Class
        require_once('class.emailValidator.php');
        // the email to validate
		$search=array("\r\n","\n","\r",",");
		$replace=array(";",";",";",";");
		$emails_tmp=str_replace($search,$replace,$_POST['emails']);
        $emails = explode(";",$emails_tmp);
        // instantiate the class
        $vmail = new emailValidator();
		$vmail->setFrom($_POST['from']);
		$vmail->setLog(false);
		if(isset($_POST['log']) && $_POST['log']==1) {
			$vmail->setLog();
		}
		$time_start=microtime(true);
        // view results
        foreach($emails as $email) {
			$vmail->resetLog();
			$email=trim($email);
			echo "<tr>";
			echo "<th valign='top' align='left' scope='row'>".$email."</th>";
			if($email!="" && $vmail->isValid($email)) {
				list($user, $domain) = $vmail->parseEmail($email);
				$mxHosts=$vmail->getMXrecords($domain,false);
				if(count($mxHosts)>0) {
					$vmail->setPort(25);
					$result=$vmail->callSmtp($email,$mxHosts,$email);
					if ($result=='email') {
						echo "<td valign='top' align='center'><img src='./imgs/exist_64x64.png' alt='Exist' title='Email exist !' width='16' height='16' /></td>";
					}
					elseif($result=='domain') {
						echo "<td valign='top' align='center'><img src='./imgs/mx-exist_64x64.png' alt='MX Exist' title=\"Domain has a mail server, but user don't exist !\" width='16' height='16' /></td>";
					}
					else {
						$vmail->setPort(587);
						$result=$vmail->callSmtp($email,$mxHosts,$email);
						if ($result=='email') {
							echo "<td valign='top' align='center'><img src='./imgs/exist_64x64.png' alt='Exist' title='Email exist !' width='16' height='16' /></td>";
						}
						elseif($result=='domain') {
							echo "<td valign='top' align='center'><img src='./imgs/mx-exist_64x64.png' alt='MX Exist' title=\"Domain has a mail server, but user don't exist !\" width='16' height='16' /></td>";
						}
						else {
							$vmail->setPort(465);
							$result=$vmail->callSmtp($email,$mxHosts,$email);
							if ($result=='email') {
								echo "<td valign='top' align='center'><img src='./imgs/exist_64x64.png' alt='Exist' title='Email exist !' width='16' height='16' /></td>";
							}
							elseif($result=='domain') {
								echo "<td valign='top' align='center'><img src='./imgs/mx-exist_64x64.png' alt='MX Exist' title=\"Domain has a mail server, but user don't exist !\" width='16' height='16' /></td>";
							}
							else {
								echo "<td valign='top' align='center'>???</td>";
							}
						}
					}
				}
				else {
					echo "<td valign='top' align='center'><img src='./imgs/valid_64x64.png' alt='Valid' title='Email is valid but no mail server answer !' width='16' height='16' /></td>";
				}
			}
			else {
				echo "<td valign='top' align='center' colspan=''><img src='./imgs/notValid_64x64.png' alt='Not valid' title='Email is not valid !' width='16' height='16' /></td>";
			}
			$time_end=microtime(true);
			$time_delta=$time_end-$time_start;
			echo "<td valign='top' align='center'>".displayMicrotime($time_delta)." s</td>";
			if(isset($_POST['log']) && $_POST['log']==1) {
				echo "<td valign='top' align='right'>".$vmail->getLogMx()."</td>";
				echo "<td valign='top' align='right'>".$vmail->getLogSmtp()."</td>";
			}
			else {
				echo "<td>&nbsp;</td>";
				echo "<td>&nbsp;</td>";
			}
        }
        ?>
    </table>
    <?php
	}
	?>
    </body>
</html>
