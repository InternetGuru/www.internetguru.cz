<?php

#TODO: what about adm/ ?
#TODO: de/activate
#TODO: success message
#TODO: syntax highlight
#TODO: select file

class ContentAdmin implements SplObserver, ContentStrategyInterface {
  const HASH_ALGO = 'crc32b';
  const HTMLPLUS_SCHEMA = "lib/HTMLPlus.rng";
  const DEFAULT_FILE = "Content.xml";
  private $subject; // SplSubject
  private $content = null;
  private $errors = array();
  private $contentValue = "";
  #private $dataFile;
  private $destinationFile = null;
  private $schema = null;
  private $adminLink;
  private $type;

  public function update(SplSubject $subject) {
    if(!isset($_GET["admin"])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "preinit") {
      $subject->setPriority($this,1);
    }
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
    $this->adminLink = $subject->getCms()->getLink()."?admin";
    try {
      $this->processAdmin();
    } catch (Exception $e) {
      $this->errors[] = $e->getMessage();
    }
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $cms->getOutputStrategy()->addCssFile(PLUGIN_FOLDER ."/". get_class($this) .'/ContentAdmin.css');
    $cms->getOutputStrategy()->addJsFile(PLUGIN_FOLDER ."/". get_class($this) .'/ContentAdmin.js', 10, "body");

    #$this->errors = array("a","b","c");
    $format = $this->type;
    if(!is_null($this->schema)) $format .= " ({$this->schema})";

    $lad = $this->adminLink ."=". $this->defaultFile;
    $la = $this->adminLink ."=". $this->destinationFile;

    $f = PLUGIN_FOLDER."/".get_class($this)."/ContentAdmin.xml";

    $newContent = $cms->getDomBuilder()->buildHTMLPlus($f);
    $newContent->insertVar("heading",$cms->getTitle(),"ContentAdmin");
    $newContent->insertVar("errors",$this->errors,"ContentAdmin");
    $newContent->insertVar("link",$cms->getLink(),"ContentAdmin");
    $newContent->insertVar("linkAdminDefault",$lad,"ContentAdmin");
    $newContent->insertVar("linkAdmin",$la,"ContentAdmin");
    $newContent->insertVar("content",$this->contentValue,"ContentAdmin");
    $newContent->insertVar("filename",$this->destinationFile,"ContentAdmin");
    $newContent->insertVar("schema",$format,"ContentAdmin");
    $newContent->insertVar("mode",$this->type,"ContentAdmin");
    #$newContent->insertVar("noparse","noparse","ContentAdmin");
    $newContent->insertVar("filehash",$this->getFileHash($this->destinationFile)."","ContentAdmin");

    return $newContent;
  }

  private function getHash($data) {
    return hash(self::HASH_ALGO,$data);
  }

  private function getFileHash($filePath) {
    return hash_file(self::HASH_ALGO,$filePath);
  }

  private function processAdmin() {

    $f = USER_FOLDER . "/" . self::DEFAULT_FILE;
    if(strlen($_GET["admin"])) $f = $_GET["admin"];
    if(strpos($f,"/") === 0) $f = substr($f,1); // remove trailing slash
    $usrFile = false;
    $this->defaultFile = $f;
    if(strpos($f,USER_FOLDER."/") === 0) {
      $usrFile = true;
      $this->defaultFile = substr($f,strlen(USER_FOLDER)+1);
    }
    if(strpos($f,ADMIN_FOLDER."/") === 0) {
      throw new Exception("Cannot edit admin files");
    }
    $this->destinationFile = USER_FOLDER."/".$this->defaultFile;
    $this->type = pathinfo($this->defaultFile,PATHINFO_EXTENSION);
    $df = findFile($this->defaultFile,false,false);

    $post = false;
    if(isset($_POST["content"],$_POST["filehash"])) {
      $post = true;
      $post_n = str_replace("\r\n", "\n", $_POST["content"]);
      $post_rn = str_replace("\n", "\r\n", $post_n);
      $this->contentValue = $post_n;
      if(in_array($_POST["filehash"],array($this->getHash($post_n),$this->getHash($post_rn))))
        throw new Exception("No changes made");
      if($_POST["filehash"] != $this->getFileHash($this->destinationFile))
        throw new Exception("User file '{$this->destinationFile}' has changed during administration");
    } else {
      $this->contentValue = $this->loadFile($f,$df);
    }

    if(in_array($this->type,array("xml","xsl"))) {
      if($df) {
        $this->schema = $this->getSchema($df);
      }
      if(is_null($this->schema) && file_exists($f)) {
        $this->schema = $this->getSchema($f);
      }
      if($post) {
        if($this->isHtmlPlus()) $doc = new HTMLPlus();
        else $doc = new DOMDocumentPlus();
        if(!@$doc->loadXml($this->contentValue))
          throw new Exception("Invalid XML syntax");
      } else $doc = $this->loadXml($f,$df);
      $doc->formatOutput = true;
      if($this->isHtmlPlus()) {
        $doc->validatePlus(true);
        if($doc->isAutocorrected()) $this->contentValue = $doc->saveXML();
      } else {
        var_dump($doc->validatePlus());
        if(!$usrFile && $doc->removeNodes("//*[@readonly]"))
          $this->contentValue = $doc->saveXML();
        $this->validateXml($doc);
      }
    }

    if($post) $this->save();
  }

  private function loadFile($f,$def) {

    if(file_exists($f)) {
      $file = $f;
      $user = false;
    }
    elseif(file_exists($def)) {
      $file = $def;
      $user = false;
    } else {
      return "";
    }

    if(!($s = @file_get_contents(findFile($file,$user))))
      throw new Exception ("Unable to get contents from '$file'");

    return $s;
  }

  private function loadXml($f,$def) {

    if(file_exists($f)) {
      $file = $f;
      $user = false;
    }
    elseif(file_exists($def)) {
      $file = $def;
      $user = false;
    } else {
      return new DOMDocumentPlus();
    }

    $db = $this->subject->getCms()->getDomBuilder();
    if($this->isHtmlPlus()) return $db->buildHTMLPlus($file,$user);
    return $db->buildDOMPlus($file,false,$user);
  }

  private function save() {
    if(saveRewrite($this->destinationFile,$this->contentValue) === false)
      throw new Exception("Unable to save changes, administration may be locked (update in progress)");
    if(empty($this->errors)) $this->redir();
  }

  private function isHtmlPlus() {
    return in_array($this->schema,array(self::HTMLPLUS_SCHEMA, "../cms/" . self::HTMLPLUS_SCHEMA));
  }

  private function validateXml(DOMDocumentPlus $doc) {
    if(is_null($this->schema)) return;
    switch(pathinfo($this->schema,PATHINFO_EXTENSION)) {
      case "rng":
      $doc->relaxNGValidatePlus($this->schema);
      break;
      default:
      throw new Exception("Unsupported schema '{$this->schema}'");
    }
  }

  private function getSchema($f) {
    $h = fopen($f,"r");
    fgets($h); // skip first line
    $line = str_replace("'",'"',fgets($h));
    fclose($h);
    if(!preg_match('<\?xml-model href="([^"]+)" ?\?>',$line,$m)) return;
    $schema = findFile($m[1],false,false);
    if(!file_exists($schema))
      throw new Exception("Schema file '$schema' not found");
    return $schema;
  }

  private function redir() {
    $redir = $this->subject->getCms()->getLink();
    if(isset($_POST["saveandstay"])) {
      $redir = $this->adminLink . "=" . $this->destinationFile;
    }
    header("Location: " . (strlen($redir) ? $redir : "."));
    exit;
  }

  public function getTitle(Array $q) {
    return $q;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>
