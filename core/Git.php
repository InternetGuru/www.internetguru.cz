<?php

namespace IGCMS\Core;
use Cz\Git\GitException;
use Cz\Git\GitRepository;
use Exception;

/**
 * Class Git
 * @package IGCMS\Core
 */
class Git {
  /**
   * @var GitRepository|null
   */
  private $gitRepository = null;

  /**
   * Git constructor.
   * @throws GitException
   * @return GitRepository
   */
  public function __construct () {
    if (!is_null($this->gitRepository)) {
      return $this->gitRepository;
    }
    $this->gitRepository = new GitRepository(USER_FOLDER);
    return $this->gitRepository;
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
      $email = '<igcms@commit.com>';
    }
    if (is_null($author)) {
      $author = Cms::getLoggedUser();
    }
    $author .= " $email";
    try {
      $this->gitRepository->addFile($filename);
      $this->gitRepository->commit($message, ['--author' => $author]);
      return true;
    } catch (Exception $e) {
      Logger::error(sprintf(_('Unable to commit file %s: %s'), $filename, $e->getMessage()));
      return false;
    }
  }
}
