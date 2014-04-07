<?php

/**
 * Create DOM from XML config files in folowing order: default/admin/user.
 * Respect readonly attribute when applying user file.
 * Default XML file is required (plugins do not have to use Config at all).
 *
 * @PARAM: String plugin (optional)
 * @USAGE: DOMDocument $myCfg = new Config("slider");
 * @THROWS: Exception when files don't exist or are corrupted/empty
 */
class Config {

  const DEBUG = true;
  private $cfg;

  function __construct($plugin="default") {
    if(!is_string($plugin)) throw new Exception('Variable type: not string.');

    $this->cfg = new DOMDocument();
    if($this::DEBUG) $this->cfg->formatOutput = true;

    // create DOM from default config xml (cms or plugin)
    if($plugin == "default") {
      if(!@$this->cfg->load("default.xml"))
        throw new Exception('Unable to load XML file.');
    } else {
      if(!@$this->cfg->load("plugins/$plugin/default.xml"))
        throw new Exception('Unable to load XML file.');
    }
    if($this::DEBUG) echo "<pre>".htmlspecialchars($this->cfg->saveXML())."</pre>";

    // update DOM by admin data (all of them)
    $this->updateDom(ADMIN_FOLDER."/$plugin.xml");
    if($this::DEBUG) echo "<pre>".htmlspecialchars($this->cfg->saveXML())."</pre>";

    // update DOM by user data (except readonly)
    $this->updateDom(USER_FOLDER."/$plugin.xml",false);
    if($this::DEBUG) echo "<pre>".htmlspecialchars($this->cfg->saveXML())."</pre>";

  }

  private function updateDom($xmlFile,$ignoreReadonly=true) {
    if(!is_string($xmlFile)) throw new Exception('Variable type: not string.');

    if(!is_file($xmlFile)) return; // file is optional
    if(!filesize($xmlFile)) return; // file can be empty
    $doc = new DOMDocument();
    if(!@$doc->load($xmlFile))
      throw new Exception('Unable to load XML file.');
    $nodes = $doc->firstChild->childNodes;
    $xPath = new DOMXPath($this->cfg);

    for($i = 0; $i < $nodes->length; $i++) {
      if($nodes->item($i)->nodeType != 1) continue;
      $cfgNodes = $xPath->query($nodes->item($i)->getNodePath());
      // only elements pass
      if($cfgNodes->length != 1) continue;
      // only without attribute readonly
      if(!$ignoreReadonly
        && $cfgNodes->item(0)->getAttribute("readonly") == "readonly") continue;
      $this->cfg->firstChild->replaceChild(
        $this->cfg->importNode($nodes->item($i),true),$cfgNodes->item(0)
      );
    }
  }

}
?>
