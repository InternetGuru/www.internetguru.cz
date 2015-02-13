<?php

class ErrorPage {
  private $relDir = "error";
  private $headingFile = "headings.txt";
  private $msgFile = "messages.txt";
  private $errFile = "error.html";
  private $errSimpleFile = "error-simple.html";
  private $whatnowFile = "whatnow.txt";

  public function __construct($message, $code, $extended=false) {
    http_response_code($code);
    new Logger($message, Logger::LOGGER_FATAL);
    $dir = LIB_FOLDER."/".$this->relDir;
    $tt = array(
      "@CODE@" => $code,
      "@STATUS@" => $this->getStatusMessage($code),
      "@ERROR@" => $message,
      "@VERSION@" => CMS_NAME
    );
    if(!$extended) {
      $html = file_get_contents($dir."/".$this->errSimpleFile);
    } else {
      $html = file_get_contents($dir."/".$this->errFile);
      $headings = file($dir."/".$this->headingFile, FILE_SKIP_EMPTY_LINES);
      $tt["@HEADING@"] = $headings[array_rand($headings)];
      $messages = file($dir."/".$this->msgFile, FILE_SKIP_EMPTY_LINES);
      $tt["@MESSAGE@"] = $messages[array_rand($messages)];
      $whatnow = file($dir."/".$this->whatnowFile, FILE_SKIP_EMPTY_LINES);
      $tt["@WHATNOW@"] = array_shift($whatnow);
      $tt["@TIPS@"] = implode("</dd>\n      <dd>", $whatnow);
      $images = $this->getImages($dir);
      $tt["@IMAGE@"] = $images[array_rand($images)];
      $tt["@ROOT@"] = ROOT_URL;
    }
    echo str_replace(array_keys($tt), $tt, $html);
    die();
  }

  private function getImages($dir) {
    $i = array();
    // http://xkcd.com/1350/#p:10e7f9b6-b9b8-11e3-8003-002590d77bdd
    foreach(scandir($dir) as $img) {
      if(pathinfo("$dir/$img", PATHINFO_EXTENSION) != "png") continue;
      $imgPath = LIB_FOLDER."/".$this->relDir."/$img";
      if(IS_LOCALHOST)
        $i[] = ROOT_URL.$imgPath;
      else
        $i[] = getRes("$dir/$img", $imgPath, CMSRES_ROOT_DIR."/".CMS_RELEASE);
    }
    return $i;
  }

  private function getStatusMessage($code) {
    $http_status_codes = array(
      100 => 'Continue',
      102 => 'Processing',
      200 => 'OK',
      201 => 'Created',
      202 => 'Accepted',
      203 => 'Non-Authoritative Information',
      204 => 'No Content',
      205 => 'Reset Content',
      206 => 'Partial Content',
      207 => 'Multi-Status',
      300 => 'Multiple Choices',
      301 => 'Moved Permanently',
      302 => 'Found',
      303 => 'See Other',
      304 => 'Not Modified',
      305 => 'Use Proxy',
      306 => 'unused',
      307 => 'Temporary Redirect',
      400 => 'Bad Request',
      401 => 'Authorization Required',
      402 => 'Payment Required',
      403 => 'Forbidden',
      404 => 'Not Found',
      405 => 'Method Not Allowed',
      406 => 'Not Acceptable',
      407 => 'Proxy Authentication Required',
      408 => 'Request Time-out',
      409 => 'Conflict',
      410 => 'Gone',
      411 => 'Length Required',
      412 => 'Precondition Failed',
      413 => 'Request Entity Too Large',
      414 => 'Request-URI Too Large',
      415 => 'Unsupported Media Type',
      416 => 'Requested Range Not Satisfiable',
      417 => 'Expectation Failed',
      418 => 'unused',
      419 => 'unused',
      420 => 'unused',
      421 => 'unused',
      422 => 'Unprocessable Entity',
      423 => 'Locked',
      424 => 'Failed Dependency',
      425 => 'No code',
      426 => 'Upgrade Required',
      500 => 'Internal Server Error',
      501 => 'Method Not Implemented',
      502 => 'Bad Gateway',
      503 => 'Service Temporarily Unavailable',
      504 => 'Gateway Time-out',
      505 => 'HTTP Version Not Supported',
      506 => 'Variant Also Negotiates',
      507 => 'Insufficient Storage',
      508 => 'unused',
      509 => 'unused',
      510 => 'Not Extended'
    );
    if(!array_key_exists($code, $http_status_codes)) return "Unknown";
    return $http_status_codes[$code];
  }

}

?>