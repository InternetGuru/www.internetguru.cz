<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="/body">
    <body>

      <div id="content">

        <div>
          <div>
            <xsl:copy-of select="/body/h1" />
            <xsl:if test="count(ol[contains(@class,'cms-breadcrumb')]/li)>1">
              <xsl:copy-of select="ol[contains(@class,'cms-breadcrumb')]"/>
            </xsl:if>
          </div>
        </div>

        <xsl:if test="/body/p[contains(@class,'description') and string-length(text()) > 0]">
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
        <ul><li><a href="?admin">Administrace</a></li><li>©2014 internetguru.cz</li></ul>
      </div>

    </body>
  </xsl:template>

  <xsl:template match="//dl[not(preceding-sibling::*[1][self::dl]) and following-sibling::*[1][self::dl]]">
    <xsl:text disable-output-escaping="yes">&lt;div class="dl">&lt;div></xsl:text>
    <dl>
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </dl>
  </xsl:template>

  <xsl:template match="//dl[preceding-sibling::*[1][self::dl] and not(following-sibling::*[1][self::dl])]">
    <dl>
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </dl>
    <xsl:text disable-output-escaping="yes">&lt;/div>&lt;/div></xsl:text>
  </xsl:template>

  <xsl:template match="//ul">
    <div class="ul"><ul>
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </ul></div>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>