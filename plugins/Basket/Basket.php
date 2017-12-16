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
  private $vars = [];
  /**
   * @var array
   */
  private $products = [];
  /**
   * @var array
   */
  private $productVars = [];
  /**
   * @var array
   */
  private $cookieProducts = [];
  /**
   * @var bool
   */
  private $useCache = true;

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent (HTMLPlus $content) {
    $content->processVariables($this->productVars);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if (!Cms::isActive()) {
      $subject->detach($this);
      return;
    }
    switch ($subject->getStatus()) {
      case STATUS_INIT:
        $this->build();
        break;
      case STATUS_PROCESS:
        $this->process();
        break;
    }
  }

  private function build () {
    if ($this->detachIfNotAttached("HtmlOutput")) {
      return;
    }
    try {
      // load config
      $this->cfg = $this->getXML();
      $this->validateConfigCache();
      $this->loadVariables();
      // after submitting form delete cookies
      if (get_link(true) == $this->vars['formpage']."?cfok=".$this->vars['formid']) {
        $this->removeBasketCookies();
      }
      // delete basket
      if (isset($_GET["btdel"])) {
        $this->removeBasketCookies();
        redir_to(build_local_url(['path' => get_link(), 'query' => 'btdelok'], true));
      }
      $this->products = $this->loadProducts();
      if (!count($this->products)) {
        $this->subject->detach($this);
        return;
      }
      // load product from cookie;
      $this->cookieProducts = $this->getCookieProducts();
      // create product variables
      $this->createProductVars();
      // fill order form
      if (get_link() == $this->vars['formpage']) {
        $this->createFormVar();
      }
      // create basket var
      $this->createBasketVar();
      // create basket-empty var
      Cms::setVariable('empty', (count($this->cookieProducts) ? null : ''));
    } catch (Exception $exc) {
      Logger::user_warning($exc->getMessage());
    }
  }

  private function validateConfigCache () {
    $userConfig = find_file($this->pluginDir."/".$this->className.".xml");
    $adminConfig = find_file($this->pluginDir."/".$this->className.".xml", false);
    $defaultConfig = find_file($this->pluginDir."/".$this->className.".xml", false, false);
    $mtimes = filemtime($userConfig).filemtime($adminConfig).filemtime($defaultConfig);
    $cacheKey = apc_get_key(__FUNCTION__);
    if (!apc_is_valid_cache($cacheKey, $mtimes)) {
      apc_store_cache($cacheKey, $mtimes, $this->pluginDir."/".$this->className.".xml");
      $this->useCache = false;
    }
  }

  private function loadVariables () {
    $keyName = "variables";
    $cacheKey = apc_get_key($keyName);
    if ($this->useApcCache($cacheKey)) {
      $this->vars = apc_fetch($cacheKey);
      return;
    }
    /** @var DOMElementPlus $var */
    foreach ($this->cfg->getElementsByTagName("var") as $var) {
      $id = $var->getRequiredAttribute('id');
      $value = null;
      if (count($var->childElementsArray)) {
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
   * @param $cacheKey
   * @return bool
   */
  private function useApcCache ($cacheKey) {
    return $this->useCache && apc_exists($cacheKey);
  }

  private function removeBasketCookies () {
    foreach ($_COOKIE as $name => $value) {
      if (strpos($name, 'basket-') !== 0) {
        continue;
      }
      unset($_COOKIE[$name]);
      setcookie($name, null, -1, '/');
    }
  }

  /**
   * @return array
   */
  private function loadProducts () {
    $products = [];
    /** @var DOMElementPlus $product */
    foreach ($this->cfg->getElementsByTagName("product") as $product) {
      $product->getRequiredAttribute("id"); // only check
      $attrs = [];
      foreach ($product->attributes as $attrName => $attrNode) {
        $attrs["product-$attrName"] = $attrNode->nodeValue;
      }
      foreach ($product->childNodes as $attr) {
        if ($attr->nodeType != XML_ELEMENT_NODE) {
          continue;
        }
        $vname = strtolower($attr->nodeName);
        if (count($attr->childElementsArray)) {
          $doc = new DOMDocumentPlus();
          $doc->appendChild($doc->importNode($attr, true));
          $attrs[$vname] = $doc;
        } else {
          $attrs[$vname] = $attr->nodeValue;
        }
        foreach ($attr->attributes as $attrName => $attrNode) {
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
   * @return array
   */
  private function getCookieProducts () {
    $p = [];
    foreach ($_COOKIE as $name => $value) {
      if (strpos($name, 'basket-') !== 0) {
        continue;
      }
      $id = substr($name, 7);
      $p[$id] = $value;
    }
    return $p;
  }

  private function createProductVars () {
    $templates = $this->loadTemplates();
    foreach ($templates as $tplName => $tpl) {
      foreach ($this->products as $product) {
        $keyName = "basket-$tplName-".$product['product-id'];
        $cacheKey = apc_get_key($keyName);
        if ($this->useApcCache($cacheKey)) {
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
   * @return array
   * @throws Exception
   */
  private function loadTemplates () {
    $templates = [];
    /** @var DOMElementPlus $tpl */
    foreach ($this->cfg->getElementsByTagName("template") as $tpl) {
      $id = $tpl->getRequiredAttribute("id");
      $templates[$id] = $tpl;
    }
    if (!count($templates)) {
      throw new Exception(_("Template element(s) not found"));
    }
    return $templates;
  }

  /**
   * @param DOMDocumentPlus $doc
   */
  private function insertAction (DOMDocumentPlus $doc) {
    $var = ["action" => get_link(true) == "" ? "/" : get_link(true)];
    $doc->processVariables($var, []);
  }

  /**
   * @param DOMElementPlus $template
   * @param array $product
   * @return DOMDocumentPlus
   */
  private function modifyTemplate (DOMElementPlus $template, Array $product) {
    $tmptpl = clone $template;
    $vars = array_merge(
      $product,
      [
        "currency" => $this->vars['currency'],
      ]
    );
    $tmptpl->processVariables($vars, [], true);
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($tmptpl, true));
    return $doc;
  }

  private function createFormVar () {
    $summary = "";
    $summaryProduct = $this->vars['summary-product'];
    $summarySeparator = $this->vars['summary-separator'];
    $summaryTotal = $this->vars['summary-total'];
    $price = null;
    foreach ($this->cookieProducts as $id => $value) {
      if (!isset($this->products[$id])) {
        continue;
      }
      $product = $this->products[$id];
      $vars = array_merge(
        $product,
        [
          'currency' => $this->vars['currency'],
          'summary-ammount' => $value,
        ]
      );
      $summary .= replace_vars($summaryProduct, $vars)."\n";
      $p = gettype($product['price']) == 'object' ? $product['price']->documentElement->nodeValue : $product['price'];
      $price = (int) $price + (int) $p * (int) $value;
    }
    if (strlen($summary)) {
      $summary .= "$summarySeparator\n";
      if (!is_null($price)) {
        $summary .= replace_vars(
            $summaryTotal,
            ['summary-total' => (string) $price, 'currency' => $this->vars['currency']]
          )."\n";
      }
      Cms::setVariable('order', $summary);
    }
  }

  private function createBasketVar () {
    if (get_link() == $this->vars['formpage']) {
      return;
    }
    $vars = array_merge(
      $this->vars,
      [
        'status' => (count($this->cookieProducts) ? 'basket-full' : 'basket-empty'),
        'title' => (count($this->cookieProducts) ? $this->vars['gotoorder'] : $this->vars['basketempty']),
      ]
    );
    $var = $this->cfg->createElement("var");
    $wrapper = $this->cfg->getElementById('basket-wrapper');
    $wrapper->processVariables($vars, [], true);
    $var->appendChild($wrapper);
    Cms::setVariable('button', $var);
  }

  private function process () {
    // save post data
    if ($this->isPost()) {
      $this->processPost();
    }
    // show success message
    if (isset($_GET["btdelok"])) {
      Logger::user_success($this->vars['deletesucess']);
    }
    if (isset($_GET["btok"]) && isset($this->products[$_GET["btok"]])) {
      $gotoorder = '<a href="'.$this->vars['formpage'].'">'.$this->vars['gotoorder'].'</a>';
      Logger::user_success($this->vars['success']." – $gotoorder");
    }
  }

  /**
   * @return bool
   */
  private function isPost () {
    return isset($_POST['id']) && !is_null(Cms::getVariable('validateform-'.$_POST['id']));
  }

  private function processPost () {
    $id = 'basket-'.$_POST['id'];
    $formValues = Cms::getVariable('validateform-'.$_POST['id']);
    $cnt = (int) ($formValues['cnt-'.$_POST['id']]);
    if (isset($_COOKIE[$id])) {
      $cnt += (int) ($_COOKIE[$id]);
    }
    setcookie($id, $cnt, time() + (86400 * 30), "/"); // 30 days
    redir_to(build_local_url(['path' => get_link(), 'query' => 'btok='.$_POST['id']], true));
  }

}

?>
