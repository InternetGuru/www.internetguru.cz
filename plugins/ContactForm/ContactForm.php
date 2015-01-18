<?php

class ContactForm extends Plugin implements SplObserver {

  private $cfg;
  private $isPost;
  private $vars = array();
  private $formVars;
  private $formValues;
  private $messages;
  private $rules = array();
  private $ruleTitles = array();
  private $errors = array();
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    #$s->setPriority($this, 2);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $this->cfg = $this->getDOMPlus();
    $this->createVars();
    foreach($this->forms as $formId => $form) {
      try {
        $this->isPost = false;
        $this->parseForm($form);
        if(!empty($this->errors))
          throw new Exception(sprintf(_("%s error(s) occured"), count($this->errors)));
        if(!$this->isPost) continue;
        $msg = $this->createMessage($this->cfg, $formId);
        $this->sendForm($form, $msg);
        Cms::addMessage($this->formVars["success"], Cms::MSG_SUCCESS, true);
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

  private function sendForm(DOMElementPlus $form, $msg) {
    if(!strlen($this->formVars["adminaddr"])) throw new Exception(_("Missing admin address"));
    if(!preg_match("/".EMAIL_PATTERN."/", $this->formVars["adminaddr"]))
      throw new Exception(sprintf(_("Invalid admin email address: '%s'"), $this->formVars["adminaddr"]));
    if(strlen($this->formVars["clientaddr"]) && !preg_match("/".EMAIL_PATTERN."/", $this->formVars["clientaddr"]))
      throw new Exception(sprintf(_("Invalid client email address: '%s'"), $this->formVars["clientaddr"]));
    require LIB_FOLDER.'/PHPMailer/PHPMailerAutoload.php';
    $this->sendMail($this->formVars["adminaddr"], $this->formVars["adminname"], $this->formVars["clientaddr"],
      $this->formVars["clientname"], $msg);
    if(strlen($this->formVars["copycond"])) {
      if(!strlen($this->formVars["clientaddr"]))
        throw new Exception(_("Unable to send copy to empty client address"));
      $this->sendMail($this->formVars["clientaddr"], $this->formVars["clientname"], $this->formVars["adminaddr"],
        $this->formVars["adminname"], $this->formVars["copymsg"].$msg);
    }
    if(!self::DEBUG) return;
    #print_r($_POST);
    #print_r($this->formVars);
    #echo $msg;
    #print_r($this->formValues);
    die("CONTACTFORM DEBUG DIE");
  }

  private function sendMail($mailto, $mailtoname, $replyto, $replytoname, $msg) {
    if(self::DEBUG) {
      echo $msg;
      new Logger(sprintf(_("Sending e-mail to %s skipped"), $mailto));
      return;
    }
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    $mail->From = "no-reply@".getDomain();
    $mail->FromName = $this->formVars["servername"];
    $mail->addAddress($mailto, $mailtoname);
    if(strlen($replyto)) {
      $mail->addReplyTo($replyto, $replytoname);
    }
    $mail->Subject = sprintf(_("New massage from %s"), getDomain());
    if(strlen($this->formVars["subject"])) $mail->Subject = $this->formVars["subject"];
    $mail->Body = $msg;
    if(!$mail->send()) {
      new Logger(sprintf(_("Failed to send e-mail: %s"), $mail->ErrorInfo));
      throw new Exception($mail->ErrorInfo);
    }
    new Logger(sprintf(_("E-mail successfully sent to %s"), $mailto));
  }

  private function createVars() {
    $this->rules = array();
    foreach($this->cfg->documentElement->childElements as $e) {
      try {
        switch($e->nodeName) {
          case "var":
          $this->validateElement($e, "id");
          $this->vars[$e->getAttribute("id")] = $e->nodeValue;
          break;
          case "rule":
          $this->validateElement($e, "id");
          $this->rules[$e->getAttribute("id")] = $e->nodeValue;
          $this->ruleTitles[$e->getAttribute("id")] = $e->getAttribute("title");
          break;
          case "form":
          $this->validateElement($e, "id");
          $this->forms[$e->getAttribute("id")] = $e;
          break;
          case "message":
          $this->validateElement($e, "for");
          $this->messages[$e->getAttribute("for")] = $e->nodeValue;
          break;
        }
      } catch(Exception $ex) {
        new Logger(sprintf(_("Skipped element %s: %s"), $e->nodeName, $ex->getMessage()), Logger::LOGGER_WARNING);
      }
    }
    if(self::DEBUG) $this->vars["adminaddr"] = "debug@somedomain.cz";
  }

  private function validateElement(DOMElementPlus $e, $aName) {
    if($e->hasAttribute($aName)) return;
    throw new Exception(sprintf(_("Missing attribute %s"), $aName));
  }

  private function parseForm(DOMElementPlus $form) {
    $doc = new DOMDocumentPlus();
    $var = $doc->appendChild($doc->createElement("var"));
    $form = $var->appendChild($doc->importNode($form, true));
    $formId = $form->getAttribute("id");
    $prefix = normalize(get_class($this));
    $timeName = "$prefix-$formId-time";
    $tokenName = "$prefix-$formId-token";
    $time = time();
    $formError = null;
    $this->formVars = array();
    foreach($this->vars as $name => $value) {
      $this->formVars[$name] = $value;
      if(!$form->hasAttribute($name)) continue;
      $this->formVars[$name] = $form->getAttribute($name);
      $form->removeAttribute($name);
    }
    try {
      $this->checkPost($timeName, $tokenName, $formId);
    } catch(Exception $e) {
      $formError = $e->getMessage();
    }
    foreach(array("clientaddr", "clientname", "copycond") as $name) {
      $this->formVars[$name] = '';
      $aVal = $form->getAttribute($name);
      $form->removeAttribute($name);
      if(!$this->isPost) continue;
      if(isset($_POST["$prefix-$formId-$aVal"])) {
        $this->formVars[$name] = $_POST["$prefix-$formId-$aVal"];
        continue;
      }
      if($name != "clientaddr") continue;
      new Logger(sprintf(_("Client address not sent; attribute name '%s' not found"), $aVal), Logger::LOGGER_WARNING);
    }
    if($this->formVars["secret"] == "SECRET_PHRASE")
      new Logger(_("Default secret phrase should be changed"), Logger::LOGGER_WARNING);
    $form->setAttribute("method", "post");
    $form->setAttribute("action", "/");
    $form->setAttribute("var", "xhtml11-link@action");
    $xpath = new DOMXPath($doc);
    $form->setAttribute("id", "$prefix-".$formId);
    $i = 1;
    $e = null;
    $formItems = array();
    $this->formValues = array();
    $rcValues = array();
    foreach($xpath->query("//input | //textarea | //select") as $e) {
      $name = "$prefix-$formId-item".$i++;
      if($e->hasAttribute("name")) {
        $aName = "$prefix-$formId-".$e->getAttribute("name");
        if(in_array($aName, array($tokenName, $timeName))) {
          new Logger(_("Forbidden element name time or token replaced"), Logger::LOGGER_WARNING);
        } else $name = $aName;
      }
      $normName = normalize($name, null, false);
      if(isset($_POST[$normName])) {
        if($e->hasAttribute("id")) $this->formValues[$e->getAttribute("id")] = $_POST[$normName];
        elseif($e->hasAttribute("name")) {
          $this->formValues[normalize($e->getAttribute("name"), null, false)] = $_POST[$normName];
        }
      }
      if($e->nodeName == "input" && in_array($e->getAttribute("type"), array("checkbox", "radio"))) {
        if(!$e->hasAttribute("value")) {
          $j = 1;
          if(isset($rcValues[$name])) while(array_key_exists("i$j", $rcValues[$name])) $j++;
          $e->setAttribute("value", "i$j");
          $rcValues[$name]["i$j"] = null;
        }
        $rcValues[$name][$e->getAttribute("value")] = null;
      }
      $formItems[] = $e;
      $e->setAttribute("name", $name);
    }
    $timeInput = $e->parentNode->appendChild($doc->createElement("input"));
    $timeInput->setAttribute("name", $timeName);
    $timeInput->setAttribute("type", "hidden");
    $timeInput->setAttribute("value", $time);
    $tokenInput = $e->parentNode->appendChild($doc->createElement("input"));
    $tokenInput->setAttribute("name", $tokenName);
    $tokenInput->setAttribute("type", "hidden");
    $tokenInput->setAttribute("value", hash("sha1", $time.$this->formVars["secret"].$formId));
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
          if(!isset($_POST[$name])) break;
          $item->setAttribute("value", $_POST[$name]);
          break;
          case "checkbox":
          $name = normalize($name, null, false);
          case "radio":
          if($this->isPost) $passed = $this->verifyChecked($name, $item->getAttribute("value"),
            $item->hasAttribute("required"), $item->getAttribute("required"));
          if(isset($_POST[$name]) && ($_POST[$name] == $item->getAttribute("value")
            || (is_array($_POST[$name]) && in_array($item->getAttribute("value"), $_POST[$name])))) {
            $item->setAttribute("checked", "checked");
          } elseif($this->isPost) {
            $item->removeAttribute("checked");
          }
        }
        break;
        case "textarea":
        if($this->isPost) $passed = $this->verifyText($name, $item->getAttribute("rule"),
          $item->hasAttribute("required"), $item->getAttribute("required"));
        if(!$item->hasAttribute("cols")) $item->setAttribute("cols", 40);
        if(!$item->hasAttribute("rows")) $item->setAttribute("rows", 20);
        if(!isset($_POST[$name])) break;
        $item->nodeValue = $_POST[$name];
        #if($e->nodeValue == "") $e->nodeValue= " ";
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
    foreach($xpath->query("form//*[@id or @for]") as $e) {
      if($e->hasAttribute("id")) $e->setAttribute("id", "$prefix-$formId-".$e->getAttribute("id"));
      if($e->hasAttribute("for")) $e->setAttribute("for", "$prefix-$formId-".$e->getAttribute("for"));
    }
    Cms::setVariable($formId, $doc);
    $doc->processVariables($this->ruleTitles);
    if(!is_null($formError)) throw new Exception($formError);
    #echo $doc->saveXML();
  }

  private function createMessage(DOMDocumentPlus $cfg, $formId) {
    if(!array_key_exists($formId, $this->messages)) {
      foreach($_POST as $k => $v) {
        if(is_array($v)) $v = implode(", ", $v);
        $msg[] = "$k: $v";
      }
      return implode("\n", $msg);
    }
    $variables = array_merge($this->formValues, Cms::getAllVariables());
    return replaceVariables($this->messages[$formId], $variables);
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
    $hash = hash("sha1", $_POST[$timeName].$this->formVars["secret"].$formId);
    if(strcmp($_POST[$tokenName], $hash) !== 0) throw new Exception(_("Security verification failed"));
  }

}

?>