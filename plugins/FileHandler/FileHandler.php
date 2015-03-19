<?php

class FileHandler extends Plugin implements SplObserver {
  private $maxFileSize;
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
    $this->maxFileSize = 50*1024*1024;
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    if(!preg_match("/".FILEPATH_PATTERN."/", getCurLink())) return;
    if(strpos(getCurLink(), FILES_DIR."/") !== 0 && strpos(getCurLink(), LIB_DIR."/") !== 0
      && strpos(getCurLink(), THEMES_DIR."/") !== 0 && strpos(getCurLink(), PLUGINS_DIR."/") !== 0)
      throw new Exception(_("File illegal path"));
    try {
      $this->handleFile();
      redirTo(ROOT_URL.getCurLink());
    } catch(Exception $e) {
      $errno = 500;
      if($e->getCode() != 0) $errno = $e->getCode();
      new ErrorPage(sprintf(_("Unable to handle file request: %s"), $e->getMessage()), $errno);
    }
  }

  private function handleFile() {
    $mode = "";
    $dest = getCurLink();
    $src = findFile($dest);
    $pLink = explode("/", $dest);
    if(count($pLink) > 2) {
      $mode = $pLink[1];
      if($src === false) {
        unset($pLink[1]);
        $src = findFile(implode("/", $pLink));
      }
    }
    if(!$src) throw new Exception(_("Requested URL not found on this server"), 404);
    $fp = lockFile($src);
    if(is_file($dest)) {
      unlockFile($fp);
      return;
    }
    $mimeType = getFileMime($src);
    if($mimeType != "image/svg+xml" && strpos($mimeType, "image/") === 0) {
      $modes = array(
        "" => array(1000, 1000, 225*1024, 85), // default, eg. resources like icons
        "images" => array(1000, 1000, 225*1024, 85),
        "thumbs" => array(200, 200, 50*1024, 85),
        "bigimages" => array(1500, 1500, 350*1024, 75),
        "fullimages" => array(0, 0, 0, 0));
      if(!isset($modes[$mode])) throw new Exception(_("Unknown image mode"));
      $this->handleImage(realpath($src), $dest, $modes[$mode]);
      unlockFile($fp);
      return;
    }
    $registeredMime = array(
      "inode/x-empty" => array(), // empty file with any ext
      "text/plain" => array("css", "js"),
      "text/x-c" => array("js"),
      "application/x-elc" => array("js"),
      "application/x-empty" => array("css", "js"),
      "application/octet-stream" => array("woff", "js"),
      "image/svg+xml" => array("svg"),
      "application/pdf" => array("pdf"),
    );
    $ext = pathinfo($src, PATHINFO_EXTENSION);
    if(!isset($registeredMime[$mimeType]) || (!empty($registeredMime[$mimeType]) && !in_array($ext, $registeredMime[$mimeType])))
      throw new Exception(sprintf(_("Unsupported mime type %s"), $mimeType), 415);
    copy_plus($src, $dest);
    unlockFile($fp);
  }

  private function handleImage($src, $dest, $mode) {
    $i = $this->getImageSize($src);
    if($i[0] < $mode[0] && $i[1] < $mode[1]) {
      $fileSize = filesize($src);
      if($fileSize > $mode[2])
        throw new Exception(sprintf(_("Image size %s is over limit %s"), fileSizeConvert($fileSize), fileSizeConvert($mode[2])));
      copy_plus($src, $dest);
      return;
    }
    $im = new Imagick($src);
    $im->setImageCompressionQuality($mode[3]);
    if($i[0] > $i[1]) $result = $im->thumbnailImage($mode[0], 0);
    else $result = $im->thumbnailImage(0, $mode[1]);
    #var_dump($im->getImageLength());
    $imBin = $im->__toString();
    if(!$result || !strlen($imBin))
      throw new Exception(_("Unable to resize image"));
    if(strlen($imBin) > $mode[2])
      throw new Exception(_("Generated image is too big"));
    mkdir_plus(dirname($dest));
    $b = file_put_contents($dest, $imBin);
    if($b === false || !touch($dest, filemtime($src))) throw new Exception(_("Unable to create file"));
  }

  private function getImageSize($imagePath) {
    $i = @getimagesize($imagePath);
    if(is_array($i)) return $i;
    throw new Exception(_("Failed to get image dimensions"));
  }

}
