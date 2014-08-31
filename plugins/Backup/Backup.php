<?php

class Backup extends Plugin implements SplObserver {

  const HASH_FILE_ALGO = 'crc32b';

  /**
   * Observer update method
   * @param  SplSubject $subject Subject with observer collection
   * @return void
   */
  public function update(SplSubject $subject) {
    if($subject->getStatus() != "process") return;
    $this->subject = $subject;
    $this->backupFilesDeep(ADMIN_FOLDER,ADMIN_BACKUP);
    $this->backupFilesDeep(USER_FOLDER,USER_BACKUP);
  }

  private function backupFilesDeep($dir,$backupDir) {
    if(!is_dir($dir)) return;
    $cfg = $this->getDOMPlus();
    $xpath = new DOMXPath($cfg);
    $deny = array();
    foreach($xpath->query("deny/ext") as $e) $deny[] = $e->nodeValue;
    foreach(scandir($dir) as $file) {
      if(strpos($file, ".") === 0) continue;
      if(is_dir("$dir/$file")) {
        $this->backupFilesDeep("$dir/$file","$backupDir/$file");
        continue;
      }
      if(in_array(pathinfo($file,PATHINFO_EXTENSION), $deny)) continue;
      $this->doBackup("$dir/$file",$backupDir ."/". $this->getBackupFileName("$dir/$file"));
    }
  }

  private function getBackupFileName($filePath) {
    $pi = pathinfo($filePath);
    return sprintf("%s.%s",$pi["basename"],$this->getFileHash($filePath));
  }

  private function doBackup($src,$dest) {
    if(file_exists($dest)) return;
    $destDir = pathinfo($dest,PATHINFO_DIRNAME);
    if(is_dir($destDir)) foreach(scandir($destDir) as $f) {
      if(pathinfo($f,PATHINFO_FILENAME) != pathinfo($src,PATHINFO_BASENAME)) continue;
      if(filectime("$destDir/$f") > time() - 60*60) return;
    }
    if(!is_dir($destDir) && !mkdir($destDir,0755,true))
      throw new Exception("Unable to create backup directory '$destDir'");
    if(!copy($src,$dest)) {
      throw new Exception("Unable to create backup '$dest'");
    }
  }

  private function getFileHash($filePath) {
    return hash_file(self::HASH_FILE_ALGO,$filePath);
  }

  #UNUSED (restore)
  public function restoreNewestBackup($filePath) {
    $fileInfo = $this->getFileInfo($filePath);
    $backupFileName = $this->getNewestBackupFileName($fileInfo["backupdirname"],$fileInfo["filename"]);
    if(is_file($filePath) && !rename($filePath,$filePath . self::CORRUPTED_FILE_EXTENSION))
      throw new Exception("Unable to rename corrupted file $filePath");
    if(!rename($fileInfo["backupdirname"] ."/". $backupFileName,$filePath))
      throw new Exception("Unable to restore newest backup $filePath");
    $this->doBackup($filePath);
  }

  #UNUSED (restore)
  private function getNewestBackupFilePath($filePath) {
    $fileInfo = $this->getFileInfo($filePath);
    return $fileInfo["backupdirname"] . "/" . $this->getNewestBackupFileName($filePath);
  }

  #UNUSED (restore)
  private function getNewestBackupFileName($filePath) {
    $fileInfo = $this->getFileInfo($filePath);
    if(is_dir($fileInfo["backupdirname"])) {
      foreach(scandir($fileInfo["backupdirname"],SCANDIR_SORT_DESCENDING) as $backupFileName) {
        if(!is_file($fileInfo["backupdirname"] ."/". $backupFileName)) continue;
        if(strpos($backupFileName,$fileInfo["filename"].self::BACKUP_FILENAME_SEPARATOR) === 0) {
          if($fileInfo["extension"] != pathinfo($backupFileName,PATHINFO_EXTENSION)) continue;
          return $backupFileName;
        }
      }
    }
    throw new Exception("Unable to find backup of '$filePath'");
  }

}
?>
