<?php

namespace IGCMS\Core;
use Cz\Git\GitException;
use Cz\Git\GitRepository;
use Exception;

/**
 * Class Git
 * @package IGCMS\Core
 */
class Git extends GitRepository {
  /**
   * @var GitRepository|null
   */
  private static $gitRepository = null;

  /**
   * @return Git|GitRepository|null
   * @throws GitException
   */
  public static function Instance () {
    if (!is_null(self::$gitRepository)) {
      return self::$gitRepository;
    }
    self::$gitRepository = new self(USER_FOLDER);
    return self::$gitRepository;
  }

  /**
   * @param $filename
   * @param string|null $message
   * @param string|null $author
   * @param null $email
   * @return bool
   */
  public function commitFile ($filename, $message=null, $author=null, $email=null) {
    if (strpos($filename, USER_FOLDER) !== 0) {
      return false;
    }
    if (is_null($message)) {
      try {
        $message = get_caller_class(2);
      } catch (Exception $exc) {
        $message = 'IGCMS commit';
      }
    }
    if (is_null($email)) {
      $email = 'igcms@commit.com';
    }
    if (is_null($author)) {
      $author = Cms::getLoggedUser();
    }
    $author .= " <$email>";
    try {
      self::$gitRepository->addFile($filename);
      self::$gitRepository->commit($message, ['--author' => $author]);
      return true;
    } catch (Exception $exc) {
      Logger::error(sprintf(_('Unable to commit file %s: %s'), $filename, $exc->getMessage()));
      return false;
    }
  }
}
