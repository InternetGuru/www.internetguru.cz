<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class FillForm
 * @package IGCMS\Plugins
 */
class FillForm extends Plugin implements SplObserver, ModifyContentStrategyInterface {

  /**
   * @var string
   */
  private $prefix;

  /**
   * FillForm constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 30);
    $this->prefix = strtolower($this->className)."-";
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_INIT) {
      return;
    }
    foreach (Cms::getAllVariables() as $varId => $formDocVar) {
      $formDoc = $formDocVar["value"];
      if (strpos($varId, "contactform-") !== 0) {
        continue;
      }
      $this->proceedForm($formDoc->documentElement->firstChild);
    }
  }

  /**
   * @param DOMElementPlus $form
   */
  private function proceedForm (DOMElementPlus $form) {
    if (!$form->hasClass("fillable")) {
      return;
    }
    $this->fixForm($form);
    $this->fillForm($form);
  }

  /**
   * @param DOMElementPlus $form
   */
  private function fixForm (DOMElementPlus $form) {
    $div = $form->ownerDocument->createElement("div");
    /** @var DOMElementPlus $input */
    foreach ($form->getElementsByTagName("input") as $input) {
      if ($input->getAttribute("type") != "checkbox") {
        continue;
      }
      $hidden = $form->ownerDocument->createElement("input");
      $hidden->setAttribute("type", "hidden");
      $hidden->setAttribute("value", "1");
      $hidden->setAttribute("name", $this->prefix.$input->getAttribute("name"));
      $div->appendChild($hidden);
    }
    $form->appendChild($div);
  }

  /**
   * @param DOMElementPlus $form
   */
  private function fillForm (DOMElementPlus $form) {
    #$post = strtolower($form->getAttribute("method")) == "post";
    /** @var DOMElementPlus $input */
    foreach ($form->getElementsByTagName("input") as $input) {
      $name = $input->getAttribute("name");
      $value = $this->getRequestData($name);
      #var_dump($input->getAttribute("name"));
      #var_dump($value);
      if (is_null($value)) {
        if (!is_null($this->getRequestData($this->prefix.$name))) {
          $input->removeAttribute("checked");
        }
        continue;
      }
      switch ($input->getAttribute("type")) {
        case "text":
        case "email":
        case "number":
        case "search":
          //case "hidden":
          $input->setAttribute("value", $value);
          break;
        case "checkbox":
        case "radio":
          if ($value == $input->getAttribute("value")
            || (is_array($value) && in_array($input->getAttribute("value"), $value))
          ) {
            $input->setAttribute("checked", "checked");
          }
      }
    }
    /** @var DOMElementPlus $textarea */
    foreach ($form->getElementsByTagName("textarea") as $textarea) {
      $value = $this->getRequestData($textarea->getAttribute("name"));
      if (is_null($value)) {
        continue;
      }
      $textarea->nodeValue = htmlspecialchars($value);
    }
    /** @var DOMElementPlus $select */
    foreach ($form->getElementsByTagName("select") as $select) {
      $value = $this->getRequestData($select->getAttribute("name"));
      if (is_null($value)) {
        continue;
      }
      /** @var DOMElementPlus $option */
      foreach ($select->getElementsByTagName("option") as $option) {
        if ($value == $option->getAttribute("value")) {
          $option->setAttribute("selected", "selected");
        } else {
          $option->removeAttribute("selected");
        }
      }
    }
  }

  /**
   * @param string $name
   * @return string|null
   */
  private function getRequestData ($name) {
    $name = str_replace("[]", "", $name);
    if (isset($_POST[$name])) {
      return $_POST[$name];
    }
    if (isset($_GET[$name])) {
      return $_GET[$name];
    }
    return null;
  }

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent (HTMLPlus $content) {
    foreach ($content->getElementsByTagName("form") as $form) {
      $this->proceedForm($form);
    }
  }

}
