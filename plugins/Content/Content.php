<?php

class Content implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $doc = $subject->getCms()->getDOMBuilder()->build("Content");
      $old = $doc->getElementsByTagName("Content")->item(0);
      $new = $doc->getElementsByTagName("body")->item(0);
      $doc->replaceChild($new,$old);
      $subject->getCms()->setContent($doc);
    }
  }

}

?>
