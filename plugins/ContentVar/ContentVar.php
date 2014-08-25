<?php

class ContentVar extends Plugin implements SplObserver, ContentStrategyInterface {
  private $content = null;

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->setPriority($this,110);
    }
  }

  public function getContent(HTMLPlus $content) {
    foreach($this->getDOMPlus()->getElementsByTagName("var") as $var) {
      if(!$var->hasAttribute("id")) throw new Exception ("Missing id in element var");
      $id = $var->getAttribute("id");
      $content->insertVar($id,$var);
    }
    return $content;
  }

  public function getTitle(Array $queries) {
    return $queries;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>