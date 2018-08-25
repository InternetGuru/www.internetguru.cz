<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
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
    $xml = self::getXML();
    $themeColor = $xml->getElementById("themeColor")->nodeValue;
    // add meta
    $outputStrategy = Cms::getOutputStrategy();
    $outputStrategy->addMetaElement("theme-color", $themeColor);
    $outputStrategy->addLinkElement(ROOT_URL.self::MANIFEST, "manifest");
    $outputStrategy->addJsFile($this->pluginDir."/".$this->className.".js");
    // do not update if uptodate
    if (is_file(self::MANIFEST) && filemtime(self::MANIFEST) == HTMLPlusBuilder::getNewestFileMtime()) {
      return;
    }
    // do not update if cache is outdated
    if (DOMBuilder::isCacheOutdated()) {
      return;
    }
    // load and process variables
    $manifestTemplate = $xml->getElementById("manifest")->nodeValue;
    $h1id = HTMLPlusBuilder::getLinkToId("");
    $name = $xml->getElementById("name");
    $shortName = $xml->getElementById("shortName");
    if (!is_null($name)) {
      $name = $name->nodeValue;
    } else {
      $name = HTMLPlusBuilder::getIdToHeading($h1id);
    }
    if (!is_null($shortName)) {
      $shortName = $shortName->nodeValue;
    } else {
      $shortName = HTMLPlusBuilder::getHeading($h1id);
    }
    if (mb_strlen($shortName) > 12 && !is_null(Cms::getLoggedUser())) {
      Logger::warning(_("Manifest short_name is longer than 12 characters"));
    }

    // save manifest
    file_put_contents(self::MANIFEST, replace_vars($manifestTemplate, [
      "name" => [
        "value" => $name,
        "cacheable" => false,
      ],
      "shortName" => [
        "value" => $shortName,
        "cacheable" => false,
      ],
      "rootUrl" => [
        "value" => ROOT_URL,
        "cacheable" => false,
      ],
      "themeColor" => [
        "value" => $themeColor,
        "cacheable" => false,
      ],
    ]));
  }

  /**
   * @param string $filePath
   * @return bool
   */
  public static function isSupportedRequest ($filePath) {
    return $filePath === self::SERVICE_WORKER;
  }

  public static function handleRequest () {
    header('Content-Type: application/javascript');
    header('Cache-Control: max-age=0'); // https://stackoverflow.com/questions/41000874/service-worker-expiration
    echo "
    importScripts('/".LIB_DIR."/sw-toolbox.js')
    
    self.toolbox.router.get(
      /^https:\\/\\/[^.]+\\.[^.]+\\.[^./]+(\\/?|\\/[^.?]+)$/,
      self.toolbox.networkFirst,
      {
        cache: {
          name: 'content-cache-v2'
        }
      }
    )
    ";
    exit;
  }
}
