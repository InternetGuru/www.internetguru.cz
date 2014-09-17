<xsl:stylesheet version="1.0"
  xmlns:xhtml="http://www.w3.org/1999/xhtml"
  xmlns="http://www.w3.org/1999/xhtml"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:xs="http://www.w3.org/2001/XMLSchema"
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"
  exclude-result-prefixes="xhtml xsl xs w m">

<!--<xsl:output method="xml" version="1.0" encoding="UTF-8" doctype-public="-//W3C//DTD XHTML 1.1//EN" doctype-system="http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd" indent="no"/>-->
<xsl:output method="xml" version="1.0" encoding="UTF-8" indent="no"/>

<!-- deklarovani parametru poslane transformatorem -->
<xsl:param name="headerFile" />
<xsl:param name="footerFile" />
<xsl:param name="numberingFile" /> <!-- TODO osetrit, pokud soubor nebude existovat -->
<xsl:param name="footnotesFile" />
<xsl:param name="relationsFile" /> <!-- TODO osetrit, pokud soubor nebude existovat -->

<!--<xsl:param name="header" select="document('system-property('user.home')/gdgenerator/source-docx/word/header1.xml')//w:p"/>-->

<xsl:param name="header" select="document($headerFile)//w:p"/>
<xsl:param name="footer" select="document($footerFile)//w:p"/>


<xsl:template match="/">
  <body xml:lang="cs">

    <!-- TODO prejmenovat jen na header a footer-->
    <!-- <xsl:if test="$headerFile"><header><xsl:apply-templates select="$header"/></header></xsl:if> -->
    <!-- <xsl:if test="$footerFile"><footer><xsl:apply-templates select="$footer"/></footer></xsl:if> -->

    <xsl:call-template name="h">
      <xsl:with-param name="pos" select="1"/>
      <xsl:with-param name="lvl" select="1"/>
      <xsl:with-param name="sec" select="0"/>
    </xsl:call-template>

  </body>
</xsl:template>

<!-- Template pro zachovani formatovani z dokumentu header.xml
<xsl:template match="w:p">
    <xsl:copy>
        <xsl:apply-templates />
    </xsl:copy>
</xsl:template>
-->

<!--
pro vsechny skupiny nadpisu konkretni urovne (napr. title, heading1...)
napis <section><h>..</h> ... </section><section><h>..</h> ... </section>
 -->


<xsl:template name="h">

  <xsl:param name="pos"/>
  <xsl:param name="lvl"/>
  <xsl:param name="sec"/>
  <xsl:variable name="bookmarkId" select="descendant::bookmarkStart/@name"/>
  <xsl:variable name="h" select="//p[pPr/pStyle[@val='Title' or @val='Heading1' or @val='Heading2' or @val='Heading3']]"/>

  <xsl:choose>

    <xsl:when test="not($h[$pos]) and $sec > 0">
        <xsl:text disable-output-escaping="yes">&lt;/section></xsl:text>
        <xsl:call-template name="h">
          <xsl:with-param name="lvl" select="$lvl"/>
          <xsl:with-param name="pos" select="$pos"/>
          <xsl:with-param name="sec" select="$sec -1"/>
        </xsl:call-template>
    </xsl:when>

    <xsl:otherwise>

      <xsl:variable name="curLvl">
        <xsl:choose>
          <xsl:when test="$h[$pos]/pPr/pStyle/@val='Title'">1</xsl:when>
          <xsl:when test="$h[$pos]/pPr/pStyle/@val='Subtitle'">2</xsl:when>
          <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading1'">3</xsl:when>
          <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading2'">4</xsl:when>
          <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading3'">5</xsl:when>
          <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading4'">6</xsl:when>
          <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading5'">7</xsl:when>
          <xsl:when test="$h[$pos]/pPr/pStyle/@val='Heading6'">8</xsl:when>
        </xsl:choose>
      </xsl:variable>

      <!-- na urovni vypise h a next -->
      <xsl:if test="$curLvl = $lvl">
        <xsl:element name="h">
          <xsl:if test="$bookmarkId">
            <xsl:attribute name="id">
              <xsl:value-of select="$bookmarkId" />
            </xsl:attribute>
          </xsl:if>
          <xsl:apply-templates select="$h[$pos]"/>
        </xsl:element>

        <xsl:variable name="currentPosition" select="count($h[$pos]/preceding-sibling::*)" />

        <xsl:variable name="position" select="count(//p[pPr/pStyle[@val='Title' or @val='Heading1' or @val='Heading2' or @val='Heading3']][$pos+1]/preceding-sibling::*)" />

        <!-- currentPosition <xsl:value-of select="$currentPosition"/> lt <xsl:value-of select="$position"/>
        x -->


        <xsl:apply-templates select="//p[position() &gt; $currentPosition+1 and (position() &lt; $position+1 or $position=0)]"/>

        <xsl:call-template name="h">
          <xsl:with-param name="lvl" select="$curLvl"/>
          <xsl:with-param name="pos" select="$pos+1"/>
          <xsl:with-param name="sec" select="$sec"/>
        </xsl:call-template>
      </xsl:if>

      <!-- vnoreni otevre section a next -->
      <xsl:if test="$curLvl &gt; $lvl">
        <xsl:text disable-output-escaping="yes">&lt;section></xsl:text>
        <xsl:call-template name="h">
          <xsl:with-param name="lvl" select="$curLvl"/>
          <xsl:with-param name="pos" select="$pos"/>
          <xsl:with-param name="sec" select="$sec+1"/>
        </xsl:call-template>
      </xsl:if>

      <!-- vynoreni uzavre section a next -->
      <xsl:if test="$curLvl &lt; $lvl">
        <xsl:text disable-output-escaping="yes">&lt;/section></xsl:text>
        <xsl:call-template name="h">
          <xsl:with-param name="lvl" select="$curLvl"/>
          <xsl:with-param name="pos" select="$pos"/>
          <xsl:with-param name="sec" select="$sec -1"/>
        </xsl:call-template>
      </xsl:if>

    </xsl:otherwise>
  </xsl:choose>

</xsl:template>

<xsl:template match="t" priority="1">
	<xsl:variable name="bold" select="preceding-sibling::rPr[1]/b"/>
	<xsl:variable name="italic" select="preceding-sibling::rPr[1]/i"/>
        <xsl:variable name="strike" select="preceding-sibling::rPr[1]/strike"/>
	<xsl:variable name="sup" select="contains(preceding-sibling::rPr[1]/vertAlign/@val, 'superscript')"/>
	<xsl:variable name="sub" select="contains(preceding-sibling::rPr[1]/vertAlign/@val, 'subscript')"/>

    <!--
	<xsl:for-each select='preceding-sibling::rPr[1]'>
		<xsl:choose>
			<xsl:when test="b"><strong><xsl:value-of select="parent::*/t" /></strong></xsl:when>
			<xsl:when test="i"><em><xsl:value-of select="parent::*/t" /></em></xsl:when>
		</xsl:choose>
	</xsl:for-each>
     -->

	<xsl:choose>
		<xsl:when test="$sup and $bold and $italic and $strike"><sup><strike><strong><em><xsl:apply-templates/></em></strong></strike></sup></xsl:when>
		<xsl:when test="$sub and $bold and $italic and $strike"><sub><strike><strong><em><xsl:apply-templates /></em></strong></strike></sub></xsl:when>

		<xsl:when test="$sup and $bold and $italic"><sup><strong><em><xsl:apply-templates/></em></strong></sup></xsl:when>
		<xsl:when test="$sub and $bold and $italic"><sub><strong><em><xsl:apply-templates /></em></strong></sub></xsl:when>
                <xsl:when test="$sup and $bold and $strike"><sup><strong><strike><xsl:apply-templates/></strike></strong></sup></xsl:when>
		<xsl:when test="$sub and $bold and $strike"><sub><strong><strike><xsl:apply-templates /></strike></strong></sub></xsl:when>
                <xsl:when test="$sup and $strike and $italic"><sup><em><strike><xsl:apply-templates/></strike></em></sup></xsl:when>
		<xsl:when test="$sub and $strike and $italic"><sub><em><strike><xsl:apply-templates /></strike></em></sub></xsl:when>

		<xsl:when test="$bold and $italic"><strong><em><xsl:apply-templates /></em></strong></xsl:when>
		<xsl:when test="$sup and $bold"><sup><strong><xsl:apply-templates /></strong></sup></xsl:when>
		<xsl:when test="$sub and $bold"><sub><strong><xsl:apply-templates /></strong></sub></xsl:when>
                <xsl:when test="$bold and $strike"><strong><strike><xsl:apply-templates /></strike></strong></xsl:when>

		<xsl:when test="$strike and $italic"><em><strike><xsl:apply-templates /></strike></em></xsl:when>
		<xsl:when test="$sup and $italic"><sup><em><xsl:apply-templates /></em></sup></xsl:when>
		<xsl:when test="$sub and $italic"><sub><em><xsl:apply-templates /></em></sub></xsl:when>

                <xsl:when test="$strike and $sup"><sup><strike><xsl:apply-templates /></strike></sup></xsl:when>
		<xsl:when test="$strike and $sub"><sub><strike><xsl:apply-templates /></strike></sub></xsl:when>

		<xsl:when test="$bold"><strong><xsl:apply-templates /></strong></xsl:when>
                <xsl:when test="$italic"><em><xsl:apply-templates /></em></xsl:when>
		<xsl:when test="$sup"><sup><xsl:apply-templates /></sup></xsl:when>
		<xsl:when test="$sub"><sub><xsl:apply-templates /></sub></xsl:when>
                <xsl:when test="$strike"><strike><xsl:apply-templates /></strike></xsl:when>
		<xsl:otherwise><xsl:apply-templates /></xsl:otherwise>
		<!--<xsl:otherwise><xsl:value-of select="." disable-output-escaping="yes"/></xsl:otherwise>-->
	</xsl:choose>

</xsl:template>


<xsl:template match="p[pPr/numPr]">
        <xsl:variable name="styleName" select="./pPr/pStyle/@val"/>
        <xsl:variable name="theLevel" select="./pPr/numPr/ilvl/@val"/>
        <xsl:variable name="theNumId" select="./pPr/numPr/numId/@val"/>

        <xsl:variable name="abstractNumber" select="document($numberingFile)//w:num[@w:numId=$theNumId]/w:abstractNumId/@w:val"/>
        <xsl:variable name="indentType" select="document($numberingFile)//w:abstractNum[@w:abstractNumId=$abstractNumber]/w:lvl[@w:ilvl=$theLevel]/w:numFmt/@w:val"/>


		<xsl:variable name="listType">
                    <xsl:choose>
                            <xsl:when test="contains($indentType,'bullet')">ul</xsl:when>
                            <xsl:otherwise>ol</xsl:otherwise>
                    </xsl:choose>
		</xsl:variable>


 		<xsl:choose>
		<xsl:when test='not(preceding-sibling::*[1][self::p][descendant::pPr/numPr/numId/@val = $theNumId])'>
                    <xsl:element name="{$listType}">
                        <xsl:for-each select='. | following-sibling::p[descendant::pPr/numPr/numId/@val = $theNumId and descendant::pPr/numPr/ilvl/@val = $theLevel]'>
                            <!-- <xsl:variable name="bookmarkId" select="descendant::bookmarkStart/@name"/> -->
                            <xsl:choose>
                                <xsl:when test='not( (following-sibling::p[1]/pPr/numPr/ilvl/@val) = $theLevel )'>
                                    <xsl:element name="li">
                                        <!-- <xsl:if test="$bookmarkId">
                                            <xsl:attribute name="id">
                                                <xsl:value-of select="$bookmarkId" />
                                            </xsl:attribute>
                                        </xsl:if> -->
                                        <xsl:apply-templates />
                                        <xsl:apply-templates select="following-sibling::p[1]" mode='restOfList' />
                                    </xsl:element>
                                </xsl:when>
                                <xsl:otherwise>
                                    <!-- <xsl:variable name="bookmarkId" select="descendant::bookmarkStart/@name"/> -->
                                    <xsl:element name="li">
                                        <!-- <xsl:if test="$bookmarkId">
                                            <xsl:attribute name="id">
                                                <xsl:value-of select="$bookmarkId" />
                                            </xsl:attribute>
                                        </xsl:if> -->
                                           <xsl:apply-templates />
                                    </xsl:element>
                                </xsl:otherwise>
                            </xsl:choose>
                        </xsl:for-each>
                    </xsl:element>
		</xsl:when>
		</xsl:choose>

    </xsl:template>

<xsl:template match="p[pPr/numPr]" mode="restOfList">
	<xsl:variable name="styleName" select="./pPr/pStyle/@val"/>
        <xsl:variable name="parentLevel" select="preceding-sibling::p[1]/pPr/numPr/ilvl/@val"/>

        <xsl:variable name="theLevel" select="./pPr/numPr/ilvl/@val"/>
        <xsl:variable name="theNumId" select="./pPr/numPr/numId/@val"/>
	<xsl:variable name="abstractNumber" select="document($numberingFile)//w:num[@w:numId=$theNumId]/w:abstractNumId/@w:val"/>
	<xsl:variable name="indentType" select="document($numberingFile)//w:abstractNum[@w:abstractNumId=$abstractNumber]/w:lvl[@w:ilvl=$theLevel]/w:numFmt/@w:val"/>

        <xsl:variable name="listType">
            <xsl:choose>
                <xsl:when test="contains($indentType,'bullet')">ul</xsl:when>
		<xsl:otherwise>ol</xsl:otherwise>
            </xsl:choose>
	</xsl:variable>

        <xsl:element name="{$listType}">
            <xsl:for-each select='. | following::p[
            descendant::pPr/numPr/numId/@val = $theNumId
            and descendant::pPr/numPr/ilvl/@val = $theLevel
            and preceding-sibling::p[pPr/numPr/ilvl/@val = $parentLevel and pPr/numPr/numId/@val = $theNumId ]
            and following-sibling::p[pPr/numPr/ilvl/@val = $parentLevel and pPr/numPr/numId/@val = $theNumId ]
            ]'>


                <xsl:choose>
                    <xsl:when test='(following-sibling::p[1]/pPr/numPr/ilvl/@val) > $theLevel'>
                        <li>
                        <xsl:apply-templates />
                        <xsl:apply-templates select="following-sibling::p[1]" mode='restOfList' />
                        </li>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:variable name="bookmarkId" select="descendant::bookmarkStart/@name"/>
                        <xsl:element name="li">
                            <xsl:if test="$bookmarkId">
                                <xsl:attribute name="id">
                                    <xsl:value-of select="$bookmarkId" />
                                </xsl:attribute>
                            </xsl:if>
                                <xsl:apply-templates />
                        </xsl:element>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:for-each>
        </xsl:element>

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
    <w:hyperlink r:id="rId4" w:history="true">
        <w:r>
            <w:rPr>
                <w:rStyle w:val="Hyperlink"/>
            </w:rPr>
            <w:t>hyperlink</w:t>
        </w:r>
    </w:hyperlink>
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
                <xsl:otherwise><xsl:value-of select="./r/t"/></xsl:otherwise>
            </xsl:choose>
          </xsl:attribute>

          <xsl:value-of select="./r/t"/>
    </xsl:element>
  </xsl:template>

<!--
    <w:tbl>
        <w:tblPr>
            <w:tblStyle w:val="TableGrid"/>
            <w:tblW w:type="auto" w:w="0"/>
            <w:tblLook w:val="04A0"/>
        </w:tblPr>
        <w:tblGrid>
            <w:gridCol w:w="3561"/>
            <w:gridCol w:w="3561"/>
            <w:gridCol w:w="3561"/>
        </w:tblGrid>
        <w:tr>
            <w:tc>
                <w:tcPr>

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
        <footnote><xsl:value-of select="$fi//w:footnote[@w:id=$referenceId]/w:p//w:r//w:t"/></footnote>
    </xsl:if>
</xsl:template>

</xsl:stylesheet>