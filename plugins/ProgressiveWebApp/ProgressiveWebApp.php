<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use IGCMS\Core\ResourceInterface;
use SplObserver;
use SplSubject;

/**
 * Class ProgressiveWebApp
 * @package IGCMS\Plugins
 */
class ProgressiveWebApp extends Plugin implements SplObserver, ResourceInterface {
  /**
   * @var string
   */
  const SERVICE_WORKER = "serviceWorker.js";
  const MANIFEST = "manifest.json";

  /**
   * ServiceWorker constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 80);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_POSTPROCESS) {
      return;
    }
    // do not update if uptodate
    if (is_file(self::MANIFEST) && filemtime(self::MANIFEST) == HTMLPlusBuilder::getNewestFileMtime()) {
      return;
    }
    // do not update if cache is outdated
    if (DOMBuilder::isCacheOutdated()) {
      return;
    }
    // load and process variables
    $xml = self::getXML();
    $manifestTemplate = $xml->getElementsByTagName("manifest")[0]->nodeValue;
    $h1id = HTMLPlusBuilder::getLinkToId("");
    // save manifest
    file_put_contents(self::MANIFEST, replace_vars($manifestTemplate, [
      "name" => HTMLPlusBuilder::getIdToHeading($h1id),
      "shortName" => HTMLPlusBuilder::getHeading($h1id),
      "rootUrl" => ROOT_URL,
    ]));
    // add meta
    $outputStrategy = Cms::getOutputStrategy();
    // TODO parametrize theme-color
    $outputStrategy->addMetaElement("theme-color", "#ddd");
    $outputStrategy->addLinkElement(self::MANIFEST, "manifest");
    // append service worker init
    $outputStrategy->addJsFile($this->pluginDir."/".$this->className.".js");
  }

  /**
   * @param string $filePath
   * @return bool
   */
  public static function isSupportedRequest ($filePath) {
    return $filePath === self::SERVICE_WORKER;
  }

  // TODO expire headers?
  public static function handleRequest () {
    header('Content-Type: application/javascript');
    echo file_get_contents(self::SERVICE_WORKER);
    exit;
  }
}
