<?php

namespace IGCMS\Plugins;

use DOMDocument;
use DOMElement;
use DOMImplementation;
use DOMXPath;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\ErrorPage;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\OutputStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use IGCMS\Core\ResourceInterface;
use IGCMS\Core\XMLBuilder;
use SplObserver;
use SplSubject;
use XSLTProcessor;

/**
 * Class HtmlOutput
 * @package IGCMS\Plugins
 */
class HtmlOutput extends Plugin implements SplObserver, OutputStrategyInterface, ResourceInterface {
  /**
   * @var string
   */
  const APPEND_HEAD = "head"; // String filename => Int priority
  /**
   * @var string
   */
  const APPEND_BODY = "body"; // String filename => Int priority
  /**
   * @var string
   */
  const FAVICON = "favicon.ico";
  /**
   * @var int
   */
  const DEFAULT_PRIORITY = 50;
  /**
   * @var array
   */
  private $linkElements = [];
  /**
   * @var array
   */
  private $metaElements = [];
  /**
   * @var array
   */
  private $jsFiles = [];
  /**
   * @var array
   */
  private $jsFilesPriority = [];
  /**
   * @var array
   */
  private $cssFiles = [];
  /**
   * @var array
   */
  private $cssFilesPriority = [];
  /**
   * @var array
   */
  private $xsltPriority = [];
  /**
   * @var array
   */
  private $transformations = [];
  /**
   * @var string|null
   */
  private $favIcon = null;
  /**
   * @var DOMDocumentPlus|null
   */
  private $cfg = null;
  /**
   * @var string|null
   */
  private $metaRobots = null;
  /**
   * @var bool
   */
  private $useOg = false;

  /**
   * HtmlOutput constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 0);
    Cms::setOutputStrategy($this);
  }

  /**
   * @param Plugins|SplSubject $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    if ($this->detachIfNotAttached("FileHandler")) {
      return;
    }
    if ($subject->getStatus() != STATUS_PROCESS) {
      return;
    }
    $this->cfg = self::getXML();
    $this->registerThemes($this->cfg);
    $robots = $this->cfg->matchElement("robots", "domain", HTTP_HOST);
    if (is_null($robots)) {
      throw new Exception("Unable to match robots element to domain");
    }
    if (!$robots->hasAttribute("domain")) {
      Logger::user_warning(_("Using default robots value (without domain match)"));
    }
    $this->metaRobots = $robots->getAttribute("meta");
    if (stream_resolve_include_path(ROBOTS_TXT) && !@unlink(ROBOTS_TXT)) {
      Logger::error(sprintf(_("Unable to delete %s file"), ROBOTS_TXT));
    }
    if (is_null($this->favIcon)) {
      $this->favIcon = find_file($this->pluginDir."/".self::FAVICON);
    }
  }

  /**
   * @param DOMDocumentPlus $cfg
   * @throws Exception
   */
  private function registerThemes (DOMDocumentPlus $cfg) {

    // add default xsl
    $this->addTransformation($this->pluginDir."/".$this->className.".xsl", 1);

    // add template files
    $theme = $cfg->getElementById("theme");
    if (!is_null($theme)) {
      $themeId = $theme->nodeValue;
      $themeElement = $cfg->getElementById($themeId);
      if (is_null($themeElement)) {
        Logger::user_warning(sprintf(_("Theme '%s' not found"), $themeId));
      } else {
        $this->addThemeFiles($themeElement);
      }
    }

    // add root template files
    $this->addThemeFiles($cfg->documentElement);
  }

  /**
   * @param string $filePath
   * @return bool
   */
  public static function isSupportedRequest ($filePath) {
    return $filePath === ROBOTS_TXT;
  }

  /**
   * @return void
   * @throws Exception
   */
  public static function handleRequest () {
    $robots = self::getXML()->matchElement("robots", "domain", HTTP_HOST);
    if (is_null($robots)) {
      new ErrorPage("No matching robots element", 404);
    }
    header('Content-Type: text/plain');
    echo $robots->nodeValue;
    exit;
  }

  /**
   * @param string $filePath
   * @param int $priority
   * @throws Exception
   */
  public function addTransformation ($filePath, $priority = self::DEFAULT_PRIORITY) {
    if (isset($this->transformations[$filePath])) {
      return;
    }
    Cms::addVariableItem("transformations", $filePath);
    $this->transformations[$filePath] = [
      "priority" => $priority,
      "file" => $filePath,
    ];
    $this->xsltPriority[$filePath] = $priority;
  }

  /**
   * @param DOMElementPlus|null $element
   */
  private function addThemeFiles (DOMElementPlus $element = null) {
    foreach ($element->childElementsArray as $node) {
      try {
        switch ($node->nodeName) {
          case "":
          case "var":
          case "themes":
            continue;
          case "xslt":
            $this->addTransformation($node->nodeValue, 5);
            break;
          case "jsFile":
            $user = !$node->hasAttribute("readonly");
            $append = self::APPEND_HEAD;
            $priority = self::DEFAULT_PRIORITY;
            if ($node->hasAttribute("append")) {
              $append = $node->getAttribute("append");
            }
            if ($node->hasAttribute("priority")) {
              $priority = $node->getAttribute("priority");
            }
            $ieIfComment = ($node->hasAttribute("if") ? $node->getAttribute("if") : null);
            $ifXpath = ($node->hasAttribute("if-xpath") ? $node->getAttribute("if-xpath") : false);
            $async = !($node->hasAttribute("async") && $node->getAttribute("async") == "false");
            $this->addJsFile($node->nodeValue, $priority, $append, $user, $ieIfComment, $ifXpath, $async);
            break;
          case "stylesheet":
            $media = ($node->hasAttribute("media") ? $node->getAttribute("media") : false);
            $ieIfComment = ($node->hasAttribute("if") ? $node->getAttribute("if") : null);
            $ifXpath = ($node->hasAttribute("if-xpath") ? $node->getAttribute("if-xpath") : false);
            $this->addCssFile($node->nodeValue, $media, self::DEFAULT_PRIORITY, true, $ieIfComment, $ifXpath);
            break;
          case "favicon":
            $this->favIcon = find_file($node->nodeValue);
        }
      } catch (Exception $exc) {
        Logger::user_warning(sprintf(_("File %s of type %s not found"), $node->nodeValue, $node->nodeName));
      }
    }
  }

  /** @noinspection PhpTooManyParametersInspection */
  /**
   * @param string $filePath
   * @param int $priority
   * @param string $append
   * @param bool $user
   * @param null $ieIfComment
   * @param bool $ifXpath
   * @param bool $async
   * @throws Exception
   */
  public function addJsFile ($filePath, $priority = self::DEFAULT_PRIORITY, $append = self::APPEND_HEAD, $user = false, $ieIfComment = null, $ifXpath = false, $async = true) {
    if (isset($this->jsFiles[$filePath])) {
      return;
    }
    Cms::addVariableItem("javascripts", $filePath);
    #if(findFile($filePath, $user) === false) throw new Exception();
    $this->jsFiles[$filePath] = [
      "file" => $filePath,
      "append" => $append,
      "content" => "",
      "user" => $user,
      "ifXpath" => $ifXpath,
      "if" => $ieIfComment,
      "async" => $async,
    ];
    $this->jsFilesPriority[$filePath] = $priority;
  }

  /** @noinspection PhpTooManyParametersInspection */
  /**
   * @param string $filePath
   * @param string $rel
   * @param bool $type
   * @param bool $media
   * @param null $ieIfComment
   */
  public function addLinkElement ($filePath, $rel, $type = false, $media = false, $ieIfComment = null) {
    if (isset($this->linkElements[$filePath])) {
      return;
    }
    $this->linkElements[$filePath] = [
      "file" => $filePath,
      "rel" => $rel,
      "type" => $type,
      "media" => $media,
      "if" => $ieIfComment,
    ];
  }

  /** @noinspection PhpTooManyParametersInspection */
  /**
   * @param string $nameValue
   * @param string $contentValue
   * @param bool $httpEquiv
   * @param bool $short
   */
  public function addMetaElement ($nameValue, $contentValue, $httpEquiv = false, $short = false) {
    $this->metaElements[$nameValue] = [
      "content" => $contentValue,
      "httpEquip" => $httpEquiv,
      "short" => $short,
    ];
  }

  /** @noinspection PhpTooManyParametersInspection */
  /**
   * @param string $filePath
   * @param bool $media
   * @param int $priority
   * @param bool $user
   * @param string|null $ieIfComment
   * @param bool $ifXpath
   * @throws Exception
   */
  public function addCssFile ($filePath, $media = false, $priority = self::DEFAULT_PRIORITY, $user = true, $ieIfComment = null, $ifXpath = false) {
    if (isset($this->cssFiles[$filePath])) {
      return;
    }
    Cms::addVariableItem("styles", $filePath);
    #if(findFile($filePath, $user) === false) throw new Exception();
    $this->cssFiles[$filePath] = [
      "priority" => $priority,
      "file" => $filePath,
      "media" => $media,
      "user" => $user,
      "ifXpath" => $ifXpath,
      "if" => $ieIfComment,
    ];
    $this->cssFilesPriority[$filePath] = $priority;
  }

  /**
   * Create HTML 5 output from HTML+ content and own registers (JS/CSS)
   * @param HTMLPlus $content
   * @return string
   * @throws Exception
   */
  public function getOutput (HTMLPlus $content) {
    stable_sort($this->cssFilesPriority);
    stable_sort($this->jsFilesPriority);
    $heading = $content->documentElement->firstElement;
    $lang = $content->documentElement->getAttribute("xml:lang");

    // apply transformations
    $content = $this->applyTransformations($content);
    $contentPlus = new DOMDocumentPlus();
    $contentPlus->loadXML($content->saveXML());

    // final plugin modification
    global $plugins;
    foreach ($plugins->getIsInterface("IGCMS\\Core\\FinalContentStrategyInterface") as $fcs) {
      $contentPlus = $fcs->getContent($contentPlus);
    }

    $this->useOg = $this->cfg->getElementById('og')->nodeValue == "enabled";

    // create output DOM with doctype
    $doc = $this->createDoc();
    $html = $this->addRoot($doc, $lang);

    // final validation
    $contentPlus->processFunctions(Cms::getAllFunctions());
    $xPath = new DOMXPath($contentPlus);

    /** @var DOMElementPlus $element */
    foreach ($xPath->query("//*[@var]") as $element) {
      $element->stripAttr("var");
    }
    foreach ($xPath->query("//*[@fn]") as $element) {
      $element->stripAttr("fn");
    }
    foreach ($xPath->query("//select[@pattern]") as $element) {
      $element->stripAttr("pattern");
    }
    foreach ($contentPlus->getElementsByTagName("a") as $element) {
      $this->processElement($element, "href");
    }
    foreach ($contentPlus->getElementsByTagName("form") as $element) {
      $this->processElement($element, "action");
    }
    $ids = [];
    foreach ($xPath->query("//*/@id") as $element) {
      $ids[$element->nodeValue] = null;
    }
    /** @var DOMElementPlus $img */
    foreach ($contentPlus->getElementsByTagName("img") as $img) {
      $this->processImage($img, $ids);
    }
    foreach ($xPath->query("//*[@xml:lang]") as $element) {
      if (!$element->hasAttribute("lang")) {
        $element->setAttribute("lang", $element->getAttribute("xml:lang"));
      }
      $element->removeAttribute("xml:lang");
    }
    $this->consolidateLang($contentPlus->documentElement, $lang);

    $this->addHead($doc, $html, $heading, $xPath, $contentPlus);
    // import into html and save
    /** @var DOMElement $content */
    $content = $doc->importNode($contentPlus->documentElement, true);
    $html->appendChild($content);
    $this->appendJsFiles($html->getElementsByTagName("head")->item(0), self::APPEND_HEAD, $xPath);
    $this->appendJsFiles($content, self::APPEND_BODY, $xPath);

    $this->validateEmptyContent($doc);
    $html = $doc->saveXML();
    return substr($html, strpos($html, "\n") + 1);
  }

  /**
   * @param DOMDocumentPlus $content
   * @return DOMDocument|DOMDocumentPlus
   */
  private function applyTransformations (DOMDocumentPlus $content) {
    $proc = new XSLTProcessor();
    /** @noinspection PhpParamsInspection */
    $proc->setParameter('', $this->getProcParams());
    stable_sort($this->xsltPriority);
    foreach ($this->xsltPriority as $xslt => $priority) {
      try {
        $newContent = $this->transform($content, $xslt, $proc);
        $newContent->encoding = "utf-8";
        $xml = $newContent->saveXML();
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        if (!@$newContent->loadXML($xml)) {
          throw new Exception(sprintf(_("Invalid transformation or parameter in '%s'"), $xslt));
        }
        #todo: validate HTML5 validity
        $content = $newContent;
      } catch (Exception $exc) {
        Logger::user_error($exc->getMessage());
      }
    }
    return $content;
  }

  /**
   * @return array
   */
  private function getProcParams () {
    $output = [];
    foreach (Cms::getAllVariables() as $key => $var) {
      $value = $var["value"];
      if ($value instanceof \Closure) {
        continue;
      } elseif ($value instanceof DOMDocumentPlus) {
        $string = $value->saveXML($value->documentElement);
      } elseif ($value instanceof DOMElement) {
        $string = "";
        foreach ($value->childNodes as $node) {
          $string .= $value->ownerDocument->saveXML($node);
        }
      } elseif (is_array($value)) {
        $string = implode(", ", $value);
      } elseif (is_object($value) && !method_exists($value, '__toString')) {
        Logger::critical(sprintf(_("Unable to convert variable '%s' to string"), $key));
        continue;
      } else {
        $string = (string) $value;
      }
      if (false) {
        if ($key != "globalmenu") {
          continue;
        }
        #$v = "&copy;2014 &amp; <a href='http://www.internetguru.cz'>InternetGuru</a>";
        echo ($value)."\n";
        echo html_entity_decode($value)."\n";
        echo htmlentities($value)."\n";
        echo html_entity_decode($value)."\n";
        echo utf8_decode(html_entity_decode($value))."\n";
        echo htmlentities(utf8_decode(html_entity_decode($value)), ENT_XHTML)."\n";
        echo to_utf8($value)."\n";
        die();
      }
      $output[$key] = str_replace("'", '"', to_utf8($string));
    }
    return $output;
  }

  /**
   * @param DOMDocument $content
   * @param string $fileName
   * @param XSLTProcessor $proc
   * @return DOMDocument
   * @throws Exception
   */
  private function transform (DOMDocument $content, $fileName, XSLTProcessor $proc) {
    #var_dump($fileName);
    $xsl = XMLBuilder::load($fileName);
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (!@$proc->importStylesheet($xsl)) {
      throw new Exception(sprintf(_("XSLT '%s' compilation error"), $fileName));
    }
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (($doc = @$proc->transformToDoc($content)) === false) {
      throw new Exception(sprintf(_("XSLT '%s' transformation fail"), $fileName));
    }
    #echo $x->saveXML();
    return $doc;
  }

  /**
   * @return DOMDocument
   */
  private function createDoc () {
    $imp = new DOMImplementation();
    $dtd = $imp->createDocumentType('html');
    #$dtd = $imp->createDocumentType('html',
    #    '-//W3C//DTD XHTML 1.1//EN',
    #    'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd');
    $doc = $imp->createDocument(null, null, $dtd);
    $doc->encoding = "utf-8";
    return $doc;
  }

  /**
   * @param DOMDocument $doc
   * @param string $lang
   * @return DOMElement
   */
  private function addRoot (DOMDocument $doc, $lang) {
    $html = $doc->createElement("html");
    $html->setAttribute("xmlns", "http://www.w3.org/1999/xhtml");
    #$html->setAttribute("xml:lang", $lang);
    $html->setAttribute("lang", $lang);
    if ($this->useOg) {
      $html->setAttribute("prefix", "og: http://ogp.me/ns#");
    }
    $doc->appendChild($html);
    return $html;
  }

  /**
   * @param DOMDocument $doc
   * @param DOMElement $html
   * @param DOMElementPlus $h1
   * @param DOMXPath $xPath
   * @return DOMElement
   * @param DOMDocumentPlus $content
   * @throws Exception
   */
  private function addHead (DOMDocument $doc, DOMElement $html, DOMElementPlus $h1, DOMXPath $xPath, DOMDocumentPlus $content) {
    $head = $doc->createElement("head");
    $head->appendChild($doc->createElement("title", $this->getTitle($h1)));
    $this->appendMeta($head, "charset", "utf-8", false, true);
    $this->appendMeta($head, "viewport", "initial-scale=1");
    $this->appendMeta($head, "generator", Cms::getVariableValue("cms-name"));
    $this->appendMeta($head, "author", $h1->getAttribute("author"));
    $this->appendMeta($head, "description", strip_tags($h1->nextElement->nodeValue));
    $this->appendMeta($head, "keywords", $h1->nextElement->getAttribute("kw"));
    $this->appendMeta($head, "robots", $this->metaRobots);
    foreach ($this->metaElements as $name => $metaElement) {
      $this->appendMeta($head, $name, $metaElement["content"], $metaElement["httpEquip"], $metaElement["short"]);
    }
    if ($this->useOg) {
      $this->setMetaOg($head, $content, $xPath);
    }
    update_file($this->favIcon, self::FAVICON); // hash?
    $this->appendLinkElement($head, $this->getFavIcon(), "shortcut icon", false, false);
    foreach ($this->linkElements as $linkElement) {
      $this->appendLinkElement($head, $linkElement["file"], $linkElement["rel"], $linkElement["type"], $linkElement["media"], $linkElement["if"]);
    }
    $this->appendCssFiles($head, $xPath);
    $html->appendChild($head);
    return $head;
  }

  /**
   * @param DOMElement $head
   * @param DOMDocumentPlus $content
   * @param DOMXPath $xpath
   * @throws Exception
   */
  private function setMetaOg (DOMElement $head, DOMDocumentPlus $content, DOMXPath $xpath) {
    $id = HTMLPlusBuilder::getLinkToId(get_link());
    $type = 'website';
    $images = [];
    $dataAttrs = HTMLPlusBuilder::getIdToData($id);
    if (is_null($dataAttrs)) {
      $dataAttrs = [];
    }
    foreach ($dataAttrs as $name => $value) {
      if (substr($name, 0, 3) !== 'og-') {
        continue;
      }
      switch ($name) {
        case 'og-image':
          $urls = explode(' ', $value);
          foreach ($urls as $url) {
            $images[] = trim($url);
          }
        break;
        case 'og-type':
          $type = $value;
          if ($type == 'article') {
            $this->appendOgElement($head, 'og:article:published_time', HTMLPlusBuilder::getIdToCtime($id));
            $mtime = HTMLPlusBuilder::getIdToMtime($id);
            if ($mtime) {
              $this->appendOgElement($head, 'og:article:modified_time', $mtime);
            }
            $this->appendOgElement($head, 'og:article:author', HTMLPlusBuilder::getIdToAuthor($id));
          }
        break;
        case 'og-article-published_time':
        case 'og-article-modified_time':
        case 'og-article-author':
        case 'og-title':
        case 'og-description':
        case 'og-site_name':
        case 'og-url':
        break;
        default:
          $this->appendOgElement($head, 'og:' . str_replace('-', ':', substr($name, 3)), $value);
      }
    }
    if (empty($images)) {
      /** @var DOMElement $img */
      foreach ($content->getElementsByTagName('img') as $img) {
        $src = $img->getAttribute('src');
        try {
          $dimensions = $this->getImageDimensions($src);
        } catch (Exception $e) {
          Logger::warning(sprintf(_('Unable to get image size: %s'), $e->getMessage()));
          continue;
        }
        if ($dimensions[0] < 200 || $dimensions[1] < 200) {
          Logger::warning(sprintf(_('Image %s dimensions are smaler than 200px'), $src));
          continue;
        }
        $images[] = $src;
      }
    }
    $this->getConfigImages($xpath, $images);
    foreach ($images as $url) {
      if (strpos($url, 'http:') !== 0 && strpos($url, 'https:') !== 0) {
        $url = HTTP_URL.'/'.ltrim($url, '/');
      }
      $this->appendOgElement($head, 'og:image', $url);
    }
    $this->appendOgElement($head, 'og:type', $type);
    $this->appendOgElement($head, 'og:title', HTMLPlusBuilder::getIdToHeading($id));
    $this->appendOgElement($head, 'og:description', HTMLPlusBuilder::getIdToDesc($id));
    $this->appendOgElement($head, 'og:site_name ', current(HTMLPlusBuilder::getIdToHeading()));
    $this->appendOgElement($head, 'og:url', HTTP_URL . '/' . get_link());
  }

  /**
   * @param DOMXPath $xPath
   * @param array $images
   */
  private function getConfigImages(DOMXPath $xPath, Array &$images) {
    $configImages = $this->cfg->getElementsByTagName('og-image');
    /** @var DOMElementPlus $img */
    foreach ($configImages as $img) {
      $ifXpath = $img->getAttribute('if-xpath');
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      if (strlen($ifXpath)) {
        $result = @$xPath->query($ifXpath);
        if ($result === false) {
          Logger::user_warning(sprintf(_("Invalid xPath query '%s'"), $ifXpath));
          continue;
        }
        if ($result->length === 0) {
          continue;
        }
      }
      $apply = $img->getAttribute('apply');
      if ($apply != 'always') {
        $apply = 'auto';
      }
      if ($apply == 'auto' && count($images)) {
        continue;
      }
      $src = $img->getAttribute('src');
      if (strlen($src)) {
        $images[] = $src;
      }
      $genid = $img->getAttribute('gen');
      if (!strlen($genid)) {
        continue;
      }
      try {
        $gen = $this->cfg->getElementById($genid, 'image-gen');
        if (is_null($gen)) {
          throw new Exception(sprintf(_('Generator %s does not exist'), $gen));
        }
      } catch (Exception $e) {
        Logger::warning(sprintf(_('Unable to generate images: %s'), $e->getMessage()));
        continue;
      }
      $count = $gen->getAttribute('count');
      $grayscale = $gen->getAttribute('grayscale');
      $blur = $gen->getAttribute('blur');
      $count = min($count, 20);
      if (!is_int($count) || $count < 0) {
        $count = 10;
      }
      if (!is_numeric($grayscale) || $grayscale < 0 || $grayscale > 1) {
        $grayscale = 0.2;
      }
      if (!is_numeric($blur) || $blur < 0 || $blur > 1) {
        $blur = 0.2;
      }
      $randomImages = $this->getRandomImages(get_link(), $count, $grayscale, $blur);
      foreach ($randomImages as $randomImage) {
        $images[] = $randomImage;
      }
    }
  }

  /**
   * @param $pageUrl
   * @param $count
   * @param $grayscale
   * @param $blur
   * @return array
   */
  private function getRandomImages ($pageUrl, $count, $grayscale, $blur) {
    $urls = [];
    srand(crc32($pageUrl));
    $gravity = ["north", "east", "south", "west", "center"];
    for ($i = 0; $i < $count; $i++) {
      $url = "https://picsum.photos/";
      if ($grayscale != 0 && rand() % round(1 / $grayscale) == 0) {
        $url .= "g/";
      }
      $url .= "600/315/?image=".rand(0, 1084);
      if ($blur != 0 && rand() % round(1 / $blur) == 0) {
        $url .= "&blur";
      }
      $url .= "&gravity=".$gravity[rand(0, count($gravity)-1)];
      $urls[] = $url;
    }
    return $urls;
  }

  /**
   * @param DOMElement $head
   * @param $name
   * @param $value
   */
  private function appendOgElement (DOMElement $head, $name, $value) {
    $meta = $head->ownerDocument->createElement("meta");
    $meta->setAttribute('property', $name);
    $meta->setAttribute('content', $value);
    $head->appendChild($meta);
  }

  /**
   * @param DOMElementPlus $h1
   * @return string|null
   */
  private function getTitle (DOMElementPlus $h1) {
    $title = null;
    foreach ($this->subject->getIsInterface("IGCMS\\Core\\TitleStrategyInterface") as $clsName => $cls) {
      $title = $cls->getTitle();
      if (!is_null($title)) {
        return $title;
      }
    }
    return $h1->hasAttribute("short") ? $h1->getAttribute("short") : $h1->nodeValue;
  }

  /**
   * Append meta element to an element (supposed head)
   * @param  DOMElement $e Element to which meta is to be appended
   * @param  string $nameValue Value of attribute name/http-equiv
   * @param  string $contentValue Value of attribute content
   * @param  boolean $httpEquiv Use attr.http-equiv instead of name
   * @param  bool $short
   * @return void
   */
  private function appendMeta (DOMElement $e, $nameValue, $contentValue, $httpEquiv = false, $short = false) {
    $meta = $e->ownerDocument->createElement("meta");
    if ($short) {
      $meta->setAttribute($nameValue, $contentValue);
    } else {
      $meta->setAttribute(($httpEquiv ? "http-equiv" : "name"), $nameValue);
      $meta->setAttribute("content", $contentValue);
    }
    $e->appendChild($meta);
  }

  /** @noinspection PhpTooManyParametersInspection */
  /**
   * @param DOMElement $parent
   * @param string $filePath
   * @param string $rel
   * @param bool $type
   * @param bool $media
   * @param string|null $ieIfComment
   */
  private function appendLinkElement (DOMElement $parent, $filePath, $rel, $type = false, $media = false, $ieIfComment = null) {
    $element = $parent->ownerDocument->createElement("link");
    if ($type) {
      $element->setAttribute("type", $type);
    }
    if ($rel) {
      $element->setAttribute("rel", $rel);
    }
    if ($media) {
      $element->setAttribute("media", $media);
    }
    $element->setAttribute("href", $filePath);
    if (!is_null($ieIfComment)) {
      $parent->appendChild(
        $parent->ownerDocument->createComment("[if $ieIfComment]>".$element->ownerDocument->saveXML($element)."<![endif]")
      );
      return;
    }
    $parent->appendChild($element);
  }

  /**
   * @return string
   * @throws Exception
   */
  private function getFavIcon () {
    if (Cms::hasErrorMessage()) {
      return $this->cfg->getElementById("error")->nodeValue;
    }
    if (Cms::hasWarningMessage()) {
      return $this->cfg->getElementById("warning")->nodeValue;
    }
    if (Cms::hasNoticeMessage()) {
      return $this->cfg->getElementById("notice")->nodeValue;
    }
    if (Cms::hasSuccessMessage()) {
      return $this->cfg->getElementById("success")->nodeValue;
    }
    return ROOT_URL.self::FAVICON;
  }

  /**
   * Append all registered CSS files into a parent (usually head)
   * @param  DOMElement $parent Element to append JS files to
   * @param  DOMXPath $xPath
   * @return void
   */
  private function appendCssFiles (DOMElement $parent, DOMXPath $xPath) {
    foreach ($this->cssFilesPriority as $key => $value) {
      $ifXpath = isset($this->cssFiles[$key]["ifXpath"]) ? $this->cssFiles[$key]["ifXpath"] : false;
      if ($ifXpath !== false) {
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        $result = @$xPath->query($ifXpath);
        if ($result === false) {
          Logger::user_warning(sprintf(_("Invalid xPath query '%s'"), $ifXpath));
          continue;
        }
        if ($result->length === 0) {
          continue;
        }
      }
      $ieIfComment = isset($this->cssFiles[$key]["if"]) ? $this->cssFiles[$key]["if"] : null;
      $filePath = ROOT_URL.get_resdir($this->cssFiles[$key]["file"]);
      $this->appendLinkElement(
        $parent,
        $filePath,
        "stylesheet",
        "text/css",
        $this->cssFiles[$key]["media"],
        $ieIfComment
      );
    }
  }

  /**
   * @param DOMElementPlus $e
   * @param string $aName
   * @throws Exception
   */
  private function processElement (DOMElementPlus $e, $aName) {
    // no target, no check, no title manipulation
    if (!$e->hasAttribute($aName)) {
      return;
    }
    $target = trim($e->getAttribute($aName));
    try {
      // link is empty
      if (!strlen($target)) {
        throw new Exception(_("Empty value"));
      }
      // throws unable to parse exception
      $pLink = parse_local_link($target);
      // link is external
      if (is_null($pLink)) {
        return;
      }
      // build local url iff local url
      $this->processLink($e, $aName, $pLink);
      if ($e->nodeName != "a") {
        return;
      }
      if (!array_key_exists("id", $pLink)) {
        return;
      }
      $e->setAttribute("lang", HTMLPlusBuilder::getIdToLang($pLink["id"]));
      // generate title if not exists
      if ($e->hasAttribute("title")) {
        return;
      }
      if (array_key_exists("query", $pLink)) {
        return;
      }
      $this->insertTitle($e, $pLink["id"]);
    } catch (Exception $exc) {
      $message = sprintf(_("Attribute %s='%s' removed: %s"), $aName, $target, $exc->getMessage());
      $e->stripAttr($aName, $message);
      if (is_null(Cms::getLoggedUser())) {
        return;
      }
      $e->setAttribute("title", $message);
      $e->addClass("stripped");
      $e->addClass(REQUEST_TOKEN); // make it always show in webdiff
    }
  }

  /**
   * @param DOMElementPlus $e
   * @param String $aName
   * @param array $pLink
   * @throws Exception
   */
  private function processLink (DOMElementPlus $e, $aName, Array &$pLink) {
    $isLink = true;
    $path = null;
    if (array_key_exists("path", $pLink)) {
      $path = $pLink["path"];
      // link to supported file
      if (FileHandler::isSupportedRequest($pLink["path"])) {
        $isLink = false;
      }
      $ext = pathinfo($pLink["path"], PATHINFO_EXTENSION);
      $isFile = is_file($pLink["path"]);
      // link to image
      if (!$isLink && FileHandler::isImage($ext)) {
        list($targetWidth, $targetHeight) = self::getImageDimensions($pLink['path']);
        $e->setAttribute("data-target-width", $targetWidth);
        $e->setAttribute("data-target-height", $targetHeight);
      }
      // link to existing file
      if ($isLink && $isFile) {
        if ($ext == "php") {
          return;
        }
        $isLink = false;
      }
    }
    $localFragment = $this->isLocalFragment($pLink);
    if ($isLink) {
      $rootId = HTMLPlusBuilder::getFileToId(HTMLPlusBuilder::getCurFile());
      $pLink = $this->getLink($pLink, $rootId);
    }
    if (empty($pLink)) {
      if ($localFragment) {
        return;
      }
      try {
        find_file($path);
      } catch(Exception $ex) {
        throw new Exception(_("Target not found"));
      }
      throw new Exception(_("Target file not supported"));
    }
    $ignoreCyclic = $e->nodeName != "a";
    $link = build_local_url($pLink, $ignoreCyclic, $isLink);
    $e->setAttribute($aName, $link);
  }

  /**
   * @param array $pHref
   * @param string $rootId
   * @return array
   */
  private function getLink ($pHref, $rootId) {
    if (!array_key_exists("path", $pHref) && !array_key_exists("fragment", $pHref)) {
      return $pHref;
    }
    $pHref["id"] = array_key_exists("path", $pHref) ? $pHref["path"] : $rootId;
    if (!strlen($pHref["id"])) {
      $pHref["id"] = HTMLPlusBuilder::getRootId();
    }
    if (array_key_exists("fragment", $pHref)) {
      $pHref["id"] .= "/".$pHref["fragment"];
    }
    // href is link
    $linkId = HTMLPlusBuilder::getLinkToId($pHref["id"]);
    if (!is_null($linkId)) {
      $pHref["id"] = $linkId;
      return $pHref;
    }
    // href is heading id
    $link = HTMLPlusBuilder::getIdToLink($pHref["id"]);
    // href is non-heading id
    if (is_null($link)) {
      $linkId = HTMLPlusBuilder::getIdToParentId($pHref["id"]);
      // link not found
      if (is_null($linkId)) {
        return [];
      }
      $link = HTMLPlusBuilder::getIdToLink($linkId);
    }
    $linkArray = explode("#", $link);
    $pHref["path"] = $linkArray[0];
    // update fragment iff not non-heading id
    if (isset($linkArray[1]) && !isset($pHref["fragment"])) {
      $pHref["fragment"] = $linkArray[1];
    }
    return $pHref;
  }

  /**
   * @param array $pLink
   * @return bool
   */
  private function isLocalFragment (Array $pLink) {
    if (array_key_exists("path", $pLink)) {
      return false;
    }
    return array_key_exists("fragment", $pLink);
  }

  /**
   * @param DOMElementPlus $a
   * @param string $id
   */
  private function insertTitle (DOMElementPlus $a, $id) {
    $title = HTMLPlusBuilder::getIdToTitle($id);
    if (!strlen($title) || $title == $a->nodeValue) {
      $title = HTMLPlusBuilder::getIdToHeading($id);
      if (!strlen($title) || $title == $a->nodeValue) {
        $title = shorten(HTMLPlusBuilder::getIdToDesc($id));
      }
    }
    $a->setAttribute("title", $title);
  }

  /**
   * @param string $text
   * @param array $register
   * @param int $attempt
   * @return mixed
   */
  private function getUniqueHash ($text, Array &$register, $attempt = 0) {
    $suffix = $attempt > 0 ? "-$attempt" : "";
    $hash = substr(hash("sha256", $text), 0, 4).$suffix;
    if (array_key_exists($hash, $register)) {
      return $this->getUniqueHash($text, $register, ++$attempt);
    }
    $register[$hash] = null;
    return $hash;
  }

  /**
   * @param DOMElementPlus $img
   * @param array $ids
   * @throws Exception
   */
  private function processImage (DOMElementPlus $img, Array &$ids) {
    if ($img->hasAttribute("width") && $img->hasAttribute("height")) {
      return;
    }
    $src = $img->getAttribute("src");
    // external
    if (is_null(parse_local_link($src))) {
      return;
    }
    try {
      list($targetWidth, $targetHeight) = $this->getImageDimensions($src);
    } catch (Exception $exc) {
      if (is_null(Cms::getLoggedUser())) {
        $img->stripElement();
        return;
      }
      $message = sprintf(_("Attribute src=%s removed: %s"), $src, $exc->getMessage());
      $img->setAttribute('src', "/".LIB_DIR."/".NOT_FOUND_IMG_FILENAME);
      list($targetWidth, $targetHeight) = getimagesize(LIB_FOLDER."/".NOT_FOUND_IMG_FILENAME);
      $img->setAttribute("title", $message);
      $img->addClass("stripped");
      $img->addClass(REQUEST_TOKEN); // make it always show in webdiff
    }
    $img->setAttribute("width", $targetWidth);
    $img->setAttribute("height", $targetHeight);
    if ($img->hasAttribute("id")) {
      return;
    }
    $img->setAttribute("id", "img".self::getUniqueHash($img->getAttribute("src"), $ids));
  }

  /**
   * @param string $src
   * @return array
   * @throws Exception
   */
  private function getImageDimensions ($src) {
    if (stream_resolve_include_path($src)) {
      return getimagesize(realpath($src));
    } else {
      return FileHandler::calculateImageSize($src);
    }
  }

  /**
   * @param DOMElementPlus $parent
   * @param string $lang
   */
  private function consolidateLang (DOMElementPlus $parent, $lang) {
    if ($parent->getAttribute("lang") == $lang) {
      $parent->removeAttribute("lang");
    }
    foreach ($parent->childElementsArray as $element) {
      $this->consolidateLang($element, $lang);
    }
  }

  /**
   * @param DOMElement $parent
   * @param string $append
   * @param DOMXPath $xPath
   */
  private function appendJsFiles (DOMElement $parent, $append = self::APPEND_HEAD, DOMXPath $xPath) {
    foreach ($this->jsFilesPriority as $key => $value) {
      if ($append != $this->jsFiles[$key]["append"]) {
        continue;
      }
      $ifXpath = isset($this->jsFiles[$key]["ifXpath"]) ? $this->jsFiles[$key]["ifXpath"] : false;
      if ($ifXpath !== false) {
        $result = $xPath->query($ifXpath);
        if ($result === false || $result->length === 0) {
          continue;
        }
      }
      $element = $parent->ownerDocument->createElement("script");
      $this->appendCdata($element, $this->jsFiles[$key]["content"]);
      $filePath = ROOT_URL.get_resdir($this->jsFiles[$key]["file"]);
      if (!is_null($this->jsFiles[$key]["file"])) {
        $element->setAttribute("src", $filePath);
        if (array_key_exists("async", $this->jsFiles[$key]) && $this->jsFiles[$key]["async"] ===  true) {
          $element->setAttribute("async", "async");
        }
      }
      $ieIfComment = isset($this->jsFiles[$key]["if"]) ? $this->jsFiles[$key]["if"] : null;
      if (!is_null($ieIfComment)) {
        #$e->nodeValue = "�";
        $parent->appendChild(
          $parent->ownerDocument->createComment("[if $ieIfComment]>".$element->ownerDocument->saveXML($element)."<![endif]")
        );
        continue;
      }
      $parent->appendChild($element);
    }
  }

  /**
   * @param DOMElement $appendTo
   * @param string $text
   */
  private function appendCdata (DOMElement $appendTo, $text) {
    if (!strlen($text)) {
      return;
    }
    $appendTo->appendChild($appendTo->ownerDocument->createTextNode("//"));
    if (strpos($text, "\n") !== 0) {
      $text = "\n$text";
    }
    $appendTo->appendChild($appendTo->ownerDocument->createCDATASection("$text\n//"));
  }

  /**
   * @param DOMDocument $doc
   */
  private function validateEmptyContent (DOMDocument $doc) {
    $emptyShort = ["input", "br", "hr", "meta", "link", "param", "img", "source"]; // allowed empty in short format
    $emptyLong = ["script", "textarea", "object"]; // allowed empty in long format only
    $xpath = new DOMXPath($doc);
    $toExpand = [];
    $toDelete = [];
    foreach ($xpath->query("//*[not(node()) and not(normalize-space())]") as $element) {
      if (in_array($element->nodeName, $emptyShort)) {
        continue;
      }
      if (in_array($element->nodeName, $emptyLong)) {
        $toExpand[] = $element;
        continue;
      }
      $toDelete[] = $element;
    }
    foreach ($toExpand as $element) {
      $element->appendChild($doc->createTextNode(""));
    }
    /** @var DOMElement $element */
    foreach ($toDelete as $element) {
      if (!property_exists($element, "ownerDocument")) {
        continue;
      } // already deleted
      $eInfo = $element->nodeName;
      foreach ($element->attributes as $attribute) {
        $eInfo .= ".".$attribute->nodeName."=".$attribute->nodeValue;
      }
      $this->removePrevDt($element);
      $this->removeEmptyElement($element, sprintf(_("Removed empty element %s"), $eInfo));
    }
  }

  /**
   * @param DOMElement $e
   */
  private function removePrevDt (DOMElement $e) {
    if ($e->nodeName != "dd") {
      return;
    }
    $prev = $this->prevElementSibling($e);
    $next = $this->nextElementSibling($e);
    if ($prev->nodeName != "dt") {
      return;
    }
    if (!is_null($next) && $next->nodeName == "dd") {
      return;
    }
    $e->parentNode->removeChild($prev);
  }

  /**
   * @param DOMElement $element
   * @return DOMElement
   */
  private function prevElementSibling (DOMElement $element) {
    while ($element && ($element = $element->previousSibling)) {
      if ($element instanceof DOMElement) {
        break;
      }
    }
    return $element;
  }

  /**
   * @param DOMElement $element
   * @return DOMElement
   */
  private function nextElementSibling (DOMElement $element) {
    while ($element && ($element = $element->nextSibling)) {
      if ($element instanceof DOMElement) {
        break;
      }
    }
    return $element;
  }

  /**
   * @param DOMElement $e
   * @param string $comment
   */
  private function removeEmptyElement (DOMElement $e, $comment) {
    $parent = $e->parentNode;
    if (strlen(trim($parent->nodeValue))) {
      if (Cms::isSuperUser()) {
        $cmt = $e->ownerDocument->createComment(" $comment ");
        $parent->insertBefore($cmt, $e);
      }
      $parent->removeChild($e);
      return;
    }
    $this->removeEmptyElement($parent, $comment);
  }

  /**
   * @param string $content
   * @param int $priority
   * @param string $append
   */
  public function addJs ($content, $priority = self::DEFAULT_PRIORITY, $append = self::APPEND_BODY) {
    $key = "k".count($this->jsFiles);
    $this->jsFiles[$key] = [
      "file" => null,
      "append" => $append,
      "content" => $content,
    ];
    $this->jsFilesPriority[$key] = $priority;
  }

}
