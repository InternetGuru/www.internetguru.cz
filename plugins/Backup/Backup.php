<?php

class Backup extends Plugin implements SplObserver {

  /**
   * Observer update method
   * @param  SplSubject $subject Subject with observer collection
   * @return void
   */
  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    $this->backupFilesDeep(ADMIN_FOLDER, ADMIN_BACKUP_FOLDER);
    $this->backupFilesDeep(USER_FOLDER, USER_BACKUP_FOLDER);
  }

  private function backupFilesDeep($dir, $backupDir) {
    if(!is_dir($dir)) return;
    $cfg = $this->getDOMPlus();
    $xpath = new DOMXPath($cfg);
    $deny = array();
    foreach($xpath->query("deny/ext") as $e) $deny[] = $e->nodeValue;
    foreach(scandir($dir) as $file) {
      if(strpos($file, ".") === 0) continue;
      if(is_dir("$dir/$file")) {
        $this->backupFilesDeep("$dir/$file", "$backupDir/$file");
        continue;
      }
      if(in_array(pathinfo($file, PATHINFO_EXTENSION), $deny)) continue;
      $src = "$dir/$file";
      $dest = $backupDir."/".$this->getBackupFileName("$dir/$file");
      // both are files within given age gap
      if(is_file($dest) && !is_link($dest) && !is_link($src)
        && filemtime($dest) > time()-60*60) continue;
      smartCopy($src, $dest);
    }
  }

  private function getBackupFileName($filePath) {
    $pi = pathinfo($filePath);
    return sprintf("%s.%s", $pi["basename"], getFileHash($filePath));
  }

}
?>
