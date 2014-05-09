<?php

class Content implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $doc = DOMBuilder::build("Content");
      $old = $doc->getElementsByTagName("Content")->item(0);
      $new = $doc->getElementsByTagName("body")->item(0);
      $doc->replaceChild($new,$old);
      $subject->getCms()->setContent($doc);
    }
  }

}

?>
