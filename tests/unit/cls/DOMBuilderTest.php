<?php

include_once('cls/globals.php');
include_once('cls/DOMBuilder.php');
include_once('cls/DOMDocumentPlus.php');
include_once('cls/HTMLPlus.php');

class DOMBuilderTest extends \Codeception\TestCase\Test
{
   /**
    * @var \UnitTester
    */
    protected $tester;
    private $builder;
    private $xml = array();
    private $html = array();

    protected function _before()
    {
      $this->builder = new DOMBuilder();

      $xml = "testXml.xml";
      $this->xml = array($xml,ADMIN_FOLDER."/$xml",USER_FOLDER."/$xml");
      foreach($this->xml as $xml) if(file_exists($xml)) {
        $this->xml = array();
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
      $this->html = array($html,ADMIN_FOLDER."/$html",USER_FOLDER."/$html");
      foreach($this->html as $html) if(file_exists($html)) {
        $this->html = array();
        throw new Exception("Test file '$html' exists");
      }

      $doc = new HTMLPlus();
      $doc->loadXML('<body xml:lang="en"><h id="h.abc">1</h><description/></body>');
      $doc->save($this->html[0]);
      $doc->loadXML('<body xml:lang="en"><h id="h.abc"></h><description/></body>');
      $doc->save($this->html[1]);
      $doc->loadXML('<body xml:lang="en"><h id="h.abc" short="3">Three</h><description/></body>');
      $doc->save($this->html[2]);

    }

    protected function _after()
    {
      foreach($this->xml as $xml) unlink($xml);
      foreach($this->html as $html) unlink($html);
    }

    // tests

    public function testBuildDOM()
    {
      $doc = $this->builder->buildDOM("",false,$this->xml[0]);
      $s1 = $doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<test><a>1</a><d id="d" readonly="readonly">2</d><c readonly="readonly">2</c><b>3</b><c>3</c></test>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

    public function testBuildHTML()
    {
      $doc = $this->builder->buildHTML("",true,$this->html[0]);
      $s1 = $doc->C14N(true,false);
      $doc = new HTMLPlus();
      $doc->loadXML('<body xml:lang="en"><h id="h.abc" short="3">Three</h><description/></body>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

}