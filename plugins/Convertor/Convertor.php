<?php

#todo: export

class Convertor extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    $this->subject = $subject;
    if($subject->getStatus() != "preinit") return;
    if(!isset($_GET["import"])) return;
    try {
      $f = $this->getFile();
      $xml = $this->transformFile($f);
      $doc = new DOMDocumentPlus();
      $doc->formatOutput = true;
      $doc->loadXML($xml);
      #echo $xml;
      echo $doc->saveXML();
      $doc->save("$f.html");
      #header("Location: ?admin=$f.html");
      exit();
    } catch(Exception $e) {
      new Logger($e->getMessage(),"warning");
      die($e->getMessage());
    }
  }

  private function getFile() {
    if(!strlen($_GET["import"]))
      throw new Exception("Missing import parameter");
    $f = $this->saveFromUrl();
    if(!is_null($f)) return $f;
    $f = findFile($_GET["import"]);
    if(!$f) throw new Exception("Invalid import parameter");
    return $f;
  }

  private function saveFromUrl() {
    $url = $_GET["import"];
    $purl = parse_url($url);
    if(!isset($purl["scheme"])) return null;
    if($purl["host"] == "docs.google.com") {
      $url = $this->createGdocsUrl($purl);
    }
    if(!$this->urlExists($url))
      throw new Exception("Invalid link");
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
    $urlstr = $url["scheme"] ."://". $url["host"] . $url["path"] . "/export?format=doc";
    if($this->urlExists($urlstr)) return $urlstr;
    return $url["scheme"] ."://". $url["host"] . dirname($url["path"]) . "/export?format=doc";
  }

  private function urlExists($url) {
    $headers = @get_headers($url);
    if(strpos($headers[0],'200') === false) return false;
    return true;
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
      $xml = $this->transform("removePrefix.xsl",$dom);
      file_put_contents($file,$xml);
      $variables[$varName] = "file:///".dirname($_SERVER['SCRIPT_FILENAME'])."/".$file;
    }
    $xml = $this->readZippedXML($f, "word/document.xml");
    if(is_null($xml))
      throw new Exception("Unable to locate word/document.xml in docx");
    $dom->loadXML($xml);
    $xml = $this->transform("removePrefix.xsl",$dom);
    file_put_contents($f."_document.xml",$xml); // just for debug
    $dom->loadXML($xml);
    return $this->transform("docx2html.xsl",$dom, $variables);
  }

  private function transform($xslFile, DOMDocument $content, $vars = array()) {
    $xsl = $this->getDOMPlus($this->getDir() ."/$xslFile",false,false);
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    foreach($vars as $varName => $path) {
      $proc->setParameter('', $varName, $path);
    }
    return $proc->transformToXML($content);
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
