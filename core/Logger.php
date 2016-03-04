<?php

namespace IGCMS\Core;

use IGCMS\Core\Cms;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\NativeMailerHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger {

  const TYPE_LOG  = "log";
  const TYPE_MAIL = "mail";

  /**
   * Monolog logger name, e.g. IGCMS_log.
   * Can be used in LOG_FORMAT as %channel%.
   */
  const LOGGER_NAME = "IGCMS";

  /**
   * Log format, for both TYPE_LOG and TYPE_MAIL.
   */
  const LOG_FORMAT = "[%datetime%] %level_name%: %message% %extra%\n";

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
    return self::writeLog('info', $message, self::TYPE_MAIL);
  }

  /**
   * Write message to Monolog.
   *
   * @param  string  $level
   * @param  string  $message
   * @return void
   */
  private static function writeLog($level, $message, $type = self::TYPE_LOG) {
    $logger = self::getMonolog($type);
    $monologLevel = self::parseLevel($level);
    $logger->{'add'.$level}($message);
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
   * @param  string         $type self::TYPE_LOG or self::TYPE_MAIL
   * @return Monolog\Logger
   */
  private static function getMonolog($type) {
    if(!is_null(self::${"monolog$type"})) return self::${"monolog$type"};
    $logger = new MonologLogger(self::LOGGER_NAME."_$type");
    $logFile = LOG_FOLDER."/".date("Ymd").".$type";
    $formatter = new LineFormatter(self::LOG_FORMAT);
    $streamHandler = new StreamHandler($logFile, MonologLogger::DEBUG);
    $streamHandler->setFormatter($formatter);
    $logger->pushHandler($streamHandler);
    if($type == self::TYPE_LOG) {
      $mailHandler = new NativeMailerHandler(
        "pavel@petrzela.eu",
        "IGCMS ".DOMAIN." error!",
        "no-reply@internetguru.cz",
        MonologLogger::CRITICAL
      );
      $mailHandler->setFormatter($formatter);
      $logger->pushHandler($mailHandler);
      $logger->pushProcessor("IGCMS\Core\Logger::appendCaller");
    }
    self::${"monolog$type"} = $logger;
    return $logger;
  }

  /**
   * Append caller to extra field in given log record.
   *
   * @param  Array  $record
   * @return Array
   */
  public static function appendCaller(Array $record) {
    $i = 6;
    $callers = debug_backtrace();
    $c = array();
    if(isset($callers[$i]['class'])) $c[] = basename($callers[$i]['class']);
    if(isset($callers[$i]['function'])) $c[] = $callers[$i]['function'];
    if(empty($c)) $record["extra"]["caller"] = "core";
    $record["extra"]["caller"] = implode(".", $c);
    return $record;
  }
}

?>