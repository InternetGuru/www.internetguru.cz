<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\ContentStrategyInterface;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use DOMDocument;
use SplObserver;
use SplSubject;

#bug: <p>$contactform-basic</p> does not parse to <p var="..."/>

class Convertor extends Plugin implements SplObserver, ContentStrategyInterface {
  private $html = null;
  private $file = null;
  private $docName = null;
  private $tmpFolder;
  private $importedFiles = array();
  private $className = null;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 5);
    $this->tmpFolder = USER_FOLDER."/".$this->pluginDir;
    mkdir_plus($this->tmpFolder);
    $this->className = basename(get_class($this));
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_PREINIT) {
      if(!Cms::isSuperUser()) $subject->detach($this);
      return;
    }
    if($subject->getStatus() != STATUS_INIT) return;
    if(!isset($_GET[$this->className])) {
      $subject->detach($this);
      return;
    }
    $this->requireActiveCms();
    try {
      if(strlen($_GET[$this->className]))
        $this->processImport($_GET[$this->className]);
    } catch(Exception $e) {
      Logger::error($e->getMessage());
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
    if(!strlen($_GET[$this->className]) && substr($_SERVER['QUERY_STRING'], -1) == "=") {
      throw new Exception(_("File URL cannot be empty"));
    }
    $f = $this->getFile($fileUrl);
    $this->docName = pathinfo($f, PATHINFO_FILENAME);
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
      throw new Exception(sprintf(_("Unsupported file MIME type '%s'"), $mime));
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
      Logger::error(_("Unable to import document; probably missing heading"));
      return;
    }
    $this->parseContent($doc, "h", "short");
    $firstHeading = $doc->documentElement->firstElement;
    if(!$firstHeading->hasAttribute("short") && !is_null($this->docName))
      $firstHeading->setAttribute("short", $this->docName);
    $this->parseContent($doc, "desc", "kw");
    $this->addLinks($doc);
    try {
      $doc->validatePlus();
    } catch(Exception $e) {
      try {
        $doc->validatePlus(true);
        foreach($doc->getErrors() as $error) {
          Logger::notice(_("Autocorrected").":$error");
        }
      } catch(Exception $e) {
        Cms::notice(_("Use @ to specify short/link attributes for heading"));
        Cms::notice(_("Eg. This Is Long Heading @ Short Heading"));
        Cms::notice(_("Use @ to specify kw attribute for description"));
        Cms::notice(_("Eg. This is description @ these, are, some, keywords"));
        throw $e;
      }
    }
    $doc->applySyntax();
    $this->html = $doc->saveXML();
    Logger::user_success(_("File successfully imported"));

    $this->file = "$f.html";
    $dest = $this->tmpFolder."/".$this->file;
    $fp = lock_file($dest);
    try {
      try {
        file_put_contents_plus($dest, $this->html);
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to save file %s: %s"), $this->file, $e->getMessage()));
      }
      try {
        if(is_file("$dest.old")) incrementalRename("$dest.old", "$dest.");
      } catch(Exception $e) {
        throw new Exception(sprintf(_("Unable to backup file %s: %s"), $this->file, $e->getMessage()));
      }
    } catch(Exception $e) {
      Logger::error($e->getMessage());
    } finally {
      unlock_file($fp, $dest);
    }
  }

  private function parseContent(HTMLPlus $doc, $eName, $aName) {
    foreach($doc->getElementsByTagName($eName) as $e) {
      $lastText = null;
      foreach($e->childNodes as $ch) {
        if($ch->nodeType != XML_TEXT_NODE) continue;
        $lastText = $ch;
      }
      if(is_null($lastText)) continue;
      $var = explode("@", $lastText->nodeValue);
      if(count($var) < 2) continue;
      $aVal = trim(array_pop($var));
      if(!strlen($aVal)) continue;
      $lastText->nodeValue = trim(implode("@", $var));
      $e->setAttribute($aName, $aVal);
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
    $vars["action"] = "?".$this->className;
    $vars["link"] = $_GET[$this->className];
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
