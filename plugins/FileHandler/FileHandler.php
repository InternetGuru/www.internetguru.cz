<?php

class FileHandler extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    if(isAtLocalhost()) return;
    if($subject->getStatus() != "preinit") return;
    $this->handleRequest();
  }

  private function handleRequest() {
    $requestUri = $_SERVER["REQUEST_URI"];
    if(strpos($requestUri,"/") !== 0) $requestUri = "/".$requestUri; // add trailing slash
    $filePathPattern = "/^\/(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9._-]+\.[a-z0-9]{2,4}$/";
    if(!preg_match($filePathPattern,$requestUri,$m)) return;
    $filePath = FILES_FOLDER . $m[0];
    if(isAtLocalhost() || !is_file($filePath)) errorPage("File not found", 404);
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