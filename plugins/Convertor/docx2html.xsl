<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:output method="xml" version="1.0" encoding="utf-8" indent="yes"/>
  <xsl:strip-space elements="*"/>
  <xsl:preserve-space elements="t"/>

  <xsl:param name="headerFile" />
  <xsl:param name="footerFile" />
  <xsl:param name="numberingFile" />
  <xsl:param name="footnotesFile" />
  <xsl:param name="relationsFile" />

  <!-- <xsl:param name="header" select="document($headerFile)//p"/> -->
  <!-- <xsl:param name="footer" select="document($footerFile)//p"/> -->

  <xsl:template xml:space="preserve" match="/"><body xml:lang="cs"><xsl:call-template name="headingStructure"/>·
</body></xsl:template>

  <!-- Template pro zachovani formatovani z dokumentu header.xml
  <xsl:template match="p">
      <xsl:copy>
          <xsl:apply-templates />
      </xsl:copy>
  </xsl:template>
  -->

  <xsl:template name="headingStructure">

    <!-- params and variables declaration -->
    <xsl:param name="pos" select="1"/>
    <xsl:param name="lvl" select="0"/>
    <xsl:param name="sec" select="0"/>
    <xsl:param name="secIndent" select="''"/>
    <xsl:param name="correct" select="1"/>
    <xsl:variable name="h" select="//p[pPr/pStyle[
      @val='Title' or @val='Název' or
      @val='Subtitle' or @val='Podtitul' or
      @val='Heading1' or @val='Nadpis1' or
      @val='Heading2' or @val='Nadpis2' or
      @val='Heading3' or @val='Nadpis3' or
      @val='Heading4' or @val='Nadpis4' or
      @val='Heading5' or @val='Nadpis5' or
      @val='Heading6' or @val='Nadpis6'
      ]][r/t]"/> <!-- ignore empty headings -->
    <xsl:variable name="bookmarkId" select="$h[$pos]/descendant::bookmarkStart/@name"/>

    <xsl:choose>
      <!-- if heading exists -->
      <xsl:when test="$h[$pos]">
        <!-- heading levels -->
        <xsl:variable name="curLvl">
          <xsl:choose>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Title'">1</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Název'">1</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Subtitle'">2</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Podtitul'">2</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading1'">3</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Nadpis1'">3</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading2'">4</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Nadpis2'">4</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading3'">5</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Nadpis3'">5</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading4'">6</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Nadpis4'">6</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading5'">7</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Nadpis5'">7</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading6'">8</xsl:when>
            <xsl:when test="$h[$pos]/pPr/pStyle/@val='Nadpis6'">8</xsl:when>
          </xsl:choose>
        </xsl:variable>

        <xsl:choose>

          <!-- find initial heading level -->
          <xsl:when test="$lvl=0">
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$curLvl"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec"/>
              <xsl:with-param name="secIndent" select="$secIndent"/>
            </xsl:call-template>
          </xsl:when>

          <!-- same level -->
          <xsl:when test="$curLvl = $lvl or not($correct)">
            <!-- generate heading -->
            <xsl:if test="$correct">·
  <xsl:copy-of select="$secIndent"/><xsl:element name="h">
                <xsl:if test="$bookmarkId">
                  <xsl:attribute name="id">
                    <xsl:value-of select="$bookmarkId" />
                  </xsl:attribute>
                </xsl:if>
                <xsl:copy-of select="$h[$pos]//t/text()"/>
              </xsl:element>
            </xsl:if>
            <xsl:if test="not($correct)">
              <xsl:text disable-output-escaping="yes">&lt;!-- mismatch heading structure ignored --></xsl:text>
              <xsl:apply-templates select="$h[$pos]">
                <xsl:with-param name="pIndent" select="$secIndent"/>
              </xsl:apply-templates>
            </xsl:if>

            <!-- description after heading -->
            <xsl:variable name="curHPos" select="count($h[$pos]/preceding-sibling::*)+1" />
            <xsl:variable name="nextHPos" select="count($h[$pos+1]/preceding-sibling::*)+1" />
            <!--
              DEBUG:
              curHPos = <xsl:value-of select="$curHPos"/>
              nextHPos = <xsl:value-of select="$nextHPos"/>
            -->
            <xsl:if test="$correct">
              <xsl:choose>
                <xsl:when test="//p[position() = $curHPos+1][pPr/jc/@val='center'][not(pPr/numPr)] and not($nextHPos = $curHPos+1)">·
  <xsl:copy-of select="$secIndent"/><desc>
                    <xsl:apply-templates select="//p[position() = $curHPos+1]">
                      <xsl:with-param name="nop" select="1"/>
                    </xsl:apply-templates>
                  </desc>
                </xsl:when>
                <xsl:otherwise>·
  <xsl:copy-of select="$secIndent"/><desc><xsl:text disable-output-escaping="yes">&lt;!-- centered paragraph not found (use @ to specify keywords) --></xsl:text></desc>
                  <xsl:if test="not($nextHPos = $curHPos+1)">
                    <xsl:apply-templates select="//p[position() = $curHPos+1]">
                      <xsl:with-param name="pIndent" select="$secIndent"/>
                    </xsl:apply-templates>
                  </xsl:if>
                </xsl:otherwise>
              </xsl:choose>
            </xsl:if>

            <!-- content between headings -->
            <xsl:apply-templates select="//p[position() &gt; $curHPos+$correct
              and (position() &lt; $nextHPos or $nextHPos = 1)]">
              <xsl:with-param name="pIndent" select="$secIndent"/>
            </xsl:apply-templates>

            <!-- next heading (pos+1) -->
            <xsl:choose>
              <xsl:when test="$correct">
                <xsl:call-template name="headingStructure">
                  <xsl:with-param name="lvl" select="$curLvl"/>
                  <xsl:with-param name="pos" select="$pos+1"/>
                  <xsl:with-param name="sec" select="$sec"/>
                  <xsl:with-param name="secIndent" select="$secIndent"/>
                </xsl:call-template>
              </xsl:when>
              <xsl:otherwise>
                <xsl:call-template name="headingStructure">
                  <xsl:with-param name="lvl" select="$lvl"/>
                  <xsl:with-param name="pos" select="$pos+1"/>
                  <xsl:with-param name="sec" select="$sec"/>
                  <xsl:with-param name="secIndent" select="$secIndent"/>
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
              <xsl:with-param name="secIndent" select="$secIndent"/>
              <xsl:with-param name="correct" select="0"/>
            </xsl:call-template>
          </xsl:when>

          <!-- lower level -->
          <xsl:when test="$curLvl &gt; $lvl">·
  <xsl:copy-of select="$secIndent"/><xsl:text disable-output-escaping="yes">&lt;section></xsl:text>
            <!-- call current-level heading -->
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$curLvl"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec+1"/>
              <!-- <xsl:with-param name="secIndent" select="$secIndent"/> -->
              <xsl:with-param name="secIndent" select="concat($secIndent,'  ')"/>
            </xsl:call-template>
          </xsl:when>

          <!-- 2+ higher level -->
          <xsl:when test="$lvl - $curLvl &gt; 1">·
<xsl:copy-of select="$secIndent"/><xsl:text disable-output-escaping="yes">&lt;/section></xsl:text>
            <!-- call current-level heading keeping level -->
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$lvl -1"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec -1"/>
              <xsl:with-param name="secIndent" select="substring($secIndent,3)"/>
            </xsl:call-template>
          </xsl:when>

          <!-- higher level -->
          <xsl:when test="$curLvl &lt; $lvl">·
<xsl:copy-of select="$secIndent"/><xsl:text disable-output-escaping="yes">&lt;/section></xsl:text>
            <!-- call current-level heading -->
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$curLvl"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec -1"/>
              <!-- <xsl:with-param name="secIndent" select="$secIndent"/> -->
              <xsl:with-param name="secIndent" select="substring($secIndent,3)"/>
            </xsl:call-template>
          </xsl:when>
        </xsl:choose>

      </xsl:when>
      <!-- close all opened section if no further heading -->
      <xsl:when test="$sec > 0">·
<xsl:copy-of select="$secIndent"/><xsl:text disable-output-escaping="yes">&lt;/section></xsl:text>
        <xsl:call-template name="headingStructure">
          <xsl:with-param name="lvl" select="$lvl"/>
          <xsl:with-param name="pos" select="$pos"/>
          <xsl:with-param name="sec" select="$sec -1"/>
          <!-- <xsl:with-param name="secIndent" select="$secIndent"/> -->
          <xsl:with-param name="secIndent" select="substring($secIndent,3)"/>
        </xsl:call-template>
      </xsl:when>
    </xsl:choose>

  </xsl:template>

  <xsl:template match="t" priority="1">
    <xsl:param name="nostrong" select="''"/>
    <xsl:variable name="b" select="preceding-sibling::rPr[1]/b/@val = 1"/>
    <xsl:variable name="i" select="preceding-sibling::rPr[1]/i/@val = 1"/>
    <xsl:variable name="u" select="preceding-sibling::rPr[1]/u/@val = 'single'"/>
    <xsl:variable name="del" select="preceding-sibling::rPr[1]/strike/@val = 1"/>
    <xsl:variable name="sup" select="preceding-sibling::rPr[1]/vertAlign/@val = 'superscript'"/>
    <xsl:variable name="sub" select="preceding-sibling::rPr[1]/vertAlign/@val = 'subscript'"/>

    <xsl:if test="$b and not($nostrong)"><xsl:text disable-output-escaping="yes">&lt;strong></xsl:text></xsl:if>
    <xsl:if test="$i"><xsl:text disable-output-escaping="yes">&lt;em></xsl:text></xsl:if>
    <xsl:if test="$u"><xsl:text disable-output-escaping="yes">&lt;samp></xsl:text></xsl:if>
    <xsl:if test="$del"><xsl:text disable-output-escaping="yes">&lt;del></xsl:text></xsl:if>
    <xsl:if test="$sub"><xsl:text disable-output-escaping="yes">&lt;sub></xsl:text></xsl:if>
    <xsl:if test="$sup"><xsl:text disable-output-escaping="yes">&lt;sup></xsl:text></xsl:if>

    <xsl:apply-templates/>

    <xsl:if test="$sup"><xsl:text disable-output-escaping="yes">&lt;/sup></xsl:text></xsl:if>
    <xsl:if test="$sub"><xsl:text disable-output-escaping="yes">&lt;/sub></xsl:text></xsl:if>
    <xsl:if test="$del"><xsl:text disable-output-escaping="yes">&lt;/del></xsl:text></xsl:if>
    <xsl:if test="$u"><xsl:text disable-output-escaping="yes">&lt;/samp></xsl:text></xsl:if>
    <xsl:if test="$i"><xsl:text disable-output-escaping="yes">&lt;/em></xsl:text></xsl:if>
    <xsl:if test="$b and not($nostrong)"><xsl:text disable-output-escaping="yes">&lt;/strong></xsl:text></xsl:if>

  </xsl:template>

  <xsl:template match="p">
    <xsl:param name="pIndent" select="'  '"/>
    <xsl:param name="nop" select="''"/>

    <xsl:if test="r/t">
      <xsl:choose>
        <!-- list items -->
        <xsl:when test="pPr/numPr">
          <!-- mind first list item only -->
          <xsl:if test="preceding-sibling::p[1][not(pPr/numPr)]">
            <!-- unreliable -->
            <!-- or (not(preceding-sibling::p[1]/pPr/numPr/numId/@val = pPr/numPr/numId/@val) and pPr/numPr/ilvl/@val = 0) -->
            <xsl:choose>
              <!-- definition list if first is bold -->
              <xsl:when test="count(r) = count(r/rPr/b[@val = 1])">·
    <xsl:copy-of select="$pIndent"/><dl>·
      <xsl:copy-of select="$pIndent"/><dt>
                    <xsl:apply-templates select="node()">
                      <xsl:with-param name="nostrong" select="1"/>
                    </xsl:apply-templates>
                    <!-- <xsl:copy-of select="r/t/text()"/> -->
                  </dt>
                  <xsl:call-template name="insertDefListItem">
                    <xsl:with-param name="i" select="1"/>
                    <xsl:with-param name="pIndent" select="$pIndent"/>
                  </xsl:call-template>·
    <xsl:copy-of select="$pIndent"/></dl>
              </xsl:when>
              <xsl:otherwise>
                <xsl:call-template name="buildList">
                  <xsl:with-param name="ilvlActual" select="0"/>
                  <xsl:with-param name="i" select="0"/>
                  <xsl:with-param name="ilvl" select="pPr/numPr/ilvl/@val"/>
                  <xsl:with-param name="numId" select="pPr/numPr/numId/@val"/>
                  <xsl:with-param name="indent" select="concat($pIndent,'  ')"/>
                </xsl:call-template>
              </xsl:otherwise>
            </xsl:choose>
          </xsl:if>
        </xsl:when>
        <xsl:otherwise>
          <xsl:choose>
            <xsl:when test="$nop"><xsl:apply-templates select="node()"/></xsl:when>
            <xsl:otherwise>·
    <xsl:copy-of select="$pIndent"/><p><xsl:apply-templates select="node()"/></p>
            </xsl:otherwise>
          </xsl:choose>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:if>

  </xsl:template>

  <xsl:template name="insertDefListItem">
    <xsl:param name="i"/>
    <xsl:param name="pIndent" select="'  '"/>
    <xsl:variable name="item" select="following-sibling::p[$i]"/>

    <xsl:if test="$item//r/t">
    <xsl:choose>
      <xsl:when test="$item/pPr/numPr/ilvl/@val = 0 and ($item/r[1]/rPr/b/@val = 1 or following-sibling::p[$i+1]/pPr/numPr/ilvl/@val &gt; 0)">·
    <xsl:copy-of select="$pIndent"/><dt>
          <!-- <xsl:copy-of select="$item//t/text()"/> -->
          <xsl:apply-templates select="$item/node()">
            <xsl:with-param name="nostrong" select="1"/>
          </xsl:apply-templates>
        </dt>
      </xsl:when>
      <xsl:otherwise>·
    <xsl:copy-of select="$pIndent"/><dd>
          <xsl:apply-templates select="$item/node()"/>
        </dd>
      </xsl:otherwise>
    </xsl:choose>
    </xsl:if>
    <xsl:if test="following-sibling::p[$i+1]/pPr/numPr/numId/@val = $item/pPr/numPr/numId/@val">
      <xsl:call-template name="insertDefListItem">
        <xsl:with-param name="i" select="$i+1"/>
        <xsl:with-param name="pIndent" select="$pIndent"/>
      </xsl:call-template>
    </xsl:if>
  </xsl:template>

  <xsl:template name="buildList">
    <xsl:param name="ilvlActual"/>
    <xsl:param name="i"/>
    <xsl:param name="ilvl"/>
    <xsl:param name="numId"/>
    <xsl:param name="indent" select="'  '"/>

    <!-- set list type -->
    <xsl:variable name="abstractNumber" select="document($numberingFile)//num[@numId=$numId]/abstractNumId/@val"/>
    <xsl:variable name="indentType" select="document($numberingFile)//abstractNum[@abstractNumId=$abstractNumber]/lvl[@ilvl=$ilvlActual]/numFmt/@val"/>
    <xsl:variable name="listType">
      <xsl:choose>
        <xsl:when test="contains($indentType,'bullet')">ul</xsl:when>
        <xsl:otherwise>ol</xsl:otherwise>
      </xsl:choose>
    </xsl:variable>·
<xsl:copy-of select="$indent"/><xsl:element name="{$listType}">
    <!-- create list and insert items -->
      <xsl:call-template name="insertListItem">
        <xsl:with-param name="i" select="$i"/>
        <xsl:with-param name="ilvl" select="$ilvl"/>
        <xsl:with-param name="ilvlActual" select="$ilvlActual"/>
        <xsl:with-param name="indent" select="concat($indent,'  ')"/>
      </xsl:call-template>·
<xsl:copy-of select="$indent"/></xsl:element>
  </xsl:template>

  <xsl:template name="insertListItem">
    <xsl:param name="i"/>
    <xsl:param name="ilvlActual"/>
    <xsl:param name="ilvl"/>
    <xsl:param name="ignore" select="0"/>
    <xsl:param name="indent"/>

    <!-- set variables -->
    <xsl:variable name="ilvlAfter" select="following-sibling::p[$i+1]/pPr/numPr/ilvl/@val"/>
    <xsl:variable name="endRecursion">
      <xsl:choose>
        <xsl:when test="following-sibling::p[$i+1][not(pPr/numPr)] or $ilvlAfter &lt; $ilvlActual">1</xsl:when>
        <xsl:otherwise>0</xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
    <xsl:variable name="nextItemIsDescendant">
      <xsl:choose>
        <xsl:when test="$ilvlAfter &gt; $ilvlActual">1</xsl:when>
        <xsl:otherwise>0</xsl:otherwise>
      </xsl:choose>
    </xsl:variable>
    <xsl:choose>
      <!-- skip ignored items on current level -->
      <xsl:when test="$ignore=1">
        <xsl:if test="$endRecursion=0">
          <xsl:call-template name="insertListItem">
            <xsl:with-param name="i" select="$i+1"/>
            <xsl:with-param name="ilvlActual" select="$ilvlActual"/>
            <xsl:with-param name="ilvl" select="$ilvlAfter"/>
            <xsl:with-param name="ignore" select="$nextItemIsDescendant"/>
            <xsl:with-param name="indent" select="$indent"/>
          </xsl:call-template>
        </xsl:if>
      </xsl:when>
      <!-- else print item -->
      <xsl:otherwise>·
<xsl:copy-of select="$indent"/><xsl:element name="li">
          <xsl:choose>
            <xsl:when test="$i=0">
              <xsl:apply-templates select="node()"/>
            </xsl:when>
            <xsl:otherwise>
              <!-- <xsl:copy-of select="following-sibling::p[$i]/r/t/text()"/> -->
              <xsl:apply-templates select="following-sibling::p[$i]/node()"/>
            </xsl:otherwise>
          </xsl:choose>
          <!-- descendant list item -->
          <xsl:if test="$endRecursion=0 and $nextItemIsDescendant=1">
            <xsl:call-template name="buildList">
              <xsl:with-param name="i" select="$i + 1"/>
              <xsl:with-param name="ilvlActual" select="$ilvlActual + 1"/>
              <xsl:with-param name="ilvl" select="$ilvlAfter"/>
              <xsl:with-param name="numId" select="following-sibling::p[$i+1]/pPr/numPr/numId/@val"/>
              <xsl:with-param name="indent" select="concat($indent,'  ')"/>
            </xsl:call-template>
          </xsl:if>
        </xsl:element>
        <xsl:if test="$endRecursion=0">
          <!-- finish recursion on current level -->
          <xsl:call-template name="insertListItem">
            <xsl:with-param name="i" select="$i+1"/>
            <xsl:with-param name="ilvlActual" select="$ilvlActual"/>
            <xsl:with-param name="ilvl" select="$ilvlAfter"/>
            <xsl:with-param name="ignore" select="$nextItemIsDescendant"/>
            <xsl:with-param name="indent" select="$indent"/>
          </xsl:call-template>
        </xsl:if>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- Template for images -->
  <xsl:template match="drawing">
    <xsl:for-each select="inline/graphic/graphicData/pic">
      <xsl:for-each select="nvPicPr/cNvPr">
        <img src="../media/{@name}" />
      </xsl:for-each>
    </xsl:for-each>
  </xsl:template>

  <!-- Math -->
  <xsl:template match="oMath">
          <xsl:variable name="refId"><xsl:value-of select="count(preceding::oMath[not(preceding::oMath= .)])+1"/></xsl:variable>

          <xsl:element name="math">
              <xsl:attribute name="ref">
                  <xsl:value-of select="$refId"/>
              </xsl:attribute>
          </xsl:element>
  </xsl:template>

  <!--
      <hyperlink r:id="rId4" history="true">
          <r>
              <rPr>
                  <rStyle val="Hyperlink"/>
              </rPr>
              <t>hyperlink</t>
          </r>
      </hyperlink>
  -->
  <!-- Template for hyperlinks -->
    <xsl:template match="hyperlink">
      <xsl:variable name="relationships" select="document($relationsFile)"/>

      <xsl:element name="a">
          <xsl:attribute name="href">
              <xsl:choose>
                  <xsl:when test="@anchor">#<xsl:value-of select="@anchor"/></xsl:when>
                  <xsl:when test="@bookmark">#<xsl:value-of select="@bookmark"/></xsl:when>
                  <xsl:when test="@arbLocation">#<xsl:value-of select="@arbLocation"/></xsl:when>
                  <xsl:when test="@id">
                      <xsl:variable name="idRelationship" select="@id"/>
                      <xsl:value-of select="$relationships//*[name() = 'Relationship' and @Id=$idRelationship]/@Target"/>
                  </xsl:when>
                  <xsl:otherwise><xsl:apply-templates select="node()"/></xsl:otherwise>
              </xsl:choose>
            </xsl:attribute>
            <!-- <xsl:apply-templates select="node()"/> -->
            <xsl:copy-of select="node()//t/text()"/>
      </xsl:element>
    </xsl:template>

  <!--
      <tbl>
          <tblPr>
              <tblStyle val="TableGrid"/>
              <tblW type="auto" w="0"/>
              <tblLook val="04A0"/>
          </tblPr>
          <tblGrid>
              <gridCol w="3561"/>
              <gridCol w="3561"/>
              <gridCol w="3561"/>
          </tblGrid>
          <tr>
              <tc>
                  <tcPr>

   -->

  <!-- Template for tables -->
  <xsl:template match="tbl">
      <xsl:element name="table">
          <xsl:for-each select="tr">
          <xsl:element name="tr">
              <xsl:for-each select="tc">
                  <xsl:element name="td">
                      <xsl:apply-templates select="p" /> <!-- Moznost mit v tabulce nadpis, seznam atd. -->
                      <!-- <xsl:apply-templates select="p/r/t" />-->
                  </xsl:element>
              </xsl:for-each>
          </xsl:element>
          </xsl:for-each>
      </xsl:element>
  </xsl:template>

  <!-- Break line and Page break-->
  <xsl:template match="br">
      <xsl:choose>
          <xsl:when test="contains(@type, 'page')"><xsl:element name="pagebreak" /></xsl:when>
          <xsl:otherwise><xsl:element name="br" /></xsl:otherwise>
      </xsl:choose>
          <!-- Is a page break?
    <xsl:element name="br">
              <xsl:if test="contains(@type, 'page')">
                      <xsl:attribute name="style">page-break-before:always</xsl:attribute>
              </xsl:if>
          </xsl:element>
          -->
  </xsl:template>

  <!-- Footnotes -->
  <xsl:template match="footnoteReference">
      <xsl:if test="$footnotesFile">
          <xsl:variable name="referenceId" select="@id"/>
          <xsl:variable name="fi" select="document($footnotesFile)"/>
          <footnote><xsl:value-of select="$fi//footnote[@id=$referenceId]/p//r//t"/></footnote>
      </xsl:if>
  </xsl:template>

</xsl:stylesheet>