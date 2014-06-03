<?php

class Backup implements SplObserver, BackupStrategyInterface {

  const BACKUP_FOLDER = 'bak';
  const HASH_FILE_ALGO = 'crc32b';
  const BACKUP_FILENAME_SEPARATOR = "~";
  const BACKUP_DATE_FORMAT = "YmdH"; // eg YmdH for 2011091513

  private function getFileInfo($filePath) {
    $fileInfo = pathinfo($filePath);
    $backupDirs = explode("/",$fileInfo["dirname"]);
    if($backupDirs[0] == "..") {
      unset($backupDirs[0]);
      unset($backupDirs[1]);
    }
    array_unshift($backupDirs,self::BACKUP_FOLDER);
    $fileInfo["backupdirname"] = implode("/",$backupDirs);
    return $fileInfo;
  }

  private function getNewestBackupFileName($dirName,$fileName) {
    foreach(scandir($dirName,SCANDIR_SORT_DESCENDING) as $backupFileName) {
      if(!is_file($dirName ."/". $backupFileName)) continue;
      if(strpos($backupFileName,$fileName.self::BACKUP_FILENAME_SEPARATOR) === 0) {
        return $backupFileName;
      }
    }
    throw new Exception("Unable to find backup of $filePath");
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "preinit") {
      $subject->getCms()->setBackupStrategy($this);
    }
  }

  public function doBackup($filePath) {
    $fileInfo = $this->getFileInfo($filePath);
    $fileHash = hash_file(self::HASH_FILE_ALGO,$filePath);
    try {
      $newestBackupFile = $this->getNewestBackupFileName($fileInfo["backupdirname"],$fileInfo["filename"]);
      // check hash
      if(strpos($newestBackupFile,$fileHash) !== false) return;
      // check timestamp
      if(strpos($newestBackupFile,date(self::BACKUP_DATE_FORMAT)) !== false) return;
    } catch(Exception $e) {}

    $s = self::BACKUP_FILENAME_SEPARATOR;
    $backupFileName = sprintf("%s$s%s$s%s.%s",$fileInfo["filename"],
      date(self::BACKUP_DATE_FORMAT),$fileHash,$fileInfo["extension"]);
    $backupFilePath = $fileInfo["backupdirname"] ."/". $backupFileName;

    if(is_file($backupFilePath)) return;
    if(!is_dir($fileInfo["backupdirname"])) mkdir($fileInfo["backupdirname"],0755,true);
    if(!copy($filePath,$backupFilePath)) {
      throw new Exception("Unable to create backup $backupFilePath");
    }
    return;
  }

  public function restoreNewestBackup($filePath) {
    $fileInfo = $this->getFileInfo($filePath);
    $backupFileName = $this->getNewestBackupFileName($fileInfo["backupdirname"],$fileInfo["filename"]);
    if(!rename($filePath,$filePath .".CORRUPTED"))
      throw new Exception("Unable to rename corrupted file $filePath");
    if(!rename($fileInfo["backupdirname"] ."/". $backupFileName,$filePath))
      throw new Exception("Unable to restore newest backup $filePath");
    $this->doBackup($filePath);
  }

}
?>
