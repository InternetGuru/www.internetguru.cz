<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="/">
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="samp[parent::*[@class='links']]">
    <xsl:element name="{name()}">
    <xsl:element name="a">
      <xsl:attribute name="href">
        <xsl:text>?ContentAdmin=</xsl:text>
        <xsl:value-of select="text()"/>
      </xsl:attribute>
      <xsl:apply-templates/>
    </xsl:element>
    </xsl:element>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>