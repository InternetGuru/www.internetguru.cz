<?php

class Backup implements SplObserver, BackupStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "preinit") {
      $subject->getCms()->getDOMBuilder()->setBackupStrategy($this);
    }
  }

  public function backupFile($file) {
    return false;
  }

  public function restoreFile($file) {
    return false;
  }

}
?>
