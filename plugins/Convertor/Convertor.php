<?php

#todo: export
#todo: support file upload
#todo: js copy content to clipboard

class Convertor extends Plugin implements SplObserver, ContentStrategyInterface {
  private $warn = array();
  private $info = array();
  private $html = null;
  private $file = null;
  private $importedFiles = array();

  public function update(SplSubject $subject) {
    $this->subject = $subject;
    if($subject->getStatus() != "preinit") return;
    $subject->setPriority($this,1);
    if(isset($_GET["import"])) redirTo(getRoot() . getCurLink() . "?" . get_class($this)
      . (strlen($_GET["import"]) ? "=".$_GET["import"] : "")); // backward compatibility
    if(!isset($_GET[get_class($this)])) {
      $subject->detach($this);
      return;
    }
    $this->getImportedFiles();
    $this->proceedImport();
  }

  private function getImportedFiles() {
    if(!is_dir(IMPORT_FOLDER) && !@mkdir(IMPORT_FOLDER, 0755, true))
      throw new Exception("Unable to create import directory");
    foreach(scandir(IMPORT_FOLDER, SCANDIR_SORT_ASCENDING) as $f) {
      if(pathinfo(IMPORT_FOLDER."/$f", PATHINFO_EXTENSION) != "html") continue;
      $this->importedFiles[] = "<a href='?Convertor=".IMPORT_FOLDER."/$f'>$f</a>";
    }
  }

  private function proceedImport() {
    try {
      $f = $this->getFile($_GET[get_class($this)]);
    } catch(Exception $e) {
      $this->warn[] = $e->getMessage();
      if(strlen($_GET[get_class($this)])) new Logger($e->getMessage(), "warning");
      return;
    }
    $mime = getFileMime($f);
    switch($mime) {
      case "application/zip":
      $this->parseZippedDoc($f);
      break;
      case "application/xml": // just display (may be xml or broken html+)
      $this->html = file_get_contents($f);
      $this->file = $f;
      break;
      default:
      $this->warn[] = "Unsupported file type '$mime'";
    }
  }

  private function parseZippedDoc($f) {
    $xml = $this->transformFile($f);
    $doc = new DOMDocumentPlus();
    $doc->loadXML($xml->saveXML());
    $ids = $this->regenerateIds($doc);
    $doc->documentElement->firstElement->setAttribute("ctime",date("Y-m-d\TH:i:sP"));
    $this->addLinks($doc);
    $this->html = $doc->saveXML();
    $this->html = str_replace(array_keys($ids),$ids,$this->html);
    $this->html = str_replace(">·\n",">\n",$this->html); // remove "format hack" from transformation
    $this->info[] = "File successfully imported";
    $this->file = "$f.html";
    if(@file_put_contents("$f.html",$this->html) !== false) return;
    $m = "Unable to save imported file into '$f.html'";
    $this->warn[] = $m;
    new Logger($m,"error");
  }

  private function addLinks(DOMDocumentPlus $doc) {
    foreach($doc->getElementsByTagName("h") as $h) {
      if($h->isSameNode($doc->documentElement->firstElement)) continue; // skip first h
      foreach($h->parentNode->childNodes as $e) {
        if($e->nodeName != "section") continue;
        $h->setAttribute("short", $h->nodeValue);
        $h->setAttribute("link", normalize($h->nodeValue));
      }
    }
  }

  public function getContent(HTMLPlus $c) {
    global $cms;
    $cms->getOutputStrategy()->addCssFile($this->getDir() . '/Convertor.css');
    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("warning", $this->warn);
    $newContent->insertVar("info", $this->info);
    $newContent->insertVar("link", $_GET[get_class($this)]);
    $newContent->insertVar("importedhtml",$this->importedFiles);
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
    if(!strlen($dest)) throw new Exception("Please, enter file to view or import");
    $f = $this->saveFromUrl($dest);
    if(!is_null($f)) return $f;
    if(!is_file($dest)) throw new Exception("File '$dest' not found");
    return $dest;
  }

  private function saveFromUrl($url) {
    $purl = parse_url($url);
    if($purl === false) throw new Exception("Unable to parse link (invalid format)");
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
      throw new Exception("Destination URL '$url' is unaccessible (must be shared publically)");
    elseif(strpos($headers[0],'200') === false)
      throw new Exception("Destination URL '$url' error: ".$headers[0]);
    $data = file_get_contents($url);
    $filename = $this->get_real_filename($http_response_header,$url);
    $f = IMPORT_FOLDER ."/$filename";
    file_put_contents($f, $data);
    return $f;
  }

  private function get_real_filename($headers,$url) {
    foreach($headers as $header) {
      if (strpos(strtolower($header),'content-disposition') !== false) {
        $tmp_name = explode('=', $header);
        if ($tmp_name[1]) return trim($tmp_name[1],'";\'');
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
      $variables[$varName] = "file:///".dirname($_SERVER['SCRIPT_FILENAME'])."/".$file;
    }
    $xml = readZippedFile($f, "word/document.xml");
    if(is_null($xml))
      throw new Exception("Unable to locate 'word/document.xml' in '$f'");
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
    if($doc === false) throw new Exception("Failed to apply transformation '$xslFile'");
    return $doc;
  }

}

?>
