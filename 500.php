<?php

$dir = CMS_FOLDER."/lib/500";
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
foreach(scandir($dir) as $img) {
  if(pathinfo("$dir/$img",PATHINFO_EXTENSION) == "png") $i[] = "$dir/$img";
}
$html = file_get_contents(CMS_FOLDER."/$dir/500.cs.html");
$search = array('@HEADING@','@MESSAGE@','@ERROR@','@IMAGE@');
$ex = $e->getMessage();
if(isAtLocalhost()) $ex = "Exception: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine();
$replace = array($h[array_rand($h)],$m[array_rand($m)],$ex,$i[array_rand($i)]);
echo str_replace($search,$replace,$html);

?>