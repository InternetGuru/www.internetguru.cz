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

  public function __construct() {}

  public function setBackupStrategy(BackupStrategyInterface $backupStrategy) {
    $this->backupStrategy = $backupStrategy;
  }

  /**
   * Create DOM from XML file and update its values from appropriate adm/usr files
   * @param  string  $path    Path to XML file (plugin name)
   * @param  boolean $replace Replace DOM instead of updating
   * @param  string  $ext     XML file extension
   * @return DOMDocument  Updated DOM
   */
  public function build($path="Cms",$replace=false,$ext="xml") {
    if(!is_string($path)) throw new Exception('Variable type: not string.');

    // create DOM from default config xml (Cms root or Plugin dir)
    if($path == "Cms") $filePath = "$path.$ext";
    else $filePath = PLUGIN_FOLDER . "/$path/$path.$ext";
    if(!is_file($filePath)) $filePath = "../" . CMS_FOLDER . "/$filePath";

    $files = array($filePath => true,ADMIN_FOLDER."/$path.$ext" => true,USER_FOLDER."/$path.$ext" => false);
    if($replace) return $this->getFirstDOM(array_reverse($files));
    return $this->updateAll($files);
  }

  private function updateAll(Array $files) {
    if(!is_file(key($files)))
      throw new Exception("File not found");
    $doc = new DOMDocument("1.0","utf-8");
    $doc->formatOutput = true;
    foreach($files as $f => $ignoreReadonly) {
      if(!is_file($f)) continue;
      $this->updateDOM($doc,$f,$ignoreReadonly);
      if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";
    }
    return $doc;
  }

  private function getFirstDOM(Array $files) {
    $doc = new DOMDocument("1.0","utf-8");
    $doc->formatOutput = true;
    foreach($files as $f => $ignoreReadonly) {
      if(is_file($f)) {
        if(!@$doc->load($f))
          throw new Exception(sprintf('Unable to load DOM from file %s',$f));
        return $doc;
      }
    }
    throw new Exception("Cannot load DOM '$f'");
  }

  /**
   * Load XML file into DOMDocument using backup/restore
   * Respect subdom attribute
   * @param  DOMDocument $doc      Load into document
   * @param  string      $filePath File to be loaded into document
   * @return void
   * @throws Exception   if unable to load XML file incl. backup file
   */
  private function updateDOM(DOMDocument $outDoc,$filePath,$ignoreReadonly=false) {
    $doc = new DOMDocument("1.0","utf-8");
    $doc->formatOutput = true;
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
    if(is_null($outDoc->documentElement)) {
      $outDoc->appendChild($outDoc->importNode($doc->documentElement));
    }
    foreach($doc->documentElement->childNodes as $n) {
      if(get_class($n) != "DOMElement") continue;
      if($this->ignoreElement($n)) continue;
      if($n->nodeValue == "") {
        $remove = array();
        foreach($outDoc->getElementsByTagName($n->nodeName) as $d) {
          if($ignoreReadonly || !$d->hasAttribute("readonly")) $remove[] = $d;
        }
        foreach($remove as $d) $d->parentNode->removeChild($d);
        #$outDoc->documentElement->removeChild($d);
      } else {
        $outDoc->documentElement->appendChild($outDoc->importNode($n,true));
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
