<?php

class Basket extends Plugin implements SplObserver, ContentStrategyInterface {

  private $cfg;
  private $vars = array();
  private $productVars = array();
  private $cookieProducts = array();


  public function getContent(HTMLPlus $content) {
    $oldContent = clone $content;
    try {
      $content->processVariables($this->productVars);
    } catch(Exception $e) {
      Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
      #echo $content->saveXML(); die();
      return $oldContent;
    }
    return $content;
  }

  public function update(SplSubject $subject) {
    switch($subject->getStatus()) {
      case STATUS_INIT:
      $this->build();
      break;
      case STATUS_PROCESS:
      $this->process();
      break;
    }
  }

  private function build() {
    if($this->detachIfNotAttached("HtmlOutput")) return;
    try {
      // load config
      $this->cfg = $this->getDOMPlus();
      $this->loadVariables();
      // after submitting form delete cookies
      if(getCurLink(true) == $this->vars['formpage']."?cfok=".$this->vars['formid'])
        $this->removeBasketCookies();
      // delete basket
      if(isset($_GET["btdel"])) {
        $this->removeBasketCookies();
        redirTo(buildLocalUrl(array('path' => getCurLink(), 'query' => 'btdelok'), true));
      }
      $products = $this->loadProducts();
      if(!count($products)) {
        $this->subject->detach($this);
        return;
      }
      $templates = $this->loadTemplates();
      // load product from cookie;
      $this->cookieProducts = $this->getCookieProducts();

      // create product variables
      $this->createProductVars($templates, $products);
      // fill order form
      if(getCurLink() == $this->vars['formpage']) $this->createFormVar($products);
      // create basket var
      $this->createBasketVar();
      // create basket-empty var
      Cms::setVariable('empty', (count($this->cookieProducts) ? null : ''));
    } catch(Exception $e) {
      Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
    }
  }

  private function process() {
    // save post data
    if($this->isPost()) $this->processPost();
    // show success message
    if(isset($_GET["btdelok"])) {
      Cms::addMessage($this->vars['deletesucess'], Cms::MSG_SUCCESS);
    }
    if(isset($_GET["btok"]) && isset($products[$_GET["btok"]])) {
      $gotoorder = '<a href="'.$this->vars['formpage'].'">'.$this->vars['gotoorder'].'</a>';
      Cms::addMessage($this->vars['success']." – $gotoorder", Cms::MSG_SUCCESS);
    }
  }

  private function getCookieProducts() {
    $p = array();
    foreach($_COOKIE as $name => $value) {
      if(strpos($name, 'basket-') !== 0) continue;
      $id = substr($name, 7);
      $p[$id] = $value;
    }
    return $p;
  }

  private function removeBasketCookies() {
    foreach($_COOKIE as $name => $value) {
      if(strpos($name, 'basket-') !== 0) continue;
      unset($_COOKIE[$name]);
      setcookie($name, null, -1, '/');
    }
  }

  private function createBasketVar() {
    if(getCurLink() == $this->vars['formpage']) return;
    $vars = array_merge($this->vars, array(
      'status' => (count($this->cookieProducts) ? 'basket-full' : 'basket-empty'),
      'title' => (count($this->cookieProducts) ? $this->vars['gotoorder'] : $this->vars['basketempty']),
    ));
    $var = $this->cfg->createElement("var");
    $wrapper = $this->cfg->getElementById('basket-wrapper');
    $wrapper->processVariables($vars, array(), true);
    $var->appendChild($wrapper);
    Cms::setVariable('button', $var);
  }

  private function createFormVar(Array $products) {
    $summary = "";
    $summaryProduct = $this->vars['summary-product'];
    $summarySeparator = $this->vars['summary-separator'];
    $summaryTotal = $this->vars['summary-total'];
    $price = null;
    foreach($this->cookieProducts as $id => $value) {
      if(!isset($products[$id])) continue;
      $product = $products[$id];
      $vars = array_merge($product, array(
        'currency' => $this->vars['currency'],
        'summary-ammount' => $value,
      ));
      $summary .= replaceVariables($summaryProduct, $vars)."\n";
      $p = gettype($product['price']) == 'object' ? $product['price']->documentElement->nodeValue : $product['price'];
      $price = (int)$price + (int)$p * (int)$value;
    }
    if(strlen($summary)) {
      $summary .= "$summarySeparator\n";
      if(!is_null($price))
        $summary .= replaceVariables($summaryTotal,
          array('summary-total' => (string)$price, 'currency' => $this->vars['currency']))."\n";
      Cms::setVariable('order', $summary);
    }
  }

  private function processPost() {
    $id = 'basket-'.$_POST['id'];
    $formValues = Cms::getVariable('validateform-'.$_POST['id']);
    $cnt = (int)($formValues['cnt-'.$_POST['id']]);
    if(isset($_COOKIE[$id])) $cnt += (int)($_COOKIE[$id]);
    setcookie($id, $cnt, time() + (86400 * 30), "/"); // 30 days
    redirTo(buildLocalUrl(array('path' => getCurLink(), 'query' => 'btok='.$_POST['id']), true));
  }

  private function isPost() {
    return isset($_POST['id']) && !is_null(Cms::getVariable('validateform-'.$_POST['id']));
  }

  private function createProductVars(Array $templates, Array $products) {
    foreach($templates as $tplName => $tpl) {
      foreach($products as $product) {
        $var = $this->modifyTemplate($tpl, $product);
        $id = "basket-$tplName-".$product['product-id'];
        $this->productVars[$id] = $var;
      }
    }
  }

  private function loadVariables() {
    foreach($this->cfg->getElementsByTagName("var") as $var) {
      if(!$var->hasAttribute("id"))
        throw new Exception(_("Element var missing attribute id"));
      $value = null;
      if(count($var->childElementsArray)) {
          $doc = new DOMDocumentPlus();
          $doc->appendChild($doc->importNode($var, true));
          $value = $doc;
        } else {
          $value = $var->nodeValue;
        }
      $this->vars[$var->getAttribute('id')] = $value;
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
      foreach($product->attributes as $attrName => $attrNode) {
        $attrs["product-$attrName"] = $attrNode->nodeValue;
      }
      foreach($product->childNodes as $attr) {
        if($attr->nodeType != XML_ELEMENT_NODE) continue;
        $vname = strtolower($attr->nodeName);
        if(count($attr->childElementsArray)) {
          $doc = new DOMDocumentPlus();
          $doc->appendChild($doc->importNode($attr, true));
          $attrs[$vname] = $doc;
        } else {
          $attrs[$vname] = $attr->nodeValue;
        }
        foreach($attr->attributes as $attrName => $attrNode) {
          $attrs["$vname-$attrName"] = $attrNode->nodeValue;
        }
      }
      /*
      if(!isset($attrs["price"])) {
        Logger::log(sprintf(_("Product id %s missing price"), $id), Logger::LOGGER_WARNING);
        continue;
      }
      */
      $products[$attrs['product-id']] = $attrs;
    }
    return $products;
  }

  private function modifyTemplate($template, $product) {
    $tmptpl = clone $template;
    $vars = array_merge($product, array(
      "action" => getCurLink(true) == "" ? "/" : getCurLink(true),
      "currency" => $this->vars['currency'],
    ));
    $tmptpl->processVariables($vars, array(), true);
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($tmptpl, true));
    return $doc;
  }

}

?>