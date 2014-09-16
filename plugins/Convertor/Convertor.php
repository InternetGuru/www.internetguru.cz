<?php

#todo: export

class Convertor extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    $this->subject = $subject;
    if($subject->getStatus() != "process") return;
    if(!isset($_GET["import"])) return;
    try {
      $f = $this->getFile();
      $doc = $this->transformFile($f);
      echo $doc->saveXML();
      die();
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
    if(!is_dir(FILES_FOLDER . "/tmp")) mkdir(FILES_FOLDER . "/tmp");
    $f = FILES_FOLDER . "/tmp/$filename";
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
    $content = new DOMDocumentPlus();
    $content->loadXML($this->readZippedXML($f, "word/document.xml"));
    $content = $this->transform("removePrefix.xsl",$content);
    return $this->transform("docx2html.xsl",$content);
  }

  private function transform($xslFile, DOMDocument $content) {
    $xsl = $this->getDOMPlus($this->getDir() ."/$xslFile",false,false);
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    return $proc->transformToDoc($content);
  }

  private function readZippedXML($archiveFile, $dataFile) {
    // Create new ZIP archive
    $zip = new ZipArchive;
    // Open received archive file
    if (!$zip->open($archiveFile))
      throw new Exception("Unable to open file");
    // If done, search for the data file in the archive
    if (!($index = $zip->locateName($dataFile)))
      throw new Exception("Unable to find data in file");
    // If found, read it to the string
    $data = $zip->getFromIndex($index);
    // Close archive file
    $zip->close();
    // Load XML from a string
    return $data;
  }

}

?>
