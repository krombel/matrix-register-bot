<?php
$lang = "de-de";
if(isset($_GET['lang'])){
	$lang = filter_var($_GET['lang'], FILTER_SANITIZE_STRING);
}
$lang_file = dirname(__FILE__) . "/lang/lang.".$lang.".php";
if (!file_exists($lang_file)) {
	error_log("Translation for " . $lang . " not found. Fallback to 'de-de'");
	$lang = "de-de";
}
require_once($lang_file);
unset($lang_file);
?>
