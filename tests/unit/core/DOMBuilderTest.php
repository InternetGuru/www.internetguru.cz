<?php

require_once('core/globals.php');
require_once('core/Logger.php');
require_once('core/DOMElementPlus.php');
require_once('core/Cms.php');
require_once('core/DOMBuilder.php');
require_once('core/DOMDocumentPlus.php');
require_once('core/HTMLPlus.php');

class DOMBuilderTest extends \PHPUnit_Framework_TestCase {
  private $xml = [];
  private $html = [];

  public function testBuildDOM () {
    $doc = DOMBuilder::buildDOMPlus($this->xml[0]);
    $s1 = $doc->C14N(true, false);
    $doc = new DOMDocumentPlus();
    $doc->loadXML(
      '<test><a>1</a><d id="d" readonly="readonly">2</d><c readonly="readonly">2</c><b>3</b><c>3</c></test>'
    );
    $s2 = $doc->C14N(true, false);
    #echo "\n$s1\n$s2"; die();
    $this->assertTrue($s1 == $s2);
  }

  public function testBuildHTML () {
    $doc = DOMBuilder::buildHTMLPlus($this->html[0]);
    $s1 = $doc->C14N(true, false);
    $doc = new HTMLPlus();
    $doc->loadXML(
      '<body xml:lang="en" ns="localhost/test"><h id="h.abc" author="test" link="test" ctime="2015" short="3">Three</h><desc kw="kw">desc</desc></body>'
    );
    $s2 = $doc->C14N(true, false);
    #echo "\n$s1\n$s2"; die();
    $this->assertTrue($s1 == $s2);
  }

  // tests

  protected function setUp () {

    $xml = "testXml.xml";
    mkdir_plus(USER_FOLDER);
    mkdir_plus(ADMIN_FOLDER);
    $this->xml = [$xml, ADMIN_FOLDER."/$xml", USER_FOLDER."/$xml"];
    foreach ($this->xml as $xml) if (file_exists($xml)) {
      $this->xml = [];
      throw new Exception("Test file '$xml' exists");
    }

    $doc = new DOMDocumentPlus();
    $doc->loadXML('<test><a>1</a><b>1</b><b>1</b><c readonly="readonly">1</c><d id="d">1</d></test>');
    $doc->save($this->xml[0]);
    $doc->loadXML('<test><b/><b>2</b><c/><c readonly="readonly">2</c><d id="d" readonly="readonly">2</d></test>');
    $doc->save($this->xml[1]);
    $doc->loadXML('<test><b/><b>3</b><c/><c>3</c><d id="d">3</d></test>');
    $doc->save($this->xml[2]);

    $html = "testHtmlPlus.xml";
    $this->html = [$html, ADMIN_FOLDER."/$html", USER_FOLDER."/$html"];
    foreach ($this->html as $html) if (file_exists($html)) {
      $this->html = [];
      throw new Exception("Test file '$html' exists");
    }

    $doc = new HTMLPlus();
    $doc->loadXML(
      '<body xml:lang="en" ns="localhost/test"><h id="h.abc" author="test" link="test" ctime="2015">1</h><desc kw="kw">desc</desc></body>'
    );
    $doc->save($this->html[0]);
    $doc->loadXML(
      '<body xml:lang="en" ns="localhost/test"><h id="h.abc" author="test" link="test" ctime="2015"></h><desc kw="kw">desc</desc></body>'
    );
    $doc->save($this->html[1]);
    $doc->loadXML(
      '<body xml:lang="en" ns="localhost/test"><h id="h.abc" author="test" link="test" ctime="2015" short="3">Three</h><desc kw="kw">desc</desc></body>'
    );
    $doc->save($this->html[2]);

  }

  protected function tearDown () {
    foreach ($this->xml as $xml) unlink($xml);
    foreach ($this->html as $html) unlink($html);
    rmdir(ADMIN_FOLDER);
    rmdir(USER_FOLDER);
  }

}
