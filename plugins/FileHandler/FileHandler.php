<?php

class FileHandler extends Plugin implements SplObserver {

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,1);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "preinit") return;
    $this->handleRequest();
  }

  private function handleRequest() {
    $rUri = $_SERVER["REQUEST_URI"];
    $pUrl = parse_url($rUri);
    if($pUrl === false || strpos($pUrl["path"], "//") !== false) errorPage("Bad Request", 400);
    if(!preg_match("/^".preg_quote(getRoot(), "/")."(".FILEPATH_PATTERN.")$/",$rUri,$m)) return;
    $filePath = FILES_FOLDER ."/". $m[1];
    if(!is_file($filePath)) errorPage("File not found", 404);
    $size = filesize($filePath);
    $l = new Logger("File download '$filePath' ".fileSizeConvert($size),null,false);
    $disallowedMime = array(
      "application/x-msdownload" => null,
      "application/x-msdos-program" => null,
      "application/x-msdos-windows" => null,
      "application/x-download" => null,
      "application/bat" => null,
      "application/x-bat" => null,
      "application/com" => null,
      "application/x-com" => null,
      "application/exe" => null,
      "application/x-exe" => null,
      "application/x-winexe" => null,
      "application/x-winhlp" => null,
      "application/x-winhelp" => null,
      "application/x-javascript" => null,
      "application/hta" => null,
      "application/x-ms-shortcut" => null,
      "application/octet-stream" => null,
      "vms/exe" => null,
    );
    $mime = getFileMime($filePath);
    if(array_key_exists($mime,$disallowedMime)) errorPage("Unsupported Media Type", 415);
    header("Content-Type: $mime");
    header("Content-Length: $size");
    set_time_limit(0);
    $file = @fopen($filePath,"rb");
    if($file === false) throw new Exception("Unable to read from file '$filePath'");
    while(!feof($file)) {
      print(fread($file, 1024*8));
      ob_flush();
      flush();
    }
    $l->finished();
    die();
  }

}