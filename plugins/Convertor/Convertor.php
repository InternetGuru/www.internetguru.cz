<?php

#todo: export
#todo: support file upload
#todo: js copy content to clipboard
#todo: support short heading after @
#todo: default short if no @
#todo: join tags (inline?)
#todo: fix normalize() ??
#todo: internal links
#todo: external links
#todo: delete empty elements

class Convertor extends Plugin implements SplObserver, ContentStrategyInterface {
  private $error = false;
  private $html = null;
  private $file = null;
  private $tmpFolder;
  private $importedFiles = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,5);
    $this->tmpFolder = TEMP_FOLDER."/".PLUGINS_DIR."/".get_class($this);
    if(is_dir($this->tmpFolder) || mkdir($this->tmpFolder, 0755, true)) return;
    throw new Exception(_("Unable to create convertor tmp folder"));
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if(!isset($_GET[get_class($this)])) {
      $subject->detach($this);
      return;
    }
    if(strlen($_GET[get_class($this)])) $this->processImport($_GET[get_class($this)]);
    elseif(substr(getCurLink(true), -1) == "=") {
      Cms::addMessage(_("File URL cannot be empty"), Cms::MSG_WARNING);
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
      Cms::addMessage($e->getMessage(), Cms::MSG_WARNING);
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
      Cms::addMessage(sprintf(_("Unsupported file MIME type '%s'"), $mime), Cms::MSG_WARNING);
      $this->error = true;
    }
  }

  private function parseZippedDoc($f) {
    $doc = $this->transformFile($this->tmpFolder ."/$f");
    $xml = $doc->saveXML();
    $xml = str_replace("·\n","\n",$xml); // remove "format hack" from transformation

    $doc = new HTMLPlus();
    $doc->loadXML($xml);
    $doc->documentElement->firstElement->setAttribute("ctime",date("Y-m-d\TH:i:sP"));
    $this->addLinks($doc);
    $this->safeValidate($doc);
    $doc->applySyntax();

    $ids = $this->regenerateIds($doc);
    $this->html = $doc->saveXML();
    $this->html = str_replace(array_keys($ids),$ids,$this->html);
    if(!$this->error) Cms::addMessage(_("File successfully imported"), Cms::MSG_SUCCESS);
    $this->file = "$f.html";
    if(@file_put_contents($this->tmpFolder ."/$f.html", $this->html) !== false) return;
    $m = sprintf(_("Unable to save imported file '%s.html' into temp folder"), $f);
    new Logger($m, "error");
  }

  private function safeValidate(HTMLPlus $doc) {
    try {
      $doc->validatePlus(true);
    } catch(Exception $e) {
      Cms::addMessage($e->getMessage(), Cms::MSG_WARNING);
    }
  }

  private function addLinks(HTMLPlus $doc) {
    foreach($doc->getElementsByTagName("h") as $h) {
      $var = explode("@", $h->nodeValue);
      if(count($var) > 1) {
        $h->setAttribute("short", trim(array_pop($var)));
        $h->nodeValue = trim(implode("@", $var));
      }
      $e = $h->nextElement;
      while(!is_null($e)) {
        if($e->nodeName == "h") break;
        if($e->nodeName == "section") {
          if(!$h->hasAttribute("short"))
            $h->setAttribute("short", $this->getShortString($h->nodeValue));
          $h->setAttribute("link", normalize($h->getAttribute("short")));
          break;
        }
        $e = $e->nextElement;
      }
    }
  }

  private function getShortString($str) {
    $lLimit = 9;
    $hLimit = 16;
    if(strlen($str) < $hLimit) return $str;
    $w = explode(" ", $str);
    $sStr = $w[0];
    $i = 1;
    while(strlen($sStr) < $lLimit) {
      if(!isset($w[$i])) break;
      $sStr .= " ".$w[$i++];
    }
    if(strlen($str) - strlen($sStr) < $hLimit - $lLimit) return $str;
    return $sStr."…";
  }

  public function getContent(HTMLPlus $c) {
    Cms::getOutputStrategy()->addCssFile($this->getDir() . '/Convertor.css');
    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("link", $_GET[get_class($this)]);
    $newContent->insertVar("path", TEMP_DIR."/".PLUGINS_DIR."/".get_class($this));
    if(!empty($this->importedFiles)) $newContent->insertVar("importedhtml", $this->importedFiles);
    $newContent->insertVar("filename",$this->file);
    if(!is_null($this->html)) {
      $newContent->insertVar("nohide", "nohide");
      $newContent->insertVar("content", $this->html);
    }
    return $newContent;
  }

  private function regenerateIds(DOMDocumentPlus $doc) {
    $ids = array();
    foreach($doc->getElementsByTagName("h") as $h) {
      $oldId = $h->getAttribute("id");
      $doc->setUniqueId($h);
      $ids[$oldId] = $h->getAttribute("id");
    }
    return $ids;
  }

  private function getFile($dest) {
    $f = $this->saveFromUrl($dest);
    if(!is_null($f)) return $f;
    if(!is_file($this->tmpFolder ."/$dest"))
      throw new Exception(sprintf(_("File '%s' not found in temp folder"), $dest));
    return $dest;
  }

  private function saveFromUrl($url) {
    $purl = parse_url($url);
    if($purl === false) throw new Exception(_("Unable to parse link"));
    if(!isset($purl["scheme"])) return null;
    if($purl["host"] == "docs.google.com") {
      $url = $purl["scheme"] ."://". $purl["host"] . $purl["path"] . "/export?format=doc";
      $headers = @get_headers($url);
      if(strpos($headers[0],'404') !== false) {
        $url = $purl["scheme"] ."://". $purl["host"] . dirname($purl["path"]) . "/export?format=doc";
        $headers = @get_headers($url);
      }
    } else $headers = @get_headers($url);
    if(strpos($headers[0],'302') !== false)
      throw new Exception(sprintf(_("Destination URL '%s' is unaccessible; must be shared publically"), $url));
    elseif(strpos($headers[0],'200') === false)
      throw new Exception(sprintf(_("Destination URL '%s' error: %s"), $url, $headers[0]));
    $data = file_get_contents($url);
    $filename = $this->get_real_filename($http_response_header,$url);
    file_put_contents($this->tmpFolder ."/$filename", $data);
    return $filename;
  }

  private function get_real_filename($headers,$url) {
    foreach($headers as $header) {
      if (strpos(strtolower($header),'content-disposition') !== false) {
        $tmp_name = explode('=', $header);
        if($tmp_name[1]) return normalize(trim($tmp_name[1],'";\''),".",false,true);
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
      $dom = $this->transform("removePrefix.xsl",$dom);
      #file_put_contents($file,$xml);
      $dom->save($file);
      $variables[$varName] = str_replace("\\", '/', realpath($file));
    }
    $wordDoc = "word/document.xml";
    $xml = readZippedFile($f, $wordDoc);
    if(is_null($xml))
      throw new Exception(sprintf(_("Unable to locate '%s' in '%s'"), "word/document.xml", $f));
    $dom->loadXML($xml);
    $dom = $this->transform("removePrefix.xsl",$dom);
    // add an empty paragraph to prevent li:following-sibling fail
    $dom->getElementsByTagName("body")->item(0)->appendChild($dom->createElement("p"));
    $dom->save($f."_document.xml"); // for debug purpose
    return $this->transform("docx2html.xsl",$dom, $variables);
  }

  private function transform($xslFile, DOMDocument $content, $vars = array()) {
    $xsl = $this->getDOMPlus($this->getDir() ."/$xslFile",false,false);
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $proc->setParameter('', $vars);
    $doc = $proc->transformToDoc($content);
    if($doc === false) throw new Exception(sprintf(_("Failed to apply transformation '%s'"), $xslFile));
    return $doc;
  }

}

?>
