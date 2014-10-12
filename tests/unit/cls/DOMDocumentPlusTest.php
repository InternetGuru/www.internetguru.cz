<?php

include_once('cls/globals.php');
include_once('cls/DOMElementPlus.php');
include_once('cls/DOMDocumentPlus.php');

class DOMDocumentPlusTest extends \Codeception\TestCase\Test
{
   /**
    * @var \UnitTester
    */
    protected $tester;
    private $doc;

    protected function _before()
    {
      $this->doc = new DOMDocumentPlus();
    }

    protected function _after()
    {
    }

    // tests

    public function testGetElementById()
    {
      $e = null;
      $this->doc->loadXML('<a><b/><c id="c"/></a>');
      try {
        $n = $this->doc->getElementById("c");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $this->assertTrue($n->nodeName == "c", 'Wrong element found');
    }

    public function testRenameElement()
    {
      $e = null;
      $this->doc->loadXML('<a><b/><c id="c"><d/><d class="f"/></c></a>');
      try {
        $n = $this->doc->renameElement($this->doc->getElementById("c"),"e");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a><b/><e id="c"><d/><d class="f"/></e></a>');
      $s2 = $doc->C14N(true,false);
      $this->assertTrue($s1 == $s2, 'Failed to rename element');
    }

    public function testInsertVarString()
    {
      $e = null;
      $this->doc->loadXML('<a><b/><c class="" var="somePlugin:someVar somePlugin:someVar@class"/></a>');
      try {
        $n = $this->doc->insertVar("someVar","someValue","somePlugin");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a><b/><c class="someValue">someValue</c></a>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

    public function testInsertVarStringRoot()
    {
      $e = null;
      $this->doc->loadXML('<a var="somePlugin:someVar"><b/>%s</a>');
      try {
        $n = $this->doc->insertVar("someVar","someValue","somePlugin");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a><b/>someValue</a>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

    public function testInsertVarArray()
    {
      $e = null;
      $this->doc->loadXML('<body><ul><li var="somePlugin:someVar"/></ul></body>');
      try {
        $n = $this->doc->insertVar("someVar",array("var1","var2"),"somePlugin");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<body><ul><li>var1</li><li>var2</li></ul></body>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

    public function testInsertVarArrayEmpty()
    {
      $e = null;
      $this->doc->loadXML('<body><ul><li var="somePlugin:someVar"/></ul></body>');
      try {
        $n = $this->doc->insertVar("someVar",array(),"somePlugin");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<body/>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

    public function testInsertVarDOM()
    {
      $e = null;
      $this->doc->loadXML('<a><b/><c var="somePlugin:someVar"><x/></c></a>');
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<var>someText<someTag/></var>');
      try {
        $n = $this->doc->insertVar("someVar",$doc->documentElement,"somePlugin");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a><b/><c>someText<someTag/></c></a>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

    public function testInsertVarStringNoparse()
    {
      $e = null;
      $this->doc->loadXML('<a><b/><c id="c" class="noparse {somePlugin:someVar}">{somePlugin:someVar}</c></a>');
      try {
        $n = $this->doc->insertVar("someVar","someValue","somePlugin");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a><b/><c id="c" class="noparse {somePlugin:someVar}">{somePlugin:someVar}</c></a>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
   }

   public function testInsertVarDOMNoparse()
    {
      $e = null;
      $this->doc->loadXML('<a class="noparse"><b/><c id="c">{somePlugin:someVar}</c></a>');
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<d><e/><f/></d>');
      try {
        $n = $this->doc->insertVar("someVar",$doc->documentElement,"somePlugin");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a class="noparse"><b/><c id="c">{somePlugin:someVar}</c></a>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }


}