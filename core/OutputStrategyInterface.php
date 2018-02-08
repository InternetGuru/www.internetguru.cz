<?php

namespace IGCMS\Core;

/** @noinspection PhpClassNamingConventionInspection */
/**
 * Interface OutputStrategyInterface
 * @package IGCMS\Core
 */
interface OutputStrategyInterface {

  /**
   * @param HTMLPlus $content
   * @return string
   */
  public function getOutput (HTMLPlus $content);

  /**
   * @param string $filePath
   * @param int $priority
   */
  public function addTransformation ($filePath, $priority = null);

  /**
   * @param string $filePath
   * @param string $rel
   * @param bool $type
   * @param bool $media
   * @param null $ieIfComment
   */
  public function addLinkElement ($filePath, $rel, $type = false, $media = false, $ieIfComment = null);

  /**
   * @param string $nameValue
   * @param string $contentValue
   * @param bool $httpEquiv
   * @param bool $short
   */
  public function addMetaElement ($nameValue, $contentValue, $httpEquiv = false, $short = false);

    /**
   * @param string $filePath
   * @param int $priority
   * @param string $append
   * @param bool $user
   * @param null $ieIfComment
   * @param bool $ifXpath
   */
  public function addJsFile ($filePath, $priority = null, $append = null, $user = null, $ieIfComment = null, $ifXpath = null);

  /**
   * @param string $filePath
   * @param bool $media
   * @param int $priority
   * @param bool $user
   * @param string|null $ieIfComment
   * @param bool $ifXpath
   */
  public function addCssFile ($filePath, $media = null, $priority = null, $user = null, $ieIfComment = null, $ifXpath = null);

  /**
   * @param string $content
   * @param int $priority
   * @param string $append
   */
  public function addJs ($content, $priority = null, $append = null);

}
