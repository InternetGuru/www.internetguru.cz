<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:param name="title" select="''"/>
  <xsl:param name="breadcrumb" select="''"/>
  <xsl:param name="menu" select="''"/>
  <xsl:param name="lang" select="''"/>
  <xsl:param name="author" select="''"/>
  <xsl:param name="desc" select="''"/>
  <xsl:param name="kw" select="''"/>
  <xsl:param name="ctime" select="''"/>
  <xsl:param name="mtime" select="''"/>
  <xsl:param name="link" select="''"/>

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>
      <div id="header">
        <xsl:value-of disable-output-escaping="yes" select="$cms-breadcrumb"/>
      </div>
      <div id="content">
        <xsl:apply-templates/>
      </div>
      <div id="footer">
        <xsl:value-of disable-output-escaping="yes" select="$cms-menu"/>
        <ul><li>©2014 internetguru.cz</li></ul>
      </div>
    </body>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>