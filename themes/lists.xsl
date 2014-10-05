<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="/">
    <xsl:apply-templates/>
  </xsl:template>

  <!-- first list from a group of lists -->
  <xsl:template match="//*[not(parent::div[@class='paragraph']) and
    (self::ul or self::ol or self::dl) and
    not(preceding-sibling::*[1][self::ul or self::ol or self::dl]) and
    following-sibling::*[1][self::ul or self::ol or self::dl]
    ]">
    <xsl:text disable-output-escaping="yes">&lt;div class="list multiple">&lt;div></xsl:text>
    <xsl:element name="{name()}">
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </xsl:element>
  </xsl:template>

  <!-- last list from a group of lists -->
  <xsl:template match="//*[not(parent::div[@class='paragraph']) and
    (self::ul or self::ol or self::dl) and
    preceding-sibling::*[1][self::ul or self::ol or self::dl] and
    not(following-sibling::*[1][self::ul or self::ol or self::dl])
    ]">
    <xsl:element name="{name()}">
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </xsl:element>
    <xsl:text disable-output-escaping="yes">&lt;/div>&lt;/div></xsl:text>
  </xsl:template>

  <!-- orphan list -->
  <xsl:template match="//*[not(parent::div[@class='paragraph']) and
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