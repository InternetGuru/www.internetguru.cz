<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

  <xsl:param name="cms-title" select="''"/>
  <xsl:param name="contentlink-bc" select="''"/>
  <xsl:param name="globalmenu" select="''"/>
  <xsl:param name="agregator-filepath" select="''"/>

  <xsl:param name="cms-lang" select="''"/>
  <xsl:param name="cms-author" select="''"/>
  <xsl:param name="cms-authorid" select="''"/>
  <xsl:param name="cms-resp" select="''"/>
  <xsl:param name="cms-respid" select="''"/>
  <xsl:param name="cms-ctime" select="''"/>
  <xsl:param name="cms-mtime" select="''"/>
  <xsl:param name="cms-version" select="''"/>
  <xsl:param name="cms-desc" select="''"/>
  <xsl:param name="cms-kw" select="''"/>
  <xsl:param name="cms-url" select="''"/>
  <xsl:param name="cms-uri" select="''"/>
  <xsl:param name="cms-link" select="''"/>
  <xsl:param name="cms-logged_user" select="''"/>
  <xsl:param name="cms-super_user" select="''"/>

  <xsl:param name="contentlink-lang" select="''"/>
  <xsl:param name="contentlink-author" select="''"/>
  <xsl:param name="contentlink-authorid" select="''"/>
  <xsl:param name="contentlink-resp" select="''"/>
  <xsl:param name="contentlink-respid" select="''"/>
  <xsl:param name="contentlink-ctime" select="''"/>
  <xsl:param name="contentlink-mtime" select="''"/>

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
      <xsl:apply-templates/>
      <div id="footer">
        <xsl:value-of disable-output-escaping="yes" select="$globalmenu"/>
        <ul>
          <li><xsl:value-of disable-output-escaping="yes" select="$copy"/> <xsl:value-of disable-output-escaping="yes" select="$cms-author"/></li>
          <li>Na službě: <a href='https://www.e-zakladna.cz'>E-Základna</a></li>
          <xsl:if test="not($cms-resp = '')">
            <li>Zodpovídá: <xsl:value-of select="$cms-resp"/></li>
          </xsl:if>
          <xsl:if test="not($cms-mtime = '')">
            <li>Upraveno: <xsl:value-of select="$inputvar-mymtime"/></li>
          </xsl:if>
          <li class="link"><xsl:value-of disable-output-escaping="yes" select="$cms-uri"/></li>
          <!-- <li><xsl:value-of select="$cms-version"/></li> -->
        </ul>
      </div>
    </body>
  </xsl:template>

  <xsl:template match="div[@id='content']">
    <xsl:element name="div">
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
      <ul class="docinfo">
        <xsl:if test="not($contentlink-resp = '')">
          <li class="resp"><xsl:value-of disable-output-escaping="yes" select="$inputvar-resp"/></li>
        </xsl:if>
        <xsl:if test="not($contentlink-mtime = $contentlink-ctime)">
          <li class="mtime"><xsl:value-of disable-output-escaping="yes" select="$inputvar-modified"/></li>
        </xsl:if>
        <xsl:if test="not($cms-super_user = '') and not($agregator-filepath = '')">
          <li class="edit"><xsl:value-of disable-output-escaping="yes" select="$inputvar-edit"/></li>
        </xsl:if>
      </ul>
    </xsl:element>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>
