<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class TreePresenter
 * @package IGCMS\Plugins
 */
class TreePresenter extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  private $getParam = 'presentation';
  private $allValue = 'all';

  /**
   * @param SplSubject|Plugins $subject <p>
   */
  public function update (SplSubject $subject) {
    if (!isset($_GET[$this->getParam])) {
      $subject->detach($this);
      return;
    }
    if ($subject->getStatus() !== STATUS_INIT) {
      return;
    }
    if ($_GET[$this->getParam] == $this->allValue) {
      $subject->detach($subject->getObserver('ContentBalancer'));
    }
  }

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent (HTMLPlus $content) {
    /** @var HtmlOutput $outputStrategy */
    $outputStrategy = Cms::getOutputStrategy();
    $outputStrategy->addJsFile($this->pluginDir.'/lib/dist/js/jquery-3.3.1.min.js', 10, 'body', true, null, false, false);
    $outputStrategy->addJsFile($this->pluginDir.'/lib/dist/js/outliner.min.js', 10, 'body', true, null, false, false);
    $outputStrategy->addJsFile($this->pluginDir.'/lib/dist/js/pdfmake.min.js', 10, 'body', true, null, false, false);
    $outputStrategy->addJsFile($this->pluginDir.'/lib/dist/js/vfs_fonts.js', 10, 'body', true, null, false, false);
    $outputStrategy->addJsFile($this->pluginDir.'/lib/dist/js/raphael.js', 10, 'body', true, null, false, false);
    $outputStrategy->addJsFile($this->pluginDir.'/lib/dist/js/Treant.js', 10, 'body', true, null, false, false);
    $outputStrategy->addJsFile($this->pluginDir.'/lib/dist/js/TreePresenter.js', 10, 'body', true, null, false, false);
    $outputStrategy->addCssFile($this->pluginDir.'/lib/dist/css/style.css', 'all', 100);
    $outputStrategy->addCssFile('https://use.fontawesome.com/releases/v5.0.10/css/all.css', 'all', 100);
  }
}
