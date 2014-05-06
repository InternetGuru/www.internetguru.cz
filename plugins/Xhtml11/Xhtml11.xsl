<?xml version="1.0" encoding="utf-8"?>

<xsl:stylesheet version="1.0"
xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="body">
<body>
  <h1><xsl:value-of select="h"/></h1>
  <p class="description"><xsl:value-of select="description"/></p>
</body>
</xsl:template>

</xsl:stylesheet>