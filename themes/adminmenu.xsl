<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0">

  <xsl:param name="cms-admin_id" select="''"/>
  <xsl:param name="cms-logged_user" select="''"/>
  <xsl:param name="cms-super_user" select="''"/>
  <xsl:param name="cms-pagespeed" select="''"/>
  <xsl:param name="cms-clearcacheurl" select="''"/>
  <xsl:param name="cms-name" select="''"/>
  <xsl:param name="filehandler-cfcurl" select="''"/>

  <xsl:template match="/">
    <xsl:apply-templates/>
  </xsl:template>

  <xsl:template match="div[contains(@id, 'footer')]/*[1]">
    <xsl:if test="$cms-logged_user">
      <ul class="adminmenu">
        <li><a href="?admin">Administrace</a></li>
        <li><a href="?log">Logy</a></li>
        <li><a href="?ver">Verze</a></li>
        <li><a href="?import">Import</a></li>
        <li><a href="?subdom">Poddomény</a></li>
      </ul>
      <ul class="adminmenu">
        <li>
          <xsl:choose>
            <xsl:when test="$cms-pagespeed = 'off'">
              <a href="?PageSpeed=start">Zapnout PageSpeed</a>
            </xsl:when>
            <xsl:otherwise>
              <a href="?PageSpeed=off">Vypnout PageSpeed</a>
            </xsl:otherwise>
          </xsl:choose>
        </li>
        <li>
          <xsl:element name="a">
            <xsl:attribute name="href">
              <xsl:value-of select="$cms-clearcacheurl"/>
            </xsl:attribute>
            <xsl:text>Smazat mezipaměť</xsl:text>
          </xsl:element>
        </li>
        <xsl:if test="$filehandler-cfcurl">
          <li>
            <xsl:element name="a">
              <xsl:attribute name="href">
                <xsl:value-of select="$filehandler-cfcurl"/>
              </xsl:attribute>
              <xsl:text>Smazat dočasné soubory</xsl:text>
            </xsl:element>
          </li>
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
