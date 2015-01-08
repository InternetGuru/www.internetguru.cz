<?php

class FileHandler extends Plugin implements SplObserver {
  private $maxFileSize;
  private $fileMime;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
    $this->maxFileSize = 50*1024*1024;
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    $this->handleRequest();
  }

  private function handleRequest() {
    $fInfo = checkUrl(FILES_FOLDER);
    if(is_null($fInfo["filepath"])) return;
    $filePath = realpath($fInfo["filepath"]);
    $this->fileMime = $fInfo["filemime"];
    if($filePath === false) return;
    if($this->fileMime == "image/svg+xml") $this->downloadFile($filePath, 0, false);
    if(strpos($this->fileMime, "image/") !== 0) $this->downloadFile($filePath);
    try {
      $this->handleImage($filePath);
    } catch(Exception $e) {
      new ErrorPage(sprintf(_("Unable to handle image: %s")
        , CMS_DEBUG ? $e->getMessage() : $fInfo["filepath"]), 500);
    }
  }

  private function downloadFile($filePath, $maxSize = 0, $log = true) {
    $fileSize = filesize($filePath);
    $shortPath = substr($filePath, strlen(FILES_FOLDER));
    if(!$fileSize)
      new ErrorPage(sprintf(_("File %s is empty"), $shortPath), 500);
    if($maxSize && $fileSize > $maxSize)
      new ErrorPage(sprintf(_("File %s size %s is exceeding variable limit %s"),
        $shortPath, fileSizeConvert($fileSize), fileSizeConvert($maxSize)), 500);
    if($fileSize > $this->maxFileSize)
      new ErrorPage(sprintf(_("File %s size %s is exceeding global limit %s"),
        $shortPath, fileSizeConvert($fileSize), fileSizeConvert($this->maxFileSize)), 500);
    $start_time = microtime(true);
    header("Content-Type: ".$this->fileMime);
    header("Content-Length: $fileSize");
    set_time_limit(0);
    $handle = @fopen($filePath, "rb");
    if($handle === false)
      new ErrorPage(sprintf(_("Unable to read file '%s'"), $shortPath), 500);
    while(!feof($handle)) {
      print(fread($handle, 1024*8));
      ob_flush();
      flush();
    }
    fclose($handle);
    if($log) new Logger("File download '$shortPath' ".fileSizeConvert($fileSize), Logger::LOGGER_INFO, $start_time);
    die();
  }

  private function handleImage($filePath) {
    $var = array(
      "thumb" => array(200, 200, 50*1024),
      "normal" => array(1000, 1000, 300*1024),
      "full" => array(0, 0, 0)
      );
    $vName = "normal";
    foreach($var as $name => $v) {
      if(!isset($_GET[$name])) continue;
      $vName = $name;
      break;
    }
    $v = $var[$vName];
    if(!$v[0] && !$v[1]) $this->downloadFile($filePath, $v[2]);
    $i = $this->getImageSize($filePath);
    if($i[0] < $v[0] && $i[1] < $v[1]) $this->downloadFile($filePath, $v[2]);
    $tmpDir = TEMP_FOLDER."/".PLUGINS_DIR."/".get_class($this)."/$vName";
    if(!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true))
      throw new Exception(_("Unable to create temporary folder"));
    $tmpPath = $tmpDir."/".basename($filePath);
    if(is_file($tmpPath) && filemtime($filePath) < filemtime($tmpPath)) {
      $i = $this->getImageSize($tmpPath);
      if($i[0] > $v[0] || $i[1] > $v[1])
        throw new Exception(_("Image dimensions are over limit"));
      $this->downloadFile($tmpPath, $v[2], false);
    }
    $im = new Imagick(realpath($filePath));
    $im->setImageCompressionQuality(88);
    if($i[0] > $i[1]) $result = $im->thumbnailImage($v[0], 0);
    else $result = $im->thumbnailImage(0, $v[1]);
    if(!$result)
      throw new Exception(sprintf(_("Unable to resize image %s"), basename($filePath)));
    $imBin = $im->__toString();
    if(!strlen($imBin) || !file_put_contents($tmpPath, $imBin)) {
      if(is_file($tmpPath)) @unlink($tmpPath);
      throw new Exception(_("Unable to create temporary image"));
    }
    $this->downloadFile($tmpPath, $v[2], false);
  }

  private function getImageSize($imagePath) {
    $i = @getimagesize($imagePath);
    if(is_array($i)) return $i;
    throw new Exception(_("Failed to get image dimensions"));
  }

}