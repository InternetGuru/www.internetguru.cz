<?php

namespace IGCMS\Core;

use Exception;

/**
 * Class NoFileException
 * @package IGCMS\Core
 */
class NoFileException extends Exception {
  /**
   * NoFileException constructor.
   * @param string $message
   * @param int $code
   * @param Exception|null $previous
   */
  public function __construct ($message, $code = 0, Exception $previous = null) {
    parent::__construct($message, $code, $previous);
  }

}
