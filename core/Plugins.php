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
    foreach (scandir(PLUGINS_FOLDER) as $p) {
      if (strpos($p, ".") === 0) {
        continue;
      }
      if (!is_dir(PLUGINS_FOLDER."/$p")) {
        continue;
      }
      if (stream_resolve_include_path(PLUGINS_FOLDER."/.$p")) {
        continue;
      }
      if (stream_resolve_include_path(".PLUGIN.$p")) {
        continue;
      }
      $p = "IGCMS\\Plugins\\$p";
      $this->attach(new $p($this));
    }
  }

  /**
   * @param Plugin|SplObserver $observer
   * @param int $priority
   */
  public function attach (SplObserver $observer, $priority = 10) {
    $o = (new \ReflectionClass($observer))->getShortName();
    $this->observers[$o] = $observer;
    if (!array_key_exists($o, $this->observerPriority)) {
      $this->observerPriority[$o] = $priority;
    }
    if ($observer->isDebug()) {
      Logger::notice(sprintf(_("Plugin %s debug mode is enabled"), $o));
    }
  }

  /**
   * @param Plugin|SplObserver $observer
   */
  public function detach (SplObserver $observer) {
    $o = (new \ReflectionClass($observer))->getShortName();
    if (array_key_exists($o, $this->observers)) {
      $this->observers[$o] = null;
    }
    if (array_key_exists($o, $this->observerPriority)) {
      unset($this->observerPriority[$o]);
    }
  }

  public function notify () {
    stableSort($this->observerPriority);
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
   * @param string $pluginName
   * @return bool
   */
  public function isAttachedPlugin ($pluginName) {
    return array_key_exists($pluginName, $this->observers);
  }

  public function printObservers () {
    stableSort($this->observerPriority);
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
    $contentStrategies = [];
    stableSort($this->observerPriority);
    foreach ($this->observerPriority as $key => $p) {
      if (!$this->observers[$key] instanceOf $itf) {
        continue;
      }
      $contentStrategies[$key] = $this->observers[$key];
    }
    return $contentStrategies;
  }

}

?>
