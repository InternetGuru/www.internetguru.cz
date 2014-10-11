<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:param name="cms-ig" select="''"/>
  <xsl:param name="cms-title" select="''"/>
  <xsl:param name="cms-breadcrumb" select="''"/>
  <xsl:param name="cms-menu" select="''"/>
  <xsl:param name="cms-lang" select="''"/>
  <xsl:param name="cms-author" select="''"/>
  <xsl:param name="cms-desc" select="''"/>
  <xsl:param name="cms-kw" select="''"/>
  <xsl:param name="cms-ctime" select="''"/>
  <xsl:param name="cms-mtime" select="''"/>
  <xsl:param name="cms-url" select="''"/>
  <xsl:param name="cms-link" select="''"/>
  <xsl:param name="creation" select="''"/>

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>
      <div id="content">
        <xsl:apply-templates/>
      </div>
      <div id="footer">
        <xsl:value-of disable-output-escaping="yes" select="$cms-menu"/>
        <ul>
            <li><xsl:value-of disable-output-escaping="yes" select="$cms-ig"/></li>
            <li><xsl:value-of disable-output-escaping="yes" select="$cms-ez"/></li>
        </ul>
      </div>
    </body>
  </xsl:template>

  <xsl:template match="/body/h1">
    <div>
      <div>
        <xsl:copy-of select="."/>
        <xsl:value-of disable-output-escaping="yes" select="$cms-breadcrumb"/>
      </div>
    </div>
  </xsl:template>

  <xsl:template match="/body/p[contains(@class,'description')]">
    <div>
      <xsl:copy-of select="." />
    </div>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>