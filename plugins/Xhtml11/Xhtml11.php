<?php

class Xhtml11 extends Plugin implements SplObserver, OutputStrategyInterface {
  private $jsFiles = array(); // String filename => Int priority
  private $jsFilesPriority = array(); // String filename => Int priority
  private $jsContent = array();
  private $jsContentBody = array();
  private $cssFiles = array();
  private $cssFilesPriority = array();
  private $transformationsPriority = array();
  private $transformations = array();
  private $favIcon = null;
  const APPEND_HEAD = "head";
  const APPEND_BODY = "body";
  const DTD_FILE = 'lib/xhtml11-flat.dtd';
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      global $cms;
      $cms->setOutputStrategy($this);
      $domain = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"];
      if(isAtLocalhost()) $domain .= substr(getRoot(),0,-1);
      $cms->setVariable("url", $domain);
      $cms->setVariable("link", getCurLink());
    }
    if($subject->getStatus() == "process") {
      $cfg = $this->getDOMPlus();
      $this->registerThemes($cfg);
    }
  }

  /**
   * Create XHTML 1.1 output from HTML+ content and own registers (JS/CSS)
   * @return void
   */
  public function getOutput(HTMLPlus $content) {
    global $cms;
    stableSort($this->cssFilesPriority);
    stableSort($this->jsFilesPriority);

    // create output DOM with doctype
    $imp = new DOMImplementation();
    $dtd = $imp->createDocumentType('html',
        '-//W3C//DTD XHTML 1.1//EN',
        'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd');
    $doc = $imp->createDocument(null, null, $dtd);
    $doc->encoding="utf-8";

    // add root element
    $html = $doc->createElement("html");
    $html->setAttribute("xmlns","http://www.w3.org/1999/xhtml");
    $html->setAttribute("xml:lang",$cms->getVariable("cms-lang"));
    $html->setAttribute("lang",$cms->getVariable("cms-lang"));
    $doc->appendChild($html);

    // add head element
    $head = $doc->createElement("head");
    $head->appendChild($doc->createElement("title",$this->getTitle($cms)));
    $this->appendMeta($head,"Content-Type","text/html; charset=utf-8");
    $this->appendMeta($head,"viewport","initial-scale=1");
    $this->appendMeta($head,"Content-Language", $cms->getVariable("cms-lang"));
    $this->appendMeta($head,"generator", $cms->getVariable("cms-version"));
    $author = $cms->getVariable("cms-author");
    if(strlen($author)) $this->appendMeta($head, "author", $author);
    $desc = $cms->getVariable("cms-desc");
    if(strlen($desc)) $this->appendMeta($head, "description", $desc);
    $kw = $cms->getVariable("cms-kw");
    if(strlen($kw)) $this->appendMeta($head, "keywords", $kw);
    if(!is_null($this->favIcon)) $this->appendLinkElement($head,$this->favIcon,"shortcut icon",false,false,false);
    $this->appendJsFiles($head);
    $this->appendCssFiles($head);
    $html->appendChild($head);

    // apply transformations
    $proc = new XSLTProcessor();
    $proc->setParameter('',$this->getProcParams($cms));
    stableSort($this->transformationsPriority);
    foreach($this->transformationsPriority as $xslt => $priority) {
      try {
        $newContent = $this->transform($content,$xslt,$this->transformations[$xslt]['user'],$proc);
        $newContent->encoding="utf-8";
        $xml = $newContent->saveXML();
        if(!@$newContent->loadXML($xml))
          throw new Exception("Invalid transformation (or parameter) in '$xslt'");
        $content = $newContent;
      } catch(Exception $e) {
        new Logger($e->getMessage(),"error");
      }
    }

    // correct links
    $contentPlus = new DOMDocumentPlus();
    $contentPlus->loadXML($content->saveXML());
    $contentPlus->validateLinks("a","href",true);
    $contentPlus->validateLinks("form","action",true);
    $contentPlus->validateLinks("object","data",true);
    $contentPlus->fragToLinks($cms->getContentFull(),getRoot(),"a","href");
    $contentPlus->fragToLinks($cms->getContentFull(),getRoot(),"form","action");

    // check object.data mime/size
    $this->validateImages($contentPlus);

    // import into html and save
    $content = $doc->importNode($contentPlus->documentElement,true);
    $html->appendChild($content);
    $this->appendJsFiles($content,self::APPEND_BODY);
    #var_dump($doc->schemaValidate(CMS_FOLDER . "/" . self::DTD_FILE));
    return $doc->saveXML();
  }

  private function validateImages(DOMDocumentPlus $dom) {
    $toStrip = array();
    foreach($dom->getElementsByTagName("object") as $o) {
      if(!$o->hasAttribute("data")) continue;
      $filePath = $o->getAttribute("data");
      $pUrl = parse_url($filePath);
      if(!is_array($pUrl)) {
        $toStrip[] = array($o, "invalid object data '$filePath' format");
        continue;
      }
      if(array_key_exists("scheme",$pUrl)) {
        #todo: $this->asynchronousExternalImageCheck($filePath);
        continue;
      }
      if(!is_file($filePath)) {
        $filePath = FILES_FOLDER ."/$filePath";
        if(!is_file($filePath)) {
          $toStrip[] = array($o, "object data '$filePath' not found");
          continue;
        }
      }
      try {
        $this->validateImage($o, $filePath);
      } catch(Exception $e) {
        $toStrip[] = array($o, $e->getMessage());
      }
    }
    foreach($toStrip as $o) $o[0]->stripTag($o[1]);
  }

  private function validateImage(DOMElement $o, $filePath) {
    $mime = getFileMime($filePath);
    if(strpos($mime, "image/") !== 0)
      throw new Exception("invalid object '$filePath' mime type '$mime'");
    if(!$o->hasAttribute("type") || $o->getAttribute("type") != $mime) {
      $o->setAttribute("type", $mime);
      new Logger("Object '$filePath' attr type set to '".$mime."'","warning");
    }
    $size = filesize($filePath);
    if($size > 350*1024) {
      new Logger("Object '$filePath' too big ".fileSizeConvert($size),"warning");
    }
  }

  /*
  private function asynchronousExternalImageCheck($file) {
    $headers = @get_headers(absoluteLink(getRoot().$filePath), 1);
    $invalid = !is_array($headers) || !array_key_exists("Content-Type", $headers)
      || !array_key_exists("Content-Length", $headers);
    if($invalid || strpos($headers[0], '200') === false) {
      $error = $invalid ? "bad response" : $headers[0];
      throw new Exception("object data '$filePath' not found ($error)");
    }
    $mime = $headers["Content-Type"];
    if(strpos($mime, "image/") !== 0)
      throw new Exception("invalid object '$filePath' mime type '$mime'");
    if(!$o->hasAttribute("type") || $o->getAttribute("type") != $mime) {
      $o->setAttribute("type", $mime);
      new Logger("Object '$filePath' attr type set to '".$mime."'","warning");
    }
    $size = (int) $headers["Content-Length"];
    if(!$size || $size > 350*1024) {
      new Logger("Object '$filePath' too big or invalid size ".fileSizeConvert($size),"warning");
    }
  }
  */

  private function getTitle(Cms $cms) {
    $title = $cms->getVariable("cms-title");
    foreach($this->subject->getIsInterface("ContentStrategyInterface") as $clsName => $cls) {
      $tmp = $cms->getVariable(strtolower($clsName)."-cms-title");
      if(!is_null($tmp)) $title = $tmp;
    }
    return $title;
  }

  private function getProcParams(Cms $cms) {
    $o = array();
    foreach($cms->getAllVariables() as $k => $v) {
      $valid = true;
      if($v instanceof DOMDocument) $v = html_entity_decode($v->saveHTML());
      elseif(is_array($v)) {
        $v = implode(",",$v);
      } elseif(is_object($v) && !method_exists($v, '__toString')) {
        new Logger("Unable to convert variable '$k' to string","error");
        continue;
      } else {
        $v = (string) $v;
      }
      if(false) {
        #continue;
        if($k != "cms-ig") continue;
        $v = "&copy;2014 &amp; <a href='http://www.internetguru.cz'>InternetGuru</a>";
        echo ($v)."\n";
        echo html_entity_decode($v)."\n";
        echo htmlentities($v)."\n";
        echo html_entity_decode($v)."\n";
        echo utf8_decode(html_entity_decode($v))."\n";
        echo htmlentities(utf8_decode(html_entity_decode($v)),ENT_XHTML)."\n";
        echo translateUtf8Entities($v)."\n";
        die();
      }
      $o[$k] = str_replace("'",'"',translateUtf8Entities($v));
    }
    return $o;
  }

  private function registerThemes(DOMDocumentPlus $cfg) {

    // add default xsl
    $this->addTransformation($this->getDir() ."/Xhtml11.xsl", 0, false);

    // add template files
    $theme = $cfg->getElementById("theme");
    if(!is_null($theme)) {
      $themeId = $theme->nodeValue;
      $t = $cfg->getElementById($themeId);
      if(is_null($t)) new Logger("Theme '$themeId' not found","error");
      else $this->addThemeFiles($t);
    }

    // add root template files
    $this->addThemeFiles($cfg->documentElement);

  }

  private function addThemeFiles(DOMElement $e) {
    foreach($e->childElements as $n) {
      if($n->nodeValue == "") continue;
      switch ($n->nodeName) {
        case "xslt":
        $user = !$n->hasAttribute("readonly");
        $this->addTransformation($n->nodeValue, 5, $user);
        break;
        case "jsFile":
        $user = !$n->hasAttribute("readonly");
        $append = self::APPEND_HEAD;
        if($n->hasAttribute("append")) $append = $n->getAttribute("append");
        $this->addJsFile($n->nodeValue,10,$append,$user);
        break;
        case "stylesheet":
        $media = ($n->hasAttribute("media") ? $n->getAttribute("media") : false);
        $this->addCssFile($n->nodeValue,$media);
        break;
        case "favicon":
        $this->favIcon = $n->nodeValue;
        break;
      }
    }
  }

  private function transform(DOMDocument $content,$fileName,$user, XSLTProcessor $proc) {
    #var_dump($fileName);
    $db = new DOMBuilder();
    $xsl = $db->buildDOMPlus($fileName,true,$user);
    if(!@$proc->importStylesheet($xsl))
      throw new Exception("XSLT '$fileName' compilation error");
    if(($x = @$proc->transformToDoc($content) ) === false)
      throw new Exception("XSLT '$fileName' transformation fail");
    #echo $x->saveXML();
    return $x;
  }

  /**
   * Append meta element to an element (supposed head)
   * @param  DOMElement $e            Element to which meta is to be appended
   * @param  string     $nameValue    Value of attribute name/http-equiv
   * @param  string     $contentValue Value of attribute content
   * @param  boolean    $httpEquiv    Use attr. http-equiv instead of name
   * @return void
   */
  private function appendMeta(DOMElement $e,$nameValue,$contentValue,$httpEquiv=false) {
    $meta = $e->ownerDocument->createElement("meta");
    $meta->setAttribute(($httpEquiv ? "http-equiv" : "name"),$nameValue);
    $meta->setAttribute("content",$contentValue);
    $e->appendChild($meta);
  }

  private function appendMetaCharset(DOMElement $e,$charset) {
    $meta = $e->ownerDocument->createElement("meta");
    $meta->setAttribute("charset",$charset);
    $e->appendChild($meta);
  }

  private function appendLinkElement(DOMElement $parent,$file,$rel,$type=false,$media=false,$user=true) {
    try {
      $f = findFile($file,$user,true,true);
    } catch (Exception $ex) {
      $f = false;
    }
    if($f === false && $rel == "shortcut icon") {
      $f = $file;
      while(strpos($f,"/") === 0) $f = substr($f,1);
    }
    if(!strlen($f)) {
      $comment = "[$rel] file '$file' not found";
      $parent->appendChild(new DOMComment(" $comment "));
      new Logger($comment,"error");
      return;
    }
    $e = $parent->ownerDocument->createElement("link");
    if($type) $e->setAttribute("type",$type);
    if($rel) $e->setAttribute("rel",$rel);
    if($media) $e->setAttribute("media",$media);
    $e->setAttribute("href", getRoot().$f);
    $parent->appendChild($e);
  }

  /**
   * Add unique JS file into register with priority
   * @param string  $filePath JS file to be registered
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJsFile($filePath,$priority = 10,$append = self::APPEND_HEAD,$user=false) {
    global $cms;
    $cms->addVariableItem("javascripts",$filePath);
    $this->jsFiles[$filePath] = array(
      "file" => $filePath,
      "append" => $append,
      "content" => "",
      "user" => $user);
    $this->jsFilesPriority[$filePath] = $priority;
  }

  /**
   * Add JS (as a string) into register with priority
   * @param string  $content  JS to be added
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJs($content,$priority = 10,$append = self::APPEND_BODY) {
    $key = "k" . count($this->jsFiles);
    $this->jsFiles[$key] = array(
      "file" => null,
      "append" => $append,
      "content" => $content);
    $this->jsFilesPriority[$key] = $priority;
  }

  /**
   * Add unique CSS file into register with priority
   * @param string  $fileName JS file to be registered
   * @param integer $priority The higher priority the lower appearance
   */
  public function addCssFile($filePath, $media = false, $priority = 10, $user = true) {
    global $cms;
    $cms->addVariableItem("styles",$filePath);
    $this->cssFiles[$filePath] = array(
      "priority" => $priority,
      "file" => $filePath,
      "media" => $media,
      "user" => $user);
    $this->cssFilesPriority[$filePath] = $priority;
  }


  public function addTransformation($filePath, $priority = 10, $user = true) {
    global $cms;
    $cms->addVariableItem("transformations",$filePath);
    $this->transformations[$filePath] = array(
      "priority" => $priority,
      "file" => $filePath,
      "user" => $user);
    $this->transformationsPriority[$filePath] = $priority;
  }

  /**
   * Append all registered JS files into a parent (usually head)
   * @param  DOMElement $parent Element to append JS files to
   * @return void
   */
  private function appendJsFiles(DOMElement $parent,$append = self::APPEND_HEAD) {
    foreach($this->jsFilesPriority as $k => $v) {
      if($append != $this->jsFiles[$k]["append"]) continue;
      $f = false;
      if(!is_null($this->jsFiles[$k]["file"])) {
        try {
          $f = findFile($this->jsFiles[$k]["file"],$this->jsFiles[$k]["user"],true,true);
        } catch (Exception $ex) {}
        if($f === false) {
          $comment = "JS file '$k' not found";
          $parent->appendChild(new DOMComment(" $comment "));
          new Logger($comment,"error");
          continue;
        }
      }
      $content = $this->jsFiles[$k]["content"];
      $e = $parent->ownerDocument->createElement("script");
      $e->appendChild($parent->ownerDocument->createTextNode($content));
      $e->setAttribute("type","text/javascript");
      if($f !== false) $e->setAttribute("src", getRoot().$f);
      $parent->appendChild($e);
    }
  }

  /**
   * Append all registered CSS files into a parent (usually head)
   * @param  DOMElement $parent Element to append JS files to
   * @return void
   */
  private function appendCssFiles(DOMElement $parent) {
    foreach($this->cssFilesPriority as $k => $v) {
      $this->appendLinkElement($parent, $this->cssFiles[$k]["file"], "stylesheet",
        "text/css", $this->cssFiles[$k]["media"], $this->cssFiles[$k]["user"]);
    }
  }

}

?>
