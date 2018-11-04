<?php

namespace IGCMS\Plugins;

use Exception;
use Gajus\Dindent\Indenter;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use IGCMS\Core\XMLBuilder;
use Markdownify\ConverterExtra;
use Michelf\MarkdownExtra;
use SplObserver;
use SplSubject;
use XSLTProcessor;

class Markdown extends Plugin implements SplObserver {

  /**
   * @param Plugins|SplSubject $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_PREINIT) {
      return;
    }
    foreach (get_modified_files() as $file) {
      try {
        $pathinfo = pathinfo($file);
        switch ($pathinfo["extension"]) {
          case "md":
            $mdFile = USER_FOLDER."/$file";
            $htmlPlusFile = USER_FOLDER."/".$pathinfo["dirname"]."/".$pathinfo["filename"].".html";
            $mdFileMtime = filemtime($mdFile);
            if (stream_resolve_include_path($htmlPlusFile)) {
              $htmlPlusFileMtime = filemtime($htmlPlusFile);
              if ($mdFileMtime < $htmlPlusFileMtime) {
                throw new Exception("Unexpected exception");
              }
              if ($htmlPlusFileMtime === $mdFileMtime) {
                return;
              }
              rename_incr($htmlPlusFile, "$htmlPlusFile.");
            }
            $htmlPlus = $this->md2htmlplus(file_get_contents($mdFile));
            fput_contents($htmlPlusFile, $htmlPlus);
            touch($htmlPlusFile, $mdFileMtime);
            break;
          case "html":
            $mdFile = USER_FOLDER."/".$pathinfo["dirname"]."/".$pathinfo["filename"].".md";
            $htmlPlusFile = USER_FOLDER."/$file";
            $htmlPlusFileMtime = filemtime($htmlPlusFile);
            if (stream_resolve_include_path($mdFile)) {
              $mdFileMtime = filemtime($mdFile);
              if ($htmlPlusFileMtime < $mdFileMtime) {
                throw new Exception("Unexpected exception");
              }
              if ($htmlPlusFileMtime === $mdFileMtime) {
                return;
              }
              rename_incr($mdFile, "$mdFile.");
            }
            $markdown = $this->htmlplus2md(file_get_contents($htmlPlusFile));
            fput_contents($mdFile, $markdown);
            touch($mdFile, $htmlPlusFileMtime);
            break;
        }
      } catch (Exception $exc) {
        global $removeWatchUserFile;
        $removeWatchUserFile = false;
        Logger::warning($exc->getMessage());
      }
    }
  }

  /**
   * @param DOMElementPlus $element
   * @param null|string $pattern
   * @return null|\DOMNode
   */
  private function getLastTextNode (DOMElementPlus $element, $pattern = null) {
    $lastNode = null;
    foreach ($element->childNodes as $node) {
      if ($node->nodeType !== XML_TEXT_NODE) {
        continue;
      }
      if (!is_null($pattern) && !preg_match($pattern, $node->nodeValue)) {
        continue;
      }
      $lastNode = $node;
    }
    return $lastNode;
  }

  /**
   * @param DOMElementPlus $element
   */
  private function convertElementTextAttr (DOMElementPlus $element) {
    $attributePattern = "/ *\{(?:[^=]+=[^=]+)+\}$/";
    $lastNode = $this->getLastTextNode($element, $attributePattern);
    if (is_null($lastNode)) {
      return;
    }
    $value = $lastNode->nodeValue;
    preg_match($attributePattern, $value, $matches);
    $lastNode->nodeValue = preg_replace($attributePattern, "", $value);
    $attributeString = trim($matches[0], "{} ");
    $attributePartsPattern = '/([^= ]+)="([^"]+)"|([^= ]+)=([^ ]+)/u';
    preg_match_all($attributePartsPattern, $attributeString, $attributeParts);
    if (!isset($attributeParts[1]) || !isset($attributeParts[2]) || !isset($attributeParts[3])
      || !isset($attributeParts[3])) {
      return;
    }
    for ($i = 0; $i < count($attributeParts[0]); $i++) {
      $attName = strlen($attributeParts[1][$i]) ? $attributeParts[1][$i] : $attributeParts[3][$i];
      $attValue = strlen($attributeParts[2][$i]) ? $attributeParts[2][$i] : $attributeParts[4][$i];
      $element->setAttribute($attName, $attValue);
    }
  }

  /**
   * @param DOMElementPlus $element
   */
  private function mergeAttrParagraph (DOMElementPlus $element) {
    $nextElement = $element->nextElement;
    if (is_null($nextElement)) {
      return;
    }
    foreach ($element->attributes as $attNode) {
      $nextElement->setAttribute($attNode->nodeName, $attNode->nodeValue);
    }
    $element->parentNode->removeChild($element);
  }

  /**
   * @param DOMDocumentPlus $content
   */
  private function convertTextAtts (DOMDocumentPlus $content) {
    // body attributes
    $firstElement = $content->documentElement->firstElement;
    if ($firstElement->nodeName === "p") {
      $this->convertElementTextAttr($firstElement);
      foreach ($firstElement->attributes as $attNode) {
        $content->documentElement->setAttribute($attNode->nodeName, $attNode->nodeValue);
      }
      $firstElement->parentNode->removeChild($firstElement);
    }
    // other attributes
    foreach ($content->getElementsByTagName('*') as $element) {
      $this->convertElementTextAttr($element);
    }
    // merge "attribute paragraphs"
    $paragraphs = [];
    foreach ($content->getElementsByTagName('p') as $element) {
      $paragraphs[] = $element;
    }
    foreach ($paragraphs as $element) {
      if ($element->nodeValue !== "") {
        continue;
      }
      if (!count($element->attributes)) {
        continue;
      }
      $this->mergeAttrParagraph($element);
    }
  }

  /**
   * @param $mdText
   * @return string
   * @throws Exception
   */
  private function md2htmlplus ($mdText) {
    $html = MarkdownExtra::defaultTransform($mdText);
    $content = new DOMDocumentPlus();
    $content->loadXML("<body>$html</body>");
    $this->convertTextAtts($content);

    $proc = new XSLTProcessor();
    $fileName = $this->pluginDir."/html2htmlplus.xsl";
    $xsl = XMLBuilder::load($fileName);

    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (!$proc->importStylesheet($xsl)) {
      throw new Exception(sprintf(_("XSLT '%s' compilation error"), $fileName));
    }
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (($doc = $proc->transformToDoc($content)) === false) {
      throw new Exception(sprintf(_("XSLT '%s' transformation fail"), $fileName));
    }
    $xml = $doc->saveXML();
    // https://github.com/gajus/dindent
    $indeter = new Indenter(["indentation_character" => "  "]);
    $xml = $indeter->indent($xml);
    // unindent each line for 2 chars
    $xml = implode(
      "\n",
      array_map(
        function($line) {
          return (strpos($line, " ") === 0) ? substr($line, 2) : $line;
        },
        explode("\n", $xml)
      )
    );
    return $xml;
  }

  /**
   * @param DOMElementPlus $element
   * @param array $except
   * @return string
   */
  private function getAttrString (DOMElementPlus $element, $except = []) {
    $attributes = [];
    foreach ($element->attributes as $attNode) {
      if (in_array($attNode->nodeName, $except)) {
        continue;
      }
      $attributes[] = $attNode->nodeName.'="'.$attNode->nodeValue.'"';
    }
    return count($attributes) ? "{".implode(" ", $attributes)."}" : "";
  }

  /**
   * @param DOMElementPlus $rootElement
   * @param array $ignore
   */
  private function convertHTMLAttrs (DOMElementPlus $rootElement, $ignore = ["form", "q"]) {
    /** @var DOMElementPlus $element */
    foreach ($rootElement->childElementsArray as $element) {
      if (in_array($element->nodeName, $ignore)) {
        continue;
      }
      if ($element->hasChildNodes()) {
        $this->convertHTMLAttrs($element, $ignore);
      }
      $attributes = $element->attributes;
      if (!$attributes->length) {
        continue;
      }
      $beforeElements = ["body", "ul", "ol", "dl"];
      if (in_array($element->nodeName, $beforeElements)) {
        $attrParagraph = $rootElement->ownerDocument->createElement("p");
        $attrParagraph->nodeValue = $this->getAttrString($element);
        $element->parentNode->insertBefore($attrParagraph, $element);
        $element->removeAllAttributes();
      } else {
        $lastNode = $this->getLastTextNode($element);
        if (is_null($lastNode)) {
          // TODO throw?
          return;
        }
        $attrString = $this->getAttrString($element, ["href"]);
        $element->removeAllAttributes(["href"]);
        $lastNode->nodeValue = $lastNode->nodeValue." $attrString";
      }
    }
  }

  /**
   * @param $htmlplus
   * @return string
   * @throws Exception
   */
  private function htmlplus2md ($htmlplus) {
    $content = new DOMDocumentPlus();
    $content->loadXML($htmlplus);

    $proc = new XSLTProcessor();
    $fileName = $this->pluginDir."/htmlplus2html.xsl";
    $xsl = XMLBuilder::load($fileName);

    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (!$proc->importStylesheet($xsl)) {
      throw new Exception(sprintf(_("XSLT '%s' compilation error"), $fileName));
    }
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (($doc = $proc->transformToDoc($content)) === false) {
      throw new Exception(sprintf(_("XSLT '%s' transformation fail"), $fileName));
    }
    $html = $doc->saveXML();
    $content->loadXML($html); // transform to DOMDocumentPlus
    $this->convertHtmlAttrs($content->documentElement);
    $html = $content->saveXML();
    $html2mdConvertor = new ConverterExtra();
    $mdString = $html2mdConvertor->parseString("$html");
    return html_entity_decode(substr($mdString, strpos($mdString, "\n") + 1));
  }
}
