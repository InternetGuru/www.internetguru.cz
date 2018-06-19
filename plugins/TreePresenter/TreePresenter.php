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
 *
 * Example of real usage in footer.xsl
 * <xsl:element name="a">
 *   <xsl:attribute name="href">
 *     <xsl:value-of select="concat('?tree_presenter#', $contentbalancer-h1id)" />
 *   </xsl:attribute>
 *   <xsl:text>Presentation</xsl:text>
 * </xsl:element>
 *
 * @package IGCMS\Plugins
 */
class TreePresenter extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  private $getParam = 'tree_presenter';

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
    $subject->detach($subject->getObserver('ContentBalancer'));
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
