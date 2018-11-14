<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="body">
    <html><body>
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </body></html>
  </xsl:template>

  <xsl:template match="//h">
    <xsl:variable name="level" select="count(ancestor::section)"/>
    <xsl:element name="h{$level+1}">
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </xsl:element>
  </xsl:template>

  <xsl:template match="//desc">
    <p>
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </p>
  </xsl:template>

  <xsl:template match="//section">
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="//blockcode">
    <pre><code>
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
    </code></pre>
  </xsl:template>

  <xsl:template match="//p[count(ul|ol|dl)>0]">
    <xsl:copy-of select="@*"/>
    <xsl:for-each select="node()">
      <xsl:variable name="pos" select="position()"/>
      <xsl:variable name="prev" select="name(../node()[($pos)-1])"/>
      <xsl:variable name="next" select="name(../node()[($pos)+1])"/>
      <xsl:if test="not(self::ul) and not(self::ol) and not(self::dl)
                  and (
                      $prev='ul' or $prev='ol' or $prev = 'dl'
                      or position()=1
                      )
                  ">
        <xsl:text disable-output-escaping="yes">&lt;p></xsl:text>
      </xsl:if>
      <xsl:copy>
        <xsl:apply-templates select="node()|@*"/>
      </xsl:copy>
      <xsl:if test="not(self::ul) and not(self::ol) and not(self::dl)
                  and (
                      $next='ul' or $next='ol' or $next = 'dl'
                      or position()=count(../node())
                      )
                  ">
        <xsl:text disable-output-escaping="yes">&lt;/p></xsl:text>
      </xsl:if>
    </xsl:for-each>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>
