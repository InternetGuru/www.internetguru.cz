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

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testValidity()
    {

      // create and test empty HTMLPlus
      $e = null;
      $doc = new HTMLPlus();
      try {
        $doc->validate();
      } catch (Exception $e) {
      }
      $this->assertNotNull($e,"HTMLPlus accepted empty doc");

      // fill with invalid XML (missing h.ID)
      $e = null;
      $doc->loadXML('<body lang="en"><h>x</h><description/></body>');
      try {
        $doc->validate();
      } catch (Exception $e) {
      }
      $this->assertNotNull($e,"HTMLPlus accepted h with no ID");

      // fill with invalid XML (missing body.lang)
      $e = null;
      $doc->loadXML('<body><h id="h.abc">x</h><description/></body>');
      try {
        $doc->validate();
      } catch (Exception $e) {
      }
      $this->assertNotNull($e,"HTMLPlus accepted body with no lang");

      // fill with valid XML
      $e = null;
      $doc->loadXML('<body lang="en"><h id="h.abc">x</h><description/></body>');
      try {
        $doc->validate();
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);

    }

}