<?php

class Basket extends Plugin implements SplObserver {

  private $cfg;
  private $vars = array();

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
    try {
      // load config
      $this->cfg = $this->getDOMPlus();
      $products = $this->loadProducts();
      if(!count($products)) {
        $this->subject->detach($this);
        return;
      }
      $templates = $this->loadTemplates();
      $this->loadVariables();
      // show success message
      if(isset($_GET["btok"]) && isset($products[$_GET["btok"]])) {
        Cms::addMessage($this->vars['success'], Cms::MSG_SUCCESS);
        $orderLink = '<a href="'.$this->vars['formpage'].'">'.$this->vars['gotoorder'].'</a>';
        Cms::addMessage($orderLink, Cms::MSG_SUCCESS);
      }
      // save post data
      if($this->isPost()) $this->processPost();
      // create product variables
      $this->createProductVars($templates, $products);
      // fill order form
      if(getCurLink() == $this->vars['formpage']) $this->createFormVar($products);
      // create basket var
      $this->createBasketVar();
    } catch(Exception $e) {
      Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
    }
  }

  private function createBasketVar() {
    if(getCurLink() == $this->vars['formpage']) return;
    $doc = new DOMDocumentPlus();
    $wrapper = $doc->appendChild($doc->createElement('div'));
    $wrapper->setAttribute('class', 'basket-wrapper');
    $a = $wrapper->appendChild($doc->createElement('a'));
    $a->setAttribute('href', $this->vars['formpage']);
    $a->nodeValue = 'Přejít do košíku';
    Cms::setVariable('button', $doc);
  }

  private function createFormVar(Array $products) {
    $var = "";
    $price = null;
    foreach($_COOKIE as $name => $value) {
      if(strpos($name, 'basket-') !== 0) continue;
      $id = substr($name, 7);
      if(!isset($products[$id])) continue;
      $product = $products[$id];
      $name = isset($product['name']) ? $product['name'] : $id;
      $var .= "– ".$name->documentElement->nodeValue;
      if(isset($product['price'])) $var .= " (".$product['price-full'].")";
      $var .= " … ${value} ks";
      $var .= "\n";
      $price = (int)$price + (int)$product['price']->documentElement->nodeValue * (int)$value;
    }
    if(!is_null($price))
      $var .= "=====\n".$this->vars['totalprice']." ${price} ${product['price-currency']}";
    if(strlen($var)) Cms::setVariable('order', $var);
  }

  private function processPost() {
    $id = 'basket-'.$_POST['id'];
    $cnt = (int)($_POST['cnt']);
    if(isset($_COOKIE[$id])) $cnt += (int)($_COOKIE[$id]);
    setcookie($id, $cnt, time() + (86400 * 30), "/"); // 30 days
    redirTo(buildLocalUrl(array('path' => getCurLink(), 'query' => 'btok='.$_POST['id'])));
  }

  private function isPost() {
    return isset($_POST['basket']) && isset($_POST['id']) && strlen($_POST['id'])
      && isset($_POST['cnt']) && strlen($_POST['cnt']);
  }

  private function createProductVars(Array $templates, Array $products) {
    foreach($templates as $tplName => $tpl) {
      foreach($products as $product) {
        $var = $this->modifyTemplate($tpl, $product);
        Cms::setVariable("$tplName-".$product['id'], $var);
      }
    }
  }

  private function loadVariables() {
    foreach($this->cfg->getElementsByTagName("var") as $var) {
      if(!$var->hasAttribute("id"))
        throw new Exception(_("Element var missing attribute id"));
      $this->vars[$var->getAttribute('id')] = $var->nodeValue;
    }
  }

  private function loadTemplates() {
    $templates = array();
    foreach($this->cfg->getElementsByTagName("template") as $tpl) {
      if(!$tpl->hasAttribute("id"))
        throw new Exception(_("Element template missing attribute id"));
      $templates[$tpl->getAttribute("id")] = $tpl;
    }
    if(!count($templates)) throw new Exception(_("Template element(s) not found"));
    return $templates;
  }

  private function loadProducts() {
    $products = array();
    foreach($this->cfg->getElementsByTagName("product") as $product) {
      if(!$product->hasAttribute("id"))
        throw new Exception(_("Element product missing attribute id"));
      $attrs = array();
      $id = $product->getAttribute("id");
      $attrs["id"] = $id;
      foreach($product->childNodes as $attr) {
        if($attr->nodeType != XML_ELEMENT_NODE) continue;
        $vname = strtolower($attr->nodeName);
        $doc = new DOMDocumentPlus();
        $doc->appendChild($doc->importNode($attr, true));
        $attrs[$vname] = $doc;
        if($attr->hasAttribute("name")) $attrs["$vname-name"] = $attr->getAttribute("name");
        if($vname == "price" && $attr->hasAttribute("currency")) {  #TODO: default currency?
          $attrs["$vname-currency"] = $attr->getAttribute("currency");
          $attrs["$vname-full"] = $attr->nodeValue." ".$attr->getAttribute("currency");
        }
      }
      /*
      if(!isset($attrs["price"])) {
        Logger::log(sprintf(_("Product id %s missing price"), $id), Logger::LOGGER_WARNING);
        continue;
      }
      */
      $products[$id] = $attrs;
    }
    return $products;
  }

  private function modifyTemplate($template, $product) {
    $tmptpl = clone $template;
    $vars = array_merge($product, array(
      "action" => getCurLink(true),
    ));
    $tmptpl->processVariables($vars, array(), true);
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($tmptpl, true));
    return $doc;
  }

}

?>