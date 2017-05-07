<?php

namespace IGCMS\Plugins;

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
    $s->setPriority($this, 100);
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
   */
  public function modifyContent (HTMLPlus $content) {
    // supported syntax only
    $xml = VENDOR_DIR."/".self::CM_DIR."/mode/xml/xml.js";
    $css = VENDOR_DIR."/".self::CM_DIR."/mode/css/css.js";
    $js = VENDOR_DIR."/".self::CM_DIR."/mode/javascript/javascript.js";
    $html = VENDOR_DIR."/".self::CM_DIR."/mode/htmlmixed/htmlmixed.js";
    $modes = [
      "xml" => [$xml],
      "css" => [$css],
      "javascript" => [$js],
      "htmlmixed" => [$xml, $css, $js, $html],
    ];
    // return if no textarea containing class "codemirror"
    $libs = [];
    $codemirror = false;
    /** @var DOMElementPlus $t */
    foreach ($content->getElementsByTagName("textarea") as $t) {
      $classes = explode(" ", $t->getAttribute("class"));
      if (!in_array("codemirror", $classes)) {
        continue;
      }
      $codemirror = true;
      foreach ($classes as $c) {
        if (array_key_exists($c, $modes)) {
          $libs = array_merge($libs, $modes[$c]);
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
   */
  private function addSources (Array $libs) {
    /** @var HtmlOutput $os */
    $os = Cms::getOutputStrategy();

    $os->addCssFile(VENDOR_DIR."/".self::CM_DIR."/lib/codemirror.css");
    $os->addCssFile(VENDOR_DIR."/".self::CM_DIR."/theme/tomorrow-night-eighties.css");
    $os->addCssFile(VENDOR_DIR."/".self::CM_DIR."/cminit.css");

    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/lib/codemirror.js");
    foreach ($libs as $l) $os->addJsFile($l);
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/keymap/sublime.js");

    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/search/searchcursor.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/search/search.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/search/jump-to-line.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/dialog/dialog.js");
    $os->addCssFile(VENDOR_DIR."/".self::CM_DIR."/addon/dialog/dialog.css");

    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/selection/active-line.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/selection/mark-selection.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/comment/comment.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/edit/closetag.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/fold/foldcode.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/fold/xml-fold.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/edit/matchtags.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/wrap/hardwrap.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/format/formatting.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/display/fullscreen.js");
    $os->addCssFile(VENDOR_DIR."/".self::CM_DIR."/addon/display/fullscreen.css");

    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/cminit.js", 10, "body");
    $os->addJsFile($this->pluginDir.'/'.$this->className.'.js', 10, "body");
    $os->addCssFile($this->pluginDir.'/'.$this->className.'.css');
  }

}

?>
