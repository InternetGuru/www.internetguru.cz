<?php


#TODO:
# - CMS_DEBUG: save all to debug log file

namespace IGCMS\Core;

use IGCMS\Core\Cms;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\IntrospectionProcessor;

// TODO doc
class Logger {

  const TYPE_LOG  = "log";
  const TYPE_EMAIL = "mail";

  const EMAIL_ALERT_TO = "pavel.petrzela@internetguru.cz jiri.pavelka@internetguru.cz";
  const EMAIL_ALERT_FROM = "no-reply@internetguru.cz";

  /**
   * Monolog logger name, e.g. IGCMS_log.
   * Can be used in LOG_FORMAT as %channel%.
   */
  const LOGGER_NAME = "IGCMS";

  /**
   * Log format, for both TYPE_LOG and TYPE_EMAIL.
   */
  const LOG_FORMAT = "[%datetime%] %level_name%: %message% %extra%\n";

  const EMAIL_FORMAT = "[%datetime%]: %message%\n";

  /**
   * Monolog log logger instance.
   *
   * @var Monolog\Logger
   */
  private static $monologlog = null;

  /**
   * Monolog mail logger instance.
   *
   * @var Monolog\Logger
   */
  private static $monologmail = null;

  /**
   * The Log levels.
   *
   * @see   http://tools.ietf.org/html/rfc5424#section-6.2.1
   * @var array
   */
  private static $levels = [
    'debug'     => MonologLogger::DEBUG,
    'info'      => MonologLogger::INFO,
    'notice'    => MonologLogger::NOTICE,
    'warning'   => MonologLogger::WARNING,
    'error'     => MonologLogger::ERROR,
    'critical'  => MonologLogger::CRITICAL,
    'alert'     => MonologLogger::ALERT,
    'emergency' => MonologLogger::EMERGENCY,
  ];

  /**
   * Log an debug message to the log.
   *
   * @param  string $message
   * @return void
   */
  public static function debug($message) {
    return self::writeLog(__FUNCTION__, $message);
  }

  /**
   * Log an info message to the log.
   *
   * @param  string $message
   * @return void
   */
  public static function info($message) {
    return self::writeLog(__FUNCTION__, $message);
  }

  /**
   * Log an notice message to the log.
   *
   * @param  string $message
   * @return void
   */
  public static function notice($message) {
    return self::writeLog(__FUNCTION__, $message);
  }

  /**
   * Log an warning message to the log.
   *
   * @param  string $message
   * @return void
   */
  public static function warning($message) {
    return self::writeLog(__FUNCTION__, $message);
  }

  /**
   * Log an error message to the log.
   *
   * @param  string $message
   * @return void
   */
  public static function error($message) {
    return self::writeLog(__FUNCTION__, $message);
  }

  /**
   * Log an critical message to the log.
   *
   * @param  string $message
   * @return void
   */
  public static function critical($message) {
    return self::writeLog(__FUNCTION__, $message);
  }

  /**
   * Log an alert message to the log.
   *
   * @param  string $message
   * @return void
   */
  public static function alert($message) {
    return self::writeLog(__FUNCTION__, $message);
  }

  /**
   * Log an emergency message to the log.
   *
   * @param  string $message
   * @return void
   */
  public static function emergency($message) {
    return self::writeLog(__FUNCTION__, $message);
  }

  /**
   * Log an mail message to the mail log.
   *
   * @param  string $message
   * @return void
   */
  public static function mail($message) {
    return self::writeLog('info', $message, self::TYPE_EMAIL);
  }

  /**
   * Write message to Monolog and add Cms message.
   *
   * @param  string  $level
   * @param  string  $message
   * @return void
   */
  private static function writeLog($level, $message, $type = self::TYPE_LOG) {
    $logger = self::getMonolog($type);
    $monologLevel = self::parseLevel($level);
    $logger->{'add'.$level}($message);
    if(!Cms::isSuperUser()) return;
    switch($monologLevel) {
      case MonologLogger::DEBUG:
      case MonologLogger::INFO:
      return;
      case MonologLogger::NOTICE:
      Cms::addMessage($message, Cms::MSG_INFO);
      return;
      case MonologLogger::WARNING:
      Cms::addMessage($message, Cms::MSG_WARNING);
      return;
      default:
      Cms::addMessage($message, Cms::MSG_ERROR);
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
   * @param  string         $type self::TYPE_LOG or self::TYPE_EMAIL
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
  private static function pushHandlers(MonologLogger $logger, $type) {
    $logFile = LOG_FOLDER."/".date("Ymd").".$type";
    $formatter = $type == self::TYPE_LOG ? new LineFormatter(self::LOG_FORMAT) : new LineFormatter(self::EMAIL_FORMAT);
    $streamHandler = new StreamHandler($logFile, MonologLogger::DEBUG);
    $streamHandler->setFormatter($formatter);
    $logger->pushHandler($streamHandler);
    $mailHandler = new NativeMailerHandler(
      self::EMAIL_ALERT_TO,
      "IGCMS ".DOMAIN." error!",
      self::EMAIL_ALERT_FROM,
      MonologLogger::CRITICAL
    , false);
    $mailHandler->setFormatter($formatter);
    $logger->pushHandler($mailHandler);
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