<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="body">
  <body>
    <xsl:apply-templates select="h">
      <xsl:with-param name="i" select="1"/>
    </xsl:apply-templates>
    <xsl:apply-templates select="description"/>
    <xsl:apply-templates select="section">
      <xsl:with-param name="i" select="1"/>
    </xsl:apply-templates>
  </body>
</xsl:template>

<xsl:template match="h">
  <xsl:param name="i"/>
  <xsl:element name = "h{$i}">
    <xsl:value-of select="."/>
  </xsl:element>
</xsl:template>

<xsl:template match="description">
     <p class="description">
          <xsl:value-of select="."/>
     </p>
</xsl:template>

<xsl:template match="section">
  <xsl:param name="i"/>
  <xsl:element name = "section">
    <xsl:apply-templates select="*">
      <xsl:with-param name="i" select="$i+1"/>
    </xsl:apply-templates>
  </xsl:element>
</xsl:template>

</xsl:stylesheet>