<?php

class ContactForm extends Plugin implements SplObserver {

  private $cfg;
  private $isPost;
  private $vars = array();
  private $rules = array();
  private $ruleTitles = array();
  private $errors = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    #$s->setPriority($this, 2);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $this->cfg = $this->getDOMPlus();
    $this->createVars();
    if($this->vars["secret_phrase"] == "SECRET_PHRASE")
      new Logger(_("Default secret phrase should be changed"), Logger::LOGGER_WARNING);
    foreach($this->forms as $formId => $form) {
      try {
        $this->isPost = false;
        $this->parseForm($form);
        if(!empty($this->errors))
          throw new Exception(sprintf(_("%s error(s) occured"), count($this->errors)));
        if(!$this->isPost) continue;
        $this->sendForm($form);
        Cms::addMessage($this->vars["success"], Cms::MSG_SUCCESS, true);
        redirTo(getRoot().getCurLink(), 302, true);
      } catch(Exception $e) {
        $message = sprintf(_("Unable to send form: %s"), $e->getMessage());
        Cms::addMessage($message, Cms::MSG_WARNING);
        foreach($this->errors as $name => $message) {
          Cms::addMessage($message, Cms::MSG_WARNING);
        }
      }
    }
  }

  private function sendForm(DOMElementPlus $form) {
    $v = array();
    foreach($this->vars as $varId => $val) {
      switch($varId) {
        case "clientaddr":
        case "clientname":
        case "copycond":
        if(is_null($val) || !isset($_POST[$val])) {
          $v[$varId] = '';
          break;
        }
        $v[$varId] = $_POST[$val];
        break;
      }
    }
    if(!strlen($this->vars["adminaddr"])) throw new Exception(_("Missing admin address"));
    if(!preg_match("/".EMAIL_PATTERN."/", $this->vars["adminaddr"]))
      throw new Exception(sprintf(_("Invalid admin email address: '%s'"), $this->vars["adminaddr"]));
    if(strlen($v["clientaddr"]) && !preg_match("/".EMAIL_PATTERN."/", $v["clientaddr"]))
      throw new Exception(sprintf(_("Invalid client email address: '%s'"), $v["clientaddr"]));
    require LIB_FOLDER.'/PHPMailer/PHPMailerAutoload.php';
    $this->sendMail($this->vars["adminaddr"], $this->vars["adminname"], $v["clientaddr"],
      $v["clientname"], "");
    if(strlen($v["copycond"])) {
      if(!strlen($v["clientaddr"]))
        throw new Exception(_("Unable to send copy to empty client address"));
      $this->sendMail($v["clientaddr"], $v["clientname"], $this->vars["adminaddr"],
        $this->vars["adminname"], $this->vars["copymsg"]);
    }
  }

  private function sendMail($mailto, $mailtoname, $replyto, $replytoname, $msg) {
    $mail = new PHPMailer;
    $mail->From = "no-reply@".getDomain();
    $mail->FromName = $this->vars["servername"];
    $mail->addAddress($mailto, $mailtoname);
    if(strlen($replyto)) {
      $mail->addReplyTo($replyto, $replytoname);
    }
    $mail->Subject = sprintf(_("New massage from %s"), getDomain());
    if(strlen($this->vars["subject"])) $mail->Subject = $this->vars["subject"];
    $mail->Body = $msg."\n".print_r($_POST, true);
    if(!$mail->send()) {
      new Logger(sprintf(_("Failed to send e-mail: %s"), $mail->ErrorInfo));
      throw new Exception($mail->ErrorInfo);
    }
    new Logger(sprintf(_("E-mail successfully sent to %s"), $mailto));
  }

  private function createVars() {
    $this->rules = array();
    $i = 1;
    foreach($this->cfg->documentElement->childElements as $e) {
      switch($e->nodeName) {
        case "var":
        if(!$e->hasAttribute("id")) {
          new Logger(_("Element var missing attribute id skipped"), Logger::LOGGER_WARNING);
          break;
        }
        $this->vars[$e->getAttribute("id")] = $e->nodeValue;
        break;
        case "rule":
        if(!$e->hasAttribute("name")) {
          new Logger(_("Rule missing attribute name skipped"), Logger::LOGGER_WARNING);
          break;
        }
        $this->rules[$e->getAttribute("name")] = $e->nodeValue;
        $this->ruleTitles[$e->getAttribute("name")] = $e->getAttribute("title");
        break;
        case "form":
        if(!$e->hasAttribute("id")) {
          new Logger(_("Form missing attribute id skipped"), Logger::LOGGER_WARNING);
          break;
        }
        $this->forms[$e->getAttribute("id")] = $e;
        break;
      }
    }
  }

  private function parseForm(DOMElementPlus $form) {
    $doc = new DOMDocumentPlus();
    $var = $doc->appendChild($doc->createElement("var"));
    $form = $var->appendChild($doc->importNode($form, true));
    $formId = $form->getAttribute("id");
    foreach(array("subject", "servername", "adminaddr", "adminname", "clientaddr", "clientname", "copycond", "copymsg") as $attr) {
      $this->vars[$attr] = $form->hasAttribute($attr) ? $form->getAttribute($attr) : '';
      $form->removeAttribute($attr);
    }
    $form->setAttribute("method", "post");
    $form->setAttribute("action", "/");
    $form->setAttribute("var", "xhtml11-link@action");
    $xpath = new DOMXPath($doc);
    $prefix = normalize(get_class($this));
    $form->setAttribute("id", "$prefix-".$formId);
    foreach($xpath->query("form//*[@id or @for]") as $e) {
      if($e->hasAttribute("id")) $e->setAttribute("id", "$prefix-$formId-".$e->getAttribute("id"));
      if($e->hasAttribute("for")) $e->setAttribute("for", "$prefix-$formId-".$e->getAttribute("for"));
    }
    $i = 1;
    $e = null;
    $timeName = "$prefix-$formId-time";
    $tokenName = "$prefix-$formId-token";
    $time = time();
    $formError = null;
    try {
      $this->checkPost($timeName, $tokenName, $formId);
    } catch(Exception $e) {
      $formError = $e->getMessage();
    }
    $formItems = array();
    foreach($xpath->query("//input | //textarea | //select") as $e) {
      $name = "$prefix-$formId-item".$i++;
      if($e->hasAttribute("name")) {
        $aName = "$prefix-$formId-".$e->getAttribute("name");
        if(in_array($aName, array($tokenName, $timeName))) {
          new Logger(_("Forbidden element name time or token replaced"), Logger::LOGGER_WARNING);
        } else $name = $aName;
      }
      $e->setAttribute("name", $name);
      $formItems[] = $e;
    }
    $timeInput = $e->parentNode->appendChild($doc->createElement("input"));
    $timeInput->setAttribute("name", $timeName);
    $timeInput->setAttribute("type", "hidden");
    $timeInput->setAttribute("value", $time);
    $tokenInput = $e->parentNode->appendChild($doc->createElement("input"));
    $tokenInput->setAttribute("name", $tokenName);
    $tokenInput->setAttribute("type", "hidden");
    $tokenInput->setAttribute("value", hash("sha1", $time.$this->vars["secret_phrase"].$formId));
    foreach($formItems as $item) {
      if(is_null($item)) continue;
      $passed = true;
      $name = $item->getAttribute("name");
      switch($item->nodeName) {
        case "input":
        if(!$item->hasAttribute("type")) {
          new Logger(_("Element input missing attribute type skipped"), Logger::LOGGER_WARNING);
          break;
        }
        switch($item->getAttribute("type")) {
          case "text":
          if($this->isPost) $passed = $this->verifyText($name, $item->getAttribute("rule"),
            $item->hasAttribute("required"), $item->getAttribute("required"));
          if(isset($_POST[$name])) $item->setAttribute("value", $_POST[$name]);
          break;
          case "checkbox":
          if(!$item->hasAttribute("value")) $item->setAttribute("value", "on");
          case "radio":
          if(!$item->hasAttribute("value")) {
            new Logger(_("Radio input missing attribute value skipped"), Logger::LOGGER_WARNING);
            break;
          }
          if($this->isPost) $passed = $this->verifyChecked($name, $item->getAttribute("value"),
            $item->hasAttribute("required"), $item->getAttribute("required"));
          if(isset($_POST[$name]) && $_POST[$name] == $item->getAttribute("value")) {
            $item->setAttribute("checked", "checked");
          } elseif($this->isPost) {
            $item->removeAttribute("checked");
          }
        }
        break;
        case "textarea":
        if($this->isPost) $passed = $this->verifyText($name, $item->getAttribute("rule"),
          $item->hasAttribute("required"), $item->getAttribute("required"));
        if(isset($_POST[$name])) $item->nodeValue = $_POST[$name];
        #if($e->nodeValue == "") $e->nodeValue= " ";
        if(!$item->hasAttribute("cols")) $item->setAttribute("cols", 40);
        if(!$item->hasAttribute("rows")) $item->setAttribute("rows", 20);
        break;
        case "select":
        if(!isset($_POST[$name])) break;
        foreach($item->getElementsByTagName("option") as $o) {
          $value = $o->nodeValue;
          if($o->hasAttribute("value")) $value = $o->getAttribute("value");
          if($_POST[$name] == $value) $o->setAttribute("selected", "selected");
          else $o->removeAttribute("selected");
        }
        break;
      }
      $item->removeAttribute("required");
      $item->removeAttribute("rule");
      if($passed) continue;
      $item->addClass("warning");
      if($item->parentNode->nodeName == "label") $item->parentNode->addClass("warning");
      if(!$item->hasAttribute("id")) continue;
      $id = $item->getAttribute("id");
      foreach($xpath->query("//label[@for='$id']") as $e) $e->addClass("warning");
    }
    Cms::setVariable($formId, $doc);
    $doc->processVariables($this->ruleTitles);
    if(!is_null($formError)) throw new Exception($formError);
    #echo $doc->saveXML();
  }

  private function verifyChecked($name, $value, $required, $error) {
    if(!$required || (isset($_POST[$name]) && $_POST[$name] == $value)) return true;
    if(strlen($error)) $this->errors[$name] = $error;
    else $this->errors[$name] = _("Item must be checked");
    return false;
  }

  private function verifyText($name, $ruleName, $required, $error) {
    if(!isset($_POST[$name])) throw new Exception(sprintf(_("Missing post data %s"), $name));
    if(!strlen(trim($_POST[$name]))) {
      if(!$required) return true;
      if(strlen($error)) $this->errors[$name] = $error;
      else $this->errors[$name] = _("Item is required");
      return false;
    }
    if(!strlen($ruleName)) return true;
    if(!array_key_exists($ruleName, $this->rules))
      new Logger(sprintf(_("Form rule %s is not defined"), $ruleName), Logger::LOGGER_WARNING);
    $res = @preg_match("/".$this->rules[$ruleName]."/", $_POST[$name]);
    if($res === false) {
      new Logger(sprintf(_("Form rule %s is invalid"), $ruleName), Logger::LOGGER_WARNING);
      return true;
    }
    if($res === 1) return true;
    if(strlen($error)) $this->errors[$name] = $error;
    elseif(strlen($this->ruleTitles[$ruleName])) $this->errors[$name] = $this->ruleTitles[$ruleName];
    else $this->errors[$name] = sprintf(_("Item value does not match required format: %s"), $this->rules[$ruleName]);
    return false;
  }

  private function checkPost($timeName, $tokenName, $formId) {
    if(!isset($_POST[$timeName], $_POST[$tokenName])) return;
    $this->isPost = true;
    if(time() - $_POST[$timeName] < 5) throw new Exception(_("Form has not been initialized"));
    if(time() - $_POST[$timeName] > 60*20) throw new Exception(_("Form has expired"));
    $hash = hash("sha1", $_POST[$timeName].$this->vars["secret_phrase"].$formId);
    if(strcmp($_POST[$tokenName], $hash) !== 0) throw new Exception(_("Security verification failed"));
  }

}

?>