<?php

class FileHandler extends Plugin implements SplObserver {

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,1);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    $this->handleRequest();
  }

  private function handleRequest() {
    $fInfo = checkUrl(FILES_FOLDER);
    $filepath = $fInfo["filepath"];
    if(is_null($filepath)) return;
    $filesize = filesize($filepath);
    $shortPath = substr($filepath,strlen(FILES_FOLDER)+1);
    $l = new Logger("File download '$shortPath' ".fileSizeConvert($filesize), null, 0);
    header("Content-Type: " . $fInfo["filemime"]);
    header("Content-Length: $filesize");
    set_time_limit(0);
    $handle = @fopen($filepath,"rb");
    if($handle === false) throw new Exception(sprintf(_("Unable to read file '%s'"), $shortPath));
    while(!feof($handle)) {
      print(fread($handle, 1024*8));
      ob_flush();
      flush();
    }
    fclose($handle);
    $l->finished();
    die();
  }

}