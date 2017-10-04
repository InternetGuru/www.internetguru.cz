<?php

namespace IGCMS\Plugins;

use Autoprefixer;
use DirectoryIterator;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use IGCMS\Core\ResourceInterface;
use Imagick;
use SplObserver;
use SplSubject;
use UglifyPHP\JS;

/**
 * Class FileHandler
 * @package IGCMS\Plugins
 */
class FileHandler extends Plugin implements SplObserver, ResourceInterface {
  /**
   * @var bool
   */
  const DEBUG = false;
  /**
   * @var array
   */
  private static $imageModes = [
    "" => [1000, 1000, 307200, 85], // default, e.g. resources like icons
    "images" => [1000, 1000, 307200, 85], // 300 kB
    "preview" => [500, 500, 204800, 85], // 200 kB
    "thumbs" => [200, 200, 71680, 85], // 70 kB
    "big" => [1500, 1500, 460800, 75], // 450 kB
    "full" => [0, 0, 0, 0],
  ];
  /**
   * @var array
   */
  private static $legalMime = [
    "inode/x-empty" => ["css", "js"],
    "text/plain" => ["css", "js"],
    "text/troff" => ["css"],
    "text/html" => ["js", "svg"],
    "text/x-c" => ["js"],
    "application/x-elc" => ["js"],
    "application/x-empty" => ["css", "js"],
    "application/octet-stream" => ["woff", "woff2", "eot", "js"],
    "image/svg+xml" => ["svg"],
    "image/png" => ["png"],
    "image/jpeg" => ["jpg", "jpeg"],
    "image/gif" => ["gif"],
    "application/pdf" => ["pdf"],
    "application/vnd.ms-fontobject" => ["eot"],
    "application/x-font-ttf" => ["ttf"],
    "application/vnd.ms-opentype" => ["otf"],
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document" => ["docx"],
  ];
  /**
   * @var array
   */
  private static $fileFolders = [
    THEMES_DIR => true, PLUGINS_DIR => true, LIB_DIR => true, VENDOR_DIR => true, FILES_DIR => false,
  ];
  /**
   * @var bool
   */
  private $deleteCache;
  /**
   * @var array
   */
  private $update = [];
  /**
   * @var array
   */
  private $error = [];

  /**
   * FileHandler constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 70);
    $this->deleteCache = isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_FILE;
  }

  public static function isSupportedRequest ($filePath) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    foreach (self::$legalMime as $extensions) {
      if (in_array($ext, $extensions)) {
        return true;
      }
    }
    return false;
  }

  public static function handleRequest () {
    try {
      $dest = getCurLink();
      $fInfo = self::getFileInfo($dest);
      #if(self::DEBUG) var_dump($fInfo);
      if (!is_file($dest)) {
        self::createFile($fInfo["src"], $dest, $fInfo["ext"], $fInfo["imgmode"], $fInfo["isroot"], $fInfo["resdir"]);
      }
      if (in_array($fInfo["ext"], ["css", "js"])) {
        self::outputFile($dest, "text/".$fInfo["ext"]);
        if (self::DEBUG) {
          unlink($dest);
        }
        exit;
      }
      redirTo(ROOT_URL.$dest);
    } catch (Exception $e) {
      $errno = $e->getCode() ? $e->getCode() : 500;
      $msg = strlen($e->getMessage()) ? $e->getMessage() : _("Server error");
      throw new Exception(sprintf(_("Unable to handle file request: %s"), $msg), $errno);
    }
    exit;
  }

  /**
   * @param string $ext
   * @return bool
   */
  public static function isImage ($ext) {
    return in_array(strtolower($ext), ["jpg", "png", "gif", "jpeg"]);
  }

  /**
   * @param string $reqFilePath
   * @return array
   * @throws Exception
   */
  private static function getFileInfo ($reqFilePath) {
    $reqFilePath = trim($reqFilePath, "/");
    $fInfo["src"] = null;
    $fInfo["imgmode"] = null;
    $fInfo["ext"] = strtolower(pathinfo($reqFilePath, PATHINFO_EXTENSION));
    $slashPos = strpos($reqFilePath, "/");
    $rootDir = substr($reqFilePath, 0, $slashPos);
    $fInfo["isroot"] = array_key_exists($rootDir, self::$fileFolders);
    $srcFilePath = $reqFilePath;
    if (!$fInfo["isroot"]) {
      $srcFilePath = substr($reqFilePath, $slashPos + 1);
    }
    // check path
    $resDir = null;
    foreach (self::$fileFolders as $dir => $resDir) {
      if (strpos($srcFilePath, "$dir/") !== 0) {
        continue;
      }
      if (!$resDir && !$fInfo["isroot"]) {
        break;
      } // eg. beta/files, res/files/*
      $fInfo["src"] = $srcFilePath;
      $fInfo["resdir"] = $resDir;
      break;
    }
    if (is_null($fInfo["src"])) {
      throw new Exception(_("File illegal path"), 403);
    }
    // search for sorce
    $fInfo["src"] = self::findFile($fInfo["src"]);
    if (!$resDir && is_null($fInfo["src"]) && self::isImage($fInfo["ext"])) {
      $fInfo["imgmode"] = self::getImageMode($srcFilePath);
      if (strlen($fInfo["imgmode"])) {
        $imgFilePath = self::getImageSource($srcFilePath, $fInfo["imgmode"]);
        $fInfo["src"] = self::findFile($imgFilePath);
      }
    }
    if (is_null($fInfo["src"])) {
      throw new Exception(_("File not found"), 404);
    }
    return $fInfo;
  }

  /**
   * @param string $filePath
   * @return string|null
   */
  private static function findFile ($filePath) {
    try {
      return findFile($filePath);
    } catch (Exception $e) {
      return null;
    }
  }

  /**
   * @param string $filePath
   * @return string|null
   */
  private static function getImageMode ($filePath) {
    foreach (self::$imageModes as $mode => $null) {
      if (strpos("$filePath/", FILES_DIR."/$mode/") === 0) {
        return $mode;
      }
    }
    return "";
  }

  /**
   * @param string $src
   * @param string $mode
   * @return string
   */
  private static function getImageSource ($src, $mode) {
    if (!strlen($mode)) {
      return $src;
    }
    return FILES_DIR.substr($src, strlen(FILES_DIR."/".$mode));
  }

  /**
   * @param string $src
   * @param string $dest
   * @param string $ext
   * @param string $imgmode
   * @param bool $isRoot
   * @param string $resDir
   */
  private static function createFile ($src, $dest, $ext, $imgmode, $isRoot, $resDir) {
    $fp = lock_file($dest);
    try {
      if (is_file($dest)) {
        return;
      }
      self::checkMime($src, $ext);
      if ($isRoot && $resDir) {
        self::handleResource($src, $dest, $ext);
      } elseif ($isRoot && !$resDir && self::isImage($ext)) {
        self::handleImage($src, $dest, $imgmode);
      } else {
        copy_plus($src, $dest);
      }
    } catch (Exception $ex) {
      Logger::error(sprintf(_("Unable to handle resource '%s': %s"), $dest, $ex->getMessage()));
      self::outputFile($src, "text/$ext");
      exit;
    } finally {
      unlock_file($fp, $dest);
    }
  }

  /**
   * @param string $src
   * @param string $ext
   * @throws Exception
   */
  private static function checkMime ($src, $ext) {
    $mime = getFileMime($src);
    if (isset(self::$legalMime[$mime]) && in_array($ext, self::$legalMime[$mime])) {
      return;
    }
    throw new Exception(sprintf(_("Unsupported mime type %s"), $mime), 415);
  }

  /**
   * @param string $src
   * @param string $dest
   * @param string $ext
   */
  private static function handleResource ($src, $dest, $ext) {
    if (strpos($src, CMS_FOLDER."/") === 0 && is_file(CMSRES_FOLDER."/".getCurLink())) { // using default file
      copy_plus(CMSRES_FOLDER."/".getCurLink(), $dest);
      return;
    }
    switch ($ext) {
      case "css":
        self::buildCss($src, $dest);
        break;
      case "js":
        self::buildJs($src, $dest);
        break;
      default:
        copy_plus($src, $dest);
        return;
    }
    touch($dest, filemtime($src));
    Logger::info(sprintf(_("File %s was successfully built"), getCurLink()));
  }

  /**
   * @param string $src
   * @param string $dest
   */
  private static function buildCss ($src, $dest) {
    $data = file_get_contents($src);
    $autoprefixer = new Autoprefixer(['last 2 version']);
    $data = $autoprefixer->compile($data);
    file_put_contents($dest, $data);
  }

  /**
   * @param string $src
   * @param string $dest
   * @throws Exception
   */
  private static function buildJs ($src, $dest) {
    if (!JS::installed()) {
      throw new Exception(_("UglifyJS not installed"));
    }
    $js = new JS($src);
    if ($js->minify($dest)) {
      return;
    }
    throw new Exception(_("Unable to minify JS"));
  }

  /**
   * @param $src
   * @return array [targetWidth, targetHeight]
   */
  public static function calculateImageSize ($src) {
    $finfo = self::getFileInfo($src);
    list($maxWidth, $maxHeight) = self::$imageModes[$finfo["imgmode"]];
    list($origWidth, $origHeight) = getimagesize($finfo["src"]);
    $ratio = $origWidth / $origHeight;
    if ($ratio < 1) {
      $imgHeight = min($maxHeight, $origHeight);
      $imgWidth = $imgHeight * $ratio;
    } else {
      $imgWidth = min($maxWidth, $origWidth);
      $imgHeight = $imgWidth / $ratio;
    }
    return [round($imgWidth), round($imgHeight)];
  }

  /**
   * @param string $src
   * @param string $dest
   * @param string $mode
   * @throws Exception
   */
  private static function handleImage ($src, $dest, $mode) {
    $mode = self::$imageModes[$mode];
    $src = realpath($src);
    $i = self::getImageSize($src);
    if ($i[0] <= $mode[0] && $i[1] <= $mode[1]) {
      $fileSize = filesize($src);
      if ($fileSize > $mode[2]) {
        throw new Exception(
          sprintf(_("Image size %s is over limit %s"), fileSizeConvert($fileSize), fileSizeConvert($mode[2]))
        );
      }
      copy_plus($src, $dest);
      return;
    }
    if ($mode[0] == 0 && $mode[1] == 0) {
      copy_plus($src, $dest);
      return;
    }
    $im = new Imagick($src);
    $im->setImageCompressionQuality($mode[3]);
    if ($i[0] > $i[1]) {
      $result = $im->thumbnailImage($mode[0], 0);
    } else {
      $result = $im->thumbnailImage(0, $mode[1]);
    }
    #var_dump($im->getImageLength());
    $imBin = $im->__toString();
    if (!$result || !strlen($imBin)) {
      throw new Exception(_("Unable to resize image"));
    }
    if (strlen($imBin) > $mode[2]) {
      throw new Exception(
        sprintf(
          _("Generated image size %s is over limit %s"),
          fileSizeConvert(strlen($imBin)),
          fileSizeConvert($mode[2])
        )
      );
    }
    mkdir_plus(dirname($dest));
    $b = file_put_contents($dest, $imBin);
    if ($b === false || !touch($dest, filemtime($src))) {
      throw new Exception(_("Unable to create file"));
    }
  }

  /**
   * @param string $imagePath
   * @return array
   * @throws Exception
   */
  private static function getImageSize ($imagePath) {
    $i = @getimagesize($imagePath);
    if (is_array($i)) {
      return $i;
    }
    throw new Exception(_("Failed to get image dimensions"));
  }

  /**
   * @param string $file
   * @param string $mime
   * @throws Exception
   */
  private static function outputFile ($file, $mime) {
    if (!stream_resolve_include_path($file)) {
      throw new Exception(sprintf(_("File %s does not exists"), $file));
    }
    header("Content-type: $mime");
    echo file_get_contents($file);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() == STATUS_PROCESS) {
      $this->checkResources();
    }
    if ($subject->getStatus() != STATUS_PREINIT) {
      return;
    }
    Cms::setVariable("cache_file", getCurLink()."?".CACHE_PARAM."=".CACHE_FILE);
  }

  private function checkResources () {
    if (!Cms::isSuperUser()) {
      return;
    }
    if (isset($_GET[CACHE_PARAM]) && $_GET[CACHE_PARAM] == CACHE_IGNORE) {
      return;
    }
    foreach (self::$fileFolders as $sourceFolder => $isResDir) {
      $folder = getRealResDir($sourceFolder);
      if ($isResDir && stream_resolve_include_path($folder)) {
        $this->doCheckResources($folder, $sourceFolder, $isResDir);
      }
      if (stream_resolve_include_path($sourceFolder) && !$isResDir || getRealResDir() == RESOURCES_DIR) {
        $this->doCheckResources($sourceFolder, $sourceFolder, $isResDir);
      }
    }
    if (count($this->error)) {
      Logger::critical(sprintf(_("Unable to update file cache: %s"), implode(", ", $this->error)));
    } elseif (count($this->update)) {
      if ($this->deleteCache) {
        Logger::user_success(
          sprintf(_("File cache successfully updated: %s"), implode(", ", array_keys($this->update)))
        );
      } else {
        Logger::user_notice(sprintf(_("Outdated file cache: %s"), implode(", ", array_keys($this->update))));
      }
    }
  }

  /**
   * @param $cacheFolder
   * @param $sourceFolder
   * @param $isResDir
   * @return bool
   */
  private function doCheckResources ($cacheFolder, $sourceFolder, $isResDir) {
    $inotifyUpToDate = $this->getSrcFolders(
      $cacheFolder,
      $sourceFolder,
      $isResDir,
      $refTs
    );
   // create user files folder if not exists
    if (!$isResDir && !stream_resolve_include_path(USER_FOLDER."/$sourceFolder")) {
      try {
        mkdir_plus(USER_FOLDER."/$sourceFolder");
        touch(USER_FOLDER."/$sourceFolder/".INOTIFY, $refTs);
      } catch (Exception $e) {
        Logger::error(sprintf(_("Unable to create user folder %s"), $sourceFolder));
      }
    }
    // all folder .inotify files uptodate
    if ($inotifyUpToDate) {
      return true;
    }
    $iter = new DirectoryIterator($cacheFolder);
    $files = [];
    $doTouch = true;
    foreach ($iter as $splfi) {
      if ($splfi->isDot() || $splfi->getFilename() == INOTIFY) {
        continue;
      }
      if ($splfi->isDir()) {
        $childUpToDate = $this->doCheckResources(
          ($cacheFolder ? "$cacheFolder/" : "").$splfi->getFilename(),
          ($sourceFolder ? "$sourceFolder/" : "").$splfi->getFilename(),
          $isResDir
        );
        if (!$childUpToDate) {
          $doTouch = false;
        }
        continue;
      }
      $files[] = $splfi->getFilename();
    }
    // touch .inotify files if folder gets to be uptodate
    $upToDate = $this->folderUpToDate(
      $cacheFolder,
      $sourceFolder,
      $isResDir,
      $files
    );
    if ($doTouch && $upToDate) {
      if (!touch("$cacheFolder/".INOTIFY, $refTs) && CMS_DEBUG) {
        Logger::debug("Unable to touch $folder/".INOTIFY);
      }
    }
    return $upToDate && $doTouch;
  }

  /**
   * @param string $cacheFolder
   * @param string $sourceFolder
   * @param bool $isResDir
   * @param int $newestFilemtime
   * @return bool
   */
  private function getSrcFolders ($cacheFolder, $sourceFolder, $isResDir, &$newestFilemtime) {
    // check for .inotify in cms/admin/user/domain
    $newestFilemtime = null;
    $cacheMtime = null;
    if (!stream_resolve_include_path("$cacheFolder/".INOTIFY)) {
      return false;
    }
    $cacheMtime = filemtime("$cacheFolder/".INOTIFY);
    $folders = [];
    // files folder has no defaults
    if ($isResDir) {
      $folders[CMS_FOLDER."/$sourceFolder"] = true;
    } else {
      $rawSourceFolder = $this->getImageSource(
        $cacheFolder, self::getImageMode($cacheFolder)
      );
      $folders[ADMIN_FOLDER."/$rawSourceFolder"] = false;
      $folders[USER_FOLDER."/$rawSourceFolder"] = false;
    }
    $folders[ADMIN_FOLDER."/$sourceFolder"] = false;
    $folders[USER_FOLDER."/$sourceFolder"] = false;
    foreach ($folders as $folder => $isCmsFolder) {
      if (!stream_resolve_include_path($folder)) {
        continue;
      }
      if (!stream_resolve_include_path("$folder/".INOTIFY)) {
        touch("$folder/".INOTIFY);
      }
      $filemtime = filemtime("$folder/".INOTIFY);
      if ($filemtime > $newestFilemtime) {
        $newestFilemtime = $filemtime;
      }
    }
    // redundant dir if only one
    if (is_null($newestFilemtime)) {
      if ($this->deleteCache) {
        @rmdir_plus($cacheFolder);
        if (stream_resolve_include_path($cacheFolder)) {
          $this->error[] = $cacheFolder;
          return false;
        }
        if (CMS_DEBUG) {
          Logger::debug("Removed cache folder $cacheFolder");
        }
      }
      $this->update[$cacheFolder] = $cacheFolder;
      return false;
    }
    return $cacheMtime >= $newestFilemtime;
  }

  /**
   * @param string $cacheFolder
   * @param string $sourceFolder
   * @param bool $isResDir
   * @param array $files
   * @return bool
   */
  private function folderUpToDate ($cacheFolder, $sourceFolder, $isResDir, Array $files) {
    $folderUptodate = true;
    foreach ($files as $f) {
      $cacheFilePath = "$cacheFolder/$f";
      $sourceFilePath = "$sourceFolder/$f";
      $fileUptodate = $this->updateCacheFile(
        $sourceFilePath, $cacheFilePath, $isResDir
      );
      if (!$fileUptodate) {
        $folderUptodate = false;
      }
    }
    if (CMS_DEBUG) {
      Logger::debug("Cache folder $cacheFolder uptodate: ".($folderUptodate ? "YES" : "NO"));
    }
    return $folderUptodate;
  }

  /**
   * @param string $fileName
   * @param string $cacheFilePath
   * @param bool $isResDir
   * @return bool
   */
  private function updateCacheFile ($fileName, $cacheFilePath, $isResDir) {
    $sourceFilePath = self::findFile($fileName);
    if (is_null($sourceFilePath) && !$isResDir && self::isImage(pathinfo($cacheFilePath, PATHINFO_EXTENSION))) {
      $sourceFilePath = $this->getImageSource($cacheFilePath, self::getImageMode($cacheFilePath));
      $sourceFilePath = self::findFile($sourceFilePath);
    }
    if (!is_file($sourceFilePath)) {
      return $this->deleteCache($cacheFilePath, $fileName);
    }
    if (isUptodate($sourceFilePath, $cacheFilePath)) {
      return true;
    }
    if (self::DEBUG) {
      Cms::notice(
        sprintf("%s@%s | %s@%s", $cacheFilePath, filemtime($cacheFilePath), $sourceFilePath, filemtime($sourceFilePath))
      );
    }
    return $this->deleteCache($cacheFilePath, $fileName);
  }

  /**
   * @param string $cacheFilePath
   * @param string $fileName
   * @return bool
   */
  private function deleteCache ($cacheFilePath, $fileName) {
    if (!is_file($cacheFilePath)) {
      return true;
    }
    if ($this->deleteCache) {
      @unlink($cacheFilePath);
      if (is_file($cacheFilePath)) {
        $this->error[] = $cacheFilePath;
        return false;
      }
    }
    $this->update[$fileName] = $cacheFilePath;
    return $this->deleteCache;
  }

}

