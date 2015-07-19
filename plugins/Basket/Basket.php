<?php

class Basket extends Plugin implements SplObserver {

  private $cfg;

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;

    $this->cfg = $this->getDOMPlus();
    $this->createVars();
  }

  private function createVars() {
    $defaultTemplate = $this->cfg->getElementById("template")->nodeValue;
    foreach($this->cfg->getElementsByTagName("product") as $product) {
      if(!$product->hasAttribute("id"))
        throw new Exception(_("Element product missing attribute id"));
      if(!$product->hasAttribute("price"))
        throw new Exception(_("Element product missing attribute price"));
      $template = $product->hasAttribute("template") ? $product->getAttribute("template") : $defaultTemplate;
      $productHtml = $this->modifyTemplate($template, $product->getAttribute("id"), $product->getAttribute("price"));
    }
  }

  private function modifyTemplate($templateName, $id, $price) {
    $vars = array(
      "action" => URI,
      "id" => $id,
      "price" => $price,
    );
    $template = $this->cfg->getElementById($templateName);
    if(is_null($template)) throw new Exception(spritf(_("Template %s does not exist"), $templateName));
  }

}

?>