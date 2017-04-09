<?php

class DOMElementPlusTest extends \PHPUnit_Framework_TestCase {

  private $doc;

  public function testRenameElement () {
    $e = null;
    $this->doc->loadXML('<a><b/><c id="c"><d/><d class="f"/></c></a>');
    try {
      $n = $this->doc->getElementById("c")->rename("e");
    } catch (Exception $e) {
      $e = $e->getMessage();
    }
    $this->assertTrue(is_null($e), $e);
    $s1 = $this->doc->C14N(true, false);
    $doc = new DOMDocumentPlus();
    $doc->loadXML('<a><b/><e id="c"><d/><d class="f"/></e></a>');
    $s2 = $doc->C14N(true, false);
    $this->assertTrue($s1 == $s2, 'Failed to rename element');
  }

  protected function setUp () {
    $this->doc = new DOMDocumentPlus();
  }

  protected function tearDown () {
  }
}
