<?php
try {

  $start_time = microtime(true);
  require_once(dirname(__FILE__) .'/global_func.php');
  if(!isAtLocalhost()) {
    $init_server_path = CMS_FOLDER ."/../init_server.php";
    if(!is_file($init_server_path)) throw new Exception("Missing init_server file");
    require_once($init_server_path);
    $subdom = basename(dirname($_SERVER["PHP_SELF"]));
    init_server($subdom);
    if(isset($_GET["updateSubdom"])) {
      if(strlen($_GET["updateSubdom"])) $subdom = $_GET["updateSubdom"];
      update_subdom($subdom);
      redirTo("http://$subdom.". getDomain());
    }
  }
  require_once(dirname(__FILE__) .'/global_const.php');

  $l = new Logger("CMS init " . dirname(__FILE__), null, microtime(true) - $start_time);
  $l->finished();
  $l = new Logger("CMS finished " . dirname(__FILE__), null, 0);

  $plugins = new Plugins();
  $plugins->setStatus("preinit");
  $plugins->notify();

  checkUrl();
  $cms = new Cms();
  $cms->init(); // because of dombulder to set variable into cms
  $plugins->setStatus("init");
  $plugins->notify();

  $cms->buildContent();
  $plugins->setStatus("process");
  $plugins->notify();

  $cms->processVariables();
  $plugins->setStatus("postprocess");
  $plugins->notify();

  echo $cms->getOutput();
  duplicateDir(USER_FOLDER);
  duplicateDir(ADMIN_FOLDER);
  $l->finished();

} catch(Exception $e) {

  $m = $e->getMessage();
  if(isAtLocalhost()) $m = "Exception: ".$m." in ".$e->getFile()." on line ".$e->getLine();
  if(isset($l)) $l->finished();
  if(class_exists("ErrorPage")) new ErrorPage($m, 500, true);

  http_response_code(500);
  echo $m;

}

?>