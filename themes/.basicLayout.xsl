<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:param name="contentlink-bc" select="''"/>

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>
      <xsl:attribute name="class">
        <xsl:value-of select="concat(@class,' fragmentable scrolltopable')"/>
      </xsl:attribute>
      <div id="header">
        <div>
          <xsl:value-of disable-output-escaping="yes" select="$contentlink-bc"/>
        </div>
        <xsl:apply-templates select="*[name() = 'h1']" />
        <xsl:apply-templates select="./p[name() = 'p' and contains(@class, 'description')]" />
      </div>
      <div id="content">
        <xsl:apply-templates select="*[not(name() = 'h1')][not(name() = 'p') and not(contains(@class, 'description'))]" />
      </div>
    </body>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>
