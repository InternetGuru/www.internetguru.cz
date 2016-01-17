<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

  <xsl:param name="cms-admin_id" select="''"/>
  <xsl:param name="cms-logged_user" select="''"/>
  <xsl:param name="cms-super_user" select="''"/>
  <xsl:param name="cms-cache_nginx" select="''"/>
  <xsl:param name="cms-cache_ignore" select="''"/>
  <xsl:param name="cms-name" select="''"/>
  <xsl:param name="cms-link" select="''"/>
  <xsl:param name="cms-url_debug_on" select="''"/>
  <xsl:param name="cms-url_debug_off" select="''"/>
  <xsl:param name="inputvar-reportbug" select="''"/>
  <xsl:param name="filehandler-cache_file" select="''"/>

  <xsl:template match="/">
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="div[contains(@id, 'footer')]/*[1]">
    <xsl:if test="$cms-logged_user">
      <ul class="adminmenu noprint">
        <li><a href="?admin">Administrace</a></li>
        <li><a href="?log">Logy</a></li>
        <li><a href="?ver">Verze</a></li>
        <li><a href="?import">Import</a></li>
        <li><a href="?subdom">Poddomény</a></li>
      </ul>
      <ul class="adminmenu noprint">
        <li>
          <xsl:element name="a">
            <xsl:if test="$cms-url_debug_off">
              <xsl:attribute name="href">
                <xsl:value-of select="$cms-url_debug_off"/>
              </xsl:attribute>
            </xsl:if>
            <xsl:text>Provozní režim</xsl:text>
          </xsl:element>
        </li>
        <li>
          <xsl:element name="a">
            <xsl:attribute name="href">
              <xsl:value-of select="$cms-url_debug_on"/>
            </xsl:attribute>
            <xsl:text>Ladící režim</xsl:text>
          </xsl:element>
        </li>
        <li>
          <xsl:element name="a">
            <xsl:attribute name="href">
              <xsl:value-of select="$cms-cache_ignore"/>
            </xsl:attribute>
            <xsl:text>Ignorovat mezipaměť</xsl:text>
          </xsl:element>
        </li>
        <li>
          <xsl:element name="a">
            <xsl:attribute name="href">
              <xsl:value-of select="$cms-cache_nginx"/>
            </xsl:attribute>
            <xsl:text>Vyčistit serverovou mezipaměť</xsl:text>
          </xsl:element>
        </li>
        <xsl:if test="$filehandler-cache_file">
          <li>
            <xsl:element name="a">
              <xsl:attribute name="href">
                <xsl:value-of select="$filehandler-cache_file"/>
              </xsl:attribute>
              <xsl:text>Vyčistit souborovou mezipaměť</xsl:text>
            </xsl:element>
          </li>
        </xsl:if>
        <xsl:if test="$inputvar-reportbug">
          <li><xsl:value-of disable-output-escaping="yes" select="$inputvar-reportbug"/></li>
        </xsl:if>
        <li class="admin">Admin: <xsl:value-of select="$cms-admin_id"/></li>
        <li><xsl:value-of select="$cms-name"/></li>
      </ul>
    </xsl:if>
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="node()|@*"/>
    </xsl:copy>
  </xsl:template>

</xsl:stylesheet>
