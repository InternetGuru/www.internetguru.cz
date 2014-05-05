<?php

class Xhtml11 implements SplObserver, OutputStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $subject->getCms()->setOutputStrategy($this);
    }
  }

  public function output(Cms $cms) {
    #getTitle();
    #getLinks();
    #getScripts();
    #return "<pre>".htmlspecialchars($cms->getTitle())."</pre>";

    echo '<' . '?xml version="1.0" encoding="utf-8"?' . '>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs" lang="cs">
<head>
  <title>' . $cms->getTitle() . '</title>
</head>
' . $cms->getContent()->getDoc()->saveXML() . '
</html>';

  }

}

?>
