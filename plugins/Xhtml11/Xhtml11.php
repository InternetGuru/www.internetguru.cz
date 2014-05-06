<?php

class Xhtml11 implements SplObserver, OutputStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $subject->getCms()->setOutputStrategy($this);
    }
  }

  public function output(Cms $cms) {
    #$cms->getContent()->finalize();
    $lang = $cms->getBodyLang();

    // create output DOM

    echo '<' . '?xml version="1.0" encoding="utf-8"?' . '>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
  "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="'.$lang.'" lang="'.$lang.'">
<head>
  <title>' . $cms->getTitle() . '</title>
</head><body>
<h>empty doc</h>
</body>
</html>';

    // add content into output body
    #$cms->getContent()->getDoc();

    // transform body
    #$xsl = DomBuilder::build("Xhtml11","xsl");
    #$proc = new XSLTProcessor();
    #$proc->importStylesheet($xsl);
    #return $proc->transformToDoc()->saveXML();

  }

}

?>
