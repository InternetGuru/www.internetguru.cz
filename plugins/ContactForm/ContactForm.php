<?php

namespace IGCMS\Plugins;

use DOMXPath;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use PHPMailer;
use SplObserver;
use SplSubject;

/**
 * Class ContactForm
 * @package IGCMS\Plugins
 */
class ContactForm extends Plugin implements SplObserver, ModifyContentStrategyInterface {
  /**
   * @var DOMDocumentPlus
   */
  private $cfg;
  /**
   * @var array
   */
  private $vars = array();
  /**
   * @var array
   */
  private $formsElements = array();
  /**
   * @var array
   */
  private $formNames = array();
  /**
   * @var array
   */
  private $formGroupValues = array();
  /**
   * @var array
   */
  private $formIds;
  /**
   * @var array
   */
  private $formVars;
  /**
   * @var array
   */
  private $formValues;
  /**
   * @var array
   */
  private $formItems = array();
  /**
   * @var array
   */
  private $messages;
  /**
   * @var array
   */
  private $forms = array();
  /**
   * @var string|null
   */
  private $prefix = null;
  /**
   * @var string
   */
  const FORM_ITEMS_QUERY = "//input | //textarea | //select";
  /**
   * @var bool
   */
  const DEBUG = false;

  /**
   * ContactForm constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 20);
    $this->prefix = strtolower($this->className);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update(SplSubject $subject) {
    if(!Cms::isActive()) {
      $subject->detach($this);
      return;
    }
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
    $this->cfg = $this->getXML();
    $this->createGlobalVars();
    foreach($this->forms as $formId => $form) {
      $form->addClass("fillable");
      $form->addClass("validable");
      $form->addClass("editable");
      $formVar = $this->parseForm($form);
      $this->formsElements[normalize($this->className)."-$formId"] = $formVar;
      Cms::setVariable($formId, $formVar);
    }
  }

  private function proceedForm() {
    $formToSend = null;
    $formIdToSend = null;
    foreach($this->forms as $formId => $form) {
      $prefixedFormId = normalize($this->className)."-$formId";
      $htmlForm = $this->formsElements[$prefixedFormId]->documentElement->firstElement;
      $formValues = Cms::getVariable("validateform-$prefixedFormId");
      $fv = $this->createFormVars($htmlForm);
      if(isset($_GET["cfok"]) && $_GET["cfok"] == $formId) {
        Logger::user_success($fv["success"]);
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
    try {
      foreach($this->formValues as $name => $value) {
        if(is_null($value) || !strlen($value)) $this->formValues[$name] = $this->formVars["nothing"];
      }
      $variables = array_merge($this->formValues, Cms::getAllVariables());
      foreach($this->formVars as $k => $v) {
        $this->formVars[$k] = replaceVariables($v, $variables);
      }
      if(array_key_exists($formIdToSend, $this->messages) && strlen($this->messages[$formIdToSend])) {
        $msg = replaceVariables($this->messages[$formIdToSend], $variables);
      } else $msg = $this->createMessage();
      $this->sendForm($formIdToSend, $msg);
      if(self::DEBUG) {
        var_dump($this->formVars);
        var_dump($this->formValues);
      }
    } catch(Exception $e) {
      $message = sprintf(_("Unable to send form %s: %s"), "<a href='#".strtolower($this->className)."-".$formIdToSend."'>"
          .$formToSend->getAttribute("id")."</a>", $e->getMessage());
      Logger::user_error($message);
    }
  }

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent(HTMLPlus $content) {
    $xpath = new DOMXPath($content);
    $forms = $xpath->query("//*[contains(@var, '$this->prefix-')]");
    if(!$forms->length) return;
    if(!strlen($this->vars["adminaddr"]) || !preg_match("/".EMAIL_PATTERN."/", $this->vars["adminaddr"])) {
      Logger::user_warning(_("Admin address is not set or invalid"));
    }
    /** @var DOMElementPlus $f */
    foreach($forms as $f) {
      $id = substr($f->getAttribute("var"), strlen($this->prefix)+1);
      if(!array_key_exists($id, $this->forms)) {
        Logger::user_warning(sprintf(_("Form id '%s' not found"), $id));
        continue;
      }
      if(!array_key_exists($id, $this->messages)) {
        Logger::user_warning(sprintf(_("Missing message for form id '%s'"), $id));
        continue;
      }
    }
  }

  /**
   * @param DOMElementPlus $form
   * @return DOMDocumentPlus
   */
  private function parseForm(DOMElementPlus $form) {
    $prefix = normalize($this->className);
    $doc = new DOMDocumentPlus();
    $var = $doc->appendChild($doc->createElement("var"));
    /** @var DOMElementPlus $htmlForm */
    $htmlForm = $var->appendChild($doc->importNode($form, true));
    $formId = $htmlForm->getAttribute("id");
    $htmlForm->removeAllAttributes(array("id", "class"));
    $htmlForm->setAttribute("method", "post");
    $htmlForm->setAttribute("action", HTMLPlusBuilder::getLinkToId(getCurLink()));
    $htmlForm->setAttribute("id", "$prefix-$formId");
    $this->registerFormItems($htmlForm, "$prefix-$formId-");
    return $doc;
  }

  /**
   * @param string $formIdToSend
   * @param string $msg
   * @throws Exception
   */
  private function sendForm($formIdToSend, $msg) {
    if(!strlen($this->formVars["adminaddr"])) throw new Exception(_("Missing admin address"));
    if(!preg_match("/".EMAIL_PATTERN."/", $this->formVars["adminaddr"]))
      throw new Exception(sprintf(_("Invalid admin email address: '%s'"), $this->formVars["adminaddr"]));
    if(strlen($this->formVars["email"]) && !preg_match("/".EMAIL_PATTERN."/", $this->formVars["email"]))
      throw new Exception(sprintf(_("Invalid client email address: '%s'"), $this->formVars["email"]));
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
    if(!is_null(Cms::getLoggedUser())) {
      Cms::notice("<pre><code class='nohighlight'>$msg</code></pre>");
      return;
    }
    $this->sendMail($adminaddr, $adminname, $email, $name, $msg, $bcc);
    Logger::mail(sprintf(_("Sending e-mail: %s"),
      "to=$adminname<$adminaddr>; replyto=$name<$email>; bcc=$bcc; msg=$msg"));
    if(strlen($this->formVars["sendcopy"])) {
      if(!strlen($this->formVars["email"]))
        throw new Exception(_("Unable to send copy to empty client address"));
      $msg = $this->formVars["copymsg"]."\n\n$msg";
      $bcc = "";
      $this->sendMail($email, $name, $adminaddr, $adminname, $msg, $bcc);
    }
    redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => "cfok=".$formIdToSend)));
  }

  /**
   * @param string $mailto
   * @param string $mailtoname
   * @param string $replyto
   * @param string $replytoname
   * @param string $msg
   * @param string $bcc
   * @throws Exception
   */
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
    if(!$mail->send()) throw new Exception($mail->ErrorInfo);
  }

  private function createGlobalVars() {
    foreach($this->cfg->documentElement->childElementsArray as $e) {
      try {
        switch($e->nodeName) {
          case "var":
          $id = $e->getRequiredAttribute("id");
          $this->vars[$id] = $e->nodeValue;
          break;
          case "form":
          $id = $e->getRequiredAttribute("id");
          $this->forms[$id] = $e;
          break;
          case "message":
          $id = $e->getRequiredAttribute("for");
          $this->messages[$id] = $e->nodeValue;
          break;
        }
      } catch(Exception $ex) {
        Logger::user_warning(sprintf(_("Skipped element %s: %s"), $e->nodeName, $ex->getMessage()));
      }
    }
    if(self::DEBUG) $this->vars["adminaddr"] = "debug@somedomain.cz";
  }

  /**
   * @param DOMElementPlus $form
   * @return array
   */
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

  /**
   * @param DOMElementPlus $form
   * @param string $prefix
   */
  private function registerFormItems(DOMElementPlus $form, $prefix) {
    $idInput = $form->ownerDocument->createElement("input");
    $idInput->setAttribute("name", $this->className);
    $idInput->setAttribute("type", "hidden");
    $idInput->setAttribute("value", $form->getAttribute("id"));
    $e = null;
    $this->formItems = array();
    $this->formValues = array();
    $this->formNames = array($this->className);
    $this->formIds = array();
    $this->formGroupValues = array();
    $xpath = new DOMXPath($form->ownerDocument);
    /** @var DOMElementPlus $e */
    foreach($xpath->query(self::FORM_ITEMS_QUERY) as $e) {
      if($e->nodeName == "textarea") {
        if(!$e->hasAttribute("cols")) $e->setAttribute("cols", 40);
        if(!$e->hasAttribute("rows")) $e->setAttribute("rows", 7);
      }
      $type = null;
      if($e->nodeName == "input") {
        try {
          $type = $e->getRequiredAttribute("type");
        } catch(Exception $ex) {
          Logger::user_warning($ex->getMessage());
          continue;
        }
      }
      $this->formItems[] = $e;
      $defId = strlen($e->getAttribute("name")) ? normalize($e->getAttribute("name")) : "item";
      $id = $this->processFormItem($this->formIds, $e, "id", $prefix, $defId, false);
      $this->processFormItem($this->formNames, $e, "name", "", $id, true);
      if(is_null(Cms::getLoggedUser()) || $type != "submit") continue;
      $e->setAttribute("value", _("Show message"));
      $e->setAttribute("title", _("Not sending form if logged user"));
    }
    $tmp = $e->parentNode;
    while(!is_null($tmp) && $tmp->nodeName != "form") {
      if($tmp->nodeName == "label") {
        $e = $tmp;
        break;
      }
      $tmp = $tmp->parentNode;
    }
    $e->parentNode->appendChild($idInput);
    foreach($xpath->query("//label") as $e) {
      if($e->hasAttribute("for")) {
        $for = $prefix.$e->getAttribute("for");
        $e->setAttribute("for", $for);
        $this->formIds[$for][] = $e;
        continue;
      }
      /** @var DOMElementPlus $f */
      foreach($xpath->query("input | textarea | select", $e) as $f) {
        $this->formIds[$f->getAttribute("id")][] = $e;
      }
    }
    foreach($this->formItems as $e) {
      $this->completeFormItem($e);
    }
  }

  /**
   * @param DOMElementPlus $e
   */
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

  /**
   * @param DOMElementPlus $e
   * @param string $default
   */
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

  /**
   * @param array $register
   * @param DOMElementPlus $e
   * @param string $aName
   * @param string $prefix
   * @param string $default
   * @param bool $arraySupport
   * @return string
   */
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

  /**
   * @return string
   */
  private function createMessage() {
    $msg = array();
    foreach($this->formValues as $k => $v) {
      if(is_array($v)) $v = implode(", ", $v);
      $msg[] = "$k: $v";
    }
    return implode("\n", $msg);
  }

}

?>
