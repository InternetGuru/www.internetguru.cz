<?php

class FileHandler extends Plugin implements SplObserver {
  private $maxFileSize;
  private $modes;
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
    $this->maxFileSize = 50*1024*1024;
    $this->modes = array(
      "normal" => array(1000, 1000, 225*1024, 85),
      "thumb" => array(200, 200, 50*1024, 85),
      "big" => array(1500, 1500, 350*1024, 75),
      "full" => array(0, 0, 0, 0));
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    if(!preg_match("/".FILEPATH_PATTERN."/", getCurLink())) return;
    if(strpos(getCurLink(), FILES_DIR."/") !== 0 && strpos(getCurLink(), LIB_DIR."/") !== 0
      && strpos(getCurLink(), THEMES_DIR."/") !== 0 && strpos(getCurLink(), PLUGINS_DIR."/") !== 0)
      throw new Exception(_("File illegal path"));
    try {
      $mode = null;
      $src = findFile(getCurLink());
      if(!$src) {
        $pLink = explode("/", getCurLink());
        if(count($pLink) > 2 && isset($this->modes[$pLink[1]])) {
          $mode = $pLink[1];
          unset($pLink[1]);
          $src = findFile(implode("/", $pLink));
        }
        if(!$src) throw new Exception(_("Requested URL not found on this server"), 404);
      }
      $this->handleUrl($src, getCurLink(), $mode);
      $this->handleDir(dirname($src), dirname(getCurLink()), pathinfo(getCurLink(), PATHINFO_EXTENSION), $mode);
      if(self::DEBUG) new ErrorPage("REFRESH to ".ROOT_URL.getCurLink(), 404);
      redirTo(ROOT_URL.getCurLink());
    } catch(Exception $e) {
      $errno = 500;
      if($e->getCode() != 0) $errno = $e->getCode();
      new ErrorPage(sprintf(_("Unable to handle file request: %s"), $e->getMessage()), $errno);
    }
  }

  private function handleDir($srcDir, $destDir, $ext, $mode) {
    foreach(scandir($srcDir) as $f) {
      if(strpos($f, ".") === 0) continue;
      if(is_dir("$srcDir/$f")) $this->handleDir("$srcDir/$f", $destDir, $ext, $mode);
      if($ext != pathinfo($f, PATHINFO_EXTENSION)) continue;
      try {
        $handleSrc = "$srcDir/$f";
        if(!is_null($mode) && is_file("$srcDir/$mode/$f")) $handleSrc = "$srcDir/$mode/$f";
        if(is_file("$destDir/$f") && filemtime($handleSrc) == filemtime("$destDir/$f")) continue;
        $this->handleUrl($handleSrc, "$destDir/$f", $mode);
      } catch(Exception $e) {
        new Logger(sprintf(_("Unable to handle file %s: %s"), "$srcDir/$f", $e->getMessage()), Logger::LOGGER_WARNING);
      }
    }
  }

  private function handleUrl($src, $dest, $mode) {
    $mimeType = getFileMime($src);
    if($mimeType != "image/svg+xml" && strpos($mimeType, "image/") === 0) {
      $this->handleImage(realpath($src), $dest, $mimeType, $mode);
      return;
    }
    $registeredMime = array(
      "inode/x-empty" => array(), // empty file with any ext
      "text/plain" => array("css", "js"),
      "application/x-elc" => array("js"),
      "application/octet-stream" => array("woff", "js"),
    );
    $ext = pathinfo($src, PATHINFO_EXTENSION);
    if(!isset($registeredMime[$mimeType]) || (!empty($registeredMime[$mimeType]) && !in_array($ext, $registeredMime[$mimeType])))
      throw new Exception(sprintf(_("Unsupported mime type %s"), $mimeType), 415);
    smartCopy($src, $dest);
  }

  private function handleImage($src, $dest, $mimeType, $mode) {
    reset($this->modes);
    $v = is_null($mode) ? current($this->modes) : $this->modes[$mode];
    $i = $this->getImageSize($src);
    if($i[0] < $v[0] && $i[1] < $v[1]) {
      $fileSize = filesize($src);
      if($fileSize > $v[2])
        throw new Exception(sprintf(_("Image size %s is over limit %s"), fileSizeConvert($fileSize), fileSizeConvert($v[2])));
      smartCopy($src, $dest);
      return;
    }
    $im = new Imagick($src);
    $im->setImageCompressionQuality($v[3]);
    if($i[0] > $i[1]) $result = $im->thumbnailImage($v[0], 0);
    else $result = $im->thumbnailImage(0, $v[1]);
    if(!$result)
      throw new Exception(_("Unable to resize image"));
    $imBin = $im->__toString();
    if($im->getImageLength() > $v[2])
      throw new Exception(_("Generated image is too big"));
    if(!strlen($imBin) || !safeRewrite($imBin, $dest) || !touch($dest, filemtime($src)))
      throw new Exception(_("Unable to save image"));
  }

  private function getImageSize($imagePath) {
    $i = @getimagesize($imagePath);
    if(is_array($i)) return $i;
    throw new Exception(_("Failed to get image dimensions"));
  }

}
