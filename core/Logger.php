<?php


namespace IGCMS\Core;

use IGCMS\Core\Cms;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\IntrospectionProcessor;
use Exception;

// TODO doc
class Logger {

  const TYPE_SYS_LOG  = "sys";
  const TYPE_USER_LOG = "usr";
  const TYPE_MAIL_LOG = "eml";

  const EMAIL_ALERT_TO   = "pavel.petrzela@internetguru.cz jiri.pavelka@internetguru.cz";
  const EMAIL_ALERT_FROM = "no-reply@internetguru.cz";

  /**
   * Monolog logger name, e.g. IGCMS_log.
   * Can be used in LOG_FORMAT as %channel%.
   */
  const LOGGER_NAME = "IGCMS";

  /**
   * Log format for TYPE_SYS_LOG and TYPE_USER_LOG.
   */
  const LOG_FORMAT   = "[%datetime%] %level_name%: %message% %extra%\n";

  /**
   * Log format for TYPE_MAIL_LOG.
   */
  const EMAIL_FORMAT = "[%datetime%]: %message%\n";

  /**
   * Monolog system logger instance.
   *
   * @var Monolog\Logger
   */
  private static $monologsys = null;

  /**
   * Monolog user logger instance.
   *
   * @var Monolog\Logger
   */
  private static $monologusr = null;

  /**
   * Monolog mail logger instance.
   *
   * @var Monolog\Logger
   */
  private static $monologeml = null;

  /**
   * The Log levels.
   *
   * @see   http://tools.ietf.org/html/rfc5424#section-6.2.1
   * @var array
   */
  private static $levels = [
    'debug'       => MonologLogger::DEBUG,
    'info'        => MonologLogger::INFO,
    'user_info'   => MonologLogger::INFO,
    'user_success'=> MonologLogger::INFO,
    'mail'        => MonologLogger::INFO,
    'notice'      => MonologLogger::NOTICE,
    'user_notice' => MonologLogger::NOTICE,
    'warning'     => MonologLogger::WARNING,
    'user_warning'=> MonologLogger::WARNING,
    'error'       => MonologLogger::ERROR,
    'user_error'  => MonologLogger::ERROR,
    'critical'    => MonologLogger::CRITICAL,
    'alert'       => MonologLogger::ALERT,
    'emergency'   => MonologLogger::EMERGENCY,
  ];

  // TODO doc
  public static function __callStatic($methodName, $arguments) {
    if(!array_key_exists($methodName, self::$levels))
      throw new Exception(sprintf(_("Undefined method name %s"), $methodName));
    if(!strlen($arguments[0]))
      throw new Exception(sprintf(_("Method %s empty or missing message"), $methodName));

    $type = self::TYPE_SYS_LOG;
    if(strpos($methodName, "user_") === 0) {
      $methodName = substr($methodName, strlen("user_"));
      $type = self::TYPE_USER_LOG;
    }
    if($methodName == "success") {
      Cms::success($arguments[0]);
      $methodName = "info";
      $type = self::TYPE_USER_LOG;
    }
    if($methodName == "mail") {
      $methodName = "info";
      $type = self::TYPE_MAIL_LOG;
    }
    self::writeLog($methodName, $arguments[0], $type);
  }

  /**
   * Write message to Monolog and add Cms message.
   *
   * @param  string  $level
   * @param  string  $message
   * @return void
   */
  private static function writeLog($level, $message, $type = self::TYPE_SYS_LOG) {
    $logger = self::getMonolog($type);
    $monologLevel = self::parseLevel($level);
    $logger->{'add'.$level}($message);
    if(!Cms::isSuperUser()) return;
    switch($monologLevel) {
      case MonologLogger::DEBUG:
      case MonologLogger::INFO:
      return;
      case MonologLogger::NOTICE:
      case MonologLogger::WARNING:
      Cms::{$level}($message);
      return;
      default:
      Cms::error($message);
    }
  }

  /**
   * Parse the string level into a Monolog constant.
   *
   * @param  string  $level
   * @return int
   *
   * @throws Exception
   */
  private static function parseLevel($level) {
      if(isset(self::$levels[$level])) return self::$levels[$level];
      throw new Exception(sprintf(_('Invalid log level %s'), $level));
  }

  /**
   * Get or create (if not exists) monolog instance for given type.
   *
   * @param  string         $type self::TYPE_SYS_LOG or self::TYPE_MAIL_LOG
   * @return Monolog\Logger
   */
  private static function getMonolog($type) {
    if(!is_null(self::${"monolog$type"})) return self::${"monolog$type"};
    $logger = new MonologLogger(self::LOGGER_NAME."_$type");
    $logger->pushProcessor("IGCMS\Core\Logger::appendDebugTrace");
    self::pushHandlers($logger, $type);
    self::${"monolog$type"} = $logger;
    return $logger;

  }

  // TODO doc
  private static function pushHandlers(MonologLogger $logger, $logType) {
    $logFile = LOG_FOLDER."/".date("Ymd").".$logType.log";
    $formatter = $logType != self::TYPE_MAIL_LOG
      ? new LineFormatter(self::LOG_FORMAT)
      : new LineFormatter(self::EMAIL_FORMAT);

    $streamHandler = new StreamHandler($logFile, MonologLogger::DEBUG);
    $streamHandler->setFormatter($formatter);
    $logger->pushHandler($streamHandler);

    foreach(array("CRITICAL", "ALERT", "EMERGENCY") as $type) {
      $mailHandler = new NativeMailerHandler(
        self::EMAIL_ALERT_TO,
        "IGCMS $type at ".DOMAIN,
        self::EMAIL_ALERT_FROM,
        constant("Monolog\Logger::$type"),
        false);
      $mailHandler->setFormatter($formatter);
      $logger->pushHandler($mailHandler);
    }

    if(CMS_DEBUG) {
      $chromeHandler = new ChromePHPHandler(MonologLogger::DEBUG, false);
      $logger->pushHandler($chromeHandler);
    }
  }

  /**
   * Append backtrace to extra field in given log record.
   *
   * @param  Array  $record
   * @return Array
   */
  public static function appendDebugTrace(Array $record) {
    if(!CMS_DEBUG && $record['level'] < MonologLogger::CRITICAL) return $record;
    $record["extra"]["backtrace"] = array_slice(debug_backtrace(), 6);
    return $record;
  }
}

?>