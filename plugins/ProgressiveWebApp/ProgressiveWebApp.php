<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
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
  const SERVICE_WORKER = "sw.js";
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

    // save manifest
    $h1id = HTMLPlusBuilder::getLinkToId("");
    file_put_contents(self::MANIFEST, '{
  "name": "'.HTMLPlusBuilder::getIdToHeading($h1id).'",
  "short_name": "'.HTMLPlusBuilder::getHeading($h1id).'",
  "display": "minimal-ui",
  "start_url": "'.ROOT_URL.'",
  "theme_color": "#ddd",
  "background_color": "#333",
  "icons": [
    {
    "src": "/files/icons/256x256.png",
    "sizes": "256x256",
    "type": "image/png"
    },
    {
    "src": "/files/icons/512x512.png",
    "sizes": "512x512",
    "type": "image/png"
    }
  ]
}');
    $outputStrategy = Cms::getOutputStrategy();
    $outputStrategy->addMetaElement("theme-color", "#ddd");
    $outputStrategy->addLinkElement(self::MANIFEST, "manifest");
    $outputStrategy->addJsFile($this->pluginDir."/".$this->className.".js");

    // save service worker
    file_put_contents(self::SERVICE_WORKER, "
importScripts('/".LIB_DIR."/sw-toolbox.js');
toolbox.router.get('/:path([^.]+)*', toolbox.networkFirst);
// toolbox.router.default = toolbox.networkFirst;
");
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
  echo file_get_contents(self::SERVICE_WORKER);
  exit;
  }
}
