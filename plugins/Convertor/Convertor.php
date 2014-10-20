<?php

#bug: infinite loop if gd ends with a list item
#todo: export
#todo: check document type/mime

class Convertor extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    $this->subject = $subject;
    if($subject->getStatus() != "preinit") return;
    if(isset($_GET["import"])) redirTo(getRoot() . getCurLink() . "?" . get_class($this)
      . (strlen($_GET["import"]) ? "=".$_GET["import"] : "")); // backward compatibility
    if(!isset($_GET[get_class($this)])) return;
    try {
      $f = $this->getFile($_GET[get_class($this)]);
      $xml = $this->transformFile($f);
      $doc = new DOMDocumentPlus();
      $doc->loadXML($xml->saveXML());
      $ids = $this->regenerateIds($doc);
      $str = $doc->saveXML();
      foreach($ids as $old => $new) {
        $str = str_replace($old,$new,$str);
      }
      $str = str_replace("> \n",">\n",$str); // remove "nbsp hack" from transformation
      file_put_contents("$f.html",$str);
      if($subject->isAttachedPlugin("ContentAdmin")) redirTo("?ContentAdmin=$f.html");
      echo $str;
      die();
    } catch(Exception $e) {
      new Logger($e->getMessage(),"error");
      throw $e;
    }
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
    if(!strlen($dest))
      throw new Exception("Missing import parameter");
    $f = $this->saveFromUrl($dest);
    if(!is_null($f)) return $f;
    $f = findFile($dest);
    if(!$f) throw new Exception("Invalid import parameter");
    return $f;
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
      throw new Exception("Destination URL '$url' not found");
    $data = file_get_contents($url);
    $filename = $this->get_real_filename($http_response_header,$url);
    if(!is_dir(IMPORT_FOLDER)) mkdir(IMPORT_FOLDER, 0755, true);
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


  private function createGdocsUrl($url) {
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
      $xml = $this->readZippedXML($f, $p);
      if(is_null($xml)) continue;
      $dom->loadXML($xml);
      $dom = $this->transform("removePrefix.xsl",$dom);
      #file_put_contents($file,$xml);
      $dom->save($file);
      $variables[$varName] = "file:///".dirname($_SERVER['SCRIPT_FILENAME'])."/".$file;
    }
    $xml = $this->readZippedXML($f, "word/document.xml");
    if(is_null($xml))
      throw new Exception("Unable to locate word/document.xml in docx");
    $dom->loadXML($xml);
    $dom = $this->transform("removePrefix.xsl",$dom);
    $dom->save($f."_document.xml"); // for debug purpose
    #file_put_contents($f."_document.xml",$xml); // just for debug
    #$dom->loadXML($xml);
    return $this->transform("docx2html.xsl",$dom, $variables);
  }

  private function transform($xslFile, DOMDocument $content, $vars = array()) {
    $xsl = $this->getDOMPlus($this->getDir() ."/$xslFile",false,false);
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $proc->setParameter('', $vars);
    return $proc->transformToDoc($content);
  }

  private function readZippedXML($archiveFile, $dataFile) {
    // Create new ZIP archive
    $zip = new ZipArchive;
    // Open received archive file
    if (!$zip->open($archiveFile))
      throw new Exception("Unable to open file");
    // If done, search for the data file in the archive
    if (!($index = $zip->locateName($dataFile))) return null;
    // If found, read it to the string
    $data = $zip->getFromIndex($index);
    // Close archive file
    $zip->close();
    // Load XML from a string
    return $data;
  }

}

?>
