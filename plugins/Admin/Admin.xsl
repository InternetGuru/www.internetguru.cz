<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="/">
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="samp[parent::*[contains(@class,'links')]]">
    <xsl:element name="{name()}">
    <xsl:element name="a">
      <xsl:attribute name="href">
        <xsl:text>?Admin=</xsl:text>
        <xsl:choose>
          <xsl:when test="parent::*[contains(@class,'plugin-xml')]">plugins/<xsl:value-of select="text()"/>/<xsl:value-of select="text()"/>.xml</xsl:when>
          <xsl:when test="parent::*[contains(@class,'plugin-html')]">plugins/<xsl:value-of select="text()"/>/<xsl:value-of select="text()"/>.html</xsl:when>
          <xsl:otherwise><xsl:value-of select="text()"/></xsl:otherwise>
        </xsl:choose>
      </xsl:attribute>
      <xsl:value-of select="text()"/>
      <xsl:choose>
        <xsl:when test="parent::*[contains(@class,'plugin-xml')]">.xml</xsl:when>
        <xsl:when test="parent::*[contains(@class,'plugin-html')]">.html</xsl:when>
      </xsl:choose>
    </xsl:element>
    </xsl:element>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>