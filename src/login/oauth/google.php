<?php
require_once __DIR__ . '/../../common/head.php';

use \Firebase\JWT\JWT;

$PAGEDATA['pageConfig'] = ["TITLE" => "Вход через Google"];
$PAGEDATA['googleAuthAvailable'] = $CONFIGCLASS->get("AUTH_PROVIDERS_GOOGLE_KEYS_ID") != false and $CONFIGCLASS->get("AUTH_PROVIDERS_GOOGLE_KEYS_SECRET") != false;

if (!$PAGEDATA['googleAuthAvailable']) {
	//Display normal login page if Google isn't available
	echo $TWIG->render('login/login.twig', $PAGEDATA);
	exit;
}
//Similar setup can be found in the link provider api endpoint
$configObject = [
	"callback" => $CONFIG['ROOTURL'] . '/login/oauth/google.php',
	"keys" => [
		"id" => $CONFIGCLASS->get("AUTH_PROVIDERS_GOOGLE_KEYS_ID"),
		"secret" => $CONFIGCLASS->get("AUTH_PROVIDERS_GOOGLE_KEYS_SECRET")
	],
	"scope" => $CONFIGCLASS->get("AUTH_PROVIDERS_GOOGLE_SCOPE"),
];
$adapter = new Hybridauth\Provider\Google($configObject);
/**
 * 3. Sign in a user with Google
 *
 * Hybridauth will attempt to negotiate with the Google api and authenticate the user.
 * This call will basically do one of 3 things...
 * 1) Redirect (with exit) away to show an authentication screen for a provider (e.g. Facebook's OAuth confirmation page)
 * 2) Finalize an incoming authentication and store access data in a session
 * 3) Confirm a session exists and do nothing
 * If for whatever reason the process fails, Hybridauth will then throw an exception.
 *
 * Note that if the user is already authenticated, then any subsequent call to this method will be ignored.
 */
try {
	$adapter->authenticate();
} catch (\Exception $e) {
	//Issue with auth state, which is a problem with the user's browser. We can't do anything about this, so just show an error
        $PAGEDATA['ERROR'] = "К сожалению, произошла ошибка при аутентификации через Google.";
	die($TWIG->render('login/error.twig', $PAGEDATA));
}
$accessToken = $adapter->getAccessToken(); //We don't actually use this - we could in theory just drop it?
$userProfile = $adapter->getUserProfile();
$adapter->disconnect(); //Disconnect this authentication from the session, so they can pick another account
if (strlen($userProfile->identifier) < 1) {
	//ISSUE WITH PROFILE
        $PAGEDATA['ERROR'] = "К сожалению, произошла ошибка при аутентификации через Google";
	echo $TWIG->render('login/error.twig', $PAGEDATA);
	exit;
}
if (strlen($userProfile->emailVerified) < 1) {
        $PAGEDATA['ERROR'] = "Подтвердите адрес эл. почты в Google, чтобы продолжить вход";
	echo $TWIG->render('login/error.twig', $PAGEDATA);
	exit;
}

$DBLIB->where("users_oauth_googleid", $userProfile->identifier);
$DBLIB->where("users_deleted", 0);
$user = $DBLIB->getOne("users", ["users.users_suspended", "users.users_userid", "users.users_hash", "users.users_emailVerified", "users.users_email"]);
if ($user) {
	if ($user['users_suspended'] != '0') {
                $PAGEDATA['ERROR'] = "К сожалению, ваша учётная запись заблокирована";
		echo $TWIG->render('login/error.twig', $PAGEDATA);
		exit;
	}

	if ($user['users_emailVerified'] != 1 and strtolower($userProfile->emailVerified) == strtolower($user['users_email'])) {
		// Update their email verification status
		$DBLIB->where("users_userid", $user['users_userid']);
		$DBLIB->update("users", ["users_emailVerified" => 1]);
	}

	//Log them in successfully - duplicated below for signup
	if (!$_SESSION['return'] and isset($_SESSION['app-oauth'])) {
		$token = $GLOBALS['AUTH']->generateToken($user['users_userid'], false, "App OAuth - Google", "app-v1");
		$jwt = $GLOBALS['AUTH']->issueJWT($token, $user['users_userid'], "app-v1");
		header("Location: " . $_SESSION['app-oauth'] . "oauth_callback?token=" . $jwt);
		exit;
	} else {
		$GLOBALS['AUTH']->generateToken($user['users_userid'], false, "Web - Google", "web-session");
		header("Location: " . (isset($_SESSION['return']) ? $_SESSION['return'] : $CONFIG['ROOTURL']));
		exit;
	}
} else {
	//See if an email is found, but not linked to google. We don't want to auto-link them because its a good attack vector, so instead prompt a password login and then link in account settings.
	$DBLIB->where("users_email", strtolower($userProfile->emailVerified));
	$user = $DBLIB->getOne("users", ["users.users_suspended", "users.users_userid", "users.users_hash", "users.users_emailVerified"]);
	if ($user) {
                $PAGEDATA['ERROR'] = "Мы нашли учётную запись AdamRMS, связанную с выбранным адресом эл. почты. Войдите снова, используя имя пользователя и пароль AdamRMS, чтобы привязать аккаунт к Google в настройках AdamRMS.";
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
	'users_email' => strtolower($userProfile->emailVerified),
	'users_emailVerified' => 1,
	'users_oauth_googleid' => $userProfile->identifier,
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
	$GLOBALS['AUTH']->generateToken($newUser, false, "Web - Google", "web-session");
	header("Location: " . (isset($_SESSION['return']) ? $_SESSION['return'] : $CONFIG['ROOTURL']));
	exit;
}
