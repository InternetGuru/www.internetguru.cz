<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

  <xsl:param name="cms-lang" select="''"/>
  <xsl:param name="inputvar-ielt7" select="''"/>

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>
      <xsl:text disable-output-escaping="yes">&lt;!--[if lte IE 7]&gt;</xsl:text>
      <xsl:value-of disable-output-escaping="yes" select="$inputvar-ielt7"/>
      <xsl:text disable-output-escaping="yes">&lt;![endif]]]--&gt;</xsl:text>
      <xsl:apply-templates/>
    </body>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>
