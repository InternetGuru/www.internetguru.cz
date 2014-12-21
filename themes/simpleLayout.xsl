<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
  xmlns:php="http://php.net/xsl">

  <xsl:param name="cms-ig" select="''"/>
  <xsl:param name="cms-ez" select="''"/>
  <xsl:param name="cms-title" select="''"/>
  <xsl:param name="contentlink-bc" select="''"/>
  <xsl:param name="globalmenu" select="''"/>
  <xsl:param name="cms-lang" select="''"/>
  <xsl:param name="cms-author" select="''"/>
  <xsl:param name="cms-authorid" select="''"/>
  <xsl:param name="cms-version" select="''"/>
  <xsl:param name="cms-desc" select="''"/>
  <xsl:param name="cms-kw" select="''"/>
  <xsl:param name="cms-ctime" select="''"/>
  <xsl:param name="cms-mtime" select="''"/>
  <xsl:param name="xhtml11-url" select="''"/>
  <xsl:param name="xhtml11-link" select="''"/>
  <xsl:param name="inputvar-myctime" select="''"/>
  <xsl:param name="inputvar-mymtime" select="''"/>
  <xsl:param name="inputvar-creation" select="''"/>

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>
      <div id="content">
        <xsl:apply-templates/>
      </div>
      <div id="footer">
        <xsl:value-of disable-output-escaping="yes" select="$globalmenu"/>
        <ul>
          <li>Webdesign <xsl:value-of disable-output-escaping="yes" select="$cms-ig"/></li>
          <li>Běží na službě <xsl:value-of disable-output-escaping="yes" select="$cms-ez"/></li>
          <xsl:if test="not($cms-ctime = '')">
            <li>Vytvořeno <xsl:value-of select="$inputvar-myctime"/></li>
          </xsl:if>
          <xsl:if test="not($cms-mtime = '')">
            <li>Poslední úpravy <xsl:value-of select="$inputvar-mymtime"/></li>
          </xsl:if>
          <li><xsl:value-of disable-output-escaping="yes" select="$xhtml11-url"/>/<xsl:value-of disable-output-escaping="yes" select="$xhtml11-link"/></li>
          <!-- <li><xsl:value-of select="$cms-version"/></li> -->
        </ul>
      </div>
    </body>
  </xsl:template>

  <xsl:template match="/body/h1">
    <div>
      <div>
        <xsl:if test="not($cms-ctime = '')">
          <p><xsl:value-of disable-output-escaping="yes" select="$inputvar-creation"/></p>
        </xsl:if>
        <xsl:copy-of select="."/>
        <xsl:value-of disable-output-escaping="yes" select="$contentlink-bc"/>
      </div>
    </div>
  </xsl:template>

  <xsl:template match="/body/p[contains(@class,'description')]">
    <div>
      <xsl:copy-of select="."/>
    </div>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>
