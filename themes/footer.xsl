<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
  xmlns:php="http://php.net/xsl">

  <xsl:param name="cms-title" select="''"/>
  <xsl:param name="contentlink-bc" select="''"/>
  <xsl:param name="globalmenu" select="''"/>

  <xsl:param name="cms-lang" select="''"/>
  <xsl:param name="cms-author" select="''"/>
  <xsl:param name="cms-authorid" select="''"/>
  <xsl:param name="cms-resp" select="''"/>
  <xsl:param name="cms-respid" select="''"/>
  <xsl:param name="cms-ctime" select="''"/>
  <xsl:param name="cms-mtime" select="''"/>

  <xsl:param name="contentlink-lang" select="''"/>
  <xsl:param name="contentlink-author" select="''"/>
  <xsl:param name="contentlink-authorid" select="''"/>
  <xsl:param name="contentlink-resp" select="''"/>
  <xsl:param name="contentlink-respid" select="''"/>
  <xsl:param name="contentlink-ctime" select="''"/>
  <xsl:param name="contentlink-mtime" select="''"/>

  <xsl:param name="cms-version" select="''"/>
  <xsl:param name="cms-desc" select="''"/>
  <xsl:param name="cms-kw" select="''"/>
  <xsl:param name="xhtml11-url" select="''"/>
  <xsl:param name="xhtml11-link" select="''"/>
  <xsl:param name="inputvar-myctime" select="''"/>
  <xsl:param name="inputvar-mymtime" select="''"/>
  <xsl:param name="inputvar-creation" select="''"/>
  <xsl:param name="inputvar-cyear" select="''"/>
  <xsl:param name="inputvar-year" select="''"/>

  <xsl:variable name="copy">
    <xsl:choose>
      <xsl:when test="$inputvar-cyear = $inputvar-year">© <xsl:value-of disable-output-escaping="yes" select="$inputvar-cyear"/></xsl:when>
      <xsl:otherwise>© <xsl:value-of disable-output-escaping="yes" select="$inputvar-cyear"/>–<xsl:value-of disable-output-escaping="yes" select="$inputvar-year"/></xsl:otherwise>
    </xsl:choose>
  </xsl:variable>

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates select="*"/>
      <div id="footer">
        <xsl:value-of disable-output-escaping="yes" select="$globalmenu"/>
        <ul>
          <li><xsl:value-of disable-output-escaping="yes" select="$copy"/> <xsl:value-of disable-output-escaping="yes" select="$cms-author"/></li>
          <li>Na službě: <a href='http://www.ezakladna.cz'>E-Základna</a></li>
          <xsl:if test="not($cms-resp = '')">
            <li>Zodpovídá: <xsl:value-of select="$cms-resp"/></li>
          </xsl:if>
          <xsl:if test="not($cms-mtime = '')">
            <li>Upraveno: <xsl:value-of select="$inputvar-mymtime"/></li>
          </xsl:if>
          <li class="link"><xsl:value-of disable-output-escaping="yes" select="$xhtml11-url"/>/<xsl:value-of disable-output-escaping="yes" select="$xhtml11-link"/></li>
          <!-- <li><xsl:value-of select="$cms-version"/></li> -->
        </ul>
      </div>
    </body>
  </xsl:template>

  <xsl:template match="/div[id='content']">
    <div>
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates select="*"/>
      <xsl:if test="not($contentlink-resp = '') or not($contentlink-mtime = $contentlink-ctime)">
        <ul class="docinfo">
          <xsl:if test="not($contentlink-resp = '')">
            <li><xsl:value-of disable-output-escaping="yes" select="$inputvar-resp"/></li>
          </xsl:if>
          <xsl:if test="not($contentlink-mtime = $contentlink-ctime)">
            <li><xsl:value-of disable-output-escaping="yes" select="$inputvar-modified"/></li>
          </xsl:if>
        </ul>
      </xsl:if>
    </div>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>
