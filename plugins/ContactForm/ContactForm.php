<?php

#todo: max one default secret phrase warning
#todo: invalid admin e-mail address warning
#todo: default servername = $cms-host
#todo: default subject = Nová zpráva z webu $cms-host
#todo: checkbox default value = trim first label

class ContactForm extends Plugin implements SplObserver, ContentStrategyInterface {

  private $cfg;
  private $vars = array();
  private $formToSend = null;
  private $formIdToSend = null;
  private $formsElements = array();
  private $formIds;
  private $formVars;
  private $formValues;
  private $formItems = array();
  private $messages;
  private $rules = array();
  private $ruleTitles = array();
  private $errors = array();
  const FORM_ITEMS_QUERY = "//input | //textarea | //select";
  const CSS_WARNING = "contactform-warning";
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 20);
  }

  public function update(SplSubject $subject) {
    if($this->detachIfNotAttached("Xhtml11")) return;
    if($subject->getStatus() == STATUS_INIT) {
      Cms::getOutputStrategy()->addCssFile($this->pluginDir.'/'.get_class($this).'.css');
      $this->cfg = $this->getDOMPlus();
      $this->createGlobalVars();
      foreach($this->forms as $formId => $form) {
        try {
          $form->addClass("fillable");
          $formVar = $this->parseForm($form);
          $htmlForm = $formVar->documentElement->firstElement;
          $this->formsElements[normalize(get_class($this))."-$formId"] = $formVar;
          $fv = $this->createFormVars($htmlForm);
          if(isset($_GET["cfok"]) && $_GET["cfok"] == $formId) {
            Cms::addMessage($fv["success"], Cms::MSG_SUCCESS);
          }
          if(!$this->isValidPost($form)) {
            $this->finishForm($htmlForm, false);
            continue;
          }
          $this->formVars = $fv;
          $this->finishForm($htmlForm, true);
          $this->formValues["form_id"] = $formId;
          $this->formToSend = $form;
          $this->formIdToSend = $formId;
        } catch(Exception $e) {
          $this->finishForm($htmlForm, true);
          $message = sprintf(_("Unable to process form %s: %s"), "<a href='#".$htmlForm->getAttribute("id")."'>"
            .$htmlForm->getAttribute("id")."</a>", $e->getMessage());
          Cms::addMessage($message, Cms::MSG_ERROR);
          foreach($this->errors as $itemId => $message) {
            Cms::addMessage(sprintf("<label for='%s'>%s</label>", $itemId, $message), Cms::MSG_ERROR);
          }
        }
      }
    }
    if($subject->getStatus() == STATUS_PROCESS) {
      if(is_null($this->formToSend)) return;
      try {
        $variables = array_merge($this->formValues, Cms::getAllVariables());
        foreach($this->formVars as $k => $v) {
          $this->formVars[$k] = replaceVariables($v, $variables);
        }
        if(array_key_exists($this->formIdToSend, $this->messages)) {
          $msg = replaceVariables($this->messages[$this->formIdToSend], $variables);
        } else $msg = $this->createMessage($this->cfg, $this->formIdToSend);
        if(IS_LOCALHOST) throw new Exception("Not sending (at localhost)");
        $this->sendForm($this->formToSend, $msg);
        redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => "cfok=".$this->formIdToSend)));
      } catch(Exception $e) {
        $message = sprintf(_("Unable to send form %s: %s"), "<a href='#".strtolower(get_class($this))."-".$this->formIdToSend."'>"
            .$this->formToSend->getAttribute("id")."</a>", $e->getMessage());
        Cms::addMessage($message, Cms::MSG_ERROR);
      }
    }
  }

  public function getContent(HTMLPlus $content) {
    $content->processVariables($this->formsElements);
    return $content;
  }

  private function finishForm($form, $error) {
    foreach($this->formItems as $e) {
      if($e->hasAttribute("required")) $e->addClass("required");
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
    $htmlForm->setAttribute("action", getCurLink());
    #$htmlForm->setAttribute("var", "cms-link@action");
    $htmlForm->setAttribute("id", "$prefix-$formId");
    $this->registerFormItems($htmlForm, "$prefix-$formId-");
    #Cms::setVariable($formId, $doc);
    return $doc;
    #print_r($_POST);
    #echo $doc->saveXML();
  }

  private function isValidPost(DOMElementPlus $form) {
    #$prefix = strtolower(get_class($this))."-".$form->getAttribute("id")."-";
    foreach($this->formItems as $i) $this->setItemValue($i);
    if(!isset($_POST[get_class($this)])
      || $_POST[get_class($this)] != normalize(get_class($this))."-".$form->getAttribute("id")) return false;
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
    $adminaddr = $this->formVars["adminaddr"];
    $adminname = $this->formVars["adminname"];
    $email = $this->formVars["email"];
    $name = $this->formVars["name"];
    $msg = trim($msg);
    $bcc = $this->formVars["bcc"];
    if(CMS_DEBUG) {
      $adminaddr = "pavelka.iix@gmail.com";
      $adminname = "Jiří Pavelka";
      $bcc = "pavel@petrzela.eu";
    }
    $this->sendMail($adminaddr, $adminname, $email, $name, $msg, $bcc);
    if(is_array($this->formVars["sendcopy"])) {
      if(!strlen($this->formVars["email"]))
        throw new Exception(_("Unable to send copy to empty client address"));
      $msg = $this->formVars["copymsg"]."\n\n$msg";
      $bcc = "";
      $this->sendMail($email, $name, $adminaddr, $adminname, $msg, $bcc);
    }
    if(!self::DEBUG) return;
    #print_r($_POST);
    #print_r($this->formVars);
    #print_r($this->formValues);
    die("CONTACTFORM DEBUG DIE");
  }

  private function sendMail($mailto, $mailtoname, $replyto, $replytoname, $msg, $bcc) {
    if(self::DEBUG) {
      echo $msg;
      new Logger(sprintf("Sending e-mail to %s skipped", $mailto));
      return;
    }
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom("no-reply@".DOMAIN, $this->formVars["servername"]);
    $mail->addAddress($mailto, $mailtoname);
    $mail->Body = $msg;
    $mail->Subject = sprintf(_("New massage from %s"), HOST);
    if(strlen($replyto)) {
      $mail->addReplyTo($replyto, $replytoname);
      $mail->Subject .= " [$replyto]";
    }
    if(strlen($this->formVars["subject"])) $mail->Subject = $this->formVars["subject"];
    if(strlen($bcc)) $mail->addBCC($bcc, '');
    if(!$mail->send()) throw new Exception($mail->ErrorInfo);
    new Logger(sprintf(_("E-mail successfully sent to %s"), $mailto));
  }

  private function createGlobalVars() {
    $this->rules = array();
    foreach($this->cfg->documentElement->childElementsArray as $e) {
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
    $formVars = array();
    foreach(array("email", "name", "sendcopy") as $name) {
      $formVars[$name] = isset($this->formValues[$name]) ? $this->formValues[$name] : "";
    }
    foreach($this->vars as $name => $value) {
      $formVars[$name] = $value;
      if(!$form->hasAttribute($name)) continue;
      $formVars[$name] = $form->getAttribute($name);
      $form->removeAttribute($name);
    }
    return $formVars;
  }

  private function registerFormItems(DOMElementPlus $form, $prefix) {
    $time = time();
    $idInput = $form->ownerDocument->createElement("input");
    $idInput->setAttribute("name", get_class($this));
    $idInput->setAttribute("type", "hidden");
    $idInput->setAttribute("value", $form->getAttribute("id"));
    $i = 1;
    $e = null;
    $this->formItems = array();
    $this->formValues = array();
    $this->formNames = array(get_class($this));
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
      $name = $this->processFormItem($this->formNames, $e, "name", "", $id, true);
    }
    $e->parentNode->appendChild($idInput);
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
    foreach($this->formItems as $e) {
      $this->completeFormItem($e);
    }
  }

  private function completeFormItem(DOMElementPlus $e) {
    switch($e->nodeName) {
      case "input":
      if(!in_array($e->getAttribute("type"), array("checkbox", "radio"))) break;
      $value = null;
      if(!empty($this->formIds[$e->getAttribute("id")])) {
        $value = trim($this->formIds[$e->getAttribute("id")][0]->nodeValue);
      }
      $this->setUniqueGroupValue($e, $value);
      break;
      case "select":
      foreach($e->childElementsArray as $o) {
        $this->setUniqueGroupValue($o, $o->nodeValue);
      }
    }
  }

  private function setUniqueGroupValue(DOMElementPlus $e, $default) {
    $name = $e->getAttribute("name");
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

  private function setItemValue(DOMElementPlus $e) {
    $name = str_replace("[]", "", $e->getAttribute("name"));
    $value = isset($_POST[$name]) ? $_POST[$name] : null;
    if(is_null($value) && isset($_GET[$name])) $value = $_GET[$name];
    if(is_null($value) || (is_array($value) && empty($value))
      || (is_string($value) && !strlen(trim($value)))) $value = $this->vars["nothing"];
    $this->formValues[$name] = $value;
  }

  private function processFormItem(Array &$register, DOMElementPlus $e, $aName, $prefix, $default, $arraySupport) {
    $value = normalize($e->getAttribute($aName), null, null, false); // remove "[]"" and stuff...
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
    foreach($this->formValues as $k => $v) {
      if(is_array($v)) $v = implode(", ", $v);
      $msg[] = "$k: $v";
    }
    return implode("\n", $msg);
  }

  private function verifyItem(DOMElementPlus $e) {
    $name = $e->getAttribute("name");
    $rule = $e->getAttribute("rule");
    $req = $e->hasAttribute("required");
    $err = $e->getAttribute("required");
    $value = isset($_POST[$name]) ? $_POST[$name] : null;
    if($e->nodeName == "textarea") {
      $this->verifyText($value, $rule, $req, $err);
    } elseif($e->nodeName != "input") return;
    switch($e->getAttribute("type")) {
      case "text":
      $this->verifyText($value, $rule, $req, $err);
      break;
      case "checkbox":
      case "radio":
      $this->verifyChecked($value, $req, $err);
      break;
    }
  }

  private function verifyText($value, $ruleName, $required, $error) {
    if(is_null($value)) throw new Exception(_("Value missing"));
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
