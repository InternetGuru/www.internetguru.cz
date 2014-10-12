<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:param name="cms-url" select="''"/>
  <xsl:param name="cms-link" select="''"/>

  <xsl:template match="//a">
    <xsl:choose>
      <xsl:when test="starts-with(@href,$cms-url)">
        <xsl:text disable-output-escaping="yes">&lt;!-- local link in absolute syntax detected --&gt;</xsl:text>
        <xsl:apply-templates select="node()"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:copy>
          <xsl:apply-templates select="node()|@*"/>
        </xsl:copy>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>