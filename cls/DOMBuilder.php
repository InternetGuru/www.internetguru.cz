<?php

/**
 * Create DOM from XML files in folowing order: default/admin/user.
 * Respect readonly attribute when applying user file.
 * Default XML file is required (plugins do not have to use Config at all).
 *
 * @PARAM: String plugin (optional)
 * @USAGE: $config = $this->domBuilder->build();
 * @RETURNS: DOMDocument
 * @THROWS: Exception when files don't exist or are corrupted/empty
 */
class DOMBuilder {

  const DEBUG = false;
  private $backupStrategy = null;

  public function __construct() {}

  public function setBackupStrategy(BackupStrategyInterface $backupStrategy) {
    $this->backupStrategy = $backupStrategy;
  }

  public function build($path="Cms",$ext="xml") {
    if(!is_string($path)) throw new Exception('Variable type: not string.');

    $doc = new DOMDocument("1.0","utf-8");
    if(self::DEBUG) $doc->formatOutput = true;

    // create DOM from default config xml (Cms root or Plugin dir)
    if($path == "Cms") $filePath = "$path.$ext";
    else $filePath = PLUGIN_FOLDER . "/$path/$path.$ext";
    if(!is_file($filePath)) $filePath = "../" . CMS_FOLDER . "/$filePath";

    $this->loadToDoc($doc,$filePath);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    // update DOM by admin data (all of them)
    $this->updateDom($doc,ADMIN_FOLDER."/$path.$ext");
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    // update DOM by user data (except readonly)
    $this->updateDom($doc,USER_FOLDER."/$path.$ext",false);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    return $doc;

  }

  private function loadToDoc(DOMDocument $doc,$filePath) {
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
  }

  private function updateDom($doc,$filePath,$ignoreReadonly=true) {
    if(!is_string($filePath)) throw new Exception('Variable type: not string.');

    if(!is_file($filePath)) return; // file is optional
    if(!filesize($filePath)) return; // file can be empty
    $doc = new DOMDocument();
    $this->loadToDoc($doc,$filePath);
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

interface BackupStrategyInterface {
    public function doBackup($filePath);
    public function restoreNewestBackup($filePath);
}

?>
