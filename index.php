<?php

# TODO:e-mail errors (plugin)

// --------------------------------------------------------------------
// IGCMS CORE
// --------------------------------------------------------------------

include('cls/globals.php');

try {

  $l = new Logger("CMS init " . dirname(__FILE__),null,false);

  // register core variables
  $cms = new Cms();
  $plugins = new Plugins($cms);

  $cms->setPlugins($plugins);
  $plugins->setStatus("preinit");
  $plugins->notify();

  $cms->init();
  $plugins->setStatus("init");
  $plugins->notify();

  $cms->buildContent();
  $plugins->setStatus("process");
  $plugins->notify();

  $cms->processVariables();
  $plugins->setStatus("postprocess");
  $plugins->notify();

  echo $cms->getOutput();
  backupDir(USER_FOLDER);
  backupDir(ADMIN_FOLDER);
  $l->finished();

} catch(Exception $e) {

  if(!$e instanceof LoggerException) try {
    new Logger($e->getMessage(),"fatal");
  } catch (Exception $e) {};

  http_response_code(500);
  #TODO: http://xkcd.com/1350/#p:10e7f9b6-b9b8-11e3-8003-002590d77bdd
  $dir = CMS_FOLDER."/lib/500";
  $h = array(
    "Něco je špatně",
    "Tak s tímhle jsme nepočítali",
    );
  $m = array(
    "Gratulujeme, objevili jste skrytou <del>chybu</del> komnatu.",
    "Náš ústav se vám jménem W3G co nejsrdečněji omlouvá za tuto politováníhodnou skutečnost, ke které dochází maximálně #ERROR#krát za 10 let."
    );
  $i = array();
  foreach(scandir($dir) as $img) {
    if(pathinfo("$dir/$img",PATHINFO_EXTENSION) == "png") $i[] = "$dir/$img";
  }
  $html = file_get_contents(CMS_FOLDER."/$dir/500.cs.html");
  $search = array('@HEADING@','@MESSAGE@','@ERROR@','@IMAGE@');
  $ex = $e->getMessage();
  if(isAtLocalhost()) $ex = "Exception: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine();
  $replace = array($h[array_rand($h)],$m[array_rand($m)],$ex,$i[array_rand($i)]);
  echo str_replace($search,$replace,$html);

}

?>