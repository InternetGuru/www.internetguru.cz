<?php

namespace IGCMS\Core;

/**
 * Class ErrorPage
 * @package IGCMS\Core
 */
class ErrorPage {
  /**
   * @var string
   */
  private $relDir = "error";
  /**
   * @var string
   */
  private $errFile = "error.html";

  /**
   * ErrorPage constructor.
   * @param string $message
   * @param int $code
   * @param bool $forceExtended
   */
  public function __construct ($message, $code, $forceExtended = false) {
    // log
    $logMessage = get_class($this).": $message ($code)";
    $code < 500 ? Logger::info($logMessage) : Logger::alert($logMessage);
    // set status code
    http_response_code($code);
    $status = $this->getStatusMessage($code);
    // set output variables
    $var = [
      "@LANGUAGE@" => "cs",
      "@TITLE@" => "$code $status",
      "@GENERATOR@" => CMS_NAME,
      "@STYLE@" => "body {font-family: Tahoma, sans-serif; line-height: 1.4; margin: 0} "
        ."#content {padding: 0 0.5em; max-width: 35em} ",
      "@CLASS@" => "na",
      "@HEADING@" => "$code $status",
      "@SUMMARY@" => $message,
      "@CONTENT@" => "",
    ];
    // get file
    $dir = LIB_FOLDER."/".$this->relDir;
    $html = file_get_contents($dir."/".$this->errFile);
    if ($code >= 500 || $forceExtended) {
      $whatnow = [
        _("Try again lager."),
        _("Try to return to previous page (Back button)."),
        "<a href='/'>"._("Go to home page.")."</a>",
        _("Contact webmaster."),
      ];
      $images = $this->getImages($dir);
      $var["@CLASS@"] = "img".array_rand($images);
      $var["@CONTENT@"] = "<ul><li>".implode("</li><li>", $whatnow)."</li></ul>";
      $var["@STYLE@"] .= "#content {padding-bottom: 12em; background-repeat: no-repeat;"
        ." background-position: right bottom; background-size: 15em} ";
      foreach ($images as $imgId => $img) {
        $var["@STYLE@"] .= "#content.img$imgId {background-image: url('$img')} ";
      }
    }
    echo str_replace(array_keys($var), $var, $html);
    exit();
  }

  /**
   * @param int $code
   * @return string
   */
  private function getStatusMessage ($code) {
    $httpStatusCodes = [
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
      451 => 'Unavailable For Legal Reasons',
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
      510 => 'Not Extended',
    ];
    if (!array_key_exists($code, $httpStatusCodes)) {
      return "Unknown";
    }
    return $httpStatusCodes[$code];
  }

  /**
   * @param string $dir
   * @return array
   */
  private function getImages ($dir) {
    $images = [];
    // http://xkcd.com/1350/#p:10e7f9b6-b9b8-11e3-8003-002590d77bdd
    foreach (scandir($dir) as $img) {
      if (strpos($img, ".") === 0) {
        continue;
      }
      if (pathinfo("$dir/$img", PATHINFO_EXTENSION) != "png") {
        continue;
      }
      $images[] = ROOT_URL.LIB_DIR."/".$this->relDir."/$img";
    }
    return $images;
  }

}
