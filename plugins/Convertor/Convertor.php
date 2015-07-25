<?php

#bug: <p>$contactform-basic</p> does not parse to <p var="..."/>

class Convertor extends Plugin implements SplObserver, ContentStrategyInterface {
  private $error = false;
  private $html = null;
  private $file = null;
  private $docName = null;
  private $tmpFolder;
  private $importedFiles = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 5);
    $this->tmpFolder = USER_FOLDER."/".$this->pluginDir;
    mkdir_plus($this->tmpFolder);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PREINIT) {
      if(!Cms::isSuperUser()) $subject->detach($this);
      return;
    }
    if($subject->getStatus() != STATUS_INIT) return;
    if(!isset($_GET[get_class($this)])) {
      $subject->detach($this);
      return;
    }
    if(strlen($_GET[get_class($this)])) $this->processImport($_GET[get_class($this)]);
    elseif(substr($_SERVER['QUERY_STRING'], -1) == "=") {
      Cms::addMessage(_("File URL cannot be empty"), Cms::MSG_ERROR);
    }
    $this->getImportedFiles();
  }

  private function getImportedFiles() {
    foreach(scandir($this->tmpFolder, SCANDIR_SORT_ASCENDING) as $f) {
      if(pathinfo($this->tmpFolder."/$f", PATHINFO_EXTENSION) != "html") continue;
      $this->importedFiles[] = "<a href='?Convertor=$f'>$f</a>";
    }
  }

  private function processImport($fileUrl) {
    try {
      $f = $this->getFile($fileUrl);
    } catch(Exception $e) {
      Cms::addMessage($e->getMessage(), Cms::MSG_ERROR);
      $this->error = true;
      return;
    }
    $mime = getFileMime($this->tmpFolder."/$f");
    switch($mime) {
      case "application/zip":
      case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
      $this->parseZippedDoc($f);
      break;
      case "application/xml": // just display (may be xml or broken html+)
      $this->html = file_get_contents($this->tmpFolder."/$f");
      $this->file = $f;
      break;
      default:
      Cms::addMessage(sprintf(_("Unsupported file MIME type '%s'"), $mime), Cms::MSG_ERROR);
      $this->error = true;
    }
  }

  private function parseZippedDoc($f) {
    $doc = $this->transformFile($this->tmpFolder."/$f");
    $d = new DOMDocumentPlus();
    $xml = $doc->saveXML();
    $xml = str_replace("·\n", "\n", $xml); // remove "format hack" from transformation
    $mergable = array("strong", "em", "sub", "sup", "ins", "del", "q", "cite", "acronym", "code", "dfn", "kbd", "samp");
    foreach($mergable as $tag) $xml = preg_replace("/<\/$tag>(\s)*<$tag>/", "$1", $xml);
    foreach($mergable as $tag) $xml = preg_replace("/(\s)*(<\/$tag>)/", "$2$1", $xml);

    $doc = new HTMLPlus();
    $doc->defaultAuthor = Cms::getVariable("cms-author");
    $doc->defaultLink = pathinfo($f, PATHINFO_FILENAME);
    $doc->loadXML($xml);
    if(is_null($doc->documentElement->firstElement)
      || $doc->documentElement->firstElement->nodeName != "h") {
      Cms::addMessage(_("Unable to import document; probably missing heading"), Cms::MSG_ERROR);
      return;
    }
    try {
      $doc->validatePlus(true);
    } catch(Exception $e) {}
    $this->parseContent($doc, "h", "short");
    $firstHeading = $doc->documentElement->firstElement;
    if(!$firstHeading->hasAttribute("short") && !is_null($this->docName))
      $firstHeading->setAttribute("short", $this->docName);
    $this->parseContent($doc, "desc", "kw");
    $this->addLinks($doc);
    try {
      $doc->validatePlus(true);
    } catch(Exception $e) {
      Cms::addMessage($e->getMessage(), Cms::MSG_ERROR);
      Cms::addMessage(_("Use @ to specify short/link attributes for heading"), Cms::MSG_INFO);
      Cms::addMessage(_("Eg. This Is Long Heading @ Short Heading"), Cms::MSG_INFO);
      Cms::addMessage(_("Use @ to specify kw attribute for description"), Cms::MSG_INFO);
      Cms::addMessage(_("Eg. This is description @ these, are, some, keywords"), Cms::MSG_INFO);
    }
    $doc->applySyntax();

    $ids = $this->regenerateIds($doc);
    $this->html = $doc->saveXML();
    $this->html = str_replace(array_keys($ids), $ids, $this->html);
    if(!$this->error) Cms::addMessage(_("File successfully imported"), Cms::MSG_SUCCESS);
    $this->file = "$f.html";
    $dest = $this->tmpFolder."/".$this->file;
    $fp = lockFile("$dest.lock");
    try {
      try {
        file_put_contents_plus($dest, $this->html);
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to save file %s: %s"), $this->file, $e->getMessage()));
      }
      try {
        incrementalRename("$dest.old", "$dest.");
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to backup file %s: %s"), $this->file, $e->getMessage()));
      }
    } catch(Exception $e) {
      Logger::log($e->getMessage(), Logger::LOGGER_ERROR);
    } finally {
      unlockFile($fp);
      unlink("$dest.lock");
    }
  }

  private function parseContent(HTMLPlus $doc, $eName, $aName) {
    foreach($doc->getElementsByTagName($eName) as $e) {
      $var = explode("@", $e->nodeValue);
      if(count($var) < 2) continue;
      $aVal = trim(array_pop($var));
      if(!strlen($aVal)) continue;
      $e->setAttribute($aName, $aVal);
      $e->nodeValue = trim(implode("@", $var));
    }
  }

  private function addLinks(HTMLPlus $doc) {
    foreach($doc->getElementsByTagName("h") as $e) {
      if(!$e->hasAttribute("short")) continue;
      $e->setAttribute("link", normalize($e->getAttribute("short"), "a-zA-Z0-9/_-", ""));
    }
  }

  public function getContent(HTMLPlus $c) {
    Cms::getOutputStrategy()->addCssFile($this->pluginDir.'/Convertor.css');
    $newContent = $this->getHTMLPlus();
    $vars["action"] = "?".get_class($this);
    $vars["link"] = $_GET[get_class($this)];
    $vars["path"] = $this->pluginDir;
    if(!empty($this->importedFiles)) $vars["importedhtml"] = $this->importedFiles;
    $vars["filename"] = $this->file;
    if(!is_null($this->html)) {
      $vars["nohide"] = "nohide";
      $vars["content"] = $this->html;
    }
    $newContent->processVariables($vars);
    return $newContent;
  }

  private function regenerateIds(DOMDocumentPlus $doc) {
    $ids = array();
    foreach($doc->getElementsByTagName("h") as $h) {
      $oldId = $h->getAttribute("id");
      $h->setUniqueId();
      $ids[$oldId] = $h->getAttribute("id");
    }
    return $ids;
  }

  private function getFile($dest) {
    $f = $this->saveFromUrl($dest);
    if(!is_null($f)) return $f;
    if(!is_file($this->tmpFolder."/$dest"))
      throw new Exception(sprintf(_("File '%s' not found in temp folder"), $dest));
    return $dest;
  }

  private function saveFromUrl($url) {
    $purl = parse_url($url);
    if($purl === false) throw new Exception(_("Unable to parse link"));
    if(!isset($purl["scheme"])) return null;
    $defaultContext = array('http' => array('method' => '', 'header' => ''));
    stream_context_get_default($defaultContext);
    stream_context_set_default(
      array(
        'http' => array(
          'method' => 'HEAD',
          'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2357.132 Safari/537.36"
        )
      )
    );
    if($purl["host"] == "docs.google.com") {
      $url = $purl["scheme"]."://".$purl["host"].$purl["path"]."/export?format=doc";
      $headers = @get_headers($url);
      if(strpos($headers[0], '404') !== false) {
        $url = $purl["scheme"]."://".$purl["host"].dirname($purl["path"])."/export?format=doc";
        $headers = @get_headers($url);
      }
    } else $headers = @get_headers($url);
    $rh = $http_response_header;
    if(strpos($headers[0], '302') !== false)
      throw new Exception(_("Destination URL is unaccessible; must be shared publically"));
    elseif(strpos($headers[0], '200') === false)
      throw new Exception(sprintf(_("Destination URL error: %s"), $headers[0]));
    stream_context_set_default($defaultContext);
    $data = file_get_contents($url);
    $filename = $this->get_real_filename($rh, $url);
    file_put_contents($this->tmpFolder."/$filename", $data);
    return $filename;
  }

  private function get_real_filename($headers, $url) {
    foreach($headers as $header) {
      if(strpos(strtolower($header), 'content-disposition') !== false) {
        $tmp_name = explode('\'\'', $header);
        if(!isset($tmp_name[1]))
          $tmp_name = explode('filename="', $header);
        if(isset($tmp_name[1])) {
          $this->docName = pathinfo(urldecode($tmp_name[1]), PATHINFO_FILENAME);
          return normalize(trim(urldecode($tmp_name[1]), '";\''), "a-zA-Z0-9/_.-", "", false);
        }
      }
    }
    $stripped_url = preg_replace('/\\?.*/', '', $url);
    return basename($stripped_url);
  }

  private function transformFile($f) {
    $dom = new DOMDocument();
    $varFiles = array(
      "headerFile" => "word/header1.xml",
      "footerFile" => "word/footer1.xml",
      "numberingFile" => "word/numbering.xml",
      "footnotesFile" => "word/footnotes.xml",
      "relationsFile" => "word/_rels/document.xml.rels"
    );
    $variables = array();
    foreach($varFiles as $varName => $p) {
      $fileSuffix = pathinfo($p, PATHINFO_BASENAME);
      $file = $f."_$fileSuffix";
      $xml = readZippedFile($f, $p);
      if(is_null($xml)) continue;
      $dom->loadXML($xml);
      $dom = $this->transform("removePrefix.xsl", $dom);
      #file_put_contents($file, $xml);
      $dom->save($file);
      $variables[$varName] = str_replace("\\", '/', realpath($file));
    }
    $wordDoc = "word/document.xml";
    $xml = readZippedFile($f, $wordDoc);
    if(is_null($xml))
      throw new Exception(sprintf(_("Unable to locate '%s' in '%s'"), "word/document.xml", $f));
    $dom->loadXML($xml);
    $dom = $this->transform("removePrefix.xsl", $dom);
    // add an empty paragraph to prevent li:following-sibling fail
    $dom->getElementsByTagName("body")->item(0)->appendChild($dom->createElement("p"));
    $dom->save($f."_document.xml"); // for debug purpose
    return $this->transform("docx2html.xsl", $dom, $variables);
  }

  private function transform($xslFile, DOMDocument $content, $vars = array()) {
    $xsl = $this->getDOMPlus($this->pluginDir."/$xslFile", false, false);
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $proc->setParameter('', $vars);
    $doc = $proc->transformToDoc($content);
    if($doc === false) throw new Exception(sprintf(_("Failed to apply transformation '%s'"), $xslFile));
    return $doc;
  }

}

?>
