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
class DomBuilder {

  const DEBUG = false;

  private function __construct() {}

  public static function build($plugin="default") {
    if(!is_string($plugin)) throw new Exception('Variable type: not string.');

    $cfg = new DOMDocument();
    if(self::DEBUG) $cfg->formatOutput = true;

    // create DOM from default config xml (cms or plugin)
    if($plugin == "default") {
      if(!@$cfg->load("default.xml"))
        throw new Exception('Unable to load XML file.');
      $plugin = DEFAULT_CFG_FILE;
    } else {
      if(!@$cfg->load("plugins/$plugin/default.xml"))
        throw new Exception('Unable to load XML file.');
    }
    if(self::DEBUG) echo "<pre>".htmlspecialchars($cfg->saveXML())."</pre>";

    // update DOM by admin data (all of them)
    self::updateDom($cfg,ADMIN_FOLDER."/$plugin.xml");
    if(self::DEBUG) echo "<pre>".htmlspecialchars($cfg->saveXML())."</pre>";

    // update DOM by user data (except readonly)
    self::updateDom($cfg,USER_FOLDER."/$plugin.xml",false);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($cfg->saveXML())."</pre>";

    return $cfg;

  }

  private static function updateDom(&$cfg,$xmlFile,$ignoreReadonly=true) {
    if(!is_string($xmlFile)) throw new Exception('Variable type: not string.');

    if(!is_file($xmlFile)) return; // file is optional
    if(!filesize($xmlFile)) return; // file can be empty
    $doc = new DOMDocument();
    if(!@$doc->load($xmlFile))
      throw new Exception('Unable to load XML file.');
    $nodes = $doc->firstChild->childNodes;
    $xPath = new DOMXPath($cfg);

    for($i = 0; $i < $nodes->length; $i++) {
      if($nodes->item($i)->nodeType != 1) continue;
      $cfgNodes = $xPath->query($nodes->item($i)->getNodePath());
      // only elements pass
      if($cfgNodes->length != 1) continue;
      // only without attribute readonly
      if(!$ignoreReadonly
        && $cfgNodes->item(0)->getAttribute("readonly") == "readonly") continue;
      $cfg->firstChild->replaceChild(
        $cfg->importNode($nodes->item($i),true),$cfgNodes->item(0)
      );
    }
  }

}
?>
