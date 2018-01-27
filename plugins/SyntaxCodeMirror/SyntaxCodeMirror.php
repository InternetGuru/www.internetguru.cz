<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class SyntaxCodeMirror
 * @package IGCMS\Plugins
 */
class SyntaxCodeMirror extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  /**
   * @var string
   */
  const CM_DIR = "internetguru/codemirror";

  /**
   * SyntaxCodeMirror constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 30);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() == STATUS_INIT) {
      $this->detachIfNotAttached("HtmlOutput");
      return;
    }
  }

  /**
   * @param HTMLPlus $content
   * @throws Exception
   */
  public function modifyContent (HTMLPlus $content) {
    // supported syntax only
    $xml = VENDOR_DIR."/".self::CM_DIR."/mode/xml/xml.js";
    $css = VENDOR_DIR."/".self::CM_DIR."/mode/css/css.js";
    $jsPath = VENDOR_DIR."/".self::CM_DIR."/mode/javascript/javascript.js";
    $html = VENDOR_DIR."/".self::CM_DIR."/mode/htmlmixed/htmlmixed.js";
    $modes = [
      "xml" => [$xml],
      "css" => [$css],
      "javascript" => [$jsPath],
      "htmlmixed" => [$xml, $css, $jsPath, $html],
    ];
    // return if no textarea containing class "codemirror"
    $libs = [];
    $codemirror = false;
    /** @var DOMElementPlus $textAreaElm */
    foreach ($content->getElementsByTagName("textarea") as $textAreaElm) {
      $classes = explode(" ", $textAreaElm->getAttribute("class"));
      if (!in_array("codemirror", $classes)) {
        continue;
      }
      $codemirror = true;
      foreach ($classes as $class) {
        if (array_key_exists($class, $modes)) {
          $libs = array_merge($libs, $modes[$class]);
          break;
        }
      }
    }
    // add sources and return
    if ($codemirror) {
      $this->addSources($libs);
    }
  }

  /**
   * @param array $libs
   * @throws Exception
   */
  private function addSources (Array $libs) {
    /** @var HtmlOutput $outputStrategy */
    $outputStrategy = Cms::getOutputStrategy();

    $outputStrategy->addCssFile(VENDOR_DIR."/".self::CM_DIR."/lib/codemirror.css");
    $outputStrategy->addCssFile(VENDOR_DIR."/".self::CM_DIR."/theme/tomorrow-night-eighties.css");
    $outputStrategy->addCssFile(VENDOR_DIR."/".self::CM_DIR."/cminit.css");

    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/lib/codemirror.js");
    foreach ($libs as $lib) $outputStrategy->addJsFile($lib);
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/keymap/sublime.js");

    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/search/searchcursor.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/search/search.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/search/jump-to-line.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/dialog/dialog.js");
    $outputStrategy->addCssFile(VENDOR_DIR."/".self::CM_DIR."/addon/dialog/dialog.css");

    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/selection/active-line.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/selection/mark-selection.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/comment/comment.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/edit/closetag.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/fold/foldcode.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/fold/xml-fold.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/edit/matchtags.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/wrap/hardwrap.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/format/formatting.js");
    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/display/fullscreen.js");
    $outputStrategy->addCssFile(VENDOR_DIR."/".self::CM_DIR."/addon/display/fullscreen.css");

    $outputStrategy->addJsFile(VENDOR_DIR."/".self::CM_DIR."/cminit.js", 10, "body");
    $outputStrategy->addJsFile($this->pluginDir.'/'.$this->className.'.js', 10, "body");
    $outputStrategy->addCssFile($this->pluginDir.'/'.$this->className.'.css');
  }

}
