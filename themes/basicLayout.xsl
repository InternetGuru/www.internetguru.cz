<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:param name="contentlink-bc" select="''"/>
  <xsl:param name="cms-lang" select="''"/>

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
      <div id="header">
        <xsl:value-of disable-output-escaping="yes" select="$contentlink-bc"/>
      </div>
      <div id="content">
        <xsl:apply-templates/>
      </div>
    </body>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>