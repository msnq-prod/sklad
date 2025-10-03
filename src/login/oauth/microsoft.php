<?php
require_once __DIR__ . '/../../common/head.php';

use \Firebase\JWT\JWT;

$PAGEDATA['pageConfig'] = ["TITLE" => "Вход через Microsoft"];
$PAGEDATA['microsoftAuthAvailable'] = $CONFIGCLASS->get("AUTH_PROVIDERS_MICROSOFT_APP_ID") != false and $CONFIGCLASS->get("AUTH_PROVIDERS_MICROSOFT_KEYS_SECRET") != false;

if (!$PAGEDATA['microsoftAuthAvailable']) {
	//Display normal login page if oauth isn't available
	header("Location: " . $CONFIG['ROOTURL'] . "/login");
	exit;
}
//Similar setup can be found in the link provider api endpoint
$configObject = [
	"callback" => $CONFIG['ROOTURL'] . '/login/oauth/microsoft.php',
	"keys" => [
		"id" => $CONFIGCLASS->get("AUTH_PROVIDERS_MICROSOFT_APP_ID"),
		"secret" => $CONFIGCLASS->get("AUTH_PROVIDERS_MICROSOFT_KEYS_SECRET")
	],
	"scope" => "openid user.read",
	"tenant" => "common",
];
$adapter = new Hybridauth\Provider\MicrosoftGraph($configObject);

try {
	$adapter->authenticate();
} catch (\Exception $e) {
	//Issue with auth state, which is a problem with the user's browser. We can't do anything about this, so just show an error
        $PAGEDATA['ERROR'] = "К сожалению, произошла ошибка при аутентификации через Microsoft.";
	die($TWIG->render('login/error.twig', $PAGEDATA));
}
$accessToken = $adapter->getAccessToken(); //We don't actually use this - we could in theory just drop it?
$userProfile = $adapter->getUserProfile();
$adapter->disconnect(); //Disconnect this authentication from the session, so they can pick another account
if (strlen($userProfile->identifier) < 1) {
	//ISSUE WITH PROFILE
        $PAGEDATA['ERROR'] = "К сожалению, произошла ошибка при аутентификации через Microsoft";
	echo $TWIG->render('login/error.twig', $PAGEDATA);
	exit;
}

$DBLIB->where("users_oauth_microsoftid", $userProfile->identifier);
$DBLIB->where("users_deleted", 0);
$user = $DBLIB->getOne("users", ["users.users_suspended", "users.users_userid", "users.users_hash", "users.users_email"]);
if ($user) {
	if ($user['users_suspended'] != '0') {
                $PAGEDATA['ERROR'] = "К сожалению, ваша учётная запись заблокирована";
		echo $TWIG->render('login/error.twig', $PAGEDATA);
		exit;
	}

	//Log them in successfully - duplicated below for signup

	$GLOBALS['AUTH']->generateToken($user['users_userid'], false, "Web - Microsoft", "web-session");
	header("Location: " . (isset($_SESSION['return']) ? $_SESSION['return'] : $CONFIG['ROOTURL']));
	exit;
} else {
	//See if an email is found, but not linked to microsoft. We don't want to auto-link them because its a good attack vector, so instead prompt a password login and then link in account settings.
	$DBLIB->where("users_email", strtolower($userProfile->email));
	$user = $DBLIB->getOne("users", ["users.users_suspended", "users.users_userid", "users.users_hash"]);
	if ($user) {
                $PAGEDATA['ERROR'] = "Мы нашли учётную запись AdamRMS, связанную с выбранным адресом эл. почты. Войдите снова, используя имя пользователя и пароль AdamRMS, чтобы привязать аккаунт к Microsoft в настройках AdamRMS.";
		echo $TWIG->render('login/error.twig', $PAGEDATA);
		exit;
	}
}

// They don't have an account already. Check whether we can create them an account.
if ($CONFIGCLASS->get("AUTH_SIGNUP_ENABLED") !== 'Enabled') {
        $PAGEDATA['ERROR'] = "Мы не нашли существующую учётную запись, а регистрация отключена. Свяжитесь с администратором компании, чтобы зарегистрироваться.";
	echo $TWIG->render('login/error.twig', $PAGEDATA);
	exit;
}

//Okay we can't find them, and signup is enabled so lets sign them up to an account.
$username = preg_replace("/[^a-zA-Z0-9]+/", "", $userProfile->firstName . $userProfile->lastName);
while ($AUTH->usernameTaken($username)) {
	$username .= "1";
}
$data = array(
	'users_email' => strtolower($userProfile->email),
	'users_emailVerified' => 0,
	'users_oauth_microsoftid' => $userProfile->identifier,
	'users_username' => $username,
	'users_name1' => $userProfile->firstName,
	'users_name2' => $userProfile->lastName,
	'users_hash' => $CONFIG['AUTH_NEXTHASH']
);
$newUser = $DBLIB->insert("users", $data);
if (!$newUser) {
        $PAGEDATA['ERROR'] = "К сожалению, не удалось создать новую учётную запись";
	echo $TWIG->render('login/error.twig', $PAGEDATA);
	exit;
}
$bCMS->auditLog("INSERT", "users", json_encode($data), null, $newUser);
if (!$_SESSION['return'] and isset($_SESSION['app-oauth'])) {
        $PAGEDATA['ERROR'] = "Учётная запись создана — перезапустите приложение и войдите снова";
	echo $TWIG->render('login/error.twig', $PAGEDATA);
	exit;
} else {
	$GLOBALS['AUTH']->generateToken($newUser, false, "Web - Microsoft", "web-session");
	header("Location: " . (isset($_SESSION['return']) ? $_SESSION['return'] : $CONFIG['ROOTURL']));
	exit;
}
