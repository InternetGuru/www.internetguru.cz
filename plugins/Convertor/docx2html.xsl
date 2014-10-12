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

  <xsl:template xml:space="preserve" match="/"><body xml:lang="cs"><xsl:call-template name="headingStructure"/>
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
      ]]"/>
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
            </xsl:call-template>
          </xsl:when>

          <!-- same level -->
          <xsl:when test="$curLvl = $lvl or not($correct)">
            <!-- generate heading -->
            <xsl:if test="$correct">&#160;
  <xsl:element name="h">
                <xsl:if test="$bookmarkId">
                  <xsl:attribute name="id">
                    <xsl:value-of select="$bookmarkId" />
                  </xsl:attribute>
                </xsl:if>
                <xsl:apply-templates select="$h[$pos]/r"/>
              </xsl:element>
            </xsl:if>
            <xsl:if test="not($correct)">
              <xsl:text disable-output-escaping="yes">&lt;!-- mismatch heading structure ignored --></xsl:text>
              <xsl:apply-templates select="$h[$pos]"/>
            </xsl:if>
            <!-- content between this and next heading -->
            <xsl:variable name="curHPos" select="count($h[$pos]/preceding-sibling::*)+1" />
            <xsl:variable name="nextHPos" select="count($h[$pos+1]/preceding-sibling::*)+1" />
            <xsl:if test="$correct">
               <xsl:choose>
                <xsl:when test="//p[position() = $curHPos+1][pPr/jc/@val='center'][not(pPr/numPr)] and not($nextHPos = $curHPos+1)">&#160;
  <desc>
                    <xsl:apply-templates select="//p[position() = $curHPos+1]/r"/>
                  </desc>
                </xsl:when>
                <xsl:otherwise>&#160;
  <desc><xsl:text disable-output-escaping="yes">&lt;!-- centered paragraph not found --></xsl:text></desc>
                  <xsl:if test="$nextHPos - $curHPos &gt; 1">
                    <xsl:apply-templates select="//p[position() = $curHPos+1]"/>
                  </xsl:if>
                </xsl:otherwise>
              </xsl:choose>
            </xsl:if>
            <!-- content between headings -->
            <xsl:apply-templates select="//p[position() &gt; $curHPos+$correct and (position() &lt; $nextHPos or $nextHPos = $curHPos)]"/>
            <!-- next heading (pos+1) -->
            <xsl:if test="$correct">
              <xsl:call-template name="headingStructure">
                <xsl:with-param name="lvl" select="$curLvl"/>
                <xsl:with-param name="pos" select="$pos+1"/>
                <xsl:with-param name="sec" select="$sec"/>
              </xsl:call-template>
            </xsl:if>
            <xsl:if test="not($correct)">
              <xsl:call-template name="headingStructure">
                <xsl:with-param name="lvl" select="$lvl"/>
                <xsl:with-param name="pos" select="$pos+1"/>
                <xsl:with-param name="sec" select="$sec"/>
              </xsl:call-template>
            </xsl:if>
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
          <xsl:when test="$curLvl &gt; $lvl">&#160;
  <xsl:text disable-output-escaping="yes">&lt;section></xsl:text>
            <!-- call current-level heading -->
            <xsl:call-template name="headingStructure">
              <xsl:with-param name="lvl" select="$curLvl"/>
              <xsl:with-param name="pos" select="$pos"/>
              <xsl:with-param name="sec" select="$sec+1"/>
            </xsl:call-template>
          </xsl:when>

          <!-- 2+ higher level -->
          <xsl:when test="$lvl - $curLvl &gt; 1">
            <!-- close section -->
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
            <!-- close section -->
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

  <xsl:template match="t" priority="1">
    <xsl:variable name="bold" select="preceding-sibling::rPr[1]/b"/>
  	<xsl:variable name="italic" select="preceding-sibling::rPr[1]/i"/>
    <xsl:variable name="del" select="preceding-sibling::rPr[1]/strike"/>
  	<xsl:variable name="sup" select="contains(preceding-sibling::rPr[1]/vertAlign/@val, 'superscript')"/>
  	<xsl:variable name="sub" select="contains(preceding-sibling::rPr[1]/vertAlign/@val, 'subscript')"/>

  	<xsl:choose>
  		<xsl:when test="$sup and $bold and $italic and $del"><sup><del><strong><em><xsl:apply-templates/></em></strong></del></sup></xsl:when>
  		<xsl:when test="$sub and $bold and $italic and $del"><sub><del><strong><em><xsl:apply-templates /></em></strong></del></sub></xsl:when>
  		<xsl:when test="$sup and $bold and $italic"><sup><strong><em><xsl:apply-templates/></em></strong></sup></xsl:when>
  		<xsl:when test="$sub and $bold and $italic"><sub><strong><em><xsl:apply-templates /></em></strong></sub></xsl:when>
      <xsl:when test="$sup and $bold and $del"><sup><strong><del><xsl:apply-templates/></del></strong></sup></xsl:when>
  		<xsl:when test="$sub and $bold and $del"><sub><strong><del><xsl:apply-templates /></del></strong></sub></xsl:when>
      <xsl:when test="$sup and $del and $italic"><sup><em><del><xsl:apply-templates/></del></em></sup></xsl:when>
  		<xsl:when test="$sub and $del and $italic"><sub><em><del><xsl:apply-templates /></del></em></sub></xsl:when>
  		<xsl:when test="$bold and $italic"><strong><em><xsl:apply-templates /></em></strong></xsl:when>
  		<xsl:when test="$sup and $bold"><sup><strong><xsl:apply-templates /></strong></sup></xsl:when>
  		<xsl:when test="$sub and $bold"><sub><strong><xsl:apply-templates /></strong></sub></xsl:when>
      <xsl:when test="$bold and $del"><strong><del><xsl:apply-templates /></del></strong></xsl:when>
  		<xsl:when test="$del and $italic"><em><del><xsl:apply-templates /></del></em></xsl:when>
  		<xsl:when test="$sup and $italic"><sup><em><xsl:apply-templates /></em></sup></xsl:when>
  		<xsl:when test="$sub and $italic"><sub><em><xsl:apply-templates /></em></sub></xsl:when>
      <xsl:when test="$del and $sup"><sup><del><xsl:apply-templates /></del></sup></xsl:when>
  		<xsl:when test="$del and $sub"><sub><del><xsl:apply-templates /></del></sub></xsl:when>
  		<xsl:when test="$bold"><strong><xsl:apply-templates /></strong></xsl:when>
      <xsl:when test="$italic"><em><xsl:apply-templates /></em></xsl:when>
  		<xsl:when test="$sup"><sup><xsl:apply-templates /></sup></xsl:when>
  		<xsl:when test="$sub"><sub><xsl:apply-templates /></sub></xsl:when>
      <xsl:when test="$del"><del><xsl:apply-templates /></del></xsl:when>
  		<xsl:otherwise><xsl:apply-templates /></xsl:otherwise>
  		<!--<xsl:otherwise><xsl:value-of select="." disable-output-escaping="yes"/></xsl:otherwise>-->
  	</xsl:choose>
  </xsl:template>

  <xsl:template match="p">
    <xsl:choose>
      <!-- list items -->
      <xsl:when test="pPr/numPr">
        <!-- mind first list item only -->
        <xsl:if test="preceding-sibling::p[1][not(pPr/numPr)] or (not(preceding-sibling::p[1]/pPr/numPr/numId/@val = pPr/numPr/numId/@val) and pPr/numPr/ilvl/@val = 0)">
          <xsl:choose>
            <!-- definition list if first is bold -->
            <xsl:when test="count(r) = 1 and r/rPr/b">&#160;
  <dl>&#160;
    <dt>
                  <xsl:copy-of select="r/t/text()"/>
                </dt>
                <xsl:call-template name="insertDefListItem">
                  <xsl:with-param name="i" select="1"/>
                </xsl:call-template>
              </dl>
            </xsl:when>
            <xsl:otherwise>
              <xsl:call-template name="buildList">
                <xsl:with-param name="ilvlActual" select="0"/>
                <xsl:with-param name="i" select="0"/>
                <xsl:with-param name="ilvl" select="pPr/numPr/ilvl/@val"/>
                <xsl:with-param name="numId" select="pPr/numPr/numId/@val"/>
              </xsl:call-template>
            </xsl:otherwise>
          </xsl:choose>
        </xsl:if>
      </xsl:when>
      <xsl:otherwise>&#160;
  <p><xsl:apply-templates select="node()"/></p>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <xsl:template name="insertDefListItem">
    <xsl:param name="i"/>
    <xsl:variable name="item" select="following-sibling::p[$i]"/>
    <xsl:choose>
      <xsl:when test="$item/pPr/numPr/ilvl/@val = 0 and ((count($item/r) = 1 and $item/r/rPr/b) or (following-sibling::p[$i+1]/pPr/numPr/ilvl/@val > $item/pPr/numPr/ilvl/@val))">&#160;
    <dt>
          <xsl:copy-of select="$item//t/text()"/>
          <!-- <xsl:apply-templates select="$item/node()"/> -->
        </dt>
      </xsl:when>
      <xsl:otherwise>&#160;
    <dd>
          <xsl:apply-templates select="$item/node()"/>
        </dd>
      </xsl:otherwise>
    </xsl:choose>
    <xsl:if test="following-sibling::p[$i+1]/pPr/numPr/numId/@val = $item/pPr/numPr/numId/@val">
      <xsl:call-template name="insertDefListItem">
        <xsl:with-param name="i" select="$i+1"/>
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
    </xsl:variable>&#160;
<xsl:copy-of select="$indent"/><xsl:element name="{$listType}">
    <!-- create list and insert items -->
      <xsl:call-template name="insertListItem">
        <xsl:with-param name="i" select="$i"/>
        <xsl:with-param name="ilvl" select="$ilvl"/>
        <xsl:with-param name="ilvlActual" select="$ilvlActual"/>
        <xsl:with-param name="indent" select="concat($indent,'  ')"/>
      </xsl:call-template>&#160;
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
      <xsl:otherwise>&#160;
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
            <xsl:apply-templates select="node()"/>
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