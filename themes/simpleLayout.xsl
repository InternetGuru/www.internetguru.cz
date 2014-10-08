<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:param name="kw" select="''"/>
  <xsl:param name="breadcrumb" select="''"/>
  <xsl:param name="menu" select="'xxxx'"/>

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>
      <div id="content">
        <xsl:apply-templates/>
      </div>
      <div id="footer">
        <xsl:value-of disable-output-escaping="yes" select="$menu"/>
        <ul>
          <li>©2014 <a href="http://www.internetguru.cz">InternetGuru</a></li>
        </ul>
      </div>
    </body>
  </xsl:template>

  <xsl:template match="/body/h1">
    <div>
      <div>
        <xsl:copy-of select="."/>
        <xsl:value-of disable-output-escaping="yes" select="$breadcrumb"/>
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