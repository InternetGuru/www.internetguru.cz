<?php

namespace IGCMS\Plugins;

use Cz\Git\GitException;
use Cz\Git\GitRepository;
use DOMElement;
use DOMNode;
use DOMText;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\GetContentStrategyInterface;
use IGCMS\Core\Git;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class InputVar
 * @package IGCMS\Plugins
 */
class InputVar extends Plugin implements SplObserver, GetContentStrategyInterface {
  /**
   * @var string|null
   */
  private $userCfgPath = null;
  /**
   * @var DOMDocumentPlus|null
   */
  private $cfg = null;
  /**
   * @var string|null
   */
  private $formId = null;
  /**
   * @var array|null
   */
  private $logins = null;
  /**
   * @var string
   */
  private $getOk = "ivok";
  /**
   * @var array
   */
  private $vars = [];
  /**
   * @var string|null
   */
  private $message = null;
  /**
   * @var string|null
   */
  private $messageSubject = null;
  /**
   * @var string|null
   */
  private $messageTo = null;

  /**
   * InputVar constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $this->userCfgPath = USER_FOLDER."/".$this->pluginDir."/".$this->className.".xml";
    $s->setPriority($this, 60);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    try {
      if ($subject->getStatus() == STATUS_POSTPROCESS) {
        $this->processPost();
      }
      if (!in_array($subject->getStatus(), [STATUS_INIT, STATUS_PROCESS])) {
        return;
      }
      $this->cfg = self::getXML();
      if ($subject->getStatus() == STATUS_INIT) {
        if (isset($_GET[$this->getOk])) {
          Logger::user_success(_("Changes successfully saved"));
        }
        $this->loadVars();
      }
      foreach ($this->cfg->documentElement->childElementsArray as $element) {
        if ($element->nodeName == "set") {
          continue;
        }
        if ($element->nodeName == "login") {
          continue;
        }
        if ($element->nodeName == "message") {
          continue;
        }
        try {
          $element->getRequiredAttribute("id"); // only check
        } catch (Exception $exc) {
          Logger::user_warning($exc->getMessage());
          continue;
        }
        switch ($element->nodeName) {
          case "var":
            if ($subject->getStatus() != STATUS_PROCESS) {
              continue;
            }
            $this->processRule($element);
          case "fn":
            if ($subject->getStatus() != STATUS_INIT) {
              continue;
            }
            $this->processRule($element);
            break;
          default:
            Logger::user_warning(sprintf(_("Unknown element name %s"), $element->nodeName));
        }
      }
    } catch (Exception $exc) {
      if ($exc->getCode() === 1) {
        Logger::user_error($exc->getMessage());
      } else {
        Logger::critical($exc->getMessage());
      }
    }
  }

  /**
   * @throws Exception
   */
  private function processPost () {
    if (!is_file($this->userCfgPath)) {
      return;
    }
    $req = Cms::getVariableValue("validateform-".$this->formId);
    if (is_null($req)) {
      return;
    }
    if (!empty($this->logins)) {
      if (!isset($req["username"]) || !isset($req["passwd"])) {
        throw new Exception(_("Invalid credentials"), 1);
      }
      Logger::info(sprintf(_('Form id %s request from username %s'), $this->formId, $req["username"]));
      if (!isset($this->logins[$req["username"]])
        || !hash_equals($this->logins[$req["username"]], crypt($req["passwd"], $this->logins[$req["username"]]))) {
        throw new Exception(_("Invalid username or password"), 1);
      }
    } else {
      Logger::info(sprintf(_('Form id %s anonymous request'), $this->formId));
    }
    if (!isset($req["userfilehash"])) {
      throw new Exception(_("Missing userfilehash value"));
    }
    if ($req["userfilehash"] != file_hash($this->userCfgPath)) {
      throw new Exception(_("Data file has changed during administration"));
    }
    $var = null;
    foreach ($req as $key => $value) {
      if (isset($this->vars[$key])) {
        if ($this->vars[$key]->hasAttribute("var")) {
          $this->vars[$key]->setAttribute("var", normalize($this->className."-$value"));
        } else {
          $this->vars[$key]->nodeValue = htmlspecialchars($value);
        }
        $var = $this->vars[$key];
      }
    }
    if (is_null($var)) {
     throw new Exception(_("No data to save"));
    }
    $toSave = $var->ownerDocument->saveXML();
    if ($toSave !== file_get_contents($this->userCfgPath)) {
      /** @var DOMDocumentPlus $document */
      $document = new DOMDocumentPlus();
      $document->loadXML($toSave);
      $commit = $document->save($this->userCfgPath, null, 'InputVar user save', $req["username"]);
      if (!$commit) {
        throw new Exception(_("Unable to save user config"));
      }
      if (!is_null($this->message)) {
        $this->sendDiffEmail($req["username"], $commit);
      }
      clear_nginx();
    }
    redir_to(build_local_url(["path" => get_link(), "query" => $this->className."&".$this->getOk], true));
  }

  /**
   * @param string $user
   * @param string $commit
   * @throws GitException
   */
  private function sendDiffEmail ($user, $commit) {
    try {
      $repo = Git::Instance();
    } catch (Exception $exc) {
      return;
    }
    $diffLines = $repo->execute(['diff', "$commit~", "$commit"]);
    $diff = "";
    foreach ($diffLines as $key => $line) {
      if ($key < 4) {
        continue;
      }
      if (strpos($line, '-') !== 0 && strpos($line, '+') !== 0) {
        continue;
      }
      $line = str_replace("</var>", "", $line);
      $line = preg_replace("/ *<var [^>]+>/", "", $line);
      $diff .= trim(html_entity_decode($line), "\n")."\n";
    }
    $vars = array_merge(Cms::getAllVariables(), [
      'user' => [
        'value' => $user,
        'cacheable' => 'false',
      ],
      'diff' => [
        'value' => $diff,
        'cacheable' => 'false',
      ],
      'date' => [
        'value' => date("j. n. Y H:i:s"),
        'cacheable' => 'false',
      ],
    ]);
    $message = replace_vars($this->message, $vars);
    $subject = replace_vars($this->messageSubject, $vars);
    try {
      send_mail($this->messageTo, '', 'info@internetguru.cz', 'Internet Guru', '', $message, $subject, '');
    } catch (Exception $exc) {
      Logger::error($exc->getMessage());
    }
  }

  /**
   * @throws Exception
   */
  private function loadVars () {
    foreach ($this->cfg->documentElement->childElementsArray as $element) {
      if ($element->nodeName == "var") {
        $this->vars[$element->getRequiredAttribute("id")] = $element;
      }
      if ($element->nodeName == "login") {
        $this->logins[$element->getRequiredAttribute('id')] = $element->getRequiredAttribute('password');
      }
      if ($element->nodeName == "message") {
        $this->messageSubject = $element->getRequiredAttribute('subject');
        $this->messageTo = $element->getRequiredAttribute('to');
        $this->message = $element->nodeValue;
      }
    }
  }

  /**
   * @param DOMElementPlus $element
   * @throws Exception
   */
  private function processRule (DOMElementPlus $element) {
    $eId = $element->getAttribute("id");
    $name = $element->nodeName;
    $attributes = [];
    foreach ($element->attributes as $attr) $attributes[$attr->nodeName] = $attr->nodeValue;
    $lastElm = $element->processVariables(Cms::getAllVariables(), [], true); // pouze posledni node
    $cacheable = $element->getAttribute("cacheable") !== "false"; // empty or true => true
    if (is_null($lastElm)
      || (gettype($lastElm) == "object" && (new \ReflectionClass($lastElm))->getShortName() != "DOMElementPlus"
        && !$lastElm->isSameNode($element))
    ) {
      if (is_null($lastElm)) {
        $lastElm = new DOMText("");
      }
      $var = $element->ownerDocument->createElement("var");
      foreach ($attributes as $aName => $aValue) {
        $var->setAttribute($aName, $aValue);
      }
      $var->appendChild($lastElm);
      $lastElm = $var;
    }
    if (!$lastElm->hasAttribute("fn")) {
      $this->setVar($name, $eId, $lastElm, $cacheable);
      return;
    }
    $lastElmFn = $lastElm->getAttribute("fn");
    if (strpos($lastElmFn, "-") === false) {
      $lastElmFn = $this->className."-$lastElmFn";
    }
    $result = Cms::getFunction($lastElmFn);
    if ($lastElm->hasAttribute("fn") && !is_null($result)) {
      try {
        $result = Cms::applyUserFn($lastElmFn, $lastElm);
      } catch (Exception $exc) {
        Logger::user_warning(sprintf(_("Unable to apply function: %s"), $exc->getMessage()));
        return;
      }
    }
    if (!is_null($result)) {
      $this->setVar($lastElm->nodeName, $eId, $result, $cacheable);
      return;
    }
    try {
      $function = $this->register($lastElm);
    } catch (Exception $exc) {
      Logger::user_warning(sprintf(_("Unable to register function %s: %s"), $lastElm->getAttribute("fn"), $exc->getMessage()));
      return;
    }
    if ($lastElm->nodeName == "fn") {
      Cms::setFunction($eId, $function);
    } else {
      Cms::setVariable($eId, $function($lastElm), $cacheable);
    }
  }

  /**
   * @param string $var
   * @param string $name
   * @param mixed $value
   * @param bool $cacheable
   * @throws Exception
   */
  private function setVar ($var, $name, $value, $cacheable=true) {
    if ($var == "fn") {
      Cms::setFunction($name, $value);
    } else {
      Cms::setVariable($name, $value, $cacheable);
    }
  }

  /**
   * @param DOMElementPlus $el
   * @return \Closure|null
   * @throws Exception
   */
  private function register (DOMElementPlus $el) {
    $function = null;
    switch ($el->getAttribute("fn")) {
      case "hash":
        $algo = $el->hasAttribute("algo") ? $el->getAttribute("algo") : null;
        $function = $this->createFnHash($this->parse($algo));
        break;
      case "strftime":
        $format = $el->hasAttribute("format") ? $el->getAttribute("format") : null;
        $function = $this->createFnStrftime($format);
        break;
      case "sprintf":
        $function = $this->createFnSprintf($el->nodeValue);
        break;
      case "pregreplace":
        $pattern = $el->hasAttribute("pattern") ? $el->getAttribute("pattern") : null;
        $replacement = $el->hasAttribute("replacement") ? $el->getAttribute("replacement") : null;
        $function = $this->createFnPregReplace($pattern, $replacement);
        break;
      case "replace":
        $toReplace = [];
        foreach ($el->childElementsArray as $dataElm) {
          if ($dataElm->nodeName != "data") {
            continue;
          }
          try {
            $name = $dataElm->getRequiredAttribute("name");
          } catch (Exception $exc) {
            Logger::user_warning($exc->getMessage());
            continue;
          }
          $toReplace[$name] = $this->parse($dataElm->nodeValue);
        }
        $function = $this->createFnReplace($toReplace);
        break;
      case "sequence":
        $seq = [];
        foreach ($el->childElementsArray as $call) {
          if ($call->nodeName != "call") {
            continue;
          }
          if (!strlen($call->nodeValue)) {
            Logger::user_warning(_("Element call missing content"));
            continue;
          }
          $seq[] = $call->nodeValue;
        }
        $function = $this->createFnSequence($seq);
        break;
      default:
        throw new Exception(_("Unknown function name"));
    }
    return $function;
  }

  /**
   * @param string|null $algo
   * @return \Closure
   */
  private function createFnHash ($algo = null) {
    if (!in_array($algo, hash_algos())) {
      $algo = "crc32b";
    }
    return function(DOMNode $node) use ($algo) {
      return hash($algo, $node->nodeValue);
    };
  }

  /**
   * @param string $value
   * @return string
   */
  private function parse ($value) {
    return replace_vars($value, Cms::getAllVariables(), strtolower($this->className."-"));
  }

  /**
   * @param string|null $format
   * @return \Closure
   */
  private function createFnStrftime ($format = null) {
    if (is_null($format)) {
      $format = "%m/%d/%Y";
    }
    return function(DOMNode $node) use ($format) {
      $value = $node->nodeValue;
      $date = trim(strftime($format, strtotime($value)));
      return $date ? $date : $value;
    };
  }

  /**
   * @param string $format
   * @return \Closure
   * @throws Exception
   */
  private function createFnSprintf ($format) {
    if (!strlen($format)) {
      throw new Exception(_("No content found"));
    }
    return function(DOMNode $node) use ($format) {
      $elements = [];
      foreach ($node->childNodes as $child) {
        if ($child->nodeType != XML_ELEMENT_NODE) {
          break;
        }
        $elements[] = $child->nodeValue;
      }
      if (empty($elements)) {
        $elements[] = $node->nodeValue;
      }
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      $temp = @vsprintf($format, $elements);
      if ($temp === false) {
        return $format;
      }
      return $temp;
    };
  }

  /**
   * @param string $pattern
   * @param string $replacement
   * @return \Closure
   * @throws Exception
   */
  private function createFnPregReplace ($pattern, $replacement) {
    if (is_null($pattern)) {
      throw new Exception(_("No pattern found"));
    }
    if (is_null($replacement)) {
      throw new Exception(_("No replacement found"));
    }
    return function(DOMNode $node) use ($pattern, $replacement) {
      return preg_replace("/$pattern/", $replacement, htmlspecialchars($node->nodeValue));
    };
  }

  /**
   * @param array $replace
   * @return \Closure
   * @throws Exception
   */
  private function createFnReplace (Array $replace) {
    if (empty($replace)) {
      throw new Exception(_("No data found"));
    }
    return function(DOMNode $node) use ($replace) {
      return str_replace(array_keys($replace), $replace, htmlspecialchars($node->nodeValue));
    };
  }

  /**
   * @param array $call
   * @return \Closure
   * @throws Exception
   */
  private function createFnSequence (Array $call) {
    if (empty($call)) {
      throw new Exception(_("No data found"));
    }
    return function(DOMNode $node) use ($call) {
      foreach ($call as $function) {
        if (strpos($function, "-") === false) {
          $function = $this->className."-$function";
        }
        try {
          $node = new DOMElement("any", Cms::applyUserFn($function, $node));
        } catch (Exception $exc) {
          Logger::user_warning(sprintf(_("Sequence call skipped: %s"), $exc->getMessage()));
        }
      }
      return $node->nodeValue;
    };
  }

  /**
   * @return HTMLPlus|null
   * @throws Exception
   */
  public function getContent () {
    if (!isset($_GET[$this->className])) {
      return null;
    }
    $sets = $this->cfg->getElementsByTagName("set");
    if (!$sets->length) {
      Logger::warning(_('No set elements found'));
      return null;
    }
    $newContent = self::getHTMLPlus();
    /** @var DOMElementPlus $form */
    $form = $newContent->getElementsByTagName("form")->item(0);
    $this->formId = $form->getAttribute("id");
    /** @var DOMElementPlus $fieldset */
    $fieldset = $newContent->getElementsByTagName("fieldset")->item(0);
    /** @var DOMElementPlus $setElm */
    foreach ($sets as $setElm) {
      try {
        $setElm->getRequiredAttribute("type"); // only check
      } catch (Exception $exc) {
        Logger::user_warning($exc->getMessage());
        continue;
      }
      $this->createFieldset($newContent, $fieldset, $setElm);
    }
    $fieldset->parentNode->removeChild($fieldset);
    $vars = [];
    if (is_null($this->logins)) {
      $vars["nopasswd"] = [
        "value" => "",
        "cacheable" => true,
      ];
    }
    $vars["action"] = [
      "value" => "?".$this->className,
      "cacheable" => true,
    ];
    $vars["userfilehash"] = [
      "value" => file_hash($this->userCfgPath),
      "cacheable" => false,
    ];
    $newContent->processVariables($vars);
    return $newContent;
  }

  /**
   * @param HTMLPlus $content
   * @param DOMElementPlus $fieldset
   * @param DOMElementPlus $set
   * @throws Exception
   */
  private function createFieldset (HTMLPlus $content, DOMElementPlus $fieldset, DOMElementPlus $set) {
    switch ($set->getAttribute("type")) {
      case "text":
      case "textarea":
      case "select":
        $this->createFs($content, $fieldset, $set, $set->getAttribute("type"));
        break;
      default:
        Logger::user_warning(sprintf(_("Unsupported attribute type value '%s'"), $set->getAttribute("type")));
    }
  }

  /**
   * @param DOMDocumentPlus $content
   * @param DOMElementPlus $fieldset
   * @param DOMElementPlus $set
   * @param string $type
   * @throws Exception
   */
  private function createFs (DOMDocumentPlus $content, DOMElementPlus $fieldset, DOMElementPlus $set, $type) {
    $for = $set->getAttribute("for");
    foreach (explode(" ", $for) as $rule) {
      $inputVar = null;
      $list = $this->filterVars($rule);
      if (!count($list)) {
        continue;
      }
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($fieldset, true));
      switch ($type) {
        case "text":
        case "textarea":
          $inputVar = $this->createTextFs($list, $set, $type);
          break;
        case "select":
          $inputVar = $this->createSelectFs($list, $set);
          break;
        default:
          // double check?
          return;
      }
      if (is_null($inputVar)) {
        Logger::user_warning(sprintf(_("Cannot create fieldset for %s"), $rule)); // never happend?
        continue;
      }
      $vars["group"] = [
        "value" => strlen($set->nodeValue) ? $set->nodeValue : $rule,
        "cacheable" => true,
      ];
      $vars["inputs"] = [
        "value" => $inputVar,
        "cacheable" => true,
      ];
      $doc->processVariables($vars);
      $fieldset->parentNode->insertBefore($content->importNode($doc->documentElement, true), $fieldset);
    }
  }

  /**
   * @param string $rule
   * @return array
   */
  private function filterVars ($rule) {
    $return = [];
    foreach ($this->vars as $var) {
      $varId = $var->getAttribute("id");
      /** @noinspection PhpUsageOfSilenceOperatorInspection */
      if (!@preg_match("/^".$rule."$/", $varId)) {
        continue;
      }
      $return[] = $var;
    }
    return $return;
  }

  /**
   * TODO refactor: neopakovat kod ...
   * @param array $list
   * @param DOMElementPlus $set
   * @param string $type
   * @return DOMNode
   * @throws Exception
   */
  private function createTextFs (Array $list, DOMElementPlus $set, $type) {
    $inputDoc = new DOMDocumentPlus();
    $inputVar = $inputDoc->appendChild($inputDoc->createElement("var"));
    $dlElm = $inputVar->appendChild($inputDoc->createElement("dl"));
    foreach ($list as $varElm) {
      $varElmId = normalize($this->className."-".$varElm->getAttribute("id"));
      $dtElm = $inputDoc->createElement("dt");
      $label = $inputDoc->createElement("label", $varElm->getAttribute("id"));
      $dtElm->appendChild($label);
      $label->setAttribute("for", $varElmId);
      $dlElm->appendChild($dtElm);
      $ddElm = $inputDoc->createElement("dd");

      if ($set->getAttribute("type") == "textarea") {
        $text = $inputDoc->createElement("textarea");
        $text->setAttribute("cols", 10);
        $text->setAttribute("rows", 3);
        if ($set->hasAttribute("pattern")) {
          $text->setAttribute("data-pattern", $set->getAttribute("pattern"));
        }
        $text->nodeValue = $varElm->nodeValue;
      } else {
        $text = $inputDoc->createElement("input");
        $text->setAttribute("type", "text");
        if ($set->hasAttribute("pattern")) {
          $text->setAttribute("pattern", $set->getAttribute("pattern"));
        }
        $text->setAttribute("value", $varElm->nodeValue);
      }

      $ddElm->appendChild($text);
      $text->setAttribute("id", $varElmId);
      $text->setAttribute("name", $varElm->getAttribute("id"));
      if ($set->hasAttribute("placeholder")) {
        $text->setAttribute("placeholder", $set->getAttribute("placeholder"));
      }
      if ($varElm->hasAttribute("required")) {
        $text->setAttribute("required", $varElm->getAttribute("required"));
      }
      $ddElm->appendChild($text);
      $dlElm->appendChild($ddElm);
    }
    return $inputVar;
  }

  /**
   * @param array $list
   * @param DOMElementPlus $set
   * @return DOMNode
   * @throws Exception
   */
  private function createSelectFs (Array $list, DOMElementPlus $set) {
    $inputDoc = new DOMDocumentPlus();
    $inputVar = $inputDoc->appendChild($inputDoc->createElement("var"));
    $dlElm = $inputVar->appendChild($inputDoc->createElement("dl"));
    $dataListArray = [];
    foreach (explode(" ", $set->getAttribute("datalist")) as $data) {
      $dataListArray = array_merge($dataListArray, $this->filterVars($data));
    }
    foreach ($list as $varElm) {
      $varElmId = normalize($this->className."-".$varElm->getAttribute("id"));
      try {
        $select = $this->createSelect($inputDoc, $dataListArray, $varElm->getAttribute("id"));
      } catch (Exception $exc) {
        Logger::critical($exc->getMessage());
        continue;
      }
      $dtElm = $inputDoc->createElement("dt");
      $label = $inputDoc->createElement("label", $varElm->nodeValue);
      $dtElm->appendChild($label);
      $label->setAttribute("for", $varElmId);
      $dlElm->appendChild($dtElm);
      $ddElm = $inputDoc->createElement("dd");
      $ddElm->appendChild($select);
      $select->setAttribute("id", $varElmId);
      $dlElm->appendChild($ddElm);
    }
    return $inputVar;
  }

  /**
   * @param DOMDocumentPlus $doc
   * @param array $vars
   * @param string $selectId
   * @return DOMElementPlus
   */
  private function createSelect (DOMDocumentPlus $doc, Array $vars, $selectId) {
    $select = $doc->createElement("select");
    $select->setAttribute("name", $selectId);
    $select->setAttribute("required", "required");
    /*if(is_null($this->vars[$selectId]->firstElement) ||
      !$this->vars[$selectId]->firstElement->hasAttribute("var")) {
      throw new Exception(sprintf(_("Variable %s missing inner element with attribute var"), $selectId));
    }*/
    $selected = substr($this->vars[$selectId]->getAttribute("var"), strlen($this->className) + 1);
    foreach ($vars as $var) {
      $varId = $var->getAttribute("id");
      $value = strlen($var->nodeValue) ? $var->nodeValue : $varId;
      $option = $doc->createElement("option", $value);
      $doc->appendChild($option);
      $option->setAttribute("value", $varId);
      if ($varId == $selected) {
        $option->setAttribute("selected", "selected");
      }
      $select->appendChild($option);
    }
    return $select;
  }

}
