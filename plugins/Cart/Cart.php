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
  const OK_PARAM = self::PLUGIN_NAME.'-add';
  const DEL_PARAM = self::PLUGIN_NAME.'-del';

  const PLUGIN_DIR = PLUGINS_DIR.'/Cart';
  const CART_IMG = self::PLUGIN_DIR.'/cart.png';
  const CART_FULL_IMG = self::PLUGIN_DIR.'/cart-full.png';

  /**
   * @var array
   */
  private $vars;

  /**
   * Cart constructor.
   * @param Plugins|SplSubject $s
   * @throws \ReflectionException
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
      // get link from id (without fragment)
      $link = HTMLPlusBuilder::getIdToLink($this->vars['formpage']['value']);
      $link = substr($link, 0, strpos($link, "#"));
      // after submitting form delete cookies
      if (get_link(true) == "$link?cfok={$this->vars['formid']['value']}") {
        $this->removeCartCookie();
      }
      $this->createVariables();
      // add to cart
      if (!empty($_POST)) {
        $this->addToCart();
      }
      // show cart-add success message
      if (array_key_exists(self::OK_PARAM, $_GET)) {
        $okParam = $_GET[self::OK_PARAM];
        $this->validateProductId($okParam);
        Cms::success("{$this->vars['success']['value']}. <a href='{$this->vars['formpage']['value']}'>{$this->vars['gotoorder']['value']}</a>.");
        return;
      }
      // show cart-delete success message
      if (array_key_exists(self::DEL_PARAM, $_GET)) {
        $this->removeCartCookie();
        Cms::success($this->vars['deletesuccess']['value']);
        return;
      }
    } catch (Exception $exc) {
      Logger::error($exc->getMessage());
    }
  }

  /**
   * @throws Exception
   */
  private function loadVariables () {
    $cfg = self::getXML();
    /** @var DOMElementPlus $var */
    foreach ($cfg->getElementsByTagName("var") as $var) {
      $varId = $var->getRequiredAttribute('id');
      $childElements = $var->childElementsArray;
      if (!count($childElements)) {
        $this->vars[$varId] = [
          "value" => $var->nodeValue,
          "cacheable" => true,
        ];
        continue;
      }
      $this->vars[$varId] = [
        "value" => $var,
        "cacheable" => true,
      ];
    }
  }

  /**
   * @throws Exception
   */
  private function createVariables () {
    $toSet = [
      'delete' => [
        "value" => $this->vars['delete']['value'],
        "cacheable" => true,
      ],
      'currency' => [
        "value" => $this->vars['currency']['value'],
        "cacheable" => true,
      ],
      'delete-href' => [
        "value" => '?'.self::DEL_PARAM,
        "cacheable" => true,
      ],
      'status-img' => [
        "value" => self::CART_IMG,
        "cacheable" => true,
      ],
    ];
    /** @var DOMElementPlus $button */
    $button = $this->vars['button']['value']->processVariables($this->vars, [], true);
    $button = $button->processVariables($toSet, [], true);
    Cms::setVariable('button', $button);
    foreach ($toSet as $name => $var) {
      Cms::setVariable($name, $var['value'], $var['cacheable']);
    }
    $this->createOrderVariable();
  }

  /**
   * @throws Exception
   */
  private function createOrderVariable () {
    $summary = "";
    $summaryProduct = $this->vars['summary-product']['value'];
    $summarySeparator = $this->vars['summary-separator']['value'];
    $summaryTotal = $this->vars['summary-total']['value'];
    $totalPrice = null;
    $cookie = self::getCartCookie();
    $notice = false;
    foreach ($cookie as $cookieId => $value) {
      try {
        $this->validateProductId($cookieId);
      } catch (Exception $exc) {
        $notice = true;
        continue;
      }
      $productData = HTMLPlusBuilder::getIdToData($cookieId);
      if (is_null($productData) || !array_key_exists('price', $productData)) {
        Logger::user_warning(sprintf(_('Product %s missing data-price attribute')));
        continue;
      }
      $vars = [
        'product-id' => [
          "value" => $cookieId,
          "cacheable" => true,
        ],
        'name' => [
          "value" => HTMLPlusBuilder::getIdToHeading($cookieId),
          "cacheable" => true,
        ],
        'price' => [
          "value" => $productData['price'],
          "cacheable" => true,
        ],
        'currency' => [
          "value" => $this->vars['currency']['value'],
          "cacheable" => true,
        ],
        'summary-ammount' => [
          "value" => $value,
          "cacheable" => true,
        ],
      ];
      $summary .= replace_vars($summaryProduct, $vars)."\n";
      $totalPrice = (int) $totalPrice + (int) $vars['price']['value'] * (int) $value;
    }
    if (strlen($summary)) {
      $summary .= "$summarySeparator\n";
      $summary .= replace_vars(
          $summaryTotal,
          [
            'summary-total' => [
              "value" => (string) $totalPrice,
              "cacheable" => false,
            ],
            'currency' => [
              "value" => $this->vars['currency']['value'],
              "cacheable" => true,
            ]
          ]
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
    $this->validateProductId($_POST['id']);
    $count = (int) ($_POST['count']);
    if (!is_numeric($count) || $count < 1 || $count > 99) {
      throw new Exception(sprintf(_('Invalid count %s at product id %s'), $_POST['count'], $_POST['id']));
    }
    $cookieId = self::PLUGIN_NAME.'-'.$_POST['id'];
    if (isset($_COOKIE[$cookieId])) {
      $count += (int) ($_COOKIE[$cookieId]);
    }
    setcookie($cookieId, $count, time() + (86400 * 30), "/"); // 30 days
    $curQuery = get_query(false, false, ["q", self::OK_PARAM]);
    $query = ($curQuery == "" ? "" : $curQuery."&").self::OK_PARAM."=".$_POST['id'];
    redir_to(build_local_url(['path' => get_link(), 'query' => $query], true));
  }

  /**
   * @param string $id
   * @throws Exception
   */
  private function validateProductId ($id) {
    if (is_null(HTMLPlusBuilder::getIdToLink($id))) {
      throw new Exception(sprintf(_('Product id %s not found'), $id));
    }
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
      $productId = substr($name, strlen(self::PLUGIN_NAME) + 1);
      $product[$productId] = $value;
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
    return $filePath == self::CART_IMG;
  }

  /**
   * @return void
   * @throws Exception
   */
  public static function handleRequest () {
    if (empty(self::getCartCookie())) {
      $src = self::CART_IMG;
    } else {
      $src = self::CART_FULL_IMG;
    }
    $src = find_file($src);
    $imagick = new Imagick($src);
    header("Content-type: {$imagick->getFormat()}");
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $imagick->getImageBlob();
    exit();
  }
}
