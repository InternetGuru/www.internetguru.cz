<?php

require_once('core/global_func.php');
require_once('core/global_const.php');
require_once('core/Logger.php');
require_once('core/DOMElementPlus.php');
require_once('core/DOMBuilder.php');
require_once('core/DOMDocumentPlus.php');
require_once('core/HTMLPlus.php');

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
        $n = $this->doc->getElementById("c")->rename("e");
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
      $this->doc->loadXML('<a><b/><c var="somevalue somevalue@class"/></a>');
      try {
        $n = $this->doc->insertVar("somevalue","myValue");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a><b/><c class="myValue">myValue</c></a>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

    public function testInsertVarStringRoot()
    {
      $e = null;
      $this->doc->loadXML('<a var="somevar">x<b/></a>');
      try {
        $n = $this->doc->insertVar("somevar","myValue");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a>myValue</a>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

    public function testInsertVarArray()
    {
      $e = null;
      $this->doc->loadXML('<body><ul><li var="somevar"/></ul></body>');
      try {
        $n = $this->doc->insertVar("somevar",array("var1","var2"));
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
      $this->doc->loadXML('<body><ul><li var="somevar"/></ul></body>');
      try {
        $n = $this->doc->insertVar("somevar",array());
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
      $this->doc->loadXML('<a><b/><c var="somevar"><x/></c></a>');
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<var>someText<someTag/></var>');
      try {
        $n = $this->doc->insertVar("somevar",$doc->documentElement);
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a><b/>someText<someTag/></a>');
      #$doc->loadXML('<a><b/><c>someText<someTag/></c></a>'); // previously correct
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }

    public function testInsertVarStringNoparse()
    {
      $e = null;
      $this->doc->loadXML('<a><c class="noparse" var="somevar"/></a>');
      try {
        $n = $this->doc->insertVar("somevar","myValue");
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a><c class="noparse" var="somevar"/></a>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
   }

   public function testInsertVarDOMNoparse()
    {
      $e = null;
      $this->doc->loadXML('<a class="noparse"><b/><c var="somevar"/></a>');
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<d><e/><f/></d>');
      try {
        $n = $this->doc->insertVar("somevar",$doc->documentElement);
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
      $s1 = $this->doc->C14N(true,false);
      $doc = new DOMDocumentPlus();
      $doc->loadXML('<a class="noparse"><b/><c var="somevar"/></a>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2"; die();
      $this->assertTrue($s1 == $s2);
    }


}