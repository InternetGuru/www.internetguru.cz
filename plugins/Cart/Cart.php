<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use IGCMS\Core\ResourceInterface;
use Imagick;
use SplObserver;
use SplSubject;

/**
 * Class Cart
 * @package IGCMS\Plugins
 */
class Cart extends Plugin implements SplObserver, ResourceInterface {

  const PLUGIN_NAME = 'cart';
  const OK_PARAM = self::PLUGIN_NAME.'-ok';
  const DEL_PARAM = self::PLUGIN_NAME.'-del';
  const STATUS_IMG = LIB_DIR.'/'.self::PLUGIN_NAME.'/status.png';

  /**
   * @var array
   */
  private $vars;

  /**
   * Cart constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 90);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_PREINIT) {
      return;
    }
    try {
      $this->loadVariables();
      $this->createVariables();
      // add to cart
      if (!empty($_POST)) {
        $this->addToCart();
      }
      // after submitting form delete cookies
      if (getCurLink(true) == "{$this->vars['formpage']}?cfok={$this->vars['formid']}") {
        $this->removeCartCookie();
      }
      // show cart-add success message
      if (array_key_exists(self::OK_PARAM, $_GET)) {
        $id = $_GET[self::OK_PARAM];
        $this->validateProductId($id);
        Cms::success("{$this->vars['success']}. <a href='{$this->vars['formpage']}'>{$this->vars['gotoorder']}</a>.");
        return;
      }
      // show cart-delete success message
      if (array_key_exists(self::DEL_PARAM, $_GET)) {
        $this->removeCartCookie();
        Cms::success($this->vars['deletesuccess']);
        return;
      }
    } catch (Exception $e) {
      Logger::error($e->getMessage());
    }
  }

  private function loadVariables () {
    $cfg = $this->getXML();
    /** @var DOMElementPlus $var */
    foreach ($cfg->getElementsByTagName("var") as $var) {
      $id = $var->getRequiredAttribute('id');
      $childElements = $var->childElementsArray;
      if (!count($childElements)) {
        $this->vars[$id] = $var->nodeValue;
        continue;
      }
      $this->vars[$id] = $var;
    }
  }

  private function createVariables () {
    $toSet = [
      'delete' => $this->vars['delete'],
      'delete-href' => '?'.self::DEL_PARAM,
      'status-img' => self::STATUS_IMG,
    ];
    /** @var DOMElementPlus $button */
    $button = $this->vars['button']->processVariables($this->vars, [], true);
    $button = $button->processVariables($toSet, [], true);
    Cms::setVariable('button', $button);
    foreach ($toSet as $name => $value) {
      Cms::setVariable($name, $value);
    }
    $this->createOrderVariable();
  }

  private function createOrderVariable () {
    $summary = "";
    $summaryProduct = $this->vars['summary-product'];
    $summarySeparator = $this->vars['summary-separator'];
    $summaryTotal = $this->vars['summary-total'];
    $totalPrice = null;
    $cookie = $this->getCartCookie();
    $notice = false;
    foreach ($cookie as $id => $value) {
      try {
        $this->validateProductId($id);
      } catch (Exception $e) {
        $notice = true;
        continue;
      }
      $productData = HTMLPlusBuilder::getIdToData($id);
      if (is_null($productData) || !array_key_exists('price', $productData)) {
        Logger::user_warning(sprintf(_('Product %s missing data-price attribute')));
        continue;
      }
      $vars = [
        'product-id' => $id,
        'name' => HTMLPlusBuilder::getIdToHeading($id),
        'price' => $productData['price'],
        'currency' => $this->vars['currency'],
        'summary-ammount' => $value,
      ];
      $summary .= replaceVariables($summaryProduct, $vars)."\n";
      $totalPrice = (int) $totalPrice + (int) $vars['price'] * (int) $value;
    }
    if (strlen($summary)) {
      $summary .= "$summarySeparator\n";
      $summary .= replaceVariables(
          $summaryTotal,
          ['summary-total' => (string) $totalPrice, 'currency' => $this->vars['currency']]
        )."\n";
      Cms::setVariable('order', $summary);
    }
    if ($notice) {
      Logger::user_notice(_('Some products from your order are no longer available'));
    }
  }

  /**
   * @throws Exception
   */
  private function addToCart () {
    if (!array_key_exists('plugin', $_POST)
      || !array_key_exists('id', $_POST)
      || !array_key_exists('count', $_POST)
    ) {
      return;
    }
    if ($_POST['plugin'] != self::PLUGIN_NAME) {
      return;
    }
    $id = $_POST['id'];
    $this->validateProductId($id);
    $count = (int) ($_POST['count']);
    // TODO validate min / max
    if (!is_numeric($count) || $count < 1) {
      throw new Exception(sprintf(_('Invalid count %s at product id %s'), $_POST['count'], $id));
    }
    $cookieId = self::PLUGIN_NAME.'-'.$id;
    if (isset($_COOKIE[$cookieId])) {
      $count += (int) ($_COOKIE[$cookieId]);
    }
    setcookie($cookieId, $count, time() + (86400 * 30), "/"); // 30 days
    redirTo(buildLocalUrl(['path' => getCurLink(), 'query' => self::OK_PARAM."=$id"], true));
  }

  /**
   * @param string $id
   * @throws Exception
   */
  private function validateProductId ($id) {
    if (!is_null(HTMLPlusBuilder::getIdToLink($id))) {
      return;
    }
    throw new Exception(sprintf(_('Product id %s not found'), $id));
  }

  /**
   * @return array
   */
  private static function getCartCookie () {
    $product = [];
    foreach ($_COOKIE as $name => $value) {
      if (strpos($name, self::PLUGIN_NAME.'-') !== 0) {
        continue;
      }
      $id = substr($name, strlen(self::PLUGIN_NAME) + 1);
      $product[$id] = $value;
    }
    return $product;
  }

  private function removeCartCookie () {
    foreach ($_COOKIE as $name => $value) {
      if (strpos($name, self::PLUGIN_NAME.'-') !== 0) {
        continue;
      }
      unset($_COOKIE[$name]);
      setcookie($name, null, -1, '/');
    }
  }

  /**
   * @param string $filePath
   * @return bool
   */
  public static function isSupportedRequest ($filePath) {
    return $filePath == self::STATUS_IMG;
  }

  /**
   * @return void
   */
  public static function handleRequest () {
    $cfg = self::getXML();
    if (empty(self::getCartCookie())) {
      $src = $cfg->getElementById('status-img-empty')->nodeValue;
    } else {
      $src = $cfg->getElementById('status-img-full')->nodeValue;
    }
    $src = findFile($src);
    $im = new Imagick($src);
    header("Content-type: {$im->getFormat()}");
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $im->getimageblob();
    exit();
  }
}

?>
