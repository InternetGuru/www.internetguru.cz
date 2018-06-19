<?php

namespace IGCMS\Core;

use Exception;
use SplObserver;
use SplSubject;

# TODO: to static
/**
 * Class Plugins
 * @package IGCMS\Core
 */
class Plugins implements SplSubject {
  /**
   * @var string|null
   */
  private $status = null;
  /**
   * @var SplObserver[]
   */
  private $observers = []; // list of enabled observer (names => Observer)
  /**
   * @var array
   */
  private $observerPriority = [];

  /**
   * Plugins constructor.
   * @throws Exception
   */
  public function __construct () {
    $this->attachPlugins();
  }

  /**
   * @throws Exception
   */
  private function attachPlugins () {
    if (!is_dir(PLUGINS_FOLDER)) {
      throw new Exception(sprintf(_("Missing plugin folder '%s'"), PLUGINS_FOLDER));
    }
    foreach (scandir(PLUGINS_FOLDER) as $plugin) {
      if (strpos($plugin, ".") === 0) {
        continue;
      }
      if (!is_dir(PLUGINS_FOLDER."/$plugin")) {
        continue;
      }
      if (stream_resolve_include_path(PLUGINS_FOLDER."/.$plugin")) {
        continue;
      }
      if (stream_resolve_include_path(".PLUGIN.$plugin")) {
        continue;
      }
      $plugin = "IGCMS\\Plugins\\$plugin";
      $this->attach(new $plugin($this));
    }
  }

  /**
   * @param Plugin|SplObserver $observer
   * @param int $priority
   */
  public function attach (SplObserver $observer, $priority = 50) {
    $oId = (new \ReflectionClass($observer))->getShortName();
    $this->observers[$oId] = $observer;
    if (!array_key_exists($oId, $this->observerPriority)) {
      $this->observerPriority[$oId] = $priority;
    }
    if ($observer->isDebug()) {
      Logger::notice(sprintf(_("Plugin %s debug mode is enabled"), $oId));
    }
  }

  /**
   * @param Plugin|SplObserver $observer
   */
  public function detach (SplObserver $observer) {
    $oId = (new \ReflectionClass($observer))->getShortName();
    if (array_key_exists($oId, $this->observers)) {
      $this->observers[$oId] = null;
    }
    if (array_key_exists($oId, $this->observerPriority)) {
      unset($this->observerPriority[$oId]);
    }
  }

  public function notify () {
    stable_sort($this->observerPriority, SORT_DESC);
    foreach ($this->observerPriority as $key => $value) {
      $this->observers[$key]->update($this);
    }
    $this->status = null;
  }

  /**
   * @return SplObserver[]
   */
  public function getObservers () {
    return $this->observers;
  }

  /**
   * @param $pluginName
   * @return null|SplObserver
   */
  public function getObserver ($pluginName) {
    return isset($this->observers[$pluginName]) ? $this->observers[$pluginName] : null;
  }

  /**
   * @param string $pluginName
   * @return bool
   */
  public function isAttachedPlugin ($pluginName) {
    return array_key_exists($pluginName, $this->observers);
  }

  public function printObservers () {
    stable_sort($this->observerPriority, SORT_DESC);
    print_r($this->observerPriority);
  }

  /**
   * @return string|null
   */
  public function getStatus () {
    return $this->status;
  }

  /**
   * @param string $status
   */
  public function setStatus ($status) {
    if ($this->status === null) {
      $this->status = $status;
    }
  }

  /**
   * @param Plugin|SplObserver $observer
   * @param int $priority
   */
  public function setPriority (SplObserver $observer, $priority) {
    $this->observerPriority[(new \ReflectionClass($observer))->getShortName()] = $priority;
  }

  /**
   * @param string $itf
   * @return Plugins[]|SplObserver[]|GetContentStrategyInterface[]|ModifyContentStrategyInterface[]|FinalContentStrategyInterface[]|ResourceInterface[]|TitleStrategyInterface[]
   */
  public function getIsInterface ($itf) {
    $contentStrats = [];
    stable_sort($this->observerPriority, SORT_DESC);
    foreach ($this->observerPriority as $key => $priority) {
      if (!$this->observers[$key] instanceOf $itf) {
        continue;
      }
      $contentStrats[$key] = $this->observers[$key];
    }
    return $contentStrats;
  }

}
