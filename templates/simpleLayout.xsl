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
          <li><a href="?admin">Administrace</a></li>
          <li>©2014 <a href="http://www.internetguru.cz">Internet guru</a></li>
        </ul>
      </div>

    </body>
  </xsl:template>

  <!-- delete remaining empty descriptions -->
  <xsl:template match="//p[contains(@class,'description') and not(string-length(text()))]"/>

  <!-- first list from a group of lists -->
  <xsl:template match="//*[parent::div[contains(@class,'section')] and
    (self::ul or self::ol or self::dl) and
    not(preceding-sibling::*[1][self::ul or self::ol or self::dl]) and
    following-sibling::*[1][self::ul or self::ol or self::dl]]">
    <xsl:text disable-output-escaping="yes">&lt;div class="list multiple">&lt;div></xsl:text>
    <xsl:element name="{name()}">
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </xsl:element>
  </xsl:template>

  <!-- last list from a group of lists -->
  <xsl:template match="//*[parent::div[contains(@class,'section')] and
    (self::ul or self::ol or self::dl) and
    preceding-sibling::*[1][self::ul or self::ol or self::dl] and
    not(following-sibling::*[1][self::ul or self::ol or self::dl])]">
    <xsl:element name="{name()}">
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </xsl:element>
    <xsl:text disable-output-escaping="yes">&lt;/div>&lt;/div></xsl:text>
  </xsl:template>

  <!-- orphan list -->
  <xsl:template match="//*[parent::div[contains(@class,'section')] and
    (self::ul or self::ol or self::dl) and
    not(preceding-sibling::*[1][self::ul or self::ol or self::dl]) and
    not(following-sibling::*[1][self::ul or self::ol or self::dl])]">
    <div class="list">
      <xsl:element name="{name()}">
        <xsl:copy-of select="@*"/>
        <xsl:apply-templates/>
      </xsl:element>
    </div>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>