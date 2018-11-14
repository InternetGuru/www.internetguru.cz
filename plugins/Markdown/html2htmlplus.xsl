<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:output method="xml" version="1.0" encoding="utf-8" indent="yes"/>

  <xsl:template match="/body">
    <xsl:copy>
      <xsl:copy-of select="@*" />
      <xsl:call-template name="headingStructure"/>
    </xsl:copy>
  </xsl:template>

  <xsl:template name="headingStructure">

    <!-- params and variables declaration -->
    <xsl:param name="pos" select="1"/>
    <xsl:param name="lvl" select="0"/>
    <xsl:param name="sec" select="0"/>
    <xsl:param name="correct" select="1"/>
    <xsl:variable name="h" select="//*[starts-with(name(), 'h')]"/>

    <xsl:choose>
      <!-- if heading exists -->
      <xsl:when test="$h[$pos]">
        <!-- heading levels -->
        <xsl:variable name="curLvl">
          <xsl:choose>
            <xsl:when test="name($h[$pos])='h1'">1</xsl:when>
            <xsl:when test="name($h[$pos])='h2'">2</xsl:when>
            <xsl:when test="name($h[$pos])='h3'">3</xsl:when>
            <xsl:when test="name($h[$pos])='h4'">4</xsl:when>
            <xsl:when test="name($h[$pos])='h5'">5</xsl:when>
            <xsl:when test="name($h[$pos])='h6'">6</xsl:when>
          </xsl:choose>
        </xsl:variable>

        <xsl:choose>

          <!-- find initial heading level -->
          <xsl:when test="$lvl=0">
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$curLvl"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec"/>
            </xsl:call-template>
          </xsl:when>

          <!-- same level -->
          <xsl:when test="$curLvl = $lvl or not($correct)">
            <!-- generate heading -->
            <xsl:if test="$correct">
              <xsl:element name="h">
                <xsl:copy-of select="$h[$pos]/@*"/>
                <xsl:copy-of select="$h[$pos]/text()"/>
              </xsl:element>
            </xsl:if>
            <xsl:if test="not($correct)">
              <xsl:text disable-output-escaping="yes">&lt;!-- mismatch heading structure ignored --></xsl:text>
              <xsl:copy-of select="$h[$pos]"/>
            </xsl:if>

            <!-- copy content between headings and create desc -->
            <!--https://stackoverflow.com/questions/3835601/how-would-you-find-all-nodes-between-two-h3s-using-xpath-->
            <xsl:if test="$correct">
              <xsl:choose>
                <xsl:when test="name($h[$pos]/following-sibling::*[1]) = 'p'">
                  <desc>
                    <xsl:copy-of select="$h[$pos]/following-sibling::*[1]/@*"/>
                    <xsl:copy-of select="$h[$pos]/following-sibling::*[1]/node()"/>
                  </desc>
                  <xsl:copy-of select="
                  $h[$pos]/following-sibling::*[
                    (
                      (count(.|$h[$pos+1]/preceding-sibling::*) =
                      count($h[$pos+1]/preceding-sibling::*))
                      and
                      position() != count($h[$pos]/following-sibling::*[1])
                    )
                    or
                    (
                      not($h[$pos+1])
                      and
                      position() != count($h[$pos]/following-sibling::*[1])
                    )
                    ]
                  "/>
                </xsl:when>
                <xsl:otherwise>
                  <desc>n/a</desc>
                  <xsl:copy-of select="$h[$pos]/following-sibling::*[
                    (
                      count(.|$h[$pos+1]/preceding-sibling::*) =
                      count($h[$pos+1]/preceding-sibling::*))
                      or
                      not($h[$pos+1]
                    )
                  ]
                  "/>
                </xsl:otherwise>
              </xsl:choose>
            </xsl:if>


            <!--DEBUG:-->
            <!--curLvl = <xsl:value-of select="$curLvl"/>-->
            <!--curHPos = <xsl:value-of select="$curHPos"/>-->
            <!--nextHPos = <xsl:value-of select="$nextHPos"/>-->
            <!--test = <xsl:value-of select="//*[position() = $curHPos]"/>-->
            <!--test2 = <xsl:value-of select="//*[position() = $nextHPos]"/>-->

            <!-- next heading (pos+1) -->
            <xsl:choose>
              <xsl:when test="$correct">
                <xsl:call-template name="headingStructure">
                  <xsl:with-param name="lvl" select="$curLvl"/>
                  <xsl:with-param name="pos" select="$pos+1"/>
                  <xsl:with-param name="sec" select="$sec"/>
                </xsl:call-template>
              </xsl:when>
              <xsl:otherwise>
                <xsl:call-template name="headingStructure">
                  <xsl:with-param name="lvl" select="$lvl"/>
                  <xsl:with-param name="pos" select="$pos+1"/>
                  <xsl:with-param name="sec" select="$sec"/>
                </xsl:call-template>
              </xsl:otherwise>
            </xsl:choose>
          </xsl:when>

          <!-- mismatched heading structure detected -->
          <xsl:when test="$curLvl &gt; 3 and $curLvl - $lvl &gt; 1">
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$lvl"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec"/>
              <xsl:with-param name="correct" select="0"/>
            </xsl:call-template>
          </xsl:when>

          <!-- lower level -->
          <xsl:when test="$curLvl &gt; $lvl">
            <xsl:text disable-output-escaping="yes">&lt;section></xsl:text>
            <!-- call current-level heading -->
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$curLvl"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec+1"/>
              <!-- <xsl:with-param name="secIndent" select="$secIndent"/> -->
            </xsl:call-template>
          </xsl:when>

          <!-- 2+ higher level -->
          <xsl:when test="$lvl - $curLvl &gt; 1">
            <xsl:text disable-output-escaping="yes">&lt;/section></xsl:text>
            <!-- call current-level heading keeping level -->
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$lvl -1"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec -1"/>
            </xsl:call-template>
          </xsl:when>

          <!-- higher level -->
          <xsl:when test="$curLvl &lt; $lvl">
            <xsl:text disable-output-escaping="yes">&lt;/section></xsl:text>
            <!-- call current-level heading -->
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$curLvl"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec -1"/>
            </xsl:call-template>
          </xsl:when>
        </xsl:choose>

      </xsl:when>
      <!-- close all opened section if no further heading -->
      <xsl:when test="$sec > 0">
        <xsl:text disable-output-escaping="yes">&lt;/section></xsl:text>
        <xsl:call-template name="headingStructure">
          <xsl:with-param name="lvl" select="$lvl"/>
          <xsl:with-param name="pos" select="$pos"/>
          <xsl:with-param name="sec" select="$sec -1"/>
        </xsl:call-template>
      </xsl:when>
    </xsl:choose>

  </xsl:template>

</xsl:stylesheet>
