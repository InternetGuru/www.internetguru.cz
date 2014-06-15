<?php

class Content implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $doc = $subject->getCms()->buildDOM("Content",true);
      $htmlplus = new HTMLPlus($doc);
      $subject->getCms()->setContent($htmlplus);
    }
  }

}

?>
