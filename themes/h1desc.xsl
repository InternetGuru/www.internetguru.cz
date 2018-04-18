<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

  <xsl:template match="//h1">
    <xsl:text disable-output-escaping="yes">&lt;div class="hdesc"&gt;</xsl:text>
    <div>
      <xsl:copy-of select="."/>
      <ul><li><a class="button" href="#contact">Already interested?</a></li></ul>
    </div>
  </xsl:template>


  <xsl:template match="//p[contains(@class, 'description')][preceding-sibling::*[1][name() = 'h1']]">
    <xsl:copy-of select="."/>
    <xsl:text disable-output-escaping="yes">&lt;/div&gt;</xsl:text>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>