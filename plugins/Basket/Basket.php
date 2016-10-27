<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class Basket
 * @package IGCMS\Plugins
 */
class Basket extends Plugin implements SplObserver, ModifyContentStrategyInterface {
  /**
   * @var DOMDocumentPlus
   */
  private $cfg;
  /**
   * @var array
   */
  private $vars = array();
  /**
   * @var array
   */
  private $products = array();
  /**
   * @var array
   */
  private $productVars = array();
  /**
   * @var array
   */
  private $cookieProducts = array();

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent(HTMLPlus $content) {
    $content->processVariables($this->productVars);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update(SplSubject $subject) {
    if(!Cms::isActive()) {
      $subject->detach($this);
      return;
    }
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
      $this->cfg = $this->getXML();
      $this->loadVariables();
      // after submitting form delete cookies
      if(getCurLink(true) == $this->vars['formpage']."?cfok=".$this->vars['formid'])
        $this->removeBasketCookies();
      // delete basket
      if(isset($_GET["btdel"])) {
        $this->removeBasketCookies();
        redirTo(buildLocalUrl(array('path' => getCurLink(), 'query' => 'btdelok'), true));
      }
      $this->products = $this->loadProducts();
      if(!count($this->products)) {
        $this->subject->detach($this);
        return;
      }
      // load product from cookie;
      $this->cookieProducts = $this->getCookieProducts();
      // create product variables
      $this->createProductVars();
      // fill order form
      if(getCurLink() == $this->vars['formpage']) $this->createFormVar();
      // create basket var
      $this->createBasketVar();
      // create basket-empty var
      Cms::setVariable('empty', (count($this->cookieProducts) ? null : ''));
    } catch(Exception $e) {
      Logger::user_warning($e->getMessage());
    }
  }

  private function process() {
    // save post data
    if($this->isPost()) $this->processPost();
    // show success message
    if(isset($_GET["btdelok"])) {
      Logger::user_success($this->vars['deletesucess']);
    }
    if(isset($_GET["btok"]) && isset($this->products[$_GET["btok"]])) {
      $gotoorder = '<a href="'.$this->vars['formpage'].'">'.$this->vars['gotoorder'].'</a>';
      Logger::user_success($this->vars['success']." – $gotoorder");
    }
  }

  /**
   * @return array
   */
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

  private function createFormVar() {
    $summary = "";
    $summaryProduct = $this->vars['summary-product'];
    $summarySeparator = $this->vars['summary-separator'];
    $summaryTotal = $this->vars['summary-total'];
    $price = null;
    foreach($this->cookieProducts as $id => $value) {
      if(!isset($this->products[$id])) continue;
      $product = $this->products[$id];
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

  /**
   * @return bool
   */
  private function isPost() {
    return isset($_POST['id']) && !is_null(Cms::getVariable('validateform-'.$_POST['id']));
  }

  private function createProductVars() {
    $templates = $this->loadTemplates();
    foreach($templates as $tplName => $tpl) {
      foreach($this->products as $product) {
        $keyName = "basket-$tplName-".$product['product-id'];
        $cacheKey = apc_get_key($keyName);
        if(apc_exists($cacheKey)) {
          $doc = new DOMDocumentPlus();
          $doc->loadXML(apc_fetch($cacheKey));
          $this->insertAction($doc);
          $this->productVars[$keyName] = $doc;
          continue;
        }
        $var = $this->modifyTemplate($tpl, $product);
        apc_store_cache($cacheKey, $var->saveXML(), $keyName);
        $this->insertAction($var);
        $this->productVars[$keyName] = $var;
      }
    }
  }

  /**
   * @param DOMDocumentPlus $doc
   */
  private function insertAction(DOMDocumentPlus $doc) {
    $var = array("action" => getCurLink(true) == "" ? "/" : getCurLink(true));
    $doc->processVariables($var, array());
  }

  private function loadVariables() {
    $keyName = "variables";
    $cacheKey = apc_get_key($keyName);
    if(apc_exists($cacheKey)) {
      $this->vars = apc_fetch($cacheKey);
      return;
    }
    /** @var DOMElementPlus $var */
    foreach($this->cfg->getElementsByTagName("var") as $var) {
      $id = $var->getRequiredAttribute('id');
      $value = null;
      if(count($var->childElementsArray)) {
        $doc = new DOMDocumentPlus();
        $doc->appendChild($doc->importNode($var, true));
        $value = $doc;
      } else {
        $value = $var->nodeValue;
      }
      $this->vars[$id] = $value;
    }
    apc_store_cache($cacheKey, $this->vars, $keyName);
  }

  /**
   * @return array
   * @throws Exception
   */
  private function loadTemplates() {
    $templates = array();
    /** @var DOMElementPlus $tpl */
    foreach($this->cfg->getElementsByTagName("template") as $tpl) {
      $id = $tpl->getRequiredAttribute("id");
      $templates[$id] = $tpl;
    }
    if(!count($templates)) throw new Exception(_("Template element(s) not found"));
    return $templates;
  }

  /**
   * @return array
   */
  private function loadProducts() {
    $products = array();
    /** @var DOMElementPlus $product */
    foreach($this->cfg->getElementsByTagName("product") as $product) {
      $product->getRequiredAttribute("id"); // only check
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
        Logger::user_warning(sprintf(_("Product id %s missing price"), $id));
        continue;
      }
      */
      $products[$attrs['product-id']] = $attrs;
    }
    return $products;
  }

  /**
   * @param DOMElementPlus $template
   * @param array $product
   * @return DOMDocumentPlus
   */
  private function modifyTemplate(DOMElementPlus $template, Array $product) {
    $tmptpl = clone $template;
    $vars = array_merge($product, array(
      "currency" => $this->vars['currency'],
    ));
    $tmptpl->processVariables($vars, array(), true);
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($tmptpl, true));
    return $doc;
  }

}

?>