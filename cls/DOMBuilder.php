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
  private $path;
  private $replace; // bool
  private $filename;

  public function __construct() {}

  public function setBackupStrategy(BackupStrategyInterface $backupStrategy) {
    $this->backupStrategy = $backupStrategy;
  }

  public function buildDOM($path="Cms",$replace=false,$filename="") {
    $this->doc = new DOMDocument("1.0","utf-8");
    $this->path = $path;
    $this->replace = $replace;
    $this->filename = ($filename == "" ? "$path.xml" : $filename);
    $this->build();
    return $this->doc;
  }

  public function buildHTML($path="Cms",$replace=false,$filename="") {
    $this->doc = new HTMLPlus();
    $this->path = $path;
    $this->replace = $replace;
    $this->filename = ($filename == "" ? "$path.xml" : $filename);
    $this->build();
    return $this->doc;
  }

  private function build() {
    if(!is_string($this->path)) throw new Exception('Variable type: not string.');

    // create DOM from default config xml (Cms root or Plugin dir)
    if($this->path == "Cms") $filePath = $this->filename;
    else $filePath = PLUGIN_FOLDER ."/". $this->path ."/". $this->filename;
    if(!is_file($filePath)) $filePath = "../" . CMS_FOLDER . "/$filePath";

    $files = array($filePath => true,ADMIN_FOLDER."/".$this->filename => true,USER_FOLDER."/".$this->filename => false);
    if($this->replace) $this->loadFirstDOM(array_reverse($files));
    else $this->updateAll($files);
  }

  private function updateAll(Array $files) {
    if(!is_file(key($files)))
      throw new Exception(sprintf("File %s not found",key($file)));
    foreach($files as $f => $ignoreReadonly) {
      if(!is_file($f)) continue;
      $this->updateDOM($f,$ignoreReadonly);
      if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";
    }
  }

  private function loadFirstDOM(Array $files) {
    foreach($files as $f => $ignoreReadonly) {
      if(is_file($f)) {
        if(!@$this->doc->load($f))
          throw new Exception(sprintf('Unable to load DOM from file %s',$f));
        return;
      }
    }
    throw new Exception("Cannot load DOM '$f'");
  }

  /**
   * Load XML file into DOMDocument using backup/restore
   * Respect subdom attribute
   * @param  string      $filePath File to be loaded into document
   * @return void
   * @throws Exception   if unable to load XML file incl. backup file
   */
  private function updateDOM($filePath,$ignoreReadonly=false) {
    $doc = new DOMDocument("1.0","utf-8");
    if(@$doc->load($filePath)) {
      if($this->backupStrategy !== null) {
        $this->backupStrategy->doBackup($filePath);
      }
    } elseif($this->backupStrategy !== null) {
      $this->backupStrategy->restoreNewestBackup($filePath);
      if(!@$doc->load($filePath)) {
        throw new Exception(sprintf('Unable to load restored XML file %s',$filePath));
      }
    } else {
      throw new Exception(sprintf('Unable to load XML file %s',$filePath));
    }
    // create root element if not exists
    if(is_null($this->doc->documentElement)) {
      $this->doc->appendChild($this->doc->importNode($doc->documentElement));
    }
    foreach($doc->documentElement->childNodes as $n) {
      if(get_class($n) != "DOMElement") continue;
      if($this->ignoreElement($n)) continue;
      if($n->nodeValue == "") {
        $remove = array();
        foreach($this->doc->getElementsByTagName($n->nodeName) as $d) {
          if($ignoreReadonly || !$d->hasAttribute("readonly")) $remove[] = $d;
        }
        foreach($remove as $d) $d->parentNode->removeChild($d);
      } else {
        $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
      }
    }
  }

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
    public function restoreNewestBackup($filePath);
}

?>
