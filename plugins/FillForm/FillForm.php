<?php

class FillForm extends Plugin implements SplObserver, ContentStrategyInterface {

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 100);
  }

  public function update(SplSubject $subject) {}

  public function getContent(HTMLPlus $c) {
    foreach($c->getElementsByTagName("form") as $form) {
      if(!$form->hasClass("fillable")) continue;
      if(strtolower($form->getAttribute("method")) == "post" && empty($_POST)) continue;
      $this->fillForm($form);
    }
    return $c;
  }

  private function fillForm(DOMElementPlus $form) {
    $val = strtolower($form->getAttribute("method")) == "post" ? $_POST : $_GET;
    if(empty($val)) return; // no data for current form
    foreach($form->getElementsByTagName("input") as $input) {
      if($input->getAttribute("type") != "text") continue;
      if(!isset($val[$input->getAttribute("name")])) continue;
      $input->setAttribute("value", $val[$input->getAttribute("name")]);
    }
  }

}

?>
