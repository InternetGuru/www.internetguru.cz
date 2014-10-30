<?php

$dir = "/lib/error";
$h = array(
  "Něco je špatně",
  "Tak s tímhle jsme nepočítali",
  "Tohle se nemělo stát",
  );
$m = array(
  "Gratulujeme, objevili jste skrytou <del>chybu</del> komnatu.",
  "Náš ústav se vám jménem W3G co nejsrdečněji omlouvá za tuto politováníhodnou skutečnost, ke které dochází maximálně #ERROR#krát za 10 let."
  );
$i = array();
// http://xkcd.com/1350/#p:10e7f9b6-b9b8-11e3-8003-002590d77bdd
foreach(scandir(CMS_FOLDER ."/$dir") as $img) {
  if(pathinfo(CMS_FOLDER ."/$dir/$img",PATHINFO_EXTENSION) != "png") continue;
  $i[] = findFile("$dir/$img",false,false,true);
}
$html = file_get_contents(CMS_FOLDER ."$dir/error.cs.html");
$search = array('@HEADING@','@MESSAGE@','@ERROR@','@IMAGE@','@ERRNR@','@ROOT@');
$replace = array($h[array_rand($h)], $m[array_rand($m)], $message, $i[array_rand($i)], http_response_code(), getRoot());
echo str_replace($search,$replace,$html);

?>