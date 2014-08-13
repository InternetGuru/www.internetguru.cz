<?php

/**
 * Create DOM from XML file and update elements from adm/usr directories.
 * Add by default; empty element to delete all elements with same nodeName.
 * Respect readonly attribute when applying user file.
 * Default XML file is required (plugins do not have to use Config at all).
 * Elements with attribute subdom will be applied only when matched.
 *
 * @param: String plugin (optional)
 * @return: DOMDocument
 * @throws: Exception when files don't exist or are corrupted/empty
 */
class DOMBuilder {

  const DEBUG = false;
  private $backupStrategy = null;
  private $doc; // DOMDocument (HTMLPlus)
  private $plugin;
  private $replace; // bool
  private $filename;
  private $validate = true;

  public function __construct() {}

  public function setBackupStrategy(BackupStrategyInterface $backupStrategy) {
    $this->backupStrategy = $backupStrategy;
  }

  public function buildDOM($plugin="",$replace=false,$filename="") {
    $this->doc = new DOMDocumentPlus();
    $this->build($plugin,$replace,$filename);
    return $this->doc;
  }

  public function buildHTML($plugin="",$replace=true,$filename="",$validate=true) {
    $this->doc = new HTMLPlus();
    $this->validate = $validate;
    $this->build($plugin,$replace,$filename);
    return $this->doc;
  }

  private function build($plugin,$replace,$filename) {

    if(!is_string($plugin)) throw new Exception('Variable type: not string.');
    if($filename == "" && $plugin == "") $filename = "Cms.xml";
    elseif($filename == "") $filename = "$plugin.xml";

    if($replace) {
      $this->loadDOM(findFilePath($filename,$plugin),$this->doc);
      if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";
      return;
    }

    if($plugin != "") $filename = PLUGIN_FOLDER . "/" . $plugin . "/" . $plugin . ".xml";
    $this->loadDOM(findFilePath($filename,"",false,false),$this->doc);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";

    $f = ADMIN_FOLDER . "/$filename";
    if(is_file($f)) $this->updateDOM($f,true);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";

    $f = USER_FOLDER . "/$filename";
    if(is_file($f)) $this->updateDOM($f);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";
  }

  private function loadDOM($filename, DOMDocumentPlus $doc, $backup=true) {
    try {
      // load
      if(!@$doc->load($filename))
        throw new Exception("Unable to load DOM from file '$filename'");
      // validate if htmlplus
      try {
        if($doc instanceof HTMLPlus && $this->validate) $doc->validate();
      } catch(Exception $e) {
        $doc->validate(true);
        $doc->saveRewrite($filename);
      }
    } catch(Exception $e) {
      // restore file if backupstrategy && $backup && !atLocalhost
      if(!isAtLocalhost() && !is_null($this->backupStrategy) && $backup)
        #$this->backupStrategy->restoreNewestBackup($filename);
        $filename = $this->backupStrategy->getNewestBackupFilePath($filename);
      else throw $e;
      // loadDOM(false)
      $this->loadDOM($filename,$doc,false);
    }
    // do backup if $backup
    if(!is_null($this->backupStrategy) && $backup)
      $this->backupStrategy->doBackup($filename);
  }

  /**
   * Load XML file into DOMDocument using backup/restore
   * Respect subdom attribute
   * @param  string      $filePath File to be loaded into document
   * @return void
   * @throws Exception   if unable to load XML file incl. backup file
   */
  private function updateDOM($filePath,$ignoreReadonly=false) {
    $doc = new DOMDocumentPlus();
    $this->loadDOM($filePath,$doc);
    // create root element if not exists
    if(is_null($this->doc->documentElement)) {
      $this->doc->appendChild($this->doc->importNode($doc->documentElement));
    }
    foreach($doc->documentElement->childNodes as $n) {
      if(get_class($n) != "DOMElement") continue;
      if($this->ignoreElement($n)) continue;
      if($n->hasAttribute("id")) {
        $sameIdElement = $this->doc->getElementById($n->getAttribute("id"));
        if(is_null($sameIdElement)) {
          $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
          continue;
        }
        if($sameIdElement->nodeName != $n->nodeName)
          throw new Exception ("Id conflict with " . $n->nodeName);
        if(!$ignoreReadonly && $sameIdElement->hasAttribute("readonly")) continue;
        $this->doc->documentElement->replaceChild($this->doc->importNode($n,true),$sameIdElement);
      } elseif($n->nodeValue == "") {
        $remove = array();
        foreach($this->doc->getElementsByTagName($n->nodeName) as $d) {
          if($ignoreReadonly || !$d->hasAttribute("readonly")) $remove[] = $d;
        }
        #if(!count($remove)) {
        #  $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
        #  continue;
        #}
        foreach($remove as $d) $d->parentNode->removeChild($d);
      } else {
        // if empty && readonly => user cannot modify
        foreach($this->doc->getElementsByTagName($n->nodeName) as $d) {
          if(!$ignoreReadonly && $d->hasAttribute("readonly") && $d->nodeValue == "") return;
        }
        $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
      }
    }
  }

  #private function getElementById($id) {
  #  $xpath = new DOMXPath($this->doc);
  #  $q = $xpath->query("//*[@id='$id']");
  #  if($q->length == 0) return null;
  #  return $q->item(0);
  #}

  private function ignoreElement(DOMElement $e) {
    if(!$e->hasAttribute("subdom")) return false;
    return !in_array($this->getSubdom(),explode(" ",$e->getAttribute("subdom")));
  }

  private function getSubdom() {
    if(isAtLocalhost()) return "localhost";
    // eg => /subdom/private/server.php
    $d = explode("/",$_SERVER["SCRIPT_NAME"]);
    return $d[2];
  }

}

interface BackupStrategyInterface {
    public function doBackup($filePath);
    #public function restoreNewestBackup($filePath);
    public function getNewestBackupFilePath($filePath);
}

?>
