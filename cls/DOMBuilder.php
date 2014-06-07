<?php

/**
 * Create DOM from XML files in folowing order: default/admin/user.
 * Respect readonly attribute when applying user file.
 * Default XML file is required (plugins do not have to use Config at all).
 *
 * @param: String plugin (optional)
 * @useage: $config = $this->domBuilder->build();
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

  /**
   * Load XML file into DOMDocument using backup/restore
   * @param  DOMDocument $doc      Load into document
   * @param  string      $filePath File to be loaded into document
   * @return void
   * @throws Exception   if unable to load XML file incl. backup file
   */
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

  /**
   * Update existing values in given DOM with values from xml file.
   * @param  DOMDOcument $doc            DOM to be updated
   * @param  string      $filePath       Path to XML file
   * @param  boolean     $ignoreReadonly Update values of elements with attribute readonly
   * @return void
   */
  private function updateDom(DOMDOcument $doc,$filePath,$ignoreReadonly=true) {
    if(!is_string($filePath)) throw new Exception('Variable type: not string.');
    if(!is_file($filePath)) return; // adm/usr files are optional
    if(!filesize($filePath)) return; // file cannot be empty
    $tempDoc = new DOMDocument();
    $this->loadToDoc($tempDoc,$filePath);
    $nodes = $tempDoc->firstChild->childNodes;
    $xPath = new DOMXPath($doc);
    for($i = 0; $i < $nodes->length; $i++) {
      if($nodes->item($i)->nodeType != 1) continue;
      $docNodes = $xPath->query($nodes->item($i)->getNodePath());
      if($docNodes->length != 1) continue; // only elements pass
      if(!$ignoreReadonly // only without attribute readonly
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
