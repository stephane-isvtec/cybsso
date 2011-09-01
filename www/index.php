<?php
//
// Copyright (C) 2011 Cyril Bouthors <cyril@bouthors.org>
//
// This program is free software: you can redistribute it and/or modify it under
// the terms of the GNU General Public License as published by the Free Software
// Foundation, either version 3 of the License, or (at your option) any later
// version.
//
// This program is distributed in the hope that it will be useful, but WITHOUT
// ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
// FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
// details.
//
// You should have received a copy of the GNU General Public License along with
// this program. If not, see <http://www.gnu.org/licenses/>.
//

require('/etc/cybsso/config.php');
require('cybsso/CybSSOPrivate.php');

# Check return_url
$return_url='http://';
if($_SERVER['SERVER_PORT'] == 443)
	$return_url='https://';

# Default return URL goes to the customer self-care
$return_url .= $_SERVER['HTTP_HOST'] . '/self/';

if(isset($_GET['return_url']))
	$return_url = $_GET['return_url'];
elseif(isset($_POST['return_url']))
	$return_url = $_POST['return_url'];

if(!preg_match('/^https?:\/\/.*$/', $return_url)) {
	echo "Invalid return_url format";
	exit;
}
   
$url_separator='?';
if(strpos($return_url, '?') == true)
	$url_separator='&';

session_start();

# Process action
$action = 'none';
if(isset($_GET['action']))
	$action = $_GET['action'];

if(isset($_POST['action']))
	$action = $_POST['action'];

# Default focus
$focus = 'log-in';

try{
	$cybsso = new CybSSOPrivate;

	switch($action) {

		case 'logout':
			# Delete SSO ticket
			if(isset($_SESSION['user']['email']))
				$cybsso->TicketDelete($_SESSION['user']['email']);

			# Delete cookie
			$_SESSION = array();
			if (ini_get('session.use_cookies')) {
				$params = session_get_cookie_params();
				setcookie(session_name(), '', time() - 42000, $params['path'],
						  $params['domain'], $params['secure'],
						  $params['httponly']);
			}

			# Destroy session
			session_destroy();

			# Redirect and show a message
			header("Location: ./?message=logout&return_url=$return_url");
			exit;

		case 'Log in':
			$focus = 'log-in';
			$ticket = $cybsso->TicketCreate($_POST['email'], $_POST['password']);
			$email = $_POST['email'];
			break;

		case 'Create account':
			$focus = 'create-account';
			$ticket = $cybsso->UserCreate($_POST);
			$email = $_POST['email'];
			break;

		case 'Password recovery':
			$focus = 'password-recovery';
			$cybsso->PasswordRecovery($_POST['email'], $return_url);
			$_GET['message'] = 'password sent';
			$focus = 'none';
			break;

		case 'Password recovery2':
			$focus = 'none';
			$cybsso->PasswordRecoveryCheckTicket($_GET['email'], $_GET['ticket']);
			$focus = 'new-password';
			break;

		case 'Password recovery3':
			$focus = 'new-password';
			$cybsso->PasswordRecoveryCheckTicket($_POST['email'],
												 $_POST['ticket']);
			$cybsso->PasswordReset($_POST['email'],
								   $_POST['password'],
								   $_POST['password2']);

			$ticket = $cybsso->TicketCreate($_POST['email'], $_POST['password']);
			$email = $_POST['email'];
			break;

		default:
			# Check ticket if no particular action was requested and a valid
			# session has been found
			if(!isset($_SESSION['ticket']) or
			   !isset($_SESSION['user']['email']))
				break;

			$cybsso->TicketCheck($_SESSION['ticket'],
								 $_SESSION['user']['email']);

			$ticket = array('name' => $_SESSION['ticket']);
			$email = $_SESSION['user']['email'];
	}

	if(isset($ticket)) {

		$_SESSION = array(
			'ticket' => $ticket['name'],
			'user'   => $cybsso->UserGetInfo($email),
		);

		header('Location: '. $return_url . $url_separator .
			   "cybsso_ticket=$ticket[name]&cybsso_email=$email");
		exit;
	}
}
catch(SoapFault $fault) {
	echo '<font color="red">'.$fault->getMessage() . '</font>';

	# Delete cookie
	$_SESSION = array();
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'],
				  $params['domain'], $params['secure'],
				  $params['httponly']);
	}

	# Destroy session
	session_destroy();
}

?>

<html>
<head>
<script>
	function tabbed(elt) 
	{
		var elem = document.getElementsByClassName('onglet');
    		for (var i = 0; i < elem.length; i++) {
		        elem[i].style.display='none';
    		};

		document.getElementById(elt).style.display='block';
	}
</script>
<title>Account</title>
</head>
<body>
<?
$messages = array(
	'logout'            => _('You are now successfully logged out'),
	'password sent'     => _('Successfully sent password recovery '.
							 'instructions by email, please check'),
	'password modified' => _('Password successfully modified'),
);

if(isset($_GET['message']))
	echo $messages[$_GET['message']] . "<br/>";
?>

<a href="#" onclick="tabbed('log-in')">Log in</a> /
<a href="#" onclick="tabbed('create-account')">Create account</a> /
<a href="#" onclick="tabbed('password-recovery')">Lost password</a>

<div class="onglet" id="log-in" style="display:none;">
<h3>Log in</h3>
<form method="POST" action="./">
 Email: <input type="text" name="email" value="<?=isset($_POST['email'])?$_POST['email']:''?>" /> <br/>
 Password: <input type="password" name="password" value="<?=isset($_POST['password'])?$_POST['password']:''?>" /> <br/>
 <input type="hidden" name="return_url" value="<?=$return_url?>" />
 <input type="submit" name='action' value="Log in"/>
</form>
</div>

<div class="onglet" id="create-account" style="display:none;">
<h3>Create new account</h3>
<form method="POST" action="./">
 Firstname: <input type="text" name="firstname" value="<?=isset($_POST['firstname'])?$_POST['firstname']:''?>" /> <br/>
 Lastname:  <input type="text" name="lastname" value="<?=isset($_POST['lastname'])?$_POST['lastname']:''?>" /> <br/>
 Email: <input type="text" name="email" value="<?=isset($_POST['email'])?$_POST['email']:''?>" /> <br/>
 Password: <input type="password" name="password" value="<?=isset($_POST['password'])?$_POST['password']:''?>" /> <br/>
	  Language: 
  <select name="language">
    <option value="fr_FR">French</option>
    <option value="en_US" <?if(isset($_POST['language']) and $_POST['language'] == 'en_US') echo 'selected';?> >English</option>
  </select>
<br/>
 <input type="hidden" name="return_url" value="<?=$return_url?>" />
 <input type="submit" name='action' value="Create account"/>
</form>
</div>


<div class="onglet" id="password-recovery" style="display:none;">
<h3>Password recovery</h3>
<form method="POST" action="./">
 Email: <input type="text" name="email" value="<?=isset($_POST['email'])?$_POST['email']:''?>" />
 <br/>
 <input type="hidden" name="return_url" value="<?=$return_url?>" />
 <input type="submit" name='action' value="Password recovery">
</form>
</div>

<div class="onglet" id="new-password" style="display:none;">
<h3>Enter new password</h3>
<form method="POST" action="./">
 <input type="hidden" name="email" value="<?=isset($_POST['email'])?$_POST['email']:''?>" />
 <input type="hidden" name="ticket" value="<?=isset($_POST['ticket'])?$_POST['ticket']:''?>" />
 <input type="hidden" name="return_url" value="<?=$return_url?>" />
 Password: <input type="password" name="password" value="<?=isset($_POST['password'])?$_POST['password']:''?>" /> <br/>
 Password (again): <input type="password" name="password2" value="<?=isset($_POST['password2'])?$_POST['password2']:''?>" /> <br/>
 <input type="submit" name='action' value="Password recovery3">
</form>
</div>

<script>
 tabbed('<?=$focus?>');
</script>

</body>
</html>
