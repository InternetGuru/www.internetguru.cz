<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
  <xsl:template match="body">
    <body>
      <xsl:apply-templates/>
    </body>
  </xsl:template>

  <xsl:template match="//h">
    <xsl:variable name="level" select="count(ancestor::section)"/>
    <xsl:element name="h{$level+1}">
      <xsl:copy-of select="@*[name()!='short' and name()!='link' and name()!='keywords']"/>
      <xsl:apply-templates/>
    </xsl:element>
  </xsl:template>

  <xsl:template match="//description">
    <p class="description">
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </p>
  </xsl:template>

  <xsl:template match="//section">
    <div class="section">
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </div>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>