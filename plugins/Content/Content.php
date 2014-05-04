<?php

class Content implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $subject->getCms()->setContent(new Dom("Content"));
    }
  }

}

?>
