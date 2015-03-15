<?php

class FileHandler extends Plugin implements SplObserver {
  private $maxFileSize;
  private $fileMime;
  const DEBUG = true;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
    $this->maxFileSize = 50*1024*1024;
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    $link = getCurLink();
    if(strpos($link, FILES_DIR."/") !== 0) return;
    $this->handleFileRequest($link);
  }

  private function handleFileRequest($link) {
    #if(!preg_match("/^".FILEPATH_PATTERN."$/", $link)) return;
    $fInfo = $this->checkLink($link);
    $filePath = realpath($fInfo["filepath"]);
    $this->fileMime = $fInfo["filemime"];
    if($filePath === false) return;
    if($this->fileMime == "image/svg+xml") $this->downloadFile($filePath, 0, false);
    if(strpos($this->fileMime, "image/") !== 0) $this->downloadFile($filePath);
    try {
      $this->handleImage($filePath);
    } catch(Exception $e) {
      new ErrorPage(sprintf(_("Unable to handle image %s: %s"), $link, $e->getMessage()), 500);
    }
  }

  private function checkLink($link) {
    $fInfo["filepath"] = USER_FOLDER."/$link";
    if(!is_file($fInfo["filepath"]))
      new ErrorPage(sprintf(_("The requested URL '%s' was not found on this server."), $link), 404);
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
    $fInfo["filemime"] = getFileMime($fInfo["filepath"]);
    if(array_key_exists($fInfo["filemime"], $disallowedMime))
      new ErrorPage(sprintf(_("Unsupported mime type '%s'"), $fInfo["filemime"]), 415);
    return $fInfo;
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
    header('Cache-Control: max-age=31104000'); // 1 year
    $etagFile = hash("md5", filemtime($filePath));
    $etagHeader=(isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false);
    header("Etag: $etagFile");
    $notModifiedEnabled = true;
    if(IS_LOCALHOST || CMS_DEBUG || preg_match("/^\D/", CMS_RELEASE)) $notModifiedEnabled = false;
    if(self::DEBUG) $notModifiedEnabled = true;
    if($notModifiedEnabled && $etagHeader == $etagFile) {
      header("HTTP/1.1 304 Not Modified");
      exit;
    }
    set_time_limit(0);
    if(IS_LOCALHOST) echo file_get_contents($filePath);
    else passthru("cat $filePath", $err);
    if($err != 0) new ErrorPage(sprintf(_("Unable to pass file %s"), $shortPath), 500);
    if($log) new Logger("File download '$shortPath' ".fileSizeConvert($fileSize), Logger::LOGGER_INFO, $start_time, false);
    die();
  }

  private function handleImage($filePath) {
    $var = array(
      "thumb" => array(200, 200, 50*1024, 85),
      "normal" => array(1000, 1000, 225*1024, 85),
      "big" => array(1500, 1500, 350*1024, 75),
      "full" => array(0, 0, 0, 0)
      );
    $vName = "normal";
    foreach($var as $name => $v) {
      if(!isset($_GET[$name])) continue;
      $vName = $name;
      break;
    }
    $v = $var[$vName];
    if(!$v[0] && !$v[1]) $this->downloadFile($filePath, $v[2]); // log only full size images
    $i = $this->getImageSize($filePath);
    if($i[0] < $v[0] && $i[1] < $v[1]) $this->downloadFile($filePath, $v[2], false);
    $tmpDir = USER_FOLDER."/".$this->pluginDir."/$vName";
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
    $im->setImageCompressionQuality($v[3]);
    if($i[0] > $i[1]) $result = $im->thumbnailImage($v[0], 0);
    else $result = $im->thumbnailImage(0, $v[1]);
    if(!$result)
      throw new Exception(sprintf(_("Unable to resize image %s"), basename($filePath)));
    $imBin = $im->__toString();
    if($im->getImageLength() > $v[2])
      throw new Exception(_("Generated image is too big"));
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
