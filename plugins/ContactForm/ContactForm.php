<?php

#todo: max one default secret phrase warning
#todo: invalid admin e-mail address warning
#todo: default servername = $cms-domain
#todo: default subject = Nová zpráva z webu $cms-domain
#todo: checkbox default value = trim first label

class ContactForm extends Plugin implements SplObserver {

  private $cfg;
  private $vars = array();
  private $formVars;
  private $formValues;
  private $formItems = array();
  private $messages;
  private $rules = array();
  private $ruleTitles = array();
  private $errors = array();
  const TOKEN_NAME = "token";
  const TIME_NAME = "time";
  const FORM_ITEMS_QUERY = "//input | //textarea | //select";
  const CSS_WARNING = "contactform-warning";
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    #$s->setPriority($this, 2);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    Cms::getOutputStrategy()->addCssFile($this->getDir().'/'.get_class($this).'.css');
    $this->cfg = $this->getDOMPlus();
    $this->createGlobalVars();
    foreach($this->forms as $formId => $form) {
      try {
        $htmlForm = $this->parseForm($form);
        if(!$this->isValidPost($form)) {
          $this->finishForm($htmlForm, false);
          continue;
        }
        $msg = $this->createMessage($this->cfg, $formId);
        $this->sendForm($form, $msg);
        Cms::addMessage($this->formVars["success"], Cms::MSG_SUCCESS, true);
        redirTo(getRoot().getCurLink(), 302, true);
      } catch(Exception $e) {
        $this->finishForm($htmlForm, true);
        $message = "<a href='#".$htmlForm->getAttribute("id")."'>"
          .sprintf(_("Unable to send form: %s"), $e->getMessage())."</a>";
        Cms::addMessage($message, Cms::MSG_WARNING);
        foreach($this->errors as $itemId => $message) {
          Cms::addMessage(sprintf("<label for='%s'>%s</label>", $itemId, $message), Cms::MSG_WARNING);
        }
      }
    }
  }

  private function finishForm($form, $error) {
    foreach($this->formItems as $e) {
      $e->removeAttribute("rule");
      $e->removeAttribute("required");
    }
    if($error) $this->ruleTitles["error"] = "";
    $form->ownerDocument->processVariables($this->ruleTitles);
  }

  private function parseForm(DOMElementPlus $form) {
    $prefix = normalize(get_class($this));
    $doc = new DOMDocumentPlus();
    $var = $doc->appendChild($doc->createElement("var"));
    $htmlForm = $var->appendChild($doc->importNode($form, true));
    $formId = $htmlForm->getAttribute("id");
    $htmlForm->removeAllAttributes(array("id", "class"));
    $htmlForm->setAttribute("method", "post");
    $htmlForm->setAttribute("action", "/");
    $htmlForm->setAttribute("var", "xhtml11-link@action");
    $htmlForm->setAttribute("id", "$prefix-$formId");
    $this->createFormVars($form);
    $this->registerFormItems($htmlForm, "$prefix-$formId-");
    Cms::setVariable($formId, $doc);
    return $htmlForm;
    #print_r($_POST);
    #echo $doc->saveXML();
  }

  private function isValidPost(DOMElementPlus $form) {
    $prefix = strtolower(get_class($this))."-".$form->getAttribute("id")."-";
    foreach($this->formItems as $i) {
      $this->setPostValue($i, $prefix);
    }
    if(!isset($_POST[$prefix.self::TIME_NAME], $_POST[$prefix.self::TOKEN_NAME])) return false;
    if(time() - $_POST[$prefix.self::TIME_NAME] < 5) throw new Exception(_("Form has not been initialized"));
    if(time() - $_POST[$prefix.self::TIME_NAME] > 60*20) throw new Exception(_("Form has expired"));
    $hash = hash("sha1", $prefix.$_POST[$prefix.self::TIME_NAME].$this->formVars["secret"]);
    if(strcmp($_POST[$prefix.self::TOKEN_NAME], $hash) !== 0) throw new Exception(_("Security verification failed"));
    foreach($this->formItems as $i) {
      try {
        $this->verifyItem($i);
      } catch(Exception $e) {
        $this->errors[$i->getAttribute("id")] = $e->getMessage();
        $i->addClass(self::CSS_WARNING);
        $i->parentNode->addClass(self::CSS_WARNING);
        foreach($this->formIds[$i->getAttribute("id")] as $l) {
          $l->addClass(self::CSS_WARNING);
        }
      }
    }
    if(!empty($this->errors))
      throw new Exception(sprintf(_("%s error(s) occured"), count($this->errors)));
    foreach(array("email", "name", "sendcopy") as $name) {
      $this->formVars[$name] = isset($this->formValues[$name]) ? $this->formValues[$name] : "";
    }
    #if(!strlen($this->formVars["email"]))
    #  new Logger(_("Attribute name email not sent or empty"), Logger::LOGGER_WARNING);
    return true;
  }

  private function sendForm(DOMElementPlus $form, $msg) {
    if(!strlen($this->formVars["adminaddr"])) throw new Exception(_("Missing admin address"));
    if(!preg_match("/".EMAIL_PATTERN."/", $this->formVars["adminaddr"]))
      throw new Exception(sprintf(_("Invalid admin email address: '%s'"), $this->formVars["adminaddr"]));
    if(strlen($this->formVars["email"]) && !preg_match("/".EMAIL_PATTERN."/", $this->formVars["email"]))
      throw new Exception(sprintf(_("Invalid client email address: '%s'"), $this->formVars["email"]));
    require LIB_FOLDER.'/PHPMailer/PHPMailerAutoload.php';
    $this->sendMail($this->formVars["adminaddr"], $this->formVars["adminname"], $this->formVars["email"],
      $this->formVars["name"], trim($msg));
    if(is_array($this->formVars["sendcopy"])) {
      if(!strlen($this->formVars["email"]))
        throw new Exception(_("Unable to send copy to empty client address"));
      $this->sendMail($this->formVars["email"], $this->formVars["name"], $this->formVars["adminaddr"],
        $this->formVars["adminname"], $this->formVars["copymsg"]."\n\n".trim($msg));
    }
    if(!self::DEBUG) return;
    #print_r($_POST);
    #print_r($this->formVars);
    #print_r($this->formValues);
    die("CONTACTFORM DEBUG DIE");
  }

  private function sendMail($mailto, $mailtoname, $replyto, $replytoname, $msg) {
    if(self::DEBUG) {
      echo $msg;
      new Logger(sprintf("Sending e-mail to %s skipped", $mailto));
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

  private function createGlobalVars() {
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

  private function createFormVars(DOMElementPlus $form) {
    $this->formVars = array();
    foreach($this->vars as $name => $value) {
      $this->formVars[$name] = $value;
      if(!$form->hasAttribute($name)) continue;
      $this->formVars[$name] = $form->getAttribute($name);
      $form->removeAttribute($name);
    }
  }

  private function registerFormItems(DOMElementPlus $form, $prefix) {
    $time = time();
    $timeInput = $form->ownerDocument->createElement("input");
    $timeInput->setAttribute("name", $prefix.self::TIME_NAME);
    $timeInput->setAttribute("type", "hidden");
    $timeInput->setAttribute("value", $time);
    $tokenInput = $form->ownerDocument->createElement("input");
    $tokenInput->setAttribute("name", $prefix.self::TOKEN_NAME);
    $tokenInput->setAttribute("type", "hidden");
    $tokenInput->setAttribute("value", hash("sha1", $prefix.$time.$this->formVars["secret"]));
    if($this->formVars["secret"] == "SECRET_PHRASE")
      new Logger(_("Default secret phrase should be changed"), Logger::LOGGER_WARNING);
    $i = 1;
    $e = null;
    $this->formItems = array();
    $this->formValues = array();
    $this->formNames = array(self::TIME_NAME, self::TOKEN_NAME);
    $this->formIds = array();
    $this->formGroupValues = array();
    $xpath = new DOMXPath($form->ownerDocument);
    foreach($xpath->query(self::FORM_ITEMS_QUERY) as $e) {
      if($e->nodeName == "textarea") {
        if(!$e->hasAttribute("cols")) $e->setAttribute("cols", 40);
        if(!$e->hasAttribute("rows")) $e->setAttribute("rows", 7);
      }
      if($e->nodeName == "input" && !$e->hasAttribute("type")) {
        new Logger(_("Element input missing attribute type skipped"), Logger::LOGGER_WARNING);
        continue;
      }
      $this->formItems[] = $e;
      $defId = strlen($e->getAttribute("name")) ? normalize($e->getAttribute("name")) : "item";
      $id = $this->processFormItem($this->formIds, $e, "id", $prefix, $defId, false);
      $name = $this->processFormItem($this->formNames, $e, "name", $prefix, $id, true);
      $this->completeFormItem($e, $name);
    }
    $e->parentNode->appendChild($timeInput);
    $e->parentNode->appendChild($tokenInput);
    foreach($xpath->query("//label") as $e) {
      if($e->hasAttribute("for")) {
        $for = $prefix.$e->getAttribute("for");
        $e->setAttribute("for", $for);
        $this->formIds[$for][] = $e;
        continue;
      }
      foreach($xpath->query("input | textarea | select", $e) as $f) {
        $this->formIds[$f->getAttribute("id")][] = $e;
      }
    }
  }

  private function completeFormItem(DOMElementPlus $e, $name) {
    switch($e->nodeName) {
      case "input":
      if(!in_array($e->getAttribute("type"), array("checkbox", "radio"))) break;
      $this->setUniqueGroupValue($e, $name, null);
      break;
      case "select":
      foreach($e->childElements as $o) {
        $this->setUniqueGroupValue($o, $name, $o->nodeValue);
      }
    }
  }

  private function setUniqueGroupValue(DOMElementPlus $e, $name, $default) {
    $value = $e->getAttribute("value");
    $j = "";
    if(!strlen($value)) $value = $default;
    if(!isset($this->formGroupValues[$name])) $this->formGroupValues[$name] = array();
    if(is_null($value) || array_key_exists($value, $this->formGroupValues[$name])) {
      $j = 1;
      while(array_key_exists($value.$j, $this->formGroupValues[$name])) $j++;
    }
    $e->setAttribute("value", $value.$j);
    $this->formGroupValues[$name][$value.$j] = null;
  }

  private function setPostValue(DOMElementPlus $e, $prefix) {
    $post = null;
    $name = str_replace("[]", "", $e->getAttribute("name"));
    $name = substr($name, strlen($prefix));
    if(isset($_POST[$prefix.$name])) $post = $_POST[$prefix.$name];
    switch($e->nodeName) {
      case "input":
      switch($e->getAttribute("type")) {
        case "text":
        $e->setAttribute("value", $post);
        break;
        case "checkbox":
        case "radio":
        if($post == $e->getAttribute("value")
          || (is_array($post) && in_array($e->getAttribute("value"), $post))) {
          $e->setAttribute("checked", "checked");
        } else {
          $e->removeAttribute("checked");
        }
      }
      break;
      case "textarea":
      $e->nodeValue = $post;
      break;
      case "select":
      foreach($e->getElementsByTagName("option") as $o) {
        if($post == $o->getAttribute("value")) $o->setAttribute("selected", "selected");
        else $o->removeAttribute("selected");
      }
    }
    if(is_null($post) || (is_array($post) && empty($post))
      || (is_string($post) && !strlen(trim($post)))) $post = $this->vars["nothing"];
    $this->formValues[$name] = $post;
  }

  private function processFormItem(Array &$register, DOMElementPlus $e, $aName, $prefix, $default, $arraySupport) {
    $value = normalize($e->getAttribute($aName), null, false); // remove "[]"" and stuff...
    if(!strlen($value)) $value = $default;
    $isCheckbox = $e->nodeName == "input" && $e->getAttribute("type") == "checkbox";
    $isRadio = $e->nodeName == "input" && $e->getAttribute("type") == "radio";
    if(array_key_exists($value, $register) && (!$arraySupport || !($isCheckbox || $isRadio))) {
      $i = 1;
      while(array_key_exists("$value$i", $register)) $i++;
      $value = "$value$i";
    }
    if($arraySupport && $isCheckbox) {
      $e->setAttribute($aName, $prefix.$value."[]");
    } else {
      $e->setAttribute($aName, $prefix.$value);
    }
    $register[$value] = array();
    return $value;
  }

  private function createMessage(DOMDocumentPlus $cfg, $formId) {
    if(!array_key_exists($formId, $this->messages)) {
      foreach($this->formValues as $k => $v) {
        if(is_array($v)) $v = implode(", ", $v);
        $msg[] = "$k: $v";
      }
      return implode("\n", $msg);
    }
    $vars = array_merge(Cms::getAllVariables(), $this->formValues);
    return replaceVariables($this->messages[$formId], $vars);
  }

  private function verifyItem(DOMElementPlus $e) {
    $rule = $e->getAttribute("rule");
    $req = $e->hasAttribute("required");
    $err = $e->getAttribute("required");
    if($e->nodeName == "textarea") {
      $this->verifyText($e->nodeValue, $rule, $req, $err);
    } elseif($e->nodeName != "input") return;
    switch($e->getAttribute("type")) {
      case "text":
      $this->verifyText($e->getAttribute("value"), $rule, $req, $err);
      break;
      case "checkbox":
      case "radio":
      $this->verifyChecked($e->hasAttribute("checked"), $req, $err);
      break;
    }
  }

  private function verifyText($value, $ruleName, $required, $error) {
    if(!strlen(trim($value))) {
      if(!$required) return;
      if(!strlen($error)) $error = _("Item is required");
      throw new Exception($error);
    }
    if(!strlen($ruleName)) return;
    if(!array_key_exists($ruleName, $this->rules))
      new Logger(sprintf(_("Form rule %s is not defined"), $ruleName), Logger::LOGGER_WARNING);
    $res = @preg_match("/".$this->rules[$ruleName]."/", $value);
    if($res === false) {
      new Logger(sprintf(_("Form rule %s is invalid"), $ruleName), Logger::LOGGER_WARNING);
      return;
    }
    if($res === 1) return;
    if(!strlen($error)) $error = $this->ruleTitles[$ruleName];
    if(!strlen($error)) $error = sprintf(_("Item value does not match required format: %s"), $this->rules[$ruleName]);
    throw new Exception($error);
  }

  private function verifyChecked($checked, $required, $error) {
    if(!$required || $checked) return;
    if(!strlen($error)) $error = _("Item must be checked");
    throw new Exception($error);
  }

}

?>