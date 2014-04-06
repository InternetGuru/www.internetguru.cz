<?php
/**
 * Create DOM from XML config files in folowing order
 *
 * @VARIABLE: String plugin (optional)
 * @USAGE: $myCfg = new Config("slider");
 */
class Config {

  const DEBUG = true;
  private $cfg;

  function __construct($plugin="default") {
    if(!is_string($plugin)) throw new Exception('Variable type: not string.');

    $this->cfg = new DOMDocument();
    $this->cfg->formatOutput = true;

    // create DOM from default config xml (cms or plugin)
    if($plugin == "default") $this->cfg->load("default.xml");
    else $this->cfg->load("plugins/$plugin/$plugin.xml");
    if($this::DEBUG) echo "<pre>".htmlspecialchars($this->cfg->saveXML())."</pre>";

    // modify DOM by admin data
    $this->updateDom(ADMIN_FOLDER."/$plugin.xml");

    if($this::DEBUG) echo "<pre>".htmlspecialchars($this->cfg->saveXML())."</pre>";

    // modify DOM by user data (except readonly)
    $this->updateDom(USER_FOLDER."/$plugin.xml",false);
    if($this::DEBUG) echo "<pre>".htmlspecialchars($this->cfg->saveXML())."</pre>";

  }

  private function updateDom($xmlFile,$ignoreReadonly=true) {
    if(!is_string($xmlFile)) throw new Exception('Variable type: not string.');

    $doc = new DOMDocument();
    $doc->load($xmlFile);
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