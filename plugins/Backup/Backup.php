<?php

class Backup implements SplObserver, BackupStrategyInterface {

  const HASH_FILE_ALGO = 'crc32b';
  const BACKUP_FILENAME_SEPARATOR = "~";
  const BACKUP_DATE_FORMAT = "YmdH"; // eg YmdH for 2011091513
  #const CORRUPTED_FILE_EXTENSION = ".corrupted";

  /**
   * Get information about a file from file path including backup directory
   * @param  string $filePath Path to a file
   * @return Array (
   *     [dirname] => ../cms
   *     [basename] => Cms.xml
   *     [extension] => xml
   *     [filename] => Cms
   *     [backupdirname] => bak
   * )
   */
  private function getFileInfo($filePath) {
    $fileInfo = pathinfo($filePath);
    $backupDirs = explode("/",$fileInfo["dirname"]);
    if($backupDirs[0] == "..") {
      unset($backupDirs[0]);
      unset($backupDirs[1]);
    }
    array_unshift($backupDirs,BACKUP_FOLDER);
    $fileInfo["backupdirname"] = implode("/",$backupDirs);
    return $fileInfo;
  }

  public function getNewestBackupFilePath($filePath) {
    $fileInfo = $this->getFileInfo($filePath);
    return $fileInfo["backupdirname"] . "/" . $this->getNewestBackupFileName($filePath);
  }

  /**
   * Get newest backup file
   * @param  string $filePath Origin file path
   * @return string           Newest backup file name
   * @throws Exception If no backup file found
   */
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

  /**
   * Observer update method
   * @param  SplSubject $subject Subject with observer collection
   * @return void
   */
  public function update(SplSubject $subject) {
    if($subject->getStatus() == "preinit") {
      $subject->getCms()->setBackupStrategy($this);
    }
  }

  /**
   * Do backup of given file if not too young or the same
   * @param  string $filePath Path to original file
   * @return void
   * @throws Exception        If unable to create backup file
   */
  public function doBackup($filePath) {
    $fileInfo = $this->getFileInfo($filePath);
    $fileHash = hash_file(self::HASH_FILE_ALGO,$filePath);
    try {
      $newestBackupFile = $this->getNewestBackupFileName($filePath);
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
  }

  /**
   * Restore newest backup of a given corrupted or missing file
   * @param  string $filePath Filename to be restored
   * @return void
   * @throws Exception If unable to rename corrupted file
   * @throws Exception If unable to find backup file
   * @throws Exception If unable to restore newest backup
   */
  public function restoreNewestBackup($filePath) {
    $fileInfo = $this->getFileInfo($filePath);
    $backupFileName = $this->getNewestBackupFileName($fileInfo["backupdirname"],$fileInfo["filename"]);
    if(is_file($filePath) && !rename($filePath,$filePath . self::CORRUPTED_FILE_EXTENSION))
      throw new Exception("Unable to rename corrupted file $filePath");
    if(!rename($fileInfo["backupdirname"] ."/". $backupFileName,$filePath))
      throw new Exception("Unable to restore newest backup $filePath");
    $this->doBackup($filePath);
  }

}
?>
