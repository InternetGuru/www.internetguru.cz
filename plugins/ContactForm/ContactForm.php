<?php

#todo: invalid admin e-mail address warning
#todo: checkbox default value = trim first label

class ContactForm extends Plugin implements SplObserver, ContentStrategyInterface {

  private $cfg;
  private $vars = array();
  private $formsElements = array();
  private $formIds;
  private $formVars;
  private $formValues;
  private $formItems = array();
  private $messages;
  private $errors = array();
  const FORM_ITEMS_QUERY = "//input | //textarea | //select";
  const CSS_WARNING = "contactform-warning";
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 20);
  }

  public function update(SplSubject $subject) {
    if($this->detachIfNotAttached("HtmlOutput")) return;
    if($this->detachIfNotAttached("ValidateForm")) return;
    switch($subject->getStatus()) {
      case STATUS_INIT:
      $this->initForm();
      break;
      case STATUS_PROCESS:
      $this->proceedForm();
      break;
    }
  }

  private function initForm() {
    $this->cfg = $this->getDOMPlus();
    $this->createGlobalVars();
    foreach($this->forms as $formId => $form) {
      $form->addClass("fillable");
      $form->addClass("validable");
      $formVar = $this->parseForm($form);
      $this->formsElements[normalize(get_class($this))."-$formId"] = $formVar;
    }
  }

  private function proceedForm() {

    $formToSend = null;
    $formIdToSend = null;

    foreach($this->forms as $formId => $form) {
      $prefixedFormId = normalize(get_class($this))."-$formId";
      $htmlForm = $this->formsElements[$prefixedFormId]->documentElement->firstElement;
      $formValues = Cms::getVariable("validateform-$prefixedFormId");
      $fv = $this->createFormVars($htmlForm);
      if(isset($_GET["cfok"]) && $_GET["cfok"] == $formId) {
        Cms::addMessage($fv["success"], Cms::MSG_SUCCESS);
      }
      if(is_null($formValues)) continue;
      foreach(array("email", "name", "sendcopy") as $name) {
        $fv[$name] = isset($formValues[$name]) ? $formValues[$name] : "";
      }
      $this->formValues = $formValues;
      $this->formVars = $fv;
      $this->formValues["form_id"] = $formId;
      $formToSend = $form;
      $formIdToSend = $formId;
    }

    if(is_null($formToSend)) return;
    foreach($this->formValues as $name => $value) {
      if(is_null($value) || !strlen($value)) $this->formValues[$name] = $this->formVars["nothing"];
    }
    try {
      $variables = array_merge($this->formValues, Cms::getAllVariables());
      foreach($this->formVars as $k => $v) {
        $this->formVars[$k] = replaceVariables($v, $variables);
      }
      if(array_key_exists($formIdToSend, $this->messages)) {
        $msg = replaceVariables($this->messages[$formIdToSend], $variables);
      } else $msg = $this->createMessage($this->cfg, $formIdToSend);
      if(IS_LOCALHOST) throw new Exception("Not sending (at localhost)");
      $this->sendForm($formToSend, $msg);
      redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => "cfok=".$formIdToSend)));
    } catch(Exception $e) {
      $message = sprintf(_("Unable to send form %s: %s"), "<a href='#".strtolower(get_class($this))."-".$formIdToSend."'>"
          .$formToSend->getAttribute("id")."</a>", $e->getMessage());
      new Logger($message, Logger::LOGGER_ERROR);
    }
  }

  public function getContent(HTMLPlus $content) {
    $content->processVariables($this->formsElements);
    return $content;
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
    $htmlForm->setAttribute("id", "$prefix-$formId");
    $this->registerFormItems($htmlForm, "$prefix-$formId-");
    return $doc;
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
    if(strlen($this->formVars["sendcopy"])) {
      if(!strlen($this->formVars["email"]))
        throw new Exception(_("Unable to send copy to empty client address"));
      $msg = $this->formVars["copymsg"]."\n\n$msg";
      $bcc = "";
      $this->sendMail($email, $name, $adminaddr, $adminname, $msg, $bcc);
    }
    if(self::DEBUG) {
      var_dump($this->formVars);
      var_dump($this->formValues);
    }
  }

  private function sendMail($mailto, $mailtoname, $replyto, $replytoname, $msg, $bcc) {
    if(self::DEBUG) {
      echo $msg;
      throw new Exception (sprintf("Sending e-mail to %s skipped", $mailto));
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
    new Logger(sprintf(_("Sending mail: to=%s<%s>; replyto=%s<%s>; bcc=%s; subject=%s; msg=%s"),
      $mailtoname, $mailto, $replytoname, $replyto, $bcc, $mail->Subject, $msg), null, null, null, "mail");
    if(!$mail->send()) throw new Exception($mail->ErrorInfo);
    new Logger(sprintf(_("E-mail successfully sent to %s"), $mailto));
  }

  private function createGlobalVars() {
    foreach($this->cfg->documentElement->childElementsArray as $e) {
      try {
        switch($e->nodeName) {
          case "var":
          $this->validateElement($e, "id");
          $this->vars[$e->getAttribute("id")] = $e->nodeValue;
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

}

?>
