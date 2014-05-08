<?php

/**
 * Create DOM from XML files in folowing order: default/admin/user.
 * Respect readonly attribute when applying user file.
 * Default XML file is required (plugins do not have to use Config at all).
 *
 * @PARAM: String plugin (optional)
 * @USAGE: $document = DomBuilder::build([plugin]);
 * @THROWS: Exception when files don't exist or are corrupted/empty
 */
class DOMBuilder {

  const DEBUG = false;

  private function __construct() {}

  public static function build($path="Cms",$ext="xml") {
    if(!is_string($path)) throw new Exception('Variable type: not string.');

    $doc = new DOMDocument();
    if(self::DEBUG) $doc->formatOutput = true;

    // create DOM from default config xml (Cms root or Plugin dir)
    if($path == "Cms") {
      if(!@$doc->load("$path.$ext"))
        throw new Exception(sprintf('Unable to load XML file %s.',"$path.$ext"));
    } else {
      $fileName = PLUGIN_FOLDER . "/$path/$path.$ext";
      if(!@$doc->load($fileName))
        throw new Exception(sprintf('Unable to load XML file %s.',$fileName));
    }
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    // update DOM by admin data (all of them)
    self::updateDom($doc,ADMIN_FOLDER."/$path.$ext");
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    // update DOM by user data (except readonly)
    self::updateDom($doc,USER_FOLDER."/$path.$ext",false);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    return $doc;

  }

  private static function updateDom(&$doc,$pathFile,$ignoreReadonly=true) {
    if(!is_string($pathFile)) throw new Exception('Variable type: not string.');

    if(!is_file($pathFile)) return; // file is optional
    if(!filesize($pathFile)) return; // file can be empty
    $doc = new DOMDocument();
    if(!@$doc->load($pathFile))
      throw new Exception('Unable to load XML file.');
    $nodes = $doc->firstChild->childNodes;
    $xPath = new DOMXPath($doc);

    for($i = 0; $i < $nodes->length; $i++) {
      if($nodes->item($i)->nodeType != 1) continue;
      $docNodes = $xPath->query($nodes->item($i)->getNodePath());
      // only elements pass
      if($docNodes->length != 1) continue;
      // only without attribute readonly
      if(!$ignoreReadonly
        && $docNodes->item(0)->getAttribute("readonly") == "readonly") continue;
      $doc->firstChild->replaceChild(
        $doc->importNode($nodes->item($i),true),$docNodes->item(0)
      );
    }
  }

}
?>
