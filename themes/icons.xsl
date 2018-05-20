<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="h3[@id='expertise']/node()">
    <span class="fab fa-fw fa-accessible-icon">icon</span>
    <xsl:value-of select="normalize-space(.)"/>
  </xsl:template>
  
  <xsl:template match="h3[@id='training']/node()">
    <span class="fas fa-fw fa-graduation-cap">icon</span>
    <xsl:value-of select="normalize-space(.)"/>
  </xsl:template>
  
  <xsl:template match="h3[@id='business']/node()">
    <span class="fas fa-fw fa-briefcase">icon</span>
<!--     <span class="fas fa-fw fa-handshake">icon</span> -->
    <xsl:value-of select="normalize-space(.)"/>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>
