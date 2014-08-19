<?php

include_once('cls/globals.php');
include_once('cls/DOMDocumentPlus.php');
include_once('cls/HTMLPlus.php');

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

    public function testEmptyXml()
    {
      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted empty XML");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted empty XML");
    }

    public function testDuplicitId()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="a">x</h><description/>'
        .'<section><h id="a">x</h><description/></section></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {
        #echo $e->getMessage();die();
      }
      $this->assertNotNull($e,"HTMLPlus accepted duplicit ID");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted duplicit ID");
    }

    public function testDuplicitLink()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="a" link="x/x">x</h><description/>'
        .'<section><h id="b" link="x/x">x</h><description/></section></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {
        #echo $e->getMessage();die();
      }
      $this->assertNotNull($e,"HTMLPlus accepted duplicit link");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted duplicit link (known issue #3)");
    }

    public function testHNoId()
    {
      $this->doc->loadXML('<body xml:lang="en"><h>x</h><description/></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted h with no ID");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted h with no ID");
    }

    public function testHNoDescription()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc">x</h></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted h with no description");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted h with no description");
    }

    public function testHNoIdAdd()
    {
      $this->doc->loadXML('<body xml:lang="en"><h>x</h><description/></body>');

      $e = null;
      try {
        $this->doc->validatePlus(true);
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
    }

    public function testHEmptyIdAdd()
    {
      $e = null;
      $this->doc->loadXML('<body xml:lang="en"><h id=" ">x</h><description/></body>');
      try {
        $this->doc->validatePlus(true);
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
    }

    public function testHNoDescAdd()
    {
      $e = null;
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc">x</h></body>');
      try {
        $this->doc->validatePlus(true);
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
    }

    public function testBodyNoLang()
    {
      $this->doc->loadXML('<body><h id="h.abc">x</h><description/></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted body with no lang");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted body with no lang");
    }

    public function testValidXML()
    {
      $e = null;
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc" link="x" short="x">xx xx</h><description/></body>');
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
    }

    public function testH1InForm()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc">x</h><description/><form action="." method="post"><div><h1/></div></form></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted h1 in form (known issue #1)");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted h1 in form (known issue #1)");
    }

    public function testListInPar()
    {
      $e = null;
      $this->doc->loadXML('<body xml:lang="en"><h id="hx">x</h><description/><p>a<dl><dt/></dl>b<ul><li/></ul>c</p></body>');
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {
        $e = $e->getMessage();
      }
      $this->assertTrue(is_null($e),$e);
    }

    public function testHEmpty()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc"/><description/></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted empty h");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted empty h");
    }

    public function testHEmptyTrim()
    {
      $this->doc->loadXML("<body xml:lang='en'><h id='h.abc'>  \t\r\n</h><description/></body>");

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted white-spaced h (known issue #2)");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted white-spaced h (known issue #2)");
    }

    public function testHShortEmpty()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc" short="">x</h><description/></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted h with empty attr short");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted h with empty attr short");
    }

    public function testHLinkEmpty()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc" link="">x</h><description/></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted h with empty attr link");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted h with empty attr link");
    }

    public function testHLinkInvalid()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc" link="á bé">x</h><description/></body>');

      $e = null;
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus accepted invalid link value");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted ivalid link value");
    }

    public function testHLinkInvalidRepair()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc" link="á bé">x</h><description/></body>');
      $e = null;
      try {
        $this->doc->validatePlus(true);
      } catch (Exception $e) {
        echo $e->getMessage();die();
      }
      $s1 = $this->doc->C14N(true,false);
      $doc = new HTMLPlus();
      $doc->loadXML('<body xml:lang="en"><h id="h.abc" link="a_be">x</h><description/></body>');
      $s2 = $doc->C14N(true,false);
      #echo "\n$s1\n$s2";die();
      $this->assertTrue($s1 == $s2, 'Link is not repaired as expected');
    }

    public function testHLinkInvalidRepairUnable()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="a" link="á bé">x</h><description/>'
        .'<section><h id="a" link="a_be">x</h><description/></section></body>');

      $e = null;
      try {
        $this->doc->validatePlus(true);
      } catch (Exception $e) {}
      $this->assertNotNull($e,"HTMLPlus invalid link repair (?)");

      $e = null;
      try {
        $this->doc->relaxNGValidatePlus();
      } catch (Exception $e) {}
      $this->assertNotNull($e,"RelaxNG schema accepted invalid link value");
    }

    public function testListNoItems()
    {
      $e = null;
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc">x</h><description/><ol/></body>');
      try {
        $this->doc->validatePlus();
      } catch (Exception $e) {
        #echo $e->getMessage();die();
      }
      $this->assertNotNull($e,"RelaxNG schema accepted list with no items");
    }

   public function testClone()
    {
      $this->doc->loadXML('<body xml:lang="en"><h id="h.abc">x</h><description/></body>');
      $s1 = $this->doc->C14N(true,false);
      $doc = clone $this->doc;
      $s2 = $doc->C14N(true,false);
      $this->assertTrue($s1 == $s2, 'Clones are not equal');
    }

}