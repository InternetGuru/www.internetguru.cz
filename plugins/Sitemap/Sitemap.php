<?php

/**
 * Unlogged user (on root url) generate sitemap.xml from all loaded files and save it to the root of current domain
 * @see http://www.sitemaps.org/protocol.html Sitemap definition
 */
class Sitemap extends Plugin implements SplObserver {

  /**
   * @var array Names of configurable elements
   */
  private $configurableElements = array("changefreq", "priority", "lastmod");
  /**
   * Allowed values for changefreq element
   * @var array
   */
  private $changefreqVals = array("always", "hourly", "daily", "weekly", "monthly", "yearly", "never", "");
  /**
   * Which files will be proceeded; Defined in constructor
   * @var array
   */
  private $allowedPaths = array();
  /**
   * @var DOMDocumentPlus Plugin configuration file; Defined in constructor
   */
  private $cfg = null;


  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1); // before agregator(2)
    $this->cfg = $this->getDOMPlus();
    $this->allowedPaths = array("((?!plugins\/).)*", ".*?\/plugins\/Agregator\/.*?");
  }

  /**
   * Main function
   * @param SplSubject $subject
   */
  public function update(SplSubject $subject) {
    if(!is_null(Cms::getLoggedUser()) || getCurLink() !== "") {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() != STATUS_POSTPROCESS) return;
    try {
      $links = $this->getLinks();
      $links["/"] = $links[""];
      unset($links[""]);
      $cfgLinks = $this->getConfigLinks();
      // update user lastmod by $lastmods
      foreach($links as $link => $mod) {
        if(isset($cfgLinks[$link]) && isset($cfgLinks[$link]["lastmod"])) continue;
        $cfgLinks[$link]["lastmod"] = $links[$link];
      }
      $cfgDefaults = $this->getConfigDefaults();
      $this->createSitemap($links, $cfgLinks, $cfgDefaults);
    } catch(Exception $e) {
      Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
    }
  }

  /**
   * Get links from all included files + root link "/"
   * @return Array links Asociative array of links => mtime in W3C format
   */
  private function getLinks() {
    $links = array();
    $files = Cms::getVariable("dombuilder-html");
    $dt = new DateTime();
    foreach($files as $f) {
      $fPath = findFile($f);
      $allowed = false;
      foreach($this->allowedPaths as $ap) if(preg_match('/^'.$ap.'$/', $fPath)) $allowed = true;
      if(!$allowed) continue;
      if(is_null($fPath)) continue;
      $fInfo = DOMBuilder::getFinfo($fPath);
      $dt->setTimestamp($fInfo["mtime"]);
      $mtime = $dt->format(DATE_W3C);
      if(count($fInfo["linktodesc"])) {
        foreach($fInfo["linktodesc"] as $link => $desc) {
          if(strpos($link, "#") !== false) continue;
          $links[$link] = $mtime;
        }
      }
    }
    return $links;
  }

  /**
   * Get links from configuration
   * @return Array Asociative array of links => the values of their elements
   */
  private function getConfigLinks() {
    $links = array();
    foreach($this->cfg->documentElement->childElementsArray as $e) {
      if($e->nodeName != "url") continue;
      if(!$e->hasAttribute("link")) throw new Exception(_("Element url missing atribute link"));
      foreach($e->childElementsArray as $f) {
        if(!in_array($f->nodeName, $this->configurableElements)) continue;
        $links[$e->getAttribute("link")][$f->nodeName] = $f->nodeValue;
      }
    }
    return $links;
  }

  /**
   * Get default config values from root configirable elements
   * @return Array Associative array of configuration elements
   */
  private function getConfigDefaults() {
    $defaults = array();
    foreach($this->cfg->documentElement->childElementsArray as $e) {
      if(!in_array($e->nodeName, $this->configurableElements)) continue;
      $defaults[$e->nodeName] = $e->nodeValue;
    }
    return $defaults;
  }

  /**
   * Create sitemap.xml according to $links modified by $cfgLinks
   * @param  Array  $links
   * @param  Array  $cfgLinks
   * @param  Array  $cfgDefaults
   */
  private function createSitemap(Array $links, Array $cfgLinks, Array $cfgDefaults) {
    $sitemap = new DOMDocumentPlus();
    $sitemap->formatOutput = true;
    $urlset = $sitemap->appendChild($sitemap->createElement("urlset"));
    $urlset->setAttribute("xmlns", "http://www.sitemaps.org/schemas/sitemap/0.9");
    foreach($links as $link => $h) {
      $url = $urlset->appendChild($sitemap->createElement("url"));
      // loc
      $scheme = Cms::getVariable("urlhandler-default_protocol");
      if(is_null($scheme)) $scheme = "http";
      $url->appendChild($sitemap->createElement("loc", "$scheme://".HOST."/".trim($link, "/")));
      // changefreq
      $changefreq = $this->getValue("changefreq", $link, $cfgLinks, $cfgDefaults);
      if(!is_null($changefreq)) {
        if(!in_array($changefreq, $this->changefreqVals))
          throw new Exception(sprintf(_("Invalid element changefreq value: %s"), $changefreq));
        $url->appendChild($sitemap->createElement("changefreq", $changefreq));
      }
      // priority
      $priority = $this->getValue("priority", $link, $cfgLinks, $cfgDefaults);
      if(!is_null($priority)) {
        if($priority < 0 || $priority > 1)
          throw new Exception(sprintf(_("Invalid element priority value: %s"), $priority));
        $url->appendChild($sitemap->createElement("priority", $priority));
      }
      // lastmod
      $lastmod = $this->getValue("lastmod", $link, $cfgLinks, $cfgDefaults);
      if(!is_null($lastmod)) {
        if(!preg_match("/^".W3C_DATETIME_PATTERN."$/", $lastmod))
          throw new Exception(sprintf(_("Invalid element lastmod value: %s"), $lastmod));
        $url->appendChild($sitemap->createElement("lastmod", $lastmod));
      }
    }
    $sitemap->save("sitemap.xml");
  }

  /**
   * Get value from $cfgLinks or from $cfgDefaults or null
   * @param  String $name
   * @param  Array  $cfgLinks
   * @param  Array  $cfgDefaults
   * @return String|null
   */
  private function getValue($name, $link, $cfgLinks, $cfgDefaults) {
    if(isset($cfgLinks[$link]) && isset($cfgLinks[$link][$name]) && strlen($cfgLinks[$link][$name]))
      return $cfgLinks[$link][$name];
    if(isset($cfgDefaults[$name]) && strlen($cfgDefaults[$name]))
      return $cfgDefaults[$name];
    return null;
  }

}

?>