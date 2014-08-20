<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>

      <div id="content">

        <div>
          <div>
            <xsl:copy-of select="/body/h1" />
            <xsl:if test="count(ol[contains(@class,'cms-breadcrumb')]/li)>1">
              <xsl:copy-of select="ol[contains(@class,'cms-breadcrumb')]"/>
            </xsl:if>
          </div>
        </div>

        <xsl:if test="/body/p[contains(@class,'description')]">
          <div><xsl:copy-of select="/body/p[contains(@class,'description')]" /></div>
        </xsl:if>

        <xsl:apply-templates select="/body/*[not(
          self::h1 or
          self::p[contains(@class,'description')] or
          self::ol[contains(@class,'cms-breadcrumb')] or
          self::ul[contains(@class,'cms-menu')] )]" />

      </div>

      <div id="footer">
        <xsl:copy-of select="ul[contains(@class,'cms-menu')]"/>
        <ul>
          <li>©2014 <a href="http://www.internetguru.cz">InternetGuru</a></li>
        </ul>
      </div>

    </body>
  </xsl:template>

  <!-- delete remaining empty descriptions -->
  <xsl:template match="//p[contains(@class,'description') and not(string-length(text()))]"/>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>