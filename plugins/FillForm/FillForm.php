<?php

namespace IGCMS\Plugins;

use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Plugin;
use SplObserver;
use SplSubject;

class FillForm extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 100);
  }

  public function update(SplSubject $subject) {}

  public function modifyContent(HTMLPlus $content) {
    foreach($content->getElementsByTagName("form") as $form) {
      if(!$form->hasClass("fillable")) continue;
      $this->fillForm($form);
    }
    return $content;
  }

  private function fillForm(DOMElementPlus $form) {
    #$post = strtolower($form->getAttribute("method")) == "post";
    foreach($form->getElementsByTagName("input") as $input) {
      $value = $this->getRequestData($input->getAttribute("name"));
      #var_dump($input->getAttribute("name"));
      #var_dump($value);
      if(is_null($value)) continue;
      switch($input->getAttribute("type")) {
        case "text":
        case "email":
        case "number":
        case "search":
        //case "hidden":
        $input->setAttribute("value", $value);
        break;
        case "checkbox":
        case "radio":
        if($value == $input->getAttribute("value")
          || (is_array($value) && in_array($input->getAttribute("value"), $value))) {
          $input->setAttribute("checked", "checked");
        } else {
          $input->removeAttribute("checked");
        }
      }
    }
    foreach($form->getElementsByTagName("textarea") as $textarea) {
      $value = $this->getRequestData($textarea->getAttribute("name"));
      if(is_null($value)) continue;
      $textarea->nodeValue = htmlspecialchars($value);
    }
    foreach($form->getElementsByTagName("select") as $select) {
      $value = $this->getRequestData($select->getAttribute("name"));
      if(is_null($value)) continue;
      foreach($select->getElementsByTagName("option") as $option) {
        if($value == $option->getAttribute("value")) $option->setAttribute("selected", "selected");
        else $option->removeAttribute("selected");
      }
    }
  }

  private function getRequestData($name) {
    $name = str_replace("[]", "", $name);
    if(isset($_POST[$name])) return $_POST[$name];
    if(isset($_GET[$name])) return $_GET[$name];
    return null;
  }

}

?>
