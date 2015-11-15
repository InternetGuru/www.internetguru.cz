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

  <xsl:template match="/body">
    <body>
      <xsl:copy-of select="@*"/>
      <xsl:attribute name="class">
         <xsl:value-of select="concat(@class,' fragmentable scrolltopable')"/>
      </xsl:attribute>
      <xsl:text disable-output-escaping="yes">&lt;!--[if lte IE 7]&gt;</xsl:text>
      <div class="old-ie-warning" style="background:#333;color:white;padding:1em 2em">
        <p>Upozornění: <strong>Používáte zastaralou verzi prohlížeče!</strong> Pro pohodlné prohlížení internetu:</p>
        <ul>
          <li><a style="color:skyblue" href="http://windows.microsoft.com/cs-cz/internet-explorer/download-ie">aktualizujte</a> na vyšší verzi,</li>
          <li>nainstalujte <a style="color:skyblue" href="https://www.google.com/chrome">alternativní prohlížeč</a> nebo</li>
          <li>kontaktujte správce svého počítače.</li>
        </ul>
      </div>
      <xsl:text disable-output-escaping="yes">&lt;![endif]]]--&gt;</xsl:text>
      <div id="content">
        <xsl:apply-templates/>
      </div>
    </body>
  </xsl:template>

  <xsl:template match="/body/h1">
    <div>
      <div>
        <xsl:if test="not($contentlink-author = '')">
          <p class="creation"><xsl:value-of disable-output-escaping="yes" select="$inputvar-creation"/>
            <xsl:attribute name="lang">
              <xsl:value-of disable-output-escaping="yes" select="$cms-lang"/>
            </xsl:attribute>
          </p>
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
