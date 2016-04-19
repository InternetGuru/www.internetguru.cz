<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\ContentStrategyInterface;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Plugin;
use SplObserver;
use SplSubject;

class SyntaxCodeMirror extends Plugin implements SplObserver, ContentStrategyInterface {

  const CM_DIR = "InternetGuru/CodeMirror";

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 100);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_INIT) {
      $this->detachIfNotAttached("HtmlOutput");
      return;
    }
  }

  public function getContent(HTMLPlus $content) {

    // supported syntax only
    $xml = VENDOR_DIR."/".self::CM_DIR."/mode/xml/xml.js";
    $css = VENDOR_DIR."/".self::CM_DIR."/mode/css/css.js";
    $js = VENDOR_DIR."/".self::CM_DIR."/mode/javascript/javascript.js";
    $html = VENDOR_DIR."/".self::CM_DIR."/mode/htmlmixed/htmlmixed.js";
    $modes = array(
      "xml" => array($xml),
      "css" => array($css),
      "javascript" => array($js),
      "htmlmixed" => array($xml, $css, $js, $html),
      );

    // return if no textarea containing class "codemirror"
    $libs = array();
    $codemirror = false;
    foreach($content->getElementsByTagName("textarea") as $t) {
      $classes = explode(" ", $t->getAttribute("class"));
      if(!in_array("codemirror", $classes)) continue;
      $codemirror = true;
      foreach($classes as $c) {
        if(array_key_exists($c, $modes)) {
          $libs = array_merge($libs, $modes[$c]);
          break;
        }
      }
    }

    // add sources and return
    if($codemirror) $this->addSources($libs);
    return $content;
  }

  private function addSources(Array $libs) {
    $os = Cms::getOutputStrategy();

    $os->addCssFile(VENDOR_DIR."/".self::CM_DIR."/lib/codemirror.css");
    $os->addCssFile(VENDOR_DIR."/".self::CM_DIR."/theme/tomorrow-night-eighties.css");
    $os->addCssFile($this->pluginDir.'/SyntaxCodeMirror.css');

    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/lib/codemirror.js");
    foreach($libs as $l) $os->addJsFile($l);
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/keymap/sublime.js");

    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/search/searchcursor.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/search/search.js");
    $os->addJsFile(VENDOR_DIR."/".self::CM_DIR."/addon/search/goto-line.js");
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

    $os->addJsFile($this->pluginDir.'/SyntaxCodeMirror.js', 10, "body");
  }

}

?>
