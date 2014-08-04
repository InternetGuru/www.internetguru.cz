<?php

include('cls/globals.php');
include('cls/DOMDocumentPlus.php');
include('cls/HTMLPlus.php');

class HTMLPlusTest extends \Codeception\TestCase\Test
{
   /**
    * @var \UnitTester
    */
    protected $tester;
    private $doc;

    protected function _before()
    {
      $this->doc = new HTMLPlus();
    }

    protected function _after()
    {
    }

    // tests

    public function testEmpty()
    {
      $e = null;
      try {
        $this->doc->validate();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted empty doc");
    }

    public function testHNoId()
    {
      $e = null;
      $this->doc->loadXML('<body lang="en"><h>x</h><description/></body>');
      try {
        $this->doc->validate();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted h with no ID");
    }

    public function testHNoDesc()
    {
      $e = null;
      $this->doc->loadXML('<body lang="en"><h id="h.abc">x</h></body>');
      try {
        $this->doc->validate();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted h with no description");
    }

    public function testBodyNoLang()
    {
      $e = null;
      $this->doc->loadXML('<body><h id="h.abc">x</h><description/></body>');
      try {
        $this->doc->validate();
      } catch (Exception $e) {
      }
      $this->assertNotNull($e,"HTMLPlus accepted body with no lang");
    }

    public function testValidXML()
    {
      $e = null;
      $this->doc->loadXML('<body lang="en"><h id="h.abc">x</h><description/></body>');
      try {
        $this->doc->validate();
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
    }

    public function testH1InForm()
    {
      $e = null;
      $this->doc->loadXML('<body lang="en"><h id="h.abc">x</h><description/><form action="." method="post"><div><h1/></div></form></body>');
      try {
        $this->doc->validate();
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertNotNull($e,"HTMLPlus accepted h1 in form (known issue #1)");
    }

    public function testHEmpty()
    {
      $e = null;
      $this->doc->loadXML('<body lang="en"><h id="h.abc"/><description/></body>');
      try {
        $this->doc->validate();
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertNotNull($e,"HTMLPlus accepted empty h");
    }

}