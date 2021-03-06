<?php
session_name('yana-server'); 
session_start();
$start=microtime(true);
ini_set('display_errors','1');

error_reporting(E_ALL & ~E_NOTICE);
//Calage de la date
date_default_timezone_set('Europe/Paris'); 

//Idleman : Active les notice uniquement pour ma config reseau (pour le débug), pour les user il faut la désactiver
//car les notices peuvent gener les reponses json, pour les dev ajoutez votre config dans une même if en dessous.
if($_SERVER["HTTP_HOST"]=='192.168.0.14' && $_SERVER['REMOTE_ADDR']=='192.168.0.69') error_reporting(E_ALL); 

mb_internal_encoding('UTF-8');

global $myUser,$conf,$_;
//Récuperation et sécurisation de toutes les variables POST et GET
$_ = array_map('Functions::secure',array_merge($_POST,$_GET));
$error = '';

require_once(dirname(__FILE__).'/constant.php');

$versions = json_decode(file_get_contents('db.json'),true);



if(!file_exists(DB_NAME) || (file_exists(DB_NAME) && filesize(DB_NAME)==0)){
	file_put_contents('dbversion',$versions[0]['version']);
	header('location:install.php');
}else{
	if(file_exists('install.php')) $error .= ($error!=''?'<br/>':'').'<strong>Attention: </strong> Par mesure de sécurité, pensez à supprimer le fichier install.php';
}

if(file_exists('db.json')){

	if(!file_exists('dbversion')) file_put_contents('dbversion', '0');
	$current = file_get_contents('dbversion');
	$versions = json_decode(file_get_contents('db.json'),true);
	if($current<$versions[0]['version']){
		Functions::alterBase($versions,$current);
		file_put_contents('dbversion',$versions[0]['version']);
	}
}

require_once(dirname(__FILE__).'/RainTPL.php');

$error = (isset($_['error']) && $_['error']!=''?'<strong>Erreur: </strong> '.str_replace('|','<br/><strong>Erreur: </strong> ',(urldecode($_['error']))):false);
$message = (isset($_['notice']) && $_['notice']!=''?'<strong>Message: </strong> '.str_replace('|','<br/><strong>Message: </strong> ',(urldecode($_['notice']))):false);

function __autoload($class_name){
    include 'classes/'.$class_name . '.class.php';
}


if(file_exists(dirname(__FILE__).'/.tool.php')){
	require_once(dirname(__FILE__).'/.tool.php');
	
	switch($tool->type){
	case 'reset_password':
		if($tool->login != null && $tool->password != null){
			$userManager = new User();
			$usr = $userManager->load(array('login'=>$tool->login));
			$usr->setPassword($tool->password);
			$usr->save();
			unlink(dirname(__FILE__).'/.tool.php');
		}
	break;
	}
}



$myUser = false;
$conf = new Configuration();
$conf->getAll();
//Inclusion des plugins  
Plugin::includeAll($conf->get("DEFAULT_THEME"));


$userManager = new User();

if(isset($_SESSION['currentUser'])){
	$myUser =unserialize($_SESSION['currentUser']);
}else{
	if(AUTO_LOGIN!=''){
		$myUser = $userManager->exist(AUTO_LOGIN,'',true);
		$_SESSION['currentUser'] = serialize($myUser);
	}
}
if(!$myUser && isset($_COOKIE[$conf->get('COOKIE_NAME')])){
	$users = User::getAllUsers();
	foreach ($users as $user) {
		if($user->getCookie() == $_COOKIE[$conf->get('COOKIE_NAME')]) 
			{
				$myUser = $user;
				$myUser->loadRight();
			}
	}
}



//Instanciation du template
$tpl = new RainTPL();


//Definition des dossiers de template
raintpl::configure("base_url", null );
raintpl::configure("tpl_dir", './templates/'.$conf->get('DEFAULT_THEME').'/' );
raintpl::configure("cache_dir", "./cache/tmp/" );
$view = '';

$rank = new Rank();
if($myUser!=false && $myUser->getRank()!=false){
	$rank = $rank->getById($myUser->getRank());
}

$tpl->assign('myUser',$myUser);
$tpl->assign('userManager',$userManager);
$tpl->assign('configurationManager',$conf);
$tpl->assign('error',$error);
$tpl->assign('notice',$message);
$tpl->assign('_',$_);
$tpl->assign('action','');
$tpl->assign('rank',$rank);
?>