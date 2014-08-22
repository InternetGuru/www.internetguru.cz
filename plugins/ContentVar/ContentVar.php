<?php

class ContentVar implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject
  private $content = null;

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->setPriority($this,110);
    }
  }

  public function getContent(HTMLPlus $content) {
    $db = $this->subject->getCms()->getDomBuilder();
    $cfg = $db->buildDOMPlus(PLUGIN_FOLDER ."/". get_class($this) ."/". get_class($this) .".xml");
    foreach($cfg->getElementsByTagName("var") as $var) {
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