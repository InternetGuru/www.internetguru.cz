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
   * @param  string $path Path to XML file (plugin name)
   * @param  string $ext  XML file extension
   * @return DOMDocument  Updated DOM
   */
  public function build($path="Cms",$ext="xml") {
    if(!is_string($path)) throw new Exception('Variable type: not string.');

    // create DOM from default config xml (Cms root or Plugin dir)
    if($path == "Cms") $filePath = "$path.$ext";
    else $filePath = PLUGIN_FOLDER . "/$path/$path.$ext";
    if(!is_file($filePath)) $filePath = "../" . CMS_FOLDER . "/$filePath";

    $doc = new DOMDocument("1.0","utf-8");
    $this->updateDom($doc,$filePath);
    if(self::DEBUG) $doc->formatOutput = true;
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    // update DOM by admin data (all of them)
    if(is_file(ADMIN_FOLDER."/$path.$ext")) $this->updateDom($doc,ADMIN_FOLDER."/$path.$ext");
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    // update DOM by user data (except readonly)
    if(is_file(USER_FOLDER."/$path.$ext")) $this->updateDom($doc,USER_FOLDER."/$path.$ext",false);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    return $doc;
  }

  /**
   * Load XML file into DOMDocument using backup/restore
   * Respect subdom attribute
   * @param  DOMDocument $doc      Load into document
   * @param  string      $filePath File to be loaded into document
   * @return void
   * @throws Exception   if unable to load XML file incl. backup file
   */
  private function updateDom(DOMDocument $outDoc,$filePath,$ignoreReadonly=true) {
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
